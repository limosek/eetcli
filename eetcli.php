#!/usr/bin/env php
<?php
/*
 * Copyright (C) 2017 Lukas Macura <lukas@macura.cz>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/*
 * Nastav zonu na tu spravnou. Moc nepocitam ze se bude eetcli pouzivat jinde
 * a ve vetsine setupu je default timezone spatne..
 */
ini_set("date.timezone", "Europe/Prague");
error_reporting(0);

require_once(__DIR__ . "/vendor/autoload.php");
require_once(__DIR__ . "/inc/Console.class.php");
require_once(__DIR__ . "/inc/Config.class.php");
require_once(__DIR__ . "/inc/Util.class.php");
require_once(__DIR__ . "/inc/EETFile.class.php");

/*
 * EETCli classes
 */

use Eetcli\Console;
use Eetcli\Config;
use Eetcli\Util;
use Eetcli\EETFile;

/*
 * Vendor classes
 */
use Ondrejnov\EET\Dispatcher;
use Ondrejnov\EET\FileCertificate;
use Ondrejnov\EET\Receipt;
use Ondrejnov\EET\Utils\UUID;
use Ondrejnov\EET\Exceptions\ServerException;
use Ondrejnov\EET\Exceptions\ClientException;

/*
 * If temp directory is not set, assume to use local directory
 * If you want to modify it, be aware that it should be secure location!
 */
if (!getenv("TMP")) {
    putenv("TMP=" . dirname($argv[0]));
}

/*
 * You can use EETCLI_DEBUG  env var too to force debuging.
 */
if (getenv("EETCLI_DEBUG")) {
    $dbg = getenv("EETCLI_DEBUG");
} else {
    $dbg = 2;
    error_reporting(255);
}

Console::init($dbg);
Config::init();
Config::setUsage("eetcli [--options]\n"
        . "Seznam dostupnych maker na vystupu v poli format:\n"
        . "{fik} - fik kod\n"
        . "{bkp} - bkp kod\n"
        . "{pkp} - pkp kod\n"
        . "a vetsina ostatnich parametru etrzby dle specifikace"
        . "\n"
        . "Seznam promennych prostredi, ktere je mozno pouzit:\n"
        . "TMP - adresar pro docasne soubory\n"
        . "EETCLI_DEBUG - debug level (0-4)\n"
        . "\n");

Config::addOpt(null, "key", Config::C_REQUIRED, "Certificate private key (pem format)", __DIR__ . "/keys/EET_CA1_Playground-CZ1212121218.pem");
Config::addOpt(null, "crt", Config::C_REQUIRED, "Certificate public key (pem format)", __DIR__ . "/keys/EET_CA1_Playground-CZ1212121218.crt");
Config::addOpt("n", "overovaci", Config::C_OPTIONAL, "Overovaci rezim", 0);
Config::addOpt("p", "neprodukcni", Config::C_OPTIONAL, "Neprodukcni rezim", 0);
Config::addOpt(null, "uuid", Config::C_REQUIRED, "UUID");
Config::addOpt(null, "dic", Config::C_REQUIRED, "Certificate public key (pem format)");
Config::addOpt(null, "provozovna", Config::C_REQUIRED, "ID provozovny", 1);
Config::addOpt(null, "pokladna", Config::C_REQUIRED, "ID pokladny", 1);
Config::addOpt(null, "pc", Config::C_REQUIRED, "Poradove cislo", 1);
$dte = New \DateTime(false, New \DateTimeZone("Europe/Prague"));
Config::addOpt(null, "cas", Config::C_REQUIRED, "Cas a datum (yyyy-mm-dd hh:mm::ss)", $dte->format(DateTime::RFC3339));
Config::addOpt(null, "trzba", Config::C_REQUIRED, "Trzba v Kc");
Config::addOpt(null, "format", Config::C_REQUIRED, "Vystupni format. Vychozi je {fik}. Muze byt napr. {fik},{bkp},{pkp}", "{fik}\n");
Config::addOpt("C", "create-eet", Config::C_OPTIONAL, "Vytvor EET soubor z parametru aplikace a posli na serveer.", false);
Config::addOpt("S", "send-eet", Config::C_OPTIONAL, "Nacti EET soubor a pokud jeste nebyl zaslan, posli na seerver. Nasledne uloz pod stejnym jmenem.", false);
Config::addOpt("P", "print-eet", Config::C_OPTIONAL, "Nacti EET soubor, otestuj jeho stav a pouze vypis informace podle format.", false);
Config::addOpt("T", "test-eet", Config::C_OPTIONAL, "Otestuj EET soubor a vrat stav.", false);

Config::read(Array("global", "firma", "cert", "eet"));
Util::init();

/*
 * Kdyz format zacina @, nacti ze souboru
 */
if (preg_match("/^@/", Config::getOpt("format"))) {
    $f = substr(Config::getOpt("format"), 1);
    $format = file_get_contents($f);
    if (!$format) {
        Console::error(Util::E_FILE, "Cannot open $f.\n");
    }
    Config::setOpt("format", $format);
}

/*
 * Rezim nacteni uctenky ze souboru a odeslani na server
 */
if (Config::getOpt("send-eet")) {
    try {
        $eet = New EETFile(Config::getOpt("send-eet"), EETFile::MODE_RW);
    } catch (Exception $e) {
        Console::error($e->getCode(), $e->getMessage() . "\n");
    }
    $dispatcher = Util::initDispatcher($eet->playground, $eet->overovaci);
    $r = $eet->toReceipt();
    if ($eet->status != 2) {
        try {
            $fik = $dispatcher->send($r, $eet->overovaci);
            $codes = Util::getCheckCodes($dispatcher, $r, $eet->playground, $eet->overovaci);
            $bkp = $codes["bkp"];
            $pkp = $codes["pkp"];
            $r->dat_odesl = New \DateTime();
            $r->fik = $fik;
            $r->bkp = $bkp;
            $r->pkp = $pkp;
            $eet->save();
            Console::out(Util::expandMacros(Config::getOpt("format"), $r));
        } catch (Exception $e) {
            Console::error($e->getCode(), $e->getMessage() . "\n");
        }
    } else {
        Console::error(Util::E_ALREADYSENT, "Tato uctenka jiz byla odeslana.\n");
    }
 /*
 * Rezim vypisu uctenky
 */
} elseif (Config::getOpt("print-eet")) {
    try {
        $eet = New EETFile(Config::getOpt("print-eet"), EETFile::MODE_R);
    } catch (Exception $e) {
        Console::error($e->getCode(), $e->getMessage() . "\n");
    }
    $r = $eet->toReceipt();
    $r->dat_odesl = New \DateTime();
    $r->fik = $eet->items["fik"];
    $r->bkp = $eet->items["bkp"];
    $r->pkp = $eet->items["pkp"];
    Console::out(Util::expandMacros(Config::getOpt("format"), $r));
 /*
 * Rezim testovani uctenky
 */
} elseif (Config::getOpt("test-eet")) {
    try {
        $eet = New EETFile(Config::getOpt("test-eet"), EETFile::MODE_R);
        $r = $eet->toReceipt();
    } catch (Exception $e) {
        Console::error($e->getCode(), $e->getMessage() . "\n");
    }
    switch ($eet->status) {
        case 0:
            Console::out("Nova\n");
            $exit = Util::E_NEW;
            break;
        case 1:
            Console::out("Neodeslana\n");
            $exit = Util::E_ALREADYSENT;
            break;
        case 2:
            Console::out("Odeslana\n");
            $exit = Util::E_SENT;
            break;
    }
    Console::error($eet->lasterrorcode, $eet->lasterror);
 /*
 * Rezim vytvareni uctenky
 */
} else {
    if (!Config::getOpt("trzba")) {
        Config::helpOpts();
        Console::error(Util::E_PARAMS, "Chybi udaj o trzbe.\n");
    }
    $dispatcher = Util::initDispatcher(Config::getOpt("neprodukcni"), Config::getOpt("overovaci"));
    $r = new Receipt();
    if (Config::getOpt("uuid")) {
        $uuid = Config::getOpt("uuid");
    } else {
        $uuid = UUID::v4();
    }
    $r->uuid_zpravy = $uuid;
    $r->dic_popl = Config::getOpt("dic");
    if (Config::getOpt("provozovna")) {
        $r->id_provoz = Config::getOpt("provozovna");
    } else {
        $r->id_provoz = 1;
    }
    if (Config::getOpt("pokladna")) {
        $r->id_pokl = Config::getOpt("pokladna");
    } else {
        $r->id_pokl = 1;
    }
    if (Config::getOpt("pc")) {
        $r->porad_cis = Config::getOpt("pc");
    } else {
        $r->porad_cis = 1;
    }
    $r->dat_trzby = New \DateTime(Config::getOpt("cas"));
    $r->celk_trzba = Config::getOpt("trzba");
    try {
        if (Config::getOpt("create-eet")) {
            $eet = New EETFile(Config::getOpt("create-eet"), EETFile::MODE_W, Config::getOpt("neprodukcni"), Config::getOpt("overovaci"));
        }
        $fik = $dispatcher->send($r, Config::getOpt("overovaci"));
        $codes = Util::getCheckCodes($dispatcher, $r, Config::getOpt("neprodukcni"), Config::getOpt("overovaci"));
        $bkp = $codes["bkp"];
        $pkp = $codes["pkp"];
        $r->dat_odesl = New \DateTime();
        $r->fik = $fik;
        $r->bkp = $bkp;
        $r->pkp = $pkp;
        if (Config::getOpt("create-eet")) {
            $eet->fromReceipt($r);
            if (Config::getOpt("overovaci")) {
                $eet->setStatus(1);
                $eet->setErrorCode(0);
                $eet->setError("Overovaci");
            } else {
                $eet->setStatus(2);
            }
            $eet->save();
        }
        Console::out(Util::expandMacros(Config::getOpt("format"), $r));
    } catch (Exception $e) {
        if (Config::getOpt("create-eet")) {
            try {
                $eet = New EETFile(Config::getOpt("create-eet"), EETFile::MODE_W, Config::getOpt("neprodukcni"), Config::getOpt("overovaci"));
                $eet->fromReceipt($r);
                $eet->setStatus(1);
                $eet->setErrorCode($e->getCode());
                $eet->setError($e->getMessage());
                $eet->save();
            } catch (Exception $e2) {
                Console::error($e2->getCode(), $e2->getMessage() . "\n");
            }
        }
        Console::error($e->getCode(), $e->getMessage() . "\n");
    }
}

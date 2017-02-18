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
}

Console::init($dbg);
Config::init();
Config::setUsage("eetcli [--options]\n"
        . "Seznam dostupnych maker na vystupu v poli format:\n"
        . "{FIK} - fik kod\n"
        . "{BKP} - bkp kod\n"
        . "{PKP} - pkp kod\n"
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
Config::addOpt(null, "format", Config::C_REQUIRED, "Vystupni format. Vychozi je {FIK}. Muze byt napr. {FIK},{BPK},{PKP}", "{FIK}\n");
Config::addOpt("W", "write-eet", Config::C_OPTIONAL, "Zapis EET file", false);
Config::addOpt("R", "read-eet", Config::C_OPTIONAL, "Nacti EET file", false);
Config::addOpt("P", "process-eet", Config::C_OPTIONAL, "Nacti EET file, zpracuj a zapis", false);

Config::read(Array("global", "firma", "cert", "eet"));
Util::init();

if (Config::getOpt("read-eet")) {
    $eet = New EETFile(Config::getOpt("read-eet"), EETFile::MODE_R);
    $dispatcher = Util::initDispatcher($eet->playground, $eet->overovaci);
    $r = $eet->toReceipt();
    print_r($r);exit;
    try {
        $fik = $dispatcher->send($r,  $eet->overovaci);
        $codes = Util::getCheckCodes($dispatcher, $r, $eet->playground, $eet->overovaci);
        $bkp=$codes["bkp"];
        $pkp=$codes["pkp"];
        $out = stripcslashes(preg_replace(
                Array("/{FIK}/", "/{BKP}/", "/{PKP}/"), Array($fik, $bkp, $pkp), Config::getOpt("format")));
        Console::out($out);
    } catch (Exception $e) {
        Console::error($e->getCode(), $e->getMessage() . "\n");
    }
} elseif (Config::getOpt("process-eet")) {
    
} else {
    if (!Config::getOpt("trzba")) {
        Config::helpOpts();
        Console::error(3, "Chybi udaj o trzbe.\n");
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
        $fik = $dispatcher->send($r,  Config::getOpt("overovaci"));
        $codes = Util::getCheckCodes($dispatcher, $r, Config::getOpt("neprodukcni"), Config::getOpt("overovaci"));
        $bkp=$codes["bkp"];
        $pkp=$codes["pkp"];
        if (Config::getOpt("write-eet")) {
            $eet = New EETFile(Config::getOpt("write-eet"), EETFile::MODE_W,Config::getOpt("neprodukcni"), Config::getOpt("overovaci"));
            $r->dat_odesl = New \DateTime();
            $r->fik = $fik;
            $r->bkp = $bkp;
            $r->pkp = $pkp;
            $eet->fromReceipt($r);
            $eet->save();
        }
        $out = stripcslashes(preg_replace(
                Array("/{FIK}/", "/{BKP}/", "/{PKP}/"), Array($fik, $bkp, $pkp), Config::getOpt("format")));
        Console::out($out);
    } catch (Exception $e) {
        Console::error($e->getCode(), $e->getMessage() . "\n");
    }
}

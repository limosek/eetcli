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
    if ($dbg > 3) {
        error_reporting(255);
    }
} else {
    $dbg = 2;
}

Console::init($dbg);
Config::init();
Config::setUsage("eetcli [--options]" . PHP_EOL
        . "Opensource klient pro etrzby.cz licencovany pod GPL3" . PHP_EOL
        . "Vice informaci na http://github.com/limosek/eetcli/" . PHP_EOL . PHP_EOL
        . "Seznam dostupnych maker na vystupu v poli format:" . PHP_EOL
        . "Poznamka: format muze zacinat znakem '@' coz znamena, ze bude nacten ze souboru, ne z parametru. Napr. @soubor.txt." . PHP_EOL
        . " {fik}\t\t fik kod" . PHP_EOL
        . " {bkp}\t\t bkp kod" . PHP_EOL
        . " {pkp}\t\t pkp kod" . PHP_EOL
        . "   a dalsi: " . join(",", Util::getMacros()) . PHP_EOL . PHP_EOL
        . "Seznam promennych prostredi, ktere je mozno pouzit:" . PHP_EOL
        . " TMP\t\t adresar pro docasne soubory" . PHP_EOL
        . " EETCLI_INI\t ini soubor k nacteni (jinak postupne: " . join(",", array(__DIR__ . "/../eetcli.ini",
            getenv("HOME") . "/eetcli.ini",
            getenv("HOME") . "/.eetclirc",
            "/etc/eetcli.ini",
            __DIR__ . "/../eetcli.ini.dist"))
        . PHP_EOL
        . " EETCLI_DEBUG\t debug level (0-4)" . PHP_EOL . PHP_EOL
        . "Mody pouziti:" . PHP_EOL
        . "-N file.eet\t\t Vytvor EET soubor z parametru a nikam nezasilej. Je mozno pouzit i makra v nazvu souboru, napr. {uuid_zpravy}" . PHP_EOL . PHP_EOL
        . "-C file.eet\t\t Vytvor EET soubor z parametru a zaroven zasli na etrzby. Je mozno pouzit i makra v nazvu souboru, napr. {uuid_zpravy}" . PHP_EOL . PHP_EOL
        . "-S file.eet\t\t Nacti EET soubor a pokud jeste nebyl zaslan, posli na etrzby. Nasledne uloz pod stejnym jmenem. V pripade, ze uz byl dany eet soubor zaslan drive, vrati se chyba." . PHP_EOL . PHP_EOL
        . "-P file.eet\t\t Nacti EET soubor, otestuj jeho stav a pouze vypis informace podle format. Pokud nesedi kontrolni soucty, vrat chybu." . PHP_EOL . PHP_EOL
        . "-T file.eet\t\t Nacti EET soubor, otestuj jeho stav a pouze vrat chybove hlaseni a navratovy kod podle stavu souboru." . PHP_EOL . PHP_EOL
        . "nebo vubec nepouzit EET soubor a pouze odeslat trzbu (--dic, --trzba, --uuid, ...)" . PHP_EOL
        . PHP_EOL
        . "Navratove kody:" . PHP_EOL
        . "1\t Docasna technicka chyba zpracovani - odeslete prosim datovou zpravu pozdeji" . PHP_EOL
        . "2\t Kodovani XML neni platne" . PHP_EOL
        . "3\t XML zprava nevyhovela kontrole XML schematu" . PHP_EOL
        . "4\t Neplatny podpis SOAP zpravy" . PHP_EOL
        . "5\t Neplatny kontrolni bezpecnostni kod poplatnika (BKP)" . PHP_EOL
        . "6\t DIC poplatnika ma chybnou strukturu" . PHP_EOL
        . "7\t Datova zprava je prilis velka" . PHP_EOL
        . "8\t Datova zprava nebyla zpracovana kvuli technicke chybe nebo chybe dat" . PHP_EOL
        . "20\t Chyba pri praci se souborem" . PHP_EOL
        . "21\t Uctenka jiz byla zaslana" . PHP_EOL
        . "22\t Chyba v parametrech" . PHP_EOL
        . "23\t Chyba ve formatu souboru" . PHP_EOL
        . "24\t Chyba pri uzamykani souboru" . PHP_EOL
        . "25\t Uctenka je nova (vysledek testu)" . PHP_EOL
        . "26\t Uctenka jiz byla zaslana (vysledek testu)" . PHP_EOL
        . "27\t Kontrolni soucty v EET souboru nesedi" . PHP_EOL
        . "29\t EET uctenka neodeslana kvuli chybe" . PHP_EOL
        . "30\t EET soubor odeslan v overovacim rezimu" . PHP_EOL
        . PHP_EOL);

Config::addOpt(null, "key", Config::C_REQUIRED, "Certificate private key (pem format)", __DIR__ . "/keys/EET_CA1_Playground-CZ1212121218.pem");
Config::addOpt(null, "crt", Config::C_REQUIRED, "Certificate public key (pem format)", __DIR__ . "/keys/EET_CA1_Playground-CZ1212121218.crt");
Config::addOpt("n", "overovaci", Config::C_OPTIONAL, "Overovaci rezim", 0);
Config::addOpt("p", "neprodukcni", Config::C_OPTIONAL, "Neprodukcni rezim", 1);
Config::addOpt(null, "uuid", Config::C_REQUIRED, "UUID");
Config::addOpt(null, "dic", Config::C_REQUIRED, "DIC", "CZ1212121218");
Config::addOpt(null, "provozovna", Config::C_REQUIRED, "ID provozovny", 1);
Config::addOpt(null, "pokladna", Config::C_REQUIRED, "ID pokladny", 1);
Config::addOpt(null, "pc", Config::C_REQUIRED, "Poradove cislo", 1);
$dte = New \DateTime(false, New \DateTimeZone("Europe/Prague"));
Config::addOpt(null, "cas", Config::C_REQUIRED, "Cas a datum (yyyy-mm-dd hh:mm::ss)", $dte->format(DateTime::RFC3339));
Config::addOpt(null, "trzba", Config::C_REQUIRED, "Trzba v Kc");
Config::addOpt(null, "format", Config::C_REQUIRED, "Vystupni format. Muze byt napr. {fik},{bkp},{pkp} nebo jine makro. Viz seznam maker.", "{fik}" . PHP_EOL);
Config::addOpt("N", "create-eet", Config::C_OPTIONAL, "Vytvor EET soubor z parametru a nikam nezasilej. Je mozno pouzit i makra v nazvu souboru, napr. {uuid_zpravy}", false);
Config::addOpt("C", "create-send-eet", Config::C_OPTIONAL, "Vytvor EET soubor z parametru a zaroven zasli na etrzby. Je mozno pouzit i makra v nazvu souboru, napr. {uuid_zpravy}", false);
Config::addOpt("S", "send-eet", Config::C_OPTIONAL, "Nacti EET soubor a pokud jeste nebyl zaslan, posli na etrzby. Nasledne uloz pod stejnym jmenem.", false);
Config::addOpt("P", "print-eet", Config::C_OPTIONAL, "Nacti EET soubor, otestuj jeho stav a pouze vypis informace podle format.", false);
Config::addOpt("T", "test-eet", Config::C_OPTIONAL, "Otestuj EET soubor a vrat stav.", false);

Config::read(Array("global", "firma", "cert", "eet"));
Util::init();

/*
 * 
 * Rezim nacteni uctenky ze souboru a odeslani na server
 * 
 */
if (Config::getOpt("send-eet")) {
    Config::read(false, false, true);
    Config::notAllowedOpts("send-eet", Array(
        "N" => "create-eet",
        "C" => "create-send-eet",
        "P" => "print-eet",
        "T" => "test-eet",
        "n" => "overovaci",
        "p" => "neprodukcni",
        "3" => "dic",
        "4" => "provozovna",
        "5" => "pokladna",
        "6" => "pc",
        "7" => "cas",
        "8" => "trzba"
    ));
    try {
        $eet = New EETFile(Config::getOpt("send-eet"), EETFile::MODE_RW);
    } catch (Exception $e) {
        Console::error($e->getCode(), $e->getMessage() . PHP_EOL);
    }
    Console::debug("Posilam EET soubor na etrzby: " . Config::getOpt("send-eet") . PHP_EOL);
    $dispatcher = Util::initDispatcher($eet->playground, $eet->overovaci);
    $r = $eet->toReceipt($dispatcher);
    if ($eet->status != EETFile::STATUS_SENT) {
        try {
            $fik = $dispatcher->send($r, $eet->overovaci);
            $codes = Util::getCheckCodes($dispatcher, $r, $eet->playground, $eet->overovaci);
            $bkp = $codes["bkp"];
            $pkp = $codes["pkp"];
            $r->dat_odesl = New \DateTime();
            $r->fik = $fik;
            $r->bkp = $bkp;
            $r->pkp = $pkp;
            $eet->fromReceipt($r);
            $eet->setStatus(EETFile::STATUS_SENT);
            $eet->save();
            Console::out(Util::expandMacros(Config::getOpt("format"), $r));
        } catch (Exception $e) {
            $codes = Util::getCheckCodes($dispatcher, $r, $eet->playground, $eet->overovaci);
            $bkp = $codes["bkp"];
            $pkp = $codes["pkp"];
            $r->dat_odesl = New \DateTime();
            $r->bkp = $bkp;
            $r->pkp = $pkp;
            $eet->fromReceipt($r);
            $eet->setStatus(EETFile::STATUS_ERR);
            $eet->setErrorCode($e->getCode());
            $eet->setError($e->getMessage());
            $eet->save();
            Console::error(Util::eetCodeToError($e->getCode()), $e->getMessage() . PHP_EOL);
        }
    } else {
        $eet->unlock();
        Console::error(Util::E_ALREADYSENT, "Tato uctenka jiz byla odeslana.\n");
    }
    /*
     * 
     * Rezim vypisu uctenky
     * 
     */
} elseif (Config::getOpt("print-eet")) {
    Config::read(false, false, true);
    Config::notAllowedOpts("print-eet", Array(
        "N" => "create-eet",
        "C" => "create-send-eet",
        "P" => "send-eet",
        "T" => "test-eet",
        "n" => "overovaci",
        "p" => "neprodukcni",
        "3" => "dic",
        "4" => "provozovna",
        "5" => "pokladna",
        "6" => "pc",
        "7" => "cas",
        "8" => "trzba"
    ));
    try {
        $eet = New EETFile(Config::getOpt("print-eet"), EETFile::MODE_R);
    } catch (Exception $e) {
        Console::error($e->getCode(), $e->getMessage() . "\n");
    }
    Console::debug("Vypisuji EET soubor: " . Config::getOpt("print-eet") . PHP_EOL);
    $dispatcher = Util::initDispatcher($eet->playground, $eet->overovaci);
    $r = $eet->toReceipt($dispatcher);
    $r->dat_odesl = New \DateTime();
    $r->fik = $eet->items["fik"];
    $r->bkp = $eet->items["bkp"];
    $r->pkp = $eet->items["pkp"];
    Console::out(Util::expandMacros(Config::getOpt("format"), $r));
    /*
     * 
     * Rezim testovani uctenky
     * 
     */
} elseif (Config::getOpt("test-eet")) {
    Config::read(false, false, true);
    Config::notAllowedOpts("test-eet", Array(
        "N" => "create-eet",
        "C" => "create-send-eet",
        "P" => "send-eet",
        "T" => "print-eet",
        "n" => "overovaci",
        "p" => "neprodukcni",
        "3" => "dic",
        "4" => "provozovna",
        "5" => "pokladna",
        "6" => "pc",
        "7" => "cas",
        "8" => "trzba"
    ));
    Console::debug("Testuji EET soubor: " . Config::getOpt("test-eet") . PHP_EOL);
    try {
        $eet = New EETFile(Config::getOpt("test-eet"), EETFile::MODE_R);
        $dispatcher = Util::initDispatcher($eet->playground, $eet->overovaci);
        $r = $eet->toReceipt($dispatcher);
    } catch (Exception $e) {
        Console::error($e->getCode(), $e->getMessage() . "\n");
    }
    switch ($eet->status) {
        case EETFile::STATUS_NEW:
            Console::out("Nova\n");
            $status = Util::E_NEW;
            break;
        case EETFile::STATUS_SENT:
            Console::out("Odeslana\n");
            $status = Util::E_ALREADYSENT;
            break;
        case EETFile::STATUS_ERR:
            Console::out("Neodeslana (chyba)\n");
            $status = Util::E_SENTERR;
            break;
        case EETFile::STATUS_OVEROVACI:
            Console::out("Odeslana pouze v overovacim rezimu\n");
            $status = Util::E_SENTOVEROVACI;
            break;
    }
    if ($status == 2) {
        Console::error($eet->lasterrorcode, $eet->lasterror);
    } else {
        Console::error($status, "");
    }
    /*
     * 
     * Rezim vytvareni EET souboru
     * 
     */
} elseif (Config::getOpt("create-eet")) {
    Config::requiredOpts("create-eet", Array(
        "3" => "dic",
        "4" => "provozovna",
        "5" => "pokladna",
        "6" => "pc",
        "7" => "cas",
        "8" => "trzba"
    ));
    Config::notAllowedOpts("create-eet", Array(
        "N" => "create-send-eet",
        "P" => "send-eet",
        "T" => "test-eet"
    ));
    $dispatcher = Util::initDispatcher(Config::getOpt("neprodukcni"), Config::getOpt("overovaci"));
    $r = Util::receiptFromParams();
    $file = Util::expandMacros(Config::getOpt("create-eet"), $r);
    Console::debug("Vytvarim EET soubor: $file" . PHP_EOL);
    try {
        $eet = New EETFile($file, EETFile::MODE_W, Config::getOpt("neprodukcni"), Config::getOpt("overovaci"));
        $eet->fromReceipt($r);
        $eet->setStatus(EETFile::STATUS_NEW);
    } catch (Exception $e) {
        Console::error(Util::eetCodeToError($e->getCode()), $e->getMessage() . PHP_EOL);
    }
    Console::out(Util::expandMacros(Config::getOpt("format"), $r));
    $eet->save();
    /*
     * 
     * Rezim zasilani na etrzby a pripadneho vytvareni EET souboru
     * 
     */
} else {
    Config::requiredOpts("create-send-eet", Array(
        "3" => "dic",
        "4" => "provozovna",
        "5" => "pokladna",
        "6" => "pc",
        "7" => "cas",
        "8" => "trzba"
    ));
    Config::notAllowedOpts("create-send-eet", Array(
        "N" => "create-eet",
        "P" => "send-eet",
        "T" => "test-eet"
    ));
    /*
     * V pripade, ze nejsou zadany zadne volby a klient je ve vychozim rezimu, vypis zakladni uctenku
     */
    if (preg_match("#CZ1212121218#", Config::getOpt("key")) && Config::isDefaultOpt("dic") && Config::isDefaultOpt("format")
    ) {
        Config::setOpt("format", file_get_contents(__DIR__ . "/doc/uctenka.txt"));
        Console::warning("Vracim vychozi uctenku, protoze je pouzity testovaci DIC.\n");
    }
    $dispatcher = Util::initDispatcher(Config::getOpt("neprodukcni"), Config::getOpt("overovaci"));
    $r = Util::receiptFromParams();
    $file = Util::expandMacros(Config::getOpt("create-send-eet"), $r);
    try {
        if ($file) {
            $eet = New EETFile($file, EETFile::MODE_W, Config::getOpt("neprodukcni"), Config::getOpt("overovaci"));
            Console::debug("Vytvarim EET soubor a zasilam na etrzby: $file" . PHP_EOL);
        } else {
            Console::debug("Zasilam uctenku na etrzby: $file" . PHP_EOL);
        }
    } catch (Exception $e) {
        Console::error($e->getCode(), $e->getMessage() . PHP_EOL);
    }
    try {
        $fik = $dispatcher->send($r, Config::getOpt("overovaci"));
    } catch (Exception $e) {
        if ($file) {
            $codes = Util::getCheckCodes($dispatcher, $r, Config::getOpt("neprodukcni"), Config::getOpt("overovaci"));
            $bkp = $codes["bkp"];
            $pkp = $codes["pkp"];
            $r->prvni_zaslani = 0;
            $r->bkp = $bkp;
            $r->pkp = $pkp;
            $eet->fromReceipt($r);
            $eet->setStatus(EETFile::STATUS_ERR);
            $eet->setErrorCode($e->getCode());
            $eet->setError($e->getMessage());
            $eet->save();
        }
        Console::error($e->getCode(), $e->getMessage() . PHP_EOL);
    }
    $codes = Util::getCheckCodes($dispatcher, $r, Config::getOpt("neprodukcni"), Config::getOpt("overovaci"));
    $bkp = $codes["bkp"];
    $pkp = $codes["pkp"];
    $r->dat_odesl = New \DateTime();
    $r->fik = $fik;
    $r->bkp = $bkp;
    $r->pkp = $pkp;
    if ($file) {
        $eet->fromReceipt($r);
        if (Config::getOpt("overovaci")) {
            $eet->setStatus(EETFile::STATUS_OVEROVACI);
            $eet->setErrorCode(0);
            $eet->setError("Overovaci");
        } else {
            $eet->setStatus(EETFile::STATUS_SENT);
        }
        $eet->save();
    }
    Console::out(Util::expandMacros(Config::getOpt("format"), $r));
}

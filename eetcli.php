#!/usr/bin/env php
<?php

ini_set("date.timezone","Europe/Prague");

require_once(__DIR__ . "/vendor/autoload.php");
require_once(__DIR__ . "/inc/Console.class.php");
require_once(__DIR__ . "/inc/Config.class.php");

/*
 * EETCli classes
 */
use Eetcli\Console;
use Eetcli\Config;

/*
 * Vendor classes
 */
use Ondrejnov\EET\Dispatcher;
use Ondrejnov\EET\FileCertificate;
use Ondrejnov\EET\Receipt;
use Ondrejnov\EET\Utils\UUID;
use Ondrejnov\EET\Exceptions\ServerException;
use Ondrejnov\EET\Exceptions\ClientException;

Console::init(100);
Config::init();
Config::setUsage("eetcli [--options]\n"
        ."Seznam dostupnych maker na vystupu:\n"
        ."{FIK} - fik kod\n"        
        ."{BKP} - bkp kod\n"
        ."{PKP} - pkp kod\n"
        ."\n");

Config::addOpt(null, "key", Config::C_REQUIRED, "Certificate private key (pem format)", "./keys/EET_CA1_Playground-CZ1212121218.pem");
Config::addOpt(null, "crt", Config::C_REQUIRED, "Certificate public key (pem format)", "./keys/EET_CA1_Playground-CZ1212121218.crt");
Config::addOpt("n", "overovaci", Config::C_OPTIONAL, "Overovaci rezim",0);
Config::addOpt("p", "neprodukcni", Config::C_OPTIONAL, "Neprodukcni rezim",0);
Config::addOpt(null, "uuid", Config::C_REQUIRED, "UUID");
Config::addOpt(null, "dic", Config::C_REQUIRED, "Certificate public key (pem format)");
Config::addOpt(null, "provozovna", Config::C_REQUIRED, "ID provozovny", 1);
Config::addOpt(null, "pokladna", Config::C_REQUIRED, "ID pokladny", 1);
Config::addOpt(null, "pc", Config::C_REQUIRED, "Poradove cislo", 1);
$dte = New \DateTime(false, New \DateTimeZone("Europe/Prague"));
Config::addOpt(null, "cas", Config::C_REQUIRED, "Cas a datum (yyyy-mm-dd hh:mm::ss)", $dte->format(DateTime::RFC3339));
Config::addOpt(null, "trzba", Config::C_REQUIRED, "Trzba v Kc");
Config::addOpt(null, "format", Config::C_REQUIRED, "Vystupni format. Vychozi je {FIK}. Muze byt napr. {FIK},{BPK},{PKP}", "{FIK}\n");

Config::read(Array("global","firma","cert"));

if (!Config::getOpt("trzba")) {
    Config::helpOpts();
    Console::error(3,"Chybi udaj o trzbe.\n");
}

$tmpfiles=Array();
function tmpclean() {
    global $tmpfiles;
    foreach ($tmpfiles as $f) {
        unlink($f);
    }
}

function file_from_phar($src) {
    global $tmpfiles;
    if (preg_match("/^aphar\:/", $src)) {
        $f = fopen($src, "r");
        if (!$f) {
            error(ERR_FILE, "Cannot find $src in phar!");
        }
        $data = file_get_contents($src);
        if (!$data) {
            error(ERR_FILE, "Cannot read $src from phar!");
        }
        $tmpf = TMP_DIR . basename($src);
        $t = fopen($tmpf, "w");
        if (!$t) {
            error(ERR_FILE, "Cannot write to $tmpf!");
        }
        if (fwrite($t, $data) != strlen($data)) {
            error(ERR_FILE, "Cannot write to $tmpf!");
        }
        fclose($t);
        $tmpfiles[] = $tmpf;
        return($tmpf);
    } else {
        return($src);
    }
}

file_from_phar(__DIR__ . '/vendor/ondrejnov/eet/src/Schema/EETXMLSchema.xsd');
if (Config::getOpt("neprodukcni")) {
    define('WSDL', file_from_phar(__DIR__ . '/vendor/ondrejnov/eet/src/Schema/PlaygroundService.wsdl'));
} else {
    define('WSDL', file_from_phar(__DIR__ . '/vendor/ondrejnov/eet/src/Schema/ProductionService.wsdl'));
}

Console::debug("WSDL: " . WSDL . "\n");
Console::debug("Key: ".Config::getOpt("key")."\n");
Console::debug("Cert: ".Config::getOpt("crt")."\n");
Console::debug("DIC: ".Config::getOpt("dic")."\n");

try {
    $dispatcher = new Dispatcher(WSDL, Config::getOpt("key"), Config::getOpt("crt"));
} catch (Exception $e) {
    error($e->getCode(), $e->getMessage());
}
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

if (!Config::getOpt("overovaci")) {
    $codes = $dispatcher->getCheckCodes($r);
    $bkp=$codes['bkp'];
    $pkp=$codes['pkp'];
} else {
    $bkp="";
    $pkp="";
}

if (Config::getOpt("overovaci")) {
    $over = "(overovaci)";
} else {
    $over = "";
}
Console::trace("Request $over:\n" . print_r($r, true));

try {
    $fik = $dispatcher->send($r, Config::getOpt("overovaci"));
} catch (Exception $e) {
    Console::error($e->getCode(), $e->getMessage()."\n");
}

$out = stripcslashes(preg_replace(
        Array("{FIK}","{BKP}","{PKP}"),
        Array($fik,$bkp["_"],bin2hex($pkp["_"])),
        Config::getOpt("format")));

Console::out($out);

tmpclean();

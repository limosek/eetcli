<?php

ini_set('display_errors', 1);
define("ROOT_DIR", __DIR__);
define("SRC_DIR", ROOT_DIR . "/vendor/ondrejnov/eet/src");
require_once(ROOT_DIR . "/vendor/autoload.php");

define("ERR_PARAMS",1);
define("ERR_FILE",2);

use Ondrejnov\EET\Dispatcher;
use Ondrejnov\EET\FileCertificate;
use Ondrejnov\EET\Receipt;
use Ondrejnov\EET\Utils\UUID;
use DealNews\Console\Console;

$console = new Console(
        array(
    "copyright" => array(
        "owner" => "Lukas Macura",
        "year" => "2017-" . date("Y")
    ),
    "help" => array(
        "header" => "This is commandline interface for Czech EET (etrzby.cz)"
    )
        ), array(
    "key" => array(
        "description" => "Certificate private key (pem format)",
        "param" => "key",
        "optional" => Console::OPTIONAL
    ),
    "crt" => array(
        "description" => "Certificate public key (pem format)",
        "param" => "crt",
        "optional" => Console::OPTIONAL
    ),
    "p12" => array(
        "description" => "Certificate in PKCS12 format",
        "param" => "p12",
        "optional" => Console::OPTIONAL
    ),
    "keysecret" => array(
        "description" => "Private key password (can be set by env EET_KEYSECRET too)",
        "param" => "secret",
        "optional" => Console::OPTIONAL
    ),
    "n" => array(
        "description" => "Overovaci rezim",
        "optional" => Console::OPTIONAL
    ),
    "uuid" => array(
        "description" => "UUID",
        "param" => "uuid",
        "optional" => Console::OPTIONAL
    ),
    "dic" => array(
        "description" => "DIC",
        "param" => "dic",
        "optional" => Console::OPTIONAL
    ),
    "provozovna" => array(
        "description" => "ID provozovny",
        "param" => "id_provoz",
        "optional" => Console::OPTIONAL
    ),
    "pokladna" => array(
        "description" => "ID pokladny",
        "param" => "id_pokl",
        "optional" => Console::OPTIONAL
    ),
    "pc" => array(
        "description" => "Poradove cislo",
        "param" => "porad_cis",
        "optional" => Console::OPTIONAL
    ),
    "cas" => array(
        "description" => "Datum a cas trzby",
        "param" => "dat_trzby",
        "optional" => Console::OPTIONAL
    ),
    "trzba" => array(
        "description" => "Celkova trzba v Kc",
        "param" => "celk_trzba",
        "optional" => Console::OPTIONAL
    ),
    "timeout" => array(
        "description" => "Timeout v milisekundach",
        "param" => "mS",
        "optional" => Console::OPTIONAL
    ),
    "output" => array(
        "description" => "Zapsat fik do souboru ",
        "param" => "soubor",
        "optional" => Console::OPTIONAL
    )
   )
);

function error($code,$msg) {
    fwrite(STDERR,"Error $code: $msg\n");
    exit($code);
}

function read_config($file) {
    global $console;
    
    $opts=parse_ini_file($file,true);
    if (array_key_exists("global",$opts)) {
        if (array_key_exists("overovaci",$opts["global"])) {
            $console->n=$opts["global"]["overovaci"];
        }
        if (array_key_exists("verbose",$opts["global"])) {
            $console->v=$opts["global"]["verbose"];
        }
        if (array_key_exists("timeout",$opts["global"])) {
            $console->timeout=$opts["global"]["timeout"];
        }
    }
    if (array_key_exists("cert",$opts)) {
        if (array_key_exists("crt",$opts["cert"])) {
            $console->crt=$opts["cert"]["crt"];
        }
        if (array_key_exists("key",$opts["cert"])) {
            $console->key=$opts["cert"]["key"];
        }
        if (array_key_exists("p12",$opts["cert"])) {
            //$console->p12=$opts["cert"]["p12"];
            error(ERR_PARAMS,"P12 Not implemented yet.");
        }
        if (array_key_exists("secret",$opts["cert"])) {
            //$console->keysecret=$opts["cert"]["secret"];
            error(ERR_PARAMS,"Passphrase not implemented yet.");
        }
    }
    if (array_key_exists("firma",$opts)) {
        if (array_key_exists("dic",$opts["firma"])) {
            $console->dic=$opts["firma"]["dic"];
        }
        if (array_key_exists("pokladna",$opts["firma"])) {
            $console->pokladna=$opts["firma"]["pokladna"];
        }
        if (array_key_exists("provozovna",$opts["firma"])) {
            $console->provozovna=$opts["firma"]["provozovna"];
        }
    }
}

function check_options() {
    global $console;
    
    if (!$console->dic) {
        error(ERR_PARAMS,"DIC musi byt nastaveno!");
    }
    if (!$console->key) {
        error(ERR_PARAMS,"Cesta ke klici musi byt nastavena!");
    } else {
        if (!file_exists($console->key)) {
            error(ERR_FILE,"Nemohu nacist soubor klice " . $console->key . "!");
        }
    }
    if (!$console->crt) {
        error(ERR_PARAMS,"Cesta k certifikatu musi byt nastavena!");
    } else {
        if (!file_exists($console->key)) {
            error(ERR_FILE,"Nemohu nacist soubor certifikatu " . $console->crt . "!");
        }
    }
    if (!$console->crt) {
        error(ERR_PARAMS,"Cesta k certifikatu musi byt nastavena!");
    } else {
        if (!file_exists($console->key)) {
            error(ERR_FILE,"Nemohu nacist soubor certifikatu " . $console->crt . "!");
        }
    }
    if (!$console->pc) {
        error(ERR_PARAMS,"Poradove cislo musi byt nastaveno!");
    }
}

read_config("eetcli.ini");
$console->run();
check_options();

if ($console->n) {
    define('WSDL', SRC_DIR . '/Schema/PlaygroundService.wsdl');
} else {
    define('WSDL', SRC_DIR . '/Schema/ProductionService.wsdl');
}

$dispatcher = new Dispatcher(WSDL, $console->key, $console->crt);

$r = new Receipt();

if ($console->timeout) {
    $r->initSoapClient();
    $r->getSoapClient->setTimeout($console->timeout);
}
if ($console->uuid) {
    $uuid = $console->uuid;
} else {
    $uuid = UUID::v4();
}
$r->uuid_zpravy = $uuid;
$r->dic_popl = $console->dic;
$r->id_provoz = $console->provozovna;
$r->id_pokl = $console->pokladna;
if ($console->pc) {
    $r->porad_cis = $console->pc;
}
if ($console->cas) {
    $r->dat_trzby = new \DateTime($console->cas);
} else {
    $r->dat_trzby = new \DateTime();
}
$r->celk_trzba = $console->trzba;

$fik=$dispatcher->send($r);

if ($console->output) {
    $f=fopen($console->output,"w");
    if (!$f) { 
        error(ERR_FILE,"Nemuzu zapsat vystupni soubor ".$console->output);
    }
    fputs($f,$fik);
    fclose($f);
} else {
    echo $dispatcher->send($r)."\n";
}



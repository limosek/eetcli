<?php

ini_set('display_errors', 0);
ini_set("soap.wsdl_cache_enabled", 0);
error_reporting(0);
require_once(__DIR__ . "/vendor/autoload.php");
if (getenv("EETCLI_TMP")) {
    define("TMP_DIR",__DIR__);
} else {
    define("TMP_DIR",getenv("EETCLI_TMP"));
}

define("ERR_PARAMS",1);
define("ERR_FILE",2);
define("ERR_COM",3);

use Ondrejnov\EET\Dispatcher;
use Ondrejnov\EET\FileCertificate;
use Ondrejnov\EET\Receipt;
use Ondrejnov\EET\Utils\UUID;
use DealNews\Console\Console;

function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    error($errno, $errstr);
}
set_error_handler("exception_error_handler");

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

function setoptifempty($option,$value) {
    global $console;
    
    if (!$console->$option) {
        $console->$option=$value;
    }
}

function read_config($file) {
    global $console;

    $opts = parse_ini_file($file, true);
    if (is_array($opts)) {
        if (array_key_exists("global", $opts)) {
            if (array_key_exists("overovaci", $opts["global"])) {
                setoptifempty("n", $opts["global"]["overovaci"]);
            }
            if (array_key_exists("verbose", $opts["global"])) {
                setoptifempty("v", $opts["global"]["verbose"]);
            }
            if (array_key_exists("timeout", $opts["global"])) {
                setoptifempty("timeout", $opts["global"]["timeout"]);
            }
        }
        if (array_key_exists("cert", $opts)) {
            if (array_key_exists("crt", $opts["cert"])) {
                setoptifempty("crt", $opts["cert"]["crt"]);
            }
            if (array_key_exists("key", $opts["cert"])) {
                setoptifempty("key", $opts["cert"]["key"]);
            }
            if (array_key_exists("p12", $opts["cert"])) {
                //$console->p12=$opts["cert"]["p12"];
                error(ERR_PARAMS, "P12 Not implemented yet.");
            }
            if (array_key_exists("secret", $opts["cert"])) {
                //$console->keysecret=$opts["cert"]["secret"];
                error(ERR_PARAMS, "Passphrase not implemented yet.");
            }
        }
        if (array_key_exists("firma", $opts)) {
            if (array_key_exists("dic", $opts["firma"])) {
                setoptifempty("dic", $opts["firma"]["dic"]);
            }
            if (array_key_exists("pokladna", $opts["firma"])) {
                setoptifempty("pokladna", $opts["firma"]["pokladna"]);
            }
            if (array_key_exists("provozovna", $opts["firma"])) {
                setoptifempty("provozovna", $opts["firma"]["provozovna"]);
            }
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
        //if (!file_exists($console->key)) {
        //    error(ERR_FILE,"Nemohu nacist soubor klice '" . $console->key . "' !");
        //}
    }
    if (!$console->crt) {
        error(ERR_PARAMS,"Cesta k certifikatu musi byt nastavena!");
    } else {
        //if (!file_exists($console->crt)) {
        //    error(ERR_FILE,"Nemohu nacist soubor certifikatu '" . $console->crt . "' !");
        //}
    }
    if (!$console->pc) {
        error(ERR_PARAMS,"Poradove cislo musi byt nastaveno!");
    }
}

function file_from_phar($src) {
    if (preg_match("/^phar\:/", $src)) {
        $f = fopen($src, "r");
        if (!$f) {
            error(ERR_FILE,"Cannot find $src in phar!");
        }
        $data = file_get_contents($src);
        if (!$data) {
            error(ERR_FILE,"Cannot read $src from phar!");
        }
        $tmpf = TMP_DIR . basename($src);
        $t = fopen($tmpf, "w");
        if (!$t) {
            error(ERR_FILE,"Cannot write to $tmpf!");
        }
        if (fwrite($t, $data)!=strlen($data)) {
            error(ERR_FILE,"Cannot write to $tmpf!");
        }
        fclose($t);
        return($tmpf);
    } else {
        return($src);
    }
}

$console->run();
if (getenv("EETCLI_INI")) {
    if (file_exists(getenv("EETCLI_INI"))) {
        read_config(getenv("EETCLI_INI"));
    } else {
        error(ERR_FILE,"Cannot open ".getenv("EETCLI_INI")." (from env EETCLI_INI)");
    }
} else {
    if (file_exists("eetcli.ini")) {
        read_config("eetcli.ini");
    }
}
check_options();

file_from_phar(__DIR__ . '/vendor/ondrejnov/eet/src/Schema/EETXMLSchema.xsd');
if ($console->n) {
    define('WSDL', file_from_phar(__DIR__ . '/vendor/ondrejnov/eet/src/Schema/PlaygroundService.wsdl'));
} else {
    define('WSDL', file_from_phar(__DIR__ . '/vendor/ondrejnov/eet/src/Schema/ProductionService.wsdl'));
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



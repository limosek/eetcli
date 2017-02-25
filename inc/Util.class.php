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

namespace Eetcli;

use Eetcli\Console;
use Eetcli\Config;
use Eetcli\EETFile;
use Ondrejnov\EET\Dispatcher;
use Ondrejnov\EET\Receipt;
use Ondrejnov\EET\Utils\UUID;

/**
 * Description of Util
 *
 * @author limo
 */
class Util {

    const E_FILE = 20;
    const E_ALREADYSENT = 21;
    const E_PARAMS = 22;
    const E_FORMAT = 23;
    const E_LOCK = 24;
    const E_NEW = 25;
    const E_SENT = 26;
    const E_CHECKCODES = 27;

    private static $tmpfiles;

    public function init() {
        self::$tmpfiles = Array();
        register_shutdown_function(function() {
            Util::tmpClean();
        });
    }

    public function getFromPhar($file) {
        $tmp = getenv("TMP");
        if (preg_match("/^phar\:/", $file)) {
            $f = fopen($file, "r");
            if (!$f) {
                Console::error(self::E_FILE, "Cannot find $file in phar!\n");
            }
            $data = file_get_contents($file);
            if (!$data) {
                Console::error(self::E_FILE, "Cannot read $file from phar!\n");
            }
            $tmpf = $tmp . "/" . basename($file);
            $t = fopen($tmpf, "w");
            if (!$t) {
                Console::error(self::E_FILE, "Cannot write to $tmpf. Please set TMP dir!\n");
            }
            if (fwrite($t, $data) != strlen($data)) {
                Console::error(self::E_FILE, "Cannot write to $tmpf!\n");
            }
            fclose($t);
            self::$tmpfiles[] = $tmpf;
            Console::trace("Created tmp file $tmpf from $file\n");
            return($tmpf);
        } else {
            return($file);
        }
    }

    public function tmpClean() {
        foreach (self::$tmpfiles as $f) {
            Console::trace("Deleting tmp file $f\n");
            unlink($f);
        }
    }

    public function initDispatcher($neprodukcni, $overovaci) {

        Util::getFromPhar(__DIR__ . '/../vendor/ondrejnov/eet/src/Schema/EETXMLSchema.xsd');
        if ($neprodukcni) {
            $wsdl = Util::getFromPhar(__DIR__ . '/../vendor/ondrejnov/eet/src/Schema/PlaygroundService.wsdl');
            Console::warning("Neprodukční prostředí. Pro produkční zadejte -p 0.\n");
        } else {
            $wsdl = Util::getFromPhar(__DIR__ . '/../vendor/ondrejnov/eet/src/Schema/ProductionService.wsdl');
        }

        if ($overovaci) {
            Console::warning("Ověřovací režim. Pro ostry zadejte -n 0.\n");
        }
        Console::debug("WSDL: " . $wsdl . PHP_EOL);
        Console::debug("Key: " . Config::getOpt("key") . PHP_EOL);
        Console::debug("Cert: " . Config::getOpt("crt") . PHP_EOL);
        Console::debug("DIC: " . Config::getOpt("dic") . PHP_EOL);

        try {
            $dispatcher = new Dispatcher($wsdl, Config::getOpt("key"), Config::getOpt("crt"));
        } catch (Exception $e) {
            Console::error($e->getCode(), $e->getMessage());
        }
        return($dispatcher);
    }

    public function getCheckCodes($d, $r, $neprodukcni = false, $overovaci = false) {
        if (!$overovaci) {
            $codes = $d->getCheckCodes($r);
            if (array_key_exists("_", $codes['bkp'])) {
                $bkp = $codes['bkp']['_'];
            } else {
                $bkp = null;
            }
            if (array_key_exists("_", $codes['pkp'])) {
                $pkp = base64_encode($codes['pkp']['_']);
            } else {
                $pkp = null;
            }
        } else {
            $bkp = null;
            $pkp = null;
        }
        return(Array(
            "bkp" => $bkp,
            "pkp" => $pkp
        ));
    }

    function expandMacros($str, $request) {
        $search = Array(
            "fik" => "/{fik}/",
            "pkp" => "/{pkp}/",
            "bkp" => "/{bkp}/"
        );
        $replace = Array(
            "fik" => "",
            "pkp" => "",
            "bkp" => ""
        );
        foreach ($request as $k => $v) {
            $key = "/{" . $k . "}/";
            if (is_object($v)) {
                $value = $v->format("d.m.Y H:i");
            } else {
                $value = $v;
            }
            $search[$k] = $key;
            $replace[$k] = $value;
            Console::trace("Adding macro {$k}=$value" . PHP_EOL);
        }
        $out = stripcslashes(preg_replace(
                        $search, $replace, $str));
        return($out);
    }
    
    public function getMacros() {
        $macros = Array();
        $eet=New EETFile(false);
        foreach ($eet->ITEMS as $key => $option) {
            $macros[] = "{".$key."}";
        }
        return($macros);
    }
    
    public function eetCodeToError($code) {
        if ($code==-1) {
            $code=1;
        }
        return($code);
    }
    
    public function receiptFromParams() {
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
        return($r);
    }

}

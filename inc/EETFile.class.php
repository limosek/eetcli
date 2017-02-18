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
use Ondrejnov\EET\Dispatcher;
use Ondrejnov\EET\Receipt;

/**
 * Class to work with .eet files. 
 *
 * @author LukasMacura
 */
class EETFile {
    /*
     * Mody otevreni souboru (read only, write only, rw)
     */

    const MODE_R = 1;
    const MODE_W = 2;
    const MODE_RW = 3;

    /*
     * Typy polozek k odeslani
     */
    const I_REQUIRED = 1;
    const I_OPTIONAL = 2;
    const I_REQUEST = 4;
    const I_REPLY = 8;
    const I_CHECK = 16;
    const I_DATE = 32;
    const VERSION = "0.1";

    static $ITEMS;
    var $filename;
    var $filemode;
    var $items = Array();
    var $playground;
    var $overovaci;

    /* Initialises new Eetcli\File object 
     * @param $filename Filename of EET file
     * @param $mode Mode to open. Default RO
     * @return true or throw exception
     */

    public function __construct($filename, $mode = self::MODE_R, $playground = false, $overovaci = false) {
        /*
         * Polozky dle specifikace EET
         */
        self::$ITEMS = Array(
            "uuid_zpravy" => self::I_REQUIRED | self::I_REQUEST,
            "dat_odesl" => self::I_REQUIRED | self::I_REQUEST | self::I_DATE,
            "prvni_zaslani" => self::I_REQUIRED | self::I_REQUEST,
            "overeni" => self::I_OPTIONAL | self::I_REQUEST,
            "dic_popl" => self::I_REQUIRED | self::I_REQUEST,
            "dic_poverujiciho" => self::I_OPTIONAL | self::I_REQUEST,
            "id_provoz" => self::I_REQUIRED | self::I_REQUEST,
            "id_pokl" => self::I_REQUIRED | self::I_REQUEST,
            "porad_cis" => self::I_REQUIRED | self::I_REQUEST,
            "dat_trzby" => self::I_REQUIRED | self::I_REQUEST | self::I_DATE,
            "celk_trzba" => self::I_REQUIRED | self::I_REQUEST,
            "zakl_nepodl_dph" => self::I_OPTIONAL | self::I_REQUEST,
            "zakl_dan1" => self::I_OPTIONAL | self::I_REQUEST,
            "dan1" => self::I_OPTIONAL | self::I_REQUEST,
            "zakl_dan2" => self::I_OPTIONAL | self::I_REQUEST,
            "dan2" => self::I_OPTIONAL | self::I_REQUEST,
            "zakl_dan3" => self::I_OPTIONAL | self::I_REQUEST,
            "dan3" => self::I_OPTIONAL | self::I_REQUEST,
            "cest_sluz" => self::I_OPTIONAL | self::I_REQUEST,
            "pouzit_zboz1" => self::I_OPTIONAL | self::I_REQUEST,
            "pouzit_zboz2" => self::I_OPTIONAL | self::I_REQUEST,
            "pouzit_zboz3" => self::I_OPTIONAL | self::I_REQUEST,
            "urceno_cerp_zuct" => self::I_OPTIONAL | self::I_REQUEST,
            "cerp_zuct" => self::I_OPTIONAL | self::I_REQUEST,
            "pkp" => self::I_OPTIONAL | self::I_CHECK,
            "bkp" => self::I_OPTIONAL | self::I_CHECK,
            "fik" => self::I_OPTIONAL | self::I_REPLY
        );
        $this->filename = $filename;
        $this->filemode = $mode;
        $this->items["dat_odesl"] = "";
        $this->playground = $playground;
        $this->overovaci = $overovaci;
        if ($overovaci) {
            $this->items["overeni"] = (int) $overovaci;
        }

        switch ($mode) {
            case self::MODE_R:
                self::load();
                break;
            case self::MODE_W:
                if (!self::lock()) {
                    throw new Exception("Cannot lock file $filename.");
                }
                break;
            case self::MODE_RW:
                if (!self::lock()) {
                    throw new Exception("Cannot lock file $filename.");
                } else {
                    self::load();
                }
                break;
        }
        return(true);
    }

    private function puts($f, $str) {
        if (fputs($f, $str) == strlen($str)) {
            return(true);
        } else {
            throw new Exception("Cannot write to file $filename.");
        }
    }

    /*
     * Save EET file
     */

    public function save() {
        $f = fopen($this->filename, "w");
        if ($this->playground) {
            $prostredi = "playground";
        } else {
            $prostredi = "produkcni";
        }
        if ($f) {
            self::puts($f, "[eetfile]" . PHP_EOL
                    . "version=" . self::VERSION . PHP_EOL
                    . "prostredi=$prostredi" . PHP_EOL . PHP_EOL
                    . "[eet]" . PHP_EOL
            );
            foreach (self::$ITEMS as $key => $options) {
                if ($options & self::I_REQUIRED) {
                    self::puts($f, $key . "=" . $this->items[$key] . PHP_EOL);
                } elseif ($options & self::I_OPTIONAL) {
                    if (array_key_exists($key, $this->items)) {
                        self::puts($f, $key . "=" . $this->items[$key] . PHP_EOL);
                    }
                }
            }
        } else {
            throw new Exception("Cannot write to file $filename.");
        }
        fclose($f);
    }

    /*
     * Generate EET file from receipt
     * @param $receipt
     */

    public function fromReceipt($receipt) {
        foreach ($receipt as $key => $value) {
            if (array_key_exists($key, self::$ITEMS)) {
                if (self::$ITEMS[$key] & self::I_DATE) {
                    $this->items[$key] = $value->format("c");
                } else {
                    $this->items[$key] = $value;
                }
            } else {
                //throw new \Exception("Unknown item $key");
            }
        }
    }
    
    /*
     * Generate receipt from EET file
     * @return $receipt
     */

    public function toReceipt() {
        $r = New Receipt();
        foreach (self::$ITEMS as $key=>$option) {
            if (($option & self::I_REQUEST) && array_key_exists($key,$this->items)) {
                $r->$key=$this->items[$key];
            }
        }
        return($r);
    }

    /*
     * Load EET file
     */

    public function load() {
        if (file_exists($this->filename)) {
            $ini = parse_ini_file($this->filename,true);
            if (!array_key_exists("eetfile", $ini) || !array_key_exists("version", $ini["eetfile"]) || $ini["eetfile"]["version"] <> self::VERSION
            ) {
                print_r($ini);
                throw new \Exception("File $this->filename is not EET file format.");
            } else {
                foreach (self::$ITEMS as $key => $option) {
                    if (($option & self::I_REQUIRED) && (!array_key_exists($key, $ini["eet"]))
                    ) {
                        throw new Exception("Missing required $key in EET file.");
                    } else {
                        if (array_key_exists($key,$ini["eet"])) {
                            $this->items["$key"] = $ini["eet"][$key];
                        }
                    }
                }
            }
        } else {
            throw new Exception("File $this->filename does not exists.");
        }
    }

    /*
     * Try to lock EET file
     * @return true or false
     */

    public function lock() {
        return(true);
    }

    /*
     * Try to unlock EET file
     * @return true or false
     */

    public function unlock() {
        return(true);
    }

}

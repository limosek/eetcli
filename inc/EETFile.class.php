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

    const MODE_R = 1;   // Read only
    const MODE_W = 2;   // Write only (create)
    const MODE_RW = 3;  // RW
    const MODE_D = 4;   // Dry (create object only)

    /*
     * Typy polozek k odeslani
     */
    const I_REQUIRED = 1;
    const I_OPTIONAL = 2;
    const I_REQUEST = 4;
    const I_REPLY = 8;
    const I_CHECK = 16;
    const I_DATE = 32;
    const I_BOOL = 64;
    const VERSION = "1.0";

    var $ITEMS;
    var $filename;
    var $filemode;
    var $lockfile;
    var $items = Array();
    var $playground;
    var $overovaci;
    var $status;
    var $lasterror;
    var $lasterrorcode = 0;

    /* Initialises new Eetcli\File object 
     * @param $filename Filename of EET file
     * @param $mode Mode to open. Default RO
     * @return true or throw exception
     */

    public function __construct($filename, $mode = self::MODE_D, $playground = false, $overovaci = false) {
        /*
         * Polozky dle specifikace EET
         */
        $this->ITEMS = Array(
            "uuid_zpravy" => self::I_REQUIRED | self::I_REQUEST,
            "dat_odesl" => self::I_REQUIRED | self::I_REQUEST | self::I_DATE,
            "prvni_zaslani" => self::I_REQUIRED | self::I_REQUEST | self::I_BOOL,
            "overeni" => self::I_OPTIONAL | self::I_REQUEST | self::I_BOOL,
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
        $this->lockfile = "$filename.lock";
        $this->items["dat_odesl"] = "";
        $this->playground = $playground;
        $this->overovaci = $overovaci;
        if ($overovaci) {
            $this->items["overeni"] = (int) $overovaci;
        }

        switch ($mode) {
            case self::MODE_R:
                self::load();
                if (array_key_exists("fik", $this->items)) {
                    $this->status = 2;
                } elseif (array_key_exists("pkp", $this->items)) {
                    $this->status = 1;
                } else {
                    $this->status = 0;
                }
                break;
            case self::MODE_W:
                if (file_exists($this->filename)) {
                    throw New \Exception("Soubor EET jiz existuje!", Util::E_FILE);
                }
                self::lock();
                break;
            case self::MODE_RW:
                self::lock();
                self::load();
                break;
            case self::MODE_D:
                break;
        }
        return(true);
    }

    private function puts($f, $str) {
        if (fputs($f, $str) == strlen($str)) {
            return(true);
        } else {
            throw new \Exception("Cannot write to file $filename.", Util::E_FILE);
        }
    }

    public function setStatus($status) {
        $this->status = $status;
    }

    public function setError($msg) {
        $this->lasterrorcode = $msg;
    }

    public function setErrorCode($code) {
        $this->lasterror = $code;
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
                    . "status=$this->status" . PHP_EOL
                    . "lasterror=$this->lasterror" . PHP_EOL
                    . "lasterrorcode=$this->lasterrorcode" . PHP_EOL
                    . "prostredi=$prostredi" . PHP_EOL . PHP_EOL
                    . "[eet]" . PHP_EOL
            );
            foreach ($this->ITEMS as $key => $options) {
                if ($options & self::I_REQUIRED) {
                    self::puts($f, $key . "=" . $this->items[$key] . PHP_EOL);
                } elseif ($options & self::I_OPTIONAL) {
                    if (array_key_exists($key, $this->items)) {
                        self::puts($f, $key . "=" . $this->items[$key] . PHP_EOL);
                    }
                }
            }
        } else {
            throw new \Exception("Cannot write to file $filename.", Util::E_FILE);
        }
        fclose($f);
        self::unlock();
    }

    /*
     * Generate EET file from receipt
     * @param $receipt
     */

    public function fromReceipt($receipt) {
        foreach ($receipt as $key => $value) {
            if (array_key_exists($key, $this->ITEMS)) {
                if ($this->ITEMS[$key] & self::I_DATE) {
                    $this->items[$key] = $value->format("c");
                } elseif ($this->ITEMS[$key] & self::I_BOOL) {
                    $this->items[$key] = (int) $value;
                } else {
                    if ($value) {
                        $this->items[$key] = $value;
                    }
                }
            }
        }
    }

    /*
     * Generate receipt from EET file
     * @return $receipt
     */

    public function toReceipt($d) {
        $r = New Receipt();
        foreach ($this->ITEMS as $key => $option) {
            if (($option & self::I_REQUEST) && array_key_exists($key, $this->items)) {
                if ($this->items[$key] && !($option & self::I_BOOL)) {
                    $r->$key = $this->items[$key];
                } else {
                    if ($option & self::I_BOOL) {
                        $r->$key = (bool) $this->items[$key];
                    }
                }
            }
        }
        $codes = Util::getCheckCodes($d, $r, $this->playground, $this->overovaci);
        if (is_array($this->items) && array_key_exists("bkp",$this->items)) {
            if ($codes["bkp"] != $this->items["bkp"] || $codes["pkp"] != $this->items["pkp"]) {
                throw New \Exception("Kontrolni kody EET v uctence nesouhlasi!", Util::E_CHECKCODES);
            }
        }
        return($r);
    }

    /*
     * Load EET file
     */

    public function load() {
        if (file_exists($this->filename)) {
            $ini = parse_ini_file($this->filename, true);
            if (!array_key_exists("eetfile", $ini) || !array_key_exists("version", $ini["eetfile"]) || $ini["eetfile"]["version"] <> self::VERSION
            ) {
                throw new \Exception("File $this->filename is not EET file format.", Util::E_FORMAT);
            } else {
                foreach ($this->ITEMS as $key => $option) {
                    if ($key != "dat_odesl" && ($option & self::I_REQUIRED) && (!array_key_exists($key, $ini["eet"]))
                    ) {
                        throw new \Exception("Missing required $key in EET file.", Util::E_FORMAT);
                    } else {
                        if (array_key_exists($key, $ini["eet"])) {
                            if ($option & self::I_DATE) {
                                $this->items["$key"] = New \DateTime($ini["eet"][$key]);
                            } else {
                                $this->items["$key"] = $ini["eet"][$key];
                            }
                        }
                    }
                }
                if (array_key_exists("lasterror", $ini["eetfile"])) {
                    $this->lasterror = $ini["eetfile"]["lasterror"];
                } else {
                    $this->lasterror = "";
                }
                if (array_key_exists("status", $ini["eetfile"])) {
                    $this->status = $ini["eetfile"]["status"];
                } else {
                    $this->status = 0;
                }
                if (array_key_exists("prostredi", $ini["eetfile"])) {
                    if ($ini["eetfile"]["prostredi"] == "playground") {
                        $this->playground = 1;
                    } else {
                        $this->playground = 0;
                    }
                } else {
                    $this->playground = 0;
                }
            }
        } else {
            throw new \Exception("File $this->filename does not exists.", Util::E_FILE);
        }
    }

    /*
     * Try to lock EET file
     * @return true or false
     */

    public function lock() {
        if (file_exists($this->lockfile)) {
            throw new \Exception("Cannot lock file $this->filename ($this->lockfile exists).", Util::E_LOCK);
        } else {
            if (touch($this->lockfile)) {
                return(true);
            } else {
                throw new \Exception("Cannot lock file $this->filename (cannot create $this->lockfile)", Util::E_LOCK);
            }
        }
    }

    /*
     * Try to unlock EET file
     * @return true or false
     */

    public function unlock() {
        if (!unlink($this->lockfile)) {
            throw new \Exception("Cannot unlock file $this->filename (cannot remove $this->lockfile)", Util::E_LOCK);
        }
    }

}

<?php

/*
 * Copyright (C) 2017 limo
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

use Ulrichsg\Getopt\Getopt;
use Ulrichsg\Getopt\Option;

/**
 * Description of Console
 *
 * @author LukasMacura
 */
class Config {

    const C_REQUIRED = 1;
    const C_OPTIONAL = 2;

    private static $opts;
    private static $usage;
    private static $args;
    private static $getopt;

    public function init($contexts = false) {
        self::$opts = New \stdClass();
        self::$usage = "";
        self::addOpt("d", "debug", Getopt::REQUIRED_ARGUMENT, "Debug level", Console::LOG_WARN);
        self::addOpt("e", "errors", Getopt::REQUIRED_ARGUMENT, "Errors to file", "php://stderr");
        self::addOpt("o", "output", Getopt::REQUIRED_ARGUMENT, "Output to file", "php://stdout");
        self::addOpt("h", "help", Getopt::OPTIONAL_ARGUMENT, "Help");
    }

    private function toGetopt() {
        $go = Array();
        foreach (self::$opts as $opt) {
            $key = $opt->option;
            $short = $opt->short;
            $go[] = array($short, $key, $opt->required);
        }
        return($go);
    }
    
    /*
     * Read config file and cli options
     * $contexts - ini file contexts to read
     * $readini  - if false, do not read ini file
     * $clean    - if true, clean all values and read again
     */
    public function read($contexts = false, $readini = true, $clean = false) {
        if (!$contexts) {
            $contexts = Array("global");
        }
        $ini = null;
        if ($clean) {
            foreach (self::$opts as $opt) {
                $key = $opt->option;
                self::$opts->$key->value = null;
            }
        }
        if ($readini) {
            if (getenv("EETCLI_INI")) {
                $cf = getenv("EETCLI_INI");
                if ($cf != "null") {
                    $cffiles = Array($cf);
                    $ini = parse_ini_file($cf, true);
                }
            } else {
                $cffiles = Array(
                    __DIR__ . "/../eetcli.ini",
                    getenv("HOME") . "/eetcli.ini",
                    getenv("HOME") . "/.eetclirc",
                    "/etc/eetcli.ini",
                    __DIR__ . "/../eetcli.ini.dist"
                );
                foreach ($cffiles as $file) {
                    if (file_exists($file)) {
                        $ini = parse_ini_file($file, true);
                        break;
                    }
                }
            }
            if (!$ini) {
                Console::debug("Did not read ini file! (tried " . join(", ", $cffiles) . ")" . PHP_EOL);
                $ini = Array();
            }
        } else {
            $ini=Array();
        }
        foreach (self::$opts as $opt) {
            $key = $opt->option;
            self::$opts->$key->value = self::$opts->$key->default;
        }
        foreach (self::$opts as $opt) {
            $key = $opt->option;
            foreach ($contexts as $c) {
                if (array_key_exists($c, $ini)) {
                    if (array_key_exists($key, $ini[$c])) {
                        $value = $ini[$c][$key];
                        Console::trace("Setting option $key from INI file to value '$value'." . PHP_EOL);
                        self::$opts->$key->value = $value;
                        self::$opts->$key->fromini = true;
                    }
                    if (array_key_exists("$c.$key", $ini[$c])) {
                        $value = $ini[$c]["$c.$key"];
                        Console::trace("Setting option $c.$opt->option from INI file to value '$value'." . PHP_EOL);
                        self::$opts->$key->value = $value;
                        self::$opts->$key->fromini = true;
                    }
                }
            }
        }
        foreach (self::$opts as $opt) {
            $key = $opt->option;
            $ekey = strtr(strtoupper("EETCLI_$key"), ".-", "__");
            if (getenv($ekey)) {
                $value = getenv($ekey);
                Console::trace("Setting option $key from ENV variable $ekey to value '$value'." . PHP_EOL);
                self::$opts->$key->value = $value;
                self::$opts->$key->fromenv = true;
            }
        }
        try {
            self::$getopt = New Getopt(self::toGetOpt());
            self::$getopt->parse();
            self::$args = self::$getopt->getOperands();
            self::$getopt->setBanner(self::$usage);
        } catch (\Exception $e) {
            Console::error(Config::E_PARMS, $e->getMessage() . PHP_EOL . "Use eetcli -h!" . PHP_EOL);
        }
        foreach (self::$opts as $opt) {
            $key = $opt->option;
            if (self::$getopt->getOption($key) != null) {
                $value = self::$getopt->getOption($key);
                Console::trace("Setting option $key from CLI to vale '$value'." . PHP_EOL);
                self::$opts->$key->value = $value;
                self::$opts->$key->fromcli = true;
            }
        }
        if (self::getOpt("debug")) {
            Console::setLevel(self::getOpt("debug"));
        }
        if (self::getOpt("errors")) {
            Console::setLogFile(self::getOpt("errors"));
        }
        if (self::getOpt("output")) {
            Console::setOutFile(self::getOpt("output"));
        }
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
        if (self::getOpt("help")) {
            self::helpOpts();
            exit;
        }
    }

    public function addOpt($short, $option, $required = false, $help = false, $default = false) {
        $opt = New \StdClass();
        $opt->short = $short;
        $opt->option = $option;
        $opt->default = $default;
        $opt->help = $help;
        $opt->required = $required;
        self::$opts->$option = $opt;
    }

    public function getOpt($key) {
        if (isset(self::$opts->$key)) {
            return(self::$opts->$key->value);
        } else {
            Console::error(self::E_PARMS, "Unknown option $key." . PHP_EOL);
        }
    }

    public function setOpt($key, $value) {
        if (isset(self::$opts->$key)) {
            self::$opts->$key->value = $value;
        } else {
            Console::error(self::E_PARMS, "Unknown option $key." . PHP_EOL);
        }
    }

    public function getArgs() {
        return(self::$args);
    }

    public function setUsage($msg) {
        self::$usage = $msg;
    }

    public function helpOpts($msg = "") {
        if (Console::$loglevel > Console::LOG_DEBUG) {
            self::helpOptsLong($msg);
        } else {
            self::helpOptsShort($msg);
        }
    }

    public function helpOptsLong($msg = "") {
        Console::log(PHP_EOL . "Available options:" . PHP_EOL);
        foreach (self::$opts as $opt) {
            $key = $opt->option;
            if (strlen($key) > 1) {
                Console::log("'--$key' ");
            } else {
                Console::log("'-$key' ");
            }
            Console::log("$opt->help" . PHP_EOL);
            if ($opt->required == self::C_OPTIONAL) {
                Console::log(" (optional),");
            } else {
                Console::log(" (required),");
            }
            if (isset($opt->value)) {
                Console::log("Value: $opt->value" . PHP_EOL);
            } else {
                if ($opt->default) {
                    Console::log(" (Default: $opt->default),");
                }
            }
            Console::log(PHP_EOL . PHP_EOL);
        }
        Console::log(self::$usage . PHP_EOL . $msg . PHP_EOL);
    }

    public function helpOptsShort($msg = "") {
        Console::log(self::$getopt->getHelpText());
        Console::log("Pouzijte eetcli -h -d 4 pro vice informaci." . PHP_EOL);
    }

    public function notAllowedOpts($option, $conflicted_opts) {
        foreach ($conflicted_opts as $short => $long) {
            if (self::getOpt($long) && (isset(self::$opts->$long->fromcli) || isset(self::$opts->$long->fromenv))) {
                Console::error(Util::E_PARAMS, "--$option nemuze byt kombinovana s --$long! Pouzijte eetcli -h pro vice informaci." . PHP_EOL);
            }
        }
    }
    
    public function requiredOpts($option, $required_opts) {
        foreach ($required_opts as $short => $long) {
            if (!self::getOpt($long)) {
                Console::error(Util::E_PARAMS, "--$option vyzaduje --$long! Pouzijte eetcli -h pro vice informaci." . PHP_EOL);
            }
        }
    }

}

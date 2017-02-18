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

/**
 * This is basic class to work with console applications in PHP
 * It solves console errors and warnings generation and autodetect of some specific things.
 *
 * @author LukasMacura
 */
class Console {

    const LOG_ERROR = 1;
    const LOG_WARN = 2;
    const LOG_DEBUG = 3;
    const LOG_TRACE = 4;

    public static $loglevel;
    private static $lh = STDERR;
    private static $oh = STDOUT;

    /*
     * Initialise console class
     * @param $level - Initial debug level
     * @param $lh - Initial log handle 
     */
    public function init($level = self::LOG_ERROR, $lh = STDERR) {
        if ($lh) {
            self::$lh = $lh;
        }
        if ($level) {
            self::$loglevel = $level;
        }
    }

    /*
     * Set debug level
     */
    public function setLevel($level) {
        self::$loglevel = $level;
    }

    /* 
     * Set log to file
     */
    public function setLogFile($file) {
        self::$lh = fopen($file, "w");
    }

    /*
     * Set output to file
     */
    public function setOutFile($file) {
        self::$oh = fopen($file, "w");
    }

    /*
     * Log message in any debug level
     */
    public function log($msg) {
        fprintf(self::$lh, "%s", $msg);
    }

    /*
     * Write data to ouput file
     */
    public function out($msg) {
        fprintf(self::$oh, "%s", $msg);
    }

    /*
     * Log message and exit with given status code
     * @param $code error code
     * @param $msg message
     */
    public function error($code, $msg) {
        if (self::$loglevel >= self::LOG_ERROR) {
            fprintf(self::$lh, "%s", $msg);
        }
        exit($code);
    }
    
    /*
     * Log message with warning level
     * @param $msg message
     */
    public function warning($msg) {
        if (self::$loglevel >= self::LOG_WARN) {
            fprintf(self::$lh, "%s", $msg);
        }
    }

    /*
     * Log message with debug level
     * @param $msg message
     */
    public function debug($msg) {
        if (self::$loglevel >= self::LOG_DEBUG) {
            fprintf(self::$lh, "%s", $msg);
        }
    }

    /*
     * Log message with trace level
     * @param $msg message
     */
    public function trace($msg) {
        if (self::$loglevel >= self::LOG_TRACE) {
            fprintf(self::$lh, "%s", $msg);
        }
    }
    
}

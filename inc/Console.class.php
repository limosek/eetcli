<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Eetcli;

/**
 * Description of Console
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

    public function init($level = self::LOG_ERROR, $lh = STDERR) {
        if ($lh) {
            self::$lh = $lh;
        }
        if ($level) {
            self::$loglevel = $level;
        }
    }

    public function setLevel($level) {
        self::$loglevel = $level;
    }

    public function setLogFile($file) {
        self::$lh = fopen($file, "w");
    }

    public function setOutFile($file) {
        self::$oh = fopen($file, "w");
    }

    public function log($msg) {
        fprintf(self::$lh, "%s", $msg);
    }

    public function out($msg) {
        fprintf(self::$oh, "%s", $msg);
    }

    public function error($code, $msg) {
        if (self::$loglevel >= self::LOG_ERROR) {
            fprintf(self::$lh, "%s", $msg);
        }
        exit($code);
    }

    public function warning($msg) {
        if (self::$loglevel >= self::LOG_WARN) {
            fprintf(self::$lh, "%s", $msg);
        }
    }

    public function debug($msg) {
        if (self::$loglevel >= self::LOG_DEBUG) {
            fprintf(self::$lh, "%s", $msg);
        }
    }

    public function trace($msg) {
        if (self::$loglevel >= self::LOG_TRACE) {
            fprintf(self::$lh, "%s", $msg);
        }
    }

}

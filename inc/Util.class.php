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

/**
 * Description of Util
 *
 * @author limo
 */
class Util {

    private static $tmpfiles;

    public function init() {
        self::$tmpfiles = Array();
        register_shutdown_function(function(){
            Util::tmpClean();
        } );
    }

    public function getFromPhar($file) {
        $tmp = getenv("TMP");
        if (preg_match("/^phar\:/", $file)) {
            $f = fopen($file, "r");
            if (!$f) {
                Console::error(10, "Cannot find $file in phar!\n");
            }
            $data = file_get_contents($file);
            if (!$data) {
                Console::error(10, "Cannot read $file from phar!\n");
            }
            $tmpf = $tmp . "/" . basename($file);
            $t = fopen($tmpf, "w");
            if (!$t) {
                Console::error(10, "Cannot write to $tmpf. Please set TMP dir!\n");
            }
            if (fwrite($t, $data) != strlen($data)) {
                Console::error(10, "Cannot write to $tmpf!\n");
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
}

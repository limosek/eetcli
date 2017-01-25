<?php

$phar = new Phar('eetcli.phar', 0, 'eetcli.phar');
$phar->buildFromDirectory(".");
$phar->setStub($phar->createDefaultStub('eetcli.php'));
$phar->convertToExecutable();


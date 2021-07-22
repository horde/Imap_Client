<?php
$autoload = __DIR__ . '/../../../../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}
if (!class_exists('Horde_Test_Bootstrap')) {
    require_once 'Horde/Test/Bootstrap.php';
}
Horde_Test_Bootstrap::bootstrap(dirname(__FILE__));

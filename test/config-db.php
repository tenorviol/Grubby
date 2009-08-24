<?php

// the location of the Grubby library
define('GRUBBY_ROOT', realpath(dirname(__FILE__).'/../src'));

require_once GRUBBY_ROOT.'/Grubby.php';
require_once GRUBBY_ROOT.'/GrubbyDB.php';

if ($GLOBALS['database']) {
    echo basename(__FILE__).': replacing $GLOBALS[\'database\']'."\n";
}
$GLOBALS['database'] = new GrubbyDB(array('phptype' => 'mysql',
                                          'protocol' => 'unix',
                                          'socket' => '/tmp/mysql.sock',
                                          'username' => 'foo',
                                          'password' => 'foo',
                                          'database' => 'foo'));

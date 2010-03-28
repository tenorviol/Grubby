<?php

require_once 'autoload.php';
require_once 'DB.php';

if (empty($GLOBALS['database'])) {
    echo basename(__FILE__).': replacing $GLOBALS[\'database\']'."\n";
}
$GLOBALS['database'] = new Grubby_DB_Database(array('phptype' => 'mysql',
                                          'protocol' => 'unix',
                                          'socket' => '/tmp/mysql.sock',
                                          'username' => 'foo',
                                          'password' => 'foo',
                                          'database' => 'foo'));

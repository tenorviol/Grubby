<?php

require_once 'autoload.php';

if (empty($GLOBALS['database'])) {
    echo basename(__FILE__).': replacing $GLOBALS[\'database\']'."\n";
}
$GLOBALS['database'] = new Grubby_PDO_Database('mysql:unix_socket=/tmp/mysql.sock;dbname=foo', 'foo', 'foo');

<?php

// the location of the Grubby library
define('GRUBBY_ROOT', realpath(dirname(__FILE__).'/../src'));

// core Grubby required for test instantiation
require_once GRUBBY_ROOT.'/Grubby.php';

// create a GrubbyDB connection
// require_once GRUBBY_ROOT.'/GrubbyDB.php';
// $dns = DB connection info
// $GLOBALS['database'] = new GrubbyDB($dns);

// create a GrubbyMDB2 connection
// require_once GRUBBY_ROOT.'/GrubbyMDB2.php';
// $dsn = MDB2 connection info
// $GLOBALS['database'] = new GrubbyMDB2($dsn, $extra);

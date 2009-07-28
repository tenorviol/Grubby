<?php

define('GRUBBY_ROOT', realpath(dirname(__FILE__).'/../Grubby'));

require_once GRUBBY_ROOT.'/GrubbyDB.php';
require_once GRUBBY_ROOT.'/GrubbyMDB2.php';

$databases = array();

$databases[] = new GrubbyMDB2(
    array('phptype' => 'mysql',
          'protocol' => 'unix',
          'socket' => '/tmp/mysql.sock',
          'username' => 'foo',
          'password' => 'foo',
          'database' => 'foo'),
    array('debug' => 2,
          'result_buffering' => false,
          'portability' => MDB2_PORTABILITY_ALL ^ MDB2_PORTABILITY_EMPTY_TO_NULL)
);

$databases[] = new GrubbyDB(
    array('phptype' => 'mysql',
          'protocol' => 'unix',
          'socket' => '/tmp/mysql.sock',
          'username' => 'foo',
          'password' => 'foo',
          'database' => 'foo')
);

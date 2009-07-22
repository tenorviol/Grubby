<?php

require_once '../Grubby/GrubbyMDB2.php';
require_once '../Grubby/GrubbyDB.php';

$TEST_DATABASES = array();

$TEST_DATABASES['GrubbyMDB2:MySQL'] = new GrubbyMDB2(
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

$TEST_DATABASES['GrubbyDB:MySQL'] = new GrubbyDB(
    array('phptype' => 'mysql',
          'protocol' => 'unix',
          'socket' => '/tmp/mysql.sock',
          'username' => 'foo',
          'password' => 'foo',
          'database' => 'foo')
);
        
<?php

require_once 'autoload.php';
require_once 'MDB2.php';

$GLOBALS['database'] = new Grubby_MDB2_Database(array('phptype' => 'mysql',
                                            'protocol' => 'unix',
                                            'socket' => '/tmp/mysql.sock',
                                            'username' => 'foo',
                                            'password' => 'foo',
                                            'database' => 'foo'),
                                      array('debug' => 2,
                                            'result_buffering' => false,
                                            'portability' => MDB2_PORTABILITY_ALL ^ MDB2_PORTABILITY_EMPTY_TO_NULL));

<?php

// the location of the Grubby library
define('GRUBBY_ROOT', realpath(dirname(__FILE__).'/../src'));

require_once GRUBBY_ROOT.'/Grubby.php';
require_once GRUBBY_ROOT.'/GrubbyMDB2.php';

$GLOBALS['database'] = new GrubbyMDB2(array('phptype' => 'mysql',
                                            'protocol' => 'unix',
                                            'socket' => '/tmp/mysql.sock',
                                            'username' => 'foo',
                                            'password' => 'foo',
                                            'database' => 'foo'),
                                      array('debug' => 2,
                                            'result_buffering' => false,
                                            'portability' => MDB2_PORTABILITY_ALL ^ MDB2_PORTABILITY_EMPTY_TO_NULL));

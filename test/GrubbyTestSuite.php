<?php

require_once 'PHPUnit/Framework.php';
require_once 'GrubbyTest.php';

require_once 'TestDatabases.php';

class GrubbyTestSuite extends PHPUnit_Framework_TestSuite
{
    private $database;
    
    public static function suite() {
        global $TEST_DATABASES;
        
        $suite = new PHPUnit_Framework_TestSuite();
        
        foreach ($TEST_DATABASES as $key => $database) {
            $test = new GrubbyTestSuite('GrubbyTest');
            $test->database = $database;
            $suite->addTestSuite($test);
        }
        
        return $suite;
    }

    protected function setUp() {
        $this->sharedFixture = array('database'=>$this->database);
    }
    
    protected function tearDown() {
        $this->sharedFixture = null;
    }
}

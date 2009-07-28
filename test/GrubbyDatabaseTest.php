<?php

require_once 'config.php';

class GrubbyTest extends PHPUnit_Framework_TestCase {
    
    private $database;
    private $test_table;
    
    // WARNING: This grubby_test table will get dropped and created in the test database
    private $test_schema = array('name' => 'grubby_test',
                                'primary_key' => 'id',
                                'fields' => array(
                                    array('name'=>'id',       'type'=>'INT', 'auto_increment'=>true),
                                    array('name'=>'foo',      'type'=>'VARCHAR'),
                                    array('name'=>'category', 'type'=>'INT'),
                                    array('name'=>'from',     'type'=>'VARCHAR'),
                                ),
                            );
    
    // Data for pre-populating grubby_test
    private $initial_data = array(
                                array('id'=>1,  'foo'=>'Spew',         'category'=>1, 'from'=>'joe'),
                                array('id'=>2,  'foo'=>'Chunks',       'category'=>2, 'from'=>'john'),
                                array('id'=>3,  'foo'=>'Not',          'category'=>3, 'from'=>'mary'),
                                array('id'=>4,  'foo'=>'Behind',       'category'=>1, 'from'=>'larry'),
                                array('id'=>5,  'foo'=>'Bars',         'category'=>3, 'from'=>'ed'),
                                array('id'=>6,  'foo'=>'But Outdoors', 'category'=>2, 'from'=>'serius'),
                                array('id'=>7,  'foo'=>'See Chris\'s Doo Dads...\\', 'category'=>1, 'from'=>'actual'),
                                array('id'=>27, 'foo'=>null,           'category'=>1, 'from'=>'chunk'),
                                array('id'=>28, 'foo'=>'',             'category'=>2, 'from'=>'doppy'),
                                );
    
    /**
     * Create grubby_test.
     */
    public function setUp() {
        
        if ($this->sharedFixture['database']) {
            $this->database = $this->sharedFixture['database'];
        } else {
            $this->database = new GrubbyDB(array('phptype' => 'mysql',
                                                 'protocol' => 'unix',
                                                 'socket' => '/tmp/mysql.sock',
                                                 'username' => 'foo',
                                                 'password' => 'foo',
                                                 'database' => 'foo')
                                                );
        }
        $this->test_table = new GrubbyTable($this->database, $this->test_schema);
        
        $this->test_table->dropTable();
        $this->test_table->createTable();
        
        // populate grubby_test with some initial data
        foreach ($this->initial_data as $row) {
            $this->test_table->create($row);
        }
        //Grubby::$debug = true;
    }
    
    /**
     * Delete grubby_test.
     */
    public function tearDown() {
        //Grubby::$debug = false;
        $this->test_table->dropTable();
    }
    
    ////////// GrubbyDatabase TESTS //////////
    
    /**
     * Query the database obtaining a result set.
     * These are typically SELECT statements.
     */
    public function testQuery() {
        // select the grubby_test table
        $result = $this->database->query('SELECT * FROM `'.$this->test_schema['name'].'`');
        
        // this should return a GrubbyRecordset
        $this->assertType('GrubbyRecordset', $result);
        
        // test each row against grubby_test's initial data
        $i = 0;
        while ($row = $result->fetch()) {
            $this->assertEquals($this->initial_data[$i], $row);
            $i++;
        }
    }
    
    /**
     * Buggy SQL throws a GrubbyException.
     * @expectedException GrubbyException
     */
    public function testQueryError() {
        $result = $this->database->query('ERRONEOUS SQL');
    }
    
    /**
     * Database execution manipulate the state of the data.
     * These are typically CREATE, UPDATE and DELETE statements.
     */
    public function testExecute() {
        // update all foos to 'bar' in grubby_test
        $result = $this->database->execute('UPDATE `'.$this->test_schema['name'].'` SET foo=\'bar\'');
        
        // this should return a GrubbyResult with the affected rows
        $this->assertType('GrubbyResult', $result);
        $this->assertEquals(count($this->initial_data), $result->affected_rows);
        
        // test the table's foo values
        $result = $this->database->query('SELECT * FROM `'.$this->test_schema['name'].'`');
        while ($row = $result->fetch()) {
            $this->assertEquals('bar', $row['foo']);
        }
    }
    
    /**
     * Buggy SQL throws a GrubbyException.
     * @expectedException GrubbyException
     */
    public function testExecuteError() {
        $result = $this->database->execute('ERRONEOUS SQL');
    }
    
    public static function wildcardTestProvider() {
        return array(
            array('', '', '', ''),  // empty string
            array('foo=42', null, null, 'foo=42'),  // no wildcards
            array('foo=?', 'bar', null, 'foo=\'bar\''),  // single wildcard
            array('foo=?', 12, GRUBBY_INT, 'foo=12'),  // single integer wildcard
            array('foo=?', 12, GRUBBY_STRING, 'foo=\'12\''),  // single string wildcard
            array('foo=? OR bar=?', array(1, 2), array(GRUBBY_INT, GRUBBY_STRING), 'foo=1 OR bar=\'2\''),  // two wildcards
            array('foo=\'?\' OR bar=?', 'bar', null, 'foo=\'?\' OR bar=\'bar\''),  // single wildcard with prior string embeded ?
            array('\'', null, null, new GrubbyException()),  // unterminated string exception
            array('foo=? OR bar=?', 42, null, new GrubbyException()), // not enough wildcards exception
            array('foo=? OR bar=?', array(1, 2, 3), null, new GrubbyException()), // too many wildcards exception
        );
    }
    
    /**
     * @dataProvider wildcardTestProvider
     */
    public function testReplaceWildcards($sql, $wildcards, $types, $expected) {
        if ($expected instanceof GrubbyException) {
            $this->setExpectedException('GrubbyException');
        }
        $result = $this->database->replaceWildcards($sql, $wildcards, $types);
        $this->assertEquals($expected, $result);
    }
}

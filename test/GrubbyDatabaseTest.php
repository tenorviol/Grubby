<?php

require_once dirname(__FILE__).'/config.php';

class GrubbyTest extends PHPUnit_Framework_TestCase {
    
    private $database;
    
    /**
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
    }
    
    /**
     */
    public function tearDown() {
    }
    
    public function databaseProvider() {
        static $return = null;
        if ($return === null) {
            global $databases;  // see config.php
            $return = array();
            foreach ($databases as $database) {
                $return[] = array($database);
            }
        }
        return $return;
    }
    
    ////////// GrubbyDatabase TESTS //////////
    
    /**
     * Query the database obtaining a result set.
     * These are typically SELECT statements.
     * @dataProvider databaseProvider
     */
    public function testQuery($database) {
        // select the grubby_test table
        $result = $database->query('SELECT 42 AS forty_two');
        
        // this should return a GrubbyRecordset
        $this->assertType('GrubbyRecordset', $result);
        
        $row = $result->fetch();
        $this->assertEquals(42, $row['forty_two']);
    }
    
    /**
     * Buggy SQL throws a GrubbyException.
     * @dataProvider databaseProvider
     * @expectedException GrubbyException
     */
    public function testQueryError($database) {
        $result = $database->query('ERRONEOUS SQL');
    }
    
    /**
     * Database execution manipulate the state of the data.
     * These are typically CREATE, UPDATE and DELETE statements.
     * @dataProvider databaseProvider
     */
    public function testExecute($database) {
        // create a temporary table foo
        $result = $database->execute('CREATE TEMPORARY TABLE foo (bar INT)');
        $this->assertType('GrubbyResult', $result);
        
        $data = array(mt_rand(), mt_rand(), mt_rand());
        
        $result = $database->execute('INSERT INTO foo (bar) VALUES ('.implode('),(', $data).')');
        $this->assertType('GrubbyResult', $result);
        $this->assertEquals(count($data), $result->affected_rows);
        
        // test the table's foo values
        $result = $database->query('SELECT * FROM foo');
        $read = array();
        while ($row = $result->fetch()) {
            $read[] = $row['bar'];
        }
        $this->assertEquals($data, $read);
    }
    
    /**
     * Buggy SQL throws a GrubbyException.
     * @dataProvider databaseProvider
     * @expectedException GrubbyException
     */
    public function testExecuteError($database) {
        $result = $database->execute('ERRONEOUS SQL');
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

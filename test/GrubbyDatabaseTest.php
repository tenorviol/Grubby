<?php
/**
 * Grubby : Quick and dirty CRUD operations
 * http://grubbycrud.com/
 * 
 * Version: @version@
 * Date: @date@
 * 
 * Copyright (c) @year@ Christopher Johnson
 * Licensed under the MIT license (see LICENSE file).
 */

require_once dirname(__FILE__).'/config.php';

class GrubbyDatabaseTest extends PHPUnit_Framework_TestCase {
    
    /**
     */
    public function setUp() {
    }
    
    /**
     */
    public function tearDown() {
    }
    
    /**
     * Query the database obtaining a result set.
     * These are typically SELECT statements.
     */
    public function testQuery() {
        $database = $GLOBALS['database'];
        
        // select the grubby_test table
        $result = $database->query('SELECT 42 AS forty_two');
        
        // this should return a Grubby_Recordset
        $this->assertType('Grubby_Recordset', $result);
        
        $row = $result->fetch();
        $this->assertEquals(42, $row['forty_two']);
    }
    
    /**
     * Buggy SQL throws a Grubby_Exception.
     * @expectedException Grubby_Exception
     */
    public function testQueryError() {
        $database = $GLOBALS['database'];
        
        $result = $database->query('ERRONEOUS SQL');
    }
    
    /**
     * Database execution manipulate the state of the data.
     * These are typically CREATE, UPDATE and DELETE statements.
     */
    public function testExecute() {
        $database = $GLOBALS['database'];
        
        $database_before = $database->time;
        $start = microtime(true);
        
        // create a temporary table foo
        $result = $database->execute('CREATE TEMPORARY TABLE foo (bar INT)');
        $this->assertType('Grubby_Result', $result);
        
        $data = array(mt_rand(), mt_rand(), mt_rand());
        
        $result = $database->execute('INSERT INTO foo (bar) VALUES ('.implode('),(', $data).')');
        $this->assertType('Grubby_Result', $result);
        $this->assertEquals(count($data), $result->affected_rows);
        
        // test the table's foo values
        $result = $database->query('SELECT * FROM foo');
        $read = array();
        while ($row = $result->fetch()) {
            $read[] = $row['bar'];
        }
        $this->assertEquals($data, $read);
        
        $time = microtime(true) - $start;
        $database_time = $database->time - $database_before;
        
        $this->assertGreaterThan(0, $time);  // some database time should have been spent
    }
    
    /**
     * Buggy SQL throws a Grubby_Exception.
     * @expectedException Grubby_Exception
     */
    public function testExecuteError() {
        $database = $GLOBALS['database'];
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
            array('\'', null, null, new Grubby_Exception()),  // unterminated string exception
            array('foo=? OR bar=?', 42, null, new Grubby_Exception()), // not enough wildcards exception
            array('foo=? OR bar=?', array(1, 2, 3), null, new Grubby_Exception()), // too many wildcards exception
        );
    }
    
    /**
     * @dataProvider wildcardTestProvider
     */
    public function testReplaceWildcards($sql, $wildcards, $types, $expected) {
        $database = $GLOBALS['database'];
        
        if ($expected instanceof Grubby_Exception) {
            $this->setExpectedException('Grubby_Exception');
        }
        $result = $database->replaceWildcards($sql, $wildcards, $types);
        $this->assertEquals($expected, $result);
    }
}

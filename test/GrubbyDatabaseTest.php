<?php

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
        
        // this should return a GrubbyRecordset
        $this->assertType('GrubbyRecordset', $result);
        
        $row = $result->fetch();
        $this->assertEquals(42, $row['forty_two']);
    }
    
    /**
     * Buggy SQL throws a GrubbyException.
     * @expectedException GrubbyException
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
        
        $grubby_before = Grubby::$time;
        $database_before = $database->time;
        $start = microtime(true);
        
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
        
        $time = microtime(true) - $start;
        $grubby_time = Grubby::$time - $grubby_before;
        $database_time = $database->time - $database_before;
        
        $this->assertGreaterThan(0, $time);  // some database time should have been spent
        $this->assertEquals($grubby_time, $database_time);  // one database, equal times
        $this->assertLessThanOrEqual($time, $grubby_time);  // more total time than database time
        $this->assertGreaterThan($time*.8, $grubby_time);  // most of time spent in database
    }
    
    /**
     * Buggy SQL throws a GrubbyException.
     * @expectedException GrubbyException
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
            array('\'', null, null, new GrubbyException()),  // unterminated string exception
            array('foo=? OR bar=?', 42, null, new GrubbyException()), // not enough wildcards exception
            array('foo=? OR bar=?', array(1, 2, 3), null, new GrubbyException()), // too many wildcards exception
        );
    }
    
    /**
     * @dataProvider wildcardTestProvider
     */
    public function testReplaceWildcards($sql, $wildcards, $types, $expected) {
        $database = $GLOBALS['database'];
        
        if ($expected instanceof GrubbyException) {
            $this->setExpectedException('GrubbyException');
        }
        $result = $database->replaceWildcards($sql, $wildcards, $types);
        $this->assertEquals($expected, $result);
    }
}

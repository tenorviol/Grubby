<?php
/**
 * GrubbyTest : PHPUnit tests of the Grubby and GrubbyMDB2 libraries
 * 
 * Version 0.1
 * Copyright (c) 2009 Christopher Johnson
 */

require_once dirname(__FILE__).'/../Grubby/GrubbyDB.php';
require_once dirname(__FILE__).'/../Grubby/GrubbyDataObject.php';

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
    
    ////////// GrubbyFilter TESTS //////////
    
    public function filterTestProvider() {
        return array(
            array(42, '`id`=\'42\''),
            array(array(), 'FALSE'),
            array(false, 'FALSE'),
            array(true, true),
            array(array('foo'=>'bar'), '`foo`=\'bar\''),
            array(array('foo'=>'bar', 'chunk'=>'down'), '`foo`=\'bar\' AND `chunk`=\'down\''),
        );
    }
    
    /**
     * @dataProvider filterTestProvider
     */
    public function testGrubbyFilter($filter, $expected) {
        $filter = new GrubbyFilter($filter);
        $filter->setTable($this->test_table);
        $expression = $filter->getExpression();
        $this->assertEquals($expected, $expression);
    }
    
    ////////// GrubbyTable TESTS //////////
    
    /**
     * Read single records by primary key value.
     * Reads of this type return the row directly, not a recordset object.
     */
    public function testReadPK() {
        foreach ($this->initial_data as $row) {
            // read each initial data row back from the database
            $result = $this->test_table->read($row['id']);
            
            // compare it against the original insert
            $this->assertEquals($row, $result);
        }
    }
    
    /**
     * Create a record and read it back.
     * Create returns a GrubbyResult: affected_rows = 1 and insert_id = the last auto increment id.
     */
    public function testCreateAndRead() {
        $data = array('foo'=>'bar', 'category'=>12);
        
        // create a new database record
        $result = $this->test_table->create($data);
        
        // this should return a GrubbyResult, with affected_rows and insert_id
        $this->assertType('GrubbyResult', $result);
        $this->assertEquals(1, $result->affected_rows);
        $this->assertGreaterThan(0, $result->insert_id);
        
        // read the new record back using the insert id
        $read = $this->test_table->read($result->insert_id);
        
        // this should mostly equal the original data array
        foreach ($data as $key => $value) {
            $this->assertEquals($value, $read[$key]);
        }
    }
    
    /**
     * Create a record and read it back.
     * Create returns a GrubbyResult: affected_rows = 1 and insert_id = the last auto increment id.
     */
    public function testCreateAndReadObject() {
        $data = array('foo'=>'bar', 'category'=>12);
        $object = new stdClass;
        foreach ($data as $key=>$value) {
            $object->$key = $value;
        }
        
        // create a new database record
        $result = $this->test_table->create($object);
        
        // this should return a GrubbyResult, with affected_rows and insert_id
        $this->assertType('GrubbyResult', $result);
        $this->assertEquals(1, $result->affected_rows);
        $this->assertGreaterThan(0, $result->insert_id);
        
        // read the new record back using the insert id
        $read = $this->test_table->read($result->insert_id);
        
        // this should mostly equal the original data array
        foreach ($data as $key => $value) {
            $this->assertEquals($value, $read[$key]);
        }
    }
    
    /**
     * Create a record using an array with extra values beyond the fields in the table.
     */
    public function testCreateFromOverstuffedArray() {
        $data = array('foo'=>'bar', 'category'=>1, 'something_that_cant_possibly_be_there'=>'nothing_that_matters_anyway');
        $result = $this->test_table->create($data);
    }
    
    /**
     * Read multiple rows at once by providing an array of their primary keys.
     * @expectedException GrubbyException
     */
    public function testReadMultiplePKs() {
        $slice = array_slice($this->initial_data, 2, 3);
        $this->assertGreaterThan(2, count($slice));  // sanity check
        $pks = array();
        foreach ($slice as $row) {
            $pks[] = $row['id'];
        }
        
        // read a set of rows from the table using their primary keys
        $result = $this->test_table->read($pks);
        $all = $result->fetchAll();
        
        // compare the set to the original data slice
        $this->assertEquals($slice, $all);
    }
    
    /**
     * Read all database records.
     */
    public function testReadAll() {
        $result = $this->test_table->read();
        $all = $result->fetchAll();
        $this->assertEquals($this->initial_data, $all);
    }
    
    /**
     * Read database records using a simple filter.
     */
    public function testReadFilter() {
        foreach ($this->initial_data as $row) {
            // read a record by its 'foo' value
            // NOTE: foo values must be unique or this test will not work
            $result = $this->test_table->read(array('foo'=>$row['foo']));
            $fetch = $result->fetch();
            $this->assertEquals($row, $fetch);
        }
    }
    
    /**
     * An empty filter value should evaluate to the empty set.
     * NOTE: This is in contrast to no filter, which evaluates to the full set.
     */
    public function testReadEmptyFilter() {
        $all = $this->test_table->read(array())->fetchAll();
        $this->assertEquals(array(), $all);
    }
    
    public function testFilterExpression() {
        $all = $this->test_table->filterExpression('category=1')->read()->fetchAll();
        
        foreach ($this->initial_data as $row) {
            if ($row['category'] == 1) {
                $cat1[] = $row;
            }
        }
        $this->assertEquals($cat1, $all);
    }
    
    public function testFilterExpressionWildcard() {
        $category = 1;
        $all = $this->test_table->filterExpression('category=?', $category)->read()->fetchAll();
        
        foreach ($this->initial_data as $row) {
            if ($row['category'] == $category) {
                $cat_set[] = $row;
            }
        }
        $this->assertEquals($cat_set, $all);
    }
    
    public function testReadSlice() {
        $offset = 0;
        $count = 3;
        $slice = array_slice($this->initial_data, $offset, $count);
        
        $all = $this->test_table->slice($offset, $count)->read()->fetchAll();
        
        $this->assertEquals($slice, $all);
    }
    
    /**
     * Update and re-read records from the table.
     */
    public function testUpdatePK() {
        foreach ($this->initial_data as $row) {
            $pk = $row['id'];
            
            // before: read the row check it against initial data
            $read = $this->test_table->read($pk);
            $this->assertEquals($row, $read);
            
            // change initial data and update the row
            $change = 'update_pk_test';
            $this->assertNotEquals($change, $row['foo']); // sanity check
            $row['foo'] = $change;
            $result = $this->test_table->update($row);
            $this->assertEquals(1, $result->affected_rows);
            
            // after: read the row and check it against the modified data
            $read = $this->test_table->read($pk);
            $this->assertEquals($row, $read);
        }
    }
    
    /**
     * Attempting to update multiple rows should abort and throw a warning.
     * Why? It is a terrible thing when a programmer mistake turns into a full table rewrite.
     * Multi-row updates must be explicitly stated as such (see testUpdateAll).
     * @see testUpdateAll
     * @expectedException GrubbyException
     */
    public function testUpdateAllError() {
        $this->test_table->update(array('foo'=>'bar'));
    }
    
    /**
     * To update a set of rows, the all() qualifier must be added to the end of the query:
     * 
     * $this->test_table->all()->update(array('foo'=>'bar'));
     * $this->test_table->filterExpression('id < ?', 5)->all()->update(array('foo'=>'bar'));
     */
    public function testUpdateAll() {
        // update all foos to $change
        $change = 'update_all_test';
        $result = $this->test_table->all()->update(array('foo'=>$change));
        $affected_rows = $result->affected_rows;
        $this->assertGreaterThan(0, $affected_rows);
        
        // read them back and test
        $result = $this->test_table->read();
        $tally = 0;
        while ($row = $result->fetch()) {
            $this->assertEquals($change, $row['foo']);
            $tally++;
        }
        $this->assertEquals($affected_rows, $tally);
        $this->assertEquals($this->test_table->count(), $tally);
    }
    
    /**
     * The all() modifier can take a filter argument.
     */
    public function testUpdateAllFilter() {
        // update all foos with category=2 to $change
        $change = 'update_all_test';
        $result = $this->test_table->filter(array('category'=>2))->all()->update(array('foo'=>$change));
        $affected_rows = $result->affected_rows;
        $this->assertGreaterThan(0, $affected_rows);
        
        // read them back and test
        $result = $this->test_table->read();
        $tally = 0;
        while ($row = $result->fetch()) {
            if ($row['category'] == 2) {
                $this->assertEquals($change, $row['foo']);
                $tally++;
            }
        }
        $this->assertEquals($affected_rows, $tally);
        $this->assertEquals($this->test_table->count(array('category'=>2)), $tally);
    }
    
    /**
     * An empty all( filter ) should evaluate to the empty set.
     */
    public function testUpdateAllEmptyFilter() {
        // update no foos, the empty all filter should evaluate to empty set
        $change = 'update_all_test';
        $result = $this->test_table->filter(array())->all()->update(array('foo'=>$change));
        $affected_rows = $result->affected_rows;
        $this->assertEquals(0, $affected_rows);
        
        // read them back and make sure no changes were made
        $all = $this->test_table->read()->fetchAll();
        $this->assertEquals($this->initial_data, $all);
    }
    
    /**
     * Delete a record from the database and fail to read it back again.
     */
    public function testDeletePK() {
        foreach ($this->initial_data as $row) {
            $pk = $row['id'];
            
            // before: read the row check it against initial data
            $read = $this->test_table->read($pk);
            $this->assertEquals($row, $read);
            
            // delete the row
            $result = $this->test_table->delete($pk);
            $this->assertEquals(1, $result->affected_rows);
            
            // after: read the row and confirm it does not exist
            $read = $this->test_table->read($pk);
            $this->assertNull($read);
        }
    }
    
    /**
     * People cannot be allowed to delete their entire table as if by accident.
     * @expectedException GrubbyException
     */
    public function testDeleteAllError() {
        $this->test_table->delete();
    }
    
    /**
     * To delete multiple records, the all modifier must be appended to the query.
     */
    public function testDeleteAll() {
        $count = $this->test_table->count();
        $this->assertGreaterThan(0, $count);
        
        $this->test_table->all()->delete();
        
        $count = $this->test_table->count();
        $this->assertEquals(0, $count);
    }
    
    /**
     * Delete all records matching a filter in the delete query.
     */
    public function testDeleteAllFilter() {
        $count_all = $this->test_table->count();
        $this->assertGreaterThan(0, $count_all);
        
        $count_cat2 = $this->test_table->count(array('category'=>2));
        $this->assertGreaterThan(0, $count_cat2);
        $this->assertLessThan($count_all, $count_cat2);
        
        $result = $this->test_table->all()->delete(array('category'=>2));
        $this->assertEquals($count_cat2, $result->affected_rows);
        
        $count = $this->test_table->count();
        $this->assertEquals($count_all - $count_cat2, $count);
    }
    
    public function testDeleteAllEmptyFilter() {
        // count the rows in the table
        $count = $this->test_table->count();
        $this->assertGreaterThan(0, $count);
        
        // delete all with an empty filter (delete the empty set)
        $result = $this->test_table->all()->delete(array());
        $this->assertEquals(0, $result->affected_rows);
        
        // the count after should equal the count before (no rows deleted)
        $this->assertEquals($count, $this->test_table->count());
    }
    
    /**
     * It is also possible to sort a table programatically.
     */
    public function testSortRead() {
        // read the table rows in sorted order
        $result = $this->test_table->sort(array('foo', 'id'))->read();
        $rows = $result->fetchAll();
        
        // sort the initial data
        $sorted = $this->initial_data;
        foreach ($sorted as $data) {
            $foos[] = $data['foo'];
            $ids[] = $data['id'];
        }
        array_multisort($foos, SORT_ASC, $ids, SORT_ASC, $sorted);
        
        // compare
        $this->assertEquals($sorted, $rows);
    }
    
    /**
     * It is also possible to sort a table programatically.
     */
    public function testSortSortRead() {
        // read the table rows in sorted order (the id sort overrides the foo sort)
        $result = $this->test_table->sort('foo')->sort('id')->read();
        $rows = $result->fetchAll();
        
        // initial data comes in id order
        $sorted = $this->initial_data;
        
        // compare
        $this->assertEquals($sorted, $rows);
    }
    
    /**
     * Counting all table rows.
     */
    public function testCount() {
        $count = $this->test_table->count();
        $this->assertEquals(count($this->initial_data), $count);
    }
    
    /**
     * Counting a subset of table rows.
     */
    public function testCountFilter() {
        $tally = 0;
        foreach ($this->initial_data as $row) {
            if ($row['category'] == 2) {
                $tally++;
            }
        }
        $this->assertGreaterThan(0, $tally);
        
        // the count criteria can be injected into the count call
        $count = $this->test_table->count(array('category'=>2));
        $this->assertEquals($tally, $count);
        
        // it can also be added to the query chain
        $count = $this->test_table->filter(array('category'=>2))->count();
        $this->assertEquals($tally, $count);
    }
    
    /**
     * The empty filter defaults to the empty set.
     * Counting the empty set should return 0.
     */
    public function testCountEmptyFilter() {
        $count = $this->test_table->count(array());
        $this->assertEquals(0, $count);
    }
    
    /**
     * Test Create, Read, Update and Delete using a null value.
     */
    public function testCRUDNulls() {
        $result = $this->test_table->create(array('foo'=>null));
        $insert_id = $result->insert_id;
        
        $result = $this->test_table->read(array('foo'=>null));
        
        // look for the inserted null value and check that all rows returned contain NULLs
        $found = false;
        while ($row = $result->fetch()) {
            $this->assertNull($row['foo']);
            $found = ($found || $row['id'] == $insert_id);
        }
        $this->assertTrue($found);
    }
    
    /**
     * Test Create, Read, Update and Delete using an empty string value.
     */
    public function testCRUDEmptyStrings() {
        $result = $this->test_table->create(array('foo'=>''));
        $insert_id = $result->insert_id;
        
        $result = $this->test_table->read(array('foo'=>''));
        
        // look for the inserted null value and check that all rows returned contain empty strings
        $found = false;
        while ($row = $result->fetch()) {
            $this->assertType('string', $row['foo']);
            $found = ($found || $row['id'] == $insert_id);
        }
        $this->assertTrue($found);
    }
    
    public function testObjectRead() {
        $object_schema = $this->test_schema;
        $object_schema['class'] = 'stdClass';
        $table = new GrubbyTable($this->database, $object_schema);
        
        $set = $table->read();
        
        // test single fetch
        $object = $set->fetch();
        $this->assertType($object_schema['class'], $object);
        $row = $this->initial_data[0];
        foreach ($row as $field => $value) {
            $this->assertEquals($value, $object->$field);
        }
        
        // test fetch all
        $all = $set->fetchAll();
        foreach ($all as $object) {
            $this->assertType($object_schema['class'], $object);
        }
        
        // test primary key read
        $object = $table->read($this->initial_data[0]['id']);
        $this->assertType($object_schema['class'], $object);
        $row = $this->initial_data[0];
        foreach ($row as $field => $value) {
            $this->assertEquals($value, $object->$field);
        }
    }
    
    public function testGrubbyDataObject() {
        $object_schema = $this->test_schema;
        $object_schema['class'] = 'TestDataObject';
        $table = new GrubbyTable($this->database, $object_schema);
        TestDataObject::$grubby_query = $table;
        
        // read an object;
        $object = $table->read($this->initial_data[0]['id']);
        $this->assertType('TestDataObject', $object);
        
        // compare it to the original row
        foreach ($this->initial_data[0] as $key => $value) {
            $this->assertEquals($value, $object->$key);
        }
        
        // modify the object
        $object->foo = 'something completely different';
        $object->save();
        
        // read the row back
        $row = $this->test_table->read($object->id);
        
        // compare the read row back to the object
        foreach ($row as $key => $value) {
            $this->assertEquals($object->$key, $value);
        }
        
        // delete the row
        $result = $object->delete();
        $this->assertTrue($result);
        
        // read back a non-existant row
        $row = $this->test_table->read($object->id);
        $this->assertNull($row);
        
        // create a new row with an id
        $object->save();
        
        // read the row back and compare
        $row = $this->test_table->read($object->id);
        foreach ($row as $key => $value) {
            $this->assertEquals($object->$key, $value);
        }
        
        // create a new row without an id
        $object->id = null;
        $object->save();
        
        // read the row back and compare
        $row = $this->test_table->read($object->id);
        foreach ($row as $key => $value) {
            $this->assertEquals($object->$key, $value);
        }
    }
    
    /**
     * The fields of a result set can be set to just about anything.
     * Use either:
     *      field def
     *      alias => field def
     */
    public function testFields() {
        $all = $this->test_table->fields(array('id', 'uc_foo'=>'UPPER(foo)'))->read()->fetchAll();
        foreach ($all as $i => $row) {
            $match = array('id'=>$this->initial_data[$i]['id'], 'uc_foo'=>strtoupper($this->initial_data[$i]['foo']));
            $this->assertEquals($match, $row);
        }
    }
    
    public function testAggregate() {
        $result = $this->test_table->aggregate('category')->fields(array('category', 'tally'=>'COUNT(*)'))->read();
        $all = $result->fetchAll();
        
        $cat_tally = array();
        foreach ($this->initial_data as $row) {
            $cat_tally[$row['category']]++;
        }
        $this->assertGreaterThan(0, count($cat_tally));
        $this->assertEquals(count($cat_tally), count($all));
        
        foreach ($all as $row) {
            $this->assertEquals($cat_tally[$row['category']], $row['tally']);
            $cat_tally[$row['category']] = false;
        }
    }
    
    public function testTableNameRead() {
        $table = new GrubbyTable($this->database, $this->test_schema['name']);
        $all = $table->read()->fetchAll();
        $this->assertEquals($this->initial_data, $all);
    }
    
    /**
     * Tests features like date_created and date_modified
     */
    public function testAutoFillFields() {
        $this->markTestIncomplete();
    }
}

class TestDataObject extends GrubbyDataObject {
    
    public static $grubby_query;  // to be set by the test
    public function grubbyQuery() {
        return self::$grubby_query;
    }
}

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
require_once GRUBBY_ROOT.'/GrubbyDataObject.php';

class GrubbyTest extends PHPUnit_Framework_TestCase {
    
    private $test_table;
    
    // WARNING: This grubby_test table will get dropped and created in the test database
    private $test_schema = array('name' => 'grubby_test',
                                'primary_key' => 'id',
                                'fields' => array(
                                    array('name'=>'id',       'type'=>'INT', 'auto_increment'=>true),
                                    array('name'=>'foo',      'type'=>'VARCHAR'),
                                    array('name'=>'category', 'type'=>'INT'),
                                    array('name'=>'name',     'type'=>'VARCHAR'),                                ),
                            );
    
    // Data for pre-populating grubby_test
    private $initial_data = array(
                                array('id'=>1,  'foo'=>'Spew',         'category'=>1, 'name'=>'serius'),
                                array('id'=>2,  'foo'=>'Chunks',       'category'=>2, 'name'=>'john'),
                                array('id'=>6,  'foo'=>'But Outdoors', 'category'=>2, 'name'=>'serius'),
                                array('id'=>7,  'foo'=>'See Chris\'s Tests...\\', 'category'=>1, 'name'=>'john'),
                                array('id'=>27, 'foo'=>null,           'category'=>1, 'name'=>'alex'),
                                array('id'=>28, 'foo'=>'',             'category'=>2, 'name'=>'meyers'),
                                );
    
    /**
     * Returns the field definition from the schema.
     * @param $name of the field
     * @return array|null
     */
    private function schemaField($name) {
        foreach ($this->test_schema['fields'] as $field) {
            if ($field['name'] == $name) {
                return $field;
            }
        }
        return null;
    }
    
    /**
     * Create the grubby_test table,
     * and populate with the initial data.
     */
    public function setUp() {
    	$this->test_schema['database'] = $GLOBALS['database'];
        $this->test_table = new GrubbyTable($this->test_schema);
        
        $this->test_table->dropTable();
        $this->test_table->createTable();
        
        // populate grubby_test with initial data
        foreach ($this->initial_data as $row) {
            $this->test_table->create($row);
        }
    }
    
    /**
     * Drop the grubby_test table.
     */
    public function tearDown() {
        $this->test_table->dropTable();
    }
    
    ////////// GrubbyFilter TESTS //////////
    
    /**
     * Filter tests.
     * (filter, expected result)
     * 
     * @return array
     */
    public function filterTestProvider() {
        return array(
            array(42, 'id=\'42\''),
            array(array(), 'FALSE'),
            array(false, 'FALSE'),
            array(true, true),
            array(array('foo'=>'bar'), 'foo=\'bar\''),
            array(array('foo'=>'bar', 'chunk'=>'down'), 'foo=\'bar\' AND chunk=\'down\''),
        );
    }
    
    /**
     * Create a new filter and check that the resulting sql is as expected.
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
     * Read a single record by its primary key value.
     * This type of read returns the row directly, not a recordset object.
     */
    public function testReadPK() {
        foreach ($this->initial_data as $row) {
            $result = $this->test_table->read($row['id']);  // read each initial data row
            $this->assertEquals($row, $result);             // compare against the original
        }
    }
    
    /**
     * Tests for creating a new data row
     * and reading the data back.
     * @return array
     * @see testCreateAndRead
     */
    public function createAndReadProvider() {
        $data = array('foo'=>'bar', 'category'=>12);
        
        $object = new stdClass;
        foreach ($data as $key=>$value) {
            $object->$key = $value;
        }
        
        $fat_array = $data;
        $fat_array['not_a_field'] = 'whatever';
        
        $fat_object = new stdClass;
        foreach ($fat_array as $key=>$value) {
            $fat_object->$key = $value;
        }
        
        return array(
            array($data),        // create a record from an array
            array($object),      // create a record from an object
            array($fat_array),   // use an array with extra non-table data
            array($fat_object),  // use an object with extra non-table data
            );
    }
    
    /**
     * Create a new record,
     * read it back and compare.
     * @dataProvider createAndReadProvider
     */
    public function testCreateAndRead($new) {
        // create a new database record
        $result = $this->test_table->create($new);
        
        // this should return a GrubbyResult,
        // with affected_rows = 1 and the auto-increment insert_id
        $this->assertType('GrubbyResult', $result);
        $this->assertEquals(1, $result->affected_rows);
        $this->assertGreaterThan(0, $result->insert_id);
        
        // read the new record back using the insert_id
        $read = $this->test_table->read($result->insert_id);
        
        // compare the create against the read data
        foreach ($new as $key => $value) {
            // only test values that have corresponding fields
            if ($this->schemaField($key)) {
                $this->assertEquals($value, $read[$key]);
            }
        }
    }
    
    /**
     * Read all records,
     * and compare against the initial data.
     */
    public function testReadAll() {
        $result = $this->test_table->read();
        $all = $result->fetchAll();
        $this->assertEquals($this->initial_data, $all);
    }
    
    /**
     * Read tests using various filters (filter, expected result).
     * @return array
     * @see testReadFilter
     */
    public function readFilterProvider() {
        $tests = array();
        
        // find rows with a specific foo value
        $foos = array();
        foreach ($this->initial_data as $row) {
            $foo = $row['foo'];
            if (empty($foos[$foo])) {
                $foos[$foo] = true;
                $filter = array('foo'=>$foo);
                $expected = array();
                foreach ($this->initial_data as $result) {
                    if ($result['foo'] === $foo) {
                        $expected[] = $result;
                    }
                }
                $tests[] = array($filter, $expected);
            }
        }
        
        // boolean filters
        $tests[] = array(false, array());             // filter false equals the empty set
        $tests[] = array(true, $this->initial_data);  // filter true equals the full set
        
        // compound filters
        foreach ($this->initial_data as $model) {
            $category = $model['category'];
            $name = $model['name'];
            $expected = array();
            foreach ($this->initial_data as $row) {
                if (($row['category'] == $category && $row['name'] == $name)) {
                    $expected[] = $row;
                }
            }
            $tests[] = array(array('category'=>$category, 'name'=>$name), $expected);
        }
        
        // empty filters; these are considered errors and return the empty set
        $tests[] = array(null, array());
        $tests[] = array(array(), array());
        
        return $tests;
    }
    
    /**
     * Read all records that satisfy a filter,
     * and compare them to the set of expected records.
     * @dataProvider readFilterProvider
     */
    public function testReadFilter($filter, $expected) {
        $result = $this->test_table->read($filter);
        $fetch = $result->fetchAll();
        $this->assertEquals($expected, $fetch);
    }
    
    /**
     * Read tests using various not filters (not filter, expected results).
     * @return array
     * @see testReadNotFilter
     */
    public function readNotFilterProvider() {
        $tests = array();
        
        // rows with other than a certain primary key
        foreach ($this->initial_data as $row) {
            $id = $row['id'];
            $expected = array();
            foreach ($this->initial_data as $result) {
                if ($result['id'] != $id) {
                    $expected[] = $result;
                }
            }
            $tests[] = array($id, $expected);
        }
        
        // rows of other than a specific category
        $cats = array();
        foreach ($this->initial_data as $row) {
            $category = $row['category'];
            if (empty($cats[$category])) {
                $cats[$category] = true;
                $filter = array('category'=>$category);
                $expected = array();
                foreach ($this->initial_data as $result) {
                    if ($result['category'] !== $category) {
                        $expected[] = $result;
                    }
                }
                $tests[] = array($filter, $expected);
            }
        }
        
        // boolean filters
        $tests[] = array(false, $this->initial_data);  // not false produces the full set
        $tests[] = array(true, array());               // not true produces the empty set
        
        // compound not filters
        foreach ($this->initial_data as $model) {
            $category = $model['category'];
            $name = $model['name'];
            $expected = array();
            foreach ($this->initial_data as $row) {
                if (!($row['category'] == $category && $row['name'] == $name)) {
                    $expected[] = $row;
                }
            }
            $tests[] = array(array('category'=>$category, 'name'=>$name), $expected);
        }
        
        // empty filters; these are considered errors and return the empty set
        $tests[] = array(null, array());
        $tests[] = array(array(), array());
        
        return $tests;
    }
    
    /**
     * The not filter limits the result set to rows not satisfying the filter.
     * @dataProvider readNotFilterProvider
     */
    public function testReadNotFilter($filter, $expected) {
        $query = $this->test_table->not($filter);
        $all = $query->read()->fetchAll();
        $this->assertEquals($expected, $all);
    }
    
    /**
     * Read tests using an expression for filtering.
     * @return array
     * @see testFilterExpression
     */
    public function filterExpressionProvider() {
        $tests = array();
        
        // id range tests
        $expected = array();
        foreach ($this->initial_data as $row) {
            if ($row['id'] < 20) {
                $expected[] = $row;
            }
        }
        $tests[] = array('id < 20', null, $expected);
        $tests[] = array('id < ?', 20, $expected);
        $tests[] = array('id < ?', array(20), $expected);
        
        // foo like test
        $expected = array();
        foreach ($this->initial_data as $row) {
            if (isset($row['foo'][0]) && $row['foo'][0] == 'S') {
                $expected[] = $row;
            }
        }
        $tests[] = array('foo LIKE ?', 'S%', $expected);
        
        return $tests;
    }
    
    /**
     * The filter expression limits the set to records matching a custom SQL expression.
     * @dataProvider filterExpressionProvider
     */
    public function testFilterExpression($expression, $wildcards, $expected) {
        $query = $this->test_table->filterExpression($expression, $wildcards);
        $all = $query->read()->fetchAll();
        $this->assertEquals($expected, $all);
    }
    
    /**
     * Reads slicing a subset of records out of the query.
     * @return array
     * @see testReadSlice
     */
    public function readSliceProvider() {
        $tests = array();
        $tests[] = array(0, 3);
        $tests[] = array(1, 1);
        $tests[] = array(10, 10);
        $tests[] = array(1, 100);
        return $tests;
    }
    
    /**
     * The slice method limits the set to a subset or chunk of the full set.
     * @dataProvider readSliceProvider
     */
    public function testReadSlice($offset, $count) {
        $expected = array_slice($this->initial_data, $offset, $count);
        $query = $this->test_table->slice($offset, $count);
        $all = $query->read()->fetchAll();
        $this->assertEquals($expected, $all);
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
            
            // add an extra column (this should not get saved to the database)
            $row['overload'] = 'bar';
            
            $result = $this->test_table->update($row);
            $this->assertEquals(1, $result->affected_rows);
            
            // remove the over-filled row before the comparison test
            unset($row['overload']);
            
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
    
    /**
     * Create a new schema with 'class' set.
     * 
     */
    public function testObjectRead() {
        $object_schema = $this->test_schema;
        $object_schema['class'] = 'stdClass';
        $table = new GrubbyTable($object_schema);
        
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
        $table = new GrubbyTable($object_schema);
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
            @$cat_tally[$row['category']]++;
        }
        $this->assertGreaterThan(0, count($cat_tally));
        $this->assertEquals(count($cat_tally), count($all));
        
        foreach ($all as $row) {
            $this->assertEquals($cat_tally[$row['category']], $row['tally']);
            $cat_tally[$row['category']] = false;
        }
    }
    
    public function testTableNameRead() {
        $table = new GrubbyTable(array('name'=>$this->test_schema['name'], 'database'=>$this->test_schema['database']));
        $all = $table->read()->fetchAll();
        $this->assertEquals($this->initial_data, $all);
    }
    
    /**
     * Tests features like date_created and date_modified
     */
    public function testAutoFillFields() {
        // modify the test schema to include auto-fill fields
        $schema = $this->test_schema;
        $schema['fields'][] = array('name'=>'created', 'type'=>'DATETIME', 'auto'=>GRUBBY_AUTO_CREATE_DATE);
        $schema['fields'][] = array('name'=>'modified', 'type'=>'DATETIME', 'auto'=>GRUBBY_AUTO_UPDATE_DATE);
        $schema['fields'][] = array('name'=>'ipaddr_created', 'type'=>'VARCHAR', 'size'=>255, 'auto'=>GRUBBY_AUTO_CREATE_REMOTE_ADDR);
        $schema['fields'][] = array('name'=>'ipaddr_modified', 'type'=>'VARCHAR', 'size'=>255, 'auto'=>GRUBBY_AUTO_UPDATE_REMOTE_ADDR);
        
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        
        // drop and recreate the table
        $table = new GrubbyTable($schema);
        $table->dropTable();
        $table->createTable();
        
        // get database time before creation
        $result = $schema['database']->query('SELECT NOW() as now')->fetch();
        $before_create = strtotime($result['now']);
        
        // create initial data rows
        foreach ($this->initial_data as $row) {
            $table->create($row);
        }
        
        // get database time after creation
        $result = $schema['database']->query('SELECT NOW() as now')->fetch();
        $after_create = strtotime($result['now']);
        // test each row for auto-field values
        $read = $table->read();
        while ($row = $read->fetch()) {
            $this->assertGreaterThanOrEqual($before_create, strtotime($row['created']));
            $this->assertLessThanOrEqual($after_create, strtotime($row['created']));
            $this->assertGreaterThanOrEqual($before_create, strtotime($row['modified']));
            $this->assertLessThanOrEqual($after_create, strtotime($row['modified']));
            $this->assertEquals($_SERVER['REMOTE_ADDR'], $row['ipaddr_created']);
            $this->assertEquals($_SERVER['REMOTE_ADDR'], $row['ipaddr_modified']);
        }
        
        // sleep 1 sec, change auto values
        sleep(1);
        $_SERVER['REMOTE_ADDR'] = 'Something that cannot be an ip';
        
        // get database time before update
        $result = $schema['database']->query('SELECT NOW() as now')->fetch();
        $before_update = strtotime($result['now']);
        
        // update all rows
        $table->all()->update(array('foo'=>'bar'));
        
        // get database time after update
        $result = $schema['database']->query('SELECT NOW() as now')->fetch();
        $after_update = strtotime($result['now']);
        
        // test each row for auto-field values
        $read = $table->read();
        while ($row = $read->fetch()) {
            $this->assertGreaterThanOrEqual($before_create, strtotime($row['created']));
            $this->assertLessThanOrEqual($after_create, strtotime($row['created']));
            $this->assertGreaterThanOrEqual($before_update, strtotime($row['modified']));
            $this->assertLessThanOrEqual($after_update, strtotime($row['modified']));
            $this->assertEquals('127.0.0.1', $row['ipaddr_created']);
            $this->assertEquals($_SERVER['REMOTE_ADDR'], $row['ipaddr_modified']);
        }
    }
}

class TestDataObject extends GrubbyDataObject {
    
    public static $grubby_query;  // to be set by the test
    public function grubbyQuery() {
        return self::$grubby_query;
    }
}

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

class GrubbyJoinTest extends PHPUnit_Framework_TestCase {
    
    /**
     * Initialize the test database.
     */
    public function setUp() {
        $database = TestDatabase::instance();
        $database->initialize();
    }
    
    public function testJoin() {
        $database = TestDatabase::instance();
        $user_table = $database->userTable();
        $category_table = $database->categoryTable();
        $user_category_join = $user_table->join($category_table, 'category_id');
        
        $categories = $database->getCategories();
        $all = $user_category_join->read()->fetchAll();
        foreach ($all as $user) {
            $this->assertEquals($categories[$user->category_id], $user->category);
        }
    }
}

class TestDatabase {
    /**
     * @return TestDatabase
     */
    public static function instance() {
        static $instance = null;
        if ($instance === null) {
            $instance = new TestDatabase();
        }
        return $instance;
    }
    
    /**
     * Drop and create all test tables.
     * Populate each table with test data.
     */
    public function initialize() {
        $table = $this->categoryTable();
        $table->dropTable();
        $table->createTable();
        $table->create(array('category_id'=>1, 'category'=>'Active'));
        $table->create(array('category_id'=>2, 'category'=>'Inactive'));
        $table->create(array('category_id'=>3, 'category'=>'Unknown'));
        
        $table = $this->userTable();
        $table->dropTable();
        $table->createTable();
        $table->create(array('user_id'=>1, 'name'=>'Chris', 'category_id'=>1));
        $table->create(array('user_id'=>2, 'name'=>'Brian', 'category_id'=>1));
        $table->create(array('user_id'=>3, 'name'=>'Jim',   'category_id'=>3));
        
        $table = $this->groupTable();
        $table->dropTable();
        $table->createTable();
        $table->create(array('group_id'=>1, 'name'=>'Surfers'));
        $table->create(array('group_id'=>2, 'name'=>'Men'));
        $table->create(array('group_id'=>3, 'name'=>'Dads'));
        $table->create(array('group_id'=>4, 'name'=>'Women'));
        
        $table = $this->userGroupTable();
        $table->dropTable();
        $table->createTable();
        $table->create(array('user_id'=>1, 'group_id'=>1));
        $table->create(array('user_id'=>2, 'group_id'=>1));
        $table->create(array('user_id'=>1, 'group_id'=>2));
        $table->create(array('user_id'=>2, 'group_id'=>2));
        $table->create(array('user_id'=>3, 'group_id'=>2));
        $table->create(array('user_id'=>2, 'group_id'=>3));
        $table->create(array('user_id'=>3, 'group_id'=>3));
    }
    
    /**
     * @return GrubbyDatabase
     */
    public function getDatabase() {
        // The global database object should be set in the config file.
        return $GLOBALS['database'];
    }
    
    private $user_table = null;
    private $category_table;
    private $group_table;
    private $user_group_table;
    
    /**
     * @return GrubbyTable
     */
    public function userTable() {
        if ($this->user_table === null) {
            $schema = array('name' => 'grubby_user',
                            'database' => $this->getDatabase(),
                            'primary_key' => 'user_id',
                            'fields' => array(
                                array('name'=>'user_id',     'type'=>'INT', 'auto_increment'=>true),
                                array('name'=>'name',        'type'=>'VARCHAR', 'size'=>40),
                                array('name'=>'category_id', 'type'=>'INT'),
                            ),
                            'class' => 'User');
            $this->user_table = new GrubbyTable($schema);
        }
        return $this->user_table;
    }
    
    /**
     * @return GrubbyTable
     */
    public function categoryTable() {
        if ($this->category_table === null) {
            $schema = array('name' => 'grubby_category',
                            'database' => $this->getDatabase(),
                            'primary_key' => 'category_id',
                            'fields' => array(
                                array('name'=>'category_id', 'type'=>'INT', 'auto_increment'=>true),
                                array('name'=>'category',    'type'=>'VARCHAR', 'size'=>40),
                            ),
                            'class' => 'Category');
            $this->category_table = new GrubbyTable($schema);
        }
        return $this->category_table;
    }
    
    /**
     * All categories organized in the array by primary key.
     * @return array
     */
    public function getCategories() {
        $categories = array();
        $all = $this->categoryTable()->read()->fetchAll();
        foreach ($all as $category) {
            $categories[$category->category_id] = $category->category;
        }
        return $categories;
    }
    
    /**
     * @return GrubbyTable
     */
    public function groupTable() {
        if ($this->group_table === null) {
            $schema = array('name' => 'grubby_group',
                            'database' => $this->getDatabase(),
                            'primary_key' => 'group_id',
                            'fields' => array(
                                array('name'=>'group_id',  'type'=>'INT', 'auto_increment'=>true),
                                array('name'=>'name',      'type'=>'VARCHAR', 'size'=>40),
                            ),
                            'class' => 'Group');
            $this->group_table = new GrubbyTable($schema);
        }
        return $this->group_table;
    }
    
    /**
     * @return GrubbyTable
     */
    public function userGroupTable() {
        if ($this->user_group_table === null) {
            $schema = array('name' => 'grubby_user_group',
                            'database' => $this->getDatabase(),
                            'primary_key' => array('user_id', 'group_id'),
                            'fields' => array(
                                array('name'=>'user_id',  'type'=>'INT'),
                                array('name'=>'group_id', 'type'=>'INT'),
                                array('name'=>'admin',    'type'=>'BOOLEAN'),
                            ),
                            'class' => __CLASS__);
            $this->user_group_table = new GrubbyTable($schema);
        }
        return $this->user_group_table;
    }
}

class User {
}

class Category {
}

class Group {
}

class UserGroup {
}

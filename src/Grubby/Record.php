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

abstract class Grubby_Record {
    
    /**
     * Return the GrubbyQuery object used for retrieval and storage of this object.
     * @return GrubbyQuery
     */
    public abstract function grubbyQuery();
    
    /**
     * Creates or updates this object using the grubbyQuery().
     * @throws GrubbyException if a create/update error occurs
     */
    public function save() {
        $query = $this->grubbyQuery();
        $pk = $query->primaryKey();
        
        // if there is a primary key, try updating
        $insert = true;
        if (isset($this->$pk) && $this->$pk) {
            $result = $query->update($this);
            if ($result->affected_rows == 1) {
                $insert = false;
            }
        }
        
        // if updating didn't work out, try inserting
        if ($insert) {
            $result = $query->create($this);
            if (empty($this->$pk)) {
                $this->$pk = $result->insert_id;
            }
        }
        
        // something strange happened, throw an exception
        if (!$result->affected_rows == 1) {
            throw new Grubby_Exception('Attempted to update and insert the object, but the database returned 0 affected rows.');
        }
    }
    
    /**
     * Removes the row in the grubbyQuery() corresponding to this object's primary key value.
     * @return boolean removed one row vs. nothing removed
     * @throws GrubbyException if a delete error occurs
     */
    public function delete() {
        $query = $this->grubbyQuery();
        $pk = $query->primaryKey();
        $result = $query->delete($this->$pk);
        return ($result->affected_rows == 1);
    }
}

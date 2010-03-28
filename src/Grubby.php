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


/**
 * 
 */
class Grubby {
    public static $debug = false;
    public static $time = 0;
    
    public static function debugMessage($message) {
        if (empty($_SERVER['REQUEST_URI'])) {
            print 'Grubby: '.$message."\n";
        } else {
            print '<div class="grubby debug">'.htmlspecialchars($message).'</div>';
        }
    }
}

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

class Grubby_NotFilter extends Grubby_Filter {
	public function getExpression() {
		return 'NOT ('.parent::getExpression().')';
	}
}

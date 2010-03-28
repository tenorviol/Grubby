<?php

class Grubby_NotFilter extends Grubby_Filter {
    public function getExpression() {
        return 'NOT ('.parent::getExpression().')';
    }
}

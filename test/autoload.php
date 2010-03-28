<?php

function __autoload($class) {
	static $lib = null;
	if (!isset($lib)) {
		$lib = realpath(dirname(__FILE__).'/../src');
	}
	include $lib.'/'.str_replace('_', '/', $class).'.php';
}

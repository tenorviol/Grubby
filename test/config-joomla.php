<?php 

define('JPATH_BASE', dirname(__FILE__).'/../lib/Joomla');

require_once JPATH_BASE.'/libraries/loader.php';
require_once JPATH_BASE.'/libraries/joomla/base/object.php';
require_once JPATH_BASE.'/libraries/joomla/database/database.php';
require_once JPATH_BASE.'/libraries/joomla/error/error.php';
require_once JPATH_BASE.'/libraries/joomla/factory.php';
require_once JPATH_BASE.'/libraries/joomla/methods.php';
require_once JPATH_BASE.'/libraries/joomla/registry/registry.php';

// the location of the Grubby library
define('GRUBBY_ROOT', realpath(dirname(__FILE__).'/../src'));

require_once GRUBBY_ROOT.'/Grubby.php';
require_once GRUBBY_ROOT.'/GrubbyJoomla.php';

if ($GLOBALS['database']) {
    echo basename(__FILE__).': replacing $GLOBALS[\'database\']'."\n";
}
$GLOBALS['database'] = new GrubbyJoomla(array(
    'dbtype'     => 'mysql',
    'host'       => ':/tmp/mysql.sock',
    'user'       => 'foo',
    'password'   => 'foo',
    'database'   => 'foo',
    'dbprefix'   => 'jos_'
));

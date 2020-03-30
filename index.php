<?php
/******************************************************************************
 *
 * Codezilla PHP Framework
 * Author  : Nathan O'Brennan
 * Email   : nathan@codezilla.xyz
 * Date    : Sun 22 Mar 2020 02:12:11 AM CDT
 * Website : https://codezilla.xyz
 * Version : 1.0
 *
******************************************************************************/

/******************************************************************************
 *
 * The .htaccess file is mandatory, if it is not available we must exit
 *
******************************************************************************/
if (!file_exists('.htaccess')) {
    die('The .htaccess file does not exist. The system will not function as intended...');
}

/******************************************************************************
 *
 * Start the framework
 *
******************************************************************************/
define('START_TIME', microtime(true));

/******************************************************************************
 *
 * Establish some internal defaults
 *
******************************************************************************/
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
ini_set('register_globals', 'Off');
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);

/******************************************************************************
 *
 * -- Domain Configuration --
 *
 * We define a DOMAIN here to use later in detection of various defined
 * environments, such as development, test, and production
 *
******************************************************************************/
if (isset($_SERVER['SERVER_NAME']) && !empty($_SERVER['SERVER_NAME'])) {
    define('DOMAIN', $_SERVER['SERVER_NAME']);
} else {
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo 'The server is not providing a server name or virtual host. $_SERVER[\'SERVER_NAME\'] is not available.';
    exit(1);
}

/******************************************************************************
 *
 * Establish system-wide defaults for basic file security and sanity
 *
******************************************************************************/
if (defined('STDIN')) {
    chdir(dirname(__FILE__));
}

// Configure basic file system paths
$doc_root = dirname(__FILE__);
if (!is_dir($doc_root)) {
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo 'Your system folder path does not appear to be set correctly. Please open the following file and correct this: ' . pathinfo(__FILE__, PATHINFO_BASENAME);
    exit(3);
}

define('SELF', pathinfo(__FILE__, PATHINFO_BASENAME));
define('BASEPATH', $doc_root);
define('CORE',      BASEPATH . DIRECTORY_SEPARATOR . 'core');
define('COMMON',    BASEPATH . DIRECTORY_SEPARATOR . 'common');
define('CONFIG',    CORE     . DIRECTORY_SEPARATOR . 'config');
define('CLASSES',   CORE     . DIRECTORY_SEPARATOR . 'classes');
define('SYSTEM',    BASEPATH . DIRECTORY_SEPARATOR . 'system');
define('MODULES',   BASEPATH . DIRECTORY_SEPARATOR . 'modules');
define('PLUGINS',   BASEPATH . DIRECTORY_SEPARATOR . 'plugins');
define('VENDORS',   COMMON   . DIRECTORY_SEPARATOR . 'vendors');

// if you want to see what is being built here
//echo '<br>SELF:     '.SELF;      // index.php
//echo '<br>BASEPATH: '.BASEPATH;  // /var/www/html
//echo '<br>CORE:     '.CORE;      // /var/www/html/core
//echo '<br>CONFIG:   '.CONFIG;    // /var/www/html/core/config
//echo '<br>CLASSES:  '.CLASSES;   // /var/www/html/core/classes
//echo '<br>SYSTEM:   '.SYSTEM;    // /var/www/html/system
//echo '<br>MODULES:  '.MODULES;   // /var/www/html/modules
//echo '<br>PLUGINS:  '.PLUGINS;   // /var/www/html/plugins
//echo '<br>COMMON:   '.COMMON;    // /var/www/html/common
//die;

// For testing during development, keep this handy
// Uncomment the following to automatically download the vault
//if (!session_id()) {
//    session_name('Codezilla');
//    session_start();
//}
//$_SESSION['registration_email']     = 'jimcrow@gmail.com';
//$_SESSION['activation_code']        = '20190822-173932-XXX000-XX00XX00-000-XXX';
//$_SESSION['recovery_code']          = 'KMyEowML3ctjYi';

/******************************************************************************
 *
 * Require the Codezilla framework bootstrap file
 *
******************************************************************************/
ob_start();
require_once(CORE . DIRECTORY_SEPARATOR . 'Codezilla.php');

/******************************************************************************
 *
 * End of the framework
 *
******************************************************************************/
ob_end_flush();

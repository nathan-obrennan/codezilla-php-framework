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
defined('BASEPATH') OR exit('No direct script access allowed');

/******************************************************************************
 *
 * Codezilla bootstrap
 *
 * This bootstrap will load the required and necessary files to instantiate
 * the default class objects, along with all classes in the auto load.
 *
 * All objects instantiated will then be available within the $code-> super object.
 *
 * DEBUG = false
 * When DEBUG = true lots of debug info will be spit out, but be warned,
 * sessions will be corrupted and not work correctly because headers
 * will be sent before the storage class instantiates and starts the session.
 *
******************************************************************************/
define('FRAMEWORK_NAME', 'Codezilla PHP Framework');
define('CODEZILLA_VERSION', '1.0.0');
define('DEBUG', false);

/******************************************************************************
 *
 * -- Codezilla Configuration --
 *
 * This file contains framework specific variables and definitions that must
 * be set for proper operation. If you need to place custom variables or
 * definitions, including pulling in additional libraries, use the Vault
 * located in the the COMMON folder.
 *
******************************************************************************/

/******************************************************************************
 *
 * Basic functions necessary for the proper operation of the framework.
 *
******************************************************************************/
define('SYSTEMLOG', BASEPATH . DIRECTORY_SEPARATOR . 'logfile.csv');
require_once(CORE . DIRECTORY_SEPARATOR . 'Functions.php');
log_message('debug', 'startup', '########################################################################################################################', true);
/******************************************************************************
 *
 * -- Auto Loader --
 *
 * Not all classes of the framework are instantiated automatically. If
 * you need specific classes loaded on bootup include them
 * here and they will always be available. This can be used to load
 * non-framework classes per your application, after the framework has loaded,
 * but before the module has been rendered. Once the framework is ready to
 * execute the active module controller these classes will be available.
 *
 *****************************************************************************/
define('CLASS_AUTOLOAD', serialize(array()));

/******************************************************************************
 *
 * -- Internal Routes --
 *
 * Internal routes belong inside the SYSTEM folder instead of the modules
 * folder allowing separation of modules for security or convenience.
 *
******************************************************************************/
$dirs = scandir(SYSTEM);
$sys_dirs = array();
foreach ($dirs as $dir) {
    if ($dir != '.' && $dir != '..') {
        if (is_dir(SYSTEM . DIRECTORY_SEPARATOR . $dir)) {
            $sys_dirs[] = $dir;
        }
    }
}
define('ROUTES', serialize($sys_dirs));

/******************************************************************************
 *
 * Establish class paths for oncoming classes and the primary master class
 *
******************************************************************************/
set_include_path(get_include_path() . PATH_SEPARATOR . CLASSES);
set_include_path(get_include_path() . PATH_SEPARATOR . COMMON . DIRECTORY_SEPARATOR . 'classes');
set_include_path(get_include_path() . PATH_SEPARATOR . COMMON . DIRECTORY_SEPARATOR . 'vendors');
require_once('class.Codezilla.php');

/******************************************************************************
 *
 * Instantiate the Codezilla super object
 *
******************************************************************************/
$vault_db = CONFIG . DIRECTORY_SEPARATOR . 'config.Codezilla.db';
$code = new Codezilla(array('vault_db' => $vault_db, 'debug' => DEBUG));

/******************************************************************************
 *
 * Instantiate the Security class
 *
******************************************************************************/
$code->loadClass('Security');

/******************************************************************************
 *
 * Instantiate the Database class
 *
******************************************************************************/
// now use the database information from the vault to connect to the database
$code->vault = $code->loadClass('Database', null, array('security', 'adaptor' => 'PDO', 'driver' => 'sqlite', 'db_file' => $vault_db), 'vault');
if (!$code->vault->active()) {
    if ($code->vault->connect()) {
        if (!$code->_initialize()) {
            die('could not initialize the framework');
        }
    }
}

/******************************************************************************
 *
 * Instantiate the Mail class
 *
******************************************************************************/
$code->loadClass('Mail', null, array('security', 'environment'));

/******************************************************************************
 *
 * Instantiate the Storage class
 *
******************************************************************************/
$code->loadClass('Storage', null,
    array(
        'database',
        'security',
        'cookie'            => $code->site_cookie,
        'cookie_lifetime'   => $code->cookie_lifetime,
        'cookie_path'       => $code->cookie_path,
        'cookie_domain'     => $code->cookie_domain,
        'cookie_secure'     => $code->cookie_secure,
        'ipstack_key'       => $code->ipstack_api_key,
        'remote_ip'         => $code->remote_ip,
        'session_id'        => $code->session_id
    )
);

/******************************************************************************
 *
 * Capture and store redirects
 *
******************************************************************************/
if (isset($_GET['redirect'])) {
    $code->storage->set('redirect', $_GET['redirect']);
}

/******************************************************************************
 *
 * Instantiate the Input class
 *
******************************************************************************/
$code->loadClass('Input', null, array('security'));

/******************************************************************************
 *
 * Instantiate the Router class
 *
******************************************************************************/
$code->loadClass('Router', null, array('input'));

/******************************************************************************
 *
 * System Maintenance
 *
 * If the logged in user is the super admin then we should allow
 * continued operation of the site, otherwise, halt processing.
 *
 * What exactly does "Maintenance" mean to the framework? What stops
 * working like normal and what continues?
 *
 *  -- STOPS --
 *  cron
 *  plugins
 *  modules
 *
******************************************************************************/
//if ($code->maintenance) {
//    if (!$code->isSuperAdmin()) {
//        if (!$code->isEmergencyOverride()) {
//            refresh(30, 'The system is currently down for maintenance. Please try again later...');
//            die();
//        }
//    }
//}

/******************************************************************************
 *
 * Instantiate the CRON class
 *
******************************************************************************/
$code->loadClass('Cron', null,
    array(
        'database',
        'cron_token' => $code->cron_token,
        'module'    => $code->router->module,
        'script'    => $code->router->request_uri
    )
);

/******************************************************************************
 *
 * Instantiate the Authentication class
 *
******************************************************************************/
$code->loadClass('Authentication', null, array('database', 'security'));

/******************************************************************************
 *
 * Instantiate the Plugins class
 *
******************************************************************************/
$code->loadClass('Plugins', null, array('database'));

// pre hooks?

/******************************************************************************
 *
 * Instantiate the Plugins class
 *
******************************************************************************/
$code->loadClass('Modules', null, array('database', 'router'));

/******************************************************************************
 *
 * Instantiate the Users class
 *
******************************************************************************/
$code->loadClass('Users', null,
    array(
        'database',
        'mail',
        'router',
        'security',
        'storage',
        'site_name'     => $code->site_name,
        'noreply'       => $code->noreply,            // No Reply email user
        'registration'  => $code->registration,       // Registration email user
        'support'       => $code->support             // Customer Support email user
    )
);

/******************************************************************************
 *
 * Instantiate the Application Classes
 *
******************************************************************************/
// Establish the primary autoloader now that we are ready
spl_autoload_register('codezilla_autoloader');

// Additional manual class loads can be added below


/******************************************************************************
 *
 * Load the Theme config
 *
******************************************************************************/
$code->loadTheme();

/******************************************************************************
 *
 * Execute the controller
 *
******************************************************************************/
$code->execute();

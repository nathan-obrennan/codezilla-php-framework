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
if ( !file_exists( '.htaccess' ))
{
    die( 'The .htaccess file does not exist. The system will not function as intended...' );
}

/******************************************************************************
 *
 * Start the framework
 *
******************************************************************************/
define( 'START_TIME', microtime( true ));

/******************************************************************************
 *
 * Establish some internal defaults
 *
******************************************************************************/
mb_internal_encoding( 'UTF-8' );
mb_http_output( 'UTF-8' );
ini_set( 'register_globals', 'Off' );
ini_set( 'session.use_cookies', 1 );
ini_set( 'session.use_only_cookies', 1 );

/******************************************************************************
 *
 * -- Domain Configuration --
 *
 * We define a DOMAIN here to use later in detection of various defined
 * environments, such as development, test, and production
 *
******************************************************************************/
if ( isset( $_SERVER['SERVER_NAME']) && !empty( $_SERVER['SERVER_NAME'] ))
{
    define( 'DOMAIN', $_SERVER['SERVER_NAME'] );
}
else {
    header( 'HTTP/1.1 503 Service Unavailable.', true, 503 );
    echo 'The server is not providing a server name or virtual host. $_SERVER[\'SERVER_NAME\'] is not available.';
    exit( 1 );
}

/******************************************************************************
 *
 * Establish system-wide defaults for basic file security and sanity
 *
******************************************************************************/
if ( defined( 'STDIN' ))
{
    chdir( dirname( __FILE__ ));
}

$base_path = dirname( __FILE__ );

if (( $_temp = realpath( $base_path )) !== FALSE )
{
    $base_path = $_temp . DIRECTORY_SEPARATOR;
}
else {
    // Ensure there's a trailing slash
    $base_path = strtr( rtrim( $base_path, '/\\' ), '/\\', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
}

// Configure basic file system paths
if ( !is_dir( $base_path ))
{
    header( 'HTTP/1.1 503 Service Unavailable.', true, 503 );
    echo 'Your system folder path does not appear to be set correctly. Please open the following file and correct this: ' . pathinfo( __FILE__, PATHINFO_BASENAME );
    exit( 3 );
}

define( 'SELF',         pathinfo( __FILE__, PATHINFO_BASENAME ));   // index.php
define( 'SYSTEM_PATH',  basename( $base_path ));           // this is the actual directory the application lives in
define( 'BASEPATH',     dirname( $base_path ) . DIRECTORY_SEPARATOR . SYSTEM_PATH );      // /var/www/html/domain.tld
define( 'DOCROOT',      $_SERVER['DOCUMENT_ROOT'] );

define( 'CORE',         BASEPATH . DIRECTORY_SEPARATOR . 'core'     );
define( 'COMMON',       BASEPATH . DIRECTORY_SEPARATOR . 'common'   );
define( 'CONFIG',       CORE     . DIRECTORY_SEPARATOR . 'config'   );
define( 'CLASSES',      CORE     . DIRECTORY_SEPARATOR . 'classes'  );
define( 'SYSTEM',       BASEPATH . DIRECTORY_SEPARATOR . 'system'   );
define( 'MODULES',      BASEPATH . DIRECTORY_SEPARATOR . 'modules'  );
define( 'PLUGINS',      BASEPATH . DIRECTORY_SEPARATOR . 'plugins'  );
define( 'VENDORS',      COMMON   . DIRECTORY_SEPARATOR . 'vendors'  );

/******************************************************************************
 *
 * Require the Codezilla framework bootstrap file
 *
******************************************************************************/
ob_start();
require_once( CORE . DIRECTORY_SEPARATOR . 'Codezilla.php' );

/******************************************************************************
 *
 * End of the framework
 *
******************************************************************************/
ob_end_flush();

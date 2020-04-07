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
 *****************************************************************************/
defined('BASEPATH') OR exit('No direct script access allowed');

/******************************************************************************
 *
 * Codezilla classes
 *
 * classes must be named in StudlyCaps
 * methods must be named in camelCase
 * properties must be named in under_score
 * constants must be UPPERCASE
 * protected words (true, false, null) must be LOWERCASE (ha ha!)
 *
 *****************************************************************************/

/******************************************************************************
 *
 * Codezilla class
 *
 * Framework Master Class
 *
 *****************************************************************************/
class Codezilla
{

    /*
     * cookie_domain is used during session initialization to set a value
     * for the default session or cookie storage.
     */
    public $cookie_domain;

    /*
     * cookie_lifetime is the default life time a cookie will be valid.
     */
    public $cookie_lifetime;

    /*
     * cookie_path is the path value for the cookie used for this domain.
     */
    public $cookie_path;

    /*
     * Once the vault is loaded this is correctly populated
     * but in the event the vault has not been loaded, it's important that
     * this be false, so we know where we stand.
     */
    public $config_status           = 0;

    /*
     * During initial configuration the IP of the configuring client
     * is stored for later emergency configurations and during initial
     * configuration. During the initial configuration the app will
     * only respond to the config_ipaddress, all others will see a please
     * wait screen and it will continually refresh while it waits to be configured.
     */
    public $config_ipaddress        = null;

    /*
     * database is the primary database object used for the framework
     * that can be passed around for used within the separate classes
     * or anywhere in the application.
     */
    public $database;

    /*
     * A global debug definition can be set which will override this, but if
     * this is set to true lots of debug information will be printed to the
     * screen as well as logged in the database.
     */
    public $debug                   = false;

    /*
     * A comma separated list of domains allowed to be used for admin/user registration
     * When enforced, only registrations from these domains will be allowed.
     */
    public $email_domains;

    /*
     * The environmental name used for matching a domain. When the vault is loaded
     * it uses the currently found domain and searches the vault for a matching
     * domain name and environment configuration, then configures the
     * application environment to match.
     */
    public $environment;

    /*
     * CSS files that are loaded are placed into an array so the files are not loaded multiple times
     */
    private $_loaded_css = array();

    /*
     * Data necessary for css files loaded in the theme
     */
    private $_loaded_css_data = array();

    /*
     * JavaScript files that are loaded via the theme are stored in an array so they are not loaded
     * multiple times.
     */
    private $_loaded_js = array();

    /*
     * A storage boolean for integrated systems to check if we are currently in the
     * recovery environment.
     */
    public $recovery;

    /*
     * The default FROM email address used when the system sends out emails that do not
     * warrant a response.
     */
    public $noreply;

    /*
     * The default FROM email address used when a new user signs up.
     */
    public $registration;

    /*
     * session_id is the value of the current PHP session
     */
    public $session_id;

    /*
     * The default FROM email address used when someone contacts using a support form.
     */
    public $support;

    /*
     * The publically accessible IP address of the remote client connecting
     * to the app is stored and used for various needs.
     */
    public $remote_ip;

    /*
     * The vault_db is the file location of the vault
     */
    public $vault_db;

    /*
     * The vault is the sqlite database used to store basic dataabse connections
     * between the framework and your primary database system.
     */
    public $vault;

    /*
     * Codezilla Master class begins here
     *
     * $params for this class can be property = value strings or
     * arrays containing additional information, property can also
     * be an object. Whatever is fed into the params will be loaded
     * as a property inside the master class.
     */
    public function __construct($params = null)
    {
        // process params
        if (!is_null($params)) {
            foreach($params as $name => $object) {
                if ($object)
                    $this->$name = $object;
            }
        }
        log_message('debug',__CLASS__, 'construct() Loading the Codezilla Vault', $this->debug);

        // Grab the remote systems IP Address
        if (isset($_SERVER['HTTP_X_CLIENT_IP'])) {
            if (!filter_var($_SERVER['HTTP_X_CLIENT_IP'], FILTER_VALIDATE_IP) === false) {
                $this->remote_ip = $_SERVER['HTTP_X_CLIENT_IP'];
            }
        }
        elseif (isset($_SERVER['REMOTE_ADDR'])) {
            if (!filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP) === false) {
                $this->remote_ip = $_SERVER['REMOTE_ADDR'];
            }
        }

        // Database connectivity is stored within an SQLite database, so verify we have access to that file
        (isset($this->vault_db))
            ? $vault_db = $this->vault_db
            : $vault_db = CONFIG . DIRECTORY_SEPARATOR . 'config.Codezilla.db';

        // if the Vault database does not exist then we must proceed with downloading
        // and creating the empty config.
        if (! file_exists($vault_db)) {
            // The database does not exist. Let's begin the Framework Setup
            define('FRAMEWORK_CONFIG', true);
            if (is_dir(SYSTEM . DIRECTORY_SEPARATOR . '_install')) {
                if (file_exists(SYSTEM . DIRECTORY_SEPARATOR . '_install' . DIRECTORY_SEPARATOR . 'framework_acquire.php')) {
                    require_once(SYSTEM . DIRECTORY_SEPARATOR . '_install' . DIRECTORY_SEPARATOR . 'framework_acquire.php');
                }
                else {
                    // The framework is broken and missing essential files
                    die('This is a corrupt installation and is missing vital files necessary for configuration<br>Please visit <a href="https://codezilla.xyz">https://codezilla.xyz</a> to download the framework');
                }
            }
            die();
        }
        else {
            log_message('debug',__CLASS__, 'construct() framework config db has been located: '.$vault_db, $this->debug);
        }

        // ultimately we want to be able to update this file, but at a minimum, it must be readable
        if (file_exists($vault_db)) {
            ($fh = fopen($vault_db, 'r'))
                ? define('CONFIG_FOUND', true)
                : define('CONFIG_FOUND', false);
            fclose($fh);

            // check if the vault is writable
            ($fh = fopen($vault_db, 'r+'))
                ? define('CONFIG_WRITABLE', true)
                : define('CONFIG_WRITABLE', false);
            fclose($fh);
        }
        else {
            die('The Vault could not be located. Something has moved or erased the file.');
        }

        if (!CONFIG_FOUND) {
            die('The Vault database is not available. Please check the system has at least read access to this file.');
        }
    }

    public function _initialize()
    {
        // we need to load the configuration information
        // from the local sqlite database into the "vault". If the vault
        // then contains information for connecting to a separate database; ie: MySQL, MariaDB;
        // then configure the internal database connection via $code->database to use this
        // new database.

        // framework configuration data should be accessed via $code->vault
        // and application data should be accessible via $code->database
        if (isset($this->vault) && (is_object($this->vault))) {
            $query = array();
            $query['select']['configuration'] = array(
                'site_name',
                'site_title',
                'site_slogan',
                'site_theme',
                'site_cookie',
                'meta_description',
                'meta_keywords',
                'meta_author',
                'ipstack_api_key',
                'cron_token',
                'robots',
                'maintenance',
                'analytics',
                'email_domains',
                'config_ipaddress',
                'registration_email',
                'activation_code',
                'codezilla_token',
                'config_status'
            );
            $query['from'] = 'configuration';
            if ($result = $this->vault->select($query)) {
                foreach($result[0] as $conf => $val) {
                    $this->$conf = $val;
                }
                if (!empty($result->email_domains)) {
                    $email_domains = explode(',', $result->email_domains);
                    $this->email_domains = $email_domains;
                }
            }

            // now pull the domains and urls
            $query = array();
            $query['select']['domains'] = array('id', 'domain', 'use_https', 'noreply', 'support', 'registration');
            $query['select']['environment'] = array(array('id' => 'environment_id'),
                'environment', 'dbtype', 'dbdriver', 'dbapikey', 'dbhost', 'dbport', 'dbuser', 'dbpass', 'dbname', 'dbprefix', 'dbfile',
                'ip_security', 'timezone', 'passminlength', 'passreqnum', 'passreqsym', 'passrequp', 'passreqlow',
                'mail_type', 'smtp_auth', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass',
                'debug', 'display_errors', 'html_errors', 'error_reporting'
            );
            $query['from'] = 'domains';
            $query['join']['left'] = array(
                'environment' => array(
                    'domains' => 'environment_id',
                    'environment' => 'id'
                )
            );
            $query['where']['domains'] = array('domain' => DOMAIN);
            if ($result = $this->vault->select($query)) {
                $this->environment = new Environment($result[0]);
            }

            // config_ipaddress can actually be a string of IPs separated by a space, so fix that here
            $ip_addresses = explode(' ', $this->config_ipaddress);
            foreach($ip_addresses as $ip_address) {
                if (!filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE) === false) {
                    if ($ip_address === $this->remote_ip)
                        $this->config_ipaddress = $this->remote_ip;
                }
                elseif ($ip_address === '127.0.0.1') {
                    // a local ip was given, which would not pass the test above, but is allowed for configuration
                    $this->config_ipaddress = $this->remote_ip;
                }
            }

            /******************************************************************************
             *
             *  -- Framework Configuration --
             *
             *  The configuration for the majority of the framework is stored within the
             *  "Vault". In the event this has not been configured we need to lock
             *  the IP Address down and not proceed until all configuration has taken
             *  place from the initially accessed IP Address.
             *
             *  config_status
             *      0 = No configuration has been done. Need to acquire the database
             *      1 = All good / doubles as boolean true
             *      2 = Name and Domain configuration complete - needs environment configuration -- Recovery Console
             *      3 = Force system maintenance and wait for recovery codes to enter 2
             *
             *****************************************************************************/
            // In the event someone gets the config_ipaddress set and they cannot access the recovery system
            // support can provide a file called 'emergency_recovery' which will allow the config_ipaddress
            // to be reset as long as the client has a correct recovery code. Once the code is verified this file will be deleted
            // and system configuration will resume.
            if (file_exists('emergency_recovery.php')) {
                define('EMERGENCY_CONSOLE', true);
                require_once 'emergency_recovery.php';
                die;
            }

            if ($this->config_status == 0) {
                // No configuration and no IP address configured yet.
                if ($this->config_ipaddress === null) {
                    if (is_dir(SYSTEM . DIRECTORY_SEPARATOR . '_install')) {
                        if (file_exists(SYSTEM . DIRECTORY_SEPARATOR . '_install' . DIRECTORY_SEPARATOR . 'framework_recovery.php')) {
                            define('FRAMEWORK_CONFIG', true);
                            require_once(SYSTEM . DIRECTORY_SEPARATOR . '_install' . DIRECTORY_SEPARATOR . 'framework_recovery.php');
                            die();
                        }
                        // The environment is misconfigured or not present.
                        refresh(30, 'The application is currently undergoing maintenance. Error Code (000)');
                        die();
                    }
                    refresh(30, 'The application is currently undergoing maintenance. Error Code (001)');
                    die();
                }
                elseif ($this->config_ipaddress === $this->remote_ip) {
                    // continue to the config setup steps
                    if (is_dir(SYSTEM . DIRECTORY_SEPARATOR . '_install')) {
                        if (file_exists(SYSTEM . DIRECTORY_SEPARATOR . '_install' . DIRECTORY_SEPARATOR . 'framework_domain.php')) {
                            define('FRAMEWORK_CONFIG', true);
                            require_once(SYSTEM . DIRECTORY_SEPARATOR . '_install' . DIRECTORY_SEPARATOR . 'framework_domain.php');
                            die();
                        }
                        // The environment is misconfigured or not present.
                        refresh(30, 'The application is currently undergoing maintenance. Error Code (002)');
                        die();
                    }
                    // The framework is missing files. Please reinstall the framework.
                    refresh(30, 'The application is currently undergoing maintenance. Error Code (003)');
                    die();
                }
                // The system has not finished configuration, but the client IP does not match the config_ipaddress
                // this is a foreign system accessing the app during configuration.
                // It's also possible at this point someone's IP address changed... they need to delete the database and start
                // over.
                refresh(30, 'The application is currently undergoing maintenance. Error Code (004)');
                die();
            }
            elseif ($this->config_status == 1) {
                if (isset($this->environment)) {
                    if (empty($this->environment->dbtype)) {
                        if ($this->config_ipaddress === $this->remote_ip) {
                            echo '<h1>config_status == 1 but dbtype is empty</h1>';
                            // If the system is not configured correctly, but the
                            // ip matches the configuration ip we can immediately go to the recovery console
                            if (is_dir(SYSTEM . DIRECTORY_SEPARATOR . '_install')) {
                                if (file_exists(SYSTEM . DIRECTORY_SEPARATOR . '_install' . DIRECTORY_SEPARATOR . 'framework_recovery.php')) {
                                    define('FRAMEWORK_CONFIG', true);
                                    $this->recovery = true;
                                    require_once(SYSTEM . DIRECTORY_SEPARATOR . '_install' . DIRECTORY_SEPARATOR . 'framework_recovery.php');
                                    die();
                                }
                                // The environment is misconfigured or not present.
                                refresh(30, 'The application is currently undergoing maintenance. Error Code (005)');
                                die();
                            }
                            // The environment is misconfigured or not present.
                            refresh(30, 'The application is currently undergoing maintenance. Error Code (006)');
                            die();
                        }
                        // The environment is misconfigured or not present.
                        refresh(30, 'The application is currently undergoing maintenance. Error Code (007)');
                        die();
                    }
                    // everything is good...
                }
                else {
                    if ($this->config_ipaddress === $this->remote_ip) {
                        // If the system is not configured correctly, but the
                        // ip matches the configuration ip we can immediately go to the recovery console
                        if (is_dir(SYSTEM . DIRECTORY_SEPARATOR . '_install')) {
                            if (file_exists(SYSTEM . DIRECTORY_SEPARATOR . '_install' . DIRECTORY_SEPARATOR . 'framework_recovery.php')) {
                                define('FRAMEWORK_CONFIG', true);
                                $this->recovery = true;
                                require_once(SYSTEM . DIRECTORY_SEPARATOR . '_install' . DIRECTORY_SEPARATOR . 'framework_recovery.php');
                                die();
                            }
                            // The environment is misconfigured or not present.
                            refresh(30, 'The application is currently undergoing maintenance. Error Code (008)');
                            die();
                        }
                        // The environment is misconfigured or not present.
                        refresh(30, 'The application is currently undergoing maintenance. Error Code (009)');
                        die();
                    }
                    // The environment is misconfigured or not present.
                    refresh(30, 'The application is currently undergoing maintenance. Error Code (010)');
                    die();
                }
            }
            elseif ($this->config_status == 2) {
                if ($this->config_ipaddress === $this->remote_ip) {
                    // continue to the config setup steps
                    if (is_dir(SYSTEM . DIRECTORY_SEPARATOR . '_install')) {
                        if (file_exists(SYSTEM . DIRECTORY_SEPARATOR . '_install' . DIRECTORY_SEPARATOR . 'framework_environment.php')) {
                            define('FRAMEWORK_CONFIG', true);
                            require_once(SYSTEM . DIRECTORY_SEPARATOR . '_install' . DIRECTORY_SEPARATOR . 'framework_environment.php');
                            die();
                        }
                        // The environment is misconfigured or not present.
                        refresh(30, 'The application is currently undergoing maintenance. Error Code (011)');
                        die();
                    }
                    // The environment is misconfigured or not present.
                    refresh(30, 'The application is currently undergoing maintenance. Error Code (012)');
                    die();
                }
                // The environment is misconfigured or not present.
                refresh(30, 'The application is currently undergoing maintenance. Error Code (013)');
                die();
            }

            /******************************************************************************
             *
             *  -- Environment Configuration --
             *
             *  The Environment definition is used throughout the framework to establish
             *  various other definitions and variables. The primary intent is so you
             *  can have the framework react in specific ways. The basic example is
             *  when your servers hostname is localhost, we assume you are developing
             *  and want all error logging enabled. So we set the environment to development
             *  and enable all logging. If the servers hostname is yourdomain.com then
             *  we want to disable all of the logging except for fatal issues and
             *  keep them in the log file only.
             *
             *  This can be further used in your application to know if it should
             *  actually submit data to a third-party, access an API using a test code,
             *  or whatever as well based on the value of ENVIRONMENT.
             *
            ******************************************************************************/
            if (isset($this->environment) && (is_object($this->environment)) && (!empty($this->environment->dbtype))) {
                // Establish initial debug via the vault
                (isset($this->environment->debug) && !empty($this->environment->debug))
                    ? $this->debug = true
                    : $this->debug = false;

                // If the master debug definition is on... turn it all on.
                if (DEBUG)
                    $this->debug = true;

                /******************************************************************************
                 *
                 * -- ENVIRONMENT LOGGING --
                 *
                 * This defines the error reporting and logging verbosity based upon
                 * the defined ENVIRONMENT.
                 *
                ******************************************************************************/
                (isset($this->environment->environment) && !empty($this->environment->environment))
                    ? define('ENVIRONMENT', strtolower($this->environment->environment))
                    : define('ENVIRONMENT', 'production');

                /******************************************************************************
                 *
                 * -- HTTPS Enforcement --
                 *
                 * Enable / Disable HTTP_HOST as HTTPS or HTTP
                 * Establish the http scheme requested in the vault and redirect if we are not using
                 * that scheme.
                 *
                ******************************************************************************/
                (($this->environment->use_https) && !empty($this->environment->use_https))
                    ? define('HTTP_SCHEME', 'https')
                    : define('HTTP_SCHEME', 'http');

                ((!empty(HTTP_SCHEME)) && (!empty(DOMAIN)))
                    ? define( 'HTTP_HOST', rtrim( HTTP_SCHEME .'://' . DOMAIN . '/', '/' ))
                    : halt('Framework bootstrap failure. Cannot properly define base url for system loading...');

                // Simple fail safe to make sure all is in working order so far
                // however, if the web server is serving via http and a front-end is serving via https
                // this will cause a massive redirect loop.
                if ((isset($_SERVER['REQUEST_SCHEME'])) && ($_SERVER['REQUEST_SCHEME'] != HTTP_SCHEME)) {
                    redirectTo(HTTP_HOST);
                    die;
                }

                // Allow 'debug' enabling via the url when not in a production environment
                if (ENVIRONMENT !== 'production') {
                    if (isset($_GET['debug'])) {
                        if ($_GET['debug'] == 'true' || $_GET['debug'] == 'force') {
                            $_SESSION['debug'] = true;
                            $this->debug = true;
                        }
                    }
                }

                // Establish the necessary emails
                $this->noreply          = $this->environment->noreply;
                $this->registration     = $this->environment->registration;
                $this->support          = $this->environment->support;

                /******************************************************************************
                 *
                 * Start the session
                 *
                ******************************************************************************/
                // This should be set once we pull from a cookie, but do not let it be empty
                $this->cookie_domain = DOMAIN;
                $this->cookie_lifetime = time() + 86400;
                $this->cookie_path = ini_get('session.cookie_path');

                (HTTP_SCHEME == 'https')
                    ? $this->cookie_secure = true
                    : $this->cookie_secure = false;

                if (!session_id()) {
                    session_name('Codezilla');
                    session_set_cookie_params(
                        $this->cookie_lifetime,
                        $this->cookie_path,
                        $this->cookie_domain,
                        $this->cookie_secure,
                        true
                    );

                    if (session_start()) {
                        $this->session_id = session_id();
                    }
                }
                else {
                    if (session_id())
                        session_destroy();
                    redirectTo(HTTP_HOST.$_SERVER['REQUEST_URI']);
                }

                // Timezone settings
                ((isset($this->environment->timezone)) && !empty($this->environment->timezone))
                    ? date_default_timezone_set($this->environment->timezone)
                    : date_default_timezone_set('America/Los_Angeles');

                // IP Security is used for strong authentication where a visitor/user must have an authorized IP address
                ((isset($this->environment->ip_security)) && !empty($this->environment->ip_security))
                    ? define('IP_SECURITY', $this->environment->ip_security)
                    : define('IP_SECURITY', false);

                // Environment Reporting Options
                ((isset($this->environment->display_errors)) && !empty($this->environment->display_errors))
                        ? ini_set('display_errors', $this->environment->display_errors)
                        : ini_set('display_errors', 0);

                ((isset($this->environment->html_errors)) && !empty($this->environment->html_errors))
                    ? ini_set('html_errors', $this->environment->html_errors)
                    : ini_set('html_errors', 0);

                if (isset($this->environment->error_reporting)) {
                    switch ($this->environment->error_reporting) {
                        case '0':
                            error_reporting(0);
                            break;
                        case '1':
                            error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
                            break;
                        case '2':
                            error_reporting(E_ALL | E_STRICT | E_DEPRECATED);
                            break;
                        default:
                            error_reporting(-1); // expressly enable all debugging until this is resolved
                            message('The ENVIRONMENT is not set correctly');
                            message('Please visit the Settings to make changes');
                            break;
                    }
                }

                // Check our REQUEST_URI for /api/ and disable debug
                $api_check = stripos($_SERVER['REQUEST_URI'], '/api/');
                if ($api_check !== FALSE) {
                    $this->debug = false;
                    ini_set('display_errors', 0);
                    ini_set('html_errors', 0);
                    error_reporting(0);
                }

                log_message('debug', __CLASS__, 'construct() Debug Enabled', $this->debug);

                // Password Requirements for users
                ((isset($this->environment->passminlength)) && !empty($this->environment->passminlength))
                    ? define('PASSWORD_MINLENGTH', $this->environment->passminlength)
                    : define('PASSWORD_MINLENGTH', 8);

                // Password requires numerals
                ((isset($this->environment->passreqnum)) && !empty($this->environment->passreqnum))
                    ? define('PASSWORD_NUMERALS', $this->environment->passreqnum)
                    : define('PASSWORD_NUMERALS', true);

                // Password requires symbols
                ((isset($this->environment->passreqsym)) && !empty($this->environment->passreqsym))
                    ? define('PASSWORD_SYMBOLS', $this->environment->passreqsym)
                    : define('PASSWORD_SYMBOLS', true);

                // Password requires UPPERCASE
                ((isset($this->environment->passrequp)) && !empty($this->environment->passrequp))
                    ? define('PASSWORD_UPPERCASE', $this->environment->passrequp)
                    : define('PASSWORD_UPPERCASE', true);

                // Password requires LOWERCASE (he he)
                ((isset($this->environment->passreqlow)) && !empty($this->environment->passreqlow))
                    ? define('PASSWORD_LOWERCASE', $this->environment->passreqlow)
                    : define('PASSWORD_LOWERCASE', true);

                ((isset($this->environment->smtp_auth)) && !empty($this->environment->smtp_auth))
                    ? define('SMTP_AUTH', $this->environment->smtp_auth)
                    : define('SMTP_AUTH', false);

                ((isset($this->environment->smtp_host)) && !empty($this->environment->smtp_host))
                    ? define('SMTP_HOSTNAME', $this->environment->smtp_host)
                    : define('SMTP_HOSTNAME', 'localhost');

                ((isset($this->environment->smtp_port)) && !empty($this->environment->smtp_port))
                    ? define('SMTP_PORT', $this->environment->smtp_port)
                    : define('SMTP_PORT', 25);

                ((isset($this->environment->smtp_user)) && !empty($this->environment->smtp_user))
                    ? define('SMTP_USERNAME', $this->environment->smtp_user)
                    : define('SMTP_USERNAME', '');

                ((isset($this->environment->smtp_pass)) && !empty($this->environment->smtp_pass))
                    ? define('SMTP_PASSWORD', $this->environment->smtp_pass)
                    : define('SMTP_PASSWORD', '');

                // Database selection and connection settings
                log_message('debug', __CLASS__, 'construct() Beginning Database Connections', $this->debug);
                if ($this->environment->dbtype == 'MySQLi') {
                    $this->database = $this->loadClass('Database', null, array(
                        'adaptor'   => 'MySQLi',
                        'db_host'   => $this->environment->dbhost,
                        'db_port'   => $this->environment->dbport,
                        'db_name'   => $this->environment->dbname,
                        'db_prefix' => $this->environment->dbprefix,
                        'db_user'   => $this->environment->dbuser,
                        'db_pass'   => $this->environment->dbpass,
                        'debug'     => $this->debug
                    ));
                }
                elseif ($this->environment->dbtype == 'AzureSQLAD') {
                    $this->database = $this->loadClass('Database', null, array(
                        'adaptor'   => 'AzureSQLAD',
                        'driver'    => $this->environment->dbdriver,
                        'db_host'   => $this->environment->dbhost,
                        'db_port'   => $this->environment->dbport,
                        'db_name'   => $this->environment->dbname,
                        'db_prefix' => $this->environment->dbprefix,
                        'db_user'   => $this->environment->dbuser,
                        'db_pass'   => $this->environment->dbpass,
                        'debug'     => $this->debug
                    ));
                }
                elseif ($this->environment->dbtype == 'PDO') {
                    $this->database = $this->loadClass('Database', null, array(
                        'adaptor'   => 'PDO',
                        'driver'    => $this->environment->dbdriver,
                        'db_host'   => $this->environment->dbhost,
                        'db_port'   => $this->environment->dbport,
                        'db_name'   => $this->environment->dbname,
                        'db_prefix' => $this->environment->dbprefix,
                        'db_user'   => $this->environment->dbuser,
                        'db_pass'   => $this->environment->dbpass,
                        'db_Authentication' => $this->environment->dbapikey,
                        'debug'     => $this->debug
                    ));
                }
                elseif ($this->environment->dbtype == 'SQLite') {
                    $this->database = $this->loadClass('Database', null, array('security' => $this->security, 'adaptor' => 'SQLite', 'dbfile' => $this->environment->dbfile));
                }
                elseif ($this->environment->dbtype == 'WebAPI') {
                    $this->database = $this->loadClass('Database', null, array('security' => $this->security, 'adaptor' => 'WebAPI', 'dbapikey' => $this->environment->dbapikey));
                }

                // this is where we will connect to the database now that we are here...
                if ($this->database->connect()) {
                    if ($this->database->active() != 1) {
                        if ($this->database->active() == 1045) {
                            // this is an access denied error

                        }
                        elseif ($this->database->active() == 2002) {
                            // the system could not connect to the server requested

                        }
                        // database connection issue... what do we do?
                        if (session_id())
                            session_destroy();
                        // gather some info and send a report
                        $this->sendSystemReport(array('message' => 'Database failure: '.$this->database->active()));
                        if ($this->config_ipaddress == $this->remote_ip) {
                            // grab this ip address and refresh the page
                            $update['update']['configuration'] = array('config_status' => 2, 'maintenance' => 1);
                            $this->vault->update($update);
                            refresh('5', 'The application could not connect to the database. Please try again later.');
                            die();
                        }
                        // The environment is misconfigured or not present.
                        refresh(30, 'The application is currently undergoing maintenance. Error Code (014)');
                        die();
                    }
                }
            }

            /******************************************************************************
             *
             * -- Authentication Cookie --
             *
             * A master authentication cookie is used for user authentication.
             * A validation string will be contained within the cookie and used
             * to verify and authenticate a known user. Keeping this cookie name
             * random doesn't provide any security, but it keeps your visitors privacy
             * by not telling the world where they've been. Change it to your
             * domain name if you really want.
             *
            ******************************************************************************/
            (isset($this->site_cookie) && ctype_alnum($this->site_cookie))
                ? define('COOKIE', $this->site_cookie)
                : halt('System Cookie not available');

            /******************************************************************************
             *
             * -- Default Module --
             *
             * If nothing else has been configured, the framework will load it's
             * welcome module.
             * Modules belong inside the MODULES folder.
             *
            ******************************************************************************/
            ((isset($this->default_module)) && !empty($this->default_module))
                ? define('DEFAULT_MODULE', $this->default_module)
                : define('DEFAULT_MODULE', 'welcome');
        }
        else {
            // ALL STOP -- Without an Environment configured for the url being accessed we cannot
            // guarantee we are providing the right content or that we are not providing the keys
            // to the kingdom, so just shutdown.
            halt('System Misconfiguration Error.');
        }

        // _init processing will halt or die if failure is encountered
        // if we make it here, the system is ready to continue
        return true;
    }

    public function curlDownload($url, $filename)
    {
        if ($fh = fopen($filename, 'w')) {
            if (!filter_var($url, FILTER_VALIDATE_URL) == FALSE) {
                $sess = curl_init();
                curl_setopt($sess, CURLOPT_URL, $url);
                curl_setopt($sess, CURLOPT_HTTPPROXYTUNNEL, true);
                curl_setopt($sess, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($sess, CURLOPT_TIMEOUT, 60);
                curl_setopt($sess, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($sess, CURLOPT_HEADER, false);
                curl_setopt($sess, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Linux x86_64; rv:66.0) Gecko/20100101 Firefox/66.0');
                curl_setopt($sess, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($sess, CURLOPT_FILE, $fh);

                if ($curl_result = curl_exec($sess)) {
                    $status_code = curl_getinfo($sess, CURLINFO_HTTP_CODE);
                    curl_close($sess);
                    echo '<p>the status code for the download: '.$status_code.'</p>';
                    fclose($fh);
                    if ($status_code == 200) {
                        echo 'Download Successful<br>';
                        return true;
                    }
                    else {
                        echo 'Download Status Code: '.$status_code.'<br>';
                    }
                }
            }
        }
        if ($sess)
            curl_close($sess);
        return false;
    }

    // the $post should be the array of data to post, the post fields
    public function curlPost($url = HTTP_HOST.'/api/', $data = array(), $use_transients = true, $expires = 21600)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL) == FALSE) {
            $sess = curl_init();
            curl_setopt($sess, CURLOPT_URL, $url);
            curl_setopt($sess, CURLOPT_HTTPPROXYTUNNEL, true);
            curl_setopt($sess, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($sess, CURLOPT_TIMEOUT, 60);
            curl_setopt($sess, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($sess, CURLOPT_HEADER, false);
            curl_setopt($sess, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Linux x86_64; rv:66.0) Gecko/20100101 Firefox/66.0');
            curl_setopt($sess, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($sess, CURLOPT_POST, count($data));
            // create the fields_string
            $fields_string = '';
            if (count($data) > 0) {
                foreach($data as $key=>$val) {
                    $fields_string .= '&'.$key.'='.urlencode($val);
                }
            }
            curl_setopt($sess, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/x-www-form-urlencoded',
                'Content-Length: ' . strlen($fields_string)
            ));
            curl_setopt($sess, CURLOPT_POSTFIELDS, $fields_string);

            if ($curl_result = curl_exec($sess)) {
                if (curl_errno($sess)) {
                    $message = sprintf('curl error [%d]: %s', curl_errno($sess), curl_error($sess));
                    log_message('warn', __CLASS__, 'curlPost Failed -- '.$url, $this->debug);
                    $this->sendInfo($this->admin_email, 'curlPost curl error', $message);
                    curl_close($sess);
                    return false;
                }
                log_message('info', __CLASS__, 'curlPost Completed -- '.$url, $this->debug);
                if ($sess)
                    curl_close($sess);
                return $curl_result;
            }
        }
        if ($sess)
            curl_close($sess);
        return false;
    }

    /******************************************************************************
     *
     * function execute
     *
     * Once all classes have been instantiated, the theme has been loaded, and
     * any additional parts needed, execute will run the requested controller.
     *
    ******************************************************************************/
    public function execute()
    {
        if (!empty($this->router->controller)) {
            // Not all modules will have a modinfo file. Particularly the default 'Welcome' module.
            // If a module requires zero permissions then there is no reason to include it.
            if (is_file($this->router->system_path . DIRECTORY_SEPARATOR . $this->router->module . DIRECTORY_SEPARATOR . 'modinfo.php')) {
                include $this->router->system_path . DIRECTORY_SEPARATOR . $this->router->module . DIRECTORY_SEPARATOR . 'modinfo.php';
            }
            // We cannot operate without the controller. If this doesn't exist we die.
            if (is_file($this->router->controller_path . DIRECTORY_SEPARATOR . $this->router->controller)) {
                require_once $this->router->controller_path . DIRECTORY_SEPARATOR . $this->router->controller;
            }
            else {
                halt('Controller could not be found. System Configuration Error occurred.');
            }
        }
        // The router does not have a configured controller.
        // If the module exists check for a controller with the same name, maybe this module
        // has not been configured yet...? This could be bad.
        elseif (isset($this->router->module)) {
            show404("Woah there, cowboy. Why don't you slow down a bit.");
            message('The router did not find a controller. What is wrong?');
            if (is_dir(MODULES . DIRECTORY_SEPARATOR . $this->router->module)) {
                if (is_file(MODULES . DIRECTORY_SEPARATOR . $this->router->module . DIRECTORY_SEPARATOR . $this->router->module . '.php')) {
                    require_once MODULES . DIRECTORY_SEPARATOR . $this->router->module . DIRECTORY_SEPARATOR . $this->router->module . '.php';
                }
            }
        }
        else {
            ?>
            <h1>Welcome to the Codezilla PHP Framework</h1>
            <p>It seems your system has not been configured yet.</p>
            <?php
                if (file_exists(SYSTEM . DIRECTORY_SEPARATOR . '_install' . DIRECTORY_SEPARATOR . 'framework_config.php')) {
                    ?>
                    <p>Please visit <a href="<?php echo HTTP_HOST.'/_install/framework_config.php'; ?>">Framework Configuration</a> to setup your framework!</p>
                    <?php
                }
            ?>
            <p>If you do not have any modules installed, now would be a great time to visit the documentation and learn how to develop your own!</p>
            <p>Of course, you can visit the <a href="https://codezilla.xyz/codezilla-framework/modules">modules</a> section of our website and find many existing modules for your use.</p>
            <p>Happy Surfing!</p>
            <?php
        }
    }

    /******************************************************************************
     *
     * function isEmergencyOverride()
     *
     * When the system is put into maintenance, or when a misconfiguration is found
     * the system can check if the remote_addr is the same as the remote_addr
     * that originally configured the service (in the event of a new install) or
     * during a maintenance / emergency, but the admin could not previously log in,
     * if the ip matches it will force to the admin site, which will require a login
     * anyway, so this should be safe. This is not a bypass of any kind.
     * It only serves to bypass modules and plugins so the user can authenticate,
     * then it will load as normal for the SuperAdmin.
     *
    ******************************************************************************/
    public function isEmergencyOverride()
    {
        if (isset($_SERVER['REMOTE_ADDR'])) {
            if (!filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP) === false) {
                if (is_array($this->config_ipaddress)) {
                    if (in_array($_SERVER['REMOTE_ADDR'], $this->config_ipaddress)) {
                        return true;
                    }
                }
                elseif ($_SERVER['REMOTE_ADDR'] == $this->config_ipaddress) {
                    return true;
                }
            }
        }
        return false;
    }

    /******************************************************************************
     *
     * function isSuperAdmin()
     *
     * isSuperAdmin checks if the logged in user is a super admin or not.
     * Unlike regular administrators, the superAdmin has access to the system
     * in all circumstances, even those which might otherwise lock out the
     * system administrators.
     *
    ******************************************************************************/
    public function isSuperAdmin()
    {
        if ($this->storage->keyExists('user')) {
            if ($user = $this->storage->decrypt('user')) {
                if (is_object($user)) {
                    // if a proper user object it will contain a method to test internally
                    if (method_exists($user, 'isSuperAdmin')) {
                        if ($user->isSuperAdmin()) {
                            return true;
                        }
                    }
                    // in some instances the object will contain the property only and not the method
                    elseif (isset($user->isSuperAdmin)) {
                        if ($user->isSuperAdmin)
                            return true;
                    }
                }
            }
        }
        return false;
    }

    /******************************************************************************
     *
     * function loadClass($class_name, $path, $params, $name_space, $object_name)
     * @param string $class_name The proper name of the class to be instantiated
     * @param string $path the file location, along with necessary file name, within standardized directories
     * @param array $params This should contain an associative array of parameters, though it can be a string
     * @param string $name_space If a name space is required for use
     * @param string $object_name If you wish to load the class into the singleton using a specific name
     *
     * This method will check for an existing class object of the name $class_name
     * and respond with that object, if the class object does not exist it will
     * be instantiated.
     *
     * @params If the value of a parameter matches an existing class within Codezilla
     * then then existing class will be passed as a parameter to the new class
     * to be instantiated. ie; you can pass the existing database class to an instantiated object
     * with params array('database'), or pass a new database using array('database' => $mydatabase).
     *
     * Expected parameters are as follows:
     *
     *  *path* is used when the location is not within the expected directories
     *      or the class file name itself does not match the class name
     * 'path' => '/absolute/path/to/file'                       i.e; /var/www/html/3rdparty/vendor/src/something/cool/ClasnameHere.php
     * 'path' => 'path/within/existing/class/dirs/filename'     i.e; MyNewClassDir/vendor/src/ClassnameHere.php
     * 'path' => 'path/within/existing/class/dirs'              i.e; MyNewClassDir/vendor/src/
     * 'path' => 'filename'                                     i.e; ClassnameHere.php
     *
     * 'params' => any parameters you need passed to the class in a format the class expects, this is passed directly
     *
     *****************************************************************************/

    /*
     *  public function loadClass($class_name, $path, $params, $object_name)
     *
     *  $code->loadClass('Users', null, array('database', 'security', 'storage'))
     *  $code->loadClass('PHPMailer', 'phpmailer-6.0.6-stable/class.phpmailer.php', null, 'PHPMailer\PHPMailer', 'phpmailer')
     */
    public function loadClass($class_name, $path = null, $params = null, $object_name = null)
    {
        log_message('debug', __CLASS__, 'loadClass('.$class_name.')', $this->debug);
        $class_absolute_path = '';

        if (isset($class_name) && !empty($class_name) && !is_null($class_name)) {
            // we add the class to the singleton using a lowercase name
            $class_name_lc = mb_strtolower($class_name);

            // if the class already exists in our system then just return
            if (is_null($object_name)) {
                if (isset($this->_classes[$class_name_lc])) {
                    log_message('debug', __CLASS__, 'loadClass() returning existing object: '.$class_name_lc, $this->debug);
                    return $this->$class_name_lc;
                }
            }
            else {
                if (isset($this->_classes[$object_name])) {
                    log_message('debug', __CLASS__, 'loadClass() returning existing object of specified name: '.$object_name, $this->debug);
                    return $this->$object_name;
                }
            }

            // Search through default locations and find the class
            log_message('debug', __CLASS__, 'loadClass() beginning class search', $this->debug);
            if (is_null($path)) {
                foreach(explode(PATH_SEPARATOR, get_include_path()) as $include_path) {
                    log_message('debug', __CLASS__, 'loadClass() examining class path: '.$include_path, $this->debug);
                    if (file_exists($include_path . DIRECTORY_SEPARATOR . 'class.' . $class_name . '.php')) {
                        log_message('debug', __CLASS__, 'loadClass() class found: '.$include_path . DIRECTORY_SEPARATOR . 'class.' . $class_name . '.php', $this->debug);
                        $class_absolute_path = $include_path . DIRECTORY_SEPARATOR . 'class.' . $class_name . '.php';
                        break;
                    }
                }
            }

            // if a specific path was provided let's find the class
            if (!is_null($path)) {
                // absolute paths
                if (file_exists($path)) {
                    // an absolute path and filename was given
                    if (is_file($path)) {
                        log_message('debug', __CLASS__, 'loadClass() class found: ' . $path, $this->debug);
                        $class_absolute_path = $path;
                    }
                    // an absolute directory path was given, no file name
                    if (is_dir($path)) {
                        // try to locate the class file now within this directory
                        if (is_file($path . DIRECTORY_SEPARATOR . $class_name . '.php')) {
                            log_message('debug', __CLASS__, 'loadClass() class found: '.$path . DIRECTORY_SEPARATOR . $class_name . '.php', $this->debug);
                            $class_absolute_path = $path . DIRECTORY_SEPARATOR . $class_name . '.php';
                        }
                    }
                }
                else {
                    foreach(explode(PATH_SEPARATOR, get_include_path()) as $include_path) {
                        if (file_exists($include_path . DIRECTORY_SEPARATOR . $path)) {
                            if (is_file($include_path . DIRECTORY_SEPARATOR . $path)) {
                                log_message('debug', __CLASS__, 'loadClass() class found: '.$include_path . DIRECTORY_SEPARATOR . $path, $this->debug);
                                $class_absolute_path = $include_path . DIRECTORY_SEPARATOR . $path;
                                break;
                            }
                            if (is_dir($include_path . DIRECTORY_SEPARATOR . $path)) {
                                if (is_file($include_path . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR . $class_name . '.php')) {
                                    log_message('debug', __CLASS__, 'loadClass() class found: ' . $include_path . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR . $class_name . '.php', $this->debug);
                                    $class_absolute_path = $include_path . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR . $class_name . '.php';
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            if (empty($class_absolute_path)) {
                speak('<h2 class="error">Unable to locate the specified class: '.$class_name.'.php</h2>', true);
                halt('Unable to locate the specified class: '.$class_name.'.php');
                exit(5);
            }

            if (!empty($class_absolute_path)) {
                log_message('debug', __CLASS__, 'loadClass() requiring class file: '.$class_absolute_path, $this->debug);
                require_once($class_absolute_path);
            }

            // Codezilla adds the debug flag to all classes so they all act accordingly
            // Also, because a class can be injected into another class at load by adding the name
            // of the class in the params array we do that here.
            //if (is_null($params)) {
            //    $params = array('debug' => $this->debug);
            //}
            //else {
                if (is_array($params)) {
                    $params['debug'] = $this->debug;
                    foreach($params as $zkey => $zval) {
                        if (is_numeric($zkey)) {
                            // numeric means we passed a single value within the array, so check if this is an object inside Codezilla
                            // and pass the object, otherwise, pass the value
                            (isset($this->$zval) && is_object($this->$zval))
                                ? $params[$zval] = $this->$zval
                                : $params[$zkey] = $zval;
                        }
                    }
                }
            //    elseif (is_string($params)) {
            //        $params = array('debug' => $this->debug, 'data' => $params);
            //    }
            //}

            // now we are going to actually instantiate the class
            if (!is_null($object_name)) {
                // instantiate the object using the requested name
                $this->_classes[$object_name] = $class_name_lc;
                $this->$object_name = isset($params)
                    ? new $class_name($params)
                    : new $class_name();
                return $this->$object_name;
            }
            else {
                $this->_classes[$class_name_lc] = $class_name_lc;
                $this->$class_name_lc = isset($params)
                    ? new $class_name($params)
                    : new $class_name();
                return $this->$class_name_lc;
            }
        }
    }

    /******************************************************************************
     * function loadCss($style)
     * @param string $style The css file to load
     * @param array $data An array of elements to add
     *
     * This will load a css file into an array when a $style is given. Once a page
     * is ready to load call this without any parameters and the html code necessary
     * to load the css files will be echo'd.
    ******************************************************************************/
    public function loadCss($style = null, $data = array())
    {
        if (is_null($style)) {
            $styles = '';
            $version = '';
            if (ENVIRONMENT !== 'production') {
                // this forces browser caches to load the current version
                // which is helpful during development, it appears to be a new
                // file with every load
                $version = '?v='.time();
            }
            foreach($this->_loaded_css as $idx => $file) {
                $params = '';
                if (isset($this->_loaded_css_data[$idx])) {
                    foreach($this->_loaded_css_data[$idx] as $key => $val) {
                        $params .= $key.'="'.$val.'" ';
                    }
                }
                $styles .= '        <link rel="stylesheet" href="' . $file . $version .'" type="text/css" '.$params.'>'.PHP_EOL;
            }
            echo $styles;
        }
        else {
            if (!isset($this->_loaded_css[$style]))
                $this->_loaded_css[] = $style;
        }

        // if optional parameters were given add them to the array for later retrieval
        if (is_array($data) && count($data) > 0) {
            $index = array_search($style, $this->_loaded_css);

            if (is_numeric($index)) {
                $this->_loaded_css_data[$index] = $data;
            }
        }
    }

    /******************************************************************************
     * function loadJs($script, $tail = false)
     * @param string $script The javascript source file to load
     * @param bool $tail Load only those scripts marked with tail
     *
     * Use this in conjuction with a theme to prepare javascript files to load. Once
     * the page is ready to be displayed call this method without any parameters
     * and the necessary html will be echo'd. You can call the scripts to be loaded anywhere
     * you like, but if you want specific scripts to load in your footer, and not anywhere
     * else, use the tail feature.
     *
     * Of course, you have to tell the view when to load the tail scripts, so this can be
     * used to load a different set of scripts any time you like.
     *
     * if called with tail = true then the script will be loaded when called in the tail
     *
     * In the view to call a script use ->loadJs() to load the javascript
     * In the view to call a script use ->loadJs(null, true) to load the scripts at the
     * end of the document.
    ******************************************************************************/
    public function loadJs($script = null)
    {
        if (is_null($script)) {
            $scripts = '';
            $version = '';
            foreach($this->_loaded_js as $file) {
                $this->_loaded_js[] = $file;
                $scripts .= '       <script src="' . $file . $version .'"></script>'.PHP_EOL;
            }
            echo $scripts;
        }
        elseif (!is_null($script) && !empty($script)) {
            if (!isset($this->_loaded_js[$script]))
                $this->_loaded_js[] = $script;
        }
        return true;
    }

    /*************************************************************************
     * function loadTheme()
     * @access public
     *
     * Verify the theme has been loaded from the Vault and pull in the required
     * theme configuration file
    *************************************************************************/
    public function loadTheme()
    {
        // If we are in the admin system path we need to load the admin theme
        // not whatever theme the framework is specifying
        $this->site_theme = 'system';
        if (isset($this->router->system_path) && ($this->router->system_path == SYSTEM)) {
            $this->site_theme = 'system';
        }

        if (!empty($this->theme_config)) {
            // if we are called twice, don't reload the theme
            return true;
        }
        elseif (is_dir(COMMON . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . $this->site_theme)) {
            if (is_file(COMMON . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . $this->site_theme . DIRECTORY_SEPARATOR . 'config.php')) {
                $this->theme_config = COMMON . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . $this->site_theme . DIRECTORY_SEPARATOR . 'config.php';
                require_once $this->theme_config;
            }
        }

        // The requested theme doesn't exist or wasn't correctly installed, so default to system and log it
        if (empty($this->theme_config)) {
            // if we are called twice, don't reload the theme
            log_message('system', __CLASS__, 'Requested theme "'.$this->site_theme.'" cannot be found. Defaulting to "system"', $this->debug);
            $this->site_theme = 'system';
            $this->loadTheme();
        }
    }

    /******************************************************************************
     * function loadView( $view )
     * @param string $view The view to load
     * @param array $view An array of views to load
     *
     * This will find the view you are attempting to load within the directory
     * of the current running theme and include the necessary file. It will not
     * return a view multiple times.
    ******************************************************************************/
    public function loadView($view)
    {
        if (is_array($view)) {
            foreach($view as $template) {
                $this->loadView($template);
            }
        }
        else {
            // get the current theme name and then look in the views directory for a matching view
            if (isset($this->_loaded_views[$view])) {
                return true;
            }
            else {
                if (is_file(COMMON . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . $this->site_theme . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'view.' . $view . '.php')) {
                    $this->_loaded_views[$view] = COMMON . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . $this->site_theme . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'view.' . $view . '.php';
                    require_once $this->_loaded_views[$view];
                }
                else {
                    log_message('warn', __CLASS__, 'loadView could not find the requested view: '.$view, $this->debug);
                }
            }
        }
    }

    // context should be checked for array or str but included in the final report
    public function sendSystemReport($context = null)
    {
        // generate a report of the current system and email it
        $message = '<html><head><title>System Report for: '.HTTP_HOST.'</title></head><body>';
        $message .= '<pre>';
        $message .= print_r($_SERVER, true);
        $message .= print_r($_SESSION, true);
        $message .= print_r($this, true);
        $message .= '</body></html>';

        echo $message;

    }

    public function sendInfo($recipient_array, $subject=null, $message)
    {
        if (empty($subject))
            $subject = 'System Notification';
        $txtBody = $message;

        $To = $recipient_array;
        $From = array($this->noreply => $this->site_title.' Information');
        $template = COMMON . '/templates/tmpl.email.send_notice.html';
        $htmlbody = file_get_contents($template);
        $htmlbody = str_replace( "{BODY_MESSAGE}", $message, $htmlbody );
        $htmlbody = str_replace( "{SITE_TITLE}", $this->site_name, $htmlbody );

        //$From = array(), $To = array(), $Subject = null, $htmlBody = null, $txtBody = null, $CC = null, $BCC = null, $Attachments = null
        if (ENVIRONMENT == 'production') {
            if ($this->mail->sendEmail($From, $To, $subject, $htmlbody, $txtBody, null, null)) {
                return true;
            }
        }
        else {
            // not in production, send it to my email instead
            if ($this->mail->sendEmail($From, array('nathan@codezilla.xyz' => "Nathan O'Brennan"), $subject, $htmlbody, $txtBody, null, null)) {
                return true;
            }
        }
        return false;
    }

    public function writeLog()
    {
        if (session_id()) {
            if (isset($_SESSION['log_message'])) {
                foreach($_SESSION['log_message'] as $idx => $log) {
                    unset($_SESSION['log_message'][$idx]);
                    if (strlen($log['message']) < 65535) {
                        $query = array();
                        $query['insert']['log'][] = array('date' => $log['date'], 'priority' => $log['priority'], 'message' => addslashes($log['message']));
                        if (isset($query)) {
                            if (is_object($this->database)) {
                                if ($result = $this->database->insert($query)) {
                                    continue;
                                }
                            }
                        }
                    }
                    // the data is too big for the database, so dump it to the log file
                    // using the function
                    writeLog($log);
                }
            }
            return true;
        }
        return false;
    }

    public function __destruct()
    {
        $this->writeLog();
        if (defined('CLEAR_SESSION')) {
            if (CLEAR_SESSION == true) {
                session_unset();
                session_destroy();
            }
        }
    }
}

/*
 * The environment class only exists to save a fraction of memory instead of using a php stdClass object
 */
class Environment {
    public $dbapikey;
    public $dbdriver;
    public $dbfile;
    public $dbhost;
    public $dbname;
    public $dbpass;
    public $dbport;
    public $dbprefix;
    public $dbtype;
    public $dbuser;
    public $debug;
    public $display_errors;
    public $domain;
    public $environment;
    public $environment_id;
    public $error_reporting;
    public $html_errors;
    public $id;
    public $ip_security;
    public $mail_type;
    public $noreply;
    public $passminlength;
    public $passreqlow;
    public $passreqnum;
    public $passreqsym;
    public $passrequp;
    public $registration;
    public $smtp_auth;
    public $smtp_host;
    public $smtp_pass;
    public $smtp_port;
    public $smtp_user;
    public $support;
    public $timezone;
    public $use_https;
    public function __construct($params = null)
    {
        if (! is_null($params)) {
            foreach($params as $name => $object) {
                if ($object)
                    $this->$name = $object;
            }
        }
    }
}

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
 * Framework Configuration
 *
 * classes must be named in StudlyCaps
 * methods must be named in camelCase
 * properties must be named in under_score
 * constants must be UPPERCASE
 * protected words (true, false, null) must be LOWERCASE (ha ha!)
 *
 * This is the initial configuration script intended to establish defaults
 * and configure the Vault and further database connections. Once the
 * framework has been configured this script should never be run again.
******************************************************************************/
class EnvironmentConfig extends Codezilla
{

    public $debug = false;
    public $code;
    public $environment;
    public $vault;
    public $vault_db;

    public function __construct($code)
    {
        log_message('debug', __CLASS__, 'framework_environment_config-> class instantiated');
        foreach($code as $prop => $value) {
            if (is_string($value))
                $this->$prop = $value;
        }
        $this->vault = $code->vault;
        $this->environment = $code->environment;

        /*********************************
         * Process Form
        *********************************/
        $continue = true;
        if (isset($_SESSION['CONFIGURATION'])) {
            if (isset($_POST['reset'])) {
                if (session_id()) {
                    session_unset();
                    session_destroy();
                }
                refresh('1', 'Please wait');
                die;
            }
            if (isset($_POST['framework_environment'])) {
                foreach($_SESSION['CONFIGURATION']['CONFIG'] as $table => $tdata) {
                    foreach($tdata as $key => $data) {
                        if (isset($_POST[$key])) {
                            if ($this->_sanitize($key, $_POST[$key])) {
                                $_SESSION['CONFIGURATION']['CONFIG'][$table][$key] = $_POST[$key];
                            }
                            else{
                                echo 'failed to sanitize key: '.$key.' and value of: ' . $_POST[$key] .'<br>';
                                $continue = false;
                            }
                        }
                    }
                }
                // set a step?
                if ($continue) {
                    if (isset($_POST['step'])) {
                        if (is_numeric($_POST['step'])) {
                            $step = $_POST['step'] +1;
                            $_SESSION['CONFIGURATION']['STEP'] = $step;
                        }
                    }
                }
                (isset($_POST['initialize_tables']))
                    ? $_SESSION['CONFIGURATION']['initialize_tables'] = 1
                    : $_SESSION['CONFIGURATION']['initialize_tables'] = 0;
            }
        }

        /*********************************
         * Configuration Steps
        *********************************/
        if (isset($_SESSION['CONFIGURATION'])) {
            $this->configHead();
            $this->showStatus();
            if ($_SESSION['CONFIGURATION']['STEP'] == 1) {
                $this->setEnvironment();
                $this->configFoot();
            }
            if ($_SESSION['CONFIGURATION']['STEP'] == 2) {
                $this->setDatabase();
                $this->configFoot();
            }
            if ($_SESSION['CONFIGURATION']['STEP'] == 3) {
                $this->setDatabaseConfig();
                $this->configFoot();
            }
            if ($_SESSION['CONFIGURATION']['STEP'] == 4) {
                $this->setConfirm();
                $this->configFoot();
            }
            if ($_SESSION['CONFIGURATION']['STEP'] == 5 || $_SESSION['CONFIGURATION']['STEP'] == 6) {
                // now we process all the details
                // write all the details to the vault and
                // attempt to connect to the initial database
                // and create basic tables

                // the default cookie is named using a generic md5 hash of 16 characters
                // by doing this it is not obvious what the cookie is for. The cookie is used to store
                // an encryption key on the user side.
                $default_cookie = substr(md5(rand()), 0, 16);
                //$default_cookie = substr(md5(gmdate('F j, Y'), true), 0, 16);
                //$default_cookie = 'cypU1H3vghKqmBdF';
                $sql = array();

                ($_SESSION['CONFIGURATION']['CONFIG']['environment']['debug'] == 'Yes')
                    ? $session_debug = 1
                    : $session_debug = 0;

                ($_SESSION['CONFIGURATION']['CONFIG']['environment']['display_errors'] == 'Yes')
                    ? $session_display_errors = 1
                    : $session_display_errors = 0;

                ($_SESSION['CONFIGURATION']['CONFIG']['environment']['html_errors'] == 'Yes')
                    ? $session_html_errors = 1
                    : $session_html_errors = 0;

                ($_SESSION['CONFIGURATION']['CONFIG']['environment']['error_reporting'] == 'Yes')
                    ? $session_error_reporting = 1
                    : $session_error_reporting = 0;

                // initialize the database
                if ($_SESSION['CONFIGURATION']['CONFIG']['database']['dbtype'] == 'MySQLi') {
                    $sql = array();
                    $sql['update']['environment'] = array(
                        'environment'       => $_SESSION['CONFIGURATION']['CONFIG']['environment']['environment'],
                        'debug'             => $session_debug,
                        'display_errors'    => $session_display_errors,
                        'html_errors'       => $session_html_errors,
                        'error_reporting'   => $session_error_reporting,
                        'dbtype'            => $_SESSION['CONFIGURATION']['CONFIG']['database']['dbtype'],
                        'dbuser'            => $_SESSION['CONFIGURATION']['CONFIG']['database']['dbuser'],
                        'dbpass'            => $_SESSION['CONFIGURATION']['CONFIG']['database']['dbpass'],
                        'dbname'            => $_SESSION['CONFIGURATION']['CONFIG']['database']['dbname'],
                        'dbhost'            => $_SESSION['CONFIGURATION']['CONFIG']['database']['dbhost'],
                        'dbport'            => $_SESSION['CONFIGURATION']['CONFIG']['database']['dbport'],
                        'dbprefix'          => $_SESSION['CONFIGURATION']['CONFIG']['database']['dbprefix']
                    );
                    $sql['where']['environment'] = array('environment' => 'Rescue');

                    // update the vault with this information
                    $this->vault->update($sql);

                    // initialize the database class
                    if ($database = $this->loadClass('Database', null, array(
                        'adaptor'   => 'MySQLi',
                        'db_host'   => $_SESSION['CONFIGURATION']['CONFIG']['database']['dbhost'],
                        'db_port'   => $_SESSION['CONFIGURATION']['CONFIG']['database']['dbport'],
                        'db_name'   => $_SESSION['CONFIGURATION']['CONFIG']['database']['dbname'],
                        'db_user'   => $_SESSION['CONFIGURATION']['CONFIG']['database']['dbuser'],
                        'db_pass'   => $_SESSION['CONFIGURATION']['CONFIG']['database']['dbpass'],
                        'debug'     => $this->debug
                    ))) {

                        // attempt to connect
                        if ($database->connect()) {
                            if ($database->active() != 1) {
                                if ($database->active() == 1045) {
                                    // this is an access denied error
                                    $_SESSION['CONFIGURATION']['STEP'] = 3;
                                    $_SESSION['messages'][] = 'Database Connection Failure. The error was: '.$database->errno;
                                    $_SESSION['messages'][] = 'The error message is: '.$database->error;
                                    refresh('0');
                                    die;
                                }
                            }
                        }

                        // We successfully connected to the database
                        // if this is the initial configuration then we need to present the option to
                        // initialize the tables, otherwise we need to reboot and move on
                        if ($_SESSION['CONFIGURATION']['STEP'] == 5) {
                            ?>
                            <form name="framework_environment_initialize_tables" method="post">
                                <table class="table">
                                    <tbody>
                                        <tr>
                                            <td>
                                                <input type="hidden" name="step" value="<?php echo $_SESSION['CONFIGURATION']['STEP']; ?>">
                                                Initialize Database? (this will destroy any existing data!) &nbsp; <input type="checkbox" value="1" name="initialize_tables">
                                            </td>
                                            <td><input type="submit" value="Continue" name="framework_environment" class="btn btn-primary pull-right"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </form>
                            <?php
                        }

                        // Step 6 needs to connect to the database.
                        // Attempt to establish a connection and then offer to use existing database or initialize new tables
                        if ($_SESSION['CONFIGURATION']['STEP'] == 6) {
                            if ($_SESSION['CONFIGURATION']['initialize_tables']) {
                                if (file_exists(SYSTEM . DIRECTORY_SEPARATOR . '_install' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'codezilla_mysql.sql')) {
                                    if ($initialize = file_get_contents(SYSTEM . DIRECTORY_SEPARATOR . '_install' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'codezilla_mysql.sql')) {
                                        if ($this->database->initial_config()) {
                                            $_SESSION['CONFIGURATION']['STEP'] = 8;
                                            refresh(5, 'Database configuration complete');
                                        }
                                        else {
                                            // If the database initialization could not complete then we need to
                                            // start over... but from where?
                                            $_SESSION['CONFIGURATION']['STEP'] = 3;
                                            $_SESSION['messages'][] = 'Database Connection Failure. The error was: '.$database->errno;
                                            $_SESSION['messages'][] = 'The error message is: '.$database->error;
                                            refresh('0');
                                            die;
                                        }
                                    }
                                }
                                else {
                                    // if the sql source file does not exist, then push back a step
                                    // because it iterates when the process begins and we need to come back here.
                                    $_SESSION['CONFIGURATION']['STEP'] = 7;
                                    refresh('0',null);
                                }
                            }
                            else {
                                // just refresh
                                $_SESSION['CONFIGURATION']['STEP'] = 8;
                                refresh('0',null);
                            }
                        }
                    }
                }

                if ($_SESSION['CONFIGURATION']['CONFIG']['database']['dbtype'] == 'SQLite') {
                    $sql = array();
                    $sql['update']['environment'] = array(
                        'environment'       => $_SESSION['CONFIGURATION']['CONFIG']['environment']['environment'],
                        'debug'             => $session_debug,
                        'display_errors'    => $session_display_errors,
                        'html_errors'       => $session_html_errors,
                        'error_reporting'   => $session_error_reporting,
                        'dbtype'            => 'PDO',
                        'dbdriver'          => 'sqlite',
                        'dbfile'            => $_SESSION['CONFIGURATION']['CONFIG']['database']['dbfile']
                    );
                    $sql['where']['environment'] = array('environment' => 'Rescue');
                    $this->vault->update($sql);
                    // SQLite file likely doesn't exist, so check
                    speak('checking if the sqlite database exists', true);
                    if (!file_exists($_SESSION['CONFIGURATION']['CONFIG']['database']['dbfile'])) {
                        speak('sqlite database does not exist', true);
                        if (file_exists( SYSTEM . DIRECTORY_SEPARATOR . '_install' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'codezilla_sqlite.sql')) {
                            speak('the sqlite sql script exists. creating initial database file', true);
                            if ($fh = fopen($_SESSION['CONFIGURATION']['CONFIG']['database']['dbfile'], 'w')) {
                                speak('Creating the INITIAL SQLite tables', $this->debug);
                                $database = $this->loadClass('Database', null, array(
                                    'adaptor'           => 'PDO',
                                    'driver'          => 'sqlite',
                                    'dbfile'            => $_SESSION['CONFIGURATION']['CONFIG']['database']['dbfile']
                                ));
                                if (is_object($database)) {
                                    // create the initial tables
                                    speak('need to create the initial tables', true);

                                    refresh('5', 'Configuration Complete. Please wait for automated refresh.');
                                    // this will force
                                    $_SESSION['CONFIGURATION']['STEP'] = 6;
                                }
                            }
                            else {
                                die('The database could not be created. Please check your PHP process has write permissions to the following: '.$_SESSION['CONFIGURATION']['CONFIG']['database']['dbfile']);
                            }
                        }
                        else {
                            die('The necessary sqlite database does not exist. You have an incomplete installation.');
                        }
                    }
                    else {
                        // We successfully connected to the database
                        // if this is the initial configuration then we need to present the option to
                        // initialize the tables, otherwise we need to reboot and move on
                        if ($_SESSION['CONFIGURATION']['STEP'] == 6) {

                            if ($_SESSION['CONFIGURATION']['initialize_tables']) {
                                die('initializing the tables...');
                            }
                            else {
                                // just refresh
                                $_SESSION['CONFIGURATION']['STEP'] = 8;
                                refresh('0',null);
                            }
                        }

                        if ($_SESSION['CONFIGURATION']['STEP'] == 5) {
                            ?>
                            <form name="framework_environment_initialize_tables" method="post">
                                <table class="table">
                                    <tbody>
                                        <tr>
                                            <td>
                                                <input type="hidden" name="step" value="<?php echo $_SESSION['CONFIGURATION']['STEP']; ?>">
                                                Initialize Database? (this will destroy any existing data!) &nbsp; <input type="checkbox" value="1" name="initialize_tables">
                                            </td>
                                            <td><input type="submit" value="Continue" name="framework_environment" class="btn btn-primary pull-right"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </form>
                            <?php
                        }
                    }
                }

                //// THIS IS NOT WORKING
                //if ($_SESSION['CONFIGURATION']['CONFIG']['database']['dbtype'] == 'WebAPI') {
                //    $sql = array();
                //    $sql['update']['environment'] = array(
                //        'environment'       => $_SESSION['CONFIGURATION']['CONFIG']['environment']['environment'],
                //        'debug'             => $session_debug,
                //        'display_errors'    => $session_display_errors,
                //        'html_errors'       => $session_html_errors,
                //        'error_reporting'   => $session_error_reporting,
                //        'dbtype'            => $_SESSION['CONFIGURATION']['CONFIG']['database']['dbtype'],
                //        'dbdriver'          => $_SESSION['CONFIGURATION']['CONFIG']['database']['dbdriver'],
                //        'dbapikey'          => $_SESSION['CONFIGURATION']['CONFIG']['database']['dbapikey'],
                //        'dbname'            => $_SESSION['CONFIGURATION']['CONFIG']['database']['dbname'],
                //    );
                //    $sql['where']['environment'] = array('environment' => 'Rescue');
                //    $this->vault->update($sql);
                //    $database = $this->loadClass('Database', null, array('dbapikey' => $_SESSION['CONFIGURATION']['CONFIG']['database']['dbapikey']));
                //    if ($database->testConnect()) {
                //        // all is confirmed
                //        $database->initial_config();
                //        refresh('5', 'Configuration Complete. Please wait for automated refresh.');
                //    }
                //}
                //
                //// THIS IS NOT WORKING
                //if ($_SESSION['CONFIGURATION']['CONFIG']['database']['dbtype'] == 'PDO') {
                //    if ($database = $this->loadClass('Database', null, array(
                //        'adaptor'   => 'PDO',
                //        'db_driver' => $_SESSION['CONFIGURATION']['CONFIG']['database']['dbdriver'],
                //        'db_host'   => $_SESSION['CONFIGURATION']['CONFIG']['database']['dbhost'],
                //        'db_name'   => $_SESSION['CONFIGURATION']['CONFIG']['database']['dbname'],
                //        'db_user'   => $_SESSION['CONFIGURATION']['CONFIG']['database']['dbuser'],
                //        'db_pass'   => $_SESSION['CONFIGURATION']['CONFIG']['database']['dbpass'],
                //        'debug'     => $this->debug
                //    ))) {
                //        // connection has been made, create the initial tables
                //        $database->initial_config();
                //        refresh('5', 'Configuration Complete. Please wait for automated refresh.');
                //    }
                //}

                // update the domain to use this id
                $update = array();
                $update['update']['domains'] = array('environment_id' => 1);
                $update['where']['domains'] = array('id' => 1);
                $this->vault->update($update);
            }
            // This step acquires the sql source code
            if ($_SESSION['CONFIGURATION']['STEP'] == 7) {
                // The default source code does not come with the mysql schema
                $data = array();
                $data['database_type'] = $_SESSION['CONFIGURATION']['CONFIG']['database']['dbtype'];
                $data['database_prefix'] = $_SESSION['CONFIGURATION']['CONFIG']['database']['dbprefix'];
                $data['codezilla_token'] = $this->codezilla_token;
                //show($data);

                if ($response = $this->curlPost('https://codezilla.xyz/api/codezilla-framework-database/', $data)) {
                    //show($response);
                    $result = json_decode($response);
                    $database = $result->download;
                    if ($this->curlDownload('https://codezilla.xyz/api/codezilla-framework-database/download.php?id='.$result->download, $database)) {
                        if (file_exists($database)) {
                            $xsum = hash_file('sha256', $database);
                            //show($xsum);
                            //show($result->sha256sum);
                            if ($xsum === $result->sha256sum) {
                                echo 'signatures match<br>';
                                $codezilladb = SYSTEM . DIRECTORY_SEPARATOR . '_install' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'codezilla_mysql.sql';
                                echo "Moving $database to $codezilladb".'<br>';
                                //echo 'filesize: '.filesize($database).'<br>';
                                if (rename($database, $codezilladb)) {
                                    //echo 'filesize: '.filesize($codezilladb).'<br>';
                                    sleep(2); // Windows will complain the file doesn't exist in the next step so we give it a moment to collect its thoughts #windowssucks
                                    $ysum = hash_file('sha256', $codezilladb);
                                    //show($xsum);
                                    //show($ysum);
                                    if ($xsum === $ysum) {
                                        // send back to step 6 for installation
                                        $_SESSION['CONFIGURATION']['STEP'] = 6;
                                        echo 'File successfully moved.<br>';
                                        echo 'Redirecting in 3 seconds ...';
                                        refresh(3);
                                        die;
                                    }
                                    else {
                                        unlink($codezilladb);
                                        message('The file was corrupted while renaming. Please download again.');
                                    }
                                }
                                else {
                                    unlink($codezilladb);
                                    unlink($databse);
                                    message('Failed to rename database file');
                                }
                            }
                            else {
                                unlink($database);
                                message('File failed to download. Please try again.');
                            }
                        }
                    }
                }
                $_SESSION['CONFIGURATION']['STEP'] = 5;
                message('Trying to download the database configuration script');
                refresh('10');
                die;
            }
            if ($_SESSION['CONFIGURATION']['STEP'] == 8) {
                /*
                 * Configuration Complete
                 *
                 * Configuration is complete here. Now we can refresh and should load the real site.
                 * if we have clean up to do, do it here, if that requires notifying the user, do that here
                 */
                $update = array();
                $update['update']['configuration'] = array('config_status' => 1, 'maintenance' => 1);
                if ($this->vault->update($update)) {
                    refresh('10', 'Configuration has been stored. Please wait...');
                }
                else {
                    refresh('10', 'Configuration could not be stored. Please wait...');
                }
                if (session_id()) {
                    session_unset();
                    session_destroy();
                }
                die();
            }
        }
        else {
            // No configuration has been started, so lets gather some information.
            $_SESSION['CONFIGURATION'] = array();
            $_SESSION['CONFIGURATION']['STEP'] = 1;
            $_SESSION['CONFIGURATION']['CONFIG']['config'] = array(
                'site_name'         => $this->site_name
            );
            $_SESSION['CONFIGURATION']['CONFIG']['domain'] = array(
                'domain'            => $this->environment->domain,
                'use_https'         => $this->environment->use_https
            );
            $_SESSION['CONFIGURATION']['CONFIG']['environment'] = array(
                'environment'       => 'Rescue',
                'debug'             => '',
                'display_errors'    => '',
                'html_errors'       => '',
                'error_reporting'   => ''
            );
            $_SESSION['CONFIGURATION']['CONFIG']['database'] = array(
                'dbtype'        => '',
                'dbdriver'      => '',
                'dbapikey'      => '',
                'dbuser'        => '',
                'dbpass'        => '',
                'dbname'        => '',
                'dbhost'        => '',
                'dbport'        => '3306',
                'dbprefix'      => 'cz_',
                'dbfile'        => CONFIG . DIRECTORY_SEPARATOR . 'Codezilla.db'
            );
            $this->configHead();
            refresh('1', 'Loading environment configuration, please wait.');
        }
    }

    public function configHead()
    {
        ?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Codezilla Framework Environment Setup</title>
    </head>
    <style>
        .error-message{
            padding: 10px;
            margin: 25px;
            background-color: grey; color: white;
        }
    </style>

    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.0/css/bootstrap.min.css">

    <!-- jQuery library -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>

    <!-- Latest compiled JavaScript -->
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.0/js/bootstrap.min.js"></script>
    <div class="jumbotron text-center">
        <h1>Codezilla PHP Framework</h1>
    </div>
    <body>
        <div class="container">
        <?php
        if (isset($_SESSION['messages'])) {
            foreach($_SESSION['messages'] as $idx => $message) {
                echo '<p class="error-message">'.$message.'</p>';
                unset($_SESSION['messages'][$idx]);
            }
        }
        ?>
        <h1>Framework Configuration</h1>
        <?php
    }

    public function configFoot()
    {
        ?>
    </body>
</html>
        <?php
    }

    public function setEnvironment()
    {
        ($_SESSION['CONFIGURATION']['CONFIG']['environment']['debug'])
            ? $debug = 'selected'
            : $debug = '';

        ($_SESSION['CONFIGURATION']['CONFIG']['environment']['display_errors'])
            ? $display_errors = 'selected'
            : $display_errors = '';

        ($_SESSION['CONFIGURATION']['CONFIG']['environment']['html_errors'])
            ? $html_errors = 'selected'
            : $html_errors = '';

        ($_SESSION['CONFIGURATION']['CONFIG']['environment']['error_reporting'])
            ? $error_reporting = 'selected'
            : $error_reporting = '';

        if (isset($_SESSION['CONFIGURATION']['CONFIG']['environment']['environment'])) {
            if (empty($_SESSION['CONFIGURATION']['CONFIG']['environment']['environment']))
                $_SESSION['CONFIGURATION']['CONFIG']['environment']['environment'] = 'Rescue';
        }
        ?>
<h3>Environment Configuration</h3>
<p>
    The framework uses "Environment's" to determine how a Domain should connect to its database and how
    the framework should handle debugging and reporting.
</p>
<p>
    You can have multiple "Environment's" configured, such as Development, Testing, and Production. The purpose
    is to attach a Domain to an Environment. When the system detects the Domain it will apply the proper configuration
    and reporting details as per the Environment.
</p>
<p>
    For now, we will only configure the "Rescue" environment as it will be used in the event of a real rescue to update
    your configuration. Once you have completed the initial setup you will be able to configure additional environments.
    You may disable any of the error reporting below, for the Rescue environment, but we suggest you leave it enabled.
</p>
<form name="setup" action="" method="post">
    <table class="table">
        <tbody>
            <tr>
                <td>Environment Name</td>
                <td><?php echo $_SESSION['CONFIGURATION']['CONFIG']['environment']['environment']; ?></td>
            </tr>
            <tr>
                <td>Debug</td>
                <td><select name="debug"><option value="No">Disabled</option><option selected value="Yes" <?php echo $debug; ?>>Enabled</option></select></td>
            </tr>
            <tr>
                <td>Error Reporting</td>
                <td><select name="error_reporting"><option value="No">Disabled</option><option selected value="Yes" <?php echo $error_reporting; ?>>Enabled</option></select></td>
            </tr>
            <tr>
                <td>Display Errors</td>
                <td><select name="display_errors"><option value="No">Disabled</option><option selected value="Yes" <?php echo $display_errors; ?>>Enabled</option></select></td>
            </tr>
            <tr>
                <td>HTML Errors</td>
                <td><select name="html_errors"><option value="No">Disabled</option><option selected value="Yes" <?php echo $html_errors; ?>>Enabled</option></select></td>
            </tr>
            <tr>
                <td><input type="hidden" name="step" value="<?php echo $_SESSION['CONFIGURATION']['STEP']; ?>"></td>
                <td><input type="submit" value="Continue" name="framework_environment" class="btn btn-primary pull-right"></td>
            </tr>
        </tbody>
    </table>
</form>
        <?php
    }

    public function setDatabase()
    {
        $dbtype_mysqli  = '';
        $dbtype_pdo     = '';
        $dbtype_sqlite  = '';
        $dbtype_webapi  = '';

        if ($_SESSION['CONFIGURATION']['CONFIG']['database']['dbtype'] == 'MySQLi') { $dbtype_mysqli = 'selected'; }
        if ($_SESSION['CONFIGURATION']['CONFIG']['database']['dbtype'] == 'PDO') { $dbtype_pdo = 'selected'; }
        if ($_SESSION['CONFIGURATION']['CONFIG']['database']['dbtype'] == 'SQLite') { $dbtype_sqlite = 'selected'; }
        if ($_SESSION['CONFIGURATION']['CONFIG']['database']['dbtype'] == 'WebAPI') { $dbtype_webapi = 'selected'; }

        ?>
<h3>Database Configuration</h3>
<p>
    Please use the following to configure the database connections for *this* environment. Don't forget, each
    environment can have its own database configuration.
</p>
<ul>
    <li>MySQLi</li>
    Tried and true industry standard for reliability and speed. MySQL/MariaDB likely powers the majority of the internet. Though PDO is available
    if you intend to use a MySQL/MariaDB database the native MySQLi library is faster than PDO.

    <li>PDO</li>
    PHP Data Objects is the defacto standard method for utilizing various databases of all kinds. The available options are
    MySQL, Microsoft SQL, PostGreSQL, SQLite, and many others. PDO has the distinct advantage of being able to switch
    between database providers by changing the PDO driver, although code changes are necessary for each driver type.
    However, the Codezilla framework offers an abstraction layer allowing seamless switching between database drivers while
    writing your queries exactly the same. *Only when utilizing the Codezilla Database class*

    <li>SQLite</li>
    Extremely fast local file based database for small to medium size websites and applications.

    <li>Web API</li>
    Codezilla offers access to a hosted API that can act as your database. If you are using a hosted database from Codezilla
    select this as your database type.
</ul>
<form name="setup" action="" method="post">
    <table class="table">
        <tbody>
            <tr>
                <td>Database Type</td>
                <td>
                    <select name="dbtype" required>
                        <option value="" disabled selected>Please Select</option>
                        <option <?php echo $dbtype_mysqli; ?> value="MySQLi">MySQLi (Recommended)</option>
                        <!-- <option <?php echo $dbtype_pdo; ?> value="PDO">PDO</option> -->
                        <option <?php echo $dbtype_sqlite; ?> value="SQLite">SQLite</option>
                        <!-- <option <?php echo $dbtype_webapi; ?> value="WebAPI">WebAPI</option> -->
                    </select>
                </td>
            </tr>
            <tr>
                <td><input type="hidden" name="step" value="<?php echo $_SESSION['CONFIGURATION']['STEP']; ?>"></td>
                <td><input type="submit" value="Continue" name="framework_environment" class="btn btn-primary pull-right"></td>
            </tr>
        </tbody>
    </table>
</form>
        <?php
    }

    public function setDatabaseConfig()
    {
        $dbdriver_mysql  = '';
        $dbdriver_odbc   = '';
        $dbdriver_sqlite = '';
        $dbdriver_sqlsrv = '';

        if ($_SESSION['CONFIGURATION']['CONFIG']['database']['dbdriver'] == 'MySQL') { $dbdriver_mysql = 'selected'; }
        if ($_SESSION['CONFIGURATION']['CONFIG']['database']['dbdriver'] == 'ODBC') { $dbdriver_odbc = 'selected'; }
        if ($_SESSION['CONFIGURATION']['CONFIG']['database']['dbdriver'] == 'SQLITE') { $dbdriver_sqlite = 'selected'; }
        if ($_SESSION['CONFIGURATION']['CONFIG']['database']['dbdriver'] == 'SQLSRV') { $dbdriver_sqlsrv = 'selected'; }

        ?>
<form name="setup" action="" method="post">
    <table class="table">
        <tbody>
        <?php
        if ($_SESSION['CONFIGURATION']['CONFIG']['database']['dbtype'] == 'MySQLi') {
            ?>
            <tr>
                <td>Database Host</td>
                <td><input type="text" name="dbhost" value="<?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbhost']; ?>" required></td>
            </tr>
            <tr>
                <td>Database Name</td>
                <td><input type="text" name="dbname" value="<?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbname']; ?>" required></td>
            </tr>
            <tr>
                <td>Database Prefix</td>
                <td><input type="text" name="dbprefix" value="<?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbprefix']; ?>" required></td>
            </tr>
            <tr>
                <td>Database Port</td>
                <td><input type="text" name="dbport" value="<?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbport']; ?>" required></td>
            </tr>
            <tr>
                <td>Database User</td>
                <td><input type="text" name="dbuser" value="<?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbuser']; ?>" required></td>
            </tr>
            <tr>
                <td>Database Password</td>
                <td><input type="text" name="dbpass" value="<?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbpass']; ?>" required></td>
            </tr>
            <?php
        }
        if ($_SESSION['CONFIGURATION']['CONFIG']['database']['dbtype'] == 'PDO') {
            ?>
            <tr>
                <td>Database Driver</td>
                <td>
                    <select name="dbdriver">
                        <option selected value="" disabled>Please Select</option>
                        <option <?php echo $dbdriver_mysql; ?> value="PDO_MySQL">MySQL</option>
                        <option <?php echo $dbdriver_odbc; ?> value="PDO_ODBC">ODBC</option>
                        <option <?php echo $dbdriver_sqlite; ?> value="PDO_SQLITE">SQLite</option>
                        <option <?php echo $dbdriver_sqlsrv; ?> value="PDO_SQLSRV">Microsoft SQL Server</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td>Database Name</td>
                <td><input type="text" name="dbname" value="<?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbname']; ?>" required></td>
            </tr>
            <tr>
                <td>Database Host</td>
                <td><input type="text" name="dbhost" value="<?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbhost']; ?>" required></td>
            </tr>
            <tr>
                <td>Database User</td>
                <td><input type="text" name="dbuser" value="<?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbuser']; ?>" required></td>
            </tr>
            <tr>
                <td>Database Password</td>
                <td><input type="text" name="dbpass" value="<?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbpass']; ?>" required></td>
            </tr>
            <?php
        }
        if ($_SESSION['CONFIGURATION']['CONFIG']['database']['dbtype'] == 'WebAPI') {
            ?>
            <tr>
                <td>WEB Api Key</td>
                <td><input type="text" name="dbapikey" value="<?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbapikey']; ?>" required></td>
            </tr>
            <?php
        }
        if ($_SESSION['CONFIGURATION']['CONFIG']['database']['dbtype'] == 'SQLite') {
            ?>
            <tr>
                <td></td>
                <td>No Further Configuration is Needed</td>
            </tr>
            <?php
        }
        ?>
            <tr>
                <td><input type="hidden" name="step" value="<?php echo $_SESSION['CONFIGURATION']['STEP']; ?>"></td>
                <td><input type="submit" value="Continue" name="framework_environment" class="btn btn-primary pull-right"></td>
            </tr>
        </tbody>
    </table>
</form>
        <?php
    }

    public function setConfirm()
    {
        ?>
<h3>Complete Configuration</h3>
<p>
    Configuration is complete. If you approve the above details click Finish to write the details to the Vault and
    configured the initial databases.
</p>
<form name="setup" action="" method="post">
    <table class="table">
        <tbody>
            <tr>
                <td><input type="hidden" name="step" value="<?php echo $_SESSION['CONFIGURATION']['STEP']; ?>"></td>
                <td><input type="submit" name="reset" value="Reset" class="btn btn-secondary pull-right"> &nbsp; <input type="submit" value="Complete" name="framework_environment" class="btn btn-primary pull-right"></td>
            </tr>
        </tbody>
    </table>
</form>
        <?php
    }

    public function showStatus()
    {
        ?>
<table class="table">
    <thead>
        <tr>
            <th>Framework Environment Review</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <th>Site Name</th>
            <td><?php echo $_SESSION['CONFIGURATION']['CONFIG']['config']['site_name']; ?></td>
        </tr>
        <tr>
            <th>Domain</th>
            <td><?php if ($_SESSION['CONFIGURATION']['CONFIG']['domain']['use_https']){ echo 'https://';}else{echo 'http://';} ?><?php echo $_SESSION['CONFIGURATION']['CONFIG']['domain']['domain']; ?></td>
        </tr>
        <tr>
            <th>Environment</th>
            <td><?php echo $_SESSION['CONFIGURATION']['CONFIG']['environment']['environment']; ?></td>
        </tr>
        <tr>
            <th>Debug</th>
            <td><?php echo $_SESSION['CONFIGURATION']['CONFIG']['environment']['debug']; ?></td>
        </tr>
        <tr>
            <th>Error Reporting</th>
            <td><?php echo $_SESSION['CONFIGURATION']['CONFIG']['environment']['error_reporting']; ?></td>
        </tr>
        <tr>
            <th>Display Errors</th>
            <td><?php echo $_SESSION['CONFIGURATION']['CONFIG']['environment']['display_errors']; ?></td>
        </tr>
        <tr>
            <th>HTML Errors</th>
            <td><?php echo $_SESSION['CONFIGURATION']['CONFIG']['environment']['html_errors']; ?></td>
        </tr>
        <tr>
            <th>Database Type</th>
            <td><?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbtype']; ?></td>
        </tr>

        <?php
            if ($_SESSION['CONFIGURATION']['CONFIG']['database']['dbtype'] == 'MySQLi') {
                ?>
        <tr>
            <th>Database Host</th>
            <td><?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbhost']; ?></td>
        </tr>
        <tr>
            <th>Database Name</th>
            <td><?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbname']; ?></td>
        </tr>
        <tr>
            <th>Database Prefix</th>
            <td><?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbprefix']; ?></td>
        </tr>
        <tr>
            <th>Database User</th>
            <td><?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbuser']; ?></td>
        </tr>
        <tr>
            <th>Database Password</th>
            <td><?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbpass']; ?></td>
        </tr>
                <?php
            }

            if ($_SESSION['CONFIGURATION']['CONFIG']['database']['dbtype'] == 'PDO') {
                ?>
        <tr>
            <th>Database Driver</th>
            <td><?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbdriver']; ?></td>
        </tr>
        <tr>
            <th>Database Host</th>
            <td><?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbhost']; ?></td>
        </tr>
        <tr>
            <th>Database Name</th>
            <td><?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbname']; ?></td>
        </tr>
        <tr>
            <th>Database Prefix</th>
            <td><?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbprefix']; ?></td>
        </tr>
        <tr>
            <th>Database User</th>
            <td><?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbuser']; ?></td>
        </tr>
        <tr>
            <th>Database Password</th>
            <td><?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbpass']; ?></td>
        </tr>
                <?php
            }

            if ($_SESSION['CONFIGURATION']['CONFIG']['database']['dbtype'] == 'WebAPI') {
                ?>
        <tr>
            <th>Web API Key</th>
            <td><?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbapikey']; ?></td>
        </tr>
                <?php
            }

            if ($_SESSION['CONFIGURATION']['CONFIG']['database']['dbtype'] == 'SQLite') {
                ?>
        <tr>
            <th>SQLite File Location</th>
            <td><?php echo $_SESSION['CONFIGURATION']['CONFIG']['database']['dbfile']; ?></td>
        </tr>
                <?php
            }
        ?>
    </tbody>
</table>
<br />
<br />
<br />
        <?php
    }


    private function _sanitize($key, $str)
    {
        // if (!preg_match('/[^a-z_\-0-9. -]/i', $str)) {
        if (!preg_match('/[^a-z_\-0-9. \!@#\$%\^&\*\(\)-]/i',$str)) {
            return $str;
        }
        return false;
    }

    public function __destruct(){}

}

// instantiate the class and let's go!
if (FRAMEWORK_CONFIG) {
    // Likely the session doesn't exist, so get it started
    if (!session_id()) {
        session_name('Codezilla');
        session_start();
    }

    if (session_id()) {
        global $code;
        $database_config = new EnvironmentConfig($code);
    }
}
//show($this);

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
ini_set('display_errors', 1);
ini_set('html_errors', 1);
error_reporting(E_ALL | E_STRICT | E_DEPRECATED);

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
 * This script is intended to download the empty Vault database for initial
 * configuration and setup.
******************************************************************************/
class Acquire extends Codezilla
{
    public $debug = false;

    public $activation_code;
    public $registration_email;

    public function __construct()
    {
        log_message('debug', __CLASS__, 'framework_acquire-> Acquire class instantiated');
        if (isset($_POST['getActivationCode'])) {
            // immediately store the email if it is a valid email
            if (isset($_POST['registration_email'])) {
                unset($_SESSION['registration_email']);
                if (!filter_var($_POST['registration_email'], FILTER_VALIDATE_EMAIL) === FALSE) {
                    $_SESSION['registration_email'] = $_POST['registration_email'];
                }
                else {
                    message('The email address provided was invalid, please enter a valid email address in the format: user@domain.tld');
                }
            }
            // check the activation code matches the format
            if (isset($_POST['activation_code'])) {
                unset($_SESSION['activation_code']);
                if ($this->validateActivationCode()) {
                    $_SESSION['activation_code'] = $_POST['activation_code'];
                }
                else {
                    message('The activation code could not be submitted because it contains errors. Please try again.');
                }
            }
        }

        if (isset($_SESSION['registration_email'])) {
            $this->registration_email = $_SESSION['registration_email'];
        }

        if (isset($_SESSION['activation_code'])) {
            $this->activation_code = $_SESSION['activation_code'];
        }

        // if the page is fresh loaded clear all stored settings
        if (count($_POST) == 0) {
            if (session_id()) {
                session_unset();
                session_destroy();
            }
        }

        // load the header
        $this->header();

        // if both properties are set we can submit and move on
        if ((!empty($this->registration_email)) && (!empty($this->activation_code))) {
            $data = array();
            $data['registration_email'] = $this->registration_email;
            $data['activation_code']    = $this->activation_code;
            //show($data);

            if ($response = $this->curlPost('https://codezilla.xyz/api/codezilla-framework-activation/', $data)) {
                //show($response);
                $result = json_decode($response);
                $database = $result->download;
                $_SESSION['recovery_keys'] = $result->recovery_keys;
                if ($this->curlDownload('https://codezilla.xyz/api/codezilla-framework-activation/download.php?id='.$result->download, $database)) {
                    if (file_exists($database)) {
                        $xsum = hash_file('sha256', $database);
                        //show($xsum);
                        //show($result->sha256sum);
                        if ($xsum === $result->sha256sum) {
                            echo 'signatures match<br>';
                            $codezilladb = CONFIG . DIRECTORY_SEPARATOR . 'config.Codezilla.db';
                            echo "Moving $database to $codezilladb".'<br>';
                            //echo 'filesize: '.filesize($database).'<br>';
                            if (rename($database, $codezilladb)) {
                                //echo 'filesize: '.filesize($codezilladb).'<br>';
                                sleep(1); // Windows will complain the file doesn't exist in the next step so we give it a moment to collect its thoughts #windowssucks
                                $ysum = hash_file('sha256', $codezilladb);
                                //show($xsum);
                                //show($ysum);
                                if ($xsum === $ysum) {
                                    echo 'File successfully moved.<br>';
                                    echo 'Redirecting in 3 seconds ...';
                                    //if (session_id())
                                    //    session_destroy();
                                    refresh(3);
                                }
                                else {
                                    echo '<p>File failed to move to correct location.</p>';
                                    refresh('30');
                                }
                            }
                        }
                    }
                }
            }
            else {
                message('The data could not be validated. A system error may have occured, please try again in a few minutes.');
                session_unset();
                session_destroy();
            }
        }
        else {
            $this->getActivationCode();
        }
    }

    public function header()
    {
        ?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Codezilla Framework Acquire Database</title>
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
    }

    public function getActivationCode()
    {
        // YYYYMMDD-HHMMSS-XXX000-XX00XX00-000-XXX
        (isset($_SESSION['activation_code']))
            ? $activation_code = $_SESSION['activation_code']
            : $activation_code = null;

        (isset($_SESSION['registration_email']))
            ? $registration_email = $_SESSION['registration_email']
            : $registration_email = null;

        echo '<h3>Acquiring the Framework configuration database</h3>';
        echo '<p>In order to continue, you must input the activation code provided after registration. If you do not have one, please either register your request, or contact support.</p>';
        echo '<form name="getActivationCode" action="" method="post">';
        echo '<table class="table">';
        echo '    <thead>';
        echo '        <tr>';
        echo '            <th scope="col">Registration Email</th>';
        echo '            <th scope="col">Activation Code</th>';
        echo '        </tr>';
        echo '    </thead>';
        echo '    <tbody>';
        echo '        <tr>';
        echo '            <td><input class="col-xs-6" name="registration_email" value="'.$registration_email.'"></td>';
        echo '            <td><input class="col-xs-10" name="activation_code" value="'.$activation_code.'"></td>';
        echo '            <td><input class="btn btn-primary" type="submit" name="getActivationCode" value="Continue"></td>';
        echo '        </tr>';
        echo '    </tbody>';
        echo '</table>';
        echo '</form>';
    }

    public function validateActivationCode()
    {
        if (isset($_POST['activation_code'])) {
            // The activation code should be in the following format
            // YYYYMMDD-HHMMSS-XXX000-XX00XX00-000-XXX
            $code = explode('-', $_POST['activation_code']);
            if (count($code) < 6) {
                return false;
            }
            if (count($code) === 6) {
                foreach($code as $idx => $segment) {
                    if (!ctype_alnum($segment)) {
                        return false;
                    }
                }
                return true;
            }
        }
        return false;
    }

    public function __destruct(){}
}

if (FRAMEWORK_CONFIG) {
    // Likely the session doesn't exist, so get it started
    if (!session_id()) {
        session_name('Codezilla');
        session_start();
    }

    if (session_id()) {
        // instantiate the class and let's go!
        $acquire = new Acquire();
    }
}

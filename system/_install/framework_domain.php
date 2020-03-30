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

class Config extends Codezilla
{

    public $debug = false;
    public $vault;

    public function __construct($vault)
    {
        log_message('debug', __CLASS__, 'framework_config-> class instantiated');
        $this->vault = $vault;
        /*********************************
         * Process Form
        *********************************/
        $continue = true;
        if (isset($_SESSION['CONFIGURATION'])) {
            if (isset($_POST['reset'])) {
                if (session_id())
                    session_destroy();
                refresh('1');
                die;
            }
            if (isset($_POST['framework_domain'])) {
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
            }
        }

        /*********************************
         * Configuration Steps
        *********************************/

        if (isset($_SESSION['CONFIGURATION'])) {
            $this->configHead();
            $this->showStatus();
            if ($_SESSION['CONFIGURATION']['STEP'] == 1) {
                $this->setSiteName();
                $this->configFoot();
            }
            if ($_SESSION['CONFIGURATION']['STEP'] == 2) {
                $this->setDomain();
                $this->configFoot();
            }
            if ($_SESSION['CONFIGURATION']['STEP'] == 3) {
                $this->setConfirm();
                $this->configFoot();
            }
            if ($_SESSION['CONFIGURATION']['STEP'] == 4) {
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
                $sql['update']['configuration'] = array(
                    'site_name'         => $_SESSION['CONFIGURATION']['CONFIG']['config']['site_name'],
                    'analytics'         => 0,
                    'maintenance'       => 1,
                    'site_theme'        => 'default',
                    'site_cookie'       => $default_cookie,
                    'config_status'   => 2
                );
                $this->vault->update($sql);

                $sql = array();
                $sql['update']['domains'] = array(
                    'domain'            => $_SESSION['CONFIGURATION']['CONFIG']['domain']['domain'],
                    'environment_id'    => 1,
                    'use_https'         => $_SESSION['CONFIGURATION']['CONFIG']['domain']['use_https']
                );
                $sql['where']['domains'] = array('id' => 1);
                $this->vault->update($sql);
                /*
                 * Configuration Complete
                 *
                 * Configuration is complete here. Now we can refresh and should load the real site.
                 * if we have clean up to do, do it here, if that requires notifying the user, do that here
                 */
                if (session_id()) {
                    session_unset();
                    session_destroy();
                }
                refresh('1');
            }
            if (isset($_SESSION['CONFIGURATION'])) {
                if ($_SESSION['CONFIGURATION']['STEP'] > 4) {
                    refresh('3', 'Reloading...');
                    define('CLEAR_SESSION', true);
                    die();
                }
            }
        }
        else {
            // No configuration has been started, so lets gather some information.
            $_SESSION['CONFIGURATION'] = array();
            $_SESSION['CONFIGURATION']['STEP'] = 1;

            $_SESSION['CONFIGURATION']['CONFIG'] = array();
            $_SESSION['CONFIGURATION']['CONFIG']['config'] = array(
                'domain'    => null,
                'site_name' => null
            );

            $_SESSION['CONFIGURATION']['CONFIG']['domain'] = array(
                'domain'            => null,
                'use_https'         => 0,
                'noreply'           => null,
                'support'           => null,
                'registration'      => null
            );
            $this->configHead();
            refresh('3', 'Loading initial configuration, please wait.');
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
        <title>Codezilla PHP Framework Initial Setup</title>
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

    public function setSiteName()
    {
        ?>
<h3>Site Name</h3>
<p>All website and applications have names, what will yours be?</p>
<form name="setup" action="" method="post">
    <table class="table">
        <tbody>
            <tr>
                <td>Site Name</td>
                <td><input type="text" name="site_name" value="<?php echo $_SESSION['CONFIGURATION']['CONFIG']['config']['site_name']; ?>" required></td>
            </tr>
            <tr>
                <td><input type="hidden" name="step" value="<?php echo $_SESSION['CONFIGURATION']['STEP']; ?>"></td>
                <td><input type="submit" name="framework_domain" value="Continue" class="btn btn-primary pull-right"></td>
            </tr>
        </tbody>
    </table>
</form>
        <?php
    }

    public function setDomain()
    {
        if (isset($_SESSION['CONFIGURATION']['CONFIG']['domain']['domain'])) {
            if (empty($_SESSION['CONFIGURATION']['CONFIG']['domain']['domain']))
                $_SESSION['CONFIGURATION']['CONFIG']['domain']['domain'] = DOMAIN;
        }
        ?>
<h3>Domain Configuration</h3>
<p>
    The framework allows for the configuration of multiple domains. For now, we are only going to focus on the
    current domain from which you are connecting. It looks like this domain should be called: <b><?php echo DOMAIN; ?></b>
</p>
<form name="setup" action="" method="post">
    <table class="table">
        <tbody>
            <tr>
                <td>Domain Name</td>
                <td><input type="text" name="domain" value="<?php echo $_SESSION['CONFIGURATION']['CONFIG']['domain']['domain']; ?>" required></td>
                <td>Use https? &nbsp; <input type="checkbox" name="use_https" value="1"></td>
            </tr>
            <tr>
                <td><input type="hidden" name="step" value="<?php echo $_SESSION['CONFIGURATION']['STEP']; ?>"></td>
                <td></td>
                <td><input type="submit" name="framework_domain" value="Continue" class="btn btn-primary pull-right"></td>
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
                <td><input type="submit" name="reset" value="Reset" class="btn btn-secondary pull-right"> &nbsp; <input type="submit" name="framework_domain" value="Continue" class="btn btn-primary pull-right"></td>
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
            <th>Framework Configuration Review</th>
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
    </tbody>
</table>
<br />
<br />
<br />
        <?php

    }


    private function _sanitize($key, $str)
    {
        if (!preg_match('/[^a-z_\-0-9. -]/i', $str)) {
            return $str;
        }
        else {
            echo 'Invalid characters in form field: ' . $key .'<br>';
        }
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
        // instantiate the class and let's go!
        $config = new Config($this->vault);
    }
}

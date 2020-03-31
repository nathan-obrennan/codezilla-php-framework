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
class Recovery extends Codezilla
{

    public $debug = false;
    public $code;
    public $code_match = 0;
    public $vault;
    public $rcodes;    // these will be the recovery codes
    public $recovery = false;

    public function __construct($code)
    {
        log_message('debug', __CLASS__, 'framework_recovery-> class instantiated');
        foreach($code as $prop => $value) {
            if (is_string($value))
                $this->$prop = $value;
        }
        $this->vault = $code->vault;

        if (isset($code->recovery))
            $this->recovery = true;

        // grab the recovery codes
        $query['select']['recovery_codes'] = array('code');
        $query['from'] = 'recovery_codes';
        $this->rcodes = array();
        if ($result = $this->vault->select($query)) {
            foreach($result as $obj) {
                foreach($obj as $recovery_code) {
                    $this->rcodes[] = $recovery_code;
                }
            }
        }

        /*********************************
         * Process Form
        *********************************/
        $continue = true;
        if (isset($_POST['framework_recovery'])) {
            if (isset($_POST['recovery_code'])) {
                if (in_array($_POST['recovery_code'], $this->rcodes)) {
                    if (isset($this->remote_ip)) {
                        ($this->recovery)
                            ? $config_status = 2
                            : $config_status = '0';
                        $cron_token = $this->vault->security->randomString('64');
                        $update['update']['configuration'] = array('config_status' => $config_status, 'maintenance' => 1, 'cron_token' => $cron_token, 'config_ipaddress' => $this->remote_ip);
                        $this->vault->update($update);
                        $this->code_match = 1; // stop display of continue button
                        $this->configHead();
                        message('The recovery code matches. Storing your IP for further configuration');
                        refresh('3', 'The Recovery Code Matches, Please Wait While Your IP is stored and you are redirected.');
                        if (session_id())
                            session_destroy();
                        die();
                    }
                    else {
                        die('no remote ip set');
                    }
                }
                else {
                    message('The recovery code is invalid. Please try again.');
                }
            }
        }
        $this->configHead();
    }

    public function configHead()
    {
        ?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Codezilla Framework Initial Setup</title>
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

        if (isset($_SESSION['recovery_keys'])) {
            $keys = explode(':', $_SESSION['recovery_keys']);
            $recovery_key_1 = $keys[0];
            $recovery_key_2 = $keys[1];
            $recovery_key_3 = $keys[2];
            $recovery_key_4 = $keys[3];
            $_SESSION['recovery_code'] = $recovery_key_1;
        }

        (isset($_SESSION['recovery_code']))
            ? $recovery_code = $_SESSION['recovery_code']
            : $recovery_code = null;
        ?>
        <h1>Framework Recovery Codes</h1>
        <form method="post" action="" name="configuration">
            <table class="table">
                <thead>
                    <td>
                        <p>Let's begin the installation. To ensure the proper party is controlling the configuration, please enter one of the recovery codes provided. Once the code is verified your IP Address will be stored and configuration will proceed. Configuration will only be allowed from the stored IP Address unless another recovery code is entered.</p>
                        <p>Upon creation of your Vault four recovery keys were provided. Please store these for future recovery needs.</p>
                        <ul>
                            <li><?php echo $recovery_key_1; ?></li>
                            <li><?php echo $recovery_key_2; ?></li>
                            <li><?php echo $recovery_key_3; ?></li>
                            <li><?php echo $recovery_key_4; ?></li>
                        </ul>
                    </td>
                </thead>
                <tbody>
                    <tr>
                        <?php if ($this->code_match == 0) {?>
                        <td><input class="input" name="recovery_code" required="" value="<?php echo $recovery_code; ?>"></td>
                        <td><input class="btn btn-primary" value="Continue" type="submit" name="framework_recovery"></td>
                        <?php }?>
                    </tr>
                </tbody>
            </table>
        </form>
    </body>
</html>
        <?php
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
        global $code;
        $recovery = new Recovery($code);
    }
}

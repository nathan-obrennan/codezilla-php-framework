<?php
/******************************************************************************
 *
 * Codezilla PHP Framework
 * Author  : Nathan O'Brennan
 * Email   : nathan@codezilla.xyz
 * Date    : Tue February 01 12:20:37 MST 2017
 * Website : https://codezilla.xyz
 * Version : 1.0
 *
******************************************************************************/
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
******************************************************************************/

/******************************************************************************
 *
 * Users class
 *
 * This class manages all things pertaining to users such as storing and
 * retrieving user information and collecting permissions for each user.
 *
 * Account Status --
 *      0 == inactive account
 *      1 == active account, all is good
 *      2 == requires password reset
 *      3 == Account has been locked by administrator
 *      4 == profit
 *
 * Account Type --
 *      0 == General Verified Email Address, Basic Account, Free Account
 *      1 == ??
 *      2 == ????
 *
 *  Security --
 *    Security is used for allowing multiple logins or forcing a single instance
 *    across shared devices.
 *    When security is set to one a user logging into the platform a second
 *    time, via another browser, or mobile, or what have you, will force the first
 *    instance to log out. When set to 0 multiple logins will be allowed.
 *      0 = Default
 *      1 = Strict
 *
 *  Permissions --
 *    General permissions which can be used by all modules are as follows:
 *      0 = no permissions
 *      1 = general user (read only viewer)
 *      2 = moderator
 *      3 = editor
 *      4 = publisher / jr. admin
 *      5 = admin
 *
 *    For modules which require finer permissions, all permissions *must*
 *    be greater than 5, so if you want your module to have a special permission
 *    for someone to perform X then the user must have a module permission
 *
 *    A user can be granted a "base permission" which will be checked throughout
 *    the system. A user with a base permission of "2" will have permissions
 *    accepted anywhere that checks for 2
 *
 *    Module permissions are based on the module_id and the permission bit
 *    appended to the end, so module_id 1230 with permission bit 6 would look like
 *    12306
 *
 *    Additionally, groups can be created which grant users specific permissions
 *    and general permissions anywhere in the system. So a user can have a base
 *    permission of 0, yet be part of the "Admin" group and gain access to various
 *    resources.
 *
******************************************************************************/
class Users
{

    private $debug = false;
    private $mail;
    private $_users = array();

    public $me;
    public $site_name;
    public $user;
    public $user_id;

    // Emails
    public $noreply;
    public $registration;
    public $support;

    public function __construct($params = null)
    {
        log_message('debug', 'class.Users-> under _construction', $this->debug);
        // process params
        if (! is_null($params)) {
            foreach($params as $name => $object) {
                if ($object)
                    $this->$name = $object;
            }
        }

        // can we auto login a current user?
        if ($this->storage->keyExists('user')) {
            log_message('debug', 'class.Users->_construct() user storage key exists', $this->debug);
            if ($secure_key = $this->storage->decrypt('user')) {
                log_message('debug', 'class.Users->_construct() secure key has been decrypted', $this->debug);
                $user_id = explode(':', $secure_key)[0];
                $verification_string = explode(':', $secure_key)[1];
                log_message('debug', 'class.Users->_construct() secure key string is:'.$verification_string, $this->debug);
                $email_address = explode(':', $secure_key)[2];
                if (is_numeric($user_id)) {
                    if ($this->userExists($user_id)) {
                        if (!filter_var($email_address, FILTER_VALIDATE_EMAIL) === FALSE) {
                            if ($this->emailExists($email_address)) {
                                log_message('debug', 'class.Users->_construct() the secure key user_id and email are valid.', $this->debug);
                                if ($verification_string === $this->getVerificationString($user_id, $email_address)) {
                                    if ($user = $this->getUser($user_id)) {
                                        if ($user->security == 1) {
                                            if ($user->session_id !== session_id()) {
                                                // the sessions do not match. This means the user has logged in else where
                                                message('Duplicate logins are not currently allowed.');
                                                $user->logout();
                                                redirectTo(HTTP_HOST.'/accounts/logout.html');
                                                die;
                                            }
                                        }
                                        log_message('debug', 'class.Users->_construct() auto login luccessful', $this->debug);
                                        $this->me = $user;
                                        $this->user_id = $user_id;
                                        if ($user->account_status == 1) {
                                            // confirmed this is me, load me up
                                            // do something here?
                                        }
                                        if ($user->account_status == 0) {
                                            if ($this->router->request_uri !=  'accounts/logout.html') {
                                                message('Your account has not been activated. Please try logging in.');
                                                redirectTo(HTTP_HOST.'/accounts/logout.html');
                                                die;
                                            }
                                        }
                                        if ($user->account_status == 2) {
                                            log_message('debug', 'class.Users->_construct() request_uri: '.$this->router->request_uri, $this->debug);
                                            if (($this->router->request_uri != 'accounts/change-password.html') && ($this->router->request_uri != 'accounts/process/process_change_password') && ($this->router->request_uri != 'accounts/logout.html')) {
                                                log_message('debug', 'class.Users->_construct() You must change your password. Redirecting...', $this->debug);
                                                message('You must change your password before you continue...');
                                                redirectTo(HTTP_HOST.'/accounts/change-password.html');
                                                die;
                                            }
                                        }
                                        if ($user->account_status == 3) {
                                            if ($this->router->request_uri !=  'accounts/logout.html') {
                                                message('Your account has been locked. Please contact support.');
                                                redirectTo(HTTP_HOST.'/accounts/logout.html');
                                                die;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        log_message('debug', 'class.Users-> class instantiated', $this->debug);
    }

    /*************************************************************************
     * function destroyEmail($email_address)
     * @access public
     *
     * This method is used only in the event that we register a new email
     * but cannot send the validation email, we delete this record so the user
     * can try again later without an error saying the account already exists.
     *
     * If you want to delete an account use the appropriate method.
    *************************************************************************/
    public function destroyEmail($email_address)
    {
        if (!filter_var($email_address, FILTER_VALIDATE_EMAIL) === FALSE) {
            if ($this->emailExists($email_address)) {
                $delete = array();
                $delete['from'] = 'users';
                $delete['where']['users'] = array('email_address' => $email_address);
                if ($this->database->delete($delete)) {
                    return true;
                }
            }
        }
        return false;
    }

    /*************************************************************************
     * function emailExists($email_address)
     * @access public
     *
     * Check if an email address exists in the system
    *************************************************************************/
    public function emailExists($email_address)
    {
        if (!filter_var($email_address, FILTER_VALIDATE_EMAIL) === FALSE) {
            $query = array();
            $query['select']['users'] = array('user_id', 'email_address');
            $query['from'] = 'users';
            $query['where']['users'] = array('email_address' => $email_address);
            if ($result = $this->database->select($query)) {
                return true;
            }
        }
        return false;
    }

    /*************************************************************************
     * function isAdmin()
     * @access public
     *
     * Check if the logged in user is an admin
    *************************************************************************/
    public function isAdmin()
    {
        if ($this->isLoggedIn()) {
            if ($this->me->isAdmin())
                return true;
        }
        return false;
    }

    /*************************************************************************
     * function isLoggedIn()
     * @access public
     *
     * Check if the current user is logged in
    *************************************************************************/
    public function isLoggedIn()
    {
        if (isset($this->me) && is_object($this->me)) {
            if ($this->me->isLoggedIn())
                return true;
        }
        return false;
        if (isset($this->user_id) && is_numeric($this->user_id)) {
            return true;
        }
        return false;
    }

    /*************************************************************************
     * function registerEmail($email_address)
     * @access public
     *
     * Stores a valid email address in the database
    *************************************************************************/
    public function registerEmail($email_address)
    {
        if (!filter_var($email_address, FILTER_VALIDATE_EMAIL) === FALSE) {
            // get the validation string...
            $verification_string = $this->security->randomString('128');
            $insert = array();
            $insert['insert']['users'][] = array(
                'email_address' => $email_address,
                'account_status' => 0,
                'session_id' => session_id(),
                'session_state' => $this->storage->get('session_state'),
                'password_hash' => $this->security->randomString('64'),
                'password_salt' => $this->security->randomString('128'),
                'display_name' => $email_address,
                'remote_ip' => $this->storage->get('remote_ip'),
                'verification_string' => $verification_string
            );
            if ($user_id = $this->database->insert($insert)) {
                return $user_id;
            }
        }
        return false;
    }

    /*************************************************************************
     * function getVerificationString($user_id, $email_address)
     * @access public
     *
     * This will pull the verification string from a user matching user_id and
     * email address, having previously registered on the site.
    *************************************************************************/
    private function getVerificationString($user_id, $email_address)
    {
        $verification_string = false;
        if (!filter_var($email_address, FILTER_VALIDATE_EMAIL) === FALSE) {
            if (is_numeric($user_id)) {
                if ($this->emailExists($email_address)) {
                    return $this->getUser($user_id)->verification_string;
                }
            }
        }
        return false;
    }

    /*************************************************************************
     * function getUser($user_id)
     * @access public
     * @param int $user_id A numerical representation of a user
     *
     * This method will collect basic information about a user and return
     * the requested user as an object. That object should contain all
     * pertinent information regarding permissions and user details.
     *
     * Account Status --
     *      0 == inactive account
     *      1 == active account, all is good
     *      2 == requires password reset
     *      3 == Account has been locked by administrator
     *      4 == profit
     *
     * Account Type --
     *      0 == General Verified Email Address, Basic Account, Free Account
     *      1 == ??
     *      2 == ????
    *************************************************************************/
    public function getUser($user_id)
    {
        log_message('debug', 'class.Users->getUser()', $this->debug);
        if (is_numeric($user_id)) {
            log_message('debug', 'class.Users->getUser() checking for user id: '.$user_id, $this->debug);
            if ($this->userExists($user_id)) {
                log_message('debug', 'class.Users->getUser() user id matched', $this->debug);
                if (isset($this->_users[$user_id])) {
                    return $this->_users[$user_id];
                }
                log_message('debug', 'class.Users->getUser() selecting user info', $this->debug);
                $query = array();
                $query['select']['users'] = array(
                    'account_status',
                    'account_type',
                    'base_permission',
                    'display_name',
                    'email_address',
                    'first_name',
                    'last_name',
                    'registered_on',
                    'remote_ip',
                    'password_hash',
                    'password_salt',
                    'security',
                    'session_id',
                    'session_state',
                    'title',
                    'user_id',
                    'verification_string'
                );
                $query['from'] = 'users';
                $query['where']['users'] = array('user_id' => $user_id);
                if ($result = $this->database->select($query)) {
                    $this->_users[$user_id] = new User($result, $this->database, $this->storage);
                    return $this->_users[$user_id];
                }
            }
        }
        return false;
    }

    public function getUserId($email_address)
    {
        if (!filter_var($email_address, FILTER_VALIDATE_EMAIL) === FALSE) {
            if ($this->emailExists($email_address)) {
                $query = array();
                $query['select']['users'] = array('user_id');
                $query['from'] = 'users';
                $query['where']['users'] = array('email_address' => $email_address);
                if ($result = $this->database->select($query)) {
                    return $result->user_id;
                }
            }
        }
        return false;
    }

    /*************************************************************************
     * function sendPasswordRecoveryEmail($email_address)
     * @access public
     *
     * This method will send an email to the address specified with a link
     * and a verification code for the user to verify ownership of the
     * requested email address and will take the user to a page to reset
     * their password.
    *************************************************************************/
    public function sendPasswordRecoveryEmail($email_address)
    {
        if (!filter_var($email_address, FILTER_VALIDATE_EMAIL) === FALSE) {
            // get the user info
            if ($this->emailExists($email_address)) {
                if ($user_id = $this->getUserId($email_address)) {
                    if ($user = $this->getUser($user_id)) {
                        // only allow password changes for current valid accounts that have not been locked.
                        if (($user->account_status == 1) || ($user->account_status == 2)) {
                            if ($vcode = $this->getVerificationString($user_id, $email_address)) {
                                // pull the code and build the html mail
                                if (is_object($this->mail)) {
                                    $first_name = '';
                                    $last_name = '';
                                    $display_name = '';
                                    $verification_string = HTTP_HOST . '/accounts/process/process_recover_password/?validation_confirmation_code='.$vcode.'&email='.$email_address.'&user_id='.$user_id;
                                    $Subject = 'Password Recovery Request';
                                    $txtBody = "
                                    Someone, hopefully you, has requested a password change for an account with this email address.
                                    Please click this link to change your password.
                                    <a href='$verification_string'>$verification_string</a>
                                    ".PHP_EOL;
                                    if (isset($user->first_name))
                                        $first_name = $user->first_name;
                                    if (isset($user->last_name))
                                        $last_name = $user->last_name;
                                    if (!empty($user->display_name)) {
                                        $to_user = $user->display_name;
                                    }
                                    elseif (!empty($first_name) || !empty($last_name)) {
                                        $to_user = $first_name.' '.$last_name;
                                    }
                                    $To = array($email_address => $to_user);
                                    $From = array('noreply@codezilla.xyz' => 'Password Recovery');
                                    $template = COMMON . '/templates/tmpl.email.password_recovery.html';
                                    $htmlbody = file_get_contents($template);
                                    $htmlbody = str_replace( "{ACTIVATION_URL}", $verification_string, $htmlbody );
                                    $htmlbody = str_replace( "{SITE_TITLE}", $this->site_name, $htmlbody );

                                    //$From = array(), $To = array(), $Subject = null, $htmlBody = null, $txtBody = null, $CC = null, $BCC = null, $Attachments = null
                                    if ($this->mail->sendEmail($From, $To, $Subject, $htmlbody, $txtBody, null, null)) {
                                        return true;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return false;
    }

    /*************************************************************************
     * function sendVerificationEmail($user_id, $email_address)
     * @access public
     *
     * This method will send an email to the address specified with a link
     * and a verification code for the user to verify ownership of the
     * requested email address.
    *************************************************************************/
    public function sendVerificationEmail($user_id, $email_address)
    {
        if ($this->userExists($user_id)) {
            if (!filter_var($email_address, FILTER_VALIDATE_EMAIL) === FALSE) {
                if ($this->emailExists($email_address)) {
                    if ($vcode = $this->getVerificationString($user_id, $email_address)) {
                        // pull the code and build the html mail
                        if (is_object($this->mail)) {
                            $verification_string = HTTP_HOST . '/accounts/process/process_registration_validation/?validation_confirmation_code='.$vcode.'&email='.$email_address.'&user_id='.$user_id;
                            $Subject = 'Email Registration and Verification';
                            $txtBody = "
                            Thank you for registering, sorry you have to view our text email, but you're mail client!
                            Try this link and activate your account.
                            <a href='$verification_string'>$verification_string</a>
                            ".PHP_EOL;
                            $To = array($email_address => 'New Registration');
                            $From = array($this->registration => 'New Registration');

                            $template = COMMON . '/templates/tmpl.email.registration.html';
                            $htmlbody = file_get_contents($template);
                            $htmlbody = str_replace( "{ACTIVATION_URL}", $verification_string, $htmlbody );
                            $htmlbody = str_replace( "{SITE_TITLE}", $this->site_name, $htmlbody );

                            if ($this->mail->sendEmail($From, $To, $Subject, $htmlbody, $txtBody, null, null)) {
                                return true;
                            }
                        }
                    }
                }
            }
        }
        return false;
    }

    /*************************************************************************
     * function userExists($user_id)
     * @access public
     *
     * Check if an user id exists in our system
    *************************************************************************/
    public function userExists($user_id)
    {
        log_message('debug', 'class.Users->userExists() '.$user_id, $this->debug);
        if (is_numeric($user_id)) {
            $query = array();
            $query['select']['users'] = array('user_id');
            $query['from'] = 'users';
            $query['where']['users'] = array('user_id' => $user_id);
            if ($result = $this->database->select($query)) {
                log_message('debug', 'class.Users->userExists() user id found', $this->debug);
                return true;
            }
        }
        elseif (!filter_var($user_id, FILTER_VALIDATE_EMAIL) === FALSE) {
            $query = array();
            $query['select']['users'] = array('user_id');
            $query['from'] = 'users';
            $query['where']['users'] = array('email_address' => $user_id);
            if ($result = $this->database->select($query)) {
                log_message('debug', 'class.Users->userExists() user email found', $this->debug);
                return true;
            }
        }
        log_message('debug', 'class.Users->userExists() user not found', $this->debug);
        return false;
    }
}

class User
{
    private $debug = false;
    private $access_string = null;
    private $columns = array(
        'account_status',
        'account_type',
        'base_permission',
        'display_name',
        'email_address',
        'first_name',
        'last_name',
        'password_hash',
        'password_salt',
        'remote_ip',
        'security',
        'session_id',
        'session_state',
        'title',
        'verification_string'
    );
    private $isAdmin      = false;
    private $isSuperAdmin = false;
    private $password_hash;
    private $password_salt;

    public $account_status;
    public $account_type;
    public $base_permission;
    public $email_address;
    public $first_name;
    public $ipInfo;
    public $last_name;
    public $perms;
    public $title;
    public $verification_string = null;

    public function __construct($user, $database, $storage, $debug = false)
    {
        log_message('debug', 'class.User-> user object class instantiated', $this->debug);
        (is_object($database))
            ? $this->database = $database
            : halt('User object class was not passed the database object');

        (is_object($storage))
            ? $this->storage = $storage
            : hatl('User object class was not passed the storage object');

        // load a user
        if (is_numeric($user->user_id)) {
            foreach($user as $key => $val) {
                if (!is_array($val) && !is_object($val)) {
                    $this->$key = $val;
                }
            }
        }
        if (!filter_var($this->remote_ip, FILTER_VALIDATE_IP) === false) {
            $this->ipInfo = $this->getIpInfo();
        }

        // establish the base permission
        $this->perms = array();
        $this->perms['base_permission'] = $this->base_permission;

        // pull in the user permissions
        $query['select']['user_perms'] = array('user_id', 'module_id', 'level_id', 'group_id');
        $query['from'] = 'user_perms';
        $query['where']['user_perms'] = array('user_id' => $this->user_id);
        if ($perms = $this->database->select($query)) {
            if (is_object($perms)) {
                $this->setPerm($perms);
            }
            else {
                foreach($perms as $id => $perms) {
                    $this->setPerm($perms);
                }
            }
        }

        // if this user is a superAdmin we need to store that
        if ($this->isSuperAdmin())
            $this->storage->encrypt('superAdmin', true);
    }

    private function setPerm($perm)
    {
        if (is_object($perm)) {
            $this->perms[$perm->module_id]['levels'][$perm->level_id]['group_id'] = $perm->group_id;
        }
    }

    /*************************************************************************
     * function activateAccount()
     * @access public
     *
     * This method will validate the given user object and activate it as a
     * new account with basic permissions matching the "General User" group,
     * if that group exists.
     *
     * Account Status 2 means force password change on login
    *************************************************************************/
    public function activateAccount()
    {
        log_message('debug', 'class.User->activateAccount() activating the account', $this->debug);
        $this->changeAccountStatus(2);
        if ($this->save()) {
            return true;
        }
        return false;
    }

    private function changeAccessString()
    {
        log_message('debug', 'class.User->changeAccessString() Changing Access String', $this->debug);
        $this->access_string = $this->storage->security->randomString('128');
        log_message('debug', 'class.User->changeAccessString() '.$this->access_string, $this->debug);
        return true;
    }

    public function changeAccountStatus($xct)
    {
        if (is_numeric($xct)) {
            $this->account_status = $xct;
            if ($this->changeAccessString()) {
                if ($this->save())
                    return true;
            }
        }
        return false;
    }

    public function changeAccountType($xct)
    {
        if (is_numeric($xct)) {
            $this->account_type = $xct;
            if ($this->changeAccessString()) {
                if ($this->save())
                    return true;
            }
        }
        return false;
    }

    /******************************************************************************
     * function getIpInfo()
     *
     * This method collects the latest user IP location information from our database
    ******************************************************************************/
    public function getIpInfo()
    {
        // valid ip address?
        if (!filter_var($this->remote_ip, FILTER_VALIDATE_IP) === false) {
            // check our local database first...
            $query['select']['ip_info'] = array('ip', 'type', 'timestamp', 'continent_code', 'continent_name', 'country_code', 'country_name', 'region_code', 'region_name', 'city', 'zip', 'latitude', 'longitude');
            $query['from'] = 'ip_info';
            $query['where']['ip_info']['ip'] = $this->remote_ip;
            if ($ipinfo = new IPLocation($this->database->select($query))) {
                return $ipinfo;
            }
        }
        return false;
    }

    public function isAdmin()
    {
        if ($this->isSuperAdmin())
            return true;
        if ($this->isAdmin)
            return true;
        return false;
    }

    public function isLoggedIn()
    {
        log_message('debug', 'class.User->isLoggedIn()', $this->debug);
        if ($this->storage->keyExists('user')) {
            log_message('debug', 'class.User->isLoggedIn() key exists', $this->debug);
            if ($secure_key = $this->storage->decrypt('user')) {
                log_message('debug', 'class.User->isLoggedIn() secure key decrypted', $this->debug);
                $user_id = explode(':', $secure_key)[0];
                $email_address = explode(':', $secure_key)[2];
                if ($user_id == $this->user_id) {
                    log_message('debug', 'class.User->isLoggedIn() secure key user id matches', $this->debug);
                    if ($email_address == $this->email_address) {
                        log_message('debug', 'class.User->isLoggedIn() secure key email address matches', $this->debug);
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public function isSuperAdmin()
    {
        if ($this->isSuperAdmin)
            return true;
        return false;
    }

    public function logout()
    {
        log_message('info', 'class.User->logout() logout initiated for user_id: '.$this->user_id, $this->debug);
        if ($this->changeAccessString()) {
            session_destroy();
        }
        return true;
    }

    private function generateHash($password)
    {
        $this->password_salt = $this->storage->security->randomString('128');
        $hash = $this->password_salt . $password;
        $hash = hash('sha256', $hash);
        for ($i=0;$i<100000;$i++) {
            $hash = hash('sha256', $hash);
        }
        if (!empty($hash)) {
            if ($this->changeAccessString()) {
                log_message('debug', 'class.User->generateHash() your password hash has been generated.', $this->debug);
                $this->password_hash = $hash;
                return true;
            }
        }
        return false;
    }

    public function passwordCheck($password)
    {
        if (isset($password)) {
            if (isset($this->password_salt)) {
                $hash = $this->password_salt . $password;
                $hash = hash('sha256', $hash);
                for ($i=0;$i<100000;$i++) {
                    $hash = hash('sha256', $hash);
                }
                if (!empty($hash)) {
                    if ($this->password_hash === $hash)
                        return true;
                }
            }
        }
        return false;
    }

    public function resetPassword($password)
    {
        if (isset($password)) {
            log_message('debug', 'class.User->resetPassword() generating new hash', $this->debug);
            if ($this->generateHash($password)) {
                log_message('debug', 'class.User->resetPassword() changing account status back to 1', $this->debug);
                if ($this->changeAccountStatus(1)) {
                    log_message('debug', 'class.User->resetPassword() saving user...', $this->debug);
                    if ($this->save()) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /* during login
     * $secure_key = $this->user->user_id.':'.$this->user->verification_string.':'.$this->user->email_address;
     * $this->storage->encrypt('user', $secure_key);
    */
    public function sessionLogin()
    {
        log_message('debug', 'class.User->sessionLogin() in progress', $this->debug);
        log_message('debug', 'class.User->sessionLogin() user_id: '.$this->user_id, $this->debug);
        log_message('debug', 'class.User->sessionLogin() verification_string: '.$this->verification_string, $this->debug);
        log_message('debug', 'class.User->sessionLogin() email_address: '.$this->email_address, $this->debug);
        log_message('info', 'class.User->sessionLogin() user login initiated for user_id: '.$this->user_id, $this->debug);
        $secure_key = $this->user_id.':'.$this->verification_string.':'.$this->email_address;

        // store the session id of the logging in user
        $update['update']['users']['session_id'] = session_id();
        $update['update']['users']['session_state'] = md5(uniqid(rand(), true));
        $update['where']['users'] = array('user_id' => $this->user_id, 'verification_string' => $this->verification_string, 'email_address' => $this->email_address);
        if ($result = $this->database->update($update)) {
            if ($this->storage->encrypt('user', $secure_key))
                return true;
        }
        return false;
    }

    private function save()
    {
        log_message('debug', 'class.User->save()', $this->debug);
        log_message('debug', 'class.User->save() current access string is"'.$this->access_string.'"', $this->debug);
        log_message('debug', 'class.User->save() current password hash is"'.$this->password_hash.'"', $this->debug);
        if ($this->validateUser()) {
            log_message('debug', 'class.User->save() user data validated', $this->debug);
            $update = array();
            // cool, now save the current contents
            foreach($this->columns as $tblkey) {
                if (isset($this->$tblkey)) {
                    $update['update']['users'][$tblkey] = $this->$tblkey;
                }
            }
            if ($this->access_string != $this->verification_string)
            $update['where']['users'] = array('user_id' => $this->user_id, 'verification_string' => $this->verification_string);
            if ($result = $this->database->update($update)) {
                log_message('info', 'class.User->save() user saved -> user_id: '.$this->user_id, $this->debug);
                log_message('debug', 'class.User->save() user data has been saved to the database', $this->debug);
                // save any permissions the user has
                // compare stored groups to set groups and remove any missing
                // and store any new
                if ($this->isLoggedIn()) {
                    log_message('debug', 'class.User->save() user is also logged in. Updating sessionLogin', $this->debug);
                    if ($this->sessionLogin()) {
                        return true;
                    }
                }
                return true;
            }
        }
        return false;
    }

    // When a user is updated the access string should be set and it should match
    // the previous verification_string.
    private function validateUser()
    {
        log_message('debug', 'class.User->validateUser()', $this->debug);
        if (isset($this->email_address) && (!filter_var($this->email_address, FILTER_VALIDATE_EMAIL) === FALSE)) {
            if (isset($this->user_id) && is_numeric($this->user_id)) {
                $query = array();
                $query['select']['users'] = array(
                    'account_status',
                    'account_type',
                    'display_name',
                    'email_address',
                    'first_name',
                    'last_name',
                    'registered_on',
                    'remote_ip',
                    'security',
                    'session_id',
                    'session_state',
                    'user_id',
                    'verification_string'
                );
                $query['from'] = 'users';
                $query['where']['users'] = array('user_id' => $this->user_id, 'email_address' => $this->email_address, 'verification_string' => $this->verification_string);
                if ($xuser = $this->database->select($query))
                    return true;
            }
        }
        return false;
    }
}

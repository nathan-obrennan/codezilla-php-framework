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
$redirect = HTTP_HOST.'/accounts/login.html';
message('A password reset link has been sent to your email.');

// examine the registerEmail and validate
if (isset($this->input->xss->registerEmail)) {
    if (!filter_var($this->input->xss->registerEmail, FILTER_VALIDATE_EMAIL) === FALSE) {
        // add a short sleep just for some stability and security
        if (ENVIRONMENT === 'production')
            sleep(3);
        // this is a valid email address
        if ($this->users->emailExists($this->input->xss->registerEmail)) {
            // the email exists in our system, so send the password reset
            if ($this->users->sendPasswordRecoveryEmail($this->input->xss->registerEmail)) {
                // ok
            }
        }
    }
}
if ($this->storage->keyExists('redirect')) {
    $redirect = $this->storage->get('redirect');
}
redirectTo($redirect);
die();

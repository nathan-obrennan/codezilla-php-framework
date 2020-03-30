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
// If a regular users hits this page, but they are not logged in, get rid of them
if (!$this->users->isLoggedIn()) {
    redirectTo(HTTP_HOST.'/accounts/login.html');
}
$this->debug = false;

// the following happens when a password change was made on /accounts/change-password
if ((isset($this->input->xss->loginPassword1)) && (isset($this->input->xss->loginPassword2))) {
    if ($this->input->xss->loginPassword1 === $this->input->xss->loginPassword2) {
        // ok, process a password change
        if ($this->users->me->resetPassword($this->input->xss->loginPassword1)) {
            log_message('debug', 'process_change_password-> Password has been changed for user: '.$this->users->me->user_id, $this->debug);
            message('Your password has been updated');
            if ($this->storage->keyExists('redirect')) {
                $redirect = $this->storage->get('redirect');
                redirectTo($redirect);
            }
            redirectTo(HTTP_HOST.'/accounts/profile.html');
            die();
        }
        else {
            log_message('debug', 'process_change_password-> Your password could not be updated. Please try again', $this->debug);
            message('Your password could not be updated. Please try again.');
            redirectTo();
            die;
        }
    }
}

log_message('debug', 'process_change_password-> Your password and confirmation did not match', $this->debug);
message('It appears your password and confirmation did not match. Please try again.');
redirectTo();
die();

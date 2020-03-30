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
$redirect = HTTP_HOST;
// slow down the login for security
sleep(2);
if ((isset($this->input->xss->loginUsername)) && (isset($this->input->xss->loginPassword))) {
    if (!filter_var($this->input->xss->loginUsername, FILTER_VALIDATE_EMAIL) === FALSE) {
        if ($this->users->emailExists($this->input->xss->loginUsername)) {
            if ($user_id = $this->users->getUserId($this->input->xss->loginUsername)) {
                if ($user = $this->users->getUser($user_id)) {
                    if ($user->account_status == 3) {
                        message('Your account has been locked. Please check support.');
                        $redirect = HTTP_HOST.'/accounts/login.html';
                    }
                    elseif ($user->account_status == 0) {
                        message('You must confirm your email address before you can log in. Please check your email or register again.');
                        $redirect = HTTP_HOST.'/accounts/login.html';
                    }
                    else {
                        // try to log them in
                        if ($user->passwordCheck($this->input->xss->loginPassword)) {
                            // congrats, your password is a match
                            $user->sessionLogin();
                            message('Welcome back!');
                            if (isset($user->prefered_home))
                                $redirect = $user->prefered_home;
                            if ($this->storage->keyExists('redirect')) {
                                $redirect = $this->storage->get('redirect');
                            }
                        }
                        else {
                            message('Incorrect username or password.');
                        }
                    }
                }
            }
        }
        else {
            message('Username or Password were incorrect.');
            $redirect = HTTP_HOST.'/accounts/login.html';
        }
    }
    else {
        message('Your username should match the email address you registered your account with.');
        $redirect = HTTP_HOST.'/accounts/login.html';
    }
}
redirectTo($redirect);
die();

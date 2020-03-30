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

if (isset($this->input->xss->redirect)) {
    $this->storage->set('redirect', $this->input->xss->redirect);
}

if (isset($this->input->xss->user_id) && is_numeric($this->input->xss->user_id)) {
    if (isset($this->input->xss->validation_confirmation_code)) {
        if (isset($this->input->xss->email)) {
            if (!filter_var($this->input->xss->email, FILTER_VALIDATE_EMAIL) === FALSE) {
                if ($this->users->emailExists($this->input->xss->email)) {
                    if ($user = $this->users->getUser($this->input->xss->user_id)) {
                        if ($user->email_address === $this->input->xss->email) {
                            if ($user->verification_string === $this->input->xss->validation_confirmation_code) {
                                if ($user->activateAccount($user)) {
                                    if ($user->sessionLogin()) {
                                        message('User account activation was successful!');
                                        redirectTo(HTTP_HOST.'/accounts/change-password.html');
                                        die();
                                    }
                                    message('Please login');
                                    redirectTo(HTTP_HOST.'/accounts/login.html');
                                    die();
                                }
                                message('The account could not be activated. Please contact support for further assistance. This has been logged.');
                                redirectTo(HTTP_HOST.'/accounts/register.html');
                                die();
                            }
                            message('The verification code was invalid.');
                            redirectTo(HTTP_HOST.'/accounts/register.html');
                            die();
                        }
                        message('The email address could not be found.');
                        redirectTo(HTTP_HOST.'/accounts/register.html');
                        die();
                    }
                    message('An invalid user_id was processed. Please try again.');
                    redirectTo(HTTP_HOST.'/accounts/register.html');
                    die();
                }
                message('The email account you tried to confirm does not exist in our systems.');
                redirectTo(HTTP_HOST.'/accounts/register.html');
                die();
            }
            message('An invalid email address was processed. Please try again.');
            redirectTo(HTTP_HOST.'/accounts/register.html');
            die();
        }
        message('Email address was not set or invalid.');
        redirectTo(HTTP_HOST.'/accounts/register.html');
        die();
    }
    message('The validation code has expired. Please try again.');
    redirectTo(HTTP_HOST.'/accounts/register.html');
    die();
}
message('The user id was invalid. Stop playing games and register.');
redirectTo(HTTP_HOST.'/accounts/register.html');
die();

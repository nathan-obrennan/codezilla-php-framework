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
$this->debug = false;

// examine the registerEmail and validate
if (isset($this->input->xss->registerEmail)) {
    log_message('debug','process_registration-> registerEmail is set', $this->debug);
    if (!filter_var($this->input->xss->registerEmail, FILTER_VALIDATE_EMAIL) === FALSE) {
        log_message('debug','process_registration-> registerEmail is valid', $this->debug);
        // add a short sleep just for some stability and security
        if (ENVIRONMENT === 'production')
            sleep(3);
        $this->storage->encrypt('userEmail', $this->input->xss->registerEmail);
        // this is a valid email address
        if (!$this->users->emailExists($this->input->xss->registerEmail)) {
            log_message('debug','process_registration-> registerEmail does not exist in our system', $this->debug);
            // the email has not been registered before
            // store the email
            if ($user_id = $this->users->registerEmail($this->input->xss->registerEmail)) {
                log_message('debug','process_registration-> email has been registered. new user_id is: '.$user_id, $this->debug);
                if ($this->users->sendVerificationEmail($user_id, $this->input->xss->registerEmail)) {
                    log_message('debug','process_registration-> email verification has been sent', $this->debug);
                    message('Your email address has been registered. Please check your email for further instructions!');
                    redirectTo(HTTP_HOST.'/accounts/login.html');
                    die();
                }
                else {
                    // something happened and the email could not be sent, delete the email from the account so they can try again.
                    log_message('debug','process_registration-> count not send the verification email. deleting user and notifying via message.', $this->debug);
                    $this->users->destroyEmail($this->input->xss->registerEmail);
                    message('A verification email could not be sent. Please try again later.');
                    redirectTo();
                    die();
                }
            }
            else {
                log_message('debug','process_registration-> user account could not be created in the database.', $this->debug);
                message('Your email could not be stored at this time, please try again later.');
                redirectTo();
                die();
            }
        }
        elseif ($this->users->emailExists($this->input->xss->registerEmail)) {
            log_message('debug','process_registration-> registerEmail exists in our system', $this->debug);
            if ($user_id = $this->users->getUserId($this->input->xss->registerEmail)) {
                log_message('debug','process_registration-> email is registered. user_id is: '.$user_id, $this->debug);
                if ($user = $this->users->getUser($user_id)) {
                    log_message('debug','process_registration-> captured the user', $this->debug);
                    if ($user->account_status == 0) {
                        log_message('debug','process_registration-> user account is inactive, send the verification email again.', $this->debug);
                        if ($this->users->sendVerificationEmail($user_id, $this->input->xss->registerEmail)) {
                            log_message('debug','process_registration-> verification email sent.', $this->debug);
                            message('Your email address has been registered. Please check your email for further instructions!');
                            redirectTo();
                            die();
                        }
                        else {
                            // something happened and the email could not be sent, delete the email from the account so they can try again.
                            log_message('debug','process_registration-> count not send the verification email. deleting user and notifying via message.', $this->debug);
                            $this->users->destroyEmail($this->input->xss->registerEmail);
                            message('A verification email could not be sent. Please try again later.');
                            redirectTo();
                            die();
                        }
                    }
                    log_message('debug','process_registration-> account exists and status is not 0. maybe their account is locked? or they just need to log in.', $this->debug);
                    message('Your email address has already been registered. Try logging in instead.');
                    redirectTo(HTTP_HOST.'/accounts/login.html');
                    die;
                }
            }
        }
    }
    log_message('debug','process_registration-> invalid email was given', $this->debug);
    message('The email you provided was not in a recognized format. Please try again.');
    redirectTo();
    die();
}

message('An unknown error occurred.');
redirectTo();
die();

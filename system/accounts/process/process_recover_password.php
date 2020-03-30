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
$redirect = HTTP_HOST.'/accounts/profile.html';

// The following happens when an email link was clicked to change the password
if (isset($this->input->xss->validation_confirmation_code)) {
    log_message('debug', 'process_recover_password-> confirmation code is set', $this->debug);
    if (isset($this->input->xss->user_id) && is_numeric($this->input->xss->user_id)) {
        log_message('debug', 'process_recover_password-> user_id is set and numeric', $this->debug);
        if (isset($this->input->xss->email)) {
            log_message('debug', 'process_recover_password-> email is set', $this->debug);
            if (!filter_var($this->input->xss->email, FILTER_VALIDATE_EMAIL) === FALSE) {
                log_message('debug', 'process_recover_password-> email is valid', $this->debug);
                if ($this->users->emailExists($this->input->xss->email)) {
                    log_message('debug', 'process_recover_password-> email exists in system', $this->debug);
                    if ($user = $this->users->getUser($this->input->xss->user_id)) {
                        log_message('debug', 'process_recover_password-> found the user', $this->debug);
                        if ($user->email_address === $this->input->xss->email) {
                            log_message('debug', 'process_recover_password-> emails match', $this->debug);
                            if ($user->verification_string === $this->input->xss->validation_confirmation_code) {
                                log_message('debug', 'process_recover_password-> codes match', $this->debug);
                                if ($user->changeAccountStatus(2)) {
                                    log_message('debug', 'process_recover_password-> calling sessionLogin', $this->debug);
                                    $user->sessionLogin();
                                    redirectTo(HTTP_HOST.'/accounts/change-password.html');
                                    die();
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
log_message('debug', 'process_recover_password-> calling sessionLogin', $this->debug);
message('An error occurred. The link you clicked was no longer valid.');
redirectTo(HTTP_HOST.'/accounts/logout.html');
die();

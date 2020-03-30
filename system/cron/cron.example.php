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

/*
 *
 *  Cron Program Name
 *  Description Goes Here
*/

$this->page_name = 'Cron Example';
$this->site_theme = 'system';
$this->loadView(array('api'));
$custom_headers = apache_request_headers();
log_message('info', $this->page_name, 'Cron script was called', $this->debug);
if (isset($custom_headers['Authorization'])) {
    $token = $this->security->sanitize($custom_headers['Authorization']);
    if ($token != $this->cron_token) {
        log_message('warn', $this->page_name, 'token mismatch, cron was not executed', $this->debug);
        // Uncomment this if you want email alerts sent for unauthorized calls
        //$this->sendInfo($this->admin_email, $this->page_name.' Unauthorized', 'Invalid Authorization Provided.');
        header("HTTP/1.1 401 Unauthorized", true);
        die();
    }
    else {
        /*
         * A valid cron authentication string was found. Process the cron job
        */
        if (isset($custom_headers['Owner'])) {
            if (isset($custom_headers['Jobid'])) {
                $jobid = $custom_headers['Jobid'];
                if ($this->cron->jobs[$jobid]->owner == $custom_headers['Owner']) {
                    /*
                     * Begin Cron Code Here
                    */

                    log_message('info', $this->page_name, 'Cron script was called and reached execution', $this->debug);

                    // End Cron Code
                    // Uncomment if you want completion notification
                    // $this->sendInfo($this->admin_email, $this->page_name.' Complete', 'Job Completed');
                    $this->cron->unlockJob($jobid);
                }
            }
        }
    }
}

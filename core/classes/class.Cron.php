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
 * Cron class
 *
 * The cron class will manage all things cron related
 *
******************************************************************************/
class Cron
{
    private $debug              = false;

    protected $module;
    protected $script;

    public $cron_wait        = 10;
    public $enabled          = true; // default to off that way the database selection will force use
    public $jobs;
    public $last_run         = 0;

    public function __construct($params = null)
    {
        log_message('debug', __CLASS__, 'forced DEBUG enabled', $this->debug);

        // process params
        if (! is_null($params)) {
            foreach($params as $name => $object) {
                if ($object)
                    $this->$name = $object;
            }
        }
        log_message('debug', __CLASS__, 'class instantiated', $this->debug);

        // Get the jobs information
        $this->last_run = $this->_lastRun();
        if ($this->_getJobs()) {
            if ($this->enabled) {
                //speak('the module: '.$this->module, $this->debug);
                if ($this->script !== 'scripts/cron.html' && $this->module != 'cron') {
                    // echo '<h1>Should we call the external web hook?</h1>';
                    // Do not execute cron jobs during normal application operation.
                    // if it is time for cron to run then call the external processor
                    // speak('START_TIME: '.START_TIME, $this->debug);
                    // speak('last_run: '.$this->last_run, $this->debug);
                    // speak('cron_wait: '.$this->cron_wait, $this->debug);
                    // speak('difference: '. (START_TIME - $this->last_run), $this->debug);
                    if ((START_TIME - $this->last_run) > $this->cron_wait) {
                        log_message('debug', __CLASS__, 'running', $this->debug);
                        // to avoid a race condition we only want to execute once within X seconds
                        // this can be increased or decreased depending on site availability.
                        // Very busy sites may be able to perform this operation more often
                        // than 10 seconds while extremely busy sites may hit a race again despite this
                        // call the external web hook
                        $this->_externalWebHook();
                    }
                }
                if ($this->script === 'scripts/cron.html'){
                    echo '<h1>Welcome to the Cron Executioner!</h1>';
                    // the cron processor is running, we can execute cron jobs here
                    foreach($this->jobs as $job) {
                        if ($job->enabled) {
                            if ($job->running == 0 && $job->owner == '0') {
                                // check if scheduled time is up
                                if ((microtime(true) - $job->finish) > $job->schedule) {
                                    // use the time as a key to make sure we own the job
                                    $cron_key = microtime(true);

                                    // If this job is not a local file to be executed then we cannot
                                    // lock it and expect a response, so in order to enable
                                    // our cron to "ping" a remote site just one off the execution here
                                    // This is for executing EXTERNAL scripts, NOT local files
                                    // which means FULL urls are required
                                    if (!filter_var($job->cron_url, FILTER_VALIDATE_URL) == FALSE) {
                                        global $code;
                                        $code->remoteExecution($job->url);
                                        log_message('info', __CLASS__, 'Cron Job Processing Remote URL -- __CLASS__, '.$job->title, $this->debug);
                                        $code->sendInfo($code->admin_email, 'Cron Remote URL Executed  -- '.$job->title, '<h1>Cron Job Info</h1><pre>'.print_r($job, true).'</pre>');
                                    }

                                    // set the job as running so nothing else will use it
                                    if ($this->_lockJob($job, $cron_key)) {
                                        // now call the processor for this job using the key
                                        $this->_cronExecute($job, $cron_key);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        /*
         * Cron Watchdog
         * As time moves on, jobs will get stuck or die, so look for jobs that
         * claim to be running but have not finished in a long time, or
         * their "owner" is pretty old.
         */
        //if (ENVIRONMENT == 'production')
            $this->_watchdog();
    }

    /******************************************************************************
     *
     * function selfExecution($url, $token, $owner)
     * @param varchar $url -- A url to curl with GET options only
     *
    ******************************************************************************/
    private function _cronExecute($job, $owner)
    {
        log_message('info', __CLASS__, '_cronExecute has been called: '.$job->title.' '.$job->cron_url);
        if (!filter_var(HTTP_HOST.$job->cron_url, FILTER_VALIDATE_URL) == FALSE) {
            $sess = curl_init();
            curl_setopt($sess, CURLOPT_PORT, '443');
            curl_setopt($sess, CURLOPT_URL, HTTP_HOST.$job->cron_url);
            curl_setopt($sess, CURLOPT_CONNECTTIMEOUT, 1);
            curl_setopt($sess, CURLOPT_TIMEOUT, 1);
            curl_setopt($sess, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($sess, CURLOPT_HEADER, false);
            curl_setopt($sess, CURLOPT_DNS_CACHE_TIMEOUT, 1);
            curl_setopt($sess, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($sess, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: ' . $this->cron_token,
                'Owner: ' . $owner,
                'Jobid: ' . $job->id
            ));
            curl_setopt($sess, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Linux x86_64; rv:66.0) Gecko/20100101 Firefox/66.0');
            curl_setopt($sess, CURLOPT_RETURNTRANSFER, false);
            global $code;
            if ($curl_result = curl_exec($sess)) {
                if (curl_errno($sess)) {
                    $message = sprintf('curl error [%d]: %s', curl_errno($sess), curl_error($sess));
                    log_message('warn', __CLASS__, 'CronExecute Failed to with curl error -- __CLASS__, '.$job->title.' -- '.$message, $this->debug);
                    $code->sendInfo($code->admin_email, '_cronExecute curl error', $message);
                    return false;
                }
                log_message('info', __CLASS__, 'Cron Job Processing -- __CLASS__, '.$job->title, $this->debug);
                //$info = curl_getinfo($sess);
                //$message = '<pre>'.print_r($info, true).'</pre>';
                //$code->sendInfo($code->admin_email, '_cronExecute Curl Information', $message);
                curl_close($sess);
                return $curl_result;
            }
            //else {
            //    $info = curl_getinfo($sess);
            //    $message = '<pre>'.print_r($info, true).'</pre>';
            //    $code->sendInfo($code->admin_email, '_cronExecute Curl Error Information', $message);
            //    curl_close($sess);
            //}
        }
        return false;
    }

    /*************************************************************************
     * function _externalWebHook()
     * @access private
     *
     * This method calls the cron processor via an asynchronous web call
     * providing a means for regular web app usage to execute cron
     * without user slowdown.
    *************************************************************************/
    private function _externalWebHook()
    {
        // speak('_externalWebHook has been called', $this->debug);
        log_message('info', __CLASS__, '_externalWebHook executed', $this->debug);
        if (substr(php_uname(), 0, 5) == 'Linux') {
            // wget and curl are typically always installed, but lets use wget
            $proc_command = 'wget '.HTTP_HOST.'/scripts/cron.html -q -O - -b';
            // speak('proc_command: '.$proc_command, $this->debug);
            $popen = popen($proc_command, "r");
            if (pclose($popen))
                return true;
        }
        elseif (substr(php_uname(), 0, 7) == 'Windows') {
            // hopefully curl.exe exists within a bin directory in our basepath
            $proc_command = BASEPATH.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'curl.exe --url '.HTTP_HOST.'/scripts/cron.html';
            $popen = popen('start /B '.$proc_command, "r");
            if (pclose($popen))
                return true;
        }
        return false;
    }

    /*************************************************************************
     * function getJobs()
     * @access public
     *
     * Get all the jobs in the cron table
    *************************************************************************/
    private function _getJobs()
    {
        if ($this->enabled) {
            $jobs = array();
            $query['select']['cron'] = array(
                'id',
                'title',
                'description',
                'date',
                'enabled',
                'schedule',
                'owner',
                'finish',
                'cron_url',
                'running'
            );
            $query['from'] = 'cron';
            if ($jobs = $this->database->select($query)) {
                if (is_array($jobs)) {
                    foreach($jobs as $job) {
                        $this->jobs[$job->id] = $job;
                    }
                }
                elseif (is_object($jobs)) {
                    $this->jobs[$jobs->id] = $jobs;
                }
                return true;
            }else {
                // cron is enabled but failed to return anything
                $this->enabled = false;
            }
        }
    }

    private function _lastRun()
    {
        if ($this->enabled) {
            $jobs = array();
            $query['select']['cron'] = array(
                'finish',
            );
            $query['from'] = 'cron';
            $query['order'] = array('cron' => array('finish' => 'DESC'));
            $query['limit'] = '1';
            if ($time = $this->database->select($query)) {
                return $time->finish;
            }
        }
    }

    /*************************************************************************
     * function _lockJob($job, $owner)
     * @access private
     * @param int $job The job object of the cron job to lock
     * @param int $owner This is a microtime key used to lock the job for ownership verification
     *
     * This will lock a job to a specific owner so only that owner is able to run
     * the job until it is released.
    *************************************************************************/
    private function _lockJob($job, $owner)
    {
        if (is_numeric($job->id) && isset($owner)) {
            if (isset($this->jobs[$job->id])) {
                $update['update']['cron'] = array('owner' => $owner);
                $update['where']['cron'] = array('id' => $job->id);
                if ($this->database->update($update)) {
                    // update the local info
                    $this->jobs[$job->id]->owner = $owner;
                    log_message('info', __CLASS__, 'Cron Job Locked for Execution -- __CLASS__, '.$job->title, $this->debug);
                    return true;
                }
            }
        }
        return false;
    }

    /*************************************************************************
     * function _unlockJob($jobid)
     * @access private
     * @param int $jobid The ID of the cron job to lock
     *
     * This will unlock a job, mark it as not running and set the finish time
    *************************************************************************/
    private function _unlockJob($jobid)
    {
        if (is_numeric($jobid)) {
            if (isset($this->jobs[$jobid])) {
                $job = $this->jobs[$jobid];
                $update['update']['cron'] = array(
                    'finish'  => microtime(true),
                    'owner'   => '0',
                    'running' => '0'
                );
                $update['where']['cron'] = array('id' => $jobid);
                if ($this->database->update($update)) {
                    log_message('info', 'Cron Job Unlocked -- '.$job->title, $this->debug);
                    return true;
                }
                else {
                    global $code;
                    log_message('warn', __CLASS__, 'Cron Failed to Unlock Job -- __CLASS__, '.$job->title, $this->debug);
                    $code->sendInfo($code->admin_email, '_unlockJob Failed to Update', '<pre>'.print_r($this->database, true).'</pre>');
                }
            }
        }
        return false;
    }

    /*************************************************************************
     * function _watchdog()
     * @access private
     *
     * This will observe the jobs currently enabled and unlock jobs that have
     * not finished in some time or have not run in a long time.
    *************************************************************************/
    private function _watchdog()
    {
        if (isset($this->jobs)) {
            global $code;
            foreach($this->jobs as $jobid => $job) {
                if ($job->enabled == 1) {
                    // don't worry about jobs that run external urls
                    if (filter_var($job->cron_url, FILTER_VALIDATE_URL) == FALSE) {
                        if ($job->running == 1) {
                            // check if a job has been running for more than 5 minutes
                            if ((microtime(true) - $job->finish) > 300) {
                                log_message('warn', __CLASS__, 'Cron Job Problem -- __CLASS__, '.$job->title.' -- Job Failed to Complete in 300 Seconds and Will Be Reset', $this->debug);
                                $code->sendInfo($code->admin_email, 'Cron Job KIA -- '.$job->title, '<h1>Cron Job Info</h1><p>The job will be reset</p><pre>'.print_r($job, true).'</pre>');
                                $this->_unlockJob($job->id);
                            }
                        }
                        if ($job->running == '0') {
                            // check for jobs that have not run in their normal time schedule
                            if ($job->schedule <= '60') {
                                $break_fix = 300; // 5 minutes
                            }
                            elseif ($job->schedule <= '900') { // 15 minutes
                                $break_fix = $job->schedule * 2.5;
                            }
                            elseif ($job->schedule <= '3600') { // 1 hour
                                $break_fix = $job->schedule * 2;
                            }
                            elseif ($job->schedule <= '28800') { // 8 hours
                                $break_fix = $job->schedule * 2;
                            }
                            elseif ($job->schedule <= '86400') { // 24 hours
                                $break_fix = $job->schedule * 1.25;
                            }
                            if ((microtime(true) - $job->finish) > $break_fix) {
                                log_message('warn', __CLASS__, 'Cron Job Problem -- __CLASS__, '.$job->title.' -- Job Failed to run. Reseting.', $this->debug);
                                //$code->sendInfo($code->admin_email, 'Cron Job Failed to run -- '.$job->title, '<h1>Cron Job Info</h1><p>The job will be reset</p><pre>'.print_r($job, true).'</pre>');
                                $this->_unlockJob($job->id);
                            }
                        }
                    }
                }
            }
        }
    }
}

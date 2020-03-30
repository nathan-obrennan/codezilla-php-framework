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
 * Codezilla functions and utilities
 *
******************************************************************************/

/******************************************************************************
 * function buildString($array, $start, $finish)
 * @param array $array  -- The name of the array
 * @param int $start    -- key to start from in the array
 * @param int $finish    -- key to end with in the array
 * @return string
 * The function will create a string from the values of the requested keys
 * using a DIRECTORY_SEPARATOR as a separator.
 * Example:
 * $colors = array('white', 'green', 'orange', 'red', 'blue', 'purple', 'pink', 'black');
 * build_string($colors, '2', '5')
 *
 * returns: orange/red/blue/purple
******************************************************************************/
if (!function_exists('buildString')) {
    function buildString($array, $start, $finish)
    {
        // verify this is an array
        if (is_array($array)) {
            // make sure the values are in the right order
            if ($finish <= count($array)) {
                if ($start <= $finish) {
                    //make sure the values exist in the array as keys
                    if (!array_key_exists($start, $array)) {
                        log_message('fatal', '', "build_string() -- invalid start($start)", $this->debug);
                        return;
                    }
                    if (!array_key_exists($finish, $array)) {
                        log_message('fatal', '', "build_string() -- invalid finish($finish)", $this->debug);
                        return;
                    }

                    $count = $start;
                    $outgoing = '';
                    while ($count <= $finish) {
                        $outgoing .= $array[$count];
                        if ($count < $finish)
                            $outgoing .= DIRECTORY_SEPARATOR;
                        $count++;
                    }
                    return $outgoing;
                }
            }else {
                log_message('fatal', '', "build_string() -- Finish($finish) is higher than array count: " . count($array), $this->debug);
                return;
            }
        }
        return;
    }
}

/******************************************************************************
 *
 * function codezilla_autoloader($class)
 * @param string class name
 *
 * This function is registered with the spl_autoloader to be the primary
 * class autoload function. When loading a class do so as normal
 * $object = new ClassName(parameter, parameter);
 * and the object will be instantiated. This will obey framework methodology
 * and pass the existing object, if it exists. The object will also be available
 * within the $code singleton.
 *
******************************************************************************/
if (!function_exists('codezilla_autoloader')) {
    function codezilla_autoloader($class) {
        if ($class) {
            global $code;
            $code->loadClass($class);
        }
    }
}

/******************************************************************************
 *
 * function convert($size)
 * @param float size convert a string of digits to the appropriate size in bytes, kilobytes...
 *
******************************************************************************/
if (!function_exists('convert')) {
    function convert($size) {
        $unit = array('b','kb','mb','gb','tb','pb');
        return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
    }
}

if (!function_exists('debug')) {
    function debug() {
        global $code;
        if (is_object($code)) {
            if (isset($code->debug)) {
                return $code->debug;
            }
        }
        return DEBUG;
    }
}

/******************************************************************************
 *
 * function halt($message)
 * @param string $message a string of text to report when halting the system
 *
******************************************************************************/
if (!function_exists('halt')) {
    function halt($message) {
        log_message('fatal', '', $message, true);
        if (headers_sent()) {
            die($message);
        }
        header("HTTP/1.1 500 Internal Server Error: $message", true);
        die();
    }
}

/******************************************************************************
 *
 * function log_message(priorty, class, message)
 * @param string priority level (info, debug, fatal)
 * @param string class the name of the class that logged the message
 * @param string message the message to record or display
 *
 * This function will store logged messages in the session until a storage
 * device is available to record them (such as a database)
 *
******************************************************************************/
if (!function_exists('log_message')) {
    function log_message($priority, $class = null, $message, $debug = false) {
        $continue = false;
        if (DEBUG || $debug) {
            $debug = true;
            $priority = 'debug';
        }

        // if priority is 'fatal' then dump to file to phsyical access in case database cannot be written to
        // Also, for play by play write debug output to file
        if ($priority === 'fatal' || $priority === 'system' || $priority === 'debug') {
            writeLog(array('priority' => $priority, 'class' => $class, 'message' => $message));
        }

        // the framework super object is not available yet
        // so store the data in the session for later processing
        if (session_id()) {
            if ($priority != 'debug') {
                $_SESSION['log_message'][] = array(
                    'date'      => microtime(true),
                    'priority'  => $priority,
                    'class'     => $class,
                    'message'   => $message
                );
            }
        }
    }
}

// This function exists to write a log to a physical file
if (!function_exists('writeLog')) {
    function writeLog($log) {
        if (!file_exists(SYSTEMLOG)) {
            $log_file = fopen(SYSTEMLOG, 'w');
            fclose($log_file);
        }
        if (file_exists(SYSTEMLOG)) {
            if (is_writable(SYSTEMLOG)) {
                if ($log_file = fopen(SYSTEMLOG, 'a+')) {
                    if (fputcsv($log_file, $log))
                        $continue = true;
                    fclose($log_file);
                }
            }
        }
        if (!$continue) {
            die('A fatal error occured, but the log file could not be written to: '.SYSTEMLOG);
        }
    }
}

/*
 * function mb_escape(string $string)
 * @param string $string The string to escape
 *
 * Escape certain characters from a string
 * 00 = \0 (NUL)
 * 0A = \n
 * 0D = \r
 * 1A = ctl-Z
 * 22 = "
 * 27 = '
 * 5C = \
 * 25 = %
 * 5F = _
*/
if (function_exists('mb_ereg_replace')) {
    function mb_escape(string $string) {
        return mb_ereg_replace('[\x00\x0A\x0D\x1A\x22\x25\x27\x5C\x5F]', '\\\0', $string);
    }
}
else {
    function mb_escape(string $string) {
        return preg_replace('~[\x00\x0A\x0D\x1A\x22\x25\x27\x5C\x5F]~u', '\\\$0', $string);
    }
}

/******************************************************************************
 *
 * function message($string)
 * @param string $string This is the message you want displayed
 * @access public
 *
 * On page load a small banner, somewhere depending on theme and configuration
 * will display system messages. Use this to add messages.
 *
******************************************************************************/
if (!function_exists('message')) {
    function message($string) {
        if (session_id()) {
            if (!isset($_SESSION['messages']))
                $_SESSION['messages'] = array();

            if (!empty($string)) {
                $key = array_search($string, $_SESSION['messages']);
                if (is_numeric($key)) {
                    // remove the previous entry so we don't have duplicates
                    unset($_SESSION['messages'][$key]);
                }
                $_SESSION['messages'][] = $string;
            }
        }
    }
}

if (!function_exists('refresh')) {
    function refresh($time = '0', $message = 'Please wait while the system refreshes')
    {
        echo '<META HTTP-EQUIV="Refresh" Content="'.$time.';">';
        echo '<p>'.$message.'</p>';
    }
}

/******************************************************************************
 *
 * function redirectTo($location, $timeout)
 * @param string $location -- The location to redirect to
 * @param int $timeout -- The number of seconds to wait before redirecting
 * @access public
 *
 * Adds a META redirect to a page to force redirection even if headers have
 * already been sent.
 *
******************************************************************************/
if (!function_exists('redirectTo')) {
    function redirectTo($location = null, $timeout = '0') {
        if (debug())
            $timeout = 3;
        if (!empty($location)) {
            if (!filter_var($location, FILTER_VALIDATE_URL) == FALSE) {
                log_message('info', '', 'redirecting to: ' . $location);
                echo '<META HTTP-EQUIV="Refresh" Content="'.$timeout.'; URL=' . $location . '">';
                exit;
            }
        }
        if (isset($_SESSION['redirect'])) {
            if (!filter_var($_SESSION['redirect'], FILTER_VALIDATE_URL) == FALSE) {
                $the_redirect = $_SESSION['redirect'];
                unset($_SESSION['redirect']);
                unset($code->storage->redirect);
                echo '<META HTTP-EQUIV="Refresh" Content="'.$timeout.'; URL=' . HTTP_HOST . $the_redirect . '">';
                exit;
            }
        }
        // nothing was requested so redirect to a referer if it exists, or the home page
        global $code;
        if (isset($code->router->referer)) {
            redirectTo($code->router->referer, $timeout);
            exit;
        }

        if (defined('HTTP_HOST')) {
            log_message('info', '', 'redirecting to: ' . HTTP_HOST);
            echo '<META HTTP-EQUIV="Refresh" Content="'.$timeout.'; URL=' . HTTP_HOST . '">';
            exit;
        }

        // last resort to refresh?
        log_message('info', '', 'redirecting to: /');
        echo '<META HTTP-EQUIV="Refresh" Content="'.$timeout.'; URL=/">';
        exit;
    }
}

/*************************************************************************
 * function reindexArray($array)
 * @param array an array to reindex
 * @access public
 * @return array
 *
 * Remove empty keys from the array and return a sorted array without index[0]
*************************************************************************/
if (!function_exists('reindexArray')) {
    function reindexArray($array)
    {
        if (isset($array) && is_array($array)) {
            foreach($array as $key => &$value) {
                if (is_array($value)) {
                    $value = reindexArray($value);
                }else {
                    if (empty($value)) {
                        unset($array[$key]);
                    }
                }
            }
            array_unshift($array, null);
            unset($array[0]);
            return $array;
        }
        return false;
    }
}

if (!function_exists('session_restart')) {
    function session_restart()
    {
        if (session_id())
            session_destroy();
        session_id();
    }
}

/******************************************************************************
 *
 * function show($data)
 * @param object data is the object, array, or string to display
 *
 * This function exists as a way to quickly display object contents
 * in a friendly and human readable way.
 *
******************************************************************************/
if (!function_exists('show')) {
    function show($data) {
        echo '<pre>' . PHP_EOL;
        if (is_array($data) || is_object($data)) {
            print_r($data);
        } else {
            echo $data;
        }
        echo '</pre>' . PHP_EOL;
    }
}

/******************************************************************************
 *
 * function show404()
 *
 * When a page cannot be found this will halt the system and display the configured
 * 404 error page.
 *
******************************************************************************/
if (!function_exists('show404')) {
    function show404($error_message = null)
    {
        global $code;
        if (!is_null($error_message))
            $code->error_message = $error_message;
        $code->loadView(array('error_page'));
        die();
    }
}

/******************************************************************************
 *
 * function speak($string, $debug)
 * @param string string the string to display
 * @param bool debug if true the string will be displayed
 *
 * This function exists as a way to print a string of text to the screen
 * in a human friendly readable way.
 *
******************************************************************************/
if (!function_exists('speak')) {
    function speak($message, $debug = false) {
        if ($debug) {
            echo '<pre>' . $message . '</pre>' . PHP_EOL;
        }
    }
}

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
 * Input class
 *
 * The Input class will collect all data input via $_POST and $_GET and
 * sanitize it and clear the original variables for safety
 *
******************************************************************************/
class Input
{

    private $debug = false;

    protected $security;
    public $xss;

    public $_GET;
    public $_POST;

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
        $this->xss = new stdClass();
        log_message('debug', __CLASS__, '_construct() cleaning _GET', $this->debug);
        if (isset($_GET)) {
            if ((count($_GET) > 0) || (! empty($_GET))) {
                foreach($_GET as $key => $val) {
                    $xkey = $this->security->sanitize($key);
                    (is_array($val))
                        ? $xval = $this->security->cleanArray($val)
                        : $xval = $this->security->sanitize($val);

                    $this->xss->$xkey = $xval;
                }
            }
            $this->_GET = $_GET;
            unset($_GET);
        }

        log_message('debug', __CLASS__, '_construct() cleaning _POST', $this->debug);
        if (isset($_POST)) {
            if ((count($_POST) > 0) || (! empty($_POST))) {
                foreach($_POST as $key => $val) {
                    $xkey = $this->security->sanitize($key);
                    (is_array($val))
                        ? $xval = $this->security->cleanArray($val)
                        : $xval = $this->security->sanitize($val);

                    $this->xss->$xkey = $xval;
                }
            }
            $this->_POST = $_POST;
            unset($_POST);
        }
        log_message('debug', __CLASS__, 'class instantiated', $this->debug);
    }

    /*************************************************************************
     * function getUri()
     * @access public
     *
     * This method will return the address bar URI and fix the query string
     * if necessary.
    *************************************************************************/
    public function getUri()
    {
        if (!isset($_SERVER['REQUEST_URI']) || !isset($_SERVER['SCRIPT_NAME'])) {
            return;
        }
        // Grab the servers request uri
        $uri = $_SERVER['REQUEST_URI'];

        // check if the index.php is in the uri and strip it out, or any other script name
        if (mb_strpos($uri, $_SERVER['SCRIPT_NAME']) === 0) {
            $uri = mb_substr($uri, mb_strlen($_SERVER['SCRIPT_NAME']));
        }
        elseif (mb_strpos($uri, dirname($_SERVER['SCRIPT_NAME'])) === 0) {
            $uri = mb_substr($uri, mb_strlen(dirname($_SERVER['SCRIPT_NAME'])));
        }

        // Check for a query string
        $parts = preg_split('#\?#i', $uri, 2);
        $uri = $parts[0];

        // Clean up the array and send it off
        if ($uri == '/' || empty($uri)) {
            return '/';
        }
        $uri = parse_url($uri, PHP_URL_PATH);
        if (isset($uri) && !empty($uri))
            return str_replace(array('//', '../'), '/', trim($uri, '/'));
        return false;
    }

    /*************************************************************************
     * function getParams()
     * @access public
     *
     * This method will return the address bar parameters.
    *************************************************************************/
    public function getParams()
    {
        if (!isset($_SERVER['REQUEST_URI']) || !isset($_SERVER['SCRIPT_NAME'])) {
            return;
        }
        // Grab the servers request uri
        $uri = $_SERVER['REQUEST_URI'];
        if ($uri = parse_url($uri, PHP_URL_QUERY)) {
            mb_parse_str($uri, $params);
        }
        if (isset($params) && is_array($params))
            return $params;
        return false;
    }
}

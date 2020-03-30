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
 * Security class
 *
 * The security class will provide basic security methods for creating
 * pseudo-random numbers and strings as well as facilities for sanitizing
 * input and arrays. This class does not have database access by design. It is
 * intended for use when a class needs some basic security functions
 * but the database has not been loaded yet and the Codezilla object is
 * not ideal to be passed.
 *
******************************************************************************/
class Security
{

    public $debug = false;

    protected $_classes = array();
    protected $_csrf_expire      = 7200;
    protected $_csrf_hash;
    protected $_csrf_token_name  = 'ci_csrf_token';
    protected $_csrf_cookie_name = 'ci_csrf_token';

    protected $_never_allowed_str = array(
        'document.cookie'   => '[removed]',
        'document.write'    => '[removed]',
        '.parentNode'       => '[removed]',
        '.innerHTML'        => '[removed]',
        '-moz-binding'      => '[removed]',
        '<!--'              => '&lt;!--',
        '-->'               => '--&gt;',
        '<![CDATA['         => '&lt;![CDATA[',
        '<comment>'         => '&lt;comment&gt;'
    );

    protected $_never_allowed_regex = array(
        'javascript\s*:',
        '(document|(document\.)?window)\.(location|on\w*)',
        'expression\s*(\(|&\#40;)', // CSS and IE
        'vbscript\s*:', // IE, surprise!
        'wscript\s*:', // IE
        'jscript\s*:', // IE
        'vbs\s*:', // IE
        'Redirect\s+30\d',
        "([\"'])?data\s*:[^\\1]*?base64[^\\1]*?,[^\\1]*?\\1?"
    );

    protected $_xss_hash;

    protected $characters       = 'aAbBcCdDeEfFgGhHiIjJkKlLmMnNoOpPqQrRsStTuUvVwWxXyYzZ0123456789';
    protected $digits           = '0123456789';

    protected $charset = 'UTF-8';

    protected $filename_bad_chars  = array(
        '../', '<!--', '-->', '<', '>',
        "'", '"', '&', '$', '#',
        '{', '}', '[', ']', '=',
        ';', '?', '%20', '%22',
        '%3c',      // <
        '%253c',    // <
        '%3e',      // >
        '%0e',      // >
        '%28',      // (
        '%29',      // )
        '%2528',    // (
        '%26',      // &
        '%24',      // $
        '%3f',      // ?
        '%3b',      // ;
        '%3d'       // =
    );

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
    }

    private function _cleanArray($array)
    {
        if (is_array($array)) {
            $clean_array = array();
            foreach($array as $key => $val) {
                $xkey = $this->_escapeString($string);
                if (is_array($val)) {
                    $xval = $this->_cleanArray($val);
                }
                elseif (is_object($val)) {
                    $xval = $this->_cleanObject($val);
                }
                else {
                    $xval = $this->_escapeString($val);
                }
                $clean_array[$xkey] = $xval;
            }
            return $clean_array;
        }
        return false;
    }

    private function _cleanObject($object)
    {
        if (is_object($object)) {
            $clean_object = new Stdclass();
            foreach($object as $key => $val) {
                $xkey = $this->_escapeString($key);
                if (is_array($val)) {
                    $xval = $this->_cleanArray($val);
                }
                elseif (is_object($val)) {
                    $xval = $this->_cleanObject($val);
                }
                else {
                    $xval = $this->_escapeString($val);
                }
                $clean_object->$xkey = $xval;
            }
            return $clean_object;
        }
        return false;
    }

    /******************************************************************************
     *
     * function _escapeString($string)
     * @param string String to be sanitized
     * @return string
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
     *
    ******************************************************************************/
    private function _escapeString($string)
    {
        if (function_exists('mb_ereg_replace')) {
            return mb_ereg_replace('[\x00\x0A\x0D\x1A\x22\x25\x27\x5C]', '\\\0', $string);
        }
        elseif (function_exists('preg_replace')) {
            return preg_replace('~[\x00\x0A\x0D\x1A\x22\x25\x27\x5C]~u', '\\\$0', $string);
        }
    }

    /******************************************************************************
     *
     * function randomString($length)
     * @param int $length The string length to be returned
     * @return string of characters $length long
     *
     * This method returns a pretty crappy but fairly safe random string with a
     * length of your choice.
    ******************************************************************************/
    public function randomString($length = '40')
    {
        log_message('debug', __CLASS__, 'randomString()', $this->debug);
        $string = sha1(microtime(true).mt_rand(PHP_INT_MAX / 10, PHP_INT_MAX));
        while (strlen($string) < $length) {
            $string .= $this->randomString();
        }
        return substr($string, 0, $length);
    }

    /******************************************************************************
     *
     * function sanitize($dirty)
     * @param str $dirty String to be sanitized
     * @param array $dirty Array to be sanitized
     * @param object $dirty Object to be sanitized
     * @return str
     * @return array
     * @return object
     *
     * This method will attempt to sanitize a string by removing unsavory characters
     * from the string and return it. The dirty object can be a string, an array, or
     * an object. The elements within the array or object will be iterated through
     * and cleaned.
     *
    ******************************************************************************/
    public function sanitize($dirty)
    {
        if (isset($dirty)) {
            if (is_array($dirty)) {
                return $this->_cleanArray($dirty);
            }
            elseif(is_object($dirty)) {
                return $this->_cleanObject($dirty);
            }
            return $this->_escapeString($dirty);
        }
        return false;
    }
}

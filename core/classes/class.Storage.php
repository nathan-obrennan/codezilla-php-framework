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
 * Storage class
 *
 * Manage the _SESSION and data that must pass between session and database
 *
******************************************************************************/
class Storage
{

    public $debug = false;
    public $cipher = "AES-256-CBC";
    public $cookie;
    public $cookie_lifetime;
    public $cookie_domain;
    public $cookie_path;
    public $cookie_secure;
    public $cookie_value;
    public $encryptionKey;             // this is the cryptographic hashed password used as a key
    public $ipstack_key;
    public $ipstack_secure;
    public $remote_ip;
    public $session_id;

    public function __construct($params = null)
    {
        log_message('debug', __CLASS__, 'class.Storage-> forced DEBUG enabled', $this->debug);
        // process params
        if (! is_null($params)) {
            foreach($params as $name => $object) {
                if ($object)
                    $this->$name = $object;
            }
        }

        // this should be passed in via the vault
        log_message('debug', __CLASS__, 'class.Storage->_construct() the ipstack access key must come from the vault', $this->debug);

        // This should be set once we pull from a cookie, but do not let it be empty
        $this->encryptionKey = sha1(microtime(true).mt_rand(PHP_INT_MAX / 10, PHP_INT_MAX));

        if (!isset($_COOKIE[$this->cookie])) {
            // set a cookie with the value as the encryption key for session storage
            setcookie($this->cookie, $this->encryptionKey, $this->cookie_lifetime, $this->cookie_path, $this->cookie_domain, $this->cookie_secure, true);
        }

        if (isset($_COOKIE[$this->cookie])) {
            $this->encryptionKey = $_COOKIE[$this->cookie];
        }

        if (!$this->keyExists('session_state')) {
            $this->set('session_state', md5(uniqid(rand(), true)));
        }
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $this->set('HTTP_USER_AGENT', $_SERVER['HTTP_USER_AGENT']);
        } else {
            $this->set('HTTP_USER_AGENT', md5(md5(time())));
        }

        // Collect some IP information to possibly store later with this user
        if (isset($this->remote_ip)) {
            if (!$this->keyExists('remote_ip')) {
                if (!filter_var($this->remote_ip, FILTER_VALIDATE_IP) === false) {
                    $this->set('remote_ip', $this->remote_ip);
                }
            }
            elseif ($this->keyExists('remote_ip')) {
                if ($this->remote_ip != $this->get('REMOTE_ADDR')) {
                    $this->set('remote_ip', $this->remote_ip);
                }
            }
            if (!filter_var($this->remote_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE) === false) {
                log_message('debug', __CLASS__, 'class.Storage->getIpInfo()', $this->debug);
                $ipinfo = $this->getIpInfo($this->get('remote_ip'));
                $this->set('ipinfo', $ipinfo);
            }
        }
        log_message('debug', __CLASS__, 'class.Storage-> class instantiated', $this->debug);
    }

    /******************************************************************************
     *
     * function _ipstack($ip_address)
     * @access public
     * @param str $ip_address A valid dot quad ip address
     * @return object with info about ip address
     *
     * This method will query https://ipstack.com for public IP info
     * Check their website for pricing. At the time of this creation 10,000 / mo
     * query access was free. If you do not have an API key then this function
     * will return false.
    ******************************************************************************/
    public function _ipstack($ip_address)
    {
        if ((isset($this->ipstack_key)) && (!empty($this->ipstack_key))) {
            if (!filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE) === false) {
                ($this->ipstack_secure)
                    ? $http_scheme = 'https'
                    : $http_scheme = 'http';
                $ipstack = $http_scheme.'://api.ipstack.com/' . $ip_address . '?access_key='.$this->ipstack_key;
                $sess = curl_init($ipstack);
                curl_setopt($sess, CURLOPT_TIMEOUT, 12);
                curl_setopt($sess, CURLOPT_HEADER, false);
                curl_setopt($sess, CURLOPT_RETURNTRANSFER, true);
                $result = curl_exec($sess);
                log_message('info', __CLASS__, 'class.Storage->_ipstack collecting info for: '.$ip_address, $this->debug);
                curl_close($sess);
                $ipinfo = json_decode($result);
                if (!empty($ipinfo)) {
                    return $ipinfo;
                }
            }
        }
        return false;
    }

    /******************************************************************************
     *
     * function decrypt($key)
     * @param str $key This is the SESSION[$key] to retrieve
     *
     * This method will decrypt the value of the session key
     * using the session encryptionKey which is stored on the browser side.
    ******************************************************************************/
    public function decrypt($key)
    {
        if (isset($key)) {
            if (isset($_SESSION[$key])) {
                $keyval = $_SESSION[$key];
                $sec_array = explode(':', $keyval);
                // if the key is not encrypted then this will more than likely fail
                // unless the value just happens to have a : in it
                // should we use something other than colons?
                if (count($sec_array) < 1)
                    return false;
                if ($decrypted = openssl_decrypt($sec_array[1], $this->cipher, $this->encryptionKey, $options=0, $sec_array[0])) {
                    return $decrypted;
                }
                else {
                    // the cookie could not be decrypted. This happens often when the cookie becomes corrupt
                    if (session_id())
                        session_destroy();
                    redirectTo();
                    die;
                }
            }
        }
        return false;
    }

    /******************************************************************************
     *
     * function encrypt($key, $value)
     * @param str $key This is the SESSION[$key] to set
     * @param str $value This is the raw string value to store
     *
     * This method will cryptographically store the value of the session key
     * using the session encryptionKey which is stored on the browser side and
     * then store the encrypted value, with the IV, in the session key.
    ******************************************************************************/
    public function encrypt($key, $value)
    {
        $ivlen = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        if ($key) {
            $encValue = openssl_encrypt($value, $this->cipher, $this->encryptionKey, $options=0, $iv);
            $_SESSION[$key] = $iv.':'.$encValue;
            return true;
        }
        return false;
    }

   /******************************************************************************
     *
     * function get($key)
     * @param str $key This is the SESSION[$key] to retrieve
     * @return str The value of the session key requested
     * @return false if key is not found
     *
     * This method will return the value of the specified session key
    ******************************************************************************/
    public function get($key)
    {
        if (isset($key)) {
            if (isset($_SESSION[$key])) {
                $output = $_SESSION[$key];
                if ($key == 'redirect')
                    unset($_SESSION[$key]);
                return $output;
            }
        }
        return false;
    }

    /******************************************************************************
     * function getIpInfo($ip_address)
     * @param var $ip_address The remote IP to validate
     *
     * This method will check the database for a valid matching IP address
     * and if it is not found it will request the info from the ipstack
     * method and then store the data.
    ******************************************************************************/
    public function getIpInfo($ip_address)
    {
        // valid ip address?
        if (!filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE) === false) {
            // check our local database first...
            $query['select']['ip_info'] = array('ip', 'type', 'timestamp', 'continent_code', 'continent_name', 'country_code', 'country_name', 'region_code', 'region_name', 'city', 'zip', 'latitude', 'longitude');
            $query['from'] = 'ip_info';
            $query['where']['ip_info']['ip'] = $ip_address;
            if ($ipinfo = $this->database->select($query)) {
                log_message('debug', __CLASS__, 'class.Storage->getIpInfo() ip found in database', $this->debug);
                // if IP data is older than 30 days lets update it
                if (strtotime($ipinfo->timestamp) < strtotime('-30 days')) {
                    log_message('debug', __CLASS__, 'class.Storage->getIpInfo() old data found. Deleting...', $this->debug);
                    // delete the stored data and pull fresh data
                    $delete['from'] = 'ip_info';
                    $delete['where']['ip_info'] = array('ip' => $ip_info->ip);
                    $this->database->delete($delete);
                    $ipinfo = new IPStack($this->_ipstack($ip_address));
                    $this->_storeIpInfo($ipinfo);
                }
            }
            else {
                // don't have it, so lets pull it!
                log_message('debug', __CLASS__, 'class.Storage->getIpInfo() ip not found in database', $this->debug);
                $ipinfo = new IPStack($this->_ipstack($ip_address));
                if (!$this->_storeIpInfo($ipinfo))
                    log_message('debug', __CLASS__, 'class.Storage->getIpInfo() ip could not be stored in database', $this->debug);
            }
        }
        if (isset($ipinfo))
            return $ipinfo;
        return false;
    }

    public function keyExists($key)
    {
        if (isset($key)) {
            if (isset($_SESSION[$key])) {
                return true;
            }
        }
        return false;
    }

    /******************************************************************************
     *
     * function set($key, $value)
     * @param str $key This is the SESSION[$key] to set
     * @param str $value This is the raw string value to store
     *
     * This method will store the value in the session key
     *
    ******************************************************************************/
    public function set($key, $value)
    {
        if (($key) && ($value)) {
            $_SESSION[$key] = $value;
            return true;
        }
        return false;
    }

    public function _storeIpInfo($ipinfo)
    {
        log_message('debug', __CLASS__, 'class.Storage->storeIpInfo()', $this->debug);
        if (is_object($ipinfo)) {
            if (!filter_var($ipinfo->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE) === false) {
                $query['insert']['ip_info'][] = array(
                    'ip' => $ipinfo->ip,
                    'type' => $ipinfo->type,
                    'continent_code' => $ipinfo->continent_code,
                    'continent_name' => $ipinfo->continent_name,
                    'country_code' => $ipinfo->country_code,
                    'country_name' => $ipinfo->country_name,
                    'region_code' => $ipinfo->region_code,
                    'region_name' => $ipinfo->region_name,
                    'city' => $ipinfo->city,
                    'zip' => $ipinfo->zip,
                    'latitude' => $ipinfo->latitude,
                    'longitude' => $ipinfo->longitude
                );
                return $this->database->insert($query);
            }
        }
        return false;
    }
}

class IPStack
{
    public $ip;
    public $type = 'ipv4';
    public $continent_code;
    public $continent_name;
    public $country_code;
    public $country_name;
    public $region_code;
    public $region_name;
    public $city;
    public $zip;
    public $latitude = '00.000';
    public $longitude = '00.000';

    public function __construct($ipstack)
    {
        if (is_object($ipstack)) {
            unset($ipstack->location);
            foreach($ipstack as $key => $val) {
                if (!empty($val))
                    $this->$key = $val;
            }
        }
        return $this;
    }
}

class IPLocation
{
    public $ip;
    public $type;
    public $timestamp;
    public $continent_code;
    public $continent_name;
    public $country_code;
    public $country_name;
    public $region_code;
    public $region_name;
    public $city;
    public $zip;
    public $latitude;
    public $longitude;
    public function __construct($data)
    {
        if (is_object($data)) {
            foreach($data as $key => $val) {
                $this->$key = $val;
            }
        }
        return $this;
    }
}

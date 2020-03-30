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
 * Authentication class
 *
 * Class for management of authorization tokens
 *
******************************************************************************/
class Authentication
{
    private $debug = false;

    protected $database;
    protected $security;

    public function __construct($params = null)
    {
        log_message('debug',__CLASS__,  'forced DEBUG enabled', $this->debug);
        // process params
        if (! is_null($params)) {
            foreach($params as $name => $object) {
                if ($object)
                    $this->$name = $object;
            }
        }
        log_message('debug', __CLASS__, 'class instantiated', $this->debug);
    }

    public function createToken($length = '16', $hours = 24)
    {
        if (!is_numeric($hours))
            $hours = 24;
        log_message('debug', __CLASS__, 'createToken()', $this->debug);
        if (!is_numeric($length))
            $length = '16';
        if ($length > '255')
            $length = '255';
        log_message('info', __CLASS__, 'createToken() requested token length of: '.$length, $this->debug);
        $token = $this->security->randomString($length);
        if ($this->_storeToken($token, $hours)) {
            log_message('info', __CLASS__, 'createToken() unique token successfully created', $this->debug);
            $xtoken = $this->getToken($token);
            return $xtoken;
        }
        $this->createToken($length, $hours);
    }

    public function checkExistingToken($token)
    {
        log_message('debug', __CLASS__, '_checkExistingToken()', $this->debug);
        if (isset($token)) {
            $query = array();
            $query['select']['authentication_tokens'] = array('token');
            $query['from'] = 'authentication_tokens';
            $query['where']['authentication_tokens']['token'] = $token;
            if ($result = $this->database->select($query)) {
                log_message('info', __CLASS__, '_checkExistingToken() token match found', $this->debug);
                return true;
            }
        }
        return false;
    }

    public function getToken($xtoken)
    {
        log_message('debug', __CLASS__, '_checkExistingToken()', $this->debug);
        if (isset($xtoken)) {
            $query = array();
            $query['select']['authentication_tokens'] = array('token', 'timestamp', 'length', 'expiration');
            $query['from'] = 'authentication_tokens';
            $query['where']['authentication_tokens']['token'] = $xtoken;
            if ($token = $this->database->select($query)) {
                log_message('info', __CLASS__, 'getToken() token match found', $this->debug);
                return $token;
            }
        }
        return false;
    }

    private function _storeToken($token, $hours)
    {
        log_message('debug', __CLASS__, '_storeToken()', $this->debug);
        if (isset($token)) {
            $expires = date('Y-m-d H:i:s', strtotime('+'.$hours.' hours'));
            $length = strlen($token);
            $insert = array();
            $insert['insert']['authentication_tokens'][] = array('token' => $token, 'session_id' => session_id(), 'ip_address' => $_SERVER['REMOTE_ADDR'], 'length' => $length, 'expiration' => $expires);
            if ($this->database->insert($insert)) {
                log_message('info', __CLASS__, '_storeToken() token stored', $this->debug);
                return true;
            }
        }
        return false;
    }
}

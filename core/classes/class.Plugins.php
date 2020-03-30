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
 * Plugins class
 *
 * This module will provide information from all available plugins, both
 * enabled and disabled, as well as provide methods for processing
 * plugin execution upon request and at proper framework locations, such
 * as hooks.
 *
 * This will also enable, disable, and update modules.
 *
******************************************************************************/
class Plugins
{

    private $debug = false;

    protected $_all_plugins;
    protected $_enabled_plugins;
    protected $_loaded_plugins;

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
}

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
 * Modules class
 *
 * This class will provide all information regarding the available modules,
 * enabled modules, and the current module in use as well as update all
 * module information in the database.
 *
 * This will also enable, disable, and update all modules.
 *
******************************************************************************/
class Modules
{

    private $debug = false;

    protected $_all_modules;
    protected $_enabled_modules;
    protected $_loaded_modules;

    public $module;

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
        (isset($this->router->module))
            ? $this->module = $this->router->module
            : $this->module = DEFAULT_MODULE;
        log_message('debug', __CLASS__, 'class instantiated', $this->debug);
    }
}

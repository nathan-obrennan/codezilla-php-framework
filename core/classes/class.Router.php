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
 * Router class
 *
 * The router class determines which files need to be sourced during
 * framework execution, as well as providing path access for modules,
 * plugins, redirect information, and essentially anything that needs
 * to know where we are or where we are going.
 *
******************************************************************************/
class Router
{
    private $debug    = false;

    protected $_all_segments;
    protected $_last_uri_seg;
    protected $_segment_name;

    public $controller;
    public $controller_path;
    public $module          = DEFAULT_MODULE;
    public $module_path;
    public $mod_info;
    public $params;
    public $referer;
    public $request_uri;
    public $segments;
    public $uri;

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

        // Establish where we came from
        if (! empty($_SERVER['HTTP_REFERER'])) {
            $this->referer  = $_SERVER['HTTP_REFERER'];
        }

        // Store the original URI request
        $this->request_uri  = $this->input->getUri();
        $this->params       = $this->input->getParams();

        // grab the segments for later use
        $this->segments     = reindexArray(explode('/', $this->request_uri));

        // establish some defaults in case we are at the root of our url
        if ($this->request_uri === '/' || empty($this->request_uri)) {
            $this->uri = $this->module;
            $this->_last_uri_seg = 1;
            $this->segments[1] = $this->module;
        }

        // since modules are broken up into system folders and modules folders
        // we need to establish the system path for looking up modinfo files
        if (in_array($this->segments[1], unserialize(ROUTES))) {
            $this->system_path = SYSTEM;
        }else {
            $this->system_path = MODULES;
        }

        // debug
        log_message('debug', __CLASS__, '_construct() system_path: ' . $this->system_path, $this->debug);

        // Search through the segments and find the last segment with an existing matching
        // directory name. This is our working path unless it has an excluded name
        $segcount = 1;
        while ($segcount <= count($this->segments)) {
            $directory = buildString($this->segments, '1', $segcount);
            if (is_dir($this->system_path . DIRECTORY_SEPARATOR . $directory)) {
                $this->controller_path = rtrim($this->system_path . DIRECTORY_SEPARATOR . $directory, DIRECTORY_SEPARATOR);
                $this->_last_uri_seg = $segcount;
                $this->_all_segments = $segcount - 1;
                $this->_segment_name = $this->segments[$this->_last_uri_seg];
                // Some modules will contain a sub directory called process or reports or something
                // else and we need to exclude those from the module name so the proper
                // modinfo.php file will load.
                if (($this->_segment_name != 'process') && ($this->_segment_name != 'reports')) {
                    if (is_file($this->controller_path . DIRECTORY_SEPARATOR . 'modinfo.php')) {
                        $this->module = $this->_segment_name;
                    }elseif ($this->_segment_name == 'cron') {
                        $this->module = 'cron';
                    }elseif ($this->_segment_name == 'scripts') {
                        if (isset($this->segments[2]) && $this->segments[2] == 'ajax') {
                            $this->module = 'ajax';
                        }
                    }
                }
            }else {
                // no more folders exist, don't waste time looking.
                break;
            }
            $segcount++;
        }

        // If the controller path is empty that means we are at the root of the site and any additional parameters or segments should
        // be handled by the default module and controller. So set the defaults...
        if (empty($this->controller_path)) {
            $this->controller_path = $this->system_path . DIRECTORY_SEPARATOR . $this->module;
        }

        // set the module path
        $this->module_path = $this->system_path . DIRECTORY_SEPARATOR . $this->module;
        $this->mod_info    = $this->module_path . DIRECTORY_SEPARATOR . 'modinfo.php';

        $next_segment = $this->_last_uri_seg + 1;

        $controllers = array();
        if (isset($this->segments[$next_segment]))
            $controllers[] = $this->segments[$next_segment];
        if (isset($this->segments[$this->_last_uri_seg]))
            $controllers[] = $this->segments[$this->_last_uri_seg];

        // fallback controllers
        $controllers[] = 'controller';
        $controllers[] = 'index';

        foreach($controllers as $xcontroller) {
            $controller = pathinfo($xcontroller)['filename'];
            if ((!empty($controller)) && is_file($this->controller_path . DIRECTORY_SEPARATOR . $controller . '.php')) {
                $this->controller = $controller.'.php';
                break;
            }
        }
        log_message('debug', __CLASS__, '_construct() the controller: ' . $this->controller, $this->debug);
        log_message('debug', __CLASS__, 'class instantiated', $this->debug);
    }
}

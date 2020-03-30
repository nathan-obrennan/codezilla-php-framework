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

/* This is the Admin theme config file which lists the appropriate files to load */
$this->loadCss('/common/themes/'.$this->site_theme.'/css/bootstrap.min.css');
$this->loadCss('/common/themes/'.$this->site_theme.'/css/font-awesome.min.css');
$this->loadCss('https://fonts.googleapis.com/css?family=Roboto:300,400,500,700');
$this->loadCss('/common/themes/'.$this->site_theme.'/css/nprogress.css');
$this->loadCss('/common/themes/'.$this->site_theme.'/css/custom.min.css');
$this->loadCss('/common/css/Codezilla.css');

$this->loadJs('/common/themes/'.$this->site_theme.'/js/jquery.min.js');
$this->loadJs('/common/themes/'.$this->site_theme.'/js/bootstrap.bundle.min.js');
$this->loadJs('/common/themes/'.$this->site_theme.'/js/fastclick.js');
$this->loadJs('/common/themes/'.$this->site_theme.'/js/nprogress.js');
$this->loadJs('/common/themes/'.$this->site_theme.'/js/custom.min.js');
$this->loadJs('/common/js/Codezilla.js');

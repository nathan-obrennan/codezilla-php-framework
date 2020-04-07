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

$this->page_name = 'Welcome';
$this->loadView(array('head'));

if (isset($_SESSION['messages'])) {
    if (count($_SESSION['messages']) > 0) {
        foreach($_SESSION['messages'] as $message) {
            echo '<p class="error-message">'.$message.'</p>';
        }
        unset($_SESSION['messages']);
    }
}

echo '<ul>';
if ($this->users->isLoggedIn()) {
    echo '  <li><a href="'.HTTP_HOST.'/accounts/profile.html">Profile</a></li>';
    if ($this->users->isAdmin())
        echo '  <li><a href="'.HTTP_HOST.'/administration/dashboard.html">Admin Dashboard</a></li>';
    echo '  <li><a href="'.HTTP_HOST.'/accounts/logout.html">Logout</a></li>';
}
else {
    message('Please log in');
    redirectTo(HTTP_HOST.'/accounts/login.html');
}
echo '</ul>';
     echo PHP_EOL; // source formatting
    // load the css files
    $this->loadJs();
?>

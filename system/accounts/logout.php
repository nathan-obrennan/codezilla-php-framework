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
if ($this->storage->keyExists('userEmail')) {
    $userEmail = $this->storage->decrypt('userEmail');
}
if (isset($this->input->xss->redirect)) {
    $this->storage->set('redirect', $this->input->xss->redirect);
}
$redirect = HTTP_HOST;
if ($this->storage->keyExists('redirect')) {
    $redirect = $this->storage->get('redirect');
}

$this->page_name = 'Logout';
$this->loadView(array('head'));
?>
<body class="login">
    <div>
        <a class="hiddenanchor" id="signup"></a>
        <a class="hiddenanchor" id="signin"></a>
        <a class="hiddenanchor" id="forgot"></a>
        <div class="login_wrapper">
            <h1>Logging out</h1>
            <p>...please wait while the system logs you out...</p>
        </div>
    </div>
</body>
<?php
if (isset($this->users->me)) {
    $this->users->me->logout();
}
redirectTo($redirect, 3);

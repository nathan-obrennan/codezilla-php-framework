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
if ($this->users->isLoggedIn()) {
    redirectTo(HTTP_HOST.'/accounts/profile.html');
}
if (isset($this->input->xss->redirect)) {
    $this->storage->set('redirect', $this->input->xss->redirect);
}

if ($this->storage->keyExists('userEmail')) {
    $userEmail = $this->storage->decrypt('userEmail');
}

$this->loadCss('/common/themes/'.$this->site_theme.'/css/animate.min.css');

$this->page_name = 'Registration';
$this->loadView(array('head'));
if (isset($_SESSION['messages'])) {
    if (count($_SESSION['messages']) > 0) {
        foreach($_SESSION['messages'] as $message) {
            echo '<p class="error-message">'.$message.'</p>';
        }
        unset($_SESSION['messages']);
    }
}
?>
<body class="login">
    <div>
        <a class="hiddenanchor" id="signup"></a>
        <a class="hiddenanchor" id="signin"></a>

        <div class="login_wrapper">
            <div class="animate form login_form">
                <section class="login_content">
                    <form method="post" class="form-validate" action="<?php echo HTTP_HOST.'/'.$this->router->module.'/process/process_registration'; ?>">
                        <h1>Registration Form</h1>
                        <div>
                            <input type="text" class="form-control" placeholder="Email" name="registerEmail" required>
                        </div>
                        <div>
                            <input type="submit" class="btn btn-default submit" value="Register">
                        </div>
                        <div class="clearfix"></div>
                        <div class="separator">
                            <p class="change_link">Already have an account ?
                                <a href="<?php echo HTTP_HOST.'/'.$this->router->module.'/login.html'; ?>"> Log in </a>
                            </p>
                            <div class="clearfix"></div>
                            <br />
                        </div>
                        <div class="clearfix"></div>
                        <div class="separator">
                            <p>
                                ** Our Promise to you ** <br />
                                We need your email so we can send you a notification when your requrested items have been delivered.
                                With your permission, we will also send you a notification when our newest product list is available. We will not spam you, nor
                                will we ever sell your email address.
                            </p>
                            <div class="clearfix"></div>
                            <br />
                        </div>
                    </form>
                </section>
            </div>

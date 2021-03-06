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
$this->page_name = 'Login';
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
        <a class="hiddenanchor" id="forgot"></a>

        <div class="login_wrapper">
            <div class="animate form login_form">
                <section class="login_content">
                    <form method="post" class="form-validate" action="<?php echo HTTP_HOST.'/'.$this->router->module.'/process/process_login'; ?>">
                        <h1>Login Form</h1>
                        <div>
                            <input type="text" class="form-control" placeholder="Email" name="loginUsername" required>
                        </div>
                        <div>
                            <input type="password" class="form-control" placeholder="Password" name="loginPassword" required>
                        </div>
                        <div>
                            <input type="submit" class="btn btn-default submit" value="Log in">
                            <a class="reset_pass" href="#signup">Lost your password?</a>
                        </div>
                        <div class="clearfix"></div>
                        <div class="separator">
                            <p class="change_link">New to site?
                                <a href="<?php echo HTTP_HOST.'/'.$this->router->module.'/register.html'; ?>"> Create Account </a>
                            </p>
                            <div class="clearfix"></div>
                            <br />
                        </div>
                    </form>
                </section>
            </div>

            <div id="register" class="animate form registration_form">
                <section class="login_content">
                    <form method="post" class="form-validate" action="<?php echo HTTP_HOST.'/'.$this->router->module.'/process/process_forgot_password'; ?>">
                        <h1>Forgot Password</h1>
                        <div>
                            <input type="email" class="form-control" placeholder="Email" required="" />
                        </div>
                        <div>
                            <input type="submit" class="btn btn-default submit" name="register" value="Send Password">
                        </div>
                        <div class="clearfix"></div>
                        <div class="separator">
                            <p class="change_link">Already a member ?
                                <a href="#signin" class="to_register"> Log in </a>
                            </p>
                            <div class="clearfix"></div>
                            <br />
                        </div>
                    </form>
                </section>
            </div>            </div>
        </div>
        <?php
            echo PHP_EOL; // source formatting
            // load the css files
            $this->loadJs();
        ?>
    </body>
</html>

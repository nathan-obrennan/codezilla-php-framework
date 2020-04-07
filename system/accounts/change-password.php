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
if (isset($this->input->xss->redirect)) {
    $this->storage->set('redirect', $this->input->xss->redirect);
}

if (!$this->users->isLoggedIn()) {
    message('You were trying to change your password while not logged in. Clearing cache to start over.');
    redirectTo(HTTP_HOST.'/accounts/logout.html');
}

if ($this->storage->keyExists('userEmail')) {
    $userEmail = $this->storage->decrypt('userEmail');
}

$this->loadCss('/common/themes/'.$this->site_theme.'/css/animate.min.css');

$this->page_name = 'Password Change';
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
                    <form method="post" class="form-validate" action="<?php echo HTTP_HOST.'/'.$this->router->module.'/process/process_change_password'; ?>">
                        <h1>Change Password</h1>
                        <div>
                            <input type="password" class="form-control" placeholder="Password" name="loginPassword1" required>
                        </div>
                        <div>
                            <input type="password" class="form-control" placeholder="Confirm Password" name="loginPassword2" required>
                        </div>
                        <div>
                            <input type="submit" class="btn btn-default submit" value="Change Password">
                        </div>
                    </form>
                </section>
            </div>

        </div>
    </div>
</body>
 <?php
    echo PHP_EOL; // source formatting
    // load the css files
    $this->loadJs();
?>

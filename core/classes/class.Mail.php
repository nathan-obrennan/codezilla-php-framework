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
 * Mail class
 *
 * Connect to and utilize different mail systems for sending mail and
 * error messages
 *
 * If an external service is selected, such as sendgrid, then we load that
 * specific mailer, if a local sendmail/postfix is selected, then use
 * the system mailer, otherwise, send emails to Codezilla for relay via
 * webAPI.
 *
 * ** WebAPI sendmail is only available for system notices.
 *
******************************************************************************/
class Mail
{
    public $active = false;
    public $debug = false;
    public $environment;

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

        /*
         * If smtp_host == localhost then we can use phpMailer to send mail
         */
        if ($this->environment->mail_type == 'phpMailer') {
            // load the phpMailer files
            //require(VENDORS . DIRECTORY_SEPARATOR . 'PHPMailer' . DIRECTORY_SEPARATOR . 'PHPMailer.php');
            //require(VENDORS . DIRECTORY_SEPARATOR . 'PHPMailer' . DIRECTORY_SEPARATOR . 'Exception.php');
            //require(VENDORS . DIRECTORY_SEPARATOR . 'PHPMailer' . DIRECTORY_SEPARATOR . 'SMTP.php');
            //require(VENDORS . DIRECTORY_SEPARATOR . 'PHPMailer' . DIRECTORY_SEPARATOR . 'OAuth.php');
            //require(VENDORS . DIRECTORY_SEPARATOR . 'PHPMailer' . DIRECTORY_SEPARATOR . 'POP3.php');
            //if ($this->phpmailer = new PHPMailer\PHPMailer\PHPMailer())
            //    $this->active = true;

            require(VENDORS . DIRECTORY_SEPARATOR . 'PHPMailer' . DIRECTORY_SEPARATOR . 'class.phpmailer.php');
            if ($this->phpmailer = new PHPMailer())
                $this->active = true;
        }
    }

    public function _initialize()
    {
        if ($this->environment->mail_type == 'phpMailer') {
            // make sure we are cleared of addresses/attachments/headers
            $this->phpmailer->clearAddresses();
            $this->phpmailer->clearCCs();
            $this->phpmailer->clearBCCs();
            $this->phpmailer->clearReplyTos();
            $this->phpmailer->clearAllRecipients();
            $this->phpmailer->clearAttachments();
            $this->phpmailer->clearCustomHeaders();

            $this->phpmailer->Host         = $this->environment->smtp_host;
            $this->phpmailer->Port         = $this->environment->smtp_port;

            // set a debug level 0 = off, 4 = everything
            ($this->debug)
                ? $this->phpmailer->SMTPDebug = 4
                : $this->phpmailer->SMTPDebug = 0;

            if ($this->environment->smtp_auth) {
                log_message('debug', __CLASS__, '_initialize() SMTP Authentication Enabled', $this->debug);
                $this->phpmailer->IsSMTP();
                $this->phpmailer->SMTPAuth     = true;
                $this->phpmailer->SMTPSecure   = 'tls';
                $this->phpmailer->Username     = $this->environment->smtp_user;
                $this->phpmailer->Password     = $this->environment->smtp_pass;
            }
            else {
                log_message('debug', __CLASS__, '_initialize() Standard SMTP Enabled', $this->debug);
                $this->phpmailer->IsSMTP();
                $this->phpmailer->SMTPAuth     = false;
            }
        }
    }

    /*************************************************************************
      * function sendEmail($From, $To, $CC, $BCC, $Subject, $htmlBody, $txtBody, $Attachments)
      * @access public
      * @param array $From       -- The sender. This array should contain the From name and email.
      * @param array $To         -- The recipients. This should be an array containing the name and address of all recipients.
      * @param array $CC         -- Courtesy Copy recipients. This should be an array containing the name and address of all cc recipients.
      * @param array $BCC        -- Blind Courtesy Copy recipients. This should be an array containing the name and address of all bcc recipients.
      * @param string $Subject   -- The subject of the email to be sent.
      * @param string $htmlBody  -- The html body of the email. You can have one or both html/txt
      * @param string $txtBody   -- The text body of the email. You can have one or both html/txt
      * @param blob $Attachments -- An attachment to include with the email.
      *
    *************************************************************************/
    public function sendEmail($From = array(), $To = array(), $Subject = null, $htmlBody = null, $txtBody = null, $CC = null, $BCC = null, $Attachments = null)
    {
        log_message('debug', __CLASS__, 'sendEmail() sending email', $this->debug);
        $this->_initialize();
        // set the FROM and REPLY_TO addresses
        if (is_array($From)) {
            foreach($From as $address => $name) {
                $this->phpmailer->SetFrom($address, $name);
                $this->phpmailer->AddReplyTo($address, $name);
            }
        }

        //set the recipient email addresses
        if (is_array($To)) {
            foreach($To as $address => $name) {
                $this->phpmailer->AddAddress($address, $name);
            }
        }

        // add any CC's to the email
        if ( is_array($CC)) {
            foreach ($CC as $address => $name) {
                $this->phpmailer->AddCC($address, $name);
            }
        }

        // add any BCC's to the email
        if (is_array($BCC)) {
            foreach ($BCC as $address => $name) {
                $this->phpmailer->AddBCC($address, $name);
            }
        }

        // set the Subject
        $this->phpmailer->Subject = $Subject;

        // set the text message in the body
        if (is_null($txtBody)) {
            $this->phpmailer->AltBody = "To view the message, please use an HTML compatible email viewer!";
        }
        else {
            $this->phpmailer->Body = $txtBody;
        }

        // set the HTML portion of the body
        if (!is_null($htmlBody)) {
            $this->phpmailer->MsgHTML($htmlBody);
        }
        else {
            $this->phpmailer->IsHtml(false);
        }

        // add attachments
        if (!is_null($Attachments)) {
            if (is_array($Attachments)) {
                foreach($Attachments as $attachment) {
                    $this->phpmailer->AddAttachment($attachment);
                }
            }
            else {
                $this->phpmailer->AddAttachment($Attachments);
            }
        }

        if ($this->active == true) {
            if ($this->phpmailer->Send()) {
                return true;
            }else {
                show($this->phpmailer);
            }
        }
        return false;
    }

}

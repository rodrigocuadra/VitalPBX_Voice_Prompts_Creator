<?php
/**
 * ============================================================================
 * File: utils/email_forgot.php
 * ============================================================================
 * Purpose:
 * --------
 * This utility provides a function to send "Forgot Password" emails to users
 * who request a password reset. It uses SMTP credentials stored in the
 * configuration file `modules/email/email_config.json` and the PHPMailer
 * library for email delivery.
 *
 * Dependencies:
 * -------------
 * - PHPMailer (autoloaded via Composer)
 * - utils/helpers.php (for debug_log)
 * - Configuration file: modules/email/email_config.json
 *
 * SMTP Configuration:
 * -------------------
 * The `email_config.json` file must contain:
 * {
 *   "host": "smtp.example.com",
 *   "port": 587,
 *   "username": "smtp-user@example.com",
 *   "password": "smtp-password",
 *   "from": "no-reply@example.com",
 *   "from_name": "Text-to-Speech Platform"
 * }
 *
 * Function:
 * ---------
 * send_forgot_password_mail(string $toEmail, string $toName, string $resetLink): bool
 *
 * Parameters:
 *  - $toEmail   The destination email address of the user.
 *  - $toName    The full name of the recipient (for personalization).
 *  - $resetLink The secure link the user will click to reset their password.
 *
 * Behavior:
 * ---------
 * 1. Loads SMTP configuration from `email_config.json`.
 * 2. Builds an HTML email body that contains a Reset Password button.
 * 3. Sends the email using PHPMailer.
 * 4. Logs activity with debug_log().
 *
 * Return:
 * -------
 * - true  → If the email was successfully sent.
 * - false → If sending failed (missing configuration, invalid credentials,
 *            PHPMailer error, etc.)
 *
 * Example:
 * --------
 *   $link = "https://example.com/login/reset_password.php?token=XYZ";
 *   send_forgot_password_mail("user@example.com", "John Doe", $link);
 *
 * Author:
 * -------
 * VitalPBX Team
 * ============================================================================
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/helpers.php'; // Provides debug_log()

/**
 * Sends a password reset email.
 *
 * @param string $toEmail Destination email.
 * @param string $toName Full name of recipient.
 * @param string $resetLink Secure reset password link.
 * @return bool True on success, false on failure.
 */
function send_forgot_password_mail(string $toEmail, string $toName, string $resetLink): bool
{
    // ------------------------------------------------------------------------
    // Load SMTP configuration
    // ------------------------------------------------------------------------
    $configFile = __DIR__ . '/../modules/email/email_config.json';
    if (!file_exists($configFile)) {
        debug_log("Missing email_config.json file", 'email_forgot');
        return false;
    }

    $config = json_decode(file_get_contents($configFile), true);
    if (
        !$config || empty($config['host']) ||
        empty($config['username']) || empty($config['password'])
    ) {
        debug_log("Invalid or incomplete SMTP configuration", 'email_forgot');
        return false;
    }

    // ------------------------------------------------------------------------
    // Build the email body
    // ------------------------------------------------------------------------
    $body = "
        <p style='font-size:18px;'>Hello <strong>" . htmlspecialchars($toName) . "</strong>,</p>
        <p style='font-size:15px;'>
            You have requested to reset your password in the Text-to-Speech system.
            Click the button below to continue:
        </p>
        <p style='margin-top:20px;'>
            <a href='" . htmlspecialchars($resetLink) . "' 
               style='display:inline-block;padding:12px 24px;background:#007BFF;color:#fff;
                      text-decoration:none;border-radius:6px;font-weight:bold;'>
                Reset Password
            </a>
        </p>
        <p style='margin-top:25px;font-size:13px;color:#555;'>
            If you did not request this, please ignore this email.
        </p>
        <hr>
        <p style='font-size:13px;color:#777;'>Text-to-Speech Platform</p>
    ";

    // ------------------------------------------------------------------------
    // Send the email using PHPMailer
    // ------------------------------------------------------------------------
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];
        $mail->SMTPSecure = 'tls';
        $mail->Port       = $config['port'] ?? 587;

        $mail->setFrom($config['from'] ?? $mail->Username, $config['from_name'] ?? 'TTS System');
        $mail->addReplyTo($mail->Username, $config['from_name'] ?? 'TTS System');
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request';
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();

        debug_log("Forgot password email sent to {$toEmail}", 'email_forgot');
        return true;

    } catch (Exception $e) {
        $msg = "Error sending forgot password email: " . $e->getMessage();
        debug_log($msg, 'email_forgot');
        return false;
    }
}

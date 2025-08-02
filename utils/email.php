<?php
/**
 * ============================================================================
 * File: utils/email.php
 * ============================================================================
 * Purpose:
 * --------
 * Provides helper functions to send emails using the PHPMailer library.
 * This module is mainly used by the system to send test emails from the
 * Email Settings page, ensuring SMTP credentials are correctly configured.
 *
 * Requirements:
 * -------------
 * - PHPMailer must be installed in `/vendor` (via Composer).
 * - utils/helpers.php for logging/debug purposes.
 *
 * Functions:
 * ----------
 * - send_test_email(array $config, string $to): bool
 *
 * SMTP Configuration:
 * -------------------
 * The $config array must include:
 *   - host       → SMTP server hostname (e.g., smtp.gmail.com)
 *   - port       → Port number (e.g., 587 for TLS, 465 for SSL)
 *   - username   → SMTP account username
 *   - password   → SMTP account password
 *   - from       → Email address that will appear as sender
 *   - from_name  → Sender name
 *
 * Behavior:
 * ---------
 * - Connects to the SMTP server.
 * - Chooses encryption type based on the configured port:
 *      Port 465 → PHPMailer::ENCRYPTION_SMTPS (SSL)
 *      Others  → PHPMailer::ENCRYPTION_STARTTLS (TLS)
 * - Sends a small HTML and plaintext test email to the given destination.
 *
 * Error Handling:
 * ---------------
 * - On failure, the error is logged into:
 *      logs/email_errors.log
 * - The function returns `false` if the email cannot be sent.
 *
 * Example:
 * --------
 * $config = [
 *   'host' => 'smtp.example.com',
 *   'port' => 587,
 *   'username' => 'user@example.com',
 *   'password' => 'secret',
 *   'from' => 'noreply@example.com',
 *   'from_name' => 'System'
 * ];
 *
 * $ok = send_test_email($config, 'test@example.com');
 * if ($ok) {
 *     echo "Email sent successfully!";
 * } else {
 *     echo "Failed to send test email.";
 * }
 *
 * Author:
 * -------
 * VitalPBX Team
 * ============================================================================
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/helpers.php';

/**
 * Sends a simple test email using the given SMTP configuration.
 *
 * @param array $config SMTP settings: host, port, username, password, from, from_name
 * @param string $to Destination email address
 * @return bool true if the email was sent successfully, false otherwise
 */
function send_test_email(array $config, string $to): bool
{
    $logFile = __DIR__ . '/../logs/email_errors.log';

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];
        $mail->Port       = (int)$config['port'];

        // Select encryption type based on port
        if ($mail->Port == 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS
        }

        $mail->setFrom($config['from'], $config['from_name']);
        $mail->addAddress($to);

        // Email content
        $mail->Subject = 'SMTP Test Email';
        $mail->isHTML(true);
        $mail->Body    = '<h2>SMTP Test Email</h2><p>This is a test email from your system configuration.</p>';
        $mail->AltBody = "SMTP Test Email\nThis is a test email from your system configuration.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email send error: " . $mail->ErrorInfo);
        file_put_contents(
            $logFile,
            "[" . date('Y-m-d H:i:s') . "] " . $mail->ErrorInfo . PHP_EOL,
            FILE_APPEND
        );
        return false;
    }
}

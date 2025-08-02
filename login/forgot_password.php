<?php
/**
 * ============================================================================
 * File: login/forgot_password.php
 * ============================================================================
 * Purpose:
 * --------
 * This script handles the **Forgot Password** functionality in the Text-to-Speech
 * dashboard. It allows users to request a password reset link, which is sent
 * to their registered email address if a matching account is found.
 *
 * Workflow:
 * ---------
 * 1. The user submits their email (or username) through a form.
 * 2. If the account exists:
 *      - A secure reset token is generated using `random_bytes()`.
 *      - The token and its expiration time (1 hour) are stored in the `users` table.
 *      - An email with a reset link is sent to the user using
 *        `send_forgot_password_mail()` from `utils/email_forgot.php`.
 * 3. Regardless of whether the account exists, a generic message is shown
 *    to avoid leaking user information.
 *
 * Security Notes:
 * ---------------
 * - The reset token should be stored in the database and verified later in
 *   `reset_password.php`.
 * - Tokens expire after 1 hour (configurable in `$expiresAt`).
 * - It is recommended to store the token in a hashed form (not plain text)
 *   for additional security.
 *
 * Dependencies:
 * -------------
 * - config/database.php: For database connection using `getPDO()`.
 * - utils/helpers.php: Debugging/logging utilities.
 * - utils/email_forgot.php: Must define `send_forgot_password_mail()`
 *   to send the reset email.
 *
 * Expected Database Columns:
 * --------------------------
 * Table: `users`
 * Columns required (add them if they don't exist):
 *   - reset_token VARCHAR(255)
 *   - reset_expires_at DATETIME
 *
 * URL of the Reset Link:
 * ----------------------
 * The generated reset link points to:
 *   /login/reset_password.php?token=<TOKEN>
 *
 * Author:
 * -------
 * VitalPBX Team
 * ============================================================================
 */

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/email_forgot.php';

session_start();

// If the user is already logged in, redirect to the dashboard
if (isset($_SESSION['validado']) && $_SESSION['validado']) {
    header('Location: /index.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    // Validate email/username input
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) < 3) {
        // Accepts emails or usernames, so a non-email input is allowed if it's long enough
        $error = "Please enter a valid email address or username.";
    } else {
        try {
            $pdo = getPDO();

            // Look up user by email OR username
            $stmt = $pdo->prepare("
                SELECT id, full_name
                FROM users
                WHERE username = :email OR email = :email
                LIMIT 1
            ");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // 1. Generate a secure token
                $token = bin2hex(random_bytes(32));
                $expiresAt = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

                // 2. Save token and expiration in database
                $pdo->prepare("
                    UPDATE users
                    SET reset_token = ?, reset_expires_at = ?
                    WHERE id = ?
                ")->execute([$token, $expiresAt, $user['id']]);

                // 3. Build the reset link
                $resetLink = "https://" . $_SERVER['HTTP_HOST']
                           . "/login/reset_password.php?token=" . urlencode($token);

                // 4. Send email with the reset link
                if (send_forgot_password_mail($email, $user['full_name'], $resetLink)) {
                    $message = "If the email exists in our system, a password reset link has been sent.";
                } else {
                    $error = "Could not send the reset email. Contact the administrator.";
                }
            } else {
                // Generic response to prevent account enumeration
                $message = "If the email exists in our system, a password reset link has been sent.";
            }
        } catch (Throwable $e) {
            error_log("Forgot password error: " . $e->getMessage());
            $error = "An unexpected error occurred.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - Text-to-Speech Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            font-family: 'Segoe UI', sans-serif;
        }
        .card {
            width: 100%;
            max-width: 420px;
            padding: 2rem;
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0 16px rgba(0, 0, 0, 0.1);
            background-color: #fff;
        }
        .logo {
            max-width: 140px;
            display: block;
            margin: 0 auto 1rem;
        }
        .form-control {
            font-size: 1rem;
        }
        .btn {
            font-size: 1rem;
            padding: 0.75rem;
        }
        .error {
            color: #dc3545;
            font-weight: 500;
            margin-bottom: 1rem;
            text-align: center;
        }
        .message {
            color: #28a745;
            font-weight: 500;
            margin-bottom: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="card">
    <img src="/img/logo.png" alt="Logo" class="logo">
    <h4 class="text-center mb-4">Forgot your password?</h4>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post" action="forgot_password.php">
        <div class="mb-3">
            <label for="email" class="form-label">Email / Username</label>
            <input type="text" name="email" id="email" class="form-control"
                   placeholder="Enter your email or username" required autofocus>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-primary">Send reset link</button>
        </div>

        <div class="text-center mt-3">
            <a href="login.php" class="text-decoration-none">
                Back to login
            </a>
        </div>
    </form>
</div>

<script src="/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
/**
 * ============================================================================
 * File: login/reset_password.php
 * ============================================================================
 * Purpose:
 * --------
 * This script allows a user to set a new password using a password reset token
 * previously sent by email (from the forgot password process).
 *
 * Workflow:
 * ---------
 * 1. The user accesses this page through a URL containing a valid token
 *    (e.g., /login/reset_password.php?token=XXXX).
 * 2. The script verifies the token:
 *    - Ensures that the token exists in the database.
 *    - Ensures that the token has not expired (1 hour default).
 * 3. If the token is valid:
 *    - Displays a form to set a new password and confirm it.
 *    - On form submission:
 *        * Validates password length and confirmation.
 *        * Hashes the new password using bcrypt.
 *        * Updates the `users` table with the new password.
 *        * Clears the reset token and expiration fields.
 * 4. If the token is invalid or expired:
 *    - Displays an error message and instructs the user to request a new reset link.
 *
 * Security Notes:
 * ---------------
 * - Passwords are hashed using PHP's password_hash() (bcrypt).
 * - Tokens are single-use: they are cleared after a successful reset.
 * - The token must be stored in the database alongside an expiration timestamp.
 * - The script does not reveal whether an email/token is invalid until after submission.
 *
 * Dependencies:
 * -------------
 * - config/database.php: provides getPDO() for database connection.
 * - utils/helpers.php: provides debug_log() and other helper functions.
 *
 * Database Requirements:
 * ----------------------
 * The `users` table must include:
 *   - reset_token (VARCHAR)
 *   - reset_expires_at (DATETIME)
 *
 * Session:
 * --------
 * - If the user is already logged in (`$_SESSION['validado'] = true`), they will
 *   be redirected to the main dashboard `/index.php`.
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

session_start();

// Redirect logged-in users
if (isset($_SESSION['validado']) && $_SESSION['validado']) {
    header('Location: /index.php');
    exit;
}

$message = '';
$error = '';
$validToken = false;
$token = $_GET['token'] ?? '';

if ($token) {
    try {
        $pdo = getPDO();

        // ----------------------------------------------------------------------
        // Step 1: Verify token
        // ----------------------------------------------------------------------
        $stmt = $pdo->prepare("
            SELECT id, full_name, reset_expires_at 
            FROM users
            WHERE reset_token = ?
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $expires = new DateTime($user['reset_expires_at']);
            $now = new DateTime();

            if ($now < $expires) {
                $validToken = true;

                // ------------------------------------------------------------------
                // Step 2: Handle form submission (reset password)
                // ------------------------------------------------------------------
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $password = $_POST['password'] ?? '';
                    $confirm  = $_POST['confirm'] ?? '';

                    // Validate password
                    if (strlen($password) < 6) {
                        $error = "Password must be at least 6 characters long.";
                    } elseif ($password !== $confirm) {
                        $error = "Passwords do not match.";
                    } else {
                        // Hash password using bcrypt
                        $hash = password_hash($password, PASSWORD_BCRYPT);

                        // Update user and clear reset token
                        $update = $pdo->prepare("
                            UPDATE users 
                            SET password = ?, reset_token = NULL, reset_expires_at = NULL 
                            WHERE id = ?
                        ");
                        $update->execute([$hash, $user['id']]);

                        $message = "Your password has been successfully reset. You can now log in.";
                        $validToken = false;
                    }
                }
            } else {
                $error = "The reset link has expired. Please request a new one.";
            }
        } else {
            $error = "Invalid token. Please request a new password reset.";
        }

    } catch (Throwable $e) {
        error_log("Reset password error: " . $e->getMessage());
        $error = "An unexpected error occurred.";
    }
} else {
    $error = "No token provided.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - Text-to-Speech Dashboard</title>
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
    <h4 class="text-center mb-4">Reset Password</h4>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
        <div class="text-center mt-3">
            <a href="login.php" class="text-decoration-none btn btn-primary">Back to Login</a>
        </div>
    <?php endif; ?>

    <?php if ($validToken): ?>
        <!-- Password reset form -->
        <form method="post" action="">
            <div class="mb-3">
                <label for="password" class="form-label">New password</label>
                <input type="password" name="password" id="password" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label for="confirm" class="form-label">Confirm password</label>
                <input type="password" name="confirm" id="confirm" class="form-control" required>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-success">Reset password</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<script src="/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
/**
 * ============================================================================
 * File: login.php
 * ============================================================================
 * Purpose:
 * --------
 * This script renders and processes the login page for the **Text-to-Speech
 * Management System**. It validates user credentials against the database and
 * starts a user session upon successful authentication.
 *
 * Workflow:
 * ---------
 * 1. If the user is already logged in (session `validado = true`), redirect
 *    immediately to `/index.php`.
 * 2. If the login form is submitted via POST:
 *      - Extract the username and password.
 *      - Call `verifyCredentials()` from `models/login_model.php`.
 *      - If credentials are valid:
 *          * Call `startSession()` to initialize the session data.
 *          * Redirect to the main dashboard `/index.php`.
 *      - If invalid, display an error message.
 *      - If the account is disabled, display a specific error message.
 * 3. If no form submission, display the login form.
 *
 * Security Notes:
 * ---------------
 * - Password verification and hashing logic is implemented inside
 *   `verifyCredentials()` in `models/login_model.php`.
 * - This page never stores the plain-text password.
 * - If authentication fails, the same generic error is displayed to avoid
 *   giving attackers clues.
 *
 * Dependencies:
 * -------------
 * - models/login_model.php: must define:
 *     - verifyCredentials(string $username, string $password): ?array
 *     - startSession(string $username, array $userData): void
 *
 * Session Variables:
 * ------------------
 * - `$_SESSION['validado']`: Boolean flag indicating whether the user is logged in.
 * - Other user details (permissions, roles, etc.) are stored by startSession().
 *
 * Author:
 * -------
 * VitalPBX Team
 * ============================================================================
 */

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

// Load login model with authentication functions
require_once __DIR__ . '/../models/login_model.php';

session_start();

// ============================================================================
// Redirect if the user is already logged in
// ============================================================================
if (isset($_SESSION['validado']) && $_SESSION['validado']) {
    header('Location: /index.php');
    exit;
}

$error = '';

// ============================================================================
// Handle POST: Login form submission
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['userpass'] ?? '';

    // Validate credentials
    $result = verifyCredentials($username, $password);

    if ($result === null) {
        // Invalid credentials
        $error = "Invalid username or password.";
    } elseif (isset($result['error_inhabilitado'])) {
        // Account disabled
        $error = $result['mensaje'] ?? 'This user is disabled.';
    } else {
        // Credentials valid: start session and redirect
        startSession($username, $result);
        header('Location: /index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Text-to-Speech Dashboard</title>
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
    </style>
</head>
<body>

<div class="card">
    <!-- Company logo -->
    <img src="/img/logo.png" alt="Logo" class="logo">
    <h4 class="text-center mb-4">Sign in</h4>

    <!-- Error message -->
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Login form -->
    <form method="post" action="login.php">
        <div class="mb-3">
            <label for="username" class="form-label">User</label>
            <input type="text" name="username" id="username"
                   class="form-control" placeholder="Enter your username" required autofocus>
        </div>

        <div class="mb-3">
            <label for="userpass" class="form-label">Password</label>
            <input type="password" name="userpass" id="userpass"
                   class="form-control" placeholder="Enter your password" required>
        </div>

        <div class="d-grid mb-2">
            <button type="submit" class="btn btn-primary">Log in</button>
        </div>

        <!-- Link to password recovery -->
        <div class="text-center">
            <a href="forgot_password.php" class="text-decoration-none">
                Forgot your password?
            </a>
        </div>
    </form>
</div>

<script src="/js/bootstrap.bundle.min.js"></script>
</body>
</html>

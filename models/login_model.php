<?php
/**
 * ============================================================================
 * File: models/login_model.php
 * ============================================================================
 * Purpose:
 * --------
 * This file manages user authentication, session lifecycle, and access control
 * for the Text-to-Speech management platform.
 *
 * Features:
 * ---------
 * - Verify user credentials and manage login sessions.
 * - Start, close, and validate user sessions.
 * - Enforce module access control based on permissions.
 *
 * Functions:
 * ----------
 * 1. verifyCredentials(string $username, string $password): ?array
 *    - Verifies username/password, validates status, and starts a session.
 *
 * 2. startSession(string $username, array $userdata): void
 *    - Stores user session data and marks user as authenticated.
 *
 * 3. closeSession(): void
 *    - Safely logs out the user, clears all session data, and redirects to login.
 *
 * 4. validateModuleAccess(int $position): void
 *    - Ensures the user has permission to access a specific module.
 *
 * Session data:
 * -------------
 * - $_SESSION['validado']  : boolean, authentication state
 * - $_SESSION['usuario']   : string, username
 * - $_SESSION['userdata']  : array, includes:
 *       * 'Permisos': 20-char string, permissions per module (S/N)
 *
 * Dependencies:
 * -------------
 * - config/database.php : for getPDO()
 * - utils/helpers.php   : for debug_log()
 *
 * Author:
 * -------
 * VitalPBX Team
 * ============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/helpers.php';

/**
 * Verifies user credentials and starts a session if valid.
 *
 * This function:
 *  1. Fetches the user from the database by username.
 *  2. Validates the password using bcrypt (password_verify)
 *     or direct comparison for legacy accounts.
 *  3. Checks the permissions string (20 chars).
 *  4. Denies access if the account is disabled (20th char = 'S').
 *  5. Starts a session and stores user data if successful.
 *
 * @param string $username Username
 * @param string $password Plain text password
 * @return array|null User data on success; null if login fails.
 */
function verifyCredentials(string $username, string $password): ?array
{
    try {
        $pdo = getPDO();

        // Lookup user by username
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $userdata = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userdata) {
            // Validate password (bcrypt hash or legacy plaintext)
            $validPassword = false;
            if (password_verify($password, $userdata['password'])) {
                $validPassword = true;
            } elseif ($userdata['password'] === $password) {
                $validPassword = true; // legacy direct match
            }

            if ($validPassword) {
                // Normalize permissions (20 chars)
                $permString = str_pad($userdata['permissions'] ?? '', 20, 'N');

                // Deny login if user is disabled (position 20)
                if ($permString[19] === 'S') {
                    debug_log("User '$username' is disabled", 'auth');
                    return [
                        'error_inhabilitado' => true,
                        'mensaje' => $userdata['message'] ?: 'This user is disabled. Contact the administrator.'
                    ];
                }

                // Add normalized permissions to userdata
                $userdata['Permisos'] = $permString;

                // Start session
                startSession($username, $userdata);

                debug_log("Login successful for '$username'", 'auth');
                return $userdata;
            }
        }

        // Login failed
        debug_log("Login failed for '$username'", 'auth');
        return null;

    } catch (PDOException $e) {
        debug_log("Database error for '$username': " . $e->getMessage(), 'auth');
        error_log('Error verifying credentials: ' . $e->getMessage());
        return null;
    }
}

/**
 * Starts a session and stores authenticated user data.
 *
 * Session variables:
 *  - $_SESSION['validado'] = true
 *  - $_SESSION['usuario'] = username
 *  - $_SESSION['userdata'] = user data (array)
 *
 * @param string $username Username of the authenticated user
 * @param array  $userdata Data retrieved from the database for this user
 */
function startSession(string $username, array $userdata): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['validado'] = true;
    $_SESSION['usuario'] = $username;
    $_SESSION['userdata'] = $userdata;
}

/**
 * Closes the current session and redirects to the login page.
 *
 * Steps:
 *  1. Logs session termination to debug log (if enabled).
 *  2. Calls session_unset() and session_destroy().
 *  3. Redirects the user to /login/login.php and exits.
 */
function closeSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $username = $_SESSION['usuario'] ?? 'unknown';
    debug_log("Session closed for user '$username'", 'auth');

    session_unset();
    session_destroy();
    header('Location: /login/login.php');
    exit;
}

/**
 * Validates whether the current user has access to a specific module.
 *
 * If the permission is not granted:
 *  - Returns HTTP 403 (Forbidden)
 *  - Outputs a JSON error message
 *  - Exits immediately
 *
 * @param int $position 1-based index of the permission (1 = first module)
 */
function validateModuleAccess(int $position): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['validado']) || !$_SESSION['validado'] || !isset($_SESSION['userdata']['Permisos'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Session not started or invalid']);
        exit;
    }

    // Convert to 0-based index
    $permissions = str_pad($_SESSION['userdata']['Permisos'], 20, 'N');
    $index = $position - 1;

    if ($permissions[$index] !== 'S') {
        debug_log("Access denied to module $position for user '{$_SESSION['usuario']}'", 'auth');

        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to access this module']);
        exit;
    }
}

<?php
/**
 * ============================================================================
 * File: login/logout.php
 * ============================================================================
 * Purpose:
 * --------
 * This script is responsible for securely logging out the currently logged-in
 * user. It does so by destroying the active session and clearing all session
 * data before redirecting the user back to the login page.
 *
 * Workflow:
 * ---------
 * 1. Include `login_model.php`, which contains the `cerrarSesion()` function.
 * 2. Call `cerrarSesion()`:
 *      - This function performs:
 *          * `session_unset()` - clears all session variables.
 *          * `session_destroy()` - fully destroys the session on the server.
 *          * Redirect to `login.php`.
 *
 * Why use this script?
 * --------------------
 * - Ensures that all session data (including permissions and user data)
 *   are securely removed when the user logs out.
 * - Prevents unauthorized access by invalidating the session ID.
 *
 * Security considerations:
 * ------------------------
 * - Always use `cerrarSesion()` instead of manually calling
 *   `session_destroy()` to maintain consistent behavior throughout the app.
 * - After logout, users must re-authenticate to access protected pages.
 *
 * Dependencies:
 * -------------
 * - models/login_model.php:
 *     Must define the `cerrarSesion()` function that handles session cleanup
 *     and the redirect to the login page.
 *
 * Author:
 * -------
 * VitalPBX Team
 * ============================================================================
 */

require_once __DIR__ . '/../models/login_model.php';

// ============================================================================
// Perform logout: destroy session and redirect to login page
// ============================================================================
closeSession();
<?php
/**
 * ============================================================================
 * File: layouts/menu_map.php
 * ============================================================================
 * Purpose:
 * --------
 * This file defines the **sidebar menu structure** of the Text-to-Speech
 * web application. It maps each permission index (0-based) to a menu entry.
 *
 * How it works:
 * -------------
 * 1. **Index as permission mapping:**
 *    The index in this array corresponds to the position in the
 *    permissions string stored in `$_SESSION['userdata']['Permisos']`.
 *    - Index 0 = Permission 1
 *    - Index 1 = Permission 2
 *    - and so on...
 *
 * 2. **Structure of each menu item:**
 *    Each menu entry is an associative array with:
 *      - 'label': Human-readable menu text (string)
 *      - 'icon' : Bootstrap Icons class (string, e.g. "bi-soundwave")
 *      - 'url'  : Relative URL for the module or page
 *
 * 3. **Dynamic Rendering:**
 *    In `layouts/layout.php`, this file is included and iterated.
 *    If the corresponding permission is enabled ('S'), the menu entry
 *    is displayed in the sidebar. Otherwise, it is hidden.
 *
 * Icons:
 * ------
 * The icons use Bootstrap Icons (https://icons.getbootstrap.com/).
 * Emoji icons are shown in comments for easy recognition.
 *
 * Example usage:
 * --------------
 *   $menuItems = require __DIR__ . '/menu_map.php';
 *   foreach ($menuItems as $index => $item) {
 *       if (permission[$index] === 'S') {
 *           // Render <a href="..."><i class="<?= $item['icon'] ?>"></i>...</a>
 *       }
 *   }
 *
 * Notes:
 * ------
 * - New modules must be added here so they appear in the sidebar.
 * - Ensure that permission indexes are synchronized with the user
 *   permissions string length (20 characters).
 *
 * Author:
 * -------
 * VitalPBX Team
 */

return [
    // Permission index 0 -> Dashboard
    0 => [
        'label' => 'Dashboard',
        'icon'  => 'bi-speedometer2', // 🏠 Dashboard icon
        'url'   => '/index.php'
    ],

    // Permission index 1 -> Text-to-Speech module
    1 => [
        'label' => 'Text-to-Speech',
        'icon'  => 'bi-soundwave',    // 🎙️ Soundwave icon
        'url'   => '/main.php?mod=text2speech'
    ],

    // Permission index 2 -> Voice Profiles module
    2 => [
        'label' => 'Voice Profiles',
        'icon'  => 'bi-robot',        // 🤖 Robot icon
        'url'   => '/main.php?mod=voiceprofiles'
    ],

    // Permission index 3 -> User management
    3 => [
        'label' => 'Users',
        'icon'  => 'bi-people-fill',  // 👥 Users icon
        'url'   => '/main.php?mod=users'
    ],

    // Permission index 4 -> Change Password
    4 => [
        'label' => 'Change Password',
        'icon'  => 'bi-key-fill',     // 🔑 Key icon
        'url'   => '/main.php?mod=changepassword'
    ],

    // Permission index 5 -> Email Settings module
    5 => [
        'label' => 'Email Settings',
        'icon'  => 'bi-envelope-fill', // 📧 Envelope icon
        'url'   => '/main.php?mod=email-settings'
    ],
];

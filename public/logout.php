<?php
/**
 * Solvex Report — Logout Action
 * 
 * Safely destroys user session and cookies,
 * and redirects to the login screen with a flash notice.
 */
define('SOLVEX_ROOT', dirname(__DIR__));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/auth.php';

init_session();
logout();

// Start fresh session just for the flash message
init_session();
flash('info', 'You have been logged out successfully.');
redirect(base_url('login.php'));

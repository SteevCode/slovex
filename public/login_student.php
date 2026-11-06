<?php
/**
 * Solvex Report — Direct Student Login Entry
 */
define('SOLVEX_ROOT', dirname(__DIR__));
require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
redirect(base_url('login.php?role=student'));

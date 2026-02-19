<?php
/**
 * KiTAcc - Entry Point
 * Redirects to dashboard or login
 */
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;

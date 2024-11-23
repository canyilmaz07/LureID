<?php
// logout.php
session_start();
require_once '../config/logger.php';

$logger = new Logger();

try {
    // Log the logout action
    if (isset($_SESSION['user_id'])) {
        $logger->log("User ID: {$_SESSION['user_id']} logged out successfully");
    }

    // Clear all session variables
    $_SESSION = array();

    // Destroy the session
    session_destroy();

    // Remove remember_token cookie if exists
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }

    // Remove theme cookie if user wants to reset theme
    if (isset($_GET['reset_theme'])) {
        setcookie('theme_preference', '', time() - 3600, '/');
    }

    // Redirect to login page
    header('Location: login.php');
    exit;
    
} catch (Exception $e) {
    $logger->log("Logout error: " . $e->getMessage(), 'ERROR');
    header('Location: error.php');
    exit;
}
?>
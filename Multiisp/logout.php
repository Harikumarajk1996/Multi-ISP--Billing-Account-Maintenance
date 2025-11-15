<?php
session_start(); // Start the session

// Destroy the session
session_unset();
session_destroy();

// Redirect to the home page or login page
header('Location: /');
exit;
?>

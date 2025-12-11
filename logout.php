<?php
session_start();

// destroy all sessions
session_unset();
session_destroy();

// redirect to login page
header("Location: login.php");
exit;
?>

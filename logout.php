<?php
session_start();
session_destroy();

// // Unset all session variables
// $_SESSION = array();



// Redirect to login page or home page
header("Location: login.php");
exit;
?>
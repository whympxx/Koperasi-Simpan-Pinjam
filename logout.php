<?php
require_once 'includes/init.php';

// Perform logout
$auth->logout();

// Redirect to login page with success message
header('Location: login.php?logout=1');
exit;
?>

<?php
require_once '../config.php';

// Destroy session
session_destroy();

// Redirect to admin login
header('Location: login.php');
exit;
?> 
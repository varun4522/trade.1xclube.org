<?php
header('Content-Type: application/json');
require_once '../config.php';

// Destroy session
session_destroy();

echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
?> 
<?php
$servername = "localhost";
$username = "chikenof_chick"; 
$password = "chikenof_chick";     
$dbname = "chikenof_chick"; 

// 1. Set PHP Timezone to India immediately
date_default_timezone_set('Asia/Kolkata');

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. Set MySQL Database Timezone to India (+5:30)
// Isse database ke andar NOW() function sahi Indian time dega
$conn->query("SET time_zone = '+05:30'");

?>
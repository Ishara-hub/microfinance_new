<?php
$host = "localhost";
$user = "root";
$pass = ""; // If you set one
$dbname = "microfinance_db";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("❌ Database Connection Failed: " . $conn->connect_error);
}
?>
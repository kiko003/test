<?php
// db.php - Database connection. Update credentials as needed.
$host     = "localhost";
$user = "jbhjqxyi_hyper";  // Change for production
$password = "Edwin02358322@";  // Change for production
$dbname = "jbhjqxyi_hyperbeam_app";


$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>

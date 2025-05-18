<?php
session_start();
include "db.php";

// Only an admin can terminate a virtual machine session.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || 
    !isset($_SESSION["is_admin"]) || !$_SESSION["is_admin"]) {
    header("Location: admin.php?error=Unauthorized access.");
    exit;
}

if (!isset($_GET['user_id'])) {
    header("Location: admin.php?error=User ID not specified.");
    exit;
}

$user_id = intval($_GET['user_id']);

// Retrieve the session data for the given user.
$stmt = $conn->prepare("SELECT session_data FROM hyperbeam_sessions WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows == 0) {
    $stmt->close();
    header("Location: admin.php?error=No active virtual machine session found for this user.");
    exit;
}
$stmt->bind_result($session_data);
$stmt->fetch();
$stmt->close();

$session = json_decode($session_data, true);
if (!isset($session['session_id'])) {
    header("Location: admin.php?error=Invalid session data.");
    exit;
}

$session_id = $session['session_id'];

// Terminate the session via the Hyperbeam API.
$api_key = "sk_live_b0ju1qsONugJhZwETBXv7V-YoBGA7fZXkqesOYNyYJ4";
$terminate_url = "https://engine.hyperbeam.com/v0/vm/" . urlencode($session_id);

$ch = curl_init($terminate_url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer $api_key",
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200 || $http_code == 204) {
    // Remove the session record from the database.
    $stmt = $conn->prepare("DELETE FROM hyperbeam_sessions WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin.php?message=Virtual machine session terminated successfully for user ID $user_id");
    exit;
} else {
    header("Location: admin.php?error=Failed to terminate virtual machine session. API response code: $http_code");
    exit;
}
?>
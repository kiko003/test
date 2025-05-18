<?php
session_start();
include "db.php";

// Verify that the user is logged in.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    die(json_encode(["error" => "Unauthorized access. Please log in."]));
}

// Check if user is blocked from creating/requesting a VM
$stmt = $conn->prepare("SELECT vm_blocked FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION["user_id"]);
$stmt->execute();
$stmt->bind_result($vm_blocked);
$stmt->fetch();
$stmt->close();
if ($vm_blocked == 1) {
    echo "<p style='color:red;'>You are blocked from requesting new virtual machines. Please contact an administrator.</p>";
    exit;
}

$user_id = (int) $_SESSION["user_id"];

// Check for an existing Hyperbeam session.
$query = "SELECT session_data FROM hyperbeam_sessions WHERE user_id = $user_id LIMIT 1";
$result = $conn->query($query);

$existing_session_id = null;
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $session = json_decode($row["session_data"], true);
    if (isset($session['id'])) {
        $existing_session_id = $session['id'];
    } elseif (isset($session['session_id'])) { // handle your own structure
        $existing_session_id = $session['session_id'];
    }
}

// Call Hyperbeam API to get all active sessions
$api_key = "sk_live_b0ju1qsONugJhZwETBXv7V-YoBGA7fZXkqesOYNyYJ4";
$get_url = "https://engine.hyperbeam.com/v0/vm";
$ch = curl_init($get_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $api_key"
]);
$get_response = curl_exec($ch);
curl_close($ch);

$active_hb_ids = [];
if ($get_response) {
    $data = json_decode($get_response, true);
    if (!empty($data['results'])) {
        foreach ($data['results'] as $hbSession) {
            $active_hb_ids[] = $hbSession['id'];
        }
    }
}

// If the session is still active, return it
if ($existing_session_id && in_array($existing_session_id, $active_hb_ids)) {
    header("Content-Type: application/json");
    echo $row["session_data"];
    exit;
}

// No valid/active session exists; create a new one.
$url     = "https://engine.hyperbeam.com/v0/vm";

// Set role-based timeout settings.
if (isset($_SESSION["is_admin"]) && $_SESSION["is_admin"]) {
    // Admin machine: 3600s inactive, 3600s offline, 60s warning.
    $timeout = [
        "absolute" => null,
        "inactive" => 3600,
        "offline"  => 3600,
        "warning"  => 60
    ];
} else {
    // Regular user machine: 1800s inactive, 1800s offline, 60s warning.
    $timeout = [
        "absolute" => null,
        "inactive" => 1800,
        "offline"  => 1800,
        "warning"  => 60
    ];
}

// Define session parameters including the timeout settings.
$data = json_encode([
    "timeout"  => $timeout
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer $api_key",
]);

$response = curl_exec($ch);
curl_close($ch);

if (!$response) {
    header("Content-Type: application/json");
    echo json_encode(["error" => "Failed to create a virtual machine session."]);
    exit;
}

// Remove any old session for this user
$conn->query("DELETE FROM hyperbeam_sessions WHERE user_id = $user_id");

// Store the new session in the database.
$stmt = $conn->prepare("INSERT INTO hyperbeam_sessions (user_id, session_data) VALUES (?, ?)");
$stmt->bind_param("is", $user_id, $response);
$stmt->execute();
$stmt->close();

header("Content-Type: application/json");
echo $response;
?>
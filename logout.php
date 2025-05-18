<?php
session_start();
include "db.php";

// Only proceed if the user is logged in
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    $user_id = (int)$_SESSION["user_id"];

    // Check for an active VM session
    $query = "SELECT session_data FROM hyperbeam_sessions WHERE user_id = $user_id LIMIT 1";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $session = json_decode($row["session_data"], true);

        // Terminate the VM via the Hyperbeam API
        if (isset($session['session_id'])) {
            $api_key = "sk_live_b0ju1qsONugJhZwETBXv7V-YoBGA7fZXkqesOYNyYJ4";
            $terminate_url = "https://engine.hyperbeam.com/v0/vm/" . urlencode($session['session_id']);
            $ch = curl_init($terminate_url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Authorization: Bearer $api_key",
            ]);
            curl_exec($ch);
            curl_close($ch);
        }

        // Delete the VM session from the database
        $conn->query("DELETE FROM hyperbeam_sessions WHERE user_id = $user_id");
    }
}

// Destroy PHP session
session_unset();
session_destroy();
header("Location: login.php");
exit;
?>
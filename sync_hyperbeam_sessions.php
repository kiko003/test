<?php
include "db.php";

// Hyperbeam API key and endpoint
$api_key = "sk_live_b0ju1qsONugJhZwETBXv7V-YoBGA7fZXkqesOYNyYJ4";
$url = "https://engine.hyperbeam.com/v0/vm";

// 1. Fetch all active Hyperbeam sessions
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $api_key"
]);
$response = curl_exec($ch);
curl_close($ch);

if (!$response) {
    die("Failed to fetch active sessions from Hyperbeam API");
}
$data = json_decode($response, true);
$active_hb_ids = [];
if (!empty($data['results'])) {
    foreach ($data['results'] as $hbSession) {
        $active_hb_ids[] = $hbSession['id'];
    }
}

// 2. Get all sessions from hyperbeam_sessions in your DB
$result = $conn->query("SELECT user_id, session_data FROM hyperbeam_sessions");
while ($row = $result->fetch_assoc()) {
    $session_data = json_decode($row['session_data'], true);
    if (isset($session_data['session_id'])) {
        $session_id = $session_data['session_id'];
        $user_id = $row['user_id'];

        // 3. If not in active hyperbeam sessions, log out user and remove session
        if (!in_array($session_id, $active_hb_ids)) {
            // (A) Remove hyperbeam_sessions row
            $stmt = $conn->prepare("DELETE FROM hyperbeam_sessions WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();

            // (B) Remove PHP session file and clear session_id in users table
            $stmt = $conn->prepare("SELECT session_id FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->bind_result($php_session_id);
            $stmt->fetch();
            $stmt->close();

            if (!empty($php_session_id)) {
                $session_file = session_save_path() . "/sess_" . $php_session_id;
                if (file_exists($session_file)) {
                    unlink($session_file);
                }
                $stmt = $conn->prepare("UPDATE users SET session_id = NULL WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();
            }

            // (C) Optionally, log or notify admin
            // echo "Logged out user_id $user_id due to inactive Hyperbeam VM\n";
        }
        // else: session is still active, do nothing
    }
}
echo "Sync complete.";
?>
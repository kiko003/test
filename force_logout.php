<?php
session_start();
require_once "db.php";

// Only allow admins
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    exit("Forbidden: Admins only.");
}

if (!isset($_POST['user_id'])) {
    http_response_code(400);
    exit("Missing user_id parameter.");
}

$user_id = (int)$_POST['user_id'];

// 1. Remove PHP session file
$stmt = $conn->prepare("SELECT session_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($session_id);
$stmt->fetch();
$stmt->close();

if ($session_id) {
    $session_file = session_save_path() . "/sess_" . $session_id;
    if (file_exists($session_file)) {
        unlink($session_file);
    }
    $stmt = $conn->prepare("UPDATE users SET session_id=NULL WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

// 2. Check if the user has a VM session
$stmt = $conn->prepare("SELECT session_data FROM hyperbeam_sessions WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($session_data_json);
$stmt->fetch();
$stmt->close();

if ($session_data_json) {
    $data = json_decode($session_data_json, true);
    if (isset($data['session_id'])) {
        $hb_session_id = $data['session_id'];

        // 3. TERMINATE THE VM SESSION ON HYPERBEAM
        $api_key = 'sk_live_b0ju1qsONugJhZwETBXv7V-YoBGA7fZXkqesOYNyYJ4'; // <--- Put your real Hyperbeam API key here
        $ch = curl_init("https://engine.hyperbeam.com/v0/vm/$hb_session_id");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $api_key"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        // (Optional) You can log or check $response for success/failure
    }

    // 4. Remove the session row from hyperbeam_sessions
    $stmt = $conn->prepare("DELETE FROM hyperbeam_sessions WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

// Redirect back to the admin panel with a success message
header("Location: admin.php?message=" . urlencode("User forcibly logged out and VM terminated (if any)."));
exit;
?>
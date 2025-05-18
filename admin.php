<?php
session_start();
include "db.php";

// Ensure the user is logged in *and* is an admin
if (
    !isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true ||
    !isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true
) {
    header("Location: login.php");
    exit;
}

// --- FETCH ACTIVE HYPERBEAM SESSIONS ---
$api_key = "sk_live_b0ju1qsONugJhZwETBXv7V-YoBGA7fZXkqesOYNyYJ4";
$url = "https://engine.hyperbeam.com/v0/vm";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $api_key"
]);
$response = curl_exec($ch);
curl_close($ch);

$active_hb_ids = [];
if ($response) {
    $data = json_decode($response, true);
    if (!empty($data['results'])) {
        foreach ($data['results'] as $hbSession) {
            $active_hb_ids[] = $hbSession['id'];
        }
    }
}

// --- GET VM SESSIONS FROM DB ---
$userVMSessionId = [];
$resultVM = $conn->query("SELECT user_id, session_data FROM hyperbeam_sessions");
while ($rowVM = $resultVM->fetch_assoc()) {
    $session_data = json_decode($rowVM['session_data'], true);
    if (isset($session_data['session_id'])) {
        $userVMSessionId[$rowVM['user_id']] = $session_data['session_id'];
    }
}

// --- FOR PHP SESSION LOGOUT BUTTON ---
$userPHPSessions = [];
$resultPHP = $conn->query("SELECT id, session_id FROM users");
while ($rowPHP = $resultPHP->fetch_assoc()) {
    if (!empty($rowPHP['session_id'])) {
        $userPHPSessions[$rowPHP['id']] = true;
    }
}

$message = isset($_GET['message']) ? $_GET['message'] : "";
$error = isset($_GET['error']) ? $_GET['error'] : "";

// Process Account Deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $deleteId = intval($_GET['id']);
    if ($deleteId == $_SESSION["user_id"]) {
        $error = "You cannot delete yourself.";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $deleteId);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $message = "User deleted successfully.";
        } else {
            $error = "Failed to delete user.";
        }
        $stmt->close();
    }
}

// Process User Access Approval, Denial, or Toggle
if (
    isset($_GET['action']) &&
    (
        $_GET['action'] == 'approve' ||
        $_GET['action'] == 'deny' ||
        $_GET['action'] == 'toggle_approve'
    ) &&
    isset($_GET['id'])
) {
    $userId = intval($_GET['id']);
    if ($_GET['action'] === 'toggle_approve') {
        $stmt = $conn->prepare("SELECT approved FROM users WHERE id=?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($approved);
        $stmt->fetch();
        $stmt->close();
        $newStatus = ($approved == 1) ? -1 : 1;
        $stmt = $conn->prepare("UPDATE users SET approved=? WHERE id=?");
        $stmt->bind_param("ii", $newStatus, $userId);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $message = "User status changed successfully.";
        } else {
            $error = "Failed to change user status.";
        }
        $stmt->close();
    } else {
        $status = ($_GET['action'] == 'approve') ? 1 : -1;
        $stmt = $conn->prepare("UPDATE users SET approved = ? WHERE id = ?");
        if ($stmt === false) {
            $error = "Error preparing statement: " . $conn->error;
        } else {
            $stmt->bind_param("ii", $status, $userId);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $msgText = ($_GET['action'] == 'approve') ? "approved" : "denied";
                $message = "User " . $msgText . " successfully.";
            } else {
                $error = "Failed to update user approval status.";
            }
            $stmt->close();
        }
    }
}

// Handle VM Block/Unblock action
if (isset($_GET['action']) && ($_GET['action'] == 'block_vm' || $_GET['action'] == 'unblock_vm') && isset($_GET['id'])) {
    $userId = intval($_GET['id']);
    $blockValue = ($_GET['action'] == 'block_vm') ? 1 : 0;
    $stmt = $conn->prepare("UPDATE users SET vm_blocked = ? WHERE id = ?");
    $stmt->bind_param("ii", $blockValue, $userId);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $msgText = ($blockValue) ? "blocked from" : "unblocked for";
        $message = "User successfully $msgText requesting new virtual machines.";
    } else {
        $error = "Failed to update VM block status.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>WFM!</title>
  <link rel="stylesheet" href="styles.css?v=11">
  <style>
    .admin-panel-container {
        background: #222;
        border-radius: 18px;
        box-shadow: 0 4px 32px #000a, 0 1.5px 6px #0006;
        padding: 32px 24px 32px 24px;
        margin: 40px auto 0 auto;
        max-width: 1400px;
        width: 97vw;
    }
    .user-list-table {
        width: 100%;
        border-collapse: collapse;
        background: #181818;
        color: #fff;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 8px #000a;
        max-width: 100%;
    }
    .user-list-table th, .user-list-table td {
        padding: 12px 14px;
        text-align: left;
        border-bottom: 1px solid #222;
    }
    .user-list-table th {
        background: #1a1a1a;
        color: #fff;
        font-weight: bold;
        letter-spacing: 0.5px;
    }
    .user-list-table tr:last-child td {
        border-bottom: none;
    }
    .user-list-table tr:hover {
        background: #232323;
    }
    .action-btn {
        display: inline-block;
        margin-right: 6px;
        padding: 5px 12px;
        border: none;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        color: #fff;
        text-decoration: none;
        cursor: pointer;
        transition: background 0.15s, opacity 0.15s;
        vertical-align: middle;
        box-shadow: 0 2px 6px #0002;
        min-width: 80px;
        text-align: center;
    }
    .action-btn:last-child {
        margin-right: 0;
    }
    .action-btn.disable-btn { background: #9a7b38; }
    .action-btn.delete-btn { background: #e74c3c; }
    .action-btn.terminate-btn { background: #ff8800; }
    .action-btn.block-vm-btn { background: #9b59b6; }
    .action-btn.approve-btn { background: #28a745; }
    .action-btn.force-logout-btn { background: #2980f3; }
    .action-btn.force-logout-btn:hover { background: #1451a3; }
    .action-btn.terminate-btn.disabled,
    .action-btn.force-logout-btn.disabled {
        background: #444 !important;
        color: #bbb !important;
        cursor: not-allowed !important;
        pointer-events: none;
        opacity: .6;
    }
    .nowrap { white-space:nowrap; }
    .message, .error {
        margin: 24px auto 0 auto;
        width: 1200px;
        max-width: 90vw;
        padding: 14px 22px;
        border-radius: 8px;
        font-size: 16px;
        text-align: center;
        font-weight: 500;
    }
    .message { background: #243526; color: #66ff7f; border: 1px solid #37944e; }
    .error { background: #362424; color: #ff6868; border: 1px solid #a33d3d; }
    h2 {
        text-align: center;
        font-size: 2rem;
        font-weight: bold;
        margin-bottom: 28px;
        margin-top: 0;
    }
    .actions-row {
        display: flex;
        flex-wrap: nowrap;
        gap: 8px;
        overflow-x: auto;
    }
    .status-yes {
        color: #00e676 !important;
        font-weight: bold;
    }
    .status-no {
        color: #ff5252 !important;
        font-weight: bold;
    }
  </style>
</head>
<body>
  <header id="topHeader">
      <span>Welcome, <?php echo htmlspecialchars($_SESSION["username"]); ?>!</span>
      <nav>
         <a href="index.php">Main Page</a>
         <?php if(isset($_SESSION["is_admin"]) && $_SESSION["is_admin"]) { ?>
            <a href="admin.php">Admin Panel</a>
         <?php } ?>
         <a href="logout.php">Logout</a>
      </nav>
  </header>
  <?php if (!empty($message)) { echo "<div class='message'>" . htmlspecialchars($message) . "</div>"; }
        if (!empty($error)) { echo "<div class='error'>" . htmlspecialchars($error) . "</div>"; } ?>
  <div class="admin-panel-container">
    <h2>User List</h2>
    <table class="user-list-table">
      <tr>
        <th>ID</th>
        <th>Username</th>
        <th>Approved</th>
        <th>Admin</th>
        <th>VM Blocked</th>
        <th>Session Active</th>
        <th>Actions</th>
      </tr>
      <?php
      $result = $conn->query("SELECT id, username, approved, is_admin, vm_blocked FROM users");
      while ($row = $result->fetch_assoc()) {
          $uid = $row['id'];
          $session_id = isset($userVMSessionId[$uid]) ? $userVMSessionId[$uid] : null;
          $is_active = ($session_id && in_array($session_id, $active_hb_ids));
          echo "<tr>";
          echo "<td>" . htmlspecialchars($uid) . "</td>";
          echo "<td>" . htmlspecialchars($row['username']) . "</td>";

          // Approved column
          echo "<td><span class='" . ($row['approved'] == 1 ? 'status-yes' : 'status-no') . "'>" . ($row['approved'] == 1 ? "Yes" : "No") . "</span></td>";

          // Admin column
          echo "<td><span class='" . ($row['is_admin'] == 1 ? 'status-yes' : 'status-no') . "'>" . ($row['is_admin'] == 1 ? "Yes" : "No") . "</span></td>";

          // VM Blocked column
          echo "<td><span class='" . ($row['vm_blocked'] == 1 ? 'status-yes' : 'status-no') . "'>" . ($row['vm_blocked'] == 1 ? "Yes" : "No") . "</span></td>";

          // Session active column
          echo "<td><span class='" . ($is_active ? 'status-yes' : 'status-no') . "'>" . ($is_active ? "Yes" : "No") . "</span></td>";

          // Actions
          echo "<td>";
          if ($uid != $_SESSION["user_id"]) {
              echo "<div class='actions-row'>";
              // Approve/Deny Toggle
              $toggleText = ($row['approved'] == 1) ? "Deny" : "Approve";
              $toggleTitle = ($row['approved'] == 1) ? "Deny this user" : "Approve this user";
              echo "<a class='action-btn disable-btn' href='admin.php?action=toggle_approve&id=" . $uid . "' title='" . $toggleTitle . "' onclick='return confirm(\"Are you sure you want to " . strtolower($toggleText) . " this user?\");'>$toggleText</a>";

              // DELETE BUTTON
              echo "<a class='action-btn delete-btn' href='admin.php?action=delete&id=" . $uid . "' onclick='return confirm(\"Delete user " . htmlspecialchars($row['username']) . "?\");'>Delete</a>";

              // Terminate VM Button
              if ($session_id && $is_active) {
                  echo "<a class='action-btn terminate-btn' href='terminate_vm.php?user_id=$uid' onclick='return confirm(\"Terminate the VM session for " . htmlspecialchars($row['username']) . "?\");'>Terminate VM</a>";
              } else {
                  echo "<span class='action-btn terminate-btn disabled'>Terminate VM</span>";
              }

              // Block/Unblock VM
              if ($row['vm_blocked'] == 0) {
                  echo "<a class='action-btn block-vm-btn' href='admin.php?action=block_vm&id=" . $uid . "' onclick='return confirm(\"Block this user from requesting new VMs?\");'>Block VM</a>";
              } else {
                  echo "<a class='action-btn approve-btn' href='admin.php?action=unblock_vm&id=" . $uid . "' onclick='return confirm(\"Unblock this user for VM requests?\");'>Unblock VM</a>";
              }

              // Force Logout Button (active only if user has PHP session)
              if (isset($userPHPSessions[$uid])) {
                  echo "<form method='POST' action='force_logout.php' style='display:inline; margin:0; padding:0; vertical-align:middle;' onsubmit='return confirm(\"Force logout this user? This will also terminate their VM session if active.\");'>
                      <input type='hidden' name='user_id' value='" . $uid . "'>
                      <button type='submit' class='action-btn force-logout-btn'>Force Logout</button>
                  </form>";
              } else {
                  echo "<span class='action-btn force-logout-btn disabled' title='User is not logged in.'>Force Logout</span>";
              }

              echo "</div>";
          } else {
              echo "N/A";
          }
          echo "</td>";
          echo "</tr>";
      }
      ?>
    </table>
  </div>
</body>
</html>
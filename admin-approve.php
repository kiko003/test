<?php
session_start();
include "db.php";

// Ensure the user is logged in *and* is an admin.
if (
    !isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true ||
    !isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true
) {
    header("Location: login.php");
    exit;
}

$message = "";
$error = "";

// ... (rest of your PHP logic remains unchanged) ...
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Panel - Manage Users</title>
  <link rel="stylesheet" href="styles.css">
  <style>
      table {
          width: 100%;
          border-collapse: collapse;
          margin-top: 20px;
      }
      table, th, td {
          border: 1px solid #555;
      }
      th, td {
          padding: 10px;
          text-align: left;
      }
      th {
          background-color: #222;
      }
      td {
          background-color: #111;
      }
      /* Action button styling */
      a.action-btn {
          margin-right: 5px;
          padding: 5px 10px;
          border-radius: 5px;
          text-decoration: none;
          color: #fff;
      }
      a.delete-btn { background-color: #dc3545; }
      a.terminate-btn { background-color: #ff4500; }
      a.approve-btn { background-color: #28a745; }
      a.deny-btn { background-color: #dc3545; }
  </style>
</head>
<body>
  <!-- Auto-Hiding Header -->
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
  <main>
      <?php
         if (!empty($message)) {
             echo "<p class='message'>" . htmlspecialchars($message) . "</p>";
         }
         if (!empty($error)) {
             echo "<p class='error'>" . htmlspecialchars($error) . "</p>";
         }
      ?>
      <!-- ... (rest of your HTML table and admin logic remains unchanged) ... -->
  </main>
</body>
</html>
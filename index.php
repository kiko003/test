<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
}
$isAdmin = isset($_SESSION["is_admin"]) && $_SESSION["is_admin"];
$username = isset($_SESSION["username"]) ? $_SESSION["username"] : "";
$user_id = isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : 0;

// --- Check user approval and VM block status for VM request button ---
require_once "db.php";
$stmt = $conn->prepare("SELECT approved, vm_blocked FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($approved, $vm_blocked);
$stmt->fetch();
$stmt->close();
$isApproved = ($approved == 1);
$isBlocked = ($vm_blocked == 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <title>Welcome, <?php echo htmlspecialchars($username); ?>!</title>
   <link rel="stylesheet" href="styles.css?v=5">
   <style>
      #bottomHeader {
        transition: bottom 0.3s, opacity 0.3s;
        z-index: 101;
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        background: #222;
        color: #fff;
        padding: 16px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-top: 2px solid #333;
      }
      #bottomHeader.hide {
        bottom: -70px;
        opacity: 0;
        pointer-events: none;
      }
      #footerToggleBtn {
        position: fixed;
        bottom: 12px;
        left: 8px;
        z-index: 102;
        width: 36px;
        height: 36px;
        background: #232323;
        color: #fff;
        border: none;
        border-radius: 50%;
        box-shadow: 0 2px 8px #0005;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s;
        cursor: pointer;
        outline: none;
      }
      #footerToggleBtn:hover {
        background: #444;
      }
      #footerToggleBtn svg {
        transition: transform 0.3s;
      }
      #footerToggleBtn.collapsed svg {
        transform: rotate(180deg);
      }
      body {
        padding-bottom: 80px; /* Space for fixed bottom bar */
      }
      #dashboardMain {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-start;
        min-height: 70vh;
        padding: 30px 0 0 0;
      }
      /* --- Hyperbeam Fullscreen Styles --- */
      #hyperbeamContainer {
        position: fixed;
        left: 0;
        top: 0;
        right: 0;
        bottom: 0;
        width: 100vw;
        height: 100vh;
        z-index: 10000;
        background: #111;
        display: none;
        flex-direction: column;
        align-items: stretch;
        justify-content: stretch;
        margin: 0;
        padding: 0;
      }
      #hyperbeamContainer.active {
        display: flex;
      }
      #virtualComputerDiv {
        width: 100%;
        height: 100%;
        min-height: 0;
        background: #111;
        border-radius: 0;
        box-shadow: none;
        flex: 1 1 auto;
      }
      @media (max-width: 600px) {
        #bottomHeader { padding: 12px 6px; font-size: 0.98em; }
        #footerToggleBtn { bottom: 8px; left: 4px; width: 32px; height: 32px; }
        body { padding-bottom: 66px; }
      }
      /* --- Custom box for blocked/approval error --- */
      .blocked-error, .approval-error {
        width: 100%;
        text-align: center;
        background: #420f0f;
        color: #fff;
        font-weight: bold;
        padding: 14px 0;
        border-radius: 8px;
        margin-bottom: 0;
        margin-top: 10px;
        font-size: 1.07em;
        letter-spacing: 0.5px;
      }
      .blocked-error .blocked-text { color: #ff6969; }
   </style>
   <script>
      // Hide/show footer bar logic
      document.addEventListener("DOMContentLoaded", function () {
         const footer = document.getElementById('bottomHeader');
         const btn = document.getElementById('footerToggleBtn');
         let footerVisible = true;
         btn.onclick = function() {
           footerVisible = !footerVisible;
           footer.classList.toggle('hide', !footerVisible);
           btn.classList.toggle('collapsed', !footerVisible);
         };
      });

      // ----- Auto-Logout Timer -----
      var sessionTimeoutSeconds = <?php echo $isAdmin ? 3600 : 1800; ?>;
      var logoutTimer;
      function resetLogoutTimer() {
         if (logoutTimer) { clearTimeout(logoutTimer); }
         logoutTimer = setTimeout(function(){
            window.location.href = "logout.php";
         }, sessionTimeoutSeconds * 1000);
      }
      document.addEventListener("mousemove", resetLogoutTimer);
      document.addEventListener("keypress", resetLogoutTimer);
      resetLogoutTimer();

      // VM Fullscreen open logic (with session check)
      document.addEventListener("DOMContentLoaded", function () {
         var showVmBtn = document.getElementById('showVmBtn');
         var hyperbeamContainer = document.getElementById('hyperbeamContainer');
         var welcomeBox = document.getElementById('welcomeBox');
         if (showVmBtn) {
            showVmBtn.addEventListener('click', async function() {
               // SESSION CHECK BEFORE SHOWING VM
               try {
                   const res = await fetch('session-check.php', { credentials: 'same-origin' });
                   const data = await res.json();
                   if (!data.valid) {
                       alert('Your session has expired. Please log in again.');
                       window.location = 'login.php';
                       return;
                   }
                   // Proceed to show VM if session is valid
                   welcomeBox.style.display = 'none';
                   hyperbeamContainer.classList.add('active');
                   if (window.initVirtualComputer) window.initVirtualComputer();
               } catch (error) {
                   alert('Session check failed. Please try again.');
                   window.location = 'login.php';
               }
            });
         }
      });
   </script>
   <script type="module">
      import Hyperbeam from "https://unpkg.com/@hyperbeam/web@latest/dist/index.js";
      window.initVirtualComputer = async function() {
         try {
             const response = await fetch("computer.php");
             const data = await response.json();
             const hyperbeamInstance = await Hyperbeam(
                document.getElementById("virtualComputerDiv"),
                data.embed_url
             );
             hyperbeamInstance.onReady(() => {
                console.log("Hyperbeam session is ready!");
             });
         } catch (error) {
             console.error("Error initializing Hyperbeam session:", error);
         }
      }
   </script>
</head>
<body>
   <main id="dashboardMain">
      <div class="form-box" id="welcomeBox">
         <?php if ($isAdmin): ?>
            <div class="admin-title">HEY ADMIN!</div>
            <div class="welcome-msg">
               Hello, <span class="admin-highlight"><?php echo strtoupper(htmlspecialchars($username)); ?></span>!
            </div>
            <div class="desc">
               You are logged in as <span class="admin-highlight">admin</span>.<br>
               Use the Admin Panel to manage users and VMs.
            </div>
         <?php else: ?>
            <div class="user-title">Welcome!</div>
            <div class="welcome-msg">
               Hello, <span class="admin-highlight"><?php echo htmlspecialchars($username); ?></span>!
            </div>
            <div class="desc">
               You are logged in.<br>
               Use the menu below to navigate.
            </div>
         <?php endif; ?>
         <?php if (!$isApproved): ?>
            <div class="approval-error">You must be approved by an admin before requesting a machine.</div>
         <?php elseif ($isBlocked): ?>
            <div class="blocked-error">
                You are <span class="blocked-text">blocked</span> from requesting virtual machines.<br>
                Please contact an administrator.
            </div>
         <?php else: ?>
            <button id="showVmBtn" class="action-btn approve-btn">Show My Virtual Machine</button>
         <?php endif; ?>
      </div>
   </main>
   <!-- Hyperbeam VM Fullscreen Container (outside main) -->
   <div id="hyperbeamContainer">
      <div id="virtualComputerDiv"></div>
   </div>
   <button id="footerToggleBtn" title="Hide/Show Footer" aria-label="Hide/Show Footer">
      <svg width="20" height="20" viewBox="0 0 20 20">
        <polyline points="7,12 10,9 13,12" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
      </svg>
   </button>
   <footer>
      <div id="bottomHeader">
         <span>
            Welcome, <?php echo htmlspecialchars($username); ?>!
         </span>
         <nav>
            <a href="index.php">Main Page</a>
            <?php if($isAdmin) { ?>
               <a href="admin.php">Admin Panel</a>
            <?php } ?>
            <a href="logout.php">Logout</a>
         </nav>
      </div>
   </footer>
</body>
</html>
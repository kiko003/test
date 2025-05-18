<?php
session_start();
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("Location: index.php");
    exit;
}
$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include "db.php";
    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT id, username, password, approved, is_admin FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($id, $user, $hashed_password, $approved, $is_admin);
        $stmt->fetch();
        if (password_verify($password, $hashed_password)) {
            if ($approved == 1) {
                $_SESSION["loggedin"] = true;
                $_SESSION["user_id"] = $id;
                $_SESSION["username"] = $user;
                $_SESSION["is_admin"] = ($is_admin == 1);

                // Store this session_id in the users table for force logout feature
                $session_id = session_id();
                $stmt2 = $conn->prepare("UPDATE users SET session_id = ? WHERE id = ?");
                $stmt2->bind_param("si", $session_id, $id);
                $stmt2->execute();
                $stmt2->close();

                header("Location: index.php");
                exit;
            } else {
                $error = "Your account is not approved.";
            }
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Invalid username or password.";
    }
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="styles.css?v=11">
</head>
<body>
<main>
    <div class="form-box">
        <h2>Login</h2>
        <?php if (!empty($error)) { echo "<p class='error'>$error</p>"; } ?>
        <form action="login.php" method="post" autocomplete="off">
            <label for="username">Username</label>
            <input type="text" name="username" id="username" required autofocus>
            <label for="password">Password</label>
            <input type="password" name="password" id="password" required>
            <input type="submit" value="Login" class="login-btn">
        </form>
        <div class="form-footer">
            Don't have an account?
            <a href="register.php">Register</a>
        </div>
    </div>
</main>
</body>
</html>
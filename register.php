<?php
session_start();
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("Location: index.php");
    exit;
}
$error = "";
$success = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include "db.php";
    $username = trim($_POST["username"]);
    $password = $_POST["password"];
    $confirm = $_POST["confirm_password"];

    if (strlen($username) < 3) {
        $error = "Username must be at least 3 characters.";
    } elseif (strlen($password) < 5) {
        $error = "Password must be at least 5 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = "Username already taken.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, approved) VALUES (?, ?, 0)");
            $stmt->bind_param("ss", $username, $hash);
            if ($stmt->execute()) {
                $success = "Registration successful! Awaiting approval.";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <link rel="stylesheet" href="styles.css?v=11">
</head>
<body>
<main>
    <div class="form-box">
        <h2>Register</h2>
        <?php
        if (!empty($error)) { echo "<p class='error'>$error</p>"; }
        if (!empty($success)) { echo "<p class='message'>$success</p>"; }
        ?>
        <form action="register.php" method="post" autocomplete="off">
            <label for="username">Username</label>
            <input type="text" name="username" id="username" required>
            <label for="password">Password</label>
            <input type="password" name="password" id="password" required>
            <label for="confirm_password">Confirm Password</label>
            <input type="password" name="confirm_password" id="confirm_password" required>
            <input type="submit" value="Register" class="register-btn">
        </form>
        <div class="form-footer">
            Already have an account?
            <a href="login.php">Login</a>
        </div>
    </div>
</main>
</body>
</html>
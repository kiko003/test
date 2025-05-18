<?php
include "db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = password_hash(trim($_POST["password"]), PASSWORD_DEFAULT);

    // Insert the new user with approved flag set to 0 (pending)
    $stmt = $conn->prepare("INSERT INTO users (username, password, approved) VALUES (?, ?, 0)");
    $stmt->bind_param("ss", $username, $password);

    if ($stmt->execute()) {
        $message = "Registration successful. Your account is pending approval. Please wait for an admin to approve it.";
    } else {
        $message = "Error: " . $stmt->error;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - Hyperbeam Virtual Computer</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body class="register-page">
  <div class="register-container">
    <h2>Register</h2>
    <?php if (!empty($message)): ?>
      <p class="message"><?php echo $message; ?></p>
    <?php endif; ?>
    <form action="registration.php" method="post">
      <input type="text" name="username" placeholder="Username" required>
      <input type="password" name="password" placeholder="Password" required>
      <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="login.php">Login here</a></p>
  </div>
</body>
</html>
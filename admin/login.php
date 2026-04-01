<?php
session_start();
include '../db.php';

if (isset($_SESSION['admin'])) {
  // Already logged in, redirect to dashboard
  header("Location: admin_reports.php");
  exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $username = trim($_POST['username']);
  $password = trim($_POST['password']);

  // Prepare statement to prevent SQL injection
  $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();

  $result = $stmt->get_result();
  $user = $result->fetch_assoc();

  if ($user && password_verify($password, $user['password']) && $user['role'] === 'admin') {
    $_SESSION['admin'] = $user['username'];
    header("Location: admin_reports.php");
    exit;
  } else {
    $error = "Invalid username or password";
  }

  $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Admin Login - Botika Mo</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f4f6f8;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    form {
      background: white;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 0 10px #aaa;
      width: 300px;
    }

    input {
      width: 100%;
      padding: 8px;
      margin: 8px 0;
    }

    button {
      background: #0d6efd;
      color: white;
      border: none;
      padding: 10px;
      width: 100%;
      border-radius: 4px;
      cursor: pointer;
    }

    button:hover {
      background: #084cd0;
    }

    .error {
      color: red;
      font-size: 0.9em;
    }
  </style>
</head>

<body class="bg-light">

  <div class="container vh-100 d-flex justify-content-center align-items-center">
    <div class="card shadow p-4" style="width: 400px;">
      <h4 class="text-center mb-4">Admin Login</h4>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>

        <button class="btn btn-primary w-100">Login</button>
      </form>
    </div>
  </div>

</body>

</html>
<?php
session_start();
include 'db.php';

if(isset($_POST['login']))
{
    $username = $_POST['username'];
    $password = $_POST['password'];

    // STEP 1: Get user by username only
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();

    if($result->num_rows > 0)
    {
        $user = $result->fetch_assoc();

        // STEP 2: Verify hashed password
        if(password_verify($password, $user['password']))
        {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            header("Location: shift_start.php");
            exit;
        }
        else
        {
            $error = "Invalid username or password";
        }
    }
    else
    {
        $error = "Invalid username or password";
    }
}
?>

<!DOCTYPE html>
<html>

<head>

<title>POS Login</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

<div class="container mt-5">

<div class="row justify-content-center">

<div class="col-md-4">

<div class="card p-4">

<h3 class="text-center">POS Login</h3>

<form method="post">

<input
name="username"
class="form-control mb-2"
placeholder="Username"
required
>

<input
name="password"
type="password"
class="form-control mb-2"
placeholder="Password"
required
>

<button
name="login"
class="btn btn-primary w-100"
>
Login
</button>

</form>

<?php if(isset($error)) echo "<div class='text-danger mt-2'>$error</div>"; ?>

</div>

</div>

</div>

</div>

</body>

</html>
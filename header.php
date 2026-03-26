<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <title>Botika Mo</title>

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&display=swap" rel="stylesheet" />

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />

  <!-- Custom CSS -->
  <link rel="stylesheet" href="style.css" />

  <style>
    body {
      font-family: 'Nunito', sans-serif;
      background-color: #f0f8ff;
      /* light blue background */
      color: #1a1a1a;
    }

    :root {
      --primary-blue: #ac1212;
      /* Bootstrap primary */
      --accent-pink: #1f099d;
      /* Pink accent */
    }

    .bg-primary {
      background-color: var(--primary-blue) !important;
    }

    .navbar-dark .navbar-nav .nav-link.active,
    .navbar-dark .navbar-nav .nav-link:hover {
      color: var(--accent-pink) !important;
    }

    .btn-pink {
      background-color: var(--accent-pink);
      border-color: var(--accent-pink);
      color: white;
    }

    .btn-pink:hover {
      background-color: #b3245a;
      border-color: #b3245a;
    }
  </style>
</head>

<body>

  <?php

  session_start();

  if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
  }
  ?>


  <?php
  // Make sure session is started on the page before including this file
  $cart_count = 0;
  if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    // Sum all quantities in the cart
    $cart_count = 0;
    foreach ($_SESSION['cart'] as $item) {
      $cart_count += isset($item['qty']) ? $item['qty'] : 0;
    }
  }
  ?>

  <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center" href="index.php">
        <img src="assets/images/logo.png" alt="Botika Mo Logo" style="height:40px; margin-right:8px;" />
        Botika Mo
      </a>
      Logged in: <?= $_SESSION['name'] ?>

      <a href="/Botika_mo_V1.0/logout.php">
        Logout
      </a>
      <!-- <a href="shift_open.php">Open Shift</a> -->

      <a href="shift_close.php">Close Shift</a>
      <!-- <a href="open_cash.php">Open Cash Drawer</a> -->
      <button
        class="navbar-toggler"
        type="button"
        data-bs-toggle="collapse"
        data-bs-target="#navbarNav"
        aria-controls="navbarNav"
        aria-expanded="false"
        aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" href="index.php">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'services.php' ? 'active' : '' ?>" href="services.php">Services</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : '' ?>" href="contact.php">Contact</a>
          <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'admin/admin_reports.php' ? 'active' : '' ?>" href="admin/admin_reports.php">Login</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'order.php' ? 'active' : '' ?>" href="order.php">
              Cart
              <?php if ($cart_count > 0): ?>
                <span class="badge bg-danger rounded-pill"><?= $cart_count ?></span>
              <?php endif; ?>
            </a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <div class="container mt-4">

    <!-- Bootstrap JS Bundle (Popper.js included) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
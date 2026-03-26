<?php

// other includes or code

?>
<?php include 'header.php'; ?>

<h2>Contact Us</h2>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Message sent successfully!</div>
<?php endif; ?>

<div class="container mt-5">
  <h3 class="mb-4 text-center">Contact  Botika Mo</h3>

  <form action="send.php" method="POST" class="card p-4 shadow-sm">
    <div class="mb-3">
      <label class="form-label">Name</label>
      <input type="text" name="name" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Message</label>
      <textarea name="message" class="form-control" rows="4" required></textarea>
    </div>

    <button class="btn btn-primary w-100">Send Enquiry</button>
  </form>
</div>

<?php include 'footer.php'; ?>

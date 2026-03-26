
<?php
include 'header.php';
include '../db.php';

// session_start();
// if (!isset($_SESSION['admin'])) {
//     header("Location: ../login.php"); // Redirect to login or homepage
//     exit;
// }

/* -------------------------
   Pagination + Search Logic
-------------------------- */
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1);
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$searchTerm = "%$search%";

/* -------------------------
   Fetch Enquiries
-------------------------- */
$sql = "SELECT * FROM enquiries
        WHERE name LIKE ?
           OR email LIKE ?
           OR message LIKE ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sssii",
    $searchTerm, $searchTerm, $searchTerm, $limit, $offset
);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

/* -------------------------
   Count Total Rows
-------------------------- */
$countSql = "SELECT COUNT(*) AS total FROM enquiries
             WHERE name LIKE ?
                OR email LIKE ?
                OR message LIKE ?";

$countStmt = mysqli_prepare($conn, $countSql);
mysqli_stmt_bind_param($countStmt, "sss",
    $searchTerm, $searchTerm, $searchTerm
);
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$totalRows = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRows / $limit);
?>

<h3 class="mb-4">Welcome, <?= htmlspecialchars($_SESSION['admin']) ?></h3>

<!-- Search -->
<form method="GET" class="mb-3 d-flex">
    <input
        type="text"
        name="search"
        class="form-control me-2"
        placeholder="Search enquiries..."
        value="<?= htmlspecialchars($search) ?>"
    >
    <button class="btn btn-primary">Search</button>
</form>

<!-- Table -->
<div class="card shadow-sm">
  <div class="card-body">

    <table class="table table-bordered table-hover align-middle">
      <thead class="table-dark">
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Message</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($totalRows == 0): ?>
          <tr>
            <td colspan="4" class="text-center text-muted">
              No results found
            </td>
          </tr>
        <?php endif; ?>

        <?php while ($row = mysqli_fetch_assoc($result)): ?>
          <tr>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['email']) ?></td>
            <td><?= nl2br(htmlspecialchars($row['message'])) ?></td>
            <td><?= htmlspecialchars($row['created_at']) ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>

  </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<nav>
  <ul class="pagination justify-content-center mt-4">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
        <a class="page-link"
           href="?page=<?= $i ?>&search=<?= urlencode($search) ?>">
          <?= $i ?>
        </a>
      </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>

<?php include 'footer.php'; ?>

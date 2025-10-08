<?php
require_once('./connect/security_headers.php');
require_once('./connect/session.php');
include('./connect/connect.php');
require_once('./connect/functions.php');

if (!isset($_SESSION['s_username'])) {
  header("Location: ./index.php");
  exit();
}

if ($_SESSION['s_role'] !== 'admin') {
  $goBack = $_SESSION['last_page'] ?? 'home.php';
  header("Location: $goBack");
  exit();
}

$logsPerPage = 15;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $logsPerPage;

$search = trim($_GET['search'] ?? '');
$action = $_GET['action'] ?? '';
$sort = $_GET['sort'] ?? 'verified_at DESC';

$where = [];
$params = [];
$types = '';

if ($search !== '') {
  $where[] = "(u.s_name LIKE ? OR u.s_surname LIKE ? OR v.errors LIKE ?)";
  $types .= 'sss';
  $params[] = "%$search%";
  $params[] = "%$search%";
  $params[] = "%$search%";
}

if ($action !== '') {
  $where[] = "v.result = ?";
  $types .= 's';
  $params[] = $action;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$orderBy = in_array($sort, ['verified_at ASC', 'verified_at DESC', 's_name ASC']) ? $sort : 'verified_at DESC';

$countQuery = "SELECT COUNT(*) AS total FROM audit_verification v LEFT JOIN user u ON v.verifier_id = u.s_id $whereClause";
$countStmt = $conn->prepare($countQuery);
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalLogs = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalLogs / $logsPerPage);
$countStmt->close();

$query = "
  SELECT v.*, u.s_name, u.s_surname
  FROM audit_verification v
  LEFT JOIN user u ON v.verifier_id = u.s_id
  $whereClause
  ORDER BY $orderBy
  LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
if ($types) {
  $types .= 'ii';
  $params[] = $logsPerPage;
  $params[] = $offset;
  $stmt->bind_param($types, ...$params);
} else {
  $stmt->bind_param("ii", $logsPerPage, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
$verifications = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>SmartHealth</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link href="https://fonts.googleapis.com/css2?family=Kanit&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
  <link href="css/style.css" rel="stylesheet">
</head>
<body class="page-logs">
  <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm py-3 sticky-top">
    <div class="container d-flex align-items-center justify-content-between">
      <a href="home.php" class="d-flex align-items-center text-dark text-decoration-none">
        <i class="bi bi-arrow-left fs-2"></i>
      </a>
      <div class="text-center position-absolute top-50 start-50 translate-middle">
        <span class="fw-bold fs-5">ประวัติการตรวจสอบ Audit Log</span>
      </div>
      <div class="d-flex align-items-center">
        <img src="./upload/profile/<?= htmlspecialchars($_SESSION['s_profile']); ?>" alt="Profile"
             class="rounded-circle me-2" width="40" height="40">
        <span class="fw-bold fs-5 me-2 d-none d-md-inline">
          <?= htmlspecialchars($_SESSION['s_role'] . ' ' . $_SESSION['s_name']); ?>
        </span>
        <a href="./process/logout.php" class="text-danger fs-4 ms-1">
          <i class="bi bi-box-arrow-right"></i>
        </a>
      </div>
    </div>
  </nav>

  <div class="container py-4 my-4">
    <form method="get" class="row g-2 g-md-3 mb-4">
      <div class="col-12 col-md-3">
        <input type="text" id="liveFilter" name="search" class="form-control" placeholder="ค้นหาชื่อหรือคำอธิบาย..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
      </div>
      <div class="col-12 col-md-3">
        <select name="action" class="form-select">
          <option value="">ทุกผลลัพธ์</option>
          <option value="pass" <?= ($_GET['action'] ?? '') === 'pass' ? 'selected' : '' ?>>ผ่าน</option>
          <option value="fail" <?= ($_GET['action'] ?? '') === 'fail' ? 'selected' : '' ?>>ไม่ผ่าน</option>
        </select>
      </div>
      <div class="col-12 col-md-2">
        <select name="sort" class="form-select">
          <option value="verified_at DESC" <?= ($_GET['sort'] ?? '') === 'verified_at DESC' ? 'selected' : '' ?>>ล่าสุด</option>
          <option value="verified_at ASC" <?= ($_GET['sort'] ?? '') === 'verified_at ASC' ? 'selected' : '' ?>>เก่าสุด</option>
          <option value="s_name ASC" <?= ($_GET['sort'] ?? '') === 's_name ASC' ? 'selected' : '' ?>>ชื่อผู้ตรวจสอบ (A-Z)</option>
        </select>
      </div>
      <div class="col-12 col-md-2">
        <button type="submit" class="btn btn-dark w-100">ค้นหา</button>
      </div>
      <div class="col-12 col-md-2">
        <a href="verify_history.php" class="btn btn-outline-dark w-100">ล้างตัวกรอง</a>
      </div>
    </form>

    <div class="table-responsive shadow-sm rounded">
      <table class="table table-bordered table-striped align-middle text-center text-nowrap small">
        <thead class="table-dark">
          <tr>
            <th>เวลา</th>
            <th>ผู้ตรวจสอบ</th>
            <th>ผลลัพธ์</th>
            <th>รายละเอียด</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($verifications)): ?>
            <tr><td colspan="4">ไม่มีประวัติการตรวจสอบ</td></tr>
          <?php else: ?>
            <?php foreach ($verifications as $entry): ?>
              <tr data-search="<?= htmlspecialchars(($entry['s_name'] ?? '') . ' ' . ($entry['s_surname'] ?? '') . ' ' . ($entry['errors'] ?? '') . ' ' . ($entry['result'] ?? '')) ?>">
                <td><?= toThaiDateTime($entry['verified_at']) ?></td>
                <td><?= $entry['s_name'] ? htmlspecialchars($entry['s_name'] . ' ' . $entry['s_surname']) : 'ไม่ทราบ' ?></td>
                <td>
                  <span class="badge bg-<?= $entry['result'] === 'pass' ? 'success' : 'danger' ?>">
                    <?= strtoupper($entry['result']) ?>
                  </span>
                </td>
                <td class="text-start">
                  <?php if ($entry['errors']): ?>
                    <details>
                      <summary class="text-primary">ดูรายละเอียด</summary>
                      <pre class="mb-0 small"><?= htmlspecialchars($entry['errors']) ?></pre>
                    </details>
                  <?php else: ?>
                    -
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <nav aria-label="Pagination" class="mt-4">
      <div class="d-flex justify-content-center">
        <ul class="pagination">
          <?php if ($page > 1): ?>
            <li class="page-item">
              <a class="page-link bg-dark text-white" href="?page=<?= $page - 1 ?>" aria-label="Previous">
                <span aria-hidden="true">&laquo;</span>
              </a>
            </li>
          <?php endif; ?>

          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
              <a class="page-link <?= $i === $page ? 'bg-dark text-white border-dark' : '' ?>" href="?page=<?= $i ?>">
                <?= $i ?>
              </a>
            </li>
          <?php endfor; ?>

          <?php if ($page < $totalPages): ?>
            <li class="page-item">
              <a class="page-link bg-dark text-white" href="?page=<?= $page + 1 ?>" aria-label="Next">
                <span aria-hidden="true">&raquo;</span>
              </a>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </nav>
  </div>

  <div class="verify-history-float">
    <a href="verify_audit_log.php" class="btn btn-dark shadow btn-verify-float">
      <i class="bi bi-shield-check"></i>
    </a>
  </div>

  <?php include './components/toast.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="js/script.js"></script>
</body>
</html>
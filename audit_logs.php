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

$where = [];
$params = [];
$types = '';
$search = trim($_GET['search'] ?? '');
$action = $_GET['action'] ?? '';
$sort = $_GET['sort'] ?? 'created_at DESC';

if ($search !== '') {
  $where[] = "(u.s_name LIKE ? OR u.s_surname LIKE ? OR audit_log.description LIKE ?)";
  $types .= 'sss';
  $params[] = "%$search%";
  $params[] = "%$search%";
  $params[] = "%$search%";
}

if ($action !== '') {
  $where[] = "audit_log.action = ?";
  $types .= 's';
  $params[] = $action;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$orderBy = in_array($sort, ['created_at ASC', 'created_at DESC', 's_role ASC', 's_name ASC']) ? $sort : 'created_at DESC';

$countQuery = "SELECT COUNT(*) AS total FROM audit_log LEFT JOIN user u ON audit_log.user_id = u.s_id $whereClause";
$countStmt = $conn->prepare($countQuery);
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalLogs = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalLogs / $logsPerPage);
$countStmt->close();

$logsQuery = "
  SELECT audit_log.*, u.s_role, u.s_name, u.s_surname
  FROM audit_log 
  LEFT JOIN user u ON audit_log.user_id = u.s_id
  $whereClause
  ORDER BY $orderBy
  LIMIT ? OFFSET ?
";

$logsStmt = $conn->prepare($logsQuery);
if ($types) {
  $types .= 'ii';
  $params[] = $logsPerPage;
  $params[] = $offset;
  $logsStmt->bind_param($types, ...$params);
} else {
  $logsStmt->bind_param("ii", $logsPerPage, $offset);
}
$logsStmt->execute();
$logs = $logsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

function getBadgeColor($action) {
  switch ($action) {
    case 'login': return 'success';
    case 'logout': return 'danger';
    case 'register': return 'primary';
    default: return 'secondary';
  }
}
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
        <span class="fw-bold fs-5">บันทึกกิจกรรมผู้ใช้งาน</span>
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
          <option value="">ทุกการกระทำ</option>
          <option value="login" <?= ($_GET['action'] ?? '') === 'login' ? 'selected' : '' ?>>เข้าสู่ระบบ</option>
          <option value="logout" <?= ($_GET['action'] ?? '') === 'logout' ? 'selected' : '' ?>>ออกจากระบบ</option>
          <option value="register" <?= ($_GET['action'] ?? '') === 'register' ? 'selected' : '' ?>>ลงทะเบียน</option>
        </select>
      </div>
      <div class="col-12 col-md-2">
        <select name="sort" class="form-select">
          <option value="created_at DESC" <?= ($_GET['sort'] ?? '') === 'created_at DESC' ? 'selected' : '' ?>>ล่าสุด</option>
          <option value="created_at ASC" <?= ($_GET['sort'] ?? '') === 'created_at ASC' ? 'selected' : '' ?>>เก่าสุด</option>
          <option value="s_role ASC" <?= ($_GET['sort'] ?? '') === 's_role ASC' ? 'selected' : '' ?>>บทบาท (A-Z)</option>
          <option value="s_name ASC" <?= ($_GET['sort'] ?? '') === 's_name ASC' ? 'selected' : '' ?>>ชื่อ (A-Z)</option>
        </select>
      </div>
      <div class="col-12 col-md-2">
        <button type="submit" class="btn btn-dark w-100 btn-search">ค้นหา</button>
      </div>
      <div class="col-12 col-md-2">
        <a href="audit_logs.php" class="btn btn-outline-dark w-100">ล้างตัวกรอง</a>
      </div>
    </form>

    <div class="table-responsive shadow-sm rounded">
      <table class="table table-bordered table-striped align-middle text-center text-nowrap small">
        <thead class="table-dark">
          <tr>
            <th>เวลา</th>
            <th>บทบาท</th>
            <th>ชื่อผู้ใช้</th>
            <th>การกระทำ</th>
            <th>คำอธิบาย</th>
            <th>IP Address</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr><td colspan="6">ไม่มีบันทึกกิจกรรม</td></tr>
          <?php else: ?>
            <?php foreach ($logs as $log): ?>
              <tr data-search="<?= htmlspecialchars(($log['s_role'] ?? '') . ' ' . ($log['s_name'] ?? '') . ' ' . ($log['s_surname'] ?? '') . ' ' . ($log['action'] ?? '') . ' ' . ($log['description'] ?? '') . ' ' . ($log['ip_address'] ?? '')) ?>">
                <td><?= toThaiDateTime($log['created_at']) ?></td>
                <td><?= $log['s_role'] ? htmlspecialchars($log['s_role']) : 'ไม่ทราบ' ?></td>
                <td><?= $log['s_name'] ? htmlspecialchars($log['s_name'] . ' ' . $log['s_surname']) : 'ไม่ทราบ' ?></td>
                <td><span class="badge bg-<?= getBadgeColor($log['action']) ?>"><?= htmlspecialchars($log['action']) ?></span></td>
                <td class="text-start"><?= htmlspecialchars($log['description']) ?></td>
                <td><?= htmlspecialchars($log['ip_address']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <nav aria-label="Audit log pagination" class="mt-4">
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

  <?php include './components/toast.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="js/script.js"></script>
</body>
</html>
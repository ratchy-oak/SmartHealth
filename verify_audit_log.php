<?php
require_once('./connect/security_headers.php');
require_once('./connect/session.php');
include('./connect/connect.php');

if (!isset($_SESSION['s_username'])) {
  header("Location: ./index.php");
  exit();
}

if ($_SESSION['s_role'] !== 'admin') {
  $goBack = $_SESSION['last_page'] ?? 'home.php';
  header("Location: $goBack");
  exit();
}

function computeLogHash($entry) {
  $string = $entry['user_id'] .
            $entry['action'] .
            $entry['description'] .
            $entry['ip_address'] .
            $entry['created_at'] .
            $entry['previous_hash'];
  return hash('sha256', $string);
}

$result = $conn->query("SELECT * FROM audit_log ORDER BY created_at ASC");
$logs = $result->fetch_all(MYSQLI_ASSOC);

$errors = [];
$previousHash = null;

foreach ($logs as $i => $log) {
  $expectedHash = computeLogHash($log);

  if ($log['log_hash'] !== $expectedHash) {
    $errors[] = "❌ ตรวจพบการแก้ไขที่ Log ID {$log['id']} — hash ไม่ตรงกัน";
  }

  if ($log['previous_hash'] !== $previousHash && $i !== 0) {
    $errors[] = "❌ ตรวจพบการแก้ไขที่ Log ID {$log['id']} — previous hash ไม่ตรงกัน";
  }

  $previousHash = $log['log_hash'];
}

$verifierId = $_SESSION['s_id'];
$resultStatus = empty($errors) ? 'pass' : 'fail';
$errorText = empty($errors) ? null : implode("\n", $errors);

$stmt = $conn->prepare("
  INSERT INTO audit_verification (verifier_id, result, errors)
  VALUES (?, ?, ?)
");
$stmt->bind_param("iss", $verifierId, $resultStatus, $errorText);
$stmt->execute();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>SmartHealth</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link href="https://fonts.googleapis.com/css2?family=Kanit&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
  <link href="css/style.css" rel="stylesheet">
</head>
<body class="bg-light page-logs">
  <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm py-3 sticky-top">
    <div class="container d-flex align-items-center justify-content-between">
      <a href="verify_history.php" class="d-flex align-items-center text-dark text-decoration-none">
        <i class="bi bi-arrow-left fs-2"></i>
      </a>
      <div class="text-center position-absolute top-50 start-50 translate-middle">
        <span class="fw-bold fs-5">ตรวจสอบความถูกต้อง Audit Log</span>
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

  <div class="container py-5">
    <div class="bg-white shadow-sm rounded p-4">
      <h4 class="fw-bold mb-4 text-center">ผลการตรวจสอบ</h4>

      <?php if (empty($errors)): ?>
        <div class="alert alert-success text-center fs-5 fw-semibold">
          ✅ ระบบไม่พบการแก้ไข Audit Logs ทั้งหมดถูกต้อง
        </div>
      <?php else: ?>
        <div class="alert alert-danger">
          ⚠️ ตรวจพบความผิดปกติ:
          <ul class="mt-2 mb-0">
            <?php foreach ($errors as $error): ?>
              <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php include './components/toast.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="js/script.js"></script>
</body>
</html>
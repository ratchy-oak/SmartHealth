<?php
require_once('./connect/security_headers.php');
require_once('./connect/session.php');
include('./connect/connect.php');

if (!isset($_SESSION['s_username'])) {
  header("Location: ./index.php");
  exit();
}

$_SESSION['last_page'] = basename($_SERVER['PHP_SELF']);

$hospitalName = 'ทุกโรงพยาบาล';
if ($_SESSION['s_role'] !== 'admin' && !empty($_SESSION['s_hospital_id'])) {
  $stmt = $conn->prepare("SELECT name FROM hospital WHERE id = ?");
  $stmt->bind_param("i", $_SESSION['s_hospital_id']);
  $stmt->execute();
  $stmt->bind_result($hospitalName);
  $stmt->fetch();
  $stmt->close();
}

$isAdmin = ($_SESSION['s_role'] === 'admin');

if ($isAdmin) {
  $stmt = $conn->prepare("
    SELECT h.name AS hospital_name, u.s_prefix, u.s_name, u.s_surname, u.s_position, u.s_profile, u.s_phone_number
    FROM user u
    LEFT JOIN hospital h ON u.s_hospital_id = h.id
    WHERE u.s_role = 'cg'
    ORDER BY h.name, u.s_name
  ");
  $stmt->execute();
  $result = $stmt->get_result();

  $grouped = [];
  while ($row = $result->fetch_assoc()) {
    $hospital_group_name = $row['hospital_name'] ?? 'ไม่ระบุโรงพยาบาล';
    $grouped[$hospital_group_name][] = $row;
  }
  $total = $result->num_rows;
} else {
  $stmt = $conn->prepare("SELECT s_prefix, s_name, s_surname, s_position, s_profile, s_phone_number FROM user WHERE s_role = 'cg' AND s_hospital_id = ?");
  $stmt->bind_param("i", $_SESSION['s_hospital_id']);
  $stmt->execute();
  $result = $stmt->get_result();
  $cgs = $result->fetch_all(MYSQLI_ASSOC);
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
<body class="page-member-cg">
  <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm py-3 sticky-top">
    <div class="container d-flex align-items-center justify-content-between">
      <a href="member.php" class="d-flex align-items-center text-dark text-decoration-none"><i class="bi bi-arrow-left fs-2"></i></a>
      <div class="text-center position-absolute top-50 start-50 translate-middle">
        <span class="fw-bold fs-5">รายชื่อ CG <?= htmlspecialchars($hospitalName) ?></span>
      </div>
      <div class="d-flex align-items-center">
        <img src="./upload/profile/<?php echo htmlspecialchars($_SESSION['s_profile']); ?>" alt="Profile" class="rounded-circle me-2" width="40" height="40">
        <span class="fw-bold fs-5 me-2 d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['s_role'] . ' ' . $_SESSION['s_name']); ?></span>
        <a href="./process/logout.php" class="text-danger fs-4 ms-1"><i class="bi bi-box-arrow-right"></i></a>
      </div>
    </div>
  </nav>

  <div class="container my-5">
    <?php if ($isAdmin): ?>
      <div class="d-flex justify-content-between align-items-center mb-4 px-1 px-sm-2 border-bottom pb-3">
        <h5 class="fw-bold mb-0">รายชื่อ CG ทั้งหมด</h5>
        <span class="badge bg-primary rounded-pill fs-6"><?= $total ?> คน</span>
      </div>
      <?php $global_index = 0; ?>
      <?php foreach ($grouped as $hospital => $cgs): ?>
        <?php $collapseId = 'collapse_cg_' . md5($hospital); ?>
        <div class="district-card">
          <button class="district-toggle collapsed" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>" aria-expanded="false" aria-controls="<?= $collapseId ?>">
            <span><?= htmlspecialchars($hospital) ?></span>
            <div class="d-flex align-items-center">
              <span class="badge badge-rounded me-2"><?= count($cgs) ?></span>
              <i class="bi bi-chevron-down collapse-icon"></i>
            </div>
          </button>
          <div class="collapse" id="<?= $collapseId ?>">
            <?php foreach ($cgs as $cg): ?>
              <div class="hospital-item">
                <div class="d-flex align-items-center">
                  <img src="./upload/profile/<?= htmlspecialchars($cg['s_profile']); ?>" alt="CG Profile" class="me-3 rounded-circle" width="48" height="48">
                  <div>
                    <div class="hospital-name"><?= htmlspecialchars($cg['s_prefix'] . ' ' . $cg['s_name'] . ' ' . $cg['s_surname']) ?></div>
                    <div class="hospital-subtext"><?= htmlspecialchars($cg['s_position']) ?></div>
                  </div>
                </div>
                <div class="d-flex align-items-center">
                  <span class="phone-number" id="cgphone<?= $global_index ?>"><?= htmlspecialchars($cg['s_phone_number']); ?></span>
                  <i class="bi bi-telephone-forward-fill fs-4 text-muted ms-2 phone-toggle" data-toggle-target="cgphone<?= $global_index ?>"></i>
                </div>
              </div>
              <?php $global_index++; ?>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <?php foreach ($cgs as $index => $cg): ?>
        <div class="cm-card">
          <div class="cm-info">
            <img src="./upload/profile/<?= htmlspecialchars($cg['s_profile']) ?>" alt="CG">
            <div>
              <div class="cm-name"><?= htmlspecialchars($cg['s_prefix'] . ' ' . $cg['s_name'] . ' ' . $cg['s_surname']) ?></div>
              <div class="cm-role"><?= htmlspecialchars($cg['s_position']) ?></div>
            </div>
          </div>
          <div class="d-flex align-items-center">
            <span class="phone-number" id="cgphone<?= $index ?>"><?= htmlspecialchars($cg['s_phone_number']) ?></span>
            <i class="bi bi-telephone-forward-fill fs-4 text-muted ms-2 phone-toggle" data-toggle-target="cgphone<?= $index ?>"></i>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php include './components/toast.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="js/script.js"></script>
</body>
</html>
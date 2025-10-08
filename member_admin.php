<?php
require_once('./connect/security_headers.php');
require_once('./connect/session.php');
include('./connect/connect.php');

if (!isset($_SESSION['s_username'])) {
  header("Location: ./index.php");
  exit();
}

$_SESSION['last_page'] = basename($_SERVER['PHP_SELF']);

$current_user_role = $_SESSION['s_role'];
$current_user_hospital_id = $_SESSION['s_hospital_id'] ?? null;

$page_title = 'รายชื่อ ADMIN';
if (($current_user_role === 'cm' || $current_user_role === 'cg') && $current_user_hospital_id) {
    $hosp_stmt = $conn->prepare("SELECT name FROM hospital WHERE id = ?");
    if ($hosp_stmt) {
        $hosp_stmt->bind_param("i", $current_user_hospital_id);
        $hosp_stmt->execute();
        $hosp_stmt->bind_result($hospital_name);
        if ($hosp_stmt->fetch()) {
            $page_title .= ' ' . $hospital_name;
        }
        $hosp_stmt->close();
    }
}

$sql = "
    SELECT h.name AS hospital_name, u.s_affiliation, u.s_prefix, u.s_name, u.s_surname, u.s_position, u.s_profile, u.s_phone_number
    FROM user u
    LEFT JOIN hospital h ON u.s_hospital_id = h.id
    WHERE u.s_role = 'admin'
";

if ($current_user_role === 'cm' || $current_user_role === 'cg') {
    $sql .= " AND u.s_hospital_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $current_user_hospital_id);
} else {
    $sql .= " ORDER BY u.s_affiliation, u.s_name";
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();

$grouped_admins = [];
while ($row = $result->fetch_assoc()) {
    $affiliation_name = $row['s_affiliation'] ?? 'ไม่ระบุสังกัด';
    $grouped_admins[$affiliation_name][] = $row;
}
$stmt->close();

$total_admins = $result->num_rows;

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
<body class="page-member-admin">
  <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm py-3 sticky-top">
    <div class="container d-flex align-items-center justify-content-between">
      <a href="member.php" class="d-flex align-items-center text-dark text-decoration-none">
        <i class="bi bi-arrow-left fs-2"></i>
      </a>
      <div class="text-center position-absolute top-50 start-50 translate-middle">
        <span class="fw-bold fs-5"><?= htmlspecialchars($page_title) ?></span>
      </div>
      <div class="d-flex align-items-center">
        <img src="./upload/profile/<?php echo htmlspecialchars($_SESSION['s_profile']); ?>" alt="Profile"
            class="rounded-circle me-2" width="40" height="40">
        <span class="fw-bold fs-5 me-2 d-none d-md-inline">
          <?php echo htmlspecialchars($_SESSION['s_role'] . ' ' . $_SESSION['s_name']); ?>
        </span>
        <a href="./process/logout.php" class="text-danger fs-4 ms-1">
          <i class="bi bi-box-arrow-right"></i>
        </a>
      </div>
    </div>
  </nav>

  <div class="container my-5">
      <div class="d-flex justify-content-between align-items-center mb-4 px-1 px-sm-2 border-bottom pb-3">
        <h5 class="fw-bold mb-0">รายชื่อ Admin ทั้งหมด</h5>
        <span class="badge bg-primary rounded-pill fs-6"><?= $total_admins ?> คน</span>
      </div>
      
      <?php if (empty($grouped_admins)): ?>
        <div class="alert alert-info text-center">ไม่พบรายชื่อ Admin</div>
      <?php else: ?>
        <?php $global_index = 0; ?>
        <?php foreach ($grouped_admins as $affiliation => $admins_in_affiliation): ?>
          <?php $collapseId = 'collapse_admin_' . md5($affiliation); ?>
          <div class="district-card">
            <button class="district-toggle collapsed" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>" aria-expanded="false" aria-controls="<?= $collapseId ?>">
              <span><?= htmlspecialchars($affiliation) ?></span>
              <div class="d-flex align-items-center">
                <span class="badge badge-rounded me-2"><?= count($admins_in_affiliation) ?></span>
                <i class="bi bi-chevron-down collapse-icon"></i>
              </div>
            </button>
            <div class="collapse" id="<?= $collapseId ?>">
              <?php foreach ($admins_in_affiliation as $admin): ?>
                <div class="hospital-item">
                  <div class="d-flex align-items-center">
                      <img src="./upload/profile/<?= htmlspecialchars($admin['s_profile']); ?>" alt="Admin Profile" class="me-3 rounded-circle" width="48" height="48">
                      <div>
                          <div class="hospital-name"><?= htmlspecialchars($admin['s_prefix'] . ' ' . $admin['s_name'] . ' ' . $admin['s_surname']) ?></div>
                          <div class="hospital-subtext"><?= htmlspecialchars($admin['s_position']) ?></div>
                          <div class="hospital-subtext text-muted small"><?= htmlspecialchars($admin['hospital_name'] ?? 'N/A') ?></div>
                      </div>
                  </div>
                  <div class="d-flex align-items-center">
                    <span class="phone-number" id="adminphone<?= $global_index ?>"><?= htmlspecialchars($admin['s_phone_number']); ?></span>
                    <i class="bi bi-telephone-forward-fill fs-4 text-muted ms-2 phone-toggle" data-toggle-target="adminphone<?= $global_index ?>"></i>
                  </div>
                </div>
                <?php $global_index++; ?>
              <?php endforeach; ?>
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
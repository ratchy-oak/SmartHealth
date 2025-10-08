<?php
require_once('./connect/security_headers.php');
require_once('./connect/session.php');
include('./connect/connect.php');

if (!isset($_SESSION['s_username'])) {
  header("Location: ./index.php");
  exit();
}

$_SESSION['last_page'] = basename($_SERVER['PHP_SELF']);

$stmt = $conn->prepare("SELECT district, name, subdistrict, province FROM hospital ORDER BY district, name");
$stmt->execute();
$result = $stmt->get_result();

$grouped = [];
$total = 0;
while ($row = $result->fetch_assoc()) {
  $grouped[$row['district']][] = $row;
  $total++;
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
<body class="page-member-hospital">
  <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm py-3 sticky-top">
    <div class="container d-flex align-items-center justify-content-between">
      <a href="member.php" class="d-flex align-items-center text-dark text-decoration-none">
        <i class="bi bi-arrow-left fs-2"></i>
      </a>
      <div class="text-center position-absolute top-50 start-50 translate-middle">
        <span class="fw-bold fs-5">รายชื่อโรงพยาบาล</span>
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
      <h5 class="fw-bold mb-0">รายชื่อโรงพยาบาลทั้งหมด</h5>
      <span class="badge bg-primary rounded-pill fs-6"><?= $total ?> แห่ง</span>
    </div>
    <?php foreach ($grouped as $district => $hospitals): ?>
      <?php $collapseId = 'collapse_' . md5($district); ?>
      <div class="district-card">
        <button class="district-toggle collapsed" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>" aria-expanded="false" aria-controls="<?= $collapseId ?>">
          <span>อำเภอ<?= htmlspecialchars($district) ?></span>
          <div class="d-flex align-items-center">
            <span class="badge badge-rounded me-2"><?= count($hospitals) ?></span>
            <i class="bi bi-chevron-down collapse-icon"></i>
          </div>
        </button>
        <div class="collapse" id="<?= $collapseId ?>">
          <?php foreach ($hospitals as $hospital): ?>
            <div class="hospital-item">
              <div class="hospital-name"><?= htmlspecialchars($hospital['name']) ?></div>
              <div class="hospital-subtext">
                ตำบล<?= htmlspecialchars($hospital['subdistrict']) ?> &nbsp;&nbsp;จังหวัด<?= htmlspecialchars($hospital['province']) ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php include './components/toast.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="js/script.js"></script>
</body>
</html>
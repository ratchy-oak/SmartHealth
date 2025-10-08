<?php
require_once('./connect/security_headers.php');
require_once('./connect/session.php');
include('./connect/connect.php');

if (!isset($_SESSION['s_username'])) {
  header("Location: ./index.php");
  exit();
}

$_SESSION['last_page'] = basename($_SERVER['PHP_SELF']);
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
<body class="page-member">
  <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm py-3 sticky-top">
    <div class="container d-flex align-items-center justify-content-between">
      <a href="home.php" class="d-flex align-items-center text-dark text-decoration-none">
        <i class="bi bi-arrow-left fs-2"></i>
      </a>
      <div class="text-center position-absolute top-50 start-50 translate-middle">
        <span class="fw-bold fs-5">ข้อมูลบุคลากรด้านสาธารณสุข</span>
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
    <div class="row g-4">
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card card-custom py-3">
          <div class="card-body text-center">
            <p class="card-text fs-5">คณะทำงานอบจ.</p>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <a href="member_admin.php" class="text-decoration-none text-dark">
          <div class="card card-custom py-3 h-100">
            <div class="card-body text-center">
              <p class="card-text fs-5 mb-0">รายชื่อ ADMIN</p>
            </div>
          </div>
        </a>
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <a href="member_cm.php" class="text-decoration-none text-dark">
          <div class="card card-custom py-3 h-100">
            <div class="card-body text-center">
              <p class="card-text fs-5 mb-0">รายชื่อ CM</p>
            </div>
          </div>
        </a>
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <a href="member_cg.php" class="text-decoration-none text-dark">
          <div class="card card-custom py-3 h-100">
            <div class="card-body text-center">
              <p class="card-text fs-5 mb-0">รายชื่อ CG</p>
            </div>
          </div>
        </a>
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <a href="member_hospital.php" class="text-decoration-none text-dark">
          <div class="card card-custom py-3">
            <div class="card-body text-center">
              <p class="card-text fs-5">รายชื่อ รพ.สต.</p>
            </div>
          </div>
        </a>
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card card-custom py-3">
          <div class="card-body text-center">
            <p class="card-text fs-5">รายชื่อ อปท.</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php include './components/toast.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="js/script.js"></script>
</body>
</html>
<?php
require_once('./connect/security_headers.php');
require_once('./connect/session.php');
include('./connect/connect.php');

if (!isset($_SESSION['s_username'])) {
  header("Location: ./index.php");
  exit();
}

$_SESSION['last_page'] = basename($_SERVER['PHP_SELF']);

$hospitalName = 'ผู้ดูแลระบบ'; // Default title for admin

if ($_SESSION['s_role'] !== 'admin' && !empty($_SESSION['s_hospital_id'])) {
    $stmt = $conn->prepare("SELECT name FROM hospital WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['s_hospital_id']);
        if ($stmt->execute()) {
            $stmt->bind_result($fetchedName);
            if ($stmt->fetch()) {
                $hospitalName = $fetchedName;
            }
        }
        $stmt->close();
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
<body class="page-home">
  <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm py-3 sticky-top z-1030">
    <div class="container d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center">
        <a class="navbar-brand d-flex align-items-center me-3" href="home.php">
          <img src="img/logo.png" alt="Logo" width="38" height="38">
        </a>
      </div>
      <div class="text-center position-absolute top-50 start-50 translate-middle">
        <span class="fw-bold fs-5 text-dark"><?= htmlspecialchars($hospitalName) ?></span>
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
        <a href="member.php" class="text-decoration-none">
          <div class="card card-custom h-100">
            <img src="img/member.png" class="card-img-top" alt="...">
            <div class="card-body text-center">
              <p class="card-title">ข้อมูลบุคลากรด้านสาธารณสุข</p>
            </div>
          </div>
        </a>
      </div>

      <div class="col-12 col-sm-6 col-lg-3">
        <a href="patient_info.php" class="text-decoration-none">
          <div class="card card-custom h-100">
            <img src="img/patient_info.png" class="card-img-top" alt="...">
            <div class="card-body text-center">
              <p class="card-title">ข้อมูลผู้สูงอายุและผู้มีภาวะพึ่งพิง</p>
            </div>
          </div>
        </a>
      </div>

      <div class="col-12 col-sm-6 col-lg-3">
        <a href="patient_form.php" class="text-decoration-none">
          <div class="card card-custom h-100">
            <img src="img/patient_form.png" class="card-img-top" alt="...">
            <div class="card-body text-center">
              <p class="card-title">แบบบันทึกการดูแลผู้มีภาวะพึ่งพิง</p>
            </div>
          </div>
        </a>
      </div>

      <div class="col-12 col-sm-6 col-lg-3">
        <a href="elderly_form.php" class="text-decoration-none">
          <div class="card card-custom h-100">
            <img src="img/elderly_form.png" class="card-img-top" alt="...">
            <div class="card-body text-center">
              <p class="card-title">แบบประเมิณสุขภาพผู้สูงอายุ</p>
            </div>
          </div>
        </a>
      </div>
      
      <div class="col-12 col-sm-6 col-lg-3">
        <a href="equipment.php" class="text-decoration-none">
          <div class="card card-custom h-100">
            <img src="img/equipment.png" class="card-img-top" alt="...">
            <div class="card-body text-center">
              <p class="card-title">ทะเบียนอุปกรณ์ทางการแพทย์</p>
            </div>
          </div>
        </a>
      </div>

      <div class="col-12 col-sm-6 col-lg-3">
        <a href="request_equipment.php" class="text-decoration-none">
          <div class="card card-custom h-100">
            <img src="img/request_equipment.png" class="card-img-top" alt="...">
            <div class="card-body text-center">
              <p class="card-title">แบบคำร้องขอยืมอุปกรณ์ทางการแพทย์</p>
            </div>
          </div>
        </a>
      </div>

      <div class="col-12 col-sm-6 col-lg-3">
        <a href="report.php" class="text-decoration-none">
          <div class="card card-custom h-100">
            <img src="img/report.png" class="card-img-top" alt="...">
            <div class="card-body text-center">
              <p class="card-title">รายงาน</p>
            </div>
          </div>
        </a>
      </div>

      <?php if ($_SESSION['s_role'] === 'admin'): ?>
        <div class="col-12 col-sm-6 col-lg-3">
          <a href="register.php" class="text-decoration-none">
            <div class="card card-custom h-100">
              <img src="img/register.png" class="card-img-top" alt="...">
              <div class="card-body text-center">
                <p class="card-title">สร้างบัญชีผู้ใช้งาน</p>
              </div>
            </div>
          </a>
        </div>
      <?php endif; ?>

      <?php if ($_SESSION['s_role'] === 'admin'): ?>
        <div class="col-12 col-sm-6 col-lg-3">
          <a href="audit_logs.php" class="text-decoration-none">
            <div class="card card-custom h-100">
              <img src="img/audit_logs.png" class="card-img-top" alt="...">
              <div class="card-body text-center">
                <p class="card-title">บันทึกกิจกรรมผู้ใช้งาน</p>
              </div>
            </div>
          </a>
        </div>
      <?php endif; ?>

      <?php if ($_SESSION['s_role'] === 'admin'): ?>
        <div class="col-12 col-sm-6 col-lg-3">
          <a href="dashboard.php" class="text-decoration-none">
            <div class="card card-custom h-100">
              <img src="img/dashboard.png" class="card-img-top" alt="...">
              <div class="card-body text-center">
                <p class="card-title">Dashboard</p>
              </div>
            </div>
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php include './components/toast.php'; ?>      
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="js/script.js"></script>
</body>
</html>
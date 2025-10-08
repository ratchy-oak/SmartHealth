<?php
require_once('./connect/security_headers.php');
require_once('./connect/session.php');
include('./connect/connect.php');

if (!isset($_SESSION['s_username'])) {
  header("Location: ./index.php");
  exit();
}
$_SESSION['last_page'] = basename($_SERVER['PHP_SELF']);

$project_year = filter_input(INPUT_GET, 'year', FILTER_SANITIZE_SPECIAL_CHARS);
$hospital_id = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT);

if (!$project_year || !$hospital_id) {
    header("Location: patient_form.php?error=invalid_params");
    exit();
}

$current_user_role = $_SESSION['s_role'];
$user_hospital_id = $_SESSION['s_hospital_id'] ?? null;

if ($current_user_role !== 'admin' && $hospital_id != $user_hospital_id) {
    header("Location: home.php?error=unauthorized");
    exit();
}

$page_title = "รายชื่อผู้ป่วยปีโครงการ " . htmlspecialchars($project_year);

$stmt = $conn->prepare("
    SELECT id, prefix, first_name, last_name, age, status, photo
    FROM patient
    WHERE hospital_id = ? 
      AND project_year = ?
      AND status IN ('ผู้มีภาวะพึ่งพิง', 'ผู้มีภาวะพึ่งพิงในโครงการ LTC')
    ORDER BY first_name, last_name
");
$stmt->bind_param("is", $hospital_id, $project_year);
$stmt->execute();
$patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SmartHealth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="page-patient-care-list">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm py-3 sticky-top">
      <div class="container d-flex align-items-center justify-content-between">
          <a href="patient_form.php" class="d-flex align-items-center text-dark text-decoration-none" aria-label="ย้อนกลับ"><i class="bi bi-arrow-left fs-2" aria-hidden="true"></i></a>
          <div class="text-center position-absolute top-50 start-50 translate-middle"><span class="fw-bold fs-5"><?= $page_title ?></span></div>
          <div class="d-flex align-items-center">
              <img src="./upload/profile/<?php echo htmlspecialchars($_SESSION['s_profile']); ?>" alt="โปรไฟล์" class="rounded-circle me-2" width="40" height="40">
              <span class="fw-bold fs-5 me-2 d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['s_role'] . ' ' . $_SESSION['s_name']); ?></span>
              <a href="./process/logout.php" class="text-danger fs-4 ms-1" aria-label="ออกจากระบบ"><i class="bi bi-box-arrow-right" aria-hidden="true"></i></a>
          </div>
      </div>
    </nav>

    <div class="container my-5">
        <?php if (empty($patients)): ?>
            <div class="alert alert-info text-center">ไม่พบรายชื่อผู้ป่วยในกลุ่มผู้มีภาวะพึ่งพิงสำหรับปีโครงการนี้</div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($patients as $patient): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="care-list-card">
                            <div class="patient-info">
                                <img src="./upload/patient/<?= htmlspecialchars($patient['photo']) ?>" alt="รูปผู้ป่วย" class="patient-photo">
                                <div>
                                    <div class="patient-name"><?= htmlspecialchars($patient['prefix'] . ' ' . $patient['first_name'] . ' ' . $patient['last_name']) ?></div>
                                    <div class="patient-details">อายุ: <?= $patient['age'] ?> ปี | สถานะ: <?= htmlspecialchars($patient['status']) ?></div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-sm-flex">
                                <a href="patient_care_history.php?patient_id=<?= $patient['id'] ?>" class="btn btn-outline-dark flex-sm-fill">
                                    <i class="bi bi-clock-history me-1"></i>ดูประวัติ
                                </a>
                                <a href="patient_care_form.php?id=<?= $patient['id'] ?>" class="btn btn-care flex-sm-fill">
                                    <i class="bi bi-pencil-square me-1"></i>บันทึก
                                </a>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include './components/toast.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
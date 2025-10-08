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
$user_hospital_id = $_SESSION['s_hospital_id'] ?? null;
$selected_hospital_id = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT);

$page_title = 'บันทึกการดูแลผู้มีภาวะพึ่งพิง';
$header_title = '';
$years = [];
$hospitals = [];
$display_mode = ''; 

if ($current_user_role === 'admin') {
    if ($selected_hospital_id) {
        $display_mode = 'year_select';
        $header_title = 'เลือกปีโครงการ';

        $year_stmt = $conn->prepare("
            SELECT DISTINCT project_year FROM patient 
            WHERE hospital_id = ? 
            AND status IN ('ผู้มีภาวะพึ่งพิง', 'ผู้มีภาวะพึ่งพิงในโครงการ LTC')
            ORDER BY project_year DESC
        ");
        $year_stmt->bind_param("i", $selected_hospital_id);
        $year_stmt->execute();
        $result = $year_stmt->get_result();
        while($row = $result->fetch_assoc()) {
            $years[] = $row['project_year'];
        }
        $year_stmt->close();
    } else {
        $display_mode = 'hospital_select';
        $header_title = 'เลือกโรงพยาบาล';
        $hosp_stmt = $conn->prepare("SELECT id, name FROM hospital ORDER BY name ASC");
        $hosp_stmt->execute();
        $hospitals = $hosp_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $hosp_stmt->close();
    }
} else {
    $display_mode = 'year_select';
    $header_title = 'เลือกปีโครงการ';
    if ($user_hospital_id) {
        $year_stmt = $conn->prepare("
            SELECT DISTINCT project_year FROM patient 
            WHERE hospital_id = ?
            AND status IN ('ผู้มีภาวะพึ่งพิง', 'ผู้มีภาวะพึ่งพิงในโครงการ LTC')
            ORDER BY project_year DESC
        ");
        $year_stmt->bind_param("i", $user_hospital_id);
        $year_stmt->execute();
        $result = $year_stmt->get_result();
        while($row = $result->fetch_assoc()) {
            $years[] = $row['project_year'];
        }
        $year_stmt->close();
    }
}
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
<body class="page-patient-form">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm py-3 sticky-top">
      <div class="container d-flex align-items-center justify-content-between">
          <a href="home.php" class="d-flex align-items-center text-dark text-decoration-none" aria-label="ย้อนกลับไปยังหน้าแรก"><i class="bi bi-arrow-left fs-2" aria-hidden="true"></i></a>
          <div class="text-center position-absolute top-50 start-50 translate-middle"><span class="fw-bold fs-5"><?= htmlspecialchars($page_title) ?></span></div>
          <div class="d-flex align-items-center">
              <img src="./upload/profile/<?php echo htmlspecialchars($_SESSION['s_profile']); ?>" alt="โปรไฟล์ของ <?php echo htmlspecialchars($_SESSION['s_name']); ?>" class="rounded-circle me-2" width="40" height="40">
              <span class="fw-bold fs-5 me-2 d-none d-md-inline" aria-current="user"><?php echo htmlspecialchars($_SESSION['s_role'] . ' ' . $_SESSION['s_name']); ?></span>
              <a href="./process/logout.php" class="text-danger fs-4 ms-1" aria-label="ออกจากระบบ"><i class="bi bi-box-arrow-right" aria-hidden="true"></i></a>
          </div>
      </div>
    </nav>

    <div class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4 px-1 px-sm-2 border-bottom pb-3">
            <h5 class="fw-bold mb-0"><?= htmlspecialchars($header_title) ?></h5>
        </div>

        <?php if ($display_mode === 'hospital_select'): ?>
            <div class="selection-container mx-auto mt-5">
                <form method="get" class="d-flex gap-2">
                    <select name="hospital_id" class="form-select form-select-lg" required>
                        <option value="" disabled selected>เลือกจากรายการ...</option>
                        <?php foreach($hospitals as $hospital): ?>
                            <option value="<?= $hospital['id'] ?>"><?= htmlspecialchars($hospital['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-data px-4 flex-shrink-0">ดูข้อมูล</button>
                </form>
            </div>
        <?php elseif ($display_mode === 'year_select'): ?>
            <?php if (empty($years)): ?>
                <div class="alert alert-info text-center">ไม่พบข้อมูลปีโครงการที่มีผู้ป่วยในกลุ่มผู้มีภาวะพึ่งพิงสำหรับโรงพยาบาลนี้</div>
            <?php else: ?>
                <div class="row g-4 justify-content-center">
                    <?php foreach ($years as $year): ?>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                            <a href="patient_care_list.php?year=<?= urlencode($year) ?>&hospital_id=<?= $selected_hospital_id ?? $user_hospital_id ?>" class="text-decoration-none">
                                <div class="year-card">
                                    <i class="bi bi-calendar-check-fill year-icon"></i>
                                    <div class="year-text">ปีโครงการ</div>
                                    <div class="year-number"><?= htmlspecialchars($year) ?></div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <?php include './components/toast.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
<?php
require_once('./connect/security_headers.php');
require_once('./connect/session.php');
include('./connect/connect.php');
require_once('./connect/functions.php');

if (!isset($_SESSION['s_username'])) { header("Location: ./index.php"); exit(); }

$_SESSION['last_page'] = $_SERVER['REQUEST_URI'];

$patient_id = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);
if (!$patient_id) { header("Location: patient_form.php?error=invalid_patient_id"); exit(); }

$p_stmt = $conn->prepare("SELECT prefix, first_name, last_name, hospital_id, project_year FROM patient WHERE id = ?");
$p_stmt->bind_param("i", $patient_id);
$p_stmt->execute();
$patient = $p_stmt->get_result()->fetch_assoc();
$p_stmt->close();

if (!$patient) { header("Location: patient_form.php?error=patient_not_found"); exit(); }

if ($_SESSION['s_role'] !== 'admin' && $patient['hospital_id'] != $_SESSION['s_hospital_id']) { header("Location: home.php?error=unauthorized"); exit(); }

$page_title = "ประวัติการดูแล " . htmlspecialchars($patient['prefix'] . $patient['first_name'] . ' ' . $patient['last_name']);
$back_link = "patient_care_list.php?year=" . urlencode($patient['project_year']) . "&hospital_id=" . $patient['hospital_id'];

function getAssessmentBadgeClass($result) {
    switch ($result) {
        case 'ดีขึ้น': return 'bg-success';
        case 'เท่าเดิม': return 'bg-warning text-dark';
        case 'แย่ลง': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

$visits = [];
$sql = "SELECT cv.id, cv.visit_date, cv.adl_total, cv.assessment_result, cv.bp_systolic, cv.bp_diastolic, SUBSTRING(cv.symptoms_found, 1, 100) as symptoms_preview, CONCAT(cm.s_prefix, cm.s_name, ' ', cm.s_surname) as cm_fullname, CONCAT(cg.s_prefix, cg.s_name, ' ', cg.s_surname) as cg_fullname FROM care_visits cv LEFT JOIN user cm ON cv.cm_id = cm.s_id LEFT JOIN user cg ON cv.cg_id = cg.s_id WHERE cv.patient_id = ? ORDER BY cv.visit_date DESC, cv.start_time DESC";
$v_stmt = $conn->prepare($sql);
$v_stmt->bind_param("i", $patient_id);
$v_stmt->execute();
$v_stmt->bind_result($id, $visit_date, $adl_total, $assessment_result, $bp_systolic, $bp_diastolic, $symptoms_preview, $cm_fullname, $cg_fullname);
while ($v_stmt->fetch()) {
    $visits[] = ['id' => $id, 'visit_date' => $visit_date, 'adl_total' => $adl_total, 'assessment_result' => $assessment_result, 'bp_systolic' => $bp_systolic, 'bp_diastolic' => $bp_diastolic, 'symptoms_preview' => $symptoms_preview, 'cm_fullname' => $cm_fullname, 'cg_fullname' => $cg_fullname];
}
$v_stmt->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SmartHealth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="page-patient-care-history">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm py-3 sticky-top">
      <div class="container d-flex align-items-center justify-content-between">
          <a href="<?= htmlspecialchars($back_link) ?>" class="d-flex align-items-center text-dark text-decoration-none" aria-label="ย้อนกลับ"><i class="bi bi-arrow-left fs-2"></i></a>
          <div class="text-center position-absolute top-50 start-50 translate-middle"><span class="fw-bold fs-5 text-truncate px-5"><?= $page_title ?></span></div>
          <div class="d-flex align-items-center">
              <img src="./upload/profile/<?= htmlspecialchars($_SESSION['s_profile']); ?>" alt="โปรไฟล์" class="rounded-circle me-2" width="40" height="40">
              <span class="fw-bold fs-5 me-2 d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['s_role'] . ' ' . $_SESSION['s_name']); ?></span>
              <a href="./process/logout.php" class="text-danger fs-4 ms-1" aria-label="ออกจากระบบ"><i class="bi bi-box-arrow-right"></i></a>
          </div>
      </div>
    </nav>

    <div class="container my-5">
        <?php if (empty($visits)): ?>
            <div class="alert alert-info text-center mt-4">ไม่พบประวัติการดูแลสำหรับผู้ป่วยรายนี้</div>
        <?php else: ?>
            <ul class="timeline">
                <?php foreach ($visits as $visit): ?>
                    <li class="timeline-item">
                        <div class="timeline-icon"></i></div>
                        <div class="timeline-card" data-visit-id="<?= $visit['id'] ?>" role="button" aria-label="ดูรายละเอียดการเยี่ยมวันที่ <?= $visit['visit_date'] ?>">
                            <div class="d-flex justify-content-between align-items-start p-2">
                                <div>
                                    <h5 class="fw-bold mb-1">เยี่ยมวันที่: <?= toThaiDate($visit['visit_date']) ?></h5>
                                    <p class="text-muted mb-2 small"><i class="bi bi-person-check-fill me-1"></i> CM: <?= htmlspecialchars($visit['cm_fullname']) ?> | <i class="bi bi-person-fill me-1"></i> CG: <?= htmlspecialchars($visit['cg_fullname']) ?></p>
                                    <p class="mb-1"><i class="bi bi-activity me-2 text-danger"></i><strong>ความดัน:</strong> <?= htmlspecialchars($visit['bp_systolic'] . '/' . $visit['bp_diastolic']) ?> mmHg</p>
                                    <p class="mb-0 fst-italic text-secondary"><i class="bi bi-card-text me-2"></i><?= htmlspecialchars($visit['symptoms_preview']) ?>...</p>
                                </div>
                                <div class="d-flex flex-column align-items-end ms-3">
                                    <span class="badge <?= getAssessmentBadgeClass($visit['assessment_result']) ?> mb-1"><?= htmlspecialchars($visit['assessment_result']) ?></span>
                                    <span class="badge bg-primary">ADL: <?= $visit['adl_total'] ?></span>
                                </div>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="historyDetailModal" tabindex="-1" aria-labelledby="historyDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-4">
                    <div class="text-center position-absolute top-50 start-50 translate-middle">
                        <h5 class="modal-title fw-bold" id="historyDetailModalLabel">รายละเอียดการเยี่ยม</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div id="history-modal-loading" class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>
                    <div id="history-modal-content" style="display:none;"></div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include './components/toast.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
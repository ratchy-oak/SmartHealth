<?php
require_once('./connect/security_headers.php');
require_once('./connect/session.php');
require_once('./connect/connect.php');
require_once('./connect/functions.php');

if (!isset($_SESSION['s_username'])) {
    header("Location: ./index.php");
    exit();
}
$_SESSION['last_page'] = $_SERVER['REQUEST_URI'];

$patient_id = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);
if (!$patient_id) {
    header("Location: elderly_form.php?error=invalid_patient_id");
    exit();
}

$p_stmt = $conn->prepare("SELECT prefix, first_name, last_name, hospital_id, project_year FROM patient WHERE id = ?");
$p_stmt->bind_param("i", $patient_id);
$p_stmt->execute();
$patient = $p_stmt->get_result()->fetch_assoc();
$p_stmt->close();

if (!$patient) {
    header("Location: elderly_form.php?error=patient_not_found");
    exit();
}

// Authorization check
if ($_SESSION['s_role'] !== 'admin' && $patient['hospital_id'] != $_SESSION['s_hospital_id']) {
    header("Location: home.php?error=unauthorized");
    exit();
}

$page_title = "ประวัติการประเมิน " . htmlspecialchars($patient['prefix'] . $patient['first_name'] . ' ' . $patient['last_name']);
$back_link = "elderly_list.php?year=" . urlencode($patient['project_year']) . "&hospital_id=" . $patient['hospital_id'];

// Fetch all assessments for this patient for the timeline view
$assessments = [];
$sql = "
    SELECT 
        ea.id, 
        ea.assessment_date,
        ea.adl_total_score,
        ea.bp_systolic,
        ea.bp_diastolic,
        ea.adl_result_display,
        CONCAT(u.s_role, ' ', u.s_name, ' ', u.s_surname) as creator_fullname 
    FROM elderly_assessment ea
    LEFT JOIN user u ON ea.user_id = u.s_id 
    WHERE ea.patient_id = ? 
    ORDER BY ea.assessment_date DESC, ea.id DESC
";
$a_stmt = $conn->prepare($sql);
$a_stmt->bind_param("i", $patient_id);
$a_stmt->execute();
$result = $a_stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $assessments[] = $row;
}
$a_stmt->close();
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
<body class="page-patient-care-history page-elderly-history">
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
        <?php if (empty($assessments)): ?>
            <div class="alert alert-info text-center mt-4">ไม่พบประวัติการประเมินสำหรับผู้ป่วยรายนี้</div>
        <?php else: ?>
            <ul class="timeline">
                <?php foreach ($assessments as $item): ?>
                    <li class="timeline-item">
                        <div class="timeline-icon bg-primary"></div>
                        <div class="timeline-card" data-assessment-id="<?= $item['id'] ?>" role="button" tabindex="0">
                            <div class="d-flex justify-content-between align-items-start p-2">
                                <div>
                                    <h5 class="fw-bold mb-1">ประเมินวันที่: <?= toThaiDate($item['assessment_date']) ?></h5>
                                    <p class="text-muted mb-2 small"><i class="bi bi-person-check-fill me-1"></i> ผู้ประเมิน: <?= htmlspecialchars($item['creator_fullname'] ?? 'N/A') ?></p>
                                    <p class="mb-1"><i class="bi bi-activity me-2 text-danger"></i><strong>ความดัน:</strong> <?= htmlspecialchars($item['bp_systolic'] . '/' . $item['bp_diastolic']) ?> mmHg</p>
                                    <p class="mb-0 fst-italic text-secondary"><i class="bi bi-card-text me-2"></i>ผล ADL: <?= htmlspecialchars($item['adl_result_display'] ?? 'N/A') ?></p>
                                </div>
                                <div class="d-flex align-items-center ms-3">
                                    <span class="badge rounded-3 bg-primary">ADL: <?= $item['adl_total_score'] ?? 'N/A' ?></span>
                                </div>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="elderlyDetailModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-4">
                    <div class="text-center position-absolute top-50 start-50 translate-middle">
                        <h5 class="modal-title fw-bold">รายละเอียดการประเมินสุขภาพ</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div id="elderly-modal-loading" class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>
                    <div id="elderly-modal-content"></div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include './components/toast.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
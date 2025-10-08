<?php
require_once('./connect/security_headers.php');
require_once('./connect/session.php');
require_once('./connect/connect.php');
require_once('./connect/functions.php');

// Security check
if (!isset($_SESSION['s_username'])) {
    header("Location: ./index.php");
    exit();
}
$_SESSION['last_page'] = basename($_SERVER['PHP_SELF']);
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch Patients for selection list
$current_user_role = $_SESSION['s_role'];
$current_user_hospital_id = $_SESSION['s_hospital_id'] ?? null;
$patient_sql = "
    SELECT 
        p.id, p.prefix, p.first_name, p.last_name, 
        p.hospital_id,
        h.name as hospital_name
    FROM patient p
    JOIN hospital h ON p.hospital_id = h.id
    WHERE p.life_status = 'มีชีวิตอยู่'
";
if ($current_user_role !== 'admin' && !empty($current_user_hospital_id)) {
    $patient_sql .= " AND p.hospital_id = ?";
}
$patient_sql .= " ORDER BY h.name ASC, p.first_name ASC";
$stmt = $conn->prepare($patient_sql);
if ($current_user_role !== 'admin' && !empty($current_user_hospital_id)) {
    $stmt->bind_param("i", $current_user_hospital_id);
}
$stmt->execute();
$patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SmartHealth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>

<body class="page-request-equipment">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm py-3 sticky-top">
        <div class="container d-flex align-items-center justify-content-between">
            <a href="home.php" class="d-flex align-items-center text-dark text-decoration-none" aria-label="ย้อนกลับ"><i class="bi bi-arrow-left fs-2"></i></a>
            <div class="text-center position-absolute top-50 start-50 translate-middle"><span class="fs-5 fw-bold">ขอยืมอุปกรณ์</span></div>
            <div class="d-flex align-items-center">
                <img src="./upload/profile/<?= htmlspecialchars($_SESSION['s_profile']); ?>" alt="โปรไฟล์" class="rounded-circle me-2" width="40" height="40">
                <span class="fs-5 me-2 d-none d-md-inline"><?= htmlspecialchars($_SESSION['s_role'] . ' ' . $_SESSION['s_name']); ?></span>
                <a href="./process/logout.php" class="text-danger fs-4 ms-1" aria-label="ออกจากระบบ"><i class="bi bi-box-arrow-right"></i></a>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <form action="./process/request_equipment_process.php" method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" id="selected_patient_id" name="patient_id" required>
            <input type="hidden" id="selected_equipment_type_id" name="equipment_type_id" required>

            <div class="row g-4">
                <div class="col-lg-5" id="step1_card">
                    <div class="selection-card">
                        <h5 class="selection-header" aria-labelledby="patient_selection_summary">
                            <span class="step-circle" aria-hidden="true">1</span>
                            <span class="step-title">เลือกผู้ป่วย</span>
                            <span class="selection-summary" id="patient_selection_summary"></span>
                        </h5>
                        <div class="selection-body">
                            <div class="p-3"><input type="text" id="patientSearch" class="form-control" placeholder="ค้นหาชื่อผู้ป่วย..." aria-label="ค้นหาชื่อผู้ป่วย"></div>
                            <div class="list-group list-group-flush selection-list" id="patientList" role="listbox" aria-label="รายชื่อผู้ป่วย">
                                <?php
                                $current_hospital = null;
                                foreach ($patients as $patient):
                                    if ($_SESSION['s_role'] === 'admin' && $patient['hospital_name'] !== $current_hospital) {
                                        echo '<div class="list-group-header">' . htmlspecialchars($patient['hospital_name']) . '</div>';
                                        $current_hospital = $patient['hospital_name'];
                                    }
                                ?>
                                    <a href="#" class="py-3 list-group-item list-group-item-action" 
                                    data-patient-id="<?= $patient['id'] ?>" 
                                    data-hospital-id="<?= $patient['hospital_id'] ?>" 
                                    data-patient-name="<?= htmlspecialchars($patient['prefix'] . $patient['first_name'] . ' ' . $patient['last_name']) ?>">

                                        <div><?= htmlspecialchars($patient['prefix'] . ' ' . $patient['first_name'] . ' ' . $patient['last_name']) ?></div>
                                        <?php if ($_SESSION['s_role'] === 'admin'): ?>
                                            <div class="small text-muted"><?= htmlspecialchars($patient['hospital_name']) ?></div>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7 step-card is-disabled" id="step2_card">
                    <div class="selection-card">
                        <h5 class="selection-header" aria-labelledby="equipment_selection_summary">
                            <span class="step-circle" aria-hidden="true">2</span>
                            <span class="step-title">เลือกอุปกรณ์</span>
                            <span class="selection-summary" id="equipment_selection_summary"></span>
                        </h5>
                        <div class="selection-body">
                            <div class="p-3"><input type="text" id="equipmentSearch" class="form-control" placeholder="ค้นหาชื่ออุปกรณ์..." aria-label="ค้นหาชื่ออุปกรณ์"></div>
                            <div class="list-group list-group-flush selection-list" id="equipmentList" role="listbox" aria-live="polite" aria-label="รายการอุปกรณ์">
                                <div class="p-4 text-center text-muted">
                                    <i class="bi bi-arrow-left-square fs-3"></i>
                                    <p class="mt-2 mb-0">กรุณาเลือกผู้ป่วยเพื่อดูรายการอุปกรณ์</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4 step-card is-disabled" id="step3_card">
                <div class="col-12">
                    <div class="selection-card p-4">
                        <h5 class="selection-header"><span class="step-circle" aria-hidden="true">3</span>ยืนยันคำขอยืม</h5>
                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <input placeholder="วันที่ต้องการยืม" type="date" name="request_date" id="request_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <input placeholder="หมายเหตุ" type="text" name="notes" id="notes" class="form-control" placeholder="เช่น, ต้องการใช้งานเร่งด่วน">
                            </div>
                        </div>
                        <div class="text-center mt-5 mb-4">
                            <button type="submit" class="btn btn-dark px-5 py-2">ส่งคำขอยืม</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="page-requests-fab"><a href="approve_requests.php" class="btn btn-dark shadow-lg" role="button" aria-label="จัดการคำขอยืม"><i class="bi bi-list-check"></i></a></div>

    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmationModalLabel">ยืนยันการดำเนินการ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="confirmationModalBody">
                    </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-primary" id="confirmModalButton">ตกลง</button>
                </div>
            </div>
        </div>
    </div>                                

    <?php include './components/toast.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
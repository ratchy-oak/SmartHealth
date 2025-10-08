<?php
require_once('./connect/security_headers.php');
require_once('./connect/session.php');
include('./connect/connect.php');

if (!isset($_SESSION['s_username'])) { header("Location: ./index.php"); exit(); }
$_SESSION['last_page'] = basename($_SERVER['PHP_SELF']);

$current_user_role = $_SESSION['s_role'];
$current_user_hospital_id = $_SESSION['s_hospital_id'] ?? null;
$where_clause = ""; $and_where_clause = ""; $params = []; $types = "";
if ($current_user_role !== 'admin' && !empty($current_user_hospital_id)) {
    $where_clause = " WHERE p.hospital_id = ?";
    $and_where_clause = " AND p.hospital_id = ?";
    $params[] = $current_user_hospital_id;
    $types = "i";
}
$sql_total = "SELECT COUNT(*) AS count FROM patient p" . $where_clause; $stmt = $conn->prepare($sql_total); if (!empty($params)) $stmt->bind_param($types, ...$params); $stmt->execute(); $total = $stmt->get_result()->fetch_assoc()['count']; $stmt->close();
$sql_alive = "SELECT COUNT(*) AS count FROM patient p WHERE p.life_status = 'มีชีวิตอยู่'" . $and_where_clause; $stmt = $conn->prepare($sql_alive); if (!empty($params)) $stmt->bind_param($types, ...$params); $stmt->execute(); $alive = $stmt->get_result()->fetch_assoc()['count']; $stmt->close();
$sql_deceased = "SELECT COUNT(*) AS count FROM patient p WHERE p.life_status != 'มีชีวิตอยู่'" . $and_where_clause; $stmt = $conn->prepare($sql_deceased); if (!empty($params)) $stmt->bind_param($types, ...$params); $stmt->execute(); $deceased = $stmt->get_result()->fetch_assoc()['count']; $stmt->close();
$sql_ltc = "SELECT COUNT(*) AS count FROM patient p WHERE p.status = 'ผู้มีภาวะพึ่งพิงในโครงการ LTC'" . $and_where_clause; $stmt = $conn->prepare($sql_ltc); if (!empty($params)) $stmt->bind_param($types, ...$params); $stmt->execute(); $ltc = $stmt->get_result()->fetch_assoc()['count']; $stmt->close();
$sql_status = "SELECT p.status, COUNT(*) as count FROM patient p" . $where_clause . " GROUP BY p.status"; $stmt = $conn->prepare($sql_status); if (!empty($params)) $stmt->bind_param($types, ...$params); $stmt->execute(); $statusCounts = $stmt->get_result(); $statusData = []; while ($row = $statusCounts->fetch_assoc()) { $statusData[$row['status']] = $row['count']; } $stmt->close();
$sql_age = "SELECT CASE WHEN p.age BETWEEN 60 AND 69 THEN '60-69 ปี' WHEN p.age BETWEEN 70 AND 79 THEN '70-79 ปี' WHEN p.age BETWEEN 80 AND 89 THEN '80-89 ปี' WHEN p.age >= 90 THEN '90+ ปี' ELSE 'ต่ำกว่า 60' END AS age_group, COUNT(*) as count FROM patient p" . $where_clause . " GROUP BY age_group ORDER BY FIELD(age_group, 'ต่ำกว่า 60', '60-69 ปี', '70-79 ปี', '80-89 ปี', '90+ ปี')"; $stmt = $conn->prepare($sql_age); if (!empty($params)) $stmt->bind_param($types, ...$params); $stmt->execute(); $ageQuery = $stmt->get_result(); $ageData = []; while ($row = $ageQuery->fetch_assoc()) { $ageData['labels'][] = $row['age_group']; $ageData['data'][] = $row['count']; } $stmt->close();
$sql_adl = "SELECT CASE WHEN p.adl BETWEEN 0 AND 4 THEN 'ภาวะพึ่งพาโดยสมบูรณ์ (0-4)' WHEN p.adl BETWEEN 5 AND 8 THEN 'ภาวะพึ่งพารุนแรง (5-8)' WHEN p.adl BETWEEN 9 AND 11 THEN 'ภาวะพึ่งพาปานกลาง (9-11)' ELSE 'ไม่เป็นการพึ่งพา (12+)' END AS adl_group, COUNT(*) as count FROM patient p" . $where_clause . " GROUP BY adl_group ORDER BY FIELD(adl_group, 'ภาวะพึ่งพาโดยสมบูรณ์ (0-4)', 'ภาวะพึ่งพารุนแรง (5-8)', 'ภาวะพึ่งพาปานกลาง (9-11)', 'ไม่เป็นการพึ่งพา (12+)')"; $stmt = $conn->prepare($sql_adl); if (!empty($params)) $stmt->bind_param($types, ...$params); $stmt->execute(); $adlQuery = $stmt->get_result(); $adlData = []; while ($row = $adlQuery->fetch_assoc()) { $adlData['labels'][] = $row['adl_group']; $adlData['data'][] = $row['count']; } $stmt->close();
$sql_markers = "SELECT p.id, p.first_name, p.last_name, p.status, p.location FROM patient p WHERE p.location IS NOT NULL AND p.location != ''" . $and_where_clause; $stmt = $conn->prepare($sql_markers); if (!empty($params)) $stmt->bind_param($types, ...$params); $stmt->execute(); $patientMarkers = $stmt->get_result(); $locations = []; while ($row = $patientMarkers->fetch_assoc()) { $coords = explode(',', $row['location']); if (count($coords) === 2 && is_numeric(trim($coords[0])) && is_numeric(trim($coords[1]))) { $lat = trim($coords[0]); $lng = trim($coords[1]); if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) { $locations[] = [ 'id' => $row['id'], 'name' => $row['first_name'].' '.$row['last_name'], 'status' => $row['status'], 'lat' => $lat, 'lng' => $lng ]; } } } $stmt->close();
$sql_patients = "SELECT h.name AS hospital_name, p.id, p.prefix, p.first_name, p.last_name, p.age, p.status, p.life_status, p.subdistrict, p.district, p.province FROM patient p LEFT JOIN hospital h ON p.hospital_id = h.id" . $where_clause . " ORDER BY h.name ASC, p.first_name ASC"; $stmt = $conn->prepare($sql_patients); if (!empty($params)) $stmt->bind_param($types, ...$params); $stmt->execute(); $groupedPatients = $stmt->get_result(); $patientsByHospital = []; while ($row = $groupedPatients->fetch_assoc()) { $patientsByHospital[$row['hospital_name'] ?? 'ไม่ระบุโรงพยาบาล'][] = $row; } $stmt->close();
function getStatusClass($status) { return match ($status) { 'ผู้สูงอายุ' => 'bar-elderly', 'ผู้พิการ' => 'bar-disabled', 'ผู้มีภาวะพึ่งพิง' => 'bar-dependent', 'ผู้มีภาวะพึ่งพิงในโครงการ LTC' => 'bar-ltc', default => 'bar-default' }; }
function getStatusBadgeClass($status) { return match ($status) { 'ผู้สูงอายุ' => 'bg-danger-subtle text-danger-emphasis', 'ผู้พิการ' => 'bg-warning-subtle text-warning-emphasis', 'ผู้มีภาวะพึ่งพิง' => 'bg-info-subtle text-info-emphasis', 'ผู้มีภาวะพึ่งพิงในโครงการ LTC' => 'bg-success-subtle text-success-emphasis', default => 'bg-secondary-subtle text-secondary-emphasis' }; }
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
<body class="page-patient-info"
      data-status-counts="<?= htmlspecialchars(json_encode(['labels' => array_keys($statusData), 'data' => array_values($statusData)]), ENT_QUOTES, 'UTF-8') ?>"
      data-age-dist="<?= htmlspecialchars(json_encode($ageData ?? ['labels' => [], 'data' => []]), ENT_QUOTES, 'UTF-8') ?>"
      data-adl-dist="<?= htmlspecialchars(json_encode($adlData ?? ['labels' => [], 'data' => []]), ENT_QUOTES, 'UTF-8') ?>"
      data-locations="<?= htmlspecialchars(json_encode($locations), ENT_QUOTES, 'UTF-8') ?>">
    
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm py-3 sticky-top" role="navigation" aria-label="แถบนำทาง">
      <div class="container d-flex align-items-center justify-content-between">
          <a href="home.php" class="d-flex align-items-center text-dark text-decoration-none" aria-label="ย้อนกลับไปยังหน้าแรก"><i class="bi bi-arrow-left fs-2" aria-hidden="true"></i></a>
          <div class="text-center position-absolute top-50 start-50 translate-middle"><span class="fw-bold fs-5">ข้อมูลผู้สูงอายุและผู้มีภาวะพึ่งพิง</span></div>
          <div class="d-flex align-items-center">
              <img src="./upload/profile/<?php echo htmlspecialchars($_SESSION['s_profile']); ?>" alt="โปรไฟล์ของ <?php echo htmlspecialchars($_SESSION['s_name']); ?>" class="rounded-circle me-2" width="40" height="40">
              <span class="fw-bold fs-5 me-2 d-none d-md-inline" aria-current="user"><?php echo htmlspecialchars($_SESSION['s_role'] . ' ' . $_SESSION['s_name']); ?></span>
              <a href="./process/logout.php" class="text-danger fs-4 ms-1" aria-label="ออกจากระบบ"><i class="bi bi-box-arrow-right" aria-hidden="true"></i></a>
          </div>
      </div>
    </nav>
    <div class="patient-add-float">
        <a href="patient_add.php" class="btn btn-dark shadow" aria-label="เพิ่มข้อมูลผู้ป่วยใหม่"><i class="bi bi-plus-lg" aria-hidden="true"></i></a>
    </div>
    <div class="container mt-5">
        <section aria-label="สถิติผู้ป่วย" class="row g-4 stat-row">
            <?php $stats = [['value'=>$total,'label'=>'ผู้ป่วยทั้งหมด','icon'=>'people','bg'=>'primary'],['value'=>$alive,'label'=>'ยังมีชีวิตอยู่','icon'=>'heart-pulse','bg'=>'success'],['value'=>$deceased,'label'=>'เสียชีวิต','icon'=>'x-circle','bg'=>'danger'],['value'=>$ltc,'label'=>'โครงการ LTC','icon'=>'stars','bg'=>'info']]; foreach ($stats as $s): ?>
            <div class="col-6 col-md-3"><div class="stat-card text-center"><div class="icon-wrapper bg-<?= $s['bg'] ?>-subtle text-<?= $s['bg'] ?>"><i class="bi bi-<?= $s['icon'] ?>" aria-hidden="true"></i></div><div class="stat-value" data-count="<?= $s['value'] ?>">0</div><div class="stat-label"><?= $s['label'] ?></div></div></div>
            <?php endforeach; ?>
        </section>
        <div class="row mt-1 g-4">
            <div class="col-lg-4"><div class="card chart-card"><div class="card-header fw-semibold">สัดส่วนกลุ่มผู้ป่วย</div><div class="card-body"><canvas id="patientStatusChart"></canvas></div></div></div>
            <div class="col-lg-4"><div class="card chart-card"><div class="card-header fw-semibold">การกระจายตัวตามช่วงอายุ</div><div class="card-body"><canvas id="ageDistributionChart"></canvas></div></div></div>
            <div class="col-lg-4"><div class="card chart-card"><div class="card-header fw-semibold">ระดับความช่วยเหลือ (ADL Score)</div><div class="card-body"><canvas id="adlScoreChart"></canvas></div></div></div>
        </div>
        <div class="row mt-1 g-4">
            <div class="col-12"><div class="card"><div class="card-header fw-semibold">แผนที่แสดงตำแหน่งผู้ป่วย</div><div class="card-body p-2"><div id="patientMap" role="region"></div></div></div></div>
        </div>
        <form class="row g-2 g-md-3 mt-3 align-items-center patient-filter" role="search" aria-label="ฟอร์มกรองผู้ป่วย">
            <div class="col-12 col-md-5"><input type="text" id="searchPatient" class="form-control" placeholder="ค้นหาชื่อผู้ป่วย..."></div>
            <div class="col-12 col-md-4"><select id="statusFilter" class="form-control"><option value="">สถานะทั้งหมด</option><option value="ผู้สูงอายุ">ผู้สูงอายุ</option><option value="ผู้พิการ">ผู้พิการ</option><option value="ผู้มีภาวะพึ่งพิง">ผู้มีภาวะพึ่งพิง</option><option value="ผู้มีภาวะพึ่งพิงในโครงการ LTC">ผู้มีภาวะพึ่งพิงในโครงการ LTC</option></select></div>
            <div class="col-12 col-md-3"><button type="button" id="resetFilter" class="btn btn-outline-dark w-100">ล้างตัวกรอง</button></div>
        </form>

        <section class="mt-4" aria-label="รายชื่อผู้ป่วยตามโรงพยาบาล">
            <div class="card">
                <div class="card-header fw-semibold">ข้อมูลกลุ่มผู้ป่วยตาม รพ.สต.</div>
                <div class="card-body">
                    <div id="patient-list-container" aria-live="polite">
                        <?php foreach ($patientsByHospital as $hospital => $patients): ?>
                            <div class="mb-4 patient-group-section" id="<?= 'hospital_group_' . md5($hospital) ?>">
                                <h6 class="text-primary fw-bold"><?= htmlspecialchars($hospital) ?></h6>
                                <div class="row row-cols-1 row-cols-md-3 g-3">
                                    <?php foreach ($patients as $index => $p): ?>
                                        <div class="col <?= ($index >= 3) ? 'patient-card-hidden' : '' ?>">
                                            <div class="patient-card" id="patient-card-<?= $p['id'] ?>" tabindex="0" data-patient-id="<?= $p['id'] ?>" data-patient-name="<?= htmlspecialchars($p['prefix'] . $p['first_name'] . $p['last_name']) ?>" data-patient-status="<?= htmlspecialchars($p['status']) ?>">
                                                <h6 class="name-with-bar <?= getStatusClass($p['status']) ?>"><?= htmlspecialchars($p['prefix'] . ' ' . $p['first_name'] . ' ' . $p['last_name']) ?></h6>
                                                <small>อายุ: <?= $p['age'] ?> ปี</small><br>
                                                <small>กลุ่ม: <?= $p['status'] ?></small><br>
                                                <small>ที่อยู่: <?= htmlspecialchars($p['subdistrict'] . ', ' . $p['district'] . ', ' . $p['province']) ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($patients) > 3): ?>
                                    <div class="text-center mt-3"><button class="btn btn-sm btn-outline-primary load-more-btn" data-target-group="#<?= 'hospital_group_' . md5($hospital) ?>">แสดงทั้งหมด</button></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div id="map-tooltip" class="custom-map-tooltip"></div>
    <div class="modal fade patient-detail-modal" id="patientDetailModal">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="patientDetailModalLabel">รายละเอียดข้อมูลผู้ป่วย</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="modal-loading-state" class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>
                    <div id="modal-content-state" class="modal-content-state">
                        <div class="profile-header"><img id="modal-patient-photo" src="" alt="Patient Photo"><div class="info"><h4 id="modal-patient-fullname" class="fw-bold mb-1"></h4><p id="modal-patient-citizen-id" class="text-muted mb-2"></p><span id="modal-status-badge" class="badge rounded-pill"></span></div></div>
                        <div class="info-section"><h6><i class="bi bi-person-vcard me-2"></i>ข้อมูลทั่วไป</h6><div class="info-grid"><div class="info-item"><i class="bi bi-cake2"></i><div><span class="label">อายุ</span><strong id="modal-info-age" class="value"></strong></div></div><div class="info-item"><i class="bi bi-telephone"></i><div><span class="label">เบอร์โทรศัพท์</span><strong id="modal-info-phone" class="value"></strong></div></div><div class="info-item"><i class="bi bi-geo-alt"></i><div><span class="label">ที่อยู่</span><strong id="modal-info-address" class="value"></strong></div></div><div class="info-item"><i class="bi bi-shield-check"></i><div><span class="label">สิทธิการรักษา</span><strong id="modal-info-rights" class="value"></strong></div></div></div></div>
                        <div class="info-section"><h6><i class="bi bi-heart-pulse me-2"></i>ข้อมูลสุขภาพ</h6><div class="info-grid"><div class="info-item"><i class="bi bi-bandaid"></i><div><span class="label">โรคประจำตัว</span><strong id="modal-health-disease" class="value"></strong></div></div><div class="info-item"><i class="bi bi-exclamation-triangle"></i><div><span class="label">การแพ้ยา/อาหาร</span><strong id="modal-health-allergy" class="value"></strong></div></div><div class="info-item"><i class="bi bi-person-wheelchair"></i><div><span class="label">ประเภทความพิการ</span><strong id="modal-health-disability" class="value"></strong></div></div></div></div>
                        <div class="info-section"><h6><i class="bi bi-people me-2"></i>ข้อมูลการดูแล</h6><div class="info-grid"><div class="info-item"><i class="bi bi-speedometer2"></i><div><span class="label">ADL / TAI</span><strong id="modal-care-scores" class="value"></strong></div></div><div class="info-item"><i class="bi bi-person-check"></i><div><span class="label">CM ผู้ดูแล</span><strong id="modal-care-cm" class="value"></strong></div></div><div class="info-item"><i class="bi bi-person-check-fill"></i><div><span class="label">CG ผู้ดูแล</span><strong id="modal-care-cg" class="value"></strong></div></div><div class="info-item"><i class="bi bi-person-hearts"></i><div><span class="label">ญาติผู้ดูแล</span><strong id="modal-care-relative" class="value"></strong></div></div><div class="info-item"><i class="bi bi-journal-text"></i><div><span class="label">ความต้องการ</span><strong id="modal-care-needs" class="value"></strong></div></div></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include './components/toast.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
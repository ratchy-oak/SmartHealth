<?php
require_once('./connect/security_headers.php');
require_once('./connect/session.php');
include('./connect/connect.php');

if (!isset($_SESSION['s_username'])) { header("Location: ./index.php"); exit(); }
$_SESSION['last_page'] = $_SERVER['REQUEST_URI'];
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

$patient_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$patient_id) { header("Location: patient_form.php?error=invalid_patient_id"); exit(); }

$stmt = $conn->prepare("SELECT p.prefix, p.first_name, p.last_name, p.hospital_id, p.project_year, p.adl as initial_adl FROM patient p WHERE p.id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) { header("Location: patient_form.php?error=patient_not_found"); exit(); }

$current_user_role = $_SESSION['s_role'];
$user_hospital_id = $_SESSION['s_hospital_id'] ?? null;
if ($current_user_role !== 'admin' && $patient['hospital_id'] != $user_hospital_id) { header("Location: home.php?error=unauthorized"); exit(); }

$patient_hospital_id = $patient['hospital_id'];

$cg_stmt = $conn->prepare("SELECT s_id, s_prefix, s_name, s_surname FROM user WHERE s_role = 'cg' AND s_hospital_id = ? ORDER BY s_name ASC");
$cg_stmt->bind_param("i", $patient_hospital_id);
$cg_stmt->execute();
$cgUsers = $cg_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$cg_stmt->close();

$cm_stmt = $conn->prepare("SELECT s_id, s_prefix, s_name, s_surname FROM user WHERE s_role = 'cm' AND s_hospital_id = ? ORDER BY s_name ASC");
$cm_stmt->bind_param("i", $patient_hospital_id);
$cm_stmt->execute();
$cmUsers = $cm_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$cm_stmt->close();

$patient_fullname = htmlspecialchars($patient['prefix'] . $patient['first_name'] . ' ' . $patient['last_name']);
$page_title = "บันทึกการดูแล " . $patient_fullname;
$back_link = "patient_care_list.php?year=" . urlencode($patient['project_year']) . "&hospital_id=" . $patient['hospital_id'];

$adl_options_text = [
    "feeding" => ["2" => "2 ตักอาหารและช่วยตัวเองได้เป็นปกติ", "1" => "1 ตักอาหารเองได้ แต่ต้องมีคนช่วย", "0" => "0 ไม่สามารถตักอาหารเข้าปากได้"],
    "grooming" => ["1" => "1 ทำได้เอง", "0" => "0 ต้องการความช่วยเหลือ"],
    "transfer" => ["3" => "3 ทำได้เอง", "2" => "2 ต้องการความช่วยเหลือบ้าง เช่น ช่วยพยุง", "1" => "1 ต้องใช้คน 1 คนแข็งแรง หรือ 2 คนทั่วไปช่วยกันยกขึ้น", "0" => "0 ไม่สามารถนั่งได้ หรือใช้ 2 คนช่วยยกขึ้น"],
    "toilet" => ["2" => "2 ช่วยเหลือตัวเองได้ดี", "1" => "1 ทำเองได้บ้าง ต้องการความช่วยเหลือบางส่วน", "0" => "0 ช่วยตัวเองไม่ได้"],
    "mobility" => ["3" => "3 เดินหรือเคลื่อนที่ได้เอง", "2" => "2 เดินหรือเคลื่อนที่โดยมีคนช่วย", "1" => "1 ใช้รถเข็นให้เคลื่อนที่เอง (ไม่ต้องมีคนเข็นให้)", "0" => "0 เคลื่อนที่ไปไหนไม่ได้"],
    "dressing" => ["2" => "2 ช่วยตัวเองได้ดี", "1" => "1 ช่วยตัวเองได้ประมาณร้อยละ 50 ที่เหลือมีคนช่วย", "0" => "0 ต้องมีคนสวมใส่ให้"],
    "stairs" => ["2" => "2 ช่วยตัวเองได้", "1" => "1 ต้องการคนช่วย", "0" => "0 ไม่สามารถทำได้"],
    "bathing" => ["1" => "1 อาบน้ำได้เอง", "0" => "0 ต้องมีคนช่วยหรือทำให้"],
    "bowels" => ["2" => "2 กลั้นได้เป็นปกติ", "1" => "1 กลั้นไม่ได้บางครั้ง", "0" => "0 กลั้นไม่ได้ หรือต้องการการสวนอุจจาระอยู่เสมอ"],
    "bladder" => ["2" => "2 กลั้นได้เป็นปกติ", "1" => "1 กลั้นไม่ได้บางครั้ง", "0" => "0 กลั้นไม่ได้ หรือ ใส่สายสวนปัสสาวะ แต่ไม่สามารถดูแลเองได้"]
];

$adl_questions = [ 
    "feeding" => "1. Feeding (รับประทานอาหาร)", "grooming" => "2. Grooming (การล้างหน้า/หวีผม)", "transfer" => "3. Transfer (ลุกนั่ง)", 
    "toilet" => "4. Toilet use (การใช้ห้องน้ำ)", "mobility" => "5. Mobility (การเคลื่อนที่)", "dressing" => "6. Dressing (การสวมใส่เสื้อผ้า)", 
    "stairs" => "7. Stairs (การขึ้นลงบันได)", "bathing" => "8. Bathing (การอาบน้ำ)", "bowels" => "9. Bowels (การกลั้นอุจจาระ)", 
    "bladder" => "10. Bladder (การกลั้นปัสสาวะ)" 
];

$errors = $_SESSION['form_errors'] ?? [];
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SmartHealth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Kanit&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="page-patient-care-form">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm py-3 sticky-top">
      <div class="container d-flex align-items-center justify-content-between">
          <a href="<?= htmlspecialchars($back_link) ?>" class="d-flex align-items-center text-dark text-decoration-none" aria-label="ย้อนกลับ"><i class="bi bi-arrow-left fs-2"></i></a>
          <div class="text-center position-absolute top-50 start-50 translate-middle"><span class="fw-bold fs-5 text-truncate px-5"><?= $page_title ?></span></div>
          <div class="d-flex align-items-center">
              <img src="./upload/profile/<?= htmlspecialchars($_SESSION['s_profile']); ?>" alt="โปรไฟล์" class="rounded-circle me-2" width="40" height="40">
              <span class="fw-bold fs-5 me-2 d-none d-md-inline"><?= htmlspecialchars($_SESSION['s_role'] . ' ' . $_SESSION['s_name']); ?></span>
              <a href="./process/logout.php" class="text-danger fs-4 ms-1" aria-label="ออกจากระบบ"><i class="bi bi-box-arrow-right"></i></a>
          </div>
      </div>
    </nav>

    <div class="container py-4 my-4">
    <form action="process/care_visit_add_process.php" method="POST" class="p-4 p-md-5 mx-auto needs-validation" enctype="multipart/form-data" novalidate>
        <?php if (!empty($errors)) : ?>
            <div class="alert alert-danger" role="alert">
                <h5 class="alert-heading">พบข้อผิดพลาด!</h5>
                <p>กรุณาตรวจสอบข้อมูลในฟอร์มและลองอีกครั้ง</p>
                <hr>
                <ul class="mb-0 small">
                    <?php foreach ($errors as $error) : ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="patient_id" value="<?= htmlspecialchars($patient_id); ?>">
        <input type="hidden" name="created_by_user_id" value="<?= htmlspecialchars($_SESSION['s_id']); ?>">
        <input type="hidden" name="relative_signature" id="relative_signature_data">
        <input type="hidden" name="cm_signature" id="cm_signature_data">
        
        <div class="position-relative text-center mb-5"><hr class="section-divider"><span class="h5 bg-white px-4 position-relative z-2 fw-bold">กรอกข้อมูลเยี่ยม</span></div>
        <div class="row g-3 mb-3">
        <div class="col-md-6">
            <select name="cg_id" class="form-control" required aria-label="CG ที่ดูแล">
                <option value="" disabled selected>CG ที่ดูแล*</option>
                <?php foreach ($cgUsers as $cg): ?>
                    <option value="<?= $cg['s_id'] ?>" <?= (isset($formData['cg_id']) && $formData['cg_id'] == $cg['s_id']) ? 'selected' : '' ?>> <?= htmlspecialchars($cg['s_prefix'].$cg['s_name'].' '.$cg['s_surname']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="invalid-feedback">กรุณาเลือก CG</div>
        </div>
        <div class="col-md-6">
            <select name="cm_id" class="form-control" required aria-label="CM ที่ดูแล">
                <option value="" disabled selected>CM ที่ดูแล*</option>
                <?php foreach ($cmUsers as $cm): ?>
                    <option value="<?= $cm['s_id'] ?>" <?= (isset($formData['cm_id']) && $formData['cm_id'] == $cm['s_id']) ? 'selected' : '' ?>> <?= htmlspecialchars($cm['s_prefix'].$cm['s_name'].' '.$cm['s_surname']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="invalid-feedback">กรุณาเลือก CM</div>
        </div>
        </div>
        <div class="row g-3 mb-5">
        <div class="col-md-4"><input type="date" name="visit_date" class="form-control" value="<?= htmlspecialchars($formData['visit_date'] ?? date('Y-m-d')) ?>" required aria-label="วันที่ตรวจเยี่ยม"><div class="invalid-feedback">กรุณาระบุวันที่ตรวจเยี่ยม</div></div> <div class="col-md-3"><input type="time" name="start_time" id="start_time" class="form-control" value="<?= htmlspecialchars($formData['start_time'] ?? '') ?>" required placeholder="เวลาเข้าเยี่ยม*" aria-label="เวลาเข้าเยี่ยม"><div class="invalid-feedback">กรุณาระบุเวลาเข้าเยี่ยม</div></div> <div class="col-md-3"><input type="time" name="end_time" id="end_time" class="form-control" value="<?= htmlspecialchars($formData['end_time'] ?? '') ?>" required placeholder="เวลาสิ้นสุด*" aria-label="เวลาสิ้นสุด"><div class="invalid-feedback">กรุณาระบุเวลาสิ้นสุด</div></div> <div class="col-md-2"><input type="text" name="total_duration" id="total_duration" class="form-control" placeholder="รวมเวลา" readonly aria-label="รวมเวลา"></div>
        </div>

        <div class="position-relative text-center mb-5"><hr class="section-divider"><span class="h5 bg-white px-4 position-relative z-2 fw-bold">สัญญาณชีพและข้อมูลกายภาพ</span></div>
        <div class="row g-3 mb-3">
            <div class="col-md-4"><input type="number" step="0.1" name="weight_kg" id="weight_kg" class="form-control" placeholder="น้ำหนัก (kg)*" aria-label="น้ำหนัก (kg)" value="<?= htmlspecialchars($formData['weight_kg'] ?? '') ?>" required><div class="invalid-feedback">กรุณาระบุน้ำหนัก (kg)</div></div> <div class="col-md-4"><input type="number" name="height_cm" id="height_cm" class="form-control" placeholder="ส่วนสูง (cm)*" aria-label="ส่วนสูง (cm)" value="<?= htmlspecialchars($formData['height_cm'] ?? '') ?>" required><div class="invalid-feedback">กรุณาระบุส่วนสูง (cm)</div></div> <div class="col-md-4"><input type="text" id="bmi" name="bmi" class="form-control" placeholder="ดัชนีมวลกาย (BMI)" readonly aria-label="ดัชนีมวลกาย (BMI)"></div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-6"><input type="number" name="bp_systolic" id="bp_systolic" class="form-control" placeholder="ความดันโลหิต (ตัวบน)*" value="<?= htmlspecialchars($formData['bp_systolic'] ?? '') ?>" required aria-label="ความดันโลหิต (ตัวบน)"><div class="invalid-feedback">กรุณาระบุความดันโลหิต (ตัวบน)</div></div> <div class="col-md-6"><input type="number" name="bp_diastolic" id="bp_diastolic" class="form-control" placeholder="ความดันโลหิต (ตัวล่าง)*" value="<?= htmlspecialchars($formData['bp_diastolic'] ?? '') ?>" required aria-label="ความดันโลหิต (ตัวล่าง)"><div class="invalid-feedback">กรุณาระบุความดันโลหิต (ตัวล่าง)</div></div> </div>
        <div class="row g-3 mb-3">
            <div class="col-md-4"><input type="number" name="pulse_rate" id="pulse_rate" class="form-control" placeholder="อัตราการเต้นหัวใจ*" aria-label="อัตราการเต้นหัวใจ" value="<?= htmlspecialchars($formData['pulse_rate'] ?? '') ?>" required><div class="invalid-feedback">กรุณาระบุอัตราการเต้นหัวใจ</div></div> <div class="col-md-4"><input type="number" name="respiratory_rate" id="respiratory_rate" class="form-control" placeholder="อัตราการหายใจ*" aria-label="อัตราการหายใจ" value="<?= htmlspecialchars($formData['respiratory_rate'] ?? '') ?>" required><div class="invalid-feedback">กรุณาระบุอัตราการหายใจ</div></div> <div class="col-md-4"><input type="number" step="0.1" name="body_temp" id="body_temp" class="form-control" placeholder="อุณหภูมิร่างกาย (°C)*" aria-label="อุณหภูมิร่างกาย (°C)" value="<?= htmlspecialchars($formData['body_temp'] ?? '') ?>" required><div class="invalid-feedback">กรุณาระบุอุณหภูมิร่างกาย (°C)</div></div> </div>
        <div class="mb-5"><textarea name="symptoms_found" id="symptoms_found" class="form-control" rows="3" placeholder="ปัญหาและอาการที่พบ*" aria-label="ปัญหาและอาการที่พบ" required><?= htmlspecialchars($formData['symptoms_found'] ?? '') ?></textarea><div class="invalid-feedback">กรุณาระบุปัญหาและอาการที่พบ</div></div> <div class="position-relative text-center mb-5"><hr class="section-divider"><span class="h5 bg-white px-4 position-relative z-2 fw-bold">กิจกรรมการดูแล</span></div>
        <div class="mb-3"><textarea name="care_provided" id="care_provided" class="form-control" rows="3" placeholder="กิจกรรมที่ให้การดูแล*" required aria-label="กิจกรรมที่ให้การดูแล"><?= htmlspecialchars($formData['care_provided'] ?? '') ?></textarea><div class="invalid-feedback">กรุณาระบุกิจกรรมที่ให้การดูแล</div></div> <div class="mb-5">
        <p class="text-muted ms-1">ผลการประเมินการเปลี่ยนแปลง*</p>
        <div class="btn-group w-100" role="group" aria-label="ผลการประเมินการเปลี่ยนแปลง">
            <input type="radio" class="btn-check" name="assessment_result" id="res-good" value="ดีขึ้น" required <?= (isset($formData['assessment_result']) && $formData['assessment_result'] == 'ดีขึ้น') ? 'checked' : '' ?>> <label class="btn btn-outline-success" for="res-good"><i class="bi bi-graph-up-arrow"></i> ดีขึ้น</label>
            <input type="radio" class="btn-check" name="assessment_result" id="res-same" value="เท่าเดิม" required <?= (isset($formData['assessment_result']) && $formData['assessment_result'] == 'เท่าเดิม') ? 'checked' : '' ?>> <label class="btn btn-outline-warning" for="res-same"><i class="bi bi-arrow-right-short"></i> เท่าเดิม</label>
            <input type="radio" class="btn-check" name="assessment_result" id="res-bad" value="แย่ลง" required <?= (isset($formData['assessment_result']) && $formData['assessment_result'] == 'แย่ลง') ? 'checked' : '' ?>> <label class="btn btn-outline-danger" for="res-bad"><i class="bi bi-graph-down-arrow"></i> แย่ลง</label>
        </div>
        <div class="invalid-feedback">กรุณาเลือกผลการประเมิน</div> </div>

        <div class="position-relative text-center mb-5"><hr class="section-divider"><span class="h5 bg-white px-4 position-relative z-2 fw-bold">ประเมิน ADL</span></div>
        <div class="row g-3 mb-5">
        <?php foreach($adl_questions as $key => $value): ?>
            <div class="col-md-6">
                <select name="adl_<?= $key ?>" class="form-control adl-score" required aria-label="<?= $value ?>">
                    <option value="" disabled selected> <?= htmlspecialchars($value) ?>* </option>
                    <?php foreach($adl_options_text[$key] as $score => $text): ?>
                        <?php $fieldName = 'adl_' . $key; ?>
                        <option value="<?= $score ?>" <?= (isset($formData[$fieldName]) && $formData[$fieldName] == $score) ? 'selected' : '' ?>> <?= htmlspecialchars($text) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">กรุณาเลือก <?= htmlspecialchars($value) ?></div>
            </div>
        <?php endforeach; ?>
        <div class="col-md-4"><input type="text" id="adl_total" name="adl_total" class="form-control" placeholder="ADL ปัจจุบัน" readonly aria-label="ADL ปัจจุบัน"></div>
        <div class="col-md-4"><input type="text" id="adl_initial" class="form-control" value="ADL ก่อนหน้า: <?= htmlspecialchars($patient['initial_adl'] ?? 'N/A'); ?>" readonly aria-label="ADL เริ่มโครงการ"></div>
        <div class="col-md-4"><input type="text" id="adl_comparison" class="form-control" placeholder="ผลเปรียบเทียบ" readonly aria-label="ผลเปรียบเทียบ"></div>
        </div>

        <div class="position-relative text-center mb-5"><hr class="section-divider"><span class="h5 bg-white px-4 position-relative z-2 fw-bold">คัดกรองโรคซึมเศร้า (2Q+)</span></div>
        <div class="mb-3"><p class="text-muted ms-1">1. รู้สึกหดหู่ เศร้าหรือท้อแท้ สิ้นหวังหรือไม่*</p><div class="btn-group w-100" role="group" aria-label="รู้สึกหดหู่"><input type="radio" class="btn-check" name="q2_depressed" id="q1-yes" value="1" required <?= (isset($formData['q2_depressed']) && $formData['q2_depressed'] == '1') ? 'checked' : '' ?>><label class="btn btn-outline-warning" for="q1-yes">มี</label><input type="radio" class="btn-check" name="q2_depressed" id="q1-no" value="0" required <?= (isset($formData['q2_depressed']) && $formData['q2_depressed'] == '0') ? 'checked' : '' ?>><label class="btn btn-outline-success" for="q1-no">ไม่มี</label></div><div class="invalid-feedback">กรุณาเลือกคำตอบ</div></div> <div class="mb-3"><p class="text-muted ms-1">2. รู้สึกเบื่อ ทำอะไรก็ไม่เพลิดเพลิน*</p><div class="btn-group w-100" role="group" aria-label="รู้สึกเบื่อ"><input type="radio" class="btn-check" name="q2_anhedonia" id="q2-yes" value="1" required <?= (isset($formData['q2_anhedonia']) && $formData['q2_anhedonia'] == '1') ? 'checked' : '' ?>><label class="btn btn-outline-warning" for="q2-yes">มี</label><input type="radio" class="btn-check" name="q2_anhedonia" id="q2-no" value="0" required <?= (isset($formData['q2_anhedonia']) && $formData['q2_anhedonia'] == '0') ? 'checked' : '' ?>><label class="btn btn-outline-success" for="q2-no">ไม่มี</label></div><div class="invalid-feedback">กรุณาเลือกคำตอบ</div></div> <div class="mb-5"><p class="text-muted ms-1">3. อยากทำร้ายตัวเอง หรืออยากไม่มีชีวิตอยู่*</p><div class="btn-group w-100" role="group" aria-label="อยากทำร้ายตัวเอง"><input type="radio" class="btn-check" name="q2_self_harm" id="q3-yes" value="1" required <?= (isset($formData['q2_self_harm']) && $formData['q2_self_harm'] == '1') ? 'checked' : '' ?>><label class="btn btn-outline-danger" for="q3-yes">มี</label><input type="radio" class="btn-check" name="q2_self_harm" id="q3-no" value="0" required <?= (isset($formData['q2_self_harm']) && $formData['q2_self_harm'] == '0') ? 'checked' : '' ?>><label class="btn btn-outline-success" for="q3-no">ไม่มี</label></div><div class="invalid-feedback">กรุณาเลือกคำตอบ</div></div> <div class="position-relative text-center mb-5"><hr class="section-divider"><span class="h5 bg-white px-4 position-relative z-2 fw-bold">ยืนยันการเยี่ยม</span></div>
        <div class="row g-3">
            <div class="col-md-6 mb-3">
                <label for="visit_photo" class="form-label">ภาพการตรวจเยี่ยม*</label>
                <input type="file" name="visit_photo" id="visit_photo" class="form-control" accept="image/*" required>
                <small id="visit_photo_info" class="form-text text-muted">หากมีข้อผิดพลาด กรุณาเลือกไฟล์อีกครั้ง</small>
                <div class="invalid-feedback">กรุณาอัพโหลดภาพการเยี่ยม</div>
            </div>
            <div class="col-md-6 mb-3"><label for="next_visit_date" class="form-label">นัดครั้งต่อไป*</label><input type="date" name="next_visit_date" id="next_visit_date" class="form-control" value="<?= htmlspecialchars($formData['next_visit_date'] ?? '') ?>" required><div class="invalid-feedback">กรุณาระบุเวลานัดครั้งต่อไป</div></div> <div class="col-md-12 mb-3"><input type="text" name="relative_relationship" class="form-control" placeholder="ความเกี่ยวข้องกับผู้ป่วย*" aria-label="ความเกี่ยวข้องกับผู้ป่วย" value="<?= htmlspecialchars($formData['relative_relationship'] ?? '') ?>" required><div class="invalid-feedback">กรุณาระบุความเกี่ยวข้องกับผู้ป่วย</div></div> <div class="col-md-6 mb-3"><p class="text-muted ms-1 mb-1">ลายมือญาติ*</p><div class="signature-pad-wrapper"><canvas id="relative_signature_pad" class="signature-pad"></canvas></div><div class="invalid-feedback" style="display:none;">กรุณาลงลายมือชื่อ</div><button type="button" class="btn btn-sm btn-outline-secondary mt-1 clear-signature" data-target="relative_signature_pad">ล้าง</button></div>
            <div class="col-md-6 mb-3"><p class="text-muted ms-1 mb-1">ลายเซ็น CM*</p><div class="signature-pad-wrapper"><canvas id="cm_signature_pad" class="signature-pad"></canvas></div><div class="invalid-feedback" style="display:none;">กรุณาลงลายมือชื่อ</div><button type="button" class="btn btn-sm btn-outline-secondary mt-1 clear-signature" data-target="cm_signature_pad">ล้าง</button></div>
        </div>

        <div class="text-center mt-4">
        <button type="submit" class="btn btn-dark px-5 py-2">บันทึกข้อมูลการเยี่ยม</button>
        </div>
    </form>
    </div>
    
    <?php include './components/toast.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
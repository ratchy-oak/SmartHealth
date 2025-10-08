<?php
require_once('./connect/security_headers.php');
require_once('./connect/session.php');
include('./connect/connect.php');

if (!isset($_SESSION['s_username'])) { header("Location: ./index.php"); exit(); }
$_SESSION['last_page'] = $_SERVER['REQUEST_URI'];
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

$patient_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$patient_id) { header("Location: elderly_form.php?error=invalid_patient_id"); exit(); }

$stmt = $conn->prepare("SELECT p.prefix, p.first_name, p.last_name, p.hospital_id, p.project_year, p.status, p.age, p.citizen_id, p.house_no, p.village_no, p.subdistrict, p.district, p.province, p.birthdate, h.name as hospital_name FROM patient p LEFT JOIN hospital h ON p.hospital_id = h.id WHERE p.id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) { header("Location: elderly_form.php?error=patient_not_found"); exit(); }
if ($_SESSION['s_role'] !== 'admin' && $patient['hospital_id'] != $_SESSION['s_hospital_id']) { header("Location: home.php?error=unauthorized"); exit(); }

$page_title = "ประเมินสุขภาพ " . htmlspecialchars($patient['prefix'] . $patient['first_name'] . ' ' . $patient['last_name']);
$back_link = "elderly_list.php?year=" . urlencode($patient['project_year']) . "&hospital_id=" . $patient['hospital_id'];

$adl_options_text = [ "feeding" => ["2" => "2 ตักอาหารและช่วยตัวเองได้เป็นปกติ", "1" => "1 ตักอาหารเองได้ แต่ต้องมีคนช่วย", "0" => "0 ไม่สามารถตักอาหารเข้าปากได้"], "grooming" => ["1" => "1 ทำได้เอง", "0" => "0 ต้องการความช่วยเหลือ"], "transfer" => ["3" => "3 ทำได้เอง", "2" => "2 ต้องการความช่วยเหลือบ้าง เช่น ช่วยพยุง", "1" => "1 ต้องใช้คน 1 คนแข็งแรง หรือ 2 คนทั่วไปช่วยกันยกขึ้น", "0" => "0 ไม่สามารถนั่งได้ หรือใช้ 2 คนช่วยยกขึ้น"], "toilet" => ["2" => "2 ช่วยเหลือตัวเองได้ดี", "1" => "1 ทำเองได้บ้าง ต้องการความช่วยเหลือบางส่วน", "0" => "0 ช่วยตัวเองไม่ได้"], "mobility" => ["3" => "3 เดินหรือเคลื่อนที่ได้เอง", "2" => "2 เดินหรือเคลื่อนที่โดยมีคนช่วย", "1" => "1 ใช้รถเข็นให้เคลื่อนที่เอง (ไม่ต้องมีคนเข็นให้)", "0" => "0 เคลื่อนที่ไปไหนไม่ได้"], "dressing" => ["2" => "2 ช่วยตัวเองได้ดี", "1" => "1 ช่วยตัวเองได้ประมาณร้อยละ 50 ที่เหลือมีคนช่วย", "0" => "0 ต้องมีคนสวมใส่ให้"], "stairs" => ["2" => "2 ช่วยตัวเองได้", "1" => "1 ต้องการคนช่วย", "0" => "0 ไม่สามารถทำได้"], "bathing" => ["1" => "1 อาบน้ำได้เอง", "0" => "0 ต้องมีคนช่วยหรือทำให้"], "bowels" => ["2" => "2 กลั้นได้เป็นปกติ", "1" => "1 กลั้นไม่ได้บางครั้ง", "0" => "0 กลั้นไม่ได้ หรือต้องการการสวนอุจจาระอยู่เสมอ"], "bladder" => ["2" => "2 กลั้นได้เป็นปกติ", "1" => "1 กลั้นไม่ได้บางครั้ง", "0" => "0 กลั้นไม่ได้ หรือ ใส่สายสวนปัสสาวะ แต่ไม่สามารถดูแลเองได้"]];
$adl_questions = [ "feeding" => "1. Feeding", "grooming" => "2. Grooming", "transfer" => "3. Transfer", "toilet" => "4. Toilet use", "mobility" => "5. Mobility", "dressing" => "6. Dressing", "stairs" => "7. Stairs", "bathing" => "8. Bathing", "bowels" => "9. Bowels", "bladder" => "10. Bladder" ];
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
<body class="page-patient-care-form">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm py-3 sticky-top">
        <div class="container d-flex align-items-center justify-content-between">
            <a href="<?= htmlspecialchars($back_link) ?>" class="d-flex align-items-center text-dark text-decoration-none" aria-label="Back"><i class="bi bi-arrow-left fs-2"></i></a>
            <div class="text-center position-absolute top-50 start-50 translate-middle"><span class="fw-bold fs-5 text-truncate px-5"><?= $page_title ?></span></div>
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
    <div class="container py-4 my-4">
        <form action="process/elderly_assessment_add_process.php" method="POST" class="p-4 p-md-5 mx-auto needs-validation" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="patient_id" value="<?= htmlspecialchars($patient_id); ?>">
            <input type="hidden" name="project_year" value="<?= htmlspecialchars($patient['project_year']); ?>">
            <input type="hidden" name="hospital_id" value="<?= htmlspecialchars($patient['hospital_id']); ?>">

            <div class="position-relative text-center mb-5"><hr class="section-divider"><span class="h5 bg-white px-4 position-relative z-2 fw-bold">ข้อมูลประวัติส่วนตัว</span></div>
            <div class="row g-3">
              <div class="col-md-6"><input type="text" class="form-control" value="อายุ <?= htmlspecialchars($patient['age']) ?> ปี" readonly></div>
              <div class="col-md-6"><input type="text" class="form-control" value="เลขบัตร <?= htmlspecialchars($patient['citizen_id']) ?>" readonly></div>
              <div class="col-md-4"><input type="text" class="form-control" value="บ้านเลขที่ <?= htmlspecialchars($patient['house_no']) ?>" readonly></div>
              <div class="col-md-4"><input type="text" class="form-control" value="หมู่ <?= htmlspecialchars($patient['village_no']) ?>" readonly></div>
              <div class="col-md-4"><input type="text" class="form-control" value="ตำบล <?= htmlspecialchars($patient['subdistrict']) ?>" readonly></div>
              <div class="col-md-4"><input type="text" class="form-control" value="อำเภอ <?= htmlspecialchars($patient['district']) ?>" readonly></div>
              <div class="col-md-4"><input type="text" class="form-control" value="จังหวัด <?= htmlspecialchars($patient['province']) ?>" readonly></div>
              <div class="col-md-4"><input type="text" class="form-control" value="วันเกิด <?= htmlspecialchars($patient['birthdate']) ?>" readonly></div>
              <div class="col-md-6"><input type="text" class="form-control" value="<?= htmlspecialchars($patient['hospital_name']) ?>" readonly></div>
              <div class="col-md-6"><select name="patient_status_at_assessment" class="form-control" required><option value="ผู้สูงอายุ" <?= $patient['status'] == 'ผู้สูงอายุ' ? 'selected' : '' ?>>ผู้สูงอายุ*</option><option value="ผู้พิการ" <?= $patient['status'] == 'ผู้พิการ' ? 'selected' : '' ?>>ผู้พิการ</option><option value="ผู้มีภาวะพึ่งพิง" <?= $patient['status'] == 'ผู้มีภาวะพึ่งพิง' ? 'selected' : '' ?>>ผู้มีภาวะพึ่งพิง</option><option value="ผู้มีภาวะพึ่งพิงในโครงการ LTC" <?= $patient['status'] == 'ผู้มีภาวะพึ่งพิงในโครงการ LTC' ? 'selected' : '' ?>>ผู้มีภาวะพึ่งพิงในโครงการ LTC</option></select><div class="invalid-feedback">กรุณาเลือกสถานะ</div></div>
              <div class="col-md-12"><label class="form-label">วันที่ประเมิน*</label><input type="date" class="form-control" name="assessment_date" value="<?= date('Y-m-d') ?>" required><div class="invalid-feedback">กรุณาระบุวันที่ประเมิน</div></div>
            </div>

            <div class="position-relative text-center my-5"><hr class="section-divider"><span class="h5 bg-white px-4 position-relative z-2 fw-bold">ข้อมูลสุขภาพ</span></div>
            <div class="row g-3">
                <div class="col-md-3"><input type="number" step="0.1" name="weight_kg" id="weight_kg" class="form-control" placeholder="น้ำหนัก (kg)*" required><div class="invalid-feedback">กรุณาระบุน้ำหนัก</div></div>
                <div class="col-md-3"><input type="number" name="height_cm" id="height_cm" class="form-control" placeholder="ส่วนสูง (cm)*" required><div class="invalid-feedback">กรุณาระบุส่วนสูง</div></div>
                <div class="col-md-3"><input type="number" step="0.01" name="waist_cm" class="form-control" placeholder="รอบเอว (cm)*" required><div class="invalid-feedback">กรุณาระบุรอบเอว</div></div>
                <div class="col-md-3"><input type="text" name="bmi" id="bmi" class="form-control" placeholder="BMI (อัตโนมัติ)" readonly></div>
                <div class="col-md-6"><input type="number" name="bp_systolic" class="form-control" placeholder="ความดันโลหิต (ตัวบน)*" required><div class="invalid-feedback">กรุณาระบุความดัน (ตัวบน)</div></div>
                <div class="col-md-6"><input type="number" name="bp_diastolic" class="form-control" placeholder="ความดันโลหิต (ตัวล่าง)*" required><div class="invalid-feedback">กรุณาระบุความดัน (ตัวล่าง)</div></div>
            </div>

            <div class="position-relative text-center my-5"><hr class="section-divider"><span class="h5 bg-white px-4 position-relative z-2 fw-bold">การประเมินความสามารถในการทำกิจวัตรประจำวัน ADL</span></div>
            <div class="row g-3 mb-3">
              <?php foreach($adl_questions as $key => $value): ?>
                <div class="col-md-6"><select name="adl_<?= $key ?>" class="form-control adl-score" required><option value="" disabled selected><?= $value ?>*</option><?php foreach($adl_options_text[$key] as $score => $text): ?><option value="<?= $score ?>"><?= htmlspecialchars($text) ?></option><?php endforeach; ?></select><div class="invalid-feedback">กรุณาเลือกคะแนน <?= $value ?></div></div>
              <?php endforeach; ?>
            </div>
            <div class="row g-3"><div class="col-md-6"><input type="text" id="adl_total_score" name="adl_total_score" class="form-control" placeholder="ADL*" readonly required></div><div class="col-md-6"><input type="text" name="adl_result_display" id="adl_result_display" class="form-control" placeholder="ผล ADL" readonly></div></div>

            <div class="position-relative text-center my-5"><hr class="section-divider"><span class="h5 bg-white px-4 position-relative z-2 fw-bold">ประวัติการเจ็บป่วย</span></div>
            <table class="table table-bordered text-center">
              <tbody>
                <?php $diseases = ['chronic_diabetes' => 'โรคเบาหวาน*', 'chronic_liver' => 'โรคตับ*', 'chronic_heart' => 'โรคหัวใจ*', 'chronic_hypertension' => 'โรคความดันโลหิต*', 'chronic_stroke' => 'โรคอัมพาต*', 'chronic_dyslipidemia' => 'โรคไขมันผิดปกติ*'];
                foreach ($diseases as $key => $label): ?>
                <tr><td class="align-middle"><?= $label ?></td><td><div class="btn-group w-100" role="group"><input type="radio" class="btn-check" name="<?= $key ?>" value="มี" id="<?= $key ?>_yes" required><label class="btn btn-outline-secondary" for="<?= $key ?>_yes">มี</label><input type="radio" class="btn-check" name="<?= $key ?>" value="ไม่มี" id="<?= $key ?>_no" required><label class="btn btn-outline-secondary" for="<?= $key ?>_no">ไม่มี</label><input type="radio" class="btn-check" name="<?= $key ?>" value="ไม่เคยตรวจ" id="<?= $key ?>_na" required><label class="btn btn-outline-secondary" for="<?= $key ?>_na">ไม่เคยตรวจ</label></div><div class="invalid-feedback text-start mt-1">กรุณาเลือกคำตอบ</div></td></tr>
                <?php endforeach; ?>
                <tr><td class="align-middle">แพ้อาหาร*</td><td><div class="btn-group w-100" role="group"><input type="radio" class="btn-check" name="chronic_food_allergy" value="มี" id="fa_yes" required><label class="btn btn-outline-secondary" for="fa_yes">มี</label><input type="radio" class="btn-check" name="chronic_food_allergy" value="ไม่มี" id="fa_no" required><label class="btn btn-outline-secondary" for="fa_no">ไม่มี</label></div><div class="invalid-feedback text-start mt-1">กรุณาเลือกคำตอบ</div></td></tr>
              </tbody>
            </table>
            <div class="mb-3"><input type="text" name="chronic_other_diseases" class="form-control" placeholder="โรคประจำตัวอื่นๆ"></div>
            <div class="mb-3"><select name="chronic_illness_practice" class="form-control" required><option value="" disabled selected>วิธีการปฏิบัติหากมีประวัติการเจ็บป่วย*</option><option value="รับการรักษาอยู่/ปฏิบัติตามที่แพทย์แนะนำ">รับการรักษาอยู่/ปฏิบัติตามที่แพทย์แนะนำ</option><option value="รับการรักษาแต่ไม่สม่ำเสมอ">รับการรักษาแต่ไม่สม่ำเสมอ</option><option value="เคยรักษา ขณะนี้ไม่รักษา/หายาทานเอง">เคยรักษา ขณะนี้ไม่รักษา/หายาทานเอง</option></select><div class="invalid-feedback">กรุณาเลือกวิธีการปฏิบัติ</div></div>

            <div class="position-relative text-center my-5"><hr class="section-divider"><span class="h5 bg-white px-4 position-relative z-2 fw-bold">พฤติกรรม</span></div>
            <div class="mb-3"><label class="form-label">ชอบอาหารรสใด (เลือกได้มากกว่า 1)*</label><div class="p-3 border rounded" id="food_flavors_group"><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" value="หวาน" name="preferred_food_flavors[]" id="flavor_sweet"><label class="form-check-label" for="flavor_sweet">หวาน</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" value="เค็ม" name="preferred_food_flavors[]" id="flavor_salty"><label class="form-check-label" for="flavor_salty">เค็ม</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" value="มัน" name="preferred_food_flavors[]" id="flavor_oily"><label class="form-check-label" for="flavor_oily">มัน</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" value="ไม่ชอบทุกข้อ" name="preferred_food_flavors[]" id="flavor_none"><label class="form-check-label" for="flavor_none">ไม่ชอบทุกข้อ</label></div><input type="text" id="food_flavors_validation" class="d-none" style="width:1px;height:1px;" required><div class="invalid-feedback">กรุณาเลือกอย่างน้อยหนึ่งรายการ</div></div></div>
            <div class="mb-3"><select name="behavior_smoking" class="form-control" required><option value="" disabled selected>สูบบุหรี่หรือไม่*</option><option>ไม่สูบ</option><option>สูบ</option><option>เคยสูบแต่เลิกแล้ว</option></select><div class="invalid-feedback">กรุณาเลือกสถานะการสูบบุหรี่</div></div>
            <div class="mb-3"><select name="behavior_alcohol" class="form-control" required><option value="" disabled selected>ดื่มแอลกอฮอล์หรือไม่*</option><option>ไม่ดื่ม</option><option>ดื่ม</option><option>เคยดื่มแต่เลิกแล้ว</option></select><div class="invalid-feedback">กรุณาเลือกสถานะการดื่มแอลกอฮอล์</div></div>
            <div class="mb-3"><select name="behavior_exercise" class="form-control" required><option value="" disabled selected>การออกกำลังกาย*</option><option>ออกกำลังกายทุกวัน ครั้งละ 30 นาที</option><option>ออกกำลังกายสัปดาห์ละมากกว่า 3 ครั้งๆ ละ 30 นาทีสม่ำเสมอ</option><option>ออกกำลังกายสัปดาห์ละ 3 ครั้งๆ ละ 30 นาทีสม่ำเสมอ</option><option>ออกกำลังกายน้อยกว่าสัปดาห์ละ 3 ครั้ง</option><option>ไม่ออกกำลังกาย</option></select><div class="invalid-feedback">กรุณาเลือกสถานะการออกกำลังกาย</div></div>

            <div class="position-relative text-center my-5"><hr class="section-divider"><span class="h5 bg-white px-4 position-relative z-2 fw-bold">การคัดกรองสุขภาพช่องปาก</span></div>
            <?php $oral_questions = ['oral_chewing_problem' => 'มีปัญหาในการบดเคี้ยวผัก ผลไม้ หรือไม่?*', 'oral_loose_teeth' => 'มีฟันโยก เคี้ยวไม่ได้ หรือไม่?*', 'oral_cavities' => 'มีฟันผุเป็นรู รากฟันผุ หรือมีตอฟันค้างอยู่ในปาก หรือไม่?*', 'oral_bleeding_gums' => 'มีเลือดออกเวลาแปรงฟัน หรือไม่?*'];
            foreach($oral_questions as $key => $label): ?>
            <div class="mb-3"><label class="form-label"><?= $label ?></label><div class="btn-group w-100"><input type="radio" class="btn-check" name="<?= $key ?>" value="มี" id="<?= $key ?>_yes" required><label class="btn btn-outline-secondary" for="<?= $key ?>_yes">มี</label><input type="radio" class="btn-check" name="<?= $key ?>" value="ไม่มี" id="<?= $key ?>_no" required><label class="btn btn-outline-secondary" for="<?= $key ?>_no">ไม่มี</label></div><div class="invalid-feedback mt-1">กรุณาเลือกคำตอบ</div></div>
            <?php endforeach; ?>
            <div class="mb-3"><input type="text" name="oral_summary" id="oral_summary" class="form-control" placeholder="สรุปผลการคัดกรองสุขภาพช่องปาก" readonly></div>

            <div class="position-relative text-center my-5"><hr class="section-divider"><span class="h5 bg-white px-4 position-relative z-2 fw-bold">คัดกรองโรคซึมเศร้า ในช่วง 2 สัปดาห์ที่ผ่านมารวมวันนี้ด้วย</span></div>
            <?php $dep_questions = ['depression_sad' => 'หดหู่ เศร้า หรือท้อแท้สิ้นหวัง หรือไม่*', 'depression_bored' => 'เบื่อ ทำอะไรก็ไม่เพลิดเพลิน หรือไม่*', 'depression_self_harm' => 'อยากทำร้ายตัวเอง หรือไม่่อยากมีชีวิตอยู่ไหม*'];
            foreach($dep_questions as $key => $label): ?>
            <div class="mb-3"><label class="form-label"><?= $label ?></label><div class="btn-group w-100"><input type="radio" class="btn-check" name="<?= $key ?>" value="มี" id="<?= $key ?>_yes" required><label class="btn btn-outline-secondary" for="<?= $key ?>_yes">มี</label><input type="radio" class="btn-check" name="<?= $key ?>" value="ไม่มี" id="<?= $key ?>_no" required><label class="btn btn-outline-secondary" for="<?= $key ?>_no">ไม่มี</label></div><div class="invalid-feedback mt-1">กรุณาเลือกคำตอบ</div></div>
            <?php endforeach; ?>
            <div class="mb-3"><input type="text" name="depression_summary" id="depression_summary" class="form-control" placeholder="สรุปผลการคัดกรองโรคซึมเศร้า" readonly></div>
            <div class="mb-3"><input type="text" name="suicide_risk_summary" id="suicide_risk_summary" class="form-control" placeholder="สรุปผลภาวะเสี่ยงต่อการฆ่าตัวตาย" readonly></div>

            <div class="position-relative text-center my-5"><hr class="section-divider"><span class="h5 bg-white px-4 position-relative z-2 fw-bold">การประเมินทางสมอง (ตอบคำถาม 3 ข้อ)</span></div>
            <div class="mb-3"><select name="brain_assessment" class="form-control" required><option value="" disabled selected>การประเมินทางสมอง (ตอบคำถาม 3 ข้อ)*</option><option value="ปกติ กรณีตอบถูกหมด">ปกติ กรณีตอบถูกหมด</option><option value="ผิดปกติ กรณีที่ตอบผิด 1-2 ข้อ อาจมีปัญหาเรื่องความจำ">ผิดปกติ กรณีที่ตอบผิด 1-2 ข้อ อาจมีปัญหาเรื่องความจำ</option></select><div class="invalid-feedback">กรุณาเลือกผลการประเมินทางสมอง</div></div>
            
            <div class="position-relative text-center my-5"><hr class="section-divider"><span class="h5 bg-white px-4 position-relative z-2 fw-bold">การประเมินการพลัดตกหกล้ม</span></div>
            <div class="mb-3"><label class="form-label">มีประวัติล้มภายใน 6 เดือน หรือไม่*</label><div class="btn-group w-100"><input type="radio" class="btn-check" name="fall_history_6m" value="มี" id="fall_yes" required><label class="btn btn-outline-secondary" for="fall_yes">มี</label><input type="radio" class="btn-check" name="fall_history_6m" value="ไม่มี" id="fall_no" required><label class="btn btn-outline-secondary" for="fall_no">ไม่มี</label></div><div class="invalid-feedback mt-1">กรุณาเลือกประวัติการล้ม</div></div>
            <div class="mb-3"><label class="form-label">ผลการประเมินการพลัดตกหกล้ม*</label><div class="btn-group w-100" role="group"><input type="radio" class="btn-check" name="fall_risk_summary" id="risk_yes" value="เสี่ยง" required><label class="btn btn-outline-secondary" for="risk_yes">เสี่ยง</label><input type="radio" class="btn-check" name="fall_risk_summary" id="risk_no" value="ไม่เสี่ยง" required><label class="btn btn-outline-secondary" for="risk_no">ไม่เสี่ยง</label><input type="radio" class="btn-check" name="fall_risk_summary" id="risk_immobile" value="เดินไม่ได้" required><label class="btn btn-outline-secondary" for="risk_immobile">เดินไม่ได้</label></div><div class="invalid-feedback mt-1">กรุณาเลือกผลการประเมิน</div></div>

            <div class="position-relative text-center my-5"><hr class="section-divider"><span class="h5 bg-white px-4 position-relative z-2 fw-bold">การคัดกรองโรคข้อเข่าเสื่อม</span></div>
            <?php $knee_questions = ['knee_stiffness' => 'ข้อเข่าฝืด ตึงหลังตื่นนอนตอนเช้านานน้อยกว่า 30 นาที*', 'knee_crepitus' => 'เสียงดังกรอบแกรบในข้อเข่า*', 'knee_bone_pain' => 'กดเจ็บที่กระดูกข้อเข่า*', 'knee_walking_pain' => 'เจ็บเข่าเวลาเดินลงน้ำหนัก*'];
            foreach($knee_questions as $key => $label): ?>
            <div class="mb-3"><label class="form-label"><?= $label ?></label><div class="btn-group w-100"><input type="radio" class="btn-check" name="<?= $key ?>" value="ใช่" id="<?= $key ?>_yes" required><label class="btn btn-outline-secondary" for="<?= $key ?>_yes">ใช่</label><input type="radio" class="btn-check" name="<?= $key ?>" value="ไม่ใช่" id="<?= $key ?>_no" required><label class="btn btn-outline-secondary" for="<?= $key ?>_no">ไม่ใช่</label></div><div class="invalid-feedback mt-1">กรุณาเลือกคำตอบ</div></div>
            <?php endforeach; ?>
            <div class="mb-3"><input type="text" name="knee_summary" id="knee_summary" class="form-control" placeholder="ผลการคัดกรองโรคข้อเข่าเสื่อม" readonly></div>

            <div class="position-relative text-center my-5"><hr class="section-divider"><span class="h5 bg-white px-4 position-relative z-2 fw-bold">การคัดกรองตา</span></div>
            <div class="mb-3"><label class="form-label">แว่นตา*</label><div class="btn-group w-100"><input type="radio" class="btn-check" name="eye_glasses" value="ใส่แว่น" id="eye_yes" required><label class="btn btn-outline-secondary" for="eye_yes">ใส่แว่น</label><input type="radio" class="btn-check" name="eye_glasses" value="ไม่ใส่แว่น" id="eye_no" required><label class="btn btn-outline-secondary" for="eye_no">ไม่ใส่แว่น</label></div><div class="invalid-feedback mt-1">กรุณาเลือกข้อมูลแว่นตา</div></div>
            <div class="mb-3"><label class="form-label">ผ่าตัดสายตา*</label><div class="btn-group w-100" role="group"><input type="radio" class="btn-check" name="eye_surgery_history" value="เคย" id="surgery_yes" required><label class="btn btn-outline-secondary" for="surgery_yes">เคย</label><input type="radio" class="btn-check" name="eye_surgery_history" value="ไม่เคย" id="surgery_no" required><label class="btn btn-outline-secondary" for="surgery_no">ไม่เคย</label></div><div class="invalid-feedback mt-1">กรุณาเลือกประวัติการผ่าตัด</div></div>
            <div class="mb-3" id="eye_surgery_side_wrapper" style="display: none;"><label class="form-label">ผ่าตัดสายตาข้าง*</label><div class="btn-group w-100" role="group"><input type="radio" class="btn-check" name="eye_surgery_side" id="side_left" value="ซ้าย"><label class="btn btn-outline-secondary" for="side_left">ซ้าย</label><input type="radio" class="btn-check" name="eye_surgery_side" id="side_right" value="ขวา"><label class="btn btn-outline-secondary" for="side_right">ขวา</label></div><div class="invalid-feedback mt-1">กรุณาเลือกข้างที่ผ่าตัด</div></div>
            <div class="mb-3"><label class="form-label">การแปลผลการคัดกรองสายตา (เลือกได้มากกว่า 1)*</label><div class="p-3 border rounded" id="eye_exam_result_group"><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" value="ตาขวาปกติ" name="eye_exam_result[]" id="eye_right_normal"><label class="form-check-label" for="eye_right_normal">ตาขวาปกติ</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" value="ตาขวาผิดปกติ" name="eye_exam_result[]" id="eye_right_abnormal"><label class="form-check-label" for="eye_right_abnormal">ตาขวาผิดปกติ</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" value="ตาซ้ายปกติ" name="eye_exam_result[]" id="eye_left_normal"><label class="form-check-label" for="eye_left_normal">ตาซ้ายปกติ</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" value="ตาซ้ายผิดปกติ" name="eye_exam_result[]" id="eye_left_abnormal"><label class="form-check-label" for="eye_left_abnormal">ตาซ้ายผิดปกติ</label></div><input type="text" id="eye_exam_validation_input" class="d-none" style="width:1px;height:1px;" required><div class="invalid-feedback">กรุณาเลือกอย่างน้อยหนึ่งรายการ</div></div></div>

            <div class="position-relative text-center my-5"><hr class="section-divider"><span class="h5 bg-white px-4 position-relative z-2 fw-bold">การคัดกรองวัณโรค</span></div>
             <?php $tb_questions = ['tb_fever' => 'มีไข้บ่ายๆ ตั้งแต่ 2 สัปดาห์ขึ้นไป*', 'tb_cough' => 'มีอาการไอติดต่อกันเกิน 2 สัปดาห์*', 'tb_bloody_cough' => 'มีอาการไอมีเลือดปน*', 'tb_weight_loss' => 'น้ำหนักลด 3-5 กก. ใน 1 เดือน*', 'tb_night_sweats' => 'เหงื่อออกผิดปกติตอนกลางคืน*'];
            foreach($tb_questions as $key => $label): ?>
            <div class="mb-3"><label class="form-label"><?= $label ?></label><div class="btn-group w-100"><input type="radio" class="btn-check" name="<?= $key ?>" value="มี" id="<?= $key ?>_yes" required><label class="btn btn-outline-secondary" for="<?= $key ?>_yes">มี</label><input type="radio" class="btn-check" name="<?= $key ?>" value="ไม่มี" id="<?= $key ?>_no" required><label class="btn btn-outline-secondary" for="<?= $key ?>_no">ไม่มี</label></div><div class="invalid-feedback mt-1">กรุณาเลือกคำตอบ</div></div>
            <?php endforeach; ?>
            <div class="mb-3"><input type="text" name="tb_summary" id="tb_summary" class="form-control" placeholder="สรุปผลการคัดกรองวัณโรค" readonly></div>

            <div class="position-relative text-center my-5"><hr class="section-divider"><span class="h5 bg-white px-4 position-relative z-2 fw-bold">คัดกรองมะเร็งลำไส้ใหญ่</span></div>
            <div class="mb-3"><select name="colon_cancer_screening" id="colon_cancer_screening_select" class="form-control" required><option value="" disabled selected>คัดกรองมะเร็งลำไส้ใหญ่*</option><option value="ปกติ">ปกติ</option><option value="ผิดปกติ มีอาการ 2 ข้อขึ้นไป">ผิดปกติ มีอาการ 2 ข้อขึ้นไป</option><option value="ปวดท้องเรื้อรัง">ปวดท้องเรื้อรัง</option><option value="ปวดเบ่งอย่างถ่ายแต่ถ่ายไม่สุด">ปวดเบ่งอย่างถ่ายแต่ถ่ายไม่สุด</option><option value="ถ่ายอุจจาระมีเลือดปน">ถ่ายอุจจาระมีเลือดปน</option><option value="อุจจาระผิดปกติ ท้องผูกสลับท้องเสีย">อุจจาระผิดปกติ ท้องผูกสลับท้องเสีย</option><option value="ซีดไม่ทราบสาเหตุ">ซีดไม่ทราบสาเหตุ</option><option value="ท้องเสียหรือท้องผูกเรื้อรังมากกว่า 3 เดือน">ท้องเสียหรือท้องผูกเรื้อรังมากกว่า 3 เดือน</option><option value="น้ำหนักลดไม่ทราบสาเหตุ">น้ำหนักลดไม่ทราบสาเหตุ</option><option value="มีประวัติว่าญาติพี่น้องป่วยด้วยโรคมะเร็งลำไส้ใหญ่">มีประวัติว่าญาติพี่น้องป่วยด้วยโรคมะเร็งลำไส้ใหญ่</option></select><div class="invalid-feedback">กรุณาเลือกผลการคัดกรอง</div></div>
            <div class="mb-3"><input type="text" name="colon_cancer_summary" id="colon_cancer_summary" class="form-control" placeholder="สรุปผลคัดกรองมะเร็งลำไส้ใหญ่" readonly></div>

            <div class="mt-5 mb-3"><label for="assessment_photo" class="form-label">ภาพตรวจประเมิน*</label><input type="file" name="assessment_photo" id="assessment_photo" class="form-control" accept="image/*" required><div class="invalid-feedback">กรุณาอัพโหลดภาพตรวจประเมิน</div></div>

            <div class="text-center mt-5"><button type="submit" class="btn btn-dark px-5 py-2">บันทึกการประเมิน</button></div>
        </form>
    </div>

    <?php include './components/toast.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
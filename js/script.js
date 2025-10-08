document.addEventListener('DOMContentLoaded', () => {
  initApp();
});

function initApp() {
  setupPhoneToggles();
  setupDistrictToggles();
  setupLiveFilter();
  setupPasswordMatchCheck();
  setupRoleBasedFields();
  setupToast();
  
  if (document.querySelector('.needs-validation')) {
    setupFormValidation(); 
    initCareAndAssessmentForms();
  }

  if (document.body.classList.contains('page-patient-info')) {
      console.log("Initializing Patient Info Page scripts...");
      setupStatCounters();
      setupWcagPatientCards();
      setupPatientFilters();
      setupLoadMorePatients();
      setupPatientCardModals();
      initializeDashboardCharts();
      loadGoogleMapsScript();
  }

  if (document.body.classList.contains('page-patient-add')) {
    console.log("Initializing Patient Add Page scripts...");
    loadGoogleMapsScript();
  }

  if (document.body.classList.contains('page-patient-care-history')) {
      console.log("Initializing Patient Care History scripts...");
      setupCareHistoryModals();
  }

  if (document.body.classList.contains('page-elderly-history')) {
    console.log("Initializing Elderly History Page scripts...");
    setupElderlyHistoryModals();
  }

  if (document.body.classList.contains('page-equipment')) {
    console.log("Initializing Equipment Page scripts...");
    setupAutoSubmittingFilters(); 
    setupEquipmentModal();
  }

  if (document.querySelector('.page-equipment-add')) {
    setupEquipmentFileInput();
    setupEquipmentTypeSelection();
  }

  if (document.body.classList.contains('page-request-equipment')) {
    console.log("Initializing Request Equipment Page scripts...");
    setupRequestEquipmentPage();
  }

  if (document.body.classList.contains('page-approve-requests')) {
    console.log("Initializing Approve Requests Page scripts...");
    setupApprovalActions();
    setupReturnActions();
  }

  if (document.body.classList.contains('page-dashboard-ltc')) {
    console.log("Initializing Enhanced LTC Dashboard scripts...");
    initializeLtcDashboard();
  }
}

function setupFormValidation() {
  const form = document.querySelector('.needs-validation');
  if (!form) return;

  form.addEventListener('submit', function (event) {
    let isFormValid = true;

    const radioGroups = {};
    form.querySelectorAll('input[type="radio"][required]').forEach(radio => {
        if (!radioGroups[radio.name]) {
            radioGroups[radio.name] = false;
        }
        if (radio.checked) {
            radioGroups[radio.name] = true;
        }
    });

    for (const groupName in radioGroups) {
        if (!radioGroups[groupName]) {
            isFormValid = false;
            const groupContainer = form.querySelector(`input[name="${groupName}"]`).closest('.btn-group');
            if (groupContainer) {
                groupContainer.classList.add('is-invalid');
            }
        }
    }
 
    validateCheckboxGroup(document.getElementById('food_flavors_group'));
    validateCheckboxGroup(document.getElementById('eye_exam_result_group'));

    if (!form.checkValidity() || !isFormValid) {
      event.preventDefault();
      event.stopPropagation();
    }
    
    form.classList.add('was-validated');
  }, false);

  form.querySelectorAll('.form-control[required], .form-select[required]').forEach(input => {
    const handler = () => {
      if (form.classList.contains('was-validated')) {
        input.checkValidity() ? input.classList.remove('is-invalid') : input.classList.add('is-invalid');
      }
    };
    input.addEventListener('input', handler);
    input.addEventListener('change', handler);
  });

  form.querySelectorAll('input[type="radio"][required]').forEach(radio => {
    radio.addEventListener('change', () => {
      const groupContainer = radio.closest('.btn-group');
      if (groupContainer && groupContainer.classList.contains('is-invalid')) {
        groupContainer.classList.remove('is-invalid');
      }
    });
  });

  const checkboxGroups = [
    document.getElementById('food_flavors_group'),
    document.getElementById('eye_exam_result_group')
  ].filter(el => el);

  checkboxGroups.forEach(groupContainer => {
    const checkboxes = groupContainer.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
      checkbox.addEventListener('change', () => {
        validateCheckboxGroup(groupContainer);
        const validationInput = groupContainer.querySelector('input[type="text"][required]');
        if (validationInput.value) { // if it's now valid
            groupContainer.classList.remove('is-invalid');
        }
      });
    });
  });
}

function validateCheckboxGroup(groupContainer) {
    if (!groupContainer) return;
    const validationInput = groupContainer.querySelector('input[type="text"][required]');
    if (!validationInput) return;
    const checkboxes = groupContainer.querySelectorAll('input[type="checkbox"]');
    const isAnyChecked = Array.from(checkboxes).some(cb => cb.checked);
    validationInput.value = isAnyChecked ? 'valid' : '';
}

function initCareAndAssessmentForms() {
  setupCareFormTimeCalculation();
  setupCareFormBmiCalculation();
  setupCareFormFileInput();
  const signaturePads = setupCareFormSignaturePads();
  const form = document.querySelector('.page-patient-care-form .needs-validation');
  if (form) {
    setupCareFormSubmission(form, signaturePads);
  }
  setupAdlCalculation();
  setupOralHealthScreening();
  setupDepressionScreening();
  setupKneeScreening();
  setupEyeSurgeryLogic();
  setupTBScreening();
  setupColonCancerScreening();
  setupFlavorCheckboxes();
}

function setupAdlCalculation() {
    const adlSelects = document.querySelectorAll('.adl-score');
    if (adlSelects.length === 0) return;
    const adlTotalLtcEl = document.getElementById('adl_total');
    const adlInitialEl = document.getElementById('adl_initial');
    const adlComparisonEl = document.getElementById('adl_comparison');
    const adlTotalElderlyEl = document.getElementById('adl_total_score');
    const adlResultDisplayEl = document.getElementById('adl_result_display');
    const calculateAdl = () => {
        let total = 0;
        adlSelects.forEach(select => { total += Number(select.value) || 0; });
        if (adlTotalLtcEl) adlTotalLtcEl.value = total;
        if (adlTotalElderlyEl) adlTotalElderlyEl.value = total;
        if (adlTotalLtcEl && adlComparisonEl && adlInitialEl) { const initialAdlText = adlInitialEl.value || "0"; const initialAdl = parseInt(initialAdlText.split(':')[1].trim(), 10); if (!isNaN(initialAdl)) { if (total > initialAdl) adlComparisonEl.value = 'ดีขึ้น'; else if (total < initialAdl) adlComparisonEl.value = 'แย่ลง'; else adlComparisonEl.value = 'เท่าเดิม'; } }
        if (adlResultDisplayEl) { if (total >= 12) adlResultDisplayEl.value = 'ไม่เป็นการพึ่งพา (12-20 คะแนน)'; else if (total >= 9) adlResultDisplayEl.value = 'ภาวะพึ่งพาปานกลาง (9-11 คะแนน)'; else if (total >= 5) adlResultDisplayEl.value = 'ภาวะพึ่งพารุนแรง (5-8 คะแนน)'; else if (total >= 0) adlResultDisplayEl.value = 'ภาวะพึ่งพาโดยสมบูรณ์ (0-4 คะแนน)'; else adlResultDisplayEl.value = ''; }
    };
    adlSelects.forEach(select => select.addEventListener('change', calculateAdl));
    calculateAdl();
}
function setupOralHealthScreening() {
  const oralRadios = document.querySelectorAll('input[name="oral_chewing_problem"], input[name="oral_loose_teeth"], input[name="oral_cavities"], input[name="oral_bleeding_gums"]');
  const summaryEl = document.getElementById('oral_summary');
  if (!summaryEl || oralRadios.length === 0) return;
  const updateOralSummary = () => { let hasProblem = false; document.querySelectorAll('input[name^="oral_"]:checked').forEach(radio => { if (radio.value === 'มี') hasProblem = true; }); summaryEl.value = hasProblem ? 'มีปัญหาสุขภาพช่องปาก' : 'ไม่มีปัญหาสุขภาพช่องปาก'; };
  oralRadios.forEach(radio => radio.addEventListener('change', updateOralSummary)); updateOralSummary();
}
function setupDepressionScreening() {
  const sadRadios = document.querySelectorAll('input[name="depression_sad"]');
  const boredRadios = document.querySelectorAll('input[name="depression_bored"]');
  const selfHarmRadios = document.querySelectorAll('input[name="depression_self_harm"]');
  const depressionSummaryEl = document.getElementById('depression_summary');
  const suicideRiskSummaryEl = document.getElementById('suicide_risk_summary');
  if (!depressionSummaryEl || !suicideRiskSummaryEl || sadRadios.length === 0) return;
  const updateSummaries = () => { const sadYes = document.querySelector('input[name="depression_sad"]:checked')?.value === 'มี'; const boredYes = document.querySelector('input[name="depression_bored"]:checked')?.value === 'มี'; depressionSummaryEl.value = (sadYes || boredYes) ? 'มีภาวะซึมเศร้า' : 'ไม่มีภาวะซึมเศร้า'; const selfHarmYes = document.querySelector('input[name="depression_self_harm"]:checked')?.value === 'มี'; suicideRiskSummaryEl.value = selfHarmYes ? 'มีความเสี่ยงต่อการฆ่าตัวตาย' : 'ไม่มีความเสี่ยงต่อการฆ่าตัวตาย'; };
  [...sadRadios, ...boredRadios, ...selfHarmRadios].forEach(radio => radio.addEventListener('change', updateSummaries)); updateSummaries();
}
function setupKneeScreening() {
  const kneeRadios = document.querySelectorAll('input[name="knee_stiffness"], input[name="knee_crepitus"], input[name="knee_bone_pain"], input[name="knee_walking_pain"]');
  const summaryEl = document.getElementById('knee_summary');
  if (!summaryEl || kneeRadios.length === 0) return;
  const updateKneeSummary = () => { let yesCount = 0; ['knee_stiffness', 'knee_crepitus', 'knee_bone_pain', 'knee_walking_pain'].forEach(name => { const isYes = document.querySelector(`input[name="${name}"]:checked`)?.value === 'ใช่'; if (isYes) yesCount++; }); summaryEl.value = (yesCount >= 2) ? 'สงสัยข้อเข่าเสื่อม' : 'ไม่สงสัยข้อเข่าเสื่อม'; };
  kneeRadios.forEach(radio => radio.addEventListener('change', updateKneeSummary)); updateKneeSummary();
}
function setupEyeSurgeryLogic() {
  const surgeryRadios = document.querySelectorAll('input[name="eye_surgery_history"]');
  const sideWrapper = document.getElementById('eye_surgery_side_wrapper');
  if (!sideWrapper || surgeryRadios.length === 0) return;
  const toggleSideSelector = () => { const surgeryYes = document.querySelector('input[name="eye_surgery_history"]:checked'); sideWrapper.style.display = (surgeryYes && surgeryYes.value === 'เคย') ? 'block' : 'none'; };
  surgeryRadios.forEach(radio => radio.addEventListener('change', toggleSideSelector)); toggleSideSelector();
}
function setupTBScreening() {
  const tbRadios = document.querySelectorAll('input[name="tb_fever"], input[name="tb_cough"], input[name="tb_bloody_cough"], input[name="tb_weight_loss"], input[name="tb_night_sweats"]');
  const summaryEl = document.getElementById('tb_summary');
  if (!summaryEl || tbRadios.length === 0) return;
  const updateTBSummary = () => { let yesCount = 0; ['tb_fever', 'tb_cough', 'tb_bloody_cough', 'tb_weight_loss', 'tb_night_sweats'].forEach(name => { const isYes = document.querySelector(`input[name="${name}"]:checked`)?.value === 'มี'; if (isYes) yesCount++; }); summaryEl.value = (yesCount >= 3) ? 'สงสัยวัณโรค' : 'ไม่สงสัยวัณโรค'; };
  tbRadios.forEach(radio => radio.addEventListener('change', updateTBSummary)); updateTBSummary();
}
function setupColonCancerScreening() {
  const screeningSelect = document.getElementById('colon_cancer_screening_select');
  const summaryEl = document.getElementById('colon_cancer_summary');
  if (!screeningSelect || !summaryEl) return;
  const updateSummary = () => { const selectedValue = screeningSelect.value; if (!selectedValue) { summaryEl.value = ''; return; } summaryEl.value = (selectedValue === 'ผิดปกติ มีอาการ 2 ข้อขึ้นไป') ? 'ผิดปกติ' : 'ปกติ'; };
  screeningSelect.addEventListener('change', updateSummary); updateSummary();
}
function setupFlavorCheckboxes() {
    const flavorCheckboxes = document.querySelectorAll('input[name="preferred_food_flavors[]"]');
    const noneCheckbox = document.getElementById('flavor_none');
    const otherFlavorCheckboxes = Array.from(flavorCheckboxes).filter(cb => cb.id !== 'flavor_none');
    const validationInput = document.getElementById('food_flavors_validation');
    if (!noneCheckbox || !validationInput) return;
    const updateFlavorState = () => {
        const anyOtherChecked = otherFlavorCheckboxes.some(cb => cb.checked);
        if (noneCheckbox.checked) {
            otherFlavorCheckboxes.forEach(cb => { cb.checked = false; cb.disabled = true; });
        } else {
            otherFlavorCheckboxes.forEach(cb => { cb.disabled = false; });
        }
        noneCheckbox.disabled = anyOtherChecked;
        validationInput.value = (anyOtherChecked || noneCheckbox.checked) ? 'valid' : '';
    };
    flavorCheckboxes.forEach(cb => cb.addEventListener('change', updateFlavorState));
    updateFlavorState();
}

function setupPhoneToggles() {
  document.querySelectorAll(".phone-toggle").forEach(icon => icon.addEventListener("click", () => {
      const targetId = icon.getAttribute("data-toggle-target");
      const phoneEl = document.getElementById(targetId);
      if (phoneEl) phoneEl.classList.toggle("show");
  }));
}

function setupDistrictToggles() {
  document.querySelectorAll(".district-toggle").forEach(toggle => toggle.addEventListener("click", () => {
      const icon = toggle.querySelector(".collapse-icon");
      if(icon) setTimeout(() => icon.classList.toggle("rotate"), 100);
  }));
}

function setupLiveFilter() {
  const input = document.getElementById("liveFilter");
  if (!input) return;
  input.addEventListener("input", () => {
    const query = input.value.toLowerCase();
    document.querySelectorAll("table tbody tr").forEach(row => {
      const text = row.getAttribute("data-search")?.toLowerCase() || "";
      row.style.display = text.includes(query) ? "" : "none";
    });
  });
}

function setupPasswordMatchCheck() {
  const password = document.querySelector('input[name="password"]');
  const confirm = document.querySelector('input[name="confirm_password"]');
  if (!password || !confirm) return;
  const validateMatch = () => { password.value !== confirm.value ? confirm.setCustomValidity("รหัสผ่านไม่ตรงกัน") : confirm.setCustomValidity(""); };
  password.addEventListener("input", validateMatch);
  confirm.addEventListener("input", validateMatch);
}

function setupRoleBasedFields() {
  const roleSelect = document.getElementById('roleSelect');
  const affiliationWrapper = document.getElementById('affiliationWrapper');
  const affiliationSelect = document.getElementById('affiliationSelect');
  if (!roleSelect || !affiliationWrapper || !affiliationSelect) return;
  const toggleAffiliation = () => {
    if (roleSelect.value === 'admin') {
      affiliationWrapper.style.display = 'block';
      affiliationSelect.setAttribute('required', '');
    } else {
      affiliationWrapper.style.display = 'none';
      affiliationSelect.removeAttribute('required');
      affiliationSelect.value = '';
    }
  };
  toggleAffiliation();
  roleSelect.addEventListener('change', toggleAffiliation);
}

function setupCareFormTimeCalculation() {
  const startTimeEl = document.getElementById('start_time');
  const endTimeEl = document.getElementById('end_time');
  const durationEl = document.getElementById('total_duration');
  if (!startTimeEl || !endTimeEl || !durationEl) return;
  const calculateDuration = () => {
    if (!startTimeEl.value || !endTimeEl.value) return;
    const start = new Date(`1970-01-01T${startTimeEl.value}`);
    const end = new Date(`1970-01-01T${endTimeEl.value}`);
    if (end < start) { durationEl.value = 'N/A'; return; }
    const diffMs = end - start;
    const hours = Math.floor(diffMs / 3600000).toString().padStart(2, '0');
    const minutes = Math.floor((diffMs % 3600000) / 60000).toString().padStart(2, '0');
    durationEl.value = `${hours}:${minutes}`;
  };
  startTimeEl.addEventListener('change', calculateDuration);
  endTimeEl.addEventListener('change', calculateDuration);
}

function setupCareFormBmiCalculation() {
  const weightEl = document.getElementById('weight_kg');
  const heightEl = document.getElementById('height_cm');
  const bmiEl = document.getElementById('bmi');
  if (!weightEl || !heightEl || !bmiEl) return;
  const calculateBmi = () => {
    const weight = parseFloat(weightEl.value);
    const height = parseFloat(heightEl.value);
    if (weight > 0 && height > 0) {
      const heightInMeters = height / 100;
      bmiEl.value = (weight / (heightInMeters * heightInMeters)).toFixed(2);
    } else { bmiEl.value = ''; }
  };
  weightEl.addEventListener('input', calculateBmi);
  heightEl.addEventListener('input', calculateBmi);
}

function setupCareFormFileInput() {
  const fileInput = document.getElementById('visit_photo');
  const fileInfo = document.getElementById('visit_photo_info');
  if (!fileInput || !fileInfo) return;

  fileInput.addEventListener('change', () => {
      if (fileInput.files.length > 0) {
          fileInfo.textContent = fileInput.files[0].name;
      } else {
          fileInfo.textContent = '';
      }
  });
}

function setupCareFormSignaturePads() {
  const pads = {};
  const initPad = (canvasId) => {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;
    const pad = new SignaturePad(canvas, { penColor: 'rgb(0,0,0)' });
    const wrapper = canvas.closest('.signature-pad-wrapper');
    const errorDiv = wrapper.nextElementSibling;
    pad.addEventListener('beginStroke', () => {
        wrapper.classList.remove('is-invalid');
        if(errorDiv) errorDiv.style.display = 'none';
    });
    return pad;
  };
  
  pads.relative = initPad('relative_signature_pad');
  pads.cm = initPad('cm_signature_pad');

  const resizeCanvas = () => {
    for (const key in pads) {
      if (pads[key]) {
        const canvas = pads[key].canvas;
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
        pads[key].clear();
      }
    }
  };
  window.addEventListener("resize", resizeCanvas);
  resizeCanvas();
  
  document.querySelectorAll('.page-patient-care-form .clear-signature').forEach(button => {
    button.addEventListener('click', () => {
      const targetId = button.dataset.target;
      if (targetId === 'relative_signature_pad' && pads.relative) pads.relative.clear();
      if (targetId === 'cm_signature_pad' && pads.cm) pads.cm.clear();
    });
  });
  return pads;
}

function setupCareFormSubmission(form, pads) {
  form.addEventListener('submit', function (event) {
    let isFormValid = true;

    Object.entries(pads).forEach(([key, pad]) => {
        if (pad && pad.isEmpty()) {
            isFormValid = false;
            const wrapper = pad.canvas.closest('.signature-pad-wrapper');
            const errorDiv = wrapper.nextElementSibling;
            wrapper.classList.add('is-invalid');
            if (errorDiv) errorDiv.style.display = 'block';
        }
    });

    if (!form.checkValidity() || !isFormValid) {
      event.preventDefault();
      event.stopPropagation();
    }
    
    form.classList.add('was-validated');

    if(form.checkValidity() && isFormValid) {
        if (pads.relative && !pads.relative.isEmpty()) {
            document.getElementById('relative_signature_data').value = pads.relative.toDataURL('image/png');
        }
        if (pads.cm && !pads.cm.isEmpty()) {
            document.getElementById('cm_signature_data').value = pads.cm.toDataURL('image/png');
        }
    }
  }, false);
}

function initializeDashboardCharts() {
  const bodyData = document.body.dataset;

  const patientStatusData = JSON.parse(bodyData.statusCounts || '{}');
  const ageDistributionData = JSON.parse(bodyData.ageDist || '{}');
  const adlDistributionData = JSON.parse(bodyData.adlDist || '{}');

  const drawAllCharts = () => {
      if (patientStatusData.labels && patientStatusData.labels.length > 0) {
          setupPatientStatusChart(patientStatusData);
      }
      if (ageDistributionData.labels && ageDistributionData.labels.length > 0) {
          setupAgeDistributionChart(ageDistributionData);
      }
      if (adlDistributionData.labels && adlDistributionData.labels.length > 0) {
          setupAdlScoreChart(adlDistributionData);
      }
  };

  if (typeof Chart === 'undefined') {
      loadChartLibraries(drawAllCharts);
  } else {
      drawAllCharts();
  }
}

function loadChartLibraries(callback) {
  const chartJsScript = document.createElement('script');
  chartJsScript.src = "https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js";
  chartJsScript.integrity = "sha384-JUh163oCRItcbPme8pYnROHQMC6fNKTBWtRG3I3I0erJkzNgL7uxKlNwcrcFKeqF";
  chartJsScript.crossOrigin = "anonymous";
  chartJsScript.onload = () => {
    const dataLabelsScript = document.createElement('script');
    dataLabelsScript.src = "https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js";
    dataLabelsScript.integrity = "sha384-y49Zu59jZHJL/PLKgZPv3k2WI9c0Yp3pWB76V8OBVCb0QBKS8l4Ff3YslzHVX76Y";
    dataLabelsScript.crossOrigin = "anonymous";
    dataLabelsScript.onload = callback;
    document.head.appendChild(dataLabelsScript);
  };
  document.head.appendChild(chartJsScript);
}

function setupPatientStatusChart(patientStatusData) {
  const ctx = document.getElementById("patientStatusChart");
  if (!ctx) return;
  Chart.register(ChartDataLabels);
  const colorMap = { 'ผู้สูงอายุ': '#FF4C4C', 'ผู้พิการ': '#FFD93D', 'ผู้มีภาวะพึ่งพิง': '#00BFFF', 'ผู้มีภาวะพึ่งพิงในโครงการ LTC': '#28a745' };
  new Chart(ctx, {
    type: "doughnut",
    data: {
      labels: patientStatusData.labels,
      datasets: [{ data: patientStatusData.data, backgroundColor: patientStatusData.labels.map(label => colorMap[label] || '#6c757d'), borderWidth: 1, borderColor: '#fff' }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: "60%",
      plugins: {
        legend: { position: 'bottom', labels: { padding: 20, boxWidth: 14, font: { family: "'Kanit', sans-serif" }}},
        datalabels: {
          color: "#000", font: { weight: "bold", size: 14 },
          formatter: (value, context) => {
            const total = context.chart.data.datasets[0].data.reduce((sum, val) => Number(sum) + Number(val), 0);
            if (total === 0) return '0%';
            const percent = (Number(value) / total) * 100;
            return percent > 5 ? percent.toFixed(0) + '%' : '';
          }
        }
      }
    }
  });
}

function setupAgeDistributionChart(ageDistributionData) {
  const ctx = document.getElementById('ageDistributionChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ageDistributionData.labels,
      datasets: [{ label: 'จำนวนผู้ป่วย', data: ageDistributionData.data, backgroundColor: 'rgba(13, 110, 253, 0.6)', borderColor: 'rgba(13, 110, 253, 1)', borderRadius: 4, borderWidth: 1 }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
  });
}

function setupAdlScoreChart(adlDistributionData) {
  const ctx = document.getElementById('adlScoreChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: adlDistributionData.labels,
      datasets: [{
        label: 'จำนวนผู้ป่วย', data: adlDistributionData.data,
        backgroundColor: 'rgba(13, 110, 253, 0.6)', borderColor: 'rgba(13, 110, 253, 1)',
        borderRadius: 4, borderWidth: 1
      }]
    },
    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
  });
}

async function loadGoogleMapsScript() {
  if (!document.getElementById('map') && !document.getElementById('patientMap')) return;
  try {
    const response = await fetch('api/get_maps_key.php');
    if (!response.ok) throw new Error('Failed to fetch API key');
    const data = await response.json();
    if (data.apiKey) {
      const script = document.createElement('script');
      script.src = `https://maps.googleapis.com/maps/api/js?key=${data.apiKey}&libraries=places,marker&callback=initMaps`;
      script.async = true;
      script.defer = true;
      document.head.appendChild(script);
    } else { throw new Error('API key was not found'); }
  } catch (error) { console.error('Could not load Google Maps:', error); }
}

async function initMaps() {
  if (document.getElementById("mapModal")) {
      initMapPicker();
  }
  if (document.getElementById("patientMap")) {
      await initPatientMap();
  }
}

function initMapPicker() {
  const mapModal = document.getElementById("mapModal");
  if (!mapModal) return;
  let selectedLatLng = null, map = null, marker = null;
  const confirmBtn = document.getElementById("confirmLocation");
  const locationInput = document.getElementById("locationInput");
  mapModal.addEventListener("shown.bs.modal", () => {
    if (!map) {
      map = new google.maps.Map(document.getElementById("map"), { center: { lat: 18.5513266, lng: 98.996277 }, zoom: 12 });
      marker = new google.maps.Marker({ map, draggable: true });
      map.addListener("click", (e) => { marker.setPosition(e.latLng); selectedLatLng = e.latLng; });
    }
    google.maps.event.trigger(map, "resize");
  });
  confirmBtn.addEventListener("click", () => {
    if (selectedLatLng) { locationInput.value = `${selectedLatLng.lat().toFixed(6)}, ${selectedLatLng.lng().toFixed(6)}`; }
    bootstrap.Modal.getInstance(mapModal).hide();
  });
}

async function initPatientMap() {
  const mapEl = document.getElementById("patientMap");
  if (!mapEl || !google.maps) return;

  const patientLocations = JSON.parse(document.body.dataset.locations || '[]');
  if (!patientLocations || patientLocations.length === 0) {
      mapEl.innerHTML = '<div class="alert alert-light text-center m-3">ไม่มีข้อมูลตำแหน่งผู้ป่วยที่จะแสดงบนแผนที่</div>';
      return;
  }

  const { AdvancedMarkerElement, PinElement } = await google.maps.importLibrary("marker");
  const tooltip = document.getElementById('map-tooltip');
  if (!tooltip) return;
  
  const map = new google.maps.Map(mapEl, { 
    zoom: 10, 
    center: { lat: 18.5679, lng: 99.0031 }, 
    gestureHandling: "greedy", 
    mapId: 'SMARTHEALTH_MAP_ID',
    streetViewControl: false 
  });

  const colorMap = { 'ผู้สูงอายุ': '#FF4C4C', 'ผู้พิการ': '#FFD93D', 'ผู้มีภาวะพึ่งพิง': '#00BFFF', 'ผู้มีภาวะพึ่งพิงในโครงการ LTC': '#28a745' };

  patientLocations.forEach(p => {
    const lat = parseFloat(p.lat);
    const lng = parseFloat(p.lng);

    if (isNaN(lat) || isNaN(lng)) {
        console.warn(`Skipping marker for patient ${p.name} due to invalid coordinates.`);
        return; 
    }

    const markerColor = colorMap[p.status] || '#6c757d';
    const pinGlyph = new PinElement({ background: markerColor, borderColor: '#333333', glyph: '•', glyphColor: 'white', scale: 0.8 });
    const marker = new AdvancedMarkerElement({ map, position: { lat, lng }, title: p.name, content: pinGlyph.element });

    marker.element.addEventListener('mouseover', (event) => {
        tooltip.innerHTML = `<div class="tooltip-name">${p.name}</div><div class="tooltip-status">สถานะ: ${p.status}</div>`;
        tooltip.style.left = `${event.pageX + 15}px`;
        tooltip.style.top = `${event.pageY - 45}px`;
        tooltip.classList.add('visible');
    });
    marker.element.addEventListener('mouseout', () => { tooltip.classList.remove('visible'); });

    marker.addListener('click', () => {
      const targetCard = document.getElementById(`patient-card-${p.id}`);
      if (!targetCard) return; // Exit if the card is not found for any reason

      // Find the direct parent column of the card
      const cardWrapper = targetCard.closest('.col');

      // Check if the card's wrapper is currently not visible
      if (cardWrapper && window.getComputedStyle(cardWrapper).display === 'none') {
          const groupSection = cardWrapper.closest('.patient-group-section');
          if (groupSection) {
              // Find the "Show More" button within that patient's hospital group
              const loadMoreButton = groupSection.querySelector('.load-more-btn');

              // If the button exists, programmatically click it to expand the list
              if (loadMoreButton) {
                  loadMoreButton.click();
              }
          }
      }

      // Now that the card is guaranteed to be visible, scroll to it
      targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });

      // Add the highlight effect
      targetCard.classList.add('highlight');
      setTimeout(() => {
          targetCard.classList.remove('highlight');
      }, 2500);
    });
  });
}

function setupPatientCardModals() {
  const modalElement = document.getElementById('patientDetailModal');
  if (!modalElement) return;
  const patientModal = new bootstrap.Modal(modalElement);
  const loadingState = document.getElementById('modal-loading-state');
  const contentState = document.getElementById('modal-content-state');
  const getStatusBadgeClass = (status) => {
    switch (status) {
      case 'ผู้สูงอายุ': return 'bg-danger-subtle text-danger-emphasis';
      case 'ผู้พิการ': return 'bg-warning-subtle text-warning-emphasis';
      case 'ผู้มีภาวะพึ่งพิง': return 'bg-info-subtle text-info-emphasis';
      case 'ผู้มีภาวะพึ่งพิงในโครงการ LTC': return 'bg-success-subtle text-success-emphasis';
      default: return 'bg-secondary-subtle text-secondary-emphasis';
    }
  };
  document.addEventListener('click', function(event) {
    const card = event.target.closest('.patient-card');
    if (card) {
      const patientId = card.getAttribute('data-patient-id');
      if (!patientId) return;
      loadingState.style.display = 'block';
      contentState.style.display = 'none';
      patientModal.show();
      fetch(`api/get_patient_details.php?id=${patientId}`)
        .then(response => { if (!response.ok) throw new Error('Network response error'); return response.json(); })
        .then(data => {
          if (data.error) throw new Error(data.error);
          document.getElementById('modal-patient-photo').src = 'upload/patient/' + (data.photo || 'default_patient.png');
          document.getElementById('modal-patient-fullname').textContent = `${data.prefix || ''} ${data.first_name || ''} ${data.last_name || ''}`;
          document.getElementById('modal-patient-citizen-id').textContent = `ID: ${data.citizen_id || 'N/A'}`;
          const statusBadge = document.getElementById('modal-status-badge');
          statusBadge.textContent = data.status || 'N/A';
          statusBadge.className = `badge rounded-pill ${getStatusBadgeClass(data.status)}`;
          document.getElementById('modal-info-age').textContent = `${data.age || 'N/A'} ปี`;
          document.getElementById('modal-info-phone').textContent = data.phone || 'N/A';
          document.getElementById('modal-info-address').textContent = `${data.house_no || ''} หมู่ ${data.village_no || ''} ต.${data.subdistrict || ''} อ.${data.district || ''} จ.${data.province || ''}`;
          document.getElementById('modal-info-rights').textContent = data.medical_rights || 'N/A';
          document.getElementById('modal-health-disease').textContent = data.disease || 'N/A';
          document.getElementById('modal-health-allergy').textContent = data.allergy || 'N/A';
          document.getElementById('modal-health-disability').textContent = data.disability_type || 'N/A';
          document.getElementById('modal-care-scores').textContent = `ADL: ${data.adl !== null ? data.adl : 'N/A'} / TAI: ${data.tai || 'N/A'}`;
          const cmFullName = data.cm_id ? `${data.cm_prefix || ''}${data.cm_name || ''} ${data.cm_surname || ''}`.trim() : 'N/A';
          const cgFullName = data.cg_id ? `${data.cg_prefix || ''}${data.cg_name || ''} ${data.cg_surname || ''}`.trim() : 'N/A';
          const relativeFullName = data.relative_name ? `${data.relative_prefix || ''}${data.relative_name}`.trim() : 'N/A';
          document.getElementById('modal-care-cm').textContent = cmFullName;
          document.getElementById('modal-care-cg').textContent = cgFullName;
          document.getElementById('modal-care-relative').textContent = `${relativeFullName} (${data.relative_phone || 'N/A'})`;
          document.getElementById('modal-care-needs').textContent = data.needs || 'N/A';
          loadingState.style.display = 'none';
          contentState.style.display = 'block';
        })
        .catch(error => {
          console.error('Error fetching patient details:', error);
          contentState.innerHTML = `<div class="alert alert-danger m-0">Could not load details.</div>`;
          loadingState.style.display = 'none';
          contentState.style.display = 'block';
        });
    }
  });
}

function setupStatCounters() {
  document.querySelectorAll(".stat-value").forEach(counter => {
    const target = +counter.getAttribute("data-count") || 0;
    if (target === 0) { counter.textContent = "0"; return; }
    const duration = 1200;
    let start = 0;
    const stepTime = Math.abs(Math.floor(duration / target)) || 1;
    const timer = setInterval(() => {
        start += 1;
        if (start > target) {
            counter.textContent = target;
            clearInterval(timer);
        } else {
            counter.textContent = start;
        }
    }, stepTime);
  });
}

function setupPatientFilters() {
  const searchInput = document.getElementById("searchPatient");
  const statusFilter = document.getElementById("statusFilter");
  const resetBtn = document.getElementById("resetFilter");

  if (!searchInput || !statusFilter || !resetBtn) {
    return;
  }
  
  const allGroups = document.querySelectorAll('.patient-group-section');

  const filterAndDisplay = () => {
    const searchText = searchInput.value.toLowerCase();
    const statusValue = statusFilter.value;
    const isFiltering = searchText !== "" || statusValue !== "";

    allGroups.forEach(group => {
      let groupHasVisibleCards = false;
      const loadMoreButton = group.querySelector('.load-more-btn');
      const isExpanded = loadMoreButton && loadMoreButton.classList.contains('is-hidden');
      
      group.querySelectorAll('.col').forEach((cardWrapper, index) => {
        const card = cardWrapper.querySelector('.patient-card');
        if (card) {
          const patientName = card.getAttribute('data-patient-name').toLowerCase();
          const patientStatus = card.getAttribute('data-patient-status');
          const matchesSearch = patientName.includes(searchText);
          const matchesStatus = (statusValue === "") || (patientStatus === statusValue);
          const shouldBeVisible = matchesSearch && matchesStatus;
          
          if(shouldBeVisible) {
            if(isFiltering || !cardWrapper.classList.contains('patient-card-hidden')) {
                cardWrapper.style.display = 'block';
            } else if (isExpanded) {
                cardWrapper.style.display = 'block';
            } else {
                 cardWrapper.style.display = 'none';
            }

            if(cardWrapper.style.display === 'block') {
                groupHasVisibleCards = true;
            }

          } else {
            cardWrapper.style.display = 'none';
          }
        }
      });
      
      group.style.display = groupHasVisibleCards ? 'block' : 'none';
      
      if (loadMoreButton) {
        loadMoreButton.style.display = !isFiltering && !isExpanded ? 'block' : 'none';
      }
    });
  };

  searchInput.addEventListener('input', filterAndDisplay);
  statusFilter.addEventListener('change', filterAndDisplay);

  resetBtn.addEventListener('click', () => {
    searchInput.value = '';
    statusFilter.value = '';

    allGroups.forEach(group => {
      group.querySelectorAll('.col').forEach((cardWrapper, index) => {
        if (cardWrapper.classList.contains('patient-card-hidden')) {
          cardWrapper.style.display = 'none'; 
        } else {
          cardWrapper.style.display = 'block';
        }
      });
      const loadMoreButton = group.querySelector('.load-more-btn');
      if (loadMoreButton) {
        loadMoreButton.classList.remove('is-hidden');
      }
    });
    
    filterAndDisplay();
  });
}

function setupWcagPatientCards() {
  document.querySelectorAll(".patient-card").forEach(card => {
    card.setAttribute("tabindex", "0");
    card.setAttribute("role", "group");
    card.setAttribute("aria-label", card.querySelector("h6")?.textContent || "ข้อมูลผู้ป่วย");
  });
}

function setupLoadMorePatients() {
  document.addEventListener('click', function(event) {
    if (event.target.matches('.load-more-btn')) {
      const button = event.target;
      const targetGroup = button.closest('.patient-group-section');
      if (targetGroup) {
        targetGroup.querySelectorAll('.col.patient-card-hidden').forEach(cardCol => {
          cardCol.style.display = 'block';
        });
      }
      button.style.display = 'none';
      button.classList.add('is-hidden'); 
    }
  });
}

function setupCareHistoryModals() {
    if (!document.body.classList.contains('page-patient-care-history')) {
        return;
    }

    const modalElement = document.getElementById('historyDetailModal');
    if (!modalElement) return;

    const historyModal = new bootstrap.Modal(modalElement);
    const loadingState = document.getElementById('history-modal-loading');
    const contentState = document.getElementById('history-modal-content');

    document.querySelectorAll('.timeline-card').forEach(card => {
        card.addEventListener('click', () => {
            const visitId = card.dataset.visitId;
            if (!visitId) return;

            loadingState.style.display = 'block';
            contentState.style.display = 'none';
            historyModal.show();
            
            fetch(`api/get_care_visit_details.php?id=${visitId}`)
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    if (data.error) throw new Error(data.error);
                    
                    const assessmentBadgeClass = data.assessment_result === 'ดีขึ้น' ? 'bg-success' : (data.assessment_result === 'แย่ลง' ? 'bg-danger' : 'bg-warning text-dark');
                    const visitDate = data.visit_date_thai;
                    const visitTime = `เวลา ${data.start_time.substring(0,5)} - ${data.end_time.substring(0,5)} น.`;
                    const nextVisitDate = data.next_visit_date_thai;

                    contentState.innerHTML = `
                        <div class="info-section">
                            <h6><i class="bi bi-calendar-event me-2"></i>ข้อมูลการเยี่ยม</h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between"><span>วันที่/เวลา</span><strong>${visitDate} ${visitTime}</strong></li>
                                <li class="list-group-item d-flex justify-content-between"><span>CM</span><strong>${data.cm_fullname || '-'}</strong></li>
                                <li class="list-group-item d-flex justify-content-between"><span>CG</span><strong>${data.cg_fullname || '-'}</strong></li>
                                <li class="list-group-item d-flex justify-content-between"><span>นัดครั้งต่อไป</span><strong>${nextVisitDate}</strong></li>
                            </ul>
                        </div>
                        <div class="info-section">
                            <h6><i class="bi bi-heart-pulse me-2"></i>สัญญาณชีพและข้อมูลกายภาพ</h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between"><span>ข้อมูลกายภาพ</span><strong>น้ำหนัก ${data.weight_kg} kg ส่วนสูง ${data.height_cm} cm BMI ${data.bmi || 'N/A'}</strong></li>
                                <li class="list-group-item d-flex justify-content-between"><span>ความดันโลหิต</span><strong>${data.bp_systolic}/${data.bp_diastolic} mmHg</strong></li>
                                <li class="list-group-item d-flex justify-content-between"><span>ชีพจร</span><strong>${data.pulse_rate} ครั้ง/นาที</strong></li>
                                <li class="list-group-item d-flex justify-content-between"><span>อัตราการหายใจ</span><strong>${data.respiratory_rate} ครั้ง/นาที</strong></li>
                                <li class="list-group-item d-flex justify-content-between"><span>อุณหภูมิ</span><strong>${data.body_temp}°C</strong></li>
                            </ul>
                        </div>
                        <div class="info-section">
                            <h6><i class="bi bi-emoji-frown me-2"></i>ผลคัดกรองโรคซึมเศร้า (2Q+)</h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between"><span>1. รู้สึกหดหู่ เศร้า หรือท้อแท้</span><strong class="${data.q2_depressed == 1 ? 'text-danger' : ''}">${data.q2_depressed == 1 ? 'มี' : 'ไม่มี'}</strong></li>
                                <li class="list-group-item d-flex justify-content-between"><span>2. รู้สึกเบื่อ ทำอะไรก็ไม่เพลิดเพลิน</span><strong class="${data.q2_anhedonia == 1 ? 'text-danger' : ''}">${data.q2_anhedonia == 1 ? 'มี' : 'ไม่มี'}</strong></li>
                                <li class="list-group-item d-flex justify-content-between"><span>3. อยากทำร้ายตัวเอง</span><strong class="${data.q2_self_harm == 1 ? 'text-danger' : ''}">${data.q2_self_harm == 1 ? 'มี' : 'ไม่มี'}</strong></li>
                            </ul>
                        </div>
                        <div class="info-section">
                            <h6><i class="bi bi-clipboard2-pulse me-2"></i>สรุปผลการประเมิน</h6>
                             <ul class="list-group list-group-flush">
                                <li class="list-group-item"><div class="d-flex justify-content-between"><span>อาการที่พบ</span><strong class="text-end ps-3">${data.symptoms_found || '-'}</strong></div></li>
                                <li class="list-group-item"><div class="d-flex justify-content-between"><span>การดูแลที่ให้</span><strong class="text-end ps-3">${data.care_provided || '-'}</strong></div></li>
                                <li class="list-group-item"><div class="d-flex justify-content-between align-items-center"><span>ผลลัพธ์</span><span class="badge ${assessmentBadgeClass}">${data.assessment_result}</span></div></li>
                                <li class="list-group-item"><div class="d-flex justify-content-between"><span>คะแนน ADL ปัจจุบัน</span><strong class="text-primary">${data.adl_total} คะแนน</strong></div></li>
                             </ul>
                        </div>
                        <div class="info-section">
                            <h6><i class="bi bi-paperclip me-2"></i>เอกสารแนบ</h6>
                            <p class="text-center text-muted">ภาพการตรวจเยี่ยม</p>
                            <div class="text-center mb-3"><img src="./upload/visit/${data.visit_photo}" class="img-fluid rounded shadow-sm border" alt="Visit Photo" style="max-height: 400px;"></div>
                            <div class="row text-center">
                                <div class="col-6">
                                    <p class="text-muted mb-1">ลายเซ็นญาติ (${data.relative_relationship || 'N/A'})</p>
                                    <img src="./upload/signature/${data.relative_signature}" class="img-fluid bg-light border rounded" alt="Relative Signature">
                                </div>
                                <div class="col-6">
                                    <p class="text-muted mb-1">ลายเซ็น CM</p>
                                    <img src="./upload/signature/${data.cm_signature}" class="img-fluid bg-light border rounded" alt="CM Signature">
                                </div>
                            </div>
                        </div>
                    `;
                    
                    loadingState.style.display = 'none';
                    contentState.style.display = 'block';
                })
                .catch(error => {
                    console.error('Error fetching visit details:', error);
                    contentState.innerHTML = '<div class="alert alert-danger m-0">Could not load details. Please check the console and API endpoint.</div>';
                    loadingState.style.display = 'none';
                    contentState.style.display = 'block';
                });
        });
    });
}

function setupElderlyHistoryModals() {
    const modalElement = document.getElementById('elderlyDetailModal');
    if (!modalElement) return;

    const historyModal = new bootstrap.Modal(modalElement);
    const loadingState = document.getElementById('elderly-modal-loading');
    const contentState = document.getElementById('elderly-modal-content');
    let lastFocusedCard = null;

    const createListItem = (label, value) => {
        if (value === null || typeof value === 'undefined' || String(value).trim() === '') return '';
        return `<li class="list-group-item d-flex justify-content-between align-items-center"><span>${label}</span><strong class="text-end ps-3">${value}</strong></li>`;
    };

    document.querySelectorAll('.page-patient-care-history .timeline-card[data-assessment-id]').forEach(card => {
        card.addEventListener('click', () => {
            lastFocusedCard = card;
            const assessmentId = card.dataset.assessmentId;
            if (!assessmentId) return;

            loadingState.style.display = 'block';
            contentState.style.display = 'none';
            historyModal.show();
            
            fetch(`api/get_elderly_assessment_details.php?id=${assessmentId}`)
                .then(response => response.ok ? response.json() : Promise.reject('Network error'))
                .then(data => {
                    if (data.error) throw new Error(data.error);

                    const assessmentInfoList = 
                        createListItem('วันที่ประเมิน', data.assessment_date_thai) +
                        createListItem('ผู้ประเมิน', data.creator_fullname) +
                        createListItem('สถานะ ณ วันประเมิน', data.patient_status_at_assessment);

                    const vitalsList =
                        createListItem('น้ำหนัก / ส่วนสูง', `${data.weight_kg || 'N/A'} kg / ${data.height_cm || 'N/A'} cm`) +
                        createListItem('รอบเอว / BMI', `${data.waist_cm || 'N/A'} cm / ${data.bmi || 'N/A'}`) +
                        createListItem('ความดันโลหิต', `${data.bp_systolic || 'N/A'} / ${data.bp_diastolic || 'N/A'} mmHg`);

                    const adlSummaryList =
                        createListItem('คะแนน ADL รวม', `<strong class="text-primary">${data.adl_total_score ?? 'N/A'}</strong>`) +
                        createListItem('สรุปผล', `<strong>${data.adl_result_display || '-'}</strong>`);

                    const chronicDiseaseList =
                        createListItem('โรคเบาหวาน', data.chronic_diabetes) +
                        createListItem('โรคตับ', data.chronic_liver) +
                        createListItem('โรคหัวใจ', data.chronic_heart) +
                        createListItem('โรคความดันโลหิตสูง', data.chronic_hypertension) +
                        createListItem('โรคหลอดเลือดสมอง', data.chronic_stroke) +
                        createListItem('ไขมันในเลือดผิดปกติ', data.chronic_dyslipidemia) +
                        createListItem('แพ้อาหาร', data.chronic_food_allergy) +
                        createListItem('โรคอื่นๆ', data.chronic_other_diseases) +
                        createListItem('การปฏิบัติตัว', data.chronic_illness_practice);

                    const behaviorList =
                        createListItem('รสชาติอาหารโปรด', data.behavior_food_flavors) +
                        createListItem('การสูบบุหรี่', data.behavior_smoking) +
                        createListItem('การดื่มแอลกอฮอล์', data.behavior_alcohol) +
                        createListItem('การออกกำลังกาย', data.behavior_exercise);

                    let surgeryText = data.eye_surgery_history;
                    if (data.eye_surgery_history === 'เคย' && data.eye_surgery_side) {
                        surgeryText += ` (ข้าง${data.eye_surgery_side})`;
                    }

                    const specificScreeningList =
                        createListItem('สุขภาพช่องปาก', data.oral_summary) +
                        createListItem('โรคซึมเศร้า', data.depression_summary) +
                        createListItem('ความเสี่ยงฆ่าตัวตาย', data.suicide_risk_summary) +
                        createListItem('การประเมินทางสมอง', data.brain_assessment) +
                        createListItem('ประวัติหกล้ม (6 ด.)', data.fall_history_6m) +
                        createListItem('ความเสี่ยงหกล้ม', data.fall_risk_summary) +
                        createListItem('ข้อเข่าเสื่อม', data.knee_summary) +
                        createListItem('การใช้แว่นตา', data.eye_glasses) +
                        createListItem('ประวัติผ่าตัดตา', surgeryText) +
                        createListItem('ผลตรวจสายตา', data.eye_exam_result) +
                        createListItem('วัณโรค', data.tb_summary) +
                        createListItem('มะเร็งลำไส้ใหญ่', data.colon_cancer_summary);

                    const photoHtml = data.assessment_photo 
                        ? `<img src="./upload/assessment/${data.assessment_photo}" class="img-fluid rounded shadow-sm border" style="max-height: 400px; display: block; margin: auto;">` 
                        : '<p class="text-muted fst-italic mt-3">ไม่มีภาพแนบ</p>';

                    contentState.innerHTML = `
                        <div class="info-section"><h6><i class="bi bi-calendar-event me-2"></i>ข้อมูลการประเมิน</h6><ul class="list-group list-group-flush">${assessmentInfoList}</ul></div>
                        <div class="info-section"><h6><i class="bi bi-heart-pulse me-2"></i>ข้อมูลกายภาพ</h6><ul class="list-group list-group-flush">${vitalsList}</ul></div>
                        <div class="info-section"><h6><i class="bi bi-clipboard2-pulse me-2"></i>ผลการประเมิน ADL</h6><ul class="list-group list-group-flush">${adlSummaryList}</ul></div>
                        <div class="info-section"><h6><i class="bi bi-bandaid me-2"></i>การคัดกรองโรคประจำตัว</h6><ul class="list-group list-group-flush">${chronicDiseaseList}</ul></div>
                        <div class="info-section"><h6><i class="bi bi-person-walking me-2"></i>พฤติกรรมสุขภาพ</h6><ul class="list-group list-group-flush">${behaviorList}</ul></div>
                        <div class="info-section"><h6><i class="bi bi-check2-circle me-2"></i>การคัดกรองเฉพาะทางและความเสี่ยง</h6><ul class="list-group list-group-flush">${specificScreeningList}</ul></div>
                        <div class="info-section"><h6><i class="bi bi-paperclip me-2"></i>เอกสารแนบ</h6><p class="text-center text-muted">ภาพการประเมิณ</p><div class="text-center">${photoHtml}</div></div>
                    `;
                    
                    loadingState.style.display = 'none';
                    contentState.style.display = 'block';
                })
                .catch(error => {
                    console.error("EXECUTION FAILED:", error);
                    contentState.innerHTML = `<div class="alert alert-danger m-0"><strong>Error:</strong> Could not load details.</div>`;
                    loadingState.style.display = 'none';
                    contentState.style.display = 'block';
                });
        });
    });

    modalElement.addEventListener('hidden.bs.modal', () => {
        if (lastFocusedCard) { lastFocusedCard.focus(); }
    });
}

function setupEquipmentModal() {
    const actionModal = document.getElementById('actionModal');
    if (!actionModal) return;

    const modalBodyPlaceholder = document.getElementById('modal-content-placeholder');

    actionModal.addEventListener('show.bs.modal', async (event) => {
        const button = event.relatedTarget;
        const itemId = button.dataset.itemId;

        modalBodyPlaceholder.innerHTML = `
            <div class="text-center p-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>`;
        
        try {
            const response = await fetch(`api/equipment_handler.php?action=get_details&item_id=${itemId}`);
            if (!response.ok) throw new Error(`Network error: ${response.statusText}`);
            
            const data = await response.json();
            if (data.error) throw new Error(data.error);

            const { details, patients } = data;

            let contentHtml = buildModalContent(details, patients);
            modalBodyPlaceholder.innerHTML = contentHtml;

            attachModalEventListeners(actionModal, itemId);

        } catch (error) {
            console.error('Failed to load equipment details:', error);
            modalBodyPlaceholder.innerHTML = `<div class="alert alert-danger m-0">Could not load details: ${error.message}</div>`;
        }
    });
}

function buildModalContent(details) {
    const isInUse = details.status === 'กำลังใช้งาน';
    
    const disabledAttribute = isInUse ? 'disabled' : '';
    let statusNote = isInUse ? '<div class="alert alert-warning-subtle small p-2 text-center">ไม่สามารถแก้ไขได้จนกว่าจะมีการส่งคืนอุปกรณ์</div>' : '';

    let html = `
        <div class="equipment-modal-header">
            <img src="upload/equipment/${details.image_url || 'default_equipment.png'}" class="equipment-modal-img" alt="${details.type_name}" onerror="this.src='upload/equipment/default_equipment.png'">
            <div>
                <h5 class="equipment-modal-title">${details.type_name}</h5>
                <p class="equipment-modal-sn text-muted mb-0">S/N: ${details.serial_number || 'N/A'}</p>
            </div>
        </div>
        <div class="equipment-modal-body">
            ${statusNote}
            
            <div class="action-section">
                <h6 class="action-section-title">หมายเหตุ</h6>
                <form id="noteForm">
                    <div class="mb-2">
                        <textarea id="notesTextarea" class="form-control" rows="3">${details.notes || ''}</textarea>
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-outline-secondary btn-sm">บันทึก</button>
                    </div>
                </form>
            </div>

            <div class="action-section">
                <h6 class="action-section-title">เปลี่ยนสถานะ</h6>
                <form id="statusForm">
                    <div class="input-group">
                        <select id="statusSelect" class="form-select" ${disabledAttribute}>
                            <option value="พร้อมใช้งาน" ${details.status === 'พร้อมใช้งาน' ? 'selected' : ''}>พร้อมใช้งาน</option>
                            <option value="ชำรุด" ${details.status === 'ชำรุด' ? 'selected' : ''}>ชำรุด</option>
                            <option value="ส่งซ่อม" ${details.status === 'ส่งซ่อม' ? 'selected' : ''}>ส่งซ่อม</option>
                        </select>
                        <button type="submit" class="btn btn-outline-secondary" ${disabledAttribute}>บันทึก</button>
                    </div>
                </form>
            </div>

            <div class="action-section">
                <h6 class="action-section-title text-danger">โซนอันตราย</h6>
                <div class="d-grid">
                    <button type="button" id="deleteBtn" class="btn btn-outline-danger" ${disabledAttribute}>
                        <i class="bi bi-trash-fill me-2"></i>ลบอุปกรณ์
                    </button>
                </div>
            </div>
        </div>
    `;
    return html;
}

function attachModalEventListeners(modal, itemId) {
    const modalInstance = bootstrap.Modal.getInstance(modal);
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;

    const handleAction = async (action, body) => {
        try {
            const response = await fetch('api/equipment_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action, item_id: itemId, csrf_token: csrfToken, ...body })
            });
            const result = await response.json();
            if (!response.ok || result.error) throw new Error(result.error || 'Request failed');
            
            window.location.reload();

        } catch (error) {
            alert(`Error: ${error.message}`);
        }
    };

    modal.querySelector('#noteForm')?.addEventListener('submit', (e) => {
        e.preventDefault();
        const newNoteText = modal.querySelector('#notesTextarea').value;
        handleAction('update_note', { notes: newNoteText });
    });

    modal.querySelector('#statusForm')?.addEventListener('submit', (e) => {
        e.preventDefault();
        const newStatus = modal.querySelector('#statusSelect').value;
        handleAction('update_status', { status: newStatus });
    });

    modal.querySelector('#deleteBtn')?.addEventListener('click', () => {
        // The confirmation pop-up has been removed. Deletion is now instant.
        handleAction('delete_item', {});
    });
}

function setupAutoSubmittingFilters() {
    const form = document.getElementById('equipmentFilterForm');
    const searchInput = document.getElementById('equipmentSearchInput');
    const statusFilter = document.getElementById('equipmentStatusFilter');

    if (!form || !searchInput || !statusFilter) {
        return;
    }

    let debounceTimer;

    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            form.submit();
        }, 500);
    });

    statusFilter.addEventListener('change', () => {
        form.submit();
    });
}

function setupEquipmentFileInput() {
  const fileInput = document.getElementById('image_url');
  const fileInfo = document.getElementById('image_info');
  if (!fileInput || !fileInfo) return;

  fileInput.addEventListener('change', () => {
      if (fileInput.files.length > 0) {
          fileInfo.textContent = `Selected file: ${fileInput.files[0].name}`;
      } else {
          fileInfo.textContent = '';
      }
  });
}

function setupEquipmentTypeSelection() {
  const typeSelect = document.getElementById('type_id_select');
  const categoryInput = document.getElementById('category');

  if (!typeSelect || !categoryInput) {
      return; 
  }

  typeSelect.addEventListener('change', () => {
      const selectedOption = typeSelect.options[typeSelect.selectedIndex];

      const category = selectedOption.getAttribute('data-category');

      categoryInput.value = category || '';
  });

  if (typeSelect.value) {
      typeSelect.dispatchEvent(new Event('change'));
  }
}

function setupRequestEquipmentPage() {
  const form = document.querySelector('.page-request-equipment .needs-validation');
  if (!form) return;

  // Get references to all our steps and elements
  const step1 = document.getElementById('step1_card');
  const step2 = document.getElementById('step2_card');
  const step3 = document.getElementById('step3_card');
  const patientList = document.getElementById('patientList');
  const equipmentList = document.getElementById('equipmentList');
  const patientSummary = document.getElementById('patient_selection_summary');
  const equipmentSummary = document.getElementById('equipment_selection_summary');
  const hiddenPatientId = document.getElementById('selected_patient_id');
  const hiddenEquipmentId = document.getElementById('selected_equipment_type_id');
  const patientSearch = document.getElementById('patientSearch');

  // Get references to the modal and its parts
  const modalElement = document.getElementById('confirmationModal');
  if (!modalElement) return; // Exit if modal isn't on the page
  const confirmationModal = new bootstrap.Modal(modalElement);
  const modalTitle = document.getElementById('confirmationModalLabel');
  const modalBody = document.getElementById('confirmationModalBody');
  const confirmButton = document.getElementById('confirmModalButton');
  
  let itemToSelect = null; // Variable to hold the item we want to select

  const fetchEquipmentForHospital = async (hospitalId) => {
      // This function remains unchanged...
      equipmentList.innerHTML = '<div class="p-4 text-center"><div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div></div>';
      try {
          const response = await fetch(`api/get_equipment_by_hospital.php?hospital_id=${hospitalId}`);
          if (!response.ok) throw new Error('Network response was not ok.');
          const equipmentData = await response.json();
          equipmentList.innerHTML = ''; 
          if (equipmentData.length === 0) {
              equipmentList.innerHTML = '<div class="p-3 text-muted">ไม่พบข้อมูลอุปกรณ์สำหรับโรงพยาบาลนี้</div>';
              return;
          }
          equipmentData.forEach(type => {
              const is_available = (type.available_count > 0);
              const itemHTML = `
                  <a href="#" 
                      class="list-group-item list-group-item-action d-flex justify-content-between align-items-center ${is_available ? '' : 'is-unavailable'}" 
                      data-type-id="${type.id}" 
                      data-type-name="${type.name}">
                      <div>
                          <div class="fw-bold">${type.name}</div>
                          <small class="text-muted">${type.category}</small>
                      </div>
                      <span class="badge rounded-pill ${is_available ? 'bg-success' : 'bg-secondary'}">
                          ${is_available ? 'มี ' + type.available_count + ' ชิ้น' : 'ของหมด'}
                      </span>
                  </a>`;
              equipmentList.insertAdjacentHTML('beforeend', itemHTML);
          });
      } catch (error) {
          console.error('Failed to fetch equipment:', error);
          equipmentList.innerHTML = '<div class="p-3 text-danger">ไม่สามารถโหลดข้อมูลอุปกรณ์ได้</div>';
      }
  };

  // This function completes the selection process
  const selectEquipmentItem = (targetItem) => {
      equipmentList.querySelectorAll('.list-group-item').forEach(item => item.classList.remove('active'));
      targetItem.classList.add('active');
      hiddenEquipmentId.value = targetItem.dataset.typeId;
      equipmentSummary.textContent = targetItem.dataset.typeName;
      equipmentSummary.style.opacity = 1;
      step2.querySelector('.step-title').style.opacity = 0;
      step2.querySelector('.selection-body').style.display = 'none';
      step3.classList.remove('is-disabled');
      step3.scrollIntoView({ behavior: 'smooth', block: 'center' });
  };

  // Patient selection logic remains the same
  patientList.addEventListener('click', (e) => {
      e.preventDefault();
      const targetItem = e.target.closest('.list-group-item');
      if (!targetItem) return;
      patientList.querySelectorAll('.list-group-item').forEach(item => item.classList.remove('active'));
      targetItem.classList.add('active');
      hiddenPatientId.value = targetItem.dataset.patientId;
      patientSummary.textContent = targetItem.dataset.patientName;
      patientSummary.style.opacity = 1;
      step1.querySelector('.step-title').style.opacity = 0;
      step1.querySelector('.selection-body').style.display = 'none';
      const hospitalId = targetItem.dataset.hospitalId;
      fetchEquipmentForHospital(hospitalId);
      step2.classList.remove('is-disabled');
      step2.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });

  // --- REWRITTEN Equipment selection logic ---
  equipmentList.addEventListener('click', (e) => {
      e.preventDefault();
      const targetItem = e.target.closest('.list-group-item');
      if (!targetItem) return;

      if (targetItem.classList.contains('is-unavailable')) {
          // Instead of confirm(), we configure and show our Bootstrap modal
          itemToSelect = targetItem; // Store the item that was clicked
          modalTitle.textContent = 'ยืนยันการขอยืม';
          modalBody.innerHTML = `
              <p>อุปกรณ์นี้หมดสต็อกในขณะนี้</p>
              <p class="mb-0">คำขอของท่านจะถูกจัดเก็บในระบบเพื่อรอคิวเมื่อมีอุปกรณ์ว่าง</p>
              <p class="fw-bold mt-3">คุณต้องการส่งคำขอหรือไม่?</p>
          `;
          confirmButton.className = 'btn btn-primary';
          confirmButton.textContent = 'ตกลง';
          confirmationModal.show();
      } else {
          // If the item is available, select it immediately
          selectEquipmentItem(targetItem);
      }
  });
  
  // Add a listener to the modal's confirm button
  confirmButton.addEventListener('click', () => {
      if (itemToSelect) {
          selectEquipmentItem(itemToSelect); // Run the selection logic
          itemToSelect = null; // Reset the stored item
          confirmationModal.hide();
      }
  });

  // Filter for patient list remains the same
  patientSearch.addEventListener('input', () => {
      const filterValue = patientSearch.value.toLowerCase().trim();
      patientList.querySelectorAll('.list-group-item').forEach(item => {
          const name = item.textContent.toLowerCase().trim();
          item.style.display = name.includes(filterValue) ? '' : 'none';
      });
  });

  // Form submission logic remains the same
  form.addEventListener('submit', (e) => {
      if (!form.checkValidity()) {
          e.preventDefault();
          e.stopPropagation();
      }
      form.classList.add('was-validated');
  });
}

function setupReturnActions() {
  const container = document.getElementById('loan-card-container');
  const modalElement = document.getElementById('confirmationModal');

  if (!container || !modalElement) return;

  const confirmationModal = new bootstrap.Modal(modalElement);
  const modalTitle = document.getElementById('confirmationModalLabel');
  const modalBody = document.getElementById('confirmationModalBody');
  const confirmButton = document.getElementById('confirmModalButton');
  const csrfToken = document.getElementById('csrf_token').value;

  let actionToConfirm = null;

  // 1. Listen for the initial click on a "Return" button
  container.addEventListener('click', (event) => {
      const button = event.target.closest('.return-btn');
      if (!button) return;

      const card = button.closest('.loan-card');
      const equipmentName = card.querySelector('.card-title').textContent.trim();
      const loanId = button.dataset.loanId;
      
      actionToConfirm = { loanId, button };

      // Configure and show the modal
      modalTitle.textContent = 'ยืนยันการรับคืนอุปกรณ์';
      modalBody.textContent = `คุณต้องการบันทึกการรับคืนสำหรับ "${equipmentName}" ใช่หรือไม่?`;
      confirmButton.className = 'btn btn-primary';
      confirmButton.textContent = 'ยืนยัน';
      
      confirmationModal.show();
  });

  // 2. Listen for the final confirmation on the modal button
  confirmButton.addEventListener('click', async () => {
      if (!actionToConfirm || !actionToConfirm.loanId) return;

      const { loanId, button } = actionToConfirm;
      
      button.disabled = true;
      button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> กำลังดำเนินการ...';
      confirmationModal.hide();

      try {
          const response = await fetch('api/loan_handler.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: new URLSearchParams({ csrf_token: csrfToken, loan_id: loanId, action: 'return_item' })
          });
          const result = await response.json();
          if (!response.ok) throw new Error(result.error || 'Server error');

          showDynamicToast('บันทึกการรับคืนสำเร็จ', 'success');

          const cardColumn = document.getElementById(`loan-row-${loanId}`);
          if(cardColumn) {
              cardColumn.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
              cardColumn.style.opacity = '0';
              cardColumn.style.transform = 'scale(0.95)';
              setTimeout(() => cardColumn.remove(), 500);
          }
      } catch (error) {
          showDynamicToast(error.message, 'danger');
          button.disabled = false;
          button.innerHTML = '<i class="bi bi-box-arrow-in-left me-1"></i> บันทึกการรับคืน';
      } finally {
          actionToConfirm = null; // Reset for the next action
      }
  });
}
  
function setupApprovalActions() {
  const container = document.getElementById('request-card-container');
  const modalElement = document.getElementById('confirmationModal');
  
  if (!container || !modalElement) return;

  const confirmationModal = new bootstrap.Modal(modalElement);
  const modalTitle = document.getElementById('confirmationModalLabel');
  const modalBody = document.getElementById('confirmationModalBody');
  const confirmButton = document.getElementById('confirmModalButton');
  const csrfToken = document.getElementById('csrf_token').value;
  
  let actionToConfirm = null;

  // 1. Listen for the initial click on an approve or reject button
  container.addEventListener('click', (event) => {
      const button = event.target.closest('.approve-btn, .reject-btn');
      if (!button) return;

      const card = button.closest('.request-card');
      const patientName = card.querySelector('.info-data').textContent.trim();
      const equipmentName = card.querySelector('.card-title').textContent.trim();
      
      const requestId = button.dataset.requestId;
      const action = button.classList.contains('approve-btn') ? 'approve' : 'reject';

      // Store the action details
      actionToConfirm = { requestId, action, button };

      // Configure the modal based on the action
      if (action === 'approve') {
          modalTitle.textContent = 'ยืนยันการอนุมัติ';
          modalBody.textContent = `คุณต้องการอนุมัติคำขอยืม "${equipmentName}" สำหรับคุณ "${patientName}" ใช่หรือไม่?`;
          confirmButton.className = 'btn btn-success';
          confirmButton.textContent = 'ยืนยันการอนุมัติ';
      } else {
          modalTitle.textContent = 'ยืนยันการปฏิเสธ';
          modalBody.textContent = `คุณต้องการปฏิเสธคำขอยืม "${equipmentName}" สำหรับคุณ "${patientName}" ใช่หรือไม่?`;
          confirmButton.className = 'btn btn-danger';
          confirmButton.textContent = 'ยืนยันการปฏิเสธ';
      }
      
      confirmationModal.show();
  });

  // 2. Listen for the final confirmation click on the modal button
  confirmButton.addEventListener('click', async () => {
      if (!actionToConfirm) return;

      const { requestId, action, button } = actionToConfirm;
      
      // Disable buttons and show spinner
      button.closest('.d-grid').querySelectorAll('button').forEach(btn => {
          btn.disabled = true;
          btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
      });
      confirmationModal.hide();

      try {
          const response = await fetch('api/request_handler.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: new URLSearchParams({
                  csrf_token: csrfToken,
                  request_id: requestId,
                  action: action
              })
          });

          const result = await response.json();
          if (!response.ok) throw new Error(result.error || 'Server error');
          
          showDynamicToast('ดำเนินการเรียบร้อยแล้ว', 'success');

          const cardColumn = document.getElementById(`request-row-${requestId}`);
          if(cardColumn) {
              cardColumn.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
              cardColumn.style.opacity = '0';
              cardColumn.style.transform = 'scale(0.95)';
              setTimeout(() => {
                  cardColumn.remove();
                  if(container.querySelectorAll('.request-card').length === 0){
                      const placeholder = document.getElementById('no-requests-row');
                      if (placeholder) placeholder.classList.remove('d-none');
                  }
              }, 500);
          }
      } catch (error) {
          showDynamicToast(error.message, 'danger');
          // Re-enable buttons if something went wrong
          button.closest('.d-grid').querySelectorAll('button').forEach(btn => btn.disabled = false);
          const originalApproveHTML = `<i class="bi bi-check-lg"></i> อนุมัติ`;
          const originalRejectHTML = `<i class="bi bi-x-lg"></i> ไม่อนุมัติ`;
          button.innerHTML = button.classList.contains('approve-btn') ? originalApproveHTML : originalRejectHTML;
          const otherBtn = button.classList.contains('approve-btn') ? button.closest('.d-grid').querySelector('.reject-btn') : button.closest('.d-grid').querySelector('.approve-btn');
          if(otherBtn) otherBtn.innerHTML = otherBtn.classList.contains('reject-btn') ? originalRejectHTML : originalApproveHTML;
      } finally {
          actionToConfirm = null; // Reset for the next action
      }
  });
}

function setupToast() {
    const toastEl = document.getElementById('liveToast');
    if (toastEl) {
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
    }
}

/**
 * Creates and shows a dynamic Bootstrap toast notification for API errors.
 * @param {string} message The message to display.
 * @param {string} type The type of toast ('success', 'danger', 'info', 'warning').
 */

function showDynamicToast(message, type = 'info') {
    const iconMap = {
        success: 'check-circle-fill',
        danger: 'x-circle-fill',
        warning: 'exclamation-triangle-fill',
        info: 'info-circle-fill'
    };
    const icon = iconMap[type] || 'info-circle-fill';

    const toastHTML = `
        <div class="toast align-items-center text-bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fs-6">
                    <i class="bi bi-${icon} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

    const toastContainer = document.querySelector('.toast-container');
    if (toastContainer) {
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        const newToastEl = toastContainer.lastElementChild;
        const newToast = new bootstrap.Toast(newToastEl, { delay: 5000 });

        newToastEl.addEventListener('hidden.bs.toast', () => {
            newToastEl.remove();
        });

        newToast.show();
    }
}

function initializeLtcDashboard() {
    const bodyData = document.body.dataset;

    // Animated Counters for KPI cards
    document.querySelectorAll(".stat-value").forEach(counter => {
        const target = +counter.getAttribute("data-count") || 0;
        if (target === 0) { counter.textContent = "0"; return; }
        const duration = 1500;
        const stepTime = Math.max(1, Math.floor(duration / target));
        let start = 0;
        const timer = setInterval(() => {
            start += 1;
            if (start >= target) {
                counter.textContent = target.toLocaleString('en-US');
                clearInterval(timer);
            } else {
                counter.textContent = start.toLocaleString('en-US');
            }
        }, stepTime);
    });

    // Load Maps and Charts
    loadGoogleMapsScriptForLtc();

    const adlData = JSON.parse(bodyData.adlGroups || '{}');
    const diseaseData = JSON.parse(bodyData.diseaseCounts || '{}');

    const drawCharts = () => {
        if (adlData.labels && adlData.labels.length > 0) {
            setupAdlGroupChart(adlData);
        }
        if (diseaseData.labels && diseaseData.labels.length > 0) {
            setupDiseaseChart(diseaseData);
        }
    };

    if (typeof Chart === 'undefined') {
        // This function should already exist in your script.js
        loadChartLibraries(drawCharts);
    } else {
        drawCharts();
    }
}

async function loadGoogleMapsScriptForLtc() {
    const mapContainer = document.getElementById('ltcDashboardMap');
    if (!mapContainer) return;

    try {
        const response = await fetch('api/get_maps_key.php');
        if (!response.ok) throw new Error('Failed to fetch API key');
        const data = await response.json();
        if (!data.apiKey) throw new Error('API key was not found');

        // Define the map initialization function on the window object
        window.initLtcMap = async function() {
            const mapEl = document.getElementById("ltcDashboardMap");
            const tooltip = document.getElementById('map-tooltip');
            if (!mapEl || !google.maps || !tooltip) return;

            const locations = JSON.parse(document.body.dataset.locations || '[]');
            if (locations.length === 0) {
                mapEl.innerHTML = '<div class="alert alert-light text-center m-3">ไม่มีข้อมูลตำแหน่งผู้ป่วยที่จะแสดง</div>';
                return;
            }

            const map = new google.maps.Map(mapEl, {
                zoom: 10,
                center: { lat: 18.5679, lng: 99.0031 },
                mapId: 'SMARTHEALTH_LTC_MAP_ID',
                disableDefaultUI: true,
                gestureHandling: "greedy"
            });

            const { AdvancedMarkerElement, PinElement } = await google.maps.importLibrary("marker");
            const colorMap = {
                'ผู้สูงอายุ': '#FF4C4C',
                'ผู้พิการ': '#FFD93D',
                'ผู้มีภาวะพึ่งพิง': '#00BFFF',
                'ผู้มีภาวะพึ่งพิงในโครงการ LTC': '#28a745'
            };

            locations.forEach(loc => {
                const lat = parseFloat(loc.lat);
                const lng = parseFloat(loc.lng);
                if (isNaN(lat) || isNaN(lng)) return;

                const markerColor = colorMap[loc.status] || '#6c757d';
                const pinGlyph = new PinElement({
                    background: markerColor,
                    borderColor: '#333333',
                    glyph: '•',
                    glyphColor: 'white',
                    scale: 0.8
                });

                const marker = new AdvancedMarkerElement({
                    map,
                    position: { lat, lng },
                    title: loc.name,
                    content: pinGlyph.element
                });

                // Add mouseover tooltip functionality
                marker.element.addEventListener('mouseover', (event) => {
                    tooltip.innerHTML = `<div class="tooltip-name">${loc.name}</div><div class="tooltip-status">สถานะ: ${loc.status}</div>`;
                    tooltip.style.left = `${event.pageX + 15}px`;
                    tooltip.style.top = `${event.pageY - 45}px`;
                    tooltip.classList.add('visible');
                });

                marker.element.addEventListener('mouseout', () => {
                    tooltip.classList.remove('visible');
                });
            });
        };

        // Create and append the script tag
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${data.apiKey}&libraries=marker&callback=initLtcMap`;
        script.async = true;
        script.defer = true;
        document.head.appendChild(script);

    } catch (error) {
        console.error('Could not load Google Maps:', error);
        mapContainer.innerHTML = '<div class="alert alert-danger m-3">Could not load map.</div>';
    }
}


// Replace or update these chart functions with the enhanced versions below
function setupAdlGroupChart(chartData) {
    const ctx = document.getElementById('adlGroupChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: chartData.labels,
            datasets: [{
                data: chartData.data,
                backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545'],
                borderColor: '#fff',
                borderWidth: 2,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { font: { family: "'Kanit', sans-serif" }, padding: 12 } },
                datalabels: { color: '#fff', font: { weight: 'bold', family: "'Kanit', sans-serif" } }
            }
        },
        plugins: [ChartDataLabels]
    });
}

function setupDiseaseChart(chartData) {
    const ctx = document.getElementById('diseaseChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'จำนวนผู้ป่วย',
                data: chartData.data,
                backgroundColor: 'rgba(255, 99, 132, 0.7)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                datalabels: { anchor: 'end', align: 'end', color: '#555', font: { weight: 'bold', family: "'Kanit', sans-serif" } }
            }
        },
        plugins: [ChartDataLabels]
    });
}
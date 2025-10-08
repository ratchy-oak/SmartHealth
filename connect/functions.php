<?php
function toThaiDateTime($dateTimeStr) {
    if (!$dateTimeStr) {
        return 'N/A';
    }
    $date = new DateTime($dateTimeStr);

    $thaiMonths = [
        'January'   => 'มกราคม', 'February' => 'กุมภาพันธ์', 'March' => 'มีนาคม',
        'April'     => 'เมษายน', 'May'      => 'พฤษภาคม', 'June'    => 'มิถุนายน',
        'July'      => 'กรกฎาคม', 'August'   => 'สิงหาคม', 'September' => 'กันยายน',
        'October'   => 'ตุลาคม', 'November' => 'พฤศจิกายน', 'December' => 'ธันวาคม'
    ];

    $day = $date->format('d');
    $month = $thaiMonths[$date->format('F')];
    $year = (int)$date->format('Y') + 543;
    $time = $date->format('H:i');

    return "{$day} {$month} {$year} เวลา {$time} น.";
}

function toThaiDate($dateStr) {
    if (!$dateStr) {
        return 'N/A';
    }
    $date = new DateTime($dateStr);

    $thaiMonths = [
        'January'   => 'มกราคม', 'February' => 'กุมภาพันธ์', 'March' => 'มีนาคม',
        'April'     => 'เมษายน', 'May'      => 'พฤษภาคม', 'June'    => 'มิถุนายน',
        'July'      => 'กรกฎาคม', 'August'   => 'สิงหาคม', 'September' => 'กันยายน',
        'October'   => 'ตุลาคม', 'November' => 'พฤศจิกายน', 'December' => 'ธันวาคม'
    ];

    $day = $date->format('d');
    $month = $thaiMonths[$date->format('F')];
    $year = (int)$date->format('Y') + 543;

    return "{$day} {$month} {$year}";
}

function sanitize_text($value) {
    if (is_array($value)) {
        return array_map('sanitize_text', $value); // recursively sanitize array items
    }
    return htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8');
}
?>
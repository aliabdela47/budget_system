<?php

// 🔹 Map Ethiopian months to fiscal quarters
function getQuarterFromEtMonth($month) {
    static $map = [
        'መስከረም' => 1, 'ጥቅምት' => 1, 'ኅዳር' => 1,
        'ታኅሣሥ' => 2, 'ጥር' => 2, 'የካቲት' => 2,
        'መጋቢት' => 3, 'ሚያዝያ' => 3, 'ግንቦት' => 3,
        'ሰኔ' => 4, 'ሐምሌ' => 4, 'ነሐሴ' => 4,
        'ፓጉሜ' => 4
    ];
    return $map[$month] ?? null;
}

// 🔹 Convert Gregorian date to Ethiopian month and quarter
function getEtMonthAndQuarter($gregDate) {
    static $monthMap = [
        'ሐምሌ' => 1, 'ነሐሴ' => 1, 'መስከረም' => 1,
        'ጥቅምት' => 2, 'ህዳር' => 2, 'ታኅሣሥ' => 2,
        'ጥር' => 3, 'የካቲቷ' => 3, 'መጋቢቷ' => 3,
        'ሚያዝያ' => 4, 'ግንቦቷ' => 4, 'ሰኔ' => 4
    ];

    $date = new DateTime($gregDate);
    $month = (int)$date->format('m');
    $day = (int)$date->format('d');
    $etMonth = 'Unknown';

    if (($month == 7 && $day >= 8) || ($month == 8 && $day <= 6)) $etMonth = 'ሐምሌ';
    elseif (($month == 8 && $day >= 7) || ($month == 9 && $day <= 5)) $etMonth = 'ነሐሴ';
    elseif (($month == 9 && $day >= 6 && $day <= 10)) $etMonth = 'መስከረም';
    elseif (($month == 9 && $day >= 11) || ($month == 10 && $day <= 10)) $etMonth = 'መስከረም';
    elseif (($month == 10 && $day >= 11) || ($month == 11 && $day <= 9)) $etMonth = 'ጥቅምት';
    elseif (($month == 11 && $day >= 10) || ($month == 12 && $day <= 9)) $etMonth = 'ህዳር';
    elseif (($month == 12 && $day >= 10) || ($month == 1 && $day <= 8)) $etMonth = 'ታኅሣሥ';
    elseif (($month == 1 && $day >= 9) || ($month == 2 && $day <= 7)) $etMonth = 'ጥር';
    elseif (($month == 2 && $day >= 8) || ($month == 3 && $day <= 9)) $etMonth = 'የካቲቷ';
    elseif (($month == 3 && $day >= 10) || ($month == 4 && $day <= 8)) $etMonth = 'መጋቢቷ';
    elseif (($month == 4 && $day >= 9) || ($month == 5 && $day <= 8)) $etMonth = 'ሚያዝያ';
    elseif (($month == 5 && $day >= 9) || ($month == 6 && $day <= 7)) $etMonth = 'ግንቦቷ';
    elseif (($month == 6 && $day >= 8) || ($month == 7 && $day <= 7)) $etMonth = 'ሰኔ';

    return [
        'etMonth' => $etMonth,
        'quarter' => $monthMap[$etMonth] ?? 0
    ];
}

// 🔹 Get current Ethiopian month (approximate)
function get_current_ethiopian_month() {
    $ethiopian_months = [
        'መስከረም', 'ጥቅምት', 'ህዳር', 'ታኅሣሥ',
        'ጥር', 'የካቲት', 'መጋቢት', 'ሚያዝያ',
        'ግንቦት', 'ሰኔ', 'ሐምሌ', 'ነሐሴ'
    ];

    // This is a rough approximation; for precision, use a proper Ethiopian calendar library
    $month_index = (date('m') + 7) % 12;
    return $ethiopian_months[$month_index];
}

// 🔹 Fetch all budgets (placeholder)
function fetchBudgets($pdo) {
    return $pdo->query("SELECT * FROM budgets")->fetchAll();
}

// 🔹 Expose month and quarter map globally if needed
$etMonths = [
    'መስከረም', 'ጥቅምት', 'ኅዳር', 'ታኅሣሥ',
    'ጥር', 'የካቲት', 'መጋቢት', 'ሚያዝያ',
    'ግንቦት', 'ሰኔ', 'ሐምሌ', 'ነሐሴ', 'ፓጉሜ'
];

$quarterMap = array_combine($etMonths, array_map('getQuarterFromEtMonth', $etMonths));
?>
<?php

function fetchBudgets($pdo) {
      return $pdo->query("SELECT ...")->fetchAll();
  }
  
function getEtMonthAndQuarter($gregDate) {
    $quarterMap = [
        'ሐምሌ' => 1, 'ነሐሴ' => 1, 'መስከረም' => 1,
        'ጥቅምት' => 2, 'ህዳር' => 2, 'ታኅሳስ' => 2,
        'ጥር' => 3, 'የካቲቷ' => 3, 'መጋቢቷ' => 3,
        'ሚያዝያ' => 4, 'ግንቦቷ' => 4, 'ሰኔ' => 4,
    ];
    $date = new DateTime($gregDate);
    $month = (int)$date->format('m');
    $day = (int)$date->format('d');
    $etMonth = 'Unknown';

    if (($month == 7 && $day >= 8) || ($month == 8 && $day <= 6)) $etMonth = 'ሐምሌ';
    else if (($month == 8 && $day >= 7) || ($month == 9 && $day <= 5)) $etMonth = 'ነሐሴ';
    else if (($month == 9 && $day >= 6 && $day <= 10)) $etMonth = 'መስከረም';
    else if (($month == 9 && $day >= 11) || ($month == 10 && $day <= 10)) $etMonth = 'መስከረም';
    else if (($month == 10 && $day >= 11) || ($month == 11 && $day <= 9)) $etMonth = 'ጥቅምት';
    else if (($month == 11 && $day >= 10) || ($month == 12 && $day <= 9)) $etMonth = 'ህዳር';
    else if (($month == 12 && $day >= 10) || ($month == 1 && $day <= 8)) $etMonth = 'ታኅሳስ';
    else if (($month == 1 && $day >= 9) || ($month == 2 && $day <= 7)) $etMonth = 'ጥር';
    else if (($month == 2 && $day >= 8) || ($month == 3 && $day <= 9)) $etMonth = 'የካቲቷ';
    else if (($month == 3 && $day >= 10) || ($month == 4 && $day <= 8)) $etMonth = 'መጋቢቷ';
    else if (($month == 4 && $day >= 9) || ($month == 5 && $day <= 8)) $etMonth = 'ሚያዝያ';
    else if (($month == 5 && $day >= 9) || ($month == 6 && $day <= 7)) $etMonth = 'ግንቦቷ';
    else if (($month == 6 && $day >= 8) || ($month == 7 && $day <= 7)) $etMonth = 'ሰኔ';

    return ['etMonth' => $etMonth, 'quarter' => $quarterMap[$etMonth] ?? 0];
}

function get_current_ethiopian_month() {
    $ethiopian_months = [
        'መስከረም', 'ጥቅምት', 'ህዳር', 'ታኅሳስ', 
        'ጥር', 'የካቲት', 'መጋቢት', 'ሚያዝያ', 
        'ግንቦት', 'ሰኔ', 'ሐምሌ', 'ነሐሴ'
    ];
    
    // This is a simplified calculation - you might need a more accurate Ethiopian calendar implementation
    $month_index = (date('m') + 7) % 12;
    return $ethiopian_months[$month_index];
}

$quarterMap = [
    'ሐምሌ' => 1, 'ነሐሴ' => 1, 'መስከረም' => 1,
    'ጥቅምት' => 2, 'ህዳር' => 2, 'ታኅሳስ' => 2,
    'ጥር' => 3, 'የካቲቷ' => 3, 'መጋቢቷ' => 3,
    'ሚያዝያ' => 4, 'ግንቦቷ' => 4, 'ሰኔ' => 4,
];

$etMonths = array_keys($quarterMap);
?>
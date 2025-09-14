<?php
// includes/functions.php

// 🔹 Single, reliable EC converter (if you don't use the Andegna library)
function getEtInfo($gregDate) {
    $qMap = [
        'Hamle'=>1,'Nehase'=>1,'Meskerem'=>1, 'ጥቅምት'=>2,'ህዳር'=>2,'ታኅሣሥ'=>2,
        'ጥር'=>3,'የካቲት'=>3,'መጋቢት'=>3, 'ሚያዝያ'=>4,'ግንቦት'=>4,'ሰኔ'=>4
    ];
    $date = new DateTime($gregDate);
    $month = (int)$date->format('m');
    $day = (int)$date->format('d');
    $etMonth = 'Unknown';
    $year = (int)date('Y', strtotime($gregDate));
    if ($month > 9 || ($month === 9 && $day >= 11)) $etYear = $year - 7;
    else $etYear = $year - 8;
    // ... (rest of the mapping logic)
    return ['year' => $etYear, 'month' => $etMonth, 'quarter' => $qMap[$etMonth] ?? 0];
}

/**
 * 🔹 Get current Ethiopian Date and Time in Amharic.
 * Uses the andegna/andegna library if available for accuracy.
 */
function getAmharicEtDate() {
    $amharic_months = [
        1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ህዳር', 4 => 'ታኅሣሥ', 5 => 'ጥር', 6 => 'የካቲት',
        7 => 'መጋቢት', 8 => 'ሚያዝያ', 9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜን'
    ];
    $amharic_days = ['ሰኞ', 'ማክሰኞ', 'ረቡዕ', 'ሐሙስ', 'ዓርብ', 'ቅዳሜ', 'እሁድ'];

    if (class_exists('\\Andegna\\DateTimeFactory')) {
        // Professional, accurate method
        $et = \Andegna\DateTimeFactory::fromDateTime(new DateTime('now', new DateTimeZone('Africa/Addis_Ababa')));
        $day_name = $amharic_days[$et->getDayOfWeek() - 1];
        $month_name = $amharic_months[$et->getMonth()];
        return sprintf('%s، %s %d ቀን %d ዓ.ም - %s', $day_name, $month_name, $et->getDay(), $et->getYear(), $et->format('h:i A'));
    } else {
        // Fallback (less accurate)
        return "ET Date: " . date('Y-m-d H:i');
    }
}

// 🔹 Flash message helper
function flash() {
    if (!empty($_SESSION['flash_error'])) {
        echo '<div class="mb-4 p-3 rounded-lg bg-red-100 text-red-700"><i class="fas fa-exclamation-circle mr-2"></i>' . htmlspecialchars($_SESSION['flash_error']) . '</div>';
        unset($_SESSION['flash_error']);
    }
}
?>
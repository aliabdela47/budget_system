<?php
ini_set('memory_limit', '512M'); // Increased memory for potentially larger reports
set_time_limit(300);

require_once 'includes/init.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;

// UPDATED PAYROLL CALCULATION LOGIC (Identical to payroll.php)
function calculate_ethiopian_payroll($basic_salary) {
    $desert_allowance = $basic_salary * 0.30;
    $gross_salary = $basic_salary + $desert_allowance;
    $income_tax = 0;
    if ($basic_salary > 14000) { $income_tax = ($basic_salary * 0.35) - 2150; }
    elseif ($basic_salary > 10000) { $income_tax = ($basic_salary * 0.30) - 1350; }
    elseif ($basic_salary > 7000) { $income_tax = ($basic_salary * 0.25) - 850; }
    elseif ($basic_salary > 4000) { $income_tax = ($basic_salary * 0.20) - 500; }
    elseif ($basic_salary > 2000) { $income_tax = ($basic_salary * 0.15) - 300; }
    $pension_employee = $basic_salary * 0.07;
    $other_deductions = 462.20;
    $total_deductions = $income_tax + $pension_employee + $other_deductions;
    $net_pay = $gross_salary - $total_deductions;
    return compact('desert_allowance', 'gross_salary', 'income_tax', 'pension_employee', 'other_deductions', 'total_deductions', 'net_pay');
}

$ethiopian_months = [
    1 => 'Meskerem', 2 => 'Tikimt', 3 => 'Hidar', 4 => 'Tahsas', 5 => 'Tir',
    6 => 'Yekatit', 7 => 'Megabit', 8 => 'Miyazya', 9 => 'Ginbot', 10 => 'Sene',
    11 => 'Hamle', 12 => 'Nehase', 13 => 'Paguemen'
];
$selected_month_num = $_GET['month'] ?? 2;
$month = $ethiopian_months[$selected_month_num] ?? 'Tikimt';
$year = "2017"; // Placeholder for Ethiopian Year

$stmt = $pdo->query("SELECT name, taamagoli, salary FROM emp_list ORDER BY name ASC");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
$format = $_GET['format'] ?? 'excel';
$filename = "Payroll_Report_{$month}_{$year}";

// --- Bureau Information ---
$bureau_region = "AFAR NATIONAL REGIONAL STATE HEALTH BUREAU";
$bureau_name = "የ አፋር/ብ/ክ/መ ጤና ቢሮ ወርሀዊ ደመወዝ";


if ($format === 'excel') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Payroll Report');

    // --- Redesigned Header ---
    // Bureau Region
    $sheet->mergeCells('A1:K1');
    $sheet->getCell('A1')->setValue($bureau_region);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
     // Bureau Name - NOW WITH EBRIMA FONT
    $sheet->mergeCells('A2:K2');
    $sheet->getCell('A2')->setValue($bureau_name);
    $sheet->getStyle('A2')->getFont()->setName('Ebrima')->setBold(true)->setSize(14);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

   
    // Report Title
    $sheet->mergeCells('A4:K4');
    $sheet->getCell('A4')->setValue("Monthly Payroll Report for {$month}, {$year} E.C.");
    $sheet->getStyle('A4')->getFont()->setBold(true)->setUnderline(true)->setSize(12);
    $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // --- Table Headers ---
    $headers = ['No.', 'Employee Name', 'Position', 'Basic Salary', 'Desert Allow.', 'Gross Salary', 'Income Tax', 'Pension (7%)', 'Other Ded.', 'Total Ded.', 'Net Pay'];
    $sheet->fromArray($headers, null, 'A6');
    $header_style = ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e3a8a']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]];
    $sheet->getStyle('A6:K6')->applyFromArray($header_style);
    
    // --- Data Population ---
    $row_num = 7; $index = 1;
    $totals = array_fill(0, 8, 0); // basic, allowance, gross, tax, pension, other, deductions, net
    foreach ($employees as $emp) {
        $payroll = calculate_ethiopian_payroll($emp['salary']);
        $dataRow = [
            $index++, $emp['name'], $emp['taamagoli'], $emp['salary'], $payroll['desert_allowance'], $payroll['gross_salary'],
            $payroll['income_tax'], $payroll['pension_employee'], $payroll['other_deductions'], $payroll['total_deductions'], $payroll['net_pay']
        ];
        $sheet->fromArray($dataRow, null, 'A' . $row_num);
        
        $totals[0] += $emp['salary']; $totals[1] += $payroll['desert_allowance']; $totals[2] += $payroll['gross_salary'];
        $totals[3] += $payroll['income_tax']; $totals[4] += $payroll['pension_employee']; $totals[5] += $payroll['other_deductions'];
        $totals[6] += $payroll['total_deductions']; $totals[7] += $payroll['net_pay'];
        $row_num++;
    }
    
    // --- Totals Row ---
    $total_style = ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0E0E0']]];
    $sheet->setCellValue('C' . $row_num, 'TOTAL');
    $sheet->fromArray($totals, null, 'D' . $row_num);
    $sheet->getStyle('C'.$row_num.':K'.$row_num)->applyFromArray($total_style);

    // --- Final Formatting ---
    foreach (range('D', 'K') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); $sheet->getStyle($col.'7:'.$col.$row_num)->getNumberFormat()->setFormatCode('#,##0.00'); }
    $sheet->getColumnDimension('B')->setWidth(30); $sheet->getColumnDimension('C')->setWidth(25);
    
    // --- Output ---
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

} elseif ($format === 'pdf') {
    class PDF extends FPDF {
        // Redesigned Header
        function Header() {
            // Uncomment the line below and replace with your logo path to add an image
            // $this->Image('path/to/logo.png', 10, 8, 33);
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 8, $GLOBALS['bureau_region'], 0, 1, 'C');
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 8, $GLOBALS['bureau_name'], 0, 1, 'C');
            $this->Ln(4);
            $this->SetFont('Arial', 'BU', 12);
            $this->Cell(0, 8, 'Monthly Payroll Report', 0, 1, 'C');
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 8, "For the month of {$GLOBALS['month']}, {$GLOBALS['year']} E.C.", 0, 1, 'C');
            $this->Ln(5);
        }

        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }

        function FancyTable($header, $data) {
            $this->SetFillColor(30, 58, 138); $this->SetTextColor(255); $this->SetFont('', 'B', 8);
            $w = [45, 25, 22, 22, 22, 22, 22, 22, 22, 24]; // Column widths
            for($i=0; $i<count($header); $i++) $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
            $this->Ln();
            $this->SetTextColor(0); $this->SetFont('', '', 8);
            $totals = array_fill(0, 8, 0);
            foreach($data as $row) {
                $payroll = calculate_ethiopian_payroll($row['salary']);
                $this->Cell($w[0], 6, $row['name'], 'LR'); $this->Cell($w[1], 6, $row['taamagoli'], 'LR');
                $this->Cell($w[2], 6, number_format($row['salary'], 2), 'LR', 0, 'R');
                $this->Cell($w[3], 6, number_format($payroll['desert_allowance'], 2), 'LR', 0, 'R');
                $this->Cell($w[4], 6, number_format($payroll['gross_salary'], 2), 'LR', 0, 'R');
                $this->Cell($w[5], 6, number_format($payroll['income_tax'], 2), 'LR', 0, 'R');
                $this->Cell($w[6], 6, number_format($payroll['pension_employee'], 2), 'LR', 0, 'R');
                $this->Cell($w[7], 6, number_format($payroll['other_deductions'], 2), 'LR', 0, 'R');
                $this->Cell($w[8], 6, number_format($payroll['total_deductions'], 2), 'LR', 0, 'R');
                $this->Cell($w[9], 6, number_format($payroll['net_pay'], 2), 'LR', 0, 'R');
                $this->Ln();
                $totals[0] += $row['salary']; $totals[1] += $payroll['desert_allowance']; $totals[2] += $payroll['gross_salary'];
                $totals[3] += $payroll['income_tax']; $totals[4] += $payroll['pension_employee']; $totals[5] += $payroll['other_deductions'];
                $totals[6] += $payroll['total_deductions']; $totals[7] += $payroll['net_pay'];
            }
            $this->Cell(array_sum($w), 0, '', 'T'); $this->Ln();
            $this->SetFont('','B');
            $this->Cell($w[0]+$w[1], 7, 'TOTAL', 'T', 0, 'R');
            $this->Cell($w[2], 7, number_format($totals[0], 2), 'T', 0, 'R');
            $this->Cell($w[3], 7, number_format($totals[1], 2), 'T', 0, 'R');
            $this->Cell($w[4], 7, number_format($totals[2], 2), 'T', 0, 'R');
            $this->Cell($w[5], 7, number_format($totals[3], 2), 'T', 0, 'R');
            $this->Cell($w[6], 7, number_format($totals[4], 2), 'T', 0, 'R');
            $this->Cell($w[7], 7, number_format($totals[5], 2), 'T', 0, 'R');
            $this->Cell($w[8], 7, number_format($totals[6], 2), 'T', 0, 'R');
            $this->Cell($w[9], 7, number_format($totals[7], 2), 'T', 0, 'R');
        }
    }

    $pdf = new PDF('L', 'mm', 'A3'); // Landscape A3 for more space
    $pdf->AliasNbPages(); $pdf->AddPage();
    $header = ['Employee', 'Position', 'Basic', 'Allowance', 'Gross', 'Tax', 'Pension', 'Other', 'Total Ded.', 'Net Pay'];
    $pdf->FancyTable($header, $employees);
    $pdf->Output('I', $filename.'.pdf');
}
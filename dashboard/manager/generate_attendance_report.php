<?php
session_start();
require_once '../../config/database.php';
require_once '../admin/includes/functions.php';

// Check if user is logged in and is manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../../login/manager.php");
    exit();
}

// Include TCPDF library
require_once('../../libraries/tcpdf/tcpdf.php');

// Get request parameters
$date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
$dept_id = isset($_POST['dept_id']) ? $_POST['dept_id'] : null;

if (!$dept_id) {
    die("Department ID is required");
}

// Create new PDF document
class MYPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'Attendance Report', 0, true, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(5);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Create new PDF document
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('EMS System');
$pdf->SetTitle('Attendance Report');

// Set default header data
$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

// Set margins
$pdf->SetMargins(15, 25, 15);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 15);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 10);

// Get department details
$stmt = $pdo->prepare("SELECT dept_name FROM departments WHERE dept_id = ?");
$stmt->execute([$dept_id]);
$department = $stmt->fetch();

// Add report header
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Department: ' . $department['dept_name'], 0, 1, 'L');
$pdf->Cell(0, 10, 'Date: ' . date('F d, Y', strtotime($date)), 0, 1, 'L');
$pdf->Ln(5);

// Get attendance data
$stmt = $pdo->prepare("
    SELECT 
        e.emp_id,
        e.first_name,
        e.last_name,
        e.position,
        a.time_in,
        a.time_out,
        a.total_hours,
        a.status,
        a.remarks
    FROM employees e
    LEFT JOIN attendance a ON e.emp_id = a.emp_id AND DATE(a.attendance_date) = ?
    WHERE e.dept_id = ?
    ORDER BY e.first_name ASC
");
$stmt->execute([$date, $dept_id]);
$attendance_records = $stmt->fetchAll();

// Create table header
$pdf->SetFont('helvetica', 'B', 10);
$header = array('Employee Name', 'Position', 'Time In', 'Time Out', 'Hours', 'Status');
$w = array(50, 40, 25, 25, 20, 30);

// Colors, line width and bold font
$pdf->SetFillColor(66, 115, 223);
$pdf->SetTextColor(255);
$pdf->SetDrawColor(66, 115, 223);
$pdf->SetLineWidth(0.3);
$pdf->SetFont('', 'B');

// Header
for($i = 0; $i < count($header); $i++) {
    $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
}
$pdf->Ln();

// Color and font restoration
$pdf->SetFillColor(245, 246, 250);
$pdf->SetTextColor(0);
$pdf->SetFont('');

// Data
$fill = false;
foreach($attendance_records as $row) {
    $pdf->Cell($w[0], 6, $row['first_name'] . ' ' . $row['last_name'], 'LR', 0, 'L', $fill);
    $pdf->Cell($w[1], 6, $row['position'], 'LR', 0, 'L', $fill);
    $pdf->Cell($w[2], 6, $row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : '-', 'LR', 0, 'C', $fill);
    $pdf->Cell($w[3], 6, $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '-', 'LR', 0, 'C', $fill);
    $pdf->Cell($w[4], 6, $row['total_hours'] ? number_format($row['total_hours'], 2) : '-', 'LR', 0, 'C', $fill);
    $pdf->Cell($w[5], 6, ucfirst($row['status'] ?? 'Not Marked'), 'LR', 0, 'C', $fill);
    $pdf->Ln();
    $fill = !$fill;
}

// Closing line
$pdf->Cell(array_sum($w), 0, '', 'T');

// Add summary
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 10, 'Attendance Summary', 0, 1, 'L');

// Calculate statistics
$present = 0;
$absent = 0;
$late = 0;
$halfday = 0;
$total_hours = 0;

foreach($attendance_records as $record) {
    if(isset($record['status'])) {
        switch($record['status']) {
            case 'present':
                $present++;
                break;
            case 'absent':
                $absent++;
                break;
            case 'late':
                $late++;
                break;
            case 'half-day':
                $halfday++;
                break;
        }
    } else {
        $absent++;
    }
    $total_hours += $record['total_hours'] ?? 0;
}

$total_employees = count($attendance_records);
$avg_hours = $total_employees > 0 ? $total_hours / $total_employees : 0;

// Add statistics to PDF
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'Total Employees: ' . $total_employees, 0, 1, 'L');
$pdf->Cell(0, 6, 'Present: ' . $present . ' (' . round(($present/$total_employees)*100) . '%)', 0, 1, 'L');
$pdf->Cell(0, 6, 'Absent: ' . $absent . ' (' . round(($absent/$total_employees)*100) . '%)', 0, 1, 'L');
$pdf->Cell(0, 6, 'Late: ' . $late . ' (' . round(($late/$total_employees)*100) . '%)', 0, 1, 'L');
$pdf->Cell(0, 6, 'Half Day: ' . $halfday . ' (' . round(($halfday/$total_employees)*100) . '%)', 0, 1, 'L');
$pdf->Cell(0, 6, 'Average Hours Worked: ' . number_format($avg_hours, 2) . ' hours', 0, 1, 'L');

// Output the PDF
$pdf->Output('attendance_report_' . $date . '.pdf', 'I');
?> 
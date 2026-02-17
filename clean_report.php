<?php
/**
 * Clean Report View - Shows exact report content without navigation
 * For secure patient access
 */

// Include required files
require_once 'config/timezone.php';
require_once 'config/database.php';
require_once 'config/auth.php';

// Get report ID from URL
$reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($reportId <= 0) {
    die('Invalid report ID');
}

// Get report data
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Get report basic info
$query = "SELECT r.*, p.name as patient_name, p.civil_id, p.mobile 
          FROM reports r 
          JOIN patients p ON r.patient_id = p.id 
          WHERE r.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $reportId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Report not found');
}

$report = $result->fetch_assoc();
$stmt->close();

// Get clinic info (hardcoded since clinics table doesn't exist)
$clinic = [
    'name' => 'BATO CLINIC',
    'phone' => '+965 XXXXXXXX',
    'email' => 'info@batoclinic.com',
    'website' => 'www.batoclinic.com',
    'address' => 'Kuwait'
];

// Get test results
$testsQuery = "SELECT rt.test_value, rt.flag, rt.remarks, tt.name as test_name, tt.unit, tt.normal_range
               FROM report_tests rt
               JOIN test_types tt ON rt.test_type_id = tt.id
               WHERE rt.report_id = ?";
$testsStmt = $conn->prepare($testsQuery);
$testsStmt->bind_param("i", $reportId);
$testsStmt->execute();
$testsResult = $testsStmt->get_result();
$testsStmt->close();
$conn->close();

// Calculate file number
$fileNumber = 'RPT-' . str_pad($reportId, 4, '0', STR_PAD_LEFT);

// Get visit date
$visitDate = date('d/m/Y', strtotime($report['report_date']));
$printedDate = date('d/m/Y H:i:s');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Report - <?php echo $report['patient_name']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            body {
                margin: 0;
                padding: 15px;
            }
            
            .report-container {
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
            }
        }
        
        .bato-logo {
            max-height: 60px;
            margin-bottom: 10px;
        }
        
        .clinic-info {
            text-align: right;
            font-size: 0.8rem;
            line-height: 1.1;
        }
        
        .report-container {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 25px;
        }
        
        .report-test-name {
            font-weight: 500;
            white-space: normal;
            word-break: break-word;
            font-size: 0.75rem;
        }
        
        .report-unit, .report-range {
            width: 60px;
            font-size: 0.7rem;
        }
        
        .table th {
            background-color: #f8f9fa;
            border-top: 2px solid #dee2e6;
            font-size: 0.75rem;
            padding: 2px;
        }
        
        .table td {
            font-size: 0.7rem;
            padding: 2px;
        }
        
        .flag-normal {
            color: #28a745;
            font-weight: bold;
        }
        
        .flag-high, .flag-low {
            color: #dc3545;
            font-weight: bold;
        }
        
        .doctor-signature {
            max-height: 60px;
        }
        
        .no-print {
            display: none !important;
        }
        
        .patient-info th {
            font-size: 0.75rem;
            padding: 1px;
            width: 80px;
        }
        
        .patient-info td {
            font-size: 0.75rem;
            padding: 1px;
        }
    </style>
</head>
<body>
    <!-- Report Content Only -->
    <div class="container report-container">
        <!-- Header with Logo and Clinic Info -->
        <div class="row">
            <div class="col-6 text-start">
                <!-- BATO Health/Beauty Logo -->
                <img src="assets/images/IMG_4554.PNG" alt="BATO Health/Beauty" class="bato-logo">
            </div>
            <div class="col-6 text-end">
                <div class="clinic-info">
                    <p style="margin-bottom: 5px; font-size: 12px;">BATO CLINIC</p>
                    <p style="margin-bottom: 0; font-size: 12px;">Phone: <?php echo $clinic['phone']; ?></p>
                    <p style="margin-bottom: 0; font-size: 12px;">Email: <?php echo $clinic['email']; ?></p>
                    <p style="margin-bottom: 0; font-size: 12px;">Website: <?php echo $clinic['website']; ?></p>
                    <p style="margin-bottom: 0; font-size: 12px;"><?php echo $clinic['address']; ?></p>
                </div>
            </div>
        </div>
        <hr style="margin-top: 0; margin-bottom: 15px;">

        <!-- Patient Information -->
        <div class="row mb-2">
            <div class="col-12">
                <table class="table table-sm table-borderless patient-info">
                    <tr>
                        <th style="padding: 1px;">Name</th>
                        <td style="padding: 1px;">: <?php echo $report['patient_name']; ?></td>
                        <th style="padding: 1px;">Civil ID</th>
                        <td style="padding: 1px;">: <?php echo $report['civil_id']; ?></td>
                        <th style="padding: 1px;">Mobile</th>
                        <td style="padding: 1px;">: <?php echo $report['mobile']; ?></td>
                    </tr>
                    <tr>
                        <th style="padding: 1px;">Report Date</th>
                        <td style="padding: 1px;">: <?php echo $visitDate; ?></td>
                        <th style="padding: 1px;">Printed At</th>
                        <td style="padding: 1px;">: <?php echo $printedDate; ?></td>
                        <td colspan="2"></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Lab Results -->
        <div class="row">
            <div class="col-12">
                <h5 class="mb-3">Lab Results</h5>
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr style="font-size: 0.9rem;">
                            <th style="width:40%">Test</th>
                            <th style="width:15%">Result</th>
                            <th class="report-unit">Unit</th>
                            <th class="report-range">Ref. Range</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: 0.85rem;">
                        <?php
                        if ($testsResult && $testsResult->num_rows > 0) {
                            while ($test = $testsResult->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td class='report-test-name' style='padding: 3px 5px;'>{$test['test_name']}</td>";
                                
                                // Display test value with flag color
                                if (!empty($test['flag'])) {
                                    $flag = strtoupper($test['flag']);
                                    $flagColor = ($flag === 'NORMAL') ? 'green' : (($flag === 'HIGH' || $flag === 'LOW') ? 'red' : '');
                                    $flagStyle = $flagColor ? "color: $flagColor; font-weight: bold;" : '';
                                    echo "<td style='padding: 3px 5px;'>{$test['test_value']} <span style='$flagStyle'>{$test['flag']}</span></td>";
                                } else {
                                    echo "<td style='padding: 3px 5px;'>{$test['test_value']}</td>";
                                }
                                
                                echo "<td style='padding: 3px 5px;'>{$test['unit']}</td>";
                                echo "<td style='padding: 3px 5px;'>{$test['normal_range']}</td>";
                                echo "</tr>";
                                
                                // Display remarks if present
                                if (!empty($test['remarks'])) {
                                    echo "<tr>";
                                    echo "<td colspan='4' style='padding: 2px 5px;'><small><strong>Remarks:</strong> ";
                                    
                                    // Combine remarks into a single line with bullet points
                                    $remarks = explode("\n", $test['remarks']);
                                    $formattedRemarks = [];
                                    foreach ($remarks as $remark) {
                                        if (trim($remark) !== '') {
                                            $formattedRemarks[] = "• " . trim($remark);
                                        }
                                    }
                                    echo implode(" | ", $formattedRemarks);
                                    echo "</small></td>";
                                    echo "</tr>";
                                }
                            }
                        } else {
                            echo "<tr><td colspan='4' class='text-center'>No test results found</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 50px space after test table -->
        <div style="height:20px;"></div>

        <!-- Conclusion and Signature -->
        <div style="page-break-inside: avoid;">
            <?php if (!empty($report['conclusion'])): ?>
            <div class="alert alert-secondary mt-4" style="border: 2px solid #888; background: #f9f9f9;">
                <strong>Conclusion:</strong><br>
                <span style="white-space: pre-line;">
                    <?php echo nl2br(htmlspecialchars($report['conclusion'])); ?>
                </span>
            </div>
            <?php endif; ?>

            <!-- Signature block -->
            <div style="margin-top: 30px;">
                <div class="row">
                    <div class="col-8 text-end">
                        <?php if (!empty($report['signature_image_path'])): ?>
                        <img src="<?php echo $report['signature_image_path']; ?>" alt="Doctor Signature" class="doctor-signature" style="max-height: 50px;">
                        <?php else: ?>
                        <div style="border-top: 1px solid #333; width: 150px; margin-left: auto; margin-top: 20px;">
                            <p style="text-align: center; font-size: 10px; margin: 0;">Doctor's Signature</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-4">
                        <?php if (!empty($report['doctor_name'])): ?>
                        <p style="text-align: center; font-weight: bold; font-size: 11px; margin: 0;"><?php echo $report['doctor_name']; ?></p>
                        <p style="text-align: center; font-size: 9px; margin: 0;"><?php echo $report['doctor_position'] ?? 'Medical Doctor'; ?></p>
                        <?php else: ?>
                        <p style="text-align: center; font-weight: bold; font-size: 11px; margin: 0;">Dr. [Doctor Name]</p>
                        <p style="text-align: center; font-size: 9px; margin: 0;">Medical Doctor</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Script -->
    <script>
        // Auto-print when page loads (optional)
        window.addEventListener('load', function() {
            // Uncomment the next line if you want auto-print
            // window.print();
        });
    </script>
</body>
</html>

<?php
// Check if the download_pdf.php file exists
$pdfFile = 'download_pdf.php';
if (!file_exists($pdfFile)) {
    die("Error: Could not find the PDF generation file.");
}

// Read the current content
$content = file_get_contents($pdfFile);

// Find the logo image code and replace it with text-based logo
$pattern = '/\$pdf->Image\(\'assets\/images\/logo\.png\',.*?\);/';
$replacement = '// Text-based logo instead of image
$pdf->SetFont(\'helvetica\', \'B\', 20);
$pdf->SetTextColor(0, 0, 0); // Black color
$pdf->Cell(0, 10, \'BATO CLINIC\', 0, 1, \'C\');
$pdf->SetFont(\'helvetica\', \'\', 10);
$pdf->SetTextColor(0, 0, 0);';

// Make the replacement
$newContent = preg_replace($pattern, $replacement, $content);

// If no replacement was made, try to find the header section and add our text logo there
if ($newContent === $content) {
    // Look for the beginning of the PDF generation
    $pattern = '/\$pdf = new TCPDF\(.*?\);.*?\$pdf->AddPage\(\);/s';
    if (preg_match($pattern, $content, $matches)) {
        $replacement = $matches[0] . "\n" . 
            '// Text-based logo
$pdf->SetFont(\'helvetica\', \'B\', 20);
$pdf->SetTextColor(0, 0, 0); // Black color
$pdf->Cell(0, 10, \'BATO CLINIC\', 0, 1, \'C\');
$pdf->SetFont(\'helvetica\', \'\', 10);
$pdf->SetTextColor(0, 0, 0);';
        
        $newContent = preg_replace($pattern, $replacement, $content);
    }
}

// Save the modified content
if (file_put_contents($pdfFile, $newContent)) {
    echo '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; text-align: center;">';
    echo '<h1 style="color: #0066cc;">PDF Logo Fixed!</h1>';
    echo '<p>The PDF generation code has been updated to use a text-based logo instead of an image.</p>';
    echo '<p>Now you can generate PDF reports without any logo-related errors.</p>';
    echo '<p><a href="reports.php" style="display: inline-block; padding: 10px 20px; background-color: #0066cc; color: white; text-decoration: none; border-radius: 4px;">View Reports</a></p>';
    echo '</div>';
} else {
    echo '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; text-align: center;">';
    echo '<h1 style="color: #cc0000;">Error</h1>';
    echo '<p>Could not update the PDF generation file. Please check file permissions.</p>';
    echo '</div>';
}
?>

<?php
// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Installing Arabic Fonts for TCPDF</h1>";

// Check if TCPDF is installed
if (!is_dir('lib/tcpdf')) {
    die("<p style='color: red;'>Error: TCPDF library not found. Please install TCPDF first.</p>");
}

// Create fonts directory if it doesn't exist
$fontsDir = 'lib/tcpdf/fonts';
if (!is_dir($fontsDir)) {
    if (!mkdir($fontsDir, 0777, true)) {
        die("<p style='color: red;'>Error: Could not create fonts directory.</p>");
    }
    echo "<p>Created fonts directory.</p>";
}

// Define the Arabic fonts to download
$arabicFonts = [
    'aealarabiya' => 'https://raw.githubusercontent.com/tecnickcom/TCPDF/master/fonts/aealarabiya.php',
    'aefurat' => 'https://raw.githubusercontent.com/tecnickcom/TCPDF/master/fonts/aefurat.php',
    'dejavusans' => 'https://raw.githubusercontent.com/tecnickcom/TCPDF/master/fonts/dejavusans.php',
    'dejavusansb' => 'https://raw.githubusercontent.com/tecnickcom/TCPDF/master/fonts/dejavusansb.php',
    'dejavusansi' => 'https://raw.githubusercontent.com/tecnickcom/TCPDF/master/fonts/dejavusansi.php',
    'dejavusansbi' => 'https://raw.githubusercontent.com/tecnickcom/TCPDF/master/fonts/dejavusansbi.php',
];

// Download and save each font file
$success = true;
foreach ($arabicFonts as $fontName => $fontUrl) {
    echo "<p>Downloading $fontName font...</p>";
    
    // Download the font PHP file
    $fontContent = @file_get_contents($fontUrl);
    if ($fontContent === false) {
        echo "<p style='color: red;'>Error: Could not download $fontName font.</p>";
        $success = false;
        continue;
    }
    
    // Save the font PHP file
    if (!file_put_contents("$fontsDir/$fontName.php", $fontContent)) {
        echo "<p style='color: red;'>Error: Could not save $fontName font.</p>";
        $success = false;
        continue;
    }
    
    // Download the font Z file
    $fontZUrl = str_replace('.php', '.z', $fontUrl);
    $fontZContent = @file_get_contents($fontZUrl);
    if ($fontZContent === false) {
        echo "<p style='color: red;'>Error: Could not download $fontName.z font data.</p>";
        $success = false;
        continue;
    }
    
    // Save the font Z file
    if (!file_put_contents("$fontsDir/$fontName.z", $fontZContent)) {
        echo "<p style='color: red;'>Error: Could not save $fontName.z font data.</p>";
        $success = false;
        continue;
    }
    
    // Download the font CTG file
    $fontCtgUrl = str_replace('.php', '.ctg.z', $fontUrl);
    $fontCtgContent = @file_get_contents($fontCtgUrl);
    if ($fontCtgContent === false) {
        echo "<p style='color: red;'>Error: Could not download $fontName.ctg.z font data.</p>";
        $success = false;
        continue;
    }
    
    // Save the font CTG file
    if (!file_put_contents("$fontsDir/$fontName.ctg.z", $fontCtgContent)) {
        echo "<p style='color: red;'>Error: Could not save $fontName.ctg.z font data.</p>";
        $success = false;
        continue;
    }
    
    echo "<p style='color: green;'>Successfully installed $fontName font.</p>";
}

// Now update the PDF generation code to use Arabic fonts
if ($success) {
    $pdfFile = 'download_pdf.php';
    if (!file_exists($pdfFile)) {
        echo "<p style='color: red;'>Error: Could not find the PDF generation file.</p>";
    } else {
        // Read the current content
        $content = file_get_contents($pdfFile);
        
        // Add font setting code after creating PDF document
        $pattern = '/\$pdf->setFontSubsetting\(true\);/';
        $replacement = '$pdf->setFontSubsetting(true);

// Add Arabic font
$pdf->AddFont(\'dejavusans\', \'\', \'dejavusans.php\');
$pdf->AddFont(\'dejavusans\', \'B\', \'dejavusansb.php\');
$pdf->AddFont(\'dejavusans\', \'I\', \'dejavusansi.php\');
$pdf->AddFont(\'dejavusans\', \'BI\', \'dejavusansbi.php\');

// Set font to DejaVu Sans which supports Arabic
$pdf->SetFont(\'dejavusans\', \'\', 10);';
        
        // Make the replacement
        $newContent = preg_replace($pattern, $replacement, $content);
        
        // Replace all helvetica font references with dejavusans
        $newContent = str_replace('\'helvetica\'', '\'dejavusans\'', $newContent);
        
        // Save the modified content
        if (file_put_contents($pdfFile, $newContent)) {
            echo "<p style='color: green;'>Successfully updated PDF generation code to support Arabic text.</p>";
        } else {
            echo "<p style='color: red;'>Error: Could not update the PDF generation file.</p>";
            $success = false;
        }
    }
}

if ($success) {
    echo "<div style='margin-top: 20px; padding: 15px; background-color: #dff0d8; border: 1px solid #d6e9c6; border-radius: 4px;'>";
    echo "<h2 style='color: #3c763d;'>Installation Successful!</h2>";
    echo "<p>Arabic fonts have been successfully installed and configured for TCPDF.</p>";
    echo "<p>You can now generate PDF reports with proper Arabic text support.</p>";
    echo "<p><a href='reports.php' style='display: inline-block; padding: 10px 20px; background-color: #5cb85c; color: white; text-decoration: none; border-radius: 4px;'>View Reports</a></p>";
    echo "</div>";
} else {
    echo "<div style='margin-top: 20px; padding: 15px; background-color: #f2dede; border: 1px solid #ebccd1; border-radius: 4px;'>";
    echo "<h2 style='color: #a94442;'>Installation Incomplete</h2>";
    echo "<p>There were errors during the installation process. Please check the messages above.</p>";
    echo "</div>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    line-height: 1.6;
    margin: 20px;
    max-width: 800px;
    margin: 0 auto;
}
h1 {
    color: #333;
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
}
p {
    margin-bottom: 10px;
}
</style>

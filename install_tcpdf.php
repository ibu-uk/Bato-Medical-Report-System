<?php
echo "<h1>TCPDF Library Installer</h1>";

// Create lib directory if it doesn't exist
if (!is_dir('lib')) {
    mkdir('lib');
    echo "<p>Created 'lib' directory.</p>";
}

// Create tcpdf directory if it doesn't exist
if (!is_dir('lib/tcpdf')) {
    mkdir('lib/tcpdf');
    echo "<p>Created 'lib/tcpdf' directory.</p>";
}

// URL of the TCPDF library
$tcpdfUrl = 'https://github.com/tecnickcom/TCPDF/archive/refs/tags/6.6.2.zip';
$zipFile = 'lib/tcpdf.zip';

echo "<p>Downloading TCPDF library from GitHub...</p>";

// Download the zip file
if (file_put_contents($zipFile, file_get_contents($tcpdfUrl))) {
    echo "<p>Download completed successfully.</p>";
    
    // Extract the zip file
    echo "<p>Extracting files...</p>";
    
    $zip = new ZipArchive;
    if ($zip->open($zipFile) === TRUE) {
        $zip->extractTo('lib/');
        $zip->close();
        echo "<p>Extraction completed.</p>";
        
        // Move files from the extracted directory to tcpdf directory
        echo "<p>Moving files to the correct location...</p>";
        
        // Recursive copy function
        function recursiveCopy($src, $dst) {
            $dir = opendir($src);
            @mkdir($dst);
            while (($file = readdir($dir)) !== false) {
                if (($file != '.') && ($file != '..')) {
                    if (is_dir($src . '/' . $file)) {
                        recursiveCopy($src . '/' . $file, $dst . '/' . $file);
                    } else {
                        copy($src . '/' . $file, $dst . '/' . $file);
                    }
                }
            }
            closedir($dir);
        }
        
        recursiveCopy('lib/TCPDF-6.6.2', 'lib/tcpdf');
        
        // Clean up
        echo "<p>Cleaning up temporary files...</p>";
        unlink($zipFile);
        
        // Remove the extracted directory
        function recursiveRemove($dir) {
            $files = array_diff(scandir($dir), array('.', '..'));
            foreach ($files as $file) {
                if (is_dir("$dir/$file")) {
                    recursiveRemove("$dir/$file");
                } else {
                    unlink("$dir/$file");
                }
            }
            return rmdir($dir);
        }
        
        recursiveRemove('lib/TCPDF-6.6.2');
        
        echo "<p style='color: green; font-weight: bold;'>TCPDF library has been successfully installed!</p>";
        echo "<p>You can now generate PDF reports.</p>";
        echo "<p><a href='index.php' class='btn btn-primary'>Return to Medical Report System</a></p>";
    } else {
        echo "<p style='color: red;'>Failed to extract the zip file.</p>";
    }
} else {
    echo "<p style='color: red;'>Failed to download the TCPDF library.</p>";
    echo "<p>Please check your internet connection and try again.</p>";
}
?>
<style>
    body {
        font-family: Arial, sans-serif;
        margin: 20px;
        line-height: 1.6;
    }
    h1 {
        color: #0066cc;
    }
    p {
        margin-bottom: 10px;
    }
    .btn {
        display: inline-block;
        padding: 8px 16px;
        background-color: #0066cc;
        color: white;
        text-decoration: none;
        border-radius: 4px;
        margin-top: 20px;
    }
    .btn:hover {
        background-color: #0052a3;
    }
</style>

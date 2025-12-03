<!DOCTYPE html>
<html>
<head>
    <title>Bato Clinic Logo</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            text-align: center;
        }
        .logo-container {
            width: 400px;
            height: 100px;
            margin: 0 auto;
            border: 1px solid #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: white;
        }
        .logo-text {
            font-size: 36px;
            font-weight: bold;
            color: #000000;
        }
        h1 {
            color: #333;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #0066cc;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <h1>Bato Clinic Logo</h1>
    
    <div class="logo-container">
        <div class="logo-text">BATO CLINIC</div>
    </div>
    
    <p>Since your PHP installation doesn't have the GD library enabled, we're showing you an HTML version of the logo.</p>
    <p>To fix the PDF logo issue, you have two options:</p>
    
    <h3>Option 1: Modify the PDF generation code</h3>
    <p>We can update the PDF generation code to use text instead of an image for the logo.</p>
    
    <h3>Option 2: Create a simple logo manually</h3>
    <p>You can create a simple PNG logo using any image editor and save it to: <br>
    <code>c:\xampp\htdocs\Bato Medical Report System\assets\images\logo.png</code></p>
    
    <a href="modify_pdf_logo.php" class="button">Fix PDF Logo Issue</a>
</body>
</html>
?>

# Bato Medical Report System

A dynamic form-based medical report system that generates print-ready PDF reports with Arabic language support.

## Features
- Patient selection from database with auto-fill functionality
- Test type selection and result input
- Doctor selection with e-signature
- PDF report generation with letterhead and clinic logo
- Arabic text support in PDF reports
- Patient-specific PDF file naming
- Manual patient registration
- Preview and download functionality

## Requirements
- PHP 7.4+
- MySQL 5.7+
- TCPDF library (included)
- Modern web browser
- XAMPP or similar PHP development environment

## Installation on a New PC

### Step 1: Install XAMPP
1. Download and install XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. Start Apache and MySQL services from the XAMPP Control Panel

### Step 2: Copy Files
1. Copy the entire "Bato Medical Report System" folder to the `htdocs` directory of your new XAMPP installation
   - Typically located at `C:\xampp\htdocs\` on Windows

### Step 3: Set Up Database
1. Open phpMyAdmin by navigating to [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
2. Create a new database named `bato_system` (or your preferred name)
3. Import the database schema from `database/schema.sql`
4. Update the database connection settings in `config/database.php` if needed

### Step 4: Install Required Components
1. Run the TCPDF installation script by navigating to [http://localhost/Bato%20Medical%20Report%20System/install_tcpdf.php](http://localhost/Bato%20Medical%20Report%20System/install_tcpdf.php)
2. Run the Arabic fonts installation script by navigating to [http://localhost/Bato%20Medical%20Report%20System/install_arabic_fonts.php](http://localhost/Bato%20Medical%20Report%20System/install_arabic_fonts.php)

### Step 5: Verify Clinic Information
1. Run the clinic information update script by navigating to [http://localhost/Bato%20Medical%20Report%20System/update_clinic_info.php](http://localhost/Bato%20Medical%20Report%20System/update_clinic_info.php)
2. Verify that the clinic information is correct

### Step 6: Access the System
1. Navigate to [http://localhost/Bato%20Medical%20Report%20System/](http://localhost/Bato%20Medical%20Report%20System/)
2. The system should now be fully functional

## Troubleshooting

### PDF Generation Issues
- If PDFs don't display Arabic text correctly, make sure you've run the `install_arabic_fonts.php` script
- Verify that the DejaVu Sans fonts are installed in the `lib/tcpdf/fonts` directory

### Database Connection Issues
- Check that the database credentials in `config/database.php` match your MySQL setup
- Ensure MySQL service is running in XAMPP Control Panel

### Missing Components
- If you see errors about missing files or directories, run the installation scripts mentioned in Step 4

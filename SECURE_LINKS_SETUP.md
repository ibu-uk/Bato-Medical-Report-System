# Secure Patient Report Links - Setup Guide

## Overview
This secure link system allows patients to access their medical reports without exposing patient IDs or requiring manual downloads by staff.

## Security Features
- ✅ **64-character cryptographically secure tokens** using `random_bytes(32)`
- ✅ **No patient ID exposure** in URLs or tokens
- ✅ **Automatic expiry** (48 hours default)
- ✅ **One-time access** (optional)
- ✅ **Access logging** for security auditing
- ✅ **Token revocation** capability

## Installation Steps

### 1. Database Setup
Run these SQL commands to create the required tables:

```sql
-- Run sql/create_report_links_table.sql
-- Run sql/create_report_access_log.sql
```

### 2. File Structure
The following files are created/modified:

```
├── config/
│   └── secure_links.php          # Core secure link functions
├── report.php                    # Secure access endpoint
├── generate_secure_link.php      # Link generation API
├── manage_secure_links.php       # Staff management interface
├── cleanup_links.php            # Automatic cleanup script
├── sql/
│   ├── create_report_links_table.sql
│   └── create_report_access_log.sql
├── view_report.php               # Modified with Generate Link button
└── SECURE_LINKS_SETUP.md        # This documentation
```

### 3. Integration Points

#### In view_report.php:
- Added "Generate Secure Link" button
- Integrated JavaScript for link generation
- Shows modal with secure URL and token

#### In your existing system:
- Call `createSecureReportLink()` when generating links
- Use `getSecureReportUrl()` to get complete URLs
- Use `validateReportToken()` in access endpoints

## Usage Examples

### Generate a Secure Link
```php
require_once 'config/secure_links.php';

$patientId = 123;
$reportFile = 'reports/patient_report_456.pdf';
$token = createSecureReportLink($patientId, $reportFile, 48); // 48 hours

if ($token) {
    $url = getSecureReportUrl($token);
    echo "Secure URL: " . $url;
}
```

### Validate Access
```php
$token = $_GET['token'];
$tokenData = validateReportToken($token);

if ($tokenData) {
    // Valid token - show report
    $reportFile = $tokenData['report_file'];
    // Display the PDF file
} else {
    // Invalid or expired token
    die("Access denied");
}
```

## URL Format
```
https://yourclinic.com/report.php?token=64_CHARACTER_SECURE_TOKEN
```

## Database Schema

### report_links table
| Field | Description |
|-------|-------------|
| id | Primary key |
| patient_id | Internal patient reference |
| report_file | PDF file path |
| token | 64-character secure token |
| expiry_date | Link expiration time |
| is_used | One-time access flag |
| created_at | Creation timestamp |

### report_access_log table
| Field | Description |
|-------|-------------|
| id | Primary key |
| token_id | Reference to report_links |
| patient_id | Patient ID |
| ip_address | Access IP |
| user_agent | Browser info |
| accessed_at | Access timestamp |

## Security Considerations

### Token Security
- Uses `random_bytes(32)` for cryptographically secure tokens
- Tokens are 64 hexadecimal characters
- No sequential patterns or patient IDs
- Tokens are stored hashed in database

### Access Control
- Tokens expire automatically after 48 hours
- Optional one-time access via `is_used` flag
- All access attempts are logged
- Staff can revoke specific links

### File Security
- Report files stored outside web root when possible
- Direct file access blocked by token validation
- Access logging tracks all report views

## Maintenance

### Automatic Cleanup
Run cleanup script daily via cron:
```bash
# Add to crontab for daily execution at 2 AM
0 2 * * * /usr/bin/php /path/to/cleanup_links.php
```

### Manual Cleanup
Staff can clean up expired links via the management interface or by running:
```bash
php cleanup_links.php
```

## API Endpoints

### Generate Secure Link
**POST** `/generate_secure_link.php`
```json
{
    "report_id": 123,
    "patient_id": 456
}
```

Response:
```json
{
    "success": true,
    "token": "64_character_token",
    "url": "https://clinic.com/report.php?token=...",
    "expiry": "2024-01-15 14:30:00"
}
```

### Access Report
**GET** `/report.php?token=TOKEN`
- Validates token
- Serves PDF file
- Logs access attempt

## Staff Interface

### Manage Secure Links
Access via `/manage_secure_links.php` (admin only):

- View all active secure links
- See patient information
- Copy tokens to clipboard
- Test links in new window
- Revoke specific links
- Clean up expired links
- View access statistics

## Customization

### Change Token Validity
Modify the hours parameter when creating links:
```php
$token = createSecureReportLink($patientId, $reportFile, 72); // 72 hours
```

### One-Time Access
Enable by uncommenting in `report.php`:
```php
markTokenAsUsed($token);
```

### Custom Expiry Messages
Modify the JavaScript in `view_report.php` to change expiry notifications.

## Troubleshooting

### Common Issues

1. **Token not working**
   - Check if token exists in database
   - Verify token hasn't expired
   - Ensure report file exists

2. **PDF not displaying**
   - Verify file path in `report_links` table
   - Check file permissions
   - Ensure PDF is not corrupted

3. **Link generation failing**
   - Check database connection
   - Verify patient and report IDs exist
   - Ensure write permissions for report directory

### Debug Mode
Add to `config/secure_links.php` for debugging:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Security Best Practices

1. **Regular Cleanup**: Schedule automatic cleanup of expired links
2. **Access Monitoring**: Review access logs regularly
3. **File Storage**: Store PDFs outside web root when possible
4. **HTTPS**: Always use HTTPS for secure links
5. **Token Rotation**: Consider shorter validity for sensitive reports

## Support

For issues or questions:
1. Check the error logs
2. Verify database connections
3. Test with a known good token
4. Review file permissions

---

**Version**: 1.0  
**Last Updated**: 2024  
**Compatible**: PHP 7.4+, MySQL 5.7+

<?php
// Common helper functions

if (!function_exists('sanitize_output')) {
    /**
     * Sanitize output for HTML display
     * Use this for displaying data in HTML
     */
    function sanitize_output($data) {
        $data = trim($data);
        $data = stripslashes($data);
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}

// Add other common helper functions here if needed
?>

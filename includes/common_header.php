<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Custom CSS for confirmations -->
<style>
    /* Add any custom styles for confirm dialogs here */
    .swal2-popup {
        font-size: 1.1rem;
    }
    .swal2-title {
        color: #2c3e50;
    }
    .swal2-actions {
        margin: 1.5em auto 0;
    }
</style>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Common JS -->
<script src="/bato medical report system/assets/js/common.js"></script>

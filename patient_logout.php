<?php
session_start();

// Clear only patient-auth session values so staff sessions are not affected.
unset($_SESSION['patient_id'], $_SESSION['patient_name'], $_SESSION['auth_type']);

header('Location: patient_login.php?portal=1');
exit;
?>

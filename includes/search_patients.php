<?php
// Include database configuration
require_once '../config/data.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if search term is provided
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = sanitize($_GET['search']);
    
    // Limit results to 50 for performance
    $query = "SELECT id, name, civil_id, file_number, mobile 
              FROM patients 
              WHERE name LIKE '%$search_term%' 
              OR civil_id LIKE '%$search_term%' 
              OR mobile LIKE '%$search_term%' 
              ORDER BY name 
              LIMIT 50";
    
    $result = executeQuery($query);
    
    $patients = array();
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $patients[] = array(
                'id' => $row['id'],
                'name' => $row['name'],
                'civil_id' => $row['civil_id'],
                'mobile' => $row['mobile'],
                'file_number' => $row['file_number']
            );
        }
        
        echo json_encode(array(
            'success' => true,
            'patients' => $patients,
            'count' => count($patients),
            'limit' => 50
        ));
    } else {
        echo json_encode(array(
            'success' => false,
            'message' => 'No patients found matching "' . $search_term . '"'
        ));
    }
} else {
    // If no search term, return recent patients (limited to 20)
    $query = "SELECT id, name, civil_id, file_number, mobile 
              FROM patients 
              ORDER BY id DESC 
              LIMIT 20";
    
    $result = executeQuery($query);
    
    $patients = array();
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $patients[] = array(
                'id' => $row['id'],
                'name' => $row['name'],
                'civil_id' => $row['civil_id'],
                'mobile' => $row['mobile'],
                'file_number' => $row['file_number']
            );
        }
        
        echo json_encode(array(
            'success' => true,
            'patients' => $patients,
            'count' => count($patients),
            'limit' => 20,
            'message' => 'Showing 20 most recent patients'
        ));
    } else {
        echo json_encode(array(
            'success' => false,
            'message' => 'No patients found'
        ));
    }
}
?>

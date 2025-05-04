<?php
require_once 'dbConnection.php';
require_once 'eligibilityCheck.php';

header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input data']);
    exit();
}

// Initialize eligibility service
$eligibilityService = new EligibilityService($conn);

// Check eligibility
$result = $eligibilityService->checkEligibility(
    $data['date_of_birth'],
    $data['citizenship'],
    $data['residency_status']
);

echo json_encode($result);
?> 
<?php
require_once 'dbConnection.php';

class EligibilityService {
    private $conn;
    private $minVotingAge = 18;
    private $allowedCitizenships = ['US', 'CA', 'UK', 'AU', 'NZ'];
    private $allowedResidencyStatuses = ['citizen', 'permanent_resident'];

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function checkEligibility($dateOfBirth, $citizenship, $residencyStatus) {
        $errors = [];

        // Check age
        if (!$this->isOfVotingAge($dateOfBirth)) {
            $errors[] = "You must be at least {$this->minVotingAge} years old to register.";
        }

        // Check citizenship
        if (!$this->isValidCitizenship($citizenship)) {
            $errors[] = "Your citizenship is not eligible for voting.";
        }

        // Check residency status
        if (!$this->isValidResidencyStatus($residencyStatus)) {
            $errors[] = "Your residency status is not eligible for voting.";
        }

        return [
            'isEligible' => empty($errors),
            'errors' => $errors
        ];
    }

    private function isOfVotingAge($dateOfBirth) {
        $birthDate = new DateTime($dateOfBirth);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
        return $age >= $this->minVotingAge;
    }

    private function isValidCitizenship($citizenship) {
        return in_array(strtoupper($citizenship), array_map('strtoupper', $this->allowedCitizenships));
    }

    private function isValidResidencyStatus($residencyStatus) {
        return in_array(strtolower($residencyStatus), array_map('strtolower', $this->allowedResidencyStatuses));
    }

    public function mockGovernmentAPI($dateOfBirth, $citizenship, $residencyStatus) {
        // Simulate API delay
        sleep(1);

        // Mock API response
        $response = $this->checkEligibility($dateOfBirth, $citizenship, $residencyStatus);

        // Add some random success rate to simulate real-world conditions
        if ($response['isEligible']) {
            $successRate = 95; // 95% success rate for eligible voters
            $response['isEligible'] = (rand(1, 100) <= $successRate);
            if (!$response['isEligible']) {
                $response['errors'][] = "Government verification failed. Please try again later.";
            }
        }

        return $response;
    }
}
?> 
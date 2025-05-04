<?php

require_once 'emailService.php';

// Example usage
$userEmail = 'nigeltan76@gmail.com';
$electionTitle = 'Student Council Election 2025';
$startDate = '2025-05-01 08:00:00';
$endDate = '2025-05-05 20:00:00';

notifyUserAboutElection($userEmail, $electionTitle, $startDate, $endDate);
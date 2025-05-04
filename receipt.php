<?php
session_start();
require 'dbConnection.php';

if (!isset($_GET['id']) || !isset($_SESSION['user_id'])) {
    header("Location: homePage.php");
    exit();
}

$receipt_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Get receipt data
$stmt = $conn->prepare("
    SELECT r.*, e.title AS election_title, u.full_name 
    FROM vote_receipts r
    JOIN elections e ON r.election_id = e.election_id
    JOIN users u ON r.user_id = u.user_id
    WHERE r.receipt_id = ? AND r.user_id = ?
");
$stmt->execute([$receipt_id, $user_id]);
$receipt = $stmt->fetch();

if (!$receipt) {
    die("Invalid receipt or access denied");
}

$receipt_data = json_decode($receipt['receipt_data'], true);

// Create XML document
$xml = new DOMDocument('1.0', 'UTF-8');
$root = $xml->createElement('receipt');
$xml->appendChild($root);

// Add receipt data to XML
$root->appendChild($xml->createElement('voter', htmlspecialchars($receipt['full_name'])));
$root->appendChild($xml->createElement('election', htmlspecialchars($receipt['election_title'])));
$root->appendChild($xml->createElement('date', date('F j, Y g:i a', strtotime($receipt_data['timestamp']))));
$root->appendChild($xml->createElement('receipt_id', $receipt_id));

$choices = $xml->createElement('choices');
$root->appendChild($choices);

foreach ($receipt_data['choices'] as $position => $choice) {
    $item = $xml->createElement('item');
    $item->appendChild($xml->createElement('position', htmlspecialchars($position)));
    $item->appendChild($xml->createElement('choice', htmlspecialchars($choice)));
    $choices->appendChild($item);
}

// Load XSL stylesheet
$xsl = new DOMDocument();
$xsl->load('receipt.xsl');

// Configure the transformer
$proc = new XSLTProcessor();
$proc->importStyleSheet($xsl);

// Transform the XML
echo $proc->transformToXML($xml);
exit();
?>
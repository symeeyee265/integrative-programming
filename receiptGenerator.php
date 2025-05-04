<?php

abstract class ReceiptGenerator {

    /**
     * The template method that defines the skeleton of the receipt generation algorithm
     */
    final public function generateReceipt(int $user_id, int $election_id, array $vote_data): string {
        // Step 1: Generate a unique receipt ID
        $receipt_id = $this->generateReceiptId();

        // Step 2: Prepare receipt data (delegated to subclasses)
        $receipt_data = $this->prepareReceiptData($user_id, $election_id, $vote_data);

        // Step 3: Store the receipt in database
        $this->storeReceipt($receipt_id, $user_id, $election_id, $receipt_data);


        return $receipt_id;
    }

    /**
     * Concrete method - same for all receipt types
     */
    protected function generateReceiptId(): string {
        return bin2hex(random_bytes(16)); // 32-character hex string
    }

    /**
     * Abstract method - must be implemented by subclasses
     */
    abstract protected function prepareReceiptData(int $user_id, int $election_id, array $vote_data): array;

    /**
     * Hook method - can be overridden by subclasses if needed
     */
    protected function postGeneration(string $receipt_id): void {
        // Default does nothing
    }

    /**
     * Concrete method - same for all receipt types
     */
    protected function storeReceipt(string $receipt_id, int $user_id, int $election_id, array $receipt_data): void {
        global $conn; // Assuming $conn is the database connection

        $stmt = $conn->prepare("
            INSERT INTO vote_receipts (receipt_id, user_id, election_id, receipt_data)
            VALUES (?, ?, ?, ?)
        ");

        $json_data = json_encode($receipt_data);
        $stmt->execute([$receipt_id, $user_id, $election_id, $json_data]);
    }
}

class CandidateReceiptGenerator extends ReceiptGenerator {

    protected function prepareReceiptData(int $user_id, int $election_id, array $vote_data): array {
        global $conn;

        $positions = [];

        foreach ($vote_data as $position_id => $candidate_id) {
            $stmt = $conn->prepare("
            SELECT p.title AS position_title, c.name AS candidate_name
            FROM positions p
            JOIN candidates c ON c.candidate_id = ?
            WHERE p.position_id = ?
        ");
            $stmt->execute([$candidate_id, $position_id]);
            $result = $stmt->fetch();

            if ($result) {
                $positions[$result['position_title']] = $result['candidate_name'];
            }
        }

        return [
            'election_id' => $election_id,
            'type' => 'candidate',
            'choices' => $positions,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}

class OptionReceiptGenerator extends ReceiptGenerator {

    protected function prepareReceiptData(int $user_id, int $election_id, array $vote_data): array {
        global $conn;

        $option_id = $vote_data['option'];

        $stmt = $conn->prepare("SELECT name FROM options WHERE option_id = ?");
        $stmt->execute([$option_id]);
        $option = $stmt->fetch();

        return [
            'election_id' => $election_id,
            'type' => 'option',
            'choices' => ['Selected Option' => $option['name']],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}

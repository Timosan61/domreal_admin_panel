<?php
/**
 * Organization Balance API
 * Handles organization balance queries and top-up operations
 */

session_start();
require_once '../auth/session.php';
checkAuth();

require_once '../db.php';

header('Content-Type: application/json');

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get org_id from session
$org_id = $_SESSION['org_id'] ?? 'org-legacy';

try {
    $conn = get_db_connection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    if ($method === 'GET') {
        // Get current balance
        $action = $_GET['action'] ?? 'get';

        if ($action === 'get') {
            $stmt = $conn->prepare("
                SELECT
                    o.org_id,
                    o.company_name,
                    o.status,
                    COALESCE(b.balance_kopeks, 0) as balance_kopeks,
                    COALESCE(b.total_deposited_kopeks, 0) as total_deposited_kopeks,
                    COALESCE(b.total_spent_kopeks, 0) as total_spent_kopeks,
                    b.last_topup_at,
                    b.last_topup_amount_kopeks
                FROM organizations o
                LEFT JOIN organization_balances b ON o.org_id = b.org_id
                WHERE o.org_id = ?
                LIMIT 1
            ");

            $stmt->bind_param('s', $org_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();

            if (!$data) {
                throw new Exception('Organization not found');
            }

            // Convert kopeks to rubles
            $data['balance_rubles'] = $data['balance_kopeks'] / 100;
            $data['total_deposited_rubles'] = $data['total_deposited_kopeks'] / 100;
            $data['total_spent_rubles'] = $data['total_spent_kopeks'] / 100;

            // Calculate tokens (1000 tokens per ruble)
            $tokens_per_ruble = 1000;
            $data['balance_tokens'] = $data['balance_rubles'] * $tokens_per_ruble;

            $stmt->close();

            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
        } else {
            throw new Exception('Invalid action');
        }

    } elseif ($method === 'POST') {
        // Top-up balance
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        if ($action === 'topup') {
            $amount_rubles = floatval($input['amount_rubles'] ?? 0);
            $payment_method = $input['payment_method'] ?? '';

            // Validation
            if ($amount_rubles < 100) {
                throw new Exception('Минимальная сумма пополнения: 100 ₽');
            }

            if (!in_array($payment_method, ['acquiring', 'bank_transfer', 'manual_topup'])) {
                throw new Exception('Недопустимый способ оплаты');
            }

            // Convert rubles to kopeks
            $amount_kopeks = intval($amount_rubles * 100);

            // Get current balance
            $stmt = $conn->prepare("
                SELECT balance_kopeks, total_deposited_kopeks
                FROM organization_balances
                WHERE org_id = ?
                FOR UPDATE
            ");
            $stmt->bind_param('s', $org_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $current = $result->fetch_assoc();
            $stmt->close();

            $current_balance = $current ? intval($current['balance_kopeks']) : 0;
            $total_deposited = $current ? intval($current['total_deposited_kopeks']) : 0;

            $new_balance = $current_balance + $amount_kopeks;
            $new_total_deposited = $total_deposited + $amount_kopeks;

            // Update balance
            if ($current) {
                // Update existing balance
                $stmt = $conn->prepare("
                    UPDATE organization_balances
                    SET
                        balance_kopeks = ?,
                        total_deposited_kopeks = ?,
                        last_topup_at = NOW(),
                        last_topup_amount_kopeks = ?,
                        last_topup_method = ?,
                        updated_at = NOW()
                    WHERE org_id = ?
                ");
                $stmt->bind_param('iiiss', $new_balance, $new_total_deposited, $amount_kopeks, $payment_method, $org_id);
            } else {
                // Insert new balance record
                $stmt = $conn->prepare("
                    INSERT INTO organization_balances
                    (org_id, balance_kopeks, total_deposited_kopeks, last_topup_at, last_topup_amount_kopeks, last_topup_method)
                    VALUES (?, ?, ?, NOW(), ?, ?)
                ");
                $stmt->bind_param('siiis', $org_id, $new_balance, $new_total_deposited, $amount_kopeks, $payment_method);
            }

            if (!$stmt->execute()) {
                throw new Exception('Failed to update balance: ' . $stmt->error);
            }

            $stmt->close();

            // Log transaction (optional - would need a transactions table)

            echo json_encode([
                'success' => true,
                'message' => 'Баланс успешно пополнен',
                'data' => [
                    'amount_rubles' => $amount_rubles,
                    'new_balance_rubles' => $new_balance / 100,
                    'new_balance_tokens' => ($new_balance / 100) * 1000
                ]
            ]);

        } else {
            throw new Exception('Invalid action');
        }

    } else {
        throw new Exception('Method not allowed');
    }

    $conn->close();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

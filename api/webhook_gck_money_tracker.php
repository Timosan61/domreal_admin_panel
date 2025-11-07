<?php
/**
 * GCK Webhook Receiver for Money Tracker
 *
 * Receives phone numbers from GCK (GetCourse/Google Sheets) and adds to enrichment queue
 *
 * Endpoint: POST /api/webhook_gck_money_tracker.php
 * Authentication: None (optional signature validation can be added)
 *
 * Expected Payload (GCK format):
 * {
 *   "phones": ["+79001234567", "+79001234568"],
 *   "mails": ["test@example.com"],
 *   "utm": {...},
 *   "vid": "12345",
 *   "batch_name": "Optional batch name"
 * }
 *
 * Response:
 * {
 *   "success": true,
 *   "added": 2,
 *   "duplicates": 0,
 *   "batch_id": 123,
 *   "batch_name": "GCK Webhook 2025-10-29 12:34:56",
 *   "message": "2 phones added to enrichment queue"
 * }
 */

// CORS and content type headers
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/webhook_gck_money_tracker.log');

// Include database configuration
include_once '../config/database.php';

/**
 * Forward webhook to external URL (asynchronous, non-blocking)
 */
function forward_webhook_async($db, $raw_payload) {
    $log_file = __DIR__ . '/../../logs/webhook_forward.log';

    try {
        // Get forward settings
        $query = "SELECT setting_key, setting_value
                  FROM money_tracker_settings
                  WHERE setting_key IN ('webhook_forward_enabled', 'webhook_forward_url', 'webhook_forward_timeout')";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $enabled = false;
        $forward_url = '';
        $timeout = 10;

        foreach ($rows as $row) {
            if ($row['setting_key'] === 'webhook_forward_enabled') {
                $enabled = ($row['setting_value'] === 'true');
            } elseif ($row['setting_key'] === 'webhook_forward_url') {
                $forward_url = trim($row['setting_value']);
            } elseif ($row['setting_key'] === 'webhook_forward_timeout') {
                $timeout = (int)$row['setting_value'];
            }
        }

        // Check if forwarding is enabled and URL is set
        if (!$enabled || empty($forward_url)) {
            return;
        }

        // Send webhook to external URL (non-blocking)
        $start_time = microtime(true);

        $ch = curl_init($forward_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $raw_payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $response_time_ms = (int)((microtime(true) - $start_time) * 1000);

        curl_close($ch);

        // Log result
        $timestamp = date('Y-m-d H:i:s');
        $log_message = '';

        if ($curl_error) {
            $log_message = "[$timestamp] Forward to $forward_url: FAILED - $curl_error\n";
        } else {
            $status = ($http_code >= 200 && $http_code < 300) ? 'SUCCESS' : 'FAILED';
            $log_message = "[$timestamp] Forward to $forward_url: $status ($http_code) - {$response_time_ms}ms\n";
        }

        // Write to log file
        @file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);

    } catch (Exception $e) {
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] Forward error: " . $e->getMessage() . "\n";
        @file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
    }
}

// Log incoming request
$request_start = microtime(true);
error_log("[GCK Webhook] Received request at " . date('Y-m-d H:i:s'));
error_log("[GCK Webhook] Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("[GCK Webhook] Remote IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "error" => "Method not allowed. Only POST requests are accepted."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Get raw payload
$raw_payload = file_get_contents('php://input');
error_log("[GCK Webhook] Raw payload length: " . strlen($raw_payload) . " bytes");
error_log("[GCK Webhook] Raw payload preview: " . substr($raw_payload, 0, 500));

// Parse JSON
$data = json_decode($raw_payload, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $error = "Invalid JSON: " . json_last_error_msg();
    error_log("[GCK Webhook] ERROR: $error");

    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => $error
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Extract phones from payload
$phones = [];

// Try different possible field names
if (isset($data['phones']) && is_array($data['phones'])) {
    $phones = $data['phones'];
} elseif (isset($data['phone'])) {
    // Single phone as string
    $phones = [$data['phone']];
} elseif (isset($data['client_phone'])) {
    $phones = [$data['client_phone']];
}

if (empty($phones)) {
    $error = "No phones found in payload. Expected 'phones' array, 'phone' or 'client_phone' field.";
    error_log("[GCK Webhook] ERROR: $error");
    error_log("[GCK Webhook] Payload keys: " . implode(', ', array_keys($data)));

    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => $error,
        "payload_keys" => array_keys($data)
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

error_log("[GCK Webhook] Found " . count($phones) . " phone(s) in payload");

// Database connection
$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    $error = "Database connection failed";
    error_log("[GCK Webhook] ERROR: $error");

    http_response_code(503);
    echo json_encode([
        "success" => false,
        "error" => $error
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Batch management
$batch_name = $data['batch_name'] ?? ('GCK Webhook ' . date('Y-m-d H:i:s'));
$batch_id = null;

// Check if batch auto-creation is enabled
$settings_query = "SELECT setting_value FROM money_tracker_settings
                   WHERE setting_key = 'webhook_batch_auto_create' LIMIT 1";
$settings_stmt = $db->prepare($settings_query);
$settings_stmt->execute();
$settings_result = $settings_stmt->fetch();
$auto_create_batch = ($settings_result && $settings_result['setting_value'] === 'true');

// Create batch if enabled
if ($auto_create_batch) {
    try {
        $batch_query = "INSERT INTO enrichment_batches (batch_name, created_at, status, total_records)
                        VALUES (:batch_name, NOW(), 'processing', 0)";
        $batch_stmt = $db->prepare($batch_query);
        $batch_stmt->execute(['batch_name' => $batch_name]);
        $batch_id = $db->lastInsertId();
        error_log("[GCK Webhook] Created batch ID: $batch_id, name: $batch_name");
    } catch (PDOException $e) {
        error_log("[GCK Webhook] Warning: Failed to create batch: " . $e->getMessage());
        // Continue without batch
    }
}

// Process each phone
$added = 0;
$skipped = 0;
$errors = [];
$enrichment_ids = [];

foreach ($phones as $phone) {
    // Normalize phone
    $phone = trim($phone);

    // Skip empty phones
    if (empty($phone)) {
        continue;
    }

    // Remove all non-digit characters except +
    $normalized = preg_replace('/[^\d+]/', '', $phone);

    // Remove leading + if present
    $digits_only = ltrim($normalized, '+');

    // Validate: must have 10-11 digits
    if (!preg_match('/^\d{10,11}$/', $digits_only)) {
        $errors[] = "Invalid phone format: $phone (must be 10-11 digits, got " . strlen($digits_only) . ")";
        error_log("[GCK Webhook] Invalid phone format: $phone (digits: $digits_only)");
        continue;
    }

    // Normalize to +7XXXXXXXXXX format
    if (strlen($digits_only) == 11) {
        // 11 digits: 8XXXXXXXXXX or 7XXXXXXXXXX
        if ($digits_only[0] == '8' || $digits_only[0] == '7') {
            $normalized = '+7' . substr($digits_only, 1);
        } else {
            // Unknown format, add +7 anyway
            $normalized = '+7' . substr($digits_only, 1);
        }
    } elseif (strlen($digits_only) == 10) {
        // 10 digits: 9XXXXXXXXX or any 10-digit number
        $normalized = '+7' . $digits_only;
    }

    error_log("[GCK Webhook] Processing phone: $phone -> $normalized");

    try {
        // NOTE: We allow duplicates per user requirement
        // No duplicate check - always insert new record

        // Insert new record
        $insert_query = "INSERT INTO client_enrichment (
            batch_id,
            client_phone,
            webhook_source,
            webhook_payload,
            webhook_received_at,
            enrichment_status,
            created_at,
            updated_at
        ) VALUES (
            :batch_id,
            :phone,
            'gck',
            :payload,
            NOW(),
            'pending',
            NOW(),
            NOW()
        )";

        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->execute([
            'batch_id' => $batch_id,
            'phone' => $normalized,
            'payload' => $raw_payload
        ]);

        $enrichment_id = $db->lastInsertId();
        $enrichment_ids[] = $enrichment_id;
        $added++;

        error_log("[GCK Webhook]  Added phone: $normalized (Enrichment ID: $enrichment_id)");

    } catch (PDOException $e) {
        $error_msg = "Error for $phone: " . $e->getMessage();
        $errors[] = $error_msg;
        error_log("[GCK Webhook] ERROR: $error_msg");
    }
}

// Update batch counters
if ($batch_id && $added > 0) {
    try {
        $update_batch = "UPDATE enrichment_batches SET
            total_records = :total,
            pending_records = :pending
            WHERE id = :batch_id";
        $update_stmt = $db->prepare($update_batch);
        $update_stmt->execute([
            'total' => $added,
            'pending' => $added,
            'batch_id' => $batch_id
        ]);
        error_log("[GCK Webhook] Updated batch counters: total=$added, pending=$added");
    } catch (PDOException $e) {
        error_log("[GCK Webhook] Warning: Failed to update batch counters: " . $e->getMessage());
    }
}

// Log webhook event to webhook_log table
$processing_time_ms = (int)((microtime(true) - $request_start) * 1000);

try {
    $log_query = "INSERT INTO webhook_log (
        source, raw_payload, phone_extracted, phones_count,
        status, error_message, enrichment_ids, batch_id,
        ip_address, user_agent, processing_time_ms, created_at
    ) VALUES (
        'gck', :payload, :phone, :count,
        :status, :error, :enrichment_ids, :batch_id,
        :ip, :user_agent, :processing_time, NOW()
    )";

    $log_stmt = $db->prepare($log_query);
    $log_stmt->execute([
        'payload' => $raw_payload,
        'phone' => count($phones) > 0 ? $phones[0] : null,
        'count' => count($phones),
        'status' => empty($errors) ? 'success' : 'error',
        'error' => empty($errors) ? null : implode('; ', $errors),
        'enrichment_ids' => empty($enrichment_ids) ? null : implode(',', $enrichment_ids),
        'batch_id' => $batch_id,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'processing_time' => $processing_time_ms
    ]);

    error_log("[GCK Webhook] Logged to webhook_log table (ID: " . $db->lastInsertId() . ")");
} catch (PDOException $e) {
    error_log("[GCK Webhook] Warning: Failed to log to webhook_log: " . $e->getMessage());
}

// Forward webhook to external URL (if configured)
forward_webhook_async($db, $raw_payload);

// Build response
$response = [
    "success" => true,
    "added" => $added,
    "skipped" => $skipped,
    "batch_id" => $batch_id,
    "batch_name" => $batch_name,
    "enrichment_ids" => $enrichment_ids,
    "processing_time_ms" => $processing_time_ms,
    "message" => "$added phone(s) added to enrichment queue"
];

if (!empty($errors)) {
    $response["errors"] = $errors;
    $response["message"] .= " (with " . count($errors) . " error(s))";
}

if ($skipped > 0) {
    $response["message"] .= ", $skipped skipped";
}

// Success response
http_response_code(200);
error_log("[GCK Webhook]  Success: $added added, $skipped skipped, " . count($errors) . " errors");
echo json_encode($response, JSON_UNESCAPED_UNICODE);

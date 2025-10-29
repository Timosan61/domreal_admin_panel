<?php
/**
 * Google Sheets Clients Management API
 *
 * Endpoints:
 * - GET /api/google_sheets_clients.php?action=list
 * - POST /api/google_sheets_clients.php?action=add
 * - POST /api/google_sheets_clients.php?action=update
 * - POST /api/google_sheets_clients.php?action=delete
 * - POST /api/google_sheets_clients.php?action=toggle
 */

session_start();
require_once '../auth/session.php';

// Only administrators can manage clients
checkAuth($require_admin = true);

header("Content-Type: application/json; charset=UTF-8");

// Include database configuration
include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    echo json_encode([
        "success" => false,
        "error" => "Database connection failed"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Get action from query parameter
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        handleList($db);
        break;
    case 'add':
        handleAdd($db);
        break;
    case 'update':
        handleUpdate($db);
        break;
    case 'delete':
        handleDelete($db);
        break;
    case 'toggle':
        handleToggle($db);
        break;
    default:
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Invalid action. Use: list, add, update, delete, toggle"
        ], JSON_UNESCAPED_UNICODE);
        break;
}

function handleList($db) {
    try {
        $query = "SELECT id, client_name, sheets_id, is_active, notes, created_at, updated_at
                  FROM google_sheets_clients
                  ORDER BY created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $clients = $stmt->fetchAll();

        echo json_encode([
            "success" => true,
            "clients" => $clients
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Failed to fetch clients: " . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

function handleAdd($db) {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['client_name']) || !isset($data['sheets_id'])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Missing required fields: client_name, sheets_id"
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $client_name = trim($data['client_name']);
    $sheets_id = trim($data['sheets_id']);
    $notes = trim($data['notes'] ?? '');

    if (empty($client_name) || empty($sheets_id)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Client name and Sheets ID cannot be empty"
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    // Validate Sheets ID format (basic check)
    if (strlen($sheets_id) < 20 || strlen($sheets_id) > 512) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Invalid Google Sheets ID format"
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        $query = "INSERT INTO google_sheets_clients (client_name, sheets_id, notes, is_active)
                  VALUES (:client_name, :sheets_id, :notes, TRUE)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            'client_name' => $client_name,
            'sheets_id' => $sheets_id,
            'notes' => $notes
        ]);

        $client_id = $db->lastInsertId();

        echo json_encode([
            "success" => true,
            "client_id" => $client_id,
            "message" => "Client added successfully"
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Failed to add client: " . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

function handleUpdate($db) {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['id']) || !isset($data['client_name']) || !isset($data['sheets_id'])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Missing required fields: id, client_name, sheets_id"
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $id = (int)$data['id'];
    $client_name = trim($data['client_name']);
    $sheets_id = trim($data['sheets_id']);
    $notes = trim($data['notes'] ?? '');

    if (empty($client_name) || empty($sheets_id)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Client name and Sheets ID cannot be empty"
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        $query = "UPDATE google_sheets_clients
                  SET client_name = :client_name,
                      sheets_id = :sheets_id,
                      notes = :notes
                  WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute([
            'id' => $id,
            'client_name' => $client_name,
            'sheets_id' => $sheets_id,
            'notes' => $notes
        ]);

        echo json_encode([
            "success" => true,
            "message" => "Client updated successfully"
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Failed to update client: " . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

function handleDelete($db) {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Missing required field: id"
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $id = (int)$data['id'];

    try {
        $query = "DELETE FROM google_sheets_clients WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute(['id' => $id]);

        echo json_encode([
            "success" => true,
            "message" => "Client deleted successfully"
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Failed to delete client: " . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

function handleToggle($db) {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Missing required field: id"
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $id = (int)$data['id'];

    try {
        // Toggle is_active
        $query = "UPDATE google_sheets_clients
                  SET is_active = NOT is_active
                  WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute(['id' => $id]);

        // Get new status
        $query = "SELECT is_active FROM google_sheets_clients WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();

        echo json_encode([
            "success" => true,
            "is_active" => (bool)$result['is_active'],
            "message" => "Client status toggled successfully"
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Failed to toggle client status: " . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

<?php
header("Content-Type: application/json; charset=UTF-8");

session_start();
require_once '../../auth/session.php';

try {
    checkAuth();

    echo json_encode([
        'success' => true,
        'message' => 'Auth OK',
        'user' => $_SESSION['username'],
        'role' => $_SESSION['role']
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

<?php
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($db === null) {
        echo json_encode(['success' => false, 'error' => 'Connection failed']);
        exit();
    }

    // Простой запрос
    $query = "SELECT COUNT(*) as count FROM calls_raw LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Database connection OK',
        'test_query' => $row['count']
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

<?php
/**
 * API для получения списка отделов для фильтра
 * GET /api/filters/departments.php
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

session_start();
require_once '../../auth/session.php';
checkAuth();

include_once '../../config/database.php';

// Подключение к БД
$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// Получаем список отделов пользователя
$user_departments = getUserDepartments();

try {
    // Если пользователь admin - показываем все отделы
    // Если user - только его отделы
    if ($_SESSION['role'] === 'admin') {
        $query = "
            SELECT DISTINCT department
            FROM calls_raw
            WHERE department IS NOT NULL AND department != ''
            ORDER BY department
        ";
        $stmt = $db->prepare($query);
        $stmt->execute();
    } else {
        if (empty($user_departments)) {
            echo json_encode(['success' => true, 'data' => []]);
            exit();
        }

        $placeholders = [];
        $params = [];
        foreach ($user_departments as $idx => $dept) {
            $key = ":dept_$idx";
            $placeholders[] = $key;
            $params[$key] = $dept;
        }

        $query = "
            SELECT DISTINCT department
            FROM calls_raw
            WHERE department IN (" . implode(',', $placeholders) . ")
            ORDER BY department
        ";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
    }

    $departments = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $departments[] = $row['department'];
    }

    echo json_encode([
        'success' => true,
        'data' => $departments
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database query failed: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

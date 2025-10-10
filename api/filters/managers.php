<?php
/**
 * API для получения списка менеджеров для фильтра
 * GET /api/filters/managers.php
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
    // Если пользователь admin - показываем всех менеджеров
    // Если user - только менеджеров из его отделов
    if ($_SESSION['role'] === 'admin') {
        $query = "
            SELECT DISTINCT ar.employee_full_name
            FROM analysis_results ar
            WHERE ar.employee_full_name IS NOT NULL AND ar.employee_full_name != ''
            ORDER BY ar.employee_full_name
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
            SELECT DISTINCT ar.employee_full_name
            FROM analysis_results ar
            INNER JOIN calls_raw cr ON ar.callid = cr.callid
            WHERE ar.employee_full_name IS NOT NULL
                AND ar.employee_full_name != ''
                AND cr.department IN (" . implode(',', $placeholders) . ")
            ORDER BY ar.employee_full_name
        ";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
    }

    $managers = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $managers[] = $row['employee_full_name'];
    }

    echo json_encode([
        'success' => true,
        'data' => $managers
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database query failed: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

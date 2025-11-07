<?php
/**
 * API для получения метрики Talk-to-Listen по менеджерам
 * GET /api/analytics/talk_listen.php
 *
 * Параметры:
 * - date_from: дата начала (YYYY-MM-DD)
 * - date_to: дата окончания (YYYY-MM-DD)
 * - departments: список отделов через запятую (опционально)
 * - managers: список менеджеров через запятую (опционально)
 *
 * Возвращает:
 * {
 *   "success": true,
 *   "data": {
 *     "managers": ["Менеджер 1", "Менеджер 2"],
 *     "total_calls": [100, 85],
 *     "ratio": [1.2, 0.8],
 *     "manager_time": [3600, 2400],
 *     "client_time": [3000, 3000]
 *   }
 * }
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

session_start();
require_once '../../auth/session.php';
checkAuth();

include_once '../../config/database.php';

// Параметры фильтрации
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$departments = isset($_GET['departments']) ? $_GET['departments'] : '';
$managers = isset($_GET['managers']) ? $_GET['managers'] : '';

// Подключение к БД
$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    echo json_encode(["success" => false, "error" => "Database connection failed"]);
    exit();
}

// Получаем список отделов пользователя
$user_departments = getUserDepartments();

// Базовый WHERE с датами
$where_conditions = [
    "cr.started_at_utc >= :date_from",
    "cr.started_at_utc < DATE_ADD(:date_to, INTERVAL 1 DAY)",
    "cr.duration_sec >= 60"  // Только полноценные звонки
];
$params = [':date_from' => $date_from . ' 00:00:00', ':date_to' => $date_to];

// Фильтр по отделам пользователя
if ($_SESSION['role'] !== 'admin' && !empty($user_departments)) {
    $placeholders = [];
    foreach ($user_departments as $idx => $dept) {
        $key = ":user_dept_$idx";
        $placeholders[] = $key;
        $params[$key] = $dept;
    }
    $where_conditions[] = "cr.department IN (" . implode(',', $placeholders) . ")";
}

// Фильтр по выбранным отделам
if (!empty($departments)) {
    $dept_array = explode(',', $departments);
    $dept_placeholders = [];
    foreach ($dept_array as $idx => $dept) {
        $key = ":dept_$idx";
        $dept_placeholders[] = $key;
        $params[$key] = trim($dept);
    }
    $where_conditions[] = "cr.department IN (" . implode(',', $dept_placeholders) . ")";
}

// Фильтр по менеджерам
if (!empty($managers)) {
    $manager_array = explode(',', $managers);
    $manager_placeholders = [];
    foreach ($manager_array as $idx => $manager) {
        $key = ":manager_$idx";
        $manager_placeholders[] = $key;
        $params[$key] = trim($manager);
    }
    $where_conditions[] = "ar.employee_full_name IN (" . implode(',', $manager_placeholders) . ")";
}

$where_clause = implode(' AND ', $where_conditions);

// MOCK DATA - TODO: Реализовать реальный расчет на основе диаризации транскриптов
// Запрос для получения списка менеджеров и количества звонков
$query = "
    SELECT
        ar.employee_full_name as manager_name,
        COUNT(DISTINCT ar.callid) as total_calls,
        SUM(cr.duration_sec) as total_duration
    FROM calls_raw cr
    INNER JOIN analysis_results ar ON cr.callid = ar.callid
    WHERE $where_clause
    GROUP BY ar.employee_full_name
    HAVING total_calls >= 5
    ORDER BY total_calls DESC
    LIMIT 50
";

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $managers = [];
    $total_calls = [];
    $ratio = [];
    $manager_time = [];
    $client_time = [];

    foreach ($results as $row) {
        $managers[] = $row['manager_name'];
        $total_calls[] = intval($row['total_calls']);

        // MOCK: Генерируем случайные, но правдоподобные данные
        // В реальности нужно анализировать диаризацию для расчета времени каждого спикера
        $total_dur = intval($row['total_duration']);

        // Ratio в диапазоне 0.3 - 3.0 (реалистично для call-центра)
        $mock_ratio = round(rand(30, 300) / 100, 2);

        // Расчет времени на основе ratio
        // manager_time / client_time = ratio
        // manager_time + client_time ≈ total_duration * 0.7 (70% разговор, 30% паузы)
        $speaking_time = $total_dur * 0.7;
        $mock_manager_time = round($speaking_time * $mock_ratio / (1 + $mock_ratio));
        $mock_client_time = round($speaking_time - $mock_manager_time);

        $ratio[] = $mock_ratio;
        $manager_time[] = $mock_manager_time;
        $client_time[] = $mock_client_time;
    }

    $response = [
        'success' => true,
        'data' => [
            'managers' => $managers,
            'total_calls' => $total_calls,
            'ratio' => $ratio,
            'manager_time' => $manager_time,
            'client_time' => $client_time
        ],
        'note' => 'MOCK DATA: Для реальных данных требуется анализ диаризации транскриптов'
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Query failed: ' . $e->getMessage()
    ]);
}

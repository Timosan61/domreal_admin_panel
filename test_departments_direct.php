<?php
// Direct test without session - simulate admin access
$_SESSION['role'] = 'admin';
$_SESSION['user_id'] = 1;

function getUserDepartments() {
    return [];
}

include_once 'config/database.php';

$date_from = '2025-10-01';
$date_to = '2025-10-08';
$limit = 5;

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    die(json_encode(["error" => "Database connection failed"]));
}

$where_conditions = ["cr.started_at_utc >= :date_from", "cr.started_at_utc < DATE_ADD(:date_to, INTERVAL 1 DAY)"];
$params = [':date_from' => $date_from . ' 00:00:00', ':date_to' => $date_to, ':limit' => $limit];

$where_clause = implode(' AND ', $where_conditions);

$query = "
    SELECT
        cr.department,
        COUNT(DISTINCT cr.callid) as total_calls,
        COUNT(DISTINCT CASE WHEN ar.callid IS NOT NULL THEN cr.callid END) as analyzed_calls,
        COUNT(DISTINCT CASE WHEN ar.is_successful = 1 THEN cr.callid END) as successful_calls
    FROM calls_raw cr
    LEFT JOIN analysis_results ar ON cr.callid = ar.callid
    WHERE $where_clause
    GROUP BY cr.department
    ORDER BY successful_calls DESC
    LIMIT :limit
";

try {
    $stmt = $db->prepare($query);

    // Bind LIMIT as integer
    foreach ($params as $key => $value) {
        if ($key === ':limit') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode([
        'success' => true,
        'count' => count($rows),
        'data' => $rows
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database query failed: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

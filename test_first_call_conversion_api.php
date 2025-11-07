<?php
/**
 * Test script for first_call_conversion API
 */

// Bypass auth for testing
$_SESSION['role'] = 'admin';
$_SESSION['full_name'] = 'Test User';

// Mock getUserDepartments function
function getUserDepartments() {
    return [];
}

// Include database config
include_once 'config/database.php';

// Parameters
$date_from = '2025-10-01';
$date_to = '2025-10-31';
$hide_short_calls = '1';

// Connect to database
$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    die("Database connection failed\n");
}

echo "✅ Database connected\n\n";

// Test query
$query = "
    SELECT
        ar.employee_full_name as manager_name,
        ar.employee_department as department,

        -- Первые звонки
        COUNT(DISTINCT CASE WHEN cr.is_first_call = 1 THEN ar.callid END) as first_total,
        COUNT(DISTINCT CASE WHEN cr.is_first_call = 1 AND ar.is_successful = 1 THEN ar.callid END) as first_successful,
        ROUND(
            100.0 * COUNT(DISTINCT CASE WHEN cr.is_first_call = 1 AND ar.is_successful = 1 THEN ar.callid END) /
            NULLIF(COUNT(DISTINCT CASE WHEN cr.is_first_call = 1 THEN ar.callid END), 0),
            1
        ) as first_conversion_rate,

        -- Повторные звонки
        COUNT(DISTINCT CASE WHEN cr.is_first_call = 0 THEN ar.callid END) as repeat_total,
        COUNT(DISTINCT CASE WHEN cr.is_first_call = 0 AND ar.is_successful = 1 THEN ar.callid END) as repeat_successful,
        ROUND(
            100.0 * COUNT(DISTINCT CASE WHEN cr.is_first_call = 0 AND ar.is_successful = 1 THEN ar.callid END) /
            NULLIF(COUNT(DISTINCT CASE WHEN cr.is_first_call = 0 THEN ar.callid END), 0),
            1
        ) as repeat_conversion_rate

    FROM calls_raw cr
    INNER JOIN analysis_results ar ON cr.callid = ar.callid
    WHERE cr.started_at_utc >= :date_from
      AND cr.started_at_utc < DATE_ADD(:date_to, INTERVAL 1 DAY)
      AND cr.duration_sec > 10
      AND ar.employee_full_name IS NOT NULL
      AND ar.employee_full_name != ''
    GROUP BY ar.employee_full_name, ar.employee_department
    HAVING COUNT(DISTINCT ar.callid) > 0
    ORDER BY first_conversion_rate IS NULL, first_conversion_rate DESC, repeat_conversion_rate DESC
    LIMIT 10
";

try {
    $stmt = $db->prepare($query);
    $stmt->bindValue(':date_from', $date_from . ' 00:00:00');
    $stmt->bindValue(':date_to', $date_to);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "📊 Query executed successfully\n";
    echo "Found " . count($rows) . " managers\n\n";

    if (count($rows) > 0) {
        echo "Top 5 managers:\n";
        echo str_repeat("=", 100) . "\n";
        printf("%-40s | %8s | %8s | %12s | %8s | %8s | %12s\n",
            "Manager", "1st Tot", "1st Succ", "1st Conv %", "Rep Tot", "Rep Succ", "Rep Conv %");
        echo str_repeat("-", 100) . "\n";

        foreach (array_slice($rows, 0, 5) as $row) {
            printf("%-40s | %8d | %8d | %11.1f%% | %8d | %8d | %11.1f%%\n",
                substr($row['manager_name'], 0, 40),
                $row['first_total'],
                $row['first_successful'],
                $row['first_conversion_rate'] ?? 0,
                $row['repeat_total'],
                $row['repeat_successful'],
                $row['repeat_conversion_rate'] ?? 0
            );
        }

        echo "\n✅ API should work correctly!\n";
    } else {
        echo "⚠️ No data found. Check filters.\n";
    }

} catch (PDOException $e) {
    echo "❌ Query failed: " . $e->getMessage() . "\n";
}
?>

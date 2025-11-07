<?php
session_start();
require_once 'auth/session.php';
checkAuth();

header("Content-Type: text/html; charset=UTF-8");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Analytics API</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        .endpoint { margin: 20px 0; padding: 15px; background: #f5f5f5; border-radius: 5px; }
        .endpoint h3 { margin-top: 0; }
        pre { background: white; padding: 10px; overflow-x: auto; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Analytics API Test</h1>

    <?php
    $date_from = '2025-10-01';
    $date_to = '2025-10-08';

    $endpoints = [
        'KPI' => "/api/analytics/kpi.php?date_from=$date_from&date_to=$date_to",
        'Departments' => "/api/analytics/departments.php?date_from=$date_from&date_to=$date_to",
        'Managers' => "/api/analytics/managers.php?date_from=$date_from&date_to=$date_to",
        'Funnel' => "/api/analytics/funnel.php?date_from=$date_from&date_to=$date_to",
        'Dynamics' => "/api/analytics/dynamics.php?date_from=$date_from&date_to=$date_to",
        'Script Quality' => "/api/analytics/script_quality.php?date_from=$date_from&date_to=$date_to"
    ];

    foreach ($endpoints as $name => $url) {
        echo "<div class='endpoint'>";
        echo "<h3>$name</h3>";
        echo "<p>URL: <code>$url</code></p>";

        $full_url = "https://domrilhost.ru" . $url;

        // Use curl to test
        $ch = curl_init($full_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            echo "<p class='error'>CURL Error: $error</p>";
        } else {
            echo "<p>HTTP Code: <span class='" . ($httpCode == 200 ? "success" : "error") . "'>$httpCode</span></p>";

            if ($httpCode == 200) {
                $data = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    echo "<p class='success'>✓ Valid JSON</p>";
                    echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
                } else {
                    echo "<p class='error'>✗ Invalid JSON: " . json_last_error_msg() . "</p>";
                    echo "<pre>" . htmlspecialchars(substr($response, 0, 1000)) . "</pre>";
                }
            } else {
                echo "<pre>" . htmlspecialchars(substr($response, 0, 1000)) . "</pre>";
            }
        }

        echo "</div>";
    }
    ?>

    <div class="endpoint">
        <h3>Database Connection Test</h3>
        <?php
        include_once 'config/database.php';
        try {
            $database = new Database();
            $db = $database->getConnection();

            if ($db) {
                echo "<p class='success'>✓ Database connected</p>";

                // Test query
                $query = "SELECT COUNT(*) as count FROM calls_raw WHERE started_at_utc >= ? AND started_at_utc < ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                echo "<p>Total calls in period: <strong>" . $result['count'] . "</strong></p>";

                // Test departments query
                $query = "SELECT cr.department, COUNT(*) as count
                         FROM calls_raw cr
                         WHERE cr.started_at_utc >= ? AND cr.started_at_utc < ?
                         GROUP BY cr.department
                         ORDER BY count DESC
                         LIMIT 5";
                $stmt = $db->prepare($query);
                $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
                $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo "<p>Top 5 departments:</p><pre>" . print_r($departments, true) . "</pre>";
            } else {
                echo "<p class='error'>✗ Database connection failed</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
        }
        ?>
    </div>
</body>
</html>

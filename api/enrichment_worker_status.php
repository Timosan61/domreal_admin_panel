<?php
/**
 * API Endpoint: Enrichment Worker Status
 *
 * Returns current worker status including:
 * - Is worker process running
 * - Processing statistics
 * - Recent errors
 * - Pending queue size
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

session_start();
require_once '../auth/session.php';

// ВАЖНО: Проверка админских прав (API режим)
checkAuth($require_admin = true, $is_api = true);

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    echo json_encode(["success" => false, "error" => "Database connection failed"]);
    exit();
}

try {
    // 1. Check if worker process is running
    $workerRunning = false;
    $workerPid = null;

    exec("ps aux | grep 'worker_enrichment.py' | grep -v grep 2>&1", $psOutput);
    if (!empty($psOutput)) {
        $workerRunning = true;
        // Extract PID
        if (preg_match('/\s+(\d+)\s+.*worker_enrichment\.py/', $psOutput[0], $matches)) {
            $workerPid = (int)$matches[1];
        }
    }

    // 2. Get processing statistics from database
    $stmt = $db->prepare("
        SELECT
            COUNT(*) as total_pending,
            SUM(CASE WHEN batch_id IS NOT NULL THEN 1 ELSE 0 END) as pending_from_uploads,
            SUM(CASE WHEN batch_id IS NULL THEN 1 ELSE 0 END) as pending_from_calls
        FROM client_enrichment
        WHERE enrichment_status = 'pending'
    ");
    $stmt->execute();
    $queueStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. Get recent processing stats (last 5 minutes)
    $stmt = $db->prepare("
        SELECT
            COUNT(*) as completed_last_5min
        FROM client_enrichment
        WHERE enrichment_status = 'completed'
        AND updated_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $stmt->execute();
    $recentStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. Check for recent errors in worker log
    $logFile = '/home/z/ailoca/Domreal_Whisper/logs/worker_enrichment.log';
    $recentErrors = [];
    $lastActivity = null;

    if (file_exists($logFile) && is_readable($logFile)) {
        // Get last 50 lines
        $logContent = file($logFile);
        if ($logContent !== false) {
            $logLines = array_slice($logContent, -50);

            // Find last activity timestamp
            foreach (array_reverse($logLines) as $line) {
                if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $matches)) {
                    $lastActivity = $matches[1];
                    break;
                }
            }

            // Find errors in last 50 lines
            foreach ($logLines as $line) {
                if (stripos($line, 'ERROR') !== false || stripos($line, 'CRITICAL') !== false) {
                    // Extract timestamp and message
                    if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}).*?\|\s*(.*)$/', $line, $matches)) {
                        $recentErrors[] = [
                            'timestamp' => $matches[1],
                            'message' => trim($matches[2])
                        ];
                    }
                }
            }

            // Keep only last 5 errors
            $recentErrors = array_slice($recentErrors, -5);
        }
    }

    // 5. Calculate worker health
    $workerHealthy = true;
    $healthIssues = [];

    if (!$workerRunning) {
        $workerHealthy = false;
        $healthIssues[] = 'Worker процесс не запущен';
    }

    if ($lastActivity) {
        $lastActivityTime = strtotime($lastActivity);
        $timeSinceActivity = time() - $lastActivityTime;

        if ($timeSinceActivity > 600) { // 10 minutes
            $workerHealthy = false;
            $healthIssues[] = 'Worker не проявлял активности ' . round($timeSinceActivity / 60) . ' минут';
        }
    }

    // Улучшенная логика проверки ошибок
    if (!empty($recentErrors)) {
        $errorCount = count($recentErrors);
        $criticalErrorCount = 0;

        // Подсчитываем критичные ошибки
        foreach ($recentErrors as $error) {
            $errorMessage = is_array($error) ? $error['message'] : $error;
            if (stripos($errorMessage, 'CRITICAL') !== false ||
                stripos($errorMessage, 'Критическая ошибка') !== false) {
                $criticalErrorCount++;
            }
        }

        // Worker нездоров только при критичных/частых ошибках
        if ($criticalErrorCount > 0) {
            $workerHealthy = false;
            $healthIssues[] = "Критичные ошибки в логах ($criticalErrorCount)";
        } elseif ($errorCount > 5) {
            $workerHealthy = false;
            $healthIssues[] = "Частые ошибки ($errorCount за последние 50 строк)";
        } elseif ($errorCount > 0) {
            // Некритично - просто показываем info, не помечаем как проблему
            $healthIssues[] = "Некритичные ошибки ($errorCount) - worker работает";
        }
    }

    // 6. Get batch-specific stats (for uploaded files)
    $stmt = $db->prepare("
        SELECT
            eb.id,
            eb.batch_name,
            eb.total_records,
            COUNT(CASE WHEN ce.enrichment_status = 'completed' THEN 1 END) as completed,
            COUNT(CASE WHEN ce.enrichment_status = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN ce.enrichment_status = 'error' THEN 1 END) as errors
        FROM enrichment_batches eb
        LEFT JOIN client_enrichment ce ON eb.id = ce.batch_id
        WHERE eb.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY eb.id, eb.batch_name, eb.total_records
        HAVING pending > 0
        ORDER BY eb.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $activeBatches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate processing speed (records per minute)
    $processingSpeed = 0;
    if ($recentStats['completed_last_5min'] > 0) {
        $processingSpeed = round($recentStats['completed_last_5min'] / 5, 1); // per minute
    }

    // 7. Build response
    $response = [
        'success' => true,
        'worker' => [
            'running' => $workerRunning,
            'pid' => $workerPid,
            'healthy' => $workerHealthy,
            'last_activity' => $lastActivity,
            'health_issues' => $healthIssues
        ],
        'queue' => [
            'total_pending' => (int)$queueStats['total_pending'],
            'from_uploads' => (int)$queueStats['pending_from_uploads'],
            'from_calls' => (int)$queueStats['pending_from_calls']
        ],
        'performance' => [
            'completed_last_5min' => (int)$recentStats['completed_last_5min'],
            'speed_per_minute' => $processingSpeed
        ],
        'errors' => $recentErrors,
        'active_batches' => $activeBatches,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

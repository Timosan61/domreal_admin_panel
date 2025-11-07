<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "=== ТЕСТ API ENDPOINT ===\n\n";

// Эмулируем сессию админа
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin1';
$_SESSION['full_name'] = 'Admin Test';
$_SESSION['role'] = 'admin';

echo "✅ Сессия установлена: user_id=" . $_SESSION['user_id'] . ", role=" . $_SESSION['role'] . "\n\n";

// Эмулируем GET параметры
$_GET['stats'] = '1';

echo "=== Подключение session.php ===\n";
try {
    require_once 'auth/session.php';
    echo "✅ session.php подключен\n\n";
} catch (Exception $e) {
    echo "❌ Ошибка подключения session.php: " . $e->getMessage() . "\n";
    exit(1);
}

echo "=== Вызов checkAuth() ===\n";
try {
    checkAuth($require_admin = true, $is_api = true);
    echo "✅ checkAuth() пройдена\n\n";
} catch (Exception $e) {
    echo "❌ Ошибка checkAuth(): " . $e->getMessage() . "\n";
    exit(1);
}

echo "=== Подключение database.php ===\n";
try {
    include_once 'config/database.php';
    echo "✅ database.php подключен\n\n";
} catch (Exception $e) {
    echo "❌ Ошибка подключения database.php: " . $e->getMessage() . "\n";
    exit(1);
}

echo "=== Подключение к БД ===\n";
try {
    $database = new Database();
    $db = $database->getConnection();

    if ($db === null) {
        throw new Exception("Database connection returned null");
    }

    echo "✅ Подключение к БД успешно\n\n";
} catch (Exception $e) {
    echo "❌ Ошибка подключения к БД: " . $e->getMessage() . "\n";
    exit(1);
}

echo "=== Выполнение SQL запроса статистики ===\n";
try {
    $stats_query = "
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN inn IS NOT NULL AND inn != '' THEN 1 ELSE 0 END) as with_inn,
            SUM(CASE WHEN rusprofile_parsed = TRUE THEN 1 ELSE 0 END) as with_rusprofile,
            SUM(CASE WHEN solvency_analyzed = TRUE THEN 1 ELSE 0 END) as with_solvency,
            SUM(CASE WHEN enrichment_status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN enrichment_status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN enrichment_status = 'error' THEN 1 ELSE 0 END) as errors,
            SUM(CASE WHEN enrichment_status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(databases_checked) as total_databases_checked,
            SUM(databases_found) as total_databases_found
        FROM client_enrichment
    ";

    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

    echo "✅ SQL запрос выполнен успешно\n";
    echo "Результат:\n";
    print_r($stats);
    echo "\n";

} catch (Exception $e) {
    echo "❌ Ошибка SQL запроса: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== ВСЕ ТЕСТЫ ПРОЙДЕНЫ ✅ ===\n";

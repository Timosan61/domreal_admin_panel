<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "=== ТЕСТ ЗАГРУЗКИ ДАННЫХ ===\n\n";

// Эмулируем сессию админа
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin1';
$_SESSION['full_name'] = 'Admin Test';
$_SESSION['role'] = 'admin';

echo "✅ Сессия установлена: user_id=" . $_SESSION['user_id'] . ", role=" . $_SESSION['role'] . "\n\n";

// Эмулируем GET параметры
$_GET['page'] = '1';
$_GET['per_page'] = '10';
$_GET['sort_by'] = 'created_at';
$_GET['sort_order'] = 'DESC';

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

echo "=== Выполнение SQL запроса данных ===\n";
try {
    $page = 1;
    $per_page = 10;
    $sort_by = 'created_at';
    $sort_order = 'DESC';
    $offset = ($page - 1) * $per_page;

    $query = "SELECT
        id,
        client_phone,
        inn,
        inn_source,
        enrichment_status,
        created_at,
        updated_at,
        userbox_searched,
        databases_found,
        databases_checked,
        rusprofile_parsed,
        company_name,
        company_full_name,
        ogrn,
        director_name,
        revenue_last_year,
        employees_count,
        solvency_analyzed,
        solvency_level,
        solvency_summary,
        rusprofile_parsed_at,
        solvency_analyzed_at
    FROM client_enrichment
    WHERE 1=1
    ORDER BY " . $sort_by . " " . $sort_order . "
    LIMIT :limit OFFSET :offset";

    echo "SQL запрос:\n" . $query . "\n\n";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    echo "Выполнение запроса...\n";
    $stmt->execute();

    echo "Получение данных...\n";
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "✅ SQL запрос выполнен успешно\n";
    echo "Количество записей: " . count($records) . "\n";

    if (count($records) > 0) {
        echo "\nПервая запись:\n";
        print_r($records[0]);
    }
    echo "\n";

} catch (Exception $e) {
    echo "❌ Ошибка SQL запроса: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== ВСЕ ТЕСТЫ ПРОЙДЕНЫ ✅ ===\n";

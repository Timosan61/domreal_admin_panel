<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== ТЕСТ SQL ЗАПРОСА ===\n\n";

include_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($db === null) {
        throw new Exception("Database connection failed");
    }

    echo "✅ Подключение к БД успешно\n\n";

    // Test the exact query from enrichment_data.php
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

    echo "Preparing statement...\n";
    $stmt = $db->prepare($query);

    echo "Binding parameters...\n";
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    echo "Executing query...\n";
    $stmt->execute();

    echo "Fetching results...\n";
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "✅ Query successful!\n";
    echo "Records found: " . count($records) . "\n\n";

    if (count($records) > 0) {
        echo "First record ID: " . $records[0]['id'] . "\n";
        echo "Client phone: " . $records[0]['client_phone'] . "\n";
        echo "INN: " . ($records[0]['inn'] ?? 'NULL') . "\n";
    }

    // Now test the count query
    echo "\n=== Testing count query ===\n";
    $count_query = "SELECT COUNT(*) as total FROM (SELECT id FROM client_enrichment WHERE 1=1) as filtered";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute();
    $total_count = $count_stmt->fetch()['total'];
    echo "Total count: " . $total_count . "\n";

    echo "\n✅ ALL TESTS PASSED\n";

} catch (PDOException $e) {
    echo "❌ PDO Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

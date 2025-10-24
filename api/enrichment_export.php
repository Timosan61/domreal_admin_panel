<?php
/**
 * API для экспорта данных обогащения клиентов в Excel/CSV
 * GET /api/enrichment_export.php
 *
 * ВАЖНО: Доступ только для администраторов!
 */

session_start();
require_once '../auth/session.php';

// КРИТИЧЕСКИ ВАЖНО: Проверка админских прав
checkAuth($require_admin = true);

include_once '../config/database.php';

// Получаем формат экспорта
$export_format = isset($_GET['export']) ? $_GET['export'] : 'csv';

// Получаем параметры фильтрации (такие же как в enrichment_data.php)
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$inn_filter = isset($_GET['inn_filter']) ? $_GET['inn_filter'] : '';
$source_filter = isset($_GET['source_filter']) ? $_GET['source_filter'] : '';
$rusprofile_filter = isset($_GET['rusprofile_filter']) ? $_GET['rusprofile_filter'] : '';
$solvency_filter = isset($_GET['solvency_filter']) ? $_GET['solvency_filter'] : '';
$phone_search = isset($_GET['phone_search']) ? $_GET['phone_search'] : '';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    die("Database connection failed");
}

// Базовый запрос (без ограничения LIMIT для экспорта всех данных)
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
    profit_last_year,
    number_of_employees,
    number_of_founders,
    authorized_capital,
    legal_address,
    main_activity,
    solvency_analyzed,
    solvency_level,
    solvency_summary,
    solvency_reasoning,
    rusprofile_parse_date,
    solvency_analysis_date,
    apifns_companies_count,
    apifns_total_revenue,
    apifns_total_profit
FROM client_enrichment
WHERE 1=1";

$params = [];

// Применяем фильтры (точно так же как в enrichment_data.php)
if (!empty($date_from)) {
    $query .= " AND DATE(created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

if (!empty($status)) {
    $query .= " AND enrichment_status = :status";
    $params[':status'] = $status;
}

if ($inn_filter === 'yes') {
    $query .= " AND inn IS NOT NULL AND inn != ''";
} elseif ($inn_filter === 'no') {
    $query .= " AND (inn IS NULL OR inn = '')";
}

if (!empty($source_filter)) {
    $query .= " AND inn_source LIKE :source_filter";
    $params[':source_filter'] = '%' . $source_filter . '%';
}

if ($rusprofile_filter === 'yes') {
    $query .= " AND rusprofile_parsed = 1";
} elseif ($rusprofile_filter === 'no') {
    $query .= " AND (rusprofile_parsed = 0 OR rusprofile_parsed IS NULL)";
}

if ($solvency_filter === 'yes') {
    $query .= " AND solvency_analyzed = 1 AND solvency_level IS NOT NULL";
} elseif ($solvency_filter === 'no') {
    $query .= " AND (solvency_analyzed = 0 OR solvency_analyzed IS NULL OR solvency_level IS NULL)";
}

if (!empty($phone_search)) {
    $query .= " AND client_phone LIKE :phone_search";
    $params[':phone_search'] = '%' . $phone_search . '%';
}

// Добавляем сортировку
$query .= " ORDER BY created_at DESC";

// Выполняем запрос
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Экспорт в зависимости от формата
if ($export_format === 'excel') {
    exportToExcel($records);
} else {
    exportToCSV($records);
}

/**
 * Экспорт в CSV
 */
function exportToCSV($records) {
    $filename = 'enrichment_export_' . date('Y-m-d_H-i-s') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // BOM для корректного отображения UTF-8 в Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Заголовки
    $headers = [
        'ID',
        'Телефон',
        'ИНН',
        'Источник ИНН',
        'Статус обогащения',
        'Дата обогащения',
        'Userbox обработан',
        'Баз найдено',
        'Баз проверено',
        'RusProfile обработан',
        'Название компании',
        'Полное название',
        'ОГРН',
        'Директор',
        'Выручка (руб)',
        'Прибыль (руб)',
        'Сотрудников',
        'Уставный капитал',
        'Юридический адрес',
        'Основной вид деятельности',
        'GigaChat обработан',
        'Уровень платежеспособности',
        'Краткий анализ',
        'Дата парсинга RusProfile',
        'Дата анализа GigaChat',
        'Компаний (учредитель)',
        'Суммарная выручка (руб)',
        'Суммарная прибыль (руб)'
    ];

    fputcsv($output, $headers, ';');

    // Данные
    foreach ($records as $record) {
        $row = [
            $record['id'],
            $record['client_phone'],
            $record['inn'] ?? '',
            $record['inn_source'] ?? '',
            translateStatus($record['enrichment_status']),
            $record['created_at'] ?? '',
            $record['userbox_searched'] ? 'Да' : 'Нет',
            $record['databases_found'] ?? 0,
            $record['databases_checked'] ?? 0,
            $record['rusprofile_parsed'] ? 'Да' : 'Нет',
            $record['company_name'] ?? '',
            $record['company_full_name'] ?? '',
            $record['ogrn'] ?? '',
            $record['director_name'] ?? '',
            $record['revenue_last_year'] ?? '',
            $record['profit_last_year'] ?? '',
            $record['number_of_employees'] ?? '',
            $record['authorized_capital'] ?? '',
            $record['legal_address'] ?? '',
            $record['main_activity'] ?? '',
            $record['solvency_analyzed'] ? 'Да' : 'Нет',
            translateSolvency($record['solvency_level']),
            $record['solvency_summary'] ?? '',
            $record['rusprofile_parse_date'] ?? '',
            $record['solvency_analysis_date'] ?? '',
            $record['apifns_companies_count'] ?? 0,
            $record['apifns_total_revenue'] ?? '',
            $record['apifns_total_profit'] ?? ''
        ];

        fputcsv($output, $row, ';');
    }

    fclose($output);
    exit();
}

/**
 * Экспорт в Excel (HTML таблица с MIME типом Excel)
 */
function exportToExcel($records) {
    $filename = 'enrichment_export_' . date('Y-m-d_H-i-s') . '.xls';

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF"; // BOM для UTF-8

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>';
    echo '<body><table border="1">';

    // Заголовки
    echo '<tr style="font-weight: bold; background-color: #f0f0f0;">';
    echo '<td>ID</td>';
    echo '<td>Телефон</td>';
    echo '<td>ИНН</td>';
    echo '<td>Источник ИНН</td>';
    echo '<td>Статус обогащения</td>';
    echo '<td>Дата обогащения</td>';
    echo '<td>Userbox обработан</td>';
    echo '<td>Баз найдено</td>';
    echo '<td>Баз проверено</td>';
    echo '<td>RusProfile обработан</td>';
    echo '<td>Название компании</td>';
    echo '<td>Полное название</td>';
    echo '<td>ОГРН</td>';
    echo '<td>Директор</td>';
    echo '<td>Выручка (руб)</td>';
    echo '<td>Прибыль (руб)</td>';
    echo '<td>Сотрудников</td>';
    echo '<td>Уставный капитал</td>';
    echo '<td>Юридический адрес</td>';
    echo '<td>Основной вид деятельности</td>';
    echo '<td>GigaChat обработан</td>';
    echo '<td>Уровень платежеспособности</td>';
    echo '<td>Краткий анализ</td>';
    echo '<td>Дата парсинга RusProfile</td>';
    echo '<td>Дата анализа GigaChat</td>';
    echo '<td>Компаний (учредитель)</td>';
    echo '<td>Суммарная выручка (руб)</td>';
    echo '<td>Суммарная прибыль (руб)</td>';
    echo '</tr>';

    // Данные
    foreach ($records as $record) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($record['id']) . '</td>';
        echo '<td>' . htmlspecialchars($record['client_phone']) . '</td>';
        echo '<td>' . htmlspecialchars($record['inn'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($record['inn_source'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars(translateStatus($record['enrichment_status'])) . '</td>';
        echo '<td>' . htmlspecialchars($record['created_at'] ?? '') . '</td>';
        echo '<td>' . ($record['userbox_searched'] ? 'Да' : 'Нет') . '</td>';
        echo '<td>' . htmlspecialchars($record['databases_found'] ?? 0) . '</td>';
        echo '<td>' . htmlspecialchars($record['databases_checked'] ?? 0) . '</td>';
        echo '<td>' . ($record['rusprofile_parsed'] ? 'Да' : 'Нет') . '</td>';
        echo '<td>' . htmlspecialchars($record['company_name'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($record['company_full_name'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($record['ogrn'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($record['director_name'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($record['revenue_last_year'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($record['profit_last_year'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($record['number_of_employees'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($record['authorized_capital'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($record['legal_address'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($record['main_activity'] ?? '') . '</td>';
        echo '<td>' . ($record['solvency_analyzed'] ? 'Да' : 'Нет') . '</td>';
        echo '<td>' . htmlspecialchars(translateSolvency($record['solvency_level'])) . '</td>';
        echo '<td>' . htmlspecialchars($record['solvency_summary'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($record['rusprofile_parse_date'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($record['solvency_analysis_date'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($record['apifns_companies_count'] ?? 0) . '</td>';
        echo '<td>' . htmlspecialchars($record['apifns_total_revenue'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($record['apifns_total_profit'] ?? '') . '</td>';
        echo '</tr>';
    }

    echo '</table></body></html>';
    exit();
}

/**
 * Перевод статуса на русский
 */
function translateStatus($status) {
    $statuses = [
        'completed' => 'Завершено',
        'in_progress' => 'В процессе',
        'error' => 'Ошибка',
        'pending' => 'Ожидание'
    ];
    return $statuses[$status] ?? $status;
}

/**
 * Перевод уровня платежеспособности на русский
 */
function translateSolvency($level) {
    $levels = [
        'green' => 'Высокая',
        'blue' => 'Средняя',
        'yellow' => 'Низкая',
        'red' => 'Очень низкая'
    ];
    return $levels[$level] ?? ($level ?? '—');
}
?>

<?php
/**
 * API для экспорта данных обогащения клиентов в Excel (XLSX)
 * POST/GET /api/enrichment_export_xlsx.php
 *
 * ВАЖНО: Доступ только для администраторов!
 *
 * Параметры:
 * - GET: все фильтры из enrichment_data.php (date_from, date_to, status, solvency_levels и т.д.)
 * - POST: selected_ids[] - массив ID для экспорта только выбранных записей
 */

// Увеличим лимиты для работы с большими файлами
@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', '300');

session_start();
require_once '../auth/session.php';

// КРИТИЧЕСКИ ВАЖНО: Проверка админских прав
checkAuth($require_admin = true);

include_once '../config/database.php';

// Подключаем PhpSpreadsheet
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

// Список РОПов (Региональных отделов продаж)
$ROP_DEPARTMENTS = [
    "Не назначен",
    "РОП Москва",
    "РОП Санкт-Петербург",
    "РОП Екатеринбург",
    "РОП Новосибирск",
    "РОП Казань",
    "РОП Нижний Новгород",
    "РОП Челябинск",
    "РОП Самара",
    "РОП Омск",
    "РОП Ростов-на-Дону",
    "РОП Уфа",
    "РОП Красноярск",
    "РОП Воронеж",
    "РОП Пермь",
    "РОП Волгоград",
];

/**
 * Форматирование уровня платежеспособности
 */
function formatSolvencyLevel($level) {
    if (empty($level)) return "—";

    $solvency_map = [
        'green' => '🟢 Низкая (до 10 млн)',
        'blue' => '🔵 Средняя (до 100 млн)',
        'yellow' => '🟡 Высокая (до 500 млн)',
        'red' => '🔴 Очень высокая (до 2 млрд)',
        'purple' => '🟣 Премиальная (свыше 2 млрд)',
    ];
    return $solvency_map[$level] ?? $level;
}

/**
 * Форматирование статуса обогащения
 */
function formatStatus($status) {
    if (empty($status)) return "—";

    $status_map = [
        'pending' => 'Ожидание',
        'in_progress' => 'В процессе',
        'completed' => 'Завершено',
        'error' => 'Ошибка',
    ];
    return $status_map[$status] ?? $status;
}

/**
 * Форматирование даты
 */
function formatDateTime($datetime) {
    if (empty($datetime)) return "—";
    return date('Y-m-d H:i', strtotime($datetime));
}

/**
 * Форматирование суммы (выручка/прибыль)
 */
function formatRevenue($value) {
    if (empty($value) || $value === '0') return "—";

    // Удаляем "₽" и лишние символы
    $clean_value = preg_replace('/[^\d.]/', '', $value);

    if (is_numeric($clean_value)) {
        $num = floatval($clean_value);
        if ($num >= 1000000000) {
            return number_format($num / 1000000000, 2, '.', ' ') . ' млрд ₽';
        } elseif ($num >= 1000000) {
            return number_format($num / 1000000, 2, '.', ' ') . ' млн ₽';
        } else {
            return number_format($num, 0, '.', ' ') . ' ₽';
        }
    }

    return $value;
}

// ======================
// ПОЛУЧАЕМ ПАРАМЕТРЫ
// ======================

// Параметры фильтрации из GET
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$enriched_date_from = isset($_GET['enriched_date_from']) ? $_GET['enriched_date_from'] : '';
$enriched_date_to = isset($_GET['enriched_date_to']) ? $_GET['enriched_date_to'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$inn_filter = isset($_GET['inn_filter']) ? $_GET['inn_filter'] : '';
$source_filter = isset($_GET['source_filter']) ? $_GET['source_filter'] : '';
$data_source_filter = isset($_GET['data_source_filter']) ? $_GET['data_source_filter'] : '';
$webhook_source_filter = isset($_GET['webhook_source_filter']) ? $_GET['webhook_source_filter'] : ''; // NEW: Фильтр GCK vs Calls
$phone_search = isset($_GET['phone_search']) ? $_GET['phone_search'] : '';
$solvency_levels = isset($_GET['solvency_levels']) ? $_GET['solvency_levels'] : '';
$batch_id = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : 0;

// Массив выбранных ID из POST (если экспорт только выбранных)
$selected_ids = isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])
    ? array_map('intval', $_POST['selected_ids'])
    : [];

// ======================
// ПОСТРОЕНИЕ SQL ЗАПРОСА
// ======================

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    die("Database connection failed");
}

$query = "SELECT
    id,
    client_phone,
    inn,
    inn_source,
    company_name,
    director_name,
    dadata_total_revenue,
    dadata_total_profit,
    solvency_level,
    enrichment_status,
    dadata_companies_count,
    data_source,
    webhook_source,
    created_at,
    updated_at
FROM client_enrichment
WHERE 1=1";

$params = [];

// Фильтр по выбранным ID (приоритетнее всех остальных фильтров)
if (!empty($selected_ids)) {
    $placeholders = str_repeat('?,', count($selected_ids) - 1) . '?';
    $query .= " AND id IN ($placeholders)";
    $params = array_merge($params, $selected_ids);
}

// Остальные фильтры применяются только если не используется selected_ids
if (empty($selected_ids)) {
    if (!empty($date_from)) {
        $query .= " AND DATE(created_at) >= ?";
        $params[] = $date_from;
    }

    if (!empty($date_to)) {
        $query .= " AND DATE(created_at) <= ?";
        $params[] = $date_to;
    }

    if (!empty($enriched_date_from)) {
        $query .= " AND DATE(updated_at) >= ?";
        $params[] = $enriched_date_from;
    }

    if (!empty($enriched_date_to)) {
        $query .= " AND DATE(updated_at) <= ?";
        $params[] = $enriched_date_to;
    }

    if (!empty($status)) {
        $query .= " AND enrichment_status = ?";
        $params[] = $status;
    }

    if ($batch_id > 0) {
        $query .= " AND batch_id = ?";
        $params[] = $batch_id;
    }

    if ($inn_filter === 'with_inn') {
        $query .= " AND inn IS NOT NULL AND inn != ''";
    } elseif ($inn_filter === 'without_inn') {
        $query .= " AND (inn IS NULL OR inn = '')";
    }

    if (!empty($source_filter)) {
        $query .= " AND inn_source = ?";
        $params[] = $source_filter;
    }

    if (!empty($data_source_filter)) {
        $query .= " AND data_source = ?";
        $params[] = $data_source_filter;
    }

    if (!empty($phone_search)) {
        $query .= " AND client_phone LIKE ?";
        $params[] = '%' . $phone_search . '%';
    }

    if (!empty($solvency_levels)) {
        $levels = explode(',', $solvency_levels);
        $placeholders = str_repeat('?,', count($levels) - 1) . '?';
        $query .= " AND solvency_level IN ($placeholders)";
        $params = array_merge($params, $levels);
    }

    // Фильтр по webhook source (GCK / Beeline Calls)
    if (!empty($webhook_source_filter)) {
        if ($webhook_source_filter === 'gck') {
            $query .= " AND webhook_source = ?";
            $params[] = 'gck';
        } elseif ($webhook_source_filter === 'calls') {
            $query .= " AND (webhook_source IS NULL OR webhook_source = ?)";
            $params[] = '';
        }
    }
}

$query .= " ORDER BY created_at DESC";

// Выполняем запрос
$stmt = $db->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ======================
// СОЗДАНИЕ EXCEL ФАЙЛА
// ======================

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Заголовки колонок
$headers = [
    'A' => 'Получатель (РОП)',
    'B' => 'ID',
    'C' => 'Телефон',
    'D' => 'ИНН',
    'E' => 'Источник ИНН',
    'F' => 'Название компании',
    'G' => 'ФИО директора',
    'H' => 'Выручка',
    'I' => 'Прибыль',
    'J' => 'Платежеспособность',
    'K' => 'Статус обогащения',
    'L' => 'Кол-во компаний',
    'M' => 'Источник данных',
    'N' => 'Дата создания',
    'O' => 'Дата обновления',
];

// Установка заголовков
foreach ($headers as $col => $header) {
    $sheet->setCellValue($col . '1', $header);
}

// Форматирование заголовков
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 11,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4472C4'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000'],
        ],
    ],
];

$sheet->getStyle('A1:O1')->applyFromArray($headerStyle);

// Установка ширины колонок
$sheet->getColumnDimension('A')->setWidth(25); // РОП
$sheet->getColumnDimension('B')->setWidth(8);  // ID
$sheet->getColumnDimension('C')->setWidth(15); // Телефон
$sheet->getColumnDimension('D')->setWidth(12); // ИНН
$sheet->getColumnDimension('E')->setWidth(15); // Источник ИНН
$sheet->getColumnDimension('F')->setWidth(30); // Название
$sheet->getColumnDimension('G')->setWidth(25); // ФИО
$sheet->getColumnDimension('H')->setWidth(18); // Выручка
$sheet->getColumnDimension('I')->setWidth(18); // Прибыль
$sheet->getColumnDimension('J')->setWidth(25); // Платежеспособность
$sheet->getColumnDimension('K')->setWidth(15); // Статус
$sheet->getColumnDimension('L')->setWidth(12); // Кол-во компаний
$sheet->getColumnDimension('M')->setWidth(15); // Источник данных
$sheet->getColumnDimension('N')->setWidth(16); // Дата создания
$sheet->getColumnDimension('O')->setWidth(16); // Дата обновления

// Заполнение данными
$row = 2; // Начинаем со второй строки (первая - заголовки)
foreach ($records as $record) {
    $sheet->setCellValue('A' . $row, 'Не назначен'); // РОП по умолчанию
    $sheet->setCellValue('B' . $row, $record['id']);
    $sheet->setCellValue('C' . $row, $record['client_phone'] ?: '—');
    $sheet->setCellValue('D' . $row, $record['inn'] ?: '—');
    $sheet->setCellValue('E' . $row, $record['inn_source'] ?: '—');
    $sheet->setCellValue('F' . $row, $record['company_name'] ?: '—');
    $sheet->setCellValue('G' . $row, $record['director_name'] ?: '—');
    $sheet->setCellValue('H' . $row, formatRevenue($record['dadata_total_revenue']));
    $sheet->setCellValue('I' . $row, formatRevenue($record['dadata_total_profit']));
    $sheet->setCellValue('J' . $row, formatSolvencyLevel($record['solvency_level']));
    $sheet->setCellValue('K' . $row, formatStatus($record['enrichment_status']));
    $sheet->setCellValue('L' . $row, $record['dadata_companies_count'] ?: '—');
    $sheet->setCellValue('M' . $row, $record['data_source'] ?: '—');
    $sheet->setCellValue('N' . $row, formatDateTime($record['created_at']));
    $sheet->setCellValue('O' . $row, formatDateTime($record['updated_at']));

    $row++;
}

// Добавляем dropdown для колонки "Получатель (РОП)"
if ($row > 2) { // Если есть хотя бы одна запись
    $validation = $sheet->getCell('A2')->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST);
    $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
    $validation->setAllowBlank(false);
    $validation->setShowInputMessage(true);
    $validation->setShowErrorMessage(true);
    $validation->setShowDropDown(true);
    $validation->setErrorTitle('Ошибка ввода');
    $validation->setError('Выберите значение из списка');
    $validation->setPromptTitle('Выбор РОПа');
    $validation->setPrompt('Выберите регион');
    $validation->setFormula1('"' . implode(',', $ROP_DEPARTMENTS) . '"');

    // Копируем валидацию на все строки
    for ($i = 2; $i < $row; $i++) {
        $sheet->getCell('A' . $i)->setDataValidation(clone $validation);
    }
}

// Применяем границы ко всем ячейкам с данными
if ($row > 2) {
    $dataStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'CCCCCC'],
            ],
        ],
    ];
    $sheet->getStyle('A2:O' . ($row - 1))->applyFromArray($dataStyle);
}

// ======================
// ФОРМАТИРОВАНИЕ КОЛОНОК
// ======================

// 1. Устанавливаем числовой формат для колонки ИНН (D)
if ($row > 2) {
    $sheet->getStyle('D2:D' . ($row - 1))->getNumberFormat()
        ->setFormatCode('0'); // Числовой формат без десятичных знаков
}

// 2. Скрываем ненужные колонки
$sheet->getColumnDimension('B')->setVisible(false); // ID
$sheet->getColumnDimension('E')->setVisible(false); // Источник ИНН
$sheet->getColumnDimension('F')->setVisible(false); // Название компании
$sheet->getColumnDimension('G')->setVisible(false); // ФИО директора

// 3. Устанавливаем автофильтр для всех колонок
$sheet->setAutoFilter('A1:O1');

// Закрепляем первую строку
$sheet->freezePane('A2');

// ======================
// ОТПРАВКА ФАЙЛА
// ======================

// Имя файла с временной меткой
$filename = 'money_tracker_export_' . date('Y-m-d_His') . '.xlsx';

// Заголовки для скачивания
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Создаем writer и сохраняем в output
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

// Очищаем память
$spreadsheet->disconnectWorksheets();
unset($spreadsheet);

exit();

<?php
/**
 * Weekly FIP Analysis API
 *
 * API для работы с еженедельными агрегациями анализа ошибок
 * Источник данных: fip_weekly_aggregations
 *
 * Endpoints:
 * GET ?action=weeks_list              - Список доступных недель
 * GET ?action=week_summary&week=...   - KPI за неделю
 * GET ?action=managers_table&week=... - Таблица менеджеров
 * GET ?action=manager_details&week=...&name=... - Детали менеджера
 * GET ?action=trends_chart&weeks=N    - Данные для графика трендов
 * GET ?action=fip_modules             - Список FIP-модулей
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/session.php';
checkAuth(false, true);

$database = new Database();
$pdo = $database->getConnection();
$org_id = $_SESSION['org_id'] ?? 'org-legacy';
$action = $_GET['action'] ?? 'weeks_list';

try {
    switch ($action) {
        case 'weeks_list':
            echo json_encode(getWeeksList($pdo, $org_id));
            break;

        case 'week_summary':
            $week = $_GET['week'] ?? null;
            echo json_encode(getWeekSummary($pdo, $org_id, $week));
            break;

        case 'managers_table':
            $week = $_GET['week'] ?? null;
            echo json_encode(getManagersTable($pdo, $org_id, $week));
            break;

        case 'manager_details':
            $week = $_GET['week'] ?? null;
            $name = $_GET['name'] ?? null;
            echo json_encode(getManagerDetails($pdo, $org_id, $week, $name));
            break;

        case 'trends_chart':
            $weeks = isset($_GET['weeks']) ? (int)$_GET['weeks'] : 8;
            echo json_encode(getTrendsChart($pdo, $org_id, $weeks));
            break;

        case 'fip_modules':
            echo json_encode(getFipModules($pdo));
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Список доступных недель
 */
function getWeeksList($pdo, $org_id) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            week_start,
            week_end,
            COUNT(DISTINCT employee_full_name) as managers_count,
            SUM(total_calls) as total_calls
        FROM fip_weekly_aggregations
        WHERE org_id = ?
        GROUP BY week_start, week_end
        ORDER BY week_start DESC
        LIMIT 52
    ");
    $stmt->execute([$org_id]);
    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Форматируем даты
    foreach ($weeks as &$week) {
        $start = new DateTime($week['week_start']);
        $end = new DateTime($week['week_end']);
        $week['label'] = $start->format('d.m') . ' - ' . $end->format('d.m.Y');
        $week['week_start'] = $start->format('Y-m-d');
        $week['week_end'] = $end->format('Y-m-d');
        $week['managers_count'] = (int)$week['managers_count'];
        $week['total_calls'] = (int)$week['total_calls'];
    }

    return [
        'success' => true,
        'data' => $weeks
    ];
}

/**
 * KPI за выбранную неделю
 */
function getWeekSummary($pdo, $org_id, $week) {
    if (!$week) {
        // Берём последнюю неделю
        $stmt = $pdo->prepare("
            SELECT week_start FROM fip_weekly_aggregations
            WHERE org_id = ? ORDER BY week_start DESC LIMIT 1
        ");
        $stmt->execute([$org_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $week = $row ? $row['week_start'] : date('Y-m-d', strtotime('monday this week'));
    }

    $stmt = $pdo->prepare("
        SELECT
            COUNT(DISTINCT employee_full_name) as managers_count,
            SUM(total_calls) as total_calls,
            AVG(avg_compliance_score) as avg_compliance,
            SUM(recommendations_generated) as total_recommendations,
            GROUP_CONCAT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(critical_skills, '$[*]'))) as all_critical_skills
        FROM fip_weekly_aggregations
        WHERE org_id = ? AND week_start = ?
    ");
    $stmt->execute([$org_id, $week]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // Подсчёт уникальных критических навыков
    $criticalSkills = [];
    if (!empty($summary['all_critical_skills'])) {
        $skills = explode(',', $summary['all_critical_skills']);
        $criticalSkills = array_unique(array_filter($skills));
    }

    // Получаем данные предыдущей недели для сравнения
    $prevWeek = date('Y-m-d', strtotime($week . ' -7 days'));
    $stmtPrev = $pdo->prepare("
        SELECT AVG(avg_compliance_score) as prev_compliance
        FROM fip_weekly_aggregations
        WHERE org_id = ? AND week_start = ?
    ");
    $stmtPrev->execute([$org_id, $prevWeek]);
    $prev = $stmtPrev->fetch(PDO::FETCH_ASSOC);

    $trend = 0;
    if ($prev && $prev['prev_compliance'] > 0 && $summary['avg_compliance'] > 0) {
        $trend = round($summary['avg_compliance'] - $prev['prev_compliance'], 1);
    }

    return [
        'success' => true,
        'week' => $week,
        'data' => [
            'managers_count' => (int)($summary['managers_count'] ?? 0),
            'total_calls' => (int)($summary['total_calls'] ?? 0),
            'avg_compliance' => round((float)($summary['avg_compliance'] ?? 0), 1),
            'compliance_trend' => $trend,
            'total_recommendations' => (int)($summary['total_recommendations'] ?? 0),
            'critical_skills_count' => count($criticalSkills),
            'critical_skills' => $criticalSkills
        ]
    ];
}

/**
 * Таблица менеджеров за неделю
 */
function getManagersTable($pdo, $org_id, $week) {
    if (!$week) {
        $stmt = $pdo->prepare("
            SELECT week_start FROM fip_weekly_aggregations
            WHERE org_id = ? ORDER BY week_start DESC LIMIT 1
        ");
        $stmt->execute([$org_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $week = $row ? $row['week_start'] : date('Y-m-d', strtotime('monday this week'));
    }

    // Текущая неделя
    $stmt = $pdo->prepare("
        SELECT
            employee_full_name,
            total_calls,
            avg_compliance_score,
            module_scores,
            critical_skills,
            recommendations_generated
        FROM fip_weekly_aggregations
        WHERE org_id = ? AND week_start = ?
        ORDER BY avg_compliance_score DESC
    ");
    $stmt->execute([$org_id, $week]);
    $managers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Предыдущая неделя для тренда
    $prevWeek = date('Y-m-d', strtotime($week . ' -7 days'));
    $stmtPrev = $pdo->prepare("
        SELECT employee_full_name, avg_compliance_score
        FROM fip_weekly_aggregations
        WHERE org_id = ? AND week_start = ?
    ");
    $stmtPrev->execute([$org_id, $prevWeek]);
    $prevData = [];
    foreach ($stmtPrev->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $prevData[$row['employee_full_name']] = (float)$row['avg_compliance_score'];
    }

    // Форматируем данные
    $result = [];
    foreach ($managers as $m) {
        $compliance = (float)$m['avg_compliance_score'];
        $prevCompliance = $prevData[$m['employee_full_name']] ?? null;

        $trend = null;
        $trendDirection = 'stable';
        if ($prevCompliance !== null) {
            $trend = round($compliance - $prevCompliance, 1);
            if ($trend > 2) $trendDirection = 'up';
            elseif ($trend < -2) $trendDirection = 'down';
        }

        $criticalSkills = json_decode($m['critical_skills'], true) ?: [];
        $moduleScores = json_decode($m['module_scores'], true) ?: [];

        $result[] = [
            'name' => $m['employee_full_name'],
            'calls' => (int)$m['total_calls'],
            'compliance' => round($compliance, 1),
            'trend' => $trend,
            'trend_direction' => $trendDirection,
            'critical_skills' => $criticalSkills,
            'critical_count' => count($criticalSkills),
            'recommendations' => (int)$m['recommendations_generated'],
            'module_scores' => $moduleScores
        ];
    }

    return [
        'success' => true,
        'week' => $week,
        'data' => $result
    ];
}

/**
 * Детальная информация по менеджеру за неделю
 */
function getManagerDetails($pdo, $org_id, $week, $name) {
    if (!$week || !$name) {
        return ['success' => false, 'error' => 'week and name required'];
    }

    $stmt = $pdo->prepare("
        SELECT
            employee_full_name,
            week_start,
            week_end,
            total_calls,
            total_errors,
            avg_compliance_score,
            module_scores,
            skill_details,
            critical_skills,
            recommendations_generated,
            processed_at
        FROM fip_weekly_aggregations
        WHERE org_id = ? AND week_start = ? AND employee_full_name = ?
    ");
    $stmt->execute([$org_id, $week, $name]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        return ['success' => false, 'error' => 'Data not found'];
    }

    // Парсим JSON
    $moduleScores = json_decode($data['module_scores'], true) ?: [];
    $skillDetails = json_decode($data['skill_details'], true) ?: [];
    $criticalSkills = json_decode($data['critical_skills'], true) ?: [];

    // Загружаем FIP-модули для названий
    $stmtModules = $pdo->query("SELECT module_id, module_name, module_icon FROM fip_modules ORDER BY sort_order");
    $modules = [];
    foreach ($stmtModules->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $modules[$m['module_id']] = [
            'name' => $m['module_name'],
            'icon' => $m['module_icon'],
            'score' => $moduleScores[$m['module_id']] ?? null
        ];
    }

    // Загружаем маппинг навыков для Moodle ссылок
    $stmtMapping = $pdo->prepare("
        SELECT skill_number, skill_name, moodle_cmid, moodle_url
        FROM fip_skill_mapping
        WHERE org_id = ? AND is_active = TRUE
    ");
    $stmtMapping->execute([$org_id]);
    $skillMapping = [];
    foreach ($stmtMapping->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $skillMapping[$s['skill_number']] = [
            'name' => $s['skill_name'],
            'moodle_cmid' => $s['moodle_cmid'],
            'moodle_url' => $s['moodle_url']
        ];
    }

    // Форматируем детали навыков
    $skillsList = [];
    foreach ($skillDetails as $skillNum => $details) {
        $mapping = $skillMapping[$skillNum] ?? null;
        $skillsList[] = [
            'skill_number' => $skillNum,
            'skill_name' => $mapping['name'] ?? $skillNum,
            'score' => (float)($details['score'] ?? 0),
            'calls' => (int)($details['calls'] ?? 0),
            'fails' => (int)($details['fails'] ?? 0),
            'is_critical' => in_array($skillNum, $criticalSkills),
            'moodle_url' => $mapping['moodle_url'] ?? null,
            'moodle_cmid' => $mapping['moodle_cmid'] ?? null
        ];
    }

    // Сортируем: критические сначала, потом по score
    usort($skillsList, function($a, $b) {
        if ($a['is_critical'] !== $b['is_critical']) {
            return $b['is_critical'] - $a['is_critical'];
        }
        return $a['score'] - $b['score'];
    });

    // Получаем историю за последние 4 недели
    $stmtHistory = $pdo->prepare("
        SELECT week_start, avg_compliance_score, total_calls
        FROM fip_weekly_aggregations
        WHERE org_id = ? AND employee_full_name = ?
        ORDER BY week_start DESC
        LIMIT 4
    ");
    $stmtHistory->execute([$org_id, $name]);
    $history = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

    return [
        'success' => true,
        'data' => [
            'name' => $data['employee_full_name'],
            'week_start' => $data['week_start'],
            'week_end' => $data['week_end'],
            'total_calls' => (int)$data['total_calls'],
            'total_errors' => (int)$data['total_errors'],
            'avg_compliance' => round((float)$data['avg_compliance_score'], 1),
            'modules' => $modules,
            'skills' => $skillsList,
            'critical_skills' => $criticalSkills,
            'recommendations' => (int)$data['recommendations_generated'],
            'history' => $history
        ]
    ];
}

/**
 * Данные для графика трендов
 */
function getTrendsChart($pdo, $org_id, $weeksCount) {
    // LIMIT requires integer binding, not string
    $weeksCount = (int)$weeksCount;
    $stmt = $pdo->prepare("
        SELECT
            week_start,
            AVG(avg_compliance_score) as avg_compliance,
            SUM(total_calls) as total_calls,
            COUNT(DISTINCT employee_full_name) as managers_count
        FROM fip_weekly_aggregations
        WHERE org_id = :org_id
        GROUP BY week_start
        ORDER BY week_start DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':org_id', $org_id, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $weeksCount, PDO::PARAM_INT);
    $stmt->execute();
    $data = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

    // Форматируем для графика
    $labels = [];
    $compliance = [];
    $calls = [];

    foreach ($data as $row) {
        $date = new DateTime($row['week_start']);
        $labels[] = $date->format('d.m');
        $compliance[] = round((float)$row['avg_compliance'], 1);
        $calls[] = (int)$row['total_calls'];
    }

    return [
        'success' => true,
        'data' => [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Compliance %',
                    'data' => $compliance,
                    'type' => 'line'
                ],
                [
                    'label' => 'Звонков',
                    'data' => $calls,
                    'type' => 'bar'
                ]
            ]
        ]
    ];
}

/**
 * Список FIP-модулей
 */
function getFipModules($pdo) {
    $stmt = $pdo->query("
        SELECT module_id, module_name, module_name_en, module_icon, module_color
        FROM fip_modules
        WHERE is_active = TRUE
        ORDER BY sort_order
    ");

    return [
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ];
}

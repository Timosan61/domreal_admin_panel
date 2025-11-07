<?php
/**
 * Тест API communication_metrics.php
 * Запускается из командной строки для проверки логики без auth
 */

// Имитация сессии
$_SESSION = [
    'user_id' => 1,
    'role' => 'admin',
    'username' => 'test_admin'
];

// Отключаем проверку аутентификации
function checkAuth($require_admin = false, $is_api = false) {
    global $_SESSION;
    return $_SESSION;
}

function getUserDepartments() {
    return [];
}

function hasAccessToDepartment($dept) {
    return true;
}

// Включаем БД
require_once 'config/database.php';

// Симулируем GET параметры
$_GET['type'] = isset($argv[1]) ? $argv[1] : 'summary';
$_GET['period'] = isset($argv[2]) ? $argv[2] : '7d';
$_GET['manager'] = isset($argv[3]) ? $argv[3] : '';
$_GET['department'] = isset($argv[4]) ? $argv[4] : '';

echo "Testing API with:\n";
echo "  Type: {$_GET['type']}\n";
echo "  Period: {$_GET['period']}\n";
echo "  Manager: " . ($_GET['manager'] ?: 'all') . "\n";
echo "  Department: " . ($_GET['department'] ?: 'all') . "\n";
echo str_repeat("-", 60) . "\n\n";

// Подключаемся к БД
$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    die("Database connection failed\n");
}

// Копируем логику из communication_metrics.php
$type = $_GET['type'];
$period = $_GET['period'];
$manager = $_GET['manager'];
$department = $_GET['department'];

$period_days = 7;
if (preg_match('/(\d+)d/', $period, $matches)) {
    $period_days = (int)$matches[1];
}

$date_from = date('Y-m-d H:i:s', strtotime("-{$period_days} days"));

// Функции расчета метрик
function calculate_interruptions($diarization_json) {
    if (empty($diarization_json)) return null;

    $data = json_decode($diarization_json, true);
    if (!$data || !isset($data['segments']) || count($data['segments']) < 2) return null;

    $segments = $data['segments'];
    $manager_speaker = $segments[0]['speaker'] ?? null;
    if (!$manager_speaker) return null;

    $client_speaker = null;
    foreach ($segments as $seg) {
        if ($seg['speaker'] !== $manager_speaker) {
            $client_speaker = $seg['speaker'];
            break;
        }
    }

    if (!$client_speaker) return null;

    $interruption_threshold = 0.5;
    $interruptions_count = 0;
    $total_transitions = 0;
    $pauses = [];

    for ($i = 1; $i < count($segments); $i++) {
        $prev_seg = $segments[$i - 1];
        $curr_seg = $segments[$i];

        if ($prev_seg['speaker'] === $client_speaker && $curr_seg['speaker'] === $manager_speaker) {
            $pause_duration = $curr_seg['start'] - $prev_seg['end'];
            $pauses[] = $pause_duration;
            $total_transitions++;

            if ($pause_duration < $interruption_threshold) {
                $interruptions_count++;
            }
        }
    }

    if ($total_transitions === 0) return null;

    $interruption_rate = ($interruptions_count / $total_transitions) * 100;
    $avg_pause = count($pauses) > 0 ? array_sum($pauses) / count($pauses) : 0.0;

    return [
        'interruptions_count' => $interruptions_count,
        'total_transitions' => $total_transitions,
        'interruption_rate' => round($interruption_rate, 2),
        'avg_pause' => round($avg_pause, 3)
    ];
}

function calculate_talk_listen_ratio($diarization_json) {
    if (empty($diarization_json)) return null;

    $data = json_decode($diarization_json, true);
    if (!$data || !isset($data['segments']) || count($data['segments']) < 2) return null;

    $segments = $data['segments'];
    $manager_speaker = $segments[0]['speaker'] ?? null;
    if (!$manager_speaker) return null;

    $client_speaker = null;
    foreach ($segments as $seg) {
        if ($seg['speaker'] !== $manager_speaker) {
            $client_speaker = $seg['speaker'];
            break;
        }
    }

    if (!$client_speaker) return null;

    $manager_duration = 0.0;
    $client_duration = 0.0;

    foreach ($segments as $seg) {
        $duration = $seg['end'] - $seg['start'];

        if ($seg['speaker'] === $manager_speaker) {
            $manager_duration += $duration;
        } elseif ($seg['speaker'] === $client_speaker) {
            $client_duration += $duration;
        }
    }

    if ($client_duration === 0.0) return null;

    $ratio = $manager_duration / $client_duration;
    $total_duration = $manager_duration + $client_duration;
    $manager_dominance = $total_duration > 0 ? ($manager_duration / $total_duration) * 100 : 0;

    return [
        'manager_duration' => round($manager_duration, 2),
        'client_duration' => round($client_duration, 2),
        'talk_to_listen_ratio' => round($ratio, 2),
        'manager_dominance' => round($manager_dominance, 2)
    ];
}

// Построение запроса
$where_clauses = ["cr.started_at_utc >= :date_from"];
$params = [':date_from' => $date_from];

if (!empty($manager)) {
    $where_clauses[] = "t.employee_full_name = :manager";
    $params[':manager'] = $manager;
}

if (!empty($department)) {
    $where_clauses[] = "t.employee_department = :department";
    $params[':department'] = $department;
}

$where_sql = implode(' AND ', $where_clauses);

// Выполнение запроса
$query = "
    SELECT
        t.employee_full_name as manager_name,
        t.employee_department as department,
        DATE(cr.started_at_utc) as call_date,
        t.diarization_json,
        t.audio_duration_sec
    FROM transcripts t
    JOIN calls_raw cr ON t.callid = cr.callid
    WHERE
        {$where_sql}
        AND t.diarization_json IS NOT NULL
        AND JSON_EXTRACT(t.diarization_json, '$.speakers_count') >= 2
        AND t.audio_duration_sec > 30
    ORDER BY cr.started_at_utc DESC
    LIMIT 100
";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$rows = $stmt->fetchAll();

echo "Found {$stmt->rowCount()} calls\n\n";

// Обработка данных
$managers = [];

foreach ($rows as $row) {
    $interruption_metrics = calculate_interruptions($row['diarization_json']);
    $talk_listen_metrics = calculate_talk_listen_ratio($row['diarization_json']);

    if (!$interruption_metrics || !$talk_listen_metrics) continue;

    $manager_key = $row['manager_name'];
    if (!isset($managers[$manager_key])) {
        $managers[$manager_key] = [
            'name' => $row['manager_name'],
            'department' => $row['department'],
            'calls_count' => 0,
            'interruption_rates' => [],
            'talk_listen_ratios' => []
        ];
    }

    $managers[$manager_key]['calls_count']++;
    $managers[$manager_key]['interruption_rates'][] = $interruption_metrics['interruption_rate'];
    $managers[$manager_key]['talk_listen_ratios'][] = $talk_listen_metrics['talk_to_listen_ratio'];
}

// Форматирование результатов
$managers_result = [];
foreach ($managers as $manager) {
    $avg_interruption = count($manager['interruption_rates']) > 0
        ? array_sum($manager['interruption_rates']) / count($manager['interruption_rates'])
        : 0;

    $avg_talk_listen = count($manager['talk_listen_ratios']) > 0
        ? array_sum($manager['talk_listen_ratios']) / count($manager['talk_listen_ratios'])
        : 0;

    $severity_interruption = 'good';
    if ($avg_interruption >= 50) {
        $severity_interruption = 'critical';
    } elseif ($avg_interruption >= 30) {
        $severity_interruption = 'warning';
    }

    $severity_talk_listen = 'good';
    if ($avg_talk_listen >= 2.5) {
        $severity_talk_listen = 'critical';
    } elseif ($avg_talk_listen >= 1.5) {
        $severity_talk_listen = 'warning';
    }

    $severity_order = ['critical' => 2, 'warning' => 1, 'good' => 0];
    $combined_severity = ($severity_order[$severity_interruption] >= $severity_order[$severity_talk_listen])
        ? $severity_interruption
        : $severity_talk_listen;

    $managers_result[] = [
        'name' => $manager['name'],
        'department' => $manager['department'],
        'calls_count' => $manager['calls_count'],
        'interruption_rate' => round($avg_interruption, 2),
        'talk_to_listen_ratio' => round($avg_talk_listen, 2),
        'severity' => $combined_severity
    ];
}

// Сортировка
usort($managers_result, function($a, $b) {
    $severity_order = ['critical' => 0, 'warning' => 1, 'good' => 2];
    $sa = $severity_order[$a['severity']] ?? 3;
    $sb = $severity_order[$b['severity']] ?? 3;
    return $sa - $sb;
});

// Вывод результатов
echo "Results (Top 20 managers):\n";
echo str_repeat("=", 120) . "\n";
printf("%-35s %-30s %8s %15s %15s %12s\n",
    "Manager", "Department", "Calls", "Interruptions", "Talk/Listen", "Severity");
echo str_repeat("=", 120) . "\n";

foreach (array_slice($managers_result, 0, 20) as $manager) {
    printf("%-35s %-30s %8d %14.2f%% %15.2f %12s\n",
        mb_substr($manager['name'], 0, 35),
        mb_substr($manager['department'] ?? 'N/A', 0, 30),
        $manager['calls_count'],
        $manager['interruption_rate'],
        $manager['talk_to_listen_ratio'],
        $manager['severity']
    );
}

echo str_repeat("=", 120) . "\n";
echo "\nJSON output:\n";
echo json_encode([
    'success' => true,
    'data' => [
        'managers' => array_slice($managers_result, 0, 10),
        'period_days' => $period_days,
        'total_managers' => count($managers_result)
    ]
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

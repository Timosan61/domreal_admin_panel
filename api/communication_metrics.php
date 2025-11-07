<?php
/**
 * API для получения метрик коммуникации менеджеров
 *
 * Endpoints:
 * - GET ?type=interruptions&period=7d&manager=&department=
 *   Возвращает данные для графика перебиваний
 *
 * - GET ?type=talk_listen&period=7d&manager=&department=
 *   Возвращает данные для графика Talk-to-Listen ratio
 *
 * - GET ?type=summary&period=7d&manager=&department=
 *   Возвращает общие метрики коммуникации
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

session_start();
require_once '../auth/session.php';
checkAuth(false, true); // Проверка авторизации для API endpoint

include_once '../config/database.php';

// Получаем параметры запроса
$type = isset($_GET['type']) ? $_GET['type'] : 'summary';
$period = isset($_GET['period']) ? $_GET['period'] : '7d';
$manager = isset($_GET['manager']) ? $_GET['manager'] : '';
$department = isset($_GET['department']) ? $_GET['department'] : '';

// Подключаемся к БД
$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// Парсим период
$period_days = 7;
if (preg_match('/(\d+)d/', $period, $matches)) {
    $period_days = (int)$matches[1];
}

// Вычисляем дату начала периода
$date_from = date('Y-m-d H:i:s', strtotime("-{$period_days} days"));

// Функция для расчета метрик перебиваний из JSON диаризации
function calculate_interruptions($diarization_json) {
    if (empty($diarization_json)) {
        return null;
    }

    $data = json_decode($diarization_json, true);
    if (!$data || !isset($data['segments']) || count($data['segments']) < 2) {
        return null;
    }

    $segments = $data['segments'];
    $speakers_count = $data['speakers_count'] ?? 2;

    // Определяем менеджера (первый спикер)
    $manager_speaker = $segments[0]['speaker'] ?? null;
    if (!$manager_speaker) {
        return null;
    }

    // Определяем клиента (второй спикер)
    $client_speaker = null;
    foreach ($segments as $seg) {
        if ($seg['speaker'] !== $manager_speaker) {
            $client_speaker = $seg['speaker'];
            break;
        }
    }

    if (!$client_speaker) {
        return null; // Монолог, не диалог
    }

    // Порог перебивания (секунды)
    $interruption_threshold = 0.5;

    $interruptions_count = 0;
    $total_transitions = 0;
    $pauses = [];

    // Анализируем переходы Клиент -> Менеджер
    for ($i = 1; $i < count($segments); $i++) {
        $prev_seg = $segments[$i - 1];
        $curr_seg = $segments[$i];

        // Переход "Клиент -> Менеджер"
        if ($prev_seg['speaker'] === $client_speaker && $curr_seg['speaker'] === $manager_speaker) {
            $pause_duration = $curr_seg['start'] - $prev_seg['end'];
            $pauses[] = $pause_duration;
            $total_transitions++;

            if ($pause_duration < $interruption_threshold) {
                $interruptions_count++;
            }
        }
    }

    if ($total_transitions === 0) {
        return null;
    }

    $interruption_rate = ($interruptions_count / $total_transitions) * 100;
    $avg_pause = count($pauses) > 0 ? array_sum($pauses) / count($pauses) : 0.0;

    return [
        'interruptions_count' => $interruptions_count,
        'total_transitions' => $total_transitions,
        'interruption_rate' => round($interruption_rate, 2),
        'avg_pause' => round($avg_pause, 3)
    ];
}

// Функция для расчета Talk-to-Listen ratio
function calculate_talk_listen_ratio($diarization_json) {
    if (empty($diarization_json)) {
        return null;
    }

    $data = json_decode($diarization_json, true);
    if (!$data || !isset($data['segments']) || count($data['segments']) < 2) {
        return null;
    }

    $segments = $data['segments'];

    // Определяем менеджера (первый спикер)
    $manager_speaker = $segments[0]['speaker'] ?? null;
    if (!$manager_speaker) {
        return null;
    }

    // Определяем клиента (второй спикер)
    $client_speaker = null;
    foreach ($segments as $seg) {
        if ($seg['speaker'] !== $manager_speaker) {
            $client_speaker = $seg['speaker'];
            break;
        }
    }

    if (!$client_speaker) {
        return null; // Монолог
    }

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

    if ($client_duration === 0.0) {
        return null;
    }

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

// Построение SQL запроса с фильтрами
$where_clauses = ["cr.started_at_utc >= :date_from"];
$params = [':date_from' => $date_from];

// Фильтр по менеджеру
if (!empty($manager)) {
    $where_clauses[] = "t.employee_full_name = :manager";
    $params[':manager'] = $manager;
}

// Фильтр по отделу
if (!empty($department)) {
    $where_clauses[] = "t.employee_department = :department";
    $params[':department'] = $department;
}

// Проверка доступа к отделам (для не-админов)
if ($_SESSION['role'] !== 'admin') {
    $user_departments = getUserDepartments();
    if (empty($user_departments)) {
        http_response_code(403);
        echo json_encode(["error" => "У вас нет доступа к отделам"], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $dept_placeholders = [];
    foreach ($user_departments as $idx => $dept) {
        $param_name = ":dept_{$idx}";
        $dept_placeholders[] = $param_name;
        $params[$param_name] = $dept;
    }
    $where_clauses[] = "t.employee_department IN (" . implode(',', $dept_placeholders) . ")";
}

$where_sql = implode(' AND ', $where_clauses);

// Обработка разных типов запросов
try {
    switch ($type) {
        case 'interruptions':
            // Получаем данные по перебиваниям
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
            ";

            $stmt = $db->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll();

            // Группировка по менеджерам
            $managers = [];
            $timeline = [];

            foreach ($rows as $row) {
                $metrics = calculate_interruptions($row['diarization_json']);
                if (!$metrics) continue;

                $manager_key = $row['manager_name'];
                if (!isset($managers[$manager_key])) {
                    $managers[$manager_key] = [
                        'name' => $row['manager_name'],
                        'department' => $row['department'],
                        'calls_count' => 0,
                        'total_interruptions' => 0,
                        'total_transitions' => 0,
                        'interruption_rates' => []
                    ];
                }

                $managers[$manager_key]['calls_count']++;
                $managers[$manager_key]['total_interruptions'] += $metrics['interruptions_count'];
                $managers[$manager_key]['total_transitions'] += $metrics['total_transitions'];
                $managers[$manager_key]['interruption_rates'][] = $metrics['interruption_rate'];

                // Группировка по датам для timeline
                $date_key = $row['call_date'];
                if (!isset($timeline[$date_key])) {
                    $timeline[$date_key] = [
                        'date' => $date_key,
                        'rates' => []
                    ];
                }
                $timeline[$date_key]['rates'][] = $metrics['interruption_rate'];
            }

            // Вычисляем средние значения и severity
            $managers_result = [];
            foreach ($managers as $manager) {
                $avg_rate = count($manager['interruption_rates']) > 0
                    ? array_sum($manager['interruption_rates']) / count($manager['interruption_rates'])
                    : 0;

                $severity = 'good';
                if ($avg_rate >= 50) {
                    $severity = 'critical';
                } elseif ($avg_rate >= 30) {
                    $severity = 'warning';
                }

                $managers_result[] = [
                    'name' => $manager['name'],
                    'department' => $manager['department'],
                    'calls_count' => $manager['calls_count'],
                    'interruption_rate' => round($avg_rate, 2),
                    'total_interruptions' => $manager['total_interruptions'],
                    'total_transitions' => $manager['total_transitions'],
                    'severity' => $severity
                ];
            }

            // Сортировка по критичности
            usort($managers_result, function($a, $b) {
                $severity_order = ['critical' => 0, 'warning' => 1, 'good' => 2];
                $sa = $severity_order[$a['severity']] ?? 3;
                $sb = $severity_order[$b['severity']] ?? 3;

                if ($sa !== $sb) {
                    return $sa - $sb;
                }
                return $b['interruption_rate'] - $a['interruption_rate'];
            });

            // Timeline агрегация
            $timeline_result = [];
            foreach ($timeline as $date_data) {
                $avg_rate = count($date_data['rates']) > 0
                    ? array_sum($date_data['rates']) / count($date_data['rates'])
                    : 0;

                $timeline_result[] = [
                    'date' => $date_data['date'],
                    'avg_interruption_rate' => round($avg_rate, 2),
                    'calls_count' => count($date_data['rates'])
                ];
            }

            // Сортировка timeline по дате
            usort($timeline_result, function($a, $b) {
                return strcmp($a['date'], $b['date']);
            });

            echo json_encode([
                'success' => true,
                'data' => [
                    'managers' => $managers_result,
                    'timeline' => $timeline_result
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'talk_listen':
            // Получаем данные по Talk-to-Listen ratio
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
            ";

            $stmt = $db->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll();

            // Группировка по менеджерам
            $managers = [];
            $timeline = [];

            foreach ($rows as $row) {
                $metrics = calculate_talk_listen_ratio($row['diarization_json']);
                if (!$metrics) continue;

                $manager_key = $row['manager_name'];
                if (!isset($managers[$manager_key])) {
                    $managers[$manager_key] = [
                        'name' => $row['manager_name'],
                        'department' => $row['department'],
                        'calls_count' => 0,
                        'ratios' => [],
                        'dominances' => []
                    ];
                }

                $managers[$manager_key]['calls_count']++;
                $managers[$manager_key]['ratios'][] = $metrics['talk_to_listen_ratio'];
                $managers[$manager_key]['dominances'][] = $metrics['manager_dominance'];

                // Timeline
                $date_key = $row['call_date'];
                if (!isset($timeline[$date_key])) {
                    $timeline[$date_key] = [
                        'date' => $date_key,
                        'ratios' => []
                    ];
                }
                $timeline[$date_key]['ratios'][] = $metrics['talk_to_listen_ratio'];
            }

            // Вычисляем средние значения и severity
            $managers_result = [];
            foreach ($managers as $manager) {
                $avg_ratio = count($manager['ratios']) > 0
                    ? array_sum($manager['ratios']) / count($manager['ratios'])
                    : 0;

                $avg_dominance = count($manager['dominances']) > 0
                    ? array_sum($manager['dominances']) / count($manager['dominances'])
                    : 0;

                $severity = 'good';
                if ($avg_ratio >= 2.5) {
                    $severity = 'critical'; // Менеджер говорит в 2.5+ раза больше клиента
                } elseif ($avg_ratio >= 1.5) {
                    $severity = 'warning'; // Менеджер говорит в 1.5+ раза больше
                }

                $managers_result[] = [
                    'name' => $manager['name'],
                    'department' => $manager['department'],
                    'calls_count' => $manager['calls_count'],
                    'talk_to_listen_ratio' => round($avg_ratio, 2),
                    'manager_dominance' => round($avg_dominance, 2),
                    'severity' => $severity
                ];
            }

            // Сортировка по критичности
            usort($managers_result, function($a, $b) {
                $severity_order = ['critical' => 0, 'warning' => 1, 'good' => 2];
                $sa = $severity_order[$a['severity']] ?? 3;
                $sb = $severity_order[$b['severity']] ?? 3;

                if ($sa !== $sb) {
                    return $sa - $sb;
                }
                return $b['talk_to_listen_ratio'] - $a['talk_to_listen_ratio'];
            });

            // Timeline агрегация
            $timeline_result = [];
            foreach ($timeline as $date_data) {
                $avg_ratio = count($date_data['ratios']) > 0
                    ? array_sum($date_data['ratios']) / count($date_data['ratios'])
                    : 0;

                $timeline_result[] = [
                    'date' => $date_data['date'],
                    'avg_talk_to_listen_ratio' => round($avg_ratio, 2),
                    'calls_count' => count($date_data['ratios'])
                ];
            }

            // Сортировка timeline по дате
            usort($timeline_result, function($a, $b) {
                return strcmp($a['date'], $b['date']);
            });

            echo json_encode([
                'success' => true,
                'data' => [
                    'managers' => $managers_result,
                    'timeline' => $timeline_result
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'summary':
            // Общие метрики (оба типа одновременно)
            $query = "
                SELECT
                    t.employee_full_name as manager_name,
                    t.employee_department as department,
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
            ";

            $stmt = $db->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll();

            // Группировка по менеджерам
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

            // Вычисляем средние значения
            $managers_result = [];
            foreach ($managers as $manager) {
                $avg_interruption = count($manager['interruption_rates']) > 0
                    ? array_sum($manager['interruption_rates']) / count($manager['interruption_rates'])
                    : 0;

                $avg_talk_listen = count($manager['talk_listen_ratios']) > 0
                    ? array_sum($manager['talk_listen_ratios']) / count($manager['talk_listen_ratios'])
                    : 0;

                // Комбинированная severity (худший из двух)
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

            // Сортировка по критичности
            usort($managers_result, function($a, $b) {
                $severity_order = ['critical' => 0, 'warning' => 1, 'good' => 2];
                $sa = $severity_order[$a['severity']] ?? 3;
                $sb = $severity_order[$b['severity']] ?? 3;

                return $sa - $sb;
            });

            echo json_encode([
                'success' => true,
                'data' => [
                    'managers' => $managers_result,
                    'period_days' => $period_days
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'error' => "Invalid type parameter. Allowed: interruptions, talk_listen, summary"
            ]);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

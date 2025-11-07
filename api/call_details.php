<?php
/**
 * API для получения детальной информации о звонке
 * GET /api/call_details.php?callid=xxx
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

// Получаем callid
$callid = isset($_GET['callid']) ? $_GET['callid'] : '';

if (empty($callid)) {
    http_response_code(400);
    echo json_encode(["error" => "Parameter 'callid' is required"], JSON_UNESCAPED_UNICODE);
    exit();
}

// Подключаемся к БД
$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// Получаем полную информацию о звонке (включая is_first_call)
$query = "SELECT
    cr.*,
    t.text as transcript_text,
    t.diarization_json,
    t.audio_duration_sec,
    t.confidence_avg,
    t.processing_time_ms,
    ar.call_type,
    ar.summary_text,
    ar.is_successful,
    ar.call_result,
    ar.success_reason,
    COALESCE(ar.script_compliance_score_v4, ar.script_compliance_score) as script_compliance_score,
    ar.script_check_location,
    ar.script_check_payment,
    ar.script_check_goal,
    ar.script_check_is_local,
    ar.script_check_budget,
    ar.script_check_details,
    ar.script_version,
    ar.script_compliance_score_v4,
    ar.script_check_v4_interest,
    ar.script_check_v4_location,
    ar.script_check_v4_payment,
    ar.script_check_v4_goal,
    ar.script_check_v4_history,
    ar.script_check_v4_action,
    ar.script_check_details_v4,
    ar.script_check_repeat_greeting,
    ar.script_check_repeat_actions,
    ar.script_check_repeat_next_step,
    ar.script_check_repeat_objections,
    ar.script_check_repeat_informal,
    ar.raw_response as llm_analysis,
    ar.crm_funnel_id,
    ar.crm_funnel_name,
    ar.crm_step_id,
    ar.crm_step_name,
    ar.crm_requisition_id,
    ar.crm_last_sync,
    aj.local_path as audio_path,
    aj.status as audio_status,
    aj.error_text as audio_error,
    aj.file_size_bytes,
    aj.file_format,
    ce.aggregate_summary,
    ce.total_calls_count,
    ce.successful_calls_count,
    ce.last_call_date
FROM calls_raw cr
LEFT JOIN transcripts t ON cr.callid = t.callid
LEFT JOIN analysis_results ar ON cr.callid = ar.callid
LEFT JOIN audio_jobs aj ON cr.callid = aj.callid
LEFT JOIN client_enrichment ce ON CONCAT('+7', cr.client_phone) = ce.client_phone
WHERE cr.callid = :callid
LIMIT 1";

// Проверяем наличие вызова и доступ к отделу
if ($_SESSION['role'] !== 'admin') {
    // Для обычных пользователей проверяем доступ к отделу
    $user_departments = getUserDepartments();
    if (empty($user_departments)) {
        http_response_code(403);
        echo json_encode(["error" => "У вас нет доступа к отделам"], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

$stmt = $db->prepare($query);
$stmt->bindParam(':callid', $callid);
$stmt->execute();

$call = $stmt->fetch();

if (!$call) {
    http_response_code(404);
    echo json_encode(["error" => "Call not found"], JSON_UNESCAPED_UNICODE);
    exit();
}

// Проверяем доступ к отделу звонка (для не-админов)
if ($_SESSION['role'] !== 'admin') {
    if (!hasAccessToDepartment($call['department'])) {
        http_response_code(403);
        echo json_encode(["error" => "У вас нет доступа к этому отделу"], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// Парсим JSON поля
if (!empty($call['diarization_json'])) {
    $call['diarization'] = json_decode($call['diarization_json'], true);
    unset($call['diarization_json']);
}

if (!empty($call['payload_json'])) {
    $call['payload'] = json_decode($call['payload_json'], true);
    unset($call['payload_json']);
}

// Парсим metrics_json и формируем чеклист
$checklist = [];
$metrics = [];

// Определяем версию скрипта и формируем соответствующий чеклист
// ВАЖНО: С версии v4 скрипт работает для ВСЕХ типов звонков (first_call и repeat_call)
if ($call['script_version'] === 'v4') {
    // Проверяем какие поля заполнены - first_call (6 пунктов) или repeat_call (3 пункта)
    $has_first_call_checks = (
        $call['script_check_v4_interest'] !== null ||
        $call['script_check_v4_location'] !== null ||
        $call['script_check_v4_payment'] !== null ||
        $call['script_check_v4_goal'] !== null ||
        $call['script_check_v4_history'] !== null ||
        $call['script_check_v4_action'] !== null
    );

    $has_repeat_call_checks = (
        $call['script_check_repeat_greeting'] !== null ||
        $call['script_check_repeat_actions'] !== null ||
        $call['script_check_repeat_next_step'] !== null ||
        $call['script_check_repeat_objections'] !== null ||
        $call['script_check_repeat_informal'] !== null
    );

    if ($has_first_call_checks) {
        // Новый чеклист v4 для первого звонка (6 пунктов)
        $checklist = [
            [
                'id' => 'v4_interest',
                'label' => '5.1. Установка контекста и интерес',
                'checked' => boolval($call['script_check_v4_interest']),
                'description' => 'Менеджер выяснил стадию заинтересованности клиента (активный поиск / изучение рынка)'
            ],
            [
                'id' => 'v4_location',
                'label' => '5.2. В Сочи? Локация и срочность',
                'checked' => boolval($call['script_check_v4_location']),
                'description' => 'Менеджер уточнил местоположение клиента и возможность очного показа'
            ],
            [
                'id' => 'v4_payment',
                'label' => '5.3. Финансовые условия',
                'checked' => boolval($call['script_check_v4_payment']),
                'description' => 'Менеджер выяснил способ оплаты (наличные/ипотека/рассрочка)'
            ],
            [
                'id' => 'v4_goal',
                'label' => '5.4. Цель покупки',
                'checked' => boolval($call['script_check_v4_goal']),
                'description' => 'Менеджер выяснил цель покупки (для себя/инвестиция/сдача в аренду)'
            ],
            [
                'id' => 'v4_history',
                'label' => '5.5. История просмотров',
                'checked' => boolval($call['script_check_v4_history']),
                'description' => 'Менеджер выяснил предыдущий опыт поиска недвижимости и причины отказа'
            ],
            [
                'id' => 'v4_action',
                'label' => '5.6. Немедленное действие',
                'checked' => boolval($call['script_check_v4_action']),
                'description' => 'Менеджер договорился о конкретном следующем шаге (отправка КП / созвон / онлайн-показ / время встречи)'
            ]
        ];

        // Используем score v4
        if ($call['script_compliance_score_v4'] !== null) {
            $call['script_compliance_score'] = $call['script_compliance_score_v4'];
        }
    } elseif ($has_repeat_call_checks) {
        // Чеклист v4 для повторного звонка (5 пунктов: 4.1-4.5)
        $checklist = [
            [
                'id' => 'repeat_greeting',
                'label' => '4.1. Представился и напомнил контекст',
                'checked' => boolval($call['script_check_repeat_greeting']),
                'description' => 'Менеджер назвал себя и напомнил о прошлом общении'
            ],
            [
                'id' => 'repeat_actions',
                'label' => '4.2. Предложил конкретные действия',
                'checked' => boolval($call['script_check_repeat_actions']),
                'description' => 'Менеджер предложил четкие варианты развития ситуации (показ, подборку, обсуждение условий)'
            ],
            [
                'id' => 'repeat_next_step',
                'label' => '4.3. Зафиксировал следующий шаг',
                'checked' => boolval($call['script_check_repeat_next_step']),
                'description' => 'Менеджер договорился о конкретном действии с датой/временем (показ, перезвон, отправка документов)'
            ],
            [
                'id' => 'repeat_objections',
                'label' => '4.4. Обработал возражения',
                'checked' => boolval($call['script_check_repeat_objections']),
                'description' => 'Менеджер выявил и проработал сомнения клиента, объяснил преимущества'
            ],
            [
                'id' => 'repeat_informal',
                'label' => '4.5. Использовал неформальный диалог',
                'checked' => boolval($call['script_check_repeat_informal']),
                'description' => 'Менеджер общался дружелюбно и естественно, без излишней официальности'
            ]
        ];

        // Используем score v4
        if ($call['script_compliance_score_v4'] !== null) {
            $call['script_compliance_score'] = $call['script_compliance_score_v4'];
        }
    }
} elseif ($call['call_type'] === 'first_call') {
    // Старый чеклист v3 для first_call (5 пунктов) - для обратной совместимости
    if (!empty($call['metrics_json'])) {
        $metrics = json_decode($call['metrics_json'], true);

        // Формируем чеклист на основе metrics_json
        if (isset($metrics['script_checks'])) {
            $checks = $metrics['script_checks'];

            $checklist = [
                [
                    'id' => 'location',
                    'label' => 'Местоположение клиента выяснено',
                    'checked' => isset($checks['location']) ? boolval($checks['location']) : false,
                    'description' => 'Менеджер уточнил, где именно клиент ищет недвижимость'
                ],
                [
                    'id' => 'payment',
                    'label' => 'Форма оплаты выяснена',
                    'checked' => isset($checks['payment']) ? boolval($checks['payment']) : false,
                    'description' => 'Уточнена форма оплаты (наличные, ипотека, рассрочка)'
                ],
                [
                    'id' => 'goal',
                    'label' => 'Цель покупки выяснена',
                    'checked' => isset($checks['goal']) ? boolval($checks['goal']) : false,
                    'description' => 'Выяснена цель покупки (инвестиция, для себя, для сдачи)'
                ],
                [
                    'id' => 'is_local',
                    'label' => 'Местный ли клиент',
                    'checked' => isset($checks['is_local']) ? boolval($checks['is_local']) : false,
                    'description' => 'Определено, находится ли клиент в городе или регионе'
                ],
                [
                    'id' => 'budget',
                    'label' => 'Бюджет выяснен',
                    'checked' => isset($checks['budget']) ? boolval($checks['budget']) : false,
                    'description' => 'Уточнен бюджет клиента на покупку'
                ]
            ];
        }
    } else {
        // Fallback на прямые поля v3 из БД
        $checklist = [
            [
                'id' => 'location',
                'label' => 'Местоположение клиента выяснено',
                'checked' => boolval($call['script_check_location']),
                'description' => 'Менеджер уточнил, где именно клиент ищет недвижимость'
            ],
            [
                'id' => 'payment',
                'label' => 'Форма оплаты выяснена',
                'checked' => boolval($call['script_check_payment']),
                'description' => 'Уточнена форма оплаты (наличные, ипотека, рассрочка)'
            ],
            [
                'id' => 'goal',
                'label' => 'Цель покупки выяснена',
                'checked' => boolval($call['script_check_goal']),
                'description' => 'Выяснена цель покупки (инвестиция, для себя, для сдачи)'
            ],
            [
                'id' => 'is_local',
                'label' => 'Местный ли клиент',
                'checked' => boolval($call['script_check_is_local']),
                'description' => 'Определено, находится ли клиент в городе или регионе'
            ],
            [
                'id' => 'budget',
                'label' => 'Бюджет выяснен',
                'checked' => boolval($call['script_check_budget']),
                'description' => 'Уточнен бюджет клиента на покупку'
            ]
        ];
    }
}

$call['checklist'] = $checklist;
$call['metrics'] = $metrics;

// Поля уже есть в запросе из базы данных - ничего дополнительно не нужно

// Формируем ответ
$response = [
    "success" => true,
    "data" => $call
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

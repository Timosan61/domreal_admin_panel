<?php
/**
 * API для управления тегами звонков
 * POST /api/tags.php - Добавить/обновить теги для звонков
 * DELETE /api/tags.php - Удалить теги
 * GET /api/tags.php?callid=XXX - Получить тег звонка
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

session_start();
require_once '../auth/session.php';
checkAuth(false, true); // Проверка авторизации для API endpoint

include_once '../config/database.php';

// Получаем подключение к БД
$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    echo json_encode([
        "success" => false,
        "error" => "Database connection failed"
    ]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'POST':
            // Добавить/обновить теги для выбранных звонков
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['callids']) || !is_array($data['callids'])) {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "error" => "callids array is required"
                ]);
                exit();
            }

            if (!isset($data['tag_type']) || !in_array($data['tag_type'], ['good', 'bad', 'question', 'problem'])) {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "error" => "Valid tag_type is required (good, bad, question, problem)"
                ]);
                exit();
            }

            $callids = $data['callids'];
            $tag_type = $data['tag_type'];
            $note = $data['note'] ?? null;
            $username = $_SESSION['username'];

            // Проверка прав доступа для РОПов (role='user')
            $user_role = $_SESSION['role'];
            $user_departments = getUserDepartments();

            $errors = [];

            // Если не админ - проверяем доступ к каждому звонку
            if ($user_role !== 'admin' && !empty($callids)) {
                // Получаем информацию об отделах для всех callids
                $placeholders = implode(',', array_fill(0, count($callids), '?'));
                $check_query = "SELECT callid, department FROM calls_raw WHERE callid IN ($placeholders)";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->execute($callids);
                $calls_info = $check_stmt->fetchAll(PDO::FETCH_ASSOC);

                // Создаем карту callid => department
                $call_departments = [];
                foreach ($calls_info as $call) {
                    $call_departments[$call['callid']] = $call['department'];
                }

                // Фильтруем callids - оставляем только доступные
                $allowed_callids = [];
                foreach ($callids as $callid) {
                    if (!isset($call_departments[$callid])) {
                        $errors[] = [
                            'callid' => $callid,
                            'error' => 'Звонок не найден'
                        ];
                        continue;
                    }

                    $dept = $call_departments[$callid];
                    if (in_array($dept, $user_departments)) {
                        $allowed_callids[] = $callid;
                    } else {
                        $errors[] = [
                            'callid' => $callid,
                            'error' => 'Доступ запрещён: звонок из отдела "' . $dept . '"'
                        ];
                    }
                }

                $callids = $allowed_callids;
            }

            // SQL для upsert (INSERT ... ON DUPLICATE KEY UPDATE)
            $stmt = $db->prepare("
                INSERT INTO call_tags (callid, tag_type, note, tagged_by_user, tagged_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    tag_type = VALUES(tag_type),
                    note = VALUES(note),
                    tagged_by_user = VALUES(tagged_by_user),
                    updated_at = NOW()
            ");

            $success_count = 0;

            foreach ($callids as $callid) {
                try {
                    $stmt->execute([$callid, $tag_type, $note, $username]);
                    $success_count++;
                } catch (PDOException $e) {
                    $errors[] = [
                        'callid' => $callid,
                        'error' => $e->getMessage()
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'count' => $success_count,
                'total' => count($callids),
                'errors' => $errors
            ]);
            break;

        case 'DELETE':
            // Удалить теги для выбранных звонков
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['callids']) || !is_array($data['callids']) || empty($data['callids'])) {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "error" => "callids array is required"
                ]);
                exit();
            }

            $callids = $data['callids'];

            // Проверка прав доступа для РОПов (role='user')
            $user_role = $_SESSION['role'];
            $user_departments = getUserDepartments();

            // Если не админ - проверяем доступ к каждому звонку
            if ($user_role !== 'admin' && !empty($callids)) {
                // Получаем информацию об отделах для всех callids
                $placeholders_check = implode(',', array_fill(0, count($callids), '?'));
                $check_query = "SELECT callid, department FROM calls_raw WHERE callid IN ($placeholders_check)";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->execute($callids);
                $calls_info = $check_stmt->fetchAll(PDO::FETCH_ASSOC);

                // Создаем карту callid => department
                $call_departments = [];
                foreach ($calls_info as $call) {
                    $call_departments[$call['callid']] = $call['department'];
                }

                // Фильтруем callids - оставляем только доступные
                $allowed_callids = [];
                foreach ($callids as $callid) {
                    if (isset($call_departments[$callid])) {
                        $dept = $call_departments[$callid];
                        if (in_array($dept, $user_departments)) {
                            $allowed_callids[] = $callid;
                        }
                    }
                }

                $callids = $allowed_callids;
            }

            // Если после фильтрации не осталось callids - ничего не удаляем
            if (empty($callids)) {
                echo json_encode([
                    'success' => true,
                    'deleted' => 0,
                    'message' => 'Нет доступных для удаления тегов'
                ]);
                break;
            }

            $placeholders = implode(',', array_fill(0, count($callids), '?'));

            $stmt = $db->prepare("DELETE FROM call_tags WHERE callid IN ($placeholders)");
            $stmt->execute($callids);

            $deleted_count = $stmt->rowCount();

            echo json_encode([
                'success' => true,
                'deleted' => $deleted_count
            ]);
            break;

        case 'GET':
            // Получить тег для конкретного звонка
            if (isset($_GET['callid'])) {
                $callid = $_GET['callid'];

                $stmt = $db->prepare("
                    SELECT
                        ct.*,
                        cr.employee_name,
                        cr.client_phone,
                        cr.started_at_utc
                    FROM call_tags ct
                    LEFT JOIN calls_raw cr ON ct.callid = cr.callid
                    WHERE ct.callid = ?
                ");
                $stmt->execute([$callid]);
                $tag = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($tag) {
                    echo json_encode([
                        'success' => true,
                        'tag' => $tag
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'tag' => null
                    ]);
                }
            } else {
                // Получить список всех тегированных звонков
                $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                $per_page = isset($_GET['per_page']) ? min(100, max(10, intval($_GET['per_page']))) : 20;
                $offset = ($page - 1) * $per_page;

                // Фильтры
                $tag_types = isset($_GET['tag_types']) ? $_GET['tag_types'] : '';

                $where_clauses = [];
                $params = [];

                // Фильтр по типам тегов
                if (!empty($tag_types)) {
                    $types_array = explode(',', $tag_types);
                    $placeholders = implode(',', array_fill(0, count($types_array), '?'));
                    $where_clauses[] = "ct.tag_type IN ($placeholders)";
                    $params = array_merge($params, $types_array);
                }

                // Фильтр по отделам пользователя (если не админ)
                $user_role = $_SESSION['role'];
                if ($user_role !== 'admin') {
                    $user_departments = getUserDepartments();

                    if (!empty($user_departments)) {
                        $dept_placeholders = [];
                        foreach ($user_departments as $index => $dept) {
                            $param_name = ':user_dept_' . $index;
                            $dept_placeholders[] = $param_name;
                            $params[$param_name] = $dept;
                        }
                        $where_clauses[] = "cr.department IN (" . implode(', ', $dept_placeholders) . ")";
                    }
                }

                $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

                // Подсчет общего количества (с JOIN для фильтрации по отделам)
                $count_query = "SELECT COUNT(*) as total
                                FROM call_tags ct
                                LEFT JOIN calls_raw cr ON ct.callid = cr.callid
                                $where_sql";
                $count_stmt = $db->prepare($count_query);
                $count_stmt->execute($params);
                $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

                // Получение данных (используем числа напрямую для LIMIT и OFFSET)
                $query = "
                    SELECT
                        ct.*,
                        cr.employee_name,
                        cr.department,
                        cr.client_phone,
                        cr.started_at_utc,
                        cr.duration_sec,
                        cr.direction,
                        ar.call_result,
                        ar.summary_text
                    FROM call_tags ct
                    LEFT JOIN calls_raw cr ON ct.callid = cr.callid
                    LEFT JOIN analysis_results ar ON ct.callid = ar.callid
                    $where_sql
                    ORDER BY ct.tagged_at DESC
                    LIMIT $per_page OFFSET $offset
                ";

                // Не добавляем LIMIT и OFFSET в $params, так как они уже в запросе

                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'data' => $tags,
                    'pagination' => [
                        'page' => $page,
                        'per_page' => $per_page,
                        'total' => (int)$total,
                        'total_pages' => ceil($total / $per_page)
                    ]
                ]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode([
                "success" => false,
                "error" => "Method not allowed"
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}

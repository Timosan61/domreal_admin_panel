<?php
/**
 * Training Approval Workflow API
 *
 * Workflow:
 * 1. LLM generates recommendation → status='pending', approval_status='pending_approval'
 * 2. ROP reviews and approves → approval_status='approved', creates Bitrix24 task
 * 3. Manager completes training in Moodle → Moodle webhook updates moodle_quiz_passed=TRUE
 * 4. Bitrix24 task auto-completed when quiz passed
 *
 * Endpoints:
 * GET  ?action=pending          - Get recommendations pending ROP approval
 * GET  ?action=approved         - Get approved recommendations in progress
 * GET  ?action=completed        - Get completed recommendations
 * GET  ?action=stats            - Get workflow statistics
 * POST ?action=approve          - Approve recommendation, create Bitrix24 task
 * POST ?action=reject           - Reject recommendation
 * POST ?action=bulk_approve     - Approve multiple recommendations
 * POST ?action=moodle_webhook   - Webhook from Moodle when quiz completed (no auth)
 */

header('Content-Type: application/json');

// Moodle webhook endpoint doesn't require auth
$action = $_GET['action'] ?? $_POST['action'] ?? 'pending';
$isMoodleWebhook = ($action === 'moodle_webhook');

if (!$isMoodleWebhook) {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../auth/session.php';
    checkAuth(false, true);
} else {
    require_once __DIR__ . '/../../config/database.php';
}

$database = new Database();
$pdo = $database->getConnection();

// For authenticated requests
$org_id = $_SESSION['org_id'] ?? 'org-legacy';
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'System';

try {
    switch ($action) {
        case 'pending':
            echo json_encode(getPendingApprovals($pdo, $org_id));
            break;

        case 'approved':
            echo json_encode(getApprovedInProgress($pdo, $org_id));
            break;

        case 'completed':
            echo json_encode(getCompletedRecommendations($pdo, $org_id));
            break;

        case 'stats':
            echo json_encode(getWorkflowStats($pdo, $org_id));
            break;

        case 'approve':
            $data = json_decode(file_get_contents('php://input'), true);
            echo json_encode(approveRecommendation($pdo, $org_id, $user_id, $user_name, $data));
            break;

        case 'reject':
            $data = json_decode(file_get_contents('php://input'), true);
            echo json_encode(rejectRecommendation($pdo, $org_id, $user_id, $user_name, $data));
            break;

        case 'bulk_approve':
            $data = json_decode(file_get_contents('php://input'), true);
            echo json_encode(bulkApprove($pdo, $org_id, $user_id, $user_name, $data));
            break;

        case 'moodle_webhook':
            // This endpoint is called by Moodle when quiz is completed
            $data = json_decode(file_get_contents('php://input'), true);
            echo json_encode(handleMoodleWebhook($pdo, $data));
            break;

        case 'managers':
            echo json_encode(getManagersWithBitrixMapping($pdo, $org_id));
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Approval workflow error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Get recommendations pending ROP approval
 */
function getPendingApprovals($pdo, $org_id) {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    $stmt = $pdo->prepare("
        SELECT
            tr.recommendation_id,
            tr.employee_full_name,
            tr.skill_code,
            COALESCE(tr.skill_name, aq.question_text, tr.skill_code) as skill_name,
            tr.current_score,
            tr.target_score,
            tr.priority,
            tr.trigger_type,
            tr.trigger_details,
            tr.created_at,
            TIMESTAMPDIFF(HOUR, tr.created_at, NOW()) as hours_pending,
            -- Bitrix mapping
            mbm.bitrix_user_id,
            mbm.bitrix_user_name,
            mbm.bitrix_supervisor_id
        FROM training_recommendations tr
        LEFT JOIN analysis_questions aq ON tr.skill_code = aq.question_code
        LEFT JOIN manager_bitrix_mapping mbm
            ON tr.employee_full_name = mbm.employee_full_name
            AND tr.org_id = mbm.org_id
            AND mbm.is_active = 1
        WHERE tr.org_id = ?
          AND tr.approval_status = 'pending_approval'
          AND tr.status NOT IN ('completed', 'expired')
        ORDER BY
            FIELD(tr.priority, 'critical', 'high', 'medium', 'low'),
            tr.created_at ASC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute([$org_id]);
    $recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM training_recommendations
        WHERE org_id = ?
          AND approval_status = 'pending_approval'
          AND status NOT IN ('completed', 'expired')
    ");
    $countStmt->execute([$org_id]);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Format data
    foreach ($recommendations as &$rec) {
        $rec['current_score'] = round((float)$rec['current_score'], 1);
        $rec['target_score'] = round((float)$rec['target_score'], 1);
        $rec['hours_pending'] = (int)$rec['hours_pending'];
        $rec['has_bitrix_mapping'] = !empty($rec['bitrix_user_id']);
        if ($rec['trigger_details']) {
            $rec['trigger_details'] = json_decode($rec['trigger_details'], true);
        }
    }

    return [
        'success' => true,
        'data' => $recommendations,
        'total' => (int)$total
    ];
}

/**
 * Get approved recommendations in progress
 */
function getApprovedInProgress($pdo, $org_id) {
    $stmt = $pdo->prepare("
        SELECT
            tr.recommendation_id,
            tr.employee_full_name,
            tr.skill_code,
            COALESCE(tr.skill_name, aq.question_text, tr.skill_code) as skill_name,
            tr.current_score,
            tr.priority,
            tr.approved_at,
            tr.approved_by_name,
            tr.approval_comment,
            -- Bitrix task
            tr.crm_task_id AS bitrix_task_id,
            tr.bitrix_task_status,
            tr.crm_task_created_at AS bitrix_task_created_at,
            -- Moodle completion
            tr.moodle_quiz_passed,
            tr.moodle_completion_at,
            tr.moodle_quiz_score,
            -- Time tracking
            TIMESTAMPDIFF(DAY, tr.approved_at, NOW()) as days_since_approval
        FROM training_recommendations tr
        LEFT JOIN analysis_questions aq ON tr.skill_code = aq.question_code
        WHERE tr.org_id = ?
          AND tr.approval_status = 'approved'
          AND tr.status NOT IN ('completed', 'expired')
        ORDER BY tr.approved_at DESC
        LIMIT 100
    ");
    $stmt->execute([$org_id]);
    $recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($recommendations as &$rec) {
        $rec['current_score'] = round((float)$rec['current_score'], 1);
        $rec['moodle_quiz_passed'] = (bool)$rec['moodle_quiz_passed'];
        $rec['moodle_quiz_score'] = $rec['moodle_quiz_score'] ? round((float)$rec['moodle_quiz_score'], 1) : null;
        $rec['days_since_approval'] = (int)$rec['days_since_approval'];
    }

    return [
        'success' => true,
        'data' => $recommendations
    ];
}

/**
 * Get completed recommendations (for history)
 */
function getCompletedRecommendations($pdo, $org_id) {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    $stmt = $pdo->prepare("
        SELECT
            tr.recommendation_id,
            tr.employee_full_name,
            tr.skill_code,
            COALESCE(tr.skill_name, aq.question_text, tr.skill_code) as skill_name,
            tr.current_score,
            tr.completion_score,
            tr.priority,
            tr.approved_at,
            tr.approved_by_name,
            tr.completed_at,
            tr.moodle_quiz_score,
            -- Calculate improvement
            (tr.completion_score - tr.current_score) as score_improvement
        FROM training_recommendations tr
        LEFT JOIN analysis_questions aq ON tr.skill_code = aq.question_code
        WHERE tr.org_id = ?
          AND tr.status = 'completed'
        ORDER BY tr.completed_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute([$org_id]);
    $recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($recommendations as &$rec) {
        $rec['current_score'] = round((float)$rec['current_score'], 1);
        $rec['completion_score'] = $rec['completion_score'] ? round((float)$rec['completion_score'], 1) : null;
        $rec['moodle_quiz_score'] = $rec['moodle_quiz_score'] ? round((float)$rec['moodle_quiz_score'], 1) : null;
        $rec['score_improvement'] = $rec['score_improvement'] ? round((float)$rec['score_improvement'], 1) : null;
    }

    return [
        'success' => true,
        'data' => $recommendations
    ];
}

/**
 * Get workflow statistics
 */
function getWorkflowStats($pdo, $org_id) {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN approval_status = 'pending_approval' AND status NOT IN ('completed', 'expired') THEN 1 ELSE 0 END) as pending_approval,
            SUM(CASE WHEN approval_status = 'approved' AND status NOT IN ('completed', 'expired') THEN 1 ELSE 0 END) as approved_in_progress,
            SUM(CASE WHEN approval_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN moodle_quiz_passed = 1 THEN 1 ELSE 0 END) as moodle_completed,
            SUM(CASE WHEN crm_task_id IS NOT NULL THEN 1 ELSE 0 END) as bitrix_tasks_created,
            SUM(CASE WHEN priority = 'critical' AND approval_status = 'pending_approval' THEN 1 ELSE 0 END) as critical_pending
        FROM training_recommendations
        WHERE org_id = ?
    ");
    $stmt->execute([$org_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get average time to approval
    $avgStmt = $pdo->prepare("
        SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, approved_at)) as avg_hours_to_approval
        FROM training_recommendations
        WHERE org_id = ? AND approved_at IS NOT NULL
    ");
    $avgStmt->execute([$org_id]);
    $avgTime = $avgStmt->fetch(PDO::FETCH_ASSOC);

    return [
        'success' => true,
        'data' => [
            'total' => (int)$stats['total'],
            'pending_approval' => (int)$stats['pending_approval'],
            'approved_in_progress' => (int)$stats['approved_in_progress'],
            'rejected' => (int)$stats['rejected'],
            'completed' => (int)$stats['completed'],
            'moodle_completed' => (int)$stats['moodle_completed'],
            'bitrix_tasks_created' => (int)$stats['bitrix_tasks_created'],
            'critical_pending' => (int)$stats['critical_pending'],
            'avg_hours_to_approval' => round((float)($avgTime['avg_hours_to_approval'] ?? 0), 1)
        ]
    ];
}

/**
 * Approve recommendation and create Bitrix24 task
 */
function approveRecommendation($pdo, $org_id, $user_id, $user_name, $data) {
    if (empty($data['recommendation_id'])) {
        return ['success' => false, 'error' => 'recommendation_id required'];
    }

    $recommendation_id = $data['recommendation_id'];
    $comment = $data['comment'] ?? null;
    $create_bitrix_task = $data['create_bitrix_task'] ?? true;

    // Get recommendation details
    $stmt = $pdo->prepare("
        SELECT tr.*, mbm.bitrix_user_id, mbm.bitrix_supervisor_id
        FROM training_recommendations tr
        LEFT JOIN manager_bitrix_mapping mbm
            ON tr.employee_full_name = mbm.employee_full_name
            AND tr.org_id = mbm.org_id
            AND mbm.is_active = 1
        WHERE tr.recommendation_id = ? AND tr.org_id = ?
    ");
    $stmt->execute([$recommendation_id, $org_id]);
    $rec = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rec) {
        return ['success' => false, 'error' => 'Recommendation not found'];
    }

    if ($rec['approval_status'] !== 'pending_approval') {
        return ['success' => false, 'error' => 'Recommendation is not pending approval'];
    }

    $pdo->beginTransaction();

    try {
        // Update recommendation status
        $updateStmt = $pdo->prepare("
            UPDATE training_recommendations
            SET approval_status = 'approved',
                approved_by_user_id = ?,
                approved_by_name = ?,
                approved_at = NOW(),
                approval_comment = ?,
                status = 'sent',
                updated_at = NOW()
            WHERE recommendation_id = ? AND org_id = ?
        ");
        $updateStmt->execute([$user_id, $user_name, $comment, $recommendation_id, $org_id]);

        // Log the approval
        logApprovalAction($pdo, $org_id, $recommendation_id, 'approved', $user_id, $user_name, $comment, $rec);

        $bitrix_task_id = null;
        $bitrix_error = null;

        // Create Bitrix24 task if requested and mapping exists
        if ($create_bitrix_task && !empty($rec['bitrix_user_id'])) {
            $taskResult = createBitrixTask($pdo, $org_id, $rec);
            if ($taskResult['success']) {
                $bitrix_task_id = $taskResult['task_id'];

                // Update with Bitrix task ID
                $bitrixStmt = $pdo->prepare("
                    UPDATE training_recommendations
                    SET crm_task_id = ?,
                        crm_task_created = 1,
                        crm_task_created_at = NOW(),
                        bitrix_task_status = 'created',
                        bitrix_responsible_id = ?
                    WHERE recommendation_id = ?
                ");
                $bitrixStmt->execute([$bitrix_task_id, $rec['bitrix_user_id'], $recommendation_id]);

                // Log Bitrix task creation
                logApprovalAction($pdo, $org_id, $recommendation_id, 'bitrix_task_created', $user_id, $user_name, null, $rec, $bitrix_task_id);
            } else {
                $bitrix_error = $taskResult['error'];
            }
        }

        $pdo->commit();

        return [
            'success' => true,
            'message' => 'Recommendation approved',
            'bitrix_task_id' => $bitrix_task_id,
            'bitrix_error' => $bitrix_error
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Reject recommendation
 */
function rejectRecommendation($pdo, $org_id, $user_id, $user_name, $data) {
    if (empty($data['recommendation_id'])) {
        return ['success' => false, 'error' => 'recommendation_id required'];
    }

    $recommendation_id = $data['recommendation_id'];
    $comment = $data['comment'] ?? null;

    // Get recommendation details for logging
    $stmt = $pdo->prepare("
        SELECT * FROM training_recommendations
        WHERE recommendation_id = ? AND org_id = ?
    ");
    $stmt->execute([$recommendation_id, $org_id]);
    $rec = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rec) {
        return ['success' => false, 'error' => 'Recommendation not found'];
    }

    // Update recommendation
    $updateStmt = $pdo->prepare("
        UPDATE training_recommendations
        SET approval_status = 'rejected',
            approved_by_user_id = ?,
            approved_by_name = ?,
            approved_at = NOW(),
            approval_comment = ?,
            status = 'expired',
            updated_at = NOW()
        WHERE recommendation_id = ? AND org_id = ?
    ");
    $updateStmt->execute([$user_id, $user_name, $comment, $recommendation_id, $org_id]);

    // Log the rejection
    logApprovalAction($pdo, $org_id, $recommendation_id, 'rejected', $user_id, $user_name, $comment, $rec);

    return [
        'success' => true,
        'message' => 'Recommendation rejected'
    ];
}

/**
 * Bulk approve multiple recommendations
 */
function bulkApprove($pdo, $org_id, $user_id, $user_name, $data) {
    if (empty($data['recommendation_ids']) || !is_array($data['recommendation_ids'])) {
        return ['success' => false, 'error' => 'recommendation_ids array required'];
    }

    $results = [];
    foreach ($data['recommendation_ids'] as $rec_id) {
        $result = approveRecommendation($pdo, $org_id, $user_id, $user_name, [
            'recommendation_id' => $rec_id,
            'create_bitrix_task' => $data['create_bitrix_task'] ?? true
        ]);
        $results[$rec_id] = $result['success'];
    }

    $successCount = count(array_filter($results));

    return [
        'success' => true,
        'message' => "Approved $successCount of " . count($results) . " recommendations",
        'results' => $results
    ];
}

/**
 * Handle Moodle webhook when quiz is completed
 */
function handleMoodleWebhook($pdo, $data) {
    // Verify webhook token
    $token = $_SERVER['HTTP_X_MOODLE_TOKEN'] ?? $data['token'] ?? null;

    if (empty($token)) {
        http_response_code(401);
        return ['success' => false, 'error' => 'Missing webhook token'];
    }

    // Find org by token
    $tokenStmt = $pdo->prepare("
        SELECT org_id FROM moodle_webhook_tokens
        WHERE webhook_token = ? AND is_active = 1
    ");
    $tokenStmt->execute([$token]);
    $tokenRow = $tokenStmt->fetch(PDO::FETCH_ASSOC);

    if (!$tokenRow) {
        http_response_code(401);
        return ['success' => false, 'error' => 'Invalid webhook token'];
    }

    $org_id = $tokenRow['org_id'];

    // Required fields from Moodle
    $employee_email = $data['user_email'] ?? null;
    $employee_name = $data['user_name'] ?? null;
    $quiz_score = $data['grade'] ?? $data['score'] ?? null;
    $skill_code = $data['skill_code'] ?? $data['course_shortname'] ?? null;

    if (!$employee_email && !$employee_name) {
        return ['success' => false, 'error' => 'user_email or user_name required'];
    }

    // Find the recommendation
    $findStmt = $pdo->prepare("
        SELECT tr.recommendation_id, tr.employee_full_name, tr.crm_task_id, tr.bitrix_responsible_id
        FROM training_recommendations tr
        LEFT JOIN manager_bitrix_mapping mbm
            ON tr.employee_full_name = mbm.employee_full_name
            AND tr.org_id = mbm.org_id
        WHERE tr.org_id = ?
          AND tr.approval_status = 'approved'
          AND tr.moodle_quiz_passed = 0
          AND tr.status NOT IN ('completed', 'expired')
          AND (
              mbm.bitrix_user_email = ?
              OR tr.employee_full_name LIKE ?
              OR (? IS NOT NULL AND tr.skill_code = ?)
          )
        ORDER BY tr.approved_at DESC
        LIMIT 1
    ");
    $searchName = '%' . ($employee_name ?? '') . '%';
    $findStmt->execute([$org_id, $employee_email, $searchName, $skill_code, $skill_code]);
    $rec = $findStmt->fetch(PDO::FETCH_ASSOC);

    if (!$rec) {
        // Log but don't fail - might be a quiz not related to our recommendations
        error_log("Moodle webhook: No matching recommendation found for user=$employee_email, skill=$skill_code");
        return ['success' => true, 'message' => 'No matching recommendation found', 'matched' => false];
    }

    // Update recommendation
    $updateStmt = $pdo->prepare("
        UPDATE training_recommendations
        SET moodle_quiz_passed = 1,
            moodle_completion_at = NOW(),
            moodle_quiz_score = ?,
            status = 'completed',
            completed_at = NOW(),
            completion_score = ?,
            updated_at = NOW()
        WHERE recommendation_id = ?
    ");
    $updateStmt->execute([$quiz_score, $quiz_score, $rec['recommendation_id']]);

    // Log the completion
    logApprovalAction($pdo, $org_id, $rec['recommendation_id'], 'moodle_completed', null, 'Moodle Webhook', null, [
        'employee_full_name' => $rec['employee_full_name'],
        'skill_code' => $skill_code,
        'current_score' => null,
        'priority' => null
    ], null, $quiz_score);

    // Try to complete Bitrix24 task
    if (!empty($rec['crm_task_id'])) {
        completeBitrixTask($pdo, $org_id, $rec['crm_task_id'], $rec['recommendation_id']);
    }

    // Update webhook stats
    $pdo->prepare("
        UPDATE moodle_webhook_tokens
        SET last_webhook_at = NOW()
        WHERE org_id = ?
    ")->execute([$org_id]);

    return [
        'success' => true,
        'message' => 'Quiz completion recorded',
        'matched' => true,
        'recommendation_id' => $rec['recommendation_id']
    ];
}

/**
 * Get managers with Bitrix mapping
 */
function getManagersWithBitrixMapping($pdo, $org_id) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            tr.employee_full_name,
            mbm.bitrix_user_id,
            mbm.bitrix_user_name,
            mbm.bitrix_user_email,
            mbm.bitrix_supervisor_id,
            mbm.is_active as has_mapping
        FROM training_recommendations tr
        LEFT JOIN manager_bitrix_mapping mbm
            ON tr.employee_full_name = mbm.employee_full_name
            AND tr.org_id = mbm.org_id
        WHERE tr.org_id = ?
        ORDER BY tr.employee_full_name
    ");
    $stmt->execute([$org_id]);

    return [
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ];
}

/**
 * Create Bitrix24 task for training
 */
function createBitrixTask($pdo, $org_id, $rec) {
    // Load Bitrix24 webhook URL for org
    // For now, we'll use the Python integration
    // In production, this would call the Bitrix24 API directly

    try {
        // Prepare task data
        $skill_name = $rec['skill_name'] ?? $rec['skill_code'];
        $manager_name = $rec['employee_full_name'];
        $current_score = round((float)$rec['current_score'], 1);
        $target_score = round((float)$rec['target_score'], 1);

        $task_title = "Обучение: $skill_name";
        $task_description = <<<EOT
Рекомендация по улучшению навыка: $skill_name

Менеджер: $manager_name
Текущий показатель: {$current_score}%
Целевой показатель: {$target_score}%

Пройдите обучающий материал в Moodle и сдайте тест.
После успешного прохождения теста задача будет закрыта автоматически.

---
Автоматически создано системой AILOCA
EOT;

        // For now, we'll simulate task creation and return a placeholder ID
        // In production, this would call the Bitrix24 API

        // Check if we have Bitrix webhook configured
        $configStmt = $pdo->prepare("
            SELECT config_json FROM organization_integrations
            WHERE org_id = ? AND integration_type = 'bitrix24_outgoing' AND is_active = 1
        ");
        $configStmt->execute([$org_id]);
        $config = $configStmt->fetch(PDO::FETCH_ASSOC);

        if (!$config) {
            // Return simulated task ID for demo
            return [
                'success' => true,
                'task_id' => 'demo-' . uniqid(),
                'note' => 'Bitrix24 integration not configured, demo task ID generated'
            ];
        }

        // TODO: Actually call Bitrix24 API
        // $bitrixConfig = json_decode($config['config_json'], true);
        // $webhookUrl = $bitrixConfig['webhook_url'];
        // ... API call ...

        return [
            'success' => true,
            'task_id' => 'btx-' . uniqid(),
            'note' => 'Task created (simulated)'
        ];

    } catch (Exception $e) {
        error_log("Error creating Bitrix task: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Complete Bitrix24 task
 */
function completeBitrixTask($pdo, $org_id, $task_id, $recommendation_id) {
    try {
        // Update task status in our DB
        $pdo->prepare("
            UPDATE training_recommendations
            SET bitrix_task_status = 'completed'
            WHERE recommendation_id = ?
        ")->execute([$recommendation_id]);

        // TODO: Actually call Bitrix24 API to complete the task
        // For now, we just update our local status

        return true;
    } catch (Exception $e) {
        error_log("Error completing Bitrix task: " . $e->getMessage());
        return false;
    }
}

/**
 * Log approval workflow action
 */
function logApprovalAction($pdo, $org_id, $recommendation_id, $action, $user_id, $user_name, $comment, $rec, $bitrix_task_id = null, $moodle_score = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO training_approval_log
            (org_id, recommendation_id, action, action_by_user_id, action_by_name, action_comment,
             employee_full_name, skill_code, current_score, priority, bitrix_task_id, moodle_quiz_score,
             ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $org_id,
            $recommendation_id,
            $action,
            $user_id,
            $user_name,
            $comment,
            $rec['employee_full_name'] ?? null,
            $rec['skill_code'] ?? null,
            $rec['current_score'] ?? null,
            $rec['priority'] ?? null,
            $bitrix_task_id,
            $moodle_score,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        // Log but don't fail the main operation
        error_log("Failed to log approval action: " . $e->getMessage());
    }
}

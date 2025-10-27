<?php
/**
 * Базовый класс для приема и обработки вебхуков
 */

class WebhookReceiver {
    private $db;
    private $source;

    public function __construct($db, $source) {
        $this->db = $db;
        $this->source = $source;
    }

    /**
     * Обработка входящего вебхука
     */
    public function process($data, $rawPayload) {
        try {
            // 1. Сохранить raw лид
            $leadId = $this->saveRawLead($data, $rawPayload);
            $this->log($leadId, 'received', 'success', 'Вебхук получен');

            // 2. Валидация телефона
            $phone = $this->extractPhone($data);
            if (!$phone) {
                throw new Exception('Телефон не найден в данных');
            }

            $phoneValidation = $this->validatePhone($phone);
            if (!$phoneValidation['valid']) {
                $this->log($leadId, 'validated', 'error', 'Невалидный телефон: ' . $phoneValidation['error']);
                throw new Exception('Невалидный телефон');
            }

            $normalizedPhone = $phoneValidation['normalized'];
            $this->log($leadId, 'validated', 'success', 'Телефон валиден: ' . $normalizedPhone);

            // 3. Проверка на дубликаты
            $isDuplicate = $this->checkDuplicate($normalizedPhone, $leadId);
            if ($isDuplicate) {
                $this->updateLead($leadId, [
                    'phone' => $normalizedPhone,
                    'status' => 'duplicate',
                    'is_duplicate' => 1,
                    'duplicate_of_id' => $isDuplicate
                ]);
                $this->log($leadId, 'deduplicated', 'warning', 'Найден дубликат: лид #' . $isDuplicate);
                return $leadId;
            }

            $this->log($leadId, 'deduplicated', 'success', 'Дубликатов не найдено');

            // 4. Обновить лид с нормализованными данными
            $mappedData = $this->mapFields($data);
            $mappedData['phone'] = $normalizedPhone;
            $mappedData['status'] = 'new';
            $mappedData['validation_status'] = 'valid';

            $this->updateLead($leadId, $mappedData);
            $this->log($leadId, 'normalized', 'success', 'Данные нормализованы');

            // 5. Добавить в очередь JoyWork
            $this->enqueueForJoyWork($leadId);
            $this->log($leadId, 'enqueued', 'success', 'Добавлен в очередь JoyWork');

            return $leadId;

        } catch (Exception $e) {
            if (isset($leadId)) {
                $this->log($leadId, 'error', 'error', $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Сохранить raw лид в БД
     */
    private function saveRawLead($data, $rawPayload) {
        $stmt = $this->db->prepare("
            INSERT INTO leads (source, raw_payload, phone_raw, created_at)
            VALUES (:source, :raw_payload, :phone_raw, NOW())
        ");

        $stmt->execute([
            'source' => $this->source,
            'raw_payload' => $rawPayload,
            'phone_raw' => $this->extractPhone($data)
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Обновить лид
     */
    private function updateLead($leadId, $data) {
        $fields = [];
        $params = ['id' => $leadId];

        foreach ($data as $key => $value) {
            $fields[] = "$key = :$key";
            $params[$key] = $value;
        }

        $sql = "UPDATE leads SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Логирование обработки
     */
    private function log($leadId, $step, $status, $message, $details = null) {
        $stmt = $this->db->prepare("
            INSERT INTO lead_processing_log (lead_id, step, status, message, details, created_at)
            VALUES (:lead_id, :step, :status, :message, :details, NOW())
        ");

        $stmt->execute([
            'lead_id' => $leadId,
            'step' => $step,
            'status' => $status,
            'message' => $message,
            'details' => $details ? json_encode($details) : null
        ]);
    }

    /**
     * Валидация телефона
     */
    private function validatePhone($phone) {
        // Убираем все не-цифры
        $cleaned = preg_replace('/\D/', '', $phone);

        // Российский формат: +7XXXXXXXXXX (11 цифр)
        if (strlen($cleaned) === 11 && $cleaned[0] === '7') {
            return [
                'valid' => true,
                'normalized' => '+' . $cleaned
            ];
        }

        // Формат без +7: 9XXXXXXXXX (10 цифр)
        if (strlen($cleaned) === 10 && $cleaned[0] === '9') {
            return [
                'valid' => true,
                'normalized' => '+7' . $cleaned
            ];
        }

        // ТЕСТОВЫЙ РЕЖИМ: Принимаем любые 11 цифр (для отладки)
        if (strlen($cleaned) === 11) {
            return [
                'valid' => true,
                'normalized' => '+' . $cleaned
            ];
        }

        return [
            'valid' => false,
            'error' => 'Неверный формат телефона. Ожидается: +79XXXXXXXXX или 11 цифр'
        ];
    }

    /**
     * Проверка на дубликаты (за последние 24 часа)
     */
    private function checkDuplicate($phone, $currentLeadId) {
        $stmt = $this->db->prepare("
            SELECT id FROM leads
            WHERE phone = :phone
            AND id != :current_id
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY created_at DESC
            LIMIT 1
        ");

        $stmt->execute([
            'phone' => $phone,
            'current_id' => $currentLeadId
        ]);

        $duplicate = $stmt->fetch(PDO::FETCH_ASSOC);

        // Записать в таблицу duplicate_checks
        $checkStmt = $this->db->prepare("
            INSERT INTO duplicate_checks (lead_id, phone, found_duplicate_id, checked_at)
            VALUES (:lead_id, :phone, :found_duplicate_id, NOW())
        ");

        $checkStmt->execute([
            'lead_id' => $currentLeadId,
            'phone' => $phone,
            'found_duplicate_id' => $duplicate ? $duplicate['id'] : null
        ]);

        return $duplicate ? $duplicate['id'] : false;
    }

    /**
     * Добавить в очередь JoyWork
     */
    private function enqueueForJoyWork($leadId) {
        $stmt = $this->db->prepare("
            INSERT INTO joywork_sync_queue (lead_id, status, created_at, next_attempt_at)
            VALUES (:lead_id, 'pending', NOW(), NOW())
            ON DUPLICATE KEY UPDATE status = 'pending', next_attempt_at = NOW()
        ");

        $stmt->execute(['lead_id' => $leadId]);
    }

    /**
     * Извлечь телефон из данных (должен быть переопределен в дочерних классах)
     */
    protected function extractPhone($data) {
        return null;
    }

    /**
     * Маппинг полей (должен быть переопределен в дочерних классах)
     */
    protected function mapFields($data) {
        return [];
    }
}

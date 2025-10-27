<?php
/**
 * Очистка лога Creatium webhook
 */

$logFile = __DIR__ . '/creatium_debug.log';

if (file_exists($logFile)) {
    file_put_contents($logFile, '');
}

echo json_encode(['success' => true]);

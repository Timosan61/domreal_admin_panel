<?php
header("Content-Type: application/json; charset=UTF-8");

echo json_encode([
    'success' => true,
    'message' => 'Simple test works',
    'timestamp' => date('Y-m-d H:i:s')
]);
?>

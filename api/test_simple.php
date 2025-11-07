<?php
// Super simple test - no includes
header("Content-Type: application/json");
echo json_encode(["test" => "success", "time" => date('Y-m-d H:i:s')]);
?>

<?php
function log_audit($conn, $user_id, $action, $description) {
  $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  $previousHash = null;
  $result = $conn->query("SELECT log_hash FROM audit_log ORDER BY created_at DESC LIMIT 1");
  if ($row = $result->fetch_assoc()) {
    $previousHash = $row['log_hash'];
  }
  $timestamp = date('Y-m-d H:i:s');
  $hashInput = $user_id . $action . $description . $ip . $timestamp . $previousHash;
  $logHash = hash('sha256', $hashInput);
  $stmt = $conn->prepare("
    INSERT INTO audit_log (user_id, action, description, ip_address, created_at, previous_hash, log_hash)
    VALUES (?, ?, ?, ?, ?, ?, ?)
  ");
  $stmt->bind_param("issssss", $user_id, $action, $description, $ip, $timestamp, $previousHash, $logHash);
  $stmt->execute();
}
?>
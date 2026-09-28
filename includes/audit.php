<?php
declare(strict_types=1);

function log_admin_action(
    PDO $pdo,
    int $adminUserId,
    string $action,
    string $entityType,
    string $entityId,
    ?string $details = null
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO audit_logs (admin_user_id, action, entity_type, entity_id, details)
         VALUES (:admin_id, :action, :entity_type, :entity_id, :details)'
    );
    $stmt->execute([
        ':admin_id' => $adminUserId,
        ':action' => $action,
        ':entity_type' => $entityType,
        ':entity_id' => $entityId,
        ':details' => $details,
    ]);
}
<?php
declare(strict_types=1);
/** UCO 1B: resolución de identidad, compatible con las sesiones actuales. */
function ucoIdentityResolve(PDO $db, string $kind, int $legacyId, bool $create = false): ?int {
    if (!in_array($kind, ['staff', 'customer'], true) || $legacyId < 1) return null;
    $table = $kind === 'staff' ? 'admin_users' : 'customers';
    $st = $db->prepare("SELECT id FROM $table WHERE id=?");
    $st->execute([$legacyId]);
    if (!$st->fetchColumn()) return null;
    $st = $db->prepare('SELECT id FROM identity_accounts WHERE kind=? AND legacy_id=?');
    $st->execute([$kind, $legacyId]);
    $id = $st->fetchColumn();
    if ($id === false && $create) {
        $db->prepare('INSERT OR IGNORE INTO identity_accounts(kind,legacy_id) VALUES(?,?)')->execute([$kind,$legacyId]);
        $st->execute([$kind,$legacyId]);
        $id = $st->fetchColumn();
    }
    if ($id === false) return null;
    if ($kind === 'customer' && $create) {
        $db->prepare('INSERT OR IGNORE INTO identity_customer_links(identity_id,customer_id) VALUES(?,?)')->execute([(int)$id,$legacyId]);
    }
    return (int)$id;
}
function ucoIdentitySessionValid(PDO $db, string $kind, int $legacyId, mixed $sessionIdentity): bool {
    // Sesiones previas a 1B continúan funcionando hasta su siguiente inicio de sesión.
    if ($sessionIdentity === null) return true;
    $id = ucoIdentityResolve($db,$kind,$legacyId,false);
    return $id !== null && $id === (int)$sessionIdentity;
}

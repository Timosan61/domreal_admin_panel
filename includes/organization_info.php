<?php
/**
 * Organization info component
 * Displays organization name, user ID, and balance
 */

// Get database connection
require_once __DIR__ . '/../db.php';

function getOrganizationInfo() {
    $conn = get_db_connection();
    if (!$conn) {
        return null;
    }

    // Get current user's org_id from session or use default
    $org_id = $_SESSION['org_id'] ?? 'org-legacy';

    // Fetch organization and balance info
    $stmt = $conn->prepare("
        SELECT
            o.org_id,
            o.company_name,
            o.status,
            COALESCE(b.balance_kopeks, 0) as balance_kopeks,
            COALESCE(b.total_deposited_kopeks, 0) as total_deposited_kopeks,
            COALESCE(b.total_spent_kopeks, 0) as total_spent_kopeks
        FROM organizations o
        LEFT JOIN organization_balances b ON o.org_id = b.org_id
        WHERE o.org_id = ?
        LIMIT 1
    ");

    $stmt->bind_param('s', $org_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $org = $result->fetch_assoc();

    if ($org) {
        // Convert kopeks to rubles (100 kopeks = 1 ruble)
        $org['balance_rubles'] = $org['balance_kopeks'] / 100;

        // Calculate tokens (1000 tokens per ruble)
        $tokens_per_ruble = 1000;
        $org['balance_tokens'] = $org['balance_rubles'] * $tokens_per_ruble;

        // Map status to org_type for display
        $org['org_type'] = $org['status'];
    }

    $stmt->close();
    $conn->close();

    return $org;
}

// Fetch organization info
$org_info = getOrganizationInfo();
?>

<?php if ($org_info): ?>
<div class="organization-info">
    <div class="org-header">
        <div class="org-name"><?= htmlspecialchars($org_info['company_name']) ?></div>
        <?php if ($org_info['org_type'] === 'trial'): ?>
            <span class="org-badge org-badge-trial">TRIAL</span>
        <?php elseif ($org_info['org_type'] === 'suspended'): ?>
            <span class="org-badge org-badge-suspended">SUSPENDED</span>
        <?php endif; ?>
    </div>

    <div class="org-details">
        <div class="org-detail-item">
            <span class="org-detail-label">Пользователь:</span>
            <span class="org-detail-value"><?= htmlspecialchars($_SESSION['username']) ?> (ID: <?= $_SESSION['user_id'] ?? 'N/A' ?>)</span>
        </div>

        <div class="org-balance">
            <div class="balance-item">
                <span class="balance-label">Баланс (₽):</span>
                <span class="balance-value balance-rubles">
                    <?php
                    $rubles = $org_info['balance_rubles'];
                    if ($rubles >= 1000) {
                        echo number_format($rubles, 0, '.', ' ') . ' ₽';
                    } else {
                        echo number_format($rubles, 2, '.', ' ') . ' ₽';
                    }
                    ?>
                </span>
            </div>
            <div class="balance-item">
                <span class="balance-label">Токены:</span>
                <span class="balance-value balance-tokens">
                    <?php
                    $tokens = $org_info['balance_tokens'];
                    if ($tokens >= 1000000) {
                        echo number_format($tokens / 1000000, 1, '.', '') . 'M';
                    } elseif ($tokens >= 1000) {
                        echo number_format($tokens / 1000, 1, '.', '') . 'K';
                    } else {
                        echo number_format($tokens, 0, '', ' ');
                    }
                    ?>
                </span>
            </div>
        </div>

        <a href="/balance_topup.php" class="btn-topup">Пополнить баланс</a>
    </div>
</div>
<?php else: ?>
<div class="organization-info-error">
    <p style="color: #f44336; padding: 10px; font-size: 12px;">
        ⚠️ Не удалось загрузить информацию об организации
    </p>
</div>
<?php endif; ?>

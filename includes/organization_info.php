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

<style>
.organization-info {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px;
    margin: -10px -10px 15px -10px;
    border-radius: 8px 8px 0 0;
}

.org-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.org-name {
    font-size: 16px;
    font-weight: 600;
    flex: 1;
}

.org-badge {
    padding: 3px 8px;
    font-size: 11px;
    font-weight: 700;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.3);
    backdrop-filter: blur(10px);
}

.org-badge-trial {
    background: rgba(33, 150, 243, 0.3);
    color: #fff;
}

.org-badge-suspended {
    background: rgba(244, 67, 54, 0.3);
    color: #fff;
}

.org-details {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.org-detail-item {
    font-size: 12px;
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.org-detail-label {
    opacity: 0.8;
    font-size: 11px;
}

.org-detail-value {
    font-weight: 500;
}

.org-balance {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    padding: 10px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 6px;
    backdrop-filter: blur(10px);
}

.balance-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.balance-label {
    font-size: 11px;
    opacity: 0.9;
}

.balance-value {
    font-size: 16px;
    font-weight: 700;
}

.balance-rubles {
    color: #ffd700;
}

.balance-tokens {
    color: #90caf9;
}

.btn-topup {
    display: block;
    width: 100%;
    padding: 8px 16px;
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 6px;
    color: white;
    text-align: center;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
}

.btn-topup:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-1px);
}
</style>
<?php else: ?>
<div class="organization-info-error">
    <p style="color: #f44336; padding: 10px; font-size: 12px;">
        ⚠️ Не удалось загрузить информацию об организации
    </p>
</div>
<?php endif; ?>

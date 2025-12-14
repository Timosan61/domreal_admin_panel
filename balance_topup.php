<?php
session_start();
require_once 'auth/session.php';
checkAuth();

$user_full_name = $_SESSION['full_name'] ?? 'Пользователь';
$user_role = $_SESSION['role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Пополнение баланса - Domreal Admin</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="topup-page">
        <div class="topup-content">
            <!-- Header -->
            <div class="topup-header">
                <h1>💳 Пополнение баланса</h1>
            </div>

            <!-- Body -->
            <div class="topup-body">
                <div class="topup-container">
                    <!-- Current Balance -->
                    <div class="balance-card" id="current-balance">
                        <h2>Текущий баланс</h2>
                        <div class="balance-display">
                            <div class="balance-item">
                                <span class="balance-label">Баланс (₽)</span>
                                <div class="balance-value" id="balance-rubles">-</div>
                            </div>
                            <div class="balance-item">
                                <span class="balance-label">Токены</span>
                                <div class="balance-value" id="balance-tokens">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="success-message" id="success-message"></div>
                    <div class="error-message" id="error-message"></div>

                    <!-- Top-up Form -->
                    <div class="topup-form-card">
                        <h2>Пополнить счет</h2>

                        <form id="topup-form">
                            <div class="form-group">
                                <label>Сумма пополнения (₽)</label>
                                <input type="number" id="amount" name="amount" min="100" step="100" placeholder="Введите сумму" required>

                                <div class="amount-presets">
                                    <div class="amount-preset" data-amount="1000">1 000 ₽</div>
                                    <div class="amount-preset" data-amount="5000">5 000 ₽</div>
                                    <div class="amount-preset" data-amount="10000">10 000 ₽</div>
                                    <div class="amount-preset" data-amount="50000">50 000 ₽</div>
                                </div>
                            </div>

                            <div class="conversion-info" id="conversion-info" style="display: none;">
                                Вы получите <strong id="tokens-amount">0</strong> токенов
                                <br>
                                <small>Курс: 1 ₽ = 1000 токенов</small>
                            </div>

                            <div class="form-group">
                                <label>Способ оплаты</label>
                                <select id="payment-method" name="payment_method" required>
                                    <option value="">Выберите способ оплаты</option>
                                    <option value="acquiring">Банковская карта (Acquiring)</option>
                                    <option value="bank_transfer">Банковский перевод</option>
                                    <option value="manual_topup">Ручное пополнение (Demo)</option>
                                </select>
                            </div>

                            <button type="submit" class="btn-submit" id="submit-btn">
                                Пополнить баланс
                            </button>
                        </form>

                        <div class="demo-notice">
                            <strong>Demo режим:</strong> Для демонстрации используйте способ "Ручное пополнение (Demo)".
                            Баланс будет пополнен автоматически без реальной оплаты.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Load current balance
        async function loadBalance() {
            try {
                const response = await fetch('/api/organization_balance.php?action=get');
                const result = await response.json();

                if (result.success) {
                    const rubles = result.data.balance_rubles;
                    const tokens = result.data.balance_tokens;

                    document.getElementById('balance-rubles').textContent =
                        rubles >= 1000 ? rubles.toLocaleString('ru-RU') + ' ₽' : rubles.toFixed(2) + ' ₽';

                    document.getElementById('balance-tokens').textContent =
                        tokens >= 1000000 ? (tokens / 1000000).toFixed(1) + 'M' :
                        tokens >= 1000 ? (tokens / 1000).toFixed(1) + 'K' :
                        tokens.toLocaleString('ru-RU');
                }
            } catch (error) {
                console.error('Failed to load balance:', error);
            }
        }

        // Amount presets
        document.querySelectorAll('.amount-preset').forEach(preset => {
            preset.addEventListener('click', function() {
                const amount = this.dataset.amount;
                document.getElementById('amount').value = amount;

                // Remove active class from all
                document.querySelectorAll('.amount-preset').forEach(p => p.classList.remove('active'));
                this.classList.add('active');

                updateConversion();
            });
        });

        // Update conversion info
        const amountInput = document.getElementById('amount');
        amountInput.addEventListener('input', updateConversion);

        function updateConversion() {
            const amount = parseFloat(amountInput.value) || 0;
            const tokens = amount * 1000; // 1 ruble = 1000 tokens

            if (amount > 0) {
                document.getElementById('conversion-info').style.display = 'block';
                document.getElementById('tokens-amount').textContent = tokens.toLocaleString('ru-RU');
            } else {
                document.getElementById('conversion-info').style.display = 'none';
            }
        }

        // Form submission
        document.getElementById('topup-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const amount = parseFloat(document.getElementById('amount').value);
            const paymentMethod = document.getElementById('payment-method').value;

            if (amount < 100) {
                showError('Минимальная сумма пополнения: 100 ₽');
                return;
            }

            if (!paymentMethod) {
                showError('Выберите способ оплаты');
                return;
            }

            const submitBtn = document.getElementById('submit-btn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Обработка...';

            try {
                const response = await fetch('/api/organization_balance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'topup',
                        amount_rubles: amount,
                        payment_method: paymentMethod
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showSuccess(`Баланс успешно пополнен на ${amount.toLocaleString('ru-RU')} ₽!`);
                    document.getElementById('topup-form').reset();
                    document.querySelectorAll('.amount-preset').forEach(p => p.classList.remove('active'));
                    document.getElementById('conversion-info').style.display = 'none';

                    // Reload balance
                    setTimeout(() => {
                        loadBalance();
                        location.reload();
                    }, 1500);
                } else {
                    showError(result.error || 'Ошибка при пополнении баланса');
                }
            } catch (error) {
                showError('Произошла ошибка при отправке запроса');
                console.error(error);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Пополнить баланс';
            }
        });

        function showSuccess(message) {
            const el = document.getElementById('success-message');
            el.textContent = message;
            el.style.display = 'block';
            document.getElementById('error-message').style.display = 'none';

            setTimeout(() => {
                el.style.display = 'none';
            }, 5000);
        }

        function showError(message) {
            const el = document.getElementById('error-message');
            el.textContent = message;
            el.style.display = 'block';
            document.getElementById('success-message').style.display = 'none';
        }

        // Load balance on page load
        loadBalance();
    </script>
</body>
</html>

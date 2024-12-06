<?php
// payment.php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['subscription_data'])) {
    header('Location: subscription_checkout.php');
    exit;
}

// Session'dan verileri al
$subscriptionData = $_SESSION['subscription_data'];
$plan = $subscriptionData['plan'];
$duration = $subscriptionData['duration'];
$total = $subscriptionData['total'];
$monthly = $subscriptionData['monthly'];
$savings = $subscriptionData['savings'];
$planName = $subscriptionData['plan_name'];

// Database connection
$dbConfig = require '../../../config/database.php';
$db = new PDO(
    "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['options']
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    try {
        $db->beginTransaction();

        // Önce normal abonelik işlemleri yapılır (transaction ve subscription kayıtları)
        $transactionId = time() . mt_rand(100000, 999999);
        
        // Abonelik ödemesi kaydı
        $stmt = $db->prepare("INSERT INTO transactions (transaction_id, sender_id, receiver_id, amount, transaction_type, status, description) VALUES (?, ?, ?, ?, 'PAYMENT', 'COMPLETED', ?)");
        $stmt->execute([
            $transactionId,
            $_SESSION['user_id'],
            $_SESSION['user_id'],
            $total,
            ($plan == 'id_plus' ? 'ID+' : 'ID+ Pro') . ' ' . $duration . ' aylık abonelik'
        ]);

        // Subscription ve user plan güncellemeleri
        $stmt = $db->prepare("INSERT INTO subscriptions (user_id, subscription_name, price, billing_period, start_date, next_billing_date) VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? MONTH))");
        $stmt->execute([
            $_SESSION['user_id'],
            $plan == 'id_plus' ? 'ID+' : 'ID+ Pro',
            $monthly,
            $duration == 12 ? 'YEARLY' : 'MONTHLY',
            $duration
        ]);

        $stmt = $db->prepare("UPDATE users SET subscription_plan = ? WHERE user_id = ?");
        $stmt->execute([$plan, $_SESSION['user_id']]);

        // Eğer 12 aylık plan seçildiyse coins işlemleri yapılır
        if ($duration == 12) {
            // Önce mevcut coins miktarını alalım
            $stmt = $db->prepare("SELECT coins FROM wallet WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $currentCoins = $stmt->fetchColumn();

            // Mevcut coins değerine 200 ekleyelim
            $stmt = $db->prepare("UPDATE wallet SET coins = coins + 200 WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);

            // Coins transaction kaydı
            $coinsTransactionId = time() . mt_rand(100000, 999999);
            $stmt = $db->prepare("INSERT INTO transactions (transaction_id, sender_id, receiver_id, amount, transaction_type, status, description) VALUES (?, ?, ?, 200, 'COINS_RECEIVED', 'COMPLETED', 'Yıllık abonelik hediye jetonu')");
            $stmt->execute([
                $coinsTransactionId,
                $_SESSION['user_id'],
                $_SESSION['user_id']
            ]);
        }

        // Session işlemleri ve yönlendirme
        $_SESSION['show_subscription_welcome'] = true;
        unset($_SESSION['subscription_data']);

        $db->commit();
        header('Location: wallet.php');
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Ödeme işlemi sırasında bir hata oluştu.";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ödeme - <?php echo $planName; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        * {
            font-family: "Poppins", sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f8f8f8;
        }

        .payment-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .payment-card {
            background: white;
            border: 1px solid #dedede;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
            color: #666;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #dedede;
            border-radius: 8px;
            font-size: 14px;
        }

        .submit-button {
            width: 100%;
            padding: 15px;
            background: #4F46E5;
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .submit-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(79, 70, 229, 0.2);
        }

        .price-summary {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #dedede;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .total-price {
            font-size: 18px;
            font-weight: 600;
            color: #4F46E5;
        }
    </style>
</head>

<body>
    <div class="payment-container">
        <h2 style="font-size: 22px; margin-bottom: 30px; font-weight: 600;">Ödeme</h2>

        <form method="POST" action="">
            <div class="payment-card">
                <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 20px;">Kart Bilgileri</h3>

                <div class="form-group">
                    <label>Kart Üzerindeki İsim</label>
                    <input type="text" required>
                </div>

                <div class="form-group">
                    <label>Kart Numarası</label>
                    <input type="text" required pattern="\d{16}" maxlength="16">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Son Kullanma Tarihi</label>
                        <input type="text" required pattern="\d{2}/\d{2}" placeholder="MM/YY" maxlength="5">
                    </div>

                    <div class="form-group">
                        <label>CVV</label>
                        <input type="text" required pattern="\d{3}" maxlength="3">
                    </div>
                </div>

                <div class="price-summary">
                    <div class="price-row">
                        <span>Plan</span>
                        <span><?php echo $planName; ?></span>
                    </div>

                    <div class="price-row">
                        <span>Süre</span>
                        <span><?php echo $duration; ?> Ay</span>
                    </div>

                    <div class="price-row">
                        <span>Aylık Ücret</span>
                        <span>₺<?php echo number_format($monthly, 2); ?></span>
                    </div>

                    <?php if ($savings > 0): ?>
                        <div class="price-row" style="color: #22c55e;">
                            <span>Toplam Tasarruf</span>
                            <span>₺<?php echo number_format($savings, 2); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="price-row total-price">
                        <span>Toplam</span>
                        <span>₺<?php echo number_format($total, 2); ?></span>
                    </div>
                </div>
            </div>

            <input type="hidden" name="process_payment" value="1">
            <button type="submit" class="submit-button">Ödemeyi Tamamla</button>
        </form>
    </div>
</body>

</html>
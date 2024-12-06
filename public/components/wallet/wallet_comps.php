<?php
// wallet_comps.php
function getWalletContent($section, $userData)
{
    global $db;

    switch ($section) {
        case 'wallet':
            // Son 6 aylık işlem verilerini çek
            $stmt = $db->prepare("
                SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
                       SUM(CASE WHEN transaction_type IN ('DEPOSIT', 'TRANSFER') AND receiver_id = ? THEN amount
                           WHEN transaction_type IN ('WITHDRAWAL', 'TRANSFER', 'PAYMENT') AND sender_id = ? THEN -amount
                           ELSE 0 END) as balance_change,
                       SUM(CASE WHEN transaction_type = 'COINS_RECEIVED' AND receiver_id = ? THEN amount
                           WHEN transaction_type = 'COINS_USED' AND sender_id = ? THEN -amount
                           ELSE 0 END) as coin_change
                FROM transactions 
                WHERE (sender_id = ? OR receiver_id = ?) 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month ASC
            ");
            $stmt->execute([$userData['user_id'], $userData['user_id'], $userData['user_id'], $userData['user_id'], $userData['user_id'], $userData['user_id']]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Son 3 para işlemini çek
            $stmt = $db->prepare("
SELECT t.*,
       CASE 
           WHEN t.sender_id = ? THEN 'outgoing'
           ELSE 'incoming'
       END as direction,
       'money' as type
FROM transactions t
WHERE (sender_id = ? OR receiver_id = ?)
AND transaction_type IN ('DEPOSIT', 'WITHDRAWAL', 'PAYMENT', 'TRANSFER')
ORDER BY created_at DESC
LIMIT 3
");
            $stmt->execute([$userData['user_id'], $userData['user_id'], $userData['user_id']]);
            $moneyTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Son 3 jeton işlemini çek
            $stmt = $db->prepare("
SELECT t.*,
       CASE 
           WHEN t.sender_id = ? THEN 'outgoing'
           ELSE 'incoming'
       END as direction,
       'coin' as type
FROM transactions t
WHERE (sender_id = ? OR receiver_id = ?)
AND transaction_type IN ('COINS_RECEIVED', 'COINS_USED')
ORDER BY created_at DESC
LIMIT 3
");
            $stmt->execute([$userData['user_id'], $userData['user_id'], $userData['user_id']]);
            $coinTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Aktif abonelikleri çek
            $stmt = $db->prepare("
                SELECT * FROM subscriptions 
                WHERE user_id = ? AND status = 'ACTIVE'
                ORDER BY next_billing_date ASC
            ");
            $stmt->execute([$userData['user_id']]);
            $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $html = '<div style="margin-left: 340px; padding: 30px;">
                <h2 style="font-size: 24px; margin-bottom: 30px;">Cüzdanım</h2>';

            $html .= '<div style="display: grid; grid-template-columns: repeat(4, 1fr); grid-template-rows: repeat(4, 1fr); gap: 20px; min-height: 800px;">
                    <!-- Bakiye Kartı - 2x2 Grid -->
                    <div style="border: 1px solid #dedede; padding: 20px; border-radius: 15px; position: relative; grid-column: span 2; grid-row: span 2;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h3 style="font-size: 18px;">Bakiye</h3>
                            <img src="/sources/icons/bulk/wallet-2.svg" style="width: 24px; opacity: 0.7;">
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <p style="font-size: 48px; font-weight: 600;">₺' . number_format($userData["balance"], 2) . '</p>
                        </div>
                        
                        <!-- Son İşlemler -->
                        <div style="margin-top: 20px;">
                            <h4 style="font-size: 14px; margin-bottom: 10px;">Son Para İşlemleri</h4>
                            <div style="max-height: 150px; overflow-y: auto;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr>
                                            <th style="text-align: left; padding: 8px 4px; border-bottom: 1px solid #eee; font-size: 12px;">İşlem No</th>
                                            <th style="text-align: left; padding: 8px 4px; border-bottom: 1px solid #eee; font-size: 12px;">İşlem</th>
                                            <th style="text-align: right; padding: 8px 4px; border-bottom: 1px solid #eee; font-size: 12px;">Tutar</th>
                                            <th style="text-align: center; padding: 8px 4px; border-bottom: 1px solid #eee; font-size: 12px;">Durum</th>
                                            <th style="text-align: right; padding: 8px 4px; border-bottom: 1px solid #eee; font-size: 12px;">Tarih</th>
                                        </tr>
                                    </thead>
                                    <tbody>';

            // Para işlemlerini listele
            foreach ($moneyTransactions as $t) {
                $amount = number_format($t['amount'], 2);
                $color = '#666'; // Default color
                $sign = '';

                switch ($t['transaction_type']) {
                    case 'DEPOSIT':
                        $color = '#22c55e';
                        $sign = '+';
                        break;
                    case 'WITHDRAWAL':
                    case 'PAYMENT':
                        $color = '#ef4444';
                        $sign = '-';
                        break;
                    case 'TRANSFER':
                        if ($t['direction'] === 'incoming') {
                            $color = '#22c55e';
                            $sign = '+';
                        } else {
                            $color = '#ef4444';
                            $sign = '-';
                        }
                        break;
                }

                $statusColor = '';
                switch ($t['status']) {
                    case 'COMPLETED':
                        $statusColor = '#22c55e';
                        break;
                    case 'PENDING':
                        $statusColor = '#f59e0b';
                        break;
                    case 'FAILED':
                        $statusColor = '#ef4444';
                        break;
                    case 'CANCELLED':
                        $statusColor = '#6b7280';
                        break;
                }

                $html .= '<tr>
                    <td style="padding: 8px 4px; border-bottom: 1px solid #eee; font-size: 12px;">' . $t['transaction_id'] . '</td>
                    <td style="padding: 8px 4px; border-bottom: 1px solid #eee; font-size: 12px;">' . ucfirst($t['transaction_type']) . '</td>
                    <td style="padding: 8px 4px; border-bottom: 1px solid #eee; font-size: 12px; text-align: right; color: ' . $color . ';">' . $sign . '₺' . $amount . '</td>
                    <td style="padding: 8px 4px; border-bottom: 1px solid #eee; font-size: 12px; text-align: center;">
                        <span style="color: ' . $statusColor . ';">' . ucfirst(strtolower($t['status'])) . '</span>
                    </td>
                    <td style="padding: 8px 4px; border-bottom: 1px solid #eee; font-size: 12px; text-align: right;">' . date('d.m.Y H:i', strtotime($t['created_at'])) . '</td>
                </tr>';
            }

            $html .= '</tbody>
                                </table>
                            </div>
                        </div>
         
                        <div style="display: flex; gap: 12px; position: absolute; bottom: 20px; right: 20px;">
                            <div style="position: relative; cursor: pointer; background: #dedede; padding: 8px; border-radius: 8px; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center;" 
                                onclick="window.location.href=\'api/deposit.php\'"
                                onmouseover="this.style.background=\'#bebebe\'; this.style.boxShadow=\'0 22px 40px rgba(0,0,0,0.1)\'; this.querySelector(\'div\').style.visibility=\'visible\'" 
                                onmouseout="this.style.background=\'#dedede\'; this.style.boxShadow=\'none\'; this.querySelector(\'div\').style.visibility=\'hidden\'">
                                <img src="/sources/icons/bulk/money-add.svg" style="width: 24px; opacity: 0.7;">
                                <div style="visibility: hidden; position: absolute; top: -32px; left: 50%; transform: translateX(-50%); background: #333; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; white-space: nowrap;">Para Yatır</div>
                            </div>
                            <div style="position: relative; cursor: pointer; background: #dedede; padding: 8px; border-radius: 8px; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center;" 
                                onclick="window.location.href=\'api/withdraw.php\'"
                                onmouseover="this.style.background=\'#bebebe\'; this.style.boxShadow=\'0 22px 40px rgba(0,0,0,0.1)\'; this.querySelector(\'div\').style.visibility=\'visible\'" 
                                onmouseout="this.style.background=\'#dedede\'; this.style.boxShadow=\'none\'; this.querySelector(\'div\').style.visibility=\'hidden\'">
                                <img src="/sources/icons/bulk/money-send.svg" style="width: 24px; opacity: 0.7;">
                                <div style="visibility: hidden; position: absolute; top: -32px; left: 50%; transform: translateX(-50%); background: #333; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; white-space: nowrap;">Para Çek</div>
                            </div>
                            <div style="position: relative; cursor: pointer; background: #dedede; padding: 8px; border-radius: 8px; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center;" 
                                onclick="window.location.href=\'api/transfer.php\'"
                                onmouseover="this.style.background=\'#bebebe\'; this.style.boxShadow=\'0 22px 40px rgba(0,0,0,0.1)\'; this.querySelector(\'div\').style.visibility=\'visible\'" 
                                onmouseout="this.style.background=\'#dedede\'; this.style.boxShadow=\'none\'; this.querySelector(\'div\').style.visibility=\'hidden\'">
                                <img src="/sources/icons/bulk/convert-card.svg" style="width: 24px; opacity: 0.7;">
                                <div style="visibility: hidden; position: absolute; top: -32px; left: 50%; transform: translateX(-50%); background: #333; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; white-space: nowrap;">Transfer</div>
                            </div>
                        </div>
                    </div>
         
                    <!-- Jeton Kartı - 2x2 Grid -->
                    <div style="border: 1px solid #dedede; padding: 20px; border-radius: 15px; position: relative; grid-column: span 2; grid-row: span 2;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h3 style="font-size: 18px;">Jetonlar</h3>
                            <img src="/sources/icons/bulk/coin.svg" style="width: 24px; opacity: 0.7;">
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <p style="font-size: 48px; font-weight: 600;">' . $userData["coins"] . ' 🪙</p>
                        </div>
                        
                        <!-- Son Jeton İşlemleri -->
                        <div style="margin-top: 20px;">
                            <h4 style="font-size: 14px; margin-bottom: 10px;">Son Jeton İşlemleri</h4>
                            <div style="max-height: 150px; overflow-y: auto;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr>
                                            <th style="text-align: left; padding: 8px 4px; border-bottom: 1px solid #eee; font-size: 12px;">İşlem No</th>
                                            <th style="text-align: left; padding: 8px 4px; border-bottom: 1px solid #eee; font-size: 12px;">İşlem</th>
                                            <th style="text-align: right; padding: 8px 4px; border-bottom: 1px solid #eee; font-size: 12px;">Miktar</th>
                                            <th style="text-align: center; padding: 8px 4px; border-bottom: 1px solid #eee; font-size: 12px;">Durum</th>
                                            <th style="text-align: right; padding: 8px 4px; border-bottom: 1px solid #eee; font-size: 12px;">Tarih</th>
                                        </tr>
                                    </thead>
                                    <tbody>';

            // Jeton işlemlerini listele
            foreach ($coinTransactions as $t) {
                $amount = (int) $t['amount'];
                $color = '#666'; // Default color
                $sign = '';

                if ($t['transaction_type'] === 'COINS_RECEIVED') {
                    $color = '#22c55e';
                    $sign = '+';
                } else if ($t['transaction_type'] === 'COINS_USED') {
                    $color = '#ef4444';
                    $sign = '-';
                }

                $statusColor = '';
                switch ($t['status']) {
                    case 'COMPLETED':
                        $statusColor = '#22c55e';
                        break;
                    case 'PENDING':
                        $statusColor = '#f59e0b';
                        break;
                    case 'FAILED':
                        $statusColor = '#ef4444';
                        break;
                    case 'CANCELLED':
                        $statusColor = '#6b7280';
                        break;
                }

                $html .= '<tr>
                    <td style="padding: 8px 4px; border-bottom: 1px solid #eee; font-size: 12px;">' . $t['transaction_id'] . '</td>
                    <td style="padding: 8px 4px; border-bottom: 1px solid #eee; font-size: 12px;">' . ucfirst($t['transaction_type']) . '</td>
                    <td style="padding: 8px 4px; border-bottom: 1px solid #eee; font-size: 12px; text-align: right; color: ' . $color . ';">' . $sign . $amount . ' 🪙</td>
                    <td style="padding: 8px 4px; border-bottom: 1px solid #eee; font-size: 12px; text-align: center;">
                        <span style="color: ' . $statusColor . ';">' . ucfirst(strtolower($t['status'])) . '</span>
                    </td>
                    <td style="padding: 8px 4px; border-bottom: 1px solid #eee; font-size: 12px; text-align: right;">' . date('d.m.Y H:i', strtotime($t['created_at'])) . '</td>
                </tr>';
            }

            $html .= '</tbody>
                                </table>
                            </div>
                        </div>
         
                        <div style="position: absolute; bottom: 20px; right: 20px;">
                            <div style="position: relative; cursor: pointer; background: #dedede; padding: 8px; border-radius: 8px; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center;" 
                                onmouseover="this.style.background=\'#bebebe\'; this.style.boxShadow=\'0 22px 40px rgba(0,0,0,0.1)\'; this.querySelector(\'div\').style.visibility=\'visible\'" 
                                onmouseout="this.style.background=\'#dedede\'; this.style.boxShadow=\'none\'; this.querySelector(\'div\').style.visibility=\'hidden\'">
                                <img src="/sources/icons/bulk/box-tick.svg" style="width: 24px; opacity: 0.7;">
                                <div style="visibility: hidden; position: absolute; top: -32px; left: 50%; transform: translateX(-50%); background: #333; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; white-space: nowrap;">Jetonları Kullan</div>
                            </div>
                        </div>
                    </div>
         
                    <!-- Abonelik Kartı - 2x2 Grid -->
                    <div style="border: 1px solid #dedede; padding: 20px; border-radius: 15px; position: relative; grid-column: span 4; grid-row: span 2;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h3 style="font-size: 18px;">Aktif Abonelikler</h3>
                            <img src="/sources/icons/bulk/timer-1.svg" style="width: 24px; opacity: 0.7;">
                        </div>
                        
                        <!-- Abonelik Listesi -->
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">';

            if (count($subscriptions) > 0) {
                foreach ($subscriptions as $s) {
                    $html .= '<div style="border: 1px solid #eee; padding: 15px; border-radius: 10px;">
                                <div style="font-weight: 600; margin-bottom: 5px;">' . htmlspecialchars($s['subscription_name']) . '</div>
                                <div style="font-size: 12px; color: #666;">Yenileme: ' . date('d F Y', strtotime($s['next_billing_date'])) . '</div>
                                <div style="font-size: 14px; margin-top: 5px;">₺' . number_format($s['price'], 2) . '/' . ($s['billing_period'] == 'MONTHLY' ? 'ay' : 'yıl') . '</div>
                            </div>';
                }
            } else {
                $html .= '<div style="grid-column: span 4; text-align: center; color: #666; padding: 20px;">
                            Aktif abonelik bulunmuyor
                        </div>';
            }

            $html .= '</div></div></div></div>';

            return $html;

        case 'transactions':
            // Toplam işlem sayısını al
            $stmt = $db->prepare("
                    SELECT COUNT(*) as total
                    FROM transactions t
                    WHERE t.sender_id = ? OR t.receiver_id = ?
                ");
            $stmt->execute([$userData['user_id'], $userData['user_id']]);
            $totalCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Sayfa başına gösterilecek işlem sayısı
            $perPage = 15;

            // Mevcut sayfa numarası
            $currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
            $currentPage = max(1, $currentPage);

            // Toplam sayfa sayısı
            $totalPages = ceil($totalCount / $perPage);

            // LIMIT ve OFFSET değerleri
            $offset = ($currentPage - 1) * $perPage;

            // İşlemleri çek
            $stmt = $db->prepare("
                    SELECT t.*, 
                           u_sender.username as sender_username,
                           u_receiver.username as receiver_username,
                           CASE 
                               WHEN t.sender_id = ? THEN 'outgoing'
                               ELSE 'incoming'
                           END as direction,
                           CASE 
                               WHEN t.transaction_type IN ('DEPOSIT', 'TRANSFER') THEN 'money'
                               ELSE 'coin'
                           END as type
                    FROM transactions t
                    LEFT JOIN users u_sender ON t.sender_id = u_sender.user_id
                    LEFT JOIN users u_receiver ON t.receiver_id = u_receiver.user_id
                    WHERE t.sender_id = ? OR t.receiver_id = ?
                    ORDER BY t.created_at DESC
                    LIMIT ? OFFSET ?
                ");
            $stmt->execute([$userData['user_id'], $userData['user_id'], $userData['user_id'], $perPage, $offset]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $html = '<div style="margin-left: 340px; padding: 30px;">
                    <h2 style="font-size: 24px; margin-bottom: 30px;">İşlem Geçmişi</h2>
                    
                    <div style="background: white; border: 1px solid #dedede; border-radius: 15px; padding: 20px;">
                        <div style="max-height: 800px; overflow-y: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align: left; padding: 12px 8px; border-bottom: 2px solid #eee; font-size: 14px;">İşlem No</th>
                                        <th style="text-align: left; padding: 12px 8px; border-bottom: 2px solid #eee; font-size: 14px;">İşlem</th>
                                        <th style="text-align: left; padding: 12px 8px; border-bottom: 2px solid #eee; font-size: 14px;">Açıklama</th>
                                        <th style="text-align: right; padding: 12px 8px; border-bottom: 2px solid #eee; font-size: 14px;">Tutar</th>
                                        <th style="text-align: center; padding: 12px 8px; border-bottom: 2px solid #eee; font-size: 14px;">Durum</th>
                                        <th style="text-align: right; padding: 12px 8px; border-bottom: 2px solid #eee; font-size: 14px;">Tarih</th>
                                    </tr>
                                </thead>
                                <tbody>';

            foreach ($transactions as $t) {
                // First determine if it's a coin or money transaction
                $isCoins = in_array($t['transaction_type'], ['COINS_RECEIVED', 'COINS_USED']);

                // İşlem tutarı formatı
                $amount = $isCoins ? (int) $t['amount'] . ' 🪙' : '₺' . number_format($t['amount'], 2);

                // Default color for fallback
                $color = '#666';
                $sign = '';

                if ($isCoins) {
                    // Handle coin transactions
                    if ($t['transaction_type'] === 'COINS_RECEIVED') {
                        $color = '#22c55e';
                        $sign = '+';
                    } else if ($t['transaction_type'] === 'COINS_USED') {
                        $color = '#ef4444';
                        $sign = '-';
                    }
                } else {
                    // Handle money transactions
                    switch ($t['transaction_type']) {
                        case 'DEPOSIT':
                            $color = '#22c55e';
                            $sign = '+';
                            break;
                        case 'WITHDRAWAL':
                        case 'PAYMENT':
                            $color = '#ef4444';
                            $sign = '-';
                            break;
                        case 'TRANSFER':
                            if ($t['direction'] === 'incoming') {
                                $color = '#22c55e';
                                $sign = '+';
                            } else {
                                $color = '#ef4444';
                                $sign = '-';
                            }
                            break;
                    }
                }

                // Durum rengi
                $statusColor = '';
                switch ($t['status']) {
                    case 'COMPLETED':
                        $statusColor = '#22c55e';
                        break;
                    case 'PENDING':
                        $statusColor = '#f59e0b';
                        break;
                    case 'FAILED':
                        $statusColor = '#ef4444';
                        break;
                    case 'CANCELLED':
                        $statusColor = '#6b7280';
                        break;
                    default:
                        $statusColor = '#666'; // Default color
                }

                // İşlem açıklaması
                $description = '';
                switch ($t['transaction_type']) {
                    case 'DEPOSIT':
                        $description = 'Para Yatırma';
                        break;
                    case 'WITHDRAWAL':
                        $description = 'Para Çekme';
                        break;
                    case 'PAYMENT':
                        $description = 'Ödeme';
                        break;
                    case 'TRANSFER':
                        $description = $t['direction'] == 'outgoing' ?
                            'Transfer: ' . htmlspecialchars($t['receiver_username']) :
                            'Transfer: ' . htmlspecialchars($t['sender_username']);
                        break;
                    case 'COINS_RECEIVED':
                        $description = 'Jeton Alındı';
                        break;
                    case 'COINS_USED':
                        $description = 'Jeton Kullanıldı';
                        break;
                    default:
                        $description = $t['description'] ?? 'Diğer İşlem';
                }

                $html .= '<tr>
                                        <td style="padding: 12px 8px; border-bottom: 1px solid #eee; font-size: 14px;">' . $t['transaction_id'] . '</td>
                                        <td style="padding: 12px 8px; border-bottom: 1px solid #eee; font-size: 14px;">' . ucfirst($t['transaction_type']) . '</td>
                                        <td style="padding: 12px 8px; border-bottom: 1px solid #eee; font-size: 14px;">' . $description . '</td>
                                        <td style="padding: 12px 8px; border-bottom: 1px solid #eee; font-size: 14px; text-align: right; color: ' . $color . ';">' . $sign . $amount . '</td>
                                        <td style="padding: 12px 8px; border-bottom: 1px solid #eee; font-size: 14px; text-align: center;">
                                            <span style="color: ' . $statusColor . ';">' . ucfirst(strtolower($t['status'])) . '</span>
                                        </td>
                                        <td style="padding: 12px 8px; border-bottom: 1px solid #eee; font-size: 14px; text-align: right;">' . date('d.m.Y H:i', strtotime($t['created_at'])) . '</td>
                                    </tr>';
            }

            $html .= '</tbody>
                            </table>
                        </div>';

            // Pagination
            if ($totalPages > 1) {
                $html .= '<div style="display: flex; justify-content: center; gap: 10px; margin-top: 20px;">';

                // Önceki sayfa
                if ($currentPage > 1) {
                    $html .= '<a href="?section=transactions&page=' . ($currentPage - 1) . '" style="padding: 8px 12px; border: 1px solid #dedede; border-radius: 8px; text-decoration: none; color: #4F46E5;">&laquo; Önceki</a>';
                }

                // Sayfa numaraları
                for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++) {
                    $isActive = $i === $currentPage;
                    $html .= '<a href="?section=transactions&page=' . $i . '" style="padding: 8px 12px; border: 1px solid #dedede; border-radius: 8px; text-decoration: none; ' .
                        ($isActive ? 'background: #4F46E5; color: white;' : 'color: #4F46E5;') .
                        '">' . $i . '</a>';
                }

                // Sonraki sayfa
                if ($currentPage < $totalPages) {
                    $html .= '<a href="?section=transactions&page=' . ($currentPage + 1) . '" style="padding: 8px 12px; border: 1px solid #dedede; border-radius: 8px; text-decoration: none; color: #4F46E5;">Sonraki &raquo;</a>';
                }

                $html .= '</div>';
            }

            $html .= '</div></div>';

            return $html;

        case 'subscriptions':
            // Fetch user's subscriptions
            $stmt = $db->prepare("
                    SELECT * FROM subscriptions 
                    WHERE user_id = ?
                    ORDER BY status = 'ACTIVE' DESC, start_date DESC
                ");
            $stmt->execute([$userData['user_id']]);
            $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $html = '<div style="margin-left: 340px; padding: 30px;">
                    <h2 style="font-size: 24px; margin-bottom: 30px;">Aboneliklerim</h2>';

            if (count($subscriptions) > 0) {
                $html .= '<div style="background: white; border: 1px solid #dedede; border-radius: 15px; padding: 20px;">
                        <div style="max-height: 800px; overflow-y: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align: left; padding: 12px 8px; border-bottom: 2px solid #eee; font-size: 14px;">Abonelik</th>
                                        <th style="text-align: left; padding: 12px 8px; border-bottom: 2px solid #eee; font-size: 14px;">Fiyat</th>
                                        <th style="text-align: center; padding: 12px 8px; border-bottom: 2px solid #eee; font-size: 14px;">Fatura Dönemi</th>
                                        <th style="text-align: center; padding: 12px 8px; border-bottom: 2px solid #eee; font-size: 14px;">Durum</th>
                                        <th style="text-align: right; padding: 12px 8px; border-bottom: 2px solid #eee; font-size: 14px;">Başlangıç Tarihi</th>
                                        <th style="text-align: right; padding: 12px 8px; border-bottom: 2px solid #eee; font-size: 14px;">Sonraki Ödeme</th>
                                        <th style="text-align: center; padding: 12px 8px; border-bottom: 2px solid #eee; font-size: 14px;">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>';

                foreach ($subscriptions as $sub) {
                    // Calculate remaining time and status text
                    $nextBillingDate = new DateTime($sub['next_billing_date']);
                    $now = new DateTime();
                    $interval = $now->diff($nextBillingDate);

                    $remainingText = '';
                    $statusColor = '';
                    $actionButton = '';

                    switch ($sub['status']) {
                        case 'ACTIVE':
                            $statusColor = '#22c55e';
                            $remainingText = $interval->days . ' gün sonra yenilenecek';
                            $actionButton = '<button onclick="cancelSubscription(' . $sub['subscription_id'] . ')" 
                                    style="padding: 6px 12px; background: #ef4444; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 12px;">
                                    İptal Et
                                </button>';
                            break;
                        case 'CANCELLED':
                            $statusColor = '#6b7280';
                            $remainingText = $interval->days . ' gün sonra sona erecek';
                            break;
                        case 'EXPIRED':
                            $statusColor = '#ef4444';
                            $remainingText = 'Sona erdi';
                            break;
                    }

                    $html .= '<tr>
                            <td style="padding: 12px 8px; border-bottom: 1px solid #eee; font-size: 14px;">
                                ' . htmlspecialchars($sub['subscription_name']) . '
                            </td>
                            <td style="padding: 12px 8px; border-bottom: 1px solid #eee; font-size: 14px;">
                                ₺' . number_format($sub['price'], 2) . '/' . ($sub['billing_period'] == 'MONTHLY' ? 'ay' : 'yıl') . '
                            </td>
                            <td style="padding: 12px 8px; border-bottom: 1px solid #eee; font-size: 14px; text-align: center;">
                                ' . ($sub['billing_period'] == 'MONTHLY' ? 'Aylık' : 'Yıllık') . '
                            </td>
                            <td style="padding: 12px 8px; border-bottom: 1px solid #eee; font-size: 14px; text-align: center;">
                                <span style="color: ' . $statusColor . ';">' .
                        ($sub['status'] == 'ACTIVE' ? 'Aktif' :
                            ($sub['status'] == 'CANCELLED' ? 'İptal Edildi' : 'Sona Erdi')) .
                        '</span><br>
                                <span style="font-size: 12px; color: #666;">' . $remainingText . '</span>
                            </td>
                            <td style="padding: 12px 8px; border-bottom: 1px solid #eee; font-size: 14px; text-align: right;">
                                ' . date('d.m.Y H:i', strtotime($sub['start_date'])) . '
                            </td>
                            <td style="padding: 12px 8px; border-bottom: 1px solid #eee; font-size: 14px; text-align: right;">
                                ' . date('d.m.Y H:i', strtotime($sub['next_billing_date'])) . '
                            </td>
                            <td style="padding: 12px 8px; border-bottom: 1px solid #eee; font-size: 14px; text-align: center;">
                                ' . $actionButton . '
                            </td>
                        </tr>';
                }

                $html .= '</tbody>
                            </table>
                        </div>';

                $html .= '<script>
                        function cancelSubscription(subscriptionId) {
                            if (confirm("Aboneliğinizi iptal etmek istediğinizden emin misiniz? Mevcut dönem sonuna kadar hizmetlerden yararlanmaya devam edeceksiniz.")) {
                                fetch("/public/components/wallet/api/cancel-subscription.php", {
                                    method: "POST",
                                    headers: {
                                        "Content-Type": "application/json"
                                    },
                                    body: JSON.stringify({
                                        subscription_id: subscriptionId
                                    })
                                })
                                .then(response => {
                                    if (!response.ok) {
                                        throw new Error("Sunucu yanıtı hatalı: " + response.status);
                                    }
                                    return response.json();
                                })
                                .then(data => {
                                    if (data.success) {
                                        alert("Aboneliğiniz başarıyla iptal edildi. Dönem sonuna kadar hizmetlerden yararlanmaya devam edebilirsiniz.");
                                        window.location.reload();
                                    } else {
                                        alert("Bir hata oluştu: " + data.message);
                                    }
                                })
                                .catch(error => {
                                    console.error("Hata:", error);
                                    alert("İşlem sırasında bir hata oluştu. Lütfen daha sonra tekrar deneyin.");
                                });
                            }
                        }
                        </script>';

            } else {
                $html .= '<div style="text-align: center; padding: 40px; background: white; border: 1px solid #dedede; border-radius: 15px;">
                        <img src="/sources/icons/bulk/empty-wallet.svg" style="width: 64px; opacity: 0.7; margin-bottom: 20px;">
                        <p style="color: #666; margin-bottom: 20px;">Henüz aktif bir aboneliğiniz bulunmuyor.</p>
                        <a href="?section=upgrade" style="display: inline-block; padding: 12px 24px; background: #4F46E5; color: white; text-decoration: none; border-radius: 8px; transition: all 0.3s ease;">
                            Paketleri İncele
                        </a>
                    </div>';
            }

            $html .= '</div></div>';

            return $html;

        case 'upgrade':
            $stmt = $db->prepare("SELECT subscription_plan FROM users WHERE user_id = ?");
            $stmt->execute([$userData['user_id']]);
            $activePlan = $stmt->fetch(PDO::FETCH_ASSOC)['subscription_plan'];

            $pricing = [
                'basic' => [
                    'name' => 'Basic',
                    'monthly' => 0,
                    'yearly' => 0,
                    'features' => [
                        'Temel özellikler',
                        'Günlük 3 ilan sınırı',
                        'Standart sıralama',
                        'Email desteği'
                    ]
                ],
                'id_plus' => [
                    'name' => 'ID+',
                    'monthly' => 199,
                    'yearly' => 1552.20, // 199 * 12 * 0.65 (35% discount)
                    'features' => [
                        'Tüm temel özellikler',
                        'Sınırsız ilan hakkı',
                        'Öncelikli sıralama',
                        '⭐ ID+ Rozeti',
                        '7/24 öncelikli destek',
                        'Detaylı istatistikler'
                    ]
                ],
                'id_plus_pro' => [
                    'name' => 'ID+ Pro',
                    'monthly' => 499,
                    'yearly' => 3892.20, // 499 * 12 * 0.65 (35% discount)
                    'features' => [
                        'Tüm ID+ özellikleri',
                        'En üst sırada gösterim',
                        '👑 Pro Rozeti',
                        'VIP destek hattı',
                        'Gelişmiş analitikler',
                        'Özel API erişimi',
                        'Reklamsız deneyim'
                    ]
                ]
            ];

            foreach ($pricing as $plan => $details) {
                if ($plan !== 'basic') {
                    $pricing[$plan]['yearly_discounted'] = $details['monthly'] * 10;
                    $pricing[$plan]['monthly_in_yearly'] = $pricing[$plan]['yearly_discounted'] / 12;
                    $pricing[$plan]['yearly_savings'] = ($details['monthly'] * 12) - $pricing[$plan]['yearly_discounted'];
                }
            }

            $stmt = $db->prepare("SELECT COUNT(*) as total_users FROM users");
            $stmt->execute();
            $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];

            $stmt = $db->prepare("SELECT COUNT(*) as premium_users FROM users WHERE subscription_plan != 'basic'");
            $stmt->execute();
            $premiumUsers = $stmt->fetch(PDO::FETCH_ASSOC)['premium_users'];

            $html = '<!DOCTYPE html>
            <html>
            <head>
                <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
                <style>
                    .billing-switch {
                        position: relative;
                        display: inline-block;
                        width: 44px;
                        height: 24px;
                    }
                    .billing-switch input {
                        opacity: 0;
                        width: 0;
                        height: 0;
                    }
                    .slider {
                        position: absolute;
                        cursor: pointer;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background-color: #ccc;
                        transition: .3s;
                        border-radius: 34px;
                    }
                    .slider:before {
                        position: absolute;
                        content: "";
                        height: 18px;
                        width: 18px;
                        left: 3px;
                        bottom: 3px;
                        background-color: white;
                        transition: .3s;
                        border-radius: 50%;
                    }
                    input:checked + .slider {
                        background-color: #4F46E5;
                    }
                    input:checked + .slider:before {
                        transform: translateX(20px);
                    }
                    .feature-item {
                        transition: all 0.3s ease;
                    }
                    .feature-item:hover {
                        transform: translateX(5px);
                    }
                    .price-card {
                        transition: all 0.3s ease;
                    }
                    .price-card:hover:not(.highlighted):not(:has(button[disabled])) {
                        transform: translateY(-5px);
                        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                    }
                </style>
            </head>
            <body>
            <div style="margin-left: 340px; padding: 30px;">
                <h2 style="font-size: 22px; font-weight: 600; margin-bottom: 24px;">ID+ Paketleri</h2>
                
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
                    <div style="background: white; border: 1px solid #dedede; border-radius: 12px; padding: 16px;">
                        <div style="font-size: 13px; color: #666; margin-bottom: 8px;">Toplam Kullanıcı</div>
                        <div style="font-size: 20px; font-weight: 600;">' . number_format($totalUsers) . '+</div>
                    </div>
                    <div style="background: white; border: 1px solid #dedede; border-radius: 12px; padding: 16px;">
                        <div style="font-size: 13px; color: #666; margin-bottom: 8px;">Aktif Premium</div>
                        <div style="font-size: 20px; font-weight: 600;">' . number_format($premiumUsers) . '+</div>
                    </div>
                    <div style="background: white; border: 1px solid #dedede; border-radius: 12px; padding: 16px;">
                        <div style="font-size: 13px; color: #666; margin-bottom: 8px;">Ortalama Tasarruf</div>
                        <div style="font-size: 20px; font-weight: 600;">₺' . number_format($pricing['id_plus']['yearly_savings']) . '/yıl</div>
                    </div>
                    <div style="background: white; border: 1px solid #dedede; border-radius: 12px; padding: 16px;">
                        <div style="font-size: 13px; color: #666; margin-bottom: 8px;">Müşteri Memnuniyeti</div>
                        <div style="font-size: 20px; font-weight: 600;">4.9/5.0 ⭐</div>
                    </div>
                </div>
            
                <div style="text-align: center; margin-bottom: 24px; background: white; padding: 16px; border-radius: 12px; border: 1px solid #dedede;">
                    <div style="display: inline-flex; align-items: center; gap: 12px; background: #f8f8f8; padding: 4px; border-radius: 30px;">
                        <label id="monthlyLabel" style="font-size: 13px; padding: 8px 12px; border-radius: 20px; cursor: pointer; transition: all 0.3s ease; background: #4F46E5; color: white;">Aylık</label>
                        <label class="billing-switch">
                            <input type="checkbox" id="billingToggle">
                            <span class="slider"></span>
                        </label>
                        <label id="yearlyLabel" style="font-size: 13px; padding: 8px 12px; border-radius: 20px; cursor: pointer; transition: all 0.3s ease;">
                            Yıllık <span style="background: #22c55e; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 4px;">35% İndirim</span>
                        </label>
                    </div>
                </div>
            
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0;">';

            foreach ($pricing as $planKey => $plan) {
                $isActive = $activePlan === $planKey;
                $isHighlighted = $planKey === 'id_plus';

                $cardStyle = $isHighlighted
                    ? 'background: white; border: 2px solid #4F46E5; padding: 24px; border-radius: 12px; position: relative; transform: scale(1.02); box-shadow: 0 8px 30px rgba(79, 70, 229, 0.1);'
                    : 'background: white; border: 1px solid #dedede; padding: 24px; border-radius: 12px; position: relative;';

                $html .= '<div class="price-card" style="' . $cardStyle . '">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                                <div>
                                    <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 8px; ' . ($planKey !== 'basic' ? 'color: ' . ($planKey === 'id_plus' ? '#4F46E5' : '#FDB931') : '') . '">' . $plan['name'] . '</h3>';

                if ($isHighlighted) {
                    $html .= '<span style="font-size: 11px; background: #4F46E5; color: white; padding: 4px 12px; border-radius: 20px;">🎯 Tavsiye Edilen</span>';
                } elseif ($planKey === 'id_plus_pro') {
                    $html .= '<span style="font-size: 11px; background: #FDB931; color: white; padding: 4px 12px; border-radius: 20px;">🚀 Premium</span>';
                }

                $html .= '</div></div>
                    
                    <div style="margin: 24px 0;">
            <div class="price-monthly" style="display: block;">
                <div style="font-size: 32px; font-weight: 700; ' . ($planKey !== 'basic' ? 'color: ' . ($planKey === 'id_plus' ? '#4F46E5' : '#FDB931') : '') . ';">
                    ' . ($plan['monthly'] === 0 ? 'Ücretsiz' : '₺' . number_format($plan['monthly'], 2)) . '
                    <span style="font-size: 14px; color: #666;">/ay</span>
                </div>
            </div>
            <div class="price-yearly" style="display: none;">
                <div style="font-size: 32px; font-weight: 700; ' . ($planKey !== 'basic' ? 'color: ' . ($planKey === 'id_plus' ? '#4F46E5' : '#FDB931') : '') . ';">
                    ' . ($plan['yearly'] === 0 ? 'Ücretsiz' : '₺' . number_format($plan['yearly'] / 12, 2)) . '
                    <span style="font-size: 14px; color: #666;">/ay</span>
                </div>
                ' . ($planKey !== 'basic' ? '<div style="font-size: 13px; color: #22c55e;">Yıllık ödemede 35% İndirim</div>' : '') . '
            </div>
        </div>
            
                    <div style="margin-bottom: 24px;">';

                foreach ($plan['features'] as $feature) {
                    $html .= '<div class="feature-item" style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                            <img src="/sources/icons/bulk/tick-circle.svg" style="width: 16px; opacity: 0.7;">
                            <span style="font-size: 14px;">' . $feature . '</span>
                        </div>';
                }

                $html .= '</div>';

                if ($isActive) {
                    $html .= '<button disabled style="width: 100%; padding: 12px; background: #f3f4f6; color: #666; border: none; border-radius: 8px; cursor: not-allowed; font-size: 14px; font-weight: 500;">Mevcut Plan</button>';
                } else {
                    $buttonColor = $planKey === 'id_plus' ? '#4F46E5' : ($planKey === 'id_plus_pro' ? '#FDB931' : '#4F46E5');
                    $buttonText = $planKey === 'basic' ? 'Ücretsiz Başla' : ($planKey === 'id_plus' ? 'Hemen Başla' : 'Pro\'ya Yükselt');

                    $html .= '<button onclick="window.location.href=\'subscription_checkout.php?plan=' . $planKey . '\' + (document.getElementById(\'billingToggle\').checked ? \'&period=yearly\' : \'\')" 
                    style="width: 100%; padding: 12px; background: ' . $buttonColor . '; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.3s ease;">' .
                        $buttonText . '</button>';
                }

                $html .= '</div>';
            }

            $html .= '</div>
            
                <div style="margin-top: 40px; padding: 24px; background: white; border: 1px solid #dedede; border-radius: 12px;">
                    <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 20px;">Özellik Karşılaştırması</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="text-align: left; padding: 12px 16px; border-bottom: 2px solid #eee; font-size: 13px; color: #666;">Özellik</th>
                                <th style="text-align: center; padding: 12px 16px; border-bottom: 2px solid #eee; font-size: 13px; color: #666;">Basic</th>
                                <th style="text-align: center; padding: 12px 16px; border-bottom: 2px solid #eee; font-size: 13px; color: #4F46E5;">ID+</th>
                                <th style="text-align: center; padding: 12px 16px; border-bottom: 2px solid #eee; font-size: 13px; color: #FDB931;">ID+ Pro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">Günlük İlan Hakkı</td>
                                <td style="text-align: center; padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">3</td>
<td style="text-align: center; padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">Sınırsız</td>
                    <td style="text-align: center; padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">Sınırsız</td>
                </tr>
                <tr>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">İlan Sıralaması</td>
                    <td style="text-align: center; padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">Standart</td>
                    <td style="text-align: center; padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">Öncelikli</td>
                    <td style="text-align: center; padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">En Üst</td>
                </tr>
                <tr>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">Müşteri Desteği</td>
                    <td style="text-align: center; padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">Email</td>
                    <td style="text-align: center; padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">7/24 Öncelikli</td>
                    <td style="text-align: center; padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">VIP</td>
                </tr>
                <tr>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">İstatistikler</td>
                    <td style="text-align: center; padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">Temel</td>
                    <td style="text-align: center; padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">Detaylı</td>
                    <td style="text-align: center; padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">Gelişmiş</td>
                </tr>
                <tr>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">Profil Rozeti</td>
                    <td style="text-align: center; padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">-</td>
                    <td style="text-align: center; padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">⭐</td>
                    <td style="text-align: center; padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">👑</td>
                </tr>
                <tr>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">API Erişimi</td>
                    <td style="text-align: center; padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">-</td>
                    <td style="text-align: center; padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">-</td>
                    <td style="text-align: center; padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">✓</td>
                </tr>
                <tr>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">Reklamsız Deneyim</td>
                    <td style="text-align: center; padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">-</td>
                    <td style="text-align: center; padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">-</td>
                    <td style="text-align: center; padding: 12px 16px; border-bottom: 1px solid #eee; font-size: 13px;">✓</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div style="margin-top: 40px; padding: 24px; background: white; border: 1px solid #dedede; border-radius: 12px;">
        <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 20px;">Sıkça Sorulan Sorular</h3>
        <div style="display: grid; gap: 16px;">
            <div style="border-bottom: 1px solid #eee; padding-bottom: 16px;">
                <h4 style="font-size: 14px; font-weight: 500; margin-bottom: 8px;">Premium üyelik ne zaman başlar?</h4>
                <p style="font-size: 13px; color: #666; line-height: 1.5;">Ödemeniz onaylandıktan hemen sonra premium özellikler hesabınıza tanımlanır.</p>
            </div>
            <div style="border-bottom: 1px solid #eee; padding-bottom: 16px;">
                <h4 style="font-size: 14px; font-weight: 500; margin-bottom: 8px;">İstediğim zaman iptal edebilir miyim?</h4>
                <p style="font-size: 13px; color: #666; line-height: 1.5;">Evet, aboneliğinizi dilediğiniz zaman iptal edebilirsiniz. İptal durumunda dönem sonuna kadar premium özelliklerden yararlanmaya devam edersiniz.</p>
            </div>
            <div style="border-bottom: 1px solid #eee; padding-bottom: 16px;">
                <h4 style="font-size: 14px; font-weight: 500; margin-bottom: 8px;">Paketler arasında geçiş yapabilir miyim?</h4>
                <p style="font-size: 13px; color: #666; line-height: 1.5;">Evet, dilediğiniz zaman paketinizi yükseltebilir veya düşürebilirsiniz. Fiyat farkı bir sonraki faturanıza yansıtılır.</p>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById("billingToggle").addEventListener("change", function() {
    const monthlyLabel = document.getElementById("monthlyLabel");
    const yearlyLabel = document.getElementById("yearlyLabel");
    const monthlyPrices = document.getElementsByClassName("price-monthly");
    const yearlyPrices = document.getElementsByClassName("price-yearly");
    
    if (this.checked) {
        monthlyLabel.style.background = "transparent";
        monthlyLabel.style.color = "#666";
        yearlyLabel.style.background = "#4F46E5";
        yearlyLabel.style.color = "white";
        
        Array.from(monthlyPrices).forEach(el => el.style.display = "none");
        Array.from(yearlyPrices).forEach(el => el.style.display = "block");
    } else {
        monthlyLabel.style.background = "#4F46E5";
        monthlyLabel.style.color = "white";
        yearlyLabel.style.background = "transparent";
        yearlyLabel.style.color = "#666";
        
        Array.from(monthlyPrices).forEach(el => el.style.display = "block");
        Array.from(yearlyPrices).forEach(el => el.style.display = "none");
    }
});
</script>
</body>
</html>';

            return $html;
        default:
            return '<div style="margin-left: 340px; padding: 30px;">Sayfa bulunamadı.</div>';
    }
}
?>
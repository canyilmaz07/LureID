<?php
// transfer.php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../languages/language_handler.php';

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

// AJAX user search endpoint
if (isset($_GET['search'])) {
    try {
        $dbConfig = require '../../../../config/database.php';
        $db = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['options']
        );

        $search = '%' . $_GET['search'] . '%';
        $stmt = $db->prepare("
            SELECT u.user_id, u.username, u.email, u.full_name, ued.profile_photo_url 
            FROM users u
            LEFT JOIN user_extended_details ued ON u.user_id = ued.user_id
            WHERE u.username LIKE ? AND u.user_id != ?
            LIMIT 5
        ");
        $stmt->execute([$search, $_SESSION['user_id']]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'users' => $users]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

function generateTransactionId() {
    return mt_rand(10000000000, 99999999999);
}

function validateTransactionId($db, $transactionId) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM transactions WHERE transaction_id = ?");
        $stmt->execute([$transactionId]);
        return $stmt->fetchColumn() == 0;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form inputs
    if (empty($_POST['receiverUsername']) || empty($_POST['amount'])) {
        echo json_encode(['success' => false, 'message' => 'Tüm alanları doldurun']);
        exit;
    }

    $amount = floatval($_POST['amount']);
    if ($amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Geçersiz transfer tutarı']);
        exit;
    }

    try {
        $dbConfig = require '../../../../config/database.php';
        $db = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['options']
        );

        // Check if receiver exists
        $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->execute([$_POST['receiverUsername']]);
        $receiver = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$receiver) {
            echo json_encode(['success' => false, 'message' => 'Alıcı kullanıcı bulunamadı']);
            exit;
        }

        if ($receiver['user_id'] == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Kendinize transfer yapamazsınız']);
            exit;
        }

        // Check sender's balance
        $stmt = $db->prepare("SELECT balance FROM wallet WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $senderBalance = $stmt->fetch(PDO::FETCH_ASSOC)['balance'];

        if ($senderBalance < $amount) {
            echo json_encode(['success' => false, 'message' => 'Yetersiz bakiye']);
            exit;
        }

        $db->beginTransaction();

        do {
            $transactionId = generateTransactionId();
        } while (!validateTransactionId($db, $transactionId));

        // Update sender's wallet
        $stmt = $db->prepare("
            UPDATE wallet 
            SET balance = balance - ?,
                updated_at = CURRENT_TIMESTAMP,
                last_transaction_date = CURRENT_TIMESTAMP
            WHERE user_id = ?
        ");
        $stmt->execute([$amount, $_SESSION['user_id']]);

        // Update receiver's wallet
        $stmt = $db->prepare("
            UPDATE wallet 
            SET balance = balance + ?,
                updated_at = CURRENT_TIMESTAMP,
                last_transaction_date = CURRENT_TIMESTAMP
            WHERE user_id = ?
        ");
        $stmt->execute([$amount, $receiver['user_id']]);

        // Record transaction
        $stmt = $db->prepare("
            INSERT INTO transactions (
                transaction_id, sender_id, receiver_id, amount, 
                transaction_type, status, description
            ) VALUES (?, ?, ?, ?, 'TRANSFER', 'COMPLETED', ?)
        ");
        $stmt->execute([
            $transactionId,
            $_SESSION['user_id'],
            $receiver['user_id'],
            $amount,
            'Transfer to ' . $_POST['receiverUsername']
        ]);

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Transfer başarıyla tamamlandı']);
    } catch (Exception $e) {
        if (isset($db)) {
            $db->rollBack();
        }
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'İşlem başarısız: ' . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('Para Transferi') ?></title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        .transfer-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .transfer-card {
            background: white;
            border: 1px solid #dedede;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
            position: relative;
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

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #dedede;
            border-radius: 8px;
            margin-top: 5px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: none;
        }

        .user-item {
            padding: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.3s ease;
        }

        .user-item:hover {
            background: #f3f4f6;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-weight: 500;
            font-size: 14px;
        }

        .user-username {
            color: #666;
            font-size: 12px;
        }

        .selected-user {
            margin-top: 15px;
            padding: 15px;
            background: #f8f8f8;
            border-radius: 8px;
            display: none;
        }

        .selected-user-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .cancel-selection {
            padding: 5px 10px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="transfer-container">
        <h2 style="font-size: 22px; margin-bottom: 30px; font-weight: 600;"><?= __('Para Transferi') ?></h2>

        <form id="transferForm">
            <div class="transfer-card">
                <div class="form-group">
                    <label><?= __('Alıcı Kullanıcı Adı') ?></label>
                    <input type="text" id="userSearch" placeholder="Kullanıcı ara...">
                    <input type="hidden" name="receiverUsername" id="receiverUsername">
                    <div class="search-results"></div>
                    <div class="selected-user"></div>
                </div>

                <div class="form-group">
                    <label><?= __('Transfer Tutarı (₺)') ?></label>
                    <input type="number" name="amount" step="0.01" min="0.01" required>
                </div>
            </div>

            <button type="submit" class="submit-button"><?= __('Transfer Yap') ?></button>
        </form>
    </div>

    <script>
        $(document).ready(function() {
            let searchTimeout;
            const searchResults = $('.search-results');
            const userSearch = $('#userSearch');
            const selectedUser = $('.selected-user');
            
            userSearch.on('input', function() {
                clearTimeout(searchTimeout);
                const query = $(this).val();

                if (query.length < 2) {
                    searchResults.hide();
                    return;
                }

                searchTimeout = setTimeout(() => {
                    $.get('transfer.php', { search: query })
                        .done(function(response) {
                            try {
                                const data = JSON.parse(response);
                                if (data.success && data.users.length > 0) {
                                    searchResults.html('');
                                    data.users.forEach(user => {
                                        const profilePhoto = user.profile_photo_url && user.profile_photo_url !== 'undefined' 
                                            ? '/public/' + user.profile_photo_url 
                                            : '/public/profile/avatars/default.jpg';
                                        
                                        searchResults.append(`
                                            <div class="user-item" data-user='${JSON.stringify(user)}'>
                                                <img src="${profilePhoto}" alt="${user.username}" class="user-avatar">
                                                <div class="user-info">
                                                    <div class="user-name">${user.full_name}</div>
                                                    <div class="user-username">@${user.username}</div>
                                                </div>
                                            </div>
                                        `);
                                    });
                                    searchResults.show();
                                } else {
                                    searchResults.html('<div style="padding: 10px; text-align: center; color: #666;">Kullanıcı bulunamadı</div>');
                                    searchResults.show();
                                }
                            } catch (e) {
                                console.error('JSON parsing error:', e);
                            }
                        });
                }, 300);
            });

            $(document).on('click', '.user-item', function() {
                const user = $(this).data('user');
                const profilePhoto = user.profile_photo_url && user.profile_photo_url !== 'undefined' 
                    ? '/public/' + user.profile_photo_url 
                    : '/public/profile/avatars/default.jpg';
                
                userSearch.val(user.username).prop('disabled', true);
                $('#receiverUsername').val(user.username);
                searchResults.hide();
                
                selectedUser.html(`
                    <div class="selected-user-header">
                        <h4>Seçilen Kullanıcı</h4>
                        <button type="button" class="cancel-selection">İptal</button>
                    </div>
                    <div class="user-item">
                        <img src="${profilePhoto}" alt="${user.username}" class="user-avatar">
                        <div class="user-info">
                            <div class="user-name">${user.full_name}</div>
                            <div class="user-username">@${user.username}</div>
                            <div class="user-email">${user.email}</div>
                        </div>
                    </div>
                `).show();
            });

            $(document).on('click', '.cancel-selection', function(e) {
                e.preventDefault();
                userSearch.val('').prop('disabled', false);
                $('#receiverUsername').val('');
                selectedUser.hide();
            });

            // Hide search results when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.form-group').length) {
                    searchResults.hide();
                }
            });

            $('#transferForm').submit(function(e) {
                e.preventDefault();
                if (!$('#receiverUsername').val()) {
                    alert('Lütfen bir alıcı seçin');
                    return;
                }

                $.post('transfer.php', $(this).serialize())
                    .done(function(response) {
                        try {
                            let result = JSON.parse(response);
                            if (result.success) {
                                alert(result.message);
                                window.location.href = '../wallet.php?section=wallet';
                            } else {
                                alert(result.message || 'İşlem sırasında bir hata oluştu');
                            }
                        } catch (e) {
                            console.error('JSON parsing error:', e);
                            alert('İşlem sırasında bir hata oluştu');
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.error('AJAX error:', status, error);
                        alert('Bağlantı hatası oluştu. Lütfen tekrar deneyin.');
                    });
            });
        });
    </script>
</body>
</html>
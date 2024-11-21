<?php
header('Content-Type: application/json');
session_start();

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    try {
        $dbConfig = require '../../config/database.php';
        $db = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['options']
        );

        $db->beginTransaction();

        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $name = $_POST['name'] ?? '';
        $google_id = $_POST['google_id'] ?? '';
        $referralCode = $_POST['referralCode'] ?? '';

        // Generate unique user ID
        do {
            $user_id = mt_rand(100000000, 999999999);
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
        } while ($stmt->fetchColumn() > 0);

        // Username availability check
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM (
                SELECT username FROM users WHERE username = ?
                UNION
                SELECT username FROM temp_users WHERE username = ?
            ) as combined_users
        ");
        $stmt->execute([$username, $username]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Username is no longer available');
        }

        // Generate invite code
        $inviteCode = strtoupper(substr(uniqid() . bin2hex(random_bytes(8)), 0, 9));

        // Insert new user
        $stmt = $db->prepare("
            INSERT INTO users (
                user_id, username, email, full_name, google_id, 
                is_verified, created_at
            ) VALUES (?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$user_id, $username, $email, $name, $google_id]);

        // Insert invitation code
        $stmt = $db->prepare("
            INSERT INTO referral_sources (
                user_id, source_type, specific_source, is_referral_signup
            ) VALUES (?, 'ORGANIC', ?, ?)
        ");
        $stmt->execute([$user_id, $inviteCode, $referralCode ? 1 : 0]);

        // Process referral if provided
        if ($referralCode) {
            // Get inviter's user ID
            $stmt = $db->prepare("
                SELECT user_id 
                FROM referral_sources 
                WHERE specific_source = ?
            ");
            $stmt->execute([$referralCode]);
            $inviterId = $stmt->fetchColumn();

            if ($inviterId) {
                // Give coins to inviter
                $stmt = $db->prepare("
                    UPDATE wallet 
                    SET coins = coins + 50,
                        last_transaction_date = CURRENT_TIMESTAMP
                    WHERE user_id = ?
                ");
                $stmt->execute([$inviterId]);

                // Give coins to new user
                $stmt = $db->prepare("
                    UPDATE wallet 
                    SET coins = coins + 25,
                        last_transaction_date = CURRENT_TIMESTAMP
                    WHERE user_id = ?
                ");
                $stmt->execute([$user_id]);

                // Record invitation
                $stmt = $db->prepare("
                    INSERT INTO invitations (
                        inviter_id, invited_user_id, invitation_code
                    ) VALUES (?, ?, ?)
                ");
                $stmt->execute([$inviterId, $user_id, $referralCode]);
            }
        }

        $db->commit();

        // Set session
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;

        echo json_encode([
            'success' => true,
            'message' => 'Registration completed successfully'
        ]);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
}
?>
<?php
// subscription_checkout.php
session_start();
require_once '../../../config/database.php';

function calculateDiscountedPrice($basePrice, $duration)
{
    switch ($duration) {
        case '12':
            return [
                'monthly' => ($basePrice * 0.65), // %35 indirim
                'total' => $basePrice * 12 * 0.65, // Toplam fiyat
                'savings' => ($basePrice * 12) - ($basePrice * 12 * 0.65) // Tasarruf
            ];
        case '6':
            $discount = 0.25; // %25 indirim
            $discountedPrice = $basePrice * (1 - $discount);
            return [
                'monthly' => $discountedPrice,
                'total' => $discountedPrice * 6,
                'savings' => ($basePrice * 6) - ($discountedPrice * 6)
            ];
        case '3':
            $discount = 0.15; // %15 indirim
            $discountedPrice = $basePrice * (1 - $discount);
            return [
                'monthly' => $discountedPrice,
                'total' => $discountedPrice * 3,
                'savings' => ($basePrice * 3) - ($discountedPrice * 3)
            ];
        default: // 1 ay
            return [
                'monthly' => $basePrice,
                'total' => $basePrice,
                'savings' => 0
            ];
    }
}

function getSubscriptionDetails($planType)
{
    switch ($planType) {
        case 'id_plus':
            return [
                'name' => 'ID+',
                'basePrice' => 199,
                'features' => [
                    'Tüm temel özellikler',
                    'Sınırsız ilan hakkı',
                    'Öncelikli sıralama',
                    '⭐ ID+ Rozeti',
                    '7/24 öncelikli destek',
                    'Detaylı istatistikler'
                ]
            ];
        case 'id_plus_pro':
            return [
                'name' => 'ID+ Pro',
                'basePrice' => 499,
                'features' => [
                    'Tüm ID+ özellikleri',
                    'En üst sırada gösterim',
                    '👑 Pro Rozeti',
                    'VIP destek hattı',
                    'Gelişmiş analitikler',
                    'Özel API erişimi',
                    'Reklamsız deneyim'
                ]
            ];
        default:
            return null;
    }
}

$selectedPlan = $_GET['plan'] ?? '';
$planDetails = getSubscriptionDetails($selectedPlan);
$period = $_GET['period'] ?? '';

$getCheckedAttribute = function($duration) use ($period) {
    if ($period === 'yearly' && $duration === '12') {
        return 'checked';
    } else if ($period !== 'yearly' && $duration === '1') {
        return 'checked';
    }
    return '';
};

if (!$planDetails) {
    header('Location: wallet.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['duration'])) {
        $duration = $_POST['duration'];
        $pricing = calculateDiscountedPrice($planDetails['basePrice'], $duration);
        
        $_SESSION['subscription_data'] = [
            'plan' => $selectedPlan,
            'duration' => $duration,
            'total' => $pricing['total'],
            'monthly' => $pricing['monthly'],
            'savings' => $pricing['savings'],
            'plan_name' => $planDetails['name']  // Plan adını da ekleyelim
        ];
        
        header("Location: payment.php");
        exit;
    }
}

$html = '<!DOCTYPE html>
<html>
<head>
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
        .checkout-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 20px;
        }
    </style>
</head>
<body>

<div class="checkout-container">
    <h2 style="font-size: 22px; margin-bottom: 30px; font-weight: 600;">Sepeti Onayla</h2>
    
    <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 24px;">
        <!-- Main Content -->
        <div style="display: flex; flex-direction: column; gap: 16px;">
            <!-- Plan Details -->
            <div style="background: white; border: 1px solid #dedede; border-radius: 12px; padding: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h3 style="font-size: 16px; font-weight: 600;">' . $planDetails['name'] . ' Planı</h3>
                    <span style="font-size: 20px; font-weight: 600;">₺' . number_format($planDetails['basePrice'], 2) . '<span style="font-size: 13px; color: #666; font-weight: 400;">/ay</span></span>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">';

foreach ($planDetails['features'] as $feature) {
    $html .= '<div style="display: flex; align-items: center; gap: 8px;">
                <img src="/sources/icons/bulk/tick-circle.svg" style="width: 16px; opacity: 0.7;">
                <span style="font-size: 13px;">' . $feature . '</span>
            </div>';
}

$html .= '</div>
            </div>
            
            <!-- Duration Selection -->
            <div style="background: white; border: 1px solid #dedede; border-radius: 12px; padding: 20px;">
                <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 16px;">Abonelik Süresi</h3>
                
                <form method="POST" action="' . $_SERVER['PHP_SELF'] . '?plan=' . $selectedPlan . '" id="subscriptionForm">
                    <div style="display: grid; gap: 12px;">
                        <!-- Radio options with hover effects -->
                        <label style="display: flex; align-items: center; padding: 12px; border: 1px solid #eee; border-radius: 8px; cursor: pointer; transition: all 0.2s ease;" 
                               onmouseover="this.style.background=\'#f8f8f8\'; this.style.borderColor=\'#4F46E5\'" 
                               onmouseout="this.style.background=\'white\'; this.style.borderColor=\'#eee\'">
<input type="radio" name="duration" value="1" ' . $getCheckedAttribute('1') . ' style="margin-right: 12px;">                            <div style="flex-grow: 1;">
                                <div style="font-size: 13px; font-weight: 500;">1 Aylık</div>
                                <div style="font-size: 12px; color: #666;">Aylık ₺' . number_format($planDetails['basePrice'], 2) . '</div>
                            </div>
                            <div style="font-weight: 500; font-size: 13px;">₺' . number_format($planDetails['basePrice'], 2) . '</div>
                        </label>
                        
                        <!-- 3 Months Option -->
                        <label style="display: flex; align-items: center; padding: 15px; border: 1px solid #dedede; border-radius: 12px; cursor: pointer; transition: all 0.3s ease;" onmouseover="this.style.background=\'#f8f8f8\'" onmouseout="this.style.background=\'white\'">
                            <input type="radio" name="duration" value="3" style="margin-right: 15px;">
                            <div style="flex-grow: 1;">
                                <div style="font-weight: 500; font-size: 14px;">3 Aylık <span style="background: #22c55e; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;">15% İndirim</span></div>
                                <div style="font-size: 14px; color: #666;">Aylık ₺' . number_format($planDetails['basePrice'] * 0.85, 2) . '</div>
                            </div>
                            <div style="font-weight: 600; font-size: 14px;">₺' . number_format($planDetails['basePrice'] * 3 * 0.85, 2) . '</div>
                        </label>
                        
                        <!-- 6 Months Option -->
                        <label style="display: flex; align-items: center; padding: 15px; border: 1px solid #dedede; border-radius: 12px; cursor: pointer; transition: all 0.3s ease;" onmouseover="this.style.background=\'#f8f8f8\'" onmouseout="this.style.background=\'white\'">
                            <input type="radio" name="duration" value="6" style="margin-right: 15px;">
                            <div style="flex-grow: 1;">
                                <div style="font-weight: 500; font-size: 14px;">6 Aylık <span style="background: #22c55e; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;">25% İndirim</span></div>
                                <div style="font-size: 14px; color: #666;">Aylık ₺' . number_format($planDetails['basePrice'] * 0.75, 2) . '</div>
                            </div>
                            <div style="font-weight: 600; font-size: 14px;">₺' . number_format($planDetails['basePrice'] * 6 * 0.75, 2) . '</div>
                        </label>
                        
                        <!-- 12 Months Option -->
                        <label style="display: flex; align-items: center; padding: 15px; border: 1px solid #dedede; border-radius: 12px; cursor: pointer; transition: all 0.3s ease;" onmouseover="this.style.background=\'#f8f8f8\'" onmouseout="this.style.background=\'white\'">
<input type="radio" name="duration" value="12" ' . $getCheckedAttribute('12') . ' style="margin-right: 12px;">                            <div style="flex-grow: 1;">
                                <div style="font-weight: 500; font-size: 14px;">12 Aylık <span style="background: #22c55e; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;">35% İndirim</span></div>
                                <div style="font-size: 14px; color: #666;">Aylık ₺' . number_format($planDetails['basePrice'] * 0.65, 2) . '</div>
                            </div>
                            <div style="font-weight: 600; font-size: 14px;">₺' . number_format($planDetails['basePrice'] * 12 * 0.65, 2) . '</div>
                        </label>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Summary -->
        <div style="background: white; border: 1px solid #dedede; border-radius: 12px; padding: 20px; height: fit-content;">
            <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 16px;">Özet</h3>
            
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div style="display: flex; justify-content: space-between; font-size: 14px;">
                    <span style="color: #666;">Plan</span>
                    <span>' . $planDetails['name'] . '</span>
                </div>
                
                <div style="display: flex; justify-content: space-between; font-size: 14px;">
                    <span style="color: #666;">Süre</span>
                    <span id="summaryDuration">1 Ay</span>
                </div>
                
                <div style="display: flex; justify-content: space-between; font-size: 14px;">
                    <span style="color: #666;">Aylık Ücret</span>
                    <span id="summaryMonthly">₺' . number_format($planDetails['basePrice'], 2) . '</span>
                </div>
                
                <div style="display: flex; justify-content: space-between; padding-top: 15px; border-top: 1px solid #dedede;">
                    <span style="font-weight: 500; font-size: 14px;">Toplam</span>
                    <span style="font-weight: 600; font-size: 14px;" id="summaryTotal">₺' . number_format($planDetails['basePrice'], 2) . '</span>
                </div>
                
                <div id="summarySavings" style="text-align: center; color: #22c55e; font-size: 14px; display: none;">
                    Toplam tasarrufunuz: ₺<span id="savingsAmount">0</span>
                </div>
                
                <button type="submit" form="subscriptionForm" style="width: 100%; padding: 15px; background: #4F46E5; color: white; border: none; border-radius: 12px; cursor: pointer; font-size: 14px; font-weight: 500; margin-top: 15px; transition: all 0.3s ease;" onmouseover="this.style.transform=\'translateY(-2px)\'; this.style.boxShadow=\'0 10px 40px rgba(79, 70, 229, 0.2)\'" onmouseout="this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'none\'">
                  Ödemeyi Tamamla
                </button>
                
                <div style="text-align: center; font-size: 14px; color: #666; margin-top: 10px;">
                    <img src="/sources/icons/bulk/shield-tick.svg" style="width: 16px; opacity: 0.7; vertical-align: middle; margin-right: 5px;">
                    Güvenli ödeme
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const checkedRadio = document.querySelector(\'input[name="duration"]:checked\');
    if (checkedRadio) {
        checkedRadio.dispatchEvent(new Event(\'change\'));
    }
});

document.querySelectorAll(\'input[name="duration"]\').forEach(radio => {
    radio.addEventListener(\'change\', function() {
        const basePrice = ' . $planDetails['basePrice'] . ';
        const duration = parseInt(this.value);
        let discount = 0;
        
        switch(duration) {
            case 3:
                discount = 0.15;
                break;
            case 6:
                discount = 0.25;
                break;
            case 12:
                discount = 0.35;
                break;
        }
        
        const monthlyPrice = basePrice * (1 - discount);
        const totalPrice = monthlyPrice * duration;
        const savings = (basePrice * duration) - totalPrice;
        
        document.getElementById(\'summaryDuration\').textContent = duration + \' Ay\';
        document.getElementById(\'summaryMonthly\').textContent = \'₺\' + monthlyPrice.toFixed(2);
        document.getElementById(\'summaryTotal\').textContent = \'₺\' + totalPrice.toFixed(2);
        
        if (savings > 0) {
            document.getElementById(\'summarySavings\').style.display = \'block\';
            document.getElementById(\'savingsAmount\').textContent = savings.toFixed(2);
        } else {
            document.getElementById(\'summarySavings\').style.display = \'none\';
        }
    });
});
</script>';

echo $html;
?>
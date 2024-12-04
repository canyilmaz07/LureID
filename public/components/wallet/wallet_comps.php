<?php
// wallet_comps.php
function getWalletContent($section, $userData) {
    switch($section) {
        case 'wallet':
            return '<div style="margin-left: 340px; padding: 30px;">
                <h2 style="font-size: 24px; margin-bottom: 30px;">Cüzdanım</h2>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; max-width: 800px;">
                    <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h3 style="font-size: 18px;">Bakiye</h3>
                            <img src="/sources/icons/bulk/wallet-2.svg" style="width: 24px; opacity: 0.7;">
                        </div>
                        <p style="font-size: 24px; font-weight: 600;">₺' . number_format($userData["balance"], 2) . '</p>
                        <button style="margin-top: 15px; background: #4F46E5; color: white; padding: 8px 16px; border: none; border-radius: 8px; cursor: pointer;">Para Yükle</button>
                    </div>
                    <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h3 style="font-size: 18px;">Jetonlar</h3>
                            <img src="/sources/icons/bulk/coin.svg" style="width: 24px; opacity: 0.7;">
                        </div>
                        <p style="font-size: 24px; font-weight: 600;">' . $userData["coins"] . ' JETON</p>
                        <button style="margin-top: 15px; background: #4F46E5; color: white; padding: 8px 16px; border: none; border-radius: 8px; cursor: pointer;">Jeton Al</button>
                    </div>
                </div>
            </div>';

        case 'transactions':
            return '<div style="margin-left: 340px; padding: 30px;">
                <h2 style="font-size: 24px; margin-bottom: 20px;">Hareketler</h2>
            </div>';

        case 'cards':
            return '<div style="margin-left: 340px; padding: 30px;">
                <h2 style="font-size: 24px; margin-bottom: 20px;">Kayıtlı Kartlar</h2>
            </div>';

        case 'subscriptions':
            return '<div style="margin-left: 340px; padding: 30px;">
                <h2 style="font-size: 24px; margin-bottom: 20px;">Abonelikler</h2>
            </div>';

        case 'upgrade':
            return '<div style="margin-left: 340px; padding: 30px;">
                <h2 style="font-size: 24px; margin-bottom: 30px;">ID+ Paketleri</h2>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; max-width: 1000px;">
                    <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <h3 style="font-size: 20px; font-weight: 600; margin-bottom: 10px;">Basic</h3>
                        <p style="font-size: 24px; font-weight: 700; margin-bottom: 20px;">Ücretsiz</p>
                        <ul style="margin-bottom: 20px; list-style: none; padding: 0;">
                            <li style="margin-bottom: 8px;">✓ Temel özellikler</li>
                            <li style="margin-bottom: 8px;">✓ Sınırlı gösterim</li>
                            <li style="margin-bottom: 8px;">✓ Standart destek</li>
                        </ul>
                        <button style="width: 100%; padding: 10px; background: #888; color: white; border: none; border-radius: 8px; cursor: not-allowed;">Mevcut Plan</button>
                    </div>
                    
                    <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border: 2px solid #4F46E5;">
                        <h3 style="font-size: 20px; font-weight: 600; margin-bottom: 10px; color: #4F46E5;">ID+</h3>
                        <p style="font-size: 24px; font-weight: 700; margin-bottom: 20px;">₺99/ay</p>
                        <ul style="margin-bottom: 20px; list-style: none; padding: 0;">
                            <li style="margin-bottom: 8px;">✓ Tüm temel özellikler</li>
                            <li style="margin-bottom: 8px;">✓ Öncelikli gösterim</li>
                            <li style="margin-bottom: 8px;">✓ 7/24 destek</li>
                            <li style="margin-bottom: 8px;">✓ Özel rozet</li>
                        </ul>
                        <button style="width: 100%; padding: 10px; background: #4F46E5; color: white; border: none; border-radius: 8px; cursor: pointer;">Şimdi Başla</button>
                    </div>
                    
                    <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <h3 style="font-size: 20px; font-weight: 600; margin-bottom: 10px; color: #FDB931;">ID+ Pro</h3>
                        <p style="font-size: 24px; font-weight: 700; margin-bottom: 20px;">₺199/ay</p>
                        <ul style="margin-bottom: 20px; list-style: none; padding: 0;">
                            <li style="margin-bottom: 8px;">✓ Tüm ID+ özellikleri</li>
                            <li style="margin-bottom: 8px;">✓ En üst sırada gösterim</li>
                            <li style="margin-bottom: 8px;">✓ VIP destek</li>
                            <li style="margin-bottom: 8px;">✓ Pro rozeti</li>
                            <li style="margin-bottom: 8px;">✓ Özel analitikler</li>
                        </ul>
                        <button style="width: 100%; padding: 10px; background: #FDB931; color: white; border: none; border-radius: 8px; cursor: pointer;">Pro\'ya Yükselt</button>
                    </div>
                </div>
            </div>';

        default:
            return '<div style="margin-left: 340px; padding: 30px;">Sayfa bulunamadı.</div>';
    }
}
?>
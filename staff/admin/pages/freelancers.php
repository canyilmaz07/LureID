<?php
// admin/pages/freelancers.php
?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h2 class="text-2xl font-bold text-gray-800">Freelancer Onayları</h2>
        <div class="flex items-center space-x-2">
            <span class="text-sm text-gray-500">Toplam bekleyen:</span>
            <span class="bg-red-500 text-white text-sm font-bold px-3 py-1 rounded-full">
                <?php echo $pendingCounts['freelancers']; ?>
            </span>
        </div>
    </div>

    <?php
    $pendingFreelancers = $admin->getPendingFreelancers();
    if (empty($pendingFreelancers)): 
    ?>
        <div class="bg-blue-50 p-4 rounded-lg">
            <p class="text-blue-600">Bekleyen freelancer başvurusu bulunmamaktadır.</p>
        </div>
    <?php else: ?>
        <?php foreach ($pendingFreelancers as $freelancer): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <!-- Üst Başlık Kısmı -->
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-800">
                                <?php echo htmlspecialchars($freelancer['full_name']); ?>
                            </h3>
                            <p class="text-gray-500">@<?php echo htmlspecialchars($freelancer['username']); ?></p>
                        </div>
                        <div class="flex space-x-2">
                            <button onclick="approveFreelancer(<?php echo $freelancer['freelancer_id']; ?>)"
                                class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition-colors">
                                Onayla
                            </button>
                            <button onclick="rejectFreelancer(<?php echo $freelancer['freelancer_id']; ?>)"
                                class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition-colors">
                                Reddet
                            </button>
                        </div>
                    </div>

                    <!-- Accordion Detaylar -->
                    <div class="border rounded-lg">
                        <!-- Kişisel Bilgiler -->
                        <div class="border-b">
                            <button class="w-full px-4 py-3 text-left bg-gray-50 hover:bg-gray-100 flex justify-between items-center"
                                    onclick="toggleAccordion(this)">
                                <span class="font-medium">Kişisel Bilgiler</span>
                                <svg class="w-5 h-5 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div class="hidden p-4 bg-white">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-sm text-gray-600">Email:</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($freelancer['email']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Telefon:</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($freelancer['phone']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">TC Kimlik No:</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($freelancer['identity_number']); ?></p>
                                    </div>
                                    <?php 
                                    $basicInfo = json_decode($freelancer['basic_info'], true);
                                    if ($basicInfo): 
                                    ?>
                                    <div>
                                        <p class="text-sm text-gray-600">Yaş:</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($basicInfo['age'] ?? 'Belirtilmemiş'); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Lokasyon:</p>
                                        <p class="font-medium">
                                            <?php 
                                            $location = $basicInfo['location'] ?? [];
                                            echo htmlspecialchars(($location['city'] ?? '') . ', ' . ($location['country'] ?? '')); 
                                            ?>
                                        </p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Profesyonel Bilgiler -->
                        <div class="border-b">
                            <button class="w-full px-4 py-3 text-left bg-gray-50 hover:bg-gray-100 flex justify-between items-center"
                                    onclick="toggleAccordion(this)">
                                <span class="font-medium">Profesyonel Bilgiler</span>
                                <svg class="w-5 h-5 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div class="hidden p-4 bg-white">
                                <?php 
                                $professionalData = json_decode($freelancer['professional_data'], true);
                                if ($professionalData): 
                                ?>
                                <div class="space-y-4">
                                    <!-- Deneyim -->
                                    <div>
                                        <h4 class="font-medium mb-2">Deneyim</h4>
                                        <p><?php echo htmlspecialchars($professionalData['experience'] ?? 'Belirtilmemiş'); ?></p>
                                    </div>

                                    <!-- Yetenekler -->
                                    <div>
                                        <h4 class="font-medium mb-2">Yetenekler</h4>
                                        <div class="flex flex-wrap gap-2">
                                            <?php foreach ($professionalData['skills'] ?? [] as $skill): ?>
                                                <span class="bg-gray-100 px-3 py-1 rounded-full text-sm">
                                                    <?php echo htmlspecialchars($skill); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Eğitim -->
                                    <div>
                                        <h4 class="font-medium mb-2">Eğitim</h4>
                                        <p><?php echo htmlspecialchars($professionalData['education'] ?? 'Belirtilmemiş'); ?></p>
                                    </div>

                                    <!-- Sertifikalar -->
                                    <div>
                                        <h4 class="font-medium mb-2">Sertifikalar</h4>
                                        <p><?php echo htmlspecialchars($professionalData['certifications'] ?? 'Belirtilmemiş'); ?></p>
                                    </div>

                                    <!-- Portfolyo -->
                                    <?php if (isset($professionalData['portfolio'])): ?>
                                    <div>
                                        <h4 class="font-medium mb-2">Portfolyo</h4>
                                        <a href="<?php echo htmlspecialchars($professionalData['portfolio']); ?>" 
                                           target="_blank"
                                           class="text-blue-600 hover:underline">
                                            Portfolyo Link
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <p class="text-gray-500">Profesyonel bilgi girilmemiş.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Finansal Bilgiler -->
                        <div class="border-b">
                            <button class="w-full px-4 py-3 text-left bg-gray-50 hover:bg-gray-100 flex justify-between items-center"
                                    onclick="toggleAccordion(this)">
                                <span class="font-medium">Finansal Bilgiler</span>
                                <svg class="w-5 h-5 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div class="hidden p-4 bg-white">
                                <?php 
                                $financialData = json_decode($freelancer['financial_data'], true);
                                if ($financialData): 
                                ?>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-sm text-gray-600">Hesap Sahibi:</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($financialData['account_holder']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Banka:</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($financialData['bank_name']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">IBAN:</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($financialData['iban']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Vergi No:</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($financialData['tax_number']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Günlük Ücret:</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($financialData['daily_rate']); ?> ₺</p>
                                    </div>
                                </div>
                                <?php else: ?>
                                <p class="text-gray-500">Finansal bilgi girilmemiş.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function toggleAccordion(button) {
    const content = button.nextElementSibling;
    const icon = button.querySelector('svg');
    
    // Toggle content
    content.classList.toggle('hidden');
    
    // Rotate icon
    if (content.classList.contains('hidden')) {
        icon.classList.remove('rotate-180');
    } else {
        icon.classList.add('rotate-180');
    }
}
</script>
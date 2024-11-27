<?php
// admin/pages/gigs.php
?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h2 class="text-2xl font-bold text-gray-800">Gig Onayları</h2>
        <div class="flex items-center space-x-2">
            <span class="text-sm text-gray-500">Toplam bekleyen:</span>
            <span class="bg-red-500 text-white text-sm font-bold px-3 py-1 rounded-full">
                <?php echo $pendingCounts['gigs']; ?>
            </span>
        </div>
    </div>

    <?php
    $pendingGigs = $admin->getPendingGigs();
    if (empty($pendingGigs)): 
    ?>
        <div class="bg-blue-50 p-4 rounded-lg">
            <p class="text-blue-600">Bekleyen gig başvurusu bulunmamaktadır.</p>
        </div>
    <?php else: ?>
        <?php foreach ($pendingGigs as $gig): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-4">
                <!-- Üst Başlık Kısmı -->
                <div class="p-6 border-b">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-800">
                                <?php echo htmlspecialchars($gig['title']); ?>
                            </h3>
                            <p class="text-gray-500">
                                Freelancer: @<?php echo htmlspecialchars($gig['username']); ?>
                            </p>
                        </div>
                        <div class="flex space-x-2">
                            <button onclick="approveGig(<?php echo $gig['gig_id']; ?>)"
                                class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition-colors">
                                Onayla
                            </button>
                            <button onclick="rejectGig(<?php echo $gig['gig_id']; ?>)"
                                class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition-colors">
                                Reddet
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Accordion Detaylar -->
                <div class="p-6">
                    <!-- Gig Detayları -->
                    <div class="mb-4">
                        <button class="accordion-btn w-full px-4 py-3 text-left bg-gray-50 hover:bg-gray-100 flex justify-between items-center rounded"
                                onclick="toggleAccordion(this)">
                            <span class="font-medium">Gig Detayları</span>
                            <svg class="w-5 h-5 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="accordion-content hidden p-4 border border-t-0 rounded-b">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-600">Kategori:</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($gig['category']); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Alt Kategori:</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($gig['subcategory']); ?></p>
                                </div>
                                <div class="col-span-2">
                                    <p class="text-sm text-gray-600">Açıklama:</p>
                                    <p class="font-medium"><?php echo nl2br(htmlspecialchars($gig['description'])); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Fiyat:</p>
                                    <p class="font-medium"><?php echo number_format($gig['price'], 2); ?> ₺</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Fiyat Tipi:</p>
                                    <p class="font-medium">
                                        <?php 
                                        $pricingTypes = [
                                            'ONE_TIME' => 'Tek Seferlik',
                                            'DAILY' => 'Günlük',
                                            'WEEKLY' => 'Haftalık',
                                            'MONTHLY' => 'Aylık'
                                        ];
                                        echo $pricingTypes[$gig['pricing_type']] ?? $gig['pricing_type']; 
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Teslim Süreçleri -->
                    <div class="mb-4">
                        <button class="accordion-btn w-full px-4 py-3 text-left bg-gray-50 hover:bg-gray-100 flex justify-between items-center rounded"
                                onclick="toggleAccordion(this)">
                            <span class="font-medium">Teslim Süreçleri</span>
                            <svg class="w-5 h-5 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="accordion-content hidden p-4 border border-t-0 rounded-b">
                            <div>
                                <p class="text-sm text-gray-600">Teslimat Süresi:</p>
                                <p class="font-medium"><?php echo htmlspecialchars($gig['delivery_time']); ?> gün</p>
                            </div>
                            <div class="mt-4">
                                <p class="text-sm text-gray-600">Revizyon Hakkı:</p>
                                <p class="font-medium"><?php echo htmlspecialchars($gig['revision_count']); ?> kez</p>
                            </div>
                            
                            <!-- Aşamalar -->
                            <?php if ($gig['milestone_title']): ?>
                            <div class="mt-4">
                                <p class="text-sm text-gray-600 mb-2">İş Aşamaları:</p>
                                <div class="space-y-2">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full bg-blue-500 text-white flex items-center justify-center font-medium">
                                            1
                                        </div>
                                        <div class="ml-3">
                                            <p class="font-medium"><?php echo htmlspecialchars($gig['milestone_title']); ?></p>
                                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($gig['milestone_description']); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Medya ve Dökümanlar -->
                    <div class="mb-4">
                        <button class="accordion-btn w-full px-4 py-3 text-left bg-gray-50 hover:bg-gray-100 flex justify-between items-center rounded"
                                onclick="toggleAccordion(this)">
                            <span class="font-medium">Medya ve Dökümanlar</span>
                            <svg class="w-5 h-5 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="accordion-content hidden p-4 border border-t-0 rounded-b">
                            <?php 
                            $mediaData = json_decode($gig['media_data'], true);
                            if ($mediaData && !empty($mediaData['images'])): 
                            ?>
                            <div class="grid grid-cols-4 gap-4">
                                <?php foreach ($mediaData['images'] as $image): ?>
                                <div class="relative group cursor-pointer" onclick="previewFile('<?php echo htmlspecialchars($image); ?>')">
                                    <img src="<?php echo htmlspecialchars($image); ?>" 
                                         alt="Gig görseli" 
                                         class="w-full h-32 object-cover rounded">
                                    <div class="absolute inset-0 bg-black bg-opacity-40 opacity-0 group-hover:opacity-100 transition-opacity rounded flex items-center justify-center">
                                        <span class="text-white text-sm">Görüntüle</span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <p class="text-gray-500">Medya eklenmemiş.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- NDA Gereksinimleri -->
                    <?php if ($gig['nda_text']): ?>
                    <div>
                        <button class="accordion-btn w-full px-4 py-3 text-left bg-gray-50 hover:bg-gray-100 flex justify-between items-center rounded"
                                onclick="toggleAccordion(this)">
                            <span class="font-medium">NDA Gereksinimleri</span>
                            <svg class="w-5 h-5 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="accordion-content hidden p-4 border border-t-0 rounded-b">
                            <div class="prose max-w-none">
                                <?php echo nl2br(htmlspecialchars($gig['nda_text'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Dosya Önizleme Modalı -->
<div id="filePreviewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 max-w-2xl w-full mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Dosya Önizleme</h3>
            <button onclick="hideModal('filePreviewModal')" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div id="filePreview" class="max-h-[600px] overflow-auto">
            <!-- Önizleme içeriği buraya gelecek -->
        </div>
    </div>
</div>

<script>
function toggleAccordion(button) {
    // Tıklanan butonun içeriğini bul
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
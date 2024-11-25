<?php
function isCurrentPage($pageName) {
    return strpos($_SERVER['SCRIPT_NAME'], $pageName) !== false;
}
?>

<!-- Top Menu -->
<nav class="bg-white border-b border-gray-200 fixed w-full z-30 top-0">
    <div class="px-3 py-3 lg:px-5 lg:pl-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center justify-start">
                <!-- Logo -->
                <a href="dashboard.php" class="text-xl font-bold flex items-center lg:ml-2.5">
                    <span class="text-blue-600">Lure</span><span class="text-gray-800">ID</span>
                </a>
            </div>
            <div class="flex items-center">
                <span class="text-gray-600 text-sm mx-4">Freelancer Dashboard</span>
            </div>
            <div class="flex items-center">
                <a href="../../index.php" 
                   class="text-gray-600 hover:text-blue-600 px-4 py-2 rounded-lg text-sm flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Ana Sayfa
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Side Menu -->
<aside class="fixed left-0 top-0 z-20 w-64 h-screen pt-16 bg-white border-r border-gray-200">
    <div class="h-full px-3 py-4 overflow-y-auto">
        <!-- Freelancer Panel -->
        <div class="space-y-2 mb-6">
            <h2 class="text-gray-500 font-medium px-3 py-2">Freelancer Paneli</h2>
            <a href="gigs.php" 
               class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group <?= isCurrentPage('gigs.php') ? 'bg-blue-50 text-blue-600' : '' ?>">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                </svg>
                İlanlarım
            </a>
        </div>

        <!-- Product & Sales Center -->
        <div class="space-y-2">
            <h2 class="text-gray-500 font-medium px-3 py-2">Ürün & Satış Merkezi</h2>
            <!-- Buraya gelecek menü öğeleri -->
        </div>
    </div>
</aside>

<!-- Main Content Padding -->
<div class="p-4 sm:ml-64 pt-20">
    <!-- İçerik buraya gelecek -->
</div>
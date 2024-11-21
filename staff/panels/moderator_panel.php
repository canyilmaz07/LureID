<?php
function getModeratorPanel() {
    return '
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-bold mb-4">Content Moderation</h2>
            <div class="space-y-3">
                <a href="#" class="block text-blue-600 hover:text-blue-800">Review Reports</a>
                <a href="#" class="block text-blue-600 hover:text-blue-800">Flagged Content</a>
                <a href="#" class="block text-blue-600 hover:text-blue-800">User Warnings</a>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-bold mb-4">Community Management</h2>
            <div class="space-y-3">
                <a href="#" class="block text-blue-600 hover:text-blue-800">User Guidelines</a>
                <a href="#" class="block text-blue-600 hover:text-blue-800">Moderation History</a>
                <a href="#" class="block text-blue-600 hover:text-blue-800">Community Updates</a>
            </div>
        </div>
    </div>';
}
?>
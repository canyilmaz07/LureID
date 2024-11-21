<?php
function getSupportPanel() {
    return '
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-bold mb-4">Support Tickets</h2>
            <div class="space-y-3">
                <a href="#" class="block text-blue-600 hover:text-blue-800">Open Tickets</a>
                <a href="#" class="block text-blue-600 hover:text-blue-800">Ticket History</a>
                <a href="#" class="block text-blue-600 hover:text-blue-800">Knowledge Base</a>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-bold mb-4">User Assistance</h2>
            <div class="space-y-3">
                <a href="#" class="block text-blue-600 hover:text-blue-800">FAQ Management</a>
                <a href="#" class="block text-blue-600 hover:text-blue-800">Support Resources</a>
                <a href="#" class="block text-blue-600 hover:text-blue-800">Contact Forms</a>
            </div>
        </div>
    </div>';
}
?>
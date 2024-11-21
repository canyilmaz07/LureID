<?php
function getAdminPanel() {
    return '
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-bold mb-4">User Management</h2>
            <div class="space-y-3">
                <a href="#" class="block text-blue-600 hover:text-blue-800">View All Users</a>
                <a href="#" class="block text-blue-600 hover:text-blue-800">Manage Staff</a>
                <a href="#" class="block text-blue-600 hover:text-blue-800">User Reports</a>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-bold mb-4">System Settings</h2>
            <div class="space-y-3">
                <a href="#" class="block text-blue-600 hover:text-blue-800">General Settings</a>
                <a href="#" class="block text-blue-600 hover:text-blue-800">Security Settings</a>
                <a href="#" class="block text-blue-600 hover:text-blue-800">Maintenance Mode</a>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-bold mb-4">Analytics</h2>
            <div class="space-y-3">
                <a href="#" class="block text-blue-600 hover:text-blue-800">User Statistics</a>
                <a href="#" class="block text-blue-600 hover:text-blue-800">System Logs</a>
                <a href="#" class="block text-blue-600 hover:text-blue-800">Performance Metrics</a>
            </div>
        </div>
    </div>';
}
?>
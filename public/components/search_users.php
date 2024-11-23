<?php 
session_start(); 
require_once '../../config/database.php';  

header('Content-Type: application/json');  

if (!isset($_GET['query'])) {     
    echo json_encode([]);     
    exit; 
}  

try {     
    $dbConfig = require '../../config/database.php';     
    $db = new PDO(         
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",         
        $dbConfig['username'],         
        $dbConfig['password'],         
        $dbConfig['options']     
    );      
    
    $query = $_GET['query'];     
    $stmt = $db->prepare("         
        SELECT 
            u.user_id, 
            u.username, 
            u.full_name, 
            ued.profile_photo_url
        FROM users u         
        LEFT JOIN user_extended_details ued ON u.user_id = ued.user_id         
        WHERE u.username LIKE ? OR u.full_name LIKE ?         
        LIMIT 5     
    ");     
    
    $stmt->execute(["%$query%", "%$query%"]);     
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);      
    
    echo json_encode($results); 

} catch (PDOException $e) {     
    echo json_encode(['error' => $e->getMessage()]); 
}
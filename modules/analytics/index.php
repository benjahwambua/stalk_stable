<?php
/**
 * Analytics Module
 * 
 * This module handles analytics and reporting for the alcohol distributor system.
 */

// Start session and include necessary files
session_start();

// Include configuration and database connection
// require_once '../../config/config.php';
// require_once '../../config/database.php';

// Check if user is authenticated
// if (!isset($_SESSION['user_id'])) {
//     header('Location: ../../login.php');
//     exit;
// }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Stable Stalk</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Analytics Dashboard</h1>
        
        <!-- Analytics content will go here -->
        <div class="analytics-section">
            <h2>Sales Overview</h2>
            <!-- Add your analytics content here -->
        </div>
    </div>

    <script src="../../assets/js/script.js"></script>
</body>
</html>

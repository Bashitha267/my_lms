<?php
session_start();
require_once '../config.php';

// Check if user is logged in as instructor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$page_title = "Instructor Payments";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    
    <?php include 'navbar.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Payments</h1>
            <span class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-bold">Instructor Portal</span>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-center p-12 flex-col">
                <i class="fas fa-wallet text-6xl text-purple-300 mb-4"></i>
                <h2 class="text-2xl font-bold text-gray-700 mb-2">No Payments Yet</h2>
                <p class="text-gray-500 text-center">Your payment history and pending balances will appear here.</p>
            </div>
        </div>
    </div>
</body>
</html>

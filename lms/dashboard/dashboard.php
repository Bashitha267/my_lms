<?php
require_once '../check_session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include 'navbar.php'; ?>
    
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <div class="bg-white rounded-lg shadow p-6">
                <h1 class="text-3xl font-bold text-gray-900 mb-4">Welcome to Dashboard</h1>
                <p class="text-gray-600">
                    Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!
                </p>
                <p class="text-gray-600 mt-2">
                    Your role: <span class="font-semibold"><?php echo htmlspecialchars($_SESSION['role'] ?? 'N/A'); ?></span>
                </p>
            </div>
        </div>
    </div>
</body>
</html>


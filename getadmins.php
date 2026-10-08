<?php
// getadmins.php - Displays Admin and Super Admin details and resets their passwords to admin123
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} elseif (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} else {
    die("Error: config.php file not found.");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$password_reset_msg = '';

// Automatically reset both admin and super_admin passwords to 'admin123'
$new_pass = 'admin123';
$new_hash = password_hash($new_pass, PASSWORD_DEFAULT);

$update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE role IN ('admin', 'super_admin')");
if ($update_stmt) {
    $update_stmt->bind_param("s", $new_hash);
    if ($update_stmt->execute()) {
        $affected = $update_stmt->affected_rows;
        $password_reset_msg = "Successfully updated password for $affected Admin/Super Admin account(s) to: <strong>$new_pass</strong>";
    }
    $update_stmt->close();
}

// Fetch all Admin & Super Admin details
$admins = [];
$query = "SELECT user_id, email, role, first_name, second_name, mobile_number, whatsapp_number, status, approved, registering_date 
          FROM users 
          WHERE role IN ('admin', 'super_admin') 
          ORDER BY role ASC, user_id ASC";
$res = $conn->query($query);

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $admins[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Accounts & Credentials - Lernerr.LK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen p-6 sm:p-12 flex items-center justify-center">
    <div class="max-w-5xl w-full bg-slate-800/90 rounded-3xl p-8 sm:p-10 border border-slate-700 shadow-2xl backdrop-blur-xl">
        <div class="flex items-center justify-between mb-8 pb-6 border-b border-slate-700">
            <div>
                <span class="bg-emerald-500/20 text-emerald-400 text-xs font-extrabold uppercase px-3 py-1 rounded-full border border-emerald-500/30">System Credentials</span>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-2">Admin Accounts & Credentials</h1>
            </div>
            <a href="admin/dashboard" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-lg transition-all">Go to Admin Dashboard</a>
        </div>

        <?php if (!empty($password_reset_msg)): ?>
            <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-bold mb-8 flex items-center gap-3">
                <i class="fas fa-key text-base"></i>
                <span><?php echo $password_reset_msg; ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($admins)): ?>
            <div class="p-6 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-bold text-center">
                No admin or super admin accounts found in database.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto rounded-2xl border border-slate-700 mb-8">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-950/60 uppercase font-extrabold text-slate-400 border-b border-slate-700">
                        <tr>
                            <th class="px-6 py-4">Username / User ID</th>
                            <th class="px-6 py-4">Full Name</th>
                            <th class="px-6 py-4">Role</th>
                            <th class="px-6 py-4">Contact Number</th>
                            <th class="px-6 py-4">Email Address</th>
                            <th class="px-6 py-4">Password</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/60 font-semibold">
                        <?php foreach ($admins as $admin): ?>
                            <tr class="hover:bg-slate-700/30 transition-colors">
                                <td class="px-6 py-4 font-mono font-bold text-blue-400 flex items-center gap-2">
                                    <i class="fas fa-user-shield text-slate-400"></i>
                                    <span class="text-sm"><?php echo htmlspecialchars($admin['user_id']); ?></span>
                                </td>
                                <td class="px-6 py-4 text-slate-200">
                                    <?php 
                                    $name = trim(($admin['first_name'] ?? '') . ' ' . ($admin['second_name'] ?? ''));
                                    echo htmlspecialchars(!empty($name) ? $name : 'System Admin'); 
                                    ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($admin['role'] === 'super_admin'): ?>
                                        <span class="bg-purple-500/20 text-purple-300 text-[10px] font-extrabold uppercase px-2.5 py-1 rounded-full border border-purple-500/30">Super Admin</span>
                                    <?php else: ?>
                                        <span class="bg-blue-500/20 text-blue-300 text-[10px] font-extrabold uppercase px-2.5 py-1 rounded-full border border-blue-500/30">Admin</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-slate-300 font-mono">
                                    <div class="space-y-1">
                                        <?php if (!empty($admin['mobile_number'])): ?>
                                            <div><i class="fas fa-phone text-slate-500 text-[10px]"></i> <?php echo htmlspecialchars($admin['mobile_number']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($admin['whatsapp_number'])): ?>
                                            <div class="text-emerald-400"><i class="fab fa-whatsapp text-[10px]"></i> <?php echo htmlspecialchars($admin['whatsapp_number']); ?></div>
                                        <?php endif; ?>
                                        <?php if (empty($admin['mobile_number']) && empty($admin['whatsapp_number'])): ?>
                                            <span class="text-slate-500">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-slate-300 font-mono">
                                    <?php echo htmlspecialchars(!empty($admin['email']) ? $admin['email'] : '-'); ?>
                                </td>
                                <td class="px-6 py-4 font-mono font-extrabold text-emerald-400">
                                    admin123
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="flex justify-end gap-4">
            <a href="index.php" class="px-6 py-3 bg-slate-700 hover:bg-slate-600 text-white font-bold text-xs rounded-xl transition-all">Go to Home</a>
        </div>
    </div>
</body>
</html>

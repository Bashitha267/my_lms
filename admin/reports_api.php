<?php
require_once '../check_session.php';
require_once '../config.php';

if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

header('Content-Type: application/json');
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ─── EXPENSES ────────────────────────────────────────────
    case 'add_expense':
        $name     = trim($_POST['name'] ?? '');
        $amount   = floatval($_POST['amount'] ?? 0);
        $is_fixed = intval($_POST['is_fixed'] ?? 0);
        $month    = $is_fixed ? null : intval($_POST['month'] ?? date('n'));
        $year     = $is_fixed ? null : intval($_POST['year'] ?? date('Y'));
        $notes    = trim($_POST['notes'] ?? '');

        if (empty($name) || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Name and amount are required.']); exit;
        }
        $stmt = $conn->prepare("INSERT INTO budget_expenses (name, amount, is_fixed, month, year, notes) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("sdiids", $name, $amount, $is_fixed, $month, $year, $notes);
        $stmt->execute();
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
        break;

    case 'edit_expense':
        $id       = intval($_POST['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $amount   = floatval($_POST['amount'] ?? 0);
        $is_fixed = intval($_POST['is_fixed'] ?? 0);
        $month    = $is_fixed ? null : intval($_POST['month'] ?? date('n'));
        $year     = $is_fixed ? null : intval($_POST['year'] ?? date('Y'));

        $stmt = $conn->prepare("UPDATE budget_expenses SET name=?, amount=?, is_fixed=?, month=?, year=? WHERE id=?");
        $stmt->bind_param("sdiidi", $name, $amount, $is_fixed, $month, $year, $id);
        $stmt->execute();
        echo json_encode(['success' => true]);
        break;

    case 'delete_expense':
        $id = intval($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM budget_expenses WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo json_encode(['success' => true]);
        break;

    // ─── SETTINGS (commission rate) ────────────────────────────
    case 'save_commission':
        $rate = floatval($_POST['commission_rate'] ?? 10);
        if ($rate < 0 || $rate > 100) {
            echo json_encode(['success' => false, 'message' => 'Rate must be 0-100.']); exit;
        }
        $stmt = $conn->prepare("INSERT INTO institute_settings (setting_key, setting_value) VALUES ('commission_rate', ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        $stmt->bind_param("s", $rate);
        $stmt->execute();
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
?>

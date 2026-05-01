<?php
require_once '../check_session.php';
require_once '../config.php';

if ($_SESSION['role'] !== 'super_admin') {
    header('Location: dashboard.php'); exit;
}

// ── Filters ──────────────────────────────────────────────────
$filter_month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('n'));
$filter_year  = isset($_GET['year'])  ? intval($_GET['year'])  : intval(date('Y'));

// ── Commission Rate ────────────────────────────────────────────
$commission_rate = 10; // default
$cr_res = $conn->query("SELECT setting_value FROM institute_settings WHERE setting_key='commission_rate'");
if ($cr_res && $cr_row = $cr_res->fetch_assoc()) {
    $commission_rate = floatval($cr_row['setting_value']);
}

// ── Income: per-teacher breakdown  ────────────────────────────
$teachers_income = [];
$income_sql = "
    SELECT
        u.user_id, u.first_name, u.second_name, u.profile_picture,
        COALESCE(SUM(ep.amount), 0)  AS enrollment_total,
        COALESCE(SUM(mp2.amount), 0) AS monthly_total
    FROM users u
    LEFT JOIN teacher_assignments ta  ON ta.teacher_id = u.user_id AND ta.status = 'active'
    LEFT JOIN stream_subjects ss      ON ss.id = ta.stream_subject_id
    LEFT JOIN student_enrollment se   ON se.stream_subject_id = ss.id AND se.academic_year = ta.academic_year
    LEFT JOIN enrollment_payments ep  ON ep.student_enrollment_id = se.id
                                     AND ep.payment_status = 'paid'
                                     AND MONTH(ep.created_at) = ?
                                     AND YEAR(ep.created_at) = ?
    LEFT JOIN monthly_payments mp2    ON mp2.student_enrollment_id = se.id
                                     AND mp2.payment_status = 'paid'
                                     AND mp2.month = ?
                                     AND mp2.year = ?
    WHERE u.role = 'teacher'
    GROUP BY u.user_id, u.first_name, u.second_name, u.profile_picture
    ORDER BY (COALESCE(SUM(ep.amount),0) + COALESCE(SUM(mp2.amount),0)) DESC
";
$ti_stmt = $conn->prepare($income_sql);
if ($ti_stmt) {
    $ti_stmt->bind_param("iiii", $filter_month, $filter_year, $filter_month, $filter_year);
    $ti_stmt->execute();
    $ti_res = $ti_stmt->get_result();
    while ($row = $ti_res->fetch_assoc()) {
        $row['total']      = $row['enrollment_total'] + $row['monthly_total'];
        // Enrollment fees go 100% to teachers (0% commission), only calculate commission on monthly fees
        $row['commission'] = round($row['monthly_total'] * $commission_rate / 100, 2);
        $teachers_income[] = $row;
    }
    $ti_stmt->close();
}

$total_student_payments = array_sum(array_column($teachers_income, 'total'));
// Calculate total commission only from monthly payments
$total_monthly_payments = array_sum(array_column($teachers_income, 'monthly_total'));
$total_commission       = round($total_monthly_payments * $commission_rate / 100, 2);

// ── Fixed expenses (stored without month/year) ─────────────────
$fixed_expenses = [];
$fe_res = $conn->query("SELECT * FROM budget_expenses WHERE is_fixed = 1 ORDER BY name");
if ($fe_res) while ($row = $fe_res->fetch_assoc()) $fixed_expenses[] = $row;

// ── One-time expenses for this month/year ─────────────────────
$variable_expenses = [];
$ve_stmt = $conn->prepare("SELECT * FROM budget_expenses WHERE is_fixed = 0 AND month = ? AND year = ? ORDER BY created_at");
$ve_stmt->bind_param("ii", $filter_month, $filter_year);
$ve_stmt->execute();
$ve_res = $ve_stmt->get_result();
while ($row = $ve_res->fetch_assoc()) $variable_expenses[] = $row;
$ve_stmt->close();

// ── Teacher payments made (salary paid to teachers) ───────────
$teacher_salaries = [];
$ts_sql = "
    SELECT u.first_name, u.second_name,
           COALESCE(SUM(tpr.amount),0) AS paid_amount
    FROM users u
    LEFT JOIN teacher_payment_requests tpr ON tpr.teacher_id = u.user_id
        AND tpr.status = 'paid'
        AND MONTH(tpr.processed_date) = ?
        AND YEAR(tpr.processed_date) = ?
    WHERE u.role = 'teacher'
    GROUP BY u.user_id, u.first_name, u.second_name
    HAVING COALESCE(SUM(tpr.amount),0) > 0
    ORDER BY COALESCE(SUM(tpr.amount),0) DESC
";
$ts_stmt = $conn->prepare($ts_sql);
if ($ts_stmt) {
    $ts_stmt->bind_param("ii", $filter_month, $filter_year);
    $ts_stmt->execute();
    $ts_res = $ts_stmt->get_result();
    while ($row = $ts_res->fetch_assoc()) $teacher_salaries[] = $row;
    $ts_stmt->close();
}

$total_teacher_salaries = array_sum(array_column($teacher_salaries, 'paid_amount'));
$total_fixed_expenses   = array_sum(array_column($fixed_expenses, 'amount'));
$total_variable_expenses= array_sum(array_column($variable_expenses, 'amount'));
$total_expenses         = $total_fixed_expenses + $total_variable_expenses + $total_teacher_salaries;

$month_name = date('F', mktime(0,0,0,$filter_month,1,$filter_year));

$months = [];
for ($m = 1; $m <= 12; $m++) {
    $months[$m] = date('F', mktime(0,0,0,$m,1));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Reports | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; }

        .report-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,.07), 0 4px 16px rgba(0,0,0,.04);
        }

        .stat-card {
            border-radius: 14px;
            padding: 20px 22px;
            display: flex;
            flex-direction: column;
        }

        /* Editable row */
        .expense-row td { padding: 10px 14px; vertical-align: middle; }
        .expense-row input {
            background: transparent;
            border: none;
            outline: none;
            width: 100%;
            font-size: 14px;
        }
        .expense-row.editing input {
            background: #fef9f0;
            border: 1.5px solid #f59e0b;
            border-radius: 6px;
            padding: 4px 8px;
        }
        .expense-row:hover { background: #fafafa; }

        /* Print styles — Formal Document */
        @media print {
            /* Hide everything by default */
            body * { visibility: hidden; }

            /* Show only the print document */
            #printDocument, #printDocument * { visibility: visible; }
            #printDocument {
                position: fixed;
                top: 0; left: 0;
                width: 100%;
                background: white;
                z-index: 9999;
                padding: 0;
                font-family: 'Inter', Arial, sans-serif;
            }

            /* Page settings */
            @page {
                size: A4;
                margin: 0;
            }
        }
        .print-header { display: none; }


        /* Scrollbar */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- FORMAL PRINT DOCUMENT (only visible during window.print) -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div id="printDocument" style="display:none; width:210mm; min-height:297mm; margin:0 auto; background:#fff; font-family:'Inter',Arial,sans-serif; font-size:9pt; color:#111;">

            <!-- Red header band -->
            <div style="background:#dc2626; padding:0; position:relative; overflow:hidden;">
                <!-- Top decorative corner -->
                <div style="position:absolute; top:0; right:0; width:60px; height:60px; background:rgba(0,0,0,0.15); clip-path:polygon(100% 0, 0 0, 100% 100%);"></div>
                <div style="padding:24px 32px 18px;">
                    <table style="width:100%; border:none; border-collapse:collapse;">
                        <tr>
                            <td style="vertical-align:top;">
                                <div style="color:#fff; font-size:20pt; font-weight:900; letter-spacing:-0.5px; line-height:1;">Lernerr.LK</div>
                                <div style="color:rgba(255,255,255,0.75); font-size:8pt; margin-top:3px; letter-spacing:1px; text-transform:uppercase;">Educational Institute</div>
                            </td>
                            <td style="text-align:right; vertical-align:top;">
                                <div style="display:inline-block; background:#fff; color:#dc2626; font-size:14pt; font-weight:900; padding:6px 20px; border-radius:4px; letter-spacing:0.5px;">BUDGET REPORT</div>
                                <div style="color:rgba(255,255,255,0.85); font-size:8pt; margin-top:6px;"><?php echo $month_name . ' ' . $filter_year; ?></div>
                            </td>
                        </tr>
                    </table>
                </div>
                <!-- Red sub-band -->
                <div style="background:#b91c1c; padding:8px 32px;">
                    <table style="width:100%; border:none; border-collapse:collapse;">
                        <tr>
                            <td style="color:#fff; font-size:7.5pt;"><strong>Generated:</strong> <?php echo date('d F Y, h:i A'); ?></td>
                            <td style="color:#fff; font-size:7.5pt; text-align:center;"><strong>Period:</strong> <?php echo $month_name . ' ' . $filter_year; ?></td>
                            <td style="color:#fff; font-size:7.5pt; text-align:right;"><strong>Commission Rate:</strong> <?php echo $commission_rate; ?>%</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Body content -->
            <div style="padding:22px 32px 0;">

                <!-- ── Summary Bar ── -->
                <table style="width:100%; border-collapse:collapse; margin-bottom:20px;">
                    <tr>
                        <td style="width:25%; padding:10px 14px; background:#f9f9f9; border:1px solid #e0e0e0; border-right:3px solid #dc2626;">
                            <div style="font-size:7pt; color:#888; text-transform:uppercase; letter-spacing:.8px; margin-bottom:3px;">TOTAL STUDENT PAYMENTS</div>
                            <div style="font-size:13pt; font-weight:800; color:#111;">Rs.<?php echo number_format($total_student_payments, 2); ?></div>
                        </td>
                        <td style="width:25%; padding:10px 14px; background:#f9f9f9; border:1px solid #e0e0e0; border-left:none; border-right:3px solid #dc2626;">
                            <div style="font-size:7pt; color:#888; text-transform:uppercase; letter-spacing:.8px; margin-bottom:3px;">INSTITUTE COMMISSION</div>
                            <div style="font-size:13pt; font-weight:800; color:#dc2626;">Rs.<?php echo number_format($total_commission, 2); ?></div>
                        </td>
                        <td style="width:25%; padding:10px 14px; background:#f9f9f9; border:1px solid #e0e0e0; border-left:none; border-right:3px solid #dc2626;">
                            <div style="font-size:7pt; color:#888; text-transform:uppercase; letter-spacing:.8px; margin-bottom:3px;">INSTRUCTOR SALARIES PAID</div>
                            <div style="font-size:13pt; font-weight:800; color:#111;">Rs.<?php echo number_format($total_teacher_salaries, 2); ?></div>
                        </td>
                        <td style="width:25%; padding:10px 14px; background:#f9f9f9; border:1px solid #e0e0e0; border-left:none;">
                            <div style="font-size:7pt; color:#888; text-transform:uppercase; letter-spacing:.8px; margin-bottom:3px;">TOTAL EXPENSES</div>
                            <div style="font-size:13pt; font-weight:800; color:#111;">Rs.<?php echo number_format($total_expenses, 2); ?></div>
                        </td>
                    </tr>
                </table>

                <!-- ── INCOME SECTION ── -->
                <div style="margin-bottom:18px;">
                    <!-- Section Header -->
                    <div style="display:flex; align-items:center; margin-bottom:8px;">
                        <div style="background:#dc2626; color:#fff; font-size:8pt; font-weight:800; padding:4px 12px; letter-spacing:.5px; text-transform:uppercase;">INCOME</div>
                        <div style="flex:1; height:1px; background:#dc2626; margin-left:0;"></div>
                        <div style="background:#111; color:#fff; font-size:7pt; padding:4px 10px; font-weight:700;">Student Payments by Instructor</div>
                    </div>
                    <table style="width:100%; border-collapse:collapse; font-size:8.5pt;">
                        <thead>
                            <tr style="background:#111; color:#fff;">
                                <th style="padding:7px 10px; text-align:left; font-weight:700; width:5%;">#</th>
                                <th style="padding:7px 10px; text-align:left; font-weight:700;">Instructor Name</th>
                                <th style="padding:7px 10px; text-align:right; font-weight:700;">Enrollment Fees</th>
                                <th style="padding:7px 10px; text-align:right; font-weight:700;">Monthly Fees</th>
                                <th style="padding:7px 10px; text-align:right; font-weight:700;">Total Collected</th>
                                <th style="padding:7px 10px; text-align:right; font-weight:700; background:#dc2626;">Commission (Monthly)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $print_i = 1;
                            $teachers_with_income = array_filter($teachers_income, function($t){ return $t['total'] > 0; });
                            if (empty($teachers_with_income)): ?>
                            <tr><td colspan="6" style="padding:12px 10px; text-align:center; color:#888; font-style:italic;">No income recorded for this period.</td></tr>
                            <?php else: foreach ($teachers_with_income as $t): ?>
                            <tr style="background:<?php echo $print_i % 2 === 0 ? '#f9f9f9' : '#fff'; ?>; border-bottom:1px solid #e8e8e8;">
                                <td style="padding:7px 10px; color:#888;"><?php echo $print_i++; ?></td>
                                <td style="padding:7px 10px; font-weight:600;"><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['second_name']); ?></td>
                                <td style="padding:7px 10px; text-align:right;">Rs.<?php echo number_format($t['enrollment_total'], 2); ?></td>
                                <td style="padding:7px 10px; text-align:right;">Rs.<?php echo number_format($t['monthly_total'], 2); ?></td>
                                <td style="padding:7px 10px; text-align:right; font-weight:700;">Rs.<?php echo number_format($t['total'], 2); ?></td>
                                <td style="padding:7px 10px; text-align:right; font-weight:700; color:#dc2626;">Rs.<?php echo number_format($t['commission'], 2); ?></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background:#111; color:#fff;">
                                <td colspan="4" style="padding:7px 10px; text-align:right; font-weight:800; font-size:9pt;">TOTAL</td>
                                <td style="padding:7px 10px; text-align:right; font-weight:800;">Rs.<?php echo number_format($total_student_payments, 2); ?></td>
                                <td style="padding:7px 10px; text-align:right; font-weight:800; background:#dc2626;">Rs.<?php echo number_format($total_commission, 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- ── EXPENSES SECTION ── -->
                <div style="margin-bottom:18px;">
                    <div style="display:flex; align-items:center; margin-bottom:8px;">
                        <div style="background:#111; color:#fff; font-size:8pt; font-weight:800; padding:4px 12px; letter-spacing:.5px; text-transform:uppercase;">EXPENSES</div>
                        <div style="flex:1; height:1px; background:#111;"></div>
                        <div style="background:#dc2626; color:#fff; font-size:7pt; padding:4px 10px; font-weight:700;"><?php echo $month_name . ' ' . $filter_year; ?></div>
                    </div>
                    <table style="width:100%; border-collapse:collapse; font-size:8.5pt;">
                        <thead>
                            <tr style="background:#111; color:#fff;">
                                <th style="padding:7px 10px; text-align:left; font-weight:700; width:5%;">#</th>
                                <th style="padding:7px 10px; text-align:left; font-weight:700;">Expense Description</th>
                                <th style="padding:7px 10px; text-align:center; font-weight:700; width:15%;">Type</th>
                                <th style="padding:7px 10px; text-align:right; font-weight:700; width:22%;">Amount (Rs.)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $exp_idx = 1; ?>
                            <?php if (!empty($fixed_expenses)): ?>
                            <tr><td colspan="4" style="padding:5px 10px; font-size:7pt; font-weight:800; text-transform:uppercase; color:#888; letter-spacing:.8px; background:#f5f5f5; border-bottom:1px solid #e0e0e0;">Fixed Monthly Expenses</td></tr>
                            <?php foreach ($fixed_expenses as $exp): ?>
                            <tr style="background:<?php echo $exp_idx % 2 === 0 ? '#f9f9f9' : '#fff'; ?>; border-bottom:1px solid #eeeeee;">
                                <td style="padding:7px 10px; color:#888;"><?php echo $exp_idx++; ?></td>
                                <td style="padding:7px 10px; font-weight:500;"><?php echo htmlspecialchars($exp['name']); ?></td>
                                <td style="padding:7px 10px; text-align:center;"><span style="background:#fef9f0; color:#b45309; font-size:7pt; font-weight:700; padding:2px 8px; border:1px solid #fde68a;">FIXED</span></td>
                                <td style="padding:7px 10px; text-align:right; font-weight:600;">Rs.<?php echo number_format($exp['amount'], 2); ?></td>
                            </tr>
                            <?php endforeach; endif; ?>

                            <?php if (!empty($variable_expenses)): ?>
                            <tr><td colspan="4" style="padding:5px 10px; font-size:7pt; font-weight:800; text-transform:uppercase; color:#888; letter-spacing:.8px; background:#f5f5f5; border-bottom:1px solid #e0e0e0;">One-time Expenses</td></tr>
                            <?php foreach ($variable_expenses as $exp): ?>
                            <tr style="background:<?php echo $exp_idx % 2 === 0 ? '#f9f9f9' : '#fff'; ?>; border-bottom:1px solid #eeeeee;">
                                <td style="padding:7px 10px; color:#888;"><?php echo $exp_idx++; ?></td>
                                <td style="padding:7px 10px; font-weight:500;"><?php echo htmlspecialchars($exp['name']); ?></td>
                                <td style="padding:7px 10px; text-align:center; color:#888;">–</td>
                                <td style="padding:7px 10px; text-align:right; font-weight:600;">Rs.<?php echo number_format($exp['amount'], 2); ?></td>
                            </tr>
                            <?php endforeach; endif; ?>

                            <!-- Instructor Salaries -->
                            <tr><td colspan="4" style="padding:5px 10px; font-size:7pt; font-weight:800; text-transform:uppercase; color:#888; letter-spacing:.8px; background:#f5f5f5; border-bottom:1px solid #e0e0e0;">Instructor Salary Payments</td></tr>
                            <?php if (!empty($teacher_salaries)): foreach ($teacher_salaries as $ts): ?>
                            <tr style="background:<?php echo $exp_idx % 2 === 0 ? '#f9f9f9' : '#fff'; ?>; border-bottom:1px solid #eeeeee;">
                                <td style="padding:7px 10px; color:#888;"><?php echo $exp_idx++; ?></td>
                                <td style="padding:7px 10px; font-weight:500;"><?php echo htmlspecialchars($ts['first_name'] . ' ' . $ts['second_name']); ?> <span style="color:#888; font-size:7.5pt;">(Instructor Salary)</span></td>
                                <td style="padding:7px 10px; text-align:center;"><span style="background:#fdf4ff; color:#7c3aed; font-size:7pt; font-weight:700; padding:2px 8px; border:1px solid #e9d5ff;">SALARY</span></td>
                                <td style="padding:7px 10px; text-align:right; font-weight:600;">Rs.<?php echo number_format($ts['paid_amount'], 2); ?></td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="4" style="padding:8px 10px; text-align:center; color:#aaa; font-style:italic; font-size:8pt;">No instructor salaries paid this period.</td></tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background:#111; color:#fff;">
                                <td colspan="3" style="padding:7px 10px; text-align:right; font-weight:800; font-size:9pt;">GRAND TOTAL EXPENSES</td>
                                <td style="padding:7px 10px; text-align:right; font-weight:800; font-size:10pt; background:#dc2626;">Rs.<?php echo number_format($total_expenses, 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- ── TEACHER PAYMENT RANKING ── -->
                <?php if (!empty($teachers_with_income)): ?>
                <div style="margin-bottom:18px;">
                    <div style="display:flex; align-items:center; margin-bottom:8px;">
                        <div style="background:#dc2626; color:#fff; font-size:8pt; font-weight:800; padding:4px 12px; letter-spacing:.5px; text-transform:uppercase;">INSTRUCTOR INCOME RANKING</div>
                        <div style="flex:1; height:1px; background:#dc2626;"></div>
                    </div>
                    <table style="width:100%; border-collapse:collapse; font-size:8.5pt;">
                        <thead>
                            <tr style="background:#dc2626; color:#fff;">
                                <th style="padding:6px 10px; text-align:center; width:8%;">Rank</th>
                                <th style="padding:6px 10px; text-align:left;">Instructor</th>
                                <th style="padding:6px 10px; text-align:right;">Total Students Paid</th>
                                <th style="padding:6px 10px; text-align:right;">Institute Commission</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $ri2 = 1; foreach ($teachers_with_income as $t): ?>
                            <tr style="border-bottom:1px solid #e8e8e8; background:<?php echo $ri2 % 2 === 0 ? '#f9f9f9' : '#fff'; ?>;">
                                <td style="padding:6px 10px; text-align:center; font-weight:800; color:<?php echo $ri2===1?'#b45309':($ri2===2?'#6b7280':($ri2===3?'#92400e':'#aaa')); ?>;">
                                    <?php echo $ri2===1?'🥇':($ri2===2?'🥈':($ri2===3?'🥉':'#'.$ri2)); ?>
                                </td>
                                <td style="padding:6px 10px; font-weight:600;"><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['second_name']); ?></td>
                                <td style="padding:6px 10px; text-align:right; font-weight:700;">Rs.<?php echo number_format($t['total'], 2); ?></td>
                                <td style="padding:6px 10px; text-align:right; font-weight:700; color:#dc2626;">Rs.<?php echo number_format($t['commission'], 2); ?></td>
                            </tr>
                            <?php $ri2++; endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

            </div><!-- end body -->

            <!-- ── Footer ── -->
            <div style="margin:20px 32px 0; border-top:2px solid #dc2626; padding-top:10px;">
                <table style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td style="font-size:7.5pt; color:#888;">This is a computer-generated report. No signature required.</td>
                        <td style="text-align:right; font-size:7.5pt; color:#888;">Lernerr.LK · <?php echo date('Y'); ?></td>
                    </tr>
                </table>
            </div>

            <!-- Bottom red band -->
            <div style="background:#dc2626; height:12px; margin-top:20px; position:relative;">
                <div style="position:absolute; bottom:0; left:0; width:60px; height:60px; background:rgba(0,0,0,0.15); clip-path:polygon(0 100%, 0 0, 100% 100%);"></div>
            </div>

        </div><!-- end printDocument -->

        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 no-print">
            <div>
                <h1 class="text-2xl font-extrabold text-gray-900">Budget Reports</h1>
                <p class="text-sm text-gray-500 mt-0.5">Monthly financial overview for LearnerX Institute</p>
            </div>

            <!-- Filters + Actions -->
            <div class="flex flex-wrap items-center gap-3">
                <form method="GET" class="flex gap-2 items-center">
                    <select name="month" class="border border-gray-200 rounded-xl px-3 py-2 text-sm font-medium bg-white focus:ring-2 focus:ring-red-500 outline-none">
                        <?php foreach ($months as $mn => $ml): ?>
                            <option value="<?php echo $mn; ?>" <?php echo $mn == $filter_month ? 'selected' : ''; ?>><?php echo $ml; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="year" class="border border-gray-200 rounded-xl px-3 py-2 text-sm font-medium bg-white focus:ring-2 focus:ring-red-500 outline-none">
                        <?php for ($y = date('Y'); $y >= date('Y')-5; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $filter_year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold px-4 py-2 rounded-xl text-sm">
                        <i class="fas fa-filter mr-1"></i> Filter
                    </button>
                </form>

                <button onclick="doPrint()" class="bg-slate-800 hover:bg-black text-white font-bold px-4 py-2 rounded-xl text-sm flex items-center gap-2">
                    <i class="fas fa-print"></i> Download PDF
                </button>
            </div>
        </div>


        <!-- ═══ Stats Overview ═══ -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="stat-card bg-gradient-to-br from-green-50 to-emerald-50 border border-green-100">
                <p class="text-xs font-bold text-green-600 uppercase tracking-wider mb-2">Total Student Payments</p>
                <p class="text-2xl font-extrabold text-green-800">Rs.<?php echo number_format($total_student_payments, 2); ?></p>
                <p class="text-xs text-green-500 mt-1"><?php echo count(array_filter($teachers_income, fn($t) => $t['total'] > 0)); ?> teachers with income</p>
            </div>
            <div class="stat-card bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-100">
                <p class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-2">Institute Commission</p>
                <p class="text-2xl font-extrabold text-blue-800">Rs.<?php echo number_format($total_commission, 2); ?></p>
                <p class="text-xs text-blue-500 mt-1"><?php echo $commission_rate; ?>% of total payments</p>
            </div>
            <div class="stat-card bg-gradient-to-br from-red-50 to-rose-50 border border-red-100">
                <p class="text-xs font-bold text-red-600 uppercase tracking-wider mb-2">Total Expenses</p>
                <p class="text-2xl font-extrabold text-red-800">Rs.<?php echo number_format($total_expenses, 2); ?></p>
                <p class="text-xs text-red-400 mt-1">Fixed + Variable + Salaries</p>
            </div>
            <div class="stat-card bg-gradient-to-br from-purple-50 to-violet-50 border border-purple-100">
                <p class="text-xs font-bold text-purple-600 uppercase tracking-wider mb-2">Teacher Salaries Paid</p>
                <p class="text-2xl font-extrabold text-purple-800">Rs.<?php echo number_format($total_teacher_salaries, 2); ?></p>
                <p class="text-xs text-purple-400 mt-1"><?php echo count($teacher_salaries); ?> teachers paid</p>
            </div>
        </div>

        <!-- ═══ INCOME TABLE ═══ -->
        <div class="report-card mb-6 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-green-100 rounded-xl flex items-center justify-center text-green-600">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-extrabold text-gray-900">Income Breakdown</h2>
                        <p class="text-xs text-gray-400"><?php echo $month_name . ' ' . $filter_year; ?> · Student payments by teacher</p>
                    </div>
                </div>
                <span class="bg-green-100 text-green-700 text-xs font-bold px-3 py-1 rounded-full">
                    Rs.<?php echo number_format($total_student_payments, 2); ?>
                </span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">#</th>
                            <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Teacher</th>
                            <th class="px-5 py-3 text-right text-xs font-bold text-gray-500 uppercase">Enrollment Fees</th>
                            <th class="px-5 py-3 text-right text-xs font-bold text-gray-500 uppercase">Monthly Fees</th>
                            <th class="px-5 py-3 text-right text-xs font-bold text-gray-500 uppercase">Total Collected</th>
                            <th class="px-5 py-3 text-right text-xs font-bold text-gray-500 uppercase">Commission (Monthly)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if (empty(array_filter($teachers_income, fn($t) => $t['total'] > 0))): ?>
                            <tr><td colspan="6" class="text-center py-12 text-gray-400">No income recorded for this period.</td></tr>
                        <?php else: ?>
                            <?php $i = 1; foreach ($teachers_income as $t):
                                if ($t['total'] == 0) continue; ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3 text-gray-400 text-xs"><?php echo $i++; ?></td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <?php if ($t['profile_picture']): ?>
                                            <img src="../<?php echo htmlspecialchars($t['profile_picture']); ?>" class="w-8 h-8 rounded-full object-cover">
                                        <?php else: ?>
                                            <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center text-red-600 text-xs font-bold">
                                                <?php echo strtoupper(substr($t['first_name'],0,1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <span class="font-semibold text-gray-900">
                                            <?php echo htmlspecialchars($t['first_name'] . ' ' . $t['second_name']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right text-gray-700">Rs.<?php echo number_format($t['enrollment_total'], 2); ?></td>
                                <td class="px-5 py-3 text-right text-gray-700">Rs.<?php echo number_format($t['monthly_total'], 2); ?></td>
                                <td class="px-5 py-3 text-right font-bold text-gray-900">Rs.<?php echo number_format($t['total'], 2); ?></td>
                                <td class="px-5 py-3 text-right">
                                    <span class="bg-blue-50 text-blue-700 font-bold px-2 py-0.5 rounded-lg">
                                        Rs.<?php echo number_format($t['commission'], 2); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                        <tr>
                            <td colspan="4" class="px-5 py-3 font-extrabold text-gray-800 text-right">TOTAL</td>
                            <td class="px-5 py-3 text-right font-extrabold text-gray-900">Rs.<?php echo number_format($total_student_payments, 2); ?></td>
                            <td class="px-5 py-3 text-right">
                                <span class="bg-blue-600 text-white font-extrabold px-3 py-1 rounded-lg text-sm">
                                    Rs.<?php echo number_format($total_commission, 2); ?>
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- ═══ EXPENSES TABLE ═══ -->
        <div class="report-card mb-6 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-red-100 rounded-xl flex items-center justify-center text-red-600">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-extrabold text-gray-900">Expenses</h2>
                        <p class="text-xs text-gray-400"><?php echo $month_name . ' ' . $filter_year; ?> · Fixed + Variable expenses</p>
                    </div>
                </div>
                <span class="bg-red-100 text-red-700 text-xs font-bold px-3 py-1 rounded-full no-print">
                    Total: Rs.<?php echo number_format($total_fixed_expenses + $total_variable_expenses, 2); ?>
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm" id="expensesTable">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase w-8">#</th>
                            <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Expense Name</th>
                            <th class="px-5 py-3 text-center text-xs font-bold text-gray-500 uppercase w-24">Fixed</th>
                            <th class="px-5 py-3 text-right text-xs font-bold text-gray-500 uppercase">Amount (Rs.)</th>
                            <th class="px-5 py-3 text-center text-xs font-bold text-gray-500 uppercase no-print w-24">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="expenseTbody">
                        <!-- Fixed expenses -->
                        <?php if (!empty($fixed_expenses)): ?>
                            <tr><td colspan="5" class="px-5 py-2 text-[11px] font-extrabold text-gray-400 uppercase tracking-widest bg-gray-50/70">Fixed Monthly Expenses</td></tr>
                            <?php foreach ($fixed_expenses as $i => $exp): ?>
                                <tr class="expense-row border-b border-gray-50" data-id="<?php echo $exp['id']; ?>" data-fixed="1">
                                    <td class="px-5 text-gray-400 text-xs"><?php echo $i+1; ?></td>
                                    <td class="px-5"><input type="text" value="<?php echo htmlspecialchars($exp['name']); ?>" class="exp-name" readonly></td>
                                    <td class="px-5 text-center"><span class="inline-flex items-center gap-1 bg-amber-100 text-amber-700 text-xs font-bold px-2 py-0.5 rounded-full"><i class="fas fa-lock text-[10px]"></i> Fixed</span></td>
                                    <td class="px-5 text-right"><input type="number" value="<?php echo $exp['amount']; ?>" class="exp-amount text-right font-semibold" readonly></td>
                                    <td class="px-5 text-center no-print">
                                        <button onclick="editRow(this)" class="text-blue-500 hover:text-blue-700 text-xs font-bold mr-2"><i class="fas fa-pencil"></i></button>
                                        <button onclick="deleteExpense(<?php echo $exp['id']; ?>, this)" class="text-red-400 hover:text-red-600 text-xs font-bold"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- Variable expenses -->
                        <?php if (!empty($variable_expenses)): ?>
                            <tr><td colspan="5" class="px-5 py-2 text-[11px] font-extrabold text-gray-400 uppercase tracking-widest bg-gray-50/70">One-time Expenses (<?php echo $month_name; ?>)</td></tr>
                            <?php foreach ($variable_expenses as $j => $exp): ?>
                                <tr class="expense-row border-b border-gray-50" data-id="<?php echo $exp['id']; ?>" data-fixed="0">
                                    <td class="px-5 text-gray-400 text-xs"><?php echo $j+1; ?></td>
                                    <td class="px-5"><input type="text" value="<?php echo htmlspecialchars($exp['name']); ?>" class="exp-name" readonly></td>
                                    <td class="px-5 text-center"><span class="text-gray-300 text-xs">–</span></td>
                                    <td class="px-5 text-right"><input type="number" value="<?php echo $exp['amount']; ?>" class="exp-amount text-right font-semibold" readonly></td>
                                    <td class="px-5 text-center no-print">
                                        <button onclick="editRow(this)" class="text-blue-500 hover:text-blue-700 text-xs font-bold mr-2"><i class="fas fa-pencil"></i></button>
                                        <button onclick="deleteExpense(<?php echo $exp['id']; ?>, this)" class="text-red-400 hover:text-red-600 text-xs font-bold"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (empty($fixed_expenses) && empty($variable_expenses)): ?>
                            <tr><td colspan="5" class="text-center py-10 text-gray-400 text-sm">No expenses recorded. Add your first expense below.</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="border-t-2 border-gray-200 bg-gray-50">
                        <!-- Teacher Salaries Row -->
                        <tr class="border-b border-gray-100">
                            <td colspan="3" class="px-5 py-3 text-sm font-bold text-gray-700">
                                <i class="fas fa-chalkboard-teacher text-purple-500 mr-2"></i>
                                Instructor Salaries (Paid this month)
                            </td>
                            <td class="px-5 py-3 text-right font-bold text-purple-700">Rs.<?php echo number_format($total_teacher_salaries, 2); ?></td>
                            <td class="no-print"></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="px-5 py-3 font-extrabold text-gray-800 text-right text-base">GRAND TOTAL</td>
                            <td class="px-5 py-3 text-right">
                                <span class="text-lg font-extrabold text-red-700">Rs.<?php echo number_format($total_expenses, 2); ?></span>
                            </td>
                            <td class="no-print"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Add Expense Row -->
            <div class="px-6 py-4 border-t border-gray-100 no-print">
                <div class="flex flex-wrap items-center gap-3">
                    <input type="text" id="newExpName" placeholder="Expense name" class="border border-gray-200 rounded-xl px-3 py-2 text-sm flex-1 min-w-[160px] focus:ring-2 focus:ring-red-500 outline-none">
                    <input type="number" id="newExpAmount" placeholder="Amount" class="border border-gray-200 rounded-xl px-3 py-2 text-sm w-32 focus:ring-2 focus:ring-red-500 outline-none">
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-600 cursor-pointer">
                        <input type="checkbox" id="newExpFixed" class="w-4 h-4 rounded text-amber-500"> Fixed (every month)
                    </label>
                    <button onclick="addExpense()" class="bg-red-600 hover:bg-red-700 text-white font-bold px-4 py-2 rounded-xl text-sm flex items-center gap-2 transition-colors">
                        <i class="fas fa-plus"></i> Add Expense
                    </button>
                </div>
            </div>
        </div>

        <!-- ═══ TEACHER SALARIES BREAKDOWN ═══ -->
        <?php if (!empty($teacher_salaries)): ?>
        <div class="report-card mb-6 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
                <div class="w-9 h-9 bg-purple-100 rounded-xl flex items-center justify-center text-purple-600">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div>
                    <h2 class="text-base font-extrabold text-gray-900">Instructor Salary Payments</h2>
                    <p class="text-xs text-gray-400"><?php echo $month_name . ' ' . $filter_year; ?> · Sorted by amount paid</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">#</th>
                            <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Instructor</th>
                            <th class="px-5 py-3 text-right text-xs font-bold text-gray-500 uppercase">Amount Paid</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($teacher_salaries as $idx => $ts): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 text-gray-400 text-xs"><?php echo $idx+1; ?></td>
                            <td class="px-5 py-3 font-semibold text-gray-800">
                                <?php echo htmlspecialchars($ts['first_name'] . ' ' . $ts['second_name']); ?>
                            </td>
                            <td class="px-5 py-3 text-right font-bold text-purple-700">Rs.<?php echo number_format($ts['paid_amount'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                        <tr>
                            <td colspan="2" class="px-5 py-3 font-extrabold text-gray-800 text-right">TOTAL</td>
                            <td class="px-5 py-3 text-right font-extrabold text-purple-700">Rs.<?php echo number_format($total_teacher_salaries, 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ═══ TEACHER LEADERBOARD (no-print excluded from PDF) ═══ -->
        <div class="report-card mb-6 overflow-hidden no-print">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
                <div class="w-9 h-9 bg-amber-100 rounded-xl flex items-center justify-center text-amber-600">
                    <i class="fas fa-trophy"></i>
                </div>
                <div>
                    <h2 class="text-base font-extrabold text-gray-900">Top Earning Teachers</h2>
                    <p class="text-xs text-gray-400">By total student payment volume · <?php echo $month_name . ' ' . $filter_year; ?></p>
                </div>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php
                $ranked = array_filter($teachers_income, fn($t) => $t['total'] > 0);
                $ranked = array_values($ranked);
                if (empty($ranked)): ?>
                    <div class="col-span-3 text-center py-10 text-gray-400">No teacher income this period.</div>
                <?php else:
                    foreach ($ranked as $ri => $t): ?>
                    <div class="flex items-center gap-4 p-4 rounded-2xl border border-gray-100 hover:border-amber-200 hover:bg-amber-50/30 transition-all">
                        <div class="relative flex-shrink-0">
                            <?php if ($t['profile_picture']): ?>
                                <img src="../<?php echo htmlspecialchars($t['profile_picture']); ?>" class="w-12 h-12 rounded-full object-cover">
                            <?php else: ?>
                                <div class="w-12 h-12 bg-gradient-to-br from-red-400 to-red-600 rounded-full flex items-center justify-center text-white font-bold text-base">
                                    <?php echo strtoupper(substr($t['first_name'],0,1)); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($ri === 0): ?>
                                <span class="absolute -top-1 -right-1 w-5 h-5 bg-amber-400 rounded-full flex items-center justify-center text-[10px]">🥇</span>
                            <?php elseif ($ri === 1): ?>
                                <span class="absolute -top-1 -right-1 w-5 h-5 bg-slate-300 rounded-full flex items-center justify-center text-[10px]">🥈</span>
                            <?php elseif ($ri === 2): ?>
                                <span class="absolute -top-1 -right-1 w-5 h-5 bg-amber-600 rounded-full flex items-center justify-center text-[10px]">🥉</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-gray-900 text-sm truncate"><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['second_name']); ?></p>
                            <p class="text-xs text-gray-400 mt-0.5">Commission: <strong class="text-blue-600">Rs.<?php echo number_format($t['commission'],0); ?></strong></p>
                        </div>
                        <div class="text-right">
                            <p class="font-extrabold text-green-700 text-sm">Rs.<?php echo number_format($t['total'],0); ?></p>
                            <p class="text-[10px] text-gray-400">total</p>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

    </div><!-- end max-w -->

    <script>
        const FILTER_MONTH = <?php echo $filter_month; ?>;
        const FILTER_YEAR  = <?php echo $filter_year; ?>;

        // ── Show formal doc → print → hide ──────────────────
        function doPrint() {
            const doc = document.getElementById('printDocument');
            doc.style.display = 'block';
            // Small delay so browser renders it before printing
            setTimeout(() => {
                window.print();
                // Hide again after dialog closes
                setTimeout(() => { doc.style.display = 'none'; }, 500);
            }, 150);
        }
        // ── Edit expense row ─────────────────────────────────
        function editRow(btn) {
            const row = btn.closest('tr.expense-row');
            const isEditing = row.classList.contains('editing');

            if (!isEditing) {
                // Enter edit mode
                row.classList.add('editing');
                row.querySelectorAll('input').forEach(i => i.removeAttribute('readonly'));
                btn.innerHTML = '<i class="fas fa-save"></i>';
                btn.classList.replace('text-blue-500','text-green-600');
                btn.onclick = () => saveRow(btn);
            }
        }

        function saveRow(btn) {
            const row = btn.closest('tr.expense-row');
            const id       = row.dataset.id;
            const is_fixed = row.dataset.fixed;
            const name   = row.querySelector('.exp-name').value;
            const amount = row.querySelector('.exp-amount').value;

            fetch('reports_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=edit_expense&id=${id}&name=${encodeURIComponent(name)}&amount=${amount}&is_fixed=${is_fixed}&month=${FILTER_MONTH}&year=${FILTER_YEAR}`
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    row.classList.remove('editing');
                    row.querySelectorAll('input').forEach(i => i.setAttribute('readonly', true));
                    btn.innerHTML = '<i class="fas fa-pencil"></i>';
                    btn.classList.replace('text-green-600','text-blue-500');
                    btn.onclick = () => editRow(btn);
                }
            });
        }

        // ── Delete expense ───────────────────────────────────
        function deleteExpense(id, btn) {
            if (!confirm('Delete this expense?')) return;
            fetch('reports_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=delete_expense&id=${id}`
            }).then(r => r.json()).then(d => {
                if (d.success) btn.closest('tr').remove();
            });
        }

        // ── Add new expense ──────────────────────────────────
        function addExpense() {
            const name     = document.getElementById('newExpName').value.trim();
            const amount   = document.getElementById('newExpAmount').value;
            const is_fixed = document.getElementById('newExpFixed').checked ? 1 : 0;

            if (!name || !amount || parseFloat(amount) <= 0) {
                alert('Please enter a valid expense name and amount.');
                return;
            }

            fetch('reports_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=add_expense&name=${encodeURIComponent(name)}&amount=${amount}&is_fixed=${is_fixed}&month=${FILTER_MONTH}&year=${FILTER_YEAR}`
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    document.getElementById('newExpName').value   = '';
                    document.getElementById('newExpAmount').value = '';
                    document.getElementById('newExpFixed').checked = false;
                    location.reload();
                } else {
                    alert(d.message);
                }
            });
        }

        // Allow pressing Enter in add expense fields
        document.getElementById('newExpAmount').addEventListener('keydown', e => {
            if (e.key === 'Enter') addExpense();
        });
    </script>
</body>
</html>

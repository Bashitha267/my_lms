<?php
require_once 'config.php';

// Simple password protection - change this password!
$access_password = 'lms@admin2024';
$entered = $_GET['key'] ?? '';
if ($entered !== $access_password) {
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;background:#f5f5f5;">
    <div style="background:white;padding:40px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.1);text-align:center">
    <h2 style="color:#e53e3e;margin-bottom:20px;">🔒 Access Key Required</h2>
    <form>
    <input name="key" type="password" placeholder="Enter access key" style="padding:10px 16px;border:1px solid #ccc;border-radius:6px;font-size:15px;width:250px;display:block;margin:0 auto 12px;">
    <button type="submit" style="background:#2563eb;color:white;padding:10px 30px;border:none;border-radius:6px;cursor:pointer;font-size:15px;">Unlock</button>
    </form></div></body></html>';
    exit;
}

// Get selected table from query string
$selected_table = $_GET['table'] ?? '';

// Fetch all tables in the database
$tables_result = $conn->query("SHOW TABLES");
$all_tables = [];
while ($row = $tables_result->fetch_row()) {
    $all_tables[] = $row[0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>DB Inspector – <?php echo htmlspecialchars(DB_NAME); ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', system-ui, sans-serif; background: #0f172a; color: #e2e8f0; display: flex; min-height: 100vh; }

  /* Sidebar */
  #sidebar {
    width: 260px; min-height: 100vh; background: #1e293b; border-right: 1px solid #334155;
    display: flex; flex-direction: column; flex-shrink: 0; position: sticky; top: 0; height: 100vh; overflow-y: auto;
  }
  #sidebar h2 { padding: 20px 16px 12px; font-size: 11px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #334155; }
  .db-name { padding: 10px 16px 6px; font-size: 13px; font-weight: 600; color: #38bdf8; }
  .table-count { font-size: 11px; color: #64748b; padding: 0 16px 12px; border-bottom: 1px solid #334155; margin-bottom: 6px; }
  .sidebar-search { padding: 10px 12px; }
  .sidebar-search input { width: 100%; padding: 7px 10px; background: #0f172a; border: 1px solid #334155; border-radius: 6px; color: #e2e8f0; font-size: 13px; outline: none; }
  .sidebar-search input:focus { border-color: #38bdf8; }
  .table-list { flex: 1; overflow-y: auto; }
  .table-link {
    display: block; padding: 9px 16px; font-size: 13px; color: #94a3b8; text-decoration: none;
    border-left: 3px solid transparent; transition: all .15s; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .table-link:hover { background: #0f172a; color: #e2e8f0; border-color: #475569; }
  .table-link.active { background: #0f172a; color: #38bdf8; border-color: #38bdf8; font-weight: 600; }

  /* Main */
  #main { flex: 1; padding: 28px; overflow: auto; }
  #main h1 { font-size: 22px; font-weight: 700; color: #f1f5f9; margin-bottom: 4px; }
  .subtitle { font-size: 13px; color: #64748b; margin-bottom: 24px; }

  /* Stats bar */
  .stats-bar { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 28px; }
  .stat-card { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 14px 20px; min-width: 130px; }
  .stat-card .label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: .08em; margin-bottom: 4px; }
  .stat-card .value { font-size: 22px; font-weight: 700; color: #38bdf8; }

  /* Table detail */
  .section-title { font-size: 13px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .1em; margin: 24px 0 10px; }
  .schema-table, .data-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 24px; }
  .schema-table th, .data-table th { background: #1e293b; color: #64748b; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: .08em; padding: 10px 12px; text-align: left; border-bottom: 1px solid #334155; }
  .schema-table td, .data-table td { padding: 9px 12px; border-bottom: 1px solid #1e293b; color: #cbd5e1; vertical-align: top; word-break: break-all; max-width: 300px; }
  .schema-table tr:hover td, .data-table tr:hover td { background: #1e293b33; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 700; letter-spacing: .05em; }
  .badge-pri { background: #fef08a22; color: #fde047; border: 1px solid #fde04740; }
  .badge-uni { background: #a5f3fc22; color: #67e8f9; border: 1px solid #67e8f940; }
  .badge-mul { background: #d8b4fe22; color: #c084fc; border: 1px solid #c084fc40; }
  .badge-null { background: #f8717122; color: #f87171; border: 1px solid #f8717140; }
  .badge-notnull { background: #4ade8022; color: #4ade80; border: 1px solid #4ade8040; }
  .row-count-info { background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 10px 16px; font-size: 13px; color: #64748b; margin-bottom: 12px; display: flex; align-items: center; gap: 10px; }
  .row-count-info strong { color: #38bdf8; font-size: 17px; }
  .overflow-wrapper { overflow-x: auto; border-radius: 10px; border: 1px solid #334155; }
  .no-rows { background: #1e293b; border-radius: 8px; padding: 24px; text-align: center; color: #475569; font-size: 13px; margin-bottom: 24px; }
  .welcome { background: #1e293b; border-radius: 14px; padding: 40px; text-align: center; border: 1px solid #334155; }
  .welcome h2 { font-size: 20px; color: #38bdf8; margin-bottom: 8px; }
  .welcome p { color: #64748b; font-size: 14px; }

  /* Pagination */
  .pagination { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 20px; align-items: center; }
  .pagination a { padding: 5px 12px; background: #1e293b; border: 1px solid #334155; border-radius: 6px; color: #94a3b8; text-decoration: none; font-size: 12px; transition: all .15s; }
  .pagination a:hover, .pagination a.cur { background: #38bdf8; color: #0f172a; border-color: #38bdf8; font-weight: 700; }
  .pagination span { font-size: 12px; color: #475569; }

  .delete-warning { background: #7f1d1d22; border: 1px solid #ef444440; border-radius: 8px; padding: 12px 16px; font-size: 12px; color: #fca5a5; margin-bottom: 16px; }

  @media (max-width: 700px) {
    body { flex-direction: column; }
    #sidebar { width: 100%; height: auto; position: static; }
    .table-list { max-height: 200px; }
  }
</style>
</head>
<body>

<!-- SIDEBAR -->
<div id="sidebar">
  <h2>DB Inspector</h2>
  <div class="db-name">📦 <?php echo htmlspecialchars(DB_NAME); ?></div>
  <div class="table-count"><?php echo count($all_tables); ?> tables found</div>
  <div class="sidebar-search">
    <input type="text" id="tableSearch" placeholder="Search tables..." oninput="filterTables(this.value)">
  </div>
  <div class="table-list" id="tableList">
    <?php foreach ($all_tables as $t): ?>
      <a href="?key=<?php echo urlencode($access_password); ?>&table=<?php echo urlencode($t); ?>"
         class="table-link <?php echo ($selected_table === $t) ? 'active' : ''; ?>">
        <?php echo htmlspecialchars($t); ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- MAIN CONTENT -->
<div id="main">

<?php if (empty($selected_table)): ?>
  <div class="welcome">
    <h2>🗄️ Database Inspector</h2>
    <p>Select a table from the sidebar to view its structure and data.</p>
    <br>
    <div class="stats-bar" style="justify-content:center;">
      <div class="stat-card"><div class="label">Database</div><div class="value" style="font-size:15px;color:#e2e8f0;"><?php echo htmlspecialchars(DB_NAME); ?></div></div>
      <div class="stat-card"><div class="label">Total Tables</div><div class="value"><?php echo count($all_tables); ?></div></div>
      <div class="stat-card"><div class="label">Server</div><div class="value" style="font-size:15px;color:#e2e8f0;"><?php echo htmlspecialchars(DB_HOST); ?></div></div>
    </div>
  </div>

<?php else:
  // Validate table name against known tables
  if (!in_array($selected_table, $all_tables)) {
      echo '<div class="delete-warning">❌ Table not found.</div>';
  } else {
      // Pagination
      $per_page = 50;
      $page = max(1, intval($_GET['page'] ?? 1));
      $offset = ($page - 1) * $per_page;

      // Row count
      $count_res = $conn->query("SELECT COUNT(*) as cnt FROM `" . $conn->real_escape_string($selected_table) . "`");
      $total_rows = $count_res ? $count_res->fetch_assoc()['cnt'] : 0;
      $total_pages = max(1, ceil($total_rows / $per_page));

      // Schema
      $schema_res = $conn->query("DESCRIBE `" . $conn->real_escape_string($selected_table) . "`");
      $columns = [];
      if ($schema_res) { while ($r = $schema_res->fetch_assoc()) { $columns[] = $r; } }

      // Data
      $data_res = $conn->query("SELECT * FROM `" . $conn->real_escape_string($selected_table) . "` LIMIT $per_page OFFSET $offset");
      $rows = [];
      if ($data_res) { while ($r = $data_res->fetch_assoc()) { $rows[] = $r; } }
?>

  <h1>📋 <?php echo htmlspecialchars($selected_table); ?></h1>
  <div class="subtitle">Database: <strong style="color:#38bdf8"><?php echo htmlspecialchars(DB_NAME); ?></strong></div>

  <div class="stats-bar">
    <div class="stat-card"><div class="label">Total Rows</div><div class="value"><?php echo number_format($total_rows); ?></div></div>
    <div class="stat-card"><div class="label">Columns</div><div class="value"><?php echo count($columns); ?></div></div>
    <div class="stat-card"><div class="label">Page</div><div class="value"><?php echo $page; ?> / <?php echo $total_pages; ?></div></div>
  </div>

  <!-- SCHEMA -->
  <div class="section-title">Table Structure</div>
  <div class="overflow-wrapper">
    <table class="schema-table">
      <thead>
        <tr>
          <th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($columns as $col): ?>
        <tr>
          <td style="color:#f1f5f9;font-weight:600;"><?php echo htmlspecialchars($col['Field']); ?></td>
          <td style="color:#a78bfa;"><?php echo htmlspecialchars($col['Type']); ?></td>
          <td>
            <?php if ($col['Null'] === 'YES'): ?>
              <span class="badge badge-null">NULL</span>
            <?php else: ?>
              <span class="badge badge-notnull">NOT NULL</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($col['Key'] === 'PRI'): ?><span class="badge badge-pri">PRI</span>
            <?php elseif ($col['Key'] === 'UNI'): ?><span class="badge badge-uni">UNI</span>
            <?php elseif ($col['Key'] === 'MUL'): ?><span class="badge badge-mul">MUL</span>
            <?php else: ?><span style="color:#475569;">–</span><?php endif; ?>
          </td>
          <td style="color:#94a3b8;"><?php echo $col['Default'] !== null ? htmlspecialchars($col['Default']) : '<span style="color:#475569">NULL</span>'; ?></td>
          <td style="color:#34d399;font-size:11px;"><?php echo htmlspecialchars($col['Extra']); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- DATA -->
  <div class="section-title">Table Data</div>

  <?php if ($total_pages > 1): ?>
  <div class="pagination">
    <span>Rows <?php echo ($offset+1); ?>–<?php echo min($offset+$per_page, $total_rows); ?> of <?php echo number_format($total_rows); ?></span>
    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
      <a href="?key=<?php echo urlencode($access_password); ?>&table=<?php echo urlencode($selected_table); ?>&page=<?php echo $p; ?>"
         class="<?php echo ($p == $page) ? 'cur' : ''; ?>"><?php echo $p; ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

  <?php if (empty($rows)): ?>
    <div class="no-rows">📭 This table has no data yet.</div>
  <?php else: ?>
  <div class="overflow-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <?php foreach ($columns as $col): ?>
            <th><?php echo htmlspecialchars($col['Field']); ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
          <?php foreach ($row as $val): ?>
            <td>
              <?php if ($val === null): ?>
                <span style="color:#475569;font-style:italic;">NULL</span>
              <?php elseif (strlen($val) > 120): ?>
                <span title="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars(substr($val, 0, 120)); ?>…</span>
              <?php else: ?>
                <?php echo htmlspecialchars($val); ?>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

<?php } endif; ?>
</div>

<script>
function filterTables(q) {
  q = q.toLowerCase();
  document.querySelectorAll('.table-link').forEach(a => {
    a.style.display = a.textContent.toLowerCase().includes(q) ? 'block' : 'none';
  });
}
</script>
</body>
</html>

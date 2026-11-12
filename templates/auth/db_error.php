<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Database unavailable</title>
<meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="font-family:Segoe UI,Arial,sans-serif;max-width:660px;margin:9vh auto;padding:0 16px;line-height:1.5">
<h2>Cannot reach the database</h2>
<p>The application could not connect to MySQL/MariaDB. Nothing has been changed.</p>
<ol>
  <li>Open the XAMPP Control Panel and make sure <b>MySQL</b> is running (green).</li>
  <li>Check the database name, user and password in <code>app/config/config.php</code>.</li>
  <li>Look at <code>storage/logs/app-errors.log</code> for the exact reason.</li>
</ol>
<?php if (!empty($debug)): ?><pre style="white-space:pre-wrap;background:#f3f4f6;padding:10px"><?= htmlspecialchars($error) ?></pre><?php endif; ?>
</body></html>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<?php
session_start();

// Login handler: use MySQL DB (isp_portal.users) if available, fallback message when DB missing
$messages = [];
// prepare PDO (allow override with config/db.php that sets $pdo)
$pdo = null;
$configPath = __DIR__ . '/config/db.php';
if (file_exists($configPath)) {
    include $configPath; // should set $pdo
}
if (empty($pdo)) {
    $dbHost = '127.0.0.1';
    $dbName = 'isp_portal';
    $dbUser = 'root';
    $dbPass = ''; // adjust if you use a password
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Exception $e) {
        $pdo = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $emailRaw = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL) ? $emailRaw : '';

    if (!$email || $password === '') {
        $messages[] = 'Please provide email and password.';
    } else {
        if ($pdo) {
            try {
                // users table columns: id,email,password_hash,roll,name
                $stmt = $pdo->prepare('SELECT id, email, password_hash, role, name FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $row = $stmt->fetch();
                if ($row) {
                    $stored = $row['password_hash'] ?? '';
                    $isValid = false;

                    // If stored value looks like a PHP password_hash (algo != 0) verify it
                    $info = password_get_info($stored);
                    if (!empty($stored) && !empty($info['algo'])) {
                        if (password_verify($password, $stored)) {
                            $isValid = true;
                        }
                    } else {
                        // Stored value appears to be plaintext (or non-standard). Compare safely.
                        if (hash_equals((string)$stored, (string)$password)) {
                            $isValid = true;
                            // Attempt to upgrade to a proper hash (best-effort; ignore failures)
                            try {
                                $newHash = password_hash($password, PASSWORD_DEFAULT);
                                if ($newHash) {
                                    $up = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                                    $up->execute([$newHash, $row['id']]);
                                }
                            } catch (Exception $e) {
                                // ignore upgrade errors
                            }
                        }
                    }

                    if ($isValid) {
                         // successful login
                         $_SESSION['user'] = [
                             'id' => $row['id'],
                             'email' => $row['email'],
                             'role' => $row['role'] ?? 'user',
                             'name' => $row['name'] ?? null,
                         ];
                         // redirect to appropriate dashboard
                         $role = strtolower($_SESSION['user']['role'] ?? 'user');
                         if ($role === 'admin') header('Location: /super/dashboard_admin.php');
                         elseif ($role === 'isp') header('Location: /dashboard_isp.php');
                         else header('Location: dashboard_user.php');
                         exit;
                    } else {
                        $messages[] = 'Invalid email or password.';
                    }
                } else {
                    $messages[] = 'Invalid email or password.';
                }
            } catch (Exception $e) {
                // write full exception to local log (ensure logs/ is writable)
                @mkdir(__DIR__ . '/logs', 0755, true);
                @file_put_contents(__DIR__ . '/logs/auth.log',
                    date('Y-m-d H:i:s') . ' ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL . PHP_EOL,
                    FILE_APPEND
                );
                error_log('Auth error: ' . $e->getMessage());
                // keep user-facing message generic
                $messages[] = 'Login failed (server error). Please try again later.';
            }
        } else {
            $messages[] = 'Authentication unavailable — DB not connected.';
        }
    }
}

// quick logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: /');
    exit;
}

$loggedIn = !empty($_SESSION['user']);
$user = $_SESSION['user'] ?? null;

// after session / $loggedIn / $user setup, add server public IP (best-effort)
$serverPublicIp = '—';
function fetchPublicIpFromService($url = 'https://api.ipify.org?format=json') {
    $json = null;
    if (function_exists('curl_version')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $json = curl_exec($ch);
        curl_close($ch);
    } elseif (ini_get('allow_url_fopen')) {
        $json = @file_get_contents($url);
    }
    if ($json) {
        $j = json_decode($json, true);
        if (is_array($j) && !empty($j['ip'])) return $j['ip'];
    }
    return null;
}
$ip = fetchPublicIpFromService();
if ($ip) $serverPublicIp = $ip;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>SilverWave — Global ISP Platform</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bg-0: #f6fbff;
    --bg-1: #eef6f9;
    --card-bg: #ffffff;
    --glass: rgba(15,23,42,0.02);
    --accent: #00a6ff;
    --accent-2: #6f6bff;
    --muted: #6b7280;
    --text: #07203a;
    --card-border: rgba(7,20,40,0.06);
    --shadow: 0 18px 48px rgba(6,18,35,0.08);
    --max-width:1200px;
  }

  *{box-sizing:border-box;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;color:var(--text)}
  html,body{height:100%;margin:0;background:linear-gradient(180deg,var(--bg-0),var(--bg-1));-webkit-font-smoothing:antialiased}

  /* site shell full-screen */
  .site{position:relative;min-height:100vh;display:flex;flex-direction:column}
  header{display:flex;align-items:center;justify-content:space-between;padding:20px 36px;background:transparent}
  .brand{display:flex;align-items:center;gap:14px}
  .logo{width:56px;height:56px;border-radius:12px;background:linear-gradient(135deg,var(--accent),var(--accent-2));display:flex;align-items:center;justify-content:center;font-weight:800;color:#022;font-size:18px;box-shadow:0 12px 40px rgba(12,15,40,0.06)}
  .title{font-weight:700;font-size:18px;color:var(--text)}
  nav{display:flex;gap:12px;align-items:center}
  a.nav{color:var(--muted);text-decoration:none;padding:8px 12px;border-radius:8px;font-size:14px}
  a.nav:hover{color:var(--text);background:rgba(0,0,0,0.02)}

  .btn{background:linear-gradient(90deg,var(--accent),var(--accent-2));color:#fff;padding:10px 16px;border-radius:10px;border:0;font-weight:700;cursor:pointer;box-shadow:var(--shadow)}
  .btn.ghost{background:transparent;border:1px solid rgba(7,20,40,0.04);color:var(--muted)}

  /* hero fills viewport (minus header/footer) */
  main{flex:1;display:flex;align-items:stretch;justify-content:center;padding:24px}
  .hero{width:100%;max-width:var(--max-width);display:grid;grid-template-columns:1fr 480px;gap:36px;align-items:center;padding:36px;border-radius:16px;background:var(--card-bg);border:1px solid var(--card-border);box-shadow:var(--shadow);min-height:calc(100vh - 160px)}
  @media(max-width:1100px){ .hero{grid-template-columns:1fr; padding:24px; min-height:auto } }

  .left h1{margin:0;font-size:40px;line-height:1.02;color:var(--text)}
  .lead{color:var(--muted);margin-top:12px;font-size:16px}
  .actions{margin-top:22px;display:flex;gap:14px;align-items:center}
  .kpis{display:flex;gap:14px;margin-top:28px}
  .kpi{flex:1;background:transparent;padding:16px;border-radius:12px}
  .kpi .val{font-weight:800;font-size:20px;color:var(--accent)}
  .kpi .lbl{color:var(--muted);font-size:13px;margin-top:6px}

  /* right: globe panel adapted for light theme */
  .right{position:relative;display:flex;flex-direction:column;gap:14px}
  .panel{background:linear-gradient(180deg,var(--card-bg),#fbfdff);border-radius:12px;padding:18px;border:1px solid var(--card-border);box-shadow:0 10px 30px rgba(6,20,40,0.04)}
  .globe-wrap{height:320px;border-radius:10px;overflow:hidden;background:linear-gradient(180deg,#ecf8ff,#f8fbff);display:flex;align-items:center;justify-content:center;position:relative}
  .globe-svg{width:100%;height:100%;opacity:0.98}

  .status-row{display:flex;gap:12px;margin-top:6px}
  .status{flex:1;padding:10px;border-radius:10px;background:transparent;text-align:center}
  .status .label{color:var(--muted);font-size:12px}
  .status .value{font-weight:800;font-size:18px;margin-top:6px;color:var(--text)}

  .providers{display:flex;align-items:center;justify-content:center;gap:10px;margin-top:6px;color:var(--muted);font-weight:600}
  .providers span{color:var(--text);opacity:0.9}

  .features{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:28px}
  .feature{background:transparent;padding:18px;border-radius:10px;text-align:center}
  .feature h4{margin:6px 0 0 0;color:var(--text)}

  footer{padding:18px 36px;color:var(--muted);display:flex;justify-content:space-between;align-items:center;border-top:1px solid rgba(7,20,40,0.03);margin-top:24px}

  @media(max-width:1100px){
    .hero{grid-template-columns:1fr}
    .globe-wrap{height:260px}
    .features{grid-template-columns:1fr 1fr}
  }
  @media(max-width:680px){
    header{padding:12px}
    .hero{padding:18px}
    .features{grid-template-columns:1fr}
  }

  /* speed visuals (adapted color for light theme) */
  .speed-graphic{position:absolute;left:18px;bottom:18px;width:320px;height:140px;display:flex;gap:12px;align-items:center;z-index:6;pointer-events:none}
  .gauge .arc{fill:none;stroke:rgba(7,20,40,0.06);stroke-width:10;stroke-linecap:round;filter:drop-shadow(0 6px 18px rgba(6,20,40,0.06))}
  .gauge .arc-anim{fill:none;stroke:var(--accent);stroke-width:10;stroke-linecap:round;transition:stroke-dashoffset 600ms cubic-bezier(.2,.9,.2,1)}
  .gauge .label{font-size:20px;font-weight:800;fill:var(--text);text-anchor:middle}
  .waveform .bar{width:6px;background:linear-gradient(180deg,var(--accent),var(--accent-2));border-radius:3px;opacity:0.98;transform-origin:bottom;transition:height 300ms ease}
  .small-note{font-size:12px;color:var(--muted);margin-top:6px}
  @media(max-width:900px){ .speed-graphic{width:260px} .gauge{width:130px;height:110px} }

  /* subtle light network wallpaper (keeps performance) */
  body::after{
    content:"";position:fixed;inset:0;pointer-events:none;z-index:0;opacity:0.9;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1600 900'><defs><linearGradient id='lg' x1='0' x2='1'><stop offset='0' stop-color='%2300a6ff' stop-opacity='0.06'/><stop offset='1' stop-color='%236f6bff' stop-opacity='0.04'/></linearGradient></defs><rect width='1600' height='900' fill='none'/><g stroke='rgba(7,20,40,0.06)' stroke-width='5' fill='none' opacity='0.06'><path d='M0 200 C400 20 1200 20 1600 200'/><path d='M0 450 C400 270 1200 270 1600 450'/><path d='M0 700 C400 520 1200 520 1600 700'/></g><g stroke='url(%23lg)' stroke-width='3' fill='none' opacity='0.08'><path d='M40 190 L140 150 L240 190'/><path d='M40 440 L140 400 L240 440'/><path d='M40 690 L140 650 L240 690'/></g></svg>");
    background-size:cover;background-position:center;mix-blend-mode:normal;filter:blur(0.6px) saturate(1.02);
  }

  /* ensure content above wallpaper */
  header,.hero,.panel,.logo,.card{position:relative;z-index:2}

  /* Dark-styled login modal */
  .modal-backdrop{display:none;position:fixed;inset:0;align-items:center;justify-content:center;background:linear-gradient(180deg,rgba(2,6,10,0.6),rgba(2,6,10,0.68));z-index:120}
  .login-card{width:420px;border-radius:14px;overflow:hidden;background:linear-gradient(180deg,rgba(255,255,255,0.02),rgba(255,255,255,0.01));box-shadow:0 30px 80px rgba(2,6,23,0.7);border:1px solid rgba(255,255,255,0.04);backdrop-filter:blur(6px) saturate(1.05)}
  .login-top{display:flex;gap:12px;align-items:center;padding:18px;background:linear-gradient(90deg,var(--accent),var(--accent-2));color:#021}
  .logo-small{width:44px;height:44px;border-radius:10px;background:rgba(255,255,255,0.12);display:inline-grid;place-items:center;font-weight:800;color:#021}
  .login-title{font-weight:800;font-size:18px;color:#021}
  .login-sub{font-size:13px;color:rgba(2,6,23,0.65)}
  .login-body{padding:20px;background:linear-gradient(180deg,rgba(0,0,0,0.28),rgba(0,0,0,0.2))}
  .form-row{display:flex;flex-direction:column;gap:8px;margin-bottom:10px}
  .input{width:100%;padding:12px;border-radius:10px;border:1px solid rgba(255,255,255,0.04);background:transparent;color:var(--text);outline:none;font-size:14px}
  .input::placeholder{color:rgba(255,255,255,0.5)}
  .controls{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:6px}
  .btn-primary{background:linear-gradient(90deg,var(--accent),var(--accent-2));color:#041726;padding:10px 14px;border-radius:10px;border:0;font-weight:800;cursor:pointer;box-shadow:0 10px 30px rgba(2,6,23,0.45)}
  .btn-ghost{background:transparent;border:1px solid rgba(255,255,255,0.04);color:var(--muted);padding:8px 12px;border-radius:8px;cursor:pointer}
  .link-small{font-size:13px;color:var(--muted);text-decoration:none}
  .close-x{position:absolute;right:12px;top:10px;color:rgba(255,255,255,0.9);background:transparent;border:0;font-size:20px;cursor:pointer}
  .msg{margin-bottom:12px;padding:10px;border-radius:8px;background:rgba(255,16,16,0.06);border:1px solid rgba(255,16,16,0.08);color:#ffdddd;font-size:13px}
  @media(max-width:480px){ .login-card{width:92vw} .login-top .logo-small{width:40px;height:40px} }
</style>
</head>
<body>
<div class="site">
  <header>
    <div class="brand">
      <div class="logo" aria-hidden="true">SW</div>
      <div>
        <div class="title">Udhayan Enterprises</div>
        <div style="font-size:13px;color:var(--muted)">Global ISP Platform</div>
      </div>
    </div>

    <nav>
      <a class="nav" href="/plans.php">Plans</a>
      <a class="nav" href="/providers.php">Providers</a>
      <a class="nav" href="/about.php">About</a>
      <a class="nav" href="/support.php">Support</a>

      <?php if ($loggedIn): ?>
        <div style="display:flex;align-items:center;gap:12px;margin-left:12px">
          <div style="color:var(--muted);font-size:13px">Signed in as <?= htmlspecialchars($user['email']) ?></div>
          <a class="nav" href="/auth.php?action=logout">Logout</a>
          <a class="btn" href="/dashboard_<?= htmlspecialchars($user['role']) ?>.php">Dashboard</a>
        </div>
      <?php else: ?>
        <button class="btn" onclick="openLogin(true)">Login</button>
      <?php endif; ?>
    </nav>
  </header>

  <main>
    <section class="hero" role="region" aria-labelledby="hero-title">
      <div class="left">
        <h1 id="hero-title">Enterprise-grade connectivity — worldwide</h1>
        <p class="lead">Single-pane portal to compare and manage RailWire, BSNL, AirFiber, N Net and other global providers. SLA-backed plans up to 10 Gbps with real-time monitoring.</p>

        <div class="actions">
          <button class="btn" onclick="openLogin(true)">Get started</button>
          <a class="btn ghost" href="/plans.php">Explore plans</a>
        </div>

        <div class="kpis" role="list">
          <div class="kpi">
            <div class="val" id="speedValue">—</div>
            <div class="lbl">Live throughput</div>
          </div>
          <div class="kpi">
            <div class="val" id="latencyValue">—</div>
            <div class="lbl">Latency (ms)</div>
          </div>
          <div class="kpi">
            <div class="val" id="jitterValue">—</div>
            <div class="lbl">Jitter (ms)</div>
          </div>
        </div>

        <div class="features" aria-hidden="false">
          <div class="feature"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" style="margin:auto"><circle cx="12" cy="12" r="10" stroke="rgba(255,255,255,0.08)"/></svg><h4>Global POPs</h4><div style="color:var(--muted);font-size:13px">Local presence, global reach</div></div>
          <div class="feature"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" style="margin:auto"><rect x="4" y="4" width="16" height="16" rx="3" stroke="rgba(255,255,255,0.08)"/></svg><h4>99.99% SLA</h4><div style="color:var(--muted);font-size:13px">Uptime & priority support</div></div>
          <div class="feature"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" style="margin:auto"><path d="M3 12h18" stroke="rgba(255,255,255,0.08)"/></svg><h4>Secure</h4><div style="color:var(--muted);font-size:13px">DDoS mitigation & encryption</div></div>
        </div>
      </div>

      <aside class="right" aria-label="Network visual & status">
        <div class="panel globe-wrap" aria-hidden="false">
          <!-- simple SVG global view with animated nodes for professional look -->
          <svg class="globe-svg" viewBox="0 0 1000 600" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Global network map">
            <defs>
              <linearGradient id="g1" x1="0" x2="1"><stop offset="0" stop-color="#00d4ff"/><stop offset="1" stop-color="#6f6bff"/></linearGradient>
              <filter id="glow"><feGaussianBlur stdDeviation="6" result="coloredBlur"/><feMerge><feMergeNode in="coloredBlur"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
            </defs>

            <rect width="1000" height="600" fill="transparent"/>
            <!-- subtle world silhouette (placeholder shapes) -->
            <g fill="rgba(255,255,255,0.02)" transform="translate(60,30) scale(0.8)">
              <path d="M200 120c30-40 90-60 160-50 70 10 110 50 150 30 40-20 110-20 150 10 40 30 60 80 60 80s-40 10-80 0c-40-10-90-20-130 0-40 20-90 10-140-10-50-20-110-50-120-70z"/>
              <path d="M80 340c40-30 100-40 160-20 60 20 110 10 160-10 50-20 100-20 140 10 40 30 40 70 40 70s-60 0-100 0c-40 0-80 10-120 20-40 10-90 10-140-10-50-20-120-60-160-60z"/>
            </g>

            <!-- animated nodes -->
            <g id="nodes">
              <circle class="node" cx="360" cy="120" r="6" filter="url(#glow)"/>
              <circle class="node small" cx="520" cy="100" r="4"/>
              <circle class="node" cx="700" cy="200" r="6"/>
              <circle class="node small" cx="260" cy="300" r="4"/>
              <circle class="node" cx="520" cy="360" r="6"/>
              <circle class="node small" cx="760" cy="320" r="4"/>
            </g>

            <!-- animated lines -->
            <g stroke="url(#g1)" stroke-width="1.6" stroke-linecap="round" opacity="0.9">
              <path d="M360 120 C430 110, 490 90, 520 100" fill="none" stroke-opacity="0.75"/>
              <path d="M520 100 C600 140, 700 160, 700 200" fill="none" stroke-opacity="0.6"/>
              <path d="M360 120 C270 220, 260 300, 260 300" fill="none" stroke-opacity="0.5"/>
              <path d="M520 360 C600 340, 720 320, 760 320" fill="none" stroke-opacity="0.6"/>
            </g>
          </svg>

          <!-- NEW: lightweight speed graphic overlay (gauge + waveform) -->
          <div class="speed-graphic" aria-hidden="false">
            <svg class="gauge" viewBox="0 0 160 160" aria-hidden="true" focusable="false">
              <!-- background arc -->
              <path class="arc" d="M20 100 A60 60 0 0 1 140 100" />
              <!-- animated arc (stroke-dash used to animate) -->
              <path id="speedArc" class="arc-anim" d="M20 100 A60 60 0 0 1 140 100" stroke-dasharray="188.5" stroke-dashoffset="188.5"/>
              <text id="speedGaugeValue" class="label" x="80" y="88">—</text>
              <text class="label" x="80" y="112" style="font-size:11px;font-weight:600;fill:var(--muted)">Throughput</div>
            </svg>

            <div class="waveform" aria-hidden="true">
              <div class="bar" id="w1" style="height:10px"></div>
              <div class="bar" id="w2" style="height:18px"></div>
              <div class="bar" id="w3" style="height:40px"></div>
              <div class="bar" id="w4" style="height:28px"></div>
              <div class="bar" id="w5" style="height:16px"></div>
              <div style="width:8px"></div>
            </div>
          </div>
        </div>

        <div class="panel status-row">
          <div class="status">
            <div class="label">Network</div>
            <!-- show only public IP here -->
            <div class="value" id="networkIP">—</div>
          </div>
          <div class="status">
            <div class="label">Latency</div>
            <div class="value" id="latPanel">—</div>
          </div>
          <div class="status">
            <div class="label">Jitter</div>
            <div class="value" id="jitPanel">—</div>
          </div>
        </div>

        <div class="panel" style="text-align:center">
          <div class="providers" aria-hidden="false">
            <span>RailWire</span> &middot; <span>BSNL</span> &middot; <span>AirFiber</span> &middot; <span>N Net</span> &middot; <span>C32</span>
          </div>
        </div>
      </aside>
    </section>
  </main>

  <footer>
    <div>© <?= date('Y') ?> Udhayan Enterprises — All rights reserved</div>
    <div style="font-size:13px;color:var(--muted)">Contact: <a href="mailto:support@isp.local" style="color:var(--accent);text-decoration:none">support@isp.local</a></div>
  </footer>
</div>

<!-- Dark login modal -->
<div id="modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="login-title">
  <div class="login-card" role="document">
    <button class="close-x" aria-label="Close" onclick="openLogin(false)">&times;</button>

    <div class="login-top">
      <div class="logo-small" aria-hidden="true">SW</div>
      <div>
        <div id="login-title" class="login-title">Sign in to Udhayan Enterprises</div>
        <div class="login-sub">Secure access to plans & monitoring</div>
      </div>
    </div>

    <div class="login-body">
      <?php foreach ($messages as $m): ?>
        <div class="msg"><?= htmlspecialchars($m) ?></div>
      <?php endforeach; ?>

      <form method="post" action="/" onsubmit="submitBtn.disabled=true">
        <input type="hidden" name="action" value="login" />
        <div class="form-row">
          <input class="input" type="email" name="email" required placeholder="you@isp.local" />
          <input class="input" type="password" name="password" required placeholder="Password" />
        </div>

        <div style="display:flex;justify-content:flex-start;align-items:center;gap:10px">
          <label style="color:var(--muted);font-size:13px"><input type="checkbox" name="remember" style="margin-right:8px"> Remember</label>
        </div>

        <div class="controls">
          <button id="submitBtn" class="btn-primary" type="submit">Sign in</button>
        </div>

        <div style="text-align:center;margin-top:12px;color:var(--muted);font-size:13px">Or sign in with your SSO provider</div>
      </form>
    </div>
  </div>
</div>
<script>
  // keep existing modal JS behavior compatible
  const modal = document.getElementById('modal');
  function openLogin(show=true){
    modal.style.display = show ? 'flex' : 'none';
    if (show) modal.querySelector('input[name=email]')?.focus();
  }
  modal.addEventListener('click',(e)=>{ if (e.target === modal) modal.style.display = 'none'; });
  <?php if (!empty($messages)): ?> modal.style.display = 'flex'; <?php endif; ?>
</script>

<script>
/* bandwidth + latency + jitter polling
   Uses /bandwidth.php (simulate or read iface) and /ping.php for RTT measurement.
   Updates multiple UI places for a professional dashboard feel.
*/
function formatBits(bps){
  if (!isFinite(bps) || bps <= 0) return '—';
  if (bps >= 1e9) return (bps/1e9).toFixed(2) + ' Gbps';
  if (bps >= 1e6) return Math.round(bps/1e6) + ' Mbps';
  if (bps >= 1e3) return Math.round(bps/1e3) + ' Kbps';
  return Math.round(bps) + ' bps';
}

/* fetch bandwidth */
(async function bandwidthPoll(){
  const el = document.getElementById('speedValue');
  const panel = document.getElementById('speedPanel');

  // new gauge elements
  const gaugeText = document.getElementById('speedGaugeValue');
  const arc = document.getElementById('speedArc');
  const bars = ['w1','w2','w3','w4','w5'].map(id => document.getElementById(id));

  // helper to format
  function formatBitsShort(bps){
    if (!isFinite(bps) || bps <= 0) return '—';
    if (bps >= 1e9) return (bps/1e9).toFixed(2) + ' G';
    if (bps >= 1e6) return Math.round(bps/1e6) + ' M';
    if (bps >= 1e3) return Math.round(bps/1e3) + ' K';
    return Math.round(bps) + ' b';
  }

  // animate arc: arc path length approx 188.5 (for given arc); offset 0 => full, offset=max => empty
  const ARC_LEN = 188.5;
  let displayed = 0;
  function animateTo(targetPercent){
    // targetPercent 0..1
    const targetOffset = Math.max(0, Math.min(1, 1 - targetPercent)) * ARC_LEN;
    arc.style.strokeDashoffset = targetOffset;
  }

  async function update(){
    try {
      const res = await fetch('/bandwidth.php?_=' + Date.now(), {cache:'no-store'});
      const j = await res.json();
      if (j && j.ok){
        const down = j.download_bps || 0;

        // update textual KPI places
        el.textContent = formatBits(down);
        if (panel) panel.textContent = formatBits(down);

        // gauge numeric (show Mbps/Gbps)
        if (gaugeText) gaugeText.textContent = formatBitsShort(down);

        // map speed to 0..1 for arc (assume 1 Gbps = 1.0, 0..10Gbps scaled)
        const normalized = Math.min(1, down / 1e9);
        animateTo(normalized);

        // animate waveform bars amplitude based on down
        const amp = Math.min(1, down / (200 * 1e6)); // 200 Mbps maps to strong waveform
        bars.forEach((b,i)=>{
          const variability = 0.2 + Math.abs(Math.sin((Date.now()/300)+i));
          const h = Math.round((12 + (amp * 80) * Math.random()) * variability);
          if (b) b.style.height = Math.max(6, Math.min(90, h)) + 'px';
        });

        // color change thresholds
        if (down < 1e6) el.style.color = '#9bb0bf';
        else if (down < 1e8) el.style.color = '#7be495';
        else if (down < 5e8) el.style.color = '#ffd166';
        else el.style.color = '#ff7b7b';
      } else {
        el.textContent = '—';
        if (panel) panel.textContent = '—';
        if (gaugeText) gaugeText.textContent = '—';
        animateTo(0);
      }
    } catch (e){
      el.textContent = '—';
      if (panel) panel.textContent = '—';
      if (gaugeText) gaugeText.textContent = '—';
      animateTo(0);
    }
  }

  // initially set arc to zero
  animateTo(0);
  await update();
  setInterval(update, 2500);
})();

/* latency & jitter via HTTP ping */
(function latencyPoll(){
  const kpiLat = document.getElementById('latencyValue');
  const kpiJit = document.getElementById('jitterValue');
  const panelLat = document.getElementById('latPanel');
  const panelJit = document.getElementById('jitPanel');

  function stats(arr){
    if (!arr.length) return {avg:null,std:null};
    const n = arr.length;
    const avg = arr.reduce((s,v)=>s+v,0)/n;
    const varr = arr.reduce((s,v)=>s + Math.pow(v-avg,2),0)/n;
    return {avg, std: Math.sqrt(varr)};
  }

  async function measure(samples=6, gap=110){
    const times = [];
    for (let i=0;i<samples;i++){
      const t0 = performance.now();
      try {
        const r = await fetch('/ping.php?_=' + Date.now(), {cache:'no-store'});
        if (!r.ok) times.push(null);
        else { await r.json(); times.push(performance.now() - t0); }
      } catch (e){
        times.push(null);
      }
      await new Promise(r=>setTimeout(r, gap));
    }
    const valid = times.filter(x=>typeof x === 'number' && isFinite(x));
    const s = stats(valid);
    if (s.avg === null){
      if (kpiLat) kpiLat.textContent = '—'; if (kpiJit) kpiJit.textContent = '—';
      if (panelLat) panelLat.textContent = '—'; if (panelJit) panelJit.textContent = '—';
      return;
    }
    const latRounded = Math.round(s.avg);
    const jitRounded = s.std ? s.std.toFixed(1) : '0.0';
    if (kpiLat) kpiLat.textContent = latRounded + ' ms';
    if (kpiJit) kpiJit.textContent = jitRounded + ' ms';
    if (panelLat) panelLat.textContent = latRounded + ' ms';
    if (panelJit) panelJit.textContent = jitRounded + ' ms';
    kpiLat.style.color = (s.avg <= 50 ? '' : '#ffd166');
    kpiJit.style.color = (s.std <= 5 ? '' : '#ffd166');
  }

  measure();
  setInterval(()=>measure(), 5000);
})();

// fetch client public IP every 30s
async function updateClientIp(){
  try {
    const res = await fetch('https://api.ipify.org?format=json');
    if (!res.ok) throw new Error('ip fetch failed');
    const j = await res.json();
    // set both (if present) for compatibility:
    const el = document.getElementById('clientIP');
    const net = document.getElementById('networkIP');
    if (el) el.textContent = j.ip || '—';
    if (net) net.textContent = j.ip || '—';
  } catch (e) {
    // silent fail — keep existing value
    console.debug('client ip update error', e);
  }
}
updateClientIp();
setInterval(updateClientIp, 30000);
</script>
</body>
</html>
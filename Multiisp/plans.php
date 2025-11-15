<?php
session_start();
$loggedIn = !empty($_SESSION['user']);
$user = $_SESSION['user'] ?? null;

// DB connection (use config/db.php if present, otherwise fallback)
$pdo = null;
$dbErr = null;
$configPath = __DIR__ . '/config/db.php';
if (file_exists($configPath)) {
    require_once $configPath;
    if (!empty($pdo) && $pdo instanceof PDO) {
        // provided PDO
    } elseif (!empty($dbCfg) && is_array($dbCfg)) {
        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $dbCfg['host'] ?? '127.0.0.1', $dbCfg['dbname'] ?? 'isp_portal');
            $pdo = new PDO($dsn, $dbCfg['user'] ?? 'root', $dbCfg['pass'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (Throwable $e) {
            $pdo = null;
            $dbErr = $e->getMessage();
        }
    }
}
if ($pdo === null) {
    try {
        $dsn = 'mysql:host=127.0.0.1;dbname=isp_portal;charset=utf8mb4';
        $pdo = new PDO($dsn, 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Throwable $e) {
        $pdo = null;
        $dbErr = $e->getMessage();
    }
}

// Fetch all plans joined with ISP name and planmode
$rows = [];
if ($pdo) {
    try {
        $sql = "SELECT p.*, i.name AS isp_name
                FROM plans p
                LEFT JOIN isps i ON p.isp_id = i.id
                ORDER BY COALESCE(i.name,'ZZZ') ASC, p.price ASC, p.id ASC";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $plans = [];
        foreach ($rows as $r) {
            // detect which column holds the mode without assuming column names
            $raw = null;
            if (array_key_exists('planmode', $r)) $raw = $r['planmode'];
            elseif (array_key_exists('plan_mode', $r)) $raw = $r['plan_mode'];
            elseif (array_key_exists('mode', $r)) $raw = $r['mode'];

            // normalize to integer modes: 1 = home, 2 = corporate (fallback = 1)
            $pm = 1;
            if ($raw !== null && $raw !== '') {
                if (is_numeric($raw)) {
                    $pm = (int)$raw;
                } else {
                    $s = strtolower(trim((string)$raw));
                    if (in_array($s, ['2','corporate','corp','enterprise','business'])) $pm = 2;
                    elseif (in_array($s, ['1','home','residential','consumer'])) $pm = 1;
                    else {
                        $num = (int) filter_var($s, FILTER_SANITIZE_NUMBER_INT);
                        if ($num >= 1) $pm = $num;
                    }
                }
            }
            // restrict to known modes (1 or 2). adjust if you support more.
            if ($pm !== 2) $pm = 1;

            $plans[] = [
                'id' => (int)($r['id'] ?? 0),
                'isp_id' => (int)($r['isp_id'] ?? 0),
                'plan_name' => $r['name'] ?? ($r['plan_name'] ?? ''),
                'speed' => $r['speed'] ?? '',
                'price' => isset($r['price']) ? (int)$r['price'] : 0,
                'planmode' => $pm,
                'isp_name' => $r['isp_name'] ?? 'Other',
            ];
        }
    } catch (Throwable $e) {
        $dbErr = $e->getMessage();
    }
}

// Group plans by ISP name
$groups = [];
if (!empty($plans)) {
    foreach ($plans as $r) {
        $isp = $r['isp_name'] ?? 'Other';
        if ($isp === '' || $isp === null) $isp = 'Other';
        if (!isset($groups[$isp])) $groups[$isp] = [];
        $groups[$isp][] = $r;
    }
}

// choose a color per ISP deterministically without immediate repeats
function assignIspColors(array $ispNames): array {
    // curated palette (good contrast). We'll assign sequentially to avoid repeats.
    $palette = [
        '#00d4ff','#6f6bff','#7be495','#ffd166','#ff8a42','#ff7b7b','#8ad0ff','#c49bff',
        '#6ce0b3','#ffa6d1','#f6d365','#a0e7e5','#d3b8ff','#9be7ff','#4dd0e1','#ffb86b'
    ];
    $colors = [];
    $i = 0;
    foreach ($ispNames as $name) {
        if ($i < count($palette)) {
            $colors[$name] = $palette[$i++];
        } else {
            // fallback: generate HSL color from hash for large number of ISPs
            $h = crc32(mb_strtolower($name)) % 360;
            $colors[$name] = "hsl($h,70%,52%)";
        }
    }
    return $colors;
}

// safe money formatter
if (!function_exists('money')) {
    function money($n) { return number_format((int)$n, 0); }
}

// build isp color map
$ispNames = array_keys($groups);
$ispColors = assignIspColors($ispNames);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Plans — SilverWave</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#f6f8fa;
  --muted:#6b7280;
  --text:#0f172a;
  --card-grad: linear-gradient(180deg,#ffffff,#f8fafc);
  --card-border: rgba(15,23,42,0.06);
  --max-width:1200px;
}

/* Network-like light wallpaper (subtle nodes + connecting lines) */
html,body{
  height:100%;
  margin:0;
  -webkit-font-smoothing:antialiased;
  color:var(--text);

  /* layers: faint diagonal connection lines, a few highlighted nodes, subtle repeated dots, base gradient */
  background:
    /* faint connecting strokes (diagonal) */
    repeating-linear-gradient(135deg, rgba(2,6,23,0.03) 0 1px, transparent 1px 180px),
    /* sparse highlighted nodes */
    radial-gradient(circle at 10% 18%, rgba(0,212,255,0.12) 0 3px, transparent 10%),
    radial-gradient(circle at 32% 62%, rgba(111,107,255,0.10) 0 3px, transparent 10%),
    radial-gradient(circle at 58% 34%, rgba(255,138,66,0.09) 0 3px, transparent 10%),
    radial-gradient(circle at 82% 72%, rgba(123,228,149,0.08) 0 3px, transparent 10%),
    /* faint grid of tiny nodes across the canvas */
    radial-gradient(circle, rgba(0,0,0,0.03) 1px, transparent 2px),
    /* base light gradient */
    linear-gradient(180deg,#f6f8fa,#eef2f6);

  /* scale the repeating subtle dot grid to be very sparse */
  background-size:
    auto,
    auto,
    auto,
    auto,
    auto,
    36px 36px,
    auto;
  background-position:
    center,
    left 10% top 18%,
    left 32% top 62%,
    left 58% top 34%,
    left 82% top 72%,
    0 0,
    0 0;
  background-repeat: no-repeat, no-repeat, no-repeat, no-repeat, no-repeat, repeat, no-repeat;
}

/* page */
*{box-sizing:border-box;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial}
.container{max-width:var(--max-width);margin:28px auto;padding:20px}

/* header */
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}
.logo{width:56px;height:56px;border-radius:12px;background:linear-gradient(135deg,#e6f7ff,#e9d8ff);display:flex;align-items:center;justify-content:center;font-weight:800;color:#07203a;box-shadow:0 8px 20px rgba(2,6,23,0.06)}

/* legend */
.legend{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px}
.legend .item{display:flex;gap:8px;align-items:center;color:var(--muted);font-size:13px}
.legend .swatch{width:14px;height:14px;border-radius:4px;border:1px solid rgba(15,23,42,0.04)}

/* grid + 3d perspective container */
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;perspective:1400px;transform-style:preserve-3d}
@media(max-width:1000px){.grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:640px){.grid{grid-template-columns:1fr}}

/* card: 3D look */
.card{
  position:relative;
  padding:18px;
  border-radius:14px;
  background:var(--card-grad);
  border:1px solid var(--card-border);
  overflow:hidden;
  transform-style:preserve-3d;
  transition:box-shadow 350ms cubic-bezier(.2,.9,.2,1), transform 450ms cubic-bezier(.2,.9,.2,1);
  will-change:transform;
  box-shadow: 0 8px 20px rgba(8,15,30,0.06), 0 1px 0 rgba(255,255,255,0.6) inset;
  /* tilt vars */
  --tiltX: 0deg;
  --tiltY: 0deg;
  transform: translateZ(0) rotateX(var(--tiltX)) rotateY(var(--tiltY));
}

/* glossy sheen layer */
.card::before{
  content:'';
  position:absolute;
  left:-30%;
  top:-40%;
  width:160%;
  height:140%;
  background: radial-gradient(600px 300px at 25% 20%, rgba(255,255,255,0.6), rgba(255,255,255,0) 28%),
              linear-gradient(180deg, rgba(255,255,255,0.04), rgba(255,255,255,0));
  transform: translateZ(-30px) rotate(12deg);
  filter: blur(18px);
  pointer-events:none;
  opacity:0.9;
}

/* stronger shadow and pop on hover/focus */
.card.is-hover{
  box-shadow: 0 28px 60px rgba(8,15,30,0.12), 0 6px 18px rgba(8,15,30,0.06);
  transform: translateY(-12px) rotateX(var(--tiltX)) rotateY(var(--tiltY));
}

/* accent top border */
.card .mode-label{
  position:absolute;
  top:12px; right:12px;
  padding:6px 10px;border-radius:999px;font-weight:700;color:#021;
  box-shadow: 0 6px 18px rgba(2,6,23,0.06);
}

/* content typography */
.card .price{font-weight:800;font-size:20px}
.meta{color:var(--muted);font-size:13px}

/* small button style (kept for possible use) */
.price-btn{
  background:linear-gradient(90deg,#ffffff,#f4f6f8);
  border:1px solid rgba(15,23,42,0.06);
  color:var(--text);
  padding:8px 12px;border-radius:8px;cursor:pointer;font-weight:700;
  box-shadow:0 6px 18px rgba(2,6,23,0.04);
}
.price-btn:active{transform:translateY(1px)}

/* Graphical home button */
.home-graphic{
  display:inline-flex;
  align-items:center;
  gap:10px;
  padding:10px 14px;
  border-radius:12px;
  background:linear-gradient(180deg,#ffffff,#f3f7fb);
  color:var(--text);
  text-decoration:none;
  border:1px solid rgba(15,23,42,0.06);
  box-shadow: 0 8px 24px rgba(2,6,23,0.06), 0 1px 0 rgba(255,255,255,0.6) inset;
  transition:transform .26s cubic-bezier(.2,.9,.2,1), box-shadow .26s;
  font-weight:700;
  -webkit-tap-highlight-color: transparent;
}
.home-graphic:hover{
  transform:translateY(-6px) scale(1.02);
  box-shadow: 0 28px 60px rgba(2,6,23,0.10);
}
.home-graphic:active{ transform:translateY(-2px) scale(.997); }

/* small 3D house icon */
.home-graphic .icon{
  width:36px;height:36px;flex:0 0 36px;border-radius:9px;
  display:inline-grid;place-items:center;
  background:linear-gradient(180deg, rgba(255,255,255,0.9), rgba(240,246,255,0.9));
  box-shadow: 0 6px 18px rgba(2,6,23,0.06);
}
.home-graphic svg{width:20px;height:20px;display:block;fill:currentColor;color:#0f172a;}

/* label text */
.home-graphic .label{display:flex;flex-direction:column;line-height:1}
.home-graphic .title{font-size:13px;color:var(--text);font-weight:800}
.home-graphic .sub{font-size:11px;color:var(--muted);font-weight:600;margin-top:2px}

/* ensure graphic adapts to dark/light theme by keeping current variables */
@media (prefers-reduced-motion: reduce){ .home-graphic, .home-graphic:hover{transition:none} }

</style>
</head>
<body>
<div class="container">
  <div class="header">
    <div style="display:flex;gap:12px;align-items:center">
      <div class="logo">SW</div>
      <div>
        <div style="font-weight:700">SilverWave</div>
        <div style="color:var(--muted);font-size:13px">Plans grouped by ISP</div>
      </div>
    </div>
    <div>
      <!-- replaced plain Home link with graphical 3D home button -->
      <a class="home-graphic" href="/" aria-label="Home">
        <span class="icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M12 3.3l8 6.4v9.3a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1V9.7l8-6.4z"/>
          </svg>
        </span>
        <span class="label">
          <span class="title">Home</span>
        </span>
      </a>

      <?php if ($loggedIn): ?>
        <a style="margin-left:10px;color:var(--muted);text-decoration:none" href="/dashboard_<?= htmlspecialchars($user['role'] ?? 'user') ?>.php">Dashboard</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($dbErr)): ?>
    <div style="color:#ffd166;margin-bottom:12px">Database error: <?= htmlspecialchars($dbErr) ?></div>
  <?php endif; ?>

  <?php if (empty($groups)): ?>
    <div class="small-note">No plans found in the database.</div>
  <?php else: ?>
    <div class="legend" aria-hidden="false">
      <?php foreach ($ispColors as $ispName => $col): ?>
        <div class="item"><span class="swatch" style="background:<?= htmlspecialchars($col) ?>;"></span><?= htmlspecialchars($ispName) ?></div>
      <?php endforeach; ?>
    </div>

    <?php foreach ($groups as $ispName => $plans): ?>
      <section class="section" aria-labelledby="isp-<?= preg_replace('/[^a-z0-9_-]/i','', $ispName) ?>">
        <h2 id="isp-<?= preg_replace('/[^a-z0-9_-]/i','', $ispName) ?>"><?= htmlspecialchars($ispName) ?></h2>
        <div class="small-note"><?= count($plans) ?> plan<?= count($plans) !== 1 ? 's' : '' ?> available</div>
        <div class="grid" role="list">
          <?php foreach ($plans as $p):
            $mode = (int)($p['planmode'] ?? 1);
            $modeLabel = $mode === 2 ? 'Corporate' : 'Home';
            $ispColor = $ispColors[$ispName] ?? '#00d4ff';
            $labelBg = $ispColor;
            $price_excl = (int)$p['price'];
            $gst_amount = (int) round($price_excl * 0.18);
            $price_incl = $price_excl + $gst_amount;
          ?>
            <article class="card" role="listitem" style="border-top:4px solid <?= htmlspecialchars($ispColor) ?>;">
              <div class="mode-label" style="background:<?= htmlspecialchars($labelBg) ?>;color:#021"><?= htmlspecialchars($modeLabel) ?></div>

              <div style="display:flex;justify-content:space-between;align-items:center">
                <div>
                  <div style="font-weight:700"><?= htmlspecialchars($p['plan_name']) ?></div>
                </div>
                <div style="background:rgba(255,255,255,0.03);padding:6px 10px;border-radius:999px;font-size:12px;color:var(--muted)"><?= htmlspecialchars($p['speed']) ?></div>
              </div>

              <div style="margin-top:12px;display:flex;justify-content:space-between;align-items:center">
                <div>
                  <div class="price"><?= money($price_incl) ?> INR <span style="font-weight:500;color:var(--muted);font-size:13px">(incl. 18% GST)</span></div>
                  <div style="color:var(--muted);font-size:13px;margin-top:6px">
                    <strong>Excl. GST: ₹<?= money($price_excl) ?></strong> &middot; GST (18%): ₹<?= money($gst_amount) ?>
                  </div>
                </div>
                <div style="text-align:right">
                  <div class="meta">per month</div>
                </div>
              </div>

              <div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end">
                <!-- Subscribe button removed -->
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
// 3D tilt interaction for cards
(function(){
  const cards = document.querySelectorAll('.card');
  const ease = 0.12;
  const maxTilt = 12; // degrees
  cards.forEach(card=>{
    let raf = null;
    let currentX = 0, currentY = 0, targetX = 0, targetY = 0;

    function update(){
      currentX += (targetX - currentX) * ease;
      currentY += (targetY - currentY) * ease;
      card.style.setProperty('--tiltX', ( -currentY ).toFixed(2) + 'deg');
      card.style.setProperty('--tiltY', ( currentX ).toFixed(2) + 'deg');
      raf = requestAnimationFrame(update);
    }

    card.addEventListener('mousemove', function(e){
      const rect = card.getBoundingClientRect();
      const px = (e.clientX - rect.left) / rect.width; // 0..1
      const py = (e.clientY - rect.top) / rect.height; // 0..1
      targetX = ( (px - 0.5) * 2 ) * maxTilt;   // -max..max
      targetY = ( (py - 0.5) * 2 ) * maxTilt;
      card.classList.add('is-hover');
      if (!raf) { raf = requestAnimationFrame(update); }
    });

    function reset(){
      targetX = 0; targetY = 0;
      card.classList.remove('is-hover');
      if (!raf) { raf = requestAnimationFrame(update); }
      setTimeout(()=>{ cancelAnimationFrame(raf); raf=null; }, 350);
    }

    card.addEventListener('mouseleave', reset);
    card.addEventListener('mouseenter', function(){ if (!raf) raf = requestAnimationFrame(update); });
    // make focus accessible via keyboard
    card.addEventListener('focus', ()=> card.classList.add('is-hover'));
    card.addEventListener('blur', reset);
  });
})();
</script>
</body>
</html>
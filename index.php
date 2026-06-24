<?php
/**
 * Currency Converter - Project 6 | FUTM SWE-221
 * Developed by: Aisha | API: Open Exchange Rates
 */
define('APP_ID',   '9069645b9f1f4dc4bb634627a5e6d9d4');
define('BASE_URL', 'https://openexchangerates.org/api');

$CURRENCIES = [
    'NGN' => 'Nigerian Naira',
    'USD' => 'US Dollar',
    'EUR' => 'Euro',
    'GBP' => 'British Pound',
    'GHS' => 'Ghanaian Cedi',
];

$result = null; $error = null;
$amount = 1; $from = 'NGN'; $to = 'USD';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert'])) {
    $amount = floatval($_POST['amount'] ?? 1);
    $from   = strtoupper(trim($_POST['from_currency'] ?? 'NGN'));
    $to     = strtoupper(trim($_POST['to_currency']   ?? 'USD'));

    if ($amount <= 0) {
        $error = 'Please enter an amount greater than zero.';
    } elseif ($from === $to) {
        $error = 'Please choose two different currencies.';
    } else {
        $url = BASE_URL.'/latest.json?app_id='.APP_ID.'&symbols='.implode(',',[$from,$to]);
        $ch  = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true]);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($cerr) {
            $error = 'Connection failed: '.$cerr;
        } elseif ($code !== 200) {
            $d = json_decode($raw, true);
            $error = $d['description'] ?? $d['message'] ?? 'API error (HTTP '.$code.')';
        } else {
            $data = json_decode($raw, true);
            $rates = $data['rates'] ?? [];
            $rf = $rates[$from] ?? null;
            $rt = $rates[$to]   ?? null;
            if ($rf && $rt && $rf != 0) {
                $result = [
                    'amount'    => $amount, 'from' => $from, 'to' => $to,
                    'converted' => $amount * ($rt / $rf),
                    'rate'      => $rt / $rf,
                    'updated'   => isset($data['timestamp'])
                                    ? date('d M Y, H:i T', $data['timestamp']) : 'N/A',
                ];
            } else {
                $error = 'Rate not available for this pair.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Currency Converter - FUTM SWE-221</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --p:#db2777;--pd:#9d174d;--pm:#be185d;--pl:#fce7f3;--pxl:#fdf2f8;
  --w:#fff;--g1:#fdf2f8;--g2:#fbcfe8;--g5:#9d174d;--g9:#500724;
  --r:#dc2626;--rl:#fee2e2;
}
body{
  font-family:'Segoe UI',system-ui,sans-serif;
  background:linear-gradient(145deg,#831843 0%,#db2777 55%,#ec4899 100%);
  min-height:100vh;display:flex;flex-direction:column;
  align-items:center;justify-content:center;
  padding:2rem 1rem;position:relative;overflow-x:hidden;
}
.wm{
  position:fixed;top:50%;left:50%;
  transform:translate(-50%,-50%);
  width:min(60vw,420px);opacity:.07;
  pointer-events:none;z-index:0;border-radius:50%;
}
.card{
  background:var(--w);border-radius:24px;
  box-shadow:0 28px 70px rgba(0,0,0,.25);
  width:100%;max-width:460px;overflow:hidden;
  position:relative;z-index:1;
}
.hd{
  background:linear-gradient(135deg,#831843 0%,#db2777 100%);
  padding:1.6rem 2rem 1.3rem;text-align:center;
}
.logo-row{display:flex;align-items:center;justify-content:center;gap:.9rem;margin-bottom:.4rem}
.logo-img{
  width:64px;height:64px;object-fit:contain;border-radius:50%;
  box-shadow:0 3px 12px rgba(0,0,0,.3);background:#fff;padding:3px;
}
.ltd h1{font-size:1.15rem;font-weight:800;color:#fff;letter-spacing:-.02em;line-height:1.2}
.ltd p{font-size:.63rem;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.07em;margin-top:2px}
.sub{font-size:.7rem;color:rgba(255,255,255,.55);margin-top:.25rem}
.bd{padding:1.75rem 2rem 2rem}
label{display:block;font-size:.7rem;font-weight:700;color:#9d174d;
      text-transform:uppercase;letter-spacing:.07em;margin-bottom:.4rem}
input[type=number],select{
  width:100%;padding:.75rem 1rem;font-size:1rem;color:#500724;
  background:#fdf2f8;border:2px solid #fbcfe8;border-radius:12px;
  outline:none;appearance:none;transition:border-color .18s,box-shadow .18s;
}
input:focus,select:focus{
  border-color:var(--p);box-shadow:0 0 0 4px rgba(219,39,119,.13);background:var(--w);
}
.fg{margin-bottom:1.1rem}
.pair{display:grid;grid-template-columns:1fr 44px 1fr;gap:.625rem;align-items:end;margin-bottom:1.1rem}
.swap{
  width:44px;height:44px;background:#fce7f3;border:2px solid var(--p);
  border-radius:50%;color:#9d174d;font-size:1rem;cursor:pointer;
  display:flex;align-items:center;justify-content:center;transition:background .18s,color .18s;
}
.swap:hover{background:var(--p);color:#fff}
.btn{
  width:100%;padding:.875rem;background:linear-gradient(135deg,#be185d,#db2777);
  color:#fff;font-size:1rem;font-weight:700;border:none;border-radius:12px;cursor:pointer;
  letter-spacing:.02em;transition:opacity .18s,transform .1s;
}
.btn:hover{opacity:.9}.btn:active{transform:scale(.98)}
.res{margin-top:1.2rem;border-radius:14px;overflow:hidden}
.ok{background:var(--pxl);border:2px solid var(--p)}
.fail{background:var(--rl);border:2px solid var(--r)}
.rb{padding:1.25rem 1.5rem;text-align:center}
.rfr{font-size:.85rem;color:#9d174d}
.rarr{font-size:.85rem;color:#f9a8d4;margin:.2rem 0}
.rto{font-size:2.1rem;font-weight:800;color:#be185d;line-height:1.1}
.rto span{font-size:1rem;font-weight:600;color:var(--p)}
.rrate{font-size:.73rem;color:#9d174d;margin-top:.45rem}
.rdate{font-size:.68rem;color:#f472b6;margin-top:.1rem}
.emsg{padding:1rem 1.5rem;color:var(--r);font-weight:600;font-size:.88rem}
footer{margin-top:1.4rem;text-align:center;color:rgba(255,255,255,.55);font-size:.68rem;z-index:1;position:relative;line-height:1.9}
footer span{color:rgba(255,255,255,.9)}
.wm-footer{display:block;margin:.5rem auto 0;width:48px;height:48px;object-fit:contain;border-radius:50%;opacity:.75;background:#fff;padding:2px;}
</style>
</head>
<body>

<img class="wm" src="/logo.png" alt="">

<div class="card">
  <div class="hd">
    <div class="logo-row">
      <img class="logo-img" src="/logo.png" alt="FUTM Logo">
      <div class="ltd">
        <h1>FUT Minna</h1>
        <p>Federal University of Technology, Minna</p>
      </div>
    </div>
    <p class="sub">Currency Converter &middot; SWE-221 Project 6 &middot; Aisha</p>
  </div>

  <div class="bd">
    <form method="POST" id="frm">
      <div class="fg">
        <label for="amt">Amount</label>
        <input type="number" id="amt" name="amount" min="0.01" step="any"
               value="<?= htmlspecialchars($amount) ?>" placeholder="e.g. 10000" required>
      </div>

      <div class="pair">
        <div>
          <label for="fc">From</label>
          <select id="fc" name="from_currency">
            <?php foreach ($CURRENCIES as $c => $n): ?>
            <option value="<?= $c ?>" <?= $c===$from?'selected':'' ?>>
              <?= $c ?> &mdash; <?= $n ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="button" class="swap" onclick="sw()" title="Swap">&#8644;</button>
        <div>
          <label for="tc">To</label>
          <select id="tc" name="to_currency">
            <?php foreach ($CURRENCIES as $c => $n): ?>
            <option value="<?= $c ?>" <?= $c===$to?'selected':'' ?>>
              <?= $c ?> &mdash; <?= $n ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <button type="submit" name="convert" class="btn">Convert</button>
    </form>

    <?php if ($result): ?>
    <div class="res ok">
      <div class="rb">
        <div class="rfr"><?= number_format($result['amount'],2,'.',',') ?> <?= $result['from'] ?></div>
        <div class="rarr">&#8595;</div>
        <div class="rto">
          <?= number_format($result['converted'],2,'.',',') ?>
          <span><?= $result['to'] ?></span>
        </div>
        <div class="rrate">1 <?= $result['from'] ?> = <?= number_format($result['rate'],6) ?> <?= $result['to'] ?></div>
        <div class="rdate">Rates updated: <?= htmlspecialchars($result['updated']) ?></div>
      </div>
    </div>
    <?php elseif ($error): ?>
    <div class="res fail">
      <div class="emsg">&#9888; <?= htmlspecialchars($error) ?></div>
    </div>
    <?php endif; ?>
  </div>
</div>

<footer>
  Developed by <span>Aisha</span> &middot;
  <span>FUTM SWE-221</span> &middot;
  API: <span>Open Exchange Rates</span>
  <img class="wm-footer" src="/logo.png" alt="FUTM">
</footer>

<script>
function sw(){
  var f=document.getElementById('fc'),t=document.getElementById('tc'),v=f.value;
  f.value=t.value;t.value=v;
}
document.getElementById('frm').addEventListener('submit',function(e){
  var f=document.getElementById('fc').value,
      t=document.getElementById('tc').value,
      a=parseFloat(document.getElementById('amt').value);
  if(f===t){e.preventDefault();alert('Choose two different currencies.');}
  else if(isNaN(a)||a<=0){e.preventDefault();alert('Enter a valid amount > 0.');}
});
</script>
</body>
</html>

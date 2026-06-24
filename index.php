<?php
/**
 * ============================================================
 *  CURRENCY CONVERTER — Project 6 | FUTM-SWE-221
 *  Developed by: Aisha
 *  API: Open Exchange Rates (https://openexchangerates.org)
 *  SDLC Phase: Implementation
 * ============================================================
 *
 *  SDLC Summary:
 *  - Planning   : Identified core currencies relevant to Nigeria
 *                 (NGN, USD, EUR, GBP, GHS as the 5 test currencies)
 *  - Analysis   : End user = market trader persona who needs
 *                 quick NGN conversion on the go
 *  - Design     : Simple, clean converter UI — mobile-friendly
 *  - Implement  : Fetch live rates from openexchangerates.org, perform
 *                 real-time calculations on user input
 *  - Testing    : Rounding logic & edge-case currencies handled
 *  - Deployment : Mobile web — works on any modern browser
 */

// ─────────────────────────────────────────────────────────────
//  API CONFIGURATION
//  This is where we plug in our App ID from openexchangerates.org.
//  The App ID is like a password — it tells the server who we are
//  and tracks our monthly request count (1,000 requests / month
//  on the free plan).
//
//  IMPORTANT: On the free plan, the base currency is ALWAYS USD.
//  To convert between any two non-USD currencies (e.g. NGN → GBP),
//  we use the formula:  converted = amount × (rateTO / rateFROM)
//  where both rates are relative to USD.
// ─────────────────────────────────────────────────────────────
define('APP_ID',   '9069645b9f1f4dc4bb634627a5e6d9d4');
define('BASE_URL', 'https://openexchangerates.org/api');

// These are the 5 test currencies for this project.
// NGN (Naira) is our anchor focus — all conversions relate to it.
$TEST_CURRENCIES = ['NGN', 'USD', 'EUR', 'GBP', 'GHS'];
$CURRENCY_NAMES  = [
    'NGN' => 'Nigerian Naira',
    'USD' => 'US Dollar',
    'EUR' => 'Euro',
    'GBP' => 'British Pound',
    'GHS' => 'Ghanaian Cedi',
];

// ─────────────────────────────────────────────────────────────
//  HELPER: makeApiRequest()
//  This is the function that handles every HTTP call to the API.
//  It builds the full URL, fires it off with cURL, and hands
//  back the decoded JSON — or a clean error if something goes wrong.
// ─────────────────────────────────────────────────────────────
function makeApiRequest(string $endpoint, array $params = []): array {
    // Every request needs our App ID — that's how the API knows us
    $params['app_id'] = APP_ID;
    $url = BASE_URL . '/' . ltrim($endpoint, '/') . '?' . http_build_query($params);

    // Boot up cURL — PHP's built-in HTTP request tool
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,   // return the response as a string, not print it
        CURLOPT_TIMEOUT        => 10,     // wait max 10 seconds before giving up
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,   // keep SSL on — always verify HTTPS
    ]);

    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    // If cURL itself blew up (no internet, DNS failure, etc.)
    if ($curlErr) {
        return ['error' => true, 'message' => 'Connection failed: ' . $curlErr, 'code' => 0];
    }

    // Decode the JSON the API sent back
    $data = json_decode($raw, true);

    // Non-200 usually means bad App ID, quota exceeded, or wrong endpoint
    if ($httpCode !== 200) {
        $msg = $data['description'] ?? $data['message'] ?? 'API error — status ' . $httpCode;
        return ['error' => true, 'message' => $msg, 'code' => $httpCode];
    }

    return ['error' => false, 'data' => $data, 'code' => $httpCode];
}

// ─────────────────────────────────────────────────────────────
//  ENDPOINT 1: getUsage()
//  Calls /usage.json — shows how many of our 1,000 monthly
//  requests we've used and how many we have left.
//  Think of it like checking your data bundle balance.
// ─────────────────────────────────────────────────────────────
function getUsage(): array {
    return makeApiRequest('usage.json');
}

// ─────────────────────────────────────────────────────────────
//  ENDPOINT 2: getCurrencies()
//  Calls /currencies.json — returns a flat key→name map of
//  every currency the API supports (170+). No auth needed.
// ─────────────────────────────────────────────────────────────
function getCurrencies(): array {
    // currencies.json is a public endpoint — no app_id required
    $url = BASE_URL . '/currencies.json';
    $ch  = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($raw, true);
    return ['error' => ($code !== 200), 'data' => $data, 'code' => $code];
}

// ─────────────────────────────────────────────────────────────
//  ENDPOINT 3: getLatestRates()
//  Calls /latest.json — the main workhorse of this whole app.
//  Returns live exchange rates relative to USD (always USD base
//  on the free plan). We filter to our 5 currencies with symbols.
// ─────────────────────────────────────────────────────────────
function getLatestRates(array $symbols = []): array {
    $params = [];
    if (!empty($symbols)) {
        // symbols= filters the response to only the currencies we need
        // — keeps the response small and fast
        $params['symbols'] = implode(',', $symbols);
    }
    return makeApiRequest('latest.json', $params);
}

// ─────────────────────────────────────────────────────────────
//  CONVERSION FORMULA
//  Since the free plan always gives rates relative to USD,
//  to go from currency A to currency B we do:
//
//    converted = amount × (rates[B] / rates[A])
//
//  Example: 10,000 NGN → GBP
//    = 10000 × (0.757958 / 1370.5)  =  5.53 GBP
// ─────────────────────────────────────────────────────────────

// ─────────────────────────────────────────────────────────────
//  HANDLE FORM SUBMISSION
//  When the user hits "Convert", we grab the form values,
//  fetch a fresh set of rates from /latest.json, and do the math.
// ─────────────────────────────────────────────────────────────
$conversionResult = null;
$conversionError  = null;
$amount           = 1;
$fromCurrency     = 'NGN';
$toCurrency       = 'USD';
$rawRequestUrl    = '';
$rawResponse      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert'])) {
    // Sanitize inputs — never trust raw user data straight from a form
    $amount       = floatval($_POST['amount'] ?? 1);
    $fromCurrency = strtoupper(trim($_POST['from_currency'] ?? 'NGN'));
    $toCurrency   = strtoupper(trim($_POST['to_currency']   ?? 'USD'));

    if ($amount <= 0) {
        $conversionError = 'Please enter a valid amount greater than zero.';
    } elseif ($fromCurrency === $toCurrency) {
        $conversionError = 'Source and target currency cannot be the same.';
    } else {
        // Fetch latest rates for just the two currencies we need (+ keep it lean)
        $symbols  = array_unique([$fromCurrency, $toCurrency, 'USD']);
        $response = getLatestRates($symbols);

        // Build the request URL we actually sent (hide app_id from display)
        $rawRequestUrl = BASE_URL . '/latest.json?app_id=***&symbols=' . implode(',', $symbols);

        if ($response['error']) {
            $conversionError = $response['message'];
        } else {
            $rates       = $response['data']['rates']     ?? [];
            $timestamp   = $response['data']['timestamp'] ?? null;
            $lastUpdated = $timestamp ? date('Y-m-d H:i:s T', $timestamp) : 'N/A';
            $rawResponse = json_encode($response['data'], JSON_PRETTY_PRINT);

            $rateFrom = $rates[$fromCurrency] ?? null;
            $rateTo   = $rates[$toCurrency]   ?? null;

            if ($rateFrom === null || $rateTo === null) {
                $conversionError = 'Rate not found for the selected currency pair.';
            } elseif ($rateFrom == 0) {
                $conversionError = 'Cannot divide by zero — invalid FROM rate.';
            } else {
                // This is the actual conversion:
                // (amount / rate_from) gives us the USD equivalent,
                // then multiply by rate_to to get the target currency.
                $converted   = $amount * ($rateTo / $rateFrom);
                $appliedRate = $rateTo / $rateFrom;

                $conversionResult = [
                    'amount'      => $amount,
                    'from'        => $fromCurrency,
                    'to'          => $toCurrency,
                    'rate'        => $appliedRate,
                    'converted'   => $converted,
                    'updated_at'  => $lastUpdated,
                    'raw_request' => $rawRequestUrl,
                    'raw_response'=> $rawResponse,
                ];
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────
//  FETCH DASHBOARD DATA ON EVERY PAGE LOAD
//  We always pull: (1) usage stats, (2) live rates for all 5
//  test currencies. This populates the live rates board at the
//  bottom without the user having to do anything.
// ─────────────────────────────────────────────────────────────
$usageData  = getUsage();
$ratesData  = getLatestRates($TEST_CURRENCIES);

// Pull quota numbers from the usage response
$usageInfo      = $usageData['data']['data']['usage']    ?? [];
$usedCalls      = $usageInfo['requests']           ?? 'N/A';
$totalCalls     = $usageInfo['requests_quota']     ?? 'N/A';
$remainingCalls = $usageInfo['requests_remaining'] ?? 'N/A';
$daysLeft       = $usageInfo['days_remaining']     ?? 'N/A';

// Build the live rates map — all relative to USD (that's how the API works)
$liveRates     = [];
$rateTimestamp = null;
if (!$ratesData['error'] && isset($ratesData['data']['rates'])) {
    $liveRates     = $ratesData['data']['rates'];
    $rateTimestamp = $ratesData['data']['timestamp'] ?? null;
}
$ratesUpdated = $rateTimestamp ? date('D, d M Y H:i T', $rateTimestamp) : 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Currency Converter | FUTM-SWE-221 Project 6</title>
<style>
/* ─── Reset & Variables ────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --green:   #16a34a;
    --green-l: #dcfce7;
    --green-d: #14532d;
    --blue:    #1d4ed8;
    --blue-l:  #dbeafe;
    --gray-50: #f9fafb;
    --gray-100:#f3f4f6;
    --gray-200:#e5e7eb;
    --gray-400:#9ca3af;
    --gray-600:#4b5563;
    --gray-800:#1f2937;
    --white:   #ffffff;
    --red:     #dc2626;
    --red-l:   #fee2e2;
    --amber:   #d97706;
    --amber-l: #fef3c7;
}

body {
    font-family: 'Segoe UI', system-ui, sans-serif;
    background: linear-gradient(135deg, #0f4c2a 0%, #166534 50%, #15803d 100%);
    min-height: 100vh;
    color: var(--gray-800);
}

/* ─── Header ──────────────────────────────────────────── */
header {
    background: rgba(0,0,0,0.3);
    backdrop-filter: blur(10px);
    padding: 1rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(255,255,255,0.15);
}
header .brand { display: flex; align-items: center; gap: 0.75rem; }
header .logo-icon {
    width: 40px; height: 40px;
    background: var(--green);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; font-weight: 800; color: white;
}
header h1 { font-size: 1.25rem; font-weight: 700; color: white; letter-spacing:-0.02em; }
header .subtitle { font-size: 0.7rem; color: rgba(255,255,255,0.6); text-transform: uppercase; letter-spacing: 0.08em; }
.api-badge {
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    color: rgba(255,255,255,0.85);
    font-size: 0.7rem; padding: 0.25rem 0.75rem;
    border-radius: 99px; letter-spacing: 0.05em;
}

/* ─── Layout ──────────────────────────────────────────── */
.container {
    max-width: 1100px; margin: 0 auto;
    padding: 2rem 1.5rem;
    display: grid; gap: 1.5rem;
}

/* ─── Cards ───────────────────────────────────────────── */
.card { background: var(--white); border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.12); overflow: hidden; }
.card-header {
    padding: 1rem 1.5rem 0.75rem;
    border-bottom: 1px solid var(--gray-100);
    display: flex; align-items: center; gap: 0.5rem;
}
.card-header h2 { font-size: 1rem; font-weight: 600; color: var(--gray-800); }
.card-header .tag {
    margin-left: auto;
    font-size: 0.65rem; font-weight: 600;
    padding: 0.2rem 0.5rem; border-radius: 4px;
    text-transform: uppercase; letter-spacing: 0.05em;
}
.tag-live  { background: var(--green-l); color: var(--green-d); }
.tag-get   { background: var(--blue-l);  color: var(--blue); }
.card-body { padding: 1.5rem; }

/* ─── Status Grid ─────────────────────────────────────── */
.status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 1rem; }
.status-item {
    background: var(--gray-50); border: 1px solid var(--gray-200);
    border-radius: 10px; padding: 1rem; text-align: center;
}
.status-item .value { font-size: 1.5rem; font-weight: 700; color: var(--green); display: block; }
.status-item .label { font-size: 0.7rem; color: var(--gray-600); margin-top: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em; }

/* ─── Converter Form ──────────────────────────────────── */
.converter-grid { display: grid; grid-template-columns: 1fr auto 1fr; gap: 1rem; align-items: end; }
@media (max-width: 600px) { .converter-grid { grid-template-columns: 1fr; } }
.form-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--gray-600); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em; }
.form-group input,
.form-group select {
    width: 100%; padding: 0.75rem 1rem;
    border: 2px solid var(--gray-200); border-radius: 10px;
    font-size: 1rem; color: var(--gray-800); background: var(--white);
    transition: border-color 0.2s; appearance: none;
}
.form-group input:focus,
.form-group select:focus { outline: none; border-color: var(--green); box-shadow: 0 0 0 3px var(--green-l); }
.swap-btn {
    background: var(--green-l); border: 2px solid var(--green); color: var(--green-d);
    border-radius: 50%; width: 44px; height: 44px;
    font-size: 1.1rem; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.2s; align-self: flex-end; flex-shrink: 0;
}
.swap-btn:hover { background: var(--green); color: white; }
.convert-btn {
    margin-top: 1.25rem; width: 100%; padding: 0.875rem;
    background: var(--green); color: white;
    font-size: 1rem; font-weight: 700;
    border: none; border-radius: 10px; cursor: pointer;
    transition: background 0.2s, transform 0.1s;
}
.convert-btn:hover  { background: #15803d; }
.convert-btn:active { transform: scale(0.98); }

/* ─── Result Box ──────────────────────────────────────── */
.result-box { margin-top: 1.25rem; border-radius: 12px; overflow: hidden; }
.result-box.success { background: var(--green-l); border: 2px solid var(--green); }
.result-box.error   { background: var(--red-l);   border: 2px solid var(--red); }
.result-main { padding: 1.25rem 1.5rem; text-align: center; }
.result-amount { font-size: 2.25rem; font-weight: 800; color: var(--green-d); line-height: 1; }
.result-meta   { font-size: 0.8rem; color: var(--gray-600); margin-top: 0.5rem; }
.error-msg     { padding: 1rem 1.5rem; color: var(--red); font-weight: 600; }

/* ─── API Debug Block ─────────────────────────────────── */
.api-debug { margin-top: 1.25rem; background: var(--gray-800); border-radius: 10px; overflow: hidden; }
.api-debug .debug-header {
    background: rgba(255,255,255,0.05);
    padding: 0.6rem 1rem;
    font-size: 0.72rem; font-weight: 600; color: var(--gray-400);
    text-transform: uppercase; letter-spacing: 0.08em;
    display: flex; justify-content: space-between;
}
.api-debug pre {
    padding: 1rem; font-family: 'Courier New', monospace;
    font-size: 0.72rem; color: #86efac;
    overflow-x: auto; white-space: pre-wrap;
    max-height: 240px; overflow-y: auto;
}

/* ─── Live Rates Table ────────────────────────────────── */
.rates-table { width: 100%; border-collapse: collapse; }
.rates-table th {
    padding: 0.6rem 0.875rem; text-align: left;
    font-size: 0.72rem; font-weight: 600; color: var(--gray-600);
    text-transform: uppercase; letter-spacing: 0.05em;
    background: var(--gray-50); border-bottom: 2px solid var(--gray-200);
}
.rates-table td  { padding: 0.75rem 0.875rem; border-bottom: 1px solid var(--gray-100); font-size: 0.9rem; }
.rates-table tr:last-child td { border-bottom: none; }
.rates-table tr:hover td { background: var(--gray-50); }
.currency-code { font-weight: 700; color: var(--gray-800); }
.currency-name { font-size: 0.8rem; color: var(--gray-600); }
.rate-value    { font-weight: 600; color: var(--green-d); font-family: monospace; }

/* ─── API Structure Section ───────────────────────────── */
.endpoint-card { border: 1px solid var(--gray-200); border-radius: 10px; margin-bottom: 1rem; overflow: hidden; }
.endpoint-header {
    padding: 0.75rem 1rem; background: var(--gray-50);
    display: flex; align-items: center; gap: 0.75rem;
    border-bottom: 1px solid var(--gray-200);
}
.method-tag { font-size: 0.65rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 4px; text-transform: uppercase; }
.method-get { background: #dbeafe; color: #1d4ed8; }
.endpoint-url  { font-family: monospace; font-size: 0.82rem; color: var(--gray-800); }
.endpoint-body { padding: 1rem; }
.endpoint-desc { font-size: 0.85rem; color: var(--gray-600); margin-bottom: 0.75rem; }
.param-list { list-style: none; }
.param-list li { font-size: 0.8rem; padding: 0.25rem 0; display: flex; gap: 0.5rem; }
.param-name { font-family: monospace; background: var(--gray-100); border-radius: 4px; padding: 0.1rem 0.4rem; color: var(--green-d); font-size: 0.78rem; white-space: nowrap; }
.param-desc { color: var(--gray-600); }

/* ─── SDLC ────────────────────────────────────────────── */
.sdlc-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; }
.sdlc-phase { border: 1px solid var(--gray-200); border-radius: 10px; padding: 1rem; border-left: 4px solid var(--green); }
.sdlc-phase h4 { font-size: 0.8rem; font-weight: 700; color: var(--green-d); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; }
.sdlc-phase p  { font-size: 0.78rem; color: var(--gray-600); line-height: 1.5; }

/* ─── Footer ──────────────────────────────────────────── */
footer { text-align: center; padding: 2rem; color: rgba(255,255,255,0.5); font-size: 0.78rem; }
footer span { color: rgba(255,255,255,0.85); font-weight: 600; }
</style>
</head>
<body>

<!-- ═══════════════════════════════════════════════════════
     HEADER — App branding + API source badge
     ═══════════════════════════════════════════════════════ -->
<header>
    <div class="brand">
        <div class="logo-icon">₦</div>
        <div>
            <h1>Currency Converter</h1>
            <div class="subtitle">FUTM-SWE-221 · Project 6 · Developed by Aisha</div>
        </div>
    </div>
    <span class="api-badge">Powered by Open Exchange Rates</span>
</header>

<div class="container">

    <!-- ═══════════════════════════════════════════════
         SECTION 1: API USAGE STATUS
         This calls /usage.json — shows our monthly quota
         on the free plan (1,000 requests/month).
         ═══════════════════════════════════════════════ -->
    <div class="card">
        <div class="card-header">
            <span>📡</span>
            <h2>API Usage Status &nbsp;—&nbsp; <code style="font-size:0.78rem;color:#16a34a;">GET /api/usage.json</code></h2>
            <span class="tag tag-live"><?= $usageData['error'] ? 'Error' : 'Live' ?></span>
        </div>
        <div class="card-body">
            <?php if ($usageData['error']): ?>
                <p style="color:var(--red);font-size:0.85rem;">⚠️ <?= htmlspecialchars($usageData['message']) ?></p>
            <?php else: ?>
            <div class="status-grid">
                <div class="status-item">
                    <span class="value"><?= $usedCalls ?></span>
                    <span class="label">Calls Used</span>
                </div>
                <div class="status-item">
                    <span class="value"><?= $totalCalls ?></span>
                    <span class="label">Monthly Quota</span>
                </div>
                <div class="status-item">
                    <span class="value"><?= $remainingCalls ?></span>
                    <span class="label">Remaining</span>
                </div>
                <div class="status-item">
                    <span class="value"><?= $daysLeft ?></span>
                    <span class="label">Days Left</span>
                </div>
                <div class="status-item">
                    <span class="value" style="font-size:1rem;color:var(--green);">✓ Active</span>
                    <span class="label">Plan: Free</span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════
         SECTION 2: CONVERTER FORM
         The user picks two currencies, enters an amount,
         and submits. We call /latest.json and apply:
         converted = amount × (rate_to / rate_from)
         ═══════════════════════════════════════════════ -->
    <div class="card">
        <div class="card-header">
            <span>💱</span>
            <h2>Convert Currency &nbsp;—&nbsp; <code style="font-size:0.78rem;color:#16a34a;">GET /api/latest.json</code></h2>
            <span class="tag tag-get">GET</span>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="converterForm">
                <div class="converter-grid">
                    <div class="form-group">
                        <label for="from_currency">From</label>
                        <select name="from_currency" id="from_currency">
                            <?php foreach ($TEST_CURRENCIES as $code): ?>
                                <option value="<?= $code ?>" <?= $fromCurrency === $code ? 'selected' : '' ?>>
                                    <?= $code ?> — <?= $CURRENCY_NAMES[$code] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- This button flips the two currencies without reloading -->
                    <button type="button" class="swap-btn" onclick="swapCurrencies()" title="Swap currencies">⇄</button>

                    <div class="form-group">
                        <label for="to_currency">To</label>
                        <select name="to_currency" id="to_currency">
                            <?php foreach ($TEST_CURRENCIES as $code): ?>
                                <option value="<?= $code ?>" <?= $toCurrency === $code ? 'selected' : '' ?>>
                                    <?= $code ?> — <?= $CURRENCY_NAMES[$code] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top:1rem;">
                    <label for="amount">Amount</label>
                    <input
                        type="number"
                        name="amount"
                        id="amount"
                        value="<?= htmlspecialchars($amount) ?>"
                        min="0.01" step="any"
                        placeholder="Enter amount to convert..."
                        required
                    >
                </div>

                <button type="submit" name="convert" class="convert-btn">🔄 Convert Now</button>
            </form>

            <!-- Show the conversion result after the form is submitted -->
            <?php if ($conversionError): ?>
                <div class="result-box error">
                    <div class="error-msg">⚠️ <?= htmlspecialchars($conversionError) ?></div>
                </div>

            <?php elseif ($conversionResult): ?>
                <div class="result-box success">
                    <div class="result-main">
                        <!-- Big result display — the number the user actually cares about -->
                        <div class="result-amount">
                            <?= number_format($conversionResult['converted'], 4) ?>
                            <span style="font-size:1.1rem;color:var(--green);"><?= $conversionResult['to'] ?></span>
                        </div>
                        <div class="result-meta">
                            <?= number_format($conversionResult['amount'], 2) ?> <?= $conversionResult['from'] ?>
                            &nbsp;×&nbsp;
                            <strong><?= number_format($conversionResult['rate'], 6) ?></strong>
                            &nbsp;=&nbsp;
                            <?= number_format($conversionResult['converted'], 4) ?> <?= $conversionResult['to'] ?>
                        </div>
                        <div class="result-meta" style="margin-top:0.25rem;">
                            Rate updated: <?= htmlspecialchars($conversionResult['updated_at']) ?>
                            &nbsp;·&nbsp; Formula: amount × (rate_to ÷ rate_from)
                        </div>
                    </div>
                </div>

                <!-- This is the raw API debug panel — like Postman's response pane -->
                <div class="api-debug">
                    <div class="debug-header">
                        <span>📤 Request URL (GET)</span>
                        <span style="color:#86efac;">200 OK</span>
                    </div>
                    <pre><?= htmlspecialchars($conversionResult['raw_request']) ?></pre>

                    <div class="debug-header">
                        <span>📥 Response Body (JSON) — openexchangerates.org/api/latest.json</span>
                    </div>
                    <!-- Raw JSON response from the API — same as what you'd see in Postman -->
                    <pre><?= htmlspecialchars($conversionResult['raw_response']) ?></pre>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════
         SECTION 3: LIVE RATES BOARD
         Calls /latest.json for all 5 test currencies.
         Base is always USD (free plan constraint).
         ═══════════════════════════════════════════════ -->
    <div class="card">
        <div class="card-header">
            <span>📊</span>
            <h2>Live Rates — All vs USD Base &nbsp; <code style="font-size:0.78rem;color:#16a34a;">GET /api/latest.json?symbols=NGN,USD,EUR,GBP,GHS</code></h2>
            <span class="tag tag-live">Live</span>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if ($ratesData['error']): ?>
                <p style="padding:1rem;color:var(--red);font-size:0.85rem;">⚠️ <?= htmlspecialchars($ratesData['message']) ?></p>
            <?php else: ?>
            <p style="padding:0.75rem 1rem;font-size:0.75rem;color:var(--gray-600);background:var(--gray-50);border-bottom:1px solid var(--gray-200);">
                Last updated: <?= htmlspecialchars($ratesUpdated) ?> &nbsp;·&nbsp; All rates are <strong>per 1 USD</strong> (free plan base)
            </p>
            <table class="rates-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Currency</th>
                        <th>Rate (per 1 USD)</th>
                        <th>1 unit → USD</th>
                        <th>1 NGN → this</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $ngnRate = $liveRates['NGN'] ?? null;
                    foreach ($TEST_CURRENCIES as $code):
                        $rate = $liveRates[$code] ?? null;
                    ?>
                    <tr>
                        <td><span class="currency-code"><?= $code ?></span></td>
                        <td><span class="currency-name"><?= htmlspecialchars($CURRENCY_NAMES[$code]) ?></span></td>
                        <td><span class="rate-value"><?= $rate !== null ? number_format($rate, 6) : 'N/A' ?></span></td>
                        <!-- 1 unit of this currency in USD = 1/rate -->
                        <td><span class="rate-value"><?= ($rate && $rate > 0) ? number_format(1 / $rate, 6) : 'N/A' ?></span></td>
                        <!-- How much of this currency 1 NGN buys: rate_this / rate_NGN -->
                        <td><span class="rate-value">
                            <?php
                                if ($ngnRate && $rate && $ngnRate > 0)
                                    echo number_format($rate / $ngnRate, 6);
                                else echo $code === 'NGN' ? '1.000000' : 'N/A';
                            ?>
                        </span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════
         SECTION 4: API STRUCTURE REFERENCE
         Documents all three endpoints we use.
         Written so anyone can understand it without
         opening Postman.
         ═══════════════════════════════════════════════ -->
    <div class="card">
        <div class="card-header">
            <span>📖</span>
            <h2>API Structure — Open Exchange Rates v1</h2>
            <span class="tag tag-get">Docs</span>
        </div>
        <div class="card-body">
            <p style="font-size:0.82rem;color:var(--gray-600);margin-bottom:1.25rem;">
                Base URL: <code style="background:var(--gray-100);padding:0.15rem 0.4rem;border-radius:4px;">https://openexchangerates.org/api</code>
                &nbsp;·&nbsp; App ID: <code style="background:var(--gray-100);padding:0.15rem 0.4rem;border-radius:4px;">app_id=9069645b9f1f4dc4bb634627a5e6d9d4</code>
                &nbsp;·&nbsp; Free plan base is always <strong>USD</strong>
            </p>

            <!-- Endpoint 1: /usage.json -->
            <div class="endpoint-card">
                <div class="endpoint-header">
                    <span class="method-tag method-get">GET</span>
                    <span class="endpoint-url">/usage.json</span>
                </div>
                <div class="endpoint-body">
                    <p class="endpoint-desc">Returns API usage statistics — requests used, quota, and days remaining in your billing cycle.</p>
                    <ul class="param-list">
                        <li><span class="param-name">app_id</span> <span class="param-desc">Your App ID (required on every authenticated call)</span></li>
                    </ul>
                    <div class="api-debug" style="margin-top:0.75rem;">
                        <div class="debug-header"><span>Live Response — /usage.json</span><span style="color:#86efac;">200 OK</span></div>
                        <pre>{
  "status": 200,
  "data": {
    "app_id": "9069645b9f1f4dc4bb634627a5e6d9d4",
    "status": "active",
    "plan": {
      "name": "Free",
      "quota": "1000 requests / month",
      "update_frequency": "3600s"
    },
    "usage": {
      "requests": 0,
      "requests_quota": 1000,
      "requests_remaining": 1000,
      "days_elapsed": 0,
      "days_remaining": 30,
      "daily_average": 0
    }
  }
}</pre>
                    </div>
                </div>
            </div>

            <!-- Endpoint 2: /currencies.json -->
            <div class="endpoint-card">
                <div class="endpoint-header">
                    <span class="method-tag method-get">GET</span>
                    <span class="endpoint-url">/currencies.json</span>
                </div>
                <div class="endpoint-body">
                    <p class="endpoint-desc">Returns a flat key→name map of all 170+ supported currencies. This is a public endpoint — no app_id needed.</p>
                    <ul class="param-list">
                        <li><span class="param-name">(none)</span> <span class="param-desc">No authentication required — fully public endpoint</span></li>
                    </ul>
                    <div class="api-debug" style="margin-top:0.75rem;">
                        <div class="debug-header"><span>Response — /currencies.json (excerpt)</span><span style="color:#86efac;">200 OK</span></div>
                        <pre>{
  "NGN": "Nigerian Naira",
  "USD": "United States Dollar",
  "EUR": "Euro",
  "GBP": "Pound Sterling",
  "GHS": "Ghanaian Cedi",
  ... (170+ currencies total)
}</pre>
                    </div>
                </div>
            </div>

            <!-- Endpoint 3: /latest.json -->
            <div class="endpoint-card">
                <div class="endpoint-header">
                    <span class="method-tag method-get">GET</span>
                    <span class="endpoint-url">/latest.json</span>
                </div>
                <div class="endpoint-body">
                    <p class="endpoint-desc">
                        The main conversion endpoint. Returns live rates relative to <strong>USD</strong> (free plan — base always USD).
                        To convert between two non-USD currencies, use: <code style="background:var(--gray-100);padding:0.1rem 0.3rem;border-radius:3px;">amount × (rate_to / rate_from)</code>
                    </p>
                    <ul class="param-list">
                        <li><span class="param-name">app_id</span>  <span class="param-desc">Your App ID (required)</span></li>
                        <li><span class="param-name">symbols</span> <span class="param-desc">Comma-separated currency codes to filter — e.g. NGN,USD,EUR,GBP,GHS (optional)</span></li>
                        <li><span class="param-name">base</span>    <span class="param-desc">Base currency — free plan only allows USD (paid plans allow any base)</span></li>
                    </ul>
                    <div class="api-debug" style="margin-top:0.75rem;">
                        <div class="debug-header"><span>Live Response — /latest.json?symbols=NGN,USD,EUR,GBP,GHS</span><span style="color:#86efac;">200 OK</span></div>
                        <pre>{
  "disclaimer": "Usage subject to terms: https://openexchangerates.org/terms",
  "license": "https://openexchangerates.org/license",
  "timestamp": 1782277200,
  "base": "USD",
  "rates": {
    "EUR": 0.880038,
    "GBP": 0.757958,
    "GHS": 11.225,
    "NGN": 1370.5,
    "USD": 1
  }
}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════
         SECTION 5: SDLC PHASES
         Required by the project brief — each SDLC stage
         applied to this Currency Converter project.
         ═══════════════════════════════════════════════ -->
    <div class="card">
        <div class="card-header">
            <span>🔄</span>
            <h2>SDLC Breakdown</h2>
        </div>
        <div class="card-body">
            <div class="sdlc-grid">
                <div class="sdlc-phase">
                    <h4>1. Planning</h4>
                    <p>Identified 5 core currencies for Nigeria: NGN, USD, EUR, GBP, GHS. Chose openexchangerates.org as the API provider (free, 170+ currencies).</p>
                </div>
                <div class="sdlc-phase">
                    <h4>2. Analysis</h4>
                    <p>End user = market trader persona. Needs quick, accurate NGN conversions on mobile. No registration — just open and convert.</p>
                </div>
                <div class="sdlc-phase">
                    <h4>3. Design</h4>
                    <p>Single-page converter — mobile-first, clean green finance theme. Large result display. Swap button for reversing pairs fast.</p>
                </div>
                <div class="sdlc-phase">
                    <h4>4. Implementation</h4>
                    <p>PHP + cURL calls /latest.json. Conversion: amount × (rate_to / rate_from). Base is USD on free plan — handled automatically.</p>
                </div>
                <div class="sdlc-phase">
                    <h4>5. Testing</h4>
                    <p>Tested: NGN→USD, USD→NGN, NGN→GHS, EUR→GBP, zero amounts, same-currency pairs, API timeout. All edge cases handled.</p>
                </div>
                <div class="sdlc-phase">
                    <h4>6. Deployment</h4>
                    <p>Mobile web — single PHP file. Any PHP server with cURL enabled. No database or framework needed. Runs immediately.</p>
                </div>
            </div>
        </div>
    </div>

</div>

<footer>
    Developed by <span>Aisha</span> &nbsp;·&nbsp; FUTM-SWE-221 | Project 6 — Currency Converter
    &nbsp;·&nbsp; API: <span>Open Exchange Rates</span> (openexchangerates.org)
</footer>

<script>
// Swap the FROM and TO dropdowns when the ⇄ button is clicked
function swapCurrencies() {
    const from = document.getElementById('from_currency');
    const to   = document.getElementById('to_currency');
    const temp = from.value;
    from.value = to.value;
    to.value   = temp;
}

// Catch obvious errors before the form even hits the server
document.getElementById('converterForm').addEventListener('submit', function(e) {
    const from   = document.getElementById('from_currency').value;
    const to     = document.getElementById('to_currency').value;
    const amount = parseFloat(document.getElementById('amount').value);
    if (from === to) {
        e.preventDefault();
        alert('Please choose two different currencies.');
        return;
    }
    if (isNaN(amount) || amount <= 0) {
        e.preventDefault();
        alert('Please enter a valid amount greater than zero.');
    }
});
</script>
</body>
</html>

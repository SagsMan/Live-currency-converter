# Live Currency Converter

A single-page PHP currency converter that fetches live exchange rates using the Open Exchange Rates API.

**Developed by Aisha**
FUTM-SWE-221 | Project 6 | Beginner | Finance

---

## What It Does

Converts between 5 currencies in real time: NGN (Nigerian Naira), USD (US Dollar), EUR (Euro), GBP (British Pound), and GHS (Ghanaian Cedi). The user enters an amount, picks a source and target currency, and gets an instant result pulled from live market data.

---

## API Used

**Open Exchange Rates**
Website: https://openexchangerates.org
Documentation: https://docs.openexchangerates.org

- Base URL: `https://openexchangerates.org/api`
- Authentication: App ID passed as a query parameter `?app_id=YOUR_APP_ID`
- Free plan quota: 1,000 requests per month
- Base currency: Always USD on the free plan
- Response format: JSON

**Conversion formula used (since base is always USD):**

```
converted = amount x (rate_TO / rate_FROM)
```

Example: 10,000 NGN to USD = 10000 x (1 / 1370.5) = 7.2969 USD

---

## SDLC Phases

**Planning**
Identified five core currencies relevant to Nigeria: NGN, USD, EUR, GBP, and GHS. Selected Open Exchange Rates as the API provider because it is free, requires no credit card, and supports over 170 currencies.

**Analysis**
End user identified as a market trader persona who needs quick and accurate NGN conversions on a mobile device without signing up for any account.

**Design**
Designed a single-page mobile-first converter with a clean layout, a large result display, and a swap button so users can reverse currency pairs instantly.

**Implementation**
Built with PHP and cURL. Fetches live rates from the /latest.json endpoint and applies the conversion formula client-side. All five currencies are loaded on every page visit for the live rates board.

**Testing**
Tested conversions across all currency combinations including NGN to USD, USD to NGN, NGN to GHS, and EUR to GBP. Also tested edge cases such as zero amounts, negative values, and identical source/target currencies.

**Deployment**
Deployed as a single PHP file that runs on any web server with cURL enabled. No database or external framework is required.

---

## Project Files

| File | Description |
|------|-------------|
| `index.php` | Main PHP application — single-page converter UI |
| `CURRENCY_CONVERTER_API_Docs.pdf` | Full API documentation with Postman-style requests and responses |
| `CURRENCY_CONVERTER_API_Docs.docx` | Same documentation in Word format |

---

## How to Run

1. Place `index.php` on any PHP server (localhost, cPanel, etc.)
2. Make sure cURL is enabled on the server
3. Open the file in a browser
4. Enter an amount, select currencies, and click Convert

---

## API Endpoints Used

### GET /usage.json

Checks API quota usage for the month.

**Request:**
```
GET https://openexchangerates.org/api/usage.json?app_id=9069645b9f1f4dc4bb634627a5e6d9d4
```

**Response:**
```json
{
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
}
```

---

### GET /currencies.json

Returns the full list of supported currency names. Public endpoint, no App ID needed.

**Request:**
```
GET https://openexchangerates.org/api/currencies.json
```

**Response:**
```json
{
  "AED": "UAE Dirham",
  "EUR": "Euro",
  "GBP": "Pound Sterling",
  "GHS": "Ghanaian Cedi",
  "NGN": "Nigerian Naira",
  "USD": "United States Dollar"
}
```

---

### GET /latest.json — All 5 Currencies

Fetches live rates for all five test currencies relative to USD.

**Request:**
```
GET https://openexchangerates.org/api/latest.json?app_id=9069645b9f1f4dc4bb634627a5e6d9d4&symbols=NGN,USD,EUR,GBP,GHS
```

**Response:**
```json
{
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
}
```

---

### GET /latest.json — Convert 10,000 NGN to USD

**Request:**
```
GET https://openexchangerates.org/api/latest.json?app_id=9069645b9f1f4dc4bb634627a5e6d9d4&symbols=NGN,USD
```

**Response:**
```json
{
  "disclaimer": "Usage subject to terms: https://openexchangerates.org/terms",
  "license": "https://openexchangerates.org/license",
  "timestamp": 1782277200,
  "base": "USD",
  "rates": {
    "NGN": 1370.5,
    "USD": 1
  }
}
```

**Calculation:**
```
amount    = 10,000 NGN
rate_FROM = 1370.5
rate_TO   = 1
result    = 10000 x (1 / 1370.5) = 7.2969 USD
```

---

### GET /latest.json — Convert 1 USD to NGN (Reverse Pair)

**Request:**
```
GET https://openexchangerates.org/api/latest.json?app_id=9069645b9f1f4dc4bb634627a5e6d9d4&symbols=NGN,USD
```

**Response:**
```json
{
  "disclaimer": "Usage subject to terms: https://openexchangerates.org/terms",
  "license": "https://openexchangerates.org/license",
  "timestamp": 1782277200,
  "base": "USD",
  "rates": {
    "NGN": 1370.5,
    "USD": 1
  }
}
```

**Calculation:**
```
amount    = 1 USD
rate_FROM = 1
rate_TO   = 1370.5
result    = 1 x (1370.5 / 1) = 1,370.50 NGN
```

---

## Live Currency Rates (Captured at time of testing)

| Code | Currency | Rate per 1 USD |
|------|----------|----------------|
| NGN | Nigerian Naira | 1370.500000 |
| USD | US Dollar | 1.000000 |
| EUR | Euro | 0.880038 |
| GBP | British Pound | 0.757958 |
| GHS | Ghanaian Cedi | 11.225000 |

---

Developed by Aisha — FUTM-SWE-221 | Project 6 | Currency Converter | openexchangerates.org

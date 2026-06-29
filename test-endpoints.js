/**
 * Store AI Connector — HMAC endpoint test script (ES Module)
 *
 * PowerShell:
 *   $env:API_KEY    = "your-api-key"
 *   $env:API_SECRET = "your-api-secret"
 *   $env:WP_URL     = "http://localhost:8881"   # optional
 *   $env:REST_PREFIX = "/wp-json/sac/v1"          # or "/?rest_route=/sac/v1" if permalinks off
 *   node test-endpoints.js
 *
 * HMAC-SHA256 string: timestamp + METHOD + route + body
 * route = /sac/v1 + path (no query string; matches WP $request->get_route())
 */

import crypto from 'crypto';

const WP_URL = (process.env.WP_URL || 'http://localhost:8881').replace(/\/$/, '');
const API_KEY = process.env.API_KEY || '';
const API_SECRET = process.env.API_SECRET || '';

const REST_PREFIX = process.env.REST_PREFIX || '/wp-json/sac/v1';
const SIGN_PREFIX = '/sac/v1';

const results = [];

function signRequest(method, endpoint, bodyStr, timestamp, secret) {
  const pathOnly = endpoint.split('?')[0];
  const route = `${SIGN_PREFIX}${pathOnly}`;
  const payload = `${timestamp}${method.toUpperCase()}${route}${bodyStr}`;
  return crypto.createHmac('sha256', secret).update(payload).digest('hex');
}

async function apiRequest(method, endpoint, options = {}) {
  const {
    body,
    apiKey = API_KEY,
    apiSecret = API_SECRET,
    timestamp = Math.floor(Date.now() / 1000).toString(),
    auth = true,
  } = options;

  const bodyStr = body !== undefined ? JSON.stringify(body) : '';
  const url = `${WP_URL}${REST_PREFIX}${endpoint}`;
  const headers = {
    Accept: 'application/json',
  };

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
  }

  if (auth) {
    headers['X-POD-API-Key'] = apiKey;
    headers['X-POD-Timestamp'] = timestamp;
    headers['X-POD-Signature'] = signRequest(method, endpoint, bodyStr, timestamp, apiSecret);
  }

  const response = await fetch(url, {
    method: method.toUpperCase(),
    headers,
    body: body !== undefined ? bodyStr : undefined,
  });

  let data = null;
  const text = await response.text();
  if (text) {
    try {
      data = JSON.parse(text);
    } catch {
      data = { _raw: text };
    }
  }

  return { status: response.status, data };
}

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

async function runTest(name, fn) {
  try {
    await fn();
    console.log(`✅ ${name}`);
    results.push({ name, pass: true });
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    console.log(`❌ ${name}: ${message}`);
    results.push({ name, pass: false, error: message });
  }
}

function printGroup(title) {
  console.log(`\n── ${title} ──`);
}

async function main() {
  console.log(`Store AI Connector endpoint tests`);
  console.log(`Target: ${WP_URL}${REST_PREFIX}`);

  if (!API_KEY || !API_SECRET) {
    console.warn('Warning: API_KEY / API_SECRET not set — protected tests will fail.');
  }

  // ── Health ──────────────────────────────────────────────────────────────
  printGroup('Health');

  await runTest('GET /health → 200 + wordpress_version', async () => {
    const { status, data } = await apiRequest('GET', '/health', { auth: false });
    assert(status === 200, `expected 200, got ${status}`);
    assert(data && typeof data.wordpress_version !== 'undefined', 'missing wordpress_version');
  });

  // ── Auth ────────────────────────────────────────────────────────────────
  printGroup('Auth');

  await runTest('wrong API key → 401', async () => {
    const { status } = await apiRequest('GET', '/orders?per_page=1', {
      apiKey: 'invalid-key-on-purpose',
      apiSecret: API_SECRET,
    });
    assert(status === 401, `expected 401, got ${status}`);
  });

  await runTest('timestamp 400s old → 401', async () => {
    const oldTs = (Math.floor(Date.now() / 1000) - 400).toString();
    const { status } = await apiRequest('GET', '/orders?per_page=1', {
      timestamp: oldTs,
    });
    assert(status === 401, `expected 401, got ${status}`);
  });

  // ── Orders ──────────────────────────────────────────────────────────────
  printGroup('Orders');

  await runTest('GET /orders?per_page=5 → success, data array, meta.total', async () => {
    const { status, data } = await apiRequest('GET', '/orders?per_page=5');
    assert(status === 200, `expected 200, got ${status}`);
    assert(data?.success === true, 'success !== true');
    assert(Array.isArray(data?.data), 'data is not an array');
    assert(typeof data?.meta?.total !== 'undefined', 'meta.total missing');
  });

  await runTest('GET /orders?status=processing → success=true', async () => {
    const { status, data } = await apiRequest('GET', '/orders?status=processing');
    assert(status === 200, `expected 200, got ${status}`);
    assert(data?.success === true, 'success !== true');
  });

  await runTest('GET /order-stats?period=month → stats fields', async () => {
    const { status, data } = await apiRequest('GET', '/order-stats?period=month');
    assert(status === 200, `expected 200, got ${status}`);
    assert(typeof data?.total_orders !== 'undefined', 'missing total_orders');
    assert(typeof data?.total_revenue !== 'undefined', 'missing total_revenue');
    assert(Array.isArray(data?.top_products), 'top_products is not an array');
    assert(data?.daily_revenue && typeof data.daily_revenue === 'object', 'missing daily_revenue');
  });

  await runTest('GET /order-stats?period=today → success=true', async () => {
    const { status, data } = await apiRequest('GET', '/order-stats?period=today');
    assert(status === 200, `expected 200, got ${status}`);
    assert(data?.success === true, 'success !== true');
  });

  await runTest('GET /get-woo-page-ids → success, shop_page_id', async () => {
    const { status, data } = await apiRequest('GET', '/get-woo-page-ids');
    assert(status === 200, `expected 200, got ${status}`);
    assert(data?.success === true, 'success !== true');
    assert(typeof data?.shop_page_id !== 'undefined', 'missing shop_page_id');
  });

  await runTest('POST /update-order-status {} → success=false', async () => {
    const { status, data } = await apiRequest('POST', '/update-order-status', { body: {} });
    assert(status === 200, `expected 200, got ${status}`);
    assert(data?.success === false, 'expected success=false');
  });

  await runTest("POST /update-order-status missing order → success=false", async () => {
    const { status, data } = await apiRequest('POST', '/update-order-status', {
      body: { order_id: 9999999, status: 'completed' },
    });
    assert(status === 200, `expected 200, got ${status}`);
    assert(data?.success === false, 'expected success=false');
  });

  await runTest('POST /bulk-update-orders {orders:[]} → success=true, total=0', async () => {
    const { status, data } = await apiRequest('POST', '/bulk-update-orders', { body: { orders: [] } });
    assert(status === 200, `expected 200, got ${status}`);
    assert(data?.success === true, 'success !== true');
    const total = data?.data?.total ?? data?.total;
    assert(total === 0, `expected total=0, got ${total}`);
  });

  await runTest('POST /add-tracking {order_id:1} → success=false', async () => {
    const { status, data } = await apiRequest('POST', '/add-tracking', { body: { order_id: 1 } });
    assert(status === 200, `expected 200, got ${status}`);
    assert(data?.success === false, 'expected success=false');
  });

  // ── Summary ─────────────────────────────────────────────────────────────
  const passed = results.filter((r) => r.pass).length;
  const failed = results.filter((r) => !r.pass).length;
  console.log(`\nTotal: ${results.length} | ✅ ${passed} | ❌ ${failed}`);

  if (failed > 0) {
    process.exit(1);
  }
}

main().catch((err) => {
  console.error(`Fatal: ${err.message}`);
  process.exit(1);
});

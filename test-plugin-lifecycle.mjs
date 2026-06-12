#!/usr/bin/env node
/**
 * WordPress plugin lifecycle test via admin session (curl cookie jar).
 * Usage: node test-plugin-lifecycle.mjs [baseUrl]
 */
import { execFileSync } from 'child_process';
import { existsSync, unlinkSync } from 'fs';
import { tmpdir } from 'os';
import { join } from 'path';

const BASE = (process.argv[2] || 'https://woo.nguyentrongphu.com').replace(/\/$/, '');
const USER = process.env.WP_USER || 'admin';
const PASS = process.env.WP_PASS || 'Phu@2026';
const ZIP = process.env.PLUGIN_ZIP || join(process.cwd(), '..', 'store-ai-connector.zip');
const COOKIE = join(tmpdir(), `wp-cookie-${Date.now()}.txt`);

const results = [];

function curl(args, { raw = false } = {}) {
  const out = execFileSync('curl.exe', ['-sS', '-b', COOKIE, '-c', COOKIE, ...args], {
    encoding: raw ? 'buffer' : 'utf8',
    maxBuffer: 20 * 1024 * 1024,
  });
  return raw ? out : out.toString();
}

function curlStatus(args) {
  const out = execFileSync(
    'curl.exe',
    ['-sS', '-o', 'NUL', '-w', '%{http_code}', '-b', COOKIE, '-c', COOKIE, ...args],
    { encoding: 'utf8' }
  );
  return out.trim();
}

function decodeHtml(s) {
  return s.replace(/&#038;/g, '&').replace(/&amp;/g, '&');
}

function match(html, re) {
  const m = html.match(re);
  return m ? decodeHtml(m[1]) : '';
}

function pass(name, detail = '') {
  results.push({ name, ok: true, detail });
  console.log(`✅ ${name}${detail ? ` — ${detail}` : ''}`);
}

function fail(name, detail = '') {
  results.push({ name, ok: false, detail });
  console.log(`❌ ${name}${detail ? `: ${detail}` : ''}`);
}

function assert(name, cond, detail) {
  if (cond) pass(name, detail);
  else fail(name, detail);
}

function login() {
  curl(['-c', COOKIE, `${BASE}/wp-login.php`]);
  curl([
    '-L',
    '-X', 'POST',
    `${BASE}/wp-login.php`,
    '-d', `log=${encodeURIComponent(USER)}&pwd=${encodeURIComponent(PASS)}&wp-submit=Log+In&redirect_to=${encodeURIComponent(`${BASE}/wp-admin/`)}&testcookie=1`,
  ]);
  const dash = curl([`${BASE}/wp-admin/`]);
  assert('Login to wp-admin', /Dashboard|wp-admin/i.test(dash), BASE);
}

function pluginsHtml() {
  return curl([`${BASE}/wp-admin/plugins.php`]);
}

function findPlugin(html) {
  const dataPlugin = match(html, /data-plugin="(store-ai-connector\/[^"]+)"/);
  const activate = match(html, /href="([^"]+)"[^>]*id="activate-store-ai-connector"/);
  const deactivate = match(html, /href="([^"]+)"[^>]*id="deactivate-store-ai-connector"/);
  const deleteLink = match(html, /href="([^"]+)"[^>]*id="delete-store-ai-connector"/);
  return { dataPlugin, activate, deactivate, deleteLink };
}

function adminGet(path) {
  const url = path.startsWith('http') ? path : `${BASE}/wp-admin/${path.replace(/^\//, '')}`;
  return curl(['-L', url]);
}

function uploadZip() {
  if (!existsSync(ZIP)) throw new Error(`Zip not found: ${ZIP}`);
  const uploadPage = curl([`${BASE}/wp-admin/plugin-install.php?tab=upload`]);
  const nonce = match(uploadPage, /name="_wpnonce" value="([^"]+)"/);
  assert('Get upload nonce', !!nonce, nonce || 'missing');

  const result = execFileSync(
    'curl.exe',
    [
      '-sS', '-L', '-b', COOKIE, '-c', COOKIE,
      '-F', `_wpnonce=${nonce}`,
      '-F', '_wp_http_referer=/wp-admin/plugin-install.php?tab=upload',
      '-F', `pluginzip=@${ZIP}`,
      `${BASE}/wp-admin/update.php?action=upload-plugin`,
    ],
    { encoding: 'utf8', maxBuffer: 20 * 1024 * 1024 }
  );

  const ok =
    /Plugin installed successfully/i.test(result) ||
    /Already installed/i.test(result);
  assert('Upload plugin zip', ok, result.match(/<p>([^<]{10,160})<\/p>/gi)?.[2]?.replace(/<\/?p>/gi, '') || 'check upload response');
  return result;
}

function confirmDelete(deleteHref) {
  const confirmPage = adminGet(deleteHref);
  const nonce = match(confirmPage, /name="_wpnonce" value="([^"]+)"/);
  const pluginPath = match(confirmPage, /name="checked\[\]" value="([^"]+)"/);
  assert('Delete confirmation page', !!nonce && !!pluginPath, pluginPath);

  execFileSync(
    'curl.exe',
    [
      '-sS', '-L', '-b', COOKIE, '-c', COOKIE,
      '-X', 'POST',
      `${BASE}/wp-admin/plugins.php`,
      '-d', `verify-delete=1&action=delete-selected&checked[]=${encodeURIComponent(pluginPath)}&_wpnonce=${nonce}&_wp_http_referer=/wp-admin/plugins.php&submit=Yes,+delete+these+files`,
    ],
    { encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 }
  );
}

try {
  console.log(`\n=== Plugin lifecycle test: ${BASE} ===\n`);
  login();

  console.log('\n--- Round 1: install → activate ---');
  uploadZip();

  let html = pluginsHtml();
  let p = findPlugin(html);
  assert('Plugin listed after install', !!p.dataPlugin, p.dataPlugin || 'not found');
  assert('Correct plugin path (no double nest)', p.dataPlugin === 'store-ai-connector/store-ai-connector.php', p.dataPlugin);

  if (p.activate) {
    const act = adminGet(p.activate);
    assert('Activate plugin', !/Plugin file does not exist/i.test(act), p.dataPlugin);
  } else if (p.deactivate) {
    pass('Activate plugin', 'already active');
  } else {
    fail('Activate plugin', 'no activate link');
  }

  html = pluginsHtml();
  p = findPlugin(html);
  assert('Plugin active after activate', !!p.deactivate, p.deactivate ? 'active' : 'missing deactivate link');

  console.log('\n--- Round 1: deactivate → delete ---');
  if (p.deactivate) adminGet(p.deactivate);

  html = pluginsHtml();
  p = findPlugin(html);
  assert('Plugin inactive after deactivate', !!p.activate && !p.deactivate, p.activate || 'missing activate link');

  if (p.deleteLink) {
    confirmDelete(p.deleteLink);
  } else {
    fail('Delete plugin', 'no delete link');
  }

  html = pluginsHtml();
  p = findPlugin(html);
  assert('Plugin gone after delete + reload', !p.dataPlugin, p.dataPlugin || 'removed');

  console.log('\n--- Round 2: reinstall → activate ---');
  uploadZip();

  html = pluginsHtml();
  p = findPlugin(html);
  assert('Plugin listed after reinstall', !!p.dataPlugin, p.dataPlugin);
  assert('Correct path on reinstall', p.dataPlugin === 'store-ai-connector/store-ai-connector.php', p.dataPlugin);

  if (p.activate) {
    const act2 = adminGet(p.activate);
    assert('Second activate success', !/Plugin file does not exist/i.test(act2), p.activate);
  }

  html = pluginsHtml();
  p = findPlugin(html);
  assert('Plugin active after second activate', !!p.deactivate, p.deactivate ? 'active' : 'missing deactivate link');

  const healthUrl = `${BASE}/index.php?rest_route=/sac/v1/health`;
  const health = curl([healthUrl]);
  assert('Health endpoint after activate', /"status"\s*:\s*"ok"/.test(health), healthUrl);

} catch (e) {
  fail('Fatal', e.message);
} finally {
  try { unlinkSync(COOKIE); } catch {}
}

const failed = results.filter((r) => !r.ok).length;
console.log(`\nTotal: ${results.length} | ✅ ${results.length - failed} | ❌ ${failed}`);
process.exit(failed ? 1 : 0);

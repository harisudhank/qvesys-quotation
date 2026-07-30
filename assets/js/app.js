/* Shared front-end helpers used across all admin pages */

const API_KEY = 'qvesys989403';

function csrfToken() {
  const m = document.querySelector('meta[name="csrf-token"]');
  return m ? m.getAttribute('content') : '';
}

async function apiCall(url, method = 'GET', body = null) {
  const opts = {
    method,
    headers: { 'X-CSRF-Token': csrfToken() },
  };
  if (body !== null) {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(body);
  }
  try {
    const res = await fetch(url, opts);
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.ok === false) {
      showToast(data.error || ('Request failed (' + res.status + ')'), true);
      return data;
    }
    return data;
  } catch (e) {
    showToast('Network error. Please try again.', true);
    return { ok: false, error: String(e) };
  }
}

let toastTimer = null;
function showToast(message, isError = false) {
  const el = document.getElementById('toast');
  if (!el) { console.log(message); return; }
  el.textContent = message;
  el.className = 'toast show' + (isError ? ' error' : '');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { el.classList.remove('show'); }, 3200);
}

function openModal(id) { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }

document.addEventListener('click', (e) => {
  if (e.target.classList && e.target.classList.contains('modal-backdrop')) {
    e.target.classList.remove('open');
  }
  
  // Animate sidebar shrinking before navigating to the editor
  const link = e.target.closest('a[href^="quotation-editor.php"]');
  if (link && !e.ctrlKey && !e.metaKey && link.target !== '_blank') {
    if (!document.body.classList.contains('page-editor')) {
      e.preventDefault();
      const targetUrl = link.href;
      document.body.classList.add('page-editor');
      // Wait for the CSS transition (0.25s) before loading the page
      setTimeout(() => {
        window.location.href = targetUrl;
      }, 250);
    }
  }
});

async function loadApiWidget() {
  const el = document.getElementById('api-widget');
  if (!el) return;
  try {
    const res = await fetch('api/data.php?api_key=' + API_KEY);
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'API error');
    const d = json.data;
    el.innerHTML =
      '<div class="widget-stat"><span class="widget-label">Quotations</span><span class="widget-value">' + d.total_quotations + '</span></div>' +
      '<div class="widget-stat"><span class="widget-label">Clients</span><span class="widget-value">' + d.total_clients + '</span></div>' +
      '<div class="widget-stat"><span class="widget-label">Items</span><span class="widget-value">' + d.total_items + '</span></div>' +
      '<div class="widget-stat"><span class="widget-label">Accepted</span><span class="widget-value widget-emerald">' + d.accepted + '</span></div>' +
      '<div class="widget-stat"><span class="widget-label">Pending</span><span class="widget-value widget-brass">' + d.pending + '</span></div>';
  } catch (e) {
    el.innerHTML = '<div class="widget-error">Could not load data</div>';
  }
}

document.addEventListener('DOMContentLoaded', loadApiWidget);

/* Parse CSV or Excel (via SheetJS) into array of row arrays. */
async function parseSpreadsheet(file) {
  const ext = file.name.split('.').pop().toLowerCase();
  if (ext === 'csv' || ext === 'txt') {
    const text = await file.text();
    return parseCSV(text);
  }
  // Excel (.xlsx, .xls)
  const arrayBuffer = await file.arrayBuffer();
  const workbook = XLSX.read(arrayBuffer, { type: 'array' });
  const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
  return XLSX.utils.sheet_to_json(firstSheet, { header: 1, defval: '' });
}

/* Parse RFC-4180-ish CSV text into an array of row arrays (header-aware). */
function parseCSV(text) {
  text = text.replace(/^﻿/, ''); // strip UTF-8 BOM
  const rows = [];
  let field = '', row = [], inQuotes = false;
  for (let i = 0; i < text.length; i++) {
    const c = text[i];
    if (inQuotes) {
      if (c === '"') {
        if (text[i + 1] === '"') { field += '"'; i++; }
        else inQuotes = false;
      } else {
        field += c;
      }
    } else if (c === '"') {
      inQuotes = true;
    } else if (c === ',') {
      row.push(field); field = '';
    } else if (c === '\n' || c === '\r') {
      if (c === '\r' && text[i + 1] === '\n') i++;
      row.push(field); rows.push(row); field = ''; row = [];
    } else {
      field += c;
    }
  }
  if (field !== '' || row.length) { row.push(field); rows.push(row); }
  return rows.filter(r => r.some(c => c.trim() !== ''));
}

/* Read a CSV/Excel File and POST its mapped records to the import API. */
async function submitImport(type, fileInputId) {
  const input = document.getElementById(fileInputId);
  const file = input.files && input.files[0];
  if (!file) { showToast('Please choose a CSV or Excel file first.', true); return; }

  const grid = await parseSpreadsheet(file);
  if (grid.length < 2) { showToast('File is empty or has no data rows.', true); return; }

  const headers = grid[0].map(h => h.trim().toLowerCase());
  const records = grid.slice(1).map(cols => {
    const o = {};
    headers.forEach((h, i) => { o[h] = (cols[i] ?? '').trim(); });
    return o;
  });

  const res = await apiCall('api/import.php?type=' + type, 'POST', { records });
  if (res.ok) {
    let msg = (res.imported || 0) + ' ' + (window.__lblImported || 'records imported successfully.');
    if (res.skipped) msg += ' ' + (res.skipped || 0) + ' ' + (window.__lblSkipped || 'skipped.');
    showToast(msg);
    setTimeout(() => location.reload(), 700);
  }
}




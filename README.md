# QVESYS Quotation — Multi-Template Bilingual Quotation System

A production-ready quotation management system for any sales domain (retail,
services, distribution, manufacturing, hospitality, etc). Built to your spec:
**HTML / CSS / JavaScript / PHP / JSON** — no database server required.

---

## 1. What you get

- **4 quotation designs** you pick per quotation: Simple, Detailed, GST
  Itemized (with HSN + tax-rate summary table), and Premium/Branded.
- **Bilingual**: every screen and every printed quotation works in English
  and Tamil (`த`). Add more languages later in `includes/lang.php`.
- **GST engine**: automatic CGST/SGST vs IGST based on client state vs your
  company state, per-line discount, tax-rate grouping, round-off, and
  amount-in-words (Indian numbering: Lakh/Crore).
- **Auto quotation numbering**: `QUO/2026-27/0001` style, resets every Indian
  financial year (1 Apr–31 Mar), safe under concurrent use (file-locked).
- **Client & item catalog** (JSON-backed) so repeat quoting is a few clicks.
- **Dashboard** with totals, status breakdown, recent quotations.
- **Status tracking**: Draft → Sent → Accepted / Rejected / Expired.
- **PDF / print**: every quotation view has a "Print / Save PDF" button that
  uses the browser's native print-to-PDF — no server PDF library needed, and
  it always matches what's on screen.
- **Company settings**: logo upload, GSTIN/PAN, bank details, default terms
  (English + Tamil separately), numbering prefix, validity days.
- Session login, CSRF protection on every write, `.htaccess` locking down the
  `/data` JSON files from direct web access.

---

## 2. Requirements

- PHP 8.0+ with the standard `json`, `session`, and `fileinfo` extensions
  (all enabled by default on virtually every shared host / XAMPP install).
- Apache with `mod_rewrite`/`.htaccess` support (or equivalent deny-rules on
  nginx — see §6).
- No MySQL, no Composer, no Node build step. Upload and run.

---

## 3. Local setup (XAMPP / WAMP / built-in server)

1. Copy the whole `qvesys-quotation` folder into your web root
   (e.g. `htdocs/qvesys-quotation`).
2. Make sure the `data/` folder is writable by the web server user
   (`chmod -R 775 data` on Linux/Mac; on Windows/XAMPP this is automatic).
3. Visit `http://localhost/qvesys-quotation/`.
4. Log in with the default account:
   - **Username:** `admin`
   - **Password:** `admin123`
   - **Change this immediately** from Settings → Change Password.

To try it instantly without Apache:
```
php -S localhost:8090
```
then open `http://localhost:8090/`.

---

## 4. First-time configuration checklist

Go to **Settings** and fill in:

1. **Company Profile** — legal name, address, GSTIN, PAN, state + state code
   (state code is what decides CGST/SGST vs IGST for each client).
2. **Bank Details** — shown on the Detailed and Premium templates.
3. **Logo** — PNG/JPG/WEBP/SVG, shown on every template.
4. **Quotation Settings** — numbering prefix and default validity (days),
   and default Terms & Conditions in both English and Tamil.
5. Add your **Clients** and **Items & Rates** once — every new quotation can
   then be built by picking from these instead of retyping.

---

## 5. Day-to-day usage

1. **Quotations → + New Quotation.**
2. Pick a client (or add one inline via the Clients link), set date /
   validity, add items (typed manually or pulled from your catalog —
   quantity, rate, discount %, GST % are all editable per line).
3. Totals (subtotal, discount, taxable value, CGST/SGST or IGST, round-off,
   grand total) recalculate live as you type.
4. Pick a **template style** and **language**, then **Save** (status =
   Sent) or **Save Draft**.
5. On the quotation view page you can switch template/language for preview
   at any time without affecting the saved copy, then **Print / Save PDF**.
6. From the Quotations list you can change status inline, **Duplicate** a
   quotation (issues a fresh number, useful for repeat customers), or
   delete it.

---

## 6. Deploying to a live server

1. Upload everything via FTP/cPanel File Manager to e.g.
   `public_html/quotation/`.
2. Ensure `data/` is writable (`755` or `775` depending on host).
3. The included `.htaccess` files already block direct browser access to
   `/data/*.json` and to `/includes/*.php`. If your host runs **nginx**
   instead of Apache, add to your server block:
   ```
   location ~* /(data|includes)/ { deny all; return 404; }
   ```
4. Serve the whole app over **HTTPS** — login/session cookies are sent over
   whatever protocol the browser used, so plain HTTP on a public host is not
   recommended for a real login.
5. Change the default admin password before sharing the URL with anyone.

---

## 7. Customizing further

- **Add a 5th template**: copy `templates/quote-detailed.php` to
  `templates/quote-yourname.php`, adjust the markup, then add `'yourname'`
  to the allowed-template lists in `quotation-editor.php` and
  `api/quotations.php`, plus the `$tplNames` array in the editor and the
  `<select>` in `quotation-view.php`.
- **Add a language**: add a new key (e.g. `'hi'`) to `$GLOBALS['LANG']` in
  `includes/lang.php` with the same set of keys as `en`/`ta`, then add it to
  the `in_array(...['en','ta']...)` checks (a few places) and the language
  switcher markup.
- **Change the brand palette**: everything is driven by CSS variables at the
  top of `assets/css/style.css` (`--navy`, `--brass`, `--emerald`, etc.) and
  mirrored in `assets/css/print.css` for the documents themselves.
- **Domain-specific fields** (e.g. delivery address for a distributor, room
  numbers for a hotel, warranty period for electronics): add inputs to
  `quotation-editor.php`, include them in the JS `payload`, and read them in
  `api/quotations.php` — the JSON schema has no fixed columns, so new fields
  are free to add.

---

## 8. Data model (for reference)

All data lives under `/data` as JSON arrays/objects:

- `settings.json` — company profile, quotation numbering rules, bilingual
  default terms, hashed admin credentials.
- `clients.json` — array of client records.
- `items.json` — array of catalog items (name in English + Tamil, HSN, unit,
  rate, default GST %).
- `quotations.json` — array of quotations; each stores a **snapshot** of the
  client at the time of issue (so editing a client later never rewrites
  historical quotations) plus computed line items and totals.
- `counters.json` — per-financial-year sequence counters used to generate
  quotation numbers atomically.

---

## 9. Security notes

- Passwords are hashed with PHP's `password_hash()` (bcrypt).
- All state-changing API calls require a CSRF token (auto-attached by
  `assets/js/app.js`).
- `data/` and `includes/` are blocked from direct browser access via
  `.htaccess`.
- This is a single-admin-account system by design (typical for one
  business's internal quoting tool). If multiple staff need separate
  logins/audit trails, extend `settings.json → auth` into a `users.json`
  array and adjust `includes/auth.php` accordingly.

---

Built for QVESYS Info Tech — reusable for any client vertical by changing
Settings → Company Profile and the item catalog.

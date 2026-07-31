<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/functions.php';


if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$lang = current_lang();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if ($u === '' || $p === '') {
        $error = $lang === 'ta' ? 'பயனர் பெயர் மற்றும் கடவுச்சொல் தேவை.' : 'Username and password are required.';
    } elseif (attempt_login($u, $p)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = t('login_error', $lang);
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= t('login', $lang) ?> — QVESYS Quotation</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?= $lang === 'ta' ? 'lang-ta' : '' ?>">
<div class="login-wrap">
  <div class="login-card">
    <div class="doc-icon" style="margin:0 auto 14px;">
      <div class="doc-fold"></div>
      <div class="doc-rupee">₹</div>
      <div class="doc-lines">
        <span></span><span></span><span></span><span class="short"></span>
      </div>
    </div>
    <h2 class="display" style="text-align:center;">QVESYS Quotation</h2>
    <div class="sub"><?= t('login_title', $lang) ?></div>
    <?php if ($error): ?>
      <div class="field-error" style="background:#FCE8E6; color:#B4453A; font-size:13px; padding:10px 14px; border-radius:8px; margin-bottom:14px; display:flex; align-items:center; gap:8px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
          <line x1="12" y1="9" x2="12" y2="13"></line>
          <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
        <?= h($error) ?>
      </div>
    <?php endif; ?>
    <form method="post" id="loginForm" novalidate>
      <div class="field">
        <label><?= t('username', $lang) ?></label>
        <input type="text" name="username" id="username" autocomplete="username" autofocus data-tip="<?= t('username', $lang) ?>">
        <div class="field-error-msg" id="usernameError" style="display:none; color:#B4453A; font-size:12px; margin-top:5px;">
          <?= $lang === 'ta' ? 'பயனர் பெயர் தேவை.' : 'Username is required.' ?>
        </div>
      </div>
      <div class="field">
        <label><?= t('password', $lang) ?></label>
        <input type="password" name="password" id="password" autocomplete="current-password" data-tip="<?= t('password', $lang) ?>">
        <div class="field-error-msg" id="passwordError" style="display:none; color:#B4453A; font-size:12px; margin-top:5px;">
          <?= $lang === 'ta' ? 'கடவுச்சொல் தேவை.' : 'Password is required.' ?>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-block" id="loginBtn" data-tip="Sign in to your account">
        <?= t('login', $lang) ?>
      </button>
    </form>
    <div style="text-align:center; margin-top:16px;">
      <a href="?lang=en" class="login-lang-link <?= $lang === 'en' ? 'active' : '' ?>" data-tip="Switch to English">EN</a>
      <a href="?lang=ta" class="login-lang-link <?= $lang === 'ta' ? 'active' : '' ?>" data-tip="Switch to Tamil">தமிழ்</a>
    </div>
  </div>
</div>
<script>
(function() {
  const form = document.getElementById('loginForm');
  const username = document.getElementById('username');
  const password = document.getElementById('password');
  const usernameError = document.getElementById('usernameError');
  const passwordError = document.getElementById('passwordError');

  function validateField(input, errorEl) {
    if (input.value.trim() === '') {
      errorEl.style.display = 'block';
      input.style.borderColor = '#B4453A';
      return false;
    } else {
      errorEl.style.display = 'none';
      input.style.borderColor = '';
      return true;
    }
  }

  username.addEventListener('input', function() { validateField(username, usernameError); });
  password.addEventListener('input', function() { validateField(password, passwordError); });

  form.addEventListener('submit', function(e) {
    var valid = true;
    if (!validateField(username, usernameError)) valid = false;
    if (!validateField(password, passwordError)) valid = false;
    if (!valid) e.preventDefault();
  });
})();
</script>
</body>
</html>

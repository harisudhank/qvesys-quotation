<?php
/**
 * Shared page chrome. Include after require_login() and after setting
 * $pageTitle and $activeNav in the calling page.
 */
$lang = current_lang();
$settings = db_read('settings');
$companyName = $settings['company']['name'] ?? 'QVESYS Quotation';
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle ?? t('app_name', $lang)) ?> — <?= h($companyName) ?></title>
<link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
</head>
<body class="<?= $lang === 'ta' ? 'lang-ta' : '' ?> <?= $bodyClass ?? '' ?>">
<div class="shell">
  <aside class="sidebar">
      <div class="sidebar-brand">
      <div class="doc-icon">
        <div class="doc-fold"></div>
        <div class="doc-rupee">₹</div>
        <div class="doc-lines">
          <span></span><span></span><span></span><span class="short"></span>
        </div>
      </div>
      <div>
        <div class="name">QVESYS</div>
        <div class="sub"><?= t('quotations', $lang) ?></div>
      </div>
    </div>
    <nav class="nav">
      <a href="dashboard.php" class="<?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>"><span class="icon">▤</span><span class="nav-text"><?= t('dashboard', $lang) ?></span></a>
      <a href="quotations.php" class="<?= ($activeNav ?? '') === 'quotations' ? 'active' : '' ?>"><span class="icon">▦</span><span class="nav-text"><?= t('quotations', $lang) ?></span></a>
      <a href="quotations.php?comparative=1" class="<?= ($activeNav ?? '') === 'comparative_quotations' ? 'active' : '' ?>"><span class="icon">◶</span><span class="nav-text"><?= t('comparative_quotations', $lang) ?></span></a>
      <a href="company.php" class="<?= ($activeNav ?? '') === 'company' ? 'active' : '' ?>"><span class="icon">🏢</span><span class="nav-text"><?= t('company', $lang) ?></span></a>
      <a href="clients.php" class="<?= ($activeNav ?? '') === 'clients' ? 'active' : '' ?>"><span class="icon">☰</span><span class="nav-text"><?= t('clients', $lang) ?></span></a>
      <a href="items.php" class="<?= ($activeNav ?? '') === 'items' ? 'active' : '' ?>"><span class="icon">◫</span><span class="nav-text"><?= t('items', $lang) ?></span></a>
      <a href="settings.php" class="<?= ($activeNav ?? '') === 'settings' ? 'active' : '' ?>"><span class="icon">⚙</span><span class="nav-text"><?= t('settings', $lang) ?></span></a>
    </nav>
    <div class="sidebar-widget" id="api-widget"></div>
    <div class="sidebar-foot">
      <?= h(current_user()['name'] ?? '') ?><br>
      <a href="logout.php"><?= t('logout', $lang) ?></a>
    </div>
  </aside>
  <div class="main">
    <div class="topbar no-print">
      <div>
        <div class="crumb"><?= h($companyName) ?></div>
        <h1><?= h($pageTitle ?? '') ?></h1>
      </div>
      <div style="display:flex; align-items:center; gap:14px;">
        <div class="lang-switch">
          <a href="?lang=en" class="<?= $lang === 'en' ? 'active' : '' ?>">EN</a>
          <a href="?lang=ta" class="<?= $lang === 'ta' ? 'active' : '' ?>">தமிழ்</a>
        </div>
        <?php if (!empty($topbarAction)) echo $topbarAction; ?>
      </div>
    </div>
    <div class="content">

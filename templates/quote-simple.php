<?php
/** @var array $q  @var array $settings  @var string $lang */
$company = $settings['company'];
$render_groups = !empty($q['is_comparative']) ? $q['options'] : ['' => $q];

$idx = 0;
foreach ($render_groups as $groupName => $grp):
  $clientName = !empty($q['is_comparative']) ? $groupName : ($q['client_snapshot']['name'] ?? '');
  $clientAddress = !empty($q['is_comparative']) ? '' : ($q['client_snapshot']['address'] ?? '');
  $clientPhone = !empty($q['is_comparative']) ? '' : ($q['client_snapshot']['phone'] ?? '');
  $clientGstin = !empty($q['is_comparative']) ? '' : ($q['client_snapshot']['gstin'] ?? '');
?>
<div class="doc-page tpl-simple <?= $lang === 'ta' ? 'lang-ta' : '' ?>" style="<?= $idx > 0 ? 'margin-top:40px; page-break-before:always;' : '' ?>">
  <?php if ($watermark_enabled && !empty($watermark_text)): ?>
  <div class="doc-watermark"><?= h($watermark_text) ?></div>
  <?php endif; ?>
  <div class="doc-header">
    <div class="doc-header-brand">
      <?php if (!empty($company['logo'])): ?>
        <img src="<?= h($company['logo']) ?>" class="doc-logo">
      <?php endif; ?>
      <div>
        <div class="doc-company-name"><?= h($company['name']) ?></div>
        <div class="doc-company-meta">
          <?= h($company['address']) ?><br>
          <?= t('phone', $lang) ?>: <?= h($company['phone']) ?> &nbsp;•&nbsp; <?= h($company['email']) ?><br>
          <?= t('gstin', $lang) ?>: <?= h($company['gstin']) ?>
        </div>
      </div>
    </div>
    <div class="doc-title-block">
      <div class="doc-doctype"><?= !empty($custom_header_title) ? h($custom_header_title) : t('quotation', $lang) ?></div>
      <div class="doc-meta-row"><b><?= t('quotation_no', $lang) ?>:</b> <?= h($q['number']) ?></div>
      <div class="doc-meta-row"><b><?= t('date', $lang) ?>:</b> <?= h($q['date']) ?></div>
      <div class="doc-meta-row"><b><?= t('valid_until', $lang) ?>:</b> <?= h($q['valid_until']) ?></div>
    </div>
  </div>

  <div class="doc-parties">

    <div class="doc-party">
      <div class="lbl"><?= t('to', $lang) ?></div>
      <div class="nm"><?= h($clientName) ?></div>
      <div class="ln">
        <?= h($clientAddress) ?><br>
        <?php if (!empty($clientGstin)): ?><?= t('gstin', $lang) ?>: <?= h($clientGstin) ?><br><?php endif; ?>
        <?= h($clientPhone) ?>
      </div>
    </div>
  </div>

  <table class="doc-table">
    <thead>
      <tr>
        <?php if ($show_col_sno): ?><th style="width:28px;"><?= !empty($lbl_col_sno) ? h($lbl_col_sno) : t('sno', $lang) ?></th><?php endif; ?>
        <?php if ($show_col_desc): ?><th><?= !empty($lbl_col_desc) ? h($lbl_col_desc) : t('item_description', $lang) ?></th><?php endif; ?>
        <?php if ($show_col_qty): ?><th class="num" style="width:60px;"><?= !empty($lbl_col_qty) ? h($lbl_col_qty) : t('qty', $lang) ?></th><?php endif; ?>
        <?php if ($show_col_rate): ?><th class="num" style="width:90px;"><?= !empty($lbl_col_rate) ? h($lbl_col_rate) : t('rate', $lang) ?></th><?php endif; ?>
        <?php if ($show_col_amount): ?><th class="num" style="width:100px;"><?= !empty($lbl_col_amount) ? h($lbl_col_amount) : t('amount', $lang) ?></th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($grp['items'] as $i => $it): ?>
      <tr>
        <?php if ($show_col_sno): ?><td><?= $i + 1 ?></td><?php endif; ?>
        <?php if ($show_col_desc): ?><td><?= h($it['name']) ?></td><?php endif; ?>
        <?php if ($show_col_qty): ?><td class="num"><?= h((string)$it['qty']) ?> <?= h($it['unit']) ?></td><?php endif; ?>
        <?php if ($show_col_rate): ?><td class="num"><?= format_currency((float)$it['rate']) ?></td><?php endif; ?>
        <?php if ($show_col_amount): ?><td class="num"><?= format_currency((float)$it['line_total']) ?></td><?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="doc-totals">
    <div class="ln"><span><?= t('subtotal', $lang) ?></span><span><?= format_currency((float)$grp['subtotal']) ?></span></div>
    <?php if ($grp['discount_total'] > 0): ?><div class="ln"><span><?= t('total_discount', $lang) ?></span><span>-<?= format_currency((float)$grp['discount_total']) ?></span></div><?php endif; ?>
    <?php if ($q['is_gst_enabled'] ?? true): ?>
      <?php if (!empty($q['inter_state'])): ?>
        <div class="ln"><span><?= t('igst', $lang) ?></span><span><?= format_currency((float)$grp['igst']) ?></span></div>
      <?php else: ?>
        <div class="ln"><span><?= t('cgst', $lang) ?></span><span><?= format_currency((float)$grp['cgst']) ?></span></div>
        <div class="ln"><span><?= t('sgst', $lang) ?></span><span><?= format_currency((float)$grp['sgst']) ?></span></div>
      <?php endif; ?>
    <?php endif; ?>
    <div class="ln grand"><span><?= t('grand_total', $lang) ?></span><span><?= format_currency((float)$grp['total']) ?></span></div>
  </div>
  <div class="doc-words"><?= t('in_words', $lang) ?>: <?= h(amount_in_words((float)$grp['total'])) ?></div>

  <div class="doc-footer-grid">
    <div class="doc-terms">
      <b><?= t('terms', $lang) ?></b><br><?= h($q['terms']) ?>
    </div>
    <?php if (!empty($company['bank_name'])): ?>
    <div class="doc-bank">
      <b><?= t('bank_details', $lang) ?></b><br>
      <?= h($company['bank_name']) ?> · <?= h($company['bank_account']) ?><br>
      IFSC: <?= h($company['bank_ifsc']) ?>
    </div>
    <?php endif; ?>
    <div class="doc-sign">
      <div>For <?= h($company['name']) ?></div>
      <div class="sign-space"></div>
      <div class="line"><?= !empty($custom_signatory) ? h($custom_signatory) : t('authorized_signatory', $lang) ?></div>
    </div>
  </div>

<?php if (!empty($custom_footer_content)): ?>
  <div class="doc-thankyou"><?= nl2br(h($custom_footer_content)) ?></div>
<?php else: ?>
  <div class="doc-thankyou"><?= t('thank_you', $lang) ?></div>
  <div class="doc-note"><?= t('this_is_computer_generated', $lang) ?></div>
<?php endif; ?>
</div>
<?php 
  $idx++;
endforeach; 
?>

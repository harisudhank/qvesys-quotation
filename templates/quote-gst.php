<?php
/** @var array $q  @var array $settings  @var string $lang */
$company = $settings['company'];
$interState = !empty($q['inter_state']);
$render_groups = !empty($q['is_comparative']) ? $q['options'] : ['' => $q];

$idx = 0;
foreach ($render_groups as $groupName => $grp):
  $clientName = !empty($q['is_comparative']) ? $groupName : ($q['client_snapshot']['name'] ?? '');
  $clientAddress = !empty($q['is_comparative']) ? '' : ($q['client_snapshot']['address'] ?? '');
  $clientPhone = !empty($q['is_comparative']) ? '' : ($q['client_snapshot']['phone'] ?? '');
  $clientGstin = !empty($q['is_comparative']) ? '' : ($q['client_snapshot']['gstin'] ?? '');
  $clientState = !empty($q['is_comparative']) ? ($settings['company']['state'] ?? '') : ($q['client_snapshot']['state'] ?? '');

  $byRate = [];
  foreach ($grp['items'] as $it) {
      $rate = (float)$it['tax_percent'];
      if (!isset($byRate[$rate])) $byRate[$rate] = ['taxable' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0];
      $byRate[$rate]['taxable'] += $it['line_taxable'];
      $byRate[$rate]['cgst'] += $it['line_cgst'];
      $byRate[$rate]['sgst'] += $it['line_sgst'];
      $byRate[$rate]['igst'] += $it['line_igst'];
  }
  ksort($byRate);
?>
<div class="doc-page tpl-gst <?= $lang === 'ta' ? 'lang-ta' : '' ?>" style="<?= $idx > 0 ? 'margin-top:40px; page-break-before:always;' : '' ?>">
  <?php if ($watermark_enabled && !empty($watermark_text)): ?>
  <div class="doc-watermark"><?= h($watermark_text) ?></div>
  <?php endif; ?>
  <div class="doc-header">
    <div class="doc-header-brand">
      <?php if (!empty($company['logo'])): ?>
        <img src="<?= h($company['logo']) ?>" class="doc-logo">
      <?php endif; ?>
      <div>
        <div class="doc-company-name" data-editable-key="company-name"><?= h($company['name']) ?></div>
        <div class="doc-company-meta" data-editable-key="company-meta">
          <?php if (!empty($company['tagline'])): ?><?= h($company['tagline']) ?><br><?php endif; ?>
          <?= h($company['address']) ?><br>
          <?= t('phone', $lang) ?>: <?= h($company['phone']) ?><br>
          <?= h($company['email']) ?>
        </div>
      </div>
    </div>
    <div class="doc-title-block">
      <div class="doc-doctype" data-editable-key="doctype"><?= !empty($custom_header_title) ? h($custom_header_title) : t('proforma_invoice', $lang) ?></div>
      <div class="doc-meta-row" data-editable-key="quotation-no"><b><?= t('quotation_no', $lang) ?>:</b> <?= h($q['number']) ?></div>
      <div class="doc-meta-row" data-editable-key="date"><b><?= t('date', $lang) ?>:</b> <?= h($q['date']) ?></div>
      <div class="doc-meta-row" data-editable-key="place-of-supply"><b>Place of Supply:</b> <?= h($clientState) ?> (<?= $interState ? 'Inter-State' : 'Intra-State' ?>)</div>
    </div>
  </div>

  <div class="doc-parties">

    <div class="doc-party" data-editable-key="party-to">
      <div class="lbl"><?= t('to', $lang) ?></div>
      <div class="nm"><?= h($clientName) ?></div>
      <div class="ln">
        <?= h($clientAddress) ?><br>
        <?php if (!empty($clientGstin)): ?><?= t('gstin', $lang) ?>: <b><?= h($clientGstin) ?></b><?php endif; ?>
      </div>
    </div>
  </div>

<div class="doc-letter-head">
  <?php if (!empty($custom_subject)): ?><div class="doc-subject" data-editable-key="subject"><b><?= t('subject', $lang) ?>:</b> <?= h($custom_subject) ?></div><?php endif; ?>
  <?php if (!empty($custom_salutation)): ?><div class="doc-salutation" data-editable-key="salutation"><?= h($custom_salutation) ?></div><?php endif; ?>
  <?php if (!empty($custom_body_text)): ?><div class="doc-body-text" data-editable-key="body-text"><?= nl2br(h($custom_body_text)) ?></div><?php endif; ?>
</div>

  <table class="doc-table" data-editable-key="items-table">
    <thead>
      <tr>
        <?php if ($show_col_sno): ?><th style="width:24px;"><?= !empty($lbl_col_sno) ? h($lbl_col_sno) : t('sno', $lang) ?></th><?php endif; ?>
        <?php if ($show_col_desc): ?><th><?= !empty($lbl_col_desc) ? h($lbl_col_desc) : t('item_description', $lang) ?></th><?php endif; ?>
        <?php if ($show_col_hsn): ?><th style="width:60px;"><?= !empty($lbl_col_hsn) ? h($lbl_col_hsn) : t('hsn_sac', $lang) ?></th><?php endif; ?>
        <?php if ($show_col_qty): ?><th class="num" style="width:45px;"><?= !empty($lbl_col_qty) ? h($lbl_col_qty) : t('qty', $lang) ?></th><?php endif; ?>
        <?php if ($show_col_rate): ?><th class="num" style="width:75px;"><?= !empty($lbl_col_rate) ? h($lbl_col_rate) : t('rate', $lang) ?></th><?php endif; ?>
        <?php if ($show_col_taxable): ?><th class="num" style="width:70px;"><?= !empty($lbl_col_taxable) ? h($lbl_col_taxable) : t('taxable_amount', $lang) ?></th><?php endif; ?>
        <?php if ($show_col_tax_percent): ?><th class="num" style="width:40px;"><?= !empty($lbl_col_tax_percent) ? h($lbl_col_tax_percent) : t('tax', $lang) ?></th><?php endif; ?>
        <?php if ($show_col_gst): ?>
          <?php if ($interState): ?>
            <th class="num" style="width:70px;"><?= !empty($lbl_col_gst) ? h($lbl_col_gst) : t('igst', $lang) ?></th>
          <?php else: ?>
            <th class="num" style="width:60px;"><?= !empty($lbl_col_gst) ? h($lbl_col_gst) . ' (C)' : t('cgst', $lang) ?></th>
            <th class="num" style="width:60px;"><?= !empty($lbl_col_gst) ? h($lbl_col_gst) . ' (S)' : t('sgst', $lang) ?></th>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($show_col_amount): ?><th class="num" style="width:85px;"><?= !empty($lbl_col_amount) ? h($lbl_col_amount) : t('amount', $lang) ?></th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($grp['items'] as $i => $it): ?>
      <tr>
        <?php if ($show_col_sno): ?><td><?= $i + 1 ?></td><?php endif; ?>
        <?php if ($show_col_desc): ?><td><?= h($it['name']) ?></td><?php endif; ?>
        <?php if ($show_col_hsn): ?><td><?= h($it['hsn'] ?? '') ?></td><?php endif; ?>
        <?php if ($show_col_qty): ?><td class="num"><?= h((string)$it['qty']) ?></td><?php endif; ?>
        <?php if ($show_col_rate): ?><td class="num"><?= format_currency((float)$it['rate']) ?></td><?php endif; ?>
        <?php if ($show_col_taxable): ?><td class="num"><?= format_currency((float)$it['line_taxable']) ?></td><?php endif; ?>
        <?php if ($show_col_tax_percent): ?><td class="num"><?= h((string)$it['tax_percent']) ?>%</td><?php endif; ?>
        <?php if ($show_col_gst): ?>
          <?php if ($interState): ?>
            <td class="num"><?= format_currency((float)$it['line_igst']) ?></td>
          <?php else: ?>
            <td class="num"><?= format_currency((float)$it['line_cgst']) ?></td>
            <td class="num"><?= format_currency((float)$it['line_sgst']) ?></td>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($show_col_amount): ?><td class="num"><?= format_currency((float)$it['line_total']) ?></td><?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($q['is_gst_enabled'] ?? true): ?>
  <table class="doc-table tax-summary" style="margin-top:14px; width:60%; margin-left:auto;" data-editable-key="tax-summary">
    <thead>
      <tr>
        <th><?= t('tax', $lang) ?></th>
        <th class="num"><?= t('taxable_amount', $lang) ?></th>
        <?php if ($interState): ?><th class="num"><?= t('igst', $lang) ?></th>
        <?php else: ?><th class="num"><?= t('cgst', $lang) ?></th><th class="num"><?= t('sgst', $lang) ?></th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($byRate as $rate => $vals): ?>
      <tr>
        <td><?= h((string)$rate) ?>%</td>
        <td class="num"><?= format_currency($vals['taxable']) ?></td>
        <?php if ($interState): ?><td class="num"><?= format_currency($vals['igst']) ?></td>
        <?php else: ?><td class="num"><?= format_currency($vals['cgst']) ?></td><td class="num"><?= format_currency($vals['sgst']) ?></td><?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <div class="doc-totals" data-editable-key="totals">
    <div class="ln"><span><?= t('taxable_amount', $lang) ?></span><span><?= format_currency((float)$grp['taxable_amount']) ?></span></div>
    <?php if ($q['is_gst_enabled'] ?? true): ?>
      <?php if ($interState): ?>
        <div class="ln"><span><?= t('igst', $lang) ?></span><span><?= format_currency((float)$grp['igst']) ?></span></div>
      <?php else: ?>
        <div class="ln"><span><?= t('cgst', $lang) ?></span><span><?= format_currency((float)$grp['cgst']) ?></span></div>
        <div class="ln"><span><?= t('sgst', $lang) ?></span><span><?= format_currency((float)$grp['sgst']) ?></span></div>
      <?php endif; ?>
    <?php endif; ?>
    <div class="ln"><span><?= t('round_off', $lang) ?></span><span><?= format_currency((float)$grp['round_off']) ?></span></div>
    <div class="ln grand"><span><?= t('grand_total', $lang) ?></span><span><?= format_currency((float)$grp['total']) ?></span></div>
  </div>
  <div class="doc-words" data-editable-key="in-words"><?= t('in_words', $lang) ?>: <?= h(amount_in_words((float)$grp['total'])) ?></div>

  <div class="doc-footer-grid">
    <div class="doc-terms" data-editable-key="terms"><b><?= t('terms', $lang) ?></b><br><?= h($q['terms']) ?></div>
    <?php if (!empty($company['bank_name'])): ?>
    <div class="doc-bank" data-editable-key="bank">
      <b><?= t('bank_details', $lang) ?></b><br>
      <?= h($company['bank_name']) ?><br>
      A/C: <?= h($company['bank_account']) ?><br>
      IFSC: <?= h($company['bank_ifsc']) ?><br>
      <?= h($company['bank_branch']) ?>
    </div>
<?php endif; ?>
<?php if (!empty($company['qr_code']) && $qr_code_enabled): ?>
    <div class="doc-qr" data-editable-key="qr-code">
      <img src="<?= h($company['qr_code']) ?>" style="height:70px;">
    </div>
<?php endif; ?>
    <div class="doc-sign" data-editable-key="signatory">
      <div><?= t('for', $lang) ?> <?= h($company['name']) ?></div>
      <div class="sign-space"></div>
      <div class="line"><?= !empty($custom_signatory) ? h($custom_signatory) : t('authorized_signatory', $lang) ?></div>
    </div>
  </div>
<?php if (!empty($custom_footer_content)): ?>
  <div class="doc-note" style="margin-top:20px;" data-editable-key="note"><?= nl2br(h($custom_footer_content)) ?></div>
<?php else: ?>
  <div class="doc-note" data-editable-key="note"><?= t('this_is_computer_generated', $lang) ?></div>
<?php endif; ?>
</div>
<?php 
  $idx++;
endforeach; 
?>

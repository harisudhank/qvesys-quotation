<?php
/** @var array $q  @var array $settings  @var string $lang */
$company = $settings['company'];
$companies = array_keys($q['options'] ?? []);
$items = $q['items'] ?? [];
?>
<div class="doc-page tpl-comparative <?= $lang === 'ta' ? 'lang-ta' : '' ?>">
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
          <?= h($company['address']) ?><br>
          <?= t('phone', $lang) ?>: <?= h($company['phone']) ?><br>
          <?= h($company['email']) ?>
        </div>
      </div>
    </div>
    <div class="doc-title-block">
      <div class="doc-doctype" data-editable-key="doctype"><?= t('comparative_quotation', $lang) ?></div>
      <div class="doc-meta-row" data-editable-key="quotation-no"><b><?= t('quotation_no', $lang) ?>:</b> <?= h($q['number']) ?></div>
      <div class="doc-meta-row" data-editable-key="date"><b><?= t('date', $lang) ?>:</b> <?= h($q['date']) ?></div>
      <div class="doc-meta-row" data-editable-key="valid-until"><b><?= t('valid_until', $lang) ?>:</b> <?= h($q['valid_until']) ?></div>
    </div>
  </div>

  <div class="doc-parties">
    <div class="doc-party" data-editable-key="party-to">
      <div class="lbl">Project / Bid Title</div>
      <div class="nm" style="font-size:16px; color:var(--bill-primary);"><?= h($q['client_snapshot']['name'] ?? 'Comparative Quotation') ?></div>
    </div>
  </div>

<div class="doc-letter-head">
  <?php if (!empty($custom_subject)): ?><div class="doc-subject" data-editable-key="subject"><b><?= t('subject', $lang) ?>:</b> <?= h($custom_subject) ?></div><?php endif; ?>
  <?php if (!empty($custom_salutation)): ?><div class="doc-salutation" data-editable-key="salutation"><?= h($custom_salutation) ?></div><?php endif; ?>
  <?php if (!empty($custom_body_text)): ?><div class="doc-body-text" data-editable-key="body-text"><?= nl2br(h($custom_body_text)) ?></div><?php endif; ?>
</div>

  <table class="doc-table" style="width:100%; border-collapse:collapse; font-size:11px;" data-editable-key="items-table">
    <thead>
      <tr>
        <th style="width:28px;"><?= t('sno', $lang) ?></th>
        <th><?= t('item_description', $lang) ?></th>
        <th class="num" style="width:40px;"><?= t('qty', $lang) ?></th>
        <th style="width:45px;"><?= t('unit', $lang) ?></th>
        <?php foreach ($companies as $comp): ?>
          <th class="num" colspan="2" style="text-align:center; border-left:1px solid #ddd; background:#fcfcfc;">
            <?= h($comp) ?>
          </th>
        <?php endforeach; ?>
      </tr>
      <tr>
        <th colspan="4" style="border-bottom:2px solid #aaa;"></th>
        <?php foreach ($companies as $comp): ?>
          <th class="num" style="width:65px; font-size:10px; border-left:1px solid #ddd; border-bottom:2px solid #aaa; background:#fcfcfc;"><?= t('rate', $lang) ?></th>
          <th class="num" style="width:75px; font-size:10px; border-bottom:2px solid #aaa; background:#fcfcfc;"><?= t('amount', $lang) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $i => $it): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td><?= h($it['name']) ?></td>
        <td class="num"><?= h((string)$it['qty']) ?></td>
        <td><?= h($it['unit']) ?></td>
        <?php foreach ($companies as $comp): ?>
          <?php 
            $compItem = $q['options'][$comp]['items'][$i] ?? null;
            $rate = $compItem ? (float)$compItem['rate'] : 0.0;
            $total = $compItem ? (float)$compItem['line_total'] : 0.0;
          ?>
          <td class="num" style="border-left:1px solid #eee;"><?= format_currency($rate) ?></td>
          <td class="num"><?= format_currency($total) ?></td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr style="font-weight:bold; border-top:2px solid #888;">
        <td colspan="4" style="text-align:right;"><?= t('subtotal', $lang) ?></td>
        <?php foreach ($companies as $comp): ?>
          <td class="num" style="border-left:1px solid #ddd;"></td>
          <td class="num"><?= format_currency((float)($q['options'][$comp]['subtotal'] ?? 0)) ?></td>
        <?php endforeach; ?>
      </tr>
      <?php if ($q['is_gst_enabled'] ?? true): ?>
        <?php if (!empty($q['inter_state'])): ?>
          <tr style="font-weight:bold;">
            <td colspan="4" style="text-align:right;"><?= t('igst', $lang) ?></td>
            <?php foreach ($companies as $comp): ?>
              <td class="num" style="border-left:1px solid #ddd;"></td>
              <td class="num"><?= format_currency((float)($q['options'][$comp]['igst'] ?? 0)) ?></td>
            <?php endforeach; ?>
          </tr>
        <?php else: ?>
          <tr style="font-weight:bold; font-size:10.5px; color:#555;">
            <td colspan="4" style="text-align:right;">CGST / SGST</td>
            <?php foreach ($companies as $comp): ?>
              <td class="num" style="border-left:1px solid #ddd;"></td>
              <td class="num"><?= format_currency((float)($q['options'][$comp]['cgst'] + $q['options'][$comp]['sgst'])) ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endif; ?>
      <?php endif; ?>
      <tr style="font-weight:bold; font-size:13px; border-top:2px solid var(--bill-primary); background:#fafafa;">
        <td colspan="4" style="text-align:right; color:var(--bill-primary);"><?= t('grand_total', $lang) ?></td>
        <?php foreach ($companies as $comp): ?>
          <td class="num" style="border-left:1px solid #ddd;"></td>
          <td class="num" style="color:var(--bill-primary);"><?= format_currency((float)($q['options'][$comp]['total'] ?? 0)) ?></td>
        <?php endforeach; ?>
      </tr>
    </tfoot>
  </table>

  <div class="doc-footer-grid" style="margin-top:24px;">
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
</div>

<?php
require_once __DIR__ . '/db.php';

/** Indian financial year label for a given date, e.g. 2026-07-12 -> "2026-27" */
function financial_year(?string $date = null): string
{
    $ts = $date ? strtotime($date) : time();
    $y = (int)date('Y', $ts);
    $m = (int)date('n', $ts);
    if ($m >= 4) {
        return $y . '-' . substr((string)($y + 1), 2, 2);
    }
    return ($y - 1) . '-' . substr((string)$y, 2, 2);
}

/**
 * Atomically allocate the next quotation number for the current settings
 * prefix + financial year. Format: PREFIX/FY/000N (e.g. QUO/2026-27/0007)
 */
function next_quotation_number(): array
{
    $settings = db_read('settings');
    $prefix = $settings['quotation']['prefix'] ?? 'QUO';
    $resetPerFY = $settings['quotation']['financial_year_reset'] ?? true;
    $fy = financial_year();
    $counterKey = $resetPerFY ? "FY{$fy}" : 'ALL';

    $number = db_transaction('counters', function ($counters) use ($counterKey, &$number) {
        $counters[$counterKey] = ($counters[$counterKey] ?? 0) + 1;
        $number = $counters[$counterKey];
        return $counters;
    });

    $seq = $number[$counterKey];
    $padded = str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    $label = $resetPerFY ? "{$prefix}/{$fy}/{$padded}" : "{$prefix}/{$padded}";

    return ['number' => $label, 'seq' => $seq, 'fy' => $fy];
}

/** GST split: same state = CGST+SGST, different state = IGST. */
function calc_gst(float $taxableAmount, float $ratePercent, bool $interState): array
{
    if ($interState) {
        $igst = round($taxableAmount * $ratePercent / 100, 2);
        return ['cgst' => 0.0, 'sgst' => 0.0, 'igst' => $igst, 'total_tax' => $igst];
    }
    $half = round($taxableAmount * ($ratePercent / 2) / 100, 2);
    return ['cgst' => $half, 'sgst' => $half, 'igst' => 0.0, 'total_tax' => $half * 2];
}

function calculate_group_totals(array $items, bool $interState, bool $isGstEnabled = true): array
{
    $subtotal = 0.0;
    $totalDiscount = 0.0;
    $taxableAmount = 0.0;
    $cgstTotal = 0.0;
    $sgstTotal = 0.0;
    $igstTotal = 0.0;
    $computedItems = [];

    foreach ($items as $it) {
        $qty = (float)($it['qty'] ?? 0);
        $rate = (float)($it['rate'] ?? 0);
        $discountPct = (float)($it['discount_percent'] ?? 0);
        $taxPct = (float)($it['tax_percent'] ?? 0);

        $lineGross = $qty * $rate;
        $lineDiscount = round($lineGross * $discountPct / 100, 2);
        $lineTaxable = round($lineGross - $lineDiscount, 2);
        if ($isGstEnabled) {
            $gst = calc_gst($lineTaxable, $taxPct, $interState);
        } else {
            $gst = ['cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0, 'total_tax' => 0.0];
        }
        $lineTotal = round($lineTaxable + $gst['total_tax'], 2);

        $subtotal += $lineGross;
        $totalDiscount += $lineDiscount;
        $taxableAmount += $lineTaxable;
        $cgstTotal += $gst['cgst'];
        $sgstTotal += $gst['sgst'];
        $igstTotal += $gst['igst'];

        $it['line_gross'] = round($lineGross, 2);
        $it['line_discount'] = $lineDiscount;
        $it['line_taxable'] = $lineTaxable;
        $it['line_cgst'] = $gst['cgst'];
        $it['line_sgst'] = $gst['sgst'];
        $it['line_igst'] = $gst['igst'];
        $it['line_total'] = $lineTotal;
        $computedItems[] = $it;
    }

    $grandTotalRaw = $taxableAmount + $cgstTotal + $sgstTotal + $igstTotal;
    $grandTotal = round($grandTotalRaw);
    $roundOff = round($grandTotal - $grandTotalRaw, 2);

    return [
        'items' => $computedItems,
        'subtotal' => round($subtotal, 2),
        'discount_total' => round($totalDiscount, 2),
        'taxable_amount' => round($taxableAmount, 2),
        'cgst' => round($cgstTotal, 2),
        'sgst' => round($sgstTotal, 2),
        'igst' => round($igstTotal, 2),
        'round_off' => $roundOff,
        'total' => $grandTotal,
    ];
}

function get_comparative_multipliers(array $config): array
{
    $p = (int)($config['prime_company_index'] ?? 1);
    $d12 = (float)($config['diff_prime_to_2'] ?? 5) / 100;
    $d23 = (float)($config['diff_2_to_3'] ?? 5) / 100;
    $d14 = (float)($config['diff_prime_to_4'] ?? 10) / 100;
    $d45 = (float)($config['diff_4_to_5'] ?? 5) / 100;

    $m = [1 => 1.0, 2 => 1.0, 3 => 1.0, 4 => 1.0, 5 => 1.0];

    if ($p === 1) {
        $m[1] = 1.0;
        $m[2] = 1.0 * (1 + $d12);
        $m[3] = $m[2] * (1 + $d23);
        $m[4] = 1.0 * (1 + $d14);
        $m[5] = $m[4] * (1 + $d45);
    } elseif ($p === 2) {
        $m[2] = 1.0;
        $m[1] = 1.0 * (1 + $d12);
        $m[3] = 1.0 * (1 + $d23);
        $m[4] = 1.0 * (1 + $d14);
        $m[5] = $m[4] * (1 + $d45);
    } elseif ($p === 3) {
        $m[3] = 1.0;
        $m[2] = 1.0 * (1 + $d23);
        $m[1] = $m[2] * (1 + $d12);
        $m[4] = 1.0 * (1 + $d14);
        $m[5] = $m[4] * (1 + $d45);
    } elseif ($p === 4) {
        $m[4] = 1.0;
        $m[5] = 1.0 * (1 + $d45);
        $m[1] = 1.0 * (1 + $d14);
        $m[2] = $m[1] * (1 + $d12);
        $m[3] = $m[2] * (1 + $d23);
    } elseif ($p === 5) {
        $m[5] = 1.0;
        $m[4] = 1.0 * (1 + $d45);
        $m[1] = $m[4] * (1 + $d14);
        $m[2] = $m[1] * (1 + $d12);
        $m[3] = $m[2] * (1 + $d23);
    }

    return $m;
}

/** Compute full line-item + summary totals for a quotation payload. */
function compute_quotation_totals(array $items, bool $interState, bool $isComparative = false, array $comparativeConfig = [], bool $isGstEnabled = true): array
{
    if (!$isComparative) {
        return calculate_group_totals($items, $interState, $isGstEnabled);
    }

    $numCompanies = (int)($comparativeConfig['num_companies'] ?? 3);
    $companies = $comparativeConfig['companies'] ?? ['Company 1', 'Company 2', 'Company 3'];
    $multipliers = get_comparative_multipliers($comparativeConfig);

    $options = [];
    foreach ($companies as $idx => $companyName) {
        $companyIdx = $idx + 1;
        $mult = $multipliers[$companyIdx] ?? 1.0;

        // Generate scaled items for this company
        $scaledItems = [];
        foreach ($items as $it) {
            $scaledIt = $it;
            $scaledIt['option_group'] = $companyName;
            $scaledIt['rate'] = round($it['rate'] * $mult, 2);
            $scaledItems[] = $scaledIt;
        }

        $options[$companyName] = calculate_group_totals($scaledItems, $interState, $isGstEnabled);
    }

    return [
        'is_comparative' => true,
        'options' => $options
    ];
}

function format_currency(float $amount, string $symbol = '₹'): string
{
    $amount = round($amount, 2);
    $isNegative = $amount < 0;
    $amountAbs = abs($amount);

    $parts = explode('.', sprintf('%0.2f', $amountAbs));
    $integer = $parts[0];
    $decimal = isset($parts[1]) ? '.' . $parts[1] : '.00';

    $len = strlen($integer);
    if ($len <= 3) {
        $formattedInteger = $integer;
    } else {
        $last3 = substr($integer, -3);
        $rest = substr($integer, 0, -3);
        
        $rest_formatted = '';
        while (strlen($rest) > 2) {
            $rest_formatted = ',' . substr($rest, -2) . $rest_formatted;
            $rest = substr($rest, 0, -2);
        }
        $rest_formatted = $rest . $rest_formatted;
        $formattedInteger = $rest_formatted . ',' . $last3;
    }

    return ($isNegative ? '-' : '') . $symbol . ' ' . $formattedInteger . $decimal;
}

/** Convert a rupee amount to words (Indian numbering system). */
function amount_in_words(float $amount): string
{
    $amount = round($amount);
    $rupees = (int)$amount;
    if ($rupees === 0) return 'Zero Rupees Only';

    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    $twoDigits = function ($n) use ($ones, $tens) {
        if ($n < 20) return $ones[$n];
        return trim($tens[intdiv($n, 10)] . ' ' . $ones[$n % 10]);
    };
    $threeDigits = function ($n) use ($twoDigits, $ones) {
        $s = '';
        if ($n >= 100) {
            $s .= $ones[intdiv($n, 100)] . ' Hundred ';
            $n %= 100;
        }
        $s .= $twoDigits($n);
        return trim($s);
    };

    $crore = intdiv($rupees, 10000000); $rupees %= 10000000;
    $lakh = intdiv($rupees, 100000); $rupees %= 100000;
    $thousand = intdiv($rupees, 1000); $rupees %= 1000;
    $hundred = $rupees;

    $out = [];
    if ($crore) $out[] = $threeDigits($crore) . ' Crore';
    if ($lakh) $out[] = $threeDigits($lakh) . ' Lakh';
    if ($thousand) $out[] = $threeDigits($thousand) . ' Thousand';
    if ($hundred) $out[] = $threeDigits($hundred);

    return trim(implode(' ', $out)) . ' Rupees Only';
}

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function status_badge_class(string $status): string
{
    return match ($status) {
        'accepted' => 'badge-success',
        'sent' => 'badge-info',
        'rejected' => 'badge-danger',
        'expired' => 'badge-muted',
        default => 'badge-warning',
    };
}

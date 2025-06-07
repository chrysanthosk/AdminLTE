<?php
// File: pages/zreport_print.php

require_once '../auth.php';
requirePermission($pdo, 'reports.view');

// HTML helper
if (!function_exists('h')) {
  function h($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  }
}

// 1) Validate and fetch the Z-report metadata
$reportId = (int)($_GET['report_id'] ?? 0);
if ($reportId <= 0) {
  die('Invalid Z Report ID');
}
$stmt = $pdo->prepare(
  "SELECT report_number, date_from, date_to, created_at
   FROM z_reports
   WHERE id = :rid
   LIMIT 1"
);
$stmt->execute(['rid' => $reportId]);
$zr = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$zr) {
  die("Z Report #{$reportId} not found.");
}
$reportNumber = $zr['report_number'];
$dateFrom     = $zr['date_from'];
$dateTo       = $zr['date_to'];
$createdAt    = $zr['created_at'];

// 2) Fetch company settings
$cfg = $pdo->query(
  "SELECT company_name, company_vat_number, company_phone_number, company_address
   FROM dashboard_settings
   LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

// 3) Compute totals for the period
$totStmt = $pdo->prepare(
  "SELECT
     COUNT(*) AS transactions_count,
     IFNULL(SUM(grand_total),0) AS total_transactions
   FROM sales
   WHERE DATE(sale_date) BETWEEN :from AND :to"
);
$totStmt->execute(['from' => $dateFrom, 'to' => $dateTo]);
$totals = $totStmt->fetch(PDO::FETCH_ASSOC);

// 4) Compute net and VAT subtotals
$subtotalsStmt = $pdo->prepare(
  "SELECT
     IFNULL(SUM(services_subtotal),0) AS services_net,
     IFNULL(SUM(services_vat),0)       AS services_vat,
     IFNULL(SUM(products_subtotal),0) AS products_net,
     IFNULL(SUM(products_vat),0)       AS products_vat
   FROM sales
   WHERE DATE(sale_date) BETWEEN :from AND :to"
);
$subtotalsStmt->execute(['from' => $dateFrom, 'to' => $dateTo]);
$subtotals = $subtotalsStmt->fetch(PDO::FETCH_ASSOC);

// Calculate VAT percentages
$servicesVatPct = $subtotals['services_net'] > 0
  ? ($subtotals['services_vat'] / $subtotals['services_net']) * 100
  : 0;
$productsVatPct = $subtotals['products_net'] > 0
  ? ($subtotals['products_vat'] / $subtotals['products_net']) * 100
  : 0;

// 5) Fetch payments breakdown
$payStmt = $pdo->prepare(
  "SELECT pm.name AS payment_method,
          IFNULL(SUM(sp.amount),0) AS amount
   FROM sale_payments sp
   JOIN sales s ON sp.sale_id = s.id
   JOIN payment_methods pm ON sp.payment_method = pm.name
   WHERE DATE(s.sale_date) BETWEEN :from AND :to
   GROUP BY pm.name"
);
$payStmt->execute(['from' => $dateFrom, 'to' => $dateTo]);
$payments = $payStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Z Report #<?= h($reportNumber) ?></title>
  <style>
    @page { size: 80mm auto; margin: 0; }
    body { width:80mm; margin:0; font-family:monospace; font-size:12px; line-height:1.2; }
    .center { text-align:center; }
    .line { margin:4px 0; border-bottom:1px dashed #000; }
    .section { margin:6px 0; }
    .bold { font-weight:bold; }
    .flex { display:flex; justify-content:space-between; }
    .no-print { display:none; }
  </style>
</head>
<body>
  <!-- Header -->
  <div class="center bold"><?= h($cfg['company_name']) ?></div>
  <div class="center"><?= h($cfg['company_address']) ?></div>
  <div class="center">Tel: <?= h($cfg['company_phone_number']) ?></div>
  <div class="center">VAT No: <?= h($cfg['company_vat_number']) ?></div>
  <div class="line"></div>

  <!-- Report Info -->
  <div class="center bold">Z-Report #<?= h($reportNumber) ?></div>
  <div class="center">Period: <?= date('d/m/Y', strtotime($dateFrom)) ?> — <?= date('d/m/Y', strtotime($dateTo)) ?></div>
  <div class="center">Generated: <?= date('d/m/Y h:i A', strtotime($createdAt)) ?></div>
  <div class="line"></div>

  <!-- Aggregated Totals -->
  <div class="section flex">
    <span>Transactions:</span>
    <span><?= (int)$totals['transactions_count'] ?></span>
  </div>
  <div class="section bold flex">
    <span>Total Amount:</span>
    <span>€ <?= number_format($totals['total_transactions'],2) ?></span>
  </div>
  <div class="line"></div>

  <!-- Services Breakdown -->
  <div class="section bold">Services</div>
  <div class="flex">
    <span>Net:</span>
    <span>€ <?= number_format($subtotals['services_net'],2) ?></span>
  </div>
  <div class="flex">
    <span>VAT <?= number_format($servicesVatPct,2) ?>%:</span>
    <span>€ <?= number_format($subtotals['services_vat'],2) ?></span>
  </div>
  <div class="line"></div>

  <!-- Products Breakdown -->
  <div class="section bold">Products</div>
  <div class="flex">
    <span>Net:</span>
    <span>€ <?= number_format($subtotals['products_net'],2) ?></span>
  </div>
  <div class="flex">
    <span>VAT <?= number_format($productsVatPct,2) ?>%:</span>
    <span>€ <?= number_format($subtotals['products_vat'],2) ?></span>
  </div>
  <div class="line"></div>

  <!-- Totals Summation -->
  <div class="section bold">Totals</div>
  <div class="flex">
    <span>Net Total:</span>
    <span>€ <?= number_format(
        $subtotals['services_net'] + $subtotals['products_net'], 2
      ) ?></span>
  </div>
  <div class="flex">
    <span>VAT Total:</span>
    <span>€ <?= number_format(
        $subtotals['services_vat'] + $subtotals['products_vat'], 2
      ) ?></span>
  </div>
  <div class="line"></div>

  <!-- Payments Received -->
  <div class="section bold">Total Received by Payment Method</div>
  <?php foreach ($payments as $p): ?>
    <div class="flex">
      <span><?= h($p['payment_method']) ?>:</span>
      <span>€ <?= number_format($p['amount'],2) ?></span>
    </div>
  <?php endforeach; ?>
  <div class="line"></div>

  <!-- Footer -->
  <div class="center bold">*** End of Z Report ***</div>

  <script>
    window.onload = function() { window.print(); };
  </script>
</body>
</html>
<?php
// File: pages/zreport_print.php

require_once '../auth.php';
requirePermission($pdo, 'reports.view');

$reportId = (int)($_GET['report_id'] ?? 0);
if ($reportId <= 0) {
  die("Invalid Z Report ID");
}

// 1) Fetch Z-report row
$stmt = $pdo->prepare("
  SELECT date_from, date_to
  FROM z_reports
  WHERE id = :rid
  LIMIT 1
");
$stmt->execute(['rid' => $reportId]);
$zr = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$zr) {
  die("Z Report #{$reportId} not found.");
}
$dateFrom = $zr['date_from'];
$dateTo   = $zr['date_to'];

// 2) Fetch all sales in that date range
$stmtSales = $pdo->prepare("
  SELECT
    s.id                                   AS sale_id,
    s.sale_date,
    COALESCE(
      CONCAT(c.first_name, ' ', c.last_name),
      'Walk-in'
    ) AS client_name,
    (
      SELECT GROUP_CONCAT(
        CONCAT(sv.name, ' (€', FORMAT(ss.unit_price,2), ')')
        SEPARATOR ', '
      )
      FROM sale_services ss
      JOIN services sv
        ON ss.service_id = sv.id
      WHERE ss.sale_id = s.id
    ) AS services_list,
    (
      SELECT GROUP_CONCAT(
        CONCAT(pv.name, ' (€', FORMAT(sp.unit_price,2), ')')
        SEPARATOR ', '
      )
      FROM sale_products sp
      JOIN products pv
        ON sp.product_id = pv.id
      WHERE sp.sale_id = s.id
    ) AS products_list,
    (s.services_subtotal + s.products_subtotal)   AS total_price,
    (
      SELECT IFNULL(SUM(amount), 0)
      FROM sale_payments spay
      WHERE spay.sale_id = s.id
    ) AS paid_amount,
    (
      SELECT GROUP_CONCAT(payment_method SEPARATOR ', ')
      FROM sale_payments spay
      WHERE spay.sale_id = s.id
    ) AS pay_methods
  FROM sales s
  LEFT JOIN clients c
    ON s.client_id = c.id
  WHERE DATE(s.sale_date) BETWEEN :date_from AND :date_to
  ORDER BY s.sale_date ASC
");
$stmtSales->execute([
  'date_from' => $dateFrom,
  'date_to'   => $dateTo
]);
$sales = $stmtSales->fetchAll(PDO::FETCH_ASSOC);

// 3) Compute totals in PHP
$totalSales    = 0.00;
$totalPayments = 0.00;
foreach ($sales as $row) {
  $totalSales    += (float)$row['total_price'];
  $totalPayments += (float)$row['paid_amount'];
}

// 4) Fetch company info from dashboard_settings
//    **Use the exact column names**. If your table has `company_telephone`, change accordingly.
$settings = $pdo->query("
  SELECT
    company_name,
    company_telephone
  FROM dashboard_settings
  LIMIT 1
")->fetch(PDO::FETCH_ASSOC);
$companyName       = $settings['company_name']      ?? '';
$companyTelephone  = $settings['company_telephone'] ?? '';

// 5) Output thermal-printer styling
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Z Report #<?= htmlspecialchars($reportId) ?></title>
  <style>
    body { font-family: Arial, sans-serif; font-size: 12px; margin: 5px; }
    h2, h3 { text-align: center; margin: 4px 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 5px; }
    th, td { padding: 2px 4px; }
    th { border-bottom: 1px dashed #000; text-align: left; }
    td { border-bottom: 1px solid #ddd; }
    .totals { font-weight: bold; }
  </style>
</head>
<body>
  <h2><?= htmlspecialchars($companyName) ?></h2>
  <h3>Tel: <?= htmlspecialchars($companyTelephone) ?></h3>
  <hr>
  <p><strong>Z Report #<?= $reportId ?></strong></p>
  <p>
    <strong>Period:</strong>
    <?= htmlspecialchars($dateFrom) ?>
    to
    <?= htmlspecialchars($dateTo) ?>
  </p>
  <hr>

  <table>
    <thead>
      <tr>
        <th>ID</th><th>Date &amp; Time</th><th>Client</th>
        <th>Serv.</th><th>Prod.</th>
        <th style="text-align:right;">Total (€)</th>
        <th style="text-align:right;">Paid (€)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($sales as $row): ?>
        <tr>
          <td><?= $row['sale_id'] ?></td>
          <td><?= date('Y-m-d H:i', strtotime($row['sale_date'])) ?></td>
          <td><?= htmlspecialchars($row['client_name']) ?></td>
          <td><?= $row['services_list'] ?: '-' ?></td>
          <td><?= $row['products_list'] ?: '-' ?></td>
          <td style="text-align:right;"><?= number_format($row['total_price'], 2) ?></td>
          <td style="text-align:right;"><?= number_format($row['paid_amount'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <hr>
  <p class="totals">Sum of Sales: € <?= number_format($totalSales, 2) ?></p>
  <p class="totals">Sum of Payments: € <?= number_format($totalPayments, 2) ?></p>

  <hr>
  <p>*** End of Z Report ***</p>
</body>
</html>
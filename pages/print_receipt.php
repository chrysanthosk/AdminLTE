<?php
// File: pages/print_receipt.php

require_once '../auth.php';
requirePermission($pdo, 'cashier.manage');

// 1) Validate & fetch sale_id
if (empty($_GET['sale_id']) || !is_numeric($_GET['sale_id'])) {
  echo "Invalid sale ID.";
  exit();
}
$sale_id = (int)$_GET['sale_id'];

// 2) Fetch sale header + appointment’s client + appointment’s service
$saleStmt = $pdo->prepare("
  SELECT
    s.sale_date,
    s.services_subtotal,
    s.services_vat,
    s.products_subtotal,
    s.products_vat,
    s.total_vat,
    s.grand_total,
    a.id         AS appt_id,
    a.start_time AS appt_time,
    CONCAT(ac.first_name,' ', ac.last_name) AS appt_client_name,
    ac.mobile    AS appt_client_mobile,
    sv.name       AS appt_service_name
  FROM sales s
  LEFT JOIN appointments a   ON s.appointment_id = a.id
  LEFT JOIN clients ac       ON a.client_id = ac.id
  LEFT JOIN services sv      ON a.service_id = sv.id
  WHERE s.id = :sale_id
");
$saleStmt->execute(['sale_id' => $sale_id]);
$sale = $saleStmt->fetch(PDO::FETCH_ASSOC);
if (!$sale) {
  echo "Sale not found.";
  exit();
}

// 3) Fetch service line‐items
$servicesStmt = $pdo->prepare("
  SELECT
    sv.name AS service_name,
    ss.quantity,
    ss.unit_price,
    ss.line_total
  FROM sale_services ss
  JOIN services sv ON ss.service_id = sv.id
  WHERE ss.sale_id = :sale_id
");
$servicesStmt->execute(['sale_id' => $sale_id]);
$services = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);

// 4) Fetch product line‐items
$productsStmt = $pdo->prepare("
  SELECT
    pv.name AS product_name,
    sp.quantity,
    sp.unit_price,
    sp.line_total
  FROM sale_products sp
  JOIN products pv ON sp.product_id = pv.id
  WHERE sp.sale_id = :sale_id
");
$productsStmt->execute(['sale_id' => $sale_id]);
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

// 5) Fetch payments
$paymentsStmt = $pdo->prepare("
  SELECT payment_date, payment_method, amount
  FROM sale_payments
  WHERE sale_id = :sale_id
");
$paymentsStmt->execute(['sale_id' => $sale_id]);
$payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

// 6) Fetch company info from dashboard_settings (id=1)
$companyStmt = $pdo->query("SELECT * FROM dashboard_settings WHERE id = 1");
$company = $companyStmt->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Receipt #<?= htmlspecialchars($sale_id) ?></title>
  <style>
    /* ───────────────────────────────────────────────────────────────── */
    /* Thermal‐printer‐friendly CSS (80mm receipt) */
    @page {
      size: 80mm auto;
      margin: 2mm 2mm;
    }
    body {
      width: 80mm;
      margin: 0;
      padding: 0;
      font-family: 'Courier New', monospace;
      font-size: 12px;
      line-height: 1.2;
      color: #000;
    }
    .header {
      text-align: center;
      margin-bottom: 4px;
    }
    .header h2 {
      margin: 0;
      font-size: 14px;
    }
    .header small {
      display: block;
      font-size: 10px;
      margin-top: 2px;
      line-height: 1.1;
    }
    .divider {
      border-top: 1px dashed #000;
      margin: 4px 0;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 4px;
    }
    th, td {
      text-align: left;
      padding: 2px 0;
    }
    th {
      border-bottom: 1px solid #000;
      font-weight: bold;
      font-size: 12px;
    }
    .totals, .payments {
      width: 100%;
      margin-top: 4px;
      margin-bottom: 4px;
    }
    .totals td, .payments td {
      padding: 2px 0;
    }
    .totals .label, .payments .label {
      text-align: left;
      font-size: 12px;
    }
    .totals .value, .payments .value {
      text-align: right;
      font-size: 12px;
    }
    .section-title {
      font-weight: bold;
      margin-top: 4px;
      margin-bottom: 2px;
      font-size: 12px;
    }
    .footer {
      margin-top: 6px;
      font-size: 10px;
      text-align: center;
    }
    @media print {
      body { margin: 0; }
      .no-print { display: none; }
    }
  </style>
</head>
<body>
  <div class="header">
    <h2><?= htmlspecialchars($company['company_name']) ?></h2>
    <small>
      <?= nl2br(htmlspecialchars($company['company_address'])) ?><br>
      Tel: <?= htmlspecialchars($company['company_phone_number']) ?><br>
      VAT No: <?= htmlspecialchars($company['company_vat_number']) ?>
    </small>
  </div>

  <div class="divider"></div>

  <div>
    <strong>Receipt #<?= htmlspecialchars($sale_id) ?></strong><br>
    <small>
      Date: <?= date('Y-m-d H:i:s', strtotime($sale['sale_date'])) ?>
      <?php if (!empty($sale['appt_id'])): ?>
        <br>
        Appointment: <?= date('H:i', strtotime($sale['appt_time'])) ?> –
        <?= htmlspecialchars($sale['appt_client_name']) ?>
        (<?= htmlspecialchars($sale['appt_service_name']) ?>)
        <br>
        <small>Mobile: <?= htmlspecialchars($sale['appt_client_mobile']) ?></small>
      <?php else: ?>
        <br>
        Client: <?= htmlspecialchars($sale['appt_client_name'] ?? 'Walk-in') ?>
        <?= !empty($sale['appt_client_mobile']) ? " [" . htmlspecialchars($sale['appt_client_mobile']) . "]" : '' ?>
      <?php endif; ?>
    </small>
  </div>

  <div class="divider"></div>

  <!-- Services Section -->
  <?php if (count($services) > 0): ?>
    <div class="section-title">SERVICES</div>
    <table>
      <thead>
        <tr>
          <th>QTY</th>
          <th>DESCRIPTION</th>
          <th style="text-align:right;">TOTAL (€)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($services as $s): ?>
          <tr>
            <td><?= (int)$s['quantity'] ?></td>
            <td>
              <?= htmlspecialchars($s['service_name']) ?>
              <span style="font-size:10px;">(€<?= number_format($s['unit_price'],2) ?>)</span>
            </td>
            <td style="text-align:right;"><?= number_format($s['line_total'],2) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <table class="totals">
      <tr>
        <td class="label">Services Subtotal:</td>
        <td class="value"><?= number_format($sale['services_subtotal'],2) ?></td>
      </tr>
      <tr>
        <td class="label">Services VAT:</td>
        <td class="value"><?= number_format($sale['services_vat'],2) ?></td>
      </tr>
    </table>
  <?php endif; ?>

  <!-- Products Section -->
  <?php if (count($products) > 0): ?>
    <div class="section-title">PRODUCTS</div>
    <table>
      <thead>
        <tr>
          <th>QTY</th>
          <th>DESCRIPTION</th>
          <th style="text-align:right;">TOTAL (€)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p): ?>
          <tr>
            <td><?= (int)$p['quantity'] ?></td>
            <td>
              <?= htmlspecialchars($p['product_name']) ?>
              <span style="font-size:10px;">(€<?= number_format($p['unit_price'],2) ?>)</span>
            </td>
            <td style="text-align:right;"><?= number_format($p['line_total'],2) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <table class="totals">
      <tr>
        <td class="label">Products Subtotal:</td>
        <td class="value"><?= number_format($sale['products_subtotal'],2) ?></td>
      </tr>
      <tr>
        <td class="label">Products VAT:</td>
        <td class="value"><?= number_format($sale['products_vat'],2) ?></td>
      </tr>
    </table>
  <?php endif; ?>

  <!-- Grand Totals -->
  <div class="section-title">TOTALS</div>
  <table class="totals">
    <tr>
      <td class="label">Total VAT:</td>
      <td class="value"><?= number_format($sale['total_vat'],2) ?></td>
    </tr>
    <tr>
      <td class="label">Grand Total:</td>
      <td class="value"><?= number_format($sale['grand_total'],2) ?></td>
    </tr>
  </table>

  <!-- Payments Section -->
  <?php if (count($payments) > 0): ?>
    <div class="section-title">PAYMENTS</div>
    <table class="payments">
      <thead>
        <tr>
          <th>Date</th>
          <th>Method</th>
          <th style="text-align:right;">Amount (€)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payments as $pay): ?>
          <tr>
            <td><?= date('Y-m-d H:i', strtotime($pay['payment_date'])) ?></td>
            <td><?= htmlspecialchars($pay['payment_method']) ?></td>
            <td style="text-align:right;"><?= number_format($pay['amount'],2) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <div class="divider"></div>

  <div class="footer">
    <?= htmlspecialchars($company['company_name']) ?> |
    Tel: <?= htmlspecialchars($company['company_phone_number']) ?>
  </div>

  <script>
    // Open print dialog on load
    window.onload = function() {
      window.print();
    };
  </script>
</body>
</html>
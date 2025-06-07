<?php
// File: pages/cashier.php

require_once '../auth.php';
requirePermission($pdo, 'cashier.manage');

// ─── Fetch all payment methods for dynamic dropdowns ───────────────────────
$pmStmt = $pdo->query("SELECT id, name FROM payment_methods ORDER BY name ASC");
$allPaymentMethods = $pmStmt->fetchAll(PDO::FETCH_ASSOC);

// ────────────────────────────────────────────────────────────────────────────────
// (A) Handle “Delete” action via GET (delete a sale and its line‐items)
// ────────────────────────────────────────────────────────────────────────────────
if (
  isset($_GET['action']) && $_GET['action'] === 'delete'
  && isset($_GET['id']) && is_numeric($_GET['id'])
) {
  $delId = (int)$_GET['id'];

  // Delete payments
  $pdo->prepare("DELETE FROM sale_payments WHERE sale_id = ?")
      ->execute([$delId]);

  // Delete products
  $pdo->prepare("DELETE FROM sale_products WHERE sale_id = ?")
      ->execute([$delId]);

  // Delete services
  $pdo->prepare("DELETE FROM sale_services WHERE sale_id = ?")
      ->execute([$delId]);

  // Delete the sale header
  $pdo->prepare("DELETE FROM sales WHERE id = ?")
      ->execute([$delId]);

  header("Location: cashier.php?deleted=1");
  exit();
}


// ────────────────────────────────────────────────────────────────────────────────
// (B) Pagination / Searching of existing sales in the lower table
// ────────────────────────────────────────────────────────────────────────────────
$search_client = trim($_GET['search'] ?? '');

// Acceptable numeric limits; “all” = no pagination.
$limit_options = [50, 100, 200, 300];
$limit_raw = $_GET['limit'] ?? '50';
if ($limit_raw === 'all') {
  $limit = 0;
} elseif (in_array((int)$limit_raw, $limit_options, true)) {
  $limit = (int)$limit_raw;
} else {
  $limit = 50;
}
$page = max(1, (int)($_GET['page'] ?? 1));

// Build WHERE clause on search by client name (search both sale.client via clients table or appointment.client)
$where_clauses = [];
$params = [];
if ($search_client !== '') {
  $where_clauses[] = "(
    CONCAT(c.first_name,' ',c.last_name) LIKE :search_client
    OR CONCAT(ac.first_name,' ',ac.last_name) LIKE :search_client
  )";
  $params['search_client'] = "%{$search_client}%";
}
$where_sql = $where_clauses
    ? "WHERE " . implode(" AND ", $where_clauses)
    : "";

$limit_sql = $limit > 0
    ? "LIMIT " . (($page - 1) * $limit) . ", $limit"
    : "";


// ─── Query existing sales ─────────────────────────────────────────────────────
// Note: sales table does NOT have a “client_name” column. We display:
//  1) joined clients (sale.client_id → clients),
//  2) joined appointment.client_id → clients,
//  3) else “Walk-in”.
$dataSql = "
  SELECT
    s.id AS sale_id,
    s.sale_date,
    COALESCE(
      CONCAT(c.first_name,' ',c.last_name),
      CONCAT(ac.first_name,' ',ac.last_name),
      'Walk-in'
    ) AS client_name,
    (
      SELECT IFNULL(
        GROUP_CONCAT(
          CONCAT(si.quantity,'× ',sv.name,' (€', FORMAT(si.unit_price,2), ')')
          SEPARATOR '<br>'
        ),
        '<span class=\"text-muted\">—</span>'
      )
      FROM sale_services si
      JOIN services sv ON si.service_id = sv.id
      WHERE si.sale_id = s.id
    ) AS services_list,
    (
      SELECT IFNULL(
        GROUP_CONCAT(
          CONCAT(pi.quantity,'× ',pv.name,' (€', FORMAT(pi.unit_price,2), ')')
          SEPARATOR '<br>'
        ),
        '<span class=\"text-muted\">—</span>'
      )
      FROM sale_products pi
      JOIN products pv ON pi.product_id = pv.id
      WHERE pi.sale_id = s.id
    ) AS products_list,
    s.grand_total,
    (
      SELECT IFNULL(
        GROUP_CONCAT(
          CONCAT(sp.payment_method, ' – €', FORMAT(sp.amount,2))
          SEPARATOR '<br>'
        ),
        '<span class=\"text-muted\">—</span>'
      )
      FROM sale_payments sp
      WHERE sp.sale_id = s.id
    ) AS payments_list
  FROM sales s
  LEFT JOIN clients c       ON s.client_id = c.id
  LEFT JOIN appointments a  ON s.appointment_id = a.id
  LEFT JOIN clients ac      ON a.client_id = ac.id
  {$where_sql}
  ORDER BY s.sale_date DESC
  {$limit_sql}
";
$stmtData = $pdo->prepare($dataSql);
$stmtData->execute($params);
$salesData = $stmtData->fetchAll(PDO::FETCH_ASSOC);

// Count total rows for pagination
$countSql = "
  SELECT COUNT(*) AS total
  FROM sales s
  LEFT JOIN clients c       ON s.client_id = c.id
  LEFT JOIN appointments a  ON s.appointment_id = a.id
  LEFT JOIN clients ac      ON a.client_id = ac.id
  {$where_sql}
";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$total_rows = (int)$stmtCount->fetchColumn();
$total_pages = $limit > 0 ? ceil($total_rows / $limit) : 1;


// ─── (D1) “Today’s Appointments” for the dropdown ───────────────────────────
$stmtA = $pdo->prepare("
  SELECT
    a.id,
    a.start_time,
    COALESCE(
      CONCAT(c.first_name, ' ', c.last_name),
      a.client_name,
      'Unknown'
    ) AS client_label
  FROM appointments a
  LEFT JOIN clients c
    ON a.client_id = c.id
  WHERE DATE(a.appointment_date) = CURDATE()
  ORDER BY a.start_time ASC
");
$stmtA->execute();
$todayAppointments = $stmtA->fetchAll(PDO::FETCH_ASSOC);


// ─── (D2) All Products (for JS dropdown) ────────────────────────────────────
$productStmt = $pdo->query("
  SELECT id, name, sell_price AS price
    FROM products
   ORDER BY name
");
$allProducts = $productStmt->fetchAll(PDO::FETCH_ASSOC);


// ─── (D3) All Therapists (for JS dropdown) ──────────────────────────────────
$therapistStmt = $pdo->query("
  SELECT id, first_name, last_name
    FROM therapists
   ORDER BY first_name, last_name
");
$allTherapists = $therapistStmt->fetchAll(PDO::FETCH_ASSOC);


// ─── Fetch all services for the “Add Service” dropdown ─────────────────────────
$serviceStmt = $pdo->query("
  SELECT id, name, price
    FROM services
   ORDER BY name
");
$allServices = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);


// ─── (C) HANDLE FORM SUBMISSION ───────────────────────────────────────────────
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_sale'])) {
  // 1) Sale header fields
  $sale_date_raw       = trim($_POST['sale_date'] ?? '');
  $sale_date           = $sale_date_raw
                         ? date('Y-m-d H:i:s', strtotime($sale_date_raw))
                         : date('Y-m-d H:i:s');
  $sale_type           = $_POST['sale_type'] ?? 'product';

  // Determine client based on sale_type
  $appointment_id      = null;
  $client_id           = null;
  $client_name         = null;
  if ($sale_type === 'appointment') {
    $appointment_id = !empty(trim($_POST['appointment_id'] ?? ''))
                      ? (int)$_POST['appointment_id']
                      : null;
    if ($appointment_id) {
      $stmt = $pdo->prepare("
        SELECT client_id, client_name
          FROM appointments
         WHERE id = :aid
         LIMIT 1
      ");
      $stmt->execute(['aid' => $appointment_id]);
      $ap = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($ap) {
        $client_id   = $ap['client_id'] ?: null;
        $client_name = $client_id ? null : $ap['client_name'];
      }
    }
  } else {
    // Product Sale: either an existing client from dropdown or manual entry
    $client_id   = !empty($_POST['existingClient'])
                   ? intval($_POST['existingClient'])
                   : null;
    $client_name = $client_id
                   ? null
                   : trim($_POST['newClientName'] ?? '');
    if (!$client_id && !empty(trim($_POST['client_manual'] ?? ''))) {
      $client_name = trim($_POST['client_manual']);
    }
  }

  // 2) Services Subtotal & VAT calculation
  $services_subtotal = 0.00;
  $services_vat      = 0.00;
  if (!empty($_POST['service_row'])) {
    foreach ($_POST['service_row'] as $idx => $row) {
      $service_id   = (int)$row['service_id'];
      $therapist_id = (int)$row['therapist_id'];
      $qty          = max(1, (int)$row['qty']);
      $unit_price   = (float)$row['unit_price']; // price includes VAT
      $line_total   = round($unit_price * $qty, 2);
      $services_subtotal += $line_total;
    }
    // 19% VAT assumed
    $services_vat = round($services_subtotal * 0.19, 2);
  }

  // 3) Products Subtotal & VAT calculation
  $products_subtotal = 0.00;
  $products_vat      = 0.00;
  if (!empty($_POST['product_row'])) {
    foreach ($_POST['product_row'] as $idx => $row) {
      $product_id   = (int)$row['product_id'];
      $therapist_id = (int)$row['therapist_id'];
      $qty          = max(1, (int)$row['qty']);
      $unit_price   = (float)$row['unit_price']; // price includes VAT
      $line_total   = round($unit_price * $qty, 2);
      $products_subtotal += $line_total;
    }
    // 19% VAT assumed
    $products_vat = round($products_subtotal * 0.19, 2);
  }

  // 4) Total VAT & Grand Total
  $total_vat   = round($services_vat + $products_vat, 2);
  $grand_total = round($services_subtotal + $products_subtotal, 2);

  // 5) Insert into `sales`
  $insSale = $pdo->prepare("
    INSERT INTO sales
      (sale_date, appointment_id, client_id,
       services_subtotal, services_vat,
       products_subtotal, products_vat,
       total_vat, grand_total)
    VALUES
      (:sale_date, :appointment_id, :client_id,
       :services_subtotal, :services_vat,
       :products_subtotal, :products_vat,
       :total_vat, :grand_total)
  ");
  $insSale->execute([
    'sale_date'           => $sale_date,
    'appointment_id'      => $appointment_id,
    'client_id'           => $client_id,
    'services_subtotal'   => $services_subtotal,
    'services_vat'        => $services_vat,
    'products_subtotal'   => $products_subtotal,
    'products_vat'        => $products_vat,
    'total_vat'           => $total_vat,
    'grand_total'         => $grand_total
  ]);
  $just_saved_sale_id = $pdo->lastInsertId();

  // 6) Insert each service line
  if (!empty($_POST['service_row'])) {
    $insServiceLine = $pdo->prepare("
      INSERT INTO sale_services
        (sale_id, service_id, therapist_id, quantity, unit_price, line_total)
      VALUES
        (:sale_id, :service_id, :therapist_id, :quantity, :unit_price, :line_total)
    ");
    foreach ($_POST['service_row'] as $idx => $row) {
      $service_id   = (int)$row['service_id'];
      $therapist_id = (int)$row['therapist_id'];
      $qty          = max(1, (int)$row['qty']);
      $unit_price   = (float)$row['unit_price'];
      $line_total   = round($unit_price * $qty, 2);
      $insServiceLine->execute([
        'sale_id'      => $just_saved_sale_id,
        'service_id'   => $service_id,
        'therapist_id' => $therapist_id,
        'quantity'     => $qty,
        'unit_price'   => $unit_price,
        'line_total'   => $line_total
      ]);
    }
  }

  // 7) Insert each product line
  if (!empty($_POST['product_row'])) {
    $insProductLine = $pdo->prepare("
      INSERT INTO sale_products
        (sale_id, product_id, therapist_id, quantity, unit_price, line_total)
      VALUES
        (:sale_id, :product_id, :therapist_id, :quantity, :unit_price, :line_total)
    ");
    foreach ($_POST['product_row'] as $idx => $row) {
      $product_id   = (int)$row['product_id'];
      $therapist_id = (int)$row['therapist_id'];
      $qty          = max(1, (int)$row['qty']);
      $unit_price   = (float)$row['unit_price'];
      $line_total   = round($unit_price * $qty, 2);
      $insProductLine->execute([
        'sale_id'      => $just_saved_sale_id,
        'product_id'   => $product_id,
        'therapist_id' => $therapist_id,
        'quantity'     => $qty,
        'unit_price'   => $unit_price,
        'line_total'   => $line_total
      ]);
    }
  }

  // 8) Insert each payment row
  if (!empty($_POST['payment_row'])) {
    $insPayment = $pdo->prepare("
      INSERT INTO sale_payments
        (sale_id, amount, payment_date, payment_method)
      VALUES
        (:sale_id, :amount, :payment_date, :payment_method)
    ");
    foreach ($_POST['payment_row'] as $idx => $row) {
      $amount        = (float)$row['amount'];
      $payment_date  = $row['payment_date'];
      $payment_method= $row['payment_method'];
      $insPayment->execute([
        'sale_id'        => $just_saved_sale_id,
        'amount'         => $amount,
        'payment_date'   => $payment_date,
        'payment_method' => $payment_method
      ]);
    }
  }

  // 9) Redirect back so pagination and search stay intact
  header("Location: cashier.php?success=1&print_id=" . $just_saved_sale_id);

  exit();
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <!-- Page Header -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Cashier</h1>
        </div>
        <div class="col-sm-6 text-right">
          <!-- (E) “New Sale” Button triggers the add‐sale form below -->
          <button
            class="btn btn-success"
            data-toggle="collapse"
            data-target="#collapseAddSale"
            aria-expanded="<?= isset($_GET['action']) && $_GET['action']==='add' ? 'true' : 'false' ?>"
            aria-controls="collapseAddSale"
          >
            <i class="fas fa-plus"></i> New Sale
          </button>
        </div>
      </div>
    </div>
  </section>

  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">
      <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-warning">Sale deleted successfully!</div>
      <?php endif; ?>
      <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">Sale saved successfully!</div>
      <?php endif; ?>

      <!-- ─── (E) Add‐Sale Collapse Panel ────────────────────────────────────────── -->
      <div id="collapseAddSale" class="collapse <?= isset($_GET['action']) && $_GET['action']==='add' ? 'show' : '' ?>">
        <div class="card card-info">
          <div class="card-header">
            <h3 class="card-title">Add New Sale</h3>
            <button
              type="button"
              class="close"
              id="btnCloseAddSale"
              data-toggle="collapse"
              data-target="#collapseAddSale"
              aria-label="Close"
            >
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="card-body">
            <form id="addSaleForm" method="POST" action="cashier.php">
              <!-- ─── Sale Type: Product vs Appointment ─────────────────────────────── -->
              <div class="form-group">
                <label class="mr-3">
                  <input type="radio" name="sale_type" id="sale_type_product" value="product" checked>
                  Product Sale
                </label>
                <label>
                  <input type="radio" name="sale_type" id="sale_type_appointment" value="appointment">
                  Appointment Sale
                </label>
              </div>

              <!-- Appointment dropdown (hidden by default) -->
              <div class="form-group" id="appointmentSection" style="display:none;">
                <label for="appointment_id_top">Link to Today’s Appointment (optional)</label>
                <select
                  id="appointment_id_top"
                  name="appointment_id"
                  class="form-control select2"
                  style="width: 100%;"
                >
                  <option value="">-- No Appointment --</option>
                  <?php foreach ($todayAppointments as $a): ?>
                    <option value="<?= htmlspecialchars($a['id'], ENT_QUOTES, 'UTF-8') ?>">
                      <?= htmlspecialchars(substr($a['start_time'], 0, 5) . ' – ' . $a['client_label'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- Hidden fields to store client info from appointment -->
              <input type="hidden" name="client_id" id="client_id" value="">
              <input type="hidden" name="client_name" id="client_name" value="">

              <!-- ─── Section 1: Date & Appointment (existing form) ──────────────────── -->
              <div class="row">
                <div class="form-group col-md-4">
                  <label for="sale_date">Sale Date</label>
                  <input
                    type="datetime-local"
                    class="form-control"
                    id="sale_date"
                    name="sale_date"
                    value="<?= date('Y-m-d\TH:i') ?>"
                    required
                  >
                </div>
                <div class="form-group col-md-8" style="display:none;">
                  <label for="appointment_id">Link to Today’s Appointment (optional)</label>
                  <select
                    id="appointment_id"
                    name="appointment_id"
                    class="form-control select2"
                    style="width: 100%;"
                  >
                    <option value="">-- No Appointment --</option>
                    <?php foreach ($todayAppointments as $a): ?>
                      <option value="<?= htmlspecialchars($a['id'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($a['start_time'] . ' – ' . $a['client_label'], ENT_QUOTES, 'UTF-8') ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <!-- ─── Section 1 End ──────────────────────────────────────────────────── -->

              <!-- ─── Section 2: Services ─────────────────────────────────────────────── -->
              <div class="card card-info">
                <div class="card-header">
                  <h3 class="card-title">Services</h3>
                  <button type="button" class="btn btn-sm btn-light ml-auto" id="btnAddService">
                    <i class="fas fa-plus"></i> Add Service
                  </button>
                </div>
                <div class="card-body">
                  <table class="table table-bordered" id="servicesTable">
                    <thead>
                      <tr>
                        <th style="width: 30%;">Service</th>
                        <th style="width: 30%;">Therapist</th>
                        <th style="width: 10%;">Qty</th>
                        <th style="width: 15%;">Unit Price (€)</th>
                        <th style="width: 15%;">Line Total (€)</th>
                        <th style="width: 5%;">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <!-- Rows are added dynamically by JS -->
                    </tbody>
                  </table>
                </div>
              </div>
              <br>
              <!-- ─── Section 2 End ──────────────────────────────────────────────────── -->

              <!-- ─── Section 3: Products ─────────────────────────────────────────────── -->
              <div class="card card-info">
                <div class="card-header">
                  <h3 class="card-title">Products</h3>
                  <button type="button" class="btn btn-sm btn-light ml-auto" id="btnAddProduct">
                    <i class="fas fa-plus"></i> Add Product
                  </button>
                </div>
                <div class="card-body">
                  <table class="table table-bordered" id="productsTable">
                    <thead>
                      <tr>
                        <th style="width: 30%;">Product</th>
                        <th style="width: 30%;">Therapist</th>
                        <th style="width: 10%;">Qty</th>
                        <th style="width: 15%;">Unit Price (€)</th>
                        <th style="width: 15%;">Line Total (€)</th>
                        <th style="width: 5%;">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <!-- Rows added dynamically -->
                    </tbody>
                  </table>
                </div>
              </div>
              <br>
              <!-- ─── Section 3 End ──────────────────────────────────────────────────── -->

              <!-- ─── Section 4: Totals ───────────────────────────────────────────────── -->
              <div class="card card-outline card-secondary">
                <div class="card-header">
                  <h3 class="card-title">Totals (19% VAT assumed)</h3>
                </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-4">
                      <p><strong>Services Subtotal (€):</strong> <span id="txtServicesSubtotal">0.00</span></p>
                      <p><strong>Services VAT (€):</strong> <span id="txtServicesVAT">0.00</span></p>
                    </div>
                    <div class="col-md-4">
                      <p><strong>Products Subtotal (€):</strong> <span id="txtProductsSubtotal">0.00</span></p>
                      <p><strong>Products VAT (€):</strong> <span id="txtProductsVAT">0.00</span></p>
                    </div>
                    <div class="col-md-4">
                      <p><strong>Total VAT (€):</strong> <span id="txtTotalVAT">0.00</span></p>
                      <p><strong>Grand Total (€):</strong> <span id="txtGrandTotal">0.00</span></p>
                    </div>
                  </div>
                </div>
              </div>
              <br>
              <!-- ─── Section 4 End ──────────────────────────────────────────────────── -->

              <!-- ─── Section 5: Payments ─────────────────────────────────────────────── -->
              <div class="card card-outline card-secondary">
                <div class="card-header">
                  <h3 class="card-title">Payments</h3>
                  <button type="button" class="btn btn-sm btn-light ml-auto" id="btnAddPayment">
                    <i class="fas fa-plus"></i> Add Payment
                  </button>
                </div>
                <div class="card-body">
                  <table class="table table-bordered" id="paymentsTable">
                    <thead>
                      <tr>
                        <th style="width: 25%;">Payment Date</th>
                        <th style="width: 25%;">Payment Method</th>
                        <th style="width: 25%;">Amount (€)</th>
                        <th style="width: 25%;">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <!-- Rows added dynamically -->
                    </tbody>
                  </table>
                </div>
              </div>
              <br>
              <!-- ─── Section 5 End ──────────────────────────────────────────────────── -->

              <!-- ─── Section 6: Submit ───────────────────────────────────────────────── -->
              <div class="text-center">
                <button type="submit" name="save_sale" class="btn btn-primary btn-lg">
                  <i class="fas fa-save"></i> Save Sale
                </button>
              </div>
              <!-- ─── Section 6 End ──────────────────────────────────────────────────── -->
            </form>
          </div>
        </div>
      </div>
      <!-- ─── End Add‐Sale Collapse Panel ────────────────────────────────────────── -->


      <!-- ─── (F) Search & Pagination ───────────────────────────────────────────── -->
      <div class="row mb-3">
        <div class="col-md-8">
          <form method="GET" action="cashier.php" class="form-inline">
            <div class="input-group">
              <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search by client name…"
                value="<?= htmlspecialchars($search_client, ENT_QUOTES, 'UTF-8') ?>"
              >
              <div class="input-group-append">
                <button class="btn btn-secondary" type="submit">
                  <i class="fas fa-search"></i>
                </button>
              </div>
            </div>
            <input type="hidden" name="limit" value="<?= $limit ?>">
          </form>
        </div>
        <div class="col-md-4 text-right">
          <nav aria-label="Page navigation">
            <ul class="pagination justify-content-end mb-0">
              <?php for ($p=1; $p <= $total_pages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                  <a class="page-link" href="cashier.php?limit=<?= $limit ?>&search=<?= urlencode($search_client) ?>&page=<?= $p ?>">
                    <?= $p ?>
                  </a>
                </li>
              <?php endfor; ?>
            </ul>
          </nav>
        </div>
      </div>
      <!-- ─── End Search & Pagination ───────────────────────────────────────────── -->


      <!-- ─── (G) Existing Sales Table ────────────────────────────────────────────── -->
      <table class="table table-striped table-bordered">
        <thead>
          <tr>
            <th>ID</th>
            <th>Date</th>
            <th>Client</th>
            <th>Services</th>
            <th>Products</th>
            <th>Total (€)</th>
            <th>Payments</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($salesData as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['sale_id'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($row['sale_date'])), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($row['client_name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= $row['services_list'] ?></td>
              <td><?= $row['products_list'] ?></td>
              <td>€ <?= number_format($row['grand_total'],2) ?></td>
              <td><?= $row['payments_list'] ?></td>
              <td>
                <!-- Print Icon: reprint receipt -->
                <button
                  class="btn btn-sm btn-secondary"
                  onclick="window.open(
                    'print_receipt.php?sale_id=<?= $row['sale_id'] ?>',
                    '_blank',
                    'toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=320,height=600'
                  ); return false;"
                  title="Re-print Receipt"
                >
                  <i class="fas fa-print"></i>
                </button>

                <!-- Delete Icon -->
                <a
                  href="cashier.php?action=delete&id=<?= $row['sale_id'] ?>"
                  class="btn btn-sm btn-danger"
                  onclick="return confirm('Are you sure you want to delete sale #<?= $row['sale_id'] ?>?');"
                  title="Delete Sale"
                >
                  <i class="fas fa-trash"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <!-- ─── End Existing Sales Table ────────────────────────────────────────── -->

    </div>
  </section>
  <!-- /.content -->
</div>
<!-- /.content-wrapper -->


<?php include '../includes/footer.php'; ?>

<!-- ─────────────────────────────────────────────────────────────────────────────────── -->
<!-- (H) Required JS: jQuery, Bootstrap, Select2 ─────────────────────────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>

<script>
  $(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({ width: '100%' });

    // Safety‐net: if “×” on the collapse header is clicked, hide the panel
    $('#collapseAddSale .close').on('click', function(){
      $('#collapseAddSale').collapse('hide');
    });

    // Force-hide panel when you click the X
    $('#btnCloseAddSale').on('click', function(){
      $('#collapseAddSale').collapse('hide');
    });

    // (A) Toggle the appointment dropdown when sale_type changes
    $('input[name="sale_type"]').on('change', function(){
      if ($('#sale_type_appointment').is(':checked')) {
        $('#appointmentSection').show();
      } else {
        $('#appointmentSection').hide();
        $('#client_id').val('');
        $('#client_name').val('');
      }
    });

    // (B) When an appointment is selected, copy its client data into hidden fields
    $('#appointment_id_top').on('change', function(){
      var apptId = $(this).val();
      if (!apptId) {
        $('#client_id').val('');
        $('#client_name').val('');
        return;
      }
      <?php
        $map = [];
        foreach ($todayAppointments as $a) {
          $stmt2 = $pdo->prepare("
            SELECT client_id, client_name
              FROM appointments
             WHERE id = :aid
             LIMIT 1
          ");
          $stmt2->execute(['aid' => $a['id']]);
          $tmp = $stmt2->fetch(PDO::FETCH_ASSOC);
          $map[$a['id']] = [
            'client_id'   => $tmp['client_id']   ?: '',
            'client_name' => $tmp['client_id']
                             ? ''
                             : $tmp['client_name']
          ];
        }
      ?>
      var apptMap = <?= json_encode($map, JSON_UNESCAPED_UNICODE) ?>;
      var info    = apptMap[apptId] || {client_id:'', client_name:''};
      $('#client_id').val(info.client_id);
      $('#client_name').val(info.client_name);
    });

    // (C) Dynamically Add/Remove Service Rows with editable Unit Price
    let serviceCount = 0;
    $('#btnAddService').click(function(){
      serviceCount++;
      const rowId = `serviceRow${serviceCount}`;
      let $tr = $(`
        <tr id="${rowId}">
          <td>
            <select
              name="service_row[${serviceCount}][service_id]"
              class="form-control service-select"
              required
            >
              <option value="">-- Select Service --</option>
            </select>
          </td>
          <td>
            <select
              name="service_row[${serviceCount}][therapist_id]"
              class="form-control therapist-select"
              required
            >
              <option value="">-- Therapist --</option>
            </select>
          </td>
          <td><input
                type="number"
                name="service_row[${serviceCount}][qty]"
                class="form-control qty-input"
                value="1"
                min="1"
                required
              ></td>
          <td><input
                type="text"
                name="service_row[${serviceCount}][unit_price]"
                class="form-control unit-price"
                placeholder="0.00"
                required
              ></td>
          <td><input
                type="text"
                name="service_row[${serviceCount}][line_total]"
                class="form-control line-total"
                readonly
                placeholder="0.00"
              ></td>
          <td class="text-center">
            <button type="button" class="btn btn-sm btn-danger btnRemoveService">
              <i class="fas fa-trash"></i>
            </button>
          </td>
        </tr>
      `);
      // Populate Service dropdown
      <?php foreach($allServices as $s): ?>
        $tr.find('.service-select')
           .append(`<option value="<?= $s['id'] ?>" data-price="<?= $s['price'] ?>"><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></option>`);
      <?php endforeach; ?>
      // Populate Therapist dropdown
      <?php foreach($allTherapists as $t): ?>
        $tr.find('.therapist-select')
           .append(`<option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name'], ENT_QUOTES, 'UTF-8') ?></option>`);
      <?php endforeach; ?>

      $('#servicesTable tbody').append($tr);

      // (C1) When a service is selected, auto‐populate unit-price & line total
      $tr.find('.service-select').on('change', function() {
        const price = parseFloat($(this).find(':selected').data('price') || 0);
        $tr.find('.unit-price').val(price.toFixed(2));
        const qty = parseInt($tr.find('.qty-input').val() || 1);
        $tr.find('.line-total').val((price * qty).toFixed(2));
        recalcTotals();
      });

      // (C2) When “Qty” changes → recalc line total
      $tr.find('.qty-input').on('input', function() {
        const price = parseFloat($tr.find('.unit-price').val() || 0);
        const qty = Math.max(1, parseInt($(this).val() || 1));
        $tr.find('.line-total').val((price * qty).toFixed(2));
        recalcTotals();
      });

      // (C3) When “Unit Price” changes manually → recalc line total
      $tr.find('.unit-price').on('input', function() {
        const price = parseFloat($(this).val() || 0);
        const qty = parseInt($tr.find('.qty-input').val() || 1);
        $tr.find('.line-total').val((price * qty).toFixed(2));
        recalcTotals();
      });

      // (C4) Remove button
      $tr.find('.btnRemoveService').click(function() {
        $tr.remove();
        recalcTotals();
      });
    });


    // (D) Dynamically Add/Remove Product Rows with editable Unit Price
    let productCount = 0;
    $('#btnAddProduct').click(function(){
      productCount++;
      const rowId = `productRow${productCount}`;
      let $tr = $(`
        <tr id="${rowId}">
          <td>
            <select
              name="product_row[${productCount}][product_id]"
              class="form-control product-select"
              required
            >
              <option value="">-- Select Product --</option>
            </select>
          </td>
          <td>
            <select
              name="product_row[${productCount}][therapist_id]"
              class="form-control therapist-select"
              required
            >
              <option value="">-- Therapist --</option>
            </select>
          </td>
          <td><input
                type="number"
                name="product_row[${productCount}][qty]"
                class="form-control qty-input"
                value="1"
                min="1"
                required
              ></td>
          <td><input
                type="text"
                name="product_row[${productCount}][unit_price]"
                class="form-control unit-price"
                placeholder="0.00"
                required
              ></td>
          <td><input
                type="text"
                name="product_row[${productCount}][line_total]"
                class="form-control line-total"
                readonly
                placeholder="0.00"
              ></td>
          <td class="text-center">
            <button type="button" class="btn btn-sm btn-danger btnRemoveProduct">
              <i class="fas fa-trash"></i>
            </button>
          </td>
        </tr>
      `);
      // Populate Product dropdown
      <?php foreach($allProducts as $p): ?>
        $tr.find('.product-select')
           .append(`<option value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>"><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?> (€<?= number_format($p['price'],2) ?>)</option>`);
      <?php endforeach; ?>
      // Populate Therapist dropdown
      <?php foreach($allTherapists as $t): ?>
        $tr.find('.therapist-select')
           .append(`<option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name'], ENT_QUOTES, 'UTF-8') ?></option>`);
      <?php endforeach; ?>

      $('#productsTable tbody').append($tr);

      // (D1) When a product is selected, auto‐populate unit-price & line total
      $tr.find('.product-select').on('change', function() {
        const price = parseFloat($(this).find(':selected').data('price') || 0);
        $tr.find('.unit-price').val(price.toFixed(2));
        const qty = parseInt($tr.find('.qty-input').val() || 1);
        $tr.find('.line-total').val((price * qty).toFixed(2));
        recalcTotals();
      });

      // (D2) When “Qty” changes → recalc line total
      $tr.find('.qty-input').on('input', function() {
        const price = parseFloat($tr.find('.unit-price').val() || 0);
        const qty = Math.max(1, parseInt($(this).val() || 1));
        $tr.find('.line-total').val((price * qty).toFixed(2));
        recalcTotals();
      });

      // (D3) When “Unit Price” changes manually → recalc line total
      $tr.find('.unit-price').on('input', function() {
        const price = parseFloat($(this).val() || 0);
        const qty = parseInt($tr.find('.qty-input').val() || 1);
        $tr.find('.line-total').val((price * qty).toFixed(2));
        recalcTotals();
      });

      // (D4) Remove button
      $tr.find('.btnRemoveProduct').click(function() {
        $tr.remove();
        recalcTotals();
      });
    });


    // (E) Dynamically Add/Remove Payment Rows with auto‐populated amount
    let paymentCount = 0;
    $('#btnAddPayment').click(function(){
      paymentCount++;
      const rowId = `paymentRow${paymentCount}`;

      // Read the current Grand Total from the page
      let grandText = $('#txtGrandTotal').text() || '0';
      let grandVal  = parseFloat(grandText.replace(/,/g, '')) || 0.00;

      let $tr = $(`
        <tr id="${rowId}">
          <td>
            <input
              type="datetime-local"
              name="payment_row[${paymentCount}][payment_date]"
              class="form-control payment-date"
              value="<?= date('Y-m-d\TH:i') ?>"
              required
            >
          </td>
          <td>
            <select
              name="payment_row[${paymentCount}][payment_method]"
              class="form-control"
              required
            >
              <option value="">-- Method --</option>
            </select>
          </td>
          <td>
            <input
              type="number"
              step="0.01"
              name="payment_row[${paymentCount}][amount]"
              class="form-control payment-amt"
              value="${grandVal.toFixed(2)}"
              required
            >
          </td>
          <td class="text-center">
            <button type="button" class="btn btn-sm btn-danger btnRemovePayment">
              <i class="fas fa-trash"></i>
            </button>
          </td>
        </tr>
      `);
      // Populate Payment Method dropdown
      <?php foreach($allPaymentMethods as $pm): ?>
        $tr.find('select')
           .append(`<option value="<?= htmlspecialchars($pm['name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($pm['name'], ENT_QUOTES, 'UTF-8') ?></option>`);
      <?php endforeach; ?>

      $('#paymentsTable tbody').append($tr);

      // (E2) Remove button
      $tr.find('.btnRemovePayment').click(function() {
        $tr.remove();
      });
    });

    // (F) Recalculate Totals whenever any “line-total” changes
    function recalcTotals() {
      let srvSubtotal = 0.00;
      let prdSubtotal = 0.00;

      // Sum all service line‐totals
      $('#servicesTable tbody tr').each(function() {
        const val = parseFloat($(this).find('.line-total').val() || 0);
        srvSubtotal += isNaN(val) ? 0 : val;
      });

      // Sum all product line‐totals
      $('#productsTable tbody tr').each(function() {
        const val = parseFloat($(this).find('.line-total').val() || 0);
        prdSubtotal += isNaN(val) ? 0 : val;
      });

      // 19% VAT on subtotals
      const srvVAT = parseFloat((srvSubtotal * 0.19).toFixed(2));
      const prdVAT = parseFloat((prdSubtotal * 0.19).toFixed(2));
      const totalVAT = parseFloat((srvVAT + prdVAT).toFixed(2));
      const grandTotal = parseFloat((srvSubtotal + prdSubtotal).toFixed(2));

      $('#txtServicesSubtotal').text(srvSubtotal.toFixed(2));
      $('#txtServicesVAT').text(srvVAT.toFixed(2));
      $('#txtProductsSubtotal').text(prdSubtotal.toFixed(2));
      $('#txtProductsVAT').text(prdVAT.toFixed(2));
      $('#txtTotalVAT').text(totalVAT.toFixed(2));
      $('#txtGrandTotal').text(grandTotal.toFixed(2));
    }
  });
</script>

<!-- Print Receipt popup (unchanged) -->
<?php if (!empty($_GET['print_id'])): ?>
<script>
  window.open(
    'print_receipt.php?sale_id=<?= (int)$_GET['print_id'] ?>',
    '_blank',
    'toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=320,height=600'
  );
  // 2) Remove print_id so refresh does NOT re-open
  history.replaceState(null, '', 'cashier.php?success=1');
</script>
<?php endif; ?>
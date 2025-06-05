<?php
// File: pages/cashier.php

require_once '../auth.php';
requirePermission($pdo, 'cashier.manage');

// ─── Fetch all payment methods for dynamic dropdowns ───────────────────────
$pmStmt = $pdo->query("SELECT id, name FROM payment_methods ORDER BY name ASC");
$allPaymentMethods = $pmStmt->fetchAll(PDO::FETCH_ASSOC);

// ────────────────────────────────────────────────────────────────────────────────
// (A) Handle “Delete” action via GET (delete an entire sale and its line‐items)
// ────────────────────────────────────────────────────────────────────────────────
if (
  isset($_GET['action']) && $_GET['action'] === 'delete'
  && isset($_GET['id']) && is_numeric($_GET['id'])
) {
  $sale_id = (int)$_GET['id'];
  // Delete the sale row; cascading foreign keys will remove sale_services, sale_products, sale_payments
  $del = $pdo->prepare("DELETE FROM sales WHERE id = ?");
  $del->execute([$sale_id]);
  // Redirect back without the querystring
  header("Location: cashier.php?deleted=1");
  exit();
}

// ────────────────────────────────────────────────────────────────────────────────
// (B) Handle “Add New Sale” via POST
// ────────────────────────────────────────────────────────────────────────────────
$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_sale'])) {
  // 1) Sale header fields
  $sale_date_raw       = trim($_POST['sale_date'] ?? '');
  $sale_date           = $sale_date_raw ? date('Y-m-d H:i:s', strtotime($sale_date_raw)) : date('Y-m-d H:i:s');
  $appointment_id      = !empty($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : null;
  $client_id           = null;
  if (!$appointment_id && !empty($_POST['client_manual'])) {
    // If no appointment chosen, user can type a client name manually (optional)
    // For simplicity, we won't create a new client record—just store NULL in client_id.
    $client_manual_name = trim($_POST['client_manual']);
  }

  // 2) Services Subtotal & VAT calculation
  $services_subtotal = 0.00;
  $services_vat      = 0.00;
  if (!empty($_POST['service_row'])) {
    foreach ($_POST['service_row'] as $idx => $row) {
      $service_id   = (int)$row['service_id'];
      $therapist_id = (int)$row['therapist_id'];
      $qty          = max(1,(int)$row['qty']);
      $unit_price   = (float)$row['unit_price']; // price includes VAT

      $line_total   = round($unit_price * $qty, 2);
      $services_subtotal += $line_total;

      // Assume 19% VAT on services; VAT portion = price × (19/119)
      $vat_line = round($line_total * (0.19/1.19), 2);
      $services_vat += $vat_line;
    }
  }

  // 3) Products Subtotal & VAT calculation
  $products_subtotal = 0.00;
  $products_vat      = 0.00;
  if (!empty($_POST['product_row'])) {
    foreach ($_POST['product_row'] as $idx => $row) {
      $product_id   = (int)$row['product_id'];
      $therapist_id = (int)$row['therapist_id'];
      $qty          = max(1,(int)$row['qty']);
      $unit_price   = (float)$row['price'];

      $line_total = round($unit_price * $qty, 2);
      $products_subtotal += $line_total;

      // Assume 19% VAT on products
      $vat_line = round($line_total * (0.19/1.19), 2);
      $products_vat += $vat_line;
    }
  }

  // 4) Totals
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
  $new_sale_id = $pdo->lastInsertId();

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
      $qty          = max(1,(int)$row['qty']);
      $unit_price   = (float)$row['unit_price'];
      $line_total   = round($unit_price * $qty, 2);

      $insServiceLine->execute([
        'sale_id'      => $new_sale_id,
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
      $qty          = max(1,(int)$row['qty']);
      $unit_price   = (float)$row['unit_price'];
      $line_total   = round($unit_price * $qty, 2);

      $insProductLine->execute([
        'sale_id'      => $new_sale_id,
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
        (sale_id, payment_date, payment_method, amount)
      VALUES
        (:sale_id, :payment_date, :payment_method, :amount)
    ");
    foreach ($_POST['payment_row'] as $idx => $row) {
      $payment_date   = trim($row['payment_date']);
      $payment_date   = $payment_date ? date('Y-m-d H:i:s', strtotime($payment_date)) : date('Y-m-d H:i:s');
      $payment_method = trim($row['payment_method']);
      $amount         = (float)$row['amount'];

      $insPayment->execute([
        'sale_id'        => $new_sale_id,
        'payment_date'   => $payment_date,
        'payment_method' => $payment_method,
        'amount'         => $amount
      ]);
    }
  }

  // 9) Redirect to a popup for printing (or simply set a success message)
  //$success_message = "Sale #{$new_sale_id} saved successfully.";
  //header("Location: print_receipt.php?sale_id={$new_sale_id}");
  //exit();
  $just_saved_sale_id = $new_sale_id;
  $success_message = "Sale #{$new_sale_id} saved successfully.";
}

// ────────────────────────────────────────────────────────────────────────────────
// (C) Fetch Paginated Sales for the Table Display
// ────────────────────────────────────────────────────────────────────────────────

$search_client = trim($_GET['search'] ?? '');

// Acceptable numeric limits; "all" = no pagination.
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

// Build WHERE clause on search by client name (search both sale.client and appointment.client)
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

// Count total rows
$countSql = "
  SELECT COUNT(*)
    FROM sales s
    LEFT JOIN clients c       ON s.client_id = c.id
    LEFT JOIN appointments a  ON s.appointment_id = a.id
    LEFT JOIN clients ac      ON a.client_id = ac.id
  {$where_sql}
";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$total_rows = (int)$stmtCount->fetchColumn();

// Compute total_pages & offset
if ($limit > 0) {
  $total_pages = max(1, ceil($total_rows / $limit));
  $offset = ($page - 1) * $limit;
} else {
  $total_pages = 1;
  $offset = 0;
}

// Build LIMIT clause if needed
$limit_sql = $limit > 0 ? "LIMIT :offset, :limit" : "";

// Fetch paginated (or all) sales, joining both sale.client_id and appointment.client_id
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
      SELECT GROUP_CONCAT(
        CONCAT(si.quantity,'× ',sv.name,' (€', FORMAT(si.unit_price,2), ')')
        SEPARATOR '<br>'
      )
      FROM sale_services si
      JOIN services sv ON si.service_id = sv.id
      WHERE si.sale_id = s.id
    ) AS services_list,
    (
      SELECT GROUP_CONCAT(
        CONCAT(pi.quantity,'× ',pv.name,' (€', FORMAT(pi.unit_price,2), ')')
        SEPARATOR '<br>'
      )
      FROM sale_products pi
      JOIN products pv ON pi.product_id = pv.id
      WHERE pi.sale_id = s.id
    ) AS products_list,
    s.grand_total,
    (
      SELECT GROUP_CONCAT(
        CONCAT(sp.payment_method, ' – €', FORMAT(sp.amount,2))
        SEPARATOR '<br>'
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
foreach ($params as $k => $v) {
  $stmtData->bindValue($k, $v);
}
if ($limit > 0) {
  $stmtData->bindValue('offset', $offset, PDO::PARAM_INT);
  $stmtData->bindValue('limit',  $limit,  PDO::PARAM_INT);
}
$stmtData->execute();
$sales = $stmtData->fetchAll(PDO::FETCH_ASSOC);

// ────────────────────────────────────────────────────────────────────────────────
// (D) Fetch data for “Add New Sale” form selects
// ────────────────────────────────────────────────────────────────────────────────
// (D1) Today’s Appointments (for the appointment dropdown)
$today_date = date('Y-m-d');
$apptStmt = $pdo->prepare("
  SELECT
    a.id,
    TIME(a.start_time) AS start_time,
    CONCAT(c.first_name,' ',c.last_name,' [',c.mobile,']') AS client_label
  FROM appointments a
  JOIN clients c ON a.client_id = c.id
  WHERE DATE(a.start_time) = :today_date
  ORDER BY a.start_time
");
$apptStmt->execute(['today_date' => $today_date]);
$todayAppointments = $apptStmt->fetchAll(PDO::FETCH_ASSOC);

// (D2) All Services
$serviceStmt = $pdo->query("
  SELECT id, name, price
    FROM services
   ORDER BY name
");
$allServices = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);

// (D3) All Products
$productStmt = $pdo->query("
  SELECT id, name, sell_price AS price
    FROM products
   ORDER BY name
");
$allProducts = $productStmt->fetchAll(PDO::FETCH_ASSOC);

// (D4) All Therapists
$therapistStmt = $pdo->query("
  SELECT id, first_name, last_name
    FROM therapists
   ORDER BY first_name, last_name
");
$allTherapists = $therapistStmt->fetchAll(PDO::FETCH_ASSOC);

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
            aria-controls="collapseAddSale"
          >
            <i class="fas fa-plus"></i> New Sale
          </button>
        </div>
      </div>
    </div>
  </section>

  <!-- ──────────────────────────────────────────────────────────────────────────── -->
  <!-- (F) “Add New Sale” Form (collapsed by default unless action=add) ───────────── -->
  <section class="content">
    <div class="container-fluid">
      <div
        class="collapse <?= isset($_GET['action']) && $_GET['action']==='add' ? 'show' : '' ?>"
        id="collapseAddSale"
      >
        <div class="card card-outline card-primary">
          <div class="card-header">
            <h3 class="card-title">Add New Sale</h3>
            <button
              type="button"
              class="close"
              data-target="#collapseAddSale"
              aria-controls="collapseAddSale"
              aria-label="Close"
            >
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="card-body">
            <?php if (!empty($errors)): ?>
              <div class="alert alert-danger">
                <ul class="mb-0">
                  <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>

            <form id="addSaleForm" method="POST" action="cashier.php">
              <!-- Section 1: Date & Appointment -->
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
                <div class="form-group col-md-8">
                  <label for="appointment_id">Link to Today’s Appointment (optional)</label>
                  <select
                    id="appointment_id"
                    name="appointment_id"
                    class="form-control select2"
                    style="width:100%;"
                  >
                    <option value="">-- No Appointment --</option>
                    <?php foreach ($todayAppointments as $a): ?>
                      <option value="<?= $a['id'] ?>">
                        <?= htmlspecialchars($a['start_time'] . ' – ' . $a['client_label']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <hr>

              <!-- Section 2: Services -->
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

              <!-- Section 3: Products -->
              <div class="card card-warning">
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

              <!-- Section 4: Totals -->
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

              <!-- Section 5: Payments -->
              <div class="card card-success">
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

              <!-- Section 6: Submit -->
              <div class="text-center">
                <button type="submit" name="save_sale" class="btn btn-primary btn-lg">
                  <i class="fas fa-save"></i> Save Sale
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ──────────────────────────────────────────────────────────────────────────── -->
  <!-- (G) Existing Sales Table with Pagination & Search ─────────────────────────── -->
  <section class="content">
    <div class="container-fluid">
      <div class="row mb-3">
        <div class="col-md-2">
          <select id="limitSelect" class="form-control">
            <?php foreach ([50,100,200,300] as $opt): ?>
              <option value="<?= $opt ?>" <?= ($opt==$limit) ? 'selected' : '' ?>>
                Show <?= $opt ?>
              </option>
            <?php endforeach; ?>
            <option value="all" <?= ($limit === 0) ? 'selected' : '' ?>>Show All</option>
          </select>
        </div>
        <div class="col-md-6">
          <form id="searchForm" method="GET" action="cashier.php">
            <div class="input-group">
              <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search by client name…"
                value="<?= htmlspecialchars($search_client) ?>"
              >
              <span class="input-group-append">
                <button class="btn btn-secondary" type="submit">
                  <i class="fas fa-search"></i>
                </button>
              </span>
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

      <table class="table table-striped table-bordered">
        <thead>
          <tr>
            <th>ID</th>
            <th>Date</th>
            <th>Client</th>
            <th>Services</th>
            <th>Products</th>
            <th>Total (€)</th>
            <th>Payment (Method – €)</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($sales) === 0): ?>
            <tr>
              <td colspan="8" class="text-center">No sales found.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($sales as $row): ?>
              <tr>
                <td><?= $row['sale_id'] ?></td>
                <td><?= date('Y-m-d H:i', strtotime($row['sale_date'])) ?></td>
                <td><?= htmlspecialchars($row['client_name']) ?></td>
                <td><?= $row['services_list'] ?? '<span class="text-muted">—</span>' ?></td>
                <td><?= $row['products_list'] ?? '<span class="text-muted">—</span>' ?></td>
                <td>€ <?= number_format($row['grand_total'],2) ?></td>
                <td><?= $row['payments_list'] ?? '<span class="text-muted">—</span>' ?></td>
                <td>
                  <!-- Print Icon: opens receipt in a new small popup -->
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

                  <!-- Delete Icon: as before -->
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
          <?php endif; ?>
        </tbody>
      </table>

      <!-- Pagination Bottom -->
      <div class="row">
        <div class="col-md-12 text-center">
          <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center mb-0">
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

    </div> <!-- /.container-fluid -->
  </section> <!-- /.content -->

</div> <!-- /.content-wrapper -->

<?php include '../includes/footer.php'; ?>

<!-- ─────────────────────────────────────────────────────────────────────────────────── -->
<!-- (H) Required JS: jQuery, Bootstrap, Select2 (for searchable selects) ───────────────── -->

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
<script>
  // Safety‐net: force-hide the #collapseAddSale panel if the “×” is clicked
  $('[data-target="#collapseAddSale"].close').on('click', function() {
    $('#collapseAddSale').collapse('hide');
  });
</script>

<!-- … (above) … -->
<script>
$(document).ready(function() {
  // Initialize Select2 as before
  $('.select2').select2({ width: '100%' });

  // (H1) “Show X” dropdown logic (unchanged) …
  // (H2) Dynamically Add/Remove Service Rows with editable unit-price
  let serviceCount = 0;
  const allServices = <?= json_encode($allServices) ?>;
  const allTherapists = <?= json_encode($allTherapists) ?>;

  $('#btnAddService').click(function() {
    serviceCount++;
    const rowId = `serviceRow${serviceCount}`;

    let $tr = $(`
      <tr id="${rowId}">
        <td>
          <select
            name="service_row[${serviceCount}][service_id]"
            class="form-control service-select"
            style="width:100%;"
            required
          >
            <option value="">-- Select Service --</option>
          </select>
        </td>
        <td>
          <select
            name="service_row[${serviceCount}][therapist_id]"
            class="form-control therapist-select"
            style="width:100%;"
            required
          >
            <option value="">-- Select Therapist --</option>
          </select>
        </td>
        <td>
          <input
            type="number"
            min="1"
            name="service_row[${serviceCount}][qty]"
            class="form-control qty-input"
            value="1"
            required
          >
        </td>
        <td>
          <!-- Removed readonly; now editable -->
          <input
            type="text"
            name="service_row[${serviceCount}][unit_price]"
            class="form-control unit-price"
            placeholder="0.00"
            required
          >
        </td>
        <td>
          <input
            type="text"
            name="service_row[${serviceCount}][line_total]"
            class="form-control line-total"
            readonly
            placeholder="0.00"
          >
        </td>
        <td class="text-center">
          <button type="button" class="btn btn-sm btn-danger btnRemoveService">
            <i class="fas fa-trash"></i>
          </button>
        </td>
      </tr>
    `);

    // Populate the “Service” dropdown
    allServices.forEach(s => {
      $tr.find('.service-select')
         .append(`<option data-price="${s.price}" value="${s.id}">${s.name} (€${parseFloat(s.price).toFixed(2)})</option>`);
    });

    // Populate the “Therapist” dropdown
    allTherapists.forEach(t => {
      $tr.find('.therapist-select')
         .append(`<option value="${t.id}">${t.first_name} ${t.last_name}</option>`);
    });

    $('#servicesTable tbody').append($tr);
    $tr.find('.service-select, .therapist-select').select2({ width: '100%' });

    // 1) When “Service” changes → auto-fill unit price & recalc line total
    $tr.find('.service-select').change(function() {
      const price = parseFloat($(this).find('option:selected').data('price') || 0);
      $tr.find('.unit-price').val(price.toFixed(2));     // auto-populate
      const qty = parseInt($tr.find('.qty-input').val() || 1);
      $tr.find('.line-total').val((price * qty).toFixed(2));
      recalcTotals();
    });

    // 2) When “Qty” changes → recalc line total
    $tr.find('.qty-input').on('input', function() {
      const price = parseFloat($tr.find('.unit-price').val() || 0);
      const qty = Math.max(1, parseInt($(this).val() || 1));
      $tr.find('.line-total').val((price * qty).toFixed(2));
      recalcTotals();
    });

    // 3) When “Unit Price” changes manually → recalc line total
    $tr.find('.unit-price').on('input', function() {
      const price = parseFloat($(this).val() || 0);
      const qty = parseInt($tr.find('.qty-input').val() || 1);
      $tr.find('.line-total').val((price * qty).toFixed(2));
      recalcTotals();
    });

    // 4) Remove button
    $tr.find('.btnRemoveService').click(function() {
      $tr.remove();
      recalcTotals();
    });
  });

  // (H3) Dynamically Add/Remove Product Rows with editable unit-price
  let productCount = 0;
  const allProducts = <?= json_encode($allProducts) ?>;

  $('#btnAddProduct').click(function() {
    productCount++;
    const rowId = `productRow${productCount}`;

    let $tr = $(`
      <tr id="${rowId}">
        <td>
          <select
            name="product_row[${productCount}][product_id]"
            class="form-control product-select"
            style="width:100%;"
            required
          >
            <option value="">-- Select Product --</option>
          </select>
        </td>
        <td>
          <select
            name="product_row[${productCount}][therapist_id]"
            class="form-control therapist-select"
            style="width:100%;"
            required
          >
            <option value="">-- Select Therapist --</option>
          </select>
        </td>
        <td>
          <input
            type="number"
            min="1"
            name="product_row[${productCount}][qty]"
            class="form-control qty-input"
            value="1"
            required
          >
        </td>
        <td>
          <!-- Removed readonly; now editable -->
          <input
            type="text"
            name="product_row[${productCount}][unit_price]"
            class="form-control unit-price"
            placeholder="0.00"
            required
          >
        </td>
        <td>
          <input
            type="text"
            name="product_row[${productCount}][line_total]"
            class="form-control line-total"
            readonly
            placeholder="0.00"
          >
        </td>
        <td class="text-center">
          <button type="button" class="btn btn-sm btn-danger btnRemoveProduct">
            <i class="fas fa-trash"></i>
          </button>
        </td>
      </tr>
    `);

    // Populate “Product” dropdown
    allProducts.forEach(p => {
      $tr.find('.product-select')
         .append(`<option data-price="${p.price}" value="${p.id}">${p.name} (€${parseFloat(p.price).toFixed(2)})</option>`);
    });

    // Populate “Therapist” dropdown
    allTherapists.forEach(t => {
      $tr.find('.therapist-select')
         .append(`<option value="${t.id}">${t.first_name} ${t.last_name}</option>`);
    });

    $('#productsTable tbody').append($tr);
    $tr.find('.product-select, .therapist-select').select2({ width: '100%' });

    // 1) When “Product” changes → auto-fill unit price & recalc line total
    $tr.find('.product-select').change(function() {
      const price = parseFloat($(this).find('option:selected').data('price') || 0);
      $tr.find('.unit-price').val(price.toFixed(2));
      const qty = parseInt($tr.find('.qty-input').val() || 1);
      $tr.find('.line-total').val((price * qty).toFixed(2));
      recalcTotals();
    });

    // 2) When “Qty” changes → recalc line total
    $tr.find('.qty-input').on('input', function() {
      const price = parseFloat($tr.find('.unit-price').val() || 0);
      const qty = Math.max(1, parseInt($(this).val() || 1));
      $tr.find('.line-total').val((price * qty).toFixed(2));
      recalcTotals();
    });

    // 3) When “Unit Price” changes manually → recalc line total
    $tr.find('.unit-price').on('input', function() {
      const price = parseFloat($(this).val() || 0);
      const qty = parseInt($tr.find('.qty-input').val() || 1);
      $tr.find('.line-total').val((price * qty).toFixed(2));
      recalcTotals();
    });

    // 4) Remove button
    $tr.find('.btnRemoveProduct').click(function() {
      $tr.remove();
      recalcTotals();
    });
  });

  // (H4) Dynamically Add/Remove Payment Rows with auto-populated amount
  let paymentCount = 0;
  $('#btnAddPayment').click(function() {
    paymentCount++;
    const rowId = `paymentRow${paymentCount}`;

    // Read the current Grand Total from the page
    // (parsed as a float). If not a number, default to 0.
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
            class="form-control payment-method"
            required
          >
          <option value="">-- Select Payment Method --</option>
          <?php foreach ($allPaymentMethods as $pm): ?>
            <option
              value="<?= htmlspecialchars($pm['name'], ENT_QUOTES) ?>"
              <?= (isset($existingPaymentMethod) && $existingPaymentMethod === $pm['name'])
                  ? 'selected'
                  : ''
              ?>
            >
              <?= htmlspecialchars($pm['name']) ?>
            </option>
          <?php endforeach; ?>
          </select>
        </td>
        <td>
          <input
            type="number"
            step="0.01"
            name="payment_row[${paymentCount}][amount]"
            class="form-control payment-amount"
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

    $('#paymentsTable tbody').append($tr);
    $tr.find('.btnRemovePayment').click(function() {
      $tr.remove();
    });
  });

  // (H5) Recalculate Totals whenever any “line-total” changes
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

    // 19% VAT assumed
    const srvVAT = +(srvSubtotal * (0.19/1.19)).toFixed(2);
    const prdVAT = +(prdSubtotal * (0.19/1.19)).toFixed(2);
    const totalVAT = +(srvVAT + prdVAT).toFixed(2);
    const grandTot = +(srvSubtotal + prdSubtotal).toFixed(2);

    // Update DOM
    $('#txtServicesSubtotal').text(srvSubtotal.toFixed(2));
    $('#txtServicesVAT').text(srvVAT.toFixed(2));
    $('#txtProductsSubtotal').text(prdSubtotal.toFixed(2));
    $('#txtProductsVAT').text(prdVAT.toFixed(2));
    $('#txtTotalVAT').text(totalVAT.toFixed(2));
    $('#txtGrandTotal').text(grandTot.toFixed(2));
  }

  // Ensure delete confirms
  $('a[href*="action=delete"]').click(function() {
    return confirm('Are you sure you want to delete this sale?');
  });

 // If the close button is clicked, force the collapse to hide
  $('[data-target="#collapseAddSale"].close').on('click', function() {
    $('#collapseAddSale').collapse('hide');
  });
});
</script>
<script>
  // Expose allPaymentMethods (PHP) to JS
  const allPaymentMethods = <?= json_encode($allPaymentMethods, JSON_UNESCAPED_UNICODE) ?>;
</script>

<?php if (!empty($just_saved_sale_id)): ?>
<script>
  // Open the newly saved receipt in a new tab/window (popup)
  window.open(
    'print_receipt.php?sale_id=<?= (int)$just_saved_sale_id ?>',
    '_blank',
    'toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=320,height=600'
  );
</script>
<?php endif; ?>
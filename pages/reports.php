<?php
// File: pages/reports.php

require_once '../auth.php';
requirePermission($pdo, 'reports.view');


// ────────────────────────────────────────────────────────────────────────────
// (A) Which report (if any) was requested?
// ────────────────────────────────────────────────────────────────────────────
$validReports = [
  'top_clients_appts',
  'top_clients_payments',
  'top_therapists_appts',
  'top_therapists_payments',
  'first_appointments',
  'gender_distribution',
  'sales_appointments',
  'sales_products',
  'cashier_all',
  'cashier_staff',
  'cashier_service',
  'cashier_products',
  'z_reports',
  'generated_zreports'
];

$selectedReport = $_GET['report'] ?? '';
if (!in_array($selectedReport, $validReports, true)) {
  $selectedReport = '';  // no report selected → show only dropdown
}


// ────────────────────────────────────────────────────────────────────────────
// (B) Handle “Generate Z Report” submission (audit‐trail + redirect)
// ────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_zreport'])) {
  $dateFrom = $_POST['zr_date_from'] ?? '';
  $dateTo   = $_POST['zr_date_to']   ?? '';
  if (
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)
  ) {
    echo "Invalid date range.";
    exit();
  }

  $pdo->beginTransaction();
  $pdo->query("
    INSERT INTO z_reports (report_number, date_from, date_to)
    VALUES (0, '$dateFrom', '$dateTo')
  ");
  $newId = (int)$pdo->lastInsertId();
  $pdo->query("
    UPDATE z_reports
    SET report_number = {$newId}
    WHERE id = {$newId}
  ");
  $pdo->commit();

  $userId = $_SESSION['user_id'] ?? null;
  $stmtLog = $pdo->prepare("
    INSERT INTO reports_log (user_id, report_type, parameters)
    VALUES (:uid, :rtype, :params)
  ");
  $jsonParams = json_encode([
    'date_from'   => $dateFrom,
    'date_to'     => $dateTo,
    'z_report_id' => $newId
  ], JSON_UNESCAPED_UNICODE);
  $stmtLog->execute([
    'uid'    => $userId,
    'rtype'  => 'z_report',
    'params' => $jsonParams
  ]);

  header("Location: zreport_print.php?report_id={$newId}");
  exit();
}


// ────────────────────────────────────────────────────────────────────────────
// (C) Prepare containers for data (all reports)
// ────────────────────────────────────────────────────────────────────────────
$topClientsAppts    = [];
$topClientsPay      = [];
$topTherapistsAppts = [];
$topTherapistsPay   = [];
$firstAppts         = [];
$genderDist         = [];
$salesAppts         = [];
$salesPrd           = [];
$cashAll            = [];
$cashStaff          = [];
$cashService        = [];
$cashProd           = [];
$genZ               = [];


// ────────────────────────────────────────────────────────────────────────────
// (D) Only run the SQL for whichever report was selected, with date filters
// ────────────────────────────────────────────────────────────────────────────
if ($selectedReport === 'top_clients_appts') {
  // ─── C1: Top 10 Clients by Appointments ──────────────────────────────────
  $from_date = $_GET['from_date'] ?? date('Y-m-d');
  $to_date   = $_GET['to_date']   ?? date('Y-m-d');

  $stmt = $pdo->prepare("
    SELECT
      CONCAT(c.first_name,' ',c.last_name) AS client_name,
      COUNT(a.id) AS appt_count
    FROM appointments a
    JOIN clients c ON a.client_id = c.id
    WHERE DATE(a.appointment_date) BETWEEN :from_date AND :to_date
    GROUP BY a.client_id
    ORDER BY appt_count DESC
    LIMIT 10
  ");
  $stmt->bindValue('from_date', $from_date);
  $stmt->bindValue('to_date',   $to_date);
  $stmt->execute();
  $topClientsAppts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($selectedReport === 'top_clients_payments') {
  // ─── C2: Top 10 Clients by Payments ─────────────────────────────────────
  $from_date = $_GET['from_date'] ?? date('Y-m-d');
    $to_date   = $_GET['to_date']   ?? date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT
          COALESCE(
            CONCAT(c.first_name, ' ', c.last_name),
            'Walk-in'
          ) AS client_name,
          SUM(sp.amount) AS total_paid
        FROM sale_payments sp
        JOIN sales s
          ON sp.sale_id = s.id
        LEFT JOIN clients c
          ON s.client_id = c.id
        WHERE DATE(s.sale_date) BETWEEN :from_date AND :to_date
        GROUP BY s.client_id
        ORDER BY total_paid DESC
        LIMIT 10
      ");
      $stmt->bindValue('from_date', $from_date);
      $stmt->bindValue('to_date',   $to_date);
      $stmt->execute();
      $topClientsPay = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($selectedReport === 'top_therapists_appts') {
  // ─── C3: Top 10 Therapists by Appointments ──────────────────────────────
  $from_date = $_GET['from_date'] ?? date('Y-m-d');
  $to_date   = $_GET['to_date']   ?? date('Y-m-d');

  $stmt = $pdo->prepare("
    SELECT
      ANY_VALUE(CONCAT(t.first_name,' ',t.last_name)) AS therapist_name,
      COUNT(a.id) AS appt_count
    FROM appointments a
    JOIN therapists t ON a.staff_id = t.id
    WHERE DATE(a.appointment_date) BETWEEN :from_date AND :to_date
    GROUP BY a.staff_id
    ORDER BY appt_count DESC
    LIMIT 10
  ");
  $stmt->bindValue('from_date', $from_date);
  $stmt->bindValue('to_date',   $to_date);
  $stmt->execute();
  $topTherapistsAppts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($selectedReport === 'top_therapists_payments') {
  // ─── C4: Top 10 Therapists by Revenue (Services) ────────────────────────
  $from_date = $_GET['from_date'] ?? date('Y-m-d');
  $to_date   = $_GET['to_date']   ?? date('Y-m-d');

  $stmt = $pdo->prepare("
    SELECT
      ANY_VALUE(CONCAT(t.first_name,' ',t.last_name)) AS therapist_name,
      SUM(ss.line_total) AS total_revenue
    FROM sale_services ss
    JOIN therapists t ON ss.therapist_id = t.id
    JOIN sales s ON ss.sale_id = s.id
    WHERE DATE(s.sale_date) BETWEEN :from_date AND :to_date
    GROUP BY ss.therapist_id
    ORDER BY total_revenue DESC
    LIMIT 10
  ");
  $stmt->bindValue('from_date', $from_date);
  $stmt->bindValue('to_date',   $to_date);
  $stmt->execute();
  $topTherapistsPay = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($selectedReport === 'first_appointments') {
  // ─── C5: First Appointment Date per Client (within range) ─────────────
  $from_date = $_GET['from_date'] ?? date('Y-m-d');
  $to_date   = $_GET['to_date']   ?? date('Y-m-d');

  $stmt = $pdo->prepare("
    SELECT
      CONCAT(c.first_name,' ',c.last_name) AS client_name,
      MIN(a.appointment_date) AS first_appt_date
    FROM appointments a
    JOIN clients c ON a.client_id = c.id
    GROUP BY a.client_id
    HAVING DATE(first_appt_date) BETWEEN :from_date AND :to_date
    ORDER BY first_appt_date DESC
    LIMIT 20
  ");
  $stmt->bindValue('from_date', $from_date);
  $stmt->bindValue('to_date',   $to_date);
  $stmt->execute();
  $firstAppts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($selectedReport === 'gender_distribution') {
  // ─── C6: Gender Distribution (filter by client creation date) ─────────
  $from_date = $_GET['from_date'] ?? date('Y-m-d');
  $to_date   = $_GET['to_date']   ?? date('Y-m-d');

  $stmt = $pdo->prepare("
    SELECT gender, COUNT(*) AS count
    FROM clients
    WHERE DATE(created_at) BETWEEN :from_date AND :to_date
    GROUP BY gender
  ");
  $stmt->bindValue('from_date', $from_date);
  $stmt->bindValue('to_date',   $to_date);
  $stmt->execute();
  $genderDist = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($selectedReport === 'sales_appointments') {
  // ─── C7: Sales (Appointments) ───────────────────────────────────────────
  $from_date = $_GET['from_date'] ?? date('Y-m-d');
    $to_date   = $_GET['to_date']   ?? date('Y-m-d');

    $stmt = $pdo->prepare("
      SELECT
        s.id                                   AS sale_id,
        s.sale_date,
        COALESCE(
          CONCAT(c.first_name, ' ', c.last_name),
          'Walk-in'
        ) AS client_name,
        ANY_VALUE(
          CONCAT(t.first_name, ' ', t.last_name)
        ) AS therapist_name,
        -- List of services on that sale (name + unit_price)
        (
          SELECT GROUP_CONCAT(
            CONCAT(sv.name, ' (€', FORMAT(ss.unit_price,2), ')')
            SEPARATOR '<br>'
          )
          FROM sale_services ss
          JOIN services sv
            ON ss.service_id = sv.id
          WHERE ss.sale_id = s.id
        ) AS services_list,
        -- List of products on that sale (name + unit_price)
        (
          SELECT GROUP_CONCAT(
            CONCAT(pv.name, ' (€', FORMAT(sp.unit_price,2), ')')
            SEPARATOR '<br>'
          )
          FROM sale_products sp
          JOIN products pv
            ON sp.product_id = pv.id
          WHERE sp.sale_id = s.id
        ) AS products_list,
        (s.services_subtotal + s.products_subtotal)   AS price,
        -- Total paid for that sale
        (
          SELECT IFNULL(SUM(amount), 0)
          FROM sale_payments spay
          WHERE spay.sale_id = s.id
        ) AS paid_amount,
        -- All payment methods used (comma-separated)
        (
          SELECT GROUP_CONCAT(payment_method SEPARATOR ', ')
          FROM sale_payments spay
          WHERE spay.sale_id = s.id
        ) AS pay_methods
      FROM sales s
      LEFT JOIN clients c
        ON s.client_id = c.id
      LEFT JOIN sale_services ss
        ON s.id = ss.sale_id
      LEFT JOIN sale_products sp
        ON s.id = sp.sale_id
      LEFT JOIN therapists t
        ON ss.therapist_id = t.id
      WHERE DATE(s.sale_date) BETWEEN :from_date AND :to_date
        AND ss.sale_id IS NOT NULL
      GROUP BY s.id
      ORDER BY s.sale_date DESC
    ");
    $stmt->bindValue('from_date', $from_date);
    $stmt->bindValue('to_date',   $to_date);
    $stmt->execute();
    $salesAppts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($selectedReport === 'sales_products') {
  // ─── C8: Sales (Products) ───────────────────────────────────────────────
  $from_date = $_GET['from_date'] ?? date('Y-m-d');
    $to_date   = $_GET['to_date']   ?? date('Y-m-d');

    $stmt = $pdo->prepare("
      SELECT
        s.id                                   AS sale_id,
        s.sale_date,
        COALESCE(
          CONCAT(c.first_name, ' ', c.last_name),
          'Walk-in'
        ) AS client_name,
        ANY_VALUE(
          CONCAT(t.first_name, ' ', t.last_name)
        ) AS therapist_name,
        (
          SELECT GROUP_CONCAT(
            CONCAT(sv.name, ' (€', FORMAT(ss.unit_price,2), ')')
            SEPARATOR '<br>'
          )
          FROM sale_services ss
          JOIN services sv
            ON ss.service_id = sv.id
          WHERE ss.sale_id = s.id
        ) AS services_list,
        (
          SELECT GROUP_CONCAT(
            CONCAT(pv.name, ' (€', FORMAT(sp2.unit_price,2), ')')
            SEPARATOR '<br>'
          )
          FROM sale_products sp2
          JOIN products pv
            ON sp2.product_id = pv.id
          WHERE sp2.sale_id = s.id
        ) AS products_list,
        s.products_subtotal               AS price,
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
      LEFT JOIN sale_services ss
        ON s.id = ss.sale_id
      LEFT JOIN sale_products sp
        ON s.id = sp.sale_id
      LEFT JOIN therapists t
        ON sp.therapist_id = t.id
      WHERE DATE(s.sale_date) BETWEEN :from_date AND :to_date
        AND ss.sale_id IS NULL
        AND sp.sale_id IS NOT NULL
      GROUP BY s.id
      ORDER BY s.sale_date DESC
    ");
    $stmt->bindValue('from_date', $from_date);
    $stmt->bindValue('to_date',   $to_date);
    $stmt->execute();
    $salesPrd = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($selectedReport === 'cashier_all') {
  // ─── C9: Cashier (All) with date filters ────────────────────────────────
 $from_date = $_GET['from_date'] ?? date('Y-m-d');
   $to_date   = $_GET['to_date']   ?? date('Y-m-d');

   $stmt = $pdo->prepare("
     SELECT
       s.id                                   AS sale_id,
       s.sale_date,
       COALESCE(
         CONCAT(c.first_name, ' ', c.last_name),
         'Walk-in'
       ) AS client_name,
       -- Services on that sale
       (
         SELECT GROUP_CONCAT(
           CONCAT(sv.name, ' (€', FORMAT(ss.unit_price,2), ')')
           SEPARATOR '<br>'
         )
         FROM sale_services ss
         JOIN services sv
           ON ss.service_id = sv.id
         WHERE ss.sale_id = s.id
       ) AS services_list,
       -- Products on that sale
       (
         SELECT GROUP_CONCAT(
           CONCAT(pv.name, ' (€', FORMAT(sp.unit_price,2), ')')
           SEPARATOR '<br>'
         )
         FROM sale_products sp
         JOIN products pv
           ON sp.product_id = pv.id
         WHERE sp.sale_id = s.id
       ) AS products_list,
       (s.services_subtotal + s.products_subtotal)   AS total_price,
       -- Amount paid
       (
         SELECT IFNULL(SUM(amount), 0)
         FROM sale_payments spay
         WHERE spay.sale_id = s.id
       ) AS paid_amount,
       -- Payment methods used
       (
         SELECT GROUP_CONCAT(payment_method SEPARATOR ', ')
         FROM sale_payments spay
         WHERE spay.sale_id = s.id
       ) AS pay_methods
     FROM sales s
     LEFT JOIN clients c
       ON s.client_id = c.id
     WHERE DATE(s.sale_date) BETWEEN :from_date AND :to_date
     ORDER BY s.sale_date DESC
   ");
   $stmt->bindValue('from_date', $from_date);
   $stmt->bindValue('to_date',   $to_date);
   $stmt->execute();
   $cashAll = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($selectedReport === 'cashier_staff') {
  // ─── C10: Cashier (Therapist) w/ date + therapist filters ──────────────
  $therapistId = (int)($_GET['therapist_id'] ?? 0);
    $from_date   = $_GET['from_date']    ?? date('Y-m-d');
    $to_date     = $_GET['to_date']      ?? date('Y-m-d');

    // If a specific therapist was chosen (>0), restrict to sales they appear on
    $whereTherapist = '';
    if ($therapistId > 0) {
      $whereTherapist = "
        AND (
          EXISTS (
            SELECT 1
            FROM sale_services ssx
            WHERE ssx.sale_id = s.id
              AND ssx.therapist_id = :tid
          )
          OR
          EXISTS (
            SELECT 1
            FROM sale_products spx
            WHERE spx.sale_id = s.id
              AND spx.therapist_id = :tid
          )
        )
      ";
    }

    $sql = "
      SELECT
        s.id                                    AS sale_id,
        s.sale_date,
        COALESCE(
          CONCAT(c.first_name, ' ', c.last_name),
          'Walk-in'
        ) AS client_name,
        ANY_VALUE(
          CONCAT(t.first_name, ' ', t.last_name)
        ) AS therapist_name,
        IFNULL(
          GROUP_CONCAT(
            DISTINCT CONCAT(sv.name, ' (€', FORMAT(ss.unit_price,2), ')')
            SEPARATOR '<br>'
          ),
          ''
        ) AS services_list,
        IFNULL(
          GROUP_CONCAT(
            DISTINCT CONCAT(pv.name, ' (€', FORMAT(sp.unit_price,2), ')')
            SEPARATOR '<br>'
          ),
          ''
        ) AS products_list,
        IFNULL(SUM(ss.line_total),0)
        + IFNULL(SUM(sp.line_total),0)        AS total_price,
        IFNULL((
          SELECT SUM(amount)
          FROM sale_payments spay
          WHERE spay.sale_id = s.id
        ), 0)                                AS paid_amount,
        (
          SELECT GROUP_CONCAT(payment_method SEPARATOR ', ')
          FROM sale_payments spay
          WHERE spay.sale_id = s.id
        )                                     AS pay_methods
      FROM sales s
      LEFT JOIN clients c
        ON s.client_id = c.id
      LEFT JOIN sale_services ss
        ON s.id = ss.sale_id
      LEFT JOIN services sv
        ON ss.service_id = sv.id
      LEFT JOIN sale_products sp
        ON s.id = sp.sale_id
      LEFT JOIN products pv
        ON sp.product_id = pv.id
      LEFT JOIN therapists t
        ON (t.id = ss.therapist_id OR t.id = sp.therapist_id)
      WHERE DATE(s.sale_date) BETWEEN :from_date AND :to_date
        $whereTherapist
      GROUP BY s.id
      ORDER BY s.sale_date DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue('from_date', $from_date);
    $stmt->bindValue('to_date',   $to_date);
    if ($therapistId > 0) {
      $stmt->bindValue('tid', $therapistId, PDO::PARAM_INT);
    }
    $stmt->execute();
    $cashStaff = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($selectedReport === 'cashier_service') {
  $serviceId  = (int)($_GET['service_id'] ?? 0);
    $from_date  = $_GET['from_date']  ?? date('Y-m-d');
    $to_date    = $_GET['to_date']    ?? date('Y-m-d');

    // Add this only if a specific service was chosen (>0)
    $whereService = '';
    if ($serviceId > 0) {
      $whereService = "AND EXISTS (
        SELECT 1
        FROM sale_services ssx
        WHERE ssx.sale_id = s.id
          AND ssx.service_id = :sid1
      )";
    }

    $sql = "
      SELECT
        s.id                                   AS sale_id,
        s.sale_date,
        COALESCE(
          CONCAT(c.first_name, ' ', c.last_name),
          'Walk-in'
        ) AS client_name,
        ANY_VALUE(
          CONCAT(t.first_name, ' ', t.last_name)
        ) AS therapist_name,
        (
          SELECT CONCAT(sv.name,' (€',FORMAT(ss2.unit_price,2),')')
          FROM sale_services ss2
          JOIN services sv
            ON ss2.service_id = sv.id
          WHERE ss2.sale_id = s.id
            AND ss2.service_id = :sid2
          LIMIT 1
        ) AS service_name,
        IFNULL(
          GROUP_CONCAT(
            DISTINCT CONCAT(pv.name,' (€',FORMAT(sp.unit_price,2),')')
            SEPARATOR '<br>'
          ),
          ''
        ) AS products_list,
        (
          SELECT IFNULL(SUM(ss3.line_total), 0)
          FROM sale_services ss3
          WHERE ss3.sale_id = s.id
            AND ss3.service_id = :sid3
        )
        +
        (
          SELECT IFNULL(SUM(sp3.line_total), 0)
          FROM sale_products sp3
          WHERE sp3.sale_id = s.id
        ) AS total_price,
        IFNULL((
          SELECT SUM(amount)
          FROM sale_payments spay
          WHERE spay.sale_id = s.id
        ),0) AS paid_amount,
        (
          SELECT GROUP_CONCAT(payment_method SEPARATOR ', ')
          FROM sale_payments spay
          WHERE spay.sale_id = s.id
        ) AS pay_methods
      FROM sales s
      LEFT JOIN clients c
        ON s.client_id = c.id
      LEFT JOIN sale_services ss
        ON s.id = ss.sale_id
      LEFT JOIN therapists t
        ON ss.therapist_id = t.id
      LEFT JOIN sale_products sp
        ON s.id = sp.sale_id
      LEFT JOIN products pv
        ON sp.product_id = pv.id
      WHERE DATE(s.sale_date) BETWEEN :from_date AND :to_date
        $whereService
      GROUP BY s.id
      ORDER BY s.sale_date DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue('from_date', $from_date);
    $stmt->bindValue('to_date',   $to_date);

    if ($serviceId > 0) {
      $stmt->bindValue('sid1', $serviceId, PDO::PARAM_INT);
      $stmt->bindValue('sid2', $serviceId, PDO::PARAM_INT);
      $stmt->bindValue('sid3', $serviceId, PDO::PARAM_INT);
    }

    $stmt->execute();
    $cashService = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($selectedReport === 'cashier_products') {
  $productId  = (int)($_GET['product_id'] ?? 0);
    $from_date  = $_GET['from_date']  ?? date('Y-m-d');
    $to_date    = $_GET['to_date']    ?? date('Y-m-d');

    // Only include the filter if productId > 0
    $whereProduct = '';
    if ($productId > 0) {
      $whereProduct = "AND EXISTS (
        SELECT 1
        FROM sale_products spx
        WHERE spx.sale_id = s.id
          AND spx.product_id = :pid1
      )";
    }

    $sql = "
      SELECT
        s.id                                   AS sale_id,
        s.sale_date,
        COALESCE(
          CONCAT(c.first_name, ' ', c.last_name),
          'Walk-in'
        ) AS client_name,
        ANY_VALUE(
          CONCAT(t.first_name, ' ', t.last_name)
        ) AS therapist_name,
        IFNULL(
          GROUP_CONCAT(
            DISTINCT CONCAT(sv.name, ' (€', FORMAT(ss.unit_price,2), ')')
            SEPARATOR '<br>'
          ),
          ''
        ) AS services_list,
        (
          SELECT CONCAT(pv2.name,' (€',FORMAT(sp2.unit_price,2),')')
          FROM sale_products sp2
          JOIN products pv2
            ON sp2.product_id = pv2.id
          WHERE sp2.sale_id = s.id
            AND sp2.product_id = :pid2
          LIMIT 1
        ) AS product_name,
        (
          SELECT IFNULL(SUM(ss2.line_total), 0)
          FROM sale_services ss2
          WHERE ss2.sale_id = s.id
        )
        +
        (
          SELECT IFNULL(SUM(sp3.line_total), 0)
          FROM sale_products sp3
          WHERE sp3.sale_id = s.id
            AND sp3.product_id = :pid3
        ) AS total_price,
        IFNULL((
          SELECT SUM(amount)
          FROM sale_payments spay
          WHERE spay.sale_id = s.id
        ),0) AS paid_amount,
        (
          SELECT GROUP_CONCAT(payment_method SEPARATOR ', ')
          FROM sale_payments spay
          WHERE spay.sale_id = s.id
        ) AS pay_methods
      FROM sales s
      LEFT JOIN clients c
        ON s.client_id = c.id
      LEFT JOIN sale_services ss
        ON s.id = ss.sale_id
      LEFT JOIN services sv
        ON ss.service_id = sv.id
      LEFT JOIN therapists t
        ON ss.therapist_id = t.id
      LEFT JOIN sale_products sp
        ON s.id = sp.sale_id
      LEFT JOIN products pv
        ON sp.product_id = pv.id
      WHERE DATE(s.sale_date) BETWEEN :from_date AND :to_date
        $whereProduct
      GROUP BY s.id
      ORDER BY s.sale_date DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue('from_date', $from_date);
    $stmt->bindValue('to_date',   $to_date);

    if ($productId > 0) {
      $stmt->bindValue('pid1', $productId, PDO::PARAM_INT);
      $stmt->bindValue('pid2', $productId, PDO::PARAM_INT);
      $stmt->bindValue('pid3', $productId, PDO::PARAM_INT);
    }

    $stmt->execute();
    $cashProd = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($selectedReport === 'z_reports') {
  // ─── C13a: Z Reports → only show form here; no data fetch yet.
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $from_date = $_POST['from_date'] ?? date('Y-m-d');
      $to_date   = $_POST['to_date']   ?? date('Y-m-d');

      // 1) Insert a new Z‐report (report_number left at 0 for now)
      $pdo->beginTransaction();
      $stmt = $pdo->prepare("
        INSERT INTO z_reports (report_number, date_from, date_to, created_at)
        VALUES (0, :df, :dt, NOW())
      ");
      $stmt->execute(['df' => $from_date, 'dt' => $to_date]);
      $newId = (int)$pdo->lastInsertId();

      // 2) Update report_number to match the newly created id
      $pdo->prepare("
        UPDATE z_reports
        SET report_number = :rn
        WHERE id = :rid
      ")->execute([
        'rn'  => $newId,
        'rid' => $newId
      ]);

      // 3) Write an audit‐trail entry in reports_log (JSON with z_report_id)
      $params = json_encode(['z_report_id' => $newId]);
      $pdo->prepare("
        INSERT INTO reports_log (report_name, parameters, created_at)
        VALUES ('z_report', :p, NOW())
      ")->execute(['p' => $params]);

      $pdo->commit();

      header("Location: zreport_print.php?report_id={$newId}");
      exit();
    }

} elseif ($selectedReport === 'generated_zreports') {
  // ─── C13b: List of Generated Z Reports
  $stmt = $pdo->query("
    SELECT id, report_number, date_from, date_to, created_at
    FROM z_reports
    ORDER BY created_at DESC
  ");
  $genZ = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <!-- Page Header -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1>Reports</h1></div>
        <div class="col-sm-6 text-right">
          <?php if ($selectedReport === 'z_reports'): ?>
            <button
              class="btn btn-primary"
              data-toggle="modal"
              data-target="#zReportModal"
            >
              <i class="fas fa-file-alt"></i> Generate Z Report
            </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- Main Content -->
  <section class="content">
    <div class="container-fluid">

      <!-- ───────────────── Reports Dropdown ────────────────────────────────────── -->
      <div class="row mb-3">
        <div class="col-md-4">
          <select
            id="reportType"
            class="form-control"
            onchange="location = '?report=' + this.value;"
          >
            <option value="" <?= $selectedReport === '' ? 'selected' : '' ?>>-- Select a Report --</option>
            <option value="top_clients_appts"       <?= $selectedReport === 'top_clients_appts'       ? 'selected' : '' ?>>Top 10 Clients (Appointments)</option>
            <option value="top_clients_payments"    <?= $selectedReport === 'top_clients_payments'    ? 'selected' : '' ?>>Top 10 Clients (Payments)</option>
            <option value="top_therapists_appts"    <?= $selectedReport === 'top_therapists_appts'    ? 'selected' : '' ?>>Top 10 Therapists (Appointments)</option>
            <option value="top_therapists_payments" <?= $selectedReport === 'top_therapists_payments' ? 'selected' : '' ?>>Top 10 Therapists (Payments)</option>
            <option value="first_appointments"      <?= $selectedReport === 'first_appointments'      ? 'selected' : '' ?>>First Appointments (New Clients)</option>
            <option value="gender_distribution"     <?= $selectedReport === 'gender_distribution'     ? 'selected' : '' ?>>Gender Distribution</option>
            <option value="sales_appointments"      <?= $selectedReport === 'sales_appointments'      ? 'selected' : '' ?>>Sales (Appointments)</option>
            <option value="sales_products"          <?= $selectedReport === 'sales_products'          ? 'selected' : '' ?>>Sales (Products)</option>
            <option value="cashier_all"             <?= $selectedReport === 'cashier_all'             ? 'selected' : '' ?>>Cashier (All)</option>
            <option value="cashier_staff"           <?= $selectedReport === 'cashier_staff'           ? 'selected' : '' ?>>Cashier (Therapist)</option>
            <option value="cashier_service"         <?= $selectedReport === 'cashier_service'         ? 'selected' : '' ?>>Cashier (Service)</option>
            <option value="cashier_products"        <?= $selectedReport === 'cashier_products'        ? 'selected' : '' ?>>Cashier (Products)</option>
            <option value="z_reports"               <?= $selectedReport === 'z_reports'               ? 'selected' : '' ?>>Z Reports</option>
            <option value="generated_zreports"      <?= $selectedReport === 'generated_zreports'      ? 'selected' : '' ?>>Generated Z Reports</option>
          </select>
        </div>
      </div>

      <!-- ───────────────── Report Contents ───────────────────────────────────────── -->

      <?php if ($selectedReport === 'top_clients_appts'): ?>
        <!-- Top 10 Clients (Appointments) with date filter -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Top 10 Clients (Appointments)</h3>
            <form method="GET" class="form-inline float-right">
              <input type="hidden" name="report" value="top_clients_appts">
              <div class="form-group mr-2">
                <label for="from_date_tc" class="mr-2">From:</label>
                <input
                  type="date"
                  id="from_date_tc"
                  name="from_date"
                  class="form-control"
                  value="<?= htmlspecialchars($_GET['from_date'] ?? date('Y-m-d')) ?>"
                  required
                >
              </div>
              <div class="form-group mr-2">
                <label for="to_date_tc" class="mr-2">To:</label>
                <input
                  type="date"
                  id="to_date_tc"
                  name="to_date"
                  class="form-control"
                  value="<?= htmlspecialchars($_GET['to_date'] ?? date('Y-m-d')) ?>"
                  required
                >
              </div>
              <button type="submit" class="btn btn-sm btn-primary">Apply Filter</button>
            </form>
          </div>
          <div class="card-body p-0">
            <table class="table table-striped">
              <thead>
                <tr><th>#</th><th>Client</th><th>Appointments</th></tr>
              </thead>
              <tbody>
                <?php foreach ($topClientsAppts as $i => $row): ?>
                  <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($row['client_name']) ?></td>
                    <td><?= $row['appt_count'] ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($selectedReport === 'top_clients_payments'): ?>
        <!-- Top 10 Clients (Payments) with date filter -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Top 10 Clients (Payments)</h3>
            <form method="GET" class="form-inline float-right">
              <input type="hidden" name="report" value="top_clients_payments">
              <div class="form-group mr-2">
                <label for="from_date_tcp" class="mr-2">From:</label>
                <input
                  type="date"
                  id="from_date_tcp"
                  name="from_date"
                  class="form-control"
                  value="<?= htmlspecialchars($_GET['from_date'] ?? date('Y-m-d')) ?>"
                  required
                >
              </div>
              <div class="form-group mr-2">
                <label for="to_date_tcp" class="mr-2">To:</label>
                <input
                  type="date"
                  id="to_date_tcp"
                  name="to_date"
                  class="form-control"
                  value="<?= htmlspecialchars($_GET['to_date'] ?? date('Y-m-d')) ?>"
                  required
                >
              </div>
              <button type="submit" class="btn btn-sm btn-primary">Apply Filter</button>
            </form>
          </div>
          <div class="card-body p-0">
            <table class="table table-striped">
              <thead>
                <tr><th>#</th><th>Client</th><th>Total Paid (€)</th></tr>
              </thead>
              <tbody>
                <?php foreach ($topClientsPay as $i => $row): ?>
                  <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($row['client_name']) ?></td>
                    <td><?= number_format($row['total_paid'], 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($selectedReport === 'top_therapists_appts'): ?>
        <!-- Top 10 Therapists (Appointments) with date filter -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Top 10 Therapists (Appointments)</h3>
            <form method="GET" class="form-inline float-right">
              <input type="hidden" name="report" value="top_therapists_appts">
              <div class="form-group mr-2">
                <label for="from_date_tta" class="mr-2">From:</label>
                <input
                  type="date"
                  id="from_date_tta"
                  name="from_date"
                  class="form-control"
                  value="<?= htmlspecialchars($_GET['from_date'] ?? date('Y-m-d')) ?>"
                  required
                >
              </div>
              <div class="form-group mr-2">
                <label for="to_date_tta" class="mr-2">To:</label>
                <input
                  type="date"
                  id="to_date_tta"
                  name="to_date"
                  class="form-control"
                  value="<?= htmlspecialchars($_GET['to_date'] ?? date('Y-m-d')) ?>"
                  required
                >
              </div>
              <button type="submit" class="btn btn-sm btn-primary">Apply Filter</button>
            </form>
          </div>
          <div class="card-body p-0">
            <table class="table table-striped">
              <thead>
                <tr><th>#</th><th>Therapist</th><th>Appointments</th></tr>
              </thead>
              <tbody>
                <?php foreach ($topTherapistsAppts as $i => $row): ?>
                  <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($row['therapist_name']) ?></td>
                    <td><?= $row['appt_count'] ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($selectedReport === 'top_therapists_payments'): ?>
        <!-- Top 10 Therapists (Payments) with date filter -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Top 10 Therapists (Payments)</h3>
            <form method="GET" class="form-inline float-right">
              <input type="hidden" name="report" value="top_therapists_payments">
              <div class="form-group mr-2">
                <label for="from_date_ttp" class="mr-2">From:</label>
                <input
                  type="date"
                  id="from_date_ttp"
                  name="from_date"
                  class="form-control"
                  value="<?= htmlspecialchars($_GET['from_date'] ?? date('Y-m-d')) ?>"
                  required
                >
              </div>
              <div class="form-group mr-2">
                <label for="to_date_ttp" class="mr-2">To:</label>
                <input
                  type="date"
                  id="to_date_ttp"
                  name="to_date"
                  class="form-control"
                  value="<?= htmlspecialchars($_GET['to_date'] ?? date('Y-m-d')) ?>"
                  required
                >
              </div>
              <button type="submit" class="btn btn-sm btn-primary">Apply Filter</button>
            </form>
          </div>
          <div class="card-body p-0">
            <table class="table table-striped">
              <thead>
                <tr><th>#</th><th>Therapist</th><th>Total Revenue (€)</th></tr>
              </thead>
              <tbody>
                <?php foreach ($topTherapistsPay as $i => $row): ?>
                  <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($row['therapist_name']) ?></td>
                    <td><?= number_format($row['total_revenue'], 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($selectedReport === 'first_appointments'): ?>
        <!-- First Appointment (per Client) with date filter -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">First Appointment (per Client)</h3>
            <form method="GET" class="form-inline float-right">
              <input type="hidden" name="report" value="first_appointments">
              <div class="form-group mr-2">
                <label for="from_date_fa" class="mr-2">From:</label>
                <input
                  type="date"
                  id="from_date_fa"
                  name="from_date"
                  class="form-control"
                  value="<?= htmlspecialchars($_GET['from_date'] ?? date('Y-m-d')) ?>"
                  required
                >
              </div>
              <div class="form-group mr-2">
                <label for="to_date_fa" class="mr-2">To:</label>
                <input
                  type="date"
                  id="to_date_fa"
                  name="to_date"
                  class="form-control"
                  value="<?= htmlspecialchars($_GET['to_date'] ?? date('Y-m-d')) ?>"
                  required
                >
              </div>
              <button type="submit" class="btn btn-sm btn-primary">Apply Filter</button>
            </form>
          </div>
          <div class="card-body p-0">
            <table class="table table-striped">
              <thead>
                <tr><th>#</th><th>Client</th><th>First Appointment</th></tr>
              </thead>
              <tbody>
                <?php foreach ($firstAppts as $i => $row): ?>
                  <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($row['client_name']) ?></td>
                    <td><?= htmlspecialchars($row['first_appt_date']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($selectedReport === 'gender_distribution'): ?>
        <!-- Clients by Gender with date filter (based on created_at) -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Clients by Gender</h3>
            <form method="GET" class="form-inline float-right">
              <input type="hidden" name="report" value="gender_distribution">
              <div class="form-group mr-2">
                <label for="from_date_gd" class="mr-2">From:</label>
                <input
                  type="date"
                  id="from_date_gd"
                  name="from_date"
                  class="form-control"
                  value="<?= htmlspecialchars($_GET['from_date'] ?? date('Y-m-d')) ?>"
                  required
                >
              </div>
              <div class="form-group mr-2">
                <label for="to_date_gd" class="mr-2">To:</label>
                <input
                  type="date"
                  id="to_date_gd"
                  name="to_date"
                  class="form-control"
                  value="<?= htmlspecialchars($_GET['to_date'] ?? date('Y-m-d')) ?>"
                  required
                >
              </div>
              <button type="submit" class="btn btn-sm btn-primary">Apply Filter</button>
            </form>
          </div>
          <div class="card-body p-0">
            <table class="table table-striped">
              <thead>
                <tr><th>Gender</th><th>Count</th></tr>
              </thead>
              <tbody>
                <?php foreach ($genderDist as $row): ?>
                  <tr>
                    <td><?= htmlspecialchars($row['gender']) ?></td>
                    <td><?= $row['count'] ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($selectedReport === 'sales_appointments'): ?>
        <!-- Sales (Appointments) with date filter -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Sales (Appointments)</h3>
            <form method="GET" class="form-inline float-right">
              <input type="hidden" name="report" value="sales_appointments">
              <div class="form-group mr-2">
                <label for="from_date_sa" class="mr-2">From:</label>
                <input
                  type="date"
                  id="from_date_sa"
                  name="from_date"
                  class="form-control"
                  value="<?= htmlspecialchars($_GET['from_date'] ?? date('Y-m-d')) ?>"
                  required
                >
              </div>
              <div class="form-group mr-2">
                <label for="to_date_sa" class="mr-2">To:</label>
                <input
                  type="date"
                  id="to_date_sa"
                  name="to_date"
                  class="form-control"
                  value="<?= htmlspecialchars($_GET['to_date'] ?? date('Y-m-d')) ?>"
                  required
                >
              </div>
              <button type="submit" class="btn btn-sm btn-primary">Apply Filter</button>
            </form>
          </div>
          <div class="card-body p-0">
            <table class="table table-bordered table-sm">
              <thead>
                <tr>
                  <th>ID</th><th>Date</th><th>Client</th><th>Therapist</th>
                  <th>Services</th><th>Products</th><th>Price (€)</th>
                  <th>Paid (€)</th><th>Pay Method</th><th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($salesAppts as $row): ?>
                  <tr>
                    <td><?= $row['sale_id'] ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($row['sale_date'])) ?></td>
                    <td><?= htmlspecialchars($row['client_name']) ?></td>
                    <td><?= htmlspecialchars($row['therapist_name']) ?></td>
                    <td><?= $row['services_list'] ?: '&mdash;' ?></td>
                    <td><?= $row['products_list'] ?: '&mdash;' ?></td>
                    <td><?= number_format($row['price'],2) ?></td>
                    <td><?= number_format($row['paid_amount'],2) ?></td>
                    <td><?= htmlspecialchars($row['pay_methods']) ?></td>
                    <td>
                      <a href="cashier.php?action=delete&id=<?= $row['sale_id'] ?>"
                         class="btn btn-sm btn-danger"
                         onclick="return confirm('Delete sale #<?= $row['sale_id'] ?>?');"
                         title="Delete">
                        <i class="fas fa-trash"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($selectedReport === 'sales_products'): ?>
        <!-- Sales (Products) with date filter -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Sales (Products)</h3>
            <form method="GET" class="form-inline float-right">
              <input type="hidden" name="report" value="sales_products">
              <div class="form-group mr-2">
                <label for="from_date_sp" class="mr-2">From:</label>
                <input
                  type="date"
                  id="from_date_sp"
                  name="from_date"
                  class="form-control"
                  value="<?= htmlspecialchars($_GET['from_date'] ?? date('Y-m-d')) ?>"
                  required
                >
              </div>
              <div class="form-group mr-2">
                <label for="to_date_sp" class="mr-2">To:</label>
                <input
                  type="date"
                  id="to_date_sp"
                  name="to_date"
                  class="form-control"
                  value="<?= htmlspecialchars($_GET['to_date'] ?? date('Y-m-d')) ?>"
                  required
                >
              </div>
              <button type="submit" class="btn btn-sm btn-primary">Apply Filter</button>
            </form>
          </div>
          <div class="card-body p-0">
            <table class="table table-bordered table-sm">
              <thead>
                <tr>
                  <th>ID</th><th>Date</th><th>Client</th><th>Therapist</th>
                  <th>Products</th><th>Price (€)</th><th>Paid (€)</th><th>Pay Method</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($salesPrd as $row): ?>
                  <tr>
                    <td><?= $row['sale_id'] ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($row['sale_date'])) ?></td>
                    <td><?= htmlspecialchars($row['client_name']) ?></td>
                    <td><?= htmlspecialchars($row['therapist_name']) ?></td>
                    <td><?= $row['products_list'] ?: '&mdash;' ?></td>
                    <td><?= number_format($row['price'],2) ?></td>
                    <td><?= number_format($row['paid_amount'],2) ?></td>
                    <td><?= htmlspecialchars($row['pay_methods']) ?></td>
                    <td>
                      <a href="cashier.php?action=delete&id=<?= $row['sale_id'] ?>"
                         class="btn btn-sm btn-danger"
                         onclick="return confirm('Delete sale #<?= $row['sale_id'] ?>?');"
                         title="Delete">
                        <i class="fas fa-trash"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($selectedReport === 'cashier_all'): ?>
        <!-- Cashier (All) with date filter -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Cashier (All)</h3>
            <form method="GET" class="form-inline float-right">
              <input type="hidden" name="report" value="cashier_all">
              <div class="form-group mr-2">
                <label for="from_date_ca" class="mr-2">From:</label>
                <input
                  type="date"
                  id="from_date_ca"
                  name="from_date"
                  class="form-control"
                  value="<?= htmlspecialchars($_GET['from_date'] ?? date('Y-m-d')) ?>"
                  required
                >
              </div>
              <div class="form-group mr-2">
                <label for="to_date_ca" class="mr-2">To:</label>
                <input
                  type="date"
                  id="to_date_ca"
                  name="to_date"
                  class="form-control"
                  value="<?= htmlspecialchars($_GET['to_date'] ?? date('Y-m-d')) ?>"
                  required
                >
              </div>
              <button type="submit" class="btn btn-sm btn-primary">Apply Filter</button>
            </form>
          </div>
          <div class="card-body p-0">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th>#</th><th>Date</th><th>Client</th><th>Services</th>
                  <th>Products</th><th>Total (€)</th><th>Paid (€)</th><th>Method</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($cashAll as $i => $row): ?>
                  <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($row['sale_date'])) ?></td>
                    <td><?= htmlspecialchars($row['client_name']) ?></td>
                    <td><?= $row['services_list'] ?: '&mdash;' ?></td>
                    <td><?= $row['products_list'] ?: '&mdash;' ?></td>
                    <td><?= number_format($row['total_price'],2) ?></td>
                    <td><?= number_format($row['paid_amount'],2) ?></td>
                    <td><?= htmlspecialchars($row['pay_methods']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <div class="mt-3">
              <?php
                $sums = [
                  'Cash'          => 0.0,
                  'Credit Card'   => 0.0,
                  'Revolut'       => 0.0,
                  'Cheque'        => 0.0,
                  'Bank Transfer' => 0.0
                ];
                foreach ($cashAll as $r) {
                  $paid = (float)$r['paid_amount'];
                  foreach (explode(',', $r['pay_methods']) as $m) {
                    $method = trim($m);
                    if (isset($sums[$method])) {
                      $sums[$method] += $paid;
                    }
                  }
                }
              ?>
              <p><strong>CASH:</strong> € <?= number_format($sums['Cash'],2) ?></p>
              <p><strong>CREDIT CARD:</strong> € <?= number_format($sums['Credit Card'],2) ?></p>
              <p><strong>REVOLUT:</strong> € <?= number_format($sums['Revolut'],2) ?></p>
              <p><strong>CHEQUE:</strong> € <?= number_format($sums['Cheque'],2) ?></p>
              <p><strong>BANK TRANSFER:</strong> € <?= number_format($sums['Bank Transfer'],2) ?></p>
              <hr>
              <p><strong>TOTAL:</strong> € <?= number_format(array_sum($sums),2) ?></p>
            </div>
          </div>
        </div>

      <?php elseif ($selectedReport === 'cashier_staff'): ?>
        <!-- Cashier (Therapist) with date + therapist filter -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Cashier (Therapist)</h3>
            <form method="GET" class="form-inline float-right">
              <input type="hidden" name="report" value="cashier_staff">
              <div class="form-group mr-2">
                <label for="therapist_id_cs" class="mr-2">Therapist:</label>
                <select id="therapist_id_cs" name="therapist_id" class="form-control select2" style="width:150px">
                  <option value="0">-- All --</option>
                  <?php
                    $tStmt = $pdo->query("SELECT id, first_name, last_name FROM therapists ORDER BY first_name");
                    $therapists = $tStmt->fetchAll(PDO::FETCH_ASSOC);
                    $selTid = $_GET['therapist_id'] ?? 0;
                    foreach ($therapists as $t) {
                      $sel = ($t['id'] == $selTid) ? 'selected' : '';
                      echo "<option value=\"{$t['id']}\" $sel>{$t['first_name']} {$t['last_name']}</option>";
                    }
                  ?>
                </select>
              </div>
              <div class="form-group mr-2">
                <label for="from_date_cs" class="mr-2">From:</label>
                <input
                  type="date"
                  id="from_date_cs"
                  name="from_date"
                  class="form-control"
                  value="<?= htmlspecialchars($_GET['from_date'] ?? date('Y-m-d')) ?>"
                  required
                >
              </div>
              <div class="form-group mr-2">
                <label for="to_date_cs" class="mr-2">To:</label>
                <input
                  type="date"
                  id="to_date_cs"
                  name="to_date"
                  class="form-control"
                  value="<?= htmlspecialchars($_GET['to_date'] ?? date('Y-m-d')) ?>"
                  required
                >
              </div>
              <button type="submit" class="btn btn-sm btn-primary">Apply Filter</button>
            </form>
          </div>
          <div class="card-body p-0">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th>#</th><th>Date</th><th>Client</th><th>Services</th>
                  <th>Products</th><th>Total (€)</th><th>Paid (€)</th><th>Method</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $i = 1;
                foreach ($cashStaff as $row):
                ?>
                  <tr>
                    <td><?= $i++ ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($row['sale_date'])) ?></td>
                    <td><?= htmlspecialchars($row['client_name']) ?></td>
                    <td><?= $row['services_list'] ?: '&mdash;' ?></td>
                    <td><?= $row['products_list'] ?: '&mdash;' ?></td>
                    <td><?= number_format($row['total_price'],2) ?></td>
                    <td><?= number_format($row['paid_amount'],2) ?></td>
                    <td><?= htmlspecialchars($row['pay_methods']) ?></td>
                  </tr>
                <?php
                endforeach;
                ?>
              </tbody>
            </table>
          </div>
          <div class="card-footer text-right">
            <strong>
              TOTAL: €
              <?= number_format(array_reduce($cashStaff, function($sum,$r){
                   return $sum + (float)$r['total_price'];
              }, 0), 2) ?>
            </strong>
          </div>
        </div>

      <?php elseif ($selectedReport === 'cashier_service'): ?>
        <!-- Cashier (Service) with date + service filter -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Cashier (Service)</h3>
            <form method="GET" class="form-inline float-right">
              <input type="hidden" name="report" value="cashier_service">
              <div class="form-group mr-2">
                <label for="service_id_cs" class="mr-2">Service:</label>
                <select id="service_id_cs" name="service_id" class="form-control select2" style="width:150px">
                  <option value="0">-- All --</option>
                  <?php
                    $sStmt = $pdo->query("SELECT id, name FROM services ORDER BY name");
                    $services = $sStmt->fetchAll(PDO::FETCH_ASSOC);
                    $selSid = $_GET['service_id'] ?? 0;
                    foreach ($services as $s) {
                      $sel = ($s['id'] == $selSid) ? 'selected' : '';
                      echo "<option value=\"{$s['id']}\" $sel>{$s['name']}</option>";
                    }
                  ?>
                </select>
              </div>
              <div class="form-group mr-2">
                <label for="from_date_scs" class="mr-2">From:</label>
                <input
                  type="date"
                  id="from_date_scs"
                  name="from_date"
                  class="form-control"
                  value="<?= htmlspecialchars($_GET['from_date'] ?? date('Y-m-d')) ?>"
                  required
                >
              </div>
              <div class="form-group mr-2">
                <label for="to_date_scs" class="mr-2">To:</label>
                <input
                  type="date"
                  id="to_date_scs"
                  name="to_date"
                  class="form-control"
                  value="<?= htmlspecialchars($_GET['to_date'] ?? date('Y-m-d')) ?>"
                  required
                >
              </div>
              <button type="submit" class="btn btn-sm btn-primary">Apply Filter</button>
            </form>
          </div>
          <div class="card-body p-0">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th>#</th><th>Date</th><th>Client</th><th>Service</th>
                  <th>Products</th><th>Total (€)</th><th>Paid (€)</th><th>Method</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $i = 1;
                foreach ($cashService as $row):
                ?>
                  <tr>
                    <td><?= $i++ ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($row['sale_date'])) ?></td>
                    <td><?= htmlspecialchars($row['client_name']) ?></td>
                    <td><?= htmlspecialchars($row['service_name'] ?: '&mdash;') ?></td>
                    <td><?= $row['products_list'] ?: '&mdash;' ?></td>
                    <td><?= number_format($row['total_price'],2) ?></td>
                    <td><?= number_format($row['paid_amount'],2) ?></td>
                    <td><?= htmlspecialchars($row['pay_methods']) ?></td>
                  </tr>
                <?php
                endforeach;
                ?>
              </tbody>
            </table>
          </div>
          <div class="card-footer text-right">
            <strong>
              TOTAL: €
              <?= number_format(array_reduce($cashService, function($sum,$r){
                   return $sum + (float)$r['total_price'];
              }, 0), 2) ?>
            </strong>
          </div>
        </div>

      <?php elseif ($selectedReport === 'cashier_products'): ?>
        <!-- Cashier (Products) with date + product filter -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Cashier (Products)</h3>
            <form method="GET" class="form-inline float-right">
              <input type="hidden" name="report" value="cashier_products">
              <div class="form-group mr-2">
                <label for="product_id_cp" class="mr-2">Product:</label>
                <select id="product_id_cp" name="product_id" class="form-control select2" style="width:150px">
                  <option value="0">-- All --</option>
                  <?php
                    $prStmt = $pdo->query("SELECT id, name FROM products ORDER BY name");
                    $products = $prStmt->fetchAll(PDO::FETCH_ASSOC);
                    $selPid = $_GET['product_id'] ?? 0;
                    foreach ($products as $p) {
                      $sel = ($p['id'] == $selPid) ? 'selected' : '';
                      echo "<option value=\"{$p['id']}\" $sel>{$p['name']}</option>";
                    }
                  ?>
                </select>
              </div>
              <div class="form-group mr-2">
                <label for="from_date_cpr" class="mr-2">From:</label>
                <input
                  type="date"
                  id="from_date_cpr"
                  name="from_date"
                  class="form-control"
                  value="<?= htmlspecialchars($_GET['from_date'] ?? date('Y-m-d')) ?>"
                  required
                >
              </div>
              <div class="form-group mr-2">
                <label for="to_date_cpr" class="mr-2">To:</label>
                <input
                  type="date"
                  id="to_date_cpr"
                  name="to_date"
                  class="form-control"
                  value="<?= htmlspecialchars($_GET['to_date'] ?? date('Y-m-d')) ?>"
                  required
                >
              </div>
              <button type="submit" class="btn btn-sm btn-primary">Apply Filter</button>
            </form>
          </div>
          <div class="card-body p-0">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th>#</th><th>Date</th><th>Client</th><th>Services</th>
                  <th>Product</th><th>Total (€)</th><th>Paid (€)</th><th>Method</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $i = 1;
                foreach ($cashProd as $row):
                ?>
                  <tr>
                    <td><?= $i++ ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($row['sale_date'])) ?></td>
                    <td><?= htmlspecialchars($row['client_name']) ?></td>
                    <td><?= $row['services_list'] ?: '&mdash;' ?></td>
                    <td><?= htmlspecialchars($row['product_name'] ?: '&mdash;') ?></td>
                    <td><?= number_format($row['total_price'],2) ?></td>
                    <td><?= number_format($row['paid_amount'],2) ?></td>
                    <td><?= htmlspecialchars($row['pay_methods']) ?></td>
                  </tr>
                <?php
                endforeach;
                ?>
              </tbody>
            </table>
          </div>
          <div class="card-footer text-right">
            <strong>
              TOTAL: €
              <?= number_format(array_reduce($cashProd, function($sum,$r){
                   return $sum + (float)$r['total_price'];
              }, 0), 2) ?>
            </strong>
          </div>
        </div>

      <?php elseif ($selectedReport === 'z_reports'): ?>
        <!-- Z Reports (date‐picker + Generate button) -->
        <div class="card">
          <div class="card-header"><h3 class="card-title">Z Reports</h3></div>
          <div class="card-body">
            <form method="POST" action="reports.php?report=z_reports" class="form-inline mb-3">
              <label for="zr_date_from" class="mr-2">From:</label>
              <input type="date" id="zr_date_from" name="zr_date_from" class="form-control mr-3" value="<?= date('Y-m-d') ?>" required>
              <label for="zr_date_to" class="mr-2">To:</label>
              <input type="date" id="zr_date_to" name="zr_date_to" class="form-control mr-3" value="<?= date('Y-m-d') ?>" required>
              <button type="submit" name="generate_zreport" class="btn btn-primary">
                <i class="fas fa-file-invoice"></i> Generate Z Report
              </button>
            </form>
            <p>Select a date range and click “Generate Z Report” to produce a Z report.</p>
          </div>
        </div>

      <?php elseif ($selectedReport === 'generated_zreports'): ?>
        <!-- Generated Z Reports List -->
        <div class="card">
          <div class="card-header"><h3 class="card-title">Generated Z Reports</h3></div>
          <div class="card-body p-0">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th>ID</th><th>Report No.</th><th>Date From</th><th>Date To</th>
                  <th>Generated At</th><th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($genZ as $row): ?>
                  <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= $row['report_number'] ?></td>
                    <td><?= htmlspecialchars($row['date_from']) ?></td>
                    <td><?= htmlspecialchars($row['date_to']) ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></td>
                    <td>
                      <button
                        type="button"
                        class="btn btn-sm btn-secondary"
                        onclick="window.open(
                          'zreport_print.php?report_id=<?= $row['id'] ?>',
                          '_blank',
                          'toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=320,height=600'
                        ); return false;"
                        title="Print/Download"
                      >
                        <i class="fas fa-print"></i>
                      </button>
                      <a
                        href="reports.php?action=delete_zreport&id=<?= $row['id'] ?>"
                        class="btn btn-sm btn-danger"
                        onclick="return confirm('Delete this Z Report?');"
                        title="Delete"
                      >
                        <i class="fas fa-trash"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </section>
</div>

<?php include '../includes/footer.php'; ?>

<!-- ─────────────────────────────────────────────────────────────────────────────── -->
<!-- Required JS libraries -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
  // Initialize Select2 on any <select class="select2">
  if ($.fn.select2) {
    $('.select2').select2({ width: '100%' });
  }
});
</script>
</body>
</html>
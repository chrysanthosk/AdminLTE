<?php
// pages/reports_export.php — CSV download for all reports
require_once __DIR__ . '/../auth.php';
requirePermission($pdo, 'reports.view');

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

$report = $_GET['report'] ?? '';
if (!in_array($report, $validReports, true)) {
  http_response_code(400);
  die("Invalid report");
}

// common filters
$from = $_GET['from_date'] ?? date('Y-m-d');
$to   = $_GET['to_date']   ?? date('Y-m-d');

switch ($report) {
  case 'sales_products':
    $sql = "
      SELECT
        s.id AS sale_id,
        s.sale_date,
        COALESCE(CONCAT(c.first_name,' ',c.last_name),'Walk-in') AS client_name,
        ANY_VALUE(CONCAT(t.first_name,' ',t.last_name)) AS therapist_name,
        (
          SELECT GROUP_CONCAT(CONCAT(pv.name,' (€',FORMAT(sp2.unit_price,2),')') SEPARATOR '\\n')
          FROM sale_products sp2
          JOIN products pv ON sp2.product_id = pv.id
          WHERE sp2.sale_id = s.id
        ) AS products_list,
        s.products_subtotal AS price,
        (
          SELECT IFNULL(SUM(amount),0)
          FROM sale_payments spay
          WHERE spay.sale_id = s.id
        ) AS paid_amount,
        (
          SELECT GROUP_CONCAT(payment_method SEPARATOR ', ')
          FROM sale_payments spay
          WHERE spay.sale_id = s.id
        ) AS pay_methods
      FROM sales s
      LEFT JOIN clients c     ON s.client_id = c.id
      LEFT JOIN therapists t  ON s.id = t.id    /* adjust join if needed */
      WHERE DATE(s.sale_date) BETWEEN :from AND :to
        AND EXISTS (
          SELECT 1 FROM sale_products sp3 WHERE sp3.sale_id = s.id
        )
      GROUP BY s.id
      ORDER BY s.sale_date DESC
    ";
    $params = ['from'=>$from,'to'=>$to];
    break;

  case 'top_clients_appts':
    $sql = "
     SELECT
      CONCAT(c.first_name,' ',c.last_name) AS client_name,
      COUNT(a.id) AS appt_count
    FROM appointments a
    JOIN clients c ON a.client_id = c.id
    WHERE DATE(a.appointment_date) BETWEEN :from AND :to
    GROUP BY a.client_id
    ORDER BY appt_count DESC
    LIMIT 10
    ";
     $params = ['from'=>$from,'to'=>$to];
     break;

   case 'top_clients_payments':
    $sql = "
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
        WHERE DATE(s.sale_date) BETWEEN :from AND :to
        GROUP BY s.client_id
        ORDER BY total_paid DESC
        LIMIT 10
    ";
    $params = ['from'=>$from,'to'=>$to];
    break;   
  
  default:
    http_response_code(400);
    die("Report not implemented");
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params ?? []);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// send CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="report_'.$report.'.csv"');

// open output stream
$out = fopen('php://output','w');

// If we have results, output header row
if (count($rows)>0) {
  fputcsv($out, array_keys($rows[0]));
  foreach ($rows as $r) {
    // convert any HTML line breaks back to newlines
    foreach ($r as &$v) {
      $v = str_replace(['<br>','<br/>','<br />'],"\n",$v);
    }
    fputcsv($out, array_values($r));
  }
}

fclose($out);
exit;
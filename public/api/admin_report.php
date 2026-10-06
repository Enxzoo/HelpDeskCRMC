<?php
header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/../../app/config/env.php';
require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/AdminReport.php';
session_start();
requireApiUser(['admin']);
session_write_close();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}
try {
    $filters = AdminReport::filters($_GET);
    $format = AdminWorkspace::text($_GET, 'format', 10, false);
    if (!in_array($format, ['', 'csv', 'print'], true))
        throw new AdminRequestException('Invalid report format.');
    $rows = (new AdminReport())->rows($filters);
    $headers = AdminReport::headers($filters['type']);
    $title = ucfirst($filters['type']) . ' report';
    $period = $filters['from'] . ' to ' . $filters['to'];
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="helpdesk-' . $filters['type'] . '-' . $filters['from'] . '-' . $filters['to'] . '.csv"');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers, ',', '"', '');
        while ($row = $rows->fetch_row())
            fputcsv($out, array_map([AdminReport::class, 'csvCell'], $row), ',', '"', '');
        fclose($out);
    } elseif ($format === 'print') {
        header('Content-Type: text/html; charset=utf-8');
        $escape = static fn($value) => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $escape($title) . '</title><style>
            body{font:13px "Segoe UI",sans-serif;color:#1c1b18;margin:32px}h1{font-size:22px}header{border-bottom:2px solid #c98a06;padding-bottom:16px}table{width:100%;border-collapse:collapse;margin-top:24px}th,td{padding:8px;border-bottom:1px solid #ddd;text-align:left;overflow-wrap:anywhere}th{background:#f1ede4}button{padding:10px 16px;background:#ecc94b;border:0;border-radius:6px;cursor:pointer}tr{break-inside:avoid}@media print{button{display:none}body{margin:0;font-size:10px}thead{display:table-header-group}@page{size:landscape;margin:12mm}}
            </style></head><body><header><img src="../assets/images/helpdesk-logo.png" width="38" height="38" alt="CRMC"><h1>HELPDESKCRMC - ' . $escape($title) . '</h1><p>' . $escape($period) . ' | ' . $rows->num_rows . ' records | Generated ' . $escape(date('Y-m-d H:i')) . '</p><button onclick="window.print()">Print / Save PDF</button></header><table><thead><tr>';
        foreach ($headers as $heading)
            echo '<th>' . $escape($heading) . '</th>';
        echo '</tr></thead><tbody>';
        while ($row = $rows->fetch_row()) {
            echo '<tr>';
            foreach ($row as $value)
                echo '<td>' . $escape($value) . '</td>';
            echo '</tr>';
        }
        if (!$rows->num_rows)
            echo '<tr><td colspan="' . count($headers) . '">No records for this period.</td></tr>';
        echo '</tbody></table></body></html>';
    } else {
        $preview = [];
        for ($i = 0; $i < 50 && ($row = $rows->fetch_row()); $i++)
            $preview[] = $row;
        echo json_encode([
            'success' => true,
            'title' => $title,
            'period' => $period,
            'filters' => $filters,
            'generated_at' => date('c'),
            'total' => $rows->num_rows,
            'headers' => $headers,
            'rows' => $preview
        ], JSON_INVALID_UTF8_SUBSTITUTE);
    }
} catch (AdminRequestException $error) {
    http_response_code($error->status);
    echo json_encode(['success' => false, 'error' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('Admin report failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to generate this report. Please try again.']);
}

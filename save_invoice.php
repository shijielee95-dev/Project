<?php
/**
 * save_invoice.php
 * Handles both CREATE and EDIT invoice form submissions.
 * Redirects back with flash message on completion.
 */
require_once 'config/bootstrap.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('invoice.php');

$pdo      = db();
$editId   = (int)($_POST['edit_id'] ?? 0);
$isEdit   = $editId > 0;

// ── Collect & sanitise inputs ──────────────────
$invoiceNo      = trim($_POST['invoice_no']        ?? '');
$status         = $_POST['status']                  ?? 'draft';
$invoiceDate    = $_POST['invoice_date']             ?? date('Y-m-d');
$dueDate        = $_POST['due_date']                 ?? null;
$currency       = $_POST['currency']                 ?? 'MYR';
$paymentMethod  = $_POST['payment_method']           ?? '01';
$customerName   = trim($_POST['customer_name']       ?? '');
$customerTin    = trim($_POST['customer_tin']        ?? '');
$customerRegNo  = trim($_POST['customer_reg_no']     ?? '');
$customerEmail  = trim($_POST['customer_email']      ?? '');
$customerPhone  = trim($_POST['customer_phone']      ?? '');
$customerAddr   = trim($_POST['customer_address']    ?? '');
$subtotal       = (float)($_POST['subtotal']         ?? 0);
$discountAmt    = (float)($_POST['discount_amount']  ?? 0);
$taxAmount      = (float)($_POST['tax_amount']       ?? 0);
$totalAmount    = (float)($_POST['total_amount']     ?? 0);
$notes          = trim($_POST['notes']               ?? '');
$lhdnType       = $_POST['lhdn_invoice_type']        ?? '01';
$msicCode       = trim($_POST['msic_code']           ?? '');

// ── Basic validation ───────────────────────────
if (!$invoiceNo || !$customerName || !$invoiceDate) {
    flash('error', 'Invoice number, customer name and date are required.');
    redirect($isEdit ? "create_invoice.php?edit=$editId" : 'create_invoice.php');
}

$allowedStatus = ['draft','sent','paid','overdue','cancelled'];
if (!in_array($status, $allowedStatus)) $status = 'draft';

$dueDate = $dueDate ?: null;

// ── Items ──────────────────────────────────────
$rawItems = $_POST['items'] ?? [];
$lineItems = [];
$taxRates  = ['none' => 0, 'sst6' => 0.06, 'sst10' => 0.10, 'service6' => 0.06];

foreach ($rawItems as $i => $item) {
    $desc      = trim($item['description'] ?? '');
    $qty       = (float)($item['quantity']   ?? 1);
    $price     = (float)($item['unit_price'] ?? 0);
    $taxType   = $item['tax_type'] ?? 'none';
    if (!isset($taxRates[$taxType])) $taxType = 'none';

    if (!$desc || $qty <= 0) continue;

    $rowSubtotal = $qty * $price;
    $rowTax      = $rowSubtotal * ($taxRates[$taxType]);
    $lineTotal   = $rowSubtotal + $rowTax;

    $lineItems[] = [
        'description' => $desc,
        'quantity'    => $qty,
        'unit_price'  => $price,
        'tax_type'    => $taxType,
        'tax_amount'  => $rowTax,
        'line_total'  => $lineTotal,
        'sort_order'  => $i,
    ];
}

if (empty($lineItems)) {
    flash('error', 'At least one line item is required.');
    redirect($isEdit ? "create_invoice.php?edit=$editId" : 'create_invoice.php');
}

// ── Determine customer_id (if matched) ────────
$customerId = null;
$cStmt = $pdo->prepare("SELECT id FROM customers WHERE customer_name = ? LIMIT 1");
$cStmt->execute([$customerName]);
$cRow = $cStmt->fetch();
if ($cRow) $customerId = $cRow['id'];

// ── Determine overall tax_type for invoice ─────
$taxTypes = array_unique(array_column($lineItems, 'tax_type'));
$taxTypes  = array_filter($taxTypes, fn($t) => $t !== 'none');
$invTaxType = !empty($taxTypes) ? reset($taxTypes) : 'none';

// ── Save ───────────────────────────────────────
try {
    $pdo->beginTransaction();

    $invoiceFields = [
        'invoice_no'        => $invoiceNo,
        'customer_id'       => $customerId,
        'customer_name'     => $customerName,
        'customer_tin'      => $customerTin,
        'customer_reg_no'   => $customerRegNo,
        'customer_email'    => $customerEmail,
        'customer_phone'    => $customerPhone,
        'customer_address'  => $customerAddr,
        'invoice_date'      => $invoiceDate,
        'due_date'          => $dueDate,
        'subtotal'          => $subtotal,
        'discount_amount'   => $discountAmt,
        'tax_type'          => $invTaxType,
        'tax_amount'        => $taxAmount,
        'total_amount'      => $totalAmount,
        'currency'          => $currency,
        'notes'             => $notes,
        'status'            => $status,
        'lhdn_invoice_type' => $lhdnType,
        'msic_code'         => $msicCode,
        'payment_method'    => $paymentMethod,
    ];

    if ($isEdit) {
        // UPDATE
        $setClauses = implode(', ', array_map(fn($k) => "$k = ?", array_keys($invoiceFields)));
        $values     = array_values($invoiceFields);
        $values[]   = $editId;
        $pdo->prepare("UPDATE invoices SET $setClauses WHERE id = ?")->execute($values);

        // Delete old items and re-insert
        $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$editId]);
        $invoiceId = $editId;

    } else {
        // INSERT
        $cols   = implode(', ', array_keys($invoiceFields));
        $placeholders = implode(', ', array_fill(0, count($invoiceFields), '?'));
        $pdo->prepare("INSERT INTO invoices ($cols) VALUES ($placeholders)")
            ->execute(array_values($invoiceFields));
        $invoiceId = (int)$pdo->lastInsertId();

        // Increment sequence
        $year = date('Y');
        $pdo->prepare("UPDATE invoice_sequences SET next_no = next_no + 1 WHERE prefix = 'INV' AND year = ?")
            ->execute([$year]);
    }

    // Insert line items
    $itemStmt = $pdo->prepare("
        INSERT INTO invoice_items
            (invoice_id, description, quantity, unit_price, tax_type, tax_amount, line_total, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($lineItems as $item) {
        $itemStmt->execute([
            $invoiceId,
            $item['description'],
            $item['quantity'],
            $item['unit_price'],
            $item['tax_type'],
            $item['tax_amount'],
            $item['line_total'],
            $item['sort_order'],
        ]);
    }

    $pdo->commit();

    flash('success', $isEdit ? 'Invoice updated successfully.' : 'Invoice created successfully.');
    redirect("view_invoice.php?id=$invoiceId");

} catch (Exception $e) {
    $pdo->rollBack();
    flash('error', 'Database error: ' . $e->getMessage());
    redirect($isEdit ? "create_invoice.php?edit=$editId" : 'create_invoice.php');
}

<?php
session_start();
include "db.php";

$journal_id = $_GET['id'] ?? 0;
$journal = $conn->query("SELECT * FROM general_journal WHERE id = $journal_id")->fetch_assoc();
$entries = $conn->query("
    SELECT je.*, coa.account_code, coa.account_name, 
           sa.sub_account_code, sa.sub_account_name
    FROM journal_entries je
    JOIN chart_of_accounts coa ON je.account_id = coa.id
    LEFT JOIN sub_accounts sa ON je.sub_account_id = sa.id
    WHERE je.journal_id = $journal_id
");

include 'header.php';
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2><i class="fas fa-book me-2"></i>Journal Entry #<?= $journal_id ?></h2>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-3">
                    <strong>Date:</strong> <?= date('m/d/Y', strtotime($journal['transaction_date'])) ?>
                </div>
                <div class="col-md-3">
                    <strong>Reference:</strong> <?= htmlspecialchars($journal['reference']) ?>
                </div>
                <div class="col-md-6">
                    <strong>Description:</strong> <?= htmlspecialchars($journal['description']) ?>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Account</th>
                            <th>Sub Account</th>
                            <th class="text-end">Debit</th>
                            <th class="text-end">Credit</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totalDebit = 0;
                        $totalCredit = 0;
                        while($entry = $entries->fetch_assoc()): 
                            $totalDebit += $entry['debit'];
                            $totalCredit += $entry['credit'];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($entry['account_code'].' - '.$entry['account_name']) ?></td>
                            <td>
                                <?= $entry['sub_account_code'] ? 
                                    htmlspecialchars($entry['sub_account_code'].' - '.$entry['sub_account_name']) : 
                                    'N/A' ?>
                            </td>
                            <td class="text-end"><?= number_format($entry['debit'], 2) ?></td>
                            <td class="text-end"><?= number_format($entry['credit'], 2) ?></td>
                            <td><?= htmlspecialchars($entry['description']) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot class="table-active">
                        <tr>
                            <th colspan="2" class="text-end">Totals:</th>
                            <th class="text-end"><?= number_format($totalDebit, 2) ?></th>
                            <th class="text-end"><?= number_format($totalCredit, 2) ?></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="mt-3">
                <a href="journal_entry.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Journal List
                </a>
                <button onclick="window.print()" class="btn btn-primary ms-2">
                    <i class="fas fa-print me-1"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
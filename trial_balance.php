<?php
session_start();
include "db.php";

// Get period parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');

// Get trial balance data
$trial_balance = $conn->query("
    SELECT 
        coa.id AS account_id,
        coa.account_code,
        coa.account_name,
        SUM(je.debit) AS total_debit,
        SUM(je.credit) AS total_credit,
        (SUM(je.debit) - SUM(je.credit)) AS balance
    FROM 
        chart_of_accounts coa
    LEFT JOIN 
        journal_entries je ON coa.id = je.account_id
    LEFT JOIN 
        general_journal j ON je.journal_id = j.id
    WHERE 
        j.transaction_date BETWEEN '$date_from' AND '$date_to'
    GROUP BY 
        coa.id, coa.account_code, coa.account_name
    ORDER BY 
        coa.account_code
");

include 'header.php';
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-balance-scale me-2"></i>Trial Balance</h2>
                <div>
                    <button class="btn btn-light btn-sm" onclick="window.print()">
                        <i class="fas fa-print me-1"></i> Print
                    </button>
                    <button class="btn btn-light btn-sm ms-2" id="exportBtn">
                        <i class="fas fa-file-export me-1"></i> Export
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <!-- Filter Form -->
            <form method="GET" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                    </div>
                </div>
            </form>

            <!-- Report Period -->
            <div class="alert alert-info mb-4">
                <strong>Report Period:</strong> <?= date('m/d/Y', strtotime($date_from)) ?> to <?= date('m/d/Y', strtotime($date_to)) ?>
            </div>

            <!-- Trial Balance Table -->
            <div class="table-responsive">
                <table class="table table-striped table-bordered" id="trialBalanceTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Account Code</th>
                            <th>Account Name</th>
                            <th class="text-end">Debit</th>
                            <th class="text-end">Credit</th>
                            <th class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $grandTotalDebit = 0;
                        $grandTotalCredit = 0;
                        while($row = $trial_balance->fetch_assoc()): 
                            $grandTotalDebit += $row['total_debit'];
                            $grandTotalCredit += $row['total_credit'];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['account_code']) ?></td>
                            <td><?= htmlspecialchars($row['account_name']) ?></td>
                            <td class="text-end"><?= number_format($row['total_debit'], 2) ?></td>
                            <td class="text-end"><?= number_format($row['total_credit'], 2) ?></td>
                            <td class="text-end"><?= number_format($row['balance'], 2) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot class="table-active">
                        <tr>
                            <th colspan="2" class="text-end">Grand Totals:</th>
                            <th class="text-end"><?= number_format($grandTotalDebit, 2) ?></th>
                            <th class="text-end"><?= number_format($grandTotalCredit, 2) ?></th>
                            <th class="text-end"><?= number_format($grandTotalDebit - $grandTotalCredit, 2) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#trialBalanceTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            'excel', 'pdf', 'print'
        ],
        order: [[0, 'asc']]
    });

    // Export button handler
    $('#exportBtn').click(function() {
        $('#trialBalanceTable').DataTable().button('excel').trigger();
    });
});
</script>

<?php include 'footer.php'; ?>
<?php
session_start();
include "db.php"; // database connection

// Fetch accounts, sub-accounts and balances
$sql = "
    SELECT 
        ac.name AS account_type,
        ca.account_code,
        ca.account_name AS main_account_name,
        sa.sub_account_code,
        sa.sub_account_name,
        SUM(je.debit) AS total_debit,
        SUM(je.credit) AS total_credit
    FROM 
        chart_of_accounts ca
    LEFT JOIN 
        sub_accounts sa ON sa.parent_account_id = ca.id
    LEFT JOIN 
        journal_entries je ON (je.account_id = ca.id OR je.account_id = sa.id)
    JOIN 
        account_categories ac ON ca.category_id = ac.id
    WHERE 
        ca.is_active = 1
        AND ac.name IN ('Assets', 'Liabilities', 'Equity')  -- ✅ FILTER here
    GROUP BY 
        ac.name, ca.account_code, ca.account_name, sa.sub_account_name, sa.sub_account_code
    ORDER BY 
        FIELD(ac.name, 'Assets', 'Liabilities', 'Equity'),  -- ✅ Order properly 
        ca.account_name ASC, 
        sa.sub_account_name ASC
";

$result = $conn->query($sql);
$accounts = $result->fetch_all(MYSQLI_ASSOC);

// Organize data
$groupedData = [];

foreach ($accounts as $account) {
    $accountType = $account['account_type'];
    $mainAccount = $account['main_account_name'];
    $subAccount = $account['sub_account_name'];
    $balance = $account['total_debit'] - $account['total_credit'];

    if (!isset($groupedData[$accountType])) {
        $groupedData[$accountType] = [];
    }

    if (!isset($groupedData[$accountType][$mainAccount])) {
        $groupedData[$accountType][$mainAccount] = [
            'account_code' => $account['account_code'],
            'sub_accounts' => []
        ];
    }

    if (!empty($subAccount)) {
        $groupedData[$accountType][$mainAccount]['sub_accounts'][] = [
            'sub_account_code' => $account['sub_account_code'],
            'sub_account_name' => $subAccount,
            'balance' => $balance
        ];
    } else {
        $groupedData[$accountType][$mainAccount]['balance'] = $balance;
    }
}

// Include header
include 'header.php';
?>

<style>
    .balance-sheet-table th, .balance-sheet-table td {
        padding: 8px;
        vertical-align: middle;
    }
    .balance-sheet-header {
        font-weight: bold;
        background-color: #eee;
    }
    .main-account {
        font-weight: bold;
    }
    .sub-account {
        padding-left: 20px;
    }
    .text-end {
        text-align: right;
    }
</style>

<div class="container mt-4">
    <h2 class="text-center">FAF Solution (pvt)Ltd , Branch All Branches</h2>
    <h5 class="text-center">as at <?= date('Y-m-d') ?></h5>

    <table class="table table-bordered balance-sheet-table">
        <thead class="table-light">
            <tr>
                <th>A/C Code MMSe</th>
                <th>Account Description</th>
                <th class="text-end">Amount</th>
                <th class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($groupedData as $type => $accounts): ?>
                <tr class="balance-sheet-header">
                    <td colspan="4"><?= htmlspecialchars($type) ?></td>
                </tr>

                <?php foreach ($accounts as $mainAccountName => $data): ?>
                    <tr class="main-account">
                        <td><?= htmlspecialchars($data['account_code']) ?></td>
                        <td><?= htmlspecialchars($mainAccountName) ?></td>
                        <td class="text-end">
                            <?= isset($data['balance']) ? number_format($data['balance'], 2) : '' ?>
                        </td>
                        <td class="text-end"></td>
                    </tr>

                    <?php foreach ($data['sub_accounts'] as $sub): ?>
                        <tr class="sub-account">
                            <td><?= htmlspecialchars($sub['sub_account_code']) ?></td>
                            <td><?= htmlspecialchars($sub['sub_account_name']) ?></td>
                            <td></td>
                            <td class="text-end"><?= number_format($sub['balance'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>

                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
// Include footer
include 'footer.php';
?>

<?php
session_start();
// Ensure Composer's autoloader is included
require_once __DIR__ . '/vendor/autoload.php';
include "db.php";

// Fetch the same balance sheet data
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
        AND ac.name IN ('Assets', 'Liabilities', 'Equity')
    GROUP BY 
        ac.name, ca.account_code, ca.account_name, sa.sub_account_code, sa.sub_account_name
    ORDER BY 
        FIELD(ac.name, 'Assets', 'Liabilities', 'Equity'),
        ca.account_code ASC,
        sa.sub_account_code ASC
";

$result = $conn->query($sql);
$accounts = $result->fetch_all(MYSQLI_ASSOC);

// Organize data
$organized = [
    'Assets' => [],
    'Liabilities' => [],
    'Equity' => []
];

foreach ($accounts as $account) {
    $balance = $account['total_debit'] - $account['total_credit'];

    $organized[$account['account_type']][] = [
        'account_code' => $account['account_code'],
        'main_account_name' => $account['main_account_name'],
        'sub_account_code' => $account['sub_account_code'],
        'sub_account_name' => $account['sub_account_name'],
        'balance' => $balance
    ];
}

// Totals
$totals = [
    'Assets' => 0,
    'Liabilities' => 0,
    'Equity' => 0
];

// Start capturing HTML
ob_start();
?>

<h2 style="text-align: center;">Company Name Here</h2>
<h5 style="text-align: center;">Balance Sheet as at <?= date('Y-m-d') ?></h5>

<table border="1" width="100%" cellspacing="0" cellpadding="4">
    <thead>
        <tr style="background-color: #f2f2f2;">
            <th>A/C Code</th>
            <th>Account Description</th>
            <th style="text-align: right;">Amount</th>
            <th style="text-align: right;">Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($organized as $type => $accounts): ?>
            <tr style="background-color: #d9edf7; font-weight: bold;">
                <td colspan="4"><?= $type ?></td>
            </tr>

            <?php 
            $currentMain = '';
            $mainTotal = 0;
            foreach ($accounts as $acc): 
            ?>
                <?php if ($currentMain != $acc['main_account_name']): ?>
                    <?php if ($currentMain != ''): ?>
                        <tr>
                            <td></td>
                            <td><b><?= $currentMain ?> Total</b></td>
                            <td></td>
                            <td style="text-align: right;"><b><?= number_format($mainTotal, 2) ?></b></td>
                        </tr>
                        <?php $mainTotal = 0; ?>
                    <?php endif; ?>
                    <tr style="font-weight: bold;">
                        <td><?= htmlspecialchars($acc['account_code']) ?></td>
                        <td><?= htmlspecialchars($acc['main_account_name']) ?></td>
                        <td></td>
                        <td></td>
                    </tr>
                    <?php $currentMain = $acc['main_account_name']; ?>
                <?php endif; ?>

                <?php if (!empty($acc['sub_account_name'])): ?>
                    <tr>
                        <td><?= htmlspecialchars($acc['sub_account_code']) ?></td>
                        <td><?= htmlspecialchars($acc['sub_account_name']) ?></td>
                        <td style="text-align: right;"><?= number_format($acc['balance'], 2) ?></td>
                        <td></td>
                    </tr>
                    <?php 
                    $mainTotal += $acc['balance'];
                    $totals[$type] += $acc['balance'];
                    ?>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($currentMain != ''): ?>
                <tr>
                    <td></td>
                    <td><b><?= $currentMain ?> Total</b></td>
                    <td></td>
                    <td style="text-align: right;"><b><?= number_format($mainTotal, 2) ?></b></td>
                </tr>
            <?php endif; ?>

            <tr style="background-color: #c4e3f3; font-weight: bold;">
                <td colspan="3" style="text-align: right;">Total <?= $type ?></td>
                <td style="text-align: right;"><?= number_format($totals[$type], 2) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<br>
<h5 style="text-align: right;">
    Balance Sheet Balanced: 
    <?= ($totals['Assets'] == ($totals['Liabilities'] + $totals['Equity'])) ? 'Yes' : 'No' ?>
</h5>

<?php
$html = ob_get_clean();

// Generate PDF
$mpdf = new \Mpdf\Mpdf();
$mpdf->WriteHTML($html);
$mpdf->Output('balance_sheet_' . date('Ymd') . '.pdf', 'I'); // Output in browser
?>

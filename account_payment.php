<?php
session_start();
include "db.php";

// Generate transaction ID securely
function generate_transaction_id() {
    $prefix = "DT-FV-" . date('Y-m-');
    $stmt = $GLOBALS['conn']->prepare("SELECT MAX(id) FROM general_journal");
    $stmt->execute();
    $result = $stmt->get_result();
    $last_id = $result->fetch_row()[0] ?? 0;
    $next_num = str_pad(($last_id + 1), 6, '0', STR_PAD_LEFT);
    return $prefix . $next_num;
}

// Get all active sub-accounts grouped by parent account
$sub_accounts = $conn->query("SELECT * FROM sub_accounts WHERE is_active = 1 ORDER BY parent_account_id ASC, sub_account_code ASC");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Validate required fields
        if (empty($_POST['debit_account_id']) || !is_array($_POST['debit_account_id'])) {
            throw new Exception("No debit entries provided");
        }
        
        if (empty($_POST['credit_account_id'])) {
            throw new Exception("No credit account selected");
        }

        $conn->autocommit(FALSE); // Start transaction
        
        // Create payment header
        $transaction_id = generate_transaction_id();
        $stmt = $conn->prepare("INSERT INTO general_journal 
                              (transaction_date, reference, description, created_by) 
                              VALUES (?, ?, ?, ?)");
        
        // Sanitize and validate input
        $accounting_date = filter_input(INPUT_POST, 'accounting_date', FILTER_SANITIZE_STRING);
        $description = htmlspecialchars($_POST['description']);
        $user_id = $_SESSION['user_id'];
        $branch_id = intval($_POST['branch_id']);
        $payment_method = htmlspecialchars($_POST['payment_method']);
        $cheque_no = isset($_POST['cheque_no']) ? htmlspecialchars($_POST['cheque_no']) : null;
        
        $stmt->bind_param("sssi", 
            $accounting_date,
            $transaction_id,
            $description,
            $user_id
        );
        $stmt->execute();
        $journal_id = $conn->insert_id;
        
        $total_amount = 0;
        
        // Process debit entries (expenses)
        foreach ($_POST['debit_account_id'] as $index => $account_id) {
            if (!empty($account_id) && isset($_POST['debit'][$index])) {
                $amount = floatval($_POST['debit'][$index]);
                
                if ($amount <= 0) {
                    throw new Exception("Invalid debit amount: must be positive number");
                }
                
                $total_amount += $amount;
                
                $stmt = $conn->prepare("INSERT INTO journal_entries 
                                      (journal_id, account_id, sub_account_id, debit, credit, description) 
                                      VALUES (?, ?, ?, ?, 0, ?)");
                
                $sub_account_id = isset($_POST['debit_sub_account_id'][$index]) ? intval($_POST['debit_sub_account_id'][$index]) : NULL;
                $entry_description = isset($_POST['debit_description'][$index]) ? htmlspecialchars($_POST['debit_description'][$index]) : '';
                
                $stmt->bind_param("iiids", 
                    $journal_id,
                    $account_id,
                    $sub_account_id,
                    $amount,
                    $entry_description
                );
                $stmt->execute();
            }
        }
        
        // Process credit entry
        $credit_account_id = intval($_POST['credit_account_id']);
        $credit_sub_account_id = isset($_POST['credit_sub_account_id']) ? intval($_POST['credit_sub_account_id']) : NULL;
        $payment_description = "Payment for " . $description;
        
        $stmt = $conn->prepare("INSERT INTO journal_entries 
                              (journal_id, account_id, sub_account_id, debit, credit, description) 
                              VALUES (?, ?, ?, 0, ?, ?)");
        
        $stmt->bind_param("iiids", 
            $journal_id,
            $credit_account_id,
            $credit_sub_account_id,
            $total_amount,
            $payment_description
        );
        $stmt->execute();
        
        // Verify the accounting equation balances
        $check_balance = $conn->query("
            SELECT ABS(SUM(debit) - SUM(credit)) as diff 
            FROM journal_entries 
            WHERE journal_id = $journal_id
        ")->fetch_assoc();

        if ($check_balance['diff'] > 0.01) {
            throw new Exception("Journal entries do not balance. Difference: ".$check_balance['diff']);
        }

        $conn->commit();
        $_SESSION['success'] = "Payment recorded successfully! Transaction ID: $transaction_id";
        header("Location: print_vaucher.php?id=$journal_id");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Payment Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        $_SESSION['error'] = "Failed to record payment. Please verify all amounts are correct.";
    }
}

// Get data for dropdowns
$branches = $conn->query("SELECT * FROM branches WHERE status = 'active' ORDER BY name");
$expense_accounts = $conn->query("SELECT * FROM chart_of_accounts 
                                 WHERE category_id IN 
                                 (SELECT id FROM account_categories WHERE name In ('Expenses', 'Income'))
                                 AND is_active = 1 ORDER BY account_code");
$payment_accounts = $conn->query("SELECT * FROM chart_of_accounts 
                                 WHERE category_id IN 
                                 (SELECT id FROM account_categories WHERE name IN ('Assets', 'Liabilities'))
                                 AND is_active = 1 ORDER BY account_code");

include 'header.php';
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2><i class="fas fa-money-bill-wave me-2"></i>Account Payment</h2>
        </div>
        <div class="card-body">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            
            <form method="POST" id="paymentForm">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Branch</label>
                        <select name="branch_id" class="form-select" required>
                            <option value="">Select Branch</option>
                            <?php while($branch = $branches->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($branch['id']) ?>">
                                    <?= htmlspecialchars($branch['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Transaction ID</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars(generate_transaction_id()) ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Accounting Date</label>
                        <input type="date" name="accounting_date" class="form-control" required 
                               value="<?= date('Y-m-d') ?>" 
                               min="<?= date('Y-m-d', strtotime('2017-01-02')) ?>">
                        <small class="text-muted">Cannot select dates before 2017-01-02</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select" required id="paymentMethod">
                            <option value="">Select Method</option>
                            <option value="cash">Cash</option>
                            <option value="cheque">Cheque</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3" id="chequeDetails" style="display:none;">
                    <div class="col-md-6">
                        <label class="form-label">Cheque No</label>
                        <input type="text" name="cheque_no" class="form-control">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control" required>
                    </div>
                </div>
                
                <h4 class="mt-4">Payment Account (Credit)</h4>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Account</label>
                        <select name="credit_account_id" class="form-select" required id="creditAccount">
                            <option value="">Select Payment Account</option>
                            <?php while($account = $payment_accounts->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($account['id']) ?>">
                                    <?= htmlspecialchars($account['account_code'] . ' - ' . $account['account_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sub Account</label>
                        <select name="credit_sub_account_id" class="form-select" id="creditSubAccount">
                            <option value="">None</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Total Amount (Rs.)</label>
                        <input type="number" name="credit" class="form-control" id="totalAmount" step="0.01" min="0" readonly>
                        <input type="hidden" name="credit_amount" id="creditAmountHidden">
                    </div>
                </div>
                
                <h4 class="mt-4">Payment Entries (Debit)</h4>
                <div id="expenseEntries">
                    <div class="entry-row row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Account</label>
                            <select name="debit_account_id[]" class="form-select account-select" required>
                                <option value="">Select Account</option>
                                <?php while($account = $expense_accounts->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($account['id']) ?>">
                                        <?= htmlspecialchars($account['account_code'] . ' - ' . $account['account_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sub Account</label>
                            <select name="debit_sub_account_id[]" class="form-select sub-account-select" disabled>
                                <option value="">None</option>
                                <?php 
                                $sub_accounts->data_seek(0);
                                while($sub = $sub_accounts->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($sub['id']) ?>" data-parent="<?= htmlspecialchars($sub['parent_account_id']) ?>">
                                        <?= htmlspecialchars($sub['sub_account_code'].' - '.$sub['sub_account_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Amount (Rs.)</label>
                            <input type="number" name="debit[]" class="form-control amount" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="button" class="btn btn-danger remove-entry">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-2">
                    <div class="col-md-12">
                        <button type="button" id="addExpenseEntry" class="btn btn-secondary">
                            <i class="fas fa-plus me-1"></i> Add Expense Entry
                        </button>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary float-end me-2">
                            <i class="fas fa-save me-1"></i> Proceed Transaction
                        </button>
                        <button type="button" class="btn btn-secondary float-end me-2" onclick="window.location.href='payments.php'">
                            <i class="fas fa-times me-1"></i> Cancel
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // Show/hide cheque details based on payment method
    $('#paymentMethod').change(function() {
        if ($(this).val() === 'cheque') {
            $('#chequeDetails').show();
            $('input[name="cheque_no"]').prop('required', true);
        } else {
            $('#chequeDetails').hide();
            $('input[name="cheque_no"]').prop('required', false);
        }
    });
    
    // Add new expense entry
    $('#addExpenseEntry').click(function() {
        const newRow = $('.entry-row:first').clone();
        newRow.find('input').val('');
        newRow.find('select').val('');
        newRow.find('.sub-account-select').prop('disabled', true);
        $('#expenseEntries').append(newRow);
    });
    
    // Remove entry row
    $(document).on('click', '.remove-entry', function() {
        if ($('.entry-row').length > 1) {
            $(this).closest('.entry-row').remove();
            calculateTotal();
        }
    });
    
    // Calculate total amount when expense amounts change
    $(document).on('input', '.amount', function() {
        calculateTotal();
    });
    
    // Load sub accounts when account changes
    $(document).on('change', '.account-select', function() {
        const accountId = $(this).val();
        const subAccountSelect = $(this).closest('.entry-row').find('.sub-account-select');
        
        if (accountId) {
            subAccountSelect.prop('disabled', false);
            subAccountSelect.find('option').hide();
            subAccountSelect.find('option[value=""]').show();
            subAccountSelect.find('option[data-parent="'+accountId+'"]').show();
            subAccountSelect.val('');
        } else {
            subAccountSelect.prop('disabled', true);
            subAccountSelect.val('');
        }
    });
    
    // Load sub accounts for credit account
    $('#creditAccount').change(function() {
        const accountId = $(this).val();
        const subAccountSelect = $('#creditSubAccount');
        
        if (accountId) {
            $.ajax({
                url: 'get_sub_accounts.php',
                method: 'POST',
                data: { account_id: accountId },
                success: function(response) {
                    subAccountSelect.html(response);
                },
                error: function(xhr, status, error) {
                    console.error("Error loading sub-accounts: " + error);
                    subAccountSelect.html('<option value="">Error loading sub-accounts</option>');
                }
            });
        } else {
            subAccountSelect.html('<option value="">None</option>');
        }
    });
    
    function calculateTotal() {
        let total = 0;
        $('.amount').each(function() {
            const amount = parseFloat($(this).val()) || 0;
            if (amount < 0) {
                $(this).val('');
                alert("Amount cannot be negative");
                return;
            }
            total += amount;
        });
        $('#totalAmount').val(total.toFixed(2));
        $('#creditAmountHidden').val(total.toFixed(2));
    }
});
</script>

<?php include 'footer.php'; ?>
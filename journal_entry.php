<?php
session_start();
include "db.php";

// Get all active sub-accounts grouped by parent account
$sub_accounts = $conn->query("SELECT * FROM sub_accounts WHERE is_active = 1 ORDER BY parent_account_id ASC, sub_account_code ASC");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn->autocommit(FALSE); // Start transaction
        
        // Insert journal header
        $stmt = $conn->prepare("INSERT INTO general_journal (transaction_date, reference, description, created_by) 
                               VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $_POST['transaction_date'], $_POST['reference'], $_POST['description'], $_SESSION['user_id']);
        $stmt->execute();
        $journal_id = $conn->insert_id;
        
        // Process each entry
        $entryCount = count($_POST['account_id']);
        $isBalanced = false;
        $totalDebit = 0;
        $totalCredit = 0;
        
        for ($i = 0; $i < $entryCount; $i++) {
            $account_id = $_POST['account_id'][$i];
            $sub_account_id = !empty($_POST['sub_account_id'][$i]) ? $_POST['sub_account_id'][$i] : NULL;
            $debit = floatval($_POST['debit'][$i]);  // Ensure this is a number
            $credit = floatval($_POST['credit'][$i]);  // Ensure this is a number
            $entry_desc = $_POST['entry_desc'][$i] ?? '';  // Ensure description is set
        
            $totalDebit += $debit;
            $totalCredit += $credit;
        
            $stmt = $conn->prepare("INSERT INTO journal_entries 
                                  (journal_id, account_id, sub_account_id, debit, credit, description) 
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiidds", $journal_id, $account_id, $sub_account_id, $debit, $credit, $entry_desc);
            $stmt->execute();
        }
        
        // Validate double-entry accounting
        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw new Exception("Journal entries are not balanced. Debits must equal credits.");
        }
        
        $conn->commit();
        $_SESSION['success'] = "Journal entry posted successfully!";
        header("Location: view_journal.php?id=".$journal_id);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
    }
}

// Get accounts for dropdown
$accounts = $conn->query("SELECT * FROM chart_of_accounts WHERE is_active = 1 ORDER BY account_code");
include 'header.php';
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h2><i class="fas fa-book me-2"></i>Journal Entry</h2>
        </div>
        <div class="card-body">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            
            <form method="POST" id="journalForm">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="transaction_date" class="form-control" required 
                               value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Reference</label>
                        <input type="text" name="reference" class="form-control" 
                               placeholder="JV-001" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control" required>
                    </div>
                </div>
                
                <div id="entriesContainer">
                    <div class="entry-row row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Account</label>
                            <select name="account_id[]" class="form-select account-select" required>
                                <option value="">Select Account</option>
                                <?php while($account = $accounts->fetch_assoc()): ?>
                                    <option value="<?= $account['id'] ?>">
                                        <?= htmlspecialchars($account['account_code'].' - '.$account['account_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sub Account</label>
                            <select name="sub_account_id[]" class="form-select sub-account-select" disabled>
                                <option value="">None</option>
                                <?php 
                                $sub_accounts->data_seek(0); // Reset pointer
                                while($sub = $sub_accounts->fetch_assoc()): ?>
                                    <option value="<?= $sub['id'] ?>" data-parent="<?= $sub['parent_account_id'] ?>">
                                        <?= htmlspecialchars($sub['sub_account_code'].' - '.$sub['sub_account_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Debit</label>
                            <input type="number" name="debit[]" class="form-control debit" step="0.01" min="0">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Credit</label>
                            <input type="number" name="credit[]" class="form-control credit" step="0.01" min="0">
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="button" class="btn btn-danger remove-entry">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-12">
                        <button type="button" id="addEntry" class="btn btn-secondary">
                            <i class="fas fa-plus me-1"></i> Add Entry
                        </button>
                        <button type="submit" class="btn btn-primary float-end">
                            <i class="fas fa-save me-1"></i> Post Journal
                        </button>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-12">
                        <div class="alert alert-info">
                            <strong>Totals:</strong> 
                            Debit: <span id="totalDebit">0.00</span> | 
                            Credit: <span id="totalCredit">0.00</span> | 
                            Difference: <span id="difference">0.00</span>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // Add new entry row
    $('#addEntry').click(function() {
        const newRow = $('.entry-row:first').clone();
        newRow.find('input').val('');
        newRow.find('select').val('');
        newRow.find('.sub-account-select').prop('disabled', true); // Disable sub-account by default
        $('#entriesContainer').append(newRow);
    });
    
    // Remove entry row
    $(document).on('click', '.remove-entry', function() {
        if ($('.entry-row').length > 1) {
            $(this).closest('.entry-row').remove();
            calculateTotals();
        }
    });
    
    // Load sub accounts when account changes
    $(document).on('change', '.account-select', function() {
        const accountId = $(this).val();
        const subAccountSelect = $(this).closest('.entry-row').find('.sub-account-select');
        
        if (accountId) {
            // Enable the sub-account select
            subAccountSelect.prop('disabled', false);
            
            // Hide all options except the "None" option
            subAccountSelect.find('option').hide();
            subAccountSelect.find('option[value=""]').show();
            
            // Show only sub-accounts for the selected parent account
            subAccountSelect.find('option[data-parent="'+accountId+'"]').show();
            
            // Reset the selected value
            subAccountSelect.val('');
        } else {
            // Disable the sub-account select if no account is selected
            subAccountSelect.prop('disabled', true);
            subAccountSelect.val('');
        }
    });
    
    // Calculate totals when debit/credit changes
    $(document).on('input', '.debit, .credit', function() {
        calculateTotals();
    });
    
    // Prevent both debit and credit being entered
    $(document).on('change', '.debit', function() {
        if ($(this).val() > 0) {
            $(this).closest('.entry-row').find('.credit').val('');
        }
    });
    
    $(document).on('change', '.credit', function() {
        if ($(this).val() > 0) {
            $(this).closest('.entry-row').find('.debit').val('');
        }
    });
    
    function calculateTotals() {
        let totalDebit = 0;
        let totalCredit = 0;
        
        $('.entry-row').each(function() {
            const debit = parseFloat($(this).find('.debit').val()) || 0;
            const credit = parseFloat($(this).find('.credit').val()) || 0;
            
            totalDebit += debit;
            totalCredit += credit;
        });
        
        $('#totalDebit').text(totalDebit.toFixed(2));
        $('#totalCredit').text(totalCredit.toFixed(2));
        $('#difference').text(Math.abs(totalDebit - totalCredit).toFixed(2));
        
        // Highlight if not balanced
        if (totalDebit.toFixed(2) === totalCredit.toFixed(2)) {
            $('#difference').parent().removeClass('alert-danger').addClass('alert-info');
        } else {
            $('#difference').parent().removeClass('alert-info').addClass('alert-danger');
        }
    }
});
</script>

<?php include 'footer.php'; ?>
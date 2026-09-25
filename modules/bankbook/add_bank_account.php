<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'modules/bankbook/index.php'); exit;
}

$bank_name       = trim($_POST['bank_name'] ?? '');
$account_title   = trim($_POST['account_title'] ?? '');
$account_number  = trim($_POST['account_number'] ?? '');
$branch_name     = trim($_POST['branch_name'] ?? '');
$opening_balance = (float)($_POST['opening_balance'] ?? 0);

if (empty($bank_name) || empty($account_title) || empty($account_number)) {
    redirect('modules/bankbook/index.php', 'Bank Name, Account Title aur Account Number zaroori hain.', 'error');
}

if (!$db_connected || !$pdo) {
    redirect('modules/bankbook/index.php', 'Database connection failed.', 'error');
}

try {
    $pdo->beginTransaction();

    // Check duplicate account number
    $chk = $pdo->prepare("SELECT id FROM bank_accounts WHERE account_number = ? LIMIT 1");
    $chk->execute([$account_number]);
    if ($chk->fetchColumn()) {
        throw new Exception("Yeh Account Number pehle se exist karta hai: {$account_number}");
    }

    $stmt = $pdo->prepare("
        INSERT INTO bank_accounts 
            (bank_name, account_title, account_number, branch_name, opening_balance, opening_date, current_balance, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')
    ");
    $stmt->execute([
        $bank_name, $account_title, $account_number,
        $branch_name, $opening_balance, date('Y-m-d'), $opening_balance
    ]);
    $new_id = (int)$pdo->lastInsertId();

    // Record opening balance as a deposit in bank_transactions
    if ($opening_balance > 0) {
        $pdo->prepare("
            INSERT INTO bank_transactions 
                (bank_account_id, transaction_date, transaction_type, amount, description, reference_type, created_by, created_at)
            VALUES (?, ?, 'deposit', ?, 'Opening Balance', 'opening_balance', ?, ?)
        ")->execute([
            $new_id, date('Y-m-d'), $opening_balance,
            $_SESSION['user_id'] ?? 1, date('Y-m-d')
        ]);
    }

    $pdo->commit();

    redirect(
        BASE_URL . 'modules/bankbook/index.php',
        "Bank account '{$bank_name} — {$account_title}' successfully add ho gaya!"
    );

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    redirect(BASE_URL . 'modules/bankbook/index.php', 'Error: ' . $e->getMessage(), 'error');
}

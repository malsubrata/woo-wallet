<?php
/**
 * The Template for displaying transaction history
 *
 * This template can be overridden by copying it to yourtheme/wc-wallet-payment/wc-endpoint-wallet-transactions.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @author 	Subrata Mal
 * @version     1.0.0
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
$transactions = get_wallet_transactions(array('user_id' => get_current_user_id()));
?>
<p>Current balance : <?php echo woo_wallet()->wallet->get_wallet_balance(get_current_user_id()); ?></p>
<table id="wc-wallet-transaction-details">
    <thead>
        <tr>
            <th>ID</th>
            <th>Credit</th>
            <th>Debit</th>
            <th>Details</th>
            <th>Date</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($transactions as $key => $transaction) : ?>
        <tr>
            <td><?php echo $transaction->transaction_id; ?></td>
            <td><?php echo $transaction->type == 'credit' ? $transaction->amount : ' - '; ?></td>
            <td><?php echo $transaction->type == 'debit' ? $transaction->amount : ' - '; ?></td>
            <td><?php echo $transaction->details; ?></td>
            <td><?php echo wc_string_to_datetime($transaction->date)->date(wc_date_format()); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
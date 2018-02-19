<?php
/**
 * The Template for displaying wallet recharge form
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet/wc-endpoint-wallet.php.
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
global $wp;
?>

<div class="woo-wallet-my-wallet-container">
    <div class="woo-wallet-sidebar">
        <h3 class="woo-wallet-sidebar-heading"><a href="<?php echo is_account_page() ? esc_url(wc_get_account_endpoint_url(get_option('woocommerce_woo_wallet_endpoint', 'woo-wallet'))) : get_permalink(); ?>"><?php _e('My Wallet', 'woo-wallet'); ?></a></h3>
        <ul>
            <li class="card"><a href="<?php echo is_account_page() ? esc_url(wc_get_account_endpoint_url(get_option('woocommerce_woo_wallet_endpoint', 'woo-wallet')) . 'add/') : '?wallet_action=add'; ?>" ><span class="dashicons dashicons-plus-alt"></span><p><?php _e('Wallet topup', 'woo-wallet'); ?></p></a></li>
            <li class="card"><a href="<?php echo is_account_page() ? esc_url(wc_get_account_endpoint_url(get_option('woocommerce_woo_wallet_transactions_endpoint', 'woo-wallet-transactions'))) : '?wallet_action=view_transactions'; ?>"><span class="dashicons dashicons-list-view"></span><p><?php _e('Transactions', 'woo-wallet'); ?></p></a></li>
        </ul>
    </div>
    <div class="woo-wallet-content">
        <div class="woo-wallet-content-heading">
            <h3 class="woo-wallet-content-h3"><?php _e('Balance', 'woo-wallet'); ?></h3>
            <p class="woo-wallet-price"><?php echo woo_wallet()->wallet->get_wallet_balance(get_current_user_id()); ?></p>
        </div>
        <div style="clear: both"></div>
        <hr/>
        <?php if ((isset($wp->query_vars['woo-wallet']) && 'add' === $wp->query_vars['woo-wallet']) || (isset($_GET['wallet_action']) && 'add' === $_GET['wallet_action'])) { ?>
            <form method="post" action="">
                <div class="woo-wallet-add-amount">
                    <label for="woo_wallet_balance_to_add"><?php _e('Enter amount', 'woo-wallet'); ?></label>
                    <input type="number" step="0.01" name="woo_wallet_balance_to_add" id="woo_wallet_balance_to_add" class="woo-wallet-balance-to-add" required="" />
                    <?php wp_nonce_field( 'woo_wallet_topup', 'woo_wallet_topup' ); ?>
                    <input type="submit" name="woo_add_to_wallet" class="woo-add-to-wallet" value="<?php _e('Add', 'woo-wallet'); ?>" />
                </div>
            </form>
        <?php } else { ?>
            <?php $transactions = get_wallet_transactions(array('user_id' => get_current_user_id()), 10); ?>
            <?php if (!empty($transactions)) { ?>
                <ul class="woo-wallet-transactions-items">
                    <?php foreach ($transactions as $transaction) : ?> 
                        <li>
                            <div>
                                <p><?php echo $transaction->details; ?></p>
                                <small><?php echo wc_string_to_datetime($transaction->date)->date(wc_date_format()); ?></small>
                            </div>
                            <div class="woo-wallet-transaction-type-<?php echo $transaction->type; ?>"><?php
                                echo $transaction->type == 'credit' ? '+' : '-';
                                echo wc_price(apply_filters('woo_wallet_amount', $transaction->amount, $transaction->currency));
                                ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php
            } else {
                _e('No transactions found', 'woo-wallet');
            }
            ?>
        <?php } ?>
    </div>
</div>
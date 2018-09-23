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
 * @version     1.1.8
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
global $wp;
do_action('woo_wallet_before_my_wallet_content');
?>

<div class="woo-wallet-my-wallet-container">
    <div class="woo-wallet-sidebar">
        <h3 class="woo-wallet-sidebar-heading"><a href="<?php echo is_account_page() ? esc_url(wc_get_account_endpoint_url(get_option('woocommerce_woo_wallet_endpoint', 'woo-wallet'))) : get_permalink(); ?>"><?php echo apply_filters('woo_wallet_account_menu_title', __('My Wallet', 'woo-wallet')); ?></a></h3>
        <ul>
            <?php if (apply_filters('woo_wallet_is_enable_top_up', true)) : ?>
                <li class="card"><a href="<?php echo is_account_page() ? esc_url(wc_get_endpoint_url(get_option('woocommerce_woo_wallet_endpoint', 'woo-wallet'), 'add', wc_get_page_permalink('myaccount'))) : add_query_arg('wallet_action', 'add', get_permalink()); ?>" ><span class="dashicons dashicons-plus-alt"></span><p><?php echo apply_filters('woo_wallet_account_topup_menu_title', __('Wallet topup', 'woo-wallet')); ?></p></a></li>
            <?php endif; ?>
            <?php if (apply_filters('woo_wallet_is_enable_transfer', 'on' === woo_wallet()->settings_api->get_option('is_enable_wallet_transfer', '_wallet_settings_general', 'on'))) : ?>
                <li class="card"><a href="<?php echo is_account_page() ? esc_url(wc_get_endpoint_url(get_option('woocommerce_woo_wallet_endpoint', 'woo-wallet'), 'transfer', wc_get_page_permalink('myaccount'))) : add_query_arg('wallet_action', 'transfer', get_permalink()); ?>" ><span class="dashicons dashicons-randomize"></span><p><?php echo apply_filters('woo_wallet_account_transfer_amount_menu_title', __('Wallet transfer', 'woo-wallet')); ?></p></a></li>
            <?php endif; ?>
            <?php if (apply_filters('woo_wallet_is_enable_transaction_details', true)) : ?>
                <li class="card"><a href="<?php echo is_account_page() ? esc_url(wc_get_account_endpoint_url(get_option('woocommerce_woo_wallet_transactions_endpoint', 'woo-wallet-transactions'))) : add_query_arg('wallet_action', 'view_transactions', get_permalink()); ?>"><span class="dashicons dashicons-list-view"></span><p><?php echo apply_filters('woo_wallet_account_transaction_menu_title', __('Transactions', 'woo-wallet')); ?></p></a></li>
            <?php endif; ?>
            <?php do_action('woo_wallet_menu_items'); ?>
        </ul>
    </div>
    <div class="woo-wallet-content">
        <div class="woo-wallet-content-heading">
            <h3 class="woo-wallet-content-h3"><?php _e('Balance', 'woo-wallet'); ?></h3>
            <p class="woo-wallet-price"><?php echo woo_wallet()->wallet->get_wallet_balance(get_current_user_id()); ?></p>
        </div>
        <div style="clear: both"></div>
        <hr/>
        <?php if ((isset($wp->query_vars['woo-wallet']) && !empty($wp->query_vars['woo-wallet'])) || isset($_GET['wallet_action'])) { ?>
            <?php if (apply_filters('woo_wallet_is_enable_top_up', true) && ((isset($wp->query_vars['woo-wallet']) && 'add' === $wp->query_vars['woo-wallet']) || (isset($_GET['wallet_action']) && 'add' === $_GET['wallet_action']))) { ?>
                <form method="post" action="">
                    <div class="woo-wallet-add-amount">
                        <label for="woo_wallet_balance_to_add"><?php _e('Enter amount', 'woo-wallet'); ?></label>
                        <?php
                        $min_amount = woo_wallet()->settings_api->get_option('min_topup_amount', '_wallet_settings_general', 0);
                        $max_amount = woo_wallet()->settings_api->get_option('max_topup_amount', '_wallet_settings_general', '');
                        ?>
                        <input type="number" step="0.01" min="<?php echo $min_amount; ?>" max="<?php echo $max_amount; ?>" name="woo_wallet_balance_to_add" id="woo_wallet_balance_to_add" class="woo-wallet-balance-to-add" required="" />
                        <?php wp_nonce_field('woo_wallet_topup', 'woo_wallet_topup'); ?>
                        <input type="submit" name="woo_add_to_wallet" class="woo-add-to-wallet" value="<?php _e('Add', 'woo-wallet'); ?>" />
                    </div>
                </form>
            <?php } else if (apply_filters('woo_wallet_is_enable_transfer', 'on' === woo_wallet()->settings_api->get_option('is_enable_wallet_transfer', '_wallet_settings_general', 'on')) && ((isset($wp->query_vars['woo-wallet']) && 'transfer' === $wp->query_vars['woo-wallet']) || (isset($_GET['wallet_action']) && 'transfer' === $_GET['wallet_action']))) { ?> 
                <form method="post" action="">
                    <p class="woo-wallet-field-container form-row form-row-wide">
                        <label for="woo_wallet_transfer_user_id"><?php _e('Select whom to transfer', 'woo-wallet'); ?> <?php
                            if (apply_filters('woo_wallet_user_search_exact_match', true)) {
                                _e('(Email)', 'woo-wallet');
                            }
                            ?></label>
                        <select name="woo_wallet_transfer_user_id" class="woo-wallet-select2" required=""></select>
                    </p>
                    <p class="woo-wallet-field-container form-row form-row-wide">
                        <label for="woo_wallet_transfer_amount"><?php _e('Amount', 'woo-wallet'); ?></label>
                        <input type="number" step="0.01" name="woo_wallet_transfer_amount" required=""/>
                    </p>
                    <p class="woo-wallet-field-container form-row form-row-wide">
                        <label for="woo_wallet_transfer_note"><?php _e('What\'s this for', 'woo-wallet'); ?></label>
                        <textarea name="woo_wallet_transfer_note" required=""></textarea>
                    </p>
                    <p class="woo-wallet-field-container form-row">
                        <?php wp_nonce_field('woo_wallet_transfer', 'woo_wallet_transfer'); ?>
                        <input type="submit" class="button" name="woo_wallet_transfer_fund" value="<?php _e('Proceed to transfer', 'woo-wallet'); ?>" />
                    </p>
                </form>
                <?php do_action('woo_wallet_menu_content'); ?>
            <?php } ?> 
        <?php } else if (apply_filters('woo_wallet_is_enable_transaction_details', true)) { ?>
            <?php $transactions = get_wallet_transactions(array('limit' => apply_filters('woo_wallet_transactions_count', 10))); ?>
            <?php if (!empty($transactions)) { ?>
                <ul class="woo-wallet-transactions-items">
                    <?php foreach ($transactions as $transaction) : ?> 
                        <li>
                            <div>
                                <p><?php echo $transaction->details; ?></p>
                                <small><?php echo wc_string_to_datetime($transaction->date)->date_i18n(wc_date_format()); ?></small>
                            </div>
                            <div class="woo-wallet-transaction-type-<?php echo $transaction->type; ?>"><?php
                                echo $transaction->type == 'credit' ? '+' : '-';
                                echo wc_price(apply_filters('woo_wallet_amount', $transaction->amount, $transaction->currency, $transaction->user_id));
                                ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php
            } else {
                _e('No transactions found', 'woo-wallet');
            }
        }
        ?>
    </div>
</div>
<?php do_action('woo_wallet_after_my_wallet_content');
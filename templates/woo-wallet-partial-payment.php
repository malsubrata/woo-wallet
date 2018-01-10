<?php
/**
 * The Template for displaying partial payment html at checkout page
 *
 * This template can be overridden by copying it to yourtheme/wc-wallet/woo-wallet-partial-payment.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @author 	Subrata Mal
 * @version     1.0.5
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
$rest_amount = wc()->cart->get_total('') - woo_wallet()->wallet->get_wallet_balance(get_current_user_id(), '');
if ('on' === woo_wallet()->settings_api->get_option('is_auto_deduct_for_partial_payment', '_wallet_settings_general')) {
    ?>
    <tr class="wallet-pay-partial">
        <th colspan="2"><label><?php echo sprintf('%s%0.2f will be debited from your wallet and %s%0.2f will be paid throught other payment method', get_woocommerce_currency_symbol(), woo_wallet()->wallet->get_wallet_balance(get_current_user_id(), ''), get_woocommerce_currency_symbol(), $rest_amount); ?></label></th>
    </tr>

<?php } else{ ?>
    <tr class="wallet-pay-partial">
        <th><?php _e('Pay by wallet', 'woo-wallet'); ?> <span id="partial_wallet_payment_tooltip" style="vertical-align: middle;" title="<?php echo sprintf('If checked %s%0.2f will be debited from your wallet and %s%0.2f will be paid throught other payment method', get_woocommerce_currency_symbol(), woo_wallet()->wallet->get_wallet_balance(get_current_user_id(), ''), get_woocommerce_currency_symbol(), $rest_amount); ?>" class="dashicons dashicons-info"></span></th>
        <td data-title="<?php esc_attr_e('Pay by wallet', 'woo-wallet'); ?>"><input type="checkbox" style="vertical-align: middle;" name="partial_pay_through_wallet" class="partial_pay_through_wallet" /></td>
    </tr>

    <script type="text/javascript">
        jQuery(function ($) {
            $('#partial_wallet_payment_tooltip').tooltip();
        });
    </script>
<?php } ?>
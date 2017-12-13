<?php
/**
 * The Template for displaying wallet recharge form
 *
 * This template can be overridden by copying it to yourtheme/wc-wallet/wc-endpoint-wallet.php.
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
$transactions = get_wallet_transactions(array(), 10);
?>

<!--<div class="woocommerce-Message woocommerce-Message--info woocommerce-info">
    <a class="woocommerce-Button button wc-wallet-add-balance" href="javascript:void(0)"><?php _e('Add balance', 'woo-wallet'); ?></a>
    <a class="woocommerce-Button button wc-wallet-wiew-transactions" href="<?php echo esc_url(wc_get_account_endpoint_url('woo-wallet-transactions')); ?>"><?php _e('View Transactions ', 'woo-wallet'); ?></a>
<?php
_e('Current wallet balance ');
echo woo_wallet()->wallet->get_wallet_balance(get_current_user_id());
?>
</div>
<form method="POST" action="<?php echo wc_get_cart_url(); ?>" class="wc-wallet-add-balance-form">

    <div class="wc-wallet-input-holder">
        <label for="wc_wallet_balance_to_add" class="wc-wallet-input-label"></label>
        <input type="number" name="wc_wallet_balance_to_add" class="wc-wallet-input" placeholder="<?php _e('Enter amount', 'woo-wallet') ?>" autofocus="autofocus" required="" />
    </div>

    <p class="wc-wallet-submit-holder">
        <input type="submit" class="button" name="wc_add_to_wallet" value="<?php _e('Add', 'woo-wallet') ?>">
    </p>
</form>-->

<script type="text/javascript">
//    jQuery(document).ready(function (){
//        jQuery('.wc-wallet-add-balance-form').hide();
//        jQuery('.wc-wallet-add-balance').on('click', function (){
//            jQuery('.wc-wallet-add-balance-form').slideDown();
//        });
//    });
</script>

<style type="text/css">
    .woo-wallet-my-wallet-container{
        max-width: 100%;
        overflow: hidden;
        border: 1px solid #f2f2f2;
        display: flex;
    }
    .woo-wallet-my-wallet-container p{
        margin: 0 auto;
    }
    .woo-wallet-my-wallet-container .woo-wallet-sidebar{
        width: 30%;
        float: left;
        background: #f2f2f2;
        min-height: 100px;
        padding-top: 20px;
    }
    .woo-wallet-my-wallet-container .woo-wallet-content{
        width: 70%;
        float: left;
        min-height: 100px;
        padding: 20px;
    }
    .woo-wallet-sidebar ul{
        margin: 0 auto;
    }
    .woo-wallet-sidebar ul li{
        list-style: none;
        margin: 10px 50px 10px 50px;
        text-align: center;
        border: 1px solid #31C3FF;
        padding: 25px;
    }
    .woo-wallet-sidebar ul li span{
        vertical-align: middle;
    }
    .woo-wallet-sidebar ul li p{
        margin: 0 auto;
        line-height: 1em;
    }
    .woo-wallet-sidebar-heading{
        padding-left: 50px
    }
    .woo-wallet-content-h3{
        float: left;
        margin: 0 0 15px;
        line-height: 1em;
    }
    .woo-wallet-price{
        float: right;
        margin: 0 0 15px;
    }
    .woo-wallet-content-heading{
        overflow: hidden;
    }
    .woo-wallet-transactions-items{
        margin: 0 auto;
    }
    .woo-wallet-transactions-items li {
        overflow: hidden;
        padding-bottom: 15px
    }
    .woo-wallet-transactions-items li div:first-child{
        float: left;
    }
    .woo-wallet-transactions-items li div:last-child{
        float: right;
    }
    .woo-wallet-transaction-type-credit{
        color: green;
    }
    .woo-wallet-transaction-type-debit{
        color: red;
    }
</style>

<div class="woo-wallet-my-wallet-container">
    <div class="woo-wallet-sidebar">
        <h3 class="woo-wallet-sidebar-heading">My Wallet</h3>
        <ul>
            <li><span class="dashicons dashicons-plus-alt"></span><p>Wallet topup</p></li>
            <li><span class="dashicons dashicons-list-view"></span><p>View Transactions</p></li>
            <li><span class="dashicons dashicons-upload"></span><p>Withdrawal</p></li>
        </ul>
    </div>
    <div class="woo-wallet-content">
        <div class="woo-wallet-content-heading">
            <h3 class="woo-wallet-content-h3">Balance</h3>
            <p class="woo-wallet-price"><?php echo woo_wallet()->wallet->get_wallet_balance(get_current_user_id()); ?></p>
        </div>
        <div style="clear: both"></div>
        <hr/>
        <ul class="woo-wallet-transactions-items">
            <?php if (!empty($transactions)) : ?>
                <?php foreach ($transactions as $transaction) : ?> 
                    <li>
                        <div>
                            <p><?php echo $transaction->details; ?></p>
                            <small><?php echo wc_string_to_datetime($transaction->date)->date(wc_date_format()); ?></small>
                        </div>
                        <div class="woo-wallet-transaction-type-<?php echo $transaction->type; ?>"><?php echo wc_price($transaction->amount); ?></div>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
</div>
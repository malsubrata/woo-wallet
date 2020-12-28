<?php
/**
 * The Template for displaying partial payment html at checkout page
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet/woo-wallet-referrals.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @author 	Subrata Mal
 * @version     1.3.5
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
$user_id = get_current_user_id();
$user = new WP_User($user_id);
$referral_url_by_userid = 'id' === $settings['referal_link'] ? true : false;
$referral_url = add_query_arg($referral->referral_handel, $user->user_login, wc_get_page_permalink('myaccount'));
if ($referral_url_by_userid) {
    $referral_url = add_query_arg($referral->referral_handel, $user->ID, wc_get_page_permalink('myaccount'));
}
$referring_visitor = get_user_meta($user_id, '_woo_wallet_referring_visitor', true) ? get_user_meta($user_id, '_woo_wallet_referring_visitor', true) : 0;
$referring_signup = get_user_meta($user_id, '_woo_wallet_referring_signup', true) ? get_user_meta($user_id, '_woo_wallet_referring_signup', true) : 0;
$referring_earning = get_user_meta($user_id, '_woo_wallet_referring_earning', true) ? get_user_meta($user_id, '_woo_wallet_referring_earning', true) : 0;
?>
<span>
    <?php _e('Your referral URL is:', 'woo-wallet'); ?> 
    <input type="text" readonly="" id="referral_url" value="<?php echo $referral_url; ?>" /> 
    <div class="referral-tooltip">
        <button onclick="referralTooltip()" onmouseout="referralTooltipOutFunc()">
            <span class="referral-tooltiptext" id="referral_tooltip"><?php _e('Copy to clipboard', 'woo-wallet'); ?></span>
            <?php _e('Copy', 'woo-wallet'); ?>
        </button>
    </div>
</span>
<h3><?php _e('Statistics', 'woo-wallet'); ?></h3>
<div class="woo_wallet_referral_statistics_container">
    <table class="woo_wallet_referral_statistics_table">
        <thead>
            <tr>
                <th><?php _e('Referring Visitors', 'woo-wallet'); ?></th>
                <th><?php _e('Referring Signups', 'woo-wallet'); ?></th>
                <th><?php _e('Total Earnings', 'woo-wallet'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?php echo $referring_visitor; ?></td>
                <td><?php echo $referring_signup; ?></td>
                <td><?php echo wc_price($referring_earning, woo_wallet_wc_price_args()); ?></td>
            </tr>
        </tbody>
    </table>
</div>
<style type="text/css">
    table.woo_wallet_referral_statistics_table{
        text-align: left;
        width: 100%;
        border: none;
        margin: 0 0 21px;
        border-collapse: collapse;
    }
    .woo_wallet_referral_statistics_table{
        word-wrap: break-word;
    }

    table.woo_wallet_referral_statistics_table th {
        background: #fafafa;
        font-weight: bold;
    }
    table.woo_wallet_referral_statistics_table th, table.woo_wallet_referral_statistics_table td {
        text-align: left;
        border: 1px solid #eee;
        color: #666;
        padding: 0.3em 1em;
        max-width: 100%;
    }

    .referral-tooltip {
        position: relative;
        display: inline-block;
    }

    .referral-tooltip .referral-tooltiptext {
        visibility: hidden;
        width: 140px;
        background-color: #555;
        color: #fff;
        text-align: center;
        border-radius: 6px;
        padding: 5px;
        position: absolute;
        z-index: 1;
        bottom: 150%;
        left: 50%;
        margin-left: -75px;
        opacity: 0;
        transition: opacity 0.3s;
    }

    .referral-tooltip .referral-tooltiptext::after {
        content: "";
        position: absolute;
        top: 100%;
        left: 50%;
        margin-left: -5px;
        border-width: 5px;
        border-style: solid;
        border-color: #555 transparent transparent transparent;
    }

    .referral-tooltip:hover .referral-tooltiptext {
        visibility: visible;
        opacity: 1;
    }
</style>
<script type="text/javascript">
    function referralTooltip() {
        var copyText = document.getElementById("referral_url");
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        document.execCommand("copy");

        var tooltip = document.getElementById("referral_tooltip");
        tooltip.innerHTML = "<?php _e('Copied', 'woo-wallet'); ?>";
    }

    function referralTooltipOutFunc() {
        var tooltip = document.getElementById("referral_tooltip");
        tooltip.innerHTML = "<?php _e('Copy to clipboard', 'woo-wallet'); ?>";
    }
</script>
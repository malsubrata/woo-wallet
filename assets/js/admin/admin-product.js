/* global woo_wallet_admin_product_param */

jQuery(document).ready(function ($) {
    if (woo_wallet_admin_product_param.is_hidden) {
        $('tr.post-' + woo_wallet_admin_product_param.product_id + '.type-product').remove();
    }
});
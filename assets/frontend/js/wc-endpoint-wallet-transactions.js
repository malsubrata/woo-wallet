/* global wallet_param */

jQuery(function ($) {
    $('#wc-wallet-transaction-details').DataTable(
            {
                searching: false,
                order: [[0, "desc"]]
            }
    );

    $('.woo-wallet-select2').select2();
});
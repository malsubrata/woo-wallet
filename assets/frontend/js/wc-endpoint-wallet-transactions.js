jQuery(function ($) {
    $('#wc-wallet-transaction-details').DataTable(
            {
                searching: false,
                order: [[0, "desc"]]
            }
    );
});
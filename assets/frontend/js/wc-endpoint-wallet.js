/* global wallet_param */

jQuery(function ($) {
    $('#wc-wallet-transaction-details').DataTable(
            {
                searching: false,
                order: [[0, "desc"]]
            }
    );
    $('.woo-wallet-select2').selectWoo({
        minimumInputLength: 3,
        ajax: {
            url: wallet_param.ajax_url,
            dataType: 'json',
            type: "POST",
            quietMillis: 50,
            data: function (term) {
                return {
                    action: 'woo-wallet-user-search',
                    autocomplete_field: 'ID',
                    term: term.term
                };
            },
            processResults: function (data) {
                // Tranforms the top-level key of the response object from 'items' to 'results'
                return {
                    results: $.map(data, function (item) {
                        return {
                            id: item.value,
                            text: item.label
                        };
                    })
                };
            }
        }
    });
});
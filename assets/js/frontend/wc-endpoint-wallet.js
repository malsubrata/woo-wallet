/* global wallet_param */

jQuery(function ($) {
    $('#wc-wallet-transaction-details').DataTable(
            {
                searching: false,
                order: [[0, 'desc']],
                language: {
                    emptyTable: wallet_param.i18n.emptyTable,
                    lengthMenu: wallet_param.i18n.lengthMenu,
                    info: wallet_param.i18n.info,
                    paginate: wallet_param.i18n.paginate
                }
            }
    );
    $('.woo-wallet-select2').selectWoo({
        language: {
            inputTooShort: function () {
                if (wallet_param.search_by_user_email) {
                    return wallet_param.i18n.non_valid_email_text;
                }
                return wallet_param.i18n.inputTooShort;
            },
            noResults: function () {
                if (wallet_param.search_by_user_email) {
                    return wallet_param.i18n.non_valid_email_text;
                } 
                return wallet_param.i18n.no_resualt;
            },
            searching: function (){
                return wallet_param.i18n.searching;
            }
        },
        minimumInputLength: 3,
        ajax: {
            url: wallet_param.ajax_url,
            dataType: 'json',
            type: 'POST',
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
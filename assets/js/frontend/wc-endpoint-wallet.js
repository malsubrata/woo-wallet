/* global wallet_param */

jQuery(function ($) {
    var transactionDetailsDataTable = $('#wc-wallet-transaction-details').DataTable(
            {
                processing: true,
                serverSide: true,
                ajax: {
                    url: wallet_param.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'draw_wallet_transaction_details_table',
                        security: wallet_param.transaction_table_nonce
                    }
                },
                columns: wallet_param.columns,
                responsive: true,
                searching: true,
                language: {
                    emptyTable: wallet_param.i18n.emptyTable,
                    zeroRecords: wallet_param.i18n.zeroRecords,
                    lengthMenu: wallet_param.i18n.lengthMenu,
                    info: wallet_param.i18n.info,
                    infoEmpty: wallet_param.i18n.infoEmpty,
                    infoFiltered: wallet_param.i18n.infoFiltered,
                    paginate: wallet_param.i18n.paginate,
                    processing: wallet_param.i18n.processing,
                    search: wallet_param.i18n.search
                },
                initComplete: function () {
                    $('#wc-wallet-transaction-details_wrapper .dataTables_filter input').attr('placeholder', wallet_param.i18n.placeholder);
                    $('#wc-wallet-transaction-details_wrapper .dataTables_filter input').datepicker({
                        dateFormat: 'yy-mm-dd',
                        maxDate: new Date(),
                        onSelect: function (dateText) {
                            transactionDetailsDataTable.search(dateText).draw();
                        }
                    });
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
            searching: function () {
                return wallet_param.i18n.searching;
            }
        },
        minimumInputLength: 3,
        ajax: {
            url: wallet_param.ajax_url,
            dataType: 'json',
            type: 'POST',
            delay: 250,
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
    $('#woo_wallet_transfer_form').submit(function () {
        // submit more than once return false
        $(this).submit(function () {
            return false;
        });
        // submit once return true
        return true;
    });
});
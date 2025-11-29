/* global wallet_param */
import '../scss/frontend.scss';
jQuery(function ($) {
    var transactionDetailsDataTable = $('#wc-wallet-transaction-details').DataTable(
        {
            dom: '<"dt-controls"lf>rt<"dt-controls"ip>',
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
                // Remove datatable default search input text
                $('#wc-wallet-transaction-details_filter input')
                    .attr('placeholder', 'Select Date Range')
                    .prop('readonly', true);
                $('#wc-wallet-transaction-details_wrapper .dataTables_filter input').daterangepicker({
                    opens: 'left',
                    drops: 'auto',
                    maxDate: moment(),
                    autoUpdateInput: false,
                    parentEl: 'body', // very important for responsive popup
                    alwaysShowCalendars: true,
                    locale: {
                        format: wallet_param.js_date_format,
                        cancelLabel: wallet_param.i18n.cancel,
                        applyLabel: wallet_param.i18n.apply,
                        customRangeLabel: wallet_param.i18n.customRangeLabel,
                        weekLabel: wallet_param.i18n.weekLabel,
                        daysOfWeek: wallet_param.i18n.daysOfWeek,
                        monthNames: wallet_param.i18n.monthNames,
                    },
                    ranges: {
                        'Today': [moment(), moment()],
                        'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                        'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                        'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                        'This Month': [moment().startOf('month'), moment().endOf('month')],
                        'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                        'This Year': [moment().startOf('year'), moment().endOf('year')],
                    },
                    alwaysShowCalendars: true,
                });

                $('#wc-wallet-transaction-details_wrapper .dataTables_filter input').on('apply.daterangepicker', function (ev, picker) {
                    transactionDetailsDataTable.search(picker.startDate.format('YYYY-MM-DD') + '|' + picker.endDate.format('YYYY-MM-DD')).draw();
                    $(this).val(picker.startDate.format(wallet_param.js_date_format) + ' - ' + picker.endDate.format(wallet_param.js_date_format));
                });

                $('#wc-wallet-transaction-details_wrapper .dataTables_filter input').on('cancel.daterangepicker', function (ev, picker) {
                    transactionDetailsDataTable.search('').draw();
                    $(this).val('');
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
                    security: wallet_param.search_user_nonce,
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

    // Submenu Toggle
    $('.woo-wallet-nav-item-wrapper.has-submenu > a').on('click', function (e) {
        e.preventDefault();
        var $submenu = $(this).siblings('.woo-wallet-submenu');
        var $icon = $(this).find('.woo-wallet-submenu-toggle');

        // Close other submenus
        $('.woo-wallet-submenu').not($submenu).slideUp();
        $('.woo-wallet-submenu-toggle').not($icon).removeClass('rotate');

        // Toggle current
        $submenu.slideToggle();
        $icon.toggleClass('rotate');
    });

    // Close submenu when clicking outside
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.woo-wallet-nav-item-wrapper.has-submenu').length) {
            $('.woo-wallet-submenu').slideUp();
            $('.woo-wallet-submenu-toggle').removeClass('rotate');
        }
    });
});
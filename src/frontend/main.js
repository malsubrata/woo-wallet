/* global wallet_param */
import '../scss/frontend.scss';
import { TabulatorFull as Tabulator } from 'tabulator-tables';
import 'tabulator-tables/dist/css/tabulator.min.css';

jQuery(function ($) {
    const tableEl = document.getElementById('wc-wallet-transaction-details');

    if (tableEl) {
        const columns = (wallet_param.columns || []).map(function (col) {
            return {
                title: col.title,
                field: col.data,
                headerSort: false,
                formatter: 'html',
                responsive: col.data === 'details' ? 0 : 2,
            };
        });

        const table = new Tabulator(tableEl, {
            layout: 'fitColumns',
            responsiveLayout: 'collapse',
            placeholder: wallet_param.i18n.emptyTable,
            columns: columns,
            pagination: true,
            paginationMode: 'remote',
            paginationSize: 10,
            paginationSizeSelector: [10, 25, 50, 100],
            ajaxURL: wallet_param.ajax_url,
            ajaxConfig: {
                method: 'POST',
            },
            ajaxContentType: 'form',
            ajaxParams: {
                action: 'draw_wallet_transaction_details_table',
                security: wallet_param.transaction_table_nonce,
            },
            ajaxResponse: function (_url, _params, response) {
                return {
                    last_page: response.last_page || 1,
                    data: response.data || [],
                };
            },
            langs: {
                default: {
                    pagination: {
                        first: wallet_param.i18n.paginate.first,
                        last: wallet_param.i18n.paginate.last,
                        prev: wallet_param.i18n.paginate.previous,
                        next: wallet_param.i18n.paginate.next,
                        page_size: wallet_param.i18n.lengthMenu.replace('_MENU_', '').trim() || 'Page Size',
                    },
                },
            },
        });

        // Build date-range filter controls outside the table.
        const controlsWrap = document.createElement('div');
        controlsWrap.className = 'woo-wallet-table-controls';
        controlsWrap.innerHTML =
            '<input type="date" class="wc-wallet-filter-from" aria-label="' + wallet_param.i18n.apply + '" max="' + new Date().toISOString().slice(0, 10) + '">' +
            '<span class="woo-wallet-filter-sep">&ndash;</span>' +
            '<input type="date" class="wc-wallet-filter-to" max="' + new Date().toISOString().slice(0, 10) + '">' +
            '<button type="button" class="button wc-wallet-filter-clear">&times;</button>';
        tableEl.parentNode.insertBefore(controlsWrap, tableEl);

        const fromInput = controlsWrap.querySelector('.wc-wallet-filter-from');
        const toInput = controlsWrap.querySelector('.wc-wallet-filter-to');
        const clearBtn = controlsWrap.querySelector('.wc-wallet-filter-clear');

        function applyDateFilter() {
            const from = fromInput.value;
            const to = toInput.value;
            if (from && to) {
                table.setData(wallet_param.ajax_url, {
                    action: 'draw_wallet_transaction_details_table',
                    security: wallet_param.transaction_table_nonce,
                    date_from: from,
                    date_to: to,
                });
            }
        }

        fromInput.addEventListener('change', applyDateFilter);
        toInput.addEventListener('change', applyDateFilter);
        clearBtn.addEventListener('click', function () {
            fromInput.value = '';
            toInput.value = '';
            table.setData(wallet_param.ajax_url, {
                action: 'draw_wallet_transaction_details_table',
                security: wallet_param.transaction_table_nonce,
            });
        });
    }

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
        $(this).submit(function () {
            return false;
        });
        return true;
    });

    // Submenu Toggle
    $('.woo-wallet-nav-item-wrapper.has-submenu > a').on('click', function (e) {
        e.preventDefault();
        var $submenu = $(this).siblings('.woo-wallet-submenu');
        var $icon = $(this).find('.woo-wallet-submenu-toggle');

        $('.woo-wallet-submenu').not($submenu).slideUp();
        $('.woo-wallet-submenu-toggle').not($icon).removeClass('rotate');

        $submenu.slideToggle();
        $icon.toggleClass('rotate');
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.woo-wallet-nav-item-wrapper.has-submenu').length) {
            $('.woo-wallet-submenu').slideUp();
            $('.woo-wallet-submenu-toggle').removeClass('rotate');
        }
    });
});

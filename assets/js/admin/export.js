/* global ajaxurl, terawallet_export_params */
(function ($, window) {
    /**
     * teraWalletExportForm handles the export process.
     */
    var teraWalletExportForm = function ($form) {
        this.$form = $form;
        this.xhr = false;
        // Initial state.
        this.$form.find('.terawallet-exporter-progress').val(0);

        // Methods.
        this.processStep = this.processStep.bind(this);

        // Events.
        $form.on('submit', {teraWalletExportForm: this}, this.onSubmit);
    };

    /**
     * Handle export form submission.
     */
    teraWalletExportForm.prototype.onSubmit = function (event) {
        event.preventDefault();

        var currentDate = new Date(),
                day = currentDate.getDate(),
                month = currentDate.getMonth() + 1,
                year = currentDate.getFullYear(),
                timestamp = currentDate.getTime(),
                filename = 'terawallet-transaction-export-' + day + '-' + month + '-' + year + '-' + timestamp + '.csv';

        event.data.teraWalletExportForm.$form.addClass('terawallet-exporter__exporting');
        event.data.teraWalletExportForm.$form.find('.terawallet-exporter-progress').val(0);
        event.data.teraWalletExportForm.$form.find('.terawallet-exporter-button').prop('disabled', true);
        event.data.teraWalletExportForm.processStep(1, $(this).serialize(), '', filename);
    };
    /**
     * Process the current export step.
     */
    teraWalletExportForm.prototype.processStep = function (step, data, columns, filename) {
        var $this = this,
                selected_columns = $('.terawallet-exporter-columns').val(),
                selected_users = $('.terawallet-exporter-users').val(),
                from_date = $('.terawallet-exporter-from-date').val(),
                to_date = $('.terawallet-exporter-to-date').val();
        $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                form: data,
                action: 'terawallet_do_ajax_transaction_export',
                step: step,
                columns: columns,
                selected_columns: selected_columns,
                selected_users: selected_users,
                start_date: from_date,
                end_date: to_date,
                filename: filename,
                security: terawallet_export_params.export_nonce
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    if ('done' === response.data.step) {
                        $this.$form.find('.terawallet-exporter-progress').val(response.data.percentage);
                        window.location = response.data.url;
                        setTimeout(function () {
                            $this.$form.removeClass('terawallet-exporter__exporting');
                            $this.$form.find('.terawallet-exporter-button').prop('disabled', false);
                        }, 200);
                    } else {
                        $this.$form.find('.terawallet-exporter-progress').val(response.data.percentage);
                        $this.processStep(parseInt(response.data.step, 10), data, response.data.columns, filename);
                    }
                }


            }
        }).fail(function (response) {
            window.console.log(response);
        });
    };

    /**
     * Function to call teraWalletExportForm on jquery selector.
     */
    $.fn.terawallet_export_form = function () {
        new teraWalletExportForm(this);
        return this;
    };

    $('.terawallet-exporter').terawallet_export_form();

    $('.terawallet-exporter-users').selectWoo({
        language: {
            inputTooShort: function () {
                return terawallet_export_params.i18n.inputTooShort;
            },
            noResults: function () {
                return terawallet_export_params.i18n.no_resualt;
            },
            searching: function () {
                return terawallet_export_params.i18n.searching;
            }
        },
        minimumInputLength: 3,
        ajax: {
            url: ajaxurl,
            dataType: 'json',
            type: 'POST',
            delay: 250,
            data: function (term) {
                return {
                    action: 'terawallet_export_user_search',
                    security: terawallet_export_params.search_user_nonce,
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
    
})(jQuery, window);
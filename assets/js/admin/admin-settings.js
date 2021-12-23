/* global woo_wallet_admin_settings_param */

jQuery(function ($) {
    var settings = {
        init: function () {
            //Initiate Color Picker
            $('.wp-color-picker-field').wpColorPicker();

            // Switches option sections
            $('.group').hide();
            var activewwtab = '';
            var url = new URL(location.href);
            var tab = url.searchParams.get('activewwtab');
            if (tab) {
                if (typeof (localStorage) !== undefined) {
                    localStorage.setItem('activewwtab', '#' + tab);
                }
            }
            if (typeof (localStorage) !== undefined) {
                activewwtab = localStorage.getItem('activewwtab');
            }
            if (activewwtab !== '' && $(activewwtab).length) {
                $(activewwtab).fadeIn();
            } else {
                $('.group:first').fadeIn();
            }
            $('.group .collapsed').each(function () {
                $(this).find('input:checked').parent().parent().parent().nextAll().each(
                        function () {
                            if ($(this).hasClass('last')) {
                                $(this).removeClass('hidden');
                                return false;
                            }
                            $(this).filter('.hidden').removeClass('hidden');
                        });
            });

            if (activewwtab !== '' && $(activewwtab + '-tab').length) {
                $(activewwtab + '-tab').addClass('nav-tab-active');
            } else {
                $('.nav-tab-wrapper a:first').addClass('nav-tab-active');
            }
            $('.nav-tab-wrapper a').click(function (evt) {
                $('.nav-tab-wrapper a').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active').blur();
                var clicked_group = $(this).attr('href');
                if (typeof (localStorage) !== undefined) {
                    localStorage.setItem('activewwtab', $(this).attr('href'));
                }
                $('.group').hide();
                $(clicked_group).fadeIn();
                evt.preventDefault();
            });

            $('.wpsa-browse').on('click', function (event) {
                event.preventDefault();

                var self = $(this);

                // Create the media frame.
                var file_frame = wp.media.frames.file_frame = wp.media({
                    title: self.data('uploader_title'),
                    button: {
                        text: self.data('uploader_button_text')
                    },
                    multiple: false
                });

                file_frame.on('select', function () {
                    var attachment = file_frame.state().get('selection').first().toJSON();
                    self.prev('.wpsa-url').val(attachment.url).change();
                });

                // Finally, open the modal
                file_frame.open();
            });
            $('.wpsa-attachment').on('click', function (event) {
                event.preventDefault();

                var self = $(this);

                // Create the media frame.
                var file_frame = wp.media.frames.file_frame = wp.media({
                    title: self.data('uploader_title'),
                    button: {
                        text: self.data('uploader_button_text')
                    },
                    multiple: false
                });

                file_frame.on('select', function () {
                    var attachment = file_frame.state().get('selection').first().toJSON();
                    self.prev('.wpsa-attachment-id').val(attachment.id).change();
                    self.parent('td').find('.wpsa-attachment-image').attr('src', attachment.url);
                });

                // Finally, open the modal
                file_frame.open();
            });
        },
        settings_page_init: function () {
            $('#_wallet_settings_general-_tax_status').on('change', function () {
                if ($(this).val() === 'taxable') {
                    $('._tax_class').show();
                } else {
                    $('._tax_class').hide();
                }
            }).change();
            $('#wcwp-_wallet_settings_general-is_enable_wallet_transfer').on('change', function () {
                if ($(this).is(':checked')) {
                    $('.min_transfer_amount, .transfer_charge_type, .transfer_charge_amount').show();
                } else {
                    $('.min_transfer_amount, .transfer_charge_type, .transfer_charge_amount').hide();
                }
            }).change();
            $('#wcwp-_wallet_settings_credit-is_enable_gateway_charge').on('change', function () {
                if ($(this).is(':checked')) {
                    $('.gateway_charge_type').show();
                    $.each(woo_wallet_admin_settings_param.gateways, function (index, value) {
                        $('#_wallet_settings_credit .' + value).show();
                    });
                } else {
                    $('.gateway_charge_type').hide();
                    $.each(woo_wallet_admin_settings_param.gateways, function (index, value) {
                        $('#_wallet_settings_credit .' + value).hide();
                    });
                }
            }).change();
            $('#wcwp-_wallet_settings_credit-is_enable_cashback_reward_program').on('change', function () {
                if ($(this).is(':checked')) {
                    $('.cashback_rule, .cashback_type, .cashback_amount, .process_cashback_status').show();
                    $('#_wallet_settings_credit-cashback_type, #_wallet_settings_credit-cashback_rule').trigger('change');
                } else {
                    $('.cashback_rule, .cashback_type, .cashback_amount, .process_cashback_status').hide();
                    $('#_wallet_settings_credit-cashback_type, #_wallet_settings_credit-cashback_rule').trigger('change');
                }
            }).change();
            $('#_wallet_settings_credit-cashback_type').on('change', function () {
                if ($(this).val() === 'percent' && $('#wcwp-_wallet_settings_credit-is_enable_cashback_reward_program').is(':checked')) {
                    $('.max_cashback_amount').show();
                } else {
                    $('.max_cashback_amount').hide();
                }
            }).change();
            $('#_wallet_settings_credit-cashback_rule').on('change', function () {
                if ($(this).val() === 'product_cat' && $('#wcwp-_wallet_settings_credit-is_enable_cashback_reward_program').is(':checked')) {
                    $('.allow_min_cashback').show();
                } else {
                    $('.allow_min_cashback').hide();
                }
                if ($(this).val() === 'cart' && $('#wcwp-_wallet_settings_credit-is_enable_cashback_reward_program').is(':checked')) {
                    $('.min_cart_amount').show();
                } else {
                    $('.min_cart_amount').hide();
                }
            }).change();
        }
    };
    settings.init();
    if (woo_wallet_admin_settings_param.screen_id === woo_wallet_admin_settings_param.settings_screen_id) {
        settings.settings_page_init();
    }
});

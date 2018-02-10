/* global woo_wallet_admin_settings_param */

jQuery(function ($) {
    var settings = {
        init: function () {
            $('#_wallet_settings_general-_tax_status').on('change', function (){
                if($(this).val() == 'taxable'){
                    $('._tax_class').show();
                } else{
                    $('._tax_class').hide();
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
            $('#wcwp-_wallet_settings_credit-is_enable_cashback_reward_program').on('change', function(){
                if ($(this).is(':checked')) {
                    $('.cashback_rule, .cashback_type, .cashback_amount, .max_cashback_amount').show();
                } else{
                    $('.cashback_rule, .cashback_type, .cashback_amount, .max_cashback_amount').hide();
                }
            }).change();
            $('#_wallet_settings_credit-cashback_type').on('change', function (){
                if($(this).val() === 'percent'){
                    $('.max_cashback_amount').show();
                } else{
                    $('.max_cashback_amount').hide();
                }
            }).change();
            $('#_wallet_settings_credit-cashback_rule').on('change', function(){
                if($(this).val() === 'product_cat'){
                    $('.allow_min_cashback').show();
                } else{
                    $('.allow_min_cashback').hide();
                }
            }).change();
        }
    };
    settings.init();
});

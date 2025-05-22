jQuery(function ($) {
    $('#woo_wallet_referrals_referring_visitors_limit_duration').on('change', function(){
        if('0' === $(this).val()){
            $('#woo_wallet_referrals_referring_visitors_limit').closest('tr').hide();
        } else{
            $('#woo_wallet_referrals_referring_visitors_limit').closest('tr').show();
        }
    }).change();
    $('#woo_wallet_referrals_referring_signups_limit_duration').on('change', function(){
        if('0' === $(this).val()){
            $('#woo_wallet_referrals_referring_signups_limit').closest('tr').hide();
        } else{
            $('#woo_wallet_referrals_referring_signups_limit').closest('tr').show();
        }
    }).change();
});
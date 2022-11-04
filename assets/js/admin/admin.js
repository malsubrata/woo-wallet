/* global terawallet_admin_params */

jQuery(function ($) {
    var $wallet_screen = $('.toplevel_page_woo-wallet'),
            $title_action = $wallet_screen.find('.wrap h2:first');
    $title_action.html($title_action.html() + ' <a href="' + terawallet_admin_params.export_url + '" class="page-title-action">' + terawallet_admin_params.export_title + '</a>');
    
    $('.lock-unlock-user-wallet').on('click', function(){
        var self = $(this);
        var data = {
            action : 'lock_unlock_terawallet',
            user_id: $(this).data('user_id'),
            type: $(this).data('type'),
            security : terawallet_admin_params.lock_unlock_nonce
        };
        $.post(terawallet_admin_params.ajax_url, data, function(response){
            if('lock' === response.data.type){
                self.find('span').removeClass('dashicons-unlock');
                self.find('span').addClass('dashicons-lock');
                self.find('label').text(response.data.text);
                self.data('type', response.data.type);
            } else{
                self.find('span').removeClass('dashicons-lock');
                self.find('span').addClass('dashicons-unlock');
                self.find('label').text(response.data.text);
                self.data('type', response.data.type);
            }
        });
    });
    
});
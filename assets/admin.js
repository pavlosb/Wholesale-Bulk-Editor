jQuery(function($){
    // Enable all checkbox functionality
    $('.wp-list-table').on('change', '.wbe-enable-all-checkbox', function(){
        var $row = $(this).closest('tr');
        var checked = $(this).is(':checked');
        $row.find('.wbe-role-checkbox').each(function(){
            $(this).prop('checked', checked).trigger('change');
        });
    });

    // Handle per-role enable/disable
    $('.wp-list-table').on('change', '.wbe-role-checkbox', function(){
        var $checkbox = $(this);
        var $input = $checkbox.closest('td').find('.wbe-wholesale-input');
        if ($checkbox.is(':checked')) {
            $input.prop('disabled', false);
        } else {
            $input.prop('disabled', true).val('');
        }
    });

    // Copy-across button
    $('.wp-list-table').on('click', '.wbe-copy-across', function(e){
        e.preventDefault();
        var $btn = $(this);
        var $input = $btn.closest('.wbe-copy-wrapper').find('.wbe-wholesale-input');
        var val = $input.val();
        var $row = $btn.closest('tr');
        $row.find('.wbe-role-checkbox:checked').each(function(){
            var $in = $(this).closest('td').find('.wbe-wholesale-input');
            $in.val(val);
        });
    });

    // Save button
    $('.wbe-save-row').on('click', function(e){
        e.preventDefault();
        var $row = $(this).closest('tr');
        var product_id = $(this).data('product_id');
        var prices = {};
        $row.find('.wbe-role-checkbox').each(function(){
            var role = $(this).data('role');
            var $input = $(this).closest('td').find('.wbe-wholesale-input');
            if ($(this).is(':checked')) {
                prices[role] = $input.val();
            } else {
                prices[role] = '';
            }
        });
        var $btn = $(this);
        var $feedback = $row.find('.wholesale-feedback');
        $btn.prop('disabled', true).text('Saving...');
        $feedback.removeClass('success error').text('');
        $.post(WBE_AJAX.ajax_url, {
            action: 'wbe_save_wholesale_prices',
            nonce: WBE_AJAX.nonce,
            product_id: product_id,
            prices: prices
        }, function(resp){
            if(resp.success){
                $btn.text('Saved!');
                $feedback.addClass('success').text('Saved!');
                setTimeout(function(){ $btn.text('Save').prop('disabled', false); $feedback.text(''); }, 1200);
            } else {
                $btn.text('Error!');
                $feedback.addClass('error').text(resp.data && resp.data.message ? resp.data.message : 'Error!');
                setTimeout(function(){ $btn.text('Save').prop('disabled', false); $feedback.text(''); }, 1800);
            }
        });
    });
});

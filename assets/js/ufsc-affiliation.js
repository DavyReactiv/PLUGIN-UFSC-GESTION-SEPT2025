(function($){
    function handleClick(e){
        e.preventDefault();
        var $form = $(this).closest('form');
        $.post(ufscAffiliation.ajax_url, {
            action: 'ufsc_affiliation_pay',
            nonce: ufscAffiliation.nonce
        }).done(function(response){
            if(response && response.success && response.data.redirect){
                window.location.href = response.data.redirect;
            } else {
                $form.off('submit').trigger('submit');
            }
        }).fail(function(){
            $form.off('submit').trigger('submit');
        });
    }

    $(document).on('click', '#ufsc-pay-affiliation', handleClick);
})(jQuery);

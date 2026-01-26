/**
 * UFSC Licences helper
 * Handle conditional fields display
 */
(function($){
    'use strict';
    function toggleFields(){
        var postier = $('#reduction_postier').is(':checked');
        $('.ufsc-field-identifiant-laposte')[ postier ? 'show' : 'hide' ]();
        var delegataire = $('#licence_delegataire').is(':checked');
        $('.ufsc-field-numero-delegataire')[ delegataire ? 'show' : 'hide' ]();
    }
    $(document).ready(function(){
        toggleFields();
        $('#reduction_postier, #licence_delegataire').on('change', toggleFields);
    });
})(jQuery);

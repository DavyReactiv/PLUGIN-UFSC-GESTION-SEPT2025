(function($){
    'use strict';

    function toggleFileActions($row, enabled, url) {
        var safeUrl = url || '#';
        $row.find('.ufsc-doc-view, .ufsc-doc-download').attr('href', safeUrl).prop('disabled', !enabled);
        if (enabled) {
            $row.find('.ufsc-doc-view, .ufsc-doc-download').removeAttr('disabled');
        }
    }

    $(document).on('click', '.ufsc-doc-replace', function(e){
        e.preventDefault();

        var $row = $(this).closest('.ufsc-doc-row');
        var frame = wp.media({
            title: (window.ufscClubDocsL10n && ufscClubDocsL10n.chooseFile) || 'Choisir un fichier',
            button: { text: (window.ufscClubDocsL10n && ufscClubDocsL10n.useFile) || 'Utiliser ce fichier' },
            multiple: false
        });

        frame.on('select', function(){
            var attachment = frame.state().get('selection').first().toJSON();
            $row.find('.ufsc-doc-attachment-id').val(attachment.id);
            $row.find('.ufsc-doc-remove-flag').val('0');

            $row.find('.ufsc-doc-badge.no-file').remove();
            $row.find('.ufsc-doc-file-name').text(attachment.title || attachment.filename || 'Fichier').show();
            $row.find('.ufsc-doc-file-meta').text((attachment.filesizeHuman || '') + (attachment.dateFormatted ? ' · ' + attachment.dateFormatted : '')).show();

            toggleFileActions($row, true, attachment.url || '#');
        });

        frame.open();
    });

    $(document).on('click', '.ufsc-doc-remove', function(e){
        e.preventDefault();

        var $row = $(this).closest('.ufsc-doc-row');
        $row.find('.ufsc-doc-attachment-id').val('');
        $row.find('.ufsc-doc-remove-flag').val('1');
        $row.find('.ufsc-doc-file-name').text('');
        $row.find('.ufsc-doc-file-meta').text('');

        if (!$row.find('.ufsc-doc-badge.no-file').length) {
            $row.find('td').eq(1).prepend('<span class="ufsc-doc-badge no-file"><span class="dashicons dashicons-warning" style="font-size:14px;width:14px;height:14px"></span>Aucun fichier</span>');
        }

        toggleFileActions($row, false, '#');
    });
})(jQuery);

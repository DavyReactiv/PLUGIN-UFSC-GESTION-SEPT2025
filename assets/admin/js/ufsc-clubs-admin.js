/* UFSC Clubs admin page enhancements */
jQuery(function($) {
    var $selectAll = $('#select-all-club');
    var $clubCheckboxes = $('input[name="club_ids[]"]');

    $selectAll.on('change', function() {
        $clubCheckboxes.prop('checked', $(this).prop('checked'));
    });

    $clubCheckboxes.on('change', function() {
        var total = $clubCheckboxes.length;
        var checked = $clubCheckboxes.filter(':checked').length;
        $selectAll.prop('checked', total > 0 && total === checked);
    });

    $('#bulk-actions-form').on('submit', function(e) {
        var action = $('#bulk-action-selector').val();
        if ('delete' === action && ! window.confirm('Confirmer la suppression des clubs sélectionnés ?')) {
            e.preventDefault();
        }
    });
});

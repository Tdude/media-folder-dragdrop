// Media Folder Drag & Drop Debug JS
// All debug JavaScript for the plugin should be placed here.
// Remove or rename this file to disable JS debugging.

jQuery(document).ready(function($) {
    // Debug: log all media rows and their badges
    $('tbody tr').each(function() {
        var $row = $(this);
        var id = $row.find('th.check-column input[type="checkbox"]').val();
        var badges = [];
        $row.find('.media-folder-badge').each(function() {
            badges.push($(this).data('folder'));
        });
        console.log('Media row', id, 'badges:', badges);
    });
});

// Optionally: Add more JS debug utilities here as needed.
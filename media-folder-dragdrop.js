jQuery(document).ready(function($) {
    // Track current folder selection
    var currentFolderId = null;

    // Drag-and-drop: make attachments draggable
    $(document).on('mouseenter', '.attachment', function() {
        $(this).draggable({
            revert: true,
            helper: 'clone',
            appendTo: 'body',
            zIndex: 10000
        });
    });

    // Bulk move
    $('#bulk-action-selector-top, #bulk-action-selector-bottom').append('<option value="move_to_folder">Move to Folder</option>');
    $(document).on('click', '#doaction, #doaction2', function(e) {
        var action = $(this).closest('form').find('select[name^="action"]:visible').val();
        if (action === 'move_to_folder') {
            e.preventDefault();
            var ids = [];
            $('tbody th.check-column input[type="checkbox"]:checked').each(function() {
                ids.push($(this).val());
            });
            showFolderPicker(function(folder_id) {
                moveMediaToFolder(ids, folder_id);
            });
        }
    });

    function showFolderPicker(callback) {
        var $modal = $('<div class="media-folder-modal"><div class="modal-content"><h3>Select Folder</h3><div class="modal-tree"></div><div class="modal-actions"><a href="#" class="button button-primary select-folder">Move Here</a> <a href="#" class="button close-modal">Cancel</a></div></div></div>');
        $.post(MediaFolderDragDrop.ajax_url, { action: 'get_media_folders' }, function(resp) {
            if (resp.success) {
                var html = renderFolderTree(resp.data, 0);
                $modal.find('.modal-tree').html(html);
            }
        });
        $('body').append($modal);
        var selectedId = null;
        $modal.on('click', '.folder-label', function(e) {
            e.stopPropagation();
            $modal.find('.folder-label.selected').removeClass('selected');
            $(this).addClass('selected');
            selectedId = $(this).closest('li').data('id');
        });
        $modal.on('click', '.select-folder', function(e) {
            e.preventDefault();
            if (selectedId) {
                callback(selectedId);
                $modal.remove();
            }
        });
        $modal.on('click', '.close-modal', function(e) {
            e.preventDefault();
            $modal.remove();
        });
    }

    function renderFolderTree(folders, parent) {
        var html = '<ul class="folder-list">';
        $.each(folders, function(i, folder) {
            var hasChildren = folder.children && folder.children.length;
            html += '<li data-id="'+folder.term_id+'">';
            if (hasChildren) {
                html += '<span class="toggle-folder" data-collapsed="true">▶</span> ';
            } else {
                html += '<span class="toggle-folder-empty"></span>';
            }
            html += '<span class="folder-label">' + folder.name + '</span>';
            if (hasChildren) {
                html += '<div class="folder-children" style="display:none;">' + renderFolderTree(folder.children, folder.term_id) + '</div>';
            }
            html += '</li>';
        });
        html += '</ul>';
        return html;
    }

    function moveMediaToFolder(ids, folder_id) {
        $.post(MediaFolderDragDrop.ajax_url, {
            action: 'move_media_to_folder',
            nonce: MediaFolderDragDrop.nonce,
            media_ids: ids,
            folder_id: folder_id
        }, function(resp) {
            if (resp.success) location.reload();
            else alert('Failed to move.');
        });
    }

    // Copy URL or Title on right click
    $(document).on('contextmenu', '.attachment, .row-title', function(e) {
        e.preventDefault();
        var url = $(this).find('img').attr('src') || $(this).closest('tr').find('.column-url').text();
        var title = $(this).attr('title') || $(this).text();
        var menu = $('<div class="media-folder-context"><a href="#" class="copy-url">Copy URL</a><a href="#" class="copy-title">Copy Title</a></div>');
        $('body').append(menu);
        menu.css({ top: e.pageY, left: e.pageX, position: 'absolute', zIndex: 99999 });
        menu.on('click', '.copy-url', function(ev) {
            ev.preventDefault();
            navigator.clipboard.writeText(url);
            menu.remove();
        });
        menu.on('click', '.copy-title', function(ev) {
            ev.preventDefault();
            navigator.clipboard.writeText(title);
            menu.remove();
        });
        $(document).on('click.contextmenu', function() { menu.remove(); $(document).off('click.contextmenu'); });
    });

    // --- Force badge refresh after page load and after folder move ---
    $(window).on('load', function() {
        setTimeout(addMediaFolderBadges, 500);
    });
    $(document).ajaxComplete(function(e, xhr, settings) {
        if (settings.url.indexOf('action=move_media_to_folder') !== -1) {
            setTimeout(addMediaFolderBadges, 300);
        }
    });

    // --- Add folder badges to media rows (robust for all IDs) ---
    function addMediaFolderBadges() {
        var idMap = {};
        $('tbody tr').each(function() {
            var id = $(this).find('th.check-column input[type="checkbox"]').val();
            if (id) idMap[id] = $(this);
        });
        var ids = Object.keys(idMap);
        if (ids.length === 0) return;
        $.post(MediaFolderDragDrop.ajax_url, { action: 'get_media_folders_for_media', ids: ids }, function(resp) {
            console.log('AJAX badge response:', resp);
            if (!resp.success) return;
            $.each(resp.data, function(id, folders) {
                var $row = idMap[id];
                if (!$row) return;
                $row.find('.media-folder-badge').remove();
                $.each(folders, function(i, folder) {
                    var badge = $('<span class="media-folder-badge" data-folder="'+folder.term_id+'">'+folder.name+'</span>');
                    $row.find('.row-title').after(badge);
                });
            });
        });
    }
    addMediaFolderBadges();

    // --- Debug: log all media rows and their badges ---
    window.mediaFolderDebug = function() {
        $('tbody tr').each(function() {
            var $row = $(this);
            var id = $row.find('th.check-column input[type="checkbox"]').val();
            var badges = [];
            $row.find('.media-folder-badge').each(function() {
                badges.push($(this).data('folder'));
            });
            console.log('Media row', id, 'badges:', badges);
        });
    };
    // Call this in console: mediaFolderDebug();
});

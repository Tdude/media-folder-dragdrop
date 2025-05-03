# Media Folder Drag & Drop

Categorize media files with drag-and-drop folders in the WordPress Media Library.

## Description

This plugin enhances the WordPress Media Library by allowing users to organize media attachments into hierarchical folders using a drag-and-drop interface. It registers a custom taxonomy called **Media Folders** for attachments, enabling folder-based categorization and filtering of media files directly within the admin area.

## Challenges

I spent a lot of time trying to get the seemingly simple task of filtering the WordPress Media Library in list view by custom "folders" (taxonomies) to work with the default UI. Unfortunately, WordPress does not natively support this for attachments, and attempts to integrate custom taxonomies for filtering in the Media Library list view are met with significant limitations.

There are unresolved tickets in the WordPress Core Trac regarding this issue, but as of now, no official solution exists.  
If you have any ideas on how to make this work without creating a custom post type, please let me know! In my opinion, this is a shortcoming in WordPress that forces developers to create custom UI for media filtering-something that should be available by default.

> See: [WordPress Core Ticket #22938](https://core.trac.wordpress.org/ticket/22938) – "Allow filtering of media library by custom taxonomy".

## Features

- Adds a hierarchical **Media Folder** taxonomy for media attachments.
- Drag-and-drop interface to move media files between folders.
- AJAX-powered folder creation and folder tree retrieval.
- Custom admin submenu pages for filtering media by folder and adding new folders.
- Folder filtering view with pagination and bulk actions UI.
- Supports REST API integration for media folder terms.
- Enqueues necessary JavaScript and CSS only on relevant admin pages.
- Security checks with nonces for AJAX requests.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/media-folder-drag-drop` directory or install via the WordPress plugin uploader.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to the Media Library (`Media > Library`) to start organizing media files into folders.
4. Use the **Folder Filter** submenu under Media to filter media by folder.
5. Use the **Add New Folder** submenu under Media to create new media folders.

## Usage

- **Organize media:** In the Media Library, drag media items onto folder names to categorize them.
- **Filter media:** Use the **Folder Filter** submenu to view media items within a specific folder.
- **Add folders:** Use the **Add New Folder** submenu to create new folders and optionally assign parent folders for hierarchy.
- **Bulk actions:** Select multiple media items in the Folder Filter view to apply bulk moves or deletions (UI present; functionality may require further extension).
- All folder operations are AJAX-powered for a smooth user experience without page reloads.

## Frequently Asked Questions (FAQ)

**Q: Can I create nested folders?**  
A: Yes, the plugin supports hierarchical folders allowing you to create parent and child folders.

**Q: Does this plugin affect the front-end media display?**  
A: No, the plugin currently manages media organization within the WordPress admin area only.

**Q: Is the drag-and-drop functionality compatible with all browsers?**  
A: The plugin uses jQuery UI Draggable and Droppable, which are widely supported in modern browsers.

**Q: Can I use this plugin with other media management plugins?**  
A: It registers a custom taxonomy for attachments and should be compatible, but conflicts depend on other plugins’ implementations.

## Screenshots

*(Add screenshots showing the drag-and-drop interface, folder filter submenu, and add new folder form here.)*

## Changelog

### 1.0.0
- Initial release with drag-and-drop media folder taxonomy.
- AJAX folder creation and media assignment.
- Custom admin views for folder filtering and folder creation.
- Bulk action UI and pagination for filtered media.

## Upgrade Notice

N/A - initial release.

## Credits

- Developed by github.com/Tdude
- Uses WordPress core APIs and jQuery UI components.

## License

This plugin is licensed under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

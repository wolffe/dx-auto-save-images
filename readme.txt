=== DX Auto Save Images ===
Contributors: butterflymedia
Tags: images, auto save, remote images, external images, media
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.6.0
License: GNU General Public License v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Update URI: https://getbutterfly.com/wordpress-plugins/dx-auto-save-images/
Text Domain: dx-auto-save-images

Automatically downloads remote images to the local media library when a post is saved, and optionally sets the first image as the featured image.

== Description ==

When you paste content into WordPress from an external source, any images in that content continue to be served from the original remote server. Those images can disappear at any time — the remote server may go offline, change its URL structure, block hotlinking, or simply be decommissioned.

DX Auto Save Images solves this automatically. Every time a post is saved, the plugin scans the content for external image URLs, downloads each image to your WordPress media library, and replaces the remote URL in the post content with the new local one. It works silently in the background and requires no manual steps.

**Features:**

* Works with both the block editor (Gutenberg) and the classic editor
* Downloads remote images to the local media library on post save
* Replaces remote image URLs in post content with local URLs
* Optionally disables WordPress thumbnail generation for downloaded images
* Optionally sets the first downloaded image as the post's featured image
* Per-post toggle to skip downloading for a specific post (block editor sidebar and classic editor)
* Universal filename sanitization — handles any language, special characters, and spaces
* URL and content-type validation to prevent saving non-image responses

== Installation ==

1. Upload the `dx-auto-save-images` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **Auto Save Images** in the admin menu to configure the options

== Frequently Asked Questions ==

= Does this work with the block editor? =

Yes. The plugin hooks into `wp_insert_post_data`, which fires for all post saves regardless of whether they originate from the block editor (REST API) or the classic editor.

= Does it re-download images that are already local? =

No. The plugin checks each image URL against your site's base URL before attempting a download. Any image already hosted on your domain is skipped.

= What happens if an image download fails? =

If a download fails for any reason (network error, non-200 response, non-image content type, invalid URL scheme), the original remote URL is left unchanged in the post content. The post still saves normally.

= What filename will the downloaded image have? =

The original filename from the URL is used, after URL-decoding and running it through WordPress's `sanitize_file_name()`. If sanitization removes all characters from the filename stem (for example a filename made entirely of symbols), the file is named using an MD5 hash of the source URL plus the original extension.

== Screenshots ==

1. Plugin settings page

== Changelog ==

= 1.6.0 =
* Fixed: CSRF vulnerability (CVE-2023-40671) — added nonce verification and capability check to settings form
* Fixed: Images not downloading in the block editor — switched from `content_save_pre` to `wp_insert_post_data`
* Fixed: PHP 8.x compatibility — added `isset()` and `is_array()` guards throughout
* Improved: Replaced `file_get_contents()` with `wp_remote_get()` and added HTTP status and content-type validation
* Improved: Universal filename sanitization using `sanitize_file_name()` and `urldecode()`, replacing the "Chinese filename" MD5 option
* Added: Block editor sidebar toggle for per-post skip option (via registered post meta and JS sidebar panel)
* Added: URL scheme validation to block non-HTTP(S) sources
* Removed: "Chinese filename support" option (now handled automatically)
* Removed: Theme Shop submenu and third-party ad scripts
* Updated: All UI strings translated to English
* Updated: Plugin author, URI, and license to reflect current maintainer

= 1.4.0 =
* Added support for WordPress 3.5
* Added auto featured image option

= 1.3.1 =
* Bug fix

= 1.3.0 =
* Added Chinese filename support
* Added per-post toggle to skip saving remote images

= 1.2.0 =
* Added settings page with thumbnail generation option

= 1.0.1 =
* Fixed error when pasting video embed codes

= 1.0 =
* Initial release

== Upgrade Notice ==

= 1.6.0 =
This update fixes a reported CSRF vulnerability (CVE-2023-40671) and restores compatibility with the WordPress block editor and PHP 8.x. Upgrade strongly recommended.

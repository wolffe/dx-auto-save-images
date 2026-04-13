<?php
/*
Plugin Name: DX Auto Save Images
Plugin URI: https://getbutterfly.com/wordpress-plugins/dx-auto-save-images/
Description: Automatically downloads remote images to the local media library when a post is saved, and optionally sets the first image as the featured image.
Version: 1.6.0
Requires at least: 5.8
Requires PHP: 7.4
Tested up to: 6.9
Author: Ciprian Popescu
Author URI: https://getbutterfly.com/
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Update URI: https://getbutterfly.com/wordpress-plugins/dx-auto-save-images/
Text Domain: dx-auto-save-images
*/

class DX_Auto_Save_Images {

	function __construct() {
		// wp_insert_post_data fires for both REST API (block editor) and classic editor saves
		add_filter( 'wp_insert_post_data', array( $this, 'post_save_images' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'menu_page' ) );
		add_filter( 'intermediate_image_sizes_advanced', array( $this, 'remove_tmb' ) );
		add_action( 'submitpost_box', array( $this, 'submit_box' ) );
		add_action( 'submitpage_box', array( $this, 'submit_box' ) );
		add_action( 'init', array( $this, 'register_post_meta' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
	}

	// Register post meta so the block editor can read/write the skip toggle via REST API
	function register_post_meta() {
		register_post_meta( '', '_dx_skip_remote_images', array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
		) );
	}

	// Enqueue block editor sidebar toggle JS (only when per-post switch option is enabled)
	function enqueue_block_editor_assets() {
		$options = get_option( 'dx-auto-save-images-options' );
		if ( ! is_array( $options ) || empty( $options['switch'] ) || $options['switch'] !== 'yes' ) {
			return;
		}
		wp_enqueue_script(
			'dx-auto-save-images-block-editor',
			plugins_url( 'block-editor-toggle.js', __FILE__ ),
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data' ),
			'1.6.0'
		);
	}

	// Save remote images found in post content.
	// Uses wp_insert_post_data (fires for REST API and classic editor alike).
	function post_save_images( $data, $postarr ) {
		// Skip autosaves and auto-drafts
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return $data;
		if ( isset( $data['post_status'] ) && $data['post_status'] === 'auto-draft' ) return $data;
		if ( isset( $postarr['post_type'] ) && $postarr['post_type'] === 'revision' ) return $data;

		$post_id = ! empty( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;

		// Check per-post skip: classic editor checkbox OR block editor meta
		$skip = ( isset( $_POST['DS_switch'] ) && $_POST['DS_switch'] === 'not_save' )
		     || ( $post_id && get_post_meta( $post_id, '_dx_skip_remote_images', true ) === 'yes' );

		if ( $skip ) return $data;

		set_time_limit( 240 );
		$content = $data['post_content'];
		$preg    = preg_match_all( '/<img[^>]+src="([^"]+)"/i', stripslashes( $content ), $matches );
		if ( $preg && $post_id ) {
			$i = 1;
			foreach ( $matches[1] as $image_url ) {
				if ( empty( $image_url ) ) continue;
				if ( strpos( $image_url, get_bloginfo( 'url' ) ) === false ) {
					$res = $this->save_images( $image_url, $post_id, $i );
					if ( ! empty( $res['url'] ) ) {
						$content = str_replace( $image_url, $res['url'], $content );
					}
				}
				$i++;
			}
		}
		$data['post_content'] = $content;
		return $data;
	}

	// Download an external image and attach it to the post
	function save_images( $image_url, $post_id, $i ) {
		$options = get_option( 'dx-auto-save-images-options' );
		if ( ! is_array( $options ) ) $options = array();

		// Only allow HTTP/HTTPS URLs — blocks file://, data:, and other schemes
		if ( ! wp_http_validate_url( $image_url ) ) return array( 'url' => $image_url );

		$response = wp_remote_get( $image_url, array( 'timeout' => 30 ) );
		if ( is_wp_error( $response ) ) return array( 'url' => $image_url );

		// Only accept successful HTTP responses
		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) return array( 'url' => $image_url );

		// Only accept image content types
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		if ( strpos( $content_type, 'image/' ) === false ) return array( 'url' => $image_url );

		$file = wp_remote_retrieve_body( $response );
		if ( empty( $file ) ) return array( 'url' => $image_url );

		// Sanitize the filename universally (handles non-ASCII, spaces, special chars)
		$raw_name = urldecode( basename( parse_url( $image_url, PHP_URL_PATH ) ) );
		$filename  = sanitize_file_name( $raw_name );

		// If sanitization stripped everything (e.g. pure Chinese name), fall back to a hash
		if ( empty( pathinfo( $filename, PATHINFO_FILENAME ) ) ) {
			$ext      = pathinfo( $raw_name, PATHINFO_EXTENSION );
			$filename = md5( $image_url ) . ( $ext ? '.' . sanitize_file_name( $ext ) : '' );
		}

		$res = wp_upload_bits( $filename, '', $file );
		if ( ! empty( $res['error'] ) ) return array( 'url' => $image_url );

		$attach_id = $this->insert_attachment( $res['file'], $post_id );
		if ( ! empty( $options['post-tmb'] ) && $options['post-tmb'] === 'yes' && $i === 1 ) {
			set_post_thumbnail( $post_id, $attach_id );
		}
		return $res;
	}

	// Register image file as a WordPress attachment
	function insert_attachment( $file, $id ) {
		$dirs       = wp_upload_dir();
		$filetype   = wp_check_filetype( $file );
		$attachment = array(
			'guid'           => $dirs['baseurl'] . '/' . _wp_relative_upload_path( $file ),
			'post_mime_type' => $filetype['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$attach_id   = wp_insert_attachment( $attachment, $file, $id );
		$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
		wp_update_attachment_metadata( $attach_id, $attach_data );
		return $attach_id;
	}

	// Admin menu
	function menu_page() {
		add_menu_page(
			'DX Auto Save Images',
			'Auto Save Images',
			'manage_options',
			'DX-auto-save-images',
			array( $this, 'options_form' ),
			plugins_url( 'icon.png', __FILE__ )
		);
	}

	// Options page
	function options_form() {
		$options = $this->save_options();
		if ( ! is_array( $options ) ) {
			$options = array( 'tmb' => '', 'switch' => '', 'post-tmb' => '' );
		}
		include( 'options-form.php' );
	}

	// Save plugin options — verifies nonce and capability before writing (CSRF fix)
	function save_options() {
		if ( isset( $_POST['submit'] ) && $_POST['submit'] ) {
			// Verify nonce
			check_admin_referer( 'dx_auto_save_images_options', 'dx_nonce' );

			// Verify capability
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to change these settings.' ) );
			}

			$data = array(
				'tmb'      => isset( $_POST['tmb'] )      ? sanitize_text_field( $_POST['tmb'] )      : '',
				'switch'   => isset( $_POST['switch'] )   ? sanitize_text_field( $_POST['switch'] )   : '',
				'post-tmb' => isset( $_POST['post-tmb'] ) ? sanitize_text_field( $_POST['post-tmb'] ) : '',
			);
			update_option( 'dx-auto-save-images-options', $data );
		}
		return get_option( 'dx-auto-save-images-options' );
	}

	// Remove all intermediate thumbnail sizes if option enabled
	function remove_tmb( $sizes ) {
		$options = get_option( 'dx-auto-save-images-options' );
		if ( is_array( $options ) && ! empty( $options['tmb'] ) && $options['tmb'] === 'yes' ) {
			$sizes = array();
		}
		return $sizes;
	}

	// Footer block rendered at the bottom of the settings page
	function form_bottom() {
?>
	<div id="form-bottom" style="width:650px;border:1px dotted #ddd;background-color:#f7f7f7;padding:10px;margin-top:20px;">
		<p>DX Auto Save Images &mdash; <a href="https://getbutterfly.com/wordpress-plugins/dx-auto-save-images/" target="_blank">Plugin page</a> &mdash; &copy; 2013&ndash;2026 <a href="https://getbutterfly.com/" target="_blank">Ciprian Popescu</a></p>
	</div>
<?php
	}

	// Per-post skip toggle for classic editor
	function submit_box() {
		$options = get_option( 'dx-auto-save-images-options' );
		if ( is_array( $options ) && ! empty( $options['switch'] ) && $options['switch'] === 'yes' ) {
			echo '<span style="padding-bottom:5px;display:inline-block;"><input type="checkbox" name="DS_switch" value="not_save"/> Skip remote image download for this post.</span>';
		}
	}

}

new DX_Auto_Save_Images();

<style type="text/css">
#dx-auto-save-images{ width:650px; margin:20px 0;border:1px solid #ddd; background-color:#f7f7f7; padding:10px; }
#dx-auto-save-images span.des{ color:#999; margin-left:10px; }
#dx-auto-save-images label{ width:160px; display:inline-block; vertical-align:top; }
#dx-auto-save-images p { margin-bottom:14px; }
#dx-auto-save-images .option-desc { display:block; margin-left:160px; margin-top:4px; color:#666; font-size:12px; }
</style>

<div class="wrap">

	<h1>DX Auto Save Images &mdash; Settings</h1>

	<div id="dx-auto-save-images">
		<form action="" method="post">

			<?php wp_nonce_field( 'dx_auto_save_images_options', 'dx_nonce' ); ?>

			<p>
				<label for="tmb">Disable thumbnails:</label>
				<input type="checkbox" id="tmb" name="tmb" value="yes" <?php checked( 'yes', isset( $options['tmb'] ) ? $options['tmb'] : '' );?>/> Yes
				<span class="option-desc">When checked, WordPress will <strong>not</strong> generate resized thumbnail versions (small, medium, large) for images downloaded by this plugin. The original full-size image is still saved. Useful if you want to avoid extra disk usage from auto-generated sizes.</span>
			</p>

			<p>
				<label for="switch">Per-post skip toggle:</label>
				<input type="checkbox" id="switch" name="switch" value="yes" <?php checked( 'yes', isset( $options['switch'] ) ? $options['switch'] : '' );?>/> Yes
				<span class="option-desc">When checked, a toggle appears in each post&rsquo;s editor (classic editor: below the publish button; block editor: in the Status &amp; Visibility sidebar panel). Enabling the toggle on a specific post will skip downloading remote images for that post only.</span>
			</p>

			<p>
				<label for="post-tmb">Auto featured image:</label>
				<input type="checkbox" id="post-tmb" name="post-tmb" value="yes" <?php checked( 'yes', isset( $options['post-tmb'] ) ? $options['post-tmb'] : '' );?>/> Yes
				<span class="option-desc">When checked, the <strong>first</strong> remote image found in post content is automatically set as the post&rsquo;s featured image (thumbnail) after it is downloaded. Requires the active theme to support featured images.</span>
			</p>

			<?php submit_button( 'Save Settings' ); ?>
		</form>
	</div>

	<div style="clear:both;"></div>

	<?php $this->form_bottom(); ?>

</div>

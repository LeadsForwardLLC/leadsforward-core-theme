<?php
/**
 * SEO per-page meta box (pages, posts, services, service areas).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('add_meta_boxes', 'lf_seo_register_meta_box');
add_action('save_post', 'lf_seo_save_meta_box', 10, 2);

function lf_seo_register_meta_box(): void {
	$screens = ['page', 'post', 'lf_service', 'lf_service_area'];
	foreach ($screens as $screen) {
		add_meta_box(
			'lf-seo-meta',
			__('SEO', 'leadsforward-core'),
			'lf_seo_render_meta_box',
			$screen,
			'normal',
			'default'
		);
	}
}

function lf_seo_render_meta_box(\WP_Post $post): void {
	wp_nonce_field('lf_seo_meta_box', 'lf_seo_meta_nonce');
	$primary = (string) get_post_meta($post->ID, '_lf_seo_primary_keyword', true);
	$secondary = (string) get_post_meta($post->ID, '_lf_seo_secondary_keywords', true);
	$title = (string) get_post_meta($post->ID, '_lf_seo_meta_title', true);
	$description = (string) get_post_meta($post->ID, '_lf_seo_meta_description', true);
	$canonical = (string) get_post_meta($post->ID, '_lf_seo_canonical_url', true);
	$noindex = (string) get_post_meta($post->ID, '_lf_seo_noindex', true) === '1';
	$nofollow = (string) get_post_meta($post->ID, '_lf_seo_nofollow', true) === '1';
	$og_image_id = (int) get_post_meta($post->ID, '_lf_seo_og_image_id', true);
	$og_image_url = $og_image_id ? wp_get_attachment_image_url($og_image_id, 'medium') : '';
	$scripts = get_post_meta($post->ID, '_lf_seo_scripts', true);
	if (!is_array($scripts)) {
		$scripts = [];
	}
	$header_scripts = (string) ($scripts['header'] ?? '');
	$footer_scripts = (string) ($scripts['footer'] ?? '');
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="lf_seo_primary_keyword"><?php esc_html_e('Primary Target Keyword', 'leadsforward-core'); ?></label></th>
			<td><input type="text" class="large-text" id="lf_seo_primary_keyword" name="lf_seo_primary_keyword" value="<?php echo esc_attr($primary); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="lf_seo_secondary_keywords"><?php esc_html_e('Secondary Keywords', 'leadsforward-core'); ?></label></th>
			<td><textarea class="large-text" rows="2" id="lf_seo_secondary_keywords" name="lf_seo_secondary_keywords"><?php echo esc_textarea($secondary); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><label for="lf_seo_meta_title"><?php esc_html_e('Custom Meta Title', 'leadsforward-core'); ?></label></th>
			<td><input type="text" class="large-text" id="lf_seo_meta_title" name="lf_seo_meta_title" value="<?php echo esc_attr($title); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="lf_seo_meta_description"><?php esc_html_e('Custom Meta Description', 'leadsforward-core'); ?></label></th>
			<td><textarea class="large-text" rows="2" id="lf_seo_meta_description" name="lf_seo_meta_description"><?php echo esc_textarea($description); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><label for="lf_seo_canonical_url"><?php esc_html_e('Canonical URL', 'leadsforward-core'); ?></label></th>
			<td><input type="url" class="large-text" id="lf_seo_canonical_url" name="lf_seo_canonical_url" value="<?php echo esc_attr($canonical); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e('Indexing', 'leadsforward-core'); ?></th>
			<td>
				<label style="margin-right:16px;">
					<input type="checkbox" name="lf_seo_noindex" value="1" <?php checked($noindex); ?> />
					<?php esc_html_e('Noindex', 'leadsforward-core'); ?>
				</label>
				<label>
					<input type="checkbox" name="lf_seo_nofollow" value="1" <?php checked($nofollow); ?> />
					<?php esc_html_e('Nofollow', 'leadsforward-core'); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e('OG Image Override', 'leadsforward-core'); ?></th>
			<td>
				<div style="display:flex;align-items:center;gap:1rem;">
					<div>
						<img id="lf-seo-og-preview" src="<?php echo esc_url($og_image_url); ?>" style="max-height:80px;<?php echo $og_image_url ? '' : 'display:none;'; ?>" alt="" />
					</div>
					<input type="hidden" name="lf_seo_og_image_id" id="lf_seo_og_image_id" value="<?php echo esc_attr((string) $og_image_id); ?>" />
					<button type="button" class="button" id="lf-seo-og-select"><?php esc_html_e('Select Image', 'leadsforward-core'); ?></button>
					<button type="button" class="button" id="lf-seo-og-clear"><?php esc_html_e('Remove', 'leadsforward-core'); ?></button>
				</div>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="lf_seo_header_scripts_override"><?php esc_html_e('Header script override', 'leadsforward-core'); ?></label></th>
			<td>
				<textarea class="large-text code" rows="3" id="lf_seo_header_scripts_override" name="lf_seo_header_scripts_override"><?php echo esc_textarea($header_scripts); ?></textarea>
				<p class="description"><?php esc_html_e('Overrides global header scripts for this page only.', 'leadsforward-core'); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="lf_seo_footer_scripts_override"><?php esc_html_e('Footer script override', 'leadsforward-core'); ?></label></th>
			<td>
				<textarea class="large-text code" rows="3" id="lf_seo_footer_scripts_override" name="lf_seo_footer_scripts_override"><?php echo esc_textarea($footer_scripts); ?></textarea>
				<p class="description"><?php esc_html_e('Overrides global footer scripts for this page only.', 'leadsforward-core'); ?></p>
			</td>
		</tr>
	</table>
	<script>
		(function () {
			var frame;
			var selectBtn = document.getElementById('lf-seo-og-select');
			var clearBtn = document.getElementById('lf-seo-og-clear');
			var input = document.getElementById('lf_seo_og_image_id');
			var preview = document.getElementById('lf-seo-og-preview');
			if (selectBtn) {
				selectBtn.addEventListener('click', function (e) {
					e.preventDefault();
					if (frame) { frame.open(); return; }
					frame = wp.media({ title: 'Select OG Image', button: { text: 'Use image' }, multiple: false });
					frame.on('select', function () {
						var attachment = frame.state().get('selection').first().toJSON();
						if (input) input.value = attachment.id;
						if (preview) { preview.src = attachment.url; preview.style.display = 'block'; }
					});
					frame.open();
				});
			}
			if (clearBtn) {
				clearBtn.addEventListener('click', function (e) {
					e.preventDefault();
					if (input) input.value = '';
					if (preview) { preview.src = ''; preview.style.display = 'none'; }
				});
			}
		})();
	</script>
	<?php
}

function lf_seo_save_meta_box(int $post_id, \WP_Post $post): void {
	if (!isset($_POST['lf_seo_meta_nonce']) || !wp_verify_nonce($_POST['lf_seo_meta_nonce'], 'lf_seo_meta_box')) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (!current_user_can('edit_post', $post_id)) {
		return;
	}
	$primary = isset($_POST['lf_seo_primary_keyword']) ? sanitize_text_field(wp_unslash($_POST['lf_seo_primary_keyword'])) : '';
	$secondary = isset($_POST['lf_seo_secondary_keywords']) ? sanitize_textarea_field(wp_unslash($_POST['lf_seo_secondary_keywords'])) : '';
	$title = isset($_POST['lf_seo_meta_title']) ? sanitize_text_field(wp_unslash($_POST['lf_seo_meta_title'])) : '';
	$description = isset($_POST['lf_seo_meta_description']) ? sanitize_textarea_field(wp_unslash($_POST['lf_seo_meta_description'])) : '';
	$canonical = isset($_POST['lf_seo_canonical_url']) ? esc_url_raw(wp_unslash($_POST['lf_seo_canonical_url'])) : '';
	$noindex = !empty($_POST['lf_seo_noindex']) ? '1' : '';
	$nofollow = !empty($_POST['lf_seo_nofollow']) ? '1' : '';
	$og_image_id = isset($_POST['lf_seo_og_image_id']) ? (int) $_POST['lf_seo_og_image_id'] : 0;
	$header_scripts = isset($_POST['lf_seo_header_scripts_override']) ? wp_unslash($_POST['lf_seo_header_scripts_override']) : '';
	$footer_scripts = isset($_POST['lf_seo_footer_scripts_override']) ? wp_unslash($_POST['lf_seo_footer_scripts_override']) : '';

	lf_seo_update_post_meta($post_id, '_lf_seo_primary_keyword', $primary);
	lf_seo_update_post_meta($post_id, '_lf_seo_secondary_keywords', $secondary);
	lf_seo_update_post_meta($post_id, '_lf_seo_meta_title', $title);
	lf_seo_update_post_meta($post_id, '_lf_seo_meta_description', $description);
	lf_seo_update_post_meta($post_id, '_lf_seo_canonical_url', $canonical);
	lf_seo_update_post_meta($post_id, '_lf_seo_noindex', $noindex);
	lf_seo_update_post_meta($post_id, '_lf_seo_nofollow', $nofollow);
	lf_seo_update_post_meta($post_id, '_lf_seo_og_image_id', $og_image_id ? (string) $og_image_id : '');

	$header_scripts = function_exists('lf_seo_sanitize_scripts') ? lf_seo_sanitize_scripts((string) $header_scripts) : '';
	$footer_scripts = function_exists('lf_seo_sanitize_scripts') ? lf_seo_sanitize_scripts((string) $footer_scripts) : '';
	if ($header_scripts === '' && $footer_scripts === '') {
		delete_post_meta($post_id, '_lf_seo_scripts');
	} else {
		update_post_meta($post_id, '_lf_seo_scripts', [
			'header' => $header_scripts,
			'footer' => $footer_scripts,
		]);
	}

	if (function_exists('lf_seo_register_keyword_map_for_post')) {
		lf_seo_register_keyword_map_for_post($post_id, $primary);
	}
}

function lf_seo_update_post_meta(int $post_id, string $key, string $value): void {
	if ($value === '') {
		delete_post_meta($post_id, $key);
		return;
	}
	update_post_meta($post_id, $key, $value);
}

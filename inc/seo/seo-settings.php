<?php
/**
 * SEO settings panel (LeadsForward → SEO). Stores all settings in lf_seo_settings option.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_menu', 'lf_seo_register_menu', 40);
add_action('admin_init', 'lf_seo_handle_save');
add_action('admin_enqueue_scripts', 'lf_seo_admin_assets');

function lf_seo_register_menu(): void {
	add_submenu_page(
		'lf-ops',
		__('SEO', 'leadsforward-core'),
		__('SEO', 'leadsforward-core'),
		'edit_theme_options',
		'lf-seo',
		'lf_seo_render_settings_page'
	);
}

function lf_seo_admin_assets(string $hook): void {
	if (!in_array($hook, ['leadsforward_page_lf-seo'], true)) {
		return;
	}
	wp_enqueue_media();
}

function lf_seo_get_settings(): array {
	$defaults = [
		'general' => [
			'title_template' => '{{page_title}} | {{city}} | {{brand}}',
			'meta_description_template' => '{{page_title}} in {{city}} by {{brand}}. Call today for fast service.',
			'title_separator' => '|',
			'append_brand' => true,
		],
		'indexing' => [
			'noindex_archives' => true,
			'noindex_search' => true,
			'noindex_paginated' => true,
		],
		'social' => [
			'default_og_image_id' => 0,
			'facebook_app_id' => '',
			'twitter_card' => 'summary_large_image',
		],
		'schema' => [
			'organization_type' => 'Organization',
			'enable_local_business' => true,
			'enable_service' => true,
		],
		'sitemap' => [
			'enable' => true,
			'include_services' => true,
			'include_service_areas' => true,
			'include_posts' => true,
		],
		'ai' => [
			'enable_auto_keywords' => true,
			'enable_keyword_map' => true,
			'enable_keyword_density' => true,
		],
	];
	$saved = get_option('lf_seo_settings', []);
	if (!is_array($saved)) {
		$saved = [];
	}
	return array_replace_recursive($defaults, $saved);
}

function lf_seo_get_setting(string $path, $default = '') {
	$settings = lf_seo_get_settings();
	$segments = array_filter(explode('.', $path));
	$current = $settings;
	foreach ($segments as $segment) {
		if (!is_array($current) || !array_key_exists($segment, $current)) {
			return $default;
		}
		$current = $current[$segment];
	}
	return $current;
}

function lf_seo_handle_save(): void {
	if (!isset($_POST['lf_seo_settings_nonce'])) {
		return;
	}
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	if (!wp_verify_nonce($_POST['lf_seo_settings_nonce'], 'lf_seo_settings')) {
		return;
	}
	$settings = lf_seo_get_settings();

	$title_template = isset($_POST['lf_seo_title_template']) ? sanitize_text_field(wp_unslash($_POST['lf_seo_title_template'])) : '';
	$meta_template = isset($_POST['lf_seo_meta_description_template']) ? sanitize_textarea_field(wp_unslash($_POST['lf_seo_meta_description_template'])) : '';
	$title_separator = isset($_POST['lf_seo_title_separator']) ? sanitize_text_field(wp_unslash($_POST['lf_seo_title_separator'])) : '|';

	$settings['general']['title_template'] = $title_template !== '' ? $title_template : '{{page_title}} | {{city}} | {{brand}}';
	$settings['general']['meta_description_template'] = $meta_template;
	$settings['general']['title_separator'] = $title_separator !== '' ? $title_separator : '|';
	$settings['general']['append_brand'] = !empty($_POST['lf_seo_append_brand']);

	$settings['indexing']['noindex_archives'] = !empty($_POST['lf_seo_noindex_archives']);
	$settings['indexing']['noindex_search'] = !empty($_POST['lf_seo_noindex_search']);
	$settings['indexing']['noindex_paginated'] = !empty($_POST['lf_seo_noindex_paginated']);

	$settings['social']['default_og_image_id'] = isset($_POST['lf_seo_default_og_image_id']) ? (int) $_POST['lf_seo_default_og_image_id'] : 0;
	$settings['social']['facebook_app_id'] = isset($_POST['lf_seo_facebook_app_id']) ? sanitize_text_field(wp_unslash($_POST['lf_seo_facebook_app_id'])) : '';
	$twitter_card = isset($_POST['lf_seo_twitter_card']) ? sanitize_text_field(wp_unslash($_POST['lf_seo_twitter_card'])) : 'summary_large_image';
	$settings['social']['twitter_card'] = in_array($twitter_card, ['summary', 'summary_large_image'], true) ? $twitter_card : 'summary_large_image';

	$org_type = isset($_POST['lf_seo_organization_type']) ? sanitize_text_field(wp_unslash($_POST['lf_seo_organization_type'])) : 'Organization';
	$allowed_org_types = [
		'Organization',
		'LocalBusiness',
		'HomeAndConstructionBusiness',
		'ProfessionalService',
		'GeneralContractor',
		'RoofingContractor',
		'Plumber',
		'HVACBusiness',
		'LandscapingBusiness',
	];
	$settings['schema']['organization_type'] = in_array($org_type, $allowed_org_types, true) ? $org_type : 'Organization';
	$settings['schema']['enable_local_business'] = !empty($_POST['lf_seo_enable_local_business']);
	$settings['schema']['enable_service'] = !empty($_POST['lf_seo_enable_service']);

	$settings['sitemap']['enable'] = !empty($_POST['lf_seo_sitemap_enable']);
	$settings['sitemap']['include_services'] = !empty($_POST['lf_seo_sitemap_include_services']);
	$settings['sitemap']['include_service_areas'] = !empty($_POST['lf_seo_sitemap_include_service_areas']);
	$settings['sitemap']['include_posts'] = !empty($_POST['lf_seo_sitemap_include_posts']);

	$settings['ai']['enable_auto_keywords'] = !empty($_POST['lf_seo_ai_auto_keywords']);
	$settings['ai']['enable_keyword_map'] = !empty($_POST['lf_seo_ai_keyword_map']);
	$settings['ai']['enable_keyword_density'] = !empty($_POST['lf_seo_ai_keyword_density']);

	update_option('lf_seo_settings', $settings);
	wp_safe_redirect(add_query_arg('saved', '1', admin_url('admin.php?page=lf-seo')));
	exit;
}

function lf_seo_render_settings_page(): void {
	$settings = lf_seo_get_settings();
	$og_id = (int) ($settings['social']['default_og_image_id'] ?? 0);
	$og_url = $og_id ? wp_get_attachment_image_url($og_id, 'medium') : '';
	$org_type = (string) ($settings['schema']['organization_type'] ?? 'Organization');
	$saved = isset($_GET['saved']) && $_GET['saved'] === '1';
	?>
	<div class="wrap">
		<h1><?php esc_html_e('SEO', 'leadsforward-core'); ?></h1>
		<?php if ($saved) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('SEO settings saved.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field('lf_seo_settings', 'lf_seo_settings_nonce'); ?>

			<h2><?php esc_html_e('General', 'leadsforward-core'); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lf_seo_title_template"><?php esc_html_e('Default Title Template', 'leadsforward-core'); ?></label></th>
					<td><input type="text" class="large-text" id="lf_seo_title_template" name="lf_seo_title_template" value="<?php echo esc_attr((string) ($settings['general']['title_template'] ?? '')); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="lf_seo_meta_description_template"><?php esc_html_e('Default Meta Description Template', 'leadsforward-core'); ?></label></th>
					<td><textarea class="large-text" rows="2" id="lf_seo_meta_description_template" name="lf_seo_meta_description_template"><?php echo esc_textarea((string) ($settings['general']['meta_description_template'] ?? '')); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="lf_seo_title_separator"><?php esc_html_e('Title Separator', 'leadsforward-core'); ?></label></th>
					<td><input type="text" class="small-text" id="lf_seo_title_separator" name="lf_seo_title_separator" value="<?php echo esc_attr((string) ($settings['general']['title_separator'] ?? '|')); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Append Brand', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_append_brand" value="1" <?php checked(!empty($settings['general']['append_brand'])); ?> /> <?php esc_html_e('Append brand name to titles when missing.', 'leadsforward-core'); ?></label></td>
				</tr>
			</table>

			<h2><?php esc_html_e('Indexing', 'leadsforward-core'); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e('Noindex Archives', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_noindex_archives" value="1" <?php checked(!empty($settings['indexing']['noindex_archives'])); ?> /> <?php esc_html_e('Noindex all archive pages.', 'leadsforward-core'); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Noindex Search', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_noindex_search" value="1" <?php checked(!empty($settings['indexing']['noindex_search'])); ?> /> <?php esc_html_e('Noindex search results.', 'leadsforward-core'); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Noindex Paginated', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_noindex_paginated" value="1" <?php checked(!empty($settings['indexing']['noindex_paginated'])); ?> /> <?php esc_html_e('Noindex paginated pages.', 'leadsforward-core'); ?></label></td>
				</tr>
			</table>

			<h2><?php esc_html_e('Social', 'leadsforward-core'); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e('Default OG Image', 'leadsforward-core'); ?></th>
					<td>
						<div style="display:flex;align-items:center;gap:1rem;">
							<div>
								<img id="lf-seo-og-preview" src="<?php echo esc_url($og_url); ?>" style="max-height:80px;<?php echo $og_url ? '' : 'display:none;'; ?>" alt="" />
							</div>
							<input type="hidden" name="lf_seo_default_og_image_id" id="lf_seo_default_og_image_id" value="<?php echo esc_attr((string) $og_id); ?>" />
							<button type="button" class="button" id="lf-seo-og-select"><?php esc_html_e('Select Image', 'leadsforward-core'); ?></button>
							<button type="button" class="button" id="lf-seo-og-clear"><?php esc_html_e('Remove', 'leadsforward-core'); ?></button>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lf_seo_facebook_app_id"><?php esc_html_e('Facebook App ID', 'leadsforward-core'); ?></label></th>
					<td><input type="text" class="regular-text" id="lf_seo_facebook_app_id" name="lf_seo_facebook_app_id" value="<?php echo esc_attr((string) ($settings['social']['facebook_app_id'] ?? '')); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="lf_seo_twitter_card"><?php esc_html_e('Twitter Card Type', 'leadsforward-core'); ?></label></th>
					<td>
						<select name="lf_seo_twitter_card" id="lf_seo_twitter_card">
							<option value="summary" <?php selected(($settings['social']['twitter_card'] ?? '') === 'summary'); ?>><?php esc_html_e('Summary', 'leadsforward-core'); ?></option>
							<option value="summary_large_image" <?php selected(($settings['social']['twitter_card'] ?? '') === 'summary_large_image'); ?>><?php esc_html_e('Summary Large Image', 'leadsforward-core'); ?></option>
						</select>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e('Schema', 'leadsforward-core'); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lf_seo_organization_type"><?php esc_html_e('Organization Type', 'leadsforward-core'); ?></label></th>
					<td>
						<select name="lf_seo_organization_type" id="lf_seo_organization_type">
							<?php
							$org_types = [
								'Organization' => __('Organization', 'leadsforward-core'),
								'LocalBusiness' => __('LocalBusiness', 'leadsforward-core'),
								'HomeAndConstructionBusiness' => __('HomeAndConstructionBusiness', 'leadsforward-core'),
								'ProfessionalService' => __('ProfessionalService', 'leadsforward-core'),
								'GeneralContractor' => __('GeneralContractor', 'leadsforward-core'),
								'RoofingContractor' => __('RoofingContractor', 'leadsforward-core'),
								'Plumber' => __('Plumber', 'leadsforward-core'),
								'HVACBusiness' => __('HVACBusiness', 'leadsforward-core'),
								'LandscapingBusiness' => __('LandscapingBusiness', 'leadsforward-core'),
							];
							foreach ($org_types as $value => $label) {
								echo '<option value="' . esc_attr($value) . '"' . selected($org_type === $value, true, false) . '>' . esc_html($label) . '</option>';
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Enable LocalBusiness schema', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_enable_local_business" value="1" <?php checked(!empty($settings['schema']['enable_local_business'])); ?> /> <?php esc_html_e('Output LocalBusiness schema.', 'leadsforward-core'); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Enable Service schema', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_enable_service" value="1" <?php checked(!empty($settings['schema']['enable_service'])); ?> /> <?php esc_html_e('Output Service schema on service pages.', 'leadsforward-core'); ?></label></td>
				</tr>
			</table>

			<h2><?php esc_html_e('Sitemap', 'leadsforward-core'); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e('Enable XML sitemap', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_sitemap_enable" value="1" <?php checked(!empty($settings['sitemap']['enable'])); ?> /> <?php esc_html_e('Serve sitemap.xml.', 'leadsforward-core'); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Include Services', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_sitemap_include_services" value="1" <?php checked(!empty($settings['sitemap']['include_services'])); ?> /> <?php esc_html_e('Include service pages.', 'leadsforward-core'); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Include Service Areas', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_sitemap_include_service_areas" value="1" <?php checked(!empty($settings['sitemap']['include_service_areas'])); ?> /> <?php esc_html_e('Include service area pages.', 'leadsforward-core'); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Include Posts', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_sitemap_include_posts" value="1" <?php checked(!empty($settings['sitemap']['include_posts'])); ?> /> <?php esc_html_e('Include blog posts.', 'leadsforward-core'); ?></label></td>
				</tr>
			</table>

			<h2><?php esc_html_e('AI SEO Engine', 'leadsforward-core'); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e('Enable automatic keyword assignment', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_ai_auto_keywords" value="1" <?php checked(!empty($settings['ai']['enable_auto_keywords'])); ?> /> <?php esc_html_e('Assign primary keywords when content is generated.', 'leadsforward-core'); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Enable keyword-to-page mapping', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_ai_keyword_map" value="1" <?php checked(!empty($settings['ai']['enable_keyword_map'])); ?> /> <?php esc_html_e('Store assigned keywords in lf_keyword_map.', 'leadsforward-core'); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Enable keyword density enforcement', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_ai_keyword_density" value="1" <?php checked(!empty($settings['ai']['enable_keyword_density'])); ?> /> <?php esc_html_e('Apply density guardrails in AI content generation.', 'leadsforward-core'); ?></label></td>
				</tr>
			</table>

			<?php submit_button(__('Save SEO Settings', 'leadsforward-core')); ?>
		</form>
	</div>
	<script>
		(function () {
			var frame;
			var selectBtn = document.getElementById('lf-seo-og-select');
			var clearBtn = document.getElementById('lf-seo-og-clear');
			var input = document.getElementById('lf_seo_default_og_image_id');
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

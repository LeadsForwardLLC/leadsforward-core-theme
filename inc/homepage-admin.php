<?php
/**
 * LeadsForward → Homepage admin UI. Toggles, variants, copy fields. No layout reorder.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_init', 'lf_homepage_admin_save');

/**
 * Show notice on front page edit screen: content is controlled by LeadsForward.
 */
add_action('admin_notices', 'lf_homepage_editor_notice');

function lf_homepage_editor_notice(): void {
	$screen = get_current_screen();
	if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'page') {
		return;
	}
	$post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
	if ($post_id === 0) {
		return;
	}
	if ((int) get_option('page_on_front') !== $post_id) {
		return;
	}
	$url = admin_url('admin.php?page=lf-homepage-settings');
	echo '<div class="notice notice-info"><p><strong>' . esc_html__('LeadsForward:', 'leadsforward-core') . '</strong> ';
	echo wp_kses(
		sprintf(
			/* translators: %s: link to LeadsForward Homepage settings */
			__('This page is controlled by LeadsForward settings. Edit sections and copy at <a href="%s">LeadsForward → Homepage</a>.', 'leadsforward-core'),
			esc_url($url)
		),
		['a' => ['href' => true]]
	);
	echo '</p></div>';
}

function lf_homepage_admin_save(): void {
	if (!isset($_POST['lf_homepage_settings_nonce']) || !current_user_can('edit_theme_options')) {
		return;
	}
	if (!wp_verify_nonce($_POST['lf_homepage_settings_nonce'], 'lf_homepage_settings')) {
		return;
	}
	$order = lf_homepage_controller_order();
	$config = lf_get_homepage_section_config();
	$allowed_variants = ['default', 'a', 'b', 'c'];
	foreach ($order as $type) {
		$key_enabled = 'lf_hp_enabled_' . $type;
		$key_variant = 'lf_hp_variant_' . $type;
		$config[$type]['enabled'] = !empty($_POST[$key_enabled]);
		$v = isset($_POST[$key_variant]) && in_array($_POST[$key_variant], $allowed_variants, true) ? $_POST[$key_variant] : 'default';
		$config[$type]['variant'] = $v;
		if ($type === 'hero') {
			$config[$type]['hero_headline'] = isset($_POST['lf_hp_hero_headline']) ? sanitize_text_field($_POST['lf_hp_hero_headline']) : '';
			$config[$type]['hero_subheadline'] = isset($_POST['lf_hp_hero_subheadline']) ? sanitize_text_field($_POST['lf_hp_hero_subheadline']) : '';
			$config[$type]['hero_cta_override'] = isset($_POST['lf_hp_hero_cta_override']) ? sanitize_text_field($_POST['lf_hp_hero_cta_override']) : '';
		}
		if ($type === 'trust_reviews') {
			$n = isset($_POST['lf_hp_trust_max_items']) ? (int) $_POST['lf_hp_trust_max_items'] : 1;
			$config[$type]['trust_max_items'] = max(1, min(10, $n));
			$config[$type]['trust_heading'] = isset($_POST['lf_hp_trust_heading']) ? sanitize_text_field($_POST['lf_hp_trust_heading']) : '';
		}
		if ($type === 'service_grid') {
			$config[$type]['section_heading'] = isset($_POST['lf_hp_services_heading']) ? sanitize_text_field($_POST['lf_hp_services_heading']) : '';
			$config[$type]['section_intro'] = isset($_POST['lf_hp_services_intro']) ? sanitize_textarea_field($_POST['lf_hp_services_intro']) : '';
		}
		if ($type === 'service_areas') {
			$config[$type]['section_heading'] = isset($_POST['lf_hp_areas_heading']) ? sanitize_text_field($_POST['lf_hp_areas_heading']) : '';
			$config[$type]['section_intro'] = isset($_POST['lf_hp_areas_intro']) ? sanitize_textarea_field($_POST['lf_hp_areas_intro']) : '';
		}
		if ($type === 'faq_accordion') {
			$config[$type]['section_heading'] = isset($_POST['lf_hp_faq_heading']) ? sanitize_text_field($_POST['lf_hp_faq_heading']) : '';
			$config[$type]['section_intro'] = isset($_POST['lf_hp_faq_intro']) ? sanitize_textarea_field($_POST['lf_hp_faq_intro']) : '';
		}
		if ($type === 'cta') {
			$config[$type]['cta_headline'] = isset($_POST['lf_hp_cta_headline']) ? sanitize_text_field($_POST['lf_hp_cta_headline']) : '';
			$config[$type]['cta_primary_override'] = isset($_POST['lf_hp_cta_primary']) ? sanitize_text_field($_POST['lf_hp_cta_primary']) : '';
			$config[$type]['cta_secondary_override'] = isset($_POST['lf_hp_cta_secondary']) ? sanitize_text_field($_POST['lf_hp_cta_secondary']) : '';
			$config[$type]['cta_ghl_override'] = isset($_POST['lf_hp_cta_ghl']) ? wp_kses_post($_POST['lf_hp_cta_ghl']) : '';
		}
	}
	update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
	wp_redirect(admin_url('admin.php?page=lf-homepage-settings&saved=1'));
	exit;
}

function lf_homepage_admin_section_labels(): array {
	return [
		'hero'           => __('Hero', 'leadsforward-core'),
		'trust_reviews'  => __('Trust / Reviews', 'leadsforward-core'),
		'service_grid'   => __('Service Grid', 'leadsforward-core'),
		'service_areas'  => __('Service Areas', 'leadsforward-core'),
		'faq_accordion'  => __('FAQ Accordion', 'leadsforward-core'),
		'cta'            => __('Final CTA', 'leadsforward-core'),
		'map_nap'        => __('Map + NAP', 'leadsforward-core'),
	];
}

function lf_homepage_admin_render(): void {
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	$config = lf_get_homepage_section_config();
	$order = lf_homepage_controller_order();
	$labels = lf_homepage_admin_section_labels();
	$variants = ['default' => __('Default', 'leadsforward-core'), 'a' => __('Variant A', 'leadsforward-core'), 'b' => __('Variant B', 'leadsforward-core'), 'c' => __('Variant C', 'leadsforward-core')];
	$saved = isset($_GET['saved']) && $_GET['saved'] === '1';
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Homepage', 'leadsforward-core'); ?></h1>
		<?php if ($saved) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<p class="description"><?php esc_html_e('Sections appear in a fixed order. Turn sections on or off and edit copy below. Layout order cannot be changed.', 'leadsforward-core'); ?></p>
		<form method="post" action="">
			<?php wp_nonce_field('lf_homepage_settings', 'lf_homepage_settings_nonce'); ?>
			<table class="form-table lf-homepage-sections" role="presentation">
				<tbody>
				<?php foreach ($order as $type) :
					$sec = $config[$type] ?? [];
					$enabled = !empty($sec['enabled']);
					$variant = $sec['variant'] ?? 'default';
					$label = $labels[$type] ?? $type;
				?>
					<tr class="lf-homepage-section-row">
						<th scope="row">
							<label for="lf_hp_enabled_<?php echo esc_attr($type); ?>"><?php echo esc_html($label); ?></label>
						</th>
						<td>
							<label><input type="checkbox" name="lf_hp_enabled_<?php echo esc_attr($type); ?>" id="lf_hp_enabled_<?php echo esc_attr($type); ?>" value="1" <?php checked($enabled); ?> /> <?php esc_html_e('Show this section', 'leadsforward-core'); ?></label>
							&nbsp;&nbsp;
							<label><?php esc_html_e('Variant', 'leadsforward-core'); ?>
								<select name="lf_hp_variant_<?php echo esc_attr($type); ?>">
									<?php foreach ($variants as $v => $vlabel) : ?>
										<option value="<?php echo esc_attr($v); ?>" <?php selected($variant, $v); ?>><?php echo esc_html($vlabel); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						</td>
					</tr>
					<?php if ($type === 'hero') : ?>
					<tr class="lf-homepage-section-fields lf-homepage-hero-fields">
						<th scope="row"><label for="lf_hp_hero_headline"><?php esc_html_e('Hero headline', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="large-text" name="lf_hp_hero_headline" id="lf_hp_hero_headline" value="<?php echo esc_attr($sec['hero_headline'] ?? ''); ?>" placeholder="<?php esc_attr_e('e.g. Quality Roofing in [City]', 'leadsforward-core'); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="lf_hp_hero_subheadline"><?php esc_html_e('Hero subheadline', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="large-text" name="lf_hp_hero_subheadline" id="lf_hp_hero_subheadline" value="<?php echo esc_attr($sec['hero_subheadline'] ?? ''); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="lf_hp_hero_cta_override"><?php esc_html_e('Hero CTA override', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="regular-text" name="lf_hp_hero_cta_override" id="lf_hp_hero_cta_override" value="<?php echo esc_attr($sec['hero_cta_override'] ?? ''); ?>" /> <span class="description"><?php esc_html_e('Leave blank to use homepage CTA.', 'leadsforward-core'); ?></span></td>
					</tr>
					<?php endif; ?>
					<?php if ($type === 'service_grid') : ?>
					<tr>
						<th scope="row"><label for="lf_hp_services_heading"><?php esc_html_e('Services section heading', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="large-text" name="lf_hp_services_heading" id="lf_hp_services_heading" value="<?php echo esc_attr($sec['section_heading'] ?? ''); ?>" placeholder="<?php esc_attr_e('Our Services', 'leadsforward-core'); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="lf_hp_services_intro"><?php esc_html_e('Services intro text', 'leadsforward-core'); ?></label></th>
						<td><textarea class="large-text" name="lf_hp_services_intro" id="lf_hp_services_intro" rows="2"><?php echo esc_textarea($sec['section_intro'] ?? ''); ?></textarea></td>
					</tr>
					<?php endif; ?>
					<?php if ($type === 'service_areas') : ?>
					<tr>
						<th scope="row"><label for="lf_hp_areas_heading"><?php esc_html_e('Service areas heading', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="large-text" name="lf_hp_areas_heading" id="lf_hp_areas_heading" value="<?php echo esc_attr($sec['section_heading'] ?? ''); ?>" placeholder="<?php esc_attr_e('Service Areas', 'leadsforward-core'); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="lf_hp_areas_intro"><?php esc_html_e('Service areas intro', 'leadsforward-core'); ?></label></th>
						<td><textarea class="large-text" name="lf_hp_areas_intro" id="lf_hp_areas_intro" rows="2"><?php echo esc_textarea($sec['section_intro'] ?? ''); ?></textarea></td>
					</tr>
					<?php endif; ?>
					<?php if ($type === 'trust_reviews') : ?>
					<tr>
						<th scope="row"><label for="lf_hp_trust_heading"><?php esc_html_e('Social proof heading', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="large-text" name="lf_hp_trust_heading" id="lf_hp_trust_heading" value="<?php echo esc_attr($sec['trust_heading'] ?? ''); ?>" placeholder="<?php esc_attr_e('What Our Customers Say', 'leadsforward-core'); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="lf_hp_trust_max_items"><?php esc_html_e('Max reviews to show', 'leadsforward-core'); ?></label></th>
						<td><input type="number" name="lf_hp_trust_max_items" id="lf_hp_trust_max_items" value="<?php echo esc_attr((string) ($sec['trust_max_items'] ?? 1)); ?>" min="1" max="10" /> (1–10)</td>
					</tr>
					<?php endif; ?>
					<?php if ($type === 'faq_accordion') : ?>
					<tr>
						<th scope="row"><label for="lf_hp_faq_heading"><?php esc_html_e('FAQ section heading', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="large-text" name="lf_hp_faq_heading" id="lf_hp_faq_heading" value="<?php echo esc_attr($sec['section_heading'] ?? ''); ?>" placeholder="<?php esc_attr_e('Frequently Asked Questions', 'leadsforward-core'); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="lf_hp_faq_intro"><?php esc_html_e('FAQ intro', 'leadsforward-core'); ?></label></th>
						<td><textarea class="large-text" name="lf_hp_faq_intro" id="lf_hp_faq_intro" rows="2"><?php echo esc_textarea($sec['section_intro'] ?? ''); ?></textarea></td>
					</tr>
					<?php endif; ?>
					<?php if ($type === 'cta') : ?>
					<tr>
						<th scope="row"><label for="lf_hp_cta_headline"><?php esc_html_e('CTA headline', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="large-text" name="lf_hp_cta_headline" id="lf_hp_cta_headline" value="<?php echo esc_attr($sec['cta_headline'] ?? ''); ?>" placeholder="<?php esc_attr_e('Ready to get started?', 'leadsforward-core'); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="lf_hp_cta_primary"><?php esc_html_e('Section primary CTA', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="regular-text" name="lf_hp_cta_primary" id="lf_hp_cta_primary" value="<?php echo esc_attr($sec['cta_primary_override'] ?? ''); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="lf_hp_cta_secondary"><?php esc_html_e('Section secondary CTA', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="regular-text" name="lf_hp_cta_secondary" id="lf_hp_cta_secondary" value="<?php echo esc_attr($sec['cta_secondary_override'] ?? ''); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="lf_hp_cta_ghl"><?php esc_html_e('Section GHL embed override', 'leadsforward-core'); ?></label></th>
						<td><textarea class="large-text code" name="lf_hp_cta_ghl" id="lf_hp_cta_ghl" rows="4"><?php echo esc_textarea($sec['cta_ghl_override'] ?? ''); ?></textarea></td>
					</tr>
					<?php endif; ?>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e('Save Homepage Settings', 'leadsforward-core'); ?></button>
			</p>
		</form>
		<p class="description"><?php esc_html_e('Homepage CTA overrides (primary/secondary text, type) are in Theme Options → Homepage.', 'leadsforward-core'); ?></p>
	</div>
	<?php
}

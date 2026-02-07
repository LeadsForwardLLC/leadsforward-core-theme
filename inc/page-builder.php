<?php
/**
 * Page Builder Framework: shared registry, renderer, and admin UI for templates.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_PB_META_KEY = 'lf_pb_config';

add_action('add_meta_boxes', 'lf_pb_register_meta_box');
add_action('admin_enqueue_scripts', 'lf_pb_admin_assets');
add_action('save_post', 'lf_pb_handle_save', 10, 2);

function lf_pb_registry(): array {
	return [
		'service' => [
			'label' => __('Service Page', 'leadsforward-core'),
			'sections' => [
				[
					'id' => 'hero',
					'label' => __('Hero', 'leadsforward-core'),
					'enabled' => true,
					'fields' => [
						['key' => 'heading', 'label' => __('Headline', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
						['key' => 'subheading', 'label' => __('Subheadline', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
						['key' => 'cta_primary_override', 'label' => __('Primary CTA label', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
						['key' => 'cta_secondary_override', 'label' => __('Secondary CTA label', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
						['key' => 'cta_primary_action', 'label' => __('Primary CTA action', 'leadsforward-core'), 'type' => 'select', 'default' => 'quote', 'options' => [
							'quote' => __('Open Quote Builder', 'leadsforward-core'),
							'call'  => __('Call now', 'leadsforward-core'),
							'link'  => __('Link', 'leadsforward-core'),
						]],
						['key' => 'cta_primary_url', 'label' => __('Primary CTA URL', 'leadsforward-core'), 'type' => 'url', 'default' => ''],
						['key' => 'cta_secondary_action', 'label' => __('Secondary CTA action', 'leadsforward-core'), 'type' => 'select', 'default' => 'call', 'options' => [
							'call'  => __('Call now', 'leadsforward-core'),
							'quote' => __('Open Quote Builder', 'leadsforward-core'),
							'link'  => __('Link', 'leadsforward-core'),
						]],
						['key' => 'cta_secondary_url', 'label' => __('Secondary CTA URL', 'leadsforward-core'), 'type' => 'url', 'default' => ''],
					],
					'render' => 'lf_pb_render_service_hero',
				],
				[
					'id' => 'content',
					'label' => __('Content', 'leadsforward-core'),
					'enabled' => true,
					'fields' => [],
					'render' => 'lf_pb_render_service_content',
				],
				[
					'id' => 'cta',
					'label' => __('Final CTA', 'leadsforward-core'),
					'enabled' => true,
					'fields' => [
						['key' => 'cta_headline', 'label' => __('CTA headline', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
						['key' => 'cta_primary_override', 'label' => __('Primary CTA label', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
						['key' => 'cta_secondary_override', 'label' => __('Secondary CTA label', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
						['key' => 'cta_primary_action', 'label' => __('Primary CTA action', 'leadsforward-core'), 'type' => 'select', 'default' => '', 'options' => [
							''      => __('Use global', 'leadsforward-core'),
							'quote' => __('Open Quote Builder', 'leadsforward-core'),
							'call'  => __('Call now', 'leadsforward-core'),
							'link'  => __('Link', 'leadsforward-core'),
						]],
						['key' => 'cta_primary_url', 'label' => __('Primary CTA URL', 'leadsforward-core'), 'type' => 'url', 'default' => ''],
						['key' => 'cta_secondary_action', 'label' => __('Secondary CTA action', 'leadsforward-core'), 'type' => 'select', 'default' => '', 'options' => [
							''      => __('Use global', 'leadsforward-core'),
							'call'  => __('Call now', 'leadsforward-core'),
							'quote' => __('Open Quote Builder', 'leadsforward-core'),
							'link'  => __('Link', 'leadsforward-core'),
						]],
						['key' => 'cta_secondary_url', 'label' => __('Secondary CTA URL', 'leadsforward-core'), 'type' => 'url', 'default' => ''],
					],
					'render' => 'lf_pb_render_cta_block',
				],
				[
					'id' => 'related_areas',
					'label' => __('Related Service Areas', 'leadsforward-core'),
					'enabled' => true,
					'fields' => [],
					'render' => 'lf_pb_render_related_areas',
				],
			],
		],
		'service_area' => [
			'label' => __('Service Area Page', 'leadsforward-core'),
			'sections' => [
				[
					'id' => 'hero',
					'label' => __('Hero', 'leadsforward-core'),
					'enabled' => true,
					'fields' => [
						['key' => 'heading', 'label' => __('Headline', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
						['key' => 'subheading', 'label' => __('Subheadline', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
						['key' => 'cta_primary_override', 'label' => __('Primary CTA label', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
						['key' => 'cta_secondary_override', 'label' => __('Secondary CTA label', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
						['key' => 'cta_primary_action', 'label' => __('Primary CTA action', 'leadsforward-core'), 'type' => 'select', 'default' => 'quote', 'options' => [
							'quote' => __('Open Quote Builder', 'leadsforward-core'),
							'call'  => __('Call now', 'leadsforward-core'),
							'link'  => __('Link', 'leadsforward-core'),
						]],
						['key' => 'cta_primary_url', 'label' => __('Primary CTA URL', 'leadsforward-core'), 'type' => 'url', 'default' => ''],
						['key' => 'cta_secondary_action', 'label' => __('Secondary CTA action', 'leadsforward-core'), 'type' => 'select', 'default' => 'call', 'options' => [
							'call'  => __('Call now', 'leadsforward-core'),
							'quote' => __('Open Quote Builder', 'leadsforward-core'),
							'link'  => __('Link', 'leadsforward-core'),
						]],
						['key' => 'cta_secondary_url', 'label' => __('Secondary CTA URL', 'leadsforward-core'), 'type' => 'url', 'default' => ''],
					],
					'render' => 'lf_pb_render_area_hero',
				],
				[
					'id' => 'content',
					'label' => __('Content', 'leadsforward-core'),
					'enabled' => true,
					'fields' => [],
					'render' => 'lf_pb_render_area_content',
				],
				[
					'id' => 'cta',
					'label' => __('Final CTA', 'leadsforward-core'),
					'enabled' => true,
					'fields' => [
						['key' => 'cta_headline', 'label' => __('CTA headline', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
						['key' => 'cta_primary_override', 'label' => __('Primary CTA label', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
						['key' => 'cta_secondary_override', 'label' => __('Secondary CTA label', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
						['key' => 'cta_primary_action', 'label' => __('Primary CTA action', 'leadsforward-core'), 'type' => 'select', 'default' => '', 'options' => [
							''      => __('Use global', 'leadsforward-core'),
							'quote' => __('Open Quote Builder', 'leadsforward-core'),
							'call'  => __('Call now', 'leadsforward-core'),
							'link'  => __('Link', 'leadsforward-core'),
						]],
						['key' => 'cta_primary_url', 'label' => __('Primary CTA URL', 'leadsforward-core'), 'type' => 'url', 'default' => ''],
						['key' => 'cta_secondary_action', 'label' => __('Secondary CTA action', 'leadsforward-core'), 'type' => 'select', 'default' => '', 'options' => [
							''      => __('Use global', 'leadsforward-core'),
							'call'  => __('Call now', 'leadsforward-core'),
							'quote' => __('Open Quote Builder', 'leadsforward-core'),
							'link'  => __('Link', 'leadsforward-core'),
						]],
						['key' => 'cta_secondary_url', 'label' => __('Secondary CTA URL', 'leadsforward-core'), 'type' => 'url', 'default' => ''],
					],
					'render' => 'lf_pb_render_cta_block',
				],
				[
					'id' => 'related_services',
					'label' => __('Related Services', 'leadsforward-core'),
					'enabled' => true,
					'fields' => [],
					'render' => 'lf_pb_render_related_services',
				],
			],
		],
	];
}

function lf_pb_get_context_for_post(\WP_Post $post): string {
	if ($post->post_type === 'lf_service') {
		return 'service';
	}
	if ($post->post_type === 'lf_service_area') {
		return 'service_area';
	}
	return '';
}

function lf_pb_default_config(string $context): array {
	$registry = lf_pb_registry();
	$sections = $registry[$context]['sections'] ?? [];
	$order = [];
	$config = [];
	foreach ($sections as $section) {
		$id = $section['id'];
		$order[] = $id;
		$settings = [];
		foreach ($section['fields'] as $field) {
			$settings[$field['key']] = $field['default'] ?? '';
		}
		$config[$id] = [
			'enabled' => !empty($section['enabled']),
			'settings' => $settings,
		];
	}
	return ['order' => $order, 'sections' => $config];
}

function lf_pb_get_post_config(int $post_id, string $context): array {
	$default = lf_pb_default_config($context);
	$stored = get_post_meta($post_id, LF_PB_META_KEY, true);
	if (!is_array($stored) || empty($stored)) {
		return $default;
	}
	$order = $stored['order'] ?? $default['order'];
	$sections = $stored['sections'] ?? [];
	$merged = $default;
	$merged['order'] = lf_pb_sanitize_order($order, array_keys($default['sections']));
	foreach ($default['sections'] as $id => $section) {
		$stored_section = is_array($sections[$id] ?? null) ? $sections[$id] : [];
		$merged['sections'][$id]['enabled'] = isset($stored_section['enabled']) ? (bool) $stored_section['enabled'] : $section['enabled'];
		$stored_settings = is_array($stored_section['settings'] ?? null) ? $stored_section['settings'] : [];
		foreach ($section['settings'] as $key => $value) {
			if (array_key_exists($key, $stored_settings)) {
				$merged['sections'][$id]['settings'][$key] = $stored_settings[$key];
			}
		}
	}
	return $merged;
}

function lf_pb_sanitize_order(array $order, array $allowed): array {
	$clean = [];
	foreach ($order as $item) {
		if (!is_string($item)) {
			continue;
		}
		$item = trim($item);
		if ($item !== '' && in_array($item, $allowed, true) && !in_array($item, $clean, true)) {
			$clean[] = $item;
		}
	}
	foreach ($allowed as $id) {
		if (!in_array($id, $clean, true)) {
			$clean[] = $id;
		}
	}
	return $clean;
}

function lf_pb_register_meta_box(): void {
	$screen = get_current_screen();
	if (!$screen || !in_array($screen->post_type, ['lf_service', 'lf_service_area'], true)) {
		return;
	}
	add_meta_box(
		'lf_page_builder',
		__('Page Builder', 'leadsforward-core'),
		'lf_pb_render_admin_box',
		$screen->post_type,
		'normal',
		'high'
	);
}

function lf_pb_admin_assets(string $hook): void {
	if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
		return;
	}
	$screen = get_current_screen();
	if (!$screen || !in_array($screen->post_type, ['lf_service', 'lf_service_area'], true)) {
		return;
	}
	wp_enqueue_script('jquery-ui-sortable');
}

function lf_pb_render_admin_box(\WP_Post $post): void {
	$context = lf_pb_get_context_for_post($post);
	if ($context === '') {
		return;
	}
	$registry = lf_pb_registry();
	$sections = $registry[$context]['sections'] ?? [];
	$config = lf_pb_get_post_config($post->ID, $context);
	$order = $config['order'] ?? [];
	$saved_sections = $config['sections'] ?? [];
	wp_nonce_field('lf_pb_save', 'lf_pb_nonce');
	?>
	<style>
		.lf-pb-sections { margin: 0; padding: 0; }
		.lf-pb-section { list-style: none; margin: 0 0 1rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; }
		.lf-pb-section-header { display: flex; align-items: center; gap: 0.75rem; padding: 0.9rem 1rem; }
		.lf-pb-drag { cursor: grab; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 8px; background: #f1f5f9; color: #64748b; }
		.lf-pb-toggle { margin-left: auto; font-size: 12px; text-decoration: none; padding: 0.35rem 0.65rem; border-radius: 999px; border: 1px solid #e2e8f0; background: #f8fafc; color: #0f172a; }
		.lf-pb-section-body { padding: 0 1rem 1rem; }
		.lf-pb-section--collapsed .lf-pb-section-body { display: none; }
		.lf-pb-section--collapsed .lf-pb-toggle { background: #0f172a; color: #fff; border-color: #0f172a; }
		.lf-pb-field { border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.75rem 1rem; margin: 0.75rem 0; }
	</style>
	<p class="description"><?php esc_html_e('Drag to reorder sections. Disable sections or edit safe fields below.', 'leadsforward-core'); ?></p>
	<ul class="lf-pb-sections">
		<?php foreach ($order as $section_id) :
			$def = null;
			foreach ($sections as $sec) {
				if ($sec['id'] === $section_id) { $def = $sec; break; }
			}
			if (!$def) { continue; }
			$sec_cfg = $saved_sections[$section_id] ?? [];
			$enabled = !empty($sec_cfg['enabled']);
			$settings = $sec_cfg['settings'] ?? [];
			?>
			<li class="lf-pb-section" data-section="<?php echo esc_attr($section_id); ?>">
				<div class="lf-pb-section-header">
					<span class="lf-pb-drag" aria-hidden="true">⋮⋮</span>
					<strong><?php echo esc_html($def['label']); ?></strong>
					<label><input type="checkbox" name="lf_pb_sections[<?php echo esc_attr($section_id); ?>][enabled]" value="1" <?php checked($enabled); ?> /> <?php esc_html_e('Enabled', 'leadsforward-core'); ?></label>
					<button type="button" class="lf-pb-toggle" data-target="<?php echo esc_attr($section_id); ?>" aria-expanded="true">▾ <?php esc_html_e('Collapse', 'leadsforward-core'); ?></button>
					<input type="hidden" name="lf_pb_order[]" value="<?php echo esc_attr($section_id); ?>" />
				</div>
				<div class="lf-pb-section-body">
					<?php foreach ($def['fields'] as $field) :
						$key = $field['key'];
						$type = $field['type'];
						$value = $settings[$key] ?? ($field['default'] ?? '');
						?>
						<div class="lf-pb-field">
							<label><strong><?php echo esc_html($field['label']); ?></strong></label>
							<?php if ($type === 'textarea') : ?>
								<textarea class="widefat" rows="2" name="lf_pb_sections[<?php echo esc_attr($section_id); ?>][settings][<?php echo esc_attr($key); ?>]"><?php echo esc_textarea($value); ?></textarea>
							<?php elseif ($type === 'select') : ?>
								<select name="lf_pb_sections[<?php echo esc_attr($section_id); ?>][settings][<?php echo esc_attr($key); ?>]">
									<?php foreach (($field['options'] ?? []) as $opt_val => $opt_label) : ?>
										<option value="<?php echo esc_attr($opt_val); ?>" <?php selected((string) $value, (string) $opt_val); ?>><?php echo esc_html($opt_label); ?></option>
									<?php endforeach; ?>
								</select>
							<?php else : ?>
								<input type="<?php echo esc_attr($type); ?>" class="widefat" name="lf_pb_sections[<?php echo esc_attr($section_id); ?>][settings][<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr((string) $value); ?>" />
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
	<script>
		jQuery(function ($) {
			var $list = $('.lf-pb-sections');
			if ($list.length && $list.sortable) {
				$list.sortable({
					items: '.lf-pb-section',
					handle: '.lf-pb-drag',
					axis: 'y'
				});
			}
			var key = 'lf_pb_collapsed';
			var collapsed = {};
			try { collapsed = JSON.parse(window.localStorage.getItem(key) || '{}') || {}; } catch (e) { collapsed = {}; }
			function applyCollapse(id) {
				var $panel = $('.lf-pb-section[data-section="' + id + '"]');
				var isCollapsed = !!collapsed[id];
				$panel.toggleClass('lf-pb-section--collapsed', isCollapsed);
				var $toggle = $panel.find('.lf-pb-toggle');
				$toggle.attr('aria-expanded', (!isCollapsed).toString());
				$toggle.text((isCollapsed ? '▸ ' : '▾ ') + (isCollapsed ? '<?php echo esc_js(__('Expand', 'leadsforward-core')); ?>' : '<?php echo esc_js(__('Collapse', 'leadsforward-core')); ?>'));
			}
			$('.lf-pb-section').each(function () {
				var id = $(this).data('section');
				if (id) applyCollapse(id);
			});
			$(document).on('click', '.lf-pb-toggle', function () {
				var id = $(this).data('target');
				if (!id) return;
				collapsed[id] = !collapsed[id];
				try { window.localStorage.setItem(key, JSON.stringify(collapsed)); } catch (e) {}
				applyCollapse(id);
			});
		});
	</script>
	<?php
}

function lf_pb_handle_save(int $post_id, \WP_Post $post): void {
	if (!in_array($post->post_type, ['lf_service', 'lf_service_area'], true)) {
		return;
	}
	if (!isset($_POST['lf_pb_nonce']) || !wp_verify_nonce($_POST['lf_pb_nonce'], 'lf_pb_save')) {
		return;
	}
	if (!current_user_can('edit_post', $post_id)) {
		return;
	}
	$context = lf_pb_get_context_for_post($post);
	$registry = lf_pb_registry();
	$sections = $registry[$context]['sections'] ?? [];
	$section_ids = array_map(fn($s) => $s['id'], $sections);
	$order_raw = isset($_POST['lf_pb_order']) ? (array) $_POST['lf_pb_order'] : [];
	$order = lf_pb_sanitize_order(array_map('sanitize_text_field', $order_raw), $section_ids);
	$input = $_POST['lf_pb_sections'] ?? [];
	$clean_sections = [];
	foreach ($sections as $section) {
		$id = $section['id'];
		$raw_section = is_array($input[$id] ?? null) ? $input[$id] : [];
		$enabled = !empty($raw_section['enabled']);
		$settings = [];
		foreach ($section['fields'] as $field) {
			$key = $field['key'];
			$raw_value = $raw_section['settings'][$key] ?? ($field['default'] ?? '');
			switch ($field['type']) {
				case 'textarea':
					$settings[$key] = sanitize_textarea_field(wp_unslash((string) $raw_value));
					break;
				case 'url':
					$settings[$key] = esc_url_raw(wp_unslash((string) $raw_value));
					break;
				case 'select':
					$options = $field['options'] ?? [];
					$val = sanitize_text_field(wp_unslash((string) $raw_value));
					$settings[$key] = array_key_exists($val, $options) ? $val : ($field['default'] ?? '');
					break;
				default:
					$settings[$key] = sanitize_text_field(wp_unslash((string) $raw_value));
					break;
			}
		}
		$clean_sections[$id] = ['enabled' => $enabled, 'settings' => $settings];
	}
	update_post_meta($post_id, LF_PB_META_KEY, ['order' => $order, 'sections' => $clean_sections]);
}

function lf_pb_render_sections(\WP_Post $post): void {
	$context = lf_pb_get_context_for_post($post);
	if ($context === '') {
		return;
	}
	$config = lf_pb_get_post_config($post->ID, $context);
	$order = $config['order'] ?? [];
	$sections = $config['sections'] ?? [];
	$registry = lf_pb_registry();
	$defs = [];
	foreach ($registry[$context]['sections'] ?? [] as $section) {
		$defs[$section['id']] = $section;
	}
	foreach ($order as $section_id) {
		$def = $defs[$section_id] ?? null;
		$sec_cfg = $sections[$section_id] ?? null;
		if (!$def || !$sec_cfg || empty($sec_cfg['enabled'])) {
			continue;
		}
		$callback = $def['render'] ?? '';
		if (is_callable($callback)) {
			call_user_func($callback, $post, $sec_cfg['settings'] ?? []);
		}
	}
}

function lf_pb_resolve_cta(array $settings): array {
	$section = [
		'cta_primary_override' => $settings['cta_primary_override'] ?? '',
		'cta_secondary_override' => $settings['cta_secondary_override'] ?? '',
		'cta_primary_action' => $settings['cta_primary_action'] ?? '',
		'cta_primary_url' => $settings['cta_primary_url'] ?? '',
		'cta_secondary_action' => $settings['cta_secondary_action'] ?? '',
		'cta_secondary_url' => $settings['cta_secondary_url'] ?? '',
	];
	return function_exists('lf_get_resolved_cta') ? lf_get_resolved_cta(['section' => $section, 'homepage' => false]) : [];
}

function lf_pb_render_service_hero(\WP_Post $post, array $settings): void {
	$h1 = function_exists('get_field') ? get_field('lf_service_seo_h1', $post->ID) : '';
	if (!$h1) {
		$h1 = get_the_title($post);
	}
	$short_desc = function_exists('get_field') ? get_field('lf_service_short_desc', $post->ID) : '';
	$heading = $settings['heading'] ?? '';
	$subheading = $settings['subheading'] ?? '';
	$cta = lf_pb_resolve_cta($settings);
	$primary = $cta['primary_text'] ?? '';
	$secondary = $cta['secondary_text'] ?? '';
	$action = $cta['primary_action'] ?? 'quote';
	$secondary_action = $cta['secondary_action'] ?? 'call';
	$primary_url = $cta['primary_url'] ?? '';
	$secondary_url = $cta['secondary_url'] ?? '';
	$phone = function_exists('lf_get_cta_phone') ? lf_get_cta_phone() : '';
	?>
	<section class="lf-pb-section lf-pb-hero">
		<div class="lf-pb-hero__inner">
			<h1 class="lf-pb-hero__title"><?php echo esc_html($heading !== '' ? $heading : $h1); ?></h1>
			<?php if ($subheading !== '' || $short_desc) : ?>
				<p class="lf-pb-hero__subtitle"><?php echo esc_html($subheading !== '' ? $subheading : $short_desc); ?></p>
			<?php endif; ?>
			<div class="lf-pb-hero__cta">
				<?php if ($primary) : ?>
					<?php if ($action === 'quote') : ?>
						<button type="button" class="lf-btn lf-btn--primary" data-lf-quote-trigger="1" data-lf-quote-source="service-hero"><?php echo esc_html($primary); ?></button>
					<?php elseif ($action === 'call' && $phone) : ?>
						<a href="tel:<?php echo esc_attr($phone); ?>" class="lf-btn lf-btn--primary"><?php echo esc_html($primary); ?></a>
					<?php elseif ($action === 'link' && $primary_url !== '') : ?>
						<a href="<?php echo esc_url($primary_url); ?>" class="lf-btn lf-btn--primary"><?php echo esc_html($primary); ?></a>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ($secondary) : ?>
					<?php if ($secondary_action === 'call' && $phone) : ?>
						<a href="tel:<?php echo esc_attr($phone); ?>" class="lf-btn lf-btn--secondary"><?php echo esc_html($secondary); ?></a>
					<?php elseif ($secondary_action === 'quote') : ?>
						<button type="button" class="lf-btn lf-btn--secondary" data-lf-quote-trigger="1" data-lf-quote-source="service-hero-secondary"><?php echo esc_html($secondary); ?></button>
					<?php elseif ($secondary_action === 'link' && $secondary_url !== '') : ?>
						<a href="<?php echo esc_url($secondary_url); ?>" class="lf-btn lf-btn--secondary"><?php echo esc_html($secondary); ?></a>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<?php
}

function lf_pb_render_area_hero(\WP_Post $post, array $settings): void {
	$state = function_exists('get_field') ? get_field('lf_service_area_state', $post->ID) : '';
	$heading = $settings['heading'] ?? '';
	$subheading = $settings['subheading'] ?? '';
	$title = $heading !== '' ? $heading : get_the_title($post);
	$sub = $subheading !== '' ? $subheading : ($state ? sprintf(__('Serving %1$s, %2$s', 'leadsforward-core'), get_the_title($post), $state) : '');
	$cta = lf_pb_resolve_cta($settings);
	$primary = $cta['primary_text'] ?? '';
	$secondary = $cta['secondary_text'] ?? '';
	$action = $cta['primary_action'] ?? 'quote';
	$secondary_action = $cta['secondary_action'] ?? 'call';
	$primary_url = $cta['primary_url'] ?? '';
	$secondary_url = $cta['secondary_url'] ?? '';
	$phone = function_exists('lf_get_cta_phone') ? lf_get_cta_phone() : '';
	?>
	<section class="lf-pb-section lf-pb-hero">
		<div class="lf-pb-hero__inner">
			<h1 class="lf-pb-hero__title"><?php echo esc_html($title); ?></h1>
			<?php if ($sub) : ?>
				<p class="lf-pb-hero__subtitle"><?php echo esc_html($sub); ?></p>
			<?php endif; ?>
			<div class="lf-pb-hero__cta">
				<?php if ($primary) : ?>
					<?php if ($action === 'quote') : ?>
						<button type="button" class="lf-btn lf-btn--primary" data-lf-quote-trigger="1" data-lf-quote-source="area-hero"><?php echo esc_html($primary); ?></button>
					<?php elseif ($action === 'call' && $phone) : ?>
						<a href="tel:<?php echo esc_attr($phone); ?>" class="lf-btn lf-btn--primary"><?php echo esc_html($primary); ?></a>
					<?php elseif ($action === 'link' && $primary_url !== '') : ?>
						<a href="<?php echo esc_url($primary_url); ?>" class="lf-btn lf-btn--primary"><?php echo esc_html($primary); ?></a>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ($secondary) : ?>
					<?php if ($secondary_action === 'call' && $phone) : ?>
						<a href="tel:<?php echo esc_attr($phone); ?>" class="lf-btn lf-btn--secondary"><?php echo esc_html($secondary); ?></a>
					<?php elseif ($secondary_action === 'quote') : ?>
						<button type="button" class="lf-btn lf-btn--secondary" data-lf-quote-trigger="1" data-lf-quote-source="area-hero-secondary"><?php echo esc_html($secondary); ?></button>
					<?php elseif ($secondary_action === 'link' && $secondary_url !== '') : ?>
						<a href="<?php echo esc_url($secondary_url); ?>" class="lf-btn lf-btn--secondary"><?php echo esc_html($secondary); ?></a>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<?php
}

function lf_pb_render_service_content(\WP_Post $post, array $settings): void {
	$long_content = function_exists('get_field') ? get_field('lf_service_long_content', $post->ID) : '';
	if (!$long_content) {
		$long_content = apply_filters('the_content', $post->post_content);
	}
	?>
	<section class="lf-pb-section lf-pb-content">
		<div class="lf-pb-content__inner">
			<?php echo wp_kses_post($long_content); ?>
		</div>
	</section>
	<?php
}

function lf_pb_render_area_content(\WP_Post $post, array $settings): void {
	$content = apply_filters('the_content', $post->post_content);
	?>
	<section class="lf-pb-section lf-pb-content">
		<div class="lf-pb-content__inner">
			<?php echo wp_kses_post($content); ?>
		</div>
	</section>
	<?php
}

function lf_pb_render_related_areas(\WP_Post $post, array $settings): void {
	?>
	<section class="lf-pb-section lf-pb-related">
		<div class="lf-pb-content__inner">
			<?php get_template_part('templates/parts/related-service-areas'); ?>
		</div>
	</section>
	<?php
}

function lf_pb_render_related_services(\WP_Post $post, array $settings): void {
	?>
	<section class="lf-pb-section lf-pb-related">
		<div class="lf-pb-content__inner">
			<?php get_template_part('templates/parts/related-services'); ?>
		</div>
	</section>
	<?php
}

function lf_pb_render_cta_block(\WP_Post $post, array $settings): void {
	$section = [
		'cta_headline' => $settings['cta_headline'] ?? '',
		'cta_primary_override' => $settings['cta_primary_override'] ?? '',
		'cta_secondary_override' => $settings['cta_secondary_override'] ?? '',
		'cta_primary_action' => $settings['cta_primary_action'] ?? '',
		'cta_primary_url' => $settings['cta_primary_url'] ?? '',
		'cta_secondary_action' => $settings['cta_secondary_action'] ?? '',
		'cta_secondary_url' => $settings['cta_secondary_url'] ?? '',
	];
	$block = [
		'id'         => 'pb-cta-' . $post->ID,
		'variant'    => 'default',
		'attributes' => ['variant' => 'default', 'layout' => 'default'],
		'context'    => ['homepage' => false, 'section' => $section],
	];
	if (function_exists('lf_render_block_template')) {
		lf_render_block_template('cta', $block, false, $block['context']);
	}
}

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
			'sections' => array_values(lf_sections_get_context_sections('service')),
		],
		'service_area' => [
			'label' => __('Service Area Page', 'leadsforward-core'),
			'sections' => array_values(lf_sections_get_context_sections('service_area')),
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
	$sections = lf_sections_get_context_sections($context);
	$order = lf_sections_default_order($context);
	$config = [];
	foreach ($order as $id) {
		$settings = lf_sections_defaults_for($id);
		$config[$id] = [
			'enabled' => true,
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
							<?php if ($type === 'textarea' || $type === 'list') : ?>
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
		$raw_settings = is_array($raw_section['settings'] ?? null) ? $raw_section['settings'] : [];
		$settings = lf_sections_sanitize_settings($id, $raw_settings);
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
	foreach ($order as $section_id) {
		$sec_cfg = $sections[$section_id] ?? null;
		if (!$sec_cfg || empty($sec_cfg['enabled'])) {
			continue;
		}
		lf_sections_render_section($section_id, $context, $sec_cfg['settings'] ?? [], $post);
	}
}


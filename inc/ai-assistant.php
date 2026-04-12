<?php
/**
 * LeadsForward AI Assistant floating admin widget.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_enqueue_scripts', 'lf_ai_assistant_assets');
add_action('wp_enqueue_scripts', 'lf_ai_assistant_assets');
add_action('admin_footer', 'lf_ai_assistant_render_floating_widget');
add_action('wp_footer', 'lf_ai_assistant_render_floating_widget');
add_action('wp_footer', 'lf_ai_inline_overrides_frontend_script', 5);
add_filter('lf_keep_jquery', 'lf_ai_assistant_keep_jquery_for_frontend_admins');

function lf_ai_assistant_keep_jquery_for_frontend_admins($keep): bool {
	if (is_admin()) {
		return (bool) $keep;
	}
	if (current_user_can('edit_theme_options')) {
		return true;
	}
	return (bool) $keep;
}

function lf_ai_assistant_section_library(array $context): array {
	if (!function_exists('lf_sections_get_context_sections')) {
		return [];
	}
	$type = (string) ($context['type'] ?? '');
	$id = (string) ($context['id'] ?? '');
	$context_key = '';
	if ($type === 'homepage' || $id === 'homepage') {
		$context_key = 'homepage';
	} elseif ($type === 'page') {
		$context_key = 'page';
	} elseif ($type === 'post') {
		$context_key = 'post';
	} elseif ($type === 'lf_service') {
		$context_key = 'service';
	} elseif ($type === 'lf_service_area') {
		$context_key = 'service_area';
	}
	if ($context_key === '' && is_numeric($id) && function_exists('lf_ai_pb_context_for_post')) {
		$post = get_post((int) $id);
		if ($post instanceof \WP_Post) {
			$context_key = (string) lf_ai_pb_context_for_post($post);
		}
	}
	if ($context_key === '') {
		return [];
	}
	$sections = lf_sections_get_context_sections($context_key);
	if (!is_array($sections) || empty($sections)) {
		return [];
	}
	$rows = [];
	foreach ($sections as $section_id => $row) {
		$sid = sanitize_text_field((string) $section_id);
		if ($sid === '') {
			continue;
		}
		// Hide legacy homepage-only media variants from the library UI.
		if (in_array($sid, ['content_image_a', 'content_image_c', 'image_content_b'], true)) {
			continue;
		}
		$label = sanitize_text_field((string) ($row['label'] ?? $sid));
		$rows[] = ['id' => $sid, 'label' => $label];
	}
	usort($rows, static function (array $a, array $b): int {
		return strcmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
	});
	return $rows;
}

function lf_ai_assistant_assets(string $hook = ''): void {
	if (!current_user_can('edit_theme_options')) {
		return;
	}

	wp_register_script('lf-ai-floating-assistant', '', ['jquery'], LF_THEME_VERSION, true);
	wp_enqueue_script('lf-ai-floating-assistant');
	if (function_exists('wp_enqueue_media')) {
		wp_enqueue_media();
	}

	$context = lf_ai_assistant_widget_context();
	$target_label = __('Homepage', 'leadsforward-core');
	if (($context['id'] ?? '') !== 'homepage') {
		$target_post = get_post((int) ($context['id'] ?? 0));
		if ($target_post instanceof \WP_Post) {
			$target_label = sprintf('%s (%s)', $target_post->post_title, strtoupper((string) $target_post->post_type));
		}
	}
	$editable = function_exists('lf_get_ai_editable_fields') ? lf_get_ai_editable_fields($context['id']) : [];
	if (empty($editable) && function_exists('lf_get_ai_editable_fields')) {
		$editable = lf_get_ai_editable_fields('homepage');
	}
	$homepage_enabled = [];
	if ((string) ($context['type'] ?? '') === 'homepage' || (string) ($context['id'] ?? '') === 'homepage') {
		if (function_exists('lf_get_homepage_section_config')) {
			$hp = lf_get_homepage_section_config();
			if (is_array($hp)) {
				foreach ($hp as $sid => $row) {
					if (!is_string($sid) || !is_array($row)) {
						continue;
					}
					$homepage_enabled[$sid] = !empty($row['enabled']);
				}
			}
		}
	}

	$bg_palette = [];
	if (function_exists('lf_sections_bg_options') && function_exists('lf_sections_bg_swatch_hex_map')) {
		$hex_map = lf_sections_bg_swatch_hex_map();
		foreach (lf_sections_bg_options() as $slug => $label) {
			$slug_s = (string) $slug;
			$bg_palette[] = [
				'slug' => $slug_s,
				'label' => (string) $label,
				'hex' => (string) ($hex_map[$slug_s] ?? '#f8fafc'),
			];
		}
	}

	$hero_variants_ui = [];
	if (function_exists('lf_sections_hero_variant_options')) {
		foreach (lf_sections_hero_variant_options() as $vk => $vlabel) {
			$hero_variants_ui[] = [
				'value' => (string) $vk,
				'label' => (string) $vlabel,
			];
		}
	}

	wp_localize_script('lf-ai-floating-assistant', 'lfAiFloating', [
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce'    => wp_create_nonce('lf_ai_editing'),
		'context_type' => (string) ($context['type'] ?? 'homepage'),
		'context_id' => (string) ($context['id'] ?? 'homepage'),
		'process_library_filter' => lf_ai_assistant_process_library_filter_context($context),
		'target_label' => $target_label,
		'labels' => $editable,
		'section_library' => lf_ai_assistant_section_library($context),
		'icon_slugs' => function_exists('lf_icon_list') ? array_values(array_map('sanitize_text_field', lf_icon_list())) : [],
		'bg_palette' => $bg_palette,
		'brand_settings_url' => admin_url('admin.php?page=lf-ops'),
		'hero_variants' => $hero_variants_ui,
		'hero_bg_modes' => [
			['value' => 'color', 'label' => __('Background color', 'leadsforward-core')],
			['value' => 'image', 'label' => __('Featured image overlay', 'leadsforward-core')],
			['value' => 'video', 'label' => __('Video background', 'leadsforward-core')],
		],
		'homepage_enabled' => $homepage_enabled,
		'i18n' => [
			'statusReady' => __('Ready.', 'leadsforward-core'),
			'statusGenerating' => __('Generating suggestions...', 'leadsforward-core'),
			'statusApplying' => __('Applying changes...', 'leadsforward-core'),
			'statusReverting' => __('Reverting last AI change...', 'leadsforward-core'),
			'statusRedoing' => __('Re-applying last reverted change...', 'leadsforward-core'),
			'statusReordering' => __('Saving section order...', 'leadsforward-core'),
			'statusParsingDoc' => __('Reading document context...', 'leadsforward-core'),
			'confirmRevert' => __('Revert the most recent AI change on this page? This cannot be undone.', 'leadsforward-core'),
			'placeholder' => __('Ask for precise copy edits, SEO rewrites, CTA improvements, or schema-safe content upgrades...', 'leadsforward-core'),
			'onboardingTip' => __('Click text or images to edit. Pick a section for AI changes. Press ⌘/Ctrl+K for commands.', 'leadsforward-core'),
			'onboardingDismiss' => __('Got it', 'leadsforward-core'),
		],
	]);

	wp_add_inline_script('lf-ai-floating-assistant', lf_ai_assistant_widget_js());
	wp_add_inline_script('lf-ai-floating-assistant', lf_ai_assistant_widget_fallback_js());
	wp_register_style('lf-ai-floating-assistant', false, [], LF_THEME_VERSION);
	wp_enqueue_style('lf-ai-floating-assistant');
	wp_add_inline_style('lf-ai-floating-assistant', lf_ai_assistant_widget_css());
}

function lf_ai_assistant_widget_context(): array {
	if (!is_admin()) {
		if (is_front_page()) {
			return ['type' => 'homepage', 'id' => 'homepage'];
		}
		$queried_id = get_queried_object_id();
		if ($queried_id > 0) {
			$post = get_post($queried_id);
			if ($post instanceof \WP_Post) {
				$front_id = (int) get_option('page_on_front');
				if ($post->post_type === 'page' && (int) $post->ID === $front_id) {
					return ['type' => 'homepage', 'id' => 'homepage'];
				}
				return ['type' => (string) $post->post_type, 'id' => (string) $post->ID];
			}
		}
		return ['type' => 'homepage', 'id' => 'homepage'];
	}
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if ($screen && $screen->base === 'post') {
		$post_id = 0;
		if (isset($_GET['post'])) {
			$post_id = absint($_GET['post']);
		} elseif (isset($_POST['post_ID'])) {
			$post_id = absint($_POST['post_ID']);
		}
		$post = $post_id > 0 ? get_post($post_id) : null;
		if ($post instanceof \WP_Post) {
			$front_id = (int) get_option('page_on_front');
			if ($post->post_type === 'page' && (int) $post->ID === $front_id) {
				return ['type' => 'homepage', 'id' => 'homepage'];
			}
			return ['type' => $post->post_type, 'id' => (string) $post->ID];
		}
	}
	return ['type' => 'homepage', 'id' => 'homepage'];
}

/**
 * When editing a service (or area with a linked service), limit the process-step picker to relevant steps.
 *
 * @return array{active:bool, service_id:int, service_slug:string, service_label:string}
 */
function lf_ai_assistant_process_library_filter_context(array $context): array {
	$out = [
		'active'         => false,
		'service_id'     => 0,
		'service_slug'   => '',
		'service_label'  => '',
	];
	$type = (string) ($context['type'] ?? '');
	$id = absint($context['id'] ?? 0);
	if ($type === 'lf_service' && $id > 0) {
		$p = get_post($id);
		if ($p instanceof \WP_Post && $p->post_type === 'lf_service') {
			$out['active'] = true;
			$out['service_id'] = $id;
			$out['service_slug'] = (string) $p->post_name;
			$out['service_label'] = (string) $p->post_title;
		}
		return $out;
	}
	if ($type === 'lf_service_area' && $id > 0 && function_exists('get_field')) {
		$area = get_post($id);
		if (!$area instanceof \WP_Post || $area->post_type !== 'lf_service_area') {
			return $out;
		}
		$services = get_field('lf_service_area_services', $id);
		if (!is_array($services) || $services === []) {
			return $out;
		}
		$first = $services[0];
		$svc = null;
		if ($first instanceof \WP_Post) {
			$svc = $first;
		} elseif (is_numeric($first)) {
			$svc = get_post((int) $first);
		}
		if ($svc instanceof \WP_Post && $svc->post_type === 'lf_service') {
			$out['active'] = true;
			$out['service_id'] = (int) $svc->ID;
			$out['service_slug'] = (string) $svc->post_name;
			$out['service_label'] = (string) $svc->post_title;
		}
	}
	return $out;
}

function lf_ai_inline_overrides_frontend_script(): void {
	if (is_admin()) {
		return;
	}
	if (!function_exists('lf_ai_get_inline_dom_overrides') || !function_exists('lf_ai_get_inline_image_overrides')) {
		return;
	}
	$context = lf_ai_assistant_widget_context();
	$context_type = (string) ($context['type'] ?? 'homepage');
	$context_id = $context['id'] ?? 'homepage';
	$text_overrides = lf_ai_get_inline_dom_overrides($context_type, $context_id);
	$image_overrides = lf_ai_get_inline_image_overrides($context_type, $context_id);
	$text_payload = [];
	foreach ((array) $text_overrides as $selector => $value) {
		$selector_clean = sanitize_text_field((string) $selector);
		$value_raw     = (string) $value;
		if ( function_exists( 'lf_ai_is_inline_dom_html_string' ) && lf_ai_is_inline_dom_html_string( $value_raw ) ) {
			$text_clean = function_exists( 'lf_ai_sanitize_inline_dom_html' ) ? lf_ai_sanitize_inline_dom_html( $value_raw ) : '';
		} else {
			$text_clean = sanitize_textarea_field( $value_raw );
		}
		if ($selector_clean === '' || $text_clean === '') {
			continue;
		}
		$text_payload[$selector_clean] = $text_clean;
	}
	$image_payload = [];
	foreach ((array) $image_overrides as $selector => $row) {
		$selector_clean = sanitize_text_field((string) $selector);
		if ($selector_clean === '' || !is_array($row)) {
			continue;
		}
		$attachment_id = (int) ($row['attachment_id'] ?? 0);
		$url = esc_url_raw((string) ($row['url'] ?? ''));
		$alt = sanitize_text_field((string) ($row['alt'] ?? ''));
		if ($attachment_id <= 0 || $url === '') {
			continue;
		}
		$image_payload[$selector_clean] = [
			'attachment_id' => $attachment_id,
			'url' => $url,
			'alt' => $alt,
		];
	}
	if (empty($text_payload) && empty($image_payload)) {
		return;
	}

	$text_json = wp_json_encode($text_payload);
	$image_json = wp_json_encode($image_payload);
	if (!is_string($text_json) || !is_string($image_json)) {
		return;
	}
	?>
	<script>
	(function(){
		var textMap = <?php echo $text_json; ?>;
		var imageMap = <?php echo $image_json; ?>;
		function applyMaps(){
			Object.keys(textMap || {}).forEach(function(selector){
				try {
					var node = document.querySelector(selector);
					if (node) {
						var raw = String(textMap[selector] || "");
						if (/<[a-z][\s\S]*>/i.test(raw)) {
							node.innerHTML = raw;
						} else {
							node.textContent = raw;
						}
					}
				} catch (e) {}
			});
			Object.keys(imageMap || {}).forEach(function(selector){
				try {
					var img = document.querySelector(selector);
					var row = imageMap[selector] || {};
					if (img && img.tagName && img.tagName.toLowerCase() === "img" && row.url) {
						img.setAttribute("src", String(row.url));
						img.removeAttribute("srcset");
						if (row.alt !== undefined) {
							img.setAttribute("alt", String(row.alt || ""));
						}
					}
				} catch (e) {}
			});
		}
		if (document.readyState === "loading") {
			document.addEventListener("DOMContentLoaded", applyMaps);
		} else {
			applyMaps();
		}
	})();
	</script>
	<?php
}

function lf_ai_assistant_render_floating_widget(): void {
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	$context = lf_ai_assistant_widget_context();
	$target_label = __('Homepage', 'leadsforward-core');
	if (($context['id'] ?? '') !== 'homepage') {
		$target_post = get_post((int) ($context['id'] ?? 0));
		if ($target_post instanceof \WP_Post) {
			$target_label = sprintf('%s (%s)', $target_post->post_title, strtoupper((string) $target_post->post_type));
		}
	}
	?>
	<div class="lf-ai-float" data-lf-ai-float>
		<button type="button" class="lf-ai-float__toggle" data-lf-ai-toggle aria-expanded="false" aria-controls="lf-ai-float-panel">
			<span class="lf-ai-float__dot" aria-hidden="true"></span>
			<?php esc_html_e('AI Assistant', 'leadsforward-core'); ?>
		</button>
		<div class="lf-ai-float__panel" id="lf-ai-float-panel" hidden>
			<div class="lf-ai-float__header">
				<strong><?php esc_html_e('LeadsForward AI Assistant', 'leadsforward-core'); ?></strong>
				<div class="lf-ai-float__header-actions">
					<button type="button" class="lf-ai-float__icon" data-lf-ai-undo aria-label="<?php esc_attr_e('Undo last change', 'leadsforward-core'); ?>" title="<?php esc_attr_e('Undo last change (click repeatedly to step back)', 'leadsforward-core'); ?>">↶</button>
					<button type="button" class="lf-ai-float__icon" data-lf-ai-redo aria-label="<?php esc_attr_e('Redo last reverted change', 'leadsforward-core'); ?>" title="<?php esc_attr_e('Redo last reverted change (click repeatedly to step forward)', 'leadsforward-core'); ?>">↷</button>
					<button type="button" class="lf-ai-float__icon" data-lf-ai-editor-toggle aria-label="<?php esc_attr_e('Toggle editor mode', 'leadsforward-core'); ?>" title="<?php esc_attr_e('Toggle editor mode on/off', 'leadsforward-core'); ?>">✎</button>
					<button type="button" class="lf-ai-float__icon" data-lf-ai-minimize aria-label="<?php esc_attr_e('Minimize', 'leadsforward-core'); ?>">−</button>
					<button type="button" class="lf-ai-float__icon" data-lf-ai-close aria-label="<?php esc_attr_e('Close', 'leadsforward-core'); ?>">×</button>
				</div>
			</div>
			<div class="lf-ai-float__body">
				<div class="lf-ai-float__tip" data-lf-ai-onboarding hidden>
					<p class="lf-ai-float__tip-text" data-lf-ai-onboarding-text></p>
					<button type="button" class="lf-ai-float__tip-dismiss" data-lf-ai-onboarding-dismiss><?php esc_html_e('Got it', 'leadsforward-core'); ?></button>
				</div>
				<div class="lf-ai-float__target" data-lf-ai-target><?php echo esc_html(sprintf(__('Target: %s (editable target)', 'leadsforward-core'), $target_label)); ?></div>
				<div class="lf-ai-float__scope" data-lf-ai-section-scope hidden>
					<span class="lf-ai-float__scope-kicker"><?php esc_html_e('AI will prioritize', 'leadsforward-core'); ?></span>
					<span class="lf-ai-float__scope-value" data-lf-ai-section-scope-value></span>
				</div>
				<details class="lf-ai-float__advanced">
					<summary class="lf-ai-float__advanced-summary"><?php esc_html_e('Mode, reference page & document', 'leadsforward-core'); ?></summary>
					<div class="lf-ai-float__advanced-body">
				<div class="lf-ai-float__mode">
					<label>
						<span><?php esc_html_e('Mode', 'leadsforward-core'); ?></span>
						<select data-lf-ai-mode>
							<option value="auto"><?php esc_html_e('Auto (Infer Action)', 'leadsforward-core'); ?></option>
							<option value="edit_existing"><?php esc_html_e('Edit Current Page', 'leadsforward-core'); ?></option>
							<option value="create_page"><?php esc_html_e('Create New Page (Draft)', 'leadsforward-core'); ?></option>
							<option value="create_cpt"><?php esc_html_e('Create New CPT Item (Draft)', 'leadsforward-core'); ?></option>
							<option value="create_blog_post"><?php esc_html_e('Create New Blog Post (Draft)', 'leadsforward-core'); ?></option>
							<option value="create_batch"><?php esc_html_e('Create Batch (Drafts)', 'leadsforward-core'); ?></option>
						</select>
					</label>
					<label data-lf-ai-cpt-wrap hidden>
						<span><?php esc_html_e('CPT Type', 'leadsforward-core'); ?></span>
						<select data-lf-ai-cpt-type>
							<option value="lf_service"><?php esc_html_e('Service', 'leadsforward-core'); ?></option>
							<option value="lf_service_area"><?php esc_html_e('Service Area', 'leadsforward-core'); ?></option>
							<option value="lf_faq"><?php esc_html_e('FAQ', 'leadsforward-core'); ?></option>
							<option value="lf_project"><?php esc_html_e('Project', 'leadsforward-core'); ?></option>
							<option value="lf_testimonial"><?php esc_html_e('Review/Testimonial', 'leadsforward-core'); ?></option>
						</select>
					</label>
					<label data-lf-ai-batch-wrap hidden>
						<span><?php esc_html_e('Batch Type', 'leadsforward-core'); ?></span>
						<select data-lf-ai-batch-type>
							<option value="post"><?php esc_html_e('Blog Posts', 'leadsforward-core'); ?></option>
							<option value="page"><?php esc_html_e('Pages', 'leadsforward-core'); ?></option>
							<option value="lf_service"><?php esc_html_e('Services', 'leadsforward-core'); ?></option>
							<option value="lf_service_area"><?php esc_html_e('Service Areas', 'leadsforward-core'); ?></option>
							<option value="lf_faq"><?php esc_html_e('FAQs', 'leadsforward-core'); ?></option>
							<option value="lf_project"><?php esc_html_e('Projects', 'leadsforward-core'); ?></option>
							<option value="lf_testimonial"><?php esc_html_e('Reviews/Testimonials', 'leadsforward-core'); ?></option>
						</select>
					</label>
					<label data-lf-ai-batch-count-wrap hidden>
						<span><?php esc_html_e('Count', 'leadsforward-core'); ?></span>
						<input type="number" min="1" max="20" value="5" data-lf-ai-batch-count />
					</label>
				</div>
				<div class="lf-ai-float__target-ref">
					<label>
						<span><?php esc_html_e('Reference target page/post (optional)', 'leadsforward-core'); ?></span>
						<input type="text" data-lf-ai-target-ref placeholder="<?php esc_attr_e('e.g. contact page, /about-us, full URL', 'leadsforward-core'); ?>" />
					</label>
				</div>
				<div class="lf-ai-float__doc">
					<input type="file" accept=".txt,.md,.csv,.json,.html,.htm,.rtf,.docx" data-lf-ai-doc-input hidden />
					<button type="button" class="button button-small" data-lf-ai-doc-attach><?php esc_html_e('Attach Document Context', 'leadsforward-core'); ?></button>
					<button type="button" class="button button-small" data-lf-ai-doc-clear hidden><?php esc_html_e('Remove', 'leadsforward-core'); ?></button>
					<span class="lf-ai-float__doc-name" data-lf-ai-doc-name></span>
				</div>
					</div>
				</details>
				<div class="lf-ai-float__presets">
					<button type="button" class="button button-small" data-lf-ai-expand-pb="1" data-lf-ai-preset="<?php esc_attr_e('Tighten this page copy for higher conversions and local trust signals.', 'leadsforward-core'); ?>"><?php esc_html_e('Optimize Copy', 'leadsforward-core'); ?></button>
					<button type="button" class="button button-small" data-lf-ai-expand-pb="1" data-lf-ai-preset="<?php esc_attr_e('Rewrite metadata and opening copy to better match transactional local intent.', 'leadsforward-core'); ?>"><?php esc_html_e('SERP Intent', 'leadsforward-core'); ?></button>
					<button type="button" class="button button-small" data-lf-ai-expand-pb="1" data-lf-ai-preset="<?php esc_attr_e('Improve CTA language for urgency, clarity, and lead quality.', 'leadsforward-core'); ?>"><?php esc_html_e('Improve CTA', 'leadsforward-core'); ?></button>
				</div>
				<textarea class="lf-ai-float__prompt" rows="4" data-lf-ai-prompt placeholder="<?php esc_attr_e('Ask for specific edits...', 'leadsforward-core'); ?>"></textarea>
				<div class="lf-ai-float__actions">
					<button type="button" class="button button-primary" data-lf-ai-generate><?php esc_html_e('Generate', 'leadsforward-core'); ?></button>
					<button type="button" class="button" data-lf-ai-apply disabled><?php esc_html_e('Apply', 'leadsforward-core'); ?></button>
					<button type="button" class="button" data-lf-ai-reject disabled><?php esc_html_e('Reject', 'leadsforward-core'); ?></button>
					<button type="button" class="button" data-lf-ai-revert><?php esc_html_e('Undo Last Change', 'leadsforward-core'); ?></button>
				</div>
				<div class="lf-ai-float__status" data-lf-ai-status><?php esc_html_e('Ready.', 'leadsforward-core'); ?></div>
				<div class="lf-ai-float__diff" data-lf-ai-diff hidden></div>
			</div>
			<div class="lf-ai-float__confirm" data-lf-ai-confirm hidden>
				<div class="lf-ai-float__confirm-card">
					<p class="lf-ai-float__confirm-text" data-lf-ai-confirm-text><?php esc_html_e('Revert the most recent AI change on this page? This cannot be undone.', 'leadsforward-core'); ?></p>
					<div class="lf-ai-float__confirm-actions">
						<button type="button" class="button button-primary" data-lf-ai-confirm-yes><?php esc_html_e('Yes, Revert', 'leadsforward-core'); ?></button>
						<button type="button" class="button" data-lf-ai-confirm-no><?php esc_html_e('Cancel', 'leadsforward-core'); ?></button>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="lf-ai-inline-link" data-lf-ai-inline-link-root>
		<div class="lf-ai-inline-link__toolbar" data-lf-ai-inline-link-toolbar hidden>
			<div class="lf-ai-inline-link__toolbar-inner">
				<button type="button" class="lf-ai-inline-link__open" data-lf-ai-inline-link-open><?php esc_html_e('Internal link', 'leadsforward-core'); ?></button>
			</div>
		</div>
		<div class="lf-ai-inline-link__backdrop" data-lf-ai-inline-link-backdrop hidden></div>
		<div class="lf-ai-inline-link__panel" data-lf-ai-inline-link-panel hidden role="dialog" aria-modal="true" aria-labelledby="lf-ai-inline-link-title">
			<div class="lf-ai-inline-link__panel-head">
				<strong id="lf-ai-inline-link-title"><?php esc_html_e('Link to a page', 'leadsforward-core'); ?></strong>
				<button type="button" class="lf-ai-inline-link__x" data-lf-ai-inline-link-close aria-label="<?php esc_attr_e('Close', 'leadsforward-core'); ?>">×</button>
			</div>
			<p class="lf-ai-inline-link__hint"><?php esc_html_e('Suggestions are ranked by the text you selected. You can also paste any internal URL.', 'leadsforward-core'); ?></p>
			<label class="lf-ai-inline-link__label">
				<span><?php esc_html_e('Search', 'leadsforward-core'); ?></span>
				<input type="search" class="lf-ai-inline-link__search" data-lf-ai-inline-link-search autocomplete="off" />
			</label>
			<div class="lf-ai-inline-link__list" data-lf-ai-inline-link-list></div>
			<label class="lf-ai-inline-link__label">
				<span><?php esc_html_e('URL', 'leadsforward-core'); ?></span>
				<input type="url" class="lf-ai-inline-link__url" data-lf-ai-inline-link-url placeholder="https://" />
			</label>
			<label class="lf-ai-inline-link__newtab">
				<input type="checkbox" data-lf-ai-inline-link-newtab />
				<span><?php esc_html_e('Open in new tab', 'leadsforward-core'); ?></span>
			</label>
			<button type="button" class="lf-ai-inline-link__unlink" data-lf-ai-inline-link-unlink hidden><?php esc_html_e('Remove link', 'leadsforward-core'); ?></button>
			<button type="button" class="lf-ai-inline-link__apply" data-lf-ai-inline-link-apply><?php esc_html_e('Apply link', 'leadsforward-core'); ?></button>
		</div>
	</div>
	<div class="lf-ai-float lf-ai-float--seo" data-lf-ai-seo-float>
		<button type="button" class="lf-ai-float__toggle lf-ai-float__toggle--seo" data-lf-ai-seo-toggle aria-expanded="false" aria-controls="lf-ai-seo-panel">
			<span class="lf-ai-float__dot" aria-hidden="true"></span>
			<?php esc_html_e('SEO Health', 'leadsforward-core'); ?>
		</button>
		<div class="lf-ai-float__panel lf-ai-float__panel--seo" id="lf-ai-seo-panel" hidden>
			<div class="lf-ai-float__header">
				<strong><?php esc_html_e('LeadsForward SEO Report', 'leadsforward-core'); ?></strong>
				<div class="lf-ai-float__header-actions">
					<button type="button" class="lf-ai-float__icon" data-lf-ai-seo-refresh aria-label="<?php esc_attr_e('Refresh SEO report', 'leadsforward-core'); ?>"><?php esc_html_e('↻', 'leadsforward-core'); ?></button>
					<button type="button" class="lf-ai-float__icon" data-lf-ai-seo-minimize aria-label="<?php esc_attr_e('Minimize', 'leadsforward-core'); ?>">−</button>
					<button type="button" class="lf-ai-float__icon" data-lf-ai-seo-close aria-label="<?php esc_attr_e('Close', 'leadsforward-core'); ?>">×</button>
				</div>
			</div>
			<div class="lf-ai-float__body">
				<?php if (is_admin() && current_user_can('edit_theme_options')) : ?>
					<p class="lf-ai-seo__admin-links" style="font-size:12px;margin:0 0 10px;line-height:1.45;color:#475569;">
						<a href="<?php echo esc_url(admin_url('admin.php?page=lf-seo&tab=settings')); ?>"><?php esc_html_e('SEO settings', 'leadsforward-core'); ?></a>
						<span aria-hidden="true"> · </span>
						<a href="<?php echo esc_url(admin_url('admin.php?page=lf-seo&tab=health')); ?>"><?php esc_html_e('Site health & pre-launch', 'leadsforward-core'); ?></a>
					</p>
				<?php endif; ?>
				<div class="lf-ai-seo" data-lf-ai-seo>
					<div class="lf-ai-seo__score" data-lf-ai-seo-score><?php esc_html_e('Overall: --', 'leadsforward-core'); ?></div>
					<div class="lf-ai-seo__perf-chip lf-ai-seo__perf-chip--pending" data-lf-ai-seo-perf-chip><?php esc_html_e('Perf --', 'leadsforward-core'); ?></div>
					<div class="lf-ai-seo__meter"><span data-lf-ai-seo-overall-fill style="width:0%;"></span></div>
					<div class="lf-ai-seo__subscores">
						<div class="lf-ai-seo__subscore">
							<span><?php esc_html_e('SEO', 'leadsforward-core'); ?></span>
							<b data-lf-ai-seo-sub-seo>--</b>
						</div>
						<div class="lf-ai-seo__subscore">
							<span><?php esc_html_e('Conversion', 'leadsforward-core'); ?></span>
							<b data-lf-ai-seo-sub-conv>--</b>
						</div>
						<div class="lf-ai-seo__subscore">
							<span><?php esc_html_e('Performance', 'leadsforward-core'); ?></span>
							<b data-lf-ai-seo-sub-perf>--</b>
						</div>
					</div>
					<div class="lf-ai-seo__list" data-lf-ai-seo-list></div>
					<div class="lf-ai-seo__queue" data-lf-ai-seo-queue>
						<div class="lf-ai-seo__section-title"><?php esc_html_e('Priority Actions', 'leadsforward-core'); ?></div>
						<div class="lf-ai-seo__queue-list" data-lf-ai-seo-tasks></div>
					</div>
					<div class="lf-ai-seo__serp" data-lf-ai-seo-serp>
						<div class="lf-ai-seo__section-title"><?php esc_html_e('SERP Preview', 'leadsforward-core'); ?></div>
						<div class="lf-ai-seo__serp-url" data-lf-ai-seo-serp-url></div>
						<div class="lf-ai-seo__serp-title" data-lf-ai-seo-serp-title></div>
						<div class="lf-ai-seo__serp-desc" data-lf-ai-seo-serp-desc></div>
					</div>
					<div class="lf-ai-seo__coverage" data-lf-ai-seo-coverage-wrap>
						<div class="lf-ai-seo__section-title"><?php esc_html_e('Keyword Coverage', 'leadsforward-core'); ?></div>
						<div class="lf-ai-seo__coverage-list" data-lf-ai-seo-coverage></div>
					</div>
					<div class="lf-ai-seo__vitals" data-lf-ai-seo-vitals-wrap>
						<div class="lf-ai-seo__section-title"><?php esc_html_e('CWV-Oriented Checks', 'leadsforward-core'); ?></div>
						<div class="lf-ai-seo__vitals-list" data-lf-ai-seo-vitals></div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
}

function lf_ai_assistant_widget_css(): string {
	return '
		.lf-ai-float { position: fixed; right: 20px; bottom: 20px; z-index: 99999; font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; display:flex; flex-direction:column; align-items:flex-end; }
		.lf-ai-float--seo { right: 188px; z-index: 99998; }
		.lf-ai-float__toggle { background:#6a3be8; color:#fff; border:0; border-radius:999px; padding:10px 14px; font-weight:600; box-shadow:none; cursor:pointer; display:flex; gap:8px; align-items:center; }
		.lf-ai-float__toggle--seo { background:#6a3be8; box-shadow:none; }
		.lf-ai-float__dot { width:8px; height:8px; border-radius:99px; background:#22c55e; box-shadow:0 0 0 4px rgba(34,197,94,.2); }
		.lf-ai-float__toggle--seo .lf-ai-float__dot { background:#a7f3d0; box-shadow:0 0 0 4px rgba(167,243,208,.25); }
		.lf-ai-float__panel { width:min(440px, calc(100vw - 36px)); max-height:min(80vh, 860px); background:#fff; border:1px solid #dbe3ef; border-radius:14px; box-shadow:0 18px 55px rgba(15,23,42,.25); overflow:hidden; position:absolute; right:0; bottom:calc(100% + 10px); display:flex; flex-direction:column; }
		.lf-ai-float__panel--seo { width:min(460px, calc(100vw - 36px)); }
		.lf-ai-float__panel[hidden] { display:none !important; }
		.lf-ai-float__header { display:flex; align-items:center; justify-content:space-between; padding:12px 14px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
		.lf-ai-float__header-actions { display:flex; gap:6px; }
		.lf-ai-float__icon { border:1px solid #d6c8fb; background:#fff; width:28px; height:28px; border-radius:8px; cursor:pointer; font-size:16px; line-height:1; color:#6a33e8; }
		.lf-ai-float__icon.is-active { background:#f5f0ff; border-color:#8348f9; color:#5b21b6; }
		.lf-ai-float__body { padding:12px; display:flex; flex-direction:column; gap:10px; flex:1; min-height:0; overflow:auto; overflow-x:hidden; }
		.lf-ai-float__body, .lf-ai-float__body * { box-sizing:border-box; }
		.lf-ai-float .lf-ai-float__body .button { appearance:none; border:1px solid #c4b5fd; border-radius:10px; background:#fff; color:#1e1b4b; min-height:34px; line-height:1.25; padding:0 12px; font-size:13px; font-weight:600; cursor:pointer; text-decoration:none; }
		.lf-ai-float .lf-ai-float__body .button:hover { background:#f5f3ff; border-color:#a78bfa; color:#4c1d95; }
		.lf-ai-float .lf-ai-float__body .button.button-primary { background:linear-gradient(180deg,#7c3aed 0%,#6d28d9 100%); border-color:#5b21b6; color:#fff; }
		.lf-ai-float .lf-ai-float__body .button.button-primary:hover { background:linear-gradient(180deg,#6d28d9 0%,#5b21b6 100%); border-color:#4c1d95; color:#fff; }
		.lf-ai-float .lf-ai-float__body .button[disabled] { background:#f1f5f9; border-color:#e2e8f0; color:#94a3b8; cursor:default; }
		.lf-ai-float__tip { display:flex; align-items:flex-start; gap:10px; padding:10px; border-radius:10px; border:1px solid #ddd6fe; background:linear-gradient(135deg,#faf5ff 0%,#f8fafc 100%); margin-bottom:2px; }
		.lf-ai-float__tip[hidden] { display:none !important; }
		.lf-ai-float__tip-text { margin:0; font-size:12px; line-height:1.45; color:#334155; flex:1; min-width:0; }
		.lf-ai-float__tip-dismiss { flex-shrink:0; border:1px solid #c4b5fd; background:#fff; color:#5b21b6; border-radius:8px; padding:6px 10px; font-size:12px; font-weight:700; cursor:pointer; }
		.lf-ai-float__tip-dismiss:hover { background:#f5f3ff; }
		.lf-ai-float__scope { font-size:12px; line-height:1.4; color:#334155; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:8px 10px; display:flex; flex-direction:column; gap:2px; }
		.lf-ai-float__scope[hidden] { display:none !important; }
		.lf-ai-float__scope-kicker { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#166534; }
		.lf-ai-float__scope-value { font-weight:600; color:#14532d; word-break:break-word; }
		.lf-ai-float__advanced { border:1px solid #e9e1ff; border-radius:10px; background:#faf7ff; margin:2px 0 4px; }
		.lf-ai-float__advanced-summary { cursor:pointer; font-size:12px; font-weight:700; color:#5b21b6; padding:9px 10px; list-style:none; user-select:none; }
		.lf-ai-float__advanced-summary::-webkit-details-marker { display:none; }
		.lf-ai-float__advanced-body { padding:0 10px 10px; display:flex; flex-direction:column; gap:10px; border-top:1px solid #ede9fe; }
		.lf-ai-float__target { font-size:12px; color:#475569; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:6px 8px; }
		.lf-ai-float__mode { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
		.lf-ai-float__mode label { display:flex; flex-direction:column; gap:4px; font-size:12px; color:#475569; }
		.lf-ai-float__mode label[hidden] { display:none !important; }
		.lf-ai-float__mode select { border:1px solid #d6c8fb; border-radius:8px; padding:6px 8px; font-size:13px; color:#0f172a; background:#fff; width:100%; max-width:100%; font-family:inherit; }
		.lf-ai-float__mode input[type="number"] { border:1px solid #d6c8fb; border-radius:8px; padding:6px 8px; font-size:13px; color:#0f172a; background:#fff; width:100%; max-width:100%; box-sizing:border-box; font-family:inherit; }
		.lf-ai-float__target-ref label { display:flex; flex-direction:column; gap:4px; font-size:12px; color:#475569; }
		.lf-ai-float__target-ref input { border:1px solid #d6c8fb; border-radius:8px; padding:6px 8px; font-size:13px; color:#0f172a; background:#fff; width:100%; max-width:100%; box-sizing:border-box; font-family:inherit; }
		.lf-ai-float__presets { display:flex; flex-wrap:wrap; gap:6px; }
		.lf-ai-float__prompt { width:100%; max-width:100%; resize:vertical; min-height:88px; border:1px solid #d6c8fb; border-radius:10px; padding:10px; font-size:13px; font-family:inherit; }
		.lf-ai-float__prompt:focus { border-color:#8348f9; box-shadow:0 0 0 1px #8348f9; outline:none; }
		.lf-ai-float__doc { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
		.lf-ai-float__doc-name { font-size:12px; color:#475569; overflow-wrap:anywhere; }
		.lf-ai-float__actions { display:flex; gap:8px; align-items:center; }
		.lf-ai-float__actions [data-lf-ai-revert] { margin-left:auto; }
		.lf-ai-seo { border:1px solid #c7f0eb; border-radius:12px; background:linear-gradient(180deg,#f4fffd 0%,#f8fafc 100%); padding:10px; display:flex; flex-direction:column; gap:7px; }
		.lf-ai-seo__head { display:flex; align-items:center; justify-content:space-between; gap:8px; }
		.lf-ai-seo__title { font-size:12px; font-weight:700; color:#0f172a; }
		.lf-ai-seo__score { font-size:12px; color:#334155; }
		.lf-ai-seo__score strong { color:#0f172a; }
		.lf-ai-seo__perf-chip { align-self:flex-start; font-size:11px; font-weight:700; border-radius:999px; padding:2px 8px; line-height:1.5; border:1px solid transparent; }
		.lf-ai-seo__perf-chip--pending { color:#475569; background:#f1f5f9; border-color:#e2e8f0; }
		.lf-ai-seo__perf-chip--good { color:#166534; background:#dcfce7; border-color:#86efac; }
		.lf-ai-seo__perf-chip--warn { color:#92400e; background:#fef3c7; border-color:#fcd34d; }
		.lf-ai-seo__perf-chip--bad { color:#b91c1c; background:#fee2e2; border-color:#fca5a5; }
		.lf-ai-seo__meter { width:100%; height:8px; border-radius:999px; background:#e2e8f0; overflow:hidden; }
		.lf-ai-seo__meter > span { display:block; height:100%; width:0; background:linear-gradient(90deg,#ef4444,#f59e0b,#22c55e); transition:width .2s ease; }
		.lf-ai-seo__subscores { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:6px; }
		.lf-ai-seo__subscore { border:1px solid #e2e8f0; border-radius:8px; background:#fff; padding:5px 6px; display:flex; flex-direction:column; gap:2px; }
		.lf-ai-seo__subscore span { font-size:10px; color:#64748b; }
		.lf-ai-seo__subscore b { font-size:12px; color:#0f172a; line-height:1.1; }
		.lf-ai-seo__list { display:flex; flex-direction:column; gap:4px; }
		.lf-ai-seo__item { font-size:11px; color:#334155; display:flex; align-items:flex-start; gap:6px; }
		.lf-ai-seo__item-dot { font-weight:700; min-width:12px; line-height:1.2; }
		.lf-ai-seo__item--ok .lf-ai-seo__item-dot { color:#16a34a; }
		.lf-ai-seo__item--warn .lf-ai-seo__item-dot { color:#d97706; }
		.lf-ai-seo__item--error .lf-ai-seo__item-dot { color:#dc2626; }
		.lf-ai-seo__section-title { font-size:11px; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:.03em; margin-top:2px; }
		.lf-ai-seo__queue, .lf-ai-seo__serp, .lf-ai-seo__coverage, .lf-ai-seo__vitals { border:1px solid #e2e8f0; border-radius:8px; background:#fff; padding:6px; display:flex; flex-direction:column; gap:4px; }
		.lf-ai-seo__queue-list, .lf-ai-seo__coverage-list, .lf-ai-seo__vitals-list { display:flex; flex-direction:column; gap:3px; }
		.lf-ai-seo__task { font-size:11px; color:#334155; display:flex; gap:6px; align-items:flex-start; }
		.lf-ai-seo__task-priority { font-size:10px; font-weight:700; border-radius:999px; padding:1px 6px; line-height:1.6; }
		.lf-ai-seo__task-priority--high { background:#fee2e2; color:#b91c1c; }
		.lf-ai-seo__task-priority--med { background:#fef3c7; color:#92400e; }
		.lf-ai-seo__task-priority--low { background:#dcfce7; color:#166534; }
		.lf-ai-seo__serp-url { font-size:11px; color:#166534; line-height:1.25; word-break:break-all; }
		.lf-ai-seo__serp-title { font-size:13px; color:#1a0dab; line-height:1.25; }
		.lf-ai-seo__serp-desc { font-size:11px; color:#4b5563; line-height:1.35; }
		.lf-ai-seo__coverage-row, .lf-ai-seo__vital-row { font-size:11px; color:#334155; display:flex; align-items:flex-start; gap:6px; }
		.lf-ai-seo__badge { font-size:10px; border-radius:999px; padding:1px 6px; line-height:1.5; font-weight:700; }
		.lf-ai-seo__badge--ok { background:#dcfce7; color:#166534; }
		.lf-ai-seo__badge--warn { background:#fef3c7; color:#92400e; }
		.lf-ai-seo__badge--error { background:#fee2e2; color:#b91c1c; }
		.lf-ai-float__status { font-size:12px; color:#475569; min-height:16px; }
		.lf-ai-float__status.is-error { color:#b91c1c; }
		.lf-ai-float__diff { border:1px solid #e2e8f0; border-radius:10px; max-height:280px; overflow:auto; background:#f8fafc; font-size:12px; }
		.lf-ai-float__row { border-bottom:1px solid #e2e8f0; padding:8px; }
		.lf-ai-float__row:last-child { border-bottom:0; }
		.lf-ai-float__field { font-weight:600; margin-bottom:6px; color:#0f172a; }
		.lf-ai-float__cols { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
		.lf-ai-float__col { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:6px; }
		.lf-ai-float__col b { display:block; margin-bottom:4px; color:#334155; }
		[data-lf-inline-editable="1"] { cursor:text; transition:outline-color .15s ease, background-color .15s ease; }
		[data-lf-inline-editable="1"]:hover { outline:2px dashed rgba(131,72,249,.45); outline-offset:2px; }
		[data-lf-inline-active="1"] { outline:2px solid #8348f9 !important; outline-offset:2px; background:rgba(131,72,249,.08); }
		[data-lf-inline-saving="1"] { opacity:.72; pointer-events:none; }
		[data-lf-inline-image="1"] { cursor:pointer; transition:outline-color .15s ease, box-shadow .15s ease; }
		[data-lf-inline-image="1"]:hover { outline:2px dashed rgba(131,72,249,.45); outline-offset:3px; box-shadow:0 8px 20px rgba(15,23,42,.12); }
		[data-lf-inline-image-active="1"] { outline:2px solid #8348f9 !important; outline-offset:3px; }
		[data-lf-section-wrap="1"] { position:relative; cursor:grab; transition:outline-color .15s ease, box-shadow .15s ease; }
		[data-lf-section-wrap="1"]:hover { outline:2px dashed rgba(131,72,249,.35); outline-offset:3px; }
		[data-lf-section-wrap="1"].is-dragging { opacity:.55; outline:2px solid #8348f9 !important; outline-offset:3px; cursor:grabbing; }
		.lf-ai-section-controls { position:absolute; top:8px; right:8px; display:flex; flex-wrap:wrap; justify-content:flex-end; max-width:calc(100% - 16px); gap:6px; z-index:4; opacity:0.38; transition:opacity .15s ease; }
		[data-lf-section-wrap="1"]:hover .lf-ai-section-controls, [data-lf-section-wrap="1"].lf-ai-section-is-hidden .lf-ai-section-controls, [data-lf-section-wrap="1"].lf-ai-section-active .lf-ai-section-controls { opacity:1; }
		@media (hover: none), (pointer: coarse) {
			.lf-ai-section-controls { opacity:1; }
		}
		/* Editor mode on: keep section chrome visible (hover-only opacity was easy to miss on desktop). */
		.lf-ai-editor-on .lf-ai-section-controls { opacity:1; }
		.lf-ai-section-btn { border:1px solid #d6c8fb; background:#fff; color:#6a33e8; border-radius:8px; min-width:28px; height:28px; padding:0 7px; font-size:12px; line-height:26px; cursor:pointer; }
		.lf-ai-section-btn:hover { background:#f5f0ff; }
		.lf-ai-section-btn--danger { border-color:#fecaca; color:#b91c1c; }
		[data-lf-section-wrap="1"].lf-ai-section-is-hidden { min-height:56px; background:rgba(131,72,249,.06); outline:2px dashed rgba(131,72,249,.35); outline-offset:3px; }
		[data-lf-section-wrap="1"].lf-ai-section-is-hidden > :not(.lf-ai-section-controls):not(.lf-ai-section-insert) { display:none !important; }
		[data-lf-section-wrap="1"].lf-ai-section-active { outline:2px solid #8348f9 !important; outline-offset:3px; box-shadow:0 0 0 4px rgba(131,72,249,.12); }
		.lf-ai-section-insert { position:absolute; inset:0; z-index:5; pointer-events:none; }
		.lf-ai-section-insert__zone { position:absolute; left:0; right:0; height:36px; display:flex; align-items:center; justify-content:center; pointer-events:auto; opacity:0; transition:opacity .15s ease; }
		.lf-ai-section-insert__zone--top { top:0; transform:translateY(-40%); }
		.lf-ai-section-insert__zone--bottom { bottom:0; transform:translateY(40%); }
		.lf-ai-section-insert__zone:hover { opacity:1; }
		.lf-ai-section-insert__btn { width:34px; height:34px; border-radius:999px; border:1px solid #d6c8fb; background:#fff; color:#6a33e8; font-size:20px; line-height:1; cursor:pointer; box-shadow:0 2px 12px rgba(15,23,42,.12); padding:0; }
		.lf-ai-section-insert__btn:hover { background:#f5f0ff; }
		.lf-ai-section-align-picker__subhead { margin-top:4px; font-size:12px; font-weight:600; color:#64748b; }
		.lf-ai-benefits-cta-picker { position:fixed; inset:0; z-index:100006; background:rgba(15,23,42,.45); display:flex; align-items:center; justify-content:center; padding:18px; }
		.lf-ai-benefits-cta-picker[hidden] { display:none !important; }
		.lf-ai-benefits-cta-picker__card { width:min(400px, calc(100vw - 30px)); background:#fff; border:1px solid #dbe3ef; border-radius:12px; box-shadow:0 20px 50px rgba(15,23,42,.28); padding:14px; display:flex; flex-direction:column; gap:10px; }
		.lf-ai-benefits-cta-picker__label { font-size:12px; font-weight:600; color:#334155; }
		.lf-ai-benefits-cta-picker__input { width:100%; border:1px solid #d6c8fb; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box; }
		.lf-ai-benefits-cta-picker__row { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
		.lf-ai-benefits-cta-picker__btn { flex:1; min-width:72px; border:1px solid #d6c8fb; background:#fff; color:#6a33e8; border-radius:8px; min-height:34px; font-size:12px; font-weight:600; cursor:pointer; }
		.lf-ai-benefits-cta-picker__btn:hover { background:#f5f0ff; }
		.lf-ai-benefits-cta-picker__btn.is-active { outline:2px solid #8348f9; }
		.lf-ai-section-insert-picker { position:fixed; inset:0; z-index:100006; background:rgba(15,23,42,.45); display:flex; align-items:center; justify-content:center; padding:18px; }
		.lf-ai-section-insert-picker[hidden] { display:none !important; }
		.lf-ai-section-insert-picker__card { width:min(420px, calc(100vw - 30px)); max-height:80vh; overflow:auto; background:#fff; border:1px solid #dbe3ef; border-radius:12px; box-shadow:0 20px 50px rgba(15,23,42,.28); padding:12px; display:flex; flex-direction:column; gap:8px; }
		.lf-ai-section-insert-picker__head { font-size:14px; font-weight:700; color:#0f172a; }
		.lf-ai-section-insert-picker__item { display:block; width:100%; text-align:left; border:1px solid #e2e8f0; background:#f8fafc; border-radius:8px; padding:8px 10px; font-size:12px; cursor:pointer; color:#0f172a; }
		.lf-ai-section-insert-picker__item:hover { border-color:#c4b5fd; background:#faf7ff; }
		.lf-ai-checklist-controls { margin-top:8px; display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
		.lf-ai-checklist-add { border:1px solid #d6c8fb; background:#fff; color:#6a33e8; border-radius:8px; min-height:28px; padding:0 10px; font-size:12px; cursor:pointer; }
		.lf-ai-checklist-add:hover { background:#f5f0ff; }
		.lf-ai-checklist-remove { border:1px solid #e2e8f0; background:#fff; color:#64748b; border-radius:6px; min-width:20px; height:20px; padding:0 5px; font-size:11px; line-height:18px; margin-left:8px; cursor:pointer; vertical-align:middle; }
		.lf-ai-checklist-remove:hover { border-color:#fecaca; color:#b91c1c; background:#fff5f5; }
		/* Corner remove on grid cards (benefits, service intro, etc.) — one consistent control */
		.lf-ai-card-remove {
			position:absolute;
			top:6px;
			right:6px;
			z-index:3;
			box-sizing:border-box;
			margin:0 !important;
			padding:0 !important;
			width:26px;
			height:26px;
			min-width:26px !important;
			border:1px solid #e2e8f0;
			background:#fff;
			color:#64748b;
			border-radius:999px !important;
			font-size:15px;
			font-weight:600;
			line-height:1;
			cursor:pointer;
			display:inline-flex;
			align-items:center;
			justify-content:center;
			box-shadow:0 1px 3px rgba(15,23,42,.08);
		}
		.lf-ai-card-remove:hover {
			border-color:#fecaca;
			color:#b91c1c;
			background:#fff5f5;
		}
		.lf-ai-benefits-grid-actions { margin-top:10px; display:flex; justify-content:center; width:100%; }
		.lf-ai-editor-on .lf-block-service-intro__card { position:relative; overflow:visible; }
		.lf-ai-hero-pills-controls { margin-top:8px; display:flex; gap:8px; align-items:center; }
		.lf-ai-hero-trust-strip-controls { margin-top:10px; padding:8px 10px; border-radius:8px; background:rgba(131,72,249,.08); border:1px solid rgba(131,72,249,.25); font-size:13px; }
		.lf-ai-benefit-editable { cursor:text; border-radius:6px; transition:box-shadow .15s ease; }
		.lf-ai-editor-on .lf-ai-benefit-editable:hover { box-shadow:0 0 0 1px rgba(131,72,249,.45); }
		.lf-ai-hero-pill-add { border:1px solid #d6c8fb; background:#fff; color:#6a33e8; border-radius:8px; min-height:28px; padding:0 10px; font-size:12px; cursor:pointer; }
		.lf-ai-hero-pill-add:hover { background:#f5f0ff; }
		.lf-ai-hero-pill-remove { border:1px solid rgba(255,255,255,.65); background:rgba(255,255,255,.2); color:inherit; border-radius:999px; min-width:18px; height:18px; padding:0 5px; font-size:11px; line-height:16px; margin-left:8px; cursor:pointer; vertical-align:middle; }
		.lf-ai-hero-pill-remove:hover { background:rgba(255,255,255,.38); }
		.lf-ai-list-remove { border:1px solid #e2e8f0; background:#fff; color:#64748b; border-radius:999px; min-width:18px; height:18px; padding:0 5px; font-size:11px; line-height:16px; margin-left:8px; cursor:pointer; vertical-align:middle; }
		.lf-ai-list-remove:hover { border-color:#fecaca; color:#b91c1c; background:#fff5f5; }
		.lf-ai-process-step.is-dragging { opacity:.6; outline:2px dashed rgba(131,72,249,.4); outline-offset:2px; }
		[data-lf-ai-cta-editable="1"] { position:relative; }
		[data-lf-ai-cta-editable="1"]:hover { outline:2px dashed rgba(131,72,249,.45); outline-offset:2px; }
		.lf-ai-column-draggable { cursor:ew-resize; transition:outline-color .15s ease; }
		.lf-ai-column-draggable:hover { outline:2px dashed rgba(131,72,249,.3); outline-offset:3px; }
		.lf-ai-column-draggable.is-dragging { outline:2px solid #8348f9 !important; outline-offset:3px; opacity:.85; }
		.lf-ai-rail { position:fixed; left:14px; top:54px; z-index:99997; width:248px; max-height:calc(100vh - 84px); overflow:auto; background:rgba(255,255,255,.98); border:1px solid #ddd6fe; border-radius:16px; box-shadow:0 18px 44px rgba(79,35,180,.2); padding:10px; backdrop-filter:blur(8px); }
		.lf-ai-rail.is-collapsed { width:auto; background:transparent; border:0; box-shadow:none; padding:0; max-height:none; }
		.lf-ai-rail__head { display:flex; align-items:center; justify-content:space-between; gap:8px; margin:0 0 8px; }
		.lf-ai-rail__title { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#5b21b6; margin:0; }
		.lf-ai-rail.is-collapsed .lf-ai-rail__title { display:none; }
		.lf-ai-rail__head-actions { display:flex; gap:6px; }
		.lf-ai-rail__toggle { border:1px solid #c4b5fd; background:#fff; color:#5b21b6; border-radius:10px; min-width:30px; height:30px; cursor:pointer; font-size:14px; line-height:1; font-weight:700; }
		.lf-ai-rail__add { border:1px solid #c4b5fd; background:#fff; color:#5b21b6; border-radius:10px; width:30px; height:30px; cursor:pointer; font-size:16px; line-height:1; }
		.lf-ai-rail__toggle:hover, .lf-ai-rail__add:hover { background:#f5f3ff; border-color:#a78bfa; }
		.lf-ai-rail.is-collapsed .lf-ai-rail__head { margin:0; }
		.lf-ai-rail.is-collapsed .lf-ai-rail__toggle { width:auto; min-width:0; height:44px; border-radius:999px; padding:0 16px; border:0; color:#fff; background:#6a3be8; box-shadow:none; font-size:22px; font-weight:700; letter-spacing:.02em; }
		.lf-ai-rail.is-collapsed .lf-ai-rail__toggle::before { content:"●"; color:#22c55e; font-size:12px; margin-right:8px; vertical-align:middle; text-shadow:0 0 0 4px rgba(34,197,94,.2); }
		.lf-ai-rail.is-collapsed .lf-ai-rail__add { display:none; }
		.lf-ai-rail__list { display:flex; flex-direction:column; gap:7px; }
		.lf-ai-rail.is-collapsed .lf-ai-rail__list { display:none; }
		.lf-ai-rail__item { border:1px solid #e9e1ff; border-radius:10px; padding:7px 9px; font-size:12px; cursor:pointer; color:#0f172a; background:#fff; display:flex; align-items:flex-start; justify-content:space-between; gap:8px; }
		.lf-ai-rail__item:hover { border-color:#a78bfa; background:#faf7ff; box-shadow:0 4px 12px rgba(131,72,249,.12); }
		.lf-ai-rail__item.is-active { border-color:#8348f9; background:#f5f0ff; box-shadow:0 0 0 2px rgba(131,72,249,.15); }
		.lf-ai-rail__item small { display:block; color:#6b7280; margin-top:2px; }
		.lf-ai-rail__item-body { min-width:0; flex:1; }
		.lf-ai-rail__drag { border:1px solid #e9e1ff; border-radius:7px; width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; color:#6b7280; cursor:grab; user-select:none; background:#fff; font-size:12px; line-height:1; }
		.lf-ai-rail__drag:active { cursor:grabbing; }
		.lf-ai-rail__item.is-dragging { opacity:.55; border-color:#8348f9; }
		.lf-ai-rail__item.is-drop-before { box-shadow: inset 0 3px 0 #8348f9; }
		.lf-ai-rail__item.is-drop-after { box-shadow: inset 0 -3px 0 #8348f9; }
		body.lf-ai-rail-dragging { user-select:none; -webkit-user-select:none; }
		.lf-ai-rail__library { border:1px solid #ddd6fe; border-radius:12px; padding:7px; margin:0 0 9px; background:#f6f1ff; display:flex; flex-direction:column; gap:6px; }
		.lf-ai-rail__library[hidden] { display:none !important; }
		.lf-ai-rail__library-search { width:100%; border:1px solid #c4b5fd; border-radius:9px; padding:7px 8px; font-size:12px; }
		.lf-ai-rail__library-search:focus { outline:none; border-color:#8348f9; box-shadow:0 0 0 3px rgba(131,72,249,.18); }
		.lf-ai-rail__library-list { max-height:180px; overflow:auto; display:flex; flex-direction:column; gap:4px; }
		.lf-ai-rail__library-item { border:1px solid #e9e1ff; border-radius:9px; background:#fff; padding:6px 8px; font-size:12px; cursor:pointer; text-align:left; color:#0f172a; }
		.lf-ai-rail__library-item:hover { border-color:#a78bfa; background:#faf7ff; }
		.lf-ai-rail__library-item[disabled] { opacity:.5; cursor:default; }
		.lf-ai-command { position:fixed; left:50%; top:16%; transform:translateX(-50%); z-index:100001; width:min(560px, calc(100vw - 26px)); background:#fff; border:1px solid #dbe3ef; border-radius:12px; box-shadow:0 20px 50px rgba(15,23,42,.28); padding:10px; }
		.lf-ai-command[hidden] { display:none !important; }
		.lf-ai-command__input { width:100%; border:1px solid #d6c8fb; border-radius:8px; padding:10px; font-size:14px; }
		.lf-ai-command__list { margin-top:8px; max-height:300px; overflow:auto; display:flex; flex-direction:column; gap:6px; }
		.lf-ai-command__row { border:1px solid #e2e8f0; border-radius:8px; padding:8px; font-size:13px; cursor:pointer; }
		.lf-ai-command__row:hover, .lf-ai-command__row.is-active { border-color:#8348f9; background:#f6f2ff; }
		.lf-ai-command__hint { margin-top:8px; font-size:11px; color:#64748b; }
		.lf-ai-icon-picker { position:fixed; inset:0; z-index:100002; background:rgba(15,23,42,.45); display:flex; align-items:center; justify-content:center; padding:18px; }
		.lf-ai-icon-picker[hidden] { display:none !important; }
		.lf-ai-icon-picker__card { width:min(560px, calc(100vw - 30px)); max-height:80vh; overflow:hidden; background:#fff; border:1px solid #dbe3ef; border-radius:12px; box-shadow:0 20px 50px rgba(15,23,42,.28); display:flex; flex-direction:column; }
		.lf-ai-icon-picker__head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 12px; border-bottom:1px solid #e2e8f0; background:#f8fafc; }
		.lf-ai-icon-picker__title { font-size:13px; font-weight:700; color:#0f172a; }
		.lf-ai-icon-picker__close { border:1px solid #d6c8fb; background:#fff; color:#6a33e8; border-radius:8px; width:28px; height:28px; cursor:pointer; font-size:15px; line-height:1; }
		.lf-ai-icon-picker__search { border:1px solid #d6c8fb; border-radius:8px; padding:8px 10px; margin:10px 12px; font-size:13px; }
		.lf-ai-icon-picker__list { padding:0 12px 12px; overflow:auto; display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:8px; }
		.lf-ai-icon-picker__item { border:1px solid #e2e8f0; border-radius:8px; background:#fff; color:#0f172a; text-align:left; padding:8px 10px; cursor:pointer; font-size:12px; }
		.lf-ai-icon-picker__item:hover { border-color:#c4b5fd; background:#faf7ff; }
		.lf-ai-icon-picker__item.is-active { border-color:#8348f9; background:#f5f0ff; color:#5b21b6; }
		.lf-ai-icon-picker__empty { padding:10px; color:#64748b; font-size:12px; }
		.lf-ai-media-add-wrap { margin-top:10px; }
		.lf-ai-media-add { border:1px dashed #bba5f8; background:#faf7ff; color:#6a33e8; border-radius:10px; min-height:34px; padding:0 12px; font-size:12px; cursor:pointer; }
		.lf-ai-media-add:hover { background:#f5f0ff; border-color:#8348f9; }
		.lf-service-details__media .lf-ai-media-add-wrap { position:absolute; top:10px; left:10px; margin-top:0; z-index:3; }
		.lf-service-details__media, .lf-media-section__media { position:relative; }
		.lf-ai-media-picker { position:fixed; inset:0; z-index:100003; background:rgba(15,23,42,.45); display:flex; align-items:center; justify-content:center; padding:18px; }
		.lf-ai-media-picker[hidden] { display:none !important; }
		.lf-ai-media-picker__card { width:min(560px, calc(100vw - 30px)); max-height:82vh; overflow:hidden; background:#fff; border:1px solid #dbe3ef; border-radius:12px; box-shadow:0 20px 50px rgba(15,23,42,.28); display:flex; flex-direction:column; }
		.lf-ai-media-picker__head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 12px; border-bottom:1px solid #e2e8f0; background:#f8fafc; }
		.lf-ai-media-picker__title { font-size:13px; font-weight:700; color:#0f172a; }
		.lf-ai-media-picker__close { border:1px solid #d6c8fb; background:#fff; color:#6a33e8; border-radius:8px; width:28px; height:28px; cursor:pointer; font-size:15px; line-height:1; }
		.lf-ai-media-picker__body { padding:12px; display:flex; flex-direction:column; gap:8px; }
		.lf-ai-media-picker__actions { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:8px; }
		.lf-ai-media-picker__actions button { border:1px solid #e2e8f0; background:#fff; color:#0f172a; border-radius:8px; min-height:34px; padding:0 10px; font-size:12px; cursor:pointer; }
		.lf-ai-media-picker__actions button:hover { border-color:#c4b5fd; background:#faf7ff; }
		.lf-ai-media-picker__hint { font-size:12px; color:#64748b; }
		.lf-ai-faq-controls { margin-top:8px; display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
		.lf-ai-faq-add { border:1px solid #d6c8fb; background:#fff; color:#6a33e8; border-radius:8px; min-height:28px; padding:0 10px; font-size:12px; cursor:pointer; }
		.lf-ai-faq-add:hover { background:#f5f0ff; }
		.lf-ai-faq-picker { position:fixed; inset:0; z-index:100004; background:rgba(15,23,42,.45); display:flex; align-items:center; justify-content:center; padding:18px; }
		.lf-ai-faq-picker[hidden] { display:none !important; }
		.lf-ai-faq-picker__card { width:min(720px, calc(100vw - 30px)); max-height:82vh; overflow:hidden; background:#fff; border:1px solid #dbe3ef; border-radius:12px; box-shadow:0 20px 50px rgba(15,23,42,.28); display:flex; flex-direction:column; }
		.lf-ai-faq-picker__head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 12px; border-bottom:1px solid #e2e8f0; background:#f8fafc; }
		.lf-ai-faq-picker__title { font-size:13px; font-weight:700; color:#0f172a; }
		.lf-ai-faq-picker__close { border:1px solid #d6c8fb; background:#fff; color:#6a33e8; border-radius:8px; width:28px; height:28px; cursor:pointer; font-size:15px; line-height:1; }
		.lf-ai-faq-picker__search { border:1px solid #d6c8fb; border-radius:8px; padding:8px 10px; margin:10px 12px; font-size:13px; }
		.lf-ai-faq-picker__list { padding:0 12px 12px; overflow:auto; display:flex; flex-direction:column; gap:8px; }
		.lf-ai-faq-picker__item { border:1px solid #e2e8f0; border-radius:8px; background:#fff; padding:8px 10px; display:flex; align-items:flex-start; justify-content:space-between; gap:10px; }
		.lf-ai-faq-picker__item:hover { border-color:#c4b5fd; background:#faf7ff; }
		.lf-ai-faq-picker__meta b { display:block; font-size:12px; color:#0f172a; margin-bottom:3px; }
		.lf-ai-faq-picker__meta small { display:block; font-size:11px; color:#64748b; line-height:1.35; }
		.lf-ai-faq-picker__context { display:block; font-size:11px; color:#475569; margin-top:4px; font-weight:600; line-height:1.35; }
		.lf-ai-faq-picker__add { border:1px solid #d6c8fb; background:#fff; color:#6a33e8; border-radius:8px; min-height:28px; padding:0 10px; font-size:12px; cursor:pointer; white-space:nowrap; }
		.lf-ai-faq-picker__add:hover { background:#f5f0ff; }
		.lf-ai-faq-picker__add[disabled] { opacity:.5; cursor:default; background:#f8fafc; }
		.lf-ai-faq-picker__empty { padding:10px; color:#64748b; font-size:12px; }
		.lf-ai-section-bg-picker { position:fixed; inset:0; z-index:100005; background:rgba(15,23,42,.45); display:flex; align-items:center; justify-content:center; padding:18px; }
		.lf-ai-section-bg-picker[hidden] { display:none !important; }
		.lf-ai-section-bg-picker__card { width:min(420px, calc(100vw - 30px)); max-height:82vh; overflow:auto; background:#fff; border:1px solid #dbe3ef; border-radius:12px; box-shadow:0 20px 50px rgba(15,23,42,.28); display:flex; flex-direction:column; gap:10px; padding:12px; }
		.lf-ai-section-bg-picker__head { display:flex; align-items:center; justify-content:space-between; gap:8px; }
		.lf-ai-section-bg-picker__title { font-size:13px; font-weight:700; color:#0f172a; }
		.lf-ai-section-bg-picker__close { border:1px solid #d6c8fb; background:#fff; color:#6a33e8; border-radius:8px; width:28px; height:28px; cursor:pointer; font-size:15px; line-height:1; }
		.lf-ai-section-bg-picker__swatches { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:8px; }
		.lf-ai-section-bg-picker__swatch { border:1px solid #e2e8f0; border-radius:8px; background:#fff; padding:8px; cursor:pointer; display:flex; align-items:center; gap:8px; text-align:left; font-size:12px; color:#0f172a; }
		.lf-ai-section-bg-picker__swatch:hover { border-color:#c4b5fd; background:#faf7ff; }
		.lf-ai-section-bg-picker__swatch-color { width:28px; height:28px; border-radius:6px; border:1px solid rgba(15,23,42,.12); flex-shrink:0; }
		.lf-ai-section-bg-picker__swatch-label { line-height:1.2; }
		.lf-ai-section-bg-picker__custom { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
		.lf-ai-section-bg-picker__input { flex:1; min-width:140px; border:1px solid #d6c8fb; border-radius:8px; padding:8px 10px; font-size:13px; }
		.lf-ai-section-bg-picker__apply { border:1px solid #d6c8fb; background:#fff; color:#6a33e8; border-radius:8px; min-height:32px; padding:0 12px; font-size:12px; cursor:pointer; }
		.lf-ai-section-bg-picker__clearcustom { border:1px solid #e2e8f0; background:#f8fafc; color:#475569; border-radius:8px; min-height:32px; padding:0 12px; font-size:12px; cursor:pointer; align-self:flex-start; }
		.lf-ai-hero-settings .lf-ai-section-bg-picker__card { width:min(480px, calc(100vw - 30px)); gap:12px; }
		.lf-ai-hero-settings__label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#64748b; margin:4px 0 0; }
		.lf-ai-hero-settings__media { display:flex; flex-direction:column; align-items:flex-start; gap:8px; }
		.lf-ai-hero-settings__hint { font-size:12px; color:#475569; margin:0; line-height:1.35; }
		.lf-ai-hero-settings__summary { font-size:12px; color:#0f172a; margin:0; }
		.lf-ai-hero-settings__footer { display:flex; flex-wrap:wrap; gap:8px; justify-content:flex-end; margin-top:4px; }
		.lf-ai-section-bg-picker__swatch.is-selected { outline:2px solid #8348f9; outline-offset:1px; }
		.lf-ai-section-align-picker { position:fixed; inset:0; z-index:100005; background:rgba(15,23,42,.45); display:flex; align-items:center; justify-content:center; padding:18px; }
		.lf-ai-section-align-picker[hidden] { display:none !important; }
		.lf-ai-section-align-picker__card { width:min(320px, calc(100vw - 30px)); background:#fff; border:1px solid #dbe3ef; border-radius:12px; box-shadow:0 20px 50px rgba(15,23,42,.28); padding:12px; display:flex; flex-direction:column; gap:10px; }
		.lf-ai-section-align-picker__head { font-size:13px; font-weight:700; color:#0f172a; }
		.lf-ai-section-align-picker__row { display:flex; gap:8px; flex-wrap:wrap; }
		/* .row uses display:flex which beats the UA [hidden] rule — force-hide benefits-only rows. */
		.lf-ai-section-align-picker__card [hidden] { display:none !important; }
		.lf-ai-section-align-picker__btn { flex:1; min-width:72px; border:1px solid #d6c8fb; background:#fff; color:#6a33e8; border-radius:8px; min-height:34px; font-size:12px; font-weight:600; cursor:pointer; }
		.lf-ai-section-align-picker__btn:hover { background:#f5f0ff; }
		.lf-ai-section-align-picker__close { align-self:flex-end; border:1px solid #e2e8f0; background:#fff; color:#64748b; border-radius:8px; width:28px; height:28px; cursor:pointer; font-size:15px; line-height:1; }
		.lf-ai-benefit-card-drag.is-dragging, .lf-ai-service-intro-card-drag.is-dragging { opacity:.65; outline:2px dashed rgba(131,72,249,.45); outline-offset:2px; }
		.lf-ai-float__confirm { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(15,23,42,.4); z-index:5; padding:12px; pointer-events:auto; }
		.lf-ai-float__confirm[hidden] { display:none !important; pointer-events:none !important; }
		.lf-ai-float__confirm-card { width:100%; max-width:360px; background:#fff; border:1px solid #dbe3ef; border-radius:12px; box-shadow:0 10px 34px rgba(15,23,42,.28); padding:14px; }
		.lf-ai-float__confirm-text { margin:0 0 10px; color:#1e293b; font-size:13px; line-height:1.45; }
		.lf-ai-float__confirm-actions { display:flex; gap:8px; justify-content:flex-end; }
		@media (max-width: 782px) {
			.lf-ai-float { right:12px; bottom:12px; left:12px; }
			.lf-ai-float--seo { right:12px; bottom:68px; left:12px; }
			.lf-ai-float__toggle { width:100%; justify-content:center; }
			.lf-ai-float__panel { width:100%; left:0; right:0; }
			.lf-ai-float__mode { grid-template-columns:1fr; }
			.lf-ai-rail {
				left:10px;
				right:10px;
				top:auto;
				bottom:calc(72px + env(safe-area-inset-bottom, 0px));
				width:auto;
				max-height:min(42vh, 340px);
				padding-bottom:max(10px, env(safe-area-inset-bottom, 0px));
			}
			.lf-ai-rail.is-collapsed {
				left:50%;
				right:auto;
				transform:translateX(-50%);
				width:auto;
			}
		}
		.lf-ai-inline-link__toolbar { position:fixed; z-index:100002; }
		.lf-ai-inline-link__toolbar[hidden] { display:none !important; }
		.lf-ai-inline-link__toolbar-inner { display:flex; gap:6px; align-items:center; padding:6px 10px; border-radius:10px; background:#1e1b4b; color:#fff; box-shadow:0 8px 24px rgba(15,23,42,.35); font-size:12px; font-weight:600; }
		.lf-ai-inline-link__open { border:0; border-radius:8px; background:#8348f9; color:#fff; font:inherit; font-weight:700; padding:6px 10px; cursor:pointer; }
		.lf-ai-inline-link__open:hover { background:#6d28d9; }
		.lf-ai-inline-link__backdrop { position:fixed; inset:0; z-index:100003; background:rgba(15,23,42,.45); }
		.lf-ai-inline-link__backdrop[hidden] { display:none !important; }
		.lf-ai-inline-link__panel { position:fixed; z-index:100004; left:50%; top:50%; transform:translate(-50%,-50%); width:min(400px,calc(100vw - 32px)); max-height:min(72vh,520px); overflow:auto; background:#fff; border:1px solid #dbe3ef; border-radius:14px; box-shadow:0 18px 55px rgba(15,23,42,.28); padding:14px; display:flex; flex-direction:column; gap:10px; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; }
		.lf-ai-inline-link__panel[hidden] { display:none !important; }
		.lf-ai-inline-link__panel-head { display:flex; align-items:center; justify-content:space-between; gap:8px; }
		.lf-ai-inline-link__panel-head strong { font-size:14px; color:#0f172a; }
		.lf-ai-inline-link__x { border:1px solid #e2e8f0; background:#fff; width:32px; height:32px; border-radius:8px; cursor:pointer; font-size:18px; line-height:1; color:#475569; }
		.lf-ai-inline-link__hint { margin:0; font-size:12px; color:#64748b; line-height:1.45; }
		.lf-ai-inline-link__label { display:flex; flex-direction:column; gap:4px; font-size:11px; font-weight:600; color:#475569; }
		.lf-ai-inline-link__label input { border:1px solid #d6c8fb; border-radius:8px; padding:8px 10px; font-size:13px; width:100%; box-sizing:border-box; }
		.lf-ai-inline-link__list { display:flex; flex-direction:column; gap:4px; max-height:220px; overflow:auto; border:1px solid #e2e8f0; border-radius:10px; padding:6px; background:#f8fafc; }
		.lf-ai-inline-link__row { display:flex; flex-direction:column; align-items:flex-start; gap:2px; text-align:left; width:100%; padding:8px 10px; border:0; border-radius:8px; background:#fff; cursor:pointer; font-size:12px; color:#0f172a; }
		.lf-ai-inline-link__row:hover { background:#f5f3ff; }
		.lf-ai-inline-link__row small { font-size:10px; color:#64748b; font-weight:600; text-transform:uppercase; letter-spacing:.03em; }
		.lf-ai-inline-link__row-url { font-size:10px; color:#64748b; line-height:1.35; word-break:break-all; margin-top:2px; }
		.lf-ai-inline-link__newtab { display:flex; align-items:center; gap:8px; font-size:12px; font-weight:600; color:#475569; cursor:pointer; user-select:none; margin:0; }
		.lf-ai-inline-link__newtab input { width:auto; margin:0; cursor:pointer; }
		.lf-ai-inline-link__unlink { align-self:stretch; border:1px solid #e2e8f0; border-radius:10px; padding:8px 14px; font-weight:600; font-size:13px; cursor:pointer; background:#fff; color:#b91c1c; }
		.lf-ai-inline-link__unlink:hover { background:#fef2f2; border-color:#fecaca; }
		.lf-ai-inline-link__unlink[hidden] { display:none !important; }
		.lf-ai-inline-link__apply { align-self:flex-end; border:0; border-radius:10px; padding:8px 14px; font-weight:700; font-size:13px; cursor:pointer; background:linear-gradient(180deg,#7c3aed 0%,#6d28d9 100%); color:#fff; }
		.lf-ai-inline-link__apply:hover { background:linear-gradient(180deg,#6d28d9 0%,#5b21b6 100%); }
		.lf-ai-inline-link__empty { font-size:12px; color:#64748b; margin:0; padding:6px; }
		.lf-ai-inline-link { pointer-events:none; }
		.lf-ai-inline-link__toolbar, .lf-ai-inline-link__panel, .lf-ai-inline-link__backdrop { pointer-events:auto; }
	';
}

function lf_ai_assistant_widget_js(): string {
	return '(function($){
		"use strict";
		var stateKey = "lfAiFloatState";
		var seoStateKey = "lfAiSeoFloatState";
		var $root = $("[data-lf-ai-float]");
		var $linkRoot = $("[data-lf-ai-inline-link-root]");
		var $seoRoot = $("[data-lf-ai-seo-float]");
		if (!$root.length || typeof lfAiFloating === "undefined") return;
		$root.attr("data-lf-ai-js-init", "1");
		var $toggle = $root.find("[data-lf-ai-toggle]");
		var $panel = $root.find("#lf-ai-float-panel");
		var $seoToggle = $seoRoot.find("[data-lf-ai-seo-toggle]");
		var $seoPanel = $seoRoot.find("#lf-ai-seo-panel");
		var $prompt = $root.find("[data-lf-ai-prompt]");
		var $status = $root.find("[data-lf-ai-status]");
		var $diff = $root.find("[data-lf-ai-diff]");
		var $btnGenerate = $root.find("[data-lf-ai-generate]");
		var $btnApply = $root.find("[data-lf-ai-apply]");
		var $btnReject = $root.find("[data-lf-ai-reject]");
		var $btnRevert = $root.find("[data-lf-ai-revert]");
		var $btnUndo = $root.find("[data-lf-ai-undo]");
		var $btnRedo = $root.find("[data-lf-ai-redo]");
		var $btnEditorToggle = $root.find("[data-lf-ai-editor-toggle]");
		var $target = $root.find("[data-lf-ai-target]");
		var $targetRef = $root.find("[data-lf-ai-target-ref]");
		var $mode = $root.find("[data-lf-ai-mode]");
		var $cptWrap = $root.find("[data-lf-ai-cpt-wrap]");
		var $cptType = $root.find("[data-lf-ai-cpt-type]");
		var $batchWrap = $root.find("[data-lf-ai-batch-wrap]");
		var $batchType = $root.find("[data-lf-ai-batch-type]");
		var $batchCountWrap = $root.find("[data-lf-ai-batch-count-wrap]");
		var $batchCount = $root.find("[data-lf-ai-batch-count]");
		var $docInput = $root.find("[data-lf-ai-doc-input]");
		var $docAttach = $root.find("[data-lf-ai-doc-attach]");
		var $docClear = $root.find("[data-lf-ai-doc-clear]");
		var $docName = $root.find("[data-lf-ai-doc-name]");
		var $onboarding = $root.find("[data-lf-ai-onboarding]");
		var $onboardingText = $root.find("[data-lf-ai-onboarding-text]");
		var $seo = $seoRoot.find("[data-lf-ai-seo]");
		var $seoScore = $seoRoot.find("[data-lf-ai-seo-score]");
		var $seoPerfChip = $seoRoot.find("[data-lf-ai-seo-perf-chip]");
		var $seoList = $seoRoot.find("[data-lf-ai-seo-list]");
		var $seoRefresh = $seoRoot.find("[data-lf-ai-seo-refresh]");
		var $seoOverallFill = $seoRoot.find("[data-lf-ai-seo-overall-fill]");
		var $seoSubSeo = $seoRoot.find("[data-lf-ai-seo-sub-seo]");
		var $seoSubConv = $seoRoot.find("[data-lf-ai-seo-sub-conv]");
		var $seoSubPerf = $seoRoot.find("[data-lf-ai-seo-sub-perf]");
		var $seoTasks = $seoRoot.find("[data-lf-ai-seo-tasks]");
		var $seoSerpUrl = $seoRoot.find("[data-lf-ai-seo-serp-url]");
		var $seoSerpTitle = $seoRoot.find("[data-lf-ai-seo-serp-title]");
		var $seoSerpDesc = $seoRoot.find("[data-lf-ai-seo-serp-desc]");
		var $seoCoverage = $seoRoot.find("[data-lf-ai-seo-coverage]");
		var $seoVitals = $seoRoot.find("[data-lf-ai-seo-vitals]");
		var $confirm = $root.find("[data-lf-ai-confirm]");
		var $confirmText = $root.find("[data-lf-ai-confirm-text]");
		var $confirmYes = $root.find("[data-lf-ai-confirm-yes]");
		var $confirmNo = $root.find("[data-lf-ai-confirm-no]");
		var defaultConfirmText = String($confirmText.text() || "");
		var defaultConfirmYesText = String($confirmYes.text() || "");
		var pendingConfirmAction = null;
		var proposed = null;
		var pbPatchPending = false;
		var expandPbNextGenerate = false;
		var lastProposalHomepageSectionId = "";
		var current = null;
		var creationPayload = null;
		var lastMode = "auto";
		var activeAssistantCptType = String($cptType.val() || "lf_service");
		var activeAssistantBatchType = String($batchType.val() || "post");
		var activeAssistantBatchCount = 5;
		var pageContextType = String(lfAiFloating.context_type || "homepage");
		var pageContextId = String(lfAiFloating.context_id || "homepage");
		var activeContextType = pageContextType;
		var activeContextId = pageContextId;
		var activeTargetLabel = String(lfAiFloating.target_label || "Homepage");
		var labels = lfAiFloating.labels || {};
		var homepageEnabledMap = (lfAiFloating.homepage_enabled && typeof lfAiFloating.homepage_enabled === "object") ? lfAiFloating.homepage_enabled : {};
		/**
		 * Inline saves must target the same store the front uses. The AI panel mutates activeContext* after
		 * generate/apply; homepage sections live in lf_homepage_section_config, not the static front page post.
		 */
		function persistContextFromWrap(wrap) {
			if (wrap && wrap.closest && wrap.closest(".site-main--homepage")) {
				return { context_type: "homepage", context_id: "homepage" };
			}
			// Use the page immutable context, not the assistant active target (which can drift).
			return { context_type: String(pageContextType || "homepage"), context_id: String(pageContextId || "homepage") };
		}
		var promptSnippet = "";
		var docContext = "";
		var docLabel = "";
		var inlineQuickEdit = null;
		var inlineActiveEl = null;
		var inlineOriginalText = "";
		var inlineOriginalHtml = "";
		var inlineBodyEditSourceEl = null;
		var inlineOriginalBodyHtml = "";
		var inlineOriginalBodyText = "";
		var inlineIsSaving = false;
		var sectionGridPickerEl = null;
		var sectionGridPickerWrap = null;
		var sectionGridPickerPatch = "";
		var serviceLibraryCache = null;
		var servicePickerEl = null;
		var servicePickerSearchEl = null;
		var servicePickerListEl = null;
		var servicePickerWrap = null;
		var activeDragSection = null;
		var activeColumnDrag = null;
		var selectedSectionWrap = null;
		var commandPaletteEl = null;
		var commandInputEl = null;
		var commandListEl = null;
		var commandRows = [];
		var commandActiveIndex = 0;
		var sectionRailEl = null;
		var railCollapsed = false;
		var railStateKey = "lfAiRailState";
		var editorModeKey = "lfAiEditorMode";
		var editingEnabled = true;
		var activeRailDragSectionId = "";
		var railPointerDrag = null;
		var activeLibraryDragSectionType = "";
		var activeProcessDragEl = null;
		var activeFaqDragEl = null;
		var activeBenefitDragEl = null;
		var activeServiceIntroDragEl = null;
		var sectionBgPickerEl = null;
		var sectionBgPickerWrap = null;
		var sectionAlignPickerEl = null;
		var sectionAlignPickerWrap = null;
		var sectionInsertPickerEl = null;
		var sectionInsertAfterId = "";
		var sectionInsertBeforeId = "";
		var benefitsCtaPickerEl = null;
		var benefitsCtaPickerWrap = null;
		var benefitsCtaPickerIsBenefits = true;
		var benefitsCtaPickerButtonNode = null;
		var heroSettingsPickerEl = null;
		var heroSettingsPickerWrap = null;
		var heroSettingsState = { variant: "default", mode: "image", imageId: 0, videoId: 0 };
		var railLibraryOpen = false;
		var suppressInlineClickUntil = 0;
		var iconSlugs = Array.isArray(lfAiFloating.icon_slugs) ? lfAiFloating.icon_slugs : [];
		var iconPickerEl = null;
		var iconPickerSearchEl = null;
		var iconPickerListEl = null;
		var iconPickerCurrentSlug = "";
		var iconPickerOnSelect = null;
		var mediaPickerEl = null;
		var mediaPickerWrap = null;
		var faqPickerEl = null;
		var faqPickerSearchEl = null;
		var faqPickerListEl = null;
		var faqPickerWrap = null;
		var faqPickerList = null;
		var faqLibraryCache = null;
		var inlineCandidateSelector = "main h1,main h2,main h3,main h4,main h5,main h6,main p,main li,main blockquote,main figcaption,main .lf-block-hero__card-item-text,main .lf-service-details__text,main .lf-service-details__micro-text,#primary h1,#primary h2,#primary h3,#primary h4,#primary h5,#primary h6,#primary p,#primary li,#primary blockquote,#primary figcaption,#primary .lf-block-hero__card-item-text,#primary .lf-service-details__text,#primary .lf-service-details__micro-text,.site-main h1,.site-main h2,.site-main h3,.site-main h4,.site-main h5,.site-main h6,.site-main p,.site-main li,.site-main blockquote,.site-main figcaption,.site-main .lf-block-hero__card-item-text,.site-main .lf-service-details__text,.site-main .lf-service-details__micro-text,.site-content h1,.site-content h2,.site-content h3,.site-content h4,.site-content h5,.site-content h6,.site-content p,.site-content li,.site-content blockquote,.site-content figcaption,.site-content .lf-block-hero__card-item-text,.site-content .lf-service-details__text,.site-content .lf-service-details__micro-text,article h1,article h2,article h3,article h4,article h5,article h6,article p,article li,article blockquote,article figcaption,article .lf-block-hero__card-item-text,article .lf-service-details__text,article .lf-service-details__micro-text";
		var inlineImageCandidateSelector = "main img,#primary img,.site-main img,.site-content img,article img";
		var mediaFrame = null;
		var launcherGapPx = 8;
		var lastPerfScore = null;

		function escapeHtml(text) {
			var div = document.createElement("div");
			div.textContent = String(text || "");
			return div.innerHTML;
		}
		function normalizeInlineText(text) {
			return String(text || "").replace(/\s+/g, " ").trim().toLowerCase();
		}
		function parseInlineReplacePrompt(prompt) {
			var p = String(prompt || "");
			var patterns = [
				/(?:change|replace|update|rewrite)[^"\n]*["“]([^"”]+)["”]\s*(?:to|with)\s*["“]([^"”]+)["”]/i,
				/(?:where\s+it\s+says|where\s+it\s+reads)\s*["“]([^"”]+)["”]\s*(?:to|with)\s*["“]([^"”]+)["”]/i,
				/(?:replace)\s*["“]([^"”]+)["”]\s*(?:with)\s*["“]([^"”]+)["”]/i
			];
			for (var i = 0; i < patterns.length; i++) {
				var m = p.match(patterns[i]);
				if (m && m[1] && m[2]) {
					return { from: String(m[1]).trim(), to: String(m[2]).trim() };
				}
			}
			return null;
		}
		function parseInlineTargetPrompt(prompt) {
			var p = String(prompt || "");
			var patterns = [
				/(?:where\s+it\s+says|where\s+it\s+reads)\s*["“]([^"”]+)["”]/i,
				/(?:change|replace|update|rewrite)\s*["“]([^"”]+)["”]/i
			];
			for (var i = 0; i < patterns.length; i++) {
				var m = p.match(patterns[i]);
				if (m && m[1]) {
					return { from: String(m[1]).trim() };
				}
			}
			return null;
		}
		function resolveInlineSelectorByText(fromText) {
			var needle = normalizeInlineText(fromText);
			if (needle === "") return null;
			var nodes = document.querySelectorAll("[data-lf-inline-editable=\"1\"]");
			if (!nodes || !nodes.length) return null;
			var best = null;
			nodes.forEach(function(node){
				var current = String(node.textContent || "").trim();
				var normalized = normalizeInlineText(current);
				if (normalized === "") return;
				var idx = normalized.indexOf(needle);
				if (idx === -1) return;
				var selector = String(node.getAttribute("data-lf-inline-selector") || "");
				if (selector === "") return;
				var score = 0;
				if (normalized === needle) score += 50;
				if (idx === 0) score += 15;
				score -= Math.abs(normalized.length - needle.length) / 100;
				if (!best || score > best.score) {
					best = { selector: selector, current: current, score: score };
				}
			});
			return best;
		}
		function countWords(text) {
			var clean = String(text || "").replace(/\s+/g, " ").trim();
			if (!clean) return 0;
			return clean.split(" ").filter(function(token){ return token !== ""; }).length;
		}
		function seoMainRoot() {
			return document.querySelector("main") || document.querySelector("#primary") || document.querySelector(".site-main") || document.querySelector("article") || document.body;
		}
		function seoMainText() {
			var root = seoMainRoot();
			if (!root) return "";
			var clone = root.cloneNode(true);
			Array.prototype.slice.call(clone.querySelectorAll("script,style,noscript,nav,footer,.lf-ai-float,.lf-ai-rail,.lf-ai-command,.lf-ai-seo-float")).forEach(function(node){
				if (node && node.parentNode) node.parentNode.removeChild(node);
			});
			return String(clone.textContent || "").replace(/\s+/g, " ").trim();
		}
		function normalizeKeywordTokens(value) {
			return String(value || "")
				.split(/[\r\n,]+/)
				.map(function(v){ return String(v || "").trim(); })
				.filter(function(v){ return v !== ""; });
		}
		function containsPhrase(haystack, needle) {
			var h = normalizeInlineText(haystack);
			var n = normalizeInlineText(needle);
			if (!h || !n) return false;
			return h.indexOf(n) !== -1;
		}
		function statusClass(status) {
			return status === "ok" ? "ok" : (status === "error" ? "error" : "warn");
		}
		function perfGrade(score) {
			var s = parseInt(String(score || "0"), 10);
			if (isNaN(s)) s = 0;
			if (s >= 92) return "A";
			if (s >= 82) return "B";
			if (s >= 70) return "C";
			if (s >= 58) return "D";
			return "F";
		}
		function renderPerfChip(score) {
			if (!$seoPerfChip.length) return;
			var s = parseInt(String(score || "0"), 10);
			if (isNaN(s)) s = 0;
			var grade = perfGrade(s);
			var delta = (lastPerfScore === null || !isFinite(lastPerfScore)) ? null : (s - lastPerfScore);
			var trend = "";
			if (delta !== null) {
				if (delta > 0) trend = " ▲+" + delta;
				else if (delta < 0) trend = " ▼" + delta;
				else trend = " •0";
			}
			$seoPerfChip.removeClass("lf-ai-seo__perf-chip--pending lf-ai-seo__perf-chip--good lf-ai-seo__perf-chip--warn lf-ai-seo__perf-chip--bad");
			var cls = s >= 85 ? "good" : (s >= 70 ? "warn" : "bad");
			$seoPerfChip.addClass("lf-ai-seo__perf-chip--" + cls);
			$seoPerfChip.text("Perf " + grade + " (" + s + ")" + trend);
			lastPerfScore = s;
		}
		function renderSeoSnapshot() {
			if (!$seo.length || !$seoScore.length || !$seoList.length) return;
			var title = String(document.title || "").trim();
			var titleLen = title.length;
			var metaDescNode = document.querySelector("meta[name=\"description\"]");
			var metaDesc = metaDescNode ? String(metaDescNode.getAttribute("content") || "").trim() : "";
			var metaDescLen = metaDesc.length;
			var canonical = !!document.querySelector("link[rel=\"canonical\"]");
			var robotsNode = document.querySelector("meta[name=\"robots\"]");
			var robots = robotsNode ? String(robotsNode.getAttribute("content") || "").toLowerCase() : "";
			var noindex = robots.indexOf("noindex") !== -1;
			var h1Count = document.querySelectorAll("main h1,#primary h1,.site-main h1,article h1,h1.entry-title,h1.wp-block-post-title").length;
			var wordCount = countWords(seoMainText());
			var root = seoMainRoot();
			var ctaCount = root ? root.querySelectorAll("a.lf-btn,button.lf-btn,a[href^=\"tel:\"],a[href*=\"contact\"],a[href*=\"quote\"],a[href*=\"book\"],button[type=\"submit\"]").length : 0;
			var formCount = root ? root.querySelectorAll("form").length : 0;
			var imgs = root ? Array.prototype.slice.call(root.querySelectorAll("img")) : [];
			var missingAlt = imgs.filter(function(img){
				return String(img.getAttribute("alt") || "").trim() === "";
			}).length;
			var missingDimensions = imgs.filter(function(img){
				var hasW = !!img.getAttribute("width");
				var hasH = !!img.getAttribute("height");
				return !(hasW && hasH);
			}).length;
			var lazyImgs = imgs.filter(function(img){
				return String(img.getAttribute("loading") || "").toLowerCase() === "lazy";
			}).length;
			var iframeCount = root ? root.querySelectorAll("iframe").length : 0;
			var videoCount = root ? root.querySelectorAll("video").length : 0;
			var domNodes = root ? root.querySelectorAll("*").length : 0;
			var scriptCount = document.querySelectorAll("script[src]").length;
			var requestCount = null;
			var totalTransferKb = null;
			var scriptTransferKb = null;
			var imageTransferKb = null;
			var perfApi = window.performance || null;
			var navEntry = null;
			var ttfbMs = null;
			var dclMs = null;
			var loadMs = null;
			var fcpMs = null;
			var lcpMs = null;
			var clsScore = null;
			var serverTimingRows = [];
			try {
				var resourceEntries = perfApi && perfApi.getEntriesByType ? perfApi.getEntriesByType("resource") : [];
				if (resourceEntries && resourceEntries.length) {
					requestCount = resourceEntries.length;
					var totalBytes = 0;
					var scriptBytes = 0;
					var imageBytes = 0;
					var hasByteSignals = false;
					resourceEntries.forEach(function(entry){
						if (!entry) return;
						var transfer = isFinite(entry.transferSize) && entry.transferSize > 0 ? entry.transferSize : (isFinite(entry.encodedBodySize) && entry.encodedBodySize > 0 ? entry.encodedBodySize : 0);
						if (transfer > 0) hasByteSignals = true;
						totalBytes += transfer;
						if (entry.initiatorType === "script") scriptBytes += transfer;
						if (entry.initiatorType === "img") imageBytes += transfer;
					});
					if (hasByteSignals) {
						totalTransferKb = Math.round(totalBytes / 1024);
						scriptTransferKb = Math.round(scriptBytes / 1024);
						imageTransferKb = Math.round(imageBytes / 1024);
					}
				}
			} catch (e) {}
			try {
				var navEntries = perfApi && perfApi.getEntriesByType ? perfApi.getEntriesByType("navigation") : [];
				navEntry = navEntries && navEntries.length ? navEntries[0] : null;
				if (navEntry) {
					ttfbMs = isFinite(navEntry.responseStart) ? Math.round(navEntry.responseStart) : null;
					dclMs = isFinite(navEntry.domContentLoadedEventEnd) ? Math.round(navEntry.domContentLoadedEventEnd) : null;
					loadMs = isFinite(navEntry.loadEventEnd) ? Math.round(navEntry.loadEventEnd) : null;
					var serverTiming = Array.isArray(navEntry.serverTiming) ? navEntry.serverTiming : [];
					serverTimingRows = serverTiming.slice(0, 3).map(function(metric){
						var name = String(metric && metric.name ? metric.name : "metric");
						var dur = (metric && isFinite(metric.duration)) ? (Math.round(metric.duration * 10) / 10) : null;
						var desc = String(metric && metric.description ? metric.description : "");
						var status = dur === null ? "warn" : (dur <= 150 ? "ok" : (dur <= 500 ? "warn" : "error"));
						return { label: "Server timing (" + name + "): " + (dur === null ? "unavailable" : (dur + " ms")) + (desc ? " - " + desc : ""), status: status };
					});
				}
			} catch (e) {}
			try {
				var paints = perfApi && perfApi.getEntriesByType ? perfApi.getEntriesByType("paint") : [];
				paints.forEach(function(entry){
					if (entry && entry.name === "first-contentful-paint" && isFinite(entry.startTime)) {
						fcpMs = Math.round(entry.startTime);
					}
				});
			} catch (e) {}
			try {
				var lcpEntries = perfApi && perfApi.getEntriesByType ? perfApi.getEntriesByType("largest-contentful-paint") : [];
				if (lcpEntries && lcpEntries.length) {
					var lcp = lcpEntries[lcpEntries.length - 1];
					if (lcp && isFinite(lcp.startTime)) lcpMs = Math.round(lcp.startTime);
				}
			} catch (e) {}
			try {
				var clsEntries = perfApi && perfApi.getEntriesByType ? perfApi.getEntriesByType("layout-shift") : [];
				if (clsEntries && clsEntries.length) {
					var clsTotal = 0;
					clsEntries.forEach(function(entry){
						if (entry && !entry.hadRecentInput && isFinite(entry.value)) clsTotal += entry.value;
					});
					clsScore = Number(clsTotal.toFixed(3));
				}
			} catch (e) {}
			var internalLinks = root ? Array.prototype.slice.call(root.querySelectorAll("a[href]")).filter(function(link){
				var href = String(link.getAttribute("href") || "");
				return href && href.indexOf("#") !== 0 && href.indexOf("mailto:") !== 0 && href.indexOf("tel:") !== 0 && (href.indexOf("http") !== 0 || href.indexOf(window.location.origin) === 0);
			}).length : 0;
			var qHeadings = root ? root.querySelectorAll("h2,h3").length : 0;
			var faqCount = root ? root.querySelectorAll(".lf-block-faq-accordion__item").length : 0;
			var seoScoreClient = 100;
			if (!title) seoScoreClient -= 20;
			else if (titleLen < 35 || titleLen > 65) seoScoreClient -= 8;
			if (!metaDesc) seoScoreClient -= 12;
			else if (metaDescLen < 120 || metaDescLen > 165) seoScoreClient -= 6;
			if (h1Count === 0) seoScoreClient -= 14;
			if (h1Count > 1) seoScoreClient -= 8;
			if (!canonical) seoScoreClient -= 6;
			if (noindex) seoScoreClient -= 24;
			if (wordCount < 250) seoScoreClient -= 12;
			if (internalLinks < 2) seoScoreClient -= 8;
			if (missingAlt > 0) seoScoreClient -= Math.min(14, missingAlt * 2);
			seoScoreClient = Math.max(0, Math.min(100, seoScoreClient));

			var convScore = 100;
			if (ctaCount === 0) convScore -= 25;
			if (formCount === 0 && ctaCount < 2) convScore -= 15;
			if (faqCount === 0) convScore -= 10;
			if (qHeadings < 2) convScore -= 8;
			if (wordCount < 250) convScore -= 12;
			convScore = Math.max(0, Math.min(100, convScore));

			var perfScore = 100;
			if (domNodes > 1800) perfScore -= 18;
			else if (domNodes > 1200) perfScore -= 10;
			if (iframeCount > 1) perfScore -= 8;
			if ((iframeCount + videoCount) > 2) perfScore -= 6;
			if (missingDimensions > 0) perfScore -= Math.min(15, missingDimensions * 2);
			if (imgs.length > 2 && lazyImgs < Math.max(1, imgs.length - 1)) perfScore -= 10;
			if (scriptCount > 25) perfScore -= 12;
			else if (scriptCount > 18) perfScore -= 6;
			if (requestCount !== null) {
				if (requestCount > 120) perfScore -= 10;
				else if (requestCount > 80) perfScore -= 5;
			}
			if (totalTransferKb !== null) {
				if (totalTransferKb > 3500) perfScore -= 10;
				else if (totalTransferKb > 2200) perfScore -= 5;
			}
			if (loadMs !== null) {
				if (loadMs > 5000) perfScore -= 12;
				else if (loadMs > 3000) perfScore -= 6;
			}
			if (lcpMs !== null) {
				if (lcpMs > 4000) perfScore -= 10;
				else if (lcpMs > 2500) perfScore -= 5;
			}
			perfScore = Math.max(0, Math.min(100, perfScore));

			$seoScore.html("Overall: <strong>Analyzing...</strong>");
			if ($seoPerfChip.length) {
				$seoPerfChip.removeClass("lf-ai-seo__perf-chip--good lf-ai-seo__perf-chip--warn lf-ai-seo__perf-chip--bad").addClass("lf-ai-seo__perf-chip--pending");
				$seoPerfChip.text("Perf " + perfGrade(perfScore) + " (" + perfScore + ")");
			}
			if ($seoOverallFill.length) $seoOverallFill.css("width", "0%");
			if ($seoSubSeo.length) $seoSubSeo.text(seoScoreClient + "/100");
			if ($seoSubConv.length) $seoSubConv.text(convScore + "/100");
			if ($seoSubPerf.length) $seoSubPerf.text(perfScore + "/100");

			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_seo_snapshot",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId
			}).done(function(res){
				var backendSeo = seoScoreClient;
				var backendChecks = [];
				var intent = "";
				if (res && res.success && res.data) {
					backendSeo = parseInt(String(res.data.backend_seo_score || seoScoreClient), 10);
					if (isNaN(backendSeo)) backendSeo = seoScoreClient;
					backendChecks = Array.isArray(res.data.checks) ? res.data.checks : [];
					intent = String(res.data.intent || "");
				}
				var seoScore = Math.round((seoScoreClient * 0.45) + (backendSeo * 0.55));
				var overall = Math.round((seoScore * 0.58) + (convScore * 0.25) + (perfScore * 0.17));
				overall = Math.max(0, Math.min(100, overall));
				$seoScore.html("Overall: <strong>" + overall + "/100</strong>" + (intent ? " • Intent: " + escapeHtml(intent) : ""));
				if ($seoOverallFill.length) $seoOverallFill.css("width", overall + "%");
				if ($seoSubSeo.length) $seoSubSeo.text(seoScore + "/100");
				if ($seoSubConv.length) $seoSubConv.text(convScore + "/100");
				if ($seoSubPerf.length) $seoSubPerf.text(perfScore + "/100");
				renderPerfChip(perfScore);

				var rows = [
					{ status: (!!title && titleLen >= 35 && titleLen <= 65) ? "ok" : "warn", label: "Title length: " + titleLen + " chars (35-65)." },
					{ status: (!!metaDesc && metaDescLen >= 120 && metaDescLen <= 165) ? "ok" : "warn", label: "Meta description: " + metaDescLen + " chars (120-165)." },
					{ status: (h1Count === 1) ? "ok" : (h1Count > 1 ? "warn" : "error"), label: "H1 count: " + h1Count + " (target 1)." },
					{ status: (internalLinks >= 2) ? "ok" : "warn", label: "Internal links in content: " + internalLinks + " (target 2+)." },
					{ status: (ctaCount >= 1) ? "ok" : "error", label: "Primary CTA presence: " + ctaCount + " found." },
					{ status: (missingAlt === 0) ? "ok" : (missingAlt < 3 ? "warn" : "error"), label: "Images missing alt text: " + missingAlt + "." },
					{ status: (perfScore >= 85) ? "ok" : (perfScore >= 70 ? "warn" : "error"), label: "Performance hygiene score: " + perfScore + "/100." }
				];
				backendChecks.forEach(function(ch){
					if (!ch || !ch.label) return;
					var st = String(ch.status || "warn");
					var msg = String(ch.message || "");
					var action = String(ch.action || "");
					rows.push({ status: st, label: String(ch.label) + ": " + msg + (action ? " " + action : "") });
				});
				var actionable = rows.filter(function(row){ return row.status !== "ok"; });
				var taskRows = actionable.slice(0, 8).map(function(row, idx){
					var pr = row.status === "error" ? "high" : (idx < 3 ? "med" : "low");
					return { priority: pr, label: row.label };
				});
				var taskHtml = "";
				if (!taskRows.length) {
					taskHtml = "<div class=\"lf-ai-seo__task\"><span class=\"lf-ai-seo__task-priority lf-ai-seo__task-priority--low\">OK</span><span>No urgent SEO actions. Keep refining for conversions.</span></div>";
				} else {
					taskRows.forEach(function(task){
						taskHtml += "<div class=\"lf-ai-seo__task\"><span class=\"lf-ai-seo__task-priority lf-ai-seo__task-priority--" + task.priority + "\">" + (task.priority === "high" ? "HIGH" : (task.priority === "med" ? "MED" : "LOW")) + "</span><span>" + escapeHtml(task.label) + "</span></div>";
					});
				}
				if ($seoTasks.length) $seoTasks.html(taskHtml);

				var meta = (res && res.success && res.data && res.data.meta) ? res.data.meta : {};
				var backendTitle = (res && res.success && res.data) ? String(res.data.post_title || "") : "";
				var backendPermalink = (res && res.success && res.data) ? String(res.data.permalink || "") : "";
				var serpTitle = String((meta.meta_title || "") || title || backendTitle || "").trim();
				var serpDesc = String((meta.meta_description || "") || metaDesc || "").trim();
				var serpUrl = String((meta.canonical || "") || backendPermalink || window.location.href || "").trim();
				if ($seoSerpUrl.length) $seoSerpUrl.text(serpUrl);
				if ($seoSerpTitle.length) $seoSerpTitle.text(serpTitle || "(missing title)");
				if ($seoSerpDesc.length) $seoSerpDesc.text(serpDesc || "(missing meta description)");

				var primaryKw = String(meta.primary_keyword || "").trim();
				var secondaryKws = normalizeKeywordTokens(meta.secondary_keywords || "");
				var h1TextNode = document.querySelector("main h1,#primary h1,.site-main h1,article h1,h1.entry-title,h1.wp-block-post-title");
				var h1Text = h1TextNode ? String(h1TextNode.textContent || "") : "";
				var introNode = document.querySelector("main p,#primary p,.site-main p,article p");
				var introText = introNode ? String(introNode.textContent || "") : "";
				var coverageRows = [];
				if (primaryKw) {
					coverageRows.push({ label: "Primary keyword in title", status: containsPhrase(serpTitle, primaryKw) ? "ok" : "warn" });
					coverageRows.push({ label: "Primary keyword in H1", status: containsPhrase(h1Text, primaryKw) ? "ok" : "warn" });
					coverageRows.push({ label: "Primary keyword in intro", status: containsPhrase(introText, primaryKw) ? "ok" : "warn" });
				} else {
					coverageRows.push({ label: "Primary keyword missing in backend SEO settings", status: "error" });
				}
				secondaryKws.slice(0, 3).forEach(function(kw){
					coverageRows.push({ label: "Secondary keyword \"" + kw + "\" in page copy", status: containsPhrase(seoMainText(), kw) ? "ok" : "warn" });
				});
				var coverageHtml = "";
				coverageRows.forEach(function(row){
					var cls = statusClass(row.status);
					coverageHtml += "<div class=\"lf-ai-seo__coverage-row\"><span class=\"lf-ai-seo__badge lf-ai-seo__badge--" + cls + "\">" + (cls === "ok" ? "OK" : (cls === "error" ? "FIX" : "CHECK")) + "</span><span>" + escapeHtml(row.label) + "</span></div>";
				});
				if ($seoCoverage.length) $seoCoverage.html(coverageHtml);

				function metricStatus(value, good, warn) {
					if (value === null || value === undefined || !isFinite(value)) return "warn";
					return value <= good ? "ok" : (value <= warn ? "warn" : "error");
				}
				var vitalsRows = [
					{ label: "TTFB (" + (ttfbMs === null ? "unavailable" : (ttfbMs + " ms")) + ")", status: metricStatus(ttfbMs, 800, 1800) },
					{ label: "DOM content loaded (" + (dclMs === null ? "unavailable" : (dclMs + " ms")) + ")", status: metricStatus(dclMs, 1500, 3000) },
					{ label: "Window load (" + (loadMs === null ? "unavailable" : (loadMs + " ms")) + ")", status: metricStatus(loadMs, 2500, 5000) },
					{ label: "First contentful paint (" + (fcpMs === null ? "unavailable" : (fcpMs + " ms")) + ")", status: metricStatus(fcpMs, 1800, 3000) },
					{ label: "Largest contentful paint (" + (lcpMs === null ? "unavailable" : (lcpMs + " ms")) + ")", status: metricStatus(lcpMs, 2500, 4000) },
					{ label: "Cumulative layout shift (" + (clsScore === null ? "unavailable" : clsScore) + ")", status: clsScore === null ? "warn" : (clsScore <= 0.1 ? "ok" : (clsScore <= 0.25 ? "warn" : "error")) },
					{ label: "Requests (" + (requestCount === null ? "unavailable" : requestCount) + ")", status: requestCount === null ? "warn" : (requestCount <= 80 ? "ok" : (requestCount <= 120 ? "warn" : "error")) },
					{ label: "Transferred bytes (" + (totalTransferKb === null ? "unavailable" : (totalTransferKb + " KB")) + ")", status: totalTransferKb === null ? "warn" : (totalTransferKb <= 2200 ? "ok" : (totalTransferKb <= 3500 ? "warn" : "error")) },
					{ label: "Script bytes (" + (scriptTransferKb === null ? "unavailable" : (scriptTransferKb + " KB")) + ")", status: scriptTransferKb === null ? "warn" : (scriptTransferKb <= 600 ? "ok" : (scriptTransferKb <= 1100 ? "warn" : "error")) },
					{ label: "Image bytes (" + (imageTransferKb === null ? "unavailable" : (imageTransferKb + " KB")) + ")", status: imageTransferKb === null ? "warn" : (imageTransferKb <= 1000 ? "ok" : (imageTransferKb <= 1800 ? "warn" : "error")) },
					{ label: "DOM nodes (" + domNodes + ")", status: domNodes <= 1200 ? "ok" : (domNodes <= 1800 ? "warn" : "error") },
					{ label: "Lazy-loaded images (" + lazyImgs + "/" + imgs.length + ")", status: imgs.length === 0 || lazyImgs >= Math.max(1, imgs.length - 1) ? "ok" : "warn" },
					{ label: "Images with dimensions missing (" + missingDimensions + ")", status: missingDimensions === 0 ? "ok" : (missingDimensions < 3 ? "warn" : "error") },
					{ label: "Embedded heavy media (iframes/videos: " + (iframeCount + videoCount) + ")", status: (iframeCount + videoCount) <= 2 ? "ok" : "warn" },
					{ label: "External script count (" + scriptCount + ")", status: scriptCount <= 18 ? "ok" : (scriptCount <= 25 ? "warn" : "error") }
				];
				serverTimingRows.forEach(function(row){
					vitalsRows.push(row);
				});
				var vitalsHtml = "";
				vitalsRows.forEach(function(row){
					var cls = statusClass(row.status);
					vitalsHtml += "<div class=\"lf-ai-seo__vital-row\"><span class=\"lf-ai-seo__badge lf-ai-seo__badge--" + cls + "\">" + (cls === "ok" ? "GOOD" : (cls === "error" ? "HIGH" : "MED")) + "</span><span>" + escapeHtml(row.label) + "</span></div>";
				});
				if ($seoVitals.length) $seoVitals.html(vitalsHtml);
				var html = "";
				rows.slice(0, 12).forEach(function(row){
					var cls = row.status === "ok" ? "ok" : (row.status === "error" ? "error" : "warn");
					var dot = cls === "ok" ? "●" : (cls === "error" ? "■" : "▲");
					html += "<div class=\"lf-ai-seo__item lf-ai-seo__item--" + cls + "\"><span class=\"lf-ai-seo__item-dot\">" + dot + "</span><span>" + escapeHtml(row.label) + "</span></div>";
				});
				$seoList.html(html);
			}).fail(function(){
				var overallFallback = Math.round((seoScoreClient * 0.58) + (convScore * 0.25) + (perfScore * 0.17));
				overallFallback = Math.max(0, Math.min(100, overallFallback));
				$seoScore.html("Overall: <strong>" + overallFallback + "/100</strong>");
				renderPerfChip(perfScore);
				if ($seoOverallFill.length) $seoOverallFill.css("width", overallFallback + "%");
				$seoList.html("<div class=\"lf-ai-seo__item lf-ai-seo__item--warn\"><span class=\"lf-ai-seo__item-dot\">▲</span><span>Backend SEO snapshot unavailable right now. Showing client-side diagnostics only.</span></div>");
				if ($seoTasks.length) $seoTasks.html("<div class=\"lf-ai-seo__task\"><span class=\"lf-ai-seo__task-priority lf-ai-seo__task-priority--med\">MED</span><span>Reconnect backend SEO snapshot for full guidance; continue fixing on-page warnings first.</span></div>");
				if ($seoSerpUrl.length) $seoSerpUrl.text(window.location.href || "");
				if ($seoSerpTitle.length) $seoSerpTitle.text(title || "(missing title)");
				if ($seoSerpDesc.length) $seoSerpDesc.text(metaDesc || "(missing meta description)");
				if ($seoCoverage.length) $seoCoverage.html("<div class=\"lf-ai-seo__coverage-row\"><span class=\"lf-ai-seo__badge lf-ai-seo__badge--warn\">CHECK</span><span>Set primary/secondary keywords in SEO meta box to enable full keyword coverage mapping.</span></div>");
				if ($seoVitals.length) $seoVitals.html(
					"<div class=\"lf-ai-seo__vital-row\"><span class=\"lf-ai-seo__badge lf-ai-seo__badge--" + ((loadMs !== null && loadMs <= 2500) ? "ok" : ((loadMs !== null && loadMs <= 5000) ? "warn" : "error")) + "\">" + ((loadMs !== null && loadMs <= 2500) ? "GOOD" : ((loadMs !== null && loadMs <= 5000) ? "MED" : "HIGH")) + "</span><span>Window load: " + (loadMs === null ? "unavailable" : (loadMs + " ms")) + "</span></div>" +
					"<div class=\"lf-ai-seo__vital-row\"><span class=\"lf-ai-seo__badge lf-ai-seo__badge--" + ((lcpMs !== null && lcpMs <= 2500) ? "ok" : ((lcpMs !== null && lcpMs <= 4000) ? "warn" : "error")) + "\">" + ((lcpMs !== null && lcpMs <= 2500) ? "GOOD" : ((lcpMs !== null && lcpMs <= 4000) ? "MED" : "HIGH")) + "</span><span>LCP: " + (lcpMs === null ? "unavailable" : (lcpMs + " ms")) + "</span></div>" +
					"<div class=\"lf-ai-seo__vital-row\"><span class=\"lf-ai-seo__badge lf-ai-seo__badge--" + ((requestCount !== null && requestCount <= 80) ? "ok" : ((requestCount !== null && requestCount <= 120) ? "warn" : "error")) + "\">" + ((requestCount !== null && requestCount <= 80) ? "GOOD" : ((requestCount !== null && requestCount <= 120) ? "MED" : "HIGH")) + "</span><span>Requests: " + (requestCount === null ? "unavailable" : requestCount) + "</span></div>" +
					"<div class=\"lf-ai-seo__vital-row\"><span class=\"lf-ai-seo__badge lf-ai-seo__badge--" + ((totalTransferKb !== null && totalTransferKb <= 2200) ? "ok" : ((totalTransferKb !== null && totalTransferKb <= 3500) ? "warn" : "error")) + "\">" + ((totalTransferKb !== null && totalTransferKb <= 2200) ? "GOOD" : ((totalTransferKb !== null && totalTransferKb <= 3500) ? "MED" : "HIGH")) + "</span><span>Transferred bytes: " + (totalTransferKb === null ? "unavailable" : (totalTransferKb + " KB")) + "</span></div>" +
					"<div class=\"lf-ai-seo__vital-row\"><span class=\"lf-ai-seo__badge lf-ai-seo__badge--" + (domNodes <= 1200 ? "ok" : "warn") + "\">" + (domNodes <= 1200 ? "GOOD" : "MED") + "</span><span>DOM nodes: " + domNodes + "</span></div>" +
					"<div class=\"lf-ai-seo__vital-row\"><span class=\"lf-ai-seo__badge lf-ai-seo__badge--" + (missingDimensions === 0 ? "ok" : "warn") + "\">" + (missingDimensions === 0 ? "GOOD" : "MED") + "</span><span>Images missing dimensions: " + missingDimensions + "</span></div>"
				);
			});
		}
		function setStatus(msg, isError) {
			$status.text(msg || "");
			$status.toggleClass("is-error", !!isError);
		}
		var lfInlineLinkSavedRange = null;
		var lfInlineLinkSavedHost = null;
		var lfInlineLinkSavedAnchor = null;
		var lfInlineLinkCache = null;
		var lfInlineLinkCacheLoading = false;
		var lfInlineLinkSelTimer = null;
		function lfHideInlineLinkToolbar() {
			if ($linkRoot.length) {
				$linkRoot.find("[data-lf-ai-inline-link-toolbar]").prop("hidden", true);
			}
		}
		function lfHideInlineLinkPanel() {
			if (!$linkRoot.length) return;
			$linkRoot.find("[data-lf-ai-inline-link-panel]").prop("hidden", true);
			$linkRoot.find("[data-lf-ai-inline-link-backdrop]").prop("hidden", true);
			lfInlineLinkSavedRange = null;
			lfInlineLinkSavedHost = null;
			lfInlineLinkSavedAnchor = null;
		}
		function lfClosestAnchorWithinHost(node, host) {
			if (!node || !host) return null;
			var el = node.nodeType === 3 ? node.parentElement : node;
			while (el && el !== host) {
				if (el.nodeType === 1 && el.tagName && String(el.tagName).toLowerCase() === "a") {
					var href = el.getAttribute ? el.getAttribute("href") : "";
					if (href && String(href).trim() !== "") {
						return el;
					}
				}
				el = el.parentElement;
			}
			return null;
		}
		function lfManagedContentEditableHost() {
			var ae = document.activeElement;
			if (!ae || !ae.isContentEditable) return null;
			if (ae.matches(".lf-benefits__title, .lf-benefits__desc, .lf-service-details__text, .lf-block-hero__card-item-text")) {
				return ae;
			}
			return null;
		}
		function lfAnyInlineLinkHostEl() {
			return inlineActiveEl || lfManagedContentEditableHost();
		}
		function lfPositionInlineLinkToolbar() {
			var host = lfAnyInlineLinkHostEl();
			if (!$linkRoot.length || !host) return;
			var sel = window.getSelection();
			if (!sel.rangeCount || sel.isCollapsed) {
				lfHideInlineLinkToolbar();
				return;
			}
			var range = sel.getRangeAt(0);
			try {
				if (!host.contains(range.commonAncestorContainer)) {
					lfHideInlineLinkToolbar();
					return;
				}
			} catch (errRange) {
				lfHideInlineLinkToolbar();
				return;
			}
			var t = String(sel.toString() || "").trim();
			if (t.length < 2) {
				lfHideInlineLinkToolbar();
				return;
			}
			var rect = range.getBoundingClientRect();
			if (rect.width === 0 && rect.height === 0) {
				lfHideInlineLinkToolbar();
				return;
			}
			var $tb = $linkRoot.find("[data-lf-ai-inline-link-toolbar]");
			var top = rect.bottom + 6;
			var left = rect.left;
			$tb.css({ position: "fixed", top: top + "px", left: Math.max(8, left) + "px" });
			$tb.prop("hidden", false);
		}
		function lfFetchInternalLinkTargets(done) {
			if (lfInlineLinkCache) {
				if (typeof done === "function") done(lfInlineLinkCache);
				return;
			}
			if (lfInlineLinkCacheLoading) return;
			lfInlineLinkCacheLoading = true;
			$.post(lfAiFloating.ajax_url, { action: "lf_ai_internal_link_targets", nonce: lfAiFloating.nonce }).done(function(res){
				lfInlineLinkCacheLoading = false;
				if (res && res.success && res.data && Array.isArray(res.data.items)) {
					lfInlineLinkCache = res.data.items;
				} else {
					lfInlineLinkCache = [];
				}
				if (typeof done === "function") done(lfInlineLinkCache);
			}).fail(function(){
				lfInlineLinkCacheLoading = false;
				lfInlineLinkCache = [];
				if (typeof done === "function") done([]);
			});
		}
		function lfRankInternalLinkItems(snippet, items, searchQuery) {
			var qSel = String(snippet || "").toLowerCase().replace(/\s+/g, " ").trim();
			var qSearch = String(searchQuery || "").toLowerCase().replace(/\s+/g, " ").trim();
			var words = qSel.split(" ").filter(function(w){ return w.length > 2; });
			var scored = items.map(function(it){
				var title = String(it.title || "").toLowerCase();
				var u = String(it.url || "").toLowerCase();
				var s = 0;
				var i;
				for (i = 0; i < words.length; i++) {
					if (title.indexOf(words[i]) !== -1) s += 4;
				}
				if (qSel.length > 2 && title.indexOf(qSel) !== -1) s += 18;
				if (qSearch) {
					if (title.indexOf(qSearch) !== -1) s += 25;
					if (u.indexOf(qSearch) !== -1) s += 15;
					var st = qSearch.split(" ").filter(Boolean);
					for (i = 0; i < st.length; i++) {
						if (title.indexOf(st[i]) !== -1) s += 6;
						if (u.indexOf(st[i]) !== -1) s += 4;
					}
				}
				return { item: it, score: s };
			});
			scored.sort(function(a, b){ return b.score - a.score; });
			return scored.map(function(x){ return x.item; });
		}
		function lfRenderInternalLinkList(query) {
			var $list = $linkRoot.find("[data-lf-ai-inline-link-list]");
			if (!$list.length) return;
			var items = (lfInlineLinkCache || []).filter(function(it){
				return String(it.type || "") !== "lf_faq";
			});
			var q = String(query || "").toLowerCase().trim();
			if (q) {
				var tokens = q.split(/\s+/).filter(function(t){ return t.length > 0; });
				items = items.filter(function(it){
					var hay = (String(it.title || "") + " " + String(it.url || "")).toLowerCase();
					var ok = true;
					var ti;
					for (ti = 0; ti < tokens.length; ti++) {
						if (hay.indexOf(tokens[ti]) === -1) {
							ok = false;
							break;
						}
					}
					return ok;
				});
			}
			var snippet = "";
			if (lfInlineLinkSavedRange) {
				try {
					snippet = lfInlineLinkSavedRange.toString();
				} catch (eSn) {
					snippet = "";
				}
			}
			items = lfRankInternalLinkItems(snippet, items, q);
			var max = 40;
			$list.empty();
			var j;
			for (j = 0; j < items.length && j < max; j++) {
				var it = items[j];
				var typeLabel = String(it.type || "").replace(/_/g, " ");
				var urlDisp = String(it.url || "");
				var $btn = $("<button type=\"button\" class=\"lf-ai-inline-link__row\"></button>");
				$btn.attr("data-lf-ai-inline-link-pick", urlDisp);
				$btn.append($("<span></span>").text(String(it.title || "")));
				$btn.append($("<small></small>").text(typeLabel));
				$btn.append($("<span class=\"lf-ai-inline-link__row-url\"></span>").text(urlDisp));
				$list.append($btn);
			}
			if (!$list.children().length) {
				$list.append($("<p class=\"lf-ai-inline-link__empty\"></p>").text("No matches. Try different words or paste a URL below."));
			}
		}
		function lfOpenInternalLinkPanel() {
			if (!$linkRoot.length) return;
			var host = lfAnyInlineLinkHostEl();
			if (!host) {
				setStatus("Click into a headline, benefit, or checklist line to edit, then add a link.", true);
				return;
			}
			var sel = window.getSelection();
			if (!sel.rangeCount) {
				return;
			}
			var range = sel.getRangeAt(0);
			try {
				if (!host.contains(range.commonAncestorContainer)) {
					setStatus("Selection must be inside the text you are editing.", true);
					return;
				}
			} catch (errO) {
				return;
			}
			var aStart = lfClosestAnchorWithinHost(range.startContainer, host);
			var aEnd = lfClosestAnchorWithinHost(range.endContainer, host);
			lfInlineLinkSavedAnchor = (aStart && aStart === aEnd) ? aStart : null;
			if (range.collapsed && !lfInlineLinkSavedAnchor) {
				setStatus("Select text, then add an internal link.", true);
				return;
			}
			lfInlineLinkSavedRange = range.cloneRange();
			lfInlineLinkSavedHost = host;
			lfFetchInternalLinkTargets(function(){
				$linkRoot.find("[data-lf-ai-inline-link-search]").val("");
				lfRenderInternalLinkList("");
				if (lfInlineLinkSavedAnchor) {
					$linkRoot.find("[data-lf-ai-inline-link-url]").val(String(lfInlineLinkSavedAnchor.getAttribute("href") || "").trim());
					$linkRoot.find("[data-lf-ai-inline-link-newtab]").prop("checked", String(lfInlineLinkSavedAnchor.getAttribute("target") || "").toLowerCase() === "_blank");
					$linkRoot.find("[data-lf-ai-inline-link-unlink]").prop("hidden", false);
				} else {
					$linkRoot.find("[data-lf-ai-inline-link-url]").val("");
					$linkRoot.find("[data-lf-ai-inline-link-newtab]").prop("checked", false);
					$linkRoot.find("[data-lf-ai-inline-link-unlink]").prop("hidden", true);
				}
				$linkRoot.find("[data-lf-ai-inline-link-panel]").prop("hidden", false);
				$linkRoot.find("[data-lf-ai-inline-link-backdrop]").prop("hidden", false);
				lfHideInlineLinkToolbar();
			});
		}
		function lfNormalizeInternalLinkUrl(url) {
			var u = String(url || "").trim();
			if (!u) return "";
			if (!/^https?:\/\//i.test(u) && u.indexOf("/") !== 0) {
				u = "/" + u.replace(/^\/+/, "");
			}
			return u;
		}
		function lfInsertLinkIntoSavedRange(url, newTab) {
			var href = lfNormalizeInternalLinkUrl(url);
			if (!href) return false;
			var host = lfInlineLinkSavedHost || inlineActiveEl || lfManagedContentEditableHost();
			if (!host) {
				setStatus("Select text in the editor, then open Internal link again.", true);
				return false;
			}
			var anchorUpdate = lfInlineLinkSavedAnchor;
			if (anchorUpdate && host.contains(anchorUpdate)) {
				try {
					anchorUpdate.setAttribute("href", href);
					if (newTab) {
						anchorUpdate.setAttribute("target", "_blank");
						anchorUpdate.setAttribute("rel", "noopener noreferrer");
					} else {
						anchorUpdate.removeAttribute("target");
						anchorUpdate.removeAttribute("rel");
					}
				} catch (errA) {
					setStatus("Could not update link.", true);
					return false;
				}
				return true;
			}
			var range = lfInlineLinkSavedRange;
			if (!range || range.collapsed) {
				setStatus("Select text in the editor, then open Internal link again.", true);
				return false;
			}
			var boundary = range.commonAncestorContainer;
			var wrapEl = boundary.nodeType === 3 ? boundary.parentNode : boundary;
			try {
				if (!host.contains(wrapEl)) {
					setStatus("Selection is no longer in the edited text. Close the dialog and try again.", true);
					return false;
				}
			} catch (errC) {
				return false;
			}
			try {
				host.focus();
			} catch (errF) {}
			var frag;
			var linkEl = document.createElement("a");
			linkEl.setAttribute("href", href);
			if (newTab) {
				linkEl.setAttribute("target", "_blank");
				linkEl.setAttribute("rel", "noopener noreferrer");
			}
			try {
				frag = range.extractContents();
				while (frag.firstChild) {
					linkEl.appendChild(frag.firstChild);
				}
				range.insertNode(linkEl);
			} catch (errIns) {
				try {
					host.focus();
					var sel = window.getSelection();
					sel.removeAllRanges();
					sel.addRange(lfInlineLinkSavedRange);
					document.execCommand("createLink", false, href);
					if (newTab && sel.rangeCount) {
						var r = sel.getRangeAt(0);
						var c = r.commonAncestorContainer;
						var el = c.nodeType === 3 ? c.parentElement : c;
						while (el && el !== host) {
							if (el.tagName && String(el.tagName).toLowerCase() === "a") {
								el.setAttribute("target", "_blank");
								el.setAttribute("rel", "noopener noreferrer");
								break;
							}
							el = el.parentElement;
						}
					}
				} catch (errEx) {
					setStatus("Could not add link at this selection. Try a shorter selection.", true);
					return false;
				}
			}
			return true;
		}
		function lfApplyInternalLinkFromUi() {
			if (!$linkRoot.length) return;
			var url = String($linkRoot.find("[data-lf-ai-inline-link-url]").val() || "").trim();
			if (!url) {
				setStatus("Choose a suggestion or enter a URL.", true);
				return;
			}
			var newTab = !!$linkRoot.find("[data-lf-ai-inline-link-newtab]").prop("checked");
			var wasEditingAnchor = !!lfInlineLinkSavedAnchor;
			if (!lfInsertLinkIntoSavedRange(url, newTab)) {
				return;
			}
			var savedHostBeforeClose = lfInlineLinkSavedHost;
			var savedAnchorBeforeClose = lfInlineLinkSavedAnchor;
			lfHideInlineLinkPanel();
			var saveHost = inlineActiveEl || savedHostBeforeClose || lfManagedContentEditableHost();
			if (!saveHost && savedAnchorBeforeClose && savedAnchorBeforeClose.closest) {
				saveHost = savedAnchorBeforeClose.closest(".lf-service-details__text,[data-lf-inline-editable=\"1\"]");
			}
			if (saveHost) {
				var managedWrap = saveHost.closest ? saveHost.closest("[data-lf-section-wrap=\"1\"][data-lf-section-id]") : null;
				if (managedWrap && saveHost.closest(".lf-service-details__checklist")) {
					persistSectionChecklist(managedWrap, saveHost);
					setStatus(wasEditingAnchor ? "Link updated and saved." : "Link inserted and saved.", false);
					return;
				}
				if (managedWrap && String(managedWrap.getAttribute("data-lf-section-type") || "") === "benefits" && saveHost.closest(".lf-benefits__card")) {
					var benefitsGrid = managedWrap.querySelector(".lf-benefits");
					if (benefitsGrid) {
						persistSectionLineItems(managedWrap, "benefits_items", benefitLinesFromGrid(benefitsGrid), "Saving benefits...");
						setStatus(wasEditingAnchor ? "Link updated and saved." : "Link inserted and saved.", false);
						return;
					}
				}
				persistInlineNodeNow(saveHost, wasEditingAnchor ? "Link updated and saved." : "Link inserted and saved.");
				return;
			}
			if (savedAnchorBeforeClose && savedAnchorBeforeClose.closest) {
				var fallbackTextHost = savedAnchorBeforeClose.closest(".lf-service-details__text");
				var fallbackWrap = fallbackTextHost && fallbackTextHost.closest
					? fallbackTextHost.closest("[data-lf-section-wrap=\"1\"][data-lf-section-id]")
					: null;
				if (fallbackWrap) {
					persistSectionChecklist(fallbackWrap, fallbackTextHost);
					setStatus(wasEditingAnchor ? "Link updated and saved." : "Link inserted and saved.", false);
					return;
				}
				var fallbackBenefitsHost = savedAnchorBeforeClose.closest(".lf-benefits__title,.lf-benefits__desc");
				var fallbackBenefitsWrap = fallbackBenefitsHost && fallbackBenefitsHost.closest
					? fallbackBenefitsHost.closest("[data-lf-section-wrap=\"1\"][data-lf-section-id]")
					: null;
				if (fallbackBenefitsWrap && String(fallbackBenefitsWrap.getAttribute("data-lf-section-type") || "") === "benefits") {
					var fallbackGrid = fallbackBenefitsWrap.querySelector(".lf-benefits");
					if (fallbackGrid) {
						persistSectionLineItems(fallbackBenefitsWrap, "benefits_items", benefitLinesFromGrid(fallbackGrid), "Saving benefits...");
						setStatus(wasEditingAnchor ? "Link updated and saved." : "Link inserted and saved.", false);
						return;
					}
				}
			}
			setStatus(wasEditingAnchor ? "Link updated. Save when done editing." : "Link inserted. Click away or press ⌘/Ctrl+Enter to save.", false);
		}
		function lfRemoveLinkFromUi() {
			if (!$linkRoot.length) return;
			var host = lfInlineLinkSavedHost || inlineActiveEl || lfManagedContentEditableHost();
			var anchor = lfInlineLinkSavedAnchor;
			if (!anchor || !host || !host.contains(anchor)) {
				setStatus("Could not remove link. Click into the linked text and try again.", true);
				return;
			}
			try {
				host.focus();
				var parent = anchor.parentNode;
				if (!parent) {
					setStatus("Could not remove link.", true);
					return;
				}
				while (anchor.firstChild) {
					parent.insertBefore(anchor.firstChild, anchor);
				}
				parent.removeChild(anchor);
			} catch (errU) {
				setStatus("Could not remove link.", true);
				return;
			}
			var savedHostBeforeClose = lfInlineLinkSavedHost;
			var savedAnchorBeforeClose = lfInlineLinkSavedAnchor;
			lfHideInlineLinkPanel();
			var saveHost = inlineActiveEl || savedHostBeforeClose || lfManagedContentEditableHost();
			if (!saveHost && savedAnchorBeforeClose && savedAnchorBeforeClose.closest) {
				saveHost = savedAnchorBeforeClose.closest(".lf-service-details__text,[data-lf-inline-editable=\"1\"]");
			}
			if (saveHost) {
				var managedWrap = saveHost.closest ? saveHost.closest("[data-lf-section-wrap=\"1\"][data-lf-section-id]") : null;
				if (managedWrap && saveHost.closest(".lf-service-details__checklist")) {
					persistSectionChecklist(managedWrap, saveHost);
					setStatus("Link removed and saved.", false);
					return;
				}
				if (managedWrap && String(managedWrap.getAttribute("data-lf-section-type") || "") === "benefits" && saveHost.closest(".lf-benefits__card")) {
					var benefitsGrid = managedWrap.querySelector(".lf-benefits");
					if (benefitsGrid) {
						persistSectionLineItems(managedWrap, "benefits_items", benefitLinesFromGrid(benefitsGrid), "Saving benefits...");
						setStatus("Link removed and saved.", false);
						return;
					}
				}
				persistInlineNodeNow(saveHost, "Link removed and saved.");
				return;
			}
			if (savedAnchorBeforeClose && savedAnchorBeforeClose.closest) {
				var fallbackTextHost = savedAnchorBeforeClose.closest(".lf-service-details__text");
				var fallbackWrap = fallbackTextHost && fallbackTextHost.closest
					? fallbackTextHost.closest("[data-lf-section-wrap=\"1\"][data-lf-section-id]")
					: null;
				if (fallbackWrap) {
					persistSectionChecklist(fallbackWrap, fallbackTextHost);
					setStatus("Link removed and saved.", false);
					return;
				}
				var fallbackBenefitsHost = savedAnchorBeforeClose.closest(".lf-benefits__title,.lf-benefits__desc");
				var fallbackBenefitsWrap = fallbackBenefitsHost && fallbackBenefitsHost.closest
					? fallbackBenefitsHost.closest("[data-lf-section-wrap=\"1\"][data-lf-section-id]")
					: null;
				if (fallbackBenefitsWrap && String(fallbackBenefitsWrap.getAttribute("data-lf-section-type") || "") === "benefits") {
					var fallbackGrid = fallbackBenefitsWrap.querySelector(".lf-benefits");
					if (fallbackGrid) {
						persistSectionLineItems(fallbackBenefitsWrap, "benefits_items", benefitLinesFromGrid(fallbackGrid), "Saving benefits...");
						setStatus("Link removed and saved.", false);
						return;
					}
				}
			}
			setStatus("Link removed. Save when done editing.", false);
		}
		function lfInitInlineLinkUi() {
			if (!$linkRoot.length) return;
			$linkRoot.find("[data-lf-ai-inline-link-toolbar]").on("mousedown", function(e){
				e.preventDefault();
			});
			$linkRoot.on("mousedown", "[data-lf-ai-inline-link-apply], [data-lf-ai-inline-link-pick], [data-lf-ai-inline-link-close], [data-lf-ai-inline-link-unlink]", function(e){
				e.preventDefault();
			});
			$linkRoot.find("[data-lf-ai-inline-link-open]").on("click", function(e){
				e.preventDefault();
				lfOpenInternalLinkPanel();
			});
			$linkRoot.find("[data-lf-ai-inline-link-close], [data-lf-ai-inline-link-backdrop]").on("click", function(e){
				e.preventDefault();
				lfHideInlineLinkPanel();
			});
			$linkRoot.find("[data-lf-ai-inline-link-apply]").on("click", function(e){
				e.preventDefault();
				lfApplyInternalLinkFromUi();
			});
			$linkRoot.find("[data-lf-ai-inline-link-unlink]").on("click", function(e){
				e.preventDefault();
				lfRemoveLinkFromUi();
			});
			$linkRoot.on("click", "[data-lf-ai-inline-link-pick]", function(e){
				e.preventDefault();
				var u = $(this).attr("data-lf-ai-inline-link-pick") || "";
				$linkRoot.find("[data-lf-ai-inline-link-url]").val(u);
				try {
					$linkRoot.find("[data-lf-ai-inline-link-url]").trigger("focus");
				} catch (errFoc) {}
			});
			$linkRoot.find("[data-lf-ai-inline-link-search]").on("input", function(){
				lfRenderInternalLinkList($(this).val() || "");
			});
		}
		lfInitInlineLinkUi();
		document.addEventListener("selectionchange", function(){
			clearTimeout(lfInlineLinkSelTimer);
			lfInlineLinkSelTimer = setTimeout(function(){
				if (!lfAnyInlineLinkHostEl()) {
					lfHideInlineLinkToolbar();
					return;
				}
				if ($linkRoot.length && !$linkRoot.find("[data-lf-ai-inline-link-panel]").prop("hidden")) {
					return;
				}
				lfPositionInlineLinkToolbar();
			}, 90);
		});
		function updateLauncherOffsets() {
			if (!$seoRoot.length || !$toggle.length) return;
			var narrow = false;
			try { narrow = !!(window.matchMedia && window.matchMedia("(max-width: 782px)").matches); } catch (e) {}
			if (narrow) {
				$seoRoot.css("right", "12px");
				return;
			}
			var aiWidth = Math.ceil($toggle.outerWidth() || 0);
			var aiRight = 20;
			var seoRight = aiRight + aiWidth + launcherGapPx;
			$seoRoot.css("right", Math.max(130, seoRight) + "px");
		}
		function setAiOpen(open) {
			$panel.prop("hidden", !open);
			$toggle.attr("aria-expanded", open ? "true" : "false");
			if (open && $seoPanel.length) {
				$seoPanel.prop("hidden", true);
				$seoToggle.attr("aria-expanded", "false");
				try { window.localStorage.setItem(seoStateKey, "closed"); } catch (e) {}
			}
			if (!open) {
				lfHideInlineLinkToolbar();
				lfHideInlineLinkPanel();
				saveInlineEdit();
			}
			try { window.localStorage.setItem(stateKey, open ? "open" : "closed"); } catch (e) {}
		}
		function setSeoOpen(open) {
			if (!$seoPanel.length || !$seoToggle.length) return;
			$seoPanel.prop("hidden", !open);
			$seoToggle.attr("aria-expanded", open ? "true" : "false");
			if (open) {
				setConfirmOpen(false);
				$panel.prop("hidden", true);
				$toggle.attr("aria-expanded", "false");
				lfHideInlineLinkToolbar();
				lfHideInlineLinkPanel();
				saveInlineEdit();
				try { window.localStorage.setItem(stateKey, "closed"); } catch (e) {}
				renderSeoSnapshot();
			}
			try { window.localStorage.setItem(seoStateKey, open ? "open" : "closed"); } catch (e) {}
		}
		function setEditorToggleUi() {
			if (!$btnEditorToggle || !$btnEditorToggle.length) return;
			$btnEditorToggle.toggleClass("is-active", editingEnabled);
			$btnEditorToggle.text(editingEnabled ? "✎" : "👁");
			$btnEditorToggle.attr("title", editingEnabled ? "Editor ON (click for live mode)" : "Live mode ON (click for editor)");
		}
		function clearEditorUi() {
			try { document.documentElement.classList.remove("lf-ai-editor-on"); } catch (eCls) {}
			lfHideInlineLinkToolbar();
			lfHideInlineLinkPanel();
			saveInlineEdit();
			cancelInlineEdit(false);
			selectedSectionWrap = null;
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap) return;
				wrap.classList.remove("lf-ai-section-active");
				wrap.setAttribute("draggable", "false");
				wrap.onmousedown = null;
				wrap.ondragstart = null;
				wrap.ondragover = null;
				wrap.ondrop = null;
				wrap.ondragend = null;
			});
			Array.prototype.slice.call(document.querySelectorAll("[data-lf-inline-editable=\"1\"],[data-lf-inline-selector]")).forEach(function(node){
				node.removeAttribute("data-lf-inline-editable");
				node.removeAttribute("data-lf-inline-selector");
				node.removeAttribute("data-lf-inline-source-selector");
				node.removeAttribute("data-lf-inline-section-id");
				node.removeAttribute("data-lf-inline-field-key");
			});
			Array.prototype.slice.call(document.querySelectorAll("[data-lf-inline-image=\"1\"],[data-lf-inline-image-selector]")).forEach(function(node){
				node.removeAttribute("data-lf-inline-image");
				node.removeAttribute("data-lf-inline-image-selector");
			});
			Array.prototype.slice.call(document.querySelectorAll(".lf-ai-section-controls,.lf-ai-section-insert,.lf-ai-benefits-grid-actions,[data-lf-ai-benefits-grid-actions=\"1\"],[data-lf-ai-service-intro-actions=\"1\"],[data-lf-ai-hero-pills-controls=\"1\"],[data-lf-ai-hero-proof-controls=\"1\"],[data-lf-ai-hero-trust-strip-controls=\"1\"],[data-lf-ai-trust-pill-controls=\"1\"],[data-lf-ai-process-controls=\"1\"],[data-lf-ai-checklist-controls=\"1\"],[data-lf-ai-micro-controls=\"1\"],[data-lf-ai-faq-controls=\"1\"],[data-lf-ai-list-remove=\"1\"],[data-lf-ai-checklist-remove=\"1\"],[data-lf-ai-micro-remove=\"1\"],[data-lf-ai-benefit-remove=\"1\"],[data-lf-ai-service-intro-remove=\"1\"],[data-lf-ai-hero-pill-remove=\"1\"],[data-lf-ai-media-add=\"1\"]")).forEach(function(node){
				if (node && node.parentNode) node.parentNode.removeChild(node);
			});
			Array.prototype.slice.call(document.querySelectorAll(".lf-service-details__text")).forEach(function(node){
				if (!node) return;
				node.onmousedown = null;
				node.onclick = null;
				node.onblur = null;
				node.onkeydown = null;
				node.removeAttribute("contenteditable");
				node.removeAttribute("spellcheck");
				node.removeAttribute("data-lf-ai-editing");
				node.removeAttribute("data-lf-ai-original-text");
			});
			Array.prototype.slice.call(document.querySelectorAll(".lf-block-hero__card-item-text")).forEach(function(node){
				if (!node) return;
				node.onmousedown = null;
				node.onclick = null;
				node.onblur = null;
				node.onkeydown = null;
				node.removeAttribute("contenteditable");
				node.removeAttribute("spellcheck");
				node.removeAttribute("data-lf-ai-editing");
				node.removeAttribute("data-lf-ai-original-text");
			});
			Array.prototype.slice.call(document.querySelectorAll(".lf-benefits__icon[data-lf-benefit-icon-index]")).forEach(function(node){
				if (!node) return;
				node.onclick = null;
				node.style.cursor = "";
			});
			Array.prototype.slice.call(document.querySelectorAll(".lf-process__step,.lf-block-faq-accordion__item[data-lf-faq-id],.lf-ai-column-draggable")).forEach(function(node){
				if (!node) return;
				node.setAttribute("draggable", "false");
				node.ondragstart = null;
				node.ondragover = null;
				node.ondrop = null;
				node.ondragend = null;
				node.ondblclick = null;
				node.classList.remove("is-dragging", "lf-ai-process-step", "lf-ai-column-draggable");
			});
			Array.prototype.slice.call(document.querySelectorAll(".lf-service-details--media")).forEach(function(node){
				if (!node) return;
				node.ondragover = null;
				node.ondrop = null;
			});
			if (sectionRailEl && sectionRailEl.parentNode) {
				sectionRailEl.parentNode.removeChild(sectionRailEl);
			}
			sectionRailEl = null;
			if (iconPickerEl) {
				iconPickerEl.hidden = true;
			}
			if (mediaPickerEl) {
				mediaPickerEl.hidden = true;
			}
			if (faqPickerEl) {
				faqPickerEl.hidden = true;
			}
			faqPickerWrap = null;
			faqPickerList = null;
			closeSectionBgPicker();
			closeSectionAlignPicker();
			closeBenefitsCtaPicker();
			closeSectionInsertPicker();
			closeSectionGridPicker();
			closeServicePicker();
			closeHeroSettingsPicker();
			refreshAiScopeBanner();
		}
		function closeIconPicker() {
			if (!iconPickerEl) return;
			iconPickerEl.hidden = true;
			iconPickerOnSelect = null;
			try { if (iconPickerSearchEl) iconPickerSearchEl.value = ""; } catch (e) {}
		}
		function renderIconPickerList(query) {
			if (!iconPickerListEl) return;
			var q = String(query || "").trim().toLowerCase();
			var rows = iconSlugs.filter(function(slug){
				var s = String(slug || "").toLowerCase();
				return !q || s.indexOf(q) !== -1;
			});
			iconPickerListEl.innerHTML = "";
			var clearBtn = document.createElement("button");
			clearBtn.type = "button";
			clearBtn.className = "lf-ai-icon-picker__item" + (iconPickerCurrentSlug === "" ? " is-active" : "");
			clearBtn.textContent = "Use default icon";
			clearBtn.addEventListener("click", function(){
				if (typeof iconPickerOnSelect === "function") iconPickerOnSelect("");
				closeIconPicker();
			});
			iconPickerListEl.appendChild(clearBtn);
			if (!rows.length) {
				var empty = document.createElement("div");
				empty.className = "lf-ai-icon-picker__empty";
				empty.textContent = "No matching icons in library.";
				iconPickerListEl.appendChild(empty);
				return;
			}
			rows.forEach(function(slug){
				var btn = document.createElement("button");
				btn.type = "button";
				btn.className = "lf-ai-icon-picker__item" + (String(slug) === iconPickerCurrentSlug ? " is-active" : "");
				btn.textContent = String(slug);
				btn.addEventListener("click", function(){
					if (typeof iconPickerOnSelect === "function") iconPickerOnSelect(String(slug));
					closeIconPicker();
				});
				iconPickerListEl.appendChild(btn);
			});
		}
		function ensureIconPicker() {
			if (iconPickerEl) return iconPickerEl;
			iconPickerEl = document.createElement("div");
			iconPickerEl.className = "lf-ai-icon-picker lf-ai-inline-editor-ignore";
			iconPickerEl.hidden = true;
			iconPickerEl.innerHTML = "<div class=\"lf-ai-icon-picker__card\"><div class=\"lf-ai-icon-picker__head\"><div class=\"lf-ai-icon-picker__title\">Choose an icon</div><button type=\"button\" class=\"lf-ai-icon-picker__close\" data-lf-ai-icon-picker-close aria-label=\"Close icon picker\">×</button></div><input type=\"text\" class=\"lf-ai-icon-picker__search\" data-lf-ai-icon-picker-search placeholder=\"Search icon slug...\" /><div class=\"lf-ai-icon-picker__list\" data-lf-ai-icon-picker-list></div></div>";
			iconPickerSearchEl = iconPickerEl.querySelector("[data-lf-ai-icon-picker-search]");
			iconPickerListEl = iconPickerEl.querySelector("[data-lf-ai-icon-picker-list]");
			var closeBtn = iconPickerEl.querySelector("[data-lf-ai-icon-picker-close]");
			if (closeBtn) {
				closeBtn.addEventListener("click", function(e){
					e.preventDefault();
					closeIconPicker();
				});
			}
			if (iconPickerSearchEl) {
				iconPickerSearchEl.addEventListener("input", function(){
					renderIconPickerList(iconPickerSearchEl.value);
				});
			}
			iconPickerEl.addEventListener("click", function(e){
				if (e.target === iconPickerEl) closeIconPicker();
			});
			document.body.appendChild(iconPickerEl);
			return iconPickerEl;
		}
		function openIconPicker(currentSlug, onSelect) {
			if (!iconSlugs.length) {
				setStatus("Icon library is unavailable on this screen.", true);
				return;
			}
			ensureIconPicker();
			iconPickerCurrentSlug = String(currentSlug || "");
			iconPickerOnSelect = typeof onSelect === "function" ? onSelect : null;
			if (!iconPickerEl) return;
			iconPickerEl.hidden = false;
			if (iconPickerSearchEl) iconPickerSearchEl.value = "";
			renderIconPickerList("");
			try { if (iconPickerSearchEl) iconPickerSearchEl.focus(); } catch (e) {}
		}
		function sectionSupportsMediaEditor(sectionType) {
			var type = String(sectionType || "");
			return ["service_details", "content_image", "content_image_a", "image_content", "image_content_b", "content_image_c"].indexOf(type) !== -1;
		}
		function persistSectionMediaSettings(wrap, payload) {
			if (!wrap || !payload) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
			if (!sectionId || !sectionSupportsMediaEditor(sectionType)) return;
			setStatus("Saving section media...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_update_section_media",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				section_id: sectionId,
				mode: String(payload.mode || "image"),
				image_id: parseInt(String(payload.image_id || "0"), 10) || 0,
				video_url: String(payload.video_url || ""),
				embed_code: String(payload.embed_code || "")
			}).done(function(res){
				if (res && res.success) {
					setStatus((res.data && res.data.message) ? res.data.message : "Section media updated.", false);
					if (res.data && res.data.reload) {
						window.location.reload();
					}
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "Section media update failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Section media update failed.";
				setStatus(msg, true);
			});
		}
		function closeMediaPicker() {
			if (!mediaPickerEl) return;
			mediaPickerEl.hidden = true;
			mediaPickerWrap = null;
		}
		function openMediaLibraryForWrap(wrap) {
			if (!wrap || !(window.wp && wp.media)) {
				setStatus("Media Library is unavailable on this screen.", true);
				return;
			}
			var frame = wp.media({
				title: "Select section media image",
				button: { text: "Use image" },
				library: { type: "image" },
				multiple: false
			});
			frame.on("select", function(){
				var selection = frame.state().get("selection");
				var attachment = selection && selection.first ? selection.first().toJSON() : null;
				var attachmentId = parseInt(String(attachment && attachment.id ? attachment.id : "0"), 10);
				if (!attachmentId) return;
				persistSectionMediaSettings(wrap, { mode: "image", image_id: attachmentId, video_url: "", embed_code: "" });
				closeMediaPicker();
			});
			frame.open();
		}
		function ensureMediaPicker() {
			if (mediaPickerEl) return mediaPickerEl;
			mediaPickerEl = document.createElement("div");
			mediaPickerEl.className = "lf-ai-media-picker lf-ai-inline-editor-ignore";
			mediaPickerEl.hidden = true;
			mediaPickerEl.innerHTML = "<div class=\"lf-ai-media-picker__card\"><div class=\"lf-ai-media-picker__head\"><div class=\"lf-ai-media-picker__title\">Section media</div><button type=\"button\" class=\"lf-ai-media-picker__close\" data-lf-ai-media-picker-close aria-label=\"Close media picker\">×</button></div><div class=\"lf-ai-media-picker__body\"><div class=\"lf-ai-media-picker__actions\"><button type=\"button\" data-lf-ai-media-image>+ Add / replace image</button><button type=\"button\" data-lf-ai-media-video-url>Video URL (YouTube/Vimeo/MP4)</button><button type=\"button\" data-lf-ai-media-embed>Embed code (iframe)</button><button type=\"button\" data-lf-ai-media-none>Remove media</button></div><div class=\"lf-ai-media-picker__hint\">Tip: choose Video URL for YouTube links, or Embed code for a custom iframe.</div></div></div>";
			var closeBtn = mediaPickerEl.querySelector("[data-lf-ai-media-picker-close]");
			var imageBtn = mediaPickerEl.querySelector("[data-lf-ai-media-image]");
			var videoBtn = mediaPickerEl.querySelector("[data-lf-ai-media-video-url]");
			var embedBtn = mediaPickerEl.querySelector("[data-lf-ai-media-embed]");
			var noneBtn = mediaPickerEl.querySelector("[data-lf-ai-media-none]");
			if (closeBtn) {
				closeBtn.addEventListener("click", function(e){
					e.preventDefault();
					closeMediaPicker();
				});
			}
			if (imageBtn) {
				imageBtn.addEventListener("click", function(e){
					e.preventDefault();
					if (!mediaPickerWrap) return;
					openMediaLibraryForWrap(mediaPickerWrap);
				});
			}
			if (videoBtn) {
				videoBtn.addEventListener("click", function(e){
					e.preventDefault();
					if (!mediaPickerWrap) return;
					var url = "";
					try {
						url = String(window.prompt("Enter a video URL (YouTube/Vimeo/MP4):", "") || "").trim();
					} catch (err) {
						url = "";
					}
					if (!url) return;
					persistSectionMediaSettings(mediaPickerWrap, { mode: "video", image_id: 0, video_url: url, embed_code: "" });
					closeMediaPicker();
				});
			}
			if (embedBtn) {
				embedBtn.addEventListener("click", function(e){
					e.preventDefault();
					if (!mediaPickerWrap) return;
					var code = "";
					try {
						code = String(window.prompt("Paste embed code (iframe):", "") || "").trim();
					} catch (err) {
						code = "";
					}
					if (!code) return;
					persistSectionMediaSettings(mediaPickerWrap, { mode: "video", image_id: 0, video_url: "", embed_code: code });
					closeMediaPicker();
				});
			}
			if (noneBtn) {
				noneBtn.addEventListener("click", function(e){
					e.preventDefault();
					if (!mediaPickerWrap) return;
					persistSectionMediaSettings(mediaPickerWrap, { mode: "none", image_id: 0, video_url: "", embed_code: "" });
					closeMediaPicker();
				});
			}
			mediaPickerEl.addEventListener("click", function(e){
				if (e.target === mediaPickerEl) closeMediaPicker();
			});
			document.body.appendChild(mediaPickerEl);
			return mediaPickerEl;
		}
		function openSectionMediaPicker(wrap) {
			if (!wrap) return;
			ensureMediaPicker();
			mediaPickerWrap = wrap;
			mediaPickerEl.hidden = false;
		}
		function buildSectionMediaEditors() {
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
				if (!sectionSupportsMediaEditor(sectionType)) return;
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-media-add=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				var mediaHost = wrap.querySelector(".lf-service-details__media,.lf-media-section__media");
				var container = mediaHost;
				if (!container) {
					container = wrap.querySelector(".lf-service-details__content,.lf-media-section__content");
					if (!container) return;
				}
				var addWrap = document.createElement("div");
				addWrap.className = "lf-ai-media-add-wrap lf-ai-inline-editor-ignore";
				addWrap.setAttribute("data-lf-ai-media-add", "1");
				var addBtn = document.createElement("button");
				addBtn.type = "button";
				addBtn.className = "lf-ai-media-add";
				addBtn.textContent = mediaHost ? "+ Media" : "+ Add media";
				addBtn.setAttribute("title", "Add or replace image/video");
				addBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					openSectionMediaPicker(wrap);
				});
				addWrap.appendChild(addBtn);
				container.appendChild(addWrap);
			});
		}
		function buildEditorUi() {
			buildInlineTargets();
			buildInlineImageTargets();
			buildSectionTargets();
			buildSectionControls();
			buildSectionInsertZones();
			buildSectionButtonEditors();
			buildHeroPillsControls();
			buildHeroProofChecklistControls();
			buildHeroTrustStripControls();
			buildTrustBadgePillsControls();
			buildChecklistControls();
			buildProofBadgeControls();
			buildServiceDetailsMicroControls();
			buildProcessStepControls();
			buildSectionColumnSwapTargets();
			buildSectionMediaEditors();
			buildFaqReorderControls();
			buildBenefitsReorderControls();
			buildBenefitsTextEditors();
			buildServiceIntroReorderControls();
			buildServiceIntroCardEditors();
			buildBenefitsGridChrome();
			buildBenefitsIconEditors();
			refreshSectionRail();
			renderSeoSnapshot();
			var firstWrap = collectSectionWrappers()[0] || null;
			if (firstWrap) {
				setSelectedSection(firstWrap);
			}
		}
		function editorSurfaceStatusMessage() {
			var n = collectSectionWrappers().length;
			if (n > 0) {
				return "Editor on: use controls on each section (top-right). Drag sections to reorder, click text to edit, images to swap.";
			}
			var ctx = String(activeContextType || "");
			return "Editor on, but this view has no LeadsForward section blocks (no data-lf-section-wrap). Open the front page or a page that uses the theme page builder for section controls; other templates only get inline text/image targets where available.";
		}
		function setEditorEnabled(enabled) {
			editingEnabled = !!enabled;
			setEditorToggleUi();
			try { window.localStorage.setItem(editorModeKey, editingEnabled ? "on" : "off"); } catch (e) {}
			if (!editingEnabled) {
				clearEditorUi();
				setStatus("Live mode enabled. Editing is off.", false);
				renderSeoSnapshot();
				return;
			}
			try { document.documentElement.classList.add("lf-ai-editor-on"); } catch (eCls2) {}
			try {
				if ($onboarding.length && !window.localStorage.getItem("lfAiOnboardingDismissed")) {
					$onboarding.prop("hidden", false);
				}
			} catch (eOn) {}
			buildEditorUi();
			setStatus(editorSurfaceStatusMessage(), false);
			renderSeoSnapshot();
		}
		function isDragBlockedTarget(target) {
			if (!target) return false;
			if (target.closest(".lf-ai-float,.lf-ai-seo-float")) return true;
			if (target.closest("a,button,input,textarea,select,label,[contenteditable=\"true\"],.lf-ai-inline-editor-ignore")) return true;
			return false;
		}
		function inlineNodeEligible(node) {
			if (!node || !node.textContent) return false;
			if (node.closest(".lf-ai-float,.lf-ai-seo-float")) return false;
			if (node.closest("nav, footer, [aria-hidden=\"true\"]")) return false;
			if (node.closest(".site-header, .site-footer, #masthead, #colophon")) return false;
			if (node.closest("button, a, label, script, style, noscript")) return false;
			// Managed lists/pills use dedicated controls; block generic inline on containers except linkable text spans.
			if (node.closest(".lf-hero-chips,.lf-trust-bar__badges,.lf-process,.lf-block-faq-accordion__list,.lf-related-links,.lf-cpt-driven-links")) return false;
			if (node.closest(".lf-block-hero__card-list") && !node.classList.contains("lf-block-hero__card-item-text")) return false;
			if (node.closest(".lf-service-details__checklist") && !node.classList.contains("lf-service-details__text")) return false;
			if (node.closest(".lf-service-details__micro")) return false;
			// Map + NAP: service area names are global CPT permalinks — not editable inline.
			if (node.closest(".lf-block-map-nap__areas-list,.lf-block-map-nap__areas-item,.lf-block-map-nap__areas-link")) return false;
			// Reviews content is source-of-truth data; do not edit testimonial copy inline.
			if (node.closest(".lf-block-trust-reviews__item,.lf-block-trust-reviews__summary")) return false;
			var tag = node.tagName ? node.tagName.toLowerCase() : "";
			var isHeading = /^h[1-6]$/.test(tag);
			// CPT / archive card titles (fixed); not section headings like lf-block-service-intro__title.
			if (node.matches(".lf-block-service-intro__card-title,.lf-block-service-grid__card-title,.lf-block-service-areas__card-title")) return false;
			if (node.closest(".lf-blog-hero__title,.lf-post-card__title,.entry-title")) return false;
			if (isHeading) {
				var entity = node.closest("article,.hentry,.type-post,.type-page,.type-lf_project,.type-lf_service,.type-lf_service_area,.type-lf_faq,.type-lf_process_step");
				if (entity) {
					var lockedTitle = entity.querySelector(".entry-title,h1.entry-title,h1.page-title,h1.post-title,h1.wp-block-post-title,[data-lf-seo-title=\"1\"]");
					if (lockedTitle && lockedTitle === node) return false;
					if (node.closest(".entry-header")) return false;
				}
			}
			var text = String(node.textContent || "").trim();
			return text !== "";
		}
		function nodeSegment(node) {
			var parent = node.parentElement;
			if (!parent) {
				return node.tagName.toLowerCase();
			}
			var tag = node.tagName.toLowerCase();
			var index = 1;
			var sibling = node.previousElementSibling;
			while (sibling) {
				if (sibling.tagName && sibling.tagName.toLowerCase() === tag) {
					index++;
				}
				sibling = sibling.previousElementSibling;
			}
			return tag + ":nth-of-type(" + index + ")";
		}
		function buildInlineSelector(node) {
			var root = node.closest("main, .site-main, #primary, .site-content");
			if (!root) {
				root = document.querySelector("main") || document.body;
			}
			var segments = [];
			var cursor = node;
			while (cursor && cursor !== root && cursor.nodeType === 1) {
				segments.unshift(nodeSegment(cursor));
				cursor = cursor.parentElement;
			}
			var rootPrefix = "main";
			if (root && root.id) {
				rootPrefix = "#" + root.id;
			} else if (root && root.classList && root.classList.length) {
				rootPrefix = root.tagName.toLowerCase() + "." + root.classList[0];
			}
			return rootPrefix + " > " + segments.join(" > ");
		}
		function buildInlineTargets() {
			var nodes = document.querySelectorAll(inlineCandidateSelector);
			if (!nodes || !nodes.length) {
				return;
			}
			nodes.forEach(function(node){
				if (!inlineNodeEligible(node)) return;
				var selector = buildInlineSelector(node);
				if (!selector) return;
				var sourceSelector = "";
				var serviceBody = node.closest(".lf-service-details__body");
				if (serviceBody) {
					sourceSelector = buildInlineSelector(serviceBody);
					if (sourceSelector) {
						selector = sourceSelector;
					}
				}
				node.setAttribute("data-lf-inline-editable", "1");
				node.setAttribute("data-lf-inline-selector", selector);
				if (sourceSelector) {
					node.setAttribute("data-lf-inline-source-selector", sourceSelector);
				} else {
					node.removeAttribute("data-lf-inline-source-selector");
				}
				var sectionWrap = node.closest("[data-lf-section-wrap=\"1\"][data-lf-section-id]");
				var sectionId = sectionWrap ? String(sectionWrap.getAttribute("data-lf-section-id") || "") : "";
				if (sectionId) {
					node.setAttribute("data-lf-inline-section-id", sectionId);
				} else {
					node.removeAttribute("data-lf-inline-section-id");
				}
				var cls = String(node.className || "");
				if (serviceBody) {
					node.setAttribute("data-lf-inline-field-key", "service_details_body");
				} else if (/\blf-hero-split__subtitle\b/.test(cls) || /\blf-hero-basic__subtitle\b/.test(cls)) {
					node.setAttribute("data-lf-inline-field-key", "hero_subheadline");
				} else {
					node.removeAttribute("data-lf-inline-field-key");
				}
			});
		}
		function inlineImageEligible(img) {
			if (!img || !img.getAttribute) return false;
			if (img.closest(".lf-ai-float,.lf-ai-seo-float")) return false;
			if (img.closest("nav, footer, .site-header, .site-footer, #masthead, #colophon")) return false;
			var src = String(img.getAttribute("src") || "").trim();
			if (src === "") return false;
			var width = img.naturalWidth || img.clientWidth || 0;
			var height = img.naturalHeight || img.clientHeight || 0;
			if (width > 0 && height > 0 && (width < 40 || height < 40)) {
				return false;
			}
			return true;
		}
		function buildInlineImageTargets() {
			var nodes = document.querySelectorAll(inlineImageCandidateSelector);
			if (!nodes || !nodes.length) return;
			nodes.forEach(function(img){
				if (!inlineImageEligible(img)) return;
				var selector = buildInlineSelector(img);
				if (!selector) return;
				img.setAttribute("data-lf-inline-image", "1");
				img.setAttribute("data-lf-inline-image-selector", selector);
			});
		}
		function saveInlineImageOverride(img, attachment) {
			var selector = String(img.getAttribute("data-lf-inline-image-selector") || "");
			if (!selector) {
				setStatus("Invalid image target.", true);
				return;
			}
			var attachmentId = parseInt(String(attachment && attachment.id ? attachment.id : "0"), 10);
			var url = "";
			if (attachment && attachment.sizes && attachment.sizes.large && attachment.sizes.large.url) {
				url = String(attachment.sizes.large.url);
			} else if (attachment && attachment.sizes && attachment.sizes.full && attachment.sizes.full.url) {
				url = String(attachment.sizes.full.url);
			} else if (attachment && attachment.url) {
				url = String(attachment.url);
			}
			if (!attachmentId || !url) {
				setStatus("Unable to use selected image.", true);
				return;
			}
			var alt = String((attachment && attachment.alt) ? attachment.alt : "");
			setStatus("Saving image replacement...", false);
			img.setAttribute("data-lf-inline-image-active", "1");
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_inline_image_save",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				selector: selector,
				attachment_id: attachmentId,
				image_url: url,
				image_alt: alt
			}).done(function(res){
				if (res && res.success) {
					img.setAttribute("src", url);
					img.removeAttribute("srcset");
					img.setAttribute("alt", alt);
					setStatus("Image replaced. Use ↶ / ↷ to undo/redo.", false);
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "Image replace failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Image replace failed.";
				setStatus(msg, true);
			}).always(function(){
				img.removeAttribute("data-lf-inline-image-active");
			});
		}
		function beginInlineImageReplace(img) {
			if (!img || inlineIsSaving) return;
			if (!(window.wp && wp.media)) {
				setStatus("Media Library is unavailable on this screen.", true);
				return;
			}
			if (mediaFrame) {
				mediaFrame.off("select");
			}
			mediaFrame = wp.media({
				title: "Replace image",
				button: { text: "Use this image" },
				library: { type: "image" },
				multiple: false
			});
			mediaFrame.on("select", function(){
				var selection = mediaFrame.state().get("selection");
				var attachment = selection && selection.first ? selection.first().toJSON() : null;
				if (!attachment) return;
				saveInlineImageOverride(img, attachment);
			});
			mediaFrame.open();
		}
		function collectSectionWrappers() {
			return Array.prototype.slice.call(document.querySelectorAll("[data-lf-section-wrap=\"1\"][data-lf-section-id]"));
		}
		function sectionLabelForWrap(wrap) {
			if (!wrap) return "Section";
			var heading = wrap.querySelector("h1,h2,h3,.lf-section__title,.lf-media-section__title");
			var text = heading ? String(heading.textContent || "").trim() : "";
			if (!text) {
				text = String(wrap.getAttribute("data-lf-section-type") || "section");
			}
			return text.length > 42 ? (text.slice(0, 39) + "...") : text;
		}
		function refreshAiScopeBanner() {
			var $scope = $root.find("[data-lf-ai-section-scope]");
			var $scopeVal = $root.find("[data-lf-ai-section-scope-value]");
			if (!$scope.length || !$scopeVal.length) return;
			if (selectedSectionWrap && editingEnabled) {
				var label = sectionLabelForWrap(selectedSectionWrap);
				var sid = String(selectedSectionWrap.getAttribute("data-lf-section-id") || "");
				var stype = String(selectedSectionWrap.getAttribute("data-lf-section-type") || "");
				$scope.prop("hidden", false);
				$scopeVal.text(label + " (" + (stype || sid) + ")");
			} else {
				$scope.prop("hidden", true);
				$scopeVal.text("");
			}
		}
		function setSelectedSection(wrap) {
			if (!editingEnabled) {
				selectedSectionWrap = null;
				refreshAiScopeBanner();
				return;
			}
			collectSectionWrappers().forEach(function(node){
				node.classList.remove("lf-ai-section-active");
			});
			selectedSectionWrap = wrap || null;
			if (selectedSectionWrap) {
				selectedSectionWrap.classList.add("lf-ai-section-active");
				// Self-heal insert chrome if the DOM was rebuilt without a full editor refresh.
				buildSectionInsertZones();
				buildServiceDetailsMicroControls();
				buildServiceIntroCardEditors();
				buildBenefitsGridChrome();
				// Self-heal list/pill controls in case a row lost its remove button.
				buildHeroPillsControls();
				buildHeroProofChecklistControls();
				buildHeroTrustStripControls();
				buildTrustBadgePillsControls();
				buildChecklistControls();
				buildProofBadgeControls();
				buildProcessStepControls();
				buildSectionMediaEditors();
				buildFaqReorderControls();
				buildBenefitsReorderControls();
				buildBenefitsTextEditors();
				buildServiceIntroReorderControls();
				buildBenefitsIconEditors();
				try {
					var sid = String(selectedSectionWrap.getAttribute("data-lf-section-id") || "");
					var stype = String(selectedSectionWrap.getAttribute("data-lf-section-type") || "");
					var label = sectionLabelForWrap(selectedSectionWrap);
					if (sid || stype) {
						setStatus("Selected section target: " + label + " (" + (stype || sid) + ")", false);
					}
				} catch (e) {}
			}
			refreshAiScopeBanner();
			refreshSectionRail();
		}
		function scrollToSectionWrap(wrap) {
			if (!wrap || !wrap.getBoundingClientRect) return;
			try {
				wrap.scrollIntoView({ behavior: "smooth", block: "center", inline: "nearest" });
			} catch (e) {
				// no-op, fallback below
			}
			try {
				var rect = wrap.getBoundingClientRect();
				var pageTop = window.pageYOffset || document.documentElement.scrollTop || 0;
				var desired = pageTop + rect.top - 120;
				if (desired < 0) desired = 0;
				window.scrollTo({ top: desired, behavior: "smooth" });
			} catch (e) {}
		}
		function ensureSectionRail() {
			if (sectionRailEl) return sectionRailEl;
			sectionRailEl = document.createElement("aside");
			sectionRailEl.className = "lf-ai-rail lf-ai-inline-editor-ignore";
			sectionRailEl.innerHTML = "<div class=\"lf-ai-rail__head\"><div class=\"lf-ai-rail__title\">Page Structure</div><div class=\"lf-ai-rail__head-actions\"><button type=\"button\" class=\"lf-ai-rail__add\" data-lf-ai-rail-add aria-label=\"Add section\" title=\"Add section\">+</button><button type=\"button\" class=\"lf-ai-rail__toggle\" data-lf-ai-rail-toggle aria-label=\"Minimize structure rail\" title=\"Minimize structure rail\">\u2212</button></div></div><div class=\"lf-ai-rail__library\" data-lf-ai-rail-library hidden><input type=\"text\" class=\"lf-ai-rail__library-search\" data-lf-ai-rail-library-search placeholder=\"Search sections...\" /><div class=\"lf-ai-rail__library-list\" data-lf-ai-rail-library-list></div></div><div class=\"lf-ai-rail__list\" data-lf-ai-rail-list></div>";
			var toggle = sectionRailEl.querySelector("[data-lf-ai-rail-toggle]");
			var addBtn = sectionRailEl.querySelector("[data-lf-ai-rail-add]");
			var library = sectionRailEl.querySelector("[data-lf-ai-rail-library]");
			var librarySearch = sectionRailEl.querySelector("[data-lf-ai-rail-library-search]");
			if (toggle) {
				toggle.addEventListener("click", function(){
					railCollapsed = !railCollapsed;
					sectionRailEl.classList.toggle("is-collapsed", railCollapsed);
					toggle.textContent = railCollapsed ? "\u2630 Structure" : "\u2212";
					toggle.setAttribute("aria-label", railCollapsed ? "Expand structure rail" : "Minimize structure rail");
					toggle.setAttribute("title", railCollapsed ? "Expand structure rail" : "Minimize structure rail");
					try { window.localStorage.setItem(railStateKey, railCollapsed ? "collapsed" : "expanded"); } catch (e) {}
				});
			}
			if (addBtn && library) {
				addBtn.addEventListener("click", function(){
					railLibraryOpen = !railLibraryOpen;
					library.hidden = !railLibraryOpen;
					if (railLibraryOpen) {
						refreshRailLibrary();
						try { if (librarySearch) librarySearch.focus(); } catch (e) {}
					}
				});
			}
			if (librarySearch) {
				librarySearch.addEventListener("input", refreshRailLibrary);
			}
			document.body.appendChild(sectionRailEl);
			sectionRailEl.classList.toggle("is-collapsed", railCollapsed);
			if (toggle) {
				toggle.textContent = railCollapsed ? "\u2630 Structure" : "\u2212";
				toggle.setAttribute("aria-label", railCollapsed ? "Expand structure rail" : "Minimize structure rail");
				toggle.setAttribute("title", railCollapsed ? "Expand structure rail" : "Minimize structure rail");
			}
			return sectionRailEl;
		}
		function addSectionFromLibrary(sectionType, afterSectionId, beforeSectionId) {
			var type = String(sectionType || "");
			if (!type) return;
			setStatus("Adding section...", false);
			var afterId = String(afterSectionId || "");
			var beforeId = String(beforeSectionId || "");
			if (!afterId && !beforeId && selectedSectionWrap) {
				afterId = String(selectedSectionWrap.getAttribute("data-lf-section-id") || "");
			}
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_add_section",
				nonce: lfAiFloating.nonce,
				context_type: pageContextType,
				context_id: pageContextId,
				section_type: type,
				after_section_id: afterId,
				before_section_id: beforeId
			}).done(function(res){
				if (res && res.success && res.data && res.data.reload) {
					window.location.reload();
					return;
				}
				if (res && res.success) {
					setStatus((res.data && res.data.message) ? res.data.message : "Section added.", false);
				} else {
					var msg = (res && res.data && res.data.message) ? res.data.message : "Section add failed.";
					setStatus(msg, true);
					try { window.alert(msg); } catch (e) {}
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Section add failed.";
				setStatus(msg, true);
				try { window.alert(msg); } catch (e) {}
			});
		}
		function refreshRailLibrary() {
			if (!sectionRailEl) return;
			var library = sectionRailEl.querySelector("[data-lf-ai-rail-library]");
			var list = sectionRailEl.querySelector("[data-lf-ai-rail-library-list]");
			var search = sectionRailEl.querySelector("[data-lf-ai-rail-library-search]");
			if (!library || !list) return;
			var visibleIds = {};
			collectSectionWrappers().forEach(function(w){
				visibleIds[String(w.getAttribute("data-lf-section-id") || "")] = true;
			});
			var q = String(search && search.value ? search.value : "").trim().toLowerCase();
			var rows = Array.isArray(lfAiFloating.section_library) ? lfAiFloating.section_library : [];
			list.innerHTML = "";
			rows.forEach(function(row){
				var id = String(row && row.id ? row.id : "");
				var label = String(row && row.label ? row.label : id);
				if (!id) return;
				if (q && label.toLowerCase().indexOf(q) === -1 && id.toLowerCase().indexOf(q) === -1) return;
				var btn = document.createElement("button");
				btn.type = "button";
				btn.className = "lf-ai-rail__library-item";
				btn.textContent = label + " (" + id + ")";
				btn.setAttribute("draggable", "true");
				btn.setAttribute("data-lf-library-section-type", id);
				btn.addEventListener("dragstart", function(e){
					activeLibraryDragSectionType = id;
					btn.classList.add("is-dragging");
					if (e.dataTransfer) {
						e.dataTransfer.effectAllowed = "copyMove";
						e.dataTransfer.setData("text/plain", id);
					}
				});
				btn.addEventListener("dragend", function(){
					btn.classList.remove("is-dragging");
					activeLibraryDragSectionType = "";
				});
				btn.addEventListener("click", function(){
					addSectionFromLibrary(id);
				});
				list.appendChild(btn);
			});
		}
		function sectionWrapById(sectionId) {
			var id = String(sectionId || "");
			if (!id) return null;
			var wraps = collectSectionWrappers();
			for (var i = 0; i < wraps.length; i++) {
				if (String(wraps[i].getAttribute("data-lf-section-id") || "") === id) {
					return wraps[i];
				}
			}
			return null;
		}
		function refreshSectionRail() {
			if (!editingEnabled) {
				if (sectionRailEl && sectionRailEl.parentNode) {
					sectionRailEl.parentNode.removeChild(sectionRailEl);
				}
				sectionRailEl = null;
				return;
			}
			var wraps = collectSectionWrappers();
			if (!wraps.length) {
				if (sectionRailEl && sectionRailEl.parentNode) {
					sectionRailEl.parentNode.removeChild(sectionRailEl);
				}
				sectionRailEl = null;
				return;
			}
			var rail = ensureSectionRail();
			var list = rail.querySelector("[data-lf-ai-rail-list]");
			if (!list) return;
			function clearRailDropMarkers() {
				Array.prototype.slice.call(list.querySelectorAll(".lf-ai-rail__item.is-drop-before,.lf-ai-rail__item.is-drop-after")).forEach(function(node){
					node.classList.remove("is-drop-before", "is-drop-after");
				});
			}
			function reorderFromRailDrop(sourceId, targetId, after) {
				var dragWrap = sectionWrapById(sourceId);
				var targetWrap = sectionWrapById(targetId);
				if (!dragWrap || !targetWrap || !dragWrap.parentNode) return false;
				if (after) {
					targetWrap.parentNode.insertBefore(dragWrap, targetWrap.nextSibling);
				} else {
					targetWrap.parentNode.insertBefore(dragWrap, targetWrap);
				}
				setSelectedSection(dragWrap);
				persistSectionOrder();
				return true;
			}
			function railRowFromPoint(clientX, clientY) {
				var el = document.elementFromPoint(clientX, clientY);
				if (!el || !el.closest) return null;
				var row = el.closest(".lf-ai-rail__item[data-lf-rail-section-id]");
				if (!row || !list.contains(row)) return null;
				return row;
			}
			list.innerHTML = "";
			wraps.forEach(function(wrap){
				var row = document.createElement("div");
				var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
				var isHidden = String(wrap.getAttribute("data-lf-section-visible") || "1") === "0";
				row.className = "lf-ai-rail__item" + (wrap === selectedSectionWrap ? " is-active" : "");
				row.setAttribute("draggable", "true");
				row.setAttribute("data-lf-rail-section-id", sectionId);
				row.setAttribute("tabindex", "0");
				row.innerHTML = "<div class=\"lf-ai-rail__item-body\">" + escapeHtml(sectionLabelForWrap(wrap)) + "<small>" + escapeHtml(String(wrap.getAttribute("data-lf-section-type") || "section")) + (isHidden ? " • hidden" : "") + "</small></div><span class=\"lf-ai-rail__drag\" data-lf-rail-drag title=\"Drag to reorder\" aria-label=\"Drag to reorder\">⋮⋮</span>";
				row.addEventListener("click", function(){
					if (row.__draggedOnce) {
						row.__draggedOnce = false;
						return;
					}
					setSelectedSection(wrap);
					scrollToSectionWrap(wrap);
				});
				row.addEventListener("focus", function(){
					setSelectedSection(wrap);
				});
				row.addEventListener("keydown", function(e){
					var key = String(e.key || "");
					if (key === "Backspace" || key === "Delete") {
						e.preventDefault();
						setSelectedSection(wrap);
						selectedSectionDelete();
						return;
					}
					if (key === "ArrowUp" && (e.altKey || e.metaKey || e.shiftKey)) {
						e.preventDefault();
						setSelectedSection(wrap);
						moveSelectedSection(-1);
						return;
					}
					if (key === "ArrowDown" && (e.altKey || e.metaKey || e.shiftKey)) {
						e.preventDefault();
						setSelectedSection(wrap);
						moveSelectedSection(1);
						return;
					}
				});
				row.addEventListener("dragstart", function(e){
					// Allow drag from the full row for reliability across browsers.
					activeRailDragSectionId = sectionId;
					row.classList.add("is-dragging");
					if (e.dataTransfer) {
						e.dataTransfer.effectAllowed = "move";
						e.dataTransfer.setData("text/plain", sectionId);
					}
				});
				row.addEventListener("mousedown", function(e){
					if (!e || e.button !== 0) return;
					var target = e.target && e.target.nodeType === 1 ? e.target : null;
					if (!target || (target.closest && target.closest("a,button,input,textarea,select,label"))) {
						return;
					}
					railPointerDrag = { sectionId: sectionId, row: row, targetId: "", after: false };
					activeRailDragSectionId = sectionId;
					row.classList.add("is-dragging");
					document.body.classList.add("lf-ai-rail-dragging");
					e.preventDefault();
				});
				row.addEventListener("dragover", function(e){
					if (activeLibraryDragSectionType) {
						e.preventDefault();
						return;
					}
					if (!activeRailDragSectionId || activeRailDragSectionId === sectionId) return;
					e.preventDefault();
					clearRailDropMarkers();
					var rect = row.getBoundingClientRect();
					var after = e.clientY > (rect.top + rect.height / 2);
					row.classList.add(after ? "is-drop-after" : "is-drop-before");
				});
				row.addEventListener("dragleave", function(){
					row.classList.remove("is-drop-before", "is-drop-after");
				});
				row.addEventListener("drop", function(e){
					if (activeLibraryDragSectionType) {
						e.preventDefault();
						addSectionFromLibrary(activeLibraryDragSectionType, sectionId);
						activeLibraryDragSectionType = "";
						return;
					}
					if (!activeRailDragSectionId || activeRailDragSectionId === sectionId) return;
					e.preventDefault();
					var rect = row.getBoundingClientRect();
					var after = e.clientY > (rect.top + rect.height / 2);
					if (!reorderFromRailDrop(activeRailDragSectionId, sectionId, after)) return;
					row.__draggedOnce = true;
					clearRailDropMarkers();
				});
				row.addEventListener("dragend", function(){
					row.classList.remove("is-dragging");
					activeRailDragSectionId = "";
					railPointerDrag = null;
					document.body.classList.remove("lf-ai-rail-dragging");
					clearRailDropMarkers();
				});
				list.appendChild(row);
			});
			if (!list.__lfRailDropBound) {
				list.__lfRailDropBound = true;
				list.addEventListener("dragover", function(e){
					if (activeLibraryDragSectionType) {
						e.preventDefault();
					}
				});
				list.addEventListener("drop", function(e){
					if (!activeLibraryDragSectionType) return;
					e.preventDefault();
					addSectionFromLibrary(activeLibraryDragSectionType, "");
					activeLibraryDragSectionType = "";
				});
				document.addEventListener("mousemove", function(e){
					if (!railPointerDrag || !railPointerDrag.row) return;
					var row = railRowFromPoint(e.clientX, e.clientY);
					clearRailDropMarkers();
					if (!row) {
						railPointerDrag.targetId = "";
						return;
					}
					var targetId = String(row.getAttribute("data-lf-rail-section-id") || "");
					if (!targetId || targetId === railPointerDrag.sectionId) {
						railPointerDrag.targetId = "";
						return;
					}
					var rect = row.getBoundingClientRect();
					var after = e.clientY > (rect.top + rect.height / 2);
					row.classList.add(after ? "is-drop-after" : "is-drop-before");
					railPointerDrag.targetId = targetId;
					railPointerDrag.after = after;
				});
				document.addEventListener("mouseup", function(){
					if (!railPointerDrag || !railPointerDrag.row) return;
					var didReorder = false;
					if (railPointerDrag.targetId && railPointerDrag.targetId !== railPointerDrag.sectionId) {
						didReorder = reorderFromRailDrop(railPointerDrag.sectionId, railPointerDrag.targetId, !!railPointerDrag.after);
					}
					if (didReorder) {
						railPointerDrag.row.__draggedOnce = true;
					}
					railPointerDrag.row.classList.remove("is-dragging");
					railPointerDrag = null;
					activeRailDragSectionId = "";
					document.body.classList.remove("lf-ai-rail-dragging");
					clearRailDropMarkers();
				});
			}
		}
		function moveSelectedSection(delta) {
			if (!selectedSectionWrap || !delta) return;
			var wraps = collectSectionWrappers();
			var idx = wraps.indexOf(selectedSectionWrap);
			if (idx < 0) return;
			var targetIdx = idx + delta;
			if (targetIdx < 0 || targetIdx >= wraps.length) return;
			var target = wraps[targetIdx];
			if (!target || !target.parentNode) return;
			if (delta < 0) {
				target.parentNode.insertBefore(selectedSectionWrap, target);
			} else {
				target.parentNode.insertBefore(selectedSectionWrap, target.nextSibling);
			}
			persistSectionOrder();
			refreshSectionRail();
		}
		function sectionSupportsDuplicate(sectionType, sectionId) {
			var type = String(sectionType || "");
			if (type === "" || type === "hero" || type === "hero_1") {
				return false;
			}
			return true;
		}
		function baseSectionType(sectionType, sectionId) {
			var type = String(sectionType || "").trim();
			var id = String(sectionId || "").trim();
			var heroLike = function(value) {
				return /^hero(?:_\d+)?$/i.test(String(value || ""));
			};
			if (heroLike(type) || heroLike(id)) return "hero";
			return type !== "" ? type : id;
		}
		function sectionSupportsColumnSwap(sectionType) {
			var type = String(sectionType || "");
			return ["service_details", "content_image", "content_image_a", "image_content", "image_content_b", "content_image_c"].indexOf(type) !== -1;
		}
		function sectionSupportsChecklistEditor(sectionType) {
			var type = String(sectionType || "");
			return ["service_details", "content_image", "content_image_a", "image_content", "image_content_b", "content_image_c"].indexOf(type) !== -1;
		}
		function checklistItemsFromWrap(wrap, listEl) {
			var list = listEl || (wrap ? wrap.querySelector(".lf-service-details__checklist") : null);
			if (!list) return [];
			return Array.prototype.slice.call(list.querySelectorAll("li")).map(function(li){
				var textNode = li.querySelector(".lf-service-details__text");
				var raw = textNode ? innerHtmlFromEditableNode(textNode) : String(li.textContent || "").trim();
				return raw;
			}).filter(function(value){ return lfPlainFromHtml(value) !== ""; });
		}
		function checklistFieldKeyFromList(listEl) {
			if (!listEl || !listEl.classList) return "service_details_checklist";
			return listEl.classList.contains("lf-service-details__checklist--secondary")
				? "service_details_checklist_secondary"
				: "service_details_checklist";
		}
		function persistSectionChecklist(wrap, sourceNode) {
			if (!wrap) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
			if (!sectionId || !sectionSupportsChecklistEditor(sectionType)) return;
			var sourceList = sourceNode && sourceNode.closest ? sourceNode.closest("ul.lf-service-details__checklist") : null;
			var items = checklistItemsFromWrap(wrap, sourceList);
			var fieldKey = checklistFieldKeyFromList(sourceList);
			var pc = persistContextFromWrap(wrap);
			setStatus("Saving checklist...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_update_section_checklist",
				nonce: lfAiFloating.nonce,
				context_type: pc.context_type,
				context_id: pc.context_id,
				section_id: sectionId,
				field_key: fieldKey,
				items: JSON.stringify(items)
			}).done(function(res){
				if (res && res.success) {
					setStatus((res.data && res.data.message) ? res.data.message : "Checklist saved.", false);
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "Checklist save failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Checklist save failed.";
				setStatus(msg, true);
			});
		}
		function buildChecklistControls() {
			function focusNodeEnd(node) {
				if (!node || !window.getSelection || !document.createRange) return;
				try {
					var range = document.createRange();
					range.selectNodeContents(node);
					range.collapse(false);
					var sel = window.getSelection();
					sel.removeAllRanges();
					sel.addRange(range);
				} catch (e) {}
			}
			function checklistTextNodeFromLi(li) {
				if (!li) return null;
				var textNode = ensureManagedListItemTextNode(li, ".lf-service-details__text", "lf-service-details__text");
				textNode.removeAttribute("data-lf-inline-editable");
				textNode.removeAttribute("data-lf-inline-selector");
				li.removeAttribute("data-lf-inline-editable");
				li.removeAttribute("data-lf-inline-selector");
				return textNode;
			}
			function finishChecklistTextEdit(textNode, li, wrap, commit) {
				if (!textNode) return;
				var originalHtml = String(textNode.getAttribute("data-lf-ai-original-html") || "");
				var nextHtml = innerHtmlFromEditableNode(textNode);
				var nextPlain = lfPlainFromHtml(nextHtml);
				textNode.removeAttribute("contenteditable");
				textNode.removeAttribute("spellcheck");
				textNode.removeAttribute("data-lf-ai-editing");
				textNode.removeAttribute("data-lf-ai-original-html");
				if (!commit) {
					textNode.innerHTML = originalHtml || nextHtml || "Checklist item";
					return;
				}
				if (nextPlain === "") {
					textNode.innerHTML = originalHtml || "Checklist item";
					return;
				}
				textNode.innerHTML = nextHtml;
				if (nextHtml !== originalHtml) {
					persistSectionChecklist(wrap, textNode);
				}
			}
			function startChecklistTextEdit(textNode, li, wrap) {
				if (!textNode) return;
				if (String(textNode.getAttribute("data-lf-ai-editing") || "0") === "1") return;
				textNode.setAttribute("data-lf-ai-original-html", innerHtmlFromEditableNode(textNode));
				textNode.setAttribute("data-lf-ai-editing", "1");
				textNode.setAttribute("contenteditable", "true");
				textNode.setAttribute("spellcheck", "true");
				try { textNode.focus(); } catch (e) {}
				focusNodeEnd(textNode);
			}
			function bindChecklistItemEditor(li, wrap) {
				if (!li || !wrap) return;
				var textNode = checklistTextNodeFromLi(li);
				if (!textNode) return;
				textNode.classList.add("lf-ai-inline-editor-ignore");
				textNode.setAttribute("title", "Click to edit checklist item");
				textNode.onmousedown = function(e){
					if (e) e.stopPropagation();
				};
				textNode.onclick = function(e){
					if (!editingEnabled) return;
					if (e) {
						e.preventDefault();
						e.stopPropagation();
					}
					startChecklistTextEdit(textNode, li, wrap);
				};
				textNode.onblur = function(){
					finishChecklistTextEdit(textNode, li, wrap, true);
				};
				textNode.onkeydown = function(e){
					var key = String((e && e.key) || "");
					if (key === "Enter") {
						e.preventDefault();
						finishChecklistTextEdit(textNode, li, wrap, true);
						return;
					}
					if (key === "Escape") {
						e.preventDefault();
						finishChecklistTextEdit(textNode, li, wrap, false);
					}
				};
			}
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-checklist-controls=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-checklist-remove=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
				if (!sectionSupportsChecklistEditor(sectionType)) return;
				var content = wrap.querySelector(".lf-service-details__content");
				if (!content) return;
				var host = content.querySelector(".lf-service-details__checklists");
				if (!host) {
					host = document.createElement("div");
					host.className = "lf-service-details__checklists";
					var microEl = content.querySelector("[data-lf-service-details-micro=\"1\"]");
					var proofEl = content.querySelector(".lf-service-details__proof");
					if (microEl && microEl.parentNode === content) {
						content.insertBefore(host, microEl);
					} else if (proofEl) {
						content.insertBefore(host, proofEl);
					} else {
						content.appendChild(host);
					}
				}
				var listEls = Array.prototype.slice.call(host.querySelectorAll("ul.lf-service-details__checklist"));
				if (!listEls.length) {
					var newList = document.createElement("ul");
					newList.className = "lf-service-details__checklist";
					newList.setAttribute("role", "list");
					host.appendChild(newList);
					listEls = Array.prototype.slice.call(host.querySelectorAll("ul.lf-service-details__checklist"));
				}
				function wireChecklistListItems(list) {
					Array.prototype.slice.call(list.querySelectorAll("li")).forEach(function(li){
						checklistTextNodeFromLi(li);
						Array.prototype.slice.call(li.querySelectorAll("[data-lf-ai-checklist-remove=\"1\"]")).forEach(function(node){
							if (node && node.parentNode) node.parentNode.removeChild(node);
						});
						var removeBtn = document.createElement("button");
						removeBtn.type = "button";
						removeBtn.className = "lf-ai-checklist-remove lf-ai-inline-editor-ignore";
						removeBtn.setAttribute("data-lf-ai-checklist-remove", "1");
						removeBtn.textContent = "x";
						removeBtn.setAttribute("title", "Remove checklist item");
						removeBtn.addEventListener("click", function(e){
							e.preventDefault();
							e.stopPropagation();
							if (li && li.parentNode) {
								li.parentNode.removeChild(li);
							}
							persistSectionChecklist(wrap, li);
						});
						li.appendChild(removeBtn);
						bindChecklistItemEditor(li, wrap);
					});
				}
				listEls.forEach(wireChecklistListItems);
				var primaryList = listEls[0];
				if (primaryList) {
					var controls = document.createElement("div");
					controls.className = "lf-ai-checklist-controls lf-ai-inline-editor-ignore";
					controls.setAttribute("data-lf-ai-checklist-controls", "1");
					var addBtn = document.createElement("button");
					addBtn.type = "button";
					addBtn.className = "lf-ai-checklist-add lf-ai-inline-editor-ignore";
					addBtn.textContent = "+ Add item";
					addBtn.setAttribute("title", "Add item to the main checklist");
					addBtn.addEventListener("click", function(e){
						e.preventDefault();
						e.stopPropagation();
						var li = document.createElement("li");
						var textNode = document.createElement("span");
						textNode.className = "lf-service-details__text";
						textNode.textContent = "New checklist item";
						li.appendChild(textNode);
						var removeBtn2 = document.createElement("button");
						removeBtn2.type = "button";
						removeBtn2.className = "lf-ai-checklist-remove lf-ai-inline-editor-ignore";
						removeBtn2.setAttribute("data-lf-ai-checklist-remove", "1");
						removeBtn2.textContent = "x";
						removeBtn2.setAttribute("title", "Remove checklist item");
						removeBtn2.addEventListener("click", function(ev){
							ev.preventDefault();
							ev.stopPropagation();
							if (li && li.parentNode) {
								li.parentNode.removeChild(li);
							}
							persistSectionChecklist(wrap, li);
						});
						li.appendChild(removeBtn2);
						primaryList.appendChild(li);
						bindChecklistItemEditor(li, wrap);
						persistSectionChecklist(wrap, li);
						startChecklistTextEdit(textNode, li, wrap);
					});
					controls.appendChild(addBtn);
					host.appendChild(controls);
				}
			});
		}
		function microLinesFromWrap(microRoot) {
			if (!microRoot) return [];
			return Array.prototype.slice.call(microRoot.querySelectorAll(".lf-service-details__micro-text")).map(function(span){
				return innerHtmlFromEditableNode(span);
			}).filter(function(v){ return lfPlainFromHtml(v) !== ""; });
		}
		function buildServiceDetailsMicroControls() {
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
				if (!sectionSupportsChecklistEditor(sectionType)) return;
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-micro-controls=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-micro-remove=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
			});
		}
		function proofBadgeLinesFromWrap(wrap) {
			var box = wrap ? wrap.querySelector(".lf-service-details__proof-badges") : null;
			if (!box) return [];
			return Array.prototype.slice.call(box.querySelectorAll(".lf-service-details__proof-badge")).map(function(badge){
				return String(badge.textContent || "").replace(/\s+/g, " ").trim();
			}).filter(function(v){ return v !== ""; });
		}
		function persistProofBadges(wrap) {
			if (!wrap) return;
			persistSectionLineItems(wrap, "service_details_proof_badges", proofBadgeLinesFromWrap(wrap), "Saving proof keywords...");
		}
		function buildProofBadgeControls() {
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
				if (!sectionSupportsChecklistEditor(sectionType)) return;
				var content = wrap.querySelector(".lf-service-details__content");
				if (!content) return;
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-proof-badge-controls=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				var proof = content.querySelector(".lf-service-details__proof");
				if (!proof) {
					proof = document.createElement("div");
					proof.className = "lf-service-details__proof";
					proof.setAttribute("role", "note");
					var badges = document.createElement("div");
					badges.className = "lf-service-details__proof-badges";
					proof.appendChild(badges);
					content.appendChild(proof);
				}
				var badgesBox = proof.querySelector(".lf-service-details__proof-badges");
				if (!badgesBox) {
					badgesBox = document.createElement("div");
					badgesBox.className = "lf-service-details__proof-badges";
					proof.appendChild(badgesBox);
				}
				Array.prototype.slice.call(badgesBox.children).forEach(function(badge){
					if (!badge.classList || !badge.classList.contains("lf-service-details__proof-badge")) return;
					var row = document.createElement("span");
					row.className = "lf-service-details__proof-badge-wrap lf-ai-inline-editor-ignore";
					badge.parentNode.insertBefore(row, badge);
					row.appendChild(badge);
					badge.classList.add("lf-service-details__proof-badge--editable");
					var rb = document.createElement("button");
					rb.type = "button";
					rb.className = "lf-ai-proof-badge-remove lf-ai-inline-editor-ignore";
					rb.setAttribute("data-lf-ai-proof-badge-remove", "1");
					rb.textContent = "×";
					rb.setAttribute("title", "Remove keyword");
					rb.addEventListener("mousedown", function(e){ if (e) e.stopPropagation(); });
					rb.addEventListener("click", function(e){
						e.preventDefault();
						e.stopPropagation();
						if (row.parentNode) row.parentNode.removeChild(row);
						persistProofBadges(wrap);
					});
					row.appendChild(rb);
				});
				Array.prototype.slice.call(badgesBox.querySelectorAll(".lf-service-details__proof-badge")).forEach(function(badge){
					if (String(badge.getAttribute("data-lf-proof-badge-wired") || "") === "1") return;
					badge.setAttribute("data-lf-proof-badge-wired", "1");
					var row = badge.parentElement;
					badge.setAttribute("title", "Click to edit keyword");
					badge.onmousedown = function(e){ if (e) e.stopPropagation(); };
					badge.onclick = function(e){
						if (!editingEnabled) return;
						if (e) {
							e.preventDefault();
							e.stopPropagation();
						}
						if (String(badge.getAttribute("data-lf-ai-editing") || "") === "1") return;
						badge.setAttribute("data-lf-ai-editing", "1");
						badge.setAttribute("contenteditable", "true");
						try { badge.focus(); } catch (e1) {}
					};
					badge.onblur = function(){
						if (String(badge.getAttribute("data-lf-ai-editing") || "") !== "1") return;
						badge.removeAttribute("contenteditable");
						badge.removeAttribute("data-lf-ai-editing");
						var t = String(badge.textContent || "").replace(/\s+/g, " ").trim();
						if (t === "") {
							if (row && row.parentNode) row.parentNode.removeChild(row);
							persistProofBadges(wrap);
							return;
						}
						persistProofBadges(wrap);
					};
				});
				var ctrl = document.createElement("div");
				ctrl.className = "lf-ai-checklist-controls lf-ai-inline-editor-ignore";
				ctrl.setAttribute("data-lf-ai-proof-badge-controls", "1");
				var addB = document.createElement("button");
				addB.type = "button";
				addB.className = "lf-ai-checklist-add lf-ai-inline-editor-ignore";
				addB.textContent = "+ Add keyword line";
				addB.setAttribute("title", "Add a short trust or SEO keyword line");
				addB.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					var row = document.createElement("span");
					row.className = "lf-service-details__proof-badge-wrap lf-ai-inline-editor-ignore";
					var badge = document.createElement("span");
					badge.className = "lf-service-details__proof-badge lf-service-details__proof-badge--editable";
					badge.textContent = "New keyword line";
					row.appendChild(badge);
					var rb2 = document.createElement("button");
					rb2.type = "button";
					rb2.className = "lf-ai-proof-badge-remove lf-ai-inline-editor-ignore";
					rb2.setAttribute("data-lf-ai-proof-badge-remove", "1");
					rb2.textContent = "×";
					rb2.setAttribute("title", "Remove keyword");
					rb2.addEventListener("mousedown", function(ev){ if (ev) ev.stopPropagation(); });
					rb2.addEventListener("click", function(ev){
						ev.preventDefault();
						ev.stopPropagation();
						if (row.parentNode) row.parentNode.removeChild(row);
						persistProofBadges(wrap);
					});
					row.appendChild(rb2);
					badgesBox.appendChild(row);
					buildProofBadgeControls();
					persistProofBadges(wrap);
				});
				ctrl.appendChild(addB);
				if (proof.nextSibling) {
					content.insertBefore(ctrl, proof.nextSibling);
				} else {
					content.appendChild(ctrl);
				}
			});
		}
		function heroPillsFromWrap(wrap) {
			var list = wrap ? wrap.querySelector(".lf-hero-chips") : null;
			if (!list) return [];
			function cleanPillText(raw) {
				var text = String(raw || "").replace(/\s+/g, " ").trim();
				text = text.replace(/\s*[xX×]\s*$/, "").trim();
				return text;
			}
			return Array.prototype.slice.call(list.querySelectorAll(".lf-hero-chip")).map(function(chip){
				var textNode = chip.querySelector("[data-lf-hero-pill-text]");
				var raw = textNode ? String(textNode.textContent || "") : String(chip.textContent || "");
				return cleanPillText(raw);
			}).filter(function(value){ return value !== ""; });
		}
		function persistHeroPills(wrap) {
			if (!wrap) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
			var baseType = baseSectionType(sectionType, sectionId);
			if (!sectionId && baseType === "hero") {
				sectionId = "hero";
			}
			if (!sectionId || baseType !== "hero") return;
			var items = heroPillsFromWrap(wrap);
			var pc = persistContextFromWrap(wrap);
			setStatus("Saving hero pills...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_update_hero_pills",
				nonce: lfAiFloating.nonce,
				context_type: pc.context_type,
				context_id: pc.context_id,
				section_id: sectionId,
				list_kind: "chips",
				items: JSON.stringify(items)
			}).done(function(res){
				if (res && res.success) {
					setStatus((res.data && res.data.message) ? res.data.message : "Hero pills saved.", false);
				} else {
					persistSectionLineItems(wrap, "hero_chip_bullets", items, "Saving pills...");
				}
			}).fail(function(xhr){
				persistSectionLineItems(wrap, "hero_chip_bullets", items, "Saving pills...");
			});
		}
		function textFromNodeWithoutAiControls(node) {
			if (!node) return "";
			var clone = node.cloneNode(true);
			Array.prototype.slice.call(clone.querySelectorAll("[data-lf-ai-hero-pill-remove=\"1\"],[data-lf-ai-checklist-remove=\"1\"],[data-lf-ai-list-remove=\"1\"]")).forEach(function(btn){
				if (btn && btn.parentNode) btn.parentNode.removeChild(btn);
			});
			// Hero proof lines use lf-ai-inline-editor-ignore on the visible text span so global inline
			// edit skips them; that class must not be stripped here or persist sends empty items.
			Array.prototype.slice.call(clone.querySelectorAll(".lf-ai-inline-editor-ignore")).forEach(function(el){
				if (el && el.classList && (el.classList.contains("lf-block-hero__card-item-text") || el.classList.contains("lf-service-details__text"))) return;
				if (el && el.parentNode) el.parentNode.removeChild(el);
			});
			return String(clone.textContent || "").replace(/\s+/g, " ").trim();
		}
		function lfPlainFromHtml(html) {
			var d = document.createElement("div");
			d.innerHTML = String(html || "");
			return String(d.textContent || "").replace(/\s+/g, " ").trim();
		}
		function innerHtmlFromEditableNode(node) {
			if (!node) return "";
			var clone = node.cloneNode(true);
			Array.prototype.slice.call(clone.querySelectorAll("[data-lf-ai-hero-pill-remove=\"1\"],[data-lf-ai-checklist-remove=\"1\"],[data-lf-ai-list-remove=\"1\"]")).forEach(function(btn){
				if (btn && btn.parentNode) btn.parentNode.removeChild(btn);
			});
			Array.prototype.slice.call(clone.querySelectorAll(".lf-ai-inline-editor-ignore")).forEach(function(el){
				if (el && el.classList && (el.classList.contains("lf-block-hero__card-item-text") || el.classList.contains("lf-service-details__text"))) return;
				if (el && el.parentNode) el.parentNode.removeChild(el);
			});
			return String(clone.innerHTML || "").trim();
		}
		function ensureManagedListItemTextNode(li, selector, className) {
			if (!li) return null;
			var textNode = li.querySelector(selector);
			var normalized = textFromNodeWithoutAiControls(li);
			if (!textNode) {
				textNode = document.createElement("span");
				textNode.className = className;
				li.insertBefore(textNode, li.firstChild || null);
				textNode.textContent = normalized || "";
			} else {
				var hasMarkup = false;
				try {
					var kids = textNode.childNodes || [];
					for (var j = 0; j < kids.length; j++) {
						if (kids[j].nodeType === 1) {
							hasMarkup = true;
							break;
						}
					}
				} catch (e1) {}
				if (!hasMarkup && !/<[a-z][\s\S]*>/i.test(String(textNode.innerHTML || ""))) {
					textNode.textContent = normalized || String(textNode.textContent || "").trim();
				}
			}
			Array.prototype.slice.call(li.childNodes || []).forEach(function(child){
				if (!child || child === textNode) return;
				if (child.nodeType === 3 && String(child.textContent || "").trim() !== "") {
					try { li.removeChild(child); } catch (e2) {}
				}
			});
			return textNode;
		}
		function persistSectionLineItems(wrap, fieldKey, items, savingLabel) {
			if (!wrap || !fieldKey) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			if (!sectionId) return;
			var lines = Array.isArray(items) ? items.map(function(v){ return String(v || "").trim(); }).filter(function(v){ return v !== ""; }) : [];
			var pc = persistContextFromWrap(wrap);
			setStatus(savingLabel || "Saving list...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_update_section_lines",
				nonce: lfAiFloating.nonce,
				context_type: pc.context_type,
				context_id: pc.context_id,
				section_id: sectionId,
				field_key: String(fieldKey),
				items: JSON.stringify(lines)
			}).done(function(res){
				if (res && res.success) {
					setStatus((res.data && res.data.message) ? res.data.message : "List saved.", false);
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "List save failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "List save failed.";
				setStatus(msg, true);
			});
		}
		function simpleListItemsFromContainer(container, itemSelector) {
			if (!container) return [];
			return Array.prototype.slice.call(container.querySelectorAll(itemSelector)).map(function(node){
				return textFromNodeWithoutAiControls(node);
			}).filter(function(text){ return text !== ""; });
		}
		function heroProofHtmlItemsFromList(list) {
			if (!list) return [];
			return Array.prototype.slice.call(list.querySelectorAll("li")).map(function(li){
				var span = li.querySelector(".lf-block-hero__card-item-text");
				return span ? innerHtmlFromEditableNode(span) : "";
			}).filter(function(h){ return lfPlainFromHtml(h) !== ""; });
		}
		function persistHeroProofItems(wrap, list) {
			if (!wrap || !list) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
			var baseType = baseSectionType(sectionType, sectionId);
			if (!sectionId && baseType === "hero") {
				sectionId = "hero";
			}
			if (!sectionId || baseType !== "hero") return;
			var items = heroProofHtmlItemsFromList(list);
			var pc = persistContextFromWrap(wrap);
			setStatus("Saving checklist...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_update_hero_pills",
				nonce: lfAiFloating.nonce,
				context_type: pc.context_type,
				context_id: pc.context_id,
				section_id: sectionId,
				list_kind: "proof",
				items: JSON.stringify(items)
			}).done(function(res){
				if (res && res.success) {
					setStatus((res.data && res.data.message) ? res.data.message : "Checklist saved.", false);
				} else {
					persistSectionLineItems(wrap, "hero_proof_bullets", items, "Saving checklist...");
				}
			}).fail(function(){
				persistSectionLineItems(wrap, "hero_proof_bullets", items, "Saving checklist...");
			});
		}
		function createGenericRemoveButton(onClick) {
			var btn = document.createElement("button");
			btn.type = "button";
			btn.className = "lf-ai-list-remove lf-ai-inline-editor-ignore";
			btn.setAttribute("data-lf-ai-list-remove", "1");
			btn.setAttribute("title", "Remove item");
			btn.textContent = "x";
			btn.addEventListener("click", function(e){
				e.preventDefault();
				e.stopPropagation();
				if (typeof onClick === "function") onClick();
			});
			return btn;
		}
		function buildHeroPillsControls() {
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-hero-pills-controls=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-hero-pill-remove=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
				var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
				if (baseSectionType(sectionType, sectionId) !== "hero") return;
				var list = wrap.querySelector(".lf-hero-chips");
				if (!list) return;
				Array.prototype.slice.call(list.querySelectorAll(".lf-hero-chip,[data-lf-hero-pill-text]")).forEach(function(node){
					node.removeAttribute("data-lf-inline-editable");
					node.removeAttribute("data-lf-inline-selector");
				});
				Array.prototype.slice.call(list.querySelectorAll(".lf-hero-chip")).forEach(function(chip){
					var textNode = chip.querySelector("[data-lf-hero-pill-text]");
					var normalized = textFromNodeWithoutAiControls(chip);
					if (!textNode) {
						textNode = document.createElement("span");
						textNode.setAttribute("data-lf-hero-pill-text", "1");
						chip.textContent = "";
						chip.appendChild(textNode);
					}
					textNode.textContent = normalized || String(textNode.textContent || "").trim();
					Array.prototype.slice.call(chip.childNodes || []).forEach(function(child){
						if (!child || child === textNode) return;
						if (child.nodeType === 3 && String(child.textContent || "").trim() !== "") {
							try { chip.removeChild(child); } catch (e) {}
						}
					});
					if (!chip.querySelector("[data-lf-ai-hero-pill-remove=\"1\"]")) {
						var removeBtn = document.createElement("button");
						removeBtn.type = "button";
						removeBtn.className = "lf-ai-hero-pill-remove lf-ai-inline-editor-ignore";
						removeBtn.setAttribute("data-lf-ai-hero-pill-remove", "1");
						removeBtn.setAttribute("title", "Remove pill");
						removeBtn.textContent = "x";
						removeBtn.addEventListener("click", function(e){
							e.preventDefault();
							e.stopPropagation();
							if (chip && chip.parentNode) {
								chip.parentNode.removeChild(chip);
							}
							persistHeroPills(wrap);
						});
						chip.appendChild(removeBtn);
					}
					chip.ondblclick = function(e){
						e.preventDefault();
						e.stopPropagation();
						if (chip && chip.parentNode) {
							chip.parentNode.removeChild(chip);
						}
						persistHeroPills(wrap);
					};
				});
				var controls = document.createElement("div");
				controls.className = "lf-ai-hero-pills-controls lf-ai-inline-editor-ignore";
				controls.setAttribute("data-lf-ai-hero-pills-controls", "1");
				var addBtn = document.createElement("button");
				addBtn.type = "button";
				addBtn.className = "lf-ai-hero-pill-add lf-ai-inline-editor-ignore";
				addBtn.textContent = "+ Add pill";
				addBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					var text = "";
					try {
						text = String(window.prompt("New pill text:", "") || "").trim();
					} catch (err) {
						text = "";
					}
					if (!text) return;
					var tag = list.tagName && list.tagName.toLowerCase() === "ul" ? "li" : "span";
					var chip = document.createElement(tag);
					chip.className = "lf-hero-chip";
					var textNode = document.createElement("span");
					textNode.setAttribute("data-lf-hero-pill-text", "1");
					textNode.textContent = text;
					chip.appendChild(textNode);
					var removeBtn = document.createElement("button");
					removeBtn.type = "button";
					removeBtn.className = "lf-ai-hero-pill-remove lf-ai-inline-editor-ignore";
					removeBtn.setAttribute("data-lf-ai-hero-pill-remove", "1");
					removeBtn.setAttribute("title", "Remove pill");
					removeBtn.textContent = "x";
					removeBtn.addEventListener("click", function(ev){
						ev.preventDefault();
						ev.stopPropagation();
						if (chip && chip.parentNode) {
							chip.parentNode.removeChild(chip);
						}
						persistHeroPills(wrap);
					});
					chip.appendChild(removeBtn);
					chip.ondblclick = function(ev2){
						ev2.preventDefault();
						ev2.stopPropagation();
						if (chip && chip.parentNode) {
							chip.parentNode.removeChild(chip);
						}
						persistHeroPills(wrap);
					};
					list.appendChild(chip);
					persistHeroPills(wrap);
				});
				controls.appendChild(addBtn);
				var parent = list.parentNode;
				if (parent) {
					parent.appendChild(controls);
				}
			});
		}
		function buildHeroProofChecklistControls() {
			function focusNodeEnd(node) {
				if (!node || !window.getSelection || !document.createRange) return;
				try {
					var range = document.createRange();
					range.selectNodeContents(node);
					range.collapse(false);
					var sel = window.getSelection();
					sel.removeAllRanges();
					sel.addRange(range);
				} catch (e) {}
			}
			function heroProofTextNodeFromLi(li) {
				if (!li) return null;
				var textNode = ensureManagedListItemTextNode(li, ".lf-block-hero__card-item-text", "lf-block-hero__card-item-text");
				textNode.removeAttribute("data-lf-inline-editable");
				textNode.removeAttribute("data-lf-inline-selector");
				li.removeAttribute("data-lf-inline-editable");
				li.removeAttribute("data-lf-inline-selector");
				return textNode;
			}
			function finishHeroProofTextEdit(textNode, wrap, list, commit) {
				if (!textNode || !wrap || !list) return;
				var originalHtml = String(textNode.getAttribute("data-lf-ai-original-html") || "");
				var nextHtml = innerHtmlFromEditableNode(textNode);
				var nextPlain = lfPlainFromHtml(nextHtml);
				textNode.removeAttribute("contenteditable");
				textNode.removeAttribute("spellcheck");
				textNode.removeAttribute("data-lf-ai-editing");
				textNode.removeAttribute("data-lf-ai-original-html");
				if (!commit) {
					textNode.innerHTML = originalHtml || nextHtml || "New item";
					return;
				}
				if (nextPlain === "") {
					textNode.innerHTML = originalHtml || "New item";
					return;
				}
				textNode.innerHTML = nextHtml;
				if (nextHtml !== originalHtml) {
					persistHeroProofItems(wrap, list);
				}
			}
			function startHeroProofTextEdit(textNode) {
				if (!textNode) return;
				if (String(textNode.getAttribute("data-lf-ai-editing") || "0") === "1") return;
				textNode.setAttribute("data-lf-ai-original-html", innerHtmlFromEditableNode(textNode));
				textNode.setAttribute("data-lf-ai-editing", "1");
				textNode.setAttribute("contenteditable", "true");
				textNode.setAttribute("spellcheck", "true");
				try { textNode.focus(); } catch (e) {}
				focusNodeEnd(textNode);
			}
			function bindHeroProofItemEditor(li, wrap, list) {
				if (!li || !wrap || !list) return;
				var textNode = heroProofTextNodeFromLi(li);
				if (!textNode) return;
				textNode.classList.add("lf-ai-inline-editor-ignore");
				textNode.setAttribute("title", "Click to edit checklist item");
				textNode.onmousedown = function(e){
					if (e) e.stopPropagation();
				};
				textNode.onclick = function(e){
					if (!editingEnabled) return;
					if (e) {
						e.preventDefault();
						e.stopPropagation();
					}
					startHeroProofTextEdit(textNode);
				};
				textNode.onblur = function(){
					finishHeroProofTextEdit(textNode, wrap, list, true);
				};
				textNode.onkeydown = function(e){
					var key = String((e && e.key) || "");
					if (key === "Enter") {
						e.preventDefault();
						finishHeroProofTextEdit(textNode, wrap, list, true);
						return;
					}
					if (key === "Escape") {
						e.preventDefault();
						finishHeroProofTextEdit(textNode, wrap, list, false);
					}
				};
			}
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
				var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
				if (baseSectionType(sectionType, sectionId) !== "hero") return;
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-hero-proof-controls=\"1\"],[data-lf-ai-list-remove=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				var list = wrap.querySelector(".lf-hero-split__proof .lf-block-hero__card-list")
					|| wrap.querySelector(".lf-hero-visual__media .lf-block-hero__card-list")
					|| wrap.querySelector(".lf-block-hero__card .lf-block-hero__card-list");
				if (!list) return;
				Array.prototype.slice.call(list.querySelectorAll("li")).forEach(function(node){
					node.removeAttribute("data-lf-inline-editable");
					node.removeAttribute("data-lf-inline-selector");
				});
				Array.prototype.slice.call(list.querySelectorAll("li")).forEach(function(li){
					heroProofTextNodeFromLi(li);
					Array.prototype.slice.call(li.querySelectorAll("[data-lf-ai-list-remove=\"1\"]")).forEach(function(node){
						if (node && node.parentNode) node.parentNode.removeChild(node);
					});
					var btn = createGenericRemoveButton(function(){
						if (li && li.parentNode) li.parentNode.removeChild(li);
						persistHeroProofItems(wrap, list);
					});
					li.appendChild(btn);
					bindHeroProofItemEditor(li, wrap, list);
				});
				var controls = document.createElement("div");
				controls.className = "lf-ai-checklist-controls lf-ai-inline-editor-ignore";
				controls.setAttribute("data-lf-ai-hero-proof-controls", "1");
				var addBtn = document.createElement("button");
				addBtn.type = "button";
				addBtn.className = "lf-ai-checklist-add lf-ai-inline-editor-ignore";
				addBtn.textContent = "+ Add item";
				addBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					var li = document.createElement("li");
					var textNode = document.createElement("span");
					textNode.className = "lf-block-hero__card-item-text";
					textNode.textContent = "New item";
					li.appendChild(textNode);
					li.appendChild(createGenericRemoveButton(function(){
						if (li && li.parentNode) li.parentNode.removeChild(li);
						persistHeroProofItems(wrap, list);
					}));
					list.appendChild(li);
					bindHeroProofItemEditor(li, wrap, list);
					persistHeroProofItems(wrap, list);
					startHeroProofTextEdit(textNode);
				});
				controls.appendChild(addBtn);
				if (list.parentNode) {
					list.parentNode.appendChild(controls);
				}
			});
		}
		function buildHeroTrustStripControls() {
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
				var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
				if (baseSectionType(sectionType, sectionId) !== "hero") return;
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-hero-trust-strip-controls=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				var heroRoot = wrap.querySelector(".lf-block-hero");
				if (!heroRoot) return;
				var trustHost = wrap.querySelector(".lf-hero-stack__trust, .lf-hero-form__trust, .lf-hero-visual__trust, .lf-hero-split__trust");
				if (!trustHost) return;
				var controls = document.createElement("div");
				controls.className = "lf-ai-hero-trust-strip-controls lf-ai-inline-editor-ignore";
				controls.setAttribute("data-lf-ai-hero-trust-strip-controls", "1");
				var on = String(heroRoot.getAttribute("data-lf-hero-trust-strip-setting") || "1") === "1";
				var label = document.createElement("label");
				label.style.cssText = "display:flex;align-items:center;gap:8px;cursor:pointer;margin:0;";
				var cb = document.createElement("input");
				cb.type = "checkbox";
				cb.checked = on;
				cb.addEventListener("change", function(){
					var next = cb.checked ? "1" : "0";
					var pc = persistContextFromWrap(wrap);
					setStatus("Saving trust strip setting...", false);
					$.post(lfAiFloating.ajax_url, {
						action: "lf_ai_update_hero_trust_strip",
						nonce: lfAiFloating.nonce,
						context_type: pc.context_type,
						context_id: pc.context_id,
						section_id: String(wrap.getAttribute("data-lf-section-id") || ""),
						enabled: next
					}).done(function(res){
						if (res && res.success) {
							heroRoot.setAttribute("data-lf-hero-trust-strip-setting", next);
							setStatus((res.data && res.data.message) ? res.data.message : "Saved.", false);
							window.location.reload();
						} else {
							cb.checked = !cb.checked;
							setStatus((res && res.data && res.data.message) ? res.data.message : "Save failed.", true);
						}
					}).fail(function(){
						cb.checked = !cb.checked;
						setStatus("Save failed.", true);
					});
				});
				label.appendChild(cb);
				var span = document.createElement("span");
				span.textContent = "Show homeowner trust row under CTAs (requires published reviews when on)";
				label.appendChild(span);
				controls.appendChild(label);
				trustHost.appendChild(controls);
			});
		}
		function buildTrustBadgePillsControls() {
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
				if (sectionType !== "trust_bar") return;
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-trust-pill-controls=\"1\"],[data-lf-ai-list-remove=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				var list = wrap.querySelector(".lf-trust-bar__badges");
				if (!list) return;
				Array.prototype.slice.call(list.querySelectorAll(".lf-trust-bar__badge")).forEach(function(node){
					node.removeAttribute("data-lf-inline-editable");
					node.removeAttribute("data-lf-inline-selector");
				});
				Array.prototype.slice.call(list.querySelectorAll(".lf-trust-bar__badge")).forEach(function(badge){
					var btn = createGenericRemoveButton(function(){
						if (badge && badge.parentNode) badge.parentNode.removeChild(badge);
						persistSectionLineItems(wrap, "trust_badges", simpleListItemsFromContainer(list, ".lf-trust-bar__badge"), "Saving badges...");
					});
					badge.appendChild(btn);
				});
				var controls = document.createElement("div");
				controls.className = "lf-ai-hero-pills-controls lf-ai-inline-editor-ignore";
				controls.setAttribute("data-lf-ai-trust-pill-controls", "1");
				var addBtn = document.createElement("button");
				addBtn.type = "button";
				addBtn.className = "lf-ai-hero-pill-add lf-ai-inline-editor-ignore";
				addBtn.textContent = "+ Add pill";
				addBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					var text = "";
					try { text = String(window.prompt("New badge text:", "") || "").trim(); } catch (err) { text = ""; }
					if (!text) return;
					var badge = document.createElement("span");
					badge.className = "lf-trust-bar__badge";
					badge.textContent = text;
					badge.appendChild(createGenericRemoveButton(function(){
						if (badge && badge.parentNode) badge.parentNode.removeChild(badge);
						persistSectionLineItems(wrap, "trust_badges", simpleListItemsFromContainer(list, ".lf-trust-bar__badge"), "Saving badges...");
					}));
					list.appendChild(badge);
					persistSectionLineItems(wrap, "trust_badges", simpleListItemsFromContainer(list, ".lf-trust-bar__badge"), "Saving badges...");
				});
				controls.appendChild(addBtn);
				if (list.parentNode) {
					list.parentNode.appendChild(controls);
				}
			});
		}
		function processStepValueFromLi(li) {
			if (!li) return "";
			var heading = li.querySelector(".lf-process__step-heading");
			var title = li.querySelector(".lf-process__step-title");
			var body = li.querySelector(".lf-process__step-body");
			if (title && body) {
				var titleText = heading ? String(heading.textContent || "").trim() : String(title.textContent || "").trim();
				var bodyText = String(body.textContent || "").trim();
				return bodyText ? (titleText + " || " + bodyText) : titleText;
			}
			if (title && !body) {
				var tOnly = heading ? String(heading.textContent || "").trim() : String(title.textContent || "").trim();
				if (tOnly) return tOnly;
			}
			var plain = li.querySelector(".lf-process__text");
			return plain ? String(plain.textContent || "").trim() : textFromNodeWithoutAiControls(li);
		}
		function processValuesFromList(list) {
			if (!list) return [];
			return Array.prototype.slice.call(list.querySelectorAll(".lf-process__step")).map(function(li){
				return processStepValueFromLi(li);
			}).filter(function(v){ return v !== ""; });
		}
		function processIdsFromList(list) {
			if (!list) return [];
			return Array.prototype.slice.call(list.querySelectorAll(".lf-process__step[data-lf-process-id]")).map(function(node){
				return parseInt(String(node.getAttribute("data-lf-process-id") || "0"), 10);
			}).filter(function(id){ return id > 0; });
		}
		var processPickerEl = null;
		var processPickerSearchEl = null;
		var processPickerListEl = null;
		var processLibraryRows = [];
		var processLibraryCacheStore = {};
		var processPickerWrap = null;
		var processPickerList = null;
		function loadProcessLibrary(done, forceShowAll) {
			var filter = (lfAiFloating.process_library_filter && lfAiFloating.process_library_filter.active) ? lfAiFloating.process_library_filter : null;
			var fid = (forceShowAll || !filter) ? 0 : (parseInt(String(filter.service_id || "0"), 10) || 0);
			var fslug = (forceShowAll || !filter) ? "" : String(filter.service_slug || "");
			var ckey = forceShowAll ? "all" : ("s" + fid);
			if (processLibraryCacheStore[ckey]) {
				processLibraryRows = processLibraryCacheStore[ckey];
				if (typeof done === "function") done(processLibraryRows);
				return;
			}
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_process_step_library",
				nonce: lfAiFloating.nonce,
				filter_service_id: forceShowAll ? 0 : fid,
				filter_service_slug: forceShowAll ? "" : fslug
			}).done(function(res){
				var items = (res && res.success && res.data && Array.isArray(res.data.items)) ? res.data.items : [];
				processLibraryCacheStore[ckey] = items;
				processLibraryRows = items;
				if (typeof done === "function") done(processLibraryRows);
			}).fail(function(){
				processLibraryRows = [];
				setStatus("Process step library unavailable right now.", true);
				if (typeof done === "function") done(processLibraryRows);
			});
		}
		function ensureProcessPicker() {
			if (processPickerEl) return processPickerEl;
			processPickerEl = document.createElement("div");
			processPickerEl.className = "lf-ai-faq-picker lf-ai-process-picker lf-ai-inline-editor-ignore";
			processPickerEl.hidden = true;
			processPickerEl.innerHTML = "<div class=\"lf-ai-faq-picker__card\"><div class=\"lf-ai-faq-picker__head\"><div class=\"lf-ai-faq-picker__title\">Select Process Steps from library</div><button type=\"button\" class=\"lf-ai-faq-picker__close\" data-lf-ai-process-picker-close aria-label=\"Close Process picker\">×</button></div><input type=\"text\" class=\"lf-ai-faq-picker__search\" data-lf-ai-process-picker-search placeholder=\"Search process steps...\" /><p class=\"lf-ai-process-picker__filter\" data-lf-ai-process-filter-wrap style=\"display:none;margin:8px 0 0;font-size:12px;line-height:1.45;color:#50575e;\"><span data-lf-ai-process-filter-label></span> <button type=\"button\" class=\"button button-small\" data-lf-ai-process-show-all>Show all steps</button></p><div class=\"lf-ai-faq-picker__list\" data-lf-ai-process-picker-list></div></div>";
			processPickerSearchEl = processPickerEl.querySelector("[data-lf-ai-process-picker-search]");
			processPickerListEl = processPickerEl.querySelector("[data-lf-ai-process-picker-list]");
			var closeBtn = processPickerEl.querySelector("[data-lf-ai-process-picker-close]");
			if (closeBtn) {
				closeBtn.addEventListener("click", function(e){
					e.preventDefault();
					closeProcessPicker();
				});
			}
			var showAllBtn = processPickerEl.querySelector("[data-lf-ai-process-show-all]");
			if (showAllBtn) {
				showAllBtn.addEventListener("click", function(e){
					e.preventDefault();
					loadProcessLibrary(function(){
						renderProcessPickerList(processPickerSearchEl ? processPickerSearchEl.value : "");
						var fw = processPickerEl.querySelector("[data-lf-ai-process-filter-wrap]");
						if (fw) fw.style.display = "none";
					}, true);
				});
			}
			if (processPickerSearchEl) {
				processPickerSearchEl.addEventListener("input", function(){
					renderProcessPickerList(processPickerSearchEl.value);
				});
			}
			processPickerEl.addEventListener("click", function(e){
				if (e.target === processPickerEl) closeProcessPicker();
			});
			document.body.appendChild(processPickerEl);
			return processPickerEl;
		}
		function closeProcessPicker() {
			if (!processPickerEl) return;
			processPickerEl.hidden = true;
			processPickerWrap = null;
			processPickerList = null;
			try { if (processPickerSearchEl) processPickerSearchEl.value = ""; } catch (e) {}
		}
		function addProcessItemToList(list, row) {
			if (!list || !row) return;
			var id = parseInt(String(row.id || "0"), 10);
			if (!id) return;
			var exists = list.querySelector(".lf-process__step[data-lf-process-id=\"" + id + "\"]");
			if (exists) return;
			var li = document.createElement("li");
			li.className = "lf-process__step";
			li.setAttribute("data-lf-process-id", String(id));
			var main = document.createElement("div");
			main.className = "lf-process__step-main";
			var title = document.createElement("span");
			title.className = "lf-process__step-title";
			var strongEl = document.createElement("strong");
			strongEl.className = "lf-process__step-heading";
			strongEl.textContent = String(row.title || "Step");
			title.appendChild(strongEl);
			main.appendChild(title);
			var bodyText = String(row.body || "").replace(/\s+/g, " ").trim();
			if (bodyText) {
				var body = document.createElement("span");
				body.className = "lf-process__step-body";
				body.textContent = bodyText;
				main.appendChild(body);
			}
			li.appendChild(main);
			list.appendChild(li);
			buildProcessStepControls();
			persistProcessSelection(list, "Saving selected process steps...");
		}
		function persistProcessSelection(list, savingLabel) {
			if (!list) return;
			var wrap = list.closest("[data-lf-section-wrap=\"1\"]");
			if (!wrap) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
			if (!sectionId || sectionType !== "process") return;
			var ids = processIdsFromList(list);
			var ctx = (typeof persistContextFromWrap === "function")
				? persistContextFromWrap(wrap)
				: { context_type: activeContextType, context_id: activeContextId };
			setStatus(savingLabel || "Saving selected process steps...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_update_section_lines",
				nonce: lfAiFloating.nonce,
				context_type: ctx.context_type,
				context_id: ctx.context_id,
				section_id: sectionId,
				field_key: "process_selected_ids",
				items: JSON.stringify(ids.map(function(id){ return String(id); }))
			}).done(function(res){
				if (res && res.success) {
					setStatus((res.data && res.data.message) ? res.data.message : "Selected process steps saved.", false);
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "Process selection save failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Process selection save failed.";
				setStatus(msg, true);
			});
		}
		function renderProcessPickerList(query) {
			if (!processPickerListEl) return;
			var rows = Array.isArray(processLibraryRows) ? processLibraryRows : [];
			var q = String(query || "").trim().toLowerCase();
			var selectedMap = {};
			if (processPickerList) {
				processIdsFromList(processPickerList).forEach(function(id){
					selectedMap[String(id)] = true;
				});
			}
			processPickerListEl.innerHTML = "";
			var filtered = rows.filter(function(row){
				var sidPart = Array.isArray(row.service_ids) ? row.service_ids.join(" ") : "";
				var text = (String(row.title || "") + " " + String(row.body || "") + " " + String((row.groups || []).join(" ") || "") + " " + sidPart).toLowerCase();
				return !q || text.indexOf(q) !== -1;
			});
			if (!filtered.length) {
				var empty = document.createElement("div");
				empty.className = "lf-ai-faq-picker__empty";
				var noRows = !rows.length;
				var svcFilter = lfAiFloating.process_library_filter && lfAiFloating.process_library_filter.active;
				if (svcFilter && noRows) {
					empty.textContent = "No process steps are linked to this service yet. Click “Show all steps” or assign services under Process step → Assigned services.";
				} else {
					empty.textContent = "No process steps match this search.";
				}
				processPickerListEl.appendChild(empty);
				return;
			}
			filtered.forEach(function(row){
				var item = document.createElement("div");
				item.className = "lf-ai-faq-picker__item";
				var meta = document.createElement("div");
				meta.className = "lf-ai-faq-picker__meta";
				var title = document.createElement("b");
				title.textContent = String(row.title || "Step");
				var preview = document.createElement("small");
				var previewText = String(row.body || "").replace(/\s+/g, " ").trim();
				preview.textContent = previewText.length > 140 ? (previewText.slice(0, 137) + "...") : previewText;
				meta.appendChild(title);
				meta.appendChild(preview);
				var gls = Array.isArray(row.group_labels) ? row.group_labels.filter(function(x){ return String(x || "").trim() !== ""; }) : [];
				if (gls.length) {
					var ctxEl = document.createElement("div");
					ctxEl.className = "lf-ai-faq-picker__context";
					ctxEl.textContent = "Process context: " + gls.join(", ");
					meta.appendChild(ctxEl);
				}
				var addBtn = document.createElement("button");
				addBtn.type = "button";
				addBtn.className = "lf-ai-faq-picker__add lf-ai-inline-editor-ignore";
				var selected = !!selectedMap[String(row.id || "")];
				addBtn.textContent = selected ? "Added" : "Add";
				addBtn.disabled = selected;
				addBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					if (!processPickerList) return;
					addProcessItemToList(processPickerList, row);
					renderProcessPickerList(processPickerSearchEl ? processPickerSearchEl.value : "");
				});
				item.appendChild(meta);
				item.appendChild(addBtn);
				processPickerListEl.appendChild(item);
			});
		}
		function openProcessPicker(wrap, list) {
			if (!wrap || !list) return;
			ensureProcessPicker();
			processPickerWrap = wrap;
			processPickerList = list;
			processPickerEl.hidden = false;
			if (processPickerSearchEl) processPickerSearchEl.value = "";
			loadProcessLibrary(function(){
				var fw = processPickerEl.querySelector("[data-lf-ai-process-filter-wrap]");
				var fl = processPickerEl.querySelector("[data-lf-ai-process-filter-label]");
				if (fw && lfAiFloating.process_library_filter && lfAiFloating.process_library_filter.active) {
					fw.style.display = "";
					if (fl) fl.textContent = "Showing steps for " + String(lfAiFloating.process_library_filter.service_label || "this service") + ".";
				} else if (fw) {
					fw.style.display = "none";
				}
				renderProcessPickerList("");
				try { if (processPickerSearchEl) processPickerSearchEl.focus(); } catch (e) {}
			});
		}
		function processPromptEditStep(li, wrap, list) {
			if (!li || !wrap || !list) return;
			var current = processStepValueFromLi(li);
			var next = "";
			try {
				next = String(window.prompt("Edit step text (use \"Title || Body\" for two-line step):", current) || "").trim();
			} catch (err) {
				next = "";
			}
			if (!next) return;
			var removeBtn = li.querySelector("[data-lf-ai-list-remove=\"1\"]");
			Array.prototype.slice.call(li.querySelectorAll(".lf-process__step-main,.lf-process__step-title,.lf-process__step-body,.lf-process__text")).forEach(function(node){
				if (node && node.parentNode) node.parentNode.removeChild(node);
			});
			var parts = next.split("||");
			if (parts.length > 1) {
				var main = document.createElement("div");
				main.className = "lf-process__step-main";
				var title = document.createElement("span");
				title.className = "lf-process__step-title";
				var h = document.createElement("strong");
				h.className = "lf-process__step-heading";
				h.textContent = String(parts[0] || "").trim();
				title.appendChild(h);
				main.appendChild(title);
				var bodyText = String(parts.slice(1).join("||") || "").trim();
				if (bodyText) {
					var body = document.createElement("span");
					body.className = "lf-process__step-body";
					body.textContent = bodyText;
					main.appendChild(body);
				}
				li.insertBefore(main, removeBtn || null);
			} else {
				var main2 = document.createElement("div");
				main2.className = "lf-process__step-main";
				var plain = document.createElement("span");
				plain.className = "lf-process__text";
				var h2 = document.createElement("strong");
				h2.className = "lf-process__step-heading";
				h2.textContent = next;
				plain.appendChild(h2);
				main2.appendChild(plain);
				li.insertBefore(main2, removeBtn || null);
			}
			persistSectionLineItems(wrap, "process_steps", processValuesFromList(list), "Saving process steps...");
		}
		function buildProcessStepControls() {
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
				if (sectionType !== "process") return;
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-process-controls=\"1\"],[data-lf-ai-list-remove=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				var list = wrap.querySelector(".lf-process");
				if (!list) return;
				Array.prototype.slice.call(list.querySelectorAll(".lf-process__step,.lf-process__step-main,.lf-process__step-title,.lf-process__step-body,.lf-process__text,.lf-process__step-heading")).forEach(function(node){
					node.removeAttribute("data-lf-inline-editable");
					node.removeAttribute("data-lf-inline-selector");
				});
				Array.prototype.slice.call(list.querySelectorAll(".lf-process__step")).forEach(function(li){
					li.classList.add("lf-ai-process-step");
					li.setAttribute("draggable", "true");
					li.ondragstart = function(e){
						activeProcessDragEl = li;
						li.classList.add("is-dragging");
						if (e.dataTransfer) {
							e.dataTransfer.effectAllowed = "move";
							e.dataTransfer.setData("text/plain", "process-step");
						}
					};
					li.ondragover = function(e){
						if (!activeProcessDragEl || activeProcessDragEl === li) return;
						e.preventDefault();
					};
					li.ondrop = function(e){
						if (!activeProcessDragEl || activeProcessDragEl === li) return;
						e.preventDefault();
						var rect = li.getBoundingClientRect();
						var after = e.clientY > (rect.top + rect.height / 2);
						if (after) {
							li.parentNode.insertBefore(activeProcessDragEl, li.nextSibling);
						} else {
							li.parentNode.insertBefore(activeProcessDragEl, li);
						}
						if (processIdsFromList(list).length) {
							persistProcessSelection(list, "Saving selected process steps...");
						} else {
							persistSectionLineItems(wrap, "process_steps", processValuesFromList(list), "Saving process steps...");
						}
					};
					li.ondragend = function(){
						li.classList.remove("is-dragging");
						activeProcessDragEl = null;
					};
					li.appendChild(createGenericRemoveButton(function(){
						if (li && li.parentNode) li.parentNode.removeChild(li);
						if (processIdsFromList(list).length) {
							persistProcessSelection(list, "Saving selected process steps...");
						} else {
							persistSectionLineItems(wrap, "process_steps", processValuesFromList(list), "Saving process steps...");
						}
					}));
					li.setAttribute("title", "Double-click to edit step text");
					li.ondblclick = function(e){
						var target = e && e.target && e.target.nodeType === 1 ? e.target : null;
						if (target && target.closest && target.closest("[data-lf-ai-list-remove=\"1\"]")) {
							return;
						}
						if (li.getAttribute("data-lf-process-id")) {
							// Library-driven items should be edited in the Process Steps CPT.
							return;
						}
						e.preventDefault();
						e.stopPropagation();
						processPromptEditStep(li, wrap, list);
					};
				});
				var controls = document.createElement("div");
				controls.className = "lf-ai-checklist-controls lf-ai-inline-editor-ignore";
				controls.setAttribute("data-lf-ai-process-controls", "1");
				var addBtn = document.createElement("button");
				addBtn.type = "button";
				addBtn.className = "lf-ai-checklist-add lf-ai-inline-editor-ignore";
				addBtn.textContent = "+ Add step";
				addBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					var li = document.createElement("li");
					li.className = "lf-process__step";
					var mainNew = document.createElement("div");
					mainNew.className = "lf-process__step-main";
					var text = document.createElement("span");
					text.className = "lf-process__text";
					var hn = document.createElement("strong");
					hn.className = "lf-process__step-heading";
					hn.textContent = "New step";
					text.appendChild(hn);
					mainNew.appendChild(text);
					li.appendChild(mainNew);
					list.appendChild(li);
					buildProcessStepControls();
					processPromptEditStep(li, wrap, list);
				});
				controls.appendChild(addBtn);
				var pickBtn = document.createElement("button");
				pickBtn.type = "button";
				pickBtn.className = "lf-ai-faq-add lf-ai-inline-editor-ignore";
				pickBtn.textContent = "+ Select Process Steps";
				pickBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					openProcessPicker(wrap, list);
				});
				controls.appendChild(pickBtn);
				if (list.parentNode) {
					list.parentNode.insertBefore(controls, list.nextSibling);
				}
			});
		}
		function faqIdsFromList(list) {
			if (!list) return [];
			return Array.prototype.slice.call(list.querySelectorAll(".lf-block-faq-accordion__item[data-lf-faq-id]")).map(function(node){
				return parseInt(String(node.getAttribute("data-lf-faq-id") || "0"), 10);
			}).filter(function(id){ return id > 0; });
		}
		function persistFaqSelection(list, savingLabel) {
			if (!list) return;
			var wrap = list.closest("[data-lf-section-wrap=\"1\"]");
			if (!wrap) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
			if (!sectionId || sectionType !== "faq_accordion") return;
			var ids = faqIdsFromList(list);
			var ctx = (typeof persistContextFromWrap === "function")
				? persistContextFromWrap(wrap)
				: { context_type: activeContextType, context_id: activeContextId };
			setStatus(savingLabel || "Saving selected FAQs...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_update_section_lines",
				nonce: lfAiFloating.nonce,
				context_type: ctx.context_type,
				context_id: ctx.context_id,
				section_id: sectionId,
				field_key: "faq_selected_ids",
				items: JSON.stringify(ids.map(function(id){ return String(id); }))
			}).done(function(res){
				if (res && res.success) {
					setStatus((res.data && res.data.message) ? res.data.message : "Selected FAQs saved.", false);
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "FAQ selection save failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "FAQ selection save failed.";
				setStatus(msg, true);
			});
		}
		function closeFaqPicker() {
			if (!faqPickerEl) return;
			faqPickerEl.hidden = true;
			faqPickerWrap = null;
			faqPickerList = null;
			try { if (faqPickerSearchEl) faqPickerSearchEl.value = ""; } catch (e) {}
		}
		function addFaqItemToList(list, row) {
			if (!list || !row) return;
			var faqId = parseInt(String(row.id || "0"), 10);
			if (!faqId) return;
			var exists = list.querySelector(".lf-block-faq-accordion__item[data-lf-faq-id=\"" + faqId + "\"]");
			if (exists) return;
			var item = document.createElement("details");
			item.className = "lf-block-faq-accordion__item";
			item.setAttribute("data-lf-faq-id", String(faqId));
			var q = document.createElement("summary");
			q.className = "lf-block-faq-accordion__question";
			q.textContent = String(row.question || "FAQ");
			var a = document.createElement("div");
			a.className = "lf-block-faq-accordion__answer";
			a.textContent = String(row.answer || "");
			item.appendChild(q);
			item.appendChild(a);
			list.appendChild(item);
			buildFaqReorderControls();
			persistFaqSelection(list, "Saving selected FAQs...");
		}
		function renderFaqPickerList(query) {
			if (!faqPickerListEl) return;
			var rows = Array.isArray(faqLibraryCache) ? faqLibraryCache : [];
			var q = String(query || "").trim().toLowerCase();
			var selectedMap = {};
			if (faqPickerList) {
				faqIdsFromList(faqPickerList).forEach(function(id){
					selectedMap[String(id)] = true;
				});
			}
			faqPickerListEl.innerHTML = "";
			var filtered = rows.filter(function(row){
				var text = (String(row.question || "") + " " + String(row.answer || "")).toLowerCase();
				return !q || text.indexOf(q) !== -1;
			});
			if (!filtered.length) {
				var empty = document.createElement("div");
				empty.className = "lf-ai-faq-picker__empty";
				empty.textContent = "No FAQs match this search.";
				faqPickerListEl.appendChild(empty);
				return;
			}
			filtered.forEach(function(row){
				var item = document.createElement("div");
				item.className = "lf-ai-faq-picker__item";
				var meta = document.createElement("div");
				meta.className = "lf-ai-faq-picker__meta";
				var title = document.createElement("b");
				title.textContent = String(row.question || "FAQ");
				var preview = document.createElement("small");
				var previewText = String(row.answer || "").replace(/\s+/g, " ").trim();
				preview.textContent = previewText.length > 140 ? (previewText.slice(0, 137) + "...") : previewText;
				meta.appendChild(title);
				meta.appendChild(preview);
				var addBtn = document.createElement("button");
				addBtn.type = "button";
				addBtn.className = "lf-ai-faq-picker__add lf-ai-inline-editor-ignore";
				var selected = !!selectedMap[String(row.id || "")];
				addBtn.textContent = selected ? "Added" : "Add";
				addBtn.disabled = selected;
				addBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					if (!faqPickerList) return;
					addFaqItemToList(faqPickerList, row);
					renderFaqPickerList(faqPickerSearchEl ? faqPickerSearchEl.value : "");
				});
				item.appendChild(meta);
				item.appendChild(addBtn);
				faqPickerListEl.appendChild(item);
			});
		}
		function loadFaqLibrary(done) {
			if (Array.isArray(faqLibraryCache)) {
				if (typeof done === "function") done(faqLibraryCache);
				return;
			}
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_faq_library",
				nonce: lfAiFloating.nonce
			}).done(function(res){
				faqLibraryCache = (res && res.success && res.data && Array.isArray(res.data.items)) ? res.data.items : [];
				if (typeof done === "function") done(faqLibraryCache);
			}).fail(function(){
				faqLibraryCache = [];
				setStatus("FAQ library unavailable right now.", true);
				if (typeof done === "function") done(faqLibraryCache);
			});
		}
		function ensureFaqPicker() {
			if (faqPickerEl) return faqPickerEl;
			faqPickerEl = document.createElement("div");
			faqPickerEl.className = "lf-ai-faq-picker lf-ai-inline-editor-ignore";
			faqPickerEl.hidden = true;
			faqPickerEl.innerHTML = "<div class=\"lf-ai-faq-picker__card\"><div class=\"lf-ai-faq-picker__head\"><div class=\"lf-ai-faq-picker__title\">Select FAQs from library</div><button type=\"button\" class=\"lf-ai-faq-picker__close\" data-lf-ai-faq-picker-close aria-label=\"Close FAQ picker\">×</button></div><input type=\"text\" class=\"lf-ai-faq-picker__search\" data-lf-ai-faq-picker-search placeholder=\"Search FAQs...\" /><div class=\"lf-ai-faq-picker__list\" data-lf-ai-faq-picker-list></div></div>";
			faqPickerSearchEl = faqPickerEl.querySelector("[data-lf-ai-faq-picker-search]");
			faqPickerListEl = faqPickerEl.querySelector("[data-lf-ai-faq-picker-list]");
			var closeBtn = faqPickerEl.querySelector("[data-lf-ai-faq-picker-close]");
			if (closeBtn) {
				closeBtn.addEventListener("click", function(e){
					e.preventDefault();
					closeFaqPicker();
				});
			}
			if (faqPickerSearchEl) {
				faqPickerSearchEl.addEventListener("input", function(){
					renderFaqPickerList(faqPickerSearchEl.value);
				});
			}
			faqPickerEl.addEventListener("click", function(e){
				if (e.target === faqPickerEl) closeFaqPicker();
			});
			document.body.appendChild(faqPickerEl);
			return faqPickerEl;
		}
		function openFaqPicker(wrap, list) {
			if (!wrap || !list) return;
			ensureFaqPicker();
			faqPickerWrap = wrap;
			faqPickerList = list;
			faqPickerEl.hidden = false;
			if (faqPickerSearchEl) faqPickerSearchEl.value = "";
			loadFaqLibrary(function(){
				renderFaqPickerList("");
				try { if (faqPickerSearchEl) faqPickerSearchEl.focus(); } catch (e) {}
			});
		}
		function buildFaqReorderControls() {
			Array.prototype.slice.call(document.querySelectorAll(".lf-block-faq-accordion__list")).forEach(function(list){
				var wrap = list.closest("[data-lf-section-wrap=\"1\"]");
				var sectionType = String(wrap && wrap.getAttribute ? (wrap.getAttribute("data-lf-section-type") || "") : "");
				if (!wrap || sectionType !== "faq_accordion") return;
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-faq-controls=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				Array.prototype.slice.call(list.querySelectorAll(".lf-block-faq-accordion__item,.lf-block-faq-accordion__question,.lf-block-faq-accordion__answer")).forEach(function(node){
					node.removeAttribute("data-lf-inline-editable");
					node.removeAttribute("data-lf-inline-selector");
				});
				Array.prototype.slice.call(list.querySelectorAll(".lf-block-faq-accordion__item[data-lf-faq-id]")).forEach(function(item){
					Array.prototype.slice.call(item.querySelectorAll("[data-lf-ai-list-remove=\"1\"]")).forEach(function(node){
						if (node && node.parentNode) node.parentNode.removeChild(node);
					});
					item.setAttribute("draggable", "true");
					item.ondragstart = function(e){
						activeFaqDragEl = item;
						item.classList.add("is-dragging");
						if (e.dataTransfer) {
							e.dataTransfer.effectAllowed = "move";
							e.dataTransfer.setData("text/plain", String(item.getAttribute("data-lf-faq-id") || ""));
						}
					};
					item.ondragover = function(e){
						if (!activeFaqDragEl || activeFaqDragEl === item) return;
						e.preventDefault();
					};
					item.ondrop = function(e){
						if (!activeFaqDragEl || activeFaqDragEl === item) return;
						e.preventDefault();
						var rect = item.getBoundingClientRect();
						var after = e.clientY > (rect.top + rect.height / 2);
						if (after) {
							item.parentNode.insertBefore(activeFaqDragEl, item.nextSibling);
						} else {
							item.parentNode.insertBefore(activeFaqDragEl, item);
						}
						persistFaqSelection(list, "Saving selected FAQs...");
					};
					item.ondragend = function(){
						item.classList.remove("is-dragging");
						activeFaqDragEl = null;
					};
					var removeBtn = createGenericRemoveButton(function(){
						if (item && item.parentNode) item.parentNode.removeChild(item);
						persistFaqSelection(list, "Saving selected FAQs...");
					});
					item.appendChild(removeBtn);
				});
				var controls = document.createElement("div");
				controls.className = "lf-ai-faq-controls lf-ai-inline-editor-ignore";
				controls.setAttribute("data-lf-ai-faq-controls", "1");
				var addBtn = document.createElement("button");
				addBtn.type = "button";
				addBtn.className = "lf-ai-faq-add lf-ai-inline-editor-ignore";
				addBtn.textContent = "+ Select FAQs";
				addBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					openFaqPicker(wrap, list);
				});
				controls.appendChild(addBtn);
				if (list.parentNode) {
					list.parentNode.insertBefore(controls, list.nextSibling);
				}
			});
		}
		function benefitLinesFromGrid(grid) {
			if (!grid) return [];
			return Array.prototype.slice.call(grid.querySelectorAll(".lf-benefits__card")).map(function(card){
				var t = card.querySelector(".lf-benefits__title");
				var b = card.querySelector(".lf-benefits__desc");
				var titleHtml = t ? innerHtmlFromEditableNode(t) : "";
				var bodyHtml = b ? innerHtmlFromEditableNode(b) : "";
				if (lfPlainFromHtml(titleHtml) || lfPlainFromHtml(bodyHtml)) {
					if (!lfPlainFromHtml(bodyHtml)) return titleHtml;
					return titleHtml + " || " + bodyHtml;
				}
				var raw = String(card.getAttribute("data-lf-benefit-line") || "").trim();
				return raw;
			}).filter(function(v){ return lfPlainFromHtml(v) !== ""; });
		}
		function buildBenefitsReorderControls() {
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				if (String(wrap.getAttribute("data-lf-section-type") || "") !== "benefits") return;
				var grid = wrap.querySelector(".lf-benefits");
				if (!grid) return;
				Array.prototype.slice.call(grid.querySelectorAll(".lf-benefits__card")).forEach(function(card){
					card.removeAttribute("draggable");
					card.classList.remove("lf-ai-benefit-card-drag", "is-dragging");
					card.ondragstart = null;
					card.ondragover = null;
					card.ondrop = null;
					card.ondragend = null;
				});
				Array.prototype.slice.call(grid.querySelectorAll(".lf-benefits__card")).forEach(function(card){
					card.setAttribute("draggable", "true");
					card.classList.add("lf-ai-benefit-card-drag", "lf-ai-inline-editor-ignore");
					card.ondragstart = function(e){
						activeBenefitDragEl = card;
						card.classList.add("is-dragging");
						if (e.dataTransfer) {
							e.dataTransfer.effectAllowed = "move";
							e.dataTransfer.setData("text/plain", "benefit");
						}
					};
					card.ondragover = function(e){
						if (!activeBenefitDragEl || activeBenefitDragEl === card) return;
						e.preventDefault();
					};
					card.ondrop = function(e){
						if (!activeBenefitDragEl || activeBenefitDragEl === card) return;
						e.preventDefault();
						var rect = card.getBoundingClientRect();
						var after = e.clientY > (rect.top + rect.height / 2);
						if (after) {
							grid.insertBefore(activeBenefitDragEl, card.nextSibling);
						} else {
							grid.insertBefore(activeBenefitDragEl, card);
						}
						persistSectionLineItems(wrap, "benefits_items", benefitLinesFromGrid(grid), "Saving benefit order...");
					};
					card.ondragend = function(){
						card.classList.remove("is-dragging");
						activeBenefitDragEl = null;
					};
				});
			});
		}
		function buildBenefitsTextEditors() {
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				if (String(wrap.getAttribute("data-lf-section-type") || "") !== "benefits") return;
				var grid = wrap.querySelector(".lf-benefits");
				if (!grid) return;
				function persistGrid() {
					Array.prototype.slice.call(grid.querySelectorAll(".lf-benefits__card")).forEach(function(card){
						var t = card.querySelector(".lf-benefits__title");
						var b = card.querySelector(".lf-benefits__desc");
						var titleHtml = t ? innerHtmlFromEditableNode(t) : "";
						var bodyHtml = b ? innerHtmlFromEditableNode(b) : "";
						var line = lfPlainFromHtml(bodyHtml) ? (titleHtml + " || " + bodyHtml) : titleHtml;
						card.setAttribute("data-lf-benefit-line", line);
					});
					persistSectionLineItems(wrap, "benefits_items", benefitLinesFromGrid(grid), "Saving benefits...");
				}
				Array.prototype.slice.call(grid.querySelectorAll(".lf-benefits__card")).forEach(function(card){
					var title = card.querySelector(".lf-benefits__title");
					var desc = card.querySelector(".lf-benefits__desc");
					if (!title || !desc) return;
					if (title.getAttribute("data-lf-benefits-editor-bound") === "1") return;
					title.removeAttribute("data-lf-inline-editable");
					title.removeAttribute("data-lf-inline-selector");
					desc.removeAttribute("data-lf-inline-editable");
					desc.removeAttribute("data-lf-inline-selector");
					title.setAttribute("data-lf-benefits-editor-bound", "1");
					desc.setAttribute("data-lf-benefits-editor-bound", "1");
					title.classList.add("lf-ai-benefit-editable");
					desc.classList.add("lf-ai-benefit-editable");
					title.setAttribute("title", "Click to edit headline");
					desc.setAttribute("title", "Click to edit description");
					title.addEventListener("mousedown", function(e){ e.stopPropagation(); });
					desc.addEventListener("mousedown", function(e){ e.stopPropagation(); });
					title.addEventListener("click", function(e){
						if (!editingEnabled) return;
						e.preventDefault();
						e.stopPropagation();
						if (String(title.getAttribute("data-lf-ai-editing") || "") === "1") return;
						title.setAttribute("data-lf-ai-original-html", innerHtmlFromEditableNode(title));
						title.setAttribute("data-lf-ai-editing", "1");
						title.setAttribute("contenteditable", "true");
						try { title.focus(); } catch (err) {}
					});
					desc.addEventListener("click", function(e){
						if (!editingEnabled) return;
						e.preventDefault();
						e.stopPropagation();
						if (String(desc.getAttribute("data-lf-ai-editing") || "") === "1") return;
						desc.setAttribute("data-lf-ai-original-html", innerHtmlFromEditableNode(desc));
						desc.setAttribute("data-lf-ai-editing", "1");
						desc.setAttribute("contenteditable", "true");
						try { desc.focus(); } catch (err2) {}
					});
					function finishTitle() {
						title.removeAttribute("contenteditable");
						title.removeAttribute("data-lf-ai-editing");
						title.removeAttribute("data-lf-ai-original-html");
						var panelOpen = false;
						try {
							panelOpen = !!($linkRoot && $linkRoot.length && !$linkRoot.find("[data-lf-ai-inline-link-panel]").prop("hidden"));
						} catch (ePanel) {
							panelOpen = false;
						}
						if (panelOpen) {
							return;
						}
						persistGrid();
					}
					function finishDesc() {
						desc.removeAttribute("contenteditable");
						desc.removeAttribute("data-lf-ai-editing");
						desc.removeAttribute("data-lf-ai-original-html");
						var panelOpen = false;
						try {
							panelOpen = !!($linkRoot && $linkRoot.length && !$linkRoot.find("[data-lf-ai-inline-link-panel]").prop("hidden"));
						} catch (ePanel2) {
							panelOpen = false;
						}
						if (panelOpen) {
							return;
						}
						persistGrid();
					}
					title.addEventListener("blur", finishTitle);
					desc.addEventListener("blur", finishDesc);
					title.addEventListener("keydown", function(e){
						var k = String((e && e.key) || "");
						if (k === "Enter") {
							e.preventDefault();
							finishTitle();
						}
						if (k === "Escape") {
							e.preventDefault();
							var oh = title.getAttribute("data-lf-ai-original-html");
							title.removeAttribute("contenteditable");
							title.removeAttribute("data-lf-ai-editing");
							if (title.hasAttribute("data-lf-ai-original-html")) {
								title.innerHTML = oh || "";
							}
							title.removeAttribute("data-lf-ai-original-html");
						}
					});
					desc.addEventListener("keydown", function(e){
						var k = String((e && e.key) || "");
						if (k === "Escape") {
							e.preventDefault();
							var ohd = desc.getAttribute("data-lf-ai-original-html");
							desc.removeAttribute("contenteditable");
							desc.removeAttribute("data-lf-ai-editing");
							if (desc.hasAttribute("data-lf-ai-original-html")) {
								desc.innerHTML = ohd || "";
							}
							desc.removeAttribute("data-lf-ai-original-html");
						}
					});
				});
			});
		}
		function buildServiceIntroReorderControls() {
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				if (String(wrap.getAttribute("data-lf-section-type") || "") !== "service_intro") return;
				var grid = wrap.querySelector(".lf-block-service-intro__grid");
				if (!grid) return;
				Array.prototype.slice.call(grid.querySelectorAll(".lf-block-service-intro__card")).forEach(function(card){
					card.removeAttribute("draggable");
					card.classList.remove("lf-ai-service-intro-card-drag", "is-dragging");
					card.ondragstart = null;
					card.ondragover = null;
					card.ondrop = null;
					card.ondragend = null;
				});
				Array.prototype.slice.call(grid.querySelectorAll(".lf-block-service-intro__card .lf-block-service-intro__card-title, .lf-block-service-intro__card .lf-block-service-intro__link")).forEach(function(node){
					node.removeAttribute("data-lf-inline-editable");
					node.removeAttribute("data-lf-inline-selector");
				});
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-service-intro-actions=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-service-intro-remove=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				Array.prototype.slice.call(grid.querySelectorAll(".lf-block-service-intro__card")).forEach(function(card){
					var rm = document.createElement("button");
					rm.type = "button";
					rm.className = "lf-ai-card-remove lf-ai-inline-editor-ignore";
					rm.setAttribute("data-lf-ai-service-intro-remove", "1");
					rm.textContent = "×";
					rm.setAttribute("title", "Remove card");
					rm.setAttribute("aria-label", "Remove card");
					rm.addEventListener("click", function(e){
						e.preventDefault();
						e.stopPropagation();
						if (card && card.parentNode) {
							card.parentNode.removeChild(card);
						}
						var ids = Array.prototype.slice.call(grid.querySelectorAll(".lf-block-service-intro__card[data-lf-service-id]")).map(function(n){
							return String(n.getAttribute("data-lf-service-id") || "").trim();
						}).filter(function(v){ return v !== ""; });
						persistSectionLineItems(wrap, "service_intro_service_ids", ids, "Saving services...");
					});
					card.insertBefore(rm, card.firstChild);
					card.setAttribute("draggable", "true");
					card.classList.add("lf-ai-service-intro-card-drag", "lf-ai-inline-editor-ignore");
					card.ondragstart = function(e){
						activeServiceIntroDragEl = card;
						card.classList.add("is-dragging");
						if (e.dataTransfer) {
							e.dataTransfer.effectAllowed = "move";
							e.dataTransfer.setData("text/plain", "service-intro");
						}
					};
					card.ondragover = function(e){
						if (!activeServiceIntroDragEl || activeServiceIntroDragEl === card) return;
						e.preventDefault();
					};
					card.ondrop = function(e){
						if (!activeServiceIntroDragEl || activeServiceIntroDragEl === card) return;
						e.preventDefault();
						var rect = card.getBoundingClientRect();
						var after = e.clientY > (rect.top + rect.height / 2);
						if (after) {
							grid.insertBefore(activeServiceIntroDragEl, card.nextSibling);
						} else {
							grid.insertBefore(activeServiceIntroDragEl, card);
						}
						var ids = Array.prototype.slice.call(grid.querySelectorAll(".lf-block-service-intro__card[data-lf-service-id]")).map(function(n){
							return String(n.getAttribute("data-lf-service-id") || "").trim();
						}).filter(function(v){ return v !== ""; });
						persistSectionLineItems(wrap, "service_intro_service_ids", ids, "Saving service order...");
					};
					card.ondragend = function(){
						card.classList.remove("is-dragging");
						activeServiceIntroDragEl = null;
					};
				});
				var introBar = document.createElement("div");
				introBar.className = "lf-ai-checklist-controls lf-ai-inline-editor-ignore";
				introBar.setAttribute("data-lf-ai-service-intro-actions", "1");
				var addIntro = document.createElement("button");
				addIntro.type = "button";
				addIntro.className = "lf-ai-checklist-add lf-ai-inline-editor-ignore";
				addIntro.textContent = "+ Add service";
				addIntro.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					openServicePickerForIntro(wrap, grid);
				});
				introBar.appendChild(addIntro);
				if (grid.nextSibling) {
					grid.parentNode.insertBefore(introBar, grid.nextSibling);
				} else {
					grid.parentNode.appendChild(introBar);
				}
			});
		}
		function wireOneServiceIntroMedia(media, sid, card, wrap) {
			if (!media || !sid || !card) return;
			Array.prototype.slice.call(media.querySelectorAll("[data-lf-service-intro-img-remove=\"1\"]")).forEach(function(n){
				if (n && n.parentNode) n.parentNode.removeChild(n);
			});
			Array.prototype.slice.call(media.querySelectorAll("[data-lf-service-intro-media-add=\"1\"]")).forEach(function(n){
				if (n && n.parentNode) n.parentNode.removeChild(n);
			});
			var img = media.querySelector("img.lf-block-service-intro__image");
			if (img) {
				var rm = document.createElement("button");
				rm.type = "button";
				rm.className = "lf-service-intro-thumb-remove lf-ai-inline-editor-ignore";
				rm.setAttribute("data-lf-service-intro-img-remove", "1");
				rm.textContent = "×";
				rm.setAttribute("title", "Remove image");
				rm.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					$.post(lfAiFloating.ajax_url, {
						action: "lf_ai_set_service_thumbnail",
						nonce: lfAiFloating.nonce,
						service_post_id: sid,
						attachment_id: 0
					}).done(function(res){
						if (res && res.success) {
							media.innerHTML = "";
							wireOneServiceIntroMedia(media, sid, card, wrap);
							setStatus((res.data && res.data.message) ? res.data.message : "Image removed.", false);
						} else {
							setStatus((res && res.data && res.data.message) ? res.data.message : "Could not remove image.", true);
						}
					}).fail(function(xhr){
						var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Could not remove image.";
						setStatus(msg, true);
					});
				});
				media.appendChild(rm);
				return;
			}
			var ph = document.createElement("button");
			ph.type = "button";
			ph.className = "lf-block-service-intro__media-add lf-ai-inline-editor-ignore";
			ph.setAttribute("data-lf-service-intro-media-add", "1");
			ph.textContent = "+ Add image";
			ph.setAttribute("title", "Set featured image on this service");
			ph.addEventListener("click", function(e){
				e.preventDefault();
				e.stopPropagation();
				if (!(window.wp && wp.media)) {
					setStatus("Image library is not available on this screen.", true);
					return;
				}
				var frame = wp.media({ library: { type: "image" }, multiple: false });
				frame.on("select", function(){
					var att = frame.state().get("selection").first();
					var aid = att ? parseInt(String(att.id || "0"), 10) : 0;
					if (!aid) return;
					$.post(lfAiFloating.ajax_url, {
						action: "lf_ai_set_service_thumbnail",
						nonce: lfAiFloating.nonce,
						service_post_id: sid,
						attachment_id: aid
					}).done(function(res){
						if (res && res.success && res.data && res.data.thumbnail_url) {
							media.innerHTML = "";
							var nimg = document.createElement("img");
							nimg.className = "lf-block-service-intro__image";
							nimg.src = String(res.data.thumbnail_url);
							nimg.alt = "";
							nimg.setAttribute("loading", "lazy");
							media.appendChild(nimg);
							wireOneServiceIntroMedia(media, sid, card, wrap);
							setStatus((res.data && res.data.message) ? res.data.message : "Image saved.", false);
						} else {
							setStatus((res && res.data && res.data.message) ? res.data.message : "Image save failed.", true);
						}
					}).fail(function(xhr){
						var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Image save failed.";
						setStatus(msg, true);
					});
				});
				frame.open();
			});
			media.appendChild(ph);
		}
		function wireServiceIntroThumbnailsForWrap(wrap) {
			if (!wrap) return;
			Array.prototype.slice.call(wrap.querySelectorAll(".lf-block-service-intro__card[data-lf-service-id]")).forEach(function(card){
				var sid = String(card.getAttribute("data-lf-service-id") || "").trim();
				if (!sid) return;
				var media = card.querySelector(".lf-block-service-intro__media");
				if (!media) {
					media = document.createElement("div");
					media.className = "lf-block-service-intro__media";
					var head = card.querySelector(".lf-block-service-intro__card-head");
					if (head) {
						if (head.nextSibling) {
							card.insertBefore(media, head.nextSibling);
						} else {
							card.appendChild(media);
						}
					} else {
						card.insertBefore(media, card.firstChild);
					}
				}
				wireOneServiceIntroMedia(media, sid, card, wrap);
			});
		}
		function buildServiceIntroCardEditors() {
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				if (String(wrap.getAttribute("data-lf-section-type") || "") !== "service_intro") return;
				Array.prototype.slice.call(wrap.querySelectorAll(".lf-block-service-intro__card[data-lf-service-id] .lf-block-service-intro__desc")).forEach(function(desc){
					var card = desc.closest(".lf-block-service-intro__card");
					var sid = card ? String(card.getAttribute("data-lf-service-id") || "").trim() : "";
					if (!sid) return;
					desc.setAttribute("data-lf-inline-editable", "1");
					desc.setAttribute("data-lf-inline-field-key", "lf_service_short_desc");
					desc.setAttribute("data-lf-service-post-id", sid);
					try {
						desc.setAttribute("data-lf-inline-selector", buildInlineSelector(desc));
					} catch (eSel) {}
					var sw = desc.closest("[data-lf-section-wrap=\"1\"][data-lf-section-id]");
					if (sw) {
						desc.setAttribute("data-lf-inline-section-id", String(sw.getAttribute("data-lf-section-id") || ""));
					}
					desc.setAttribute("title", "Click to edit card description");
				});
				wireServiceIntroThumbnailsForWrap(wrap);
			});
		}
		function closeServicePicker() {
			if (servicePickerEl) servicePickerEl.hidden = true;
			servicePickerWrap = null;
			try { if (servicePickerSearchEl) servicePickerSearchEl.value = ""; } catch (eSp) {}
		}
		function loadServiceLibrary(done) {
			if (Array.isArray(serviceLibraryCache)) {
				if (typeof done === "function") done(serviceLibraryCache);
				return;
			}
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_service_library",
				nonce: lfAiFloating.nonce
			}).done(function(res){
				serviceLibraryCache = (res && res.success && res.data && Array.isArray(res.data.items)) ? res.data.items : [];
				if (typeof done === "function") done(serviceLibraryCache);
			}).fail(function(){
				serviceLibraryCache = [];
				setStatus("Service library unavailable.", true);
				if (typeof done === "function") done(serviceLibraryCache);
			});
		}
		function ensureServicePicker() {
			if (servicePickerEl) return servicePickerEl;
			servicePickerEl = document.createElement("div");
			servicePickerEl.className = "lf-ai-faq-picker lf-ai-inline-editor-ignore";
			servicePickerEl.hidden = true;
			servicePickerEl.innerHTML = "<div class=\"lf-ai-faq-picker__card\"><div class=\"lf-ai-faq-picker__head\"><div class=\"lf-ai-faq-picker__title\">Add a service card</div><button type=\"button\" class=\"lf-ai-faq-picker__close\" data-lf-ai-service-picker-close aria-label=\"Close\">×</button></div><input type=\"text\" class=\"lf-ai-faq-picker__search\" data-lf-ai-service-picker-search placeholder=\"Search services...\" /><div class=\"lf-ai-faq-picker__list\" data-lf-ai-service-picker-list></div></div>";
			servicePickerSearchEl = servicePickerEl.querySelector("[data-lf-ai-service-picker-search]");
			servicePickerListEl = servicePickerEl.querySelector("[data-lf-ai-service-picker-list]");
			var cbtn = servicePickerEl.querySelector("[data-lf-ai-service-picker-close]");
			if (cbtn) {
				cbtn.addEventListener("click", function(e){
					e.preventDefault();
					closeServicePicker();
				});
			}
			if (servicePickerSearchEl) {
				servicePickerSearchEl.addEventListener("input", function(){
					renderServicePickerList(servicePickerSearchEl.value);
				});
			}
			servicePickerEl.addEventListener("click", function(e){
				if (e.target === servicePickerEl) closeServicePicker();
			});
			document.body.appendChild(servicePickerEl);
			return servicePickerEl;
		}
		function lfDecodeHtmlEntities(str) {
			var t = document.createElement("textarea");
			t.innerHTML = String(str || "");
			return String(t.value || "");
		}
		function renderServicePickerList(query) {
			if (!servicePickerListEl) return;
			var rows = Array.isArray(serviceLibraryCache) ? serviceLibraryCache : [];
			var q = String(query || "").trim().toLowerCase();
			var onGrid = servicePickerWrap ? servicePickerWrap.grid : null;
			var existing = {};
			if (onGrid) {
				Array.prototype.slice.call(onGrid.querySelectorAll(".lf-block-service-intro__card[data-lf-service-id]")).forEach(function(c){
					existing[String(c.getAttribute("data-lf-service-id") || "")] = true;
				});
			}
			servicePickerListEl.innerHTML = "";
			var filtered = rows.filter(function(row){
				var t = String(row.title || "").toLowerCase();
				return !q || t.indexOf(q) !== -1;
			});
			if (!filtered.length) {
				var em = document.createElement("div");
				em.className = "lf-ai-faq-picker__empty";
				em.textContent = "No services match.";
				servicePickerListEl.appendChild(em);
				return;
			}
			filtered.forEach(function(row){
				var sid = String(row.id || "");
				var item = document.createElement("div");
				item.className = "lf-ai-faq-picker__item";
				var meta = document.createElement("div");
				meta.className = "lf-ai-faq-picker__meta";
				var title = document.createElement("b");
				title.textContent = lfDecodeHtmlEntities(String(row.title || "Service"));
				meta.appendChild(title);
				var addBtn = document.createElement("button");
				addBtn.type = "button";
				addBtn.className = "lf-ai-faq-picker__add lf-ai-inline-editor-ignore";
				var already = !!existing[sid];
				addBtn.textContent = already ? "Added" : "Add";
				addBtn.disabled = already;
				addBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					if (!servicePickerWrap || !servicePickerWrap.wrap || !servicePickerWrap.grid) return;
					if (already) return;
					appendServiceIntroCardFromRow(servicePickerWrap.grid, row);
					closeServicePicker();
					buildServiceIntroReorderControls();
					buildServiceIntroCardEditors();
					var ids = Array.prototype.slice.call(servicePickerWrap.grid.querySelectorAll(".lf-block-service-intro__card[data-lf-service-id]")).map(function(n){
						return String(n.getAttribute("data-lf-service-id") || "").trim();
					}).filter(function(v){ return v !== ""; });
					persistSectionLineItems(servicePickerWrap.wrap, "service_intro_service_ids", ids, "Saving services...");
				});
				item.appendChild(meta);
				item.appendChild(addBtn);
				servicePickerListEl.appendChild(item);
			});
		}
		function appendServiceIntroCardFromRow(grid, row) {
			if (!grid || !row) return;
			var sid = String(row.id || "");
			if (!sid) return;
			var art = document.createElement("article");
			art.className = "lf-block-service-intro__card lf-card lf-card--interactive";
			art.setAttribute("data-lf-service-id", sid);
			var head = document.createElement("div");
			head.className = "lf-block-service-intro__card-head";
			var h3 = document.createElement("h3");
			h3.className = "lf-block-service-intro__card-title";
			h3.textContent = lfDecodeHtmlEntities(String(row.title || "Service"));
			head.appendChild(h3);
			var media = document.createElement("div");
			media.className = "lf-block-service-intro__media";
			var thumb = String(row.thumbnail_url || "").trim();
			if (thumb) {
				var img = document.createElement("img");
				img.className = "lf-block-service-intro__image";
				img.src = thumb;
				img.alt = "";
				img.setAttribute("loading", "lazy");
				media.appendChild(img);
			}
			var desc = document.createElement("p");
			desc.className = "lf-block-service-intro__desc";
			desc.textContent = "Short overview — click to edit.";
			var link = document.createElement("a");
			link.className = "lf-block-service-intro__link";
			link.href = String(row.permalink || "#");
			link.textContent = "Learn more";
			art.appendChild(head);
			art.appendChild(media);
			art.appendChild(desc);
			art.appendChild(link);
			grid.appendChild(art);
		}
		function openServicePickerForIntro(wrap, grid) {
			ensureServicePicker();
			servicePickerWrap = { wrap: wrap, grid: grid };
			loadServiceLibrary(function(){
				renderServicePickerList("");
				if (servicePickerEl) servicePickerEl.hidden = false;
				try { if (servicePickerSearchEl) servicePickerSearchEl.focus(); } catch (eF) {}
			});
		}
		function closeSectionGridPicker() {
			if (sectionGridPickerEl) sectionGridPickerEl.hidden = true;
			sectionGridPickerWrap = null;
			sectionGridPickerPatch = "";
		}
		function openSectionGridPicker(wrap) {
			if (!wrap) return;
			var st = String(wrap.getAttribute("data-lf-section-type") || "");
			var patch = st === "benefits" ? "set_benefits_grid_columns" : "set_service_intro_grid_columns";
			if (st !== "benefits" && st !== "service_intro") return;
			if (!sectionGridPickerEl) {
				sectionGridPickerEl = document.createElement("div");
				sectionGridPickerEl.className = "lf-ai-benefits-cta-picker lf-ai-inline-editor-ignore";
				sectionGridPickerEl.hidden = true;
				var card = document.createElement("div");
				card.className = "lf-ai-benefits-cta-picker__card";
				var head = document.createElement("div");
				head.className = "lf-ai-section-bg-picker__head";
				head.innerHTML = "<span class=\"lf-ai-section-bg-picker__title\">Columns</span>";
				var sub = document.createElement("p");
				sub.style.margin = "0";
				sub.style.fontSize = "12px";
				sub.style.color = "#64748b";
				sub.textContent = "Desktop grid width (2, 3, or 4 columns).";
				var row = document.createElement("div");
				row.className = "lf-ai-benefits-cta-picker__row";
				row.setAttribute("data-lf-grid-cols-row", "1");
				[2, 3, 4].forEach(function(n){
					var b = document.createElement("button");
					b.type = "button";
					b.className = "lf-ai-benefits-cta-picker__btn";
					b.textContent = String(n);
					b.setAttribute("data-lf-grid-cols", String(n));
					b.addEventListener("click", function(e){
						e.preventDefault();
						var w = sectionGridPickerWrap;
						var p = sectionGridPickerPatch;
						closeSectionGridPicker();
						if (w && p) {
							persistSectionStyle(w, p, { grid_columns: String(n) });
						}
					});
					row.appendChild(b);
				});
				var cx = document.createElement("button");
				cx.type = "button";
				cx.className = "lf-ai-benefits-cta-picker__btn";
				cx.textContent = "Cancel";
				cx.addEventListener("click", function(e){
					e.preventDefault();
					closeSectionGridPicker();
				});
				card.appendChild(head);
				card.appendChild(sub);
				card.appendChild(row);
				card.appendChild(cx);
				sectionGridPickerEl.appendChild(card);
				sectionGridPickerEl.addEventListener("click", function(e){
					if (e.target === sectionGridPickerEl) closeSectionGridPicker();
				});
				document.body.appendChild(sectionGridPickerEl);
			}
			sectionGridPickerWrap = wrap;
			sectionGridPickerPatch = patch;
			sectionGridPickerEl.hidden = false;
		}
		function renumberBenefitIconIndices(grid) {
			if (!grid) return;
			Array.prototype.slice.call(grid.querySelectorAll(".lf-benefits__card")).forEach(function(card, idx){
				var ic = card.querySelector(".lf-benefits__icon[data-lf-benefit-icon-index]");
				if (ic) ic.setAttribute("data-lf-benefit-icon-index", String(idx));
			});
		}
		function buildBenefitsGridChrome() {
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				if (String(wrap.getAttribute("data-lf-section-type") || "") !== "benefits") return;
				var grid = wrap.querySelector(".lf-benefits");
				if (!grid) return;
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-benefits-grid-actions=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				Array.prototype.slice.call(grid.querySelectorAll("[data-lf-ai-benefit-remove=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				Array.prototype.slice.call(grid.querySelectorAll(".lf-benefits__card")).forEach(function(card){
					var btn = document.createElement("button");
					btn.type = "button";
					btn.className = "lf-ai-card-remove lf-ai-inline-editor-ignore";
					btn.setAttribute("data-lf-ai-benefit-remove", "1");
					btn.textContent = "×";
					btn.setAttribute("title", "Remove card");
					btn.setAttribute("aria-label", "Remove card");
					btn.addEventListener("click", function(e){
						e.preventDefault();
						e.stopPropagation();
						if (card && card.parentNode) {
							card.parentNode.removeChild(card);
						}
						persistSectionLineItems(wrap, "benefits_items", benefitLinesFromGrid(grid), "Saving benefits...");
					});
					card.insertBefore(btn, card.firstChild);
				});
				var bar = document.createElement("div");
				bar.className = "lf-ai-benefits-grid-actions lf-ai-inline-editor-ignore";
				bar.setAttribute("data-lf-ai-benefits-grid-actions", "1");
				var addC = document.createElement("button");
				addC.type = "button";
				addC.className = "lf-ai-checklist-add lf-ai-inline-editor-ignore";
				addC.textContent = "+ Add benefit card";
				addC.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					var c = document.createElement("div");
					c.className = "lf-benefits__card";
					c.setAttribute("data-lf-benefit-line", "New headline || New supporting text");
					var ht = document.createElement("h3");
					ht.className = "lf-benefits__title";
					ht.textContent = "New headline";
					var bd = document.createElement("p");
					bd.className = "lf-benefits__desc";
					bd.textContent = "New supporting text";
					if (grid.querySelector(".lf-benefits__icon")) {
						var icNew = document.createElement("span");
						icNew.className = "lf-benefits__icon lf-benefits__icon--empty";
						icNew.setAttribute("aria-hidden", "true");
						icNew.setAttribute("data-lf-benefit-icon-index", "0");
						c.appendChild(icNew);
					}
					c.appendChild(ht);
					c.appendChild(bd);
					renumberBenefitIconIndices(grid);
					var act = grid.querySelector(".lf-benefits__actions");
					var pts = grid.querySelector(".lf-benefits__points");
					if (act) {
						grid.insertBefore(c, act);
					} else if (pts) {
						grid.insertBefore(c, pts);
					} else {
						grid.appendChild(c);
					}
					buildBenefitsReorderControls();
					buildBenefitsTextEditors();
					buildBenefitsIconEditors();
					buildBenefitsGridChrome();
					persistSectionLineItems(wrap, "benefits_items", benefitLinesFromGrid(grid), "Saving benefits...");
				});
				bar.appendChild(addC);
				grid.parentNode.insertBefore(bar, grid.nextSibling);
				renumberBenefitIconIndices(grid);
			});
		}
		function trustLayoutFromWrap(wrap) {
			var block = wrap ? wrap.querySelector(".lf-block-trust-reviews") : null;
			if (!block || !block.classList) return "slider";
			if (block.classList.contains("lf-block-trust-reviews--slider")) return "slider";
			if (block.classList.contains("lf-block-trust-reviews--masonry")) return "masonry";
			if (block.classList.contains("lf-block-trust-reviews--grid")) return "grid";
			return "grid";
		}
		function nextTrustLayout(current) {
			var cycle = ["slider", "masonry", "grid"];
			var idx = cycle.indexOf(String(current || "slider"));
			if (idx < 0) idx = 0;
			return cycle[(idx + 1) % cycle.length];
		}
		function persistTrustLayout(wrap) {
			if (!wrap) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			if (!sectionId) return;
			var current = trustLayoutFromWrap(wrap);
			var layout = nextTrustLayout(current);
			setStatus("Switching review layout...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_set_trust_layout",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				section_id: sectionId,
				layout: layout
			}).done(function(res){
				if (res && res.success && res.data && res.data.reload) {
					window.location.reload();
					return;
				}
				if (res && res.success) {
					setStatus((res.data && res.data.message) ? res.data.message : "Review layout updated.", false);
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "Review layout update failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Review layout update failed.";
				setStatus(msg, true);
			});
		}
		function buildBenefitsIconEditors() {
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
				if (sectionType !== "benefits") return;
				var nodes = Array.prototype.slice.call(wrap.querySelectorAll(".lf-benefits__icon[data-lf-benefit-icon-index]"));
				if (!nodes.length || !iconSlugs.length) return;
				var currentOverrides = [];
				function parseCurrentOverrides() {
					currentOverrides = [];
					nodes.forEach(function(iconNode){
						var svg = iconNode.querySelector("svg");
						if (!svg) {
							currentOverrides.push("");
							return;
						}
						var cls = String(svg.getAttribute("class") || "");
						var m = cls.match(/\blf-icon--([a-z0-9-]+)\b/i);
						currentOverrides.push(m && m[1] ? String(m[1]) : "");
					});
				}
				function persistOverrides() {
					persistSectionLineItems(wrap, "benefits_icon_overrides", currentOverrides, "Saving icons...");
					setTimeout(function(){ window.location.reload(); }, 220);
				}
				parseCurrentOverrides();
				nodes.forEach(function(iconNode){
					iconNode.classList.add("lf-ai-inline-editor-ignore");
					iconNode.setAttribute("title", "Click to change icon");
					iconNode.style.cursor = "pointer";
					iconNode.onclick = function(e){
						if (!editingEnabled) return;
						e.preventDefault();
						e.stopPropagation();
						var idx = parseInt(String(iconNode.getAttribute("data-lf-benefit-icon-index") || "0"), 10);
						if (isNaN(idx) || idx < 0) idx = 0;
						while (currentOverrides.length <= idx) currentOverrides.push("");
						var currentSlug = String(currentOverrides[idx] || "");
						openIconPicker(currentSlug, function(selectedSlug){
							currentOverrides[idx] = String(selectedSlug || "");
							persistOverrides();
						});
					};
				});
			});
		}
		function ctaSlotForButton(node) {
			if (!node || !node.classList) return "primary";
			if (node.classList.contains("lf-btn--secondary") || /secondary/i.test(String(node.className || ""))) {
				return "secondary";
			}
			return "primary";
		}
		function ctaActionForButton(node) {
			if (!node) return "quote";
			if (node.getAttribute("data-lf-quote-trigger") === "1") return "quote";
			if (node.tagName && node.tagName.toLowerCase() === "a") {
				var href = String(node.getAttribute("href") || "").trim().toLowerCase();
				if (href.indexOf("tel:") === 0) return "call";
				if (href !== "") return "link";
			}
			return "quote";
		}
		function ctaUrlForButton(node) {
			if (!node || !node.getAttribute) return "";
			var href = String(node.getAttribute("href") || "").trim();
			if (!href || /^tel:/i.test(href)) return "";
			return href;
		}
		function persistSectionButtonCta(wrap, slot, text, action, url) {
			if (!wrap) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			if (!sectionId) return;
			setStatus("Saving button...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_update_section_cta",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				section_id: sectionId,
				slot: String(slot || "primary"),
				text: String(text || ""),
				cta_action: String(action || "quote"),
				url: String(url || "")
			}).done(function(res){
				if (res && res.success) {
					setStatus((res.data && res.data.message) ? res.data.message : "Button saved.", false);
					if (res.data && res.data.reload) {
						window.location.reload();
					}
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "Button save failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Button save failed.";
				setStatus(msg, true);
			});
		}
		function openCtaButtonEditor(wrap, node) {
			if (!wrap || !node) return;
			if (node.closest && node.closest(".lf-benefits__actions")) {
				openBenefitsCtaPicker(wrap, node);
				return;
			}
			openSectionCtaPicker(wrap, node, false);
		}
		function buildSectionButtonEditors() {
			if (!editingEnabled) return;
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				var nodes = Array.prototype.slice.call(wrap.querySelectorAll("a.lf-btn,button.lf-btn,span.lf-btn"));
				nodes.forEach(function(node){
					if (!node || (node.closest && node.closest(".lf-ai-float"))) return;
					if (node.getAttribute("data-lf-ai-cta-editable") === "1") return;
					node.setAttribute("data-lf-ai-cta-editable", "1");
					node.setAttribute("title", "Click to edit button text and action/link");
					node.addEventListener("click", function(e){
						if (!editingEnabled) return;
						e.preventDefault();
						e.stopPropagation();
						openCtaButtonEditor(wrap, node);
					});
					node.addEventListener("dblclick", function(e){
						if (!editingEnabled) return;
						e.preventDefault();
						e.stopPropagation();
						openCtaButtonEditor(wrap, node);
					});
				});
			});
		}
		function toggleSectionColumnsInDom(wrap, newLayout) {
			if (!wrap) return;
			var details = wrap.querySelector(".lf-service-details--media");
			if (!details) return;
			var contentNode = null;
			var mediaNode = null;
			Array.prototype.slice.call(details.children || []).forEach(function(child){
				if (!child || !child.classList) return;
				if (child.classList.contains("lf-service-details__content")) contentNode = child;
				if (child.classList.contains("lf-service-details__media")) mediaNode = child;
			});
			if (!contentNode || !mediaNode) return;
			var mediaLeft = String(newLayout || "") === "media_content";
			if (mediaLeft) {
				details.classList.add("lf-service-details--media-left");
				if (details.firstElementChild !== mediaNode) {
					details.insertBefore(mediaNode, contentNode);
				}
			} else {
				details.classList.remove("lf-service-details--media-left");
				if (details.firstElementChild !== contentNode) {
					details.insertBefore(contentNode, mediaNode);
				}
			}
		}
		function persistSectionColumnSwap(wrap) {
			if (!wrap) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
			if (!sectionId || !sectionSupportsColumnSwap(sectionType)) return;
			var ctx = persistContextFromWrap(wrap);
			setStatus("Reversing section columns...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_toggle_section_columns",
				nonce: lfAiFloating.nonce,
				context_type: ctx.context_type,
				context_id: ctx.context_id,
				section_id: sectionId
			}).done(function(res){
				if (res && res.success && res.data) {
					toggleSectionColumnsInDom(wrap, String(res.data.new_layout || ""));
					setStatus((res.data && res.data.message) ? res.data.message : "Section columns reversed.", false);
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "Column reversal failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Column reversal failed.";
				setStatus(msg, true);
			});
		}
		function applySectionVisibilityUi(wrap, visible) {
			if (!wrap) return;
			var isVisible = !!visible;
			var sid = String(wrap.getAttribute("data-lf-section-id") || "");
			if (sid) {
				homepageEnabledMap[sid] = isVisible;
			}
			wrap.setAttribute("data-lf-section-visible", isVisible ? "1" : "0");
			wrap.classList.toggle("lf-ai-section-is-hidden", !isVisible);
			var btn = wrap.querySelector("[data-lf-section-toggle]");
			if (btn) {
				btn.textContent = isVisible ? "Hide" : "Show";
				btn.setAttribute("aria-label", isVisible ? "Hide section" : "Show section");
			}
		}
		function persistSectionVisibility(wrap, visible) {
			if (!wrap) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			if (!sectionId) return;
			var ctx = persistContextFromWrap(wrap);
			setStatus(visible ? "Showing section..." : "Hiding section...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_toggle_section_visibility",
				nonce: lfAiFloating.nonce,
				context_type: ctx.context_type,
				context_id: ctx.context_id,
				section_id: sectionId,
				visible: visible ? "1" : "0"
			}).done(function(res){
				if (res && res.success) {
					var nowVisible = !!(res.data && res.data.visible);
					applySectionVisibilityUi(wrap, nowVisible);
					setStatus((res.data && res.data.message) ? res.data.message : "Section visibility updated.", false);
					refreshSectionRail();
					// If we just restored a hidden section, reload to re-render its actual content.
					// Hidden sections render as placeholders on refresh, so toggling "Show" must rehydrate markup.
					if (nowVisible) {
						var isPlaceholder = wrap.classList && wrap.classList.contains("lf-inline-section-wrap--hidden");
						var hasRealMarkup = !!(wrap.querySelector && (wrap.querySelector(".lf-section") || wrap.querySelector(".lf-block")));
						if (isPlaceholder || !hasRealMarkup) {
							window.location.reload();
						}
					}
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "Section visibility update failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Section visibility update failed.";
				setStatus(msg, true);
			});
		}
		function persistSectionDelete(wrap) {
			if (!wrap) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			if (!sectionId) return;
			var ctx = persistContextFromWrap(wrap);
			setStatus("Deleting section...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_delete_section",
				nonce: lfAiFloating.nonce,
				context_type: ctx.context_type,
				context_id: ctx.context_id,
				section_id: sectionId
			}).done(function(res){
				if (res && res.success) {
					if (selectedSectionWrap === wrap) {
						selectedSectionWrap = null;
					}
					var sid = String(wrap.getAttribute("data-lf-section-id") || "");
					if (sid) {
						homepageEnabledMap[sid] = false;
					}
					wrap.remove();
					setStatus((res.data && res.data.message) ? res.data.message : "Section deleted. Use undo to restore.", false);
					refreshSectionRail();
				} else {
					var failMsg = (res && res.data && res.data.message) ? res.data.message : "Section delete failed.";
					setStatus(failMsg, true);
					try { window.alert(failMsg); } catch (e) {}
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Section delete failed.";
				setStatus(msg, true);
				try { window.alert(msg); } catch (e) {}
			});
		}
		function stripClonedSectionEditorArtifacts(root) {
			if (!root || !root.querySelectorAll) return;
			Array.prototype.slice.call(root.querySelectorAll(".lf-ai-section-controls,.lf-ai-section-insert,.lf-ai-benefits-grid-actions,[data-lf-ai-benefits-grid-actions=\"1\"],[data-lf-ai-service-intro-actions=\"1\"],[data-lf-ai-hero-pills-controls=\"1\"],[data-lf-ai-hero-proof-controls=\"1\"],[data-lf-ai-hero-trust-strip-controls=\"1\"],[data-lf-ai-trust-pill-controls=\"1\"],[data-lf-ai-process-controls=\"1\"],[data-lf-ai-checklist-controls=\"1\"],[data-lf-ai-micro-controls=\"1\"],[data-lf-ai-faq-controls=\"1\"],[data-lf-ai-list-remove=\"1\"],[data-lf-ai-checklist-remove=\"1\"],[data-lf-ai-micro-remove=\"1\"],[data-lf-ai-benefit-remove=\"1\"],[data-lf-ai-service-intro-remove=\"1\"],[data-lf-ai-hero-pill-remove=\"1\"],[data-lf-ai-media-add=\"1\"]")).forEach(function(node){
				if (node && node.parentNode) node.parentNode.removeChild(node);
			});
			Array.prototype.slice.call(root.querySelectorAll("[data-lf-benefits-editor-bound]")).forEach(function(node){
				node.removeAttribute("data-lf-benefits-editor-bound");
			});
			Array.prototype.slice.call(root.querySelectorAll("[data-lf-inline-editable],[data-lf-inline-selector],[data-lf-inline-image],[data-lf-inline-image-selector]")).forEach(function(node){
				node.removeAttribute("data-lf-inline-editable");
				node.removeAttribute("data-lf-inline-selector");
				node.removeAttribute("data-lf-inline-image");
				node.removeAttribute("data-lf-inline-image-selector");
				node.removeAttribute("data-lf-inline-field-key");
			});
			Array.prototype.slice.call(root.querySelectorAll("[data-lf-inline-active],[data-lf-inline-saving]")).forEach(function(node){
				node.removeAttribute("data-lf-inline-active");
				node.removeAttribute("data-lf-inline-saving");
			});
			Array.prototype.slice.call(root.querySelectorAll(".lf-service-details__text,.lf-block-hero__card-item-text,.lf-benefits__title,.lf-benefits__desc")).forEach(function(node){
				if (!node) return;
				node.removeAttribute("contenteditable");
				node.removeAttribute("spellcheck");
				node.removeAttribute("data-lf-ai-editing");
				node.removeAttribute("data-lf-ai-original-text");
				node.removeAttribute("data-lf-ai-original-html");
			});
		}
		function persistSectionDuplicate(wrap) {
			if (!wrap) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			if (!sectionId) return;
			var ctx = persistContextFromWrap(wrap);
			setStatus("Duplicating section...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_duplicate_section",
				nonce: lfAiFloating.nonce,
				context_type: ctx.context_type,
				context_id: ctx.context_id,
				section_id: sectionId
			}).done(function(res){
				if (res && res.success && res.data && res.data.reload) {
					window.location.reload();
					return;
				}
				if (res && res.success && res.data && res.data.new_section_id) {
					var clone = wrap.cloneNode(true);
					stripClonedSectionEditorArtifacts(clone);
					clone.classList.remove("is-dragging", "lf-ai-section-active");
					clone.setAttribute("data-lf-section-id", String(res.data.new_section_id));
					clone.removeAttribute("data-lf-section-visible");
					homepageEnabledMap[String(res.data.new_section_id)] = true;
					wrap.parentNode.insertBefore(clone, wrap.nextSibling);
					buildSectionTargets();
					buildInlineTargets();
					buildInlineImageTargets();
					buildSectionControls();
					buildSectionInsertZones();
					buildSectionButtonEditors();
					buildHeroPillsControls();
					buildHeroProofChecklistControls();
					buildHeroTrustStripControls();
					buildTrustBadgePillsControls();
					buildChecklistControls();
					buildProofBadgeControls();
					buildServiceDetailsMicroControls();
					buildProcessStepControls();
					buildSectionColumnSwapTargets();
					buildSectionMediaEditors();
					buildFaqReorderControls();
					buildBenefitsReorderControls();
					buildBenefitsTextEditors();
					buildServiceIntroReorderControls();
					buildServiceIntroCardEditors();
					buildBenefitsGridChrome();
					buildBenefitsIconEditors();
					setSelectedSection(clone);
					setStatus((res.data && res.data.message) ? res.data.message : "Section duplicated.", false);
				} else {
					var failMsg = (res && res.data && res.data.message) ? res.data.message : "Section duplicate failed.";
					setStatus(failMsg, true);
					try { window.alert(failMsg); } catch (e) {}
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Section duplicate failed.";
				setStatus(msg, true);
				try { window.alert(msg); } catch (e) {}
			});
		}
		function sectionSupportsHeaderAlign(wrap) {
			if (!wrap) return false;
			if (String(wrap.getAttribute("data-lf-section-type") || "") === "hero") return false;
			return !!(wrap.querySelector(".lf-section__header, .lf-block-faq-accordion__header, .lf-block-trust-reviews__header, .lf-block-map-nap__header, .lf-block-service-intro__header, .lf-block-service-grid__header, .lf-block-service-areas__header, .lf-block-cta__content"));
		}
		function closeSectionBgPicker() {
			if (sectionBgPickerEl) sectionBgPickerEl.hidden = true;
			sectionBgPickerWrap = null;
		}
		function ensureSectionBgPicker() {
			if (sectionBgPickerEl) return sectionBgPickerEl;
			var root = document.createElement("div");
			root.className = "lf-ai-section-bg-picker lf-ai-inline-editor-ignore";
			root.hidden = true;
			var card = document.createElement("div");
			card.className = "lf-ai-section-bg-picker__card";
			var head = document.createElement("div");
			head.className = "lf-ai-section-bg-picker__head";
			var title = document.createElement("span");
			title.className = "lf-ai-section-bg-picker__title";
			title.textContent = "Section background";
			var closeHead = document.createElement("button");
			closeHead.type = "button";
			closeHead.className = "lf-ai-section-bg-picker__close";
			closeHead.textContent = "×";
			closeHead.setAttribute("aria-label", "Close");
			closeHead.addEventListener("click", function(e){
				e.preventDefault();
				closeSectionBgPicker();
			});
			head.appendChild(title);
			head.appendChild(closeHead);
			var swatches = document.createElement("div");
			swatches.className = "lf-ai-section-bg-picker__swatches";
			var palette = Array.isArray(lfAiFloating.bg_palette) ? lfAiFloating.bg_palette : [];
			palette.forEach(function(entry){
				var slug = String(entry.slug || "");
				if (!slug) return;
				var btn = document.createElement("button");
				btn.type = "button";
				btn.className = "lf-ai-section-bg-picker__swatch";
				var sw = document.createElement("span");
				sw.className = "lf-ai-section-bg-picker__swatch-color";
				sw.style.background = String(entry.hex || "#ccc");
				var lab = document.createElement("span");
				lab.className = "lf-ai-section-bg-picker__swatch-label";
				lab.textContent = String(entry.label || slug);
				btn.appendChild(sw);
				btn.appendChild(lab);
				btn.addEventListener("click", function(e){
					e.preventDefault();
					var w = sectionBgPickerWrap;
					closeSectionBgPicker();
					if (w) persistSectionStyle(w, "set_preset_bg", { background_slug: slug });
				});
				swatches.appendChild(btn);
			});
			var brandNote = document.createElement("p");
			brandNote.className = "lf-ai-section-bg-picker__brand-note";
			brandNote.style.margin = "10px 0 0";
			brandNote.style.fontSize = "12px";
			brandNote.style.color = "#64748b";
			brandNote.style.lineHeight = "1.45";
			var bUrl = String((lfAiFloating && lfAiFloating.brand_settings_url) ? lfAiFloating.brand_settings_url : "");
			if (bUrl) {
				brandNote.innerHTML = "Swatches use your saved brand colors. Update primary, secondary, and surfaces in <a href=\"" + bUrl + "\" target=\"_blank\" rel=\"noopener\">LeadsForward → Global Settings</a>.";
			} else {
				brandNote.textContent = "Swatches use your saved brand colors from Global Settings.";
			}
			var customRow = document.createElement("div");
			customRow.className = "lf-ai-section-bg-picker__custom";
			var inp = document.createElement("input");
			inp.type = "text";
			inp.className = "lf-ai-section-bg-picker__input";
			inp.setAttribute("placeholder", "#hex or rgb(...)");
			var applyCustom = document.createElement("button");
			applyCustom.type = "button";
			applyCustom.className = "lf-ai-section-bg-picker__apply";
			applyCustom.textContent = "Apply custom";
			applyCustom.addEventListener("click", function(e){
				e.preventDefault();
				var w = sectionBgPickerWrap;
				var v = String(inp.value || "").trim();
				closeSectionBgPicker();
				if (w && v) persistSectionStyle(w, "set_custom_bg", { custom_background: v });
			});
			customRow.appendChild(inp);
			customRow.appendChild(applyCustom);
			var clearCustom = document.createElement("button");
			clearCustom.type = "button";
			clearCustom.className = "lf-ai-section-bg-picker__clearcustom";
			clearCustom.textContent = "Clear custom color";
			clearCustom.addEventListener("click", function(e){
				e.preventDefault();
				var w = sectionBgPickerWrap;
				closeSectionBgPicker();
				if (w) persistSectionStyle(w, "clear_custom_bg", {});
			});
			card.appendChild(head);
			card.appendChild(swatches);
			card.appendChild(brandNote);
			card.appendChild(customRow);
			card.appendChild(clearCustom);
			root.appendChild(card);
			root.addEventListener("click", function(e){
				if (e.target === root) closeSectionBgPicker();
			});
			document.body.appendChild(root);
			sectionBgPickerEl = root;
			return root;
		}
		function openSectionBgPicker(wrap) {
			ensureSectionBgPicker();
			sectionBgPickerWrap = wrap;
			sectionBgPickerEl.hidden = false;
		}
		function closeSectionAlignPicker() {
			if (sectionAlignPickerEl) sectionAlignPickerEl.hidden = true;
			sectionAlignPickerWrap = null;
		}
		function ensureSectionAlignPicker() {
			if (sectionAlignPickerEl) return sectionAlignPickerEl;
			var root = document.createElement("div");
			root.className = "lf-ai-section-align-picker lf-ai-inline-editor-ignore";
			root.hidden = true;
			var card = document.createElement("div");
			card.className = "lf-ai-section-align-picker__card";
			var head = document.createElement("div");
			head.className = "lf-ai-section-align-picker__head";
			head.textContent = "Header alignment";
			var row = document.createElement("div");
			row.className = "lf-ai-section-align-picker__row";
			["left", "center", "right"].forEach(function(al){
				var b = document.createElement("button");
				b.type = "button";
				b.className = "lf-ai-section-align-picker__btn";
				b.textContent = al.charAt(0).toUpperCase() + al.slice(1);
				b.addEventListener("click", function(e){
					e.preventDefault();
					var w = sectionAlignPickerWrap;
					closeSectionAlignPicker();
					if (w) persistSectionStyle(w, "set_header_align", { header_align: al });
				});
				row.appendChild(b);
			});
			var ctaHead = document.createElement("div");
			ctaHead.className = "lf-ai-section-align-picker__subhead";
			ctaHead.setAttribute("data-lf-benefits-cta-align-head", "1");
			ctaHead.textContent = "Benefits button row";
			var ctaRow = document.createElement("div");
			ctaRow.className = "lf-ai-section-align-picker__row";
			ctaRow.setAttribute("data-lf-benefits-cta-align-row", "1");
			["left", "center", "right"].forEach(function(al){
				var cb = document.createElement("button");
				cb.type = "button";
				cb.className = "lf-ai-section-align-picker__btn";
				cb.textContent = al.charAt(0).toUpperCase() + al.slice(1);
				cb.addEventListener("click", function(e){
					e.preventDefault();
					var w = sectionAlignPickerWrap;
					closeSectionAlignPicker();
					if (w) persistSectionStyle(w, "set_benefits_cta_align", { benefits_cta_align: al });
				});
				ctaRow.appendChild(cb);
			});
			var closeBtn = document.createElement("button");
			closeBtn.type = "button";
			closeBtn.className = "lf-ai-section-align-picker__close";
			closeBtn.textContent = "×";
			closeBtn.setAttribute("aria-label", "Close");
			closeBtn.addEventListener("click", function(e){
				e.preventDefault();
				closeSectionAlignPicker();
			});
			card.appendChild(head);
			card.appendChild(row);
			card.appendChild(ctaHead);
			card.appendChild(ctaRow);
			card.appendChild(closeBtn);
			root.appendChild(card);
			root.addEventListener("click", function(e){
				if (e.target === root) closeSectionAlignPicker();
			});
			document.body.appendChild(root);
			sectionAlignPickerEl = root;
			return root;
		}
		function openSectionAlignPicker(wrap) {
			ensureSectionAlignPicker();
			sectionAlignPickerWrap = wrap;
			sectionAlignPickerEl.hidden = false;
			var isBen = wrap && String(wrap.getAttribute("data-lf-section-type") || "") === "benefits";
			var ctaHead = sectionAlignPickerEl.querySelector("[data-lf-benefits-cta-align-head]");
			var ctaRow = sectionAlignPickerEl.querySelector("[data-lf-benefits-cta-align-row]");
			if (ctaHead) ctaHead.hidden = !isBen;
			if (ctaRow) ctaRow.hidden = !isBen;
		}
		function closeBenefitsCtaPicker() {
			if (benefitsCtaPickerEl) benefitsCtaPickerEl.hidden = true;
			benefitsCtaPickerWrap = null;
			benefitsCtaPickerButtonNode = null;
			benefitsCtaPickerIsBenefits = true;
		}
		function closeSectionInsertPicker() {
			if (sectionInsertPickerEl) sectionInsertPickerEl.hidden = true;
			sectionInsertAfterId = "";
			sectionInsertBeforeId = "";
		}
		function ensureBenefitsCtaPicker() {
			if (benefitsCtaPickerEl) return benefitsCtaPickerEl;
			var root = document.createElement("div");
			root.className = "lf-ai-benefits-cta-picker lf-ai-inline-editor-ignore";
			root.hidden = true;
			var card = document.createElement("div");
			card.className = "lf-ai-benefits-cta-picker__card";
			var title = document.createElement("div");
			title.className = "lf-ai-section-bg-picker__head";
			title.style.marginBottom = "0";
			var titleSpan = document.createElement("span");
			titleSpan.className = "lf-ai-section-bg-picker__title";
			titleSpan.setAttribute("data-lf-section-cta-title", "1");
			titleSpan.textContent = "Benefits button";
			title.appendChild(titleSpan);
			var textLab = document.createElement("label");
			textLab.className = "lf-ai-benefits-cta-picker__label";
			textLab.setAttribute("data-lf-section-cta-text-label", "1");
			textLab.textContent = "Button text (leave empty to remove)";
			var textInp = document.createElement("input");
			textInp.type = "text";
			textInp.className = "lf-ai-benefits-cta-picker__input";
			textInp.setAttribute("data-lf-benefits-cta-text", "1");
			textLab.appendChild(textInp);
			var actLab = document.createElement("label");
			actLab.className = "lf-ai-benefits-cta-picker__label";
			actLab.textContent = "Action";
			var actSel = document.createElement("select");
			actSel.className = "lf-ai-benefits-cta-picker__input";
			actSel.setAttribute("data-lf-benefits-cta-action", "1");
			[["quote", "Open quote"], ["call", "Call"], ["link", "Link"]].forEach(function(opt){
				var o = document.createElement("option");
				o.value = opt[0];
				o.textContent = opt[1];
				actSel.appendChild(o);
			});
			actLab.appendChild(actSel);
			var urlLab = document.createElement("label");
			urlLab.className = "lf-ai-benefits-cta-picker__label";
			urlLab.setAttribute("data-lf-section-cta-url-wrap", "1");
			urlLab.textContent = "URL (if Link)";
			var urlInp = document.createElement("input");
			urlInp.type = "url";
			urlInp.className = "lf-ai-benefits-cta-picker__input";
			urlInp.setAttribute("data-lf-benefits-cta-url", "1");
			urlInp.setAttribute("placeholder", "https://");
			urlLab.appendChild(urlInp);
			var callHint = document.createElement("p");
			callHint.className = "lf-ai-benefits-cta-picker__hint";
			callHint.setAttribute("data-lf-section-cta-call-hint", "1");
			callHint.style.margin = "0";
			callHint.style.fontSize = "12px";
			callHint.style.color = "#64748b";
			callHint.textContent = "Uses the phone number from Business Info (global settings).";
			callHint.hidden = true;
			var alignLab = document.createElement("div");
			alignLab.className = "lf-ai-benefits-cta-picker__label";
			alignLab.textContent = "Alignment";
			var alignRow = document.createElement("div");
			alignRow.className = "lf-ai-benefits-cta-picker__row";
			var alignState = { value: "center" };
			["left", "center", "right"].forEach(function(al){
				var ab = document.createElement("button");
				ab.type = "button";
				ab.className = "lf-ai-benefits-cta-picker__btn";
				ab.textContent = al.charAt(0).toUpperCase() + al.slice(1);
				ab.setAttribute("data-lf-benefits-cta-align-btn", al);
				ab.addEventListener("click", function(e){
					e.preventDefault();
					alignState.value = al;
					Array.prototype.slice.call(alignRow.querySelectorAll("[data-lf-benefits-cta-align-btn]")).forEach(function(x){
						x.classList.toggle("is-active", String(x.getAttribute("data-lf-benefits-cta-align-btn") || "") === al);
					});
				});
				alignRow.appendChild(ab);
			});
			var alignWrap = document.createElement("div");
			alignWrap.setAttribute("data-lf-section-cta-align-wrap", "1");
			alignWrap.appendChild(alignLab);
			alignWrap.appendChild(alignRow);
			var btnRow = document.createElement("div");
			btnRow.className = "lf-ai-benefits-cta-picker__row";
			var removeBtn = document.createElement("button");
			removeBtn.type = "button";
			removeBtn.className = "lf-ai-benefits-cta-picker__btn";
			removeBtn.setAttribute("data-lf-section-cta-remove", "1");
			removeBtn.textContent = "Remove button";
			removeBtn.addEventListener("click", function(e){
				e.preventDefault();
				if (!benefitsCtaPickerIsBenefits) return;
				var w = benefitsCtaPickerWrap;
				closeBenefitsCtaPicker();
				if (w) persistBenefitsSectionCta(w, "", "quote", "", "center");
			});
			var saveBtn = document.createElement("button");
			saveBtn.type = "button";
			saveBtn.className = "lf-ai-benefits-cta-picker__btn";
			saveBtn.textContent = "Save";
			saveBtn.addEventListener("click", function(e){
				e.preventDefault();
				var w = benefitsCtaPickerWrap;
				var t = String(textInp.value || "").trim();
				var act = String(actSel.value || "quote");
				var u = String(urlInp.value || "").trim();
				if (benefitsCtaPickerIsBenefits) {
					closeBenefitsCtaPicker();
					if (w) persistBenefitsSectionCta(w, t, act, u, alignState.value);
					return;
				}
				if (t === "") {
					setStatus("Button text cannot be empty.", true);
					return;
				}
				if (act === "link" && u === "") {
					setStatus("Link URL is required for link action.", true);
					return;
				}
				closeBenefitsCtaPicker();
				var slot = ctaSlotForButton(benefitsCtaPickerButtonNode);
				if (w) persistSectionButtonCta(w, slot, t, act, act === "link" ? u : "");
			});
			var cancelBtn = document.createElement("button");
			cancelBtn.type = "button";
			cancelBtn.className = "lf-ai-benefits-cta-picker__btn";
			cancelBtn.style.borderColor = "#e2e8f0";
			cancelBtn.style.color = "#64748b";
			cancelBtn.textContent = "Cancel";
			cancelBtn.addEventListener("click", function(e){
				e.preventDefault();
				closeBenefitsCtaPicker();
			});
			function syncCtaPickerActionUi() {
				var act = String(actSel.value || "quote");
				if (urlLab) urlLab.hidden = act !== "link";
				if (callHint) callHint.hidden = act !== "call";
			}
			actSel.addEventListener("change", syncCtaPickerActionUi);
			btnRow.appendChild(removeBtn);
			btnRow.appendChild(cancelBtn);
			btnRow.appendChild(saveBtn);
			card.appendChild(title);
			card.appendChild(textLab);
			card.appendChild(actLab);
			card.appendChild(urlLab);
			card.appendChild(callHint);
			card.appendChild(alignWrap);
			card.appendChild(btnRow);
			root.appendChild(card);
			root.addEventListener("click", function(e){
				if (e.target === root) closeBenefitsCtaPicker();
			});
			root.__lfAlignState = alignState;
			root.__lfAlignRow = alignRow;
			root.__lfSyncCtaPickerActionUi = syncCtaPickerActionUi;
			document.body.appendChild(root);
			benefitsCtaPickerEl = root;
			return root;
		}
		function openSectionCtaPicker(wrap, buttonNode, isBenefits) {
			ensureBenefitsCtaPicker();
			benefitsCtaPickerWrap = wrap;
			benefitsCtaPickerIsBenefits = !!isBenefits;
			benefitsCtaPickerButtonNode = buttonNode || null;
			var titleEl = benefitsCtaPickerEl.querySelector("[data-lf-section-cta-title]");
			var textLab = benefitsCtaPickerEl.querySelector("[data-lf-section-cta-text-label]");
			var alignWrap = benefitsCtaPickerEl.querySelector("[data-lf-section-cta-align-wrap]");
			var removeBtn = benefitsCtaPickerEl.querySelector("[data-lf-section-cta-remove]");
			if (isBenefits) {
				if (titleEl) titleEl.textContent = "Benefits button";
				if (textLab) textLab.textContent = "Button text (leave empty to remove)";
				if (alignWrap) alignWrap.hidden = false;
				if (removeBtn) removeBtn.hidden = false;
			} else {
				if (titleEl) {
					titleEl.textContent = ctaSlotForButton(buttonNode) === "secondary" ? "Secondary button" : "Button";
				}
				if (textLab) textLab.textContent = "Button text";
				if (alignWrap) alignWrap.hidden = true;
				if (removeBtn) removeBtn.hidden = true;
			}
			var textInp = benefitsCtaPickerEl.querySelector("[data-lf-benefits-cta-text]");
			var actSel = benefitsCtaPickerEl.querySelector("[data-lf-benefits-cta-action]");
			var urlInp = benefitsCtaPickerEl.querySelector("[data-lf-benefits-cta-url]");
			var actionsEl = wrap ? wrap.querySelector(".lf-benefits__actions") : null;
			var btn = isBenefits ? (buttonNode || (actionsEl ? actionsEl.querySelector("a.lf-btn,button.lf-btn") : null)) : buttonNode;
			var curText = btn ? String(btn.textContent || "").trim() : "";
			var curAct = "quote";
			var curUrl = "";
			if (btn) {
				curAct = ctaActionForButton(btn);
				curUrl = ctaUrlForButton(btn);
			}
			var curAlign = "center";
			if (isBenefits && actionsEl && actionsEl.className) {
				var m = String(actionsEl.className || "").match(/\blf-benefits__actions--align-(left|center|right)\b/);
				if (m && m[1]) curAlign = m[1];
			}
			if (textInp) textInp.value = curText;
			if (actSel) {
				if (curAct === "link") actSel.value = "link";
				else if (curAct === "call") actSel.value = "call";
				else actSel.value = "quote";
			}
			if (urlInp) urlInp.value = curUrl;
			var alignRow = benefitsCtaPickerEl.__lfAlignRow;
			var alignState = benefitsCtaPickerEl.__lfAlignState;
			if (alignState) alignState.value = curAlign;
			if (alignRow) {
				Array.prototype.slice.call(alignRow.querySelectorAll("[data-lf-benefits-cta-align-btn]")).forEach(function(x){
					var al = String(x.getAttribute("data-lf-benefits-cta-align-btn") || "");
					x.classList.toggle("is-active", al === curAlign);
				});
			}
			if (benefitsCtaPickerEl.__lfSyncCtaPickerActionUi) benefitsCtaPickerEl.__lfSyncCtaPickerActionUi();
			benefitsCtaPickerEl.hidden = false;
		}
		function openBenefitsCtaPicker(wrap, buttonNode) {
			openSectionCtaPicker(wrap, buttonNode, true);
		}
		function persistBenefitsSectionCta(wrap, text, action, url, align) {
			if (!wrap) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			if (!sectionId) return;
			var ctx = persistContextFromWrap(wrap);
			setStatus("Saving button...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_update_section_cta",
				nonce: lfAiFloating.nonce,
				context_type: ctx.context_type,
				context_id: ctx.context_id,
				section_id: sectionId,
				cta_target: "benefits",
				text: String(text || ""),
				cta_action: String(action || "quote"),
				url: String(url || ""),
				benefits_cta_align: String(align || "center")
			}).done(function(res){
				if (res && res.success) {
					setStatus((res.data && res.data.message) ? res.data.message : "Saved.", false);
					if (res.data && res.data.reload) window.location.reload();
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "Save failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Save failed.";
				setStatus(msg, true);
			});
		}
		function ensureSectionInsertPicker() {
			if (sectionInsertPickerEl) return sectionInsertPickerEl;
			var root = document.createElement("div");
			root.className = "lf-ai-section-insert-picker lf-ai-inline-editor-ignore";
			root.hidden = true;
			var card = document.createElement("div");
			card.className = "lf-ai-section-insert-picker__card";
			var head = document.createElement("div");
			head.className = "lf-ai-section-insert-picker__head";
			head.textContent = "Add section";
			var hint = document.createElement("p");
			hint.style.margin = "0";
			hint.style.fontSize = "12px";
			hint.style.color = "#64748b";
			hint.setAttribute("data-lf-section-insert-hint", "1");
			hint.textContent = "Choose a section type.";
			var list = document.createElement("div");
			list.setAttribute("data-lf-section-insert-list", "1");
			var closeBtn = document.createElement("button");
			closeBtn.type = "button";
			closeBtn.className = "lf-ai-benefits-cta-picker__btn";
			closeBtn.style.marginTop = "6px";
			closeBtn.textContent = "Cancel";
			closeBtn.addEventListener("click", function(e){
				e.preventDefault();
				closeSectionInsertPicker();
			});
			card.appendChild(head);
			card.appendChild(hint);
			card.appendChild(list);
			card.appendChild(closeBtn);
			root.appendChild(card);
			root.addEventListener("click", function(e){
				if (e.target === root) closeSectionInsertPicker();
			});
			document.body.appendChild(root);
			sectionInsertPickerEl = root;
			return root;
		}
		function openSectionInsertPicker(afterId, beforeId) {
			ensureSectionInsertPicker();
			sectionInsertAfterId = String(afterId || "");
			sectionInsertBeforeId = String(beforeId || "");
			var hint = sectionInsertPickerEl.querySelector("[data-lf-section-insert-hint]");
			if (hint) {
				if (sectionInsertBeforeId) {
					hint.textContent = "Inserts above the hovered section.";
				} else {
					hint.textContent = "Inserts below the hovered section.";
				}
			}
			var list = sectionInsertPickerEl.querySelector("[data-lf-section-insert-list]");
			if (list) {
				list.innerHTML = "";
				var rows = Array.isArray(lfAiFloating.section_library) ? lfAiFloating.section_library : [];
				rows.forEach(function(row){
					var id = String(row && row.id ? row.id : "");
					var label = String(row && row.label ? row.label : id);
					if (!id) return;
					var b = document.createElement("button");
					b.type = "button";
					b.className = "lf-ai-section-insert-picker__item";
					b.textContent = label + " (" + id + ")";
					b.addEventListener("click", function(e){
						e.preventDefault();
						var aid = sectionInsertAfterId;
						var bid = sectionInsertBeforeId;
						closeSectionInsertPicker();
						addSectionFromLibrary(id, aid, bid);
					});
					list.appendChild(b);
				});
			}
			sectionInsertPickerEl.hidden = false;
		}
		function buildSectionInsertZones() {
			if (!editingEnabled) return;
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				if (wrap.querySelector(".lf-ai-section-insert")) return;
				var shell = document.createElement("div");
				shell.className = "lf-ai-section-insert lf-ai-inline-editor-ignore";
				var topZ = document.createElement("div");
				topZ.className = "lf-ai-section-insert__zone lf-ai-section-insert__zone--top";
				var topBtn = document.createElement("button");
				topBtn.type = "button";
				topBtn.className = "lf-ai-section-insert__btn";
				topBtn.textContent = "+";
				topBtn.setAttribute("aria-label", "Add section above");
				topBtn.setAttribute("title", "Add section above");
				topBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					var sid = String(wrap.getAttribute("data-lf-section-id") || "");
					openSectionInsertPicker("", sid);
				});
				topZ.appendChild(topBtn);
				var botZ = document.createElement("div");
				botZ.className = "lf-ai-section-insert__zone lf-ai-section-insert__zone--bottom";
				var botBtn = document.createElement("button");
				botBtn.type = "button";
				botBtn.className = "lf-ai-section-insert__btn";
				botBtn.textContent = "+";
				botBtn.setAttribute("aria-label", "Add section below");
				botBtn.setAttribute("title", "Add section below");
				botBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					var sid = String(wrap.getAttribute("data-lf-section-id") || "");
					openSectionInsertPicker(sid, "");
				});
				botZ.appendChild(botBtn);
				shell.appendChild(topZ);
				shell.appendChild(botZ);
				wrap.appendChild(shell);
			});
		}
		function closeHeroSettingsPicker() {
			if (heroSettingsPickerEl) heroSettingsPickerEl.hidden = true;
			heroSettingsPickerWrap = null;
		}
		function updateHeroSettingsMediaSummary() {
			if (!heroSettingsPickerEl) return;
			var imgEl = heroSettingsPickerEl.querySelector("[data-lf-hero-image-summary]");
			var vidEl = heroSettingsPickerEl.querySelector("[data-lf-hero-video-summary]");
			if (imgEl) {
				imgEl.textContent = heroSettingsState.imageId > 0
					? ("Image attachment ID: " + String(heroSettingsState.imageId))
					: "No custom image (featured image can still show when overlay mode is on).";
			}
			if (vidEl) {
				vidEl.textContent = heroSettingsState.videoId > 0
					? ("Video attachment ID: " + String(heroSettingsState.videoId))
					: "No video selected.";
			}
		}
		function openHeroBackgroundImagePicker() {
			if (!(window.wp && wp.media)) {
				setStatus("Media Library is unavailable on this screen.", true);
				return;
			}
			if (mediaFrame) mediaFrame.off("select");
			mediaFrame = wp.media({
				title: "Hero background image",
				button: { text: "Use this image" },
				library: { type: "image" },
				multiple: false
			});
			mediaFrame.on("select", function(){
				var sel = mediaFrame.state().get("selection");
				var attachment = sel && sel.first ? sel.first().toJSON() : null;
				if (attachment && attachment.id) {
					heroSettingsState.imageId = parseInt(String(attachment.id), 10) || 0;
					updateHeroSettingsMediaSummary();
				}
			});
			mediaFrame.open();
		}
		function openHeroBackgroundVideoPicker() {
			if (!(window.wp && wp.media)) {
				setStatus("Media Library is unavailable on this screen.", true);
				return;
			}
			if (mediaFrame) mediaFrame.off("select");
			mediaFrame = wp.media({
				title: "Hero background video",
				button: { text: "Use this video" },
				library: { type: "video" },
				multiple: false
			});
			mediaFrame.on("select", function(){
				var sel = mediaFrame.state().get("selection");
				var attachment = sel && sel.first ? sel.first().toJSON() : null;
				if (attachment && attachment.id) {
					heroSettingsState.videoId = parseInt(String(attachment.id), 10) || 0;
					updateHeroSettingsMediaSummary();
				}
			});
			mediaFrame.open();
		}
		function renderHeroSettingsMediaRow() {
			if (!heroSettingsPickerEl) return;
			var mediaRow = heroSettingsPickerEl.querySelector("[data-lf-hero-media-row]");
			if (!mediaRow) return;
			mediaRow.innerHTML = "";
			var mode = heroSettingsState.mode;
			if (mode === "image") {
				var hint = document.createElement("p");
				hint.className = "lf-ai-hero-settings__hint";
				hint.textContent = "Optional image overrides the page featured image for the overlay.";
				var pick = document.createElement("button");
				pick.type = "button";
				pick.className = "lf-ai-section-bg-picker__apply";
				pick.textContent = "Select background image…";
				pick.addEventListener("click", function(e){
					e.preventDefault();
					openHeroBackgroundImagePicker();
				});
				var clear = document.createElement("button");
				clear.type = "button";
				clear.className = "lf-ai-section-bg-picker__clearcustom";
				clear.textContent = "Clear image";
				clear.addEventListener("click", function(e){
					e.preventDefault();
					heroSettingsState.imageId = 0;
					updateHeroSettingsMediaSummary();
				});
				var sum = document.createElement("p");
				sum.className = "lf-ai-hero-settings__summary";
				sum.setAttribute("data-lf-hero-image-summary", "1");
				mediaRow.appendChild(hint);
				mediaRow.appendChild(pick);
				mediaRow.appendChild(clear);
				mediaRow.appendChild(sum);
			} else if (mode === "video") {
				var vhint = document.createElement("p");
				vhint.className = "lf-ai-hero-settings__hint";
				vhint.textContent = "Upload MP4 (or WebM) to the Media Library for best compatibility.";
				var vp = document.createElement("button");
				vp.type = "button";
				vp.className = "lf-ai-section-bg-picker__apply";
				vp.textContent = "Select background video…";
				vp.addEventListener("click", function(e){
					e.preventDefault();
					openHeroBackgroundVideoPicker();
				});
				var vc = document.createElement("button");
				vc.type = "button";
				vc.className = "lf-ai-section-bg-picker__clearcustom";
				vc.textContent = "Clear video";
				vc.addEventListener("click", function(e){
					e.preventDefault();
					heroSettingsState.videoId = 0;
					updateHeroSettingsMediaSummary();
				});
				var vsum = document.createElement("p");
				vsum.className = "lf-ai-hero-settings__summary";
				vsum.setAttribute("data-lf-hero-video-summary", "1");
				mediaRow.appendChild(vhint);
				mediaRow.appendChild(vp);
				mediaRow.appendChild(vc);
				mediaRow.appendChild(vsum);
			} else {
				var ch = document.createElement("p");
				ch.className = "lf-ai-hero-settings__hint";
				ch.textContent = "Uses section background color only (no photo or video layer).";
				mediaRow.appendChild(ch);
			}
			updateHeroSettingsMediaSummary();
		}
		function refreshHeroSettingsPickerUi() {
			if (!heroSettingsPickerEl) return;
			var vRow = heroSettingsPickerEl.querySelector("[data-lf-hero-variant-row]");
			var mRow = heroSettingsPickerEl.querySelector("[data-lf-hero-mode-row]");
			var modesAllowedForVariant = function(variant) {
				var v = String(variant || "default");
				// Visual Proof (c) is designed as a color-only hero.
				if (v === "c") return ["color"];
				return ["color", "image", "video"];
			};
			if (vRow) {
				vRow.innerHTML = "";
				var vars = Array.isArray(lfAiFloating.hero_variants) ? lfAiFloating.hero_variants : [];
				vars.forEach(function(entry){
					var val = String(entry.value || "");
					if (!val) return;
					var btn = document.createElement("button");
					btn.type = "button";
					btn.className = "lf-ai-section-bg-picker__swatch";
					if (heroSettingsState.variant === val) btn.classList.add("is-selected");
					var dot = document.createElement("span");
					dot.className = "lf-ai-section-bg-picker__swatch-color";
					dot.style.background = "linear-gradient(135deg,#8348f9,#38bdf8)";
					var lab = document.createElement("span");
					lab.className = "lf-ai-section-bg-picker__swatch-label";
					lab.textContent = String(entry.label || val);
					btn.appendChild(dot);
					btn.appendChild(lab);
					btn.addEventListener("click", function(e){
						e.preventDefault();
						heroSettingsState.variant = val;
						var allowed = modesAllowedForVariant(val);
						if (allowed.indexOf(String(heroSettingsState.mode || "")) < 0) {
							heroSettingsState.mode = allowed[0] || "color";
						}
						refreshHeroSettingsPickerUi();
					});
					vRow.appendChild(btn);
				});
			}
			if (mRow) {
				mRow.innerHTML = "";
				var allowed = modesAllowedForVariant(heroSettingsState.variant);
				// Hide the "Background type" label + row when only one option is allowed.
				var hideModeRow = allowed.length <= 1;
				mRow.style.display = hideModeRow ? "none" : "";
				if (mRow.previousElementSibling && mRow.previousElementSibling.classList && mRow.previousElementSibling.classList.contains("lf-ai-hero-settings__label")) {
					mRow.previousElementSibling.style.display = hideModeRow ? "none" : "";
				}
				var modes = Array.isArray(lfAiFloating.hero_bg_modes) ? lfAiFloating.hero_bg_modes : [];
				modes.forEach(function(entry){
					var val = String(entry.value || "");
					if (!val) return;
					if (allowed.indexOf(val) < 0) return;
					var b = document.createElement("button");
					b.type = "button";
					b.className = "lf-ai-section-bg-picker__swatch";
					if (heroSettingsState.mode === val) b.classList.add("is-selected");
					var d = document.createElement("span");
					d.className = "lf-ai-section-bg-picker__swatch-color";
					d.style.background = val === "color" ? "#e2e8f0" : (val === "video" ? "#1e293b" : "#94a3b8");
					var lb = document.createElement("span");
					lb.className = "lf-ai-section-bg-picker__swatch-label";
					lb.textContent = String(entry.label || val);
					b.appendChild(d);
					b.appendChild(lb);
					b.addEventListener("click", function(e){
						e.preventDefault();
						heroSettingsState.mode = val;
						refreshHeroSettingsPickerUi();
					});
					mRow.appendChild(b);
				});
			}
			renderHeroSettingsMediaRow();
		}
		function ensureHeroSettingsPicker() {
			if (heroSettingsPickerEl) return heroSettingsPickerEl;
			var root = document.createElement("div");
			root.className = "lf-ai-section-bg-picker lf-ai-hero-settings lf-ai-inline-editor-ignore";
			root.hidden = true;
			var card = document.createElement("div");
			card.className = "lf-ai-section-bg-picker__card";
			var head = document.createElement("div");
			head.className = "lf-ai-section-bg-picker__head";
			var title = document.createElement("span");
			title.className = "lf-ai-section-bg-picker__title";
			title.textContent = "Hero layout & background";
			var closeHead = document.createElement("button");
			closeHead.type = "button";
			closeHead.className = "lf-ai-section-bg-picker__close";
			closeHead.textContent = "×";
			closeHead.setAttribute("aria-label", "Close");
			closeHead.addEventListener("click", function(e){
				e.preventDefault();
				closeHeroSettingsPicker();
			});
			head.appendChild(title);
			head.appendChild(closeHead);
			var lv = document.createElement("p");
			lv.className = "lf-ai-hero-settings__label";
			lv.textContent = "Layout variant";
			var vRow = document.createElement("div");
			vRow.className = "lf-ai-section-bg-picker__swatches";
			vRow.setAttribute("data-lf-hero-variant-row", "1");
			var lm = document.createElement("p");
			lm.className = "lf-ai-hero-settings__label";
			lm.textContent = "Background type";
			var mRow = document.createElement("div");
			mRow.className = "lf-ai-section-bg-picker__swatches";
			mRow.setAttribute("data-lf-hero-mode-row", "1");
			var mediaRow = document.createElement("div");
			mediaRow.className = "lf-ai-hero-settings__media";
			mediaRow.setAttribute("data-lf-hero-media-row", "1");
			var foot = document.createElement("div");
			foot.className = "lf-ai-hero-settings__footer";
			var applyBtn = document.createElement("button");
			applyBtn.type = "button";
			applyBtn.className = "lf-ai-section-bg-picker__apply";
			applyBtn.textContent = "Apply";
			applyBtn.addEventListener("click", function(e){
				e.preventDefault();
				persistHeroSettings();
			});
			var cancelBtn = document.createElement("button");
			cancelBtn.type = "button";
			cancelBtn.className = "lf-ai-section-bg-picker__clearcustom";
			cancelBtn.textContent = "Cancel";
			cancelBtn.addEventListener("click", function(e){
				e.preventDefault();
				closeHeroSettingsPicker();
			});
			foot.appendChild(cancelBtn);
			foot.appendChild(applyBtn);
			card.appendChild(head);
			card.appendChild(lv);
			card.appendChild(vRow);
			card.appendChild(lm);
			card.appendChild(mRow);
			card.appendChild(mediaRow);
			card.appendChild(foot);
			root.appendChild(card);
			root.addEventListener("click", function(e){
				if (e.target === root) closeHeroSettingsPicker();
			});
			document.body.appendChild(root);
			heroSettingsPickerEl = root;
			return root;
		}
		function openHeroSettingsPicker(wrap) {
			var heroEl = wrap && wrap.querySelector ? wrap.querySelector(".lf-block-hero") : null;
			if (!heroEl) {
				setStatus("Hero block not found in this section.", true);
				return;
			}
			heroSettingsPickerWrap = wrap;
			heroSettingsState.variant = String(heroEl.getAttribute("data-variant") || "default");
			heroSettingsState.mode = String(heroEl.getAttribute("data-lf-hero-bg-mode") || "image");
			if (["color", "image", "video"].indexOf(heroSettingsState.mode) < 0) {
				heroSettingsState.mode = "image";
			}
			heroSettingsState.imageId = parseInt(String(heroEl.getAttribute("data-lf-hero-bg-image-id") || "0"), 10) || 0;
			heroSettingsState.videoId = parseInt(String(heroEl.getAttribute("data-lf-hero-bg-video-id") || "0"), 10) || 0;
			ensureHeroSettingsPicker();
			refreshHeroSettingsPickerUi();
			heroSettingsPickerEl.hidden = false;
		}
		function persistHeroSettings() {
			var wrap = heroSettingsPickerWrap;
			if (!wrap) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			if (!sectionId) {
				setStatus("Missing section id for hero.", true);
				return;
			}
			var ctx = persistContextFromWrap(wrap);
			setStatus("Saving hero settings...", false);
			var mode = String(heroSettingsState.mode || "image");
			var imageIdToSend = (mode === "image") ? (parseInt(String(heroSettingsState.imageId || "0"), 10) || 0) : 0;
			var videoIdToSend = (mode === "video") ? (parseInt(String(heroSettingsState.videoId || "0"), 10) || 0) : 0;
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_update_hero_settings",
				nonce: lfAiFloating.nonce,
				context_type: ctx.context_type,
				context_id: ctx.context_id,
				section_id: sectionId,
				hero_variant: heroSettingsState.variant,
				hero_background_mode: mode,
				hero_background_image_id: String(imageIdToSend),
				hero_background_video_id: String(videoIdToSend)
			}).done(function(res){
				if (res && res.success) {
					closeHeroSettingsPicker();
					setStatus((res.data && res.data.message) ? res.data.message : "Saved.", false);
					if (res.data && res.data.reload) {
						window.location.reload();
					}
				} else {
					var msg = (res && res.data && res.data.message) ? res.data.message : "Hero settings save failed.";
					setStatus(msg, true);
					try { window.alert(msg); } catch (e) {}
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
					? xhr.responseJSON.data.message
					: ("Hero settings save failed. (" + String(xhr.status || "") + ")");
				setStatus(msg, true);
				try { window.alert(msg); } catch (e) {}
			});
		}
		function persistSectionStyle(wrap, patch, extra) {
			if (!wrap || !patch) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			if (!sectionId) return;
			var ctx = persistContextFromWrap(wrap);
			setStatus("Updating section style...", false);
			var payload = {
				action: "lf_ai_update_section_style",
				nonce: lfAiFloating.nonce,
				context_type: ctx.context_type,
				context_id: ctx.context_id,
				section_id: sectionId,
				patch: String(patch)
			};
			if (extra && typeof extra === "object") {
				if (extra.background_slug) payload.background_slug = String(extra.background_slug);
				if (extra.custom_background) payload.custom_background = String(extra.custom_background);
				if (Object.prototype.hasOwnProperty.call(extra, "header_align")) payload.header_align = String(extra.header_align || "");
				if (Object.prototype.hasOwnProperty.call(extra, "benefits_cta_align")) payload.benefits_cta_align = String(extra.benefits_cta_align || "");
				if (extra.grid_columns) payload.grid_columns = String(extra.grid_columns);
			}
			$.post(lfAiFloating.ajax_url, payload).done(function(res){
				if (res && res.success) {
					setStatus((res.data && res.data.message) ? res.data.message : "Saved.", false);
					if (res.data && res.data.reload) {
						window.location.reload();
					}
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "Style update failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Style update failed.";
				setStatus(msg, true);
			});
		}
		function persistSectionStylePatch(wrap, patch) {
			persistSectionStyle(wrap, patch, null);
		}
		function moveSectionByStep(wrap, delta) {
			if (!wrap || !delta) return;
			var wraps = collectSectionWrappers();
			var idx = wraps.indexOf(wrap);
			if (idx < 0) return;
			var targetIdx = idx + delta;
			if (targetIdx < 0 || targetIdx >= wraps.length) return;
			var target = wraps[targetIdx];
			if (!target || !target.parentNode) return;
			if (delta < 0) {
				target.parentNode.insertBefore(wrap, target);
			} else {
				target.parentNode.insertBefore(wrap, target.nextSibling);
			}
			setSelectedSection(wrap);
			persistSectionOrder();
		}
		function buildSectionControls() {
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				var controls = wrap.querySelector(".lf-ai-section-controls");
				if (!controls) {
					controls = document.createElement("div");
					controls.className = "lf-ai-section-controls lf-ai-inline-editor-ignore";
					wrap.appendChild(controls);
				}
				var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
				var ensureBtn = function(text, title, ariaLabel, onClick) {
					if (!controls) return null;
					var btns = controls.querySelectorAll("button");
					for (var i = 0; i < btns.length; i++) {
						if (String(btns[i].textContent || "").trim() === text) return btns[i];
					}
					var btn = document.createElement("button");
					btn.type = "button";
					btn.className = "lf-ai-section-btn";
					btn.textContent = text;
					if (title) btn.setAttribute("title", title);
					if (ariaLabel) btn.setAttribute("aria-label", ariaLabel);
					btn.addEventListener("click", function(e){
						e.preventDefault();
						e.stopPropagation();
						try { onClick(); } catch (err) {}
					});
					controls.appendChild(btn);
					return btn;
				};
				ensureBtn("BG", "Choose section background (theme presets or custom color)", "Choose section background", function(){
					openSectionBgPicker(wrap);
				});
				if (sectionType === "trust_reviews") {
					ensureBtn("Layout", "Cycle review layout: slider → masonry → grid", "Cycle review layout", function(){
						persistTrustLayout(wrap);
					});
				}
				if (sectionType === "hero") {
					var heroBtn = document.createElement("button");
					heroBtn.type = "button";
					heroBtn.className = "lf-ai-section-btn";
					heroBtn.textContent = "Hero";
					heroBtn.setAttribute("title", "Hero layout variant and background (image, color, or video)");
					heroBtn.setAttribute("aria-label", "Hero layout and background");
					heroBtn.addEventListener("click", function(e){
						e.preventDefault();
						e.stopPropagation();
						openHeroSettingsPicker(wrap);
					});
					controls.appendChild(heroBtn);
				}
				if (sectionSupportsHeaderAlign(wrap)) {
					var alignBtn = document.createElement("button");
					alignBtn.type = "button";
					alignBtn.className = "lf-ai-section-btn";
					alignBtn.textContent = "Align";
					alignBtn.setAttribute("title", "Header alignment; for benefits sections, also button row alignment");
					alignBtn.setAttribute("aria-label", "Set section header alignment");
					alignBtn.addEventListener("click", function(e){
						e.preventDefault();
						e.stopPropagation();
						openSectionAlignPicker(wrap);
					});
					controls.appendChild(alignBtn);
				}
				if (sectionType === "benefits") {
					ensureBtn("Grid", "Card columns: 2, 3, or 4 on desktop", "Set benefit columns", function(){
						openSectionGridPicker(wrap);
					});
					ensureBtn("CTA", "Add or edit the optional benefits button", "Benefits button", function(){
						openBenefitsCtaPicker(wrap, null);
					});
				}
				if (sectionType === "service_intro") {
					ensureBtn("Grid", "Service cards per row: 2, 3, or 4 on desktop", "Set service grid columns", function(){
						openSectionGridPicker(wrap);
					});
				}
				if (sectionSupportsColumnSwap(sectionType)) {
					var swapBtn = document.createElement("button");
					swapBtn.type = "button";
					swapBtn.className = "lf-ai-section-btn";
					swapBtn.textContent = "⇆";
					swapBtn.setAttribute("title", "Reverse image/content columns");
					swapBtn.setAttribute("aria-label", "Reverse image/content columns");
					swapBtn.addEventListener("click", function(e){
						e.preventDefault();
						e.stopPropagation();
						persistSectionColumnSwap(wrap);
					});
					controls.appendChild(swapBtn);
				}
				var upBtn = document.createElement("button");
				upBtn.type = "button";
				upBtn.className = "lf-ai-section-btn";
				upBtn.textContent = "↑";
				upBtn.setAttribute("title", "Move this block up on the page (or drag the section / use the Structure panel)");
				upBtn.setAttribute("aria-label", "Move section up");
				upBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					moveSectionByStep(wrap, -1);
				});
				controls.appendChild(upBtn);
				var downBtn = document.createElement("button");
				downBtn.type = "button";
				downBtn.className = "lf-ai-section-btn";
				downBtn.textContent = "↓";
				downBtn.setAttribute("title", "Move this block down on the page (or drag the section / use the Structure panel)");
				downBtn.setAttribute("aria-label", "Move section down");
				downBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					moveSectionByStep(wrap, 1);
				});
				controls.appendChild(downBtn);
				if (sectionSupportsDuplicate(sectionType, String(wrap.getAttribute("data-lf-section-id") || ""))) {
					var dupBtn = document.createElement("button");
					dupBtn.type = "button";
					dupBtn.className = "lf-ai-section-btn";
					dupBtn.textContent = "Dup";
					dupBtn.setAttribute("title", "Duplicate section");
					dupBtn.setAttribute("aria-label", "Duplicate section");
					dupBtn.addEventListener("click", function(e){
						e.preventDefault();
						e.stopPropagation();
						persistSectionDuplicate(wrap);
					});
					controls.appendChild(dupBtn);
				}
				var toggleBtn = document.createElement("button");
				toggleBtn.type = "button";
				toggleBtn.className = "lf-ai-section-btn";
				toggleBtn.setAttribute("data-lf-section-toggle", "1");
				toggleBtn.textContent = "Hide";
				toggleBtn.setAttribute("title", "Hide or show section");
				toggleBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					var isVisible = String(wrap.getAttribute("data-lf-section-visible") || "1") !== "0";
					persistSectionVisibility(wrap, !isVisible);
				});
				controls.appendChild(toggleBtn);
				var deleteBtn = document.createElement("button");
				deleteBtn.type = "button";
				deleteBtn.className = "lf-ai-section-btn lf-ai-section-btn--danger";
				deleteBtn.textContent = "Del";
				deleteBtn.setAttribute("title", "Delete section");
				deleteBtn.setAttribute("aria-label", "Delete section");
				deleteBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					var ok = false;
					try {
						ok = window.confirm("Delete this section? You can undo after deleting.");
					} catch (err) {
						ok = true;
					}
					if (ok) {
						persistSectionDelete(wrap);
					}
				});
				controls.appendChild(deleteBtn);
				// If controls existed already, we only ensure missing buttons above.
				if (!wrap.querySelector(".lf-ai-section-controls")) {
					wrap.appendChild(controls);
				}
				applySectionVisibilityUi(wrap, String(wrap.getAttribute("data-lf-section-visible") || "1") !== "0");
			});
		}
		function buildSectionColumnSwapTargets() {
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
				if (!sectionSupportsColumnSwap(sectionType)) return;
				var details = wrap.querySelector(".lf-service-details--media");
				if (!details) return;
				var contentNode = null;
				var mediaNode = null;
				Array.prototype.slice.call(details.children || []).forEach(function(child){
					if (!child || !child.classList) return;
					if (child.classList.contains("lf-service-details__content")) contentNode = child;
					if (child.classList.contains("lf-service-details__media")) mediaNode = child;
				});
				if (!contentNode || !mediaNode) return;
				[contentNode, mediaNode].forEach(function(col){
					col.classList.add("lf-ai-column-draggable", "lf-ai-inline-editor-ignore");
					col.setAttribute("draggable", "true");
					col.ondragstart = function(e){
						var role = col.classList.contains("lf-service-details__media") ? "media" : "content";
						activeColumnDrag = { wrap: wrap, role: role };
						col.classList.add("is-dragging");
						if (e.dataTransfer) {
							e.dataTransfer.effectAllowed = "move";
							e.dataTransfer.setData("text/plain", role);
						}
						suppressInlineClickUntil = Date.now() + 350;
					};
					col.ondragover = function(e){
						if (!activeColumnDrag || activeColumnDrag.wrap !== wrap) return;
						e.preventDefault();
					};
					col.ondragend = function(){
						col.classList.remove("is-dragging");
						activeColumnDrag = null;
					};
				});
				details.ondragover = function(e){
					if (!activeColumnDrag || activeColumnDrag.wrap !== wrap) return;
					e.preventDefault();
				};
				details.ondrop = function(e){
					if (!activeColumnDrag || activeColumnDrag.wrap !== wrap) return;
					e.preventDefault();
					var rect = details.getBoundingClientRect();
					var dropOnLeftHalf = e.clientX < (rect.left + rect.width / 2);
					var currentMediaLeft = details.classList.contains("lf-service-details--media-left");
					var desiredMediaLeft = dropOnLeftHalf;
					if (desiredMediaLeft !== currentMediaLeft) {
						persistSectionColumnSwap(wrap);
					}
				};
			});
		}
		function reorderSectionInDom(targetWrap, clientY) {
			if (!activeDragSection || !targetWrap || targetWrap === activeDragSection) {
				return;
			}
			var rect = targetWrap.getBoundingClientRect();
			var after = clientY > (rect.top + rect.height / 2);
			if (after) {
				if (targetWrap.nextSibling !== activeDragSection) {
					targetWrap.parentNode.insertBefore(activeDragSection, targetWrap.nextSibling);
				}
			} else if (targetWrap.previousSibling !== activeDragSection) {
				targetWrap.parentNode.insertBefore(activeDragSection, targetWrap);
			}
		}
		function persistSectionOrder() {
			var ids = collectSectionWrappers().map(function(node){
				return String(node.getAttribute("data-lf-section-id") || "");
			}).filter(function(id){ return id !== ""; });
			if (!ids.length) {
				return;
			}
			var ctx = persistContextFromWrap(collectSectionWrappers()[0]);
			setStatus((lfAiFloating.i18n && lfAiFloating.i18n.statusReordering) ? lfAiFloating.i18n.statusReordering : "Saving section order...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_reorder_sections",
				nonce: lfAiFloating.nonce,
				context_type: ctx.context_type,
				context_id: ctx.context_id,
				ordered_ids: JSON.stringify(ids)
			}).done(function(res){
				if (res && res.success) {
					setStatus((res.data && res.data.message) ? res.data.message : "Section order saved.", false);
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "Section reorder failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Section reorder failed.";
				setStatus(msg, true);
			});
		}
		function buildSectionTargets() {
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				wrap.onmousedown = function(e){
					if (!e || !e.target) return;
					var t = e.target.nodeType === 1 ? e.target : e.target.parentElement;
					if (!t) return;
					// Do not reselect/rebuild when clicking inline editor controls.
					if (isDragBlockedTarget(t)) return;
					if ((t.closest && t.closest(".lf-ai-section-controls")) || (t.closest && t.closest(".lf-ai-section-insert")) || (t.closest && t.closest(".lf-ai-float"))) return;
					setSelectedSection(wrap);
				};
				wrap.setAttribute("draggable", "true");
				wrap.ondragstart = function(e){
					if (isDragBlockedTarget(e.target)) {
						// Allow nested draggable controls (e.g. column swap drag handles) to work.
						// Only cancel when the blocked target is not itself part of another draggable UI.
						var nestedDraggable = null;
						try {
							nestedDraggable = e && e.target && e.target.closest ? e.target.closest("[draggable=true]") : null;
						} catch (err) {
							nestedDraggable = null;
						}
						if (!nestedDraggable || nestedDraggable === wrap) {
							e.preventDefault();
						}
						return;
					}
					if (activeColumnDrag) {
						e.preventDefault();
						return;
					}
					suppressInlineClickUntil = Date.now() + 350;
					if (inlineActiveEl) {
						saveInlineEdit();
					}
					activeDragSection = wrap;
					wrap.classList.add("is-dragging");
					if (e.dataTransfer) {
						e.dataTransfer.effectAllowed = "move";
						e.dataTransfer.setData("text/plain", String(wrap.getAttribute("data-lf-section-id") || ""));
					}
				};
				wrap.ondragover = function(e){
					if (activeLibraryDragSectionType) {
						e.preventDefault();
						return;
					}
					if (!activeDragSection || wrap === activeDragSection) return;
					e.preventDefault();
					reorderSectionInDom(wrap, e.clientY);
				};
				wrap.ondrop = function(e){
					if (activeLibraryDragSectionType) {
						e.preventDefault();
						addSectionFromLibrary(activeLibraryDragSectionType, String(wrap.getAttribute("data-lf-section-id") || ""));
						activeLibraryDragSectionType = "";
						return;
					}
					e.preventDefault();
				};
				wrap.ondragend = function(){
					if (activeDragSection) {
						activeDragSection.classList.remove("is-dragging");
					}
					activeDragSection = null;
					persistSectionOrder();
				};
			});
		}
		function beginInlineEdit(el) {
			if (!el || inlineIsSaving) return;
			var selector = String(el.getAttribute("data-lf-inline-selector") || "");
			if (!selector) return;
			if (inlineActiveEl && inlineActiveEl !== el) {
				saveInlineEdit(function(){
					beginInlineEdit(el);
				});
				return;
			}
			if (inlineActiveEl === el) {
				return;
			}
			inlineActiveEl = el;
			inlineBodyEditSourceEl = null;
			inlineOriginalBodyHtml = "";
			inlineOriginalBodyText = "";
			var fkInline = String(el.getAttribute("data-lf-inline-field-key") || "");
			var srcSel = String(el.getAttribute("data-lf-inline-source-selector") || "");
			if (fkInline === "service_details_body" && srcSel) {
				try {
					inlineBodyEditSourceEl = document.querySelector(srcSel);
				} catch (errSrc) {
					inlineBodyEditSourceEl = null;
				}
				if (inlineBodyEditSourceEl) {
					inlineOriginalBodyHtml = String(inlineBodyEditSourceEl.innerHTML || "").trim();
					inlineOriginalBodyText = String(inlineBodyEditSourceEl.textContent || "").trim();
					inlineOriginalText = inlineOriginalBodyText;
					inlineOriginalHtml = inlineOriginalBodyHtml;
				} else {
					inlineOriginalText = String(el.textContent || "").trim();
					inlineOriginalHtml = String(el.innerHTML || "").trim();
				}
			} else {
				inlineOriginalText = String(el.textContent || "").trim();
				inlineOriginalHtml = String(el.innerHTML || "").trim();
			}
			el.setAttribute("data-lf-inline-active", "1");
			el.setAttribute("contenteditable", "true");
			el.setAttribute("spellcheck", "true");
			try { el.focus(); } catch (e) {}
			setStatus("Editing text. Click away to auto-save.", false);
			el.addEventListener("blur", function onBlur(){
				setTimeout(function(){
					if (inlineActiveEl === el) {
						saveInlineEdit();
					}
				}, 0);
			}, { once: true });
		}
		function cancelInlineEdit(showStatus) {
			if (!inlineActiveEl) {
				return;
			}
			if (inlineBodyEditSourceEl && inlineActiveEl && inlineBodyEditSourceEl.contains && inlineBodyEditSourceEl.contains(inlineActiveEl)) {
				inlineBodyEditSourceEl.innerHTML = inlineOriginalBodyHtml;
			} else {
				inlineActiveEl.innerHTML = inlineOriginalHtml;
			}
			inlineActiveEl.removeAttribute("contenteditable");
			inlineActiveEl.removeAttribute("spellcheck");
			inlineActiveEl.removeAttribute("data-lf-inline-active");
			inlineActiveEl.removeAttribute("data-lf-inline-saving");
			inlineActiveEl = null;
			inlineOriginalText = "";
			inlineOriginalHtml = "";
			inlineBodyEditSourceEl = null;
			inlineOriginalBodyHtml = "";
			inlineOriginalBodyText = "";
			if (showStatus !== false) {
				setStatus("Inline edit cancelled.", false);
			}
		}
		function saveInlineEdit(done) {
			if (!inlineActiveEl || inlineIsSaving) {
				if (typeof done === "function") done();
				return;
			}
			var el = inlineActiveEl;
			var selector = String(el.getAttribute("data-lf-inline-selector") || "");
			var fieldKey = String(el.getAttribute("data-lf-inline-field-key") || "");
			var sectionId = String(el.getAttribute("data-lf-inline-section-id") || "");
			var sourceSelector = String(el.getAttribute("data-lf-inline-source-selector") || "");
			var sourceNode = null;
			if (sourceSelector) {
				try {
					sourceNode = document.querySelector(sourceSelector);
				} catch (errQ) {
					sourceNode = null;
				}
			}
			var valueNode = sourceNode || el;
			var newText = String(valueNode.textContent || "").trim();
			var newHtml = String(valueNode.innerHTML || "").trim();
			var compareText = inlineBodyEditSourceEl ? inlineOriginalBodyText : String(inlineOriginalText || "");
			var compareHtml = inlineBodyEditSourceEl ? inlineOriginalBodyHtml : String(inlineOriginalHtml || "");
			var origHtml = String(compareHtml || "").trim();
			var textChanged = (newText !== compareText);
			var htmlChanged = (newHtml !== origHtml);
			if (!selector && !fieldKey) {
				setStatus("Invalid inline target.", true);
				if (typeof done === "function") done();
				return;
			}
			if (newText === "") {
				setStatus("Text cannot be empty.", true);
				if (typeof done === "function") done();
				return;
			}
			if (!textChanged && !htmlChanged) {
				el.removeAttribute("contenteditable");
				el.removeAttribute("spellcheck");
				el.removeAttribute("data-lf-inline-active");
				el.removeAttribute("data-lf-inline-saving");
				inlineActiveEl = null;
				inlineOriginalText = "";
				inlineOriginalHtml = "";
				inlineBodyEditSourceEl = null;
				inlineOriginalBodyHtml = "";
				inlineOriginalBodyText = "";
				if (typeof done === "function") done();
				return;
			}
			inlineIsSaving = true;
			el.setAttribute("data-lf-inline-saving", "1");
			setStatus("Saving inline edit...", false);
			var useHtml = /<[a-z]/i.test(newHtml);
			var wrap = el && el.closest ? el.closest("[data-lf-section-wrap=\"1\"][data-lf-section-id]") : null;
			var ctx = persistContextFromWrap(wrap);
			var payload = {
				action: "lf_ai_inline_save",
				nonce: lfAiFloating.nonce,
				context_type: ctx.context_type,
				context_id: ctx.context_id,
				value: useHtml ? newHtml : newText,
				value_format: useHtml ? "html" : "text"
			};
			if (fieldKey) {
				payload.field_key = fieldKey;
			} else {
				payload.selector = selector;
			}
			if (sectionId) {
				payload.section_id = sectionId;
			}
			var svcPostId = String(el.getAttribute("data-lf-service-post-id") || "");
			if (fieldKey === "lf_service_short_desc" && svcPostId) {
				payload.service_post_id = svcPostId;
			}
			$.post(lfAiFloating.ajax_url, payload).done(function(res){
				if (res && res.success) {
					el.removeAttribute("contenteditable");
					el.removeAttribute("spellcheck");
					el.removeAttribute("data-lf-inline-active");
					el.removeAttribute("data-lf-inline-saving");
					if (inlineActiveEl === el) {
						inlineActiveEl = null;
						inlineOriginalText = "";
						inlineOriginalHtml = "";
						inlineBodyEditSourceEl = null;
						inlineOriginalBodyHtml = "";
						inlineOriginalBodyText = "";
					}
					setStatus("Saved. Use the ↶ icon to undo (repeat to go back further).", false);
				} else {
					el.removeAttribute("data-lf-inline-saving");
					setStatus((res && res.data && res.data.message) ? res.data.message : "Inline save failed.", true);
				}
			}).fail(function(xhr){
				el.removeAttribute("data-lf-inline-saving");
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Inline save failed.";
				setStatus(msg, true);
			}).always(function(){
				inlineIsSaving = false;
				if (typeof done === "function") done();
			});
		}
		function persistInlineNodeNow(el, successMessage, done) {
			if (!el) {
				if (typeof done === "function") done(false);
				return;
			}
			var selector = String(el.getAttribute("data-lf-inline-selector") || "");
			var fieldKey = String(el.getAttribute("data-lf-inline-field-key") || "");
			var sectionId = String(el.getAttribute("data-lf-inline-section-id") || "");
			var sourceSelector = String(el.getAttribute("data-lf-inline-source-selector") || "");
			if (!selector && !fieldKey) {
				if (typeof done === "function") done(false);
				return;
			}
			var sourceNode = null;
			if (sourceSelector) {
				try {
					sourceNode = document.querySelector(sourceSelector);
				} catch (errQ) {
					sourceNode = null;
				}
			}
			var valueNode = sourceNode || el;
			if (sourceSelector && sourceNode) {
				selector = sourceSelector;
			}
			var valueText = String(valueNode.textContent || "").trim();
			var valueHtml = String(valueNode.innerHTML || "").trim();
			if (valueText === "") {
				setStatus("Text cannot be empty.", true);
				if (typeof done === "function") done(false);
				return;
			}
			var useHtml = /<[a-z]/i.test(valueHtml);
			var wrap = el && el.closest ? el.closest("[data-lf-section-wrap=\"1\"][data-lf-section-id]") : null;
			var ctx = persistContextFromWrap(wrap);
			var payload = {
				action: "lf_ai_inline_save",
				nonce: lfAiFloating.nonce,
				context_type: ctx.context_type,
				context_id: ctx.context_id,
				value: useHtml ? valueHtml : valueText,
				value_format: useHtml ? "html" : "text"
			};
			if (fieldKey) {
				payload.field_key = fieldKey;
			} else {
				payload.selector = selector;
			}
			if (sectionId) {
				payload.section_id = sectionId;
			}
			setStatus("Saving inline edit...", false);
			$.post(lfAiFloating.ajax_url, payload).done(function(res){
				if (res && res.success) {
					setStatus(successMessage || "Saved.", false);
					if (typeof done === "function") done(true);
					return;
				}
				setStatus((res && res.data && res.data.message) ? res.data.message : "Inline save failed.", true);
				if (typeof done === "function") done(false);
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Inline save failed.";
				setStatus(msg, true);
				if (typeof done === "function") done(false);
			});
		}
		function renderDiff() {
			if (!proposed) return;
			var html = "";
			Object.keys(proposed).forEach(function(key){
				var fieldLabel = labels[key] || key;
				var oldVal = current && current[key] !== undefined ? current[key] : "";
				var newVal = proposed[key] !== undefined ? proposed[key] : "";
				html += "<div class=\"lf-ai-float__row\">"
					+ "<div class=\"lf-ai-float__field\">" + escapeHtml(fieldLabel) + "</div>"
					+ "<div class=\"lf-ai-float__cols\">"
					+ "<div class=\"lf-ai-float__col\"><b>Current</b>" + escapeHtml(oldVal) + "</div>"
					+ "<div class=\"lf-ai-float__col\"><b>Suggested</b>" + escapeHtml(newVal) + "</div>"
					+ "</div></div>";
			});
			$diff.html(html).prop("hidden", false);
		}
		function renderInlineQuickPreview(currentText, newText) {
			var html = "<div class=\"lf-ai-float__row\">"
				+ "<div class=\"lf-ai-float__field\">Matched on-page text</div>"
				+ "<div class=\"lf-ai-float__cols\">"
				+ "<div class=\"lf-ai-float__col\"><b>Current</b>" + escapeHtml(currentText) + "</div>"
				+ "<div class=\"lf-ai-float__col\"><b>Suggested</b>" + escapeHtml(newText) + "</div>"
				+ "</div></div>";
			$diff.html(html).prop("hidden", false);
		}
		function renderQaAnswer(answer, snapshot) {
			var text = String(answer || "").trim();
			var data = snapshot && typeof snapshot === "object" ? snapshot : {};
			var score = parseInt(String(data.backend_seo_score || "0"), 10);
			if (isNaN(score)) score = 0;
			var intent = String(data.intent || "");
			var primary = data.meta && data.meta.primary_keyword ? String(data.meta.primary_keyword) : "";
			var html = "<div class=\"lf-ai-float__row\">"
				+ "<div class=\"lf-ai-float__field\">AI Answer</div>"
				+ "<div>" + escapeHtml(text || "No answer returned.") + "</div>";
			if (score > 0 || intent || primary) {
				html += "<div style=\"margin-top:8px;font-size:11px;color:#475569;\">"
					+ (score > 0 ? ("SEO score: <b>" + score + "/100</b>. ") : "")
					+ (intent ? ("Intent: <b>" + escapeHtml(intent) + "</b>. ") : "")
					+ (primary ? ("Primary keyword: <b>" + escapeHtml(primary) + "</b>.") : "")
					+ "</div>";
			}
			html += "</div>";
			$diff.html(html).prop("hidden", false);
		}
		function renderCreationPreview(data) {
			var html = "";
			var title = data && data.title ? data.title : "";
			var type = data && data.type ? data.type : "";
			var status = data && data.status ? data.status : "draft";
			var notes = data && data.notes && Array.isArray(data.notes) ? data.notes : [];
			html += "<div class=\"lf-ai-float__row\">"
				+ "<div class=\"lf-ai-float__field\">Draft Preview</div>"
				+ "<div><b>Title:</b> " + escapeHtml(title) + "</div>"
				+ "<div><b>Type:</b> " + escapeHtml(type) + "</div>"
				+ "<div><b>Status:</b> " + escapeHtml(status) + "</div>";
			if (notes.length) {
				html += "<div style=\"margin-top:6px;\"><b>Notes:</b><ul style=\"margin:6px 0 0 16px;\">";
				notes.forEach(function(note){
					html += "<li>" + escapeHtml(note) + "</li>";
				});
				html += "</ul></div>";
			}
			html += "</div>";
			$diff.html(html).prop("hidden", false);
		}
		function renderCreationQueue(items) {
			var rows = Array.isArray(items) ? items : [];
			if (!rows.length) {
				$diff.html("").prop("hidden", true);
				return;
			}
			var html = "<div class=\"lf-ai-float__row\"><div class=\"lf-ai-float__field\">Batch Draft Queue (" + rows.length + ")</div></div>";
			rows.forEach(function(item, idx){
				var notes = item && item.notes && Array.isArray(item.notes) ? item.notes : [];
				html += "<div class=\"lf-ai-float__row\">"
					+ "<div class=\"lf-ai-float__field\">#" + (idx + 1) + " " + escapeHtml(item && item.title ? item.title : "") + "</div>"
					+ "<div><b>Type:</b> " + escapeHtml(item && item.type ? item.type : "") + " | <b>Status:</b> draft</div>";
				if (notes.length) {
					html += "<div style=\"margin-top:6px;\"><ul style=\"margin:0 0 0 16px;\">";
					notes.forEach(function(note){ html += "<li>" + escapeHtml(note) + "</li>"; });
					html += "</ul></div>";
				}
				html += "</div>";
			});
			$diff.html(html).prop("hidden", false);
		}
		function setProposalEnabled(enabled){
			$btnApply.prop("disabled", !enabled);
			$btnReject.prop("disabled", !enabled);
		}
		function modeValue() {
			return String($mode.val() || "auto");
		}
		function cptTypeValue() {
			return String($cptType.val() || "lf_service");
		}
		function batchTypeValue() {
			return String($batchType.val() || "post");
		}
		function batchCountValue() {
			var n = parseInt(String($batchCount.val() || "5"), 10);
			if (!n || n < 1) n = 1;
			if (n > 20) n = 20;
			activeAssistantBatchCount = n;
			return n;
		}
		function syncModeUi() {
			var mode = modeValue();
			$cptWrap.prop("hidden", mode !== "create_cpt");
			$batchWrap.prop("hidden", mode !== "create_batch");
			$batchCountWrap.prop("hidden", mode !== "create_batch");
			var targetSuffix = (mode === "edit_existing" || mode === "auto") ? " (editable target)" : " (context source)";
			$target.text("Target: " + (activeTargetLabel || "Homepage") + targetSuffix);
		}
		function setDocState(name, content) {
			docLabel = String(name || "");
			docContext = String(content || "");
			$docName.text(docLabel !== "" ? ("Attached: " + docLabel) : "");
			$docClear.prop("hidden", docLabel === "");
		}
		function parseDoc(file) {
			var form = new FormData();
			form.append("action", "lf_ai_extract_context_doc");
			form.append("nonce", lfAiFloating.nonce);
			form.append("document", file);
			setStatus(lfAiFloating.i18n && lfAiFloating.i18n.statusParsingDoc ? lfAiFloating.i18n.statusParsingDoc : "Reading document...", false);
			$.ajax({
				url: lfAiFloating.ajax_url,
				type: "POST",
				data: form,
				processData: false,
				contentType: false
			}).done(function(res){
				if (res && res.success && res.data) {
					setDocState(res.data.name || file.name || "document", res.data.context || "");
					setStatus("Document context attached.", false);
				} else {
					setDocState("", "");
					setStatus((res && res.data && res.data.message) ? res.data.message : "Unable to parse document.", true);
				}
			}).fail(function(xhr){
				setDocState("", "");
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Document upload failed.";
				setStatus(msg, true);
			});
		}
		function setConfirmOpen(open) {
			$confirm.prop("hidden", !open);
		}
		function openConfirm(message, yesLabel, onYes) {
			pendingConfirmAction = typeof onYes === "function" ? onYes : null;
			$confirmText.text(String(message || defaultConfirmText));
			$confirmYes.text(String(yesLabel || defaultConfirmYesText));
			setConfirmOpen(true);
		}
		function runRollback() {
			setStatus(lfAiFloating.i18n && lfAiFloating.i18n.statusReverting ? lfAiFloating.i18n.statusReverting : "Reverting...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_rollback_latest",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType || "homepage",
				context_id: activeContextId || "homepage"
			}).done(function(res){
				if (res && res.success && res.data && res.data.reload) {
					window.location.reload();
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "No rollback available.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Rollback failed.";
				setStatus(msg, true);
			});
		}
		function runRedo() {
			setStatus(lfAiFloating.i18n && lfAiFloating.i18n.statusRedoing ? lfAiFloating.i18n.statusRedoing : "Redoing...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_redo_latest",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType || "homepage",
				context_id: activeContextId || "homepage"
			}).done(function(res){
				if (res && res.success && res.data && res.data.reload) {
					window.location.reload();
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "No redo available.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Redo failed.";
				setStatus(msg, true);
			});
		}
		function selectedSectionCanReverse() {
			if (!selectedSectionWrap) return false;
			return sectionSupportsColumnSwap(String(selectedSectionWrap.getAttribute("data-lf-section-type") || ""));
		}
		function selectedSectionToggleVisibility() {
			if (!selectedSectionWrap) return;
			var isVisible = String(selectedSectionWrap.getAttribute("data-lf-section-visible") || "1") !== "0";
			persistSectionVisibility(selectedSectionWrap, !isVisible);
		}
		function selectedSectionDelete() {
			if (!selectedSectionWrap) return;
			var targetWrap = selectedSectionWrap;
			var ok = false;
			try {
				ok = window.confirm("Delete this section? You can undo after deleting.");
			} catch (e) {
				ok = true;
			}
			if (!ok) return;
			persistSectionDelete(targetWrap);
		}
		function selectedSectionReverseColumns() {
			if (!selectedSectionWrap || !selectedSectionCanReverse()) return;
			persistSectionColumnSwap(selectedSectionWrap);
		}
		function selectedSectionDuplicate() {
			if (!selectedSectionWrap) return;
			var type = String(selectedSectionWrap.getAttribute("data-lf-section-type") || "");
			var id = String(selectedSectionWrap.getAttribute("data-lf-section-id") || "");
			if (!sectionSupportsDuplicate(type, id)) return;
			persistSectionDuplicate(selectedSectionWrap);
		}
		function ensureCommandPalette() {
			if (commandPaletteEl) return commandPaletteEl;
			commandPaletteEl = document.createElement("div");
			commandPaletteEl.className = "lf-ai-command lf-ai-inline-editor-ignore";
			commandPaletteEl.hidden = true;
			commandPaletteEl.innerHTML = "<input type=\"text\" class=\"lf-ai-command__input\" placeholder=\"Type a command...\" /><div class=\"lf-ai-command__list\"></div><div class=\"lf-ai-command__hint\">Enter to run • Esc to close • \u2318/\u2303+K to toggle</div>";
			document.body.appendChild(commandPaletteEl);
			commandInputEl = commandPaletteEl.querySelector(".lf-ai-command__input");
			commandListEl = commandPaletteEl.querySelector(".lf-ai-command__list");
			commandInputEl.addEventListener("input", refreshCommandPalette);
			commandInputEl.addEventListener("keydown", function(e){
				if (e.key === "ArrowDown") {
					e.preventDefault();
					commandActiveIndex = Math.min(commandRows.length - 1, commandActiveIndex + 1);
					refreshCommandPalette();
				} else if (e.key === "ArrowUp") {
					e.preventDefault();
					commandActiveIndex = Math.max(0, commandActiveIndex - 1);
					refreshCommandPalette();
				} else if (e.key === "Enter") {
					e.preventDefault();
					executeCommandAt(commandActiveIndex);
				} else if (e.key === "Escape") {
					e.preventDefault();
					toggleCommandPalette(false);
				}
			});
			commandInputEl.addEventListener("keyup", function(e){
				if (e.key === "Enter") {
					e.preventDefault();
					executeCommandAt(commandActiveIndex);
				}
			});
			commandPaletteEl.addEventListener("click", function(e){
				if (e.target === commandPaletteEl) {
					toggleCommandPalette(false);
				}
			});
			return commandPaletteEl;
		}
		function commandItems() {
			var hasSel = !!selectedSectionWrap;
			var visible = hasSel ? (String(selectedSectionWrap.getAttribute("data-lf-section-visible") || "1") !== "0") : true;
			return [
				{ label: "Focus AI prompt", enabled: true, run: function(){ setAiOpen(true); setSeoOpen(false); try { $prompt.trigger("focus"); } catch (e) {} } },
				{ label: "Undo last action", enabled: true, run: runRollback },
				{ label: "Redo last action", enabled: true, run: runRedo },
				{ label: "Move selected section up", enabled: hasSel, run: function(){ moveSelectedSection(-1); } },
				{ label: "Move selected section down", enabled: hasSel, run: function(){ moveSelectedSection(1); } },
				{ label: visible ? "Hide selected section" : "Show selected section", enabled: hasSel, run: selectedSectionToggleVisibility },
				{ label: "Delete selected section", enabled: hasSel, run: selectedSectionDelete },
				{ label: "Duplicate selected section", enabled: hasSel && sectionSupportsDuplicate(selectedSectionWrap ? selectedSectionWrap.getAttribute("data-lf-section-type") : "", selectedSectionWrap ? selectedSectionWrap.getAttribute("data-lf-section-id") : ""), run: selectedSectionDuplicate },
				{ label: "Reverse selected columns", enabled: hasSel && selectedSectionCanReverse(), run: selectedSectionReverseColumns }
			];
		}
		function refreshCommandPalette() {
			if (!commandPaletteEl || !commandListEl || commandPaletteEl.hidden) return;
			var q = String(commandInputEl && commandInputEl.value ? commandInputEl.value : "").trim().toLowerCase();
			commandRows = commandItems().filter(function(item){
				if (!item.enabled) return false;
				if (!q) return true;
				return String(item.label || "").toLowerCase().indexOf(q) !== -1;
			});
			if (commandActiveIndex >= commandRows.length) {
				commandActiveIndex = Math.max(0, commandRows.length - 1);
			}
			commandListEl.innerHTML = "";
			commandRows.forEach(function(item, idx){
				var row = document.createElement("div");
				row.className = "lf-ai-command__row" + (idx === commandActiveIndex ? " is-active" : "");
				row.setAttribute("tabindex", "0");
				row.textContent = String(item.label || "");
				row.addEventListener("mouseenter", function(){
					commandActiveIndex = idx;
					refreshCommandPalette();
				});
				row.addEventListener("click", function(){
					executeCommandAt(idx);
				});
				row.addEventListener("keydown", function(e){
					if (e.key === "Enter" || e.key === " ") {
						e.preventDefault();
						executeCommandAt(idx);
					}
				});
				commandListEl.appendChild(row);
			});
		}
		function executeCommandAt(index) {
			var idx = parseInt(String(index), 10);
			if (isNaN(idx) || idx < 0 || idx >= commandRows.length) {
				setStatus("No command selected.", true);
				return;
			}
			var cmd = commandRows[idx] || null;
			if (!cmd || typeof cmd.run !== "function") {
				setStatus("Selected command is unavailable.", true);
				return;
			}
			try {
				cmd.run();
				setStatus("Ran command: " + String(cmd.label || "action"), false);
			} catch (err) {
				setStatus("Command failed.", true);
			}
			toggleCommandPalette(false);
		}
		function toggleCommandPalette(open) {
			ensureCommandPalette();
			commandPaletteEl.hidden = !open;
			if (open) {
				commandActiveIndex = 0;
				commandInputEl.value = "";
				refreshCommandPalette();
				try { commandInputEl.focus(); } catch (e) {}
			}
		}
		function showShortcutHelp() {
			var lines = [
				"Shortcuts:",
				"Cmd/Ctrl+Z = Undo",
				"Cmd/Ctrl+Shift+Z or Ctrl+Y = Redo",
				"Cmd/Ctrl+K = Command Palette",
				"/ = Command Palette",
				"D = Duplicate selected section",
				"H = Hide/Show selected section",
				"Delete/Backspace = Delete selected section",
				"Alt/Shift+Arrow Up/Down = Move section"
			];
			var msg = lines.join("\n");
			setStatus("Shortcut help opened.", false);
			try { window.alert(msg); } catch (e) {}
		}

		$toggle.on("click", function(){
			var willOpen = $panel.prop("hidden");
			setConfirmOpen(false);
			setAiOpen(willOpen);
			if (willOpen) setSeoOpen(false);
			updateLauncherOffsets();
		});
		$seoToggle.on("click", function(){
			var willOpen = $seoPanel.prop("hidden");
			setConfirmOpen(false);
			setSeoOpen(willOpen);
			if (willOpen) setAiOpen(false);
			updateLauncherOffsets();
		});
		$root.find("[data-lf-ai-close],[data-lf-ai-minimize]").on("click", function(){ setConfirmOpen(false); setAiOpen(false); });
		$seoRoot.find("[data-lf-ai-seo-close],[data-lf-ai-seo-minimize]").on("click", function(){ setSeoOpen(false); });
		$(window).on("resize", function(){ updateLauncherOffsets(); });
		$btnEditorToggle.on("click", function(){
			setEditorEnabled(!editingEnabled);
		});
		$root.find("[data-lf-ai-preset]").on("click", function(){
			var el = this;
			expandPbNextGenerate = !!(el && el.getAttribute && el.getAttribute("data-lf-ai-expand-pb") === "1");
			$prompt.val($(el).attr("data-lf-ai-preset") || "").trigger("focus");
		});
		$seoRefresh.on("click", function(e){
			e.preventDefault();
			renderSeoSnapshot();
			setStatus("SEO snapshot refreshed.", false);
		});
		$(document).on("click", "[data-lf-inline-image=\"1\"]", function(e){
			if (!editingEnabled) return;
			if (Date.now() < suppressInlineClickUntil) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			beginInlineImageReplace(this);
		});
		$(document).on("click", "[data-lf-inline-editable=\"1\"]", function(e){
			if (!editingEnabled) return;
			if (Date.now() < suppressInlineClickUntil) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			beginInlineEdit(this);
		});
		$(document).on("click", function(e){
			if (!inlineActiveEl || inlineIsSaving) {
				return;
			}
			var target = e.target;
			if (target === inlineActiveEl || inlineActiveEl.contains(target)) {
				return;
			}
			if ($(target).closest("[data-lf-ai-float],[data-lf-ai-seo-float]").length) {
				return;
			}
			saveInlineEdit();
		});
		$mode.on("change", function(){
			syncModeUi();
			var m = modeValue();
			if (m === "create_cpt" || m === "create_batch" || m === "create_page" || m === "create_blog_post") {
				try {
					var det = $root.find("details.lf-ai-float__advanced").get(0);
					if (det) det.open = true;
				} catch (err) {}
			}
		});
		$docAttach.on("click", function(){
			$docInput.trigger("click");
		});
		$docInput.on("change", function(){
			var file = this.files && this.files[0] ? this.files[0] : null;
			if (!file) {
				return;
			}
			parseDoc(file);
		});
		$docClear.on("click", function(){
			setDocState("", "");
			try { $docInput.val(""); } catch (e) {}
			setStatus("Document context removed.", false);
		});

		$btnGenerate.on("click", function(){
			var prompt = String($prompt.val() || "").trim();
			if (!prompt) {
				setStatus("Please enter a prompt.", true);
				return;
			}
			inlineQuickEdit = null;
			promptSnippet = prompt.length > 80 ? prompt.slice(0,77) + "..." : prompt;
			setStatus(lfAiFloating.i18n && lfAiFloating.i18n.statusGenerating ? lfAiFloating.i18n.statusGenerating : "Generating...", false);
			$diff.prop("hidden", true).empty();
			setProposalEnabled(false);
			proposed = null;
			pbPatchPending = false;
			lastProposalHomepageSectionId = "";
			current = null;
			creationPayload = null;
			var parsedInlineReplace = parseInlineReplacePrompt(prompt);
			if (parsedInlineReplace && parsedInlineReplace.from && parsedInlineReplace.to) {
				var resolvedInline = resolveInlineSelectorByText(parsedInlineReplace.from);
				if (resolvedInline && resolvedInline.selector) {
					inlineQuickEdit = {
						selector: resolvedInline.selector,
						value: parsedInlineReplace.to,
						current: resolvedInline.current || parsedInlineReplace.from
					};
					renderInlineQuickPreview(inlineQuickEdit.current, inlineQuickEdit.value);
					setStatus("Direct text replacement ready. Review and apply.", false);
					setProposalEnabled(true);
					return;
				}
			}
			var parsedInlineTarget = parseInlineTargetPrompt(prompt);
			if (parsedInlineTarget && parsedInlineTarget.from) {
				var resolvedTarget = resolveInlineSelectorByText(parsedInlineTarget.from);
				if (resolvedTarget && resolvedTarget.selector) {
					setStatus("Rewriting targeted text only...", false);
					$.post(lfAiFloating.ajax_url, {
						action: "lf_ai_inline_rewrite",
						nonce: lfAiFloating.nonce,
						context_type: activeContextType,
						context_id: activeContextId,
						selector: resolvedTarget.selector,
						current_text: resolvedTarget.current || parsedInlineTarget.from,
						prompt: prompt
					}).done(function(rewriteRes){
						if (rewriteRes && rewriteRes.success && rewriteRes.data && rewriteRes.data.rewritten_text) {
							inlineQuickEdit = {
								selector: resolvedTarget.selector,
								value: String(rewriteRes.data.rewritten_text),
								current: resolvedTarget.current || parsedInlineTarget.from
							};
							renderInlineQuickPreview(inlineQuickEdit.current, inlineQuickEdit.value);
							setStatus("Targeted rewrite ready. Review and apply.", false);
							setProposalEnabled(true);
						} else {
							setStatus((rewriteRes && rewriteRes.data && rewriteRes.data.message) ? rewriteRes.data.message : "Targeted rewrite failed.", true);
						}
					}).fail(function(xhr){
						var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Targeted rewrite failed.";
						setStatus(msg, true);
					});
					return;
				}
			}
			lastMode = modeValue();
			activeAssistantCptType = cptTypeValue();
			activeAssistantBatchType = batchTypeValue();
			activeAssistantBatchCount = batchCountValue();
			var postExpandPb = expandPbNextGenerate;
			expandPbNextGenerate = false;
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_generate",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				prompt: prompt,
				document_context: docContext,
				document_name: docLabel,
				assistant_mode: lastMode,
				assistant_cpt_type: activeAssistantCptType,
				assistant_batch_type: activeAssistantBatchType,
				assistant_batch_count: activeAssistantBatchCount,
				target_reference: String($targetRef.val() || "").trim(),
				selected_section_id: selectedSectionWrap ? String(selectedSectionWrap.getAttribute("data-lf-section-id") || "") : "",
				selected_section_type: selectedSectionWrap ? String(selectedSectionWrap.getAttribute("data-lf-section-type") || "") : "",
				expand_pb_sections: postExpandPb ? "1" : ""
			}).done(function(res){
				if (res && res.success && res.data) {
					if (res.data.context_type) activeContextType = String(res.data.context_type);
					if (res.data.context_id !== undefined) activeContextId = String(res.data.context_id);
					if (res.data.target_label) activeTargetLabel = String(res.data.target_label);
					if (res.data.mode) lastMode = String(res.data.mode);
					if (res.data.assistant_cpt_type) {
						activeAssistantCptType = String(res.data.assistant_cpt_type);
						try { $cptType.val(activeAssistantCptType); } catch (e) {}
					}
					if (res.data.assistant_batch_type) {
						activeAssistantBatchType = String(res.data.assistant_batch_type);
						try { $batchType.val(activeAssistantBatchType); } catch (e) {}
					}
					if (res.data.assistant_batch_count) {
						activeAssistantBatchCount = parseInt(String(res.data.assistant_batch_count), 10) || activeAssistantBatchCount;
						try { $batchCount.val(String(activeAssistantBatchCount)); } catch (e) {}
					}
					syncModeUi();
				}
				if (res && res.success && res.data && res.data.mode === "qa" && res.data.answer) {
					renderQaAnswer(res.data.answer, res.data.snapshot || {});
					setStatus("Answer ready. Ask follow-up questions anytime.", false);
					setProposalEnabled(false);
				} else if (res && res.success && res.data && res.data.mode === "edit_existing" && res.data.pb_patch_pending) {
					pbPatchPending = true;
					proposed = null;
					current = null;
					var prevPb = String(res.data.pb_patch_preview || "").trim();
					$diff.html("<div class=\"lf-ai-float__row\"><div class=\"lf-ai-float__field\">Page Builder (multi-section)</div><div><pre style=\"white-space:pre-wrap;margin:0;font-size:12px;max-height:14em;overflow:auto;\">" + escapeHtml(prevPb || "Updates ready for multiple sections. Apply to save to the Page Builder.") + "</pre></div></div>").prop("hidden", false);
					setStatus("Multi-section Page Builder updates ready. Review the preview, then apply to save.", false);
					setProposalEnabled(true);
				} else if (res && res.success && res.data && res.data.mode === "edit_existing" && res.data.proposed) {
					pbPatchPending = false;
					proposed = res.data.proposed;
					lastProposalHomepageSectionId = (res.data.homepage_section_row_id && String(res.data.homepage_section_row_id).trim()) ? String(res.data.homepage_section_row_id).trim() : "";
					current = res.data.current || {};
					labels = res.data.labels || labels;
					renderDiff();
					setStatus("Suggestions ready. Review and apply.", false);
					setProposalEnabled(true);
				} else if (res && res.success && res.data && res.data.creation_payload) {
					creationPayload = res.data.creation_payload;
					if (res.data.creation_queue) {
						renderCreationQueue(res.data.creation_queue || []);
					} else {
						renderCreationPreview(res.data.creation_preview || {});
					}
					setStatus("Draft plan ready. Review and apply.", false);
					setProposalEnabled(true);
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "No suggestions returned.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Request failed.";
				setStatus(msg, true);
			});
		});

		$btnApply.on("click", function(){
			if (inlineQuickEdit && inlineQuickEdit.selector) {
				setStatus("Applying direct text replacement...", false);
				$.post(lfAiFloating.ajax_url, {
					action: "lf_ai_inline_save",
					nonce: lfAiFloating.nonce,
					context_type: activeContextType,
					context_id: activeContextId,
					selector: inlineQuickEdit.selector,
					value: inlineQuickEdit.value
				}).done(function(res){
					if (res && res.success) {
						try {
							var node = document.querySelector(inlineQuickEdit.selector);
							if (node) node.textContent = String(inlineQuickEdit.value || "");
						} catch (e) {}
						setStatus((res.data && res.data.message) ? res.data.message : "Change applied.", false);
						inlineQuickEdit = null;
						setProposalEnabled(false);
					} else {
						setStatus((res && res.data && res.data.message) ? res.data.message : "Apply failed.", true);
					}
				}).fail(function(xhr){
					var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Apply failed.";
					setStatus(msg, true);
				});
				return;
			}
			if (lastMode === "edit_existing" && pbPatchPending) {
				setStatus(lfAiFloating.i18n && lfAiFloating.i18n.statusApplying ? lfAiFloating.i18n.statusApplying : "Applying...", false);
				$.post(lfAiFloating.ajax_url, {
					action: "lf_ai_apply",
					nonce: lfAiFloating.nonce,
					context_type: activeContextType,
					context_id: activeContextId,
					prompt_snippet: promptSnippet,
					proposed: JSON.stringify({}),
					creation_payload: JSON.stringify({}),
					assistant_mode: lastMode,
					assistant_cpt_type: activeAssistantCptType,
					assistant_batch_type: activeAssistantBatchType,
					selected_section_id: lastProposalHomepageSectionId,
					apply_pb_patch: "1"
				}).done(function(res){
					if (res && res.success && res.data && res.data.reload) {
						window.location.reload();
					} else {
						setStatus((res && res.data && res.data.message) ? res.data.message : "Apply failed.", true);
					}
				}).fail(function(xhr){
					var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Apply failed.";
					setStatus(msg, true);
				});
				return;
			}
			if (lastMode === "edit_existing" && !proposed) {
				setStatus("No suggestions to apply.", true);
				return;
			}
			if (lastMode !== "edit_existing" && !creationPayload) {
				setStatus("No draft plan to apply.", true);
				return;
			}
			setStatus(lfAiFloating.i18n && lfAiFloating.i18n.statusApplying ? lfAiFloating.i18n.statusApplying : "Applying...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_apply",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				prompt_snippet: promptSnippet,
				proposed: JSON.stringify(proposed || {}),
				creation_payload: JSON.stringify(creationPayload || {}),
				assistant_mode: lastMode,
				assistant_cpt_type: activeAssistantCptType,
				assistant_batch_type: activeAssistantBatchType,
				selected_section_id: lastProposalHomepageSectionId
			}).done(function(res){
				if (res && res.success && res.data && res.data.reload) {
					window.location.reload();
				} else if (res && res.success && res.data && res.data.created) {
					var links = "";
					if (res.data.edit_link) {
						links += "<a href=\"" + escapeHtml(res.data.edit_link) + "\" target=\"_blank\" rel=\"noopener\">Open Draft</a>";
					}
					if (res.data.view_link) {
						links += (links ? " | " : "") + "<a href=\"" + escapeHtml(res.data.view_link) + "\" target=\"_blank\" rel=\"noopener\">View</a>";
					}
					$diff.html("<div class=\"lf-ai-float__row\"><div class=\"lf-ai-float__field\">Draft Created</div><div>" + links + "</div></div>").prop("hidden", false);
					setStatus((res.data.message || "Draft created successfully."), false);
					setProposalEnabled(false);
				} else if (res && res.success && res.data && res.data.created_batch) {
					var items = Array.isArray(res.data.created_items) ? res.data.created_items : [];
					var html = "<div class=\"lf-ai-float__row\"><div class=\"lf-ai-float__field\">Batch Drafts Created (" + items.length + ")</div></div>";
					items.forEach(function(item, idx){
						var links = "";
						if (item.edit_link) {
							links += "<a href=\"" + escapeHtml(item.edit_link) + "\" target=\"_blank\" rel=\"noopener\">Open Draft</a>";
						}
						if (item.view_link) {
							links += (links ? " | " : "") + "<a href=\"" + escapeHtml(item.view_link) + "\" target=\"_blank\" rel=\"noopener\">View</a>";
						}
						html += "<div class=\"lf-ai-float__row\"><div class=\"lf-ai-float__field\">#" + (idx + 1) + " " + escapeHtml(item.title || "") + "</div><div>" + links + "</div></div>";
					});
					$diff.html(html).prop("hidden", false);
					setStatus((res.data.message || "Batch drafts created."), false);
					setProposalEnabled(false);
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "Apply failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Apply failed.";
				setStatus(msg, true);
			});
		});

		$btnReject.on("click", function(){
			inlineQuickEdit = null;
			proposed = null;
			pbPatchPending = false;
			lastProposalHomepageSectionId = "";
			current = null;
			creationPayload = null;
			$diff.prop("hidden", true).empty();
			setProposalEnabled(false);
			setStatus("Suggestions rejected.", false);
		});

		$btnRevert.on("click", function(){
			runRollback();
		});
		$btnUndo.on("click", function(){
			runRollback();
		});
		$btnRedo.on("click", function(){
			runRedo();
		});
		$confirmNo.on("click", function(){
			pendingConfirmAction = null;
			$confirmText.text(defaultConfirmText);
			$confirmYes.text(defaultConfirmYesText);
			setConfirmOpen(false);
		});
		$confirmYes.on("click", function(){
			var action = pendingConfirmAction;
			pendingConfirmAction = null;
			$confirmText.text(defaultConfirmText);
			$confirmYes.text(defaultConfirmYesText);
			setConfirmOpen(false);
			if (typeof action === "function") {
				action();
				return;
			}
			runRollback();
		});
		$confirm.on("click", function(e){
			if (e.target === this) {
				setConfirmOpen(false);
			}
		});
		$(document).on("keydown", function(e){
			var target = e.target;
			var targetTag = target && target.tagName ? String(target.tagName).toLowerCase() : "";
			var isTypingTarget = !!(target && (target.isContentEditable || targetTag === "input" || targetTag === "textarea" || targetTag === "select"));
			var key = String(e.key || "");
			var keyLower = key.toLowerCase();
			if (!isTypingTarget && (e.metaKey || e.ctrlKey) && keyLower === "z" && !e.shiftKey) {
				e.preventDefault();
				runRollback();
				return;
			}
			if (!isTypingTarget && ((e.metaKey || e.ctrlKey) && e.shiftKey && keyLower === "z")) {
				e.preventDefault();
				runRedo();
				return;
			}
			if (!isTypingTarget && e.ctrlKey && keyLower === "y") {
				e.preventDefault();
				runRedo();
				return;
			}
			if (!isTypingTarget && (e.metaKey || e.ctrlKey) && keyLower === "s") {
				e.preventDefault();
				if (inlineActiveEl) {
					saveInlineEdit();
				} else {
					setStatus("No active inline edit to save.", false);
				}
				return;
			}
			if ((e.metaKey || e.ctrlKey) && (keyLower === "k" || ((e.shiftKey && keyLower === "p")))) {
				e.preventDefault();
				ensureCommandPalette();
				toggleCommandPalette(commandPaletteEl.hidden);
				return;
			}
			if (!isTypingTarget && ((e.shiftKey && key === "?") || key === "F1")) {
				e.preventDefault();
				showShortcutHelp();
				return;
			}
			if (!isTypingTarget && key === "/") {
				e.preventDefault();
				ensureCommandPalette();
				toggleCommandPalette(true);
				return;
			}
			if (inlineActiveEl) {
				if (e.key === "Escape" || e.keyCode === 27) {
					if ($linkRoot.length && !$linkRoot.find("[data-lf-ai-inline-link-panel]").prop("hidden")) {
						e.preventDefault();
						lfHideInlineLinkPanel();
						return;
					}
					e.preventDefault();
					cancelInlineEdit(true);
					return;
				}
				if ((e.ctrlKey || e.metaKey) && (e.key === "Enter" || e.keyCode === 13)) {
					e.preventDefault();
					saveInlineEdit();
					return;
				}
				if ((e.ctrlKey || e.metaKey) && keyLower === "l") {
					e.preventDefault();
					lfOpenInternalLinkPanel();
					return;
				}
			}
			if (!selectedSectionWrap && !isTypingTarget) {
				var first = collectSectionWrappers()[0] || null;
				if (first) {
					setSelectedSection(first);
				}
			}
			if (!isTypingTarget && selectedSectionWrap && $confirm.prop("hidden") && (!commandPaletteEl || commandPaletteEl.hidden)) {
				if ((e.altKey || e.metaKey || e.shiftKey) && key === "ArrowUp") {
					e.preventDefault();
					moveSelectedSection(-1);
					return;
				}
				if ((e.altKey || e.metaKey || e.shiftKey) && key === "ArrowDown") {
					e.preventDefault();
					moveSelectedSection(1);
					return;
				}
				if (keyLower === "k" && !e.metaKey && !e.ctrlKey) {
					e.preventDefault();
					moveSelectedSection(-1);
					return;
				}
				if (keyLower === "j" && !e.metaKey && !e.ctrlKey) {
					e.preventDefault();
					moveSelectedSection(1);
					return;
				}
				if (keyLower === "d" && !e.metaKey && !e.ctrlKey) {
					e.preventDefault();
					selectedSectionDuplicate();
					return;
				}
				if (keyLower === "h" && !e.metaKey && !e.ctrlKey) {
					e.preventDefault();
					selectedSectionToggleVisibility();
					return;
				}
				if (e.key === "Backspace" || e.key === "Delete") {
					e.preventDefault();
					selectedSectionDelete();
					return;
				}
			}
			if ((e.key === "Escape" || e.keyCode === 27) && !$confirm.prop("hidden")) {
				pendingConfirmAction = null;
				$confirmText.text(defaultConfirmText);
				$confirmYes.text(defaultConfirmYesText);
				setConfirmOpen(false);
			}
			if ((e.key === "Escape" || e.keyCode === 27) && commandPaletteEl && !commandPaletteEl.hidden) {
				toggleCommandPalette(false);
			}
		});

		try {
			var initial = window.localStorage.getItem(stateKey);
			if (initial === "open") setAiOpen(true);
		} catch (e) {}
		try {
			var seoInitial = window.localStorage.getItem(seoStateKey);
			if (seoInitial === "open") {
				setSeoOpen(true);
				setAiOpen(false);
			}
		} catch (e) {}
		try {
			railCollapsed = window.localStorage.getItem(railStateKey) === "collapsed";
		} catch (e) {}
		try {
			editingEnabled = window.localStorage.getItem(editorModeKey) !== "off";
		} catch (e) {}
		setEditorToggleUi();
		updateLauncherOffsets();
		try { window.setTimeout(updateLauncherOffsets, 180); } catch (e) {}
		$prompt.attr("placeholder", (lfAiFloating.i18n && lfAiFloating.i18n.placeholder) ? lfAiFloating.i18n.placeholder : "Ask for specific edits...");
		setStatus((lfAiFloating.i18n && lfAiFloating.i18n.statusReady) ? lfAiFloating.i18n.statusReady : "Ready.", false);
		setConfirmOpen(false);
		try { $mode.val("auto"); } catch (e) {}
		syncModeUi();
		if ($onboardingText.length && lfAiFloating.i18n && lfAiFloating.i18n.onboardingTip) {
			$onboardingText.text(String(lfAiFloating.i18n.onboardingTip));
		}
		try {
			var onboardKey = "lfAiOnboardingDismissed";
			if ($onboarding.length && editingEnabled && !window.localStorage.getItem(onboardKey)) {
				$onboarding.prop("hidden", false);
			}
		} catch (e0) {}
		$root.on("click", "[data-lf-ai-onboarding-dismiss]", function(){
			$onboarding.prop("hidden", true);
			try { window.localStorage.setItem("lfAiOnboardingDismissed", "1"); } catch (e1) {}
		});
		if (editingEnabled) {
			try { document.documentElement.classList.add("lf-ai-editor-on"); } catch (eCls3) {}
			buildEditorUi();
			setStatus(editorSurfaceStatusMessage(), false);
		} else {
			clearEditorUi();
			setStatus("Live mode enabled. Toggle ✎ (editor) in the AI Assistant header to show section controls and inline editing.", false);
		}
		renderSeoSnapshot();
	})(jQuery);';
}

function lf_ai_assistant_widget_fallback_js(): string {
	return '(function(){
		"use strict";
		var roots = document.querySelectorAll("[data-lf-ai-float]");
		if (!roots || !roots.length) return;
		roots.forEach(function(root){
			// If the primary jQuery controller initialized, skip fallback bindings
			// to avoid double-toggle behavior (open then immediate close).
			if (root.getAttribute("data-lf-ai-js-init") === "1") {
				return;
			}
			var panel = root.querySelector("#lf-ai-float-panel");
			var toggle = root.querySelector("[data-lf-ai-toggle]");
			var closeButtons = root.querySelectorAll("[data-lf-ai-close],[data-lf-ai-minimize]");
			if (!panel || !toggle) return;
			var key = "lfAiFloatState";
			function setOpen(open){
				panel.hidden = !open;
				toggle.setAttribute("aria-expanded", open ? "true" : "false");
				try { window.localStorage.setItem(key, open ? "open" : "closed"); } catch (e) {}
			}
			toggle.addEventListener("click", function(){
				setOpen(panel.hidden);
			});
			closeButtons.forEach(function(btn){
				btn.addEventListener("click", function(){
					setOpen(false);
				});
			});
			document.addEventListener("keydown", function(e){
				if ((e.key === "Escape" || e.keyCode === 27) && !panel.hidden) {
					setOpen(false);
				}
			});
			try {
				var current = window.localStorage.getItem(key);
				if (current === "open") setOpen(true);
			} catch (e) {}
		});
	})();';
}

<?php
/**
 * LeadsForward AI Assistant page (bounded to copy + CTA).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_menu', 'lf_ai_assistant_register_menu', 12);
add_action('admin_enqueue_scripts', 'lf_ai_assistant_assets');

function lf_ai_assistant_register_menu(): void {
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	add_submenu_page(
		'lf-ops',
		__('AI Assistant', 'leadsforward-core'),
		__('AI Assistant', 'leadsforward-core'),
		'edit_theme_options',
		'lf-ai-assistant',
		'lf_ai_assistant_render_page'
	);
}

function lf_ai_assistant_assets(string $hook): void {
	if ($hook !== 'leadsforward_page_lf-ai-assistant') {
		return;
	}
	$editable = function_exists('lf_get_ai_editable_fields') ? lf_get_ai_editable_fields('homepage') : [];
	if (empty($editable)) {
		return;
	}
	wp_enqueue_script(
		'lf-ai-editing',
		LF_THEME_URI . '/inc/ai-editing/admin-ui.js',
		['jquery'],
		LF_THEME_VERSION,
		true
	);
	wp_localize_script('lf-ai-editing', 'lfAiEditing', [
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce'    => wp_create_nonce('lf_ai_editing'),
		'labels'   => $editable,
	]);
	wp_register_style('lf-ai-editing', false, [], LF_THEME_VERSION);
	wp_enqueue_style('lf-ai-editing');
	wp_add_inline_style('lf-ai-editing', '
		.lf-ai-diff table { table-layout: fixed; }
		.lf-ai-diff pre { white-space: pre-wrap; word-wrap: break-word; margin: 0; font-size: 12px; max-height: 140px; overflow: auto; }
		.lf-ai-status.error { color: #b32d2e; }
		.lf-ai-presets { display: flex; flex-wrap: wrap; gap: 0.5rem; margin: 0.5rem 0 1rem; }
		.lf-ai-presets .button { font-size: 12px; }
	');
}

function lf_ai_assistant_render_page(): void {
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	$saved = isset($_GET['saved']) && $_GET['saved'] === '1';
	$has_key = get_option('lf_openai_api_key', '') !== '';
	$log = function_exists('lf_ai_get_log') ? lf_ai_get_log() : [];
	$log = is_array($log) ? array_slice($log, 0, 5) : [];
	?>
	<div class="wrap">
		<h1><?php esc_html_e('AI Assistant', 'leadsforward-core'); ?></h1>
		<p class="description"><?php esc_html_e('Use AI to refine copy and CTA messaging. This assistant only edits text fields and requires confirmation before applying changes.', 'leadsforward-core'); ?></p>
		<?php if ($saved) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('AI settings saved.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<p class="description"><?php echo $has_key ? esc_html__('OpenAI key is set in LeadsForward → Setup.', 'leadsforward-core') : esc_html__('Add your OpenAI key in LeadsForward → Setup to enable AI suggestions.', 'leadsforward-core'); ?></p>
		<div class="lf-ai-editing" data-context-type="homepage" data-context-id="homepage">
			<p class="lf-ai-description"><?php esc_html_e('Describe the change you want. Allowed fields: hero headline/subheadline, hero CTA override, homepage CTA labels.', 'leadsforward-core'); ?></p>
			<div class="lf-ai-presets">
				<button type="button" class="button" data-prompt="Tighten the hero headline and subheadline for stronger local service credibility."><?php esc_html_e('Refine hero copy', 'leadsforward-core'); ?></button>
				<button type="button" class="button" data-prompt="Make the primary CTA more urgent and conversion focused; keep it short."><?php esc_html_e('Improve CTA', 'leadsforward-core'); ?></button>
				<button type="button" class="button" data-prompt="Rewrite hero copy to emphasize speed, trust, and clarity of pricing."><?php esc_html_e('Emphasize trust + speed', 'leadsforward-core'); ?></button>
			</div>
			<label for="lf-ai-prompt" class="screen-reader-text"><?php esc_html_e('Edit prompt', 'leadsforward-core'); ?></label>
			<textarea id="lf-ai-prompt" class="widefat" rows="3" placeholder="<?php esc_attr_e('e.g. Make the hero more premium and conversion-focused', 'leadsforward-core'); ?>"></textarea>
			<p>
				<button type="button" class="button button-primary" id="lf-ai-submit"><?php esc_html_e('Generate suggestions', 'leadsforward-core'); ?></button>
			</p>
			<div id="lf-ai-status" class="lf-ai-status" aria-live="polite"></div>
			<div id="lf-ai-diff" class="lf-ai-diff" style="display:none;">
				<h3><?php esc_html_e('Review suggestions', 'leadsforward-core'); ?></h3>
				<table class="widefat striped" id="lf-ai-diff-table"></table>
				<p>
					<button type="button" class="button button-primary" id="lf-ai-apply"><?php esc_html_e('Apply', 'leadsforward-core'); ?></button>
					<button type="button" class="button" id="lf-ai-reject"><?php esc_html_e('Reject', 'leadsforward-core'); ?></button>
				</p>
			</div>
			<?php if (!empty($log)) : ?>
				<div class="lf-ai-log" style="margin-top:1em;">
					<h3><?php esc_html_e('Recent AI edits', 'leadsforward-core'); ?></h3>
					<ul class="lf-ai-log-list">
						<?php foreach ($log as $entry) {
							$id = $entry['id'] ?? '';
							$rolled = !empty($entry['rolled_back']);
							$time = isset($entry['time']) ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $entry['time']) : '';
							?>
							<li>
								<?php echo esc_html($time); ?>
								<?php if (!$rolled && $id) { ?>
									<button type="button" class="button button-small lf-ai-rollback" data-id="<?php echo esc_attr($id); ?>"><?php esc_html_e('Rollback', 'leadsforward-core'); ?></button>
								<?php } elseif ($rolled) { ?>
									<span class="lf-ai-rolled"><?php esc_html_e('Rolled back', 'leadsforward-core'); ?></span>
								<?php } ?>
							</li>
						<?php } ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<script>
		document.addEventListener('click', function (e) {
			if (e.target && e.target.matches('.lf-ai-presets .button')) {
				var prompt = e.target.getAttribute('data-prompt') || '';
				var textarea = document.getElementById('lf-ai-prompt');
				if (textarea) {
					textarea.value = prompt;
					textarea.focus();
				}
			}
		});
	</script>
	<?php
}

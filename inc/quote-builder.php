<?php
/**
 * Quote Builder: full-screen multi-step modal with safe admin config.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_QUOTE_BUILDER_OPTION = 'lf_quote_builder_config';
const LF_QUOTE_BUILDER_MANUAL_OPTION = 'lf_quote_builder_manual_override';
const LF_QUOTE_BUILDER_SUBMISSIONS = 'lf_quote_builder_submissions';

add_action('admin_init', 'lf_quote_builder_handle_save');
add_action('wp_enqueue_scripts', 'lf_quote_builder_enqueue_assets');
add_action('wp_footer', 'lf_quote_builder_render_modal', 20);
add_action('wp_ajax_lf_quote_builder_submit', 'lf_quote_builder_handle_submit');
add_action('wp_ajax_nopriv_lf_quote_builder_submit', 'lf_quote_builder_handle_submit');

function lf_quote_builder_default_config(?string $niche_slug = null): array {
	$config = [
		'version' => 1,
		'steps' => [
			[
				'id'      => 'service_type',
				'title'   => __('What can we help you with?', 'leadsforward-core'),
				'helper'  => __('Choose the service that best matches your need.', 'leadsforward-core'),
				'enabled' => true,
				'type'    => 'standard',
				'fields'  => [
					[
						'key'      => 'service_type',
						'label'    => __('Service type', 'leadsforward-core'),
						'type'     => 'choice',
						'required' => true,
						'options'  => [
							__('General Service', 'leadsforward-core'),
							__('Repair', 'leadsforward-core'),
							__('Installation', 'leadsforward-core'),
							__('Maintenance', 'leadsforward-core'),
						],
						'default' => '',
					],
				],
			],
			[
				'id'      => 'project_details',
				'title'   => __('Tell us about your project', 'leadsforward-core'),
				'helper'  => __('A little detail helps us send the right expert.', 'leadsforward-core'),
				'enabled' => true,
				'type'    => 'standard',
				'fields'  => [
					[
						'key'         => 'project_details',
						'label'       => __('Project details (optional)', 'leadsforward-core'),
						'type'        => 'textarea',
						'required'    => false,
						'placeholder' => __('Briefly describe what you need help with…', 'leadsforward-core'),
						'default'     => '',
					],
					[
						'key'      => 'project_timeline',
						'label'    => __('When do you need help?', 'leadsforward-core'),
						'type'     => 'choice',
						'required' => false,
						'options'  => [
							__('As soon as possible', 'leadsforward-core'),
							__('This week', 'leadsforward-core'),
							__('Next 2-4 weeks', 'leadsforward-core'),
							__('Just researching', 'leadsforward-core'),
						],
						'default' => '',
					],
				],
			],
			[
				'id'      => 'location',
				'title'   => __('Where should we send help?', 'leadsforward-core'),
				'helper'  => __('We only use this to plan your estimate and arrival.', 'leadsforward-core'),
				'enabled' => true,
				'type'    => 'standard',
				'fields'  => [
					[
						'key'         => 'address_street',
						'label'       => __('Street address', 'leadsforward-core'),
						'type'        => 'text',
						'required'    => true,
						'placeholder' => __('123 Main Street', 'leadsforward-core'),
						'default'     => '',
					],
					[
						'key'         => 'address_city',
						'label'       => __('City', 'leadsforward-core'),
						'type'        => 'text',
						'required'    => true,
						'placeholder' => __('City', 'leadsforward-core'),
						'default'     => '',
					],
					[
						'key'         => 'address_zip',
						'label'       => __('ZIP code', 'leadsforward-core'),
						'type'        => 'text',
						'required'    => true,
						'placeholder' => __('ZIP', 'leadsforward-core'),
						'default'     => '',
					],
				],
			],
			[
				'id'      => 'contact',
				'title'   => __('How can we reach you?', 'leadsforward-core'),
				'helper'  => __('We respond quickly and never share your information.', 'leadsforward-core'),
				'enabled' => true,
				'type'    => 'standard',
				'fields'  => [
					[
						'key'         => 'full_name',
						'label'       => __('Full name', 'leadsforward-core'),
						'type'        => 'text',
						'required'    => true,
						'placeholder' => __('Your name', 'leadsforward-core'),
						'default'     => '',
					],
					[
						'key'         => 'phone',
						'label'       => __('Phone', 'leadsforward-core'),
						'type'        => 'tel',
						'required'    => true,
						'placeholder' => __('(555) 123-4567', 'leadsforward-core'),
						'default'     => '',
					],
					[
						'key'         => 'email',
						'label'       => __('Email', 'leadsforward-core'),
						'type'        => 'email',
						'required'    => true,
						'placeholder' => __('you@email.com', 'leadsforward-core'),
						'default'     => '',
					],
				],
			],
			[
				'id'      => 'schedule',
				'title'   => __('Scheduling preference', 'leadsforward-core'),
				'helper'  => __('Tell us how and when you prefer to connect.', 'leadsforward-core'),
				'enabled' => true,
				'type'    => 'standard',
				'fields'  => [
					[
						'key'      => 'contact_method',
						'label'    => __('Preferred contact method', 'leadsforward-core'),
						'type'     => 'choice',
						'required' => true,
						'options'  => [
							__('Call me', 'leadsforward-core'),
							__('Text me', 'leadsforward-core'),
							__('Email me', 'leadsforward-core'),
						],
						'default' => '',
					],
					[
						'key'         => 'preferred_time',
						'label'       => __('Preferred time (optional)', 'leadsforward-core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => __('e.g. Weekdays after 4pm', 'leadsforward-core'),
						'default'     => '',
					],
				],
			],
			[
				'id'      => 'confirmation',
				'title'   => __('Request received', 'leadsforward-core'),
				'helper'  => __('Thanks for the details. We’ll follow up shortly.', 'leadsforward-core'),
				'enabled' => true,
				'type'    => 'confirmation',
				'confirmation_title' => __('Thanks! Your request is on the way.', 'leadsforward-core'),
				'confirmation_body'  => __('A local specialist will review your details and contact you shortly to confirm next steps.', 'leadsforward-core'),
				'fields'  => [],
			],
		],
	];
	return apply_filters('lf_quote_builder_default_config', $config, $niche_slug);
}

function lf_quote_builder_get_config(): array {
	$stored = get_option(LF_QUOTE_BUILDER_OPTION, null);
	$manual = (bool) get_option(LF_QUOTE_BUILDER_MANUAL_OPTION, false);
	$default = lf_quote_builder_default_config(get_option('lf_homepage_niche_slug', ''));
	if (is_array($stored) && !empty($stored)) {
		return lf_quote_builder_merge_config($stored, $default);
	}
	if (!$manual) {
		update_option(LF_QUOTE_BUILDER_OPTION, $default, true);
	}
	return $default;
}

function lf_quote_builder_merge_config(array $stored, array $default): array {
	$out = $default;
	$out['version'] = $default['version'] ?? 1;
	$stored_steps = $stored['steps'] ?? [];
	if (!is_array($stored_steps)) {
		return $out;
	}
	foreach ($out['steps'] as $index => $step) {
		$match = null;
		foreach ($stored_steps as $candidate) {
			if (!empty($candidate['id']) && $candidate['id'] === $step['id']) {
				$match = $candidate;
				break;
			}
		}
		if (is_array($match)) {
			$out['steps'][$index] = array_merge($step, $match);
		}
	}
	return $out;
}

function lf_quote_builder_apply_niche_config(string $niche_slug): void {
	$config = lf_quote_builder_default_config($niche_slug);
	update_option(LF_QUOTE_BUILDER_OPTION, $config, true);
	update_option(LF_QUOTE_BUILDER_MANUAL_OPTION, false, true);
}

function lf_quote_builder_handle_save(): void {
	if (!isset($_POST['lf_quote_builder_nonce'])) {
		return;
	}
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	if (!wp_verify_nonce($_POST['lf_quote_builder_nonce'], 'lf_quote_builder_save')) {
		return;
	}
	$defaults = lf_quote_builder_default_config(get_option('lf_homepage_niche_slug', ''));
	$input = $_POST['lf_qb_steps'] ?? [];
	$config = lf_quote_builder_sanitize_config($input, $defaults);
	update_option(LF_QUOTE_BUILDER_OPTION, $config, true);
	update_option(LF_QUOTE_BUILDER_MANUAL_OPTION, true, true);
	wp_safe_redirect(admin_url('admin.php?page=lf-quote-builder&saved=1'));
	exit;
}

function lf_quote_builder_sanitize_config($input, array $defaults): array {
	$out = $defaults;
	$raw_steps = is_array($input) ? $input : [];
	foreach ($out['steps'] as $index => $step) {
		$step_id = $step['id'];
		$raw = $raw_steps[$step_id] ?? [];
		$out['steps'][$index]['enabled'] = !empty($raw['enabled']);
		$title = isset($raw['title']) ? sanitize_text_field(wp_unslash($raw['title'])) : $step['title'];
		$helper = isset($raw['helper']) ? sanitize_textarea_field(wp_unslash($raw['helper'])) : $step['helper'];
		$out['steps'][$index]['title'] = $title;
		$out['steps'][$index]['helper'] = $helper;
		if (($step['type'] ?? '') === 'confirmation') {
			$confirm_title = isset($raw['confirmation_title']) ? sanitize_text_field(wp_unslash($raw['confirmation_title'])) : ($step['confirmation_title'] ?? '');
			$confirm_body = isset($raw['confirmation_body']) ? sanitize_textarea_field(wp_unslash($raw['confirmation_body'])) : ($step['confirmation_body'] ?? '');
			$out['steps'][$index]['confirmation_title'] = $confirm_title;
			$out['steps'][$index]['confirmation_body'] = $confirm_body;
			continue;
		}
		$fields = $step['fields'] ?? [];
		foreach ($fields as $field_index => $field) {
			$key = $field['key'];
			$raw_field = $raw['fields'][$key] ?? [];
			$out_field = $field;
			$out_field['label'] = isset($raw_field['label']) ? sanitize_text_field(wp_unslash($raw_field['label'])) : $field['label'];
			$out_field['required'] = !empty($raw_field['required']);
			$out_field['default'] = isset($raw_field['default']) ? sanitize_text_field(wp_unslash($raw_field['default'])) : ($field['default'] ?? '');
			$out_field['placeholder'] = isset($raw_field['placeholder']) ? sanitize_text_field(wp_unslash($raw_field['placeholder'])) : ($field['placeholder'] ?? '');
			if (($field['type'] ?? '') === 'choice') {
				$options_raw = isset($raw_field['options']) ? wp_unslash($raw_field['options']) : '';
				$options = array_filter(array_map('sanitize_text_field', array_map('trim', explode("\n", (string) $options_raw))));
				$out_field['options'] = !empty($options) ? array_values($options) : ($field['options'] ?? []);
			}
			$out['steps'][$index]['fields'][$field_index] = $out_field;
		}
	}
	return $out;
}

function lf_quote_builder_enqueue_assets(): void {
	if (is_admin()) {
		return;
	}
	$handle = 'lf-quote-builder';
	$src = LF_THEME_URI . '/assets/js/quote-builder.js';
	wp_enqueue_script($handle, $src, [], LF_THEME_VERSION, true);
	wp_localize_script($handle, 'lfQuoteBuilder', [
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce'    => wp_create_nonce('lf_quote_builder'),
	]);
}

function lf_quote_builder_render_modal(): void {
	if (is_admin()) {
		return;
	}
	$config = lf_quote_builder_get_config();
	$steps = array_values(array_filter($config['steps'] ?? [], function ($step) {
		return !empty($step['enabled']);
	}));
	if (empty($steps)) {
		return;
	}
	$total = count($steps);
	$modal_id = 'lf-quote-builder';
	$first_title_id = 'lf-quote-title-0';
	?>
	<div class="lf-quote-modal" id="<?php echo esc_attr($modal_id); ?>" aria-hidden="true">
		<div class="lf-quote-modal__overlay" data-lf-quote-close></div>
		<div class="lf-quote-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($first_title_id); ?>" tabindex="-1">
			<button type="button" class="lf-quote-modal__close" data-lf-quote-close aria-label="<?php esc_attr_e('Close', 'leadsforward-core'); ?>">×</button>
			<div class="lf-quote-modal__progress">
				<span class="lf-quote-modal__step" id="lf-quote-step-label"><?php echo esc_html(sprintf(__('Step %d of %d', 'leadsforward-core'), 1, $total)); ?></span>
				<div class="lf-quote-modal__bar"><span class="lf-quote-modal__bar-fill" style="width:<?php echo esc_attr((string) (100 / max(1, $total))); ?>%"></span></div>
			</div>
			<form class="lf-quote-form" autocomplete="on">
				<?php foreach ($steps as $index => $step) :
					$step_id = $step['id'] ?? 'step-' . $index;
					$step_type = $step['type'] ?? 'standard';
					$fields = $step['fields'] ?? [];
					$is_confirm = $step_type === 'confirmation';
					?>
					<section class="lf-quote-step<?php echo $index === 0 ? ' is-active' : ''; ?>" data-step-index="<?php echo esc_attr((string) $index); ?>" data-step-id="<?php echo esc_attr($step_id); ?>" data-step-type="<?php echo esc_attr($step_type); ?>">
						<h2 class="lf-quote-step__title" id="<?php echo esc_attr('lf-quote-title-' . $index); ?>"><?php echo esc_html($step['title'] ?? ''); ?></h2>
						<?php if (!empty($step['helper'])) : ?>
							<p class="lf-quote-step__helper"><?php echo esc_html($step['helper']); ?></p>
						<?php endif; ?>
						<?php if ($is_confirm) : ?>
							<div class="lf-quote-step__confirmation">
								<p class="lf-quote-step__confirm-title"><?php echo esc_html($step['confirmation_title'] ?? ''); ?></p>
								<p class="lf-quote-step__confirm-body"><?php echo esc_html($step['confirmation_body'] ?? ''); ?></p>
							</div>
						<?php else : ?>
							<div class="lf-quote-fields">
								<?php foreach ($fields as $field) :
									$key = $field['key'];
									$type = $field['type'];
									$label = $field['label'] ?? '';
									$required = !empty($field['required']);
									$default = $field['default'] ?? '';
									$placeholder = $field['placeholder'] ?? '';
									$name = 'lf_quote[' . $key . ']';
									?>
									<div class="lf-quote-field lf-quote-field--<?php echo esc_attr($type); ?>">
										<label class="lf-quote-field__label">
											<?php echo esc_html($label); ?>
											<?php if ($required) : ?><span class="lf-quote-field__required">*</span><?php endif; ?>
										</label>
										<?php if ($type === 'choice') : ?>
											<div class="lf-quote-choice">
												<?php foreach (($field['options'] ?? []) as $option_index => $option) :
													$option_value = is_string($option) ? $option : '';
													$input_id = 'lf-quote-' . $key . '-' . $option_index;
													?>
													<label class="lf-quote-choice__card" for="<?php echo esc_attr($input_id); ?>">
														<input type="radio" id="<?php echo esc_attr($input_id); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($option_value); ?>" <?php checked($default, $option_value); ?> <?php echo $required ? 'required' : ''; ?> />
														<span><?php echo esc_html($option_value); ?></span>
													</label>
												<?php endforeach; ?>
											</div>
										<?php elseif ($type === 'textarea') : ?>
											<textarea name="<?php echo esc_attr($name); ?>" rows="3" placeholder="<?php echo esc_attr($placeholder); ?>" <?php echo $required ? 'required' : ''; ?>><?php echo esc_textarea($default); ?></textarea>
										<?php else : ?>
											<input type="<?php echo esc_attr($type); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($default); ?>" placeholder="<?php echo esc_attr($placeholder); ?>" <?php echo $required ? 'required' : ''; ?> />
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</section>
				<?php endforeach; ?>
				<div class="lf-quote-modal__actions">
					<button type="button" class="lf-quote-btn lf-quote-btn--ghost" data-lf-quote-back><?php esc_html_e('Back', 'leadsforward-core'); ?></button>
					<button type="button" class="lf-quote-btn lf-quote-btn--primary" data-lf-quote-next><?php esc_html_e('Continue', 'leadsforward-core'); ?></button>
				</div>
				<div class="lf-quote-modal__status" role="status" aria-live="polite"></div>
			</form>
		</div>
	</div>
	<?php
}

function lf_quote_builder_handle_submit(): void {
	check_ajax_referer('lf_quote_builder', 'nonce');
	$payload = $_POST['lf_quote'] ?? [];
	if (!is_array($payload)) {
		wp_send_json_error(['message' => __('Invalid submission.', 'leadsforward-core')]);
	}
	$config = lf_quote_builder_get_config();
	$allowed = [];
	$required = [];
	foreach ($config['steps'] as $step) {
		if (empty($step['enabled'])) {
			continue;
		}
		foreach ($step['fields'] ?? [] as $field) {
			if (!empty($field['key'])) {
				$allowed[] = $field['key'];
				if (!empty($field['required'])) {
					$required[] = $field['key'];
				}
			}
		}
	}
	$allowed = array_unique($allowed);
	$required = array_unique($required);
	$clean = [];
	foreach ($allowed as $key) {
		if (!isset($payload[$key])) {
			continue;
		}
		$val = $payload[$key];
		if (is_array($val)) {
			$val = wp_json_encode($val);
		}
		$clean[$key] = sanitize_text_field(wp_unslash((string) $val));
	}
	if (empty($clean)) {
		wp_send_json_error(['message' => __('Please complete the required fields.', 'leadsforward-core')]);
	}
	foreach ($required as $key) {
		if (empty($clean[$key])) {
			wp_send_json_error(['message' => __('Please complete the required fields.', 'leadsforward-core')]);
		}
	}
	$log = get_option(LF_QUOTE_BUILDER_SUBMISSIONS, []);
	if (!is_array($log)) {
		$log = [];
	}
	array_unshift($log, [
		'time' => time(),
		'data' => $clean,
		'ip'   => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
	]);
	$log = array_slice($log, 0, 50);
	update_option(LF_QUOTE_BUILDER_SUBMISSIONS, $log, false);
	do_action('lf_quote_builder_submission', $clean);
	wp_send_json_success(['ok' => true]);
}

function lf_quote_builder_render_admin(): void {
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	$config = lf_quote_builder_get_config();
	$steps = $config['steps'] ?? [];
	$saved = isset($_GET['saved']) && $_GET['saved'] === '1';
	echo '<div class="wrap"><h1>' . esc_html__('Quote Builder', 'leadsforward-core') . '</h1>';
	echo '<p class="description">' . esc_html__('Configure the multi-step Quote Builder. This is a structured, safe editor—no HTML, no layout changes.', 'leadsforward-core') . '</p>';
	if ($saved) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Quote Builder settings saved.', 'leadsforward-core') . '</p></div>';
	}
	?>
	<style>
		.lf-qb-step { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem 1.25rem; margin:1rem 0; }
		.lf-qb-step-header { display:flex; align-items:center; gap:0.75rem; }
		.lf-qb-step-header h2 { margin:0; font-size:1.1rem; }
		.lf-qb-toggle { margin-left:auto; font-size:12px; text-decoration:none; padding:0.35rem 0.65rem; border-radius:999px; border:1px solid #e2e8f0; background:#f8fafc; color:#0f172a; }
		.lf-qb-toggle:hover { background:#e2e8f0; }
		.lf-qb-step--collapsed .lf-qb-step-body { display:none; }
		.lf-qb-step--collapsed .lf-qb-toggle { background:#0f172a; color:#fff; border-color:#0f172a; }
		.lf-qb-field { border:1px solid #e2e8f0; border-radius:10px; padding:0.75rem 1rem; margin:0.75rem 0; }
		.lf-qb-field h4 { margin:0 0 0.5rem; }
	</style>
	<form method="post">
		<?php wp_nonce_field('lf_quote_builder_save', 'lf_quote_builder_nonce'); ?>
		<?php foreach ($steps as $index => $step) :
			$step_id = $step['id'];
			$enabled = !empty($step['enabled']);
			?>
			<div class="lf-qb-step" data-step="<?php echo esc_attr($step_id); ?>">
				<div class="lf-qb-step-header">
					<h2><?php echo esc_html(sprintf(__('Step %d', 'leadsforward-core'), $index + 1)); ?> — <?php echo esc_html($step['title'] ?? ''); ?></h2>
					<label><input type="checkbox" name="lf_qb_steps[<?php echo esc_attr($step_id); ?>][enabled]" value="1" <?php checked($enabled); ?> /> <?php esc_html_e('Enabled', 'leadsforward-core'); ?></label>
					<button type="button" class="lf-qb-toggle" data-target="<?php echo esc_attr($step_id); ?>" aria-expanded="true">▾ <?php esc_html_e('Collapse', 'leadsforward-core'); ?></button>
				</div>
				<div class="lf-qb-step-body">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label><?php esc_html_e('Step title', 'leadsforward-core'); ?></label></th>
							<td><input type="text" class="large-text" name="lf_qb_steps[<?php echo esc_attr($step_id); ?>][title]" value="<?php echo esc_attr($step['title'] ?? ''); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label><?php esc_html_e('Helper text', 'leadsforward-core'); ?></label></th>
							<td><textarea class="large-text" rows="2" name="lf_qb_steps[<?php echo esc_attr($step_id); ?>][helper]"><?php echo esc_textarea($step['helper'] ?? ''); ?></textarea></td>
						</tr>
						<?php if (($step['type'] ?? '') === 'confirmation') : ?>
							<tr>
								<th scope="row"><label><?php esc_html_e('Confirmation title', 'leadsforward-core'); ?></label></th>
								<td><input type="text" class="large-text" name="lf_qb_steps[<?php echo esc_attr($step_id); ?>][confirmation_title]" value="<?php echo esc_attr($step['confirmation_title'] ?? ''); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e('Confirmation message', 'leadsforward-core'); ?></label></th>
								<td><textarea class="large-text" rows="2" name="lf_qb_steps[<?php echo esc_attr($step_id); ?>][confirmation_body]"><?php echo esc_textarea($step['confirmation_body'] ?? ''); ?></textarea></td>
							</tr>
						<?php endif; ?>
					</table>
					<?php if (($step['type'] ?? '') !== 'confirmation') : ?>
						<?php foreach (($step['fields'] ?? []) as $field) :
							$key = $field['key'];
							$type = $field['type'];
							?>
							<div class="lf-qb-field">
								<h4><?php echo esc_html($field['label'] ?? $key); ?> <span class="description">(<?php echo esc_html($type); ?>)</span></h4>
								<table class="form-table" role="presentation">
									<tr>
										<th scope="row"><label><?php esc_html_e('Label', 'leadsforward-core'); ?></label></th>
										<td><input type="text" class="regular-text" name="lf_qb_steps[<?php echo esc_attr($step_id); ?>][fields][<?php echo esc_attr($key); ?>][label]" value="<?php echo esc_attr($field['label'] ?? ''); ?>" /></td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e('Required', 'leadsforward-core'); ?></th>
										<td><label><input type="checkbox" name="lf_qb_steps[<?php echo esc_attr($step_id); ?>][fields][<?php echo esc_attr($key); ?>][required]" value="1" <?php checked(!empty($field['required'])); ?> /> <?php esc_html_e('Yes', 'leadsforward-core'); ?></label></td>
									</tr>
									<tr>
										<th scope="row"><label><?php esc_html_e('Default value', 'leadsforward-core'); ?></label></th>
										<td><input type="text" class="regular-text" name="lf_qb_steps[<?php echo esc_attr($step_id); ?>][fields][<?php echo esc_attr($key); ?>][default]" value="<?php echo esc_attr($field['default'] ?? ''); ?>" /></td>
									</tr>
									<?php if ($type !== 'choice') : ?>
										<tr>
											<th scope="row"><label><?php esc_html_e('Placeholder', 'leadsforward-core'); ?></label></th>
											<td><input type="text" class="regular-text" name="lf_qb_steps[<?php echo esc_attr($step_id); ?>][fields][<?php echo esc_attr($key); ?>][placeholder]" value="<?php echo esc_attr($field['placeholder'] ?? ''); ?>" /></td>
										</tr>
									<?php else : ?>
										<tr>
											<th scope="row"><label><?php esc_html_e('Choices (one per line)', 'leadsforward-core'); ?></label></th>
											<td><textarea class="large-text" rows="3" name="lf_qb_steps[<?php echo esc_attr($step_id); ?>][fields][<?php echo esc_attr($key); ?>][options]"><?php echo esc_textarea(implode("\n", $field['options'] ?? [])); ?></textarea></td>
										</tr>
									<?php endif; ?>
								</table>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
		<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e('Save Quote Builder', 'leadsforward-core'); ?></button></p>
	</form>
	<script>
		(function () {
			var storageKey = 'lf_quote_builder_collapsed';
			var collapsed = {};
			try { collapsed = JSON.parse(window.localStorage.getItem(storageKey) || '{}') || {}; } catch (e) { collapsed = {}; }
			function applyCollapse(type) {
				var panel = document.querySelector('.lf-qb-step[data-step="' + type + '"]');
				if (!panel) return;
				var isCollapsed = !!collapsed[type];
				panel.classList.toggle('lf-qb-step--collapsed', isCollapsed);
				var toggle = panel.querySelector('.lf-qb-toggle');
				if (toggle) {
					toggle.setAttribute('aria-expanded', (!isCollapsed).toString());
					toggle.textContent = (isCollapsed ? '▸ ' : '▾ ') + (isCollapsed ? '<?php echo esc_js(__('Expand', 'leadsforward-core')); ?>' : '<?php echo esc_js(__('Collapse', 'leadsforward-core')); ?>');
				}
			}
			document.querySelectorAll('.lf-qb-step').forEach(function (panel) {
				var type = panel.getAttribute('data-step');
				if (type) applyCollapse(type);
			});
			document.addEventListener('click', function (e) {
				if (!e.target || !e.target.classList.contains('lf-qb-toggle')) return;
				var type = e.target.getAttribute('data-target');
				if (!type) return;
				collapsed[type] = !collapsed[type];
				try { window.localStorage.setItem(storageKey, JSON.stringify(collapsed)); } catch (e) {}
				applyCollapse(type);
			});
		})();
	</script>
	<?php
	echo '</div>';
}

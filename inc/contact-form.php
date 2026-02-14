<?php
/**
 * Contact form: lightweight field builder + GHL webhook submit.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_CONTACT_FORM_OPTION = 'lf_contact_form_config';

add_action('admin_init', 'lf_contact_form_handle_save');
add_action('wp_enqueue_scripts', 'lf_contact_form_enqueue_assets');
add_action('wp_ajax_lf_contact_form_submit', 'lf_contact_form_handle_submit');
add_action('wp_ajax_nopriv_lf_contact_form_submit', 'lf_contact_form_handle_submit');

function lf_contact_form_default_config(): array {
	return [
		'webhook_url' => '',
		'fields' => [
			[
				'key' => 'full_name',
				'label' => __('Full name', 'leadsforward-core'),
				'type' => 'text',
				'required' => true,
				'placeholder' => __('Your name', 'leadsforward-core'),
			],
			[
				'key' => 'email',
				'label' => __('Email', 'leadsforward-core'),
				'type' => 'email',
				'required' => true,
				'placeholder' => __('you@email.com', 'leadsforward-core'),
			],
			[
				'key' => 'phone',
				'label' => __('Phone', 'leadsforward-core'),
				'type' => 'tel',
				'required' => false,
				'placeholder' => __('(555) 123-4567', 'leadsforward-core'),
			],
			[
				'key' => 'message',
				'label' => __('How can we help?', 'leadsforward-core'),
				'type' => 'textarea',
				'required' => true,
				'placeholder' => __('Tell us about your project.', 'leadsforward-core'),
			],
		],
	];
}

function lf_contact_form_get_config(): array {
	$stored = get_option(LF_CONTACT_FORM_OPTION, []);
	$default = lf_contact_form_default_config();
	if (!is_array($stored)) {
		return $default;
	}
	$stored['fields'] = isset($stored['fields']) && is_array($stored['fields']) ? $stored['fields'] : [];
	return [
		'webhook_url' => isset($stored['webhook_url']) ? (string) $stored['webhook_url'] : '',
		'fields' => !empty($stored['fields']) ? $stored['fields'] : $default['fields'],
	];
}

function lf_contact_form_fields_to_text(array $fields): string {
	$lines = [];
	foreach ($fields as $field) {
		$label = (string) ($field['label'] ?? '');
		if ($label === '') {
			continue;
		}
		$type = (string) ($field['type'] ?? 'text');
		$required = !empty($field['required']) ? 'required' : 'optional';
		$placeholder = (string) ($field['placeholder'] ?? '');
		$lines[] = trim($label . ' | ' . $type . ' | ' . $required . ' | ' . $placeholder, ' |');
	}
	return implode("\n", $lines);
}

function lf_contact_form_parse_fields(string $raw): array {
	$lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw)));
	$out = [];
	$used_keys = [];
	foreach ($lines as $line) {
		$parts = array_map('trim', explode('|', $line));
		$label = $parts[0] ?? '';
		if ($label === '') {
			continue;
		}
		$type = strtolower($parts[1] ?? 'text');
		if (!in_array($type, ['text', 'email', 'tel', 'textarea'], true)) {
			$type = 'text';
		}
		$required_raw = strtolower($parts[2] ?? 'optional');
		$required = in_array($required_raw, ['required', 'yes', 'true', '1'], true);
		$placeholder = (string) ($parts[3] ?? '');
		$key = sanitize_title($label);
		if ($key === '' || in_array($key, $used_keys, true)) {
			$key = sanitize_title($label . '-' . (count($used_keys) + 1));
		}
		$used_keys[] = $key;
		$out[] = [
			'key' => $key,
			'label' => $label,
			'type' => $type,
			'required' => $required,
			'placeholder' => $placeholder,
		];
	}
	return !empty($out) ? $out : lf_contact_form_default_config()['fields'];
}

function lf_contact_form_handle_save(): void {
	if (!isset($_POST['lf_contact_form_submit'])) {
		return;
	}
	if (!current_user_can('manage_options')) {
		return;
	}
	check_admin_referer('lf_contact_form_save', 'lf_contact_form_nonce');
	$fields_raw = isset($_POST['lf_contact_form_fields']) ? wp_unslash((string) $_POST['lf_contact_form_fields']) : '';
	$webhook = isset($_POST['lf_contact_form_webhook']) ? wp_unslash((string) $_POST['lf_contact_form_webhook']) : '';
	$webhook = trim($webhook);
	$config = [
		'webhook_url' => esc_url_raw($webhook),
		'fields' => lf_contact_form_parse_fields($fields_raw),
	];
	update_option(LF_CONTACT_FORM_OPTION, $config, true);
	wp_safe_redirect(admin_url('admin.php?page=lf-contact-form&saved=1'));
	exit;
}

function lf_contact_form_render_admin(): void {
	if (!current_user_can('manage_options')) {
		return;
	}
	$config = lf_contact_form_get_config();
	$fields_text = lf_contact_form_fields_to_text($config['fields'] ?? []);
	$webhook = (string) ($config['webhook_url'] ?? '');
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Contact Form', 'leadsforward-core'); ?></h1>
		<?php if (isset($_GET['saved'])) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Contact form settings saved.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<form method="post" action="">
			<?php wp_nonce_field('lf_contact_form_save', 'lf_contact_form_nonce'); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lf_contact_form_fields"><?php esc_html_e('Form fields', 'leadsforward-core'); ?></label></th>
					<td>
						<textarea name="lf_contact_form_fields" id="lf_contact_form_fields" class="large-text code" rows="8"><?php echo esc_textarea($fields_text); ?></textarea>
						<p class="description"><?php esc_html_e('One field per line: Label | type | required|optional | placeholder. Types: text, email, tel, textarea.', 'leadsforward-core'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lf_contact_form_webhook"><?php esc_html_e('GHL webhook URL', 'leadsforward-core'); ?></label></th>
					<td>
						<input type="url" class="regular-text" name="lf_contact_form_webhook" id="lf_contact_form_webhook" value="<?php echo esc_attr($webhook); ?>" />
						<p class="description"><?php esc_html_e('Submissions are sent to this webhook.', 'leadsforward-core'); ?></p>
					</td>
				</tr>
			</table>
			<p>
				<button type="submit" class="button button-primary" name="lf_contact_form_submit" value="1"><?php esc_html_e('Save settings', 'leadsforward-core'); ?></button>
			</p>
		</form>
	</div>
	<?php
}

function lf_contact_form_enqueue_assets(): void {
	if (is_admin()) {
		return;
	}
	wp_enqueue_script(
		'lf-contact-form',
		LF_THEME_URI . '/assets/js/contact-form.js',
		[],
		LF_THEME_VERSION,
		true
	);
	wp_localize_script('lf-contact-form', 'lfContactForm', [
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('lf_contact_form_submit'),
	]);
}

function lf_contact_form_render(): void {
	$config = lf_contact_form_get_config();
	$fields = $config['fields'] ?? [];
	if (empty($fields)) {
		$fields = lf_contact_form_default_config()['fields'];
	}
	?>
	<form class="lf-contact-form" data-lf-contact-form>
		<div class="lf-contact-form__fields">
			<?php foreach ($fields as $field) : ?>
				<?php
				$key = sanitize_title((string) ($field['key'] ?? ''));
				if ($key === '') {
					continue;
				}
				$label = (string) ($field['label'] ?? '');
				$type = (string) ($field['type'] ?? 'text');
				$required = !empty($field['required']);
				$placeholder = (string) ($field['placeholder'] ?? '');
				?>
				<label class="lf-contact-form__field">
					<span class="lf-contact-form__label"><?php echo esc_html($label); ?><?php echo $required ? ' *' : ''; ?></span>
					<?php if ($type === 'textarea') : ?>
						<textarea name="<?php echo esc_attr($key); ?>" rows="4" <?php echo $required ? 'required' : ''; ?> placeholder="<?php echo esc_attr($placeholder); ?>"></textarea>
					<?php else : ?>
						<input type="<?php echo esc_attr($type); ?>" name="<?php echo esc_attr($key); ?>" <?php echo $required ? 'required' : ''; ?> placeholder="<?php echo esc_attr($placeholder); ?>" />
					<?php endif; ?>
				</label>
			<?php endforeach; ?>
		</div>
		<div class="lf-contact-form__actions">
			<button type="submit" class="lf-btn lf-btn--primary"><?php esc_html_e('Send message', 'leadsforward-core'); ?></button>
			<span class="lf-contact-form__status" aria-live="polite"></span>
		</div>
	</form>
	<?php
}

function lf_contact_form_handle_submit(): void {
	check_ajax_referer('lf_contact_form_submit', 'nonce');
	$config = lf_contact_form_get_config();
	$fields = $config['fields'] ?? [];
	$webhook = (string) ($config['webhook_url'] ?? '');
	$payload = [];
	$errors = [];
	foreach ($fields as $field) {
		$key = sanitize_title((string) ($field['key'] ?? ''));
		if ($key === '') {
			continue;
		}
		$value = isset($_POST[$key]) ? sanitize_text_field(wp_unslash((string) $_POST[$key])) : '';
		if (!empty($field['required']) && $value === '') {
			$errors[] = $field['label'] ?? $key;
		}
		$payload[$key] = $value;
	}
	if (!empty($errors)) {
		wp_send_json_error(['message' => __('Please fill out required fields.', 'leadsforward-core')], 422);
	}
	$payload['page_url'] = esc_url_raw(wp_unslash((string) ($_POST['page_url'] ?? '')));
	$payload['page_title'] = sanitize_text_field(wp_unslash((string) ($_POST['page_title'] ?? '')));
	if ($webhook !== '') {
		$response = wp_remote_post($webhook, [
			'headers' => ['Content-Type' => 'application/json'],
			'body' => wp_json_encode($payload),
			'timeout' => 12,
		]);
		if (is_wp_error($response)) {
			wp_send_json_error(['message' => __('Unable to send request right now.', 'leadsforward-core')], 500);
		}
	}
	wp_send_json_success(['message' => __('Thanks! We will be in touch shortly.', 'leadsforward-core')]);
}

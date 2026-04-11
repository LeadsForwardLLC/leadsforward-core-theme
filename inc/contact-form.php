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
				'required' => true,
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

function lf_contact_form_available_field_templates(): array {
	return [
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
			'required' => true,
			'placeholder' => __('(555) 123-4567', 'leadsforward-core'),
		],
		[
			'key' => 'company',
			'label' => __('Company', 'leadsforward-core'),
			'type' => 'text',
			'required' => false,
			'placeholder' => __('Company name', 'leadsforward-core'),
		],
		[
			'key' => 'service_needed',
			'label' => __('Service needed', 'leadsforward-core'),
			'type' => 'text',
			'required' => false,
			'placeholder' => __('What service are you looking for?', 'leadsforward-core'),
		],
		[
			'key' => 'project_address',
			'label' => __('Project address', 'leadsforward-core'),
			'type' => 'text',
			'required' => false,
			'placeholder' => __('Street, city, state', 'leadsforward-core'),
		],
		[
			'key' => 'budget',
			'label' => __('Budget', 'leadsforward-core'),
			'type' => 'text',
			'required' => false,
			'placeholder' => __('Estimated budget range', 'leadsforward-core'),
		],
		[
			'key' => 'timeline',
			'label' => __('Preferred timeline', 'leadsforward-core'),
			'type' => 'text',
			'required' => false,
			'placeholder' => __('When do you want to start?', 'leadsforward-core'),
		],
		[
			'key' => 'message',
			'label' => __('How can we help?', 'leadsforward-core'),
			'type' => 'textarea',
			'required' => true,
			'placeholder' => __('Tell us about your project.', 'leadsforward-core'),
		],
	];
}

/**
 * @param array<string,mixed> $field
 * @param array<string,bool>  $used_keys
 * @return array<string,mixed>|null
 */
function lf_contact_form_sanitize_field(array $field, array &$used_keys): ?array {
	$label = sanitize_text_field((string) ($field['label'] ?? ''));
	if ($label === '') {
		return null;
	}
	$type = strtolower(sanitize_text_field((string) ($field['type'] ?? 'text')));
	if (!in_array($type, ['text', 'email', 'tel', 'textarea'], true)) {
		$type = 'text';
	}
	$required = !empty($field['required']);
	$placeholder = sanitize_text_field((string) ($field['placeholder'] ?? ''));
	$key = sanitize_title((string) ($field['key'] ?? ''));
	if ($key === '') {
		$key = sanitize_title($label);
	}
	if ($key === '') {
		$key = 'field';
	}
	$base_key = $key;
	$suffix = 2;
	while (isset($used_keys[$key])) {
		$key = $base_key . '_' . $suffix;
		$suffix++;
	}
	$used_keys[$key] = true;
	return [
		'key' => $key,
		'label' => $label,
		'type' => $type,
		'required' => $required,
		'placeholder' => $placeholder,
	];
}

/**
 * @param array<int,array<string,mixed>> $fields
 * @return array<int,array<string,mixed>>
 */
function lf_contact_form_sanitize_fields(array $fields): array {
	$used = [];
	$clean = [];
	foreach ($fields as $field) {
		if (!is_array($field)) {
			continue;
		}
		$sanitized = lf_contact_form_sanitize_field($field, $used);
		if ($sanitized !== null) {
			$clean[] = $sanitized;
		}
	}
	return !empty($clean) ? $clean : lf_contact_form_default_config()['fields'];
}

function lf_contact_form_get_config(): array {
	$stored = get_option(LF_CONTACT_FORM_OPTION, []);
	$default = lf_contact_form_default_config();
	if (!is_array($stored)) {
		return $default;
	}
	$stored['fields'] = isset($stored['fields']) && is_array($stored['fields']) ? lf_contact_form_sanitize_fields($stored['fields']) : [];
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
	$fields_json_raw = isset($_POST['lf_contact_form_fields_json']) ? wp_unslash((string) $_POST['lf_contact_form_fields_json']) : '';
	$webhook = isset($_POST['lf_contact_form_webhook']) ? wp_unslash((string) $_POST['lf_contact_form_webhook']) : '';
	$webhook = trim($webhook);
	$fields = [];
	if ($fields_json_raw !== '') {
		$decoded = json_decode($fields_json_raw, true);
		if (is_array($decoded)) {
			$fields = lf_contact_form_sanitize_fields($decoded);
		}
	}
	if (empty($fields)) {
		$fields = lf_contact_form_parse_fields($fields_raw);
	}
	$config = [
		'webhook_url' => esc_url_raw($webhook),
		'fields' => $fields,
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
	$fields_json = wp_json_encode($config['fields'] ?? []);
	$templates_json = wp_json_encode(lf_contact_form_available_field_templates());
	$webhook = (string) ($config['webhook_url'] ?? '');
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Contact Form', 'leadsforward-core'); ?></h1>
		<?php if (isset($_GET['saved'])) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Contact form settings saved.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<style>
			.lf-cf-builder-grid { display: grid; grid-template-columns: 1.8fr 1fr; gap: 1.25rem; margin-top: 1rem; }
			.lf-cf-panel { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 1rem; }
			.lf-cf-panel h2 { margin-top: 0; margin-bottom: 0.6rem; }
			.lf-cf-list { display: grid; gap: 0.75rem; min-height: 64px; }
			.lf-cf-item { border: 1px solid #dcdcde; border-radius: 8px; background: #f8f9fb; padding: 0.8rem; }
			.lf-cf-item.is-dragging { opacity: 0.65; }
			.lf-cf-item__head { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.65rem; }
			.lf-cf-item__drag { cursor: grab; border: 1px solid #c3c4c7; background: #fff; border-radius: 6px; padding: 0.1rem 0.45rem; }
			.lf-cf-item__title { font-weight: 600; margin-right: auto; }
			.lf-cf-item__actions button { margin-left: 0.35rem; }
			.lf-cf-item__grid { display: grid; grid-template-columns: 1fr 170px 120px; gap: 0.6rem; }
			.lf-cf-item__grid .wide { grid-column: 1 / -1; }
			.lf-cf-templates { display: grid; gap: 0.5rem; margin-bottom: 0.9rem; }
			.lf-cf-templates button { justify-content: flex-start; text-align: left; }
			.lf-cf-empty { color: #646970; font-style: italic; padding: 0.4rem 0; }
			@media (max-width: 1100px) {
				.lf-cf-builder-grid { grid-template-columns: 1fr; }
				.lf-cf-item__grid { grid-template-columns: 1fr; }
			}
		</style>
		<form method="post" action="">
			<?php wp_nonce_field('lf_contact_form_save', 'lf_contact_form_nonce'); ?>
			<div class="lf-cf-builder-grid">
				<div class="lf-cf-panel">
					<h2><?php esc_html_e('Form Builder', 'leadsforward-core'); ?></h2>
					<p class="description"><?php esc_html_e('Drag fields to reorder. Edit labels, type, required status, and placeholders inline.', 'leadsforward-core'); ?></p>
					<div id="lf-cf-selected-list" class="lf-cf-list"></div>
				</div>
				<div class="lf-cf-panel">
					<h2><?php esc_html_e('Available Fields', 'leadsforward-core'); ?></h2>
					<p class="description"><?php esc_html_e('Click to add, then drag into position. You can duplicate any field from the builder too.', 'leadsforward-core'); ?></p>
					<div id="lf-cf-templates" class="lf-cf-templates"></div>
					<hr />
					<button type="button" class="button" id="lf-cf-add-custom"><?php esc_html_e('Add Custom Field', 'leadsforward-core'); ?></button>
				</div>
			</div>
			<input type="hidden" name="lf_contact_form_fields_json" id="lf_contact_form_fields_json" value="" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lf_contact_form_fields"><?php esc_html_e('Form fields', 'leadsforward-core'); ?></label></th>
					<td>
						<textarea name="lf_contact_form_fields" id="lf_contact_form_fields" class="large-text code" rows="5"><?php echo esc_textarea($fields_text); ?></textarea>
						<p class="description"><?php esc_html_e('Advanced fallback format: one field per line = Label | type | required|optional | placeholder.', 'leadsforward-core'); ?></p>
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
		<script>
			(function () {
				var initialFields = <?php echo $fields_json ?: '[]'; ?>;
				var templates = <?php echo $templates_json ?: '[]'; ?>;
				var listEl = document.getElementById('lf-cf-selected-list');
				var templatesEl = document.getElementById('lf-cf-templates');
				var jsonEl = document.getElementById('lf_contact_form_fields_json');
				var legacyTextarea = document.getElementById('lf_contact_form_fields');
				var addCustomBtn = document.getElementById('lf-cf-add-custom');
				if (!listEl || !templatesEl || !jsonEl || !legacyTextarea || !Array.isArray(initialFields)) {
					return;
				}

				function sanitizeField(field) {
					if (!field || typeof field !== 'object') return null;
					var label = String(field.label || '').trim();
					if (!label) return null;
					var type = String(field.type || 'text').toLowerCase();
					if (['text', 'email', 'tel', 'textarea'].indexOf(type) === -1) type = 'text';
					return {
						key: String(field.key || '').trim(),
						label: label,
						type: type,
						required: !!field.required,
						placeholder: String(field.placeholder || '').trim()
					};
				}

				function getDragAfterElement(container, y) {
					var items = [].slice.call(container.querySelectorAll('.lf-cf-item:not(.is-dragging)'));
					return items.reduce(function (closest, child) {
						var box = child.getBoundingClientRect();
						var offset = y - box.top - box.height / 2;
						if (offset < 0 && offset > closest.offset) {
							return { offset: offset, element: child };
						}
						return closest;
					}, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
				}

				function renderEmptyState() {
					if (!listEl.querySelector('.lf-cf-item')) {
						var empty = document.createElement('div');
						empty.className = 'lf-cf-empty';
						empty.textContent = '<?php echo esc_js(__('No fields yet. Add from the right panel.', 'leadsforward-core')); ?>';
						listEl.appendChild(empty);
					}
				}

				function clearEmptyState() {
					var empty = listEl.querySelector('.lf-cf-empty');
					if (empty) empty.remove();
				}

				function syncSerializedFields() {
					var rows = [].slice.call(listEl.querySelectorAll('.lf-cf-item'));
					var data = rows.map(function (row) {
						return sanitizeField({
							key: row.querySelector('[data-role="key"]').value,
							label: row.querySelector('[data-role="label"]').value,
							type: row.querySelector('[data-role="type"]').value,
							required: row.querySelector('[data-role="required"]').checked,
							placeholder: row.querySelector('[data-role="placeholder"]').value
						});
					}).filter(Boolean);
					jsonEl.value = JSON.stringify(data);
					legacyTextarea.value = data.map(function (f) {
						return [f.label, f.type, (f.required ? 'required' : 'optional'), f.placeholder].join(' | ');
					}).join("\n");
				}

				function wireRowEvents(row) {
					row.querySelector('[data-action="remove"]').addEventListener('click', function () {
						row.remove();
						renderEmptyState();
						syncSerializedFields();
					});
					row.querySelector('[data-action="duplicate"]').addEventListener('click', function () {
						var payload = {
							key: row.querySelector('[data-role="key"]').value,
							label: row.querySelector('[data-role="label"]').value,
							type: row.querySelector('[data-role="type"]').value,
							required: row.querySelector('[data-role="required"]').checked,
							placeholder: row.querySelector('[data-role="placeholder"]').value
						};
						addField(payload);
					});
					[].slice.call(row.querySelectorAll('input,select,textarea')).forEach(function (el) {
						el.addEventListener('input', syncSerializedFields);
						el.addEventListener('change', syncSerializedFields);
					});
					row.addEventListener('dragstart', function () {
						row.classList.add('is-dragging');
					});
					row.addEventListener('dragend', function () {
						row.classList.remove('is-dragging');
						syncSerializedFields();
					});
				}

				function buildRow(field) {
					var clean = sanitizeField(field);
					if (!clean) return null;
					var row = document.createElement('div');
					row.className = 'lf-cf-item';
					row.setAttribute('draggable', 'true');
					row.innerHTML =
						'<div class="lf-cf-item__head">' +
							'<button type="button" class="lf-cf-item__drag" title="<?php echo esc_attr__('Drag to reorder', 'leadsforward-core'); ?>">⋮⋮</button>' +
							'<div class="lf-cf-item__title">' + clean.label + '</div>' +
							'<div class="lf-cf-item__actions">' +
								'<button type="button" class="button-link" data-action="duplicate"><?php echo esc_js(__('Duplicate', 'leadsforward-core')); ?></button>' +
								'<button type="button" class="button-link-delete" data-action="remove"><?php echo esc_js(__('Remove', 'leadsforward-core')); ?></button>' +
							'</div>' +
						'</div>' +
						'<div class="lf-cf-item__grid">' +
							'<input type="hidden" data-role="key" value="' + clean.key.replace(/"/g, '&quot;') + '">' +
							'<p><label><?php echo esc_js(__('Label', 'leadsforward-core')); ?><br><input type="text" class="regular-text" data-role="label" value="' + clean.label.replace(/"/g, '&quot;') + '"></label></p>' +
							'<p><label><?php echo esc_js(__('Type', 'leadsforward-core')); ?><br>' +
								'<select data-role="type">' +
									'<option value="text">text</option>' +
									'<option value="email">email</option>' +
									'<option value="tel">tel</option>' +
									'<option value="textarea">textarea</option>' +
								'</select>' +
							'</label></p>' +
							'<p><label><?php echo esc_js(__('Required', 'leadsforward-core')); ?><br><input type="checkbox" data-role="required"></label></p>' +
							'<p class="wide"><label><?php echo esc_js(__('Placeholder', 'leadsforward-core')); ?><br><input type="text" class="large-text" data-role="placeholder" value="' + clean.placeholder.replace(/"/g, '&quot;') + '"></label></p>' +
						'</div>';
					row.querySelector('[data-role="type"]').value = clean.type;
					row.querySelector('[data-role="required"]').checked = clean.required;
					wireRowEvents(row);
					return row;
				}

				function addField(field) {
					clearEmptyState();
					var row = buildRow(field);
					if (!row) return;
					listEl.appendChild(row);
					syncSerializedFields();
				}

				listEl.addEventListener('dragover', function (e) {
					e.preventDefault();
					var dragging = listEl.querySelector('.is-dragging');
					if (!dragging) return;
					var after = getDragAfterElement(listEl, e.clientY);
					if (!after) {
						listEl.appendChild(dragging);
					} else {
						listEl.insertBefore(dragging, after);
					}
				});

				templates.forEach(function (template) {
					var btn = document.createElement('button');
					btn.type = 'button';
					btn.className = 'button';
					btn.textContent = '+ ' + (template.label || '<?php echo esc_js(__('Field', 'leadsforward-core')); ?>');
					btn.addEventListener('click', function () {
						addField(template);
					});
					templatesEl.appendChild(btn);
				});

				if (addCustomBtn) {
					addCustomBtn.addEventListener('click', function () {
						addField({
							key: '',
							label: '<?php echo esc_js(__('Custom field', 'leadsforward-core')); ?>',
							type: 'text',
							required: false,
							placeholder: ''
						});
					});
				}

				initialFields.forEach(function (field) {
					addField(field);
				});
				renderEmptyState();
				syncSerializedFields();
			})();
		</script>
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
		<input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;opacity:0;" aria-hidden="true" />
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
	if (function_exists('lf_security_rate_limit_allow') && !lf_security_rate_limit_allow('contact_form_submit', 8, 300)) {
		wp_send_json_error(['message' => __('Too many attempts. Please wait a few minutes and try again.', 'leadsforward-core')], 429);
	}
	$honeypot = isset($_POST['website']) ? trim((string) wp_unslash($_POST['website'])) : '';
	if ($honeypot !== '') {
		// Silent success keeps bots from probing validation behavior.
		wp_send_json_success(['message' => __('Thanks! We will be in touch shortly.', 'leadsforward-core')]);
	}
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

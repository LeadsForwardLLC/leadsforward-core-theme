<?php
/**
 * Per-page publish timing (Manifest scope UI) + unpublished CPT card links.
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_PUBLISH_SCHEDULE_OPTION = 'lf_ai_publish_schedule';

/**
 * @return list<string>
 */
function lf_publish_schedule_page_keys(): array {
	return [
		'page:home',
		'page:about',
		'page:contact',
		'page:reviews',
		'page:blog',
		'page:services',
		'page:service-areas',
	];
}

/**
 * @return array<string, string>
 */
function lf_publish_schedule_page_labels(): array {
	return [
		'page:home' => __('Homepage', 'leadsforward-core'),
		'page:about' => __('About', 'leadsforward-core'),
		'page:contact' => __('Contact', 'leadsforward-core'),
		'page:reviews' => __('Reviews', 'leadsforward-core'),
		'page:blog' => __('Blog', 'leadsforward-core'),
		'page:services' => __('Services overview', 'leadsforward-core'),
		'page:service-areas' => __('Service areas overview', 'leadsforward-core'),
	];
}

/**
 * Built-in publish timing for core pages (used when no per-key override is saved).
 *
 * @return array<string, array{timing:string,date:string}>
 */
function lf_publish_schedule_default_items(): array {
	return [
		'page:home' => ['timing' => 'now', 'date' => ''],
		'page:services' => ['timing' => 'now', 'date' => ''],
		'page:service-areas' => ['timing' => 'now', 'date' => ''],
		'page:contact' => ['timing' => 'now', 'date' => ''],
		'page:about' => ['timing' => 'draft', 'date' => ''],
		'page:reviews' => ['timing' => 'draft', 'date' => ''],
		'page:blog' => ['timing' => 'draft', 'date' => ''],
	];
}

/**
 * Default timing for one schedule key (pages from defaults map; CPT rows draft).
 *
 * @return array{timing:string,date:string}|null
 */
function lf_publish_schedule_default_item_for_key(string $schedule_key): ?array {
	$defaults = lf_publish_schedule_default_items();
	if (isset($defaults[$schedule_key])) {
		return $defaults[$schedule_key];
	}
	if (preg_match('/^lf_service:/', $schedule_key) || preg_match('/^lf_service_area:/', $schedule_key)) {
		return ['timing' => 'draft', 'date' => ''];
	}

	return null;
}

/**
 * Saved or built-in timing row for UI + status resolution.
 *
 * @return array{timing:string,date:string}
 */
function lf_publish_schedule_resolved_item(string $schedule_key): array {
	$items = lf_publish_schedule_get_items();
	if (isset($items[$schedule_key]) && is_array($items[$schedule_key])) {
		return $items[$schedule_key];
	}
	$fallback = lf_publish_schedule_default_item_for_key($schedule_key);
	if ($fallback !== null) {
		return $fallback;
	}

	return ['timing' => 'draft', 'date' => ''];
}

/**
 * Reset publish timing to built-in page defaults (drops per-CPT overrides).
 */
function lf_publish_schedule_reset_to_defaults(): void {
	update_option(LF_PUBLISH_SCHEDULE_OPTION, ['items' => lf_publish_schedule_default_items()], false);
}

/**
 * Remove saved CPT publish-timing rows so built-in draft defaults apply.
 */
function lf_publish_schedule_strip_cpt_items(): void {
	$raw = get_option(LF_PUBLISH_SCHEDULE_OPTION, []);
	if (!is_array($raw)) {
		return;
	}
	$items = isset($raw['items']) && is_array($raw['items']) ? $raw['items'] : $raw;
	if (!is_array($items)) {
		return;
	}
	$changed = false;
	foreach (array_keys($items) as $key) {
		if (!is_string($key)) {
			continue;
		}
		if (lf_publish_schedule_is_cpt_key($key)) {
			unset($items[ $key ]);
			$changed = true;
		}
	}
	if (!$changed) {
		return;
	}
	if ($items === []) {
		lf_publish_schedule_reset_to_defaults();
		return;
	}
	update_option(LF_PUBLISH_SCHEDULE_OPTION, ['items' => $items], false);
}

/**
 * Seed core page defaults into the option when nothing has been saved yet.
 */
function lf_publish_schedule_seed_defaults_if_empty(): void {
	$raw = get_option(LF_PUBLISH_SCHEDULE_OPTION, null);
	if ($raw !== null && $raw !== false && $raw !== []) {
		$items = lf_publish_schedule_get_items();
		if ($items !== []) {
			return;
		}
	}
	update_option(LF_PUBLISH_SCHEDULE_OPTION, ['items' => lf_publish_schedule_default_items()], false);
}

/**
 * Whether a schedule key is a service / service-area CPT row.
 */
function lf_publish_schedule_is_cpt_key(string $schedule_key): bool {
	return (bool) preg_match('/^lf_service(:|$)/', $schedule_key)
		|| (bool) preg_match('/^lf_service_area(:|$)/', $schedule_key);
}

/**
 * @param array<string, mixed> $row
 * @return array{timing:string,date:string}
 */
function lf_publish_schedule_normalize_row(string $schedule_key, array $row): array {
	$default = lf_publish_schedule_default_item_for_key($schedule_key) ?? ['timing' => 'draft', 'date' => ''];
	$timing = sanitize_key((string) ($row['timing'] ?? $default['timing']));
	if ($timing === '' || !in_array($timing, ['now', 'schedule', 'draft'], true)) {
		$timing = $default['timing'];
	}
	$date = sanitize_text_field((string) ($row['date'] ?? ''));
	if ($timing === 'schedule') {
		$date = lf_publish_schedule_normalize_datetime($date);
	} else {
		$date = '';
	}

	return [
		'timing' => $timing,
		'date' => $date,
	];
}

/**
 * @return array<string, array{timing:string,date:string}>
 */
function lf_publish_schedule_get_items(): array {
	$raw = get_option(LF_PUBLISH_SCHEDULE_OPTION, []);
	if (!is_array($raw)) {
		return [];
	}
	$items = isset($raw['items']) && is_array($raw['items']) ? $raw['items'] : $raw;
	$out = [];
	foreach ($items as $key => $row) {
		if (!is_string($key) || !is_array($row)) {
			continue;
		}
		$normalized = lf_publish_schedule_normalize_row($key, $row);
		if (lf_publish_schedule_is_cpt_key($key) && $normalized['timing'] === 'draft') {
			continue;
		}
		$out[ $key ] = $normalized;
	}

	return $out;
}

/**
 * One-time: drop legacy auto-stagger CPT schedule rows so draft defaults apply in the UI.
 */
function lf_publish_schedule_maybe_migrate_cpt_defaults(): void {
	if (!is_admin() || !current_user_can('edit_theme_options')) {
		return;
	}
	$page = isset($_GET['page']) ? sanitize_key((string) wp_unslash((string) $_GET['page'])) : '';
	$manifest_slug = defined('LF_MANIFEST_ADMIN_SLUG') ? LF_MANIFEST_ADMIN_SLUG : 'lf-manifest';
	if ($page !== $manifest_slug) {
		return;
	}
	if (get_option('lf_publish_schedule_cpt_draft_migrated') === '2') {
		return;
	}
	lf_publish_schedule_strip_cpt_items();
	update_option('lf_publish_schedule_cpt_draft_migrated', '2', false);
}
add_action('admin_init', 'lf_publish_schedule_maybe_migrate_cpt_defaults', 20);

/**
 * @param array<string, mixed> $raw_post
 */
function lf_publish_schedule_save_from_post(array $raw_post): void {
	$page_defaults = lf_publish_schedule_default_items();
	$items = [];

	foreach (lf_publish_schedule_page_keys() as $page_key) {
		if (isset($raw_post[ $page_key ]) && is_array($raw_post[ $page_key ])) {
			$items[ $page_key ] = lf_publish_schedule_normalize_row($page_key, $raw_post[ $page_key ]);
			continue;
		}
		$items[ $page_key ] = $page_defaults[ $page_key ] ?? ['timing' => 'draft', 'date' => ''];
	}

	foreach ($raw_post as $key => $row) {
		if (!is_string($key) || !is_array($row)) {
			continue;
		}
		$key = sanitize_text_field($key);
		if ($key === '' || !lf_publish_schedule_is_cpt_key($key)) {
			continue;
		}
		$normalized = lf_publish_schedule_normalize_row($key, $row);
		if ($normalized['timing'] === 'draft') {
			continue;
		}
		$items[ $key ] = $normalized;
	}

	update_option(LF_PUBLISH_SCHEDULE_OPTION, ['items' => $items], false);
}

function lf_publish_schedule_normalize_datetime(string $value): string {
	$value = trim($value);
	if ($value === '') {
		return '';
	}
	if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $value)) {
		$value = str_replace('T', ' ', substr($value, 0, 16));
		if (strlen($value) === 16) {
			$value .= ':00';
		}
	}
	if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
		return $value . ' 09:00:00';
	}
	if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}/', $value)) {
		try {
			$dt = new \DateTimeImmutable($value, wp_timezone());

			return $dt->format('Y-m-d H:i:s');
		} catch (\Throwable $e) {
			return '';
		}
	}

	return '';
}

/**
 * Value for <input type="datetime-local"> from stored schedule datetime.
 */
function lf_publish_schedule_datetime_local_value(string $stored): string {
	$stored = trim($stored);
	if ($stored === '') {
		return '';
	}
	if (preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/', $stored, $m)) {
		return $m[1] . 'T' . $m[2];
	}
	if (preg_match('/^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2})/', $stored, $m)) {
		return $m[1] . 'T' . $m[2];
	}
	if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $stored)) {
		return $stored . 'T09:00';
	}

	return '';
}

function lf_publish_schedule_datetime_local_min(): string {
	return wp_date('Y-m-d\TH:i');
}

/**
 * Resolve WordPress post_status + dates for a schedule key.
 *
 * @return array<string, string>|array{}
 */
function lf_publish_schedule_status_args(string $schedule_key): array {
	$items = lf_publish_schedule_get_items();
	$has_saved = isset($items[$schedule_key]) && is_array($items[$schedule_key]);
	$item = $has_saved ? $items[$schedule_key] : lf_publish_schedule_default_item_for_key($schedule_key);
	if (!is_array($item)) {
		return [];
	}
	$timing = (string) ($item['timing'] ?? 'now');
	if ($timing === 'now') {
		return [
			'post_status' => 'publish',
			'post_date' => current_time('mysql'),
			'post_date_gmt' => current_time('mysql', 1),
		];
	}
	if ($timing === 'draft') {
		return [
			'post_status' => 'draft',
		];
	}
	$date = lf_publish_schedule_normalize_datetime((string) ($item['date'] ?? ''));
	if ($date === '') {
		return [
			'post_status' => 'draft',
		];
	}
	$date = lf_launch_schedule_bump_until_future($date);

	return [
		'post_status' => 'future',
		'post_date' => $date,
		'post_date_gmt' => get_gmt_from_date($date),
	];
}

/**
 * @param array<string, mixed> $base_args
 * @return array<string, mixed>
 */
function lf_publish_schedule_merge_status_args(string $schedule_key, array $base_args): array {
	$items = lf_publish_schedule_get_items();
	$has_saved = isset($items[$schedule_key]) && is_array($items[$schedule_key]);
	if (!$has_saved) {
		$fallback = lf_publish_schedule_default_item_for_key($schedule_key);
		if ($fallback === null) {
			return $base_args;
		}
	}

	$explicit = lf_publish_schedule_status_args($schedule_key);
	if ($explicit === []) {
		return $base_args;
	}

	return array_merge($base_args, $explicit);
}

/**
 * Map schedule page key to WP page path.
 */
function lf_publish_schedule_page_path(string $schedule_key): string {
	$map = [
		'page:home' => 'home',
		'page:about' => 'about-us',
		'page:contact' => 'contact',
		'page:reviews' => 'reviews',
		'page:blog' => 'blog',
		'page:services' => 'services',
		'page:service-areas' => 'service-areas',
	];
	if (!isset($map[$schedule_key])) {
		return '';
	}

	return $map[$schedule_key];
}

/**
 * Apply saved publish timing to core/overview pages.
 */
function lf_publish_schedule_apply_site_pages(): void {
	foreach (lf_publish_schedule_page_keys() as $key) {
		$status_args = lf_publish_schedule_status_args($key);
		if ($status_args === []) {
			continue;
		}
		$path = lf_publish_schedule_page_path($key);
		if ($path === '') {
			continue;
		}
		$page = get_page_by_path($path, OBJECT, 'page');
		if (!$page instanceof \WP_Post) {
			continue;
		}
		$update = array_merge(['ID' => (int) $page->ID], $status_args);
		wp_update_post($update);
		update_post_meta((int) $page->ID, 'lf_manifest_schedule_managed', 1);
	}
}

/**
 * Post statuses to include in service / area card grids (unpublished still listed).
 *
 * @return list<string>
 */
function lf_cpt_card_query_post_statuses(): array {
	return ['publish', 'future', 'draft', 'pending', 'private'];
}

/**
 * Whether a CPT row should link to its own permalink (live on the front end).
 */
function lf_cpt_card_is_live(\WP_Post $post): bool {
	return $post->post_status === 'publish';
}

/**
 * Editor-facing publish state for service / area pickers.
 *
 * @return array{status: string, status_label: string, is_live: bool}
 */
function lf_cpt_editor_status_meta(\WP_Post $post): array {
	$status = sanitize_key((string) $post->post_status);
	switch ($status) {
		case 'publish':
			$label = __('Live', 'leadsforward-core');
			break;
		case 'future':
			$ts = strtotime((string) $post->post_date_gmt . ' GMT');
			$when = $ts ? wp_date('M j, Y g:ia', $ts) : '';
			$label = $when !== ''
				? sprintf(
					/* translators: %s: scheduled publish datetime */
					__('Scheduled · %s', 'leadsforward-core'),
					$when
				)
				: __('Scheduled', 'leadsforward-core');
			break;
		case 'draft':
			$label = __('Draft', 'leadsforward-core');
			break;
		case 'pending':
			$label = __('Pending review', 'leadsforward-core');
			break;
		case 'private':
			$label = __('Private', 'leadsforward-core');
			break;
		default:
			$label = ucfirst($status);
	}
	return [
		'status' => $status,
		'status_label' => $label,
		'is_live' => $status === 'publish',
	];
}

/**
 * Fallback URL for unpublished service / area cards (overview page, then Global Settings).
 */
function lf_unpublished_cpt_card_url(?\WP_Post $post = null): string {
	if ($post instanceof \WP_Post) {
		$overview_slug = $post->post_type === 'lf_service_area' ? 'service-areas' : 'services';
		$overview = get_page_by_path($overview_slug, OBJECT, 'page');
		if ($overview instanceof \WP_Post && $overview->post_status === 'publish') {
			$url = get_permalink($overview);
			if (is_string($url) && $url !== '') {
				return $url;
			}
		}
	}

	$page_id = 0;
	if (function_exists('lf_get_option')) {
		$page_id = (int) lf_get_option('lf_unpublished_card_link', 'option', 0);
	}
	if ($page_id > 0) {
		$url = get_permalink($page_id);
		if (is_string($url) && $url !== '') {
			return $url;
		}
	}
	$contact = get_page_by_path('contact', OBJECT, 'page');
	if ($contact instanceof \WP_Post) {
		$url = get_permalink($contact);
		if (is_string($url) && $url !== '') {
			return $url;
		}
	}

	return home_url('/contact/');
}

/**
 * Permalink for a service / area card (live page or overview / Global Settings fallback).
 */
function lf_cpt_card_permalink(\WP_Post $post): string {
	if (lf_cpt_card_is_live($post)) {
		$url = get_permalink($post);
		if (is_string($url) && $url !== '') {
			return $url;
		}
	}

	return lf_unpublished_cpt_card_url($post);
}

/**
 * Render publish-timing controls for one schedule key.
 *
 * @param array{timing?:string,date?:string} $stored
 */
function lf_publish_schedule_render_controls(string $schedule_key, array $stored, bool $compact = false): void {
	$resolved = $stored !== [] ? $stored : lf_publish_schedule_resolved_item($schedule_key);
	$default = lf_publish_schedule_default_item_for_key($schedule_key) ?? ['timing' => 'draft', 'date' => ''];
	$timing = sanitize_key((string) ($resolved['timing'] ?? $default['timing']));
	if (!in_array($timing, ['now', 'schedule', 'draft'], true)) {
		$timing = $default['timing'];
	}
	$date = sanitize_text_field((string) ($resolved['date'] ?? ''));
	$field_base = 'lf_ai_publish_schedule[' . esc_attr($schedule_key) . ']';
	$wrap_class = $compact ? 'lf-publish-schedule lf-publish-schedule--compact' : 'lf-publish-schedule';
	?>
	<div class="<?php echo esc_attr($wrap_class); ?>" data-lf-publish-schedule data-schedule-key="<?php echo esc_attr($schedule_key); ?>">
		<label class="screen-reader-text" for="<?php echo esc_attr('lf-ps-timing-' . md5($schedule_key)); ?>"><?php esc_html_e('Publish timing', 'leadsforward-core'); ?></label>
		<select
			id="<?php echo esc_attr('lf-ps-timing-' . md5($schedule_key)); ?>"
			class="lf-publish-schedule__timing"
			name="<?php echo esc_attr($field_base . '[timing]'); ?>"
			data-lf-publish-timing
		>
			<option value="now" <?php selected($timing, 'now'); ?>><?php esc_html_e('Publish now', 'leadsforward-core'); ?></option>
			<option value="schedule" <?php selected($timing, 'schedule'); ?>><?php esc_html_e('Schedule', 'leadsforward-core'); ?></option>
			<option value="draft" <?php selected($timing, 'draft'); ?>><?php esc_html_e('Keep draft', 'leadsforward-core'); ?></option>
		</select>
		<input
			type="datetime-local"
			class="lf-publish-schedule__date<?php echo $timing === 'schedule' ? '' : ' lf-publish-schedule__date--hidden'; ?>"
			name="<?php echo esc_attr($field_base . '[date]'); ?>"
			value="<?php echo esc_attr(lf_publish_schedule_datetime_local_value($date)); ?>"
			min="<?php echo esc_attr(lf_publish_schedule_datetime_local_min()); ?>"
			step="60"
			data-lf-publish-date
			autocomplete="off"
			aria-label="<?php esc_attr_e('Publish date and time', 'leadsforward-core'); ?>"
		/>
		<button
			type="button"
			class="button button-small lf-publish-schedule__date-trigger<?php echo $timing === 'schedule' ? '' : ' lf-publish-schedule__date-trigger--hidden'; ?>"
			data-lf-publish-date-trigger
			aria-label="<?php esc_attr_e('Open date picker', 'leadsforward-core'); ?>"
			title="<?php esc_attr_e('Pick date & time', 'leadsforward-core'); ?>"
		><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span></button>
	</div>
	<?php
}

/**
 * Page-type publish timing block (homepage, core pages, overviews).
 */
function lf_publish_schedule_render_page_types_panel(): void {
	$items = lf_publish_schedule_get_items();
	$labels = lf_publish_schedule_page_labels();
	?>
	<details class="lf-publish-schedule-panel">
		<summary class="lf-publish-schedule-panel__summary"><?php esc_html_e('Publish timing', 'leadsforward-core'); ?></summary>
		<div class="lf-publish-schedule-panel__body">
			<p class="description lf-publish-schedule-panel__lead"><?php esc_html_e('Publish now, schedule a date (WordPress auto-publishes), or keep as draft.', 'leadsforward-core'); ?></p>
			<div class="lf-publish-schedule-panel__table" role="group" aria-label="<?php esc_attr_e('Page publish timing', 'leadsforward-core'); ?>">
				<?php foreach (lf_publish_schedule_page_keys() as $key) : ?>
					<div class="lf-publish-schedule-panel__row">
						<span class="lf-publish-schedule-panel__label"><?php echo esc_html($labels[$key] ?? $key); ?></span>
						<?php lf_publish_schedule_render_controls($key, $items[$key] ?? lf_publish_schedule_resolved_item($key), true); ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</details>
	<?php
}

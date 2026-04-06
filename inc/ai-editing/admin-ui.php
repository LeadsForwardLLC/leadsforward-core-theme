<?php
/**
 * AI editing: admin-only UI. "Edit with AI" meta box, diff preview, apply/rollback.
 * No frontend scripts; no API keys. All AI calls server-side.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_AI_CAP = 'edit_posts';

function lf_ai_assistant_modes(): array {
	return [
		'auto',
		'edit_existing',
		'create_page',
		'create_cpt',
		'create_blog_post',
		'create_batch',
	];
}

function lf_ai_assistant_allowed_cpt_types(): array {
	return [
		'lf_service',
		'lf_service_area',
		'lf_faq',
		'lf_project',
		'lf_testimonial',
	];
}

function lf_ai_assistant_batch_types(): array {
	return [
		'page',
		'post',
		'lf_service',
		'lf_service_area',
		'lf_faq',
		'lf_project',
		'lf_testimonial',
	];
}

function lf_ai_assistant_creation_post_type(string $mode, string $cpt_type): string {
	if ($mode === 'create_page') {
		return 'page';
	}
	if ($mode === 'create_blog_post') {
		return 'post';
	}
	if ($mode === 'create_cpt' && in_array($cpt_type, lf_ai_assistant_allowed_cpt_types(), true)) {
		return $cpt_type;
	}
	return '';
}

function lf_ai_assistant_mode_and_cpt_for_batch_type(string $batch_type): array {
	if ($batch_type === 'page') {
		return ['mode' => 'create_page', 'cpt' => ''];
	}
	if ($batch_type === 'post') {
		return ['mode' => 'create_blog_post', 'cpt' => ''];
	}
	if (in_array($batch_type, lf_ai_assistant_allowed_cpt_types(), true)) {
		return ['mode' => 'create_cpt', 'cpt' => $batch_type];
	}
	return ['mode' => '', 'cpt' => ''];
}

function lf_ai_assistant_mode_and_cpt_for_post_type(string $post_type): array {
	if ($post_type === 'page') {
		return ['mode' => 'create_page', 'cpt' => ''];
	}
	if ($post_type === 'post') {
		return ['mode' => 'create_blog_post', 'cpt' => ''];
	}
	if (in_array($post_type, lf_ai_assistant_allowed_cpt_types(), true)) {
		return ['mode' => 'create_cpt', 'cpt' => $post_type];
	}
	return ['mode' => '', 'cpt' => ''];
}

function lf_ai_assistant_context_for_post(\WP_Post $post): array {
	$front_id = (int) get_option('page_on_front');
	if ($post->post_type === 'page' && (int) $post->ID === $front_id) {
		return ['type' => 'homepage', 'id' => 'homepage'];
	}
	return ['type' => (string) $post->post_type, 'id' => (int) $post->ID];
}

function lf_ai_assistant_target_label_for_context(string $context_type, $context_id): string {
	if ($context_type === 'homepage' || $context_id === 'homepage') {
		return __('Homepage', 'leadsforward-core');
	}
	$post = is_numeric($context_id) ? get_post((int) $context_id) : null;
	if ($post instanceof \WP_Post) {
		return sprintf('%s (%s)', $post->post_title, strtoupper((string) $post->post_type));
	}
	return __('Current context', 'leadsforward-core');
}

function lf_ai_assistant_extract_reference_from_prompt(string $prompt): string {
	$prompt = trim($prompt);
	if ($prompt === '') {
		return '';
	}
	$patterns = [
		'/(?:on|for|to|in)\s+(?:the\s+)?["\']?([a-z0-9\- ]{2,80})["\']?\s+page\b/i',
		'/\b(?:update|edit|change|rewrite)\s+["\']?([a-z0-9\- ]{2,80})["\']?\s+page\b/i',
	];
	foreach ($patterns as $pattern) {
		if (preg_match($pattern, $prompt, $m) === 1) {
			$reference = sanitize_text_field((string) ($m[1] ?? ''));
			$reference_lower = strtolower(trim($reference));
			// "this/current page" should always mean the active context.
			if (in_array($reference_lower, ['this', 'current', 'that', 'here'], true)) {
				return '';
			}
			return $reference;
		}
	}
	return '';
}

function lf_ai_assistant_resolve_target_context(string $reference, string $fallback_context_type, $fallback_context_id): array {
	$reference = sanitize_text_field(trim($reference));
	if ($reference === '') {
		return ['type' => $fallback_context_type, 'id' => $fallback_context_id];
	}
	$ref_lower = strtolower($reference);
	if (in_array($ref_lower, ['this', 'this page', 'current', 'current page', 'here', 'that page'], true)) {
		return ['type' => $fallback_context_type, 'id' => $fallback_context_id];
	}
	if (in_array($ref_lower, ['homepage', 'home page', 'front page', 'home'], true)) {
		return ['type' => 'homepage', 'id' => 'homepage'];
	}
	$slug = sanitize_title($reference);
	if (preg_match('#https?://#i', $reference) === 1) {
		$path = (string) parse_url($reference, PHP_URL_PATH);
		$slug = sanitize_title((string) basename(trim($path, '/')));
	}
	$post_types = ['page', 'post', 'lf_service', 'lf_service_area', 'lf_faq', 'lf_project', 'lf_testimonial'];
	if ($slug !== '') {
		$by_path = get_page_by_path($slug, OBJECT, $post_types);
		if ($by_path instanceof \WP_Post) {
			return lf_ai_assistant_context_for_post($by_path);
		}
	}
	$matches = get_posts([
		'post_type' => $post_types,
		'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
		'posts_per_page' => 1,
		's' => $reference,
		'orderby' => 'relevance',
		'order' => 'DESC',
		'no_found_rows' => true,
	]);
	if (!empty($matches) && $matches[0] instanceof \WP_Post) {
		return lf_ai_assistant_context_for_post($matches[0]);
	}
	return ['type' => $fallback_context_type, 'id' => $fallback_context_id];
}

function lf_ai_assistant_infer_mode_from_prompt(string $prompt): array {
	$lower = strtolower($prompt);
	$count = 5;
	if (preg_match('/\b([1-9]|1\d|20)\b/', $lower, $m) === 1) {
		$count = max(1, min(20, (int) $m[1]));
	}
	$has_create_verb = preg_match('/\b(create|add|generate)\b/', $lower) === 1;
	$has_new_object = preg_match('/\bnew\s+(faq|service area|service|project|testimonial|review|blog post|post|page)\b/', $lower) === 1;
	if (!$has_create_verb && !$has_new_object) {
		return ['mode' => 'edit_existing', 'cpt_type' => '', 'batch_type' => 'post', 'batch_count' => $count];
	}
	if (preg_match('/\b(batch|multiple|several|list of)\b/', $lower) === 1) {
		$batch_type = 'post';
		if (strpos($lower, 'service area') !== false) {
			$batch_type = 'lf_service_area';
		} elseif (strpos($lower, 'service') !== false) {
			$batch_type = 'lf_service';
		} elseif (strpos($lower, 'faq') !== false) {
			$batch_type = 'lf_faq';
		} elseif (strpos($lower, 'project') !== false) {
			$batch_type = 'lf_project';
		} elseif (strpos($lower, 'testimonial') !== false || strpos($lower, 'review') !== false) {
			$batch_type = 'lf_testimonial';
		} elseif (strpos($lower, 'page') !== false) {
			$batch_type = 'page';
		}
		return ['mode' => 'create_batch', 'cpt_type' => '', 'batch_type' => $batch_type, 'batch_count' => $count];
	}
	if (strpos($lower, 'faq') !== false) {
		return ['mode' => 'create_cpt', 'cpt_type' => 'lf_faq', 'batch_type' => 'post', 'batch_count' => $count];
	}
	if (strpos($lower, 'service area') !== false) {
		return ['mode' => 'create_cpt', 'cpt_type' => 'lf_service_area', 'batch_type' => 'post', 'batch_count' => $count];
	}
	if (strpos($lower, 'service') !== false) {
		return ['mode' => 'create_cpt', 'cpt_type' => 'lf_service', 'batch_type' => 'post', 'batch_count' => $count];
	}
	if (strpos($lower, 'project') !== false) {
		return ['mode' => 'create_cpt', 'cpt_type' => 'lf_project', 'batch_type' => 'post', 'batch_count' => $count];
	}
	if (strpos($lower, 'testimonial') !== false || strpos($lower, 'review') !== false) {
		return ['mode' => 'create_cpt', 'cpt_type' => 'lf_testimonial', 'batch_type' => 'post', 'batch_count' => $count];
	}
	if (strpos($lower, 'blog') !== false || strpos($lower, 'post') !== false) {
		return ['mode' => 'create_blog_post', 'cpt_type' => '', 'batch_type' => 'post', 'batch_count' => $count];
	}
	if (strpos($lower, 'page') !== false) {
		return ['mode' => 'create_page', 'cpt_type' => '', 'batch_type' => 'post', 'batch_count' => $count];
	}
	return ['mode' => 'edit_existing', 'cpt_type' => '', 'batch_type' => 'post', 'batch_count' => $count];
}

function lf_ai_assistant_requested_edit_keys(string $prompt, $context_id): array {
	$prompt_lower = strtolower($prompt);
	$editable = lf_get_ai_editable_fields($context_id);
	$keys = [];
	$add_if_allowed = static function (string $key) use (&$keys, $editable): void {
		if (isset($editable[$key])) {
			$keys[] = $key;
		}
	};

	$subheadline_signals = [
		'subheadline',
		'sub headline',
		'sub-heading',
		'subheading',
		'sub heading',
		'subtitle',
		'tagline',
		'supporting text',
		'support text',
		'under the headline',
		'below the headline',
		'text under the headline',
		'text below the headline',
	];
	$has_subheadline_signal = false;
	foreach ($subheadline_signals as $signal) {
		if (strpos($prompt_lower, $signal) !== false) {
			$has_subheadline_signal = true;
			break;
		}
	}

	// Subheadline/supporting-copy intent should map to hero_subheadline.
	if ($has_subheadline_signal) {
		$add_if_allowed('hero_subheadline');
	}

	// "headline" should mean hero_headline unless prompt indicates supporting/subheadline copy.
	if (strpos($prompt_lower, 'headline') !== false && !$has_subheadline_signal) {
		$add_if_allowed('hero_headline');
	}
	if (strpos($prompt_lower, 'primary cta') !== false || strpos($prompt_lower, 'cta primary') !== false) {
		$add_if_allowed('cta_primary_override');
		$add_if_allowed('lf_homepage_cta_primary');
		$add_if_allowed('lf_cta_primary_text');
	}
	if (strpos($prompt_lower, 'secondary cta') !== false || strpos($prompt_lower, 'cta secondary') !== false) {
		$add_if_allowed('lf_homepage_cta_secondary');
		$add_if_allowed('lf_cta_secondary_text');
	}
	if (strpos($prompt_lower, 'map') !== false) {
		$add_if_allowed('lf_service_area_map_override');
	}
	if (strpos($prompt_lower, 'faq question') !== false) {
		$add_if_allowed('lf_faq_question');
	}
	if (strpos($prompt_lower, 'faq answer') !== false) {
		$add_if_allowed('lf_faq_answer');
	}
	if (strpos($prompt_lower, 'review text') !== false || strpos($prompt_lower, 'testimonial text') !== false) {
		$add_if_allowed('lf_testimonial_review_text');
	}
	$keys = array_values(array_unique($keys));
	return $keys;
}

function lf_ai_resolve_registry_section_type(string $context_type, $context_id, string $selected_section_id, string $selected_section_type): string {
	$section_id = sanitize_text_field($selected_section_id);
	$section_type = sanitize_text_field($selected_section_type);
	if ($section_type !== '') {
		return $section_type;
	}
	if ($section_id === '') {
		return '';
	}
	if (($context_type === 'homepage' || $context_id === 'homepage') && function_exists('lf_get_homepage_section_config')) {
		$config = lf_get_homepage_section_config();
		if (is_array($config[ $section_id ] ?? null)) {
			$row = $config[ $section_id ];
			$row_type = (string) ($row['section_type'] ?? $row['type'] ?? '');
			if ($row_type !== '') {
				return sanitize_text_field($row_type);
			}
		}
		if (function_exists('lf_homepage_base_section_type')) {
			return lf_homepage_base_section_type($section_id);
		}
	}
	if (is_numeric($context_id) && defined('LF_PB_META_KEY') && function_exists('lf_pb_get_post_config') && function_exists('lf_ai_pb_context_for_post')) {
		$post = get_post((int) $context_id);
		if ($post instanceof \WP_Post) {
			$pb_context = (string) lf_ai_pb_context_for_post($post);
			if ($pb_context !== '') {
				$config = lf_pb_get_post_config((int) $context_id, $pb_context);
				$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
				$row = is_array($sections[ $section_id ] ?? null) ? $sections[ $section_id ] : [];
				$t = sanitize_text_field((string) ($row['type'] ?? ''));
				if ($t !== '') {
					return $t;
				}
			}
		}
	}
	return $section_id;
}

function lf_ai_assistant_section_allowed_keys(string $context_type, $context_id, string $selected_section_id, string $selected_section_type): array {
	$section_id = sanitize_text_field($selected_section_id);
	$section_type = sanitize_text_field($selected_section_type);
	if ($section_id === '' && $section_type === '') {
		return [];
	}
	$resolved_type = lf_ai_resolve_registry_section_type($context_type, $context_id, $section_id, $section_type);
	if ($resolved_type === '' || !function_exists('lf_sections_registry')) {
		return [];
	}
	$registry = lf_sections_registry();
	$section_def = is_array($registry[ $resolved_type ] ?? null) ? $registry[ $resolved_type ] : [];
	$fields = is_array($section_def['fields'] ?? null) ? $section_def['fields'] : [];
	$keys = [];
	foreach ($fields as $field) {
		if (!is_array($field)) {
			continue;
		}
		$key = sanitize_text_field((string) ($field['key'] ?? ''));
		if ($key !== '') {
			$keys[] = $key;
		}
	}
	return array_values(array_unique($keys));
}

function lf_ai_assistant_parse_secondary_keywords($value): array {
	if (is_array($value)) {
		$list = $value;
	} else {
		$list = preg_split('/[\n,]+/', (string) $value) ?: [];
	}
	$list = array_values(array_filter(array_map(static function ($item): string {
		return sanitize_text_field((string) $item);
	}, $list)));
	$list = array_values(array_unique($list));
	return array_slice($list, 0, 8);
}

function lf_ai_assistant_build_creation_prompt(string $mode, string $post_type, string $context_type, string $prompt): string {
	$base = "You are a WordPress content builder for a local business site.\n";
	$base .= "Return ONLY one JSON object. No markdown. No explanation.\n";
	$base .= "Context type: {$context_type}. Target post_type: {$post_type}.\n";
	$base .= "Create HIGH quality, concrete local-business copy. Avoid generic placeholders.\n";
	$schema = "JSON schema:\n";
	$schema .= "{\n";
	$schema .= "  \"title\": \"string (required)\",\n";
	$schema .= "  \"slug\": \"string optional\",\n";
	$schema .= "  \"excerpt\": \"string optional\",\n";
	$schema .= "  \"content\": \"string (required, >= 120 chars)\",\n";
	$schema .= "  \"primary_keyword\": \"string optional\",\n";
	$schema .= "  \"secondary_keywords\": [\"string\", \"...\"] optional,\n";
	$schema .= "  \"question\": \"string optional (FAQ only)\",\n";
	$schema .= "  \"answer\": \"string optional (FAQ only)\",\n";
	$schema .= "  \"city\": \"string optional (service area only)\",\n";
	$schema .= "  \"state\": \"string optional (service area only)\"\n";
	$schema .= "}\n";
	$rules = "Rules:\n";
	$rules .= "- status is always draft; do not include status.\n";
	$rules .= "- Keep title under 70 chars.\n";
	$rules .= "- Provide strong content body, not bullet fragments.\n";
	$rules .= "- Do not include HTML wrappers like <html> or markdown fences.\n";
	$rules .= "- If mode is create_blog_post, write as a full blog draft.\n";
	$rules .= "- If mode is create_cpt and post_type is lf_faq, include question and answer.\n";
	$rules .= "- If mode is create_cpt and post_type is lf_service_area, include city and state when possible.\n";
	return $base . $schema . $rules . "\nUser request:\n" . $prompt;
}

function lf_ai_assistant_build_batch_prompt(string $batch_type, int $count, string $context_type, string $prompt): string {
	$count = max(1, min(20, $count));
	$mapping = lf_ai_assistant_mode_and_cpt_for_batch_type($batch_type);
	$mode = (string) ($mapping['mode'] ?? '');
	$cpt = (string) ($mapping['cpt'] ?? '');
	$post_type = lf_ai_assistant_creation_post_type($mode, $cpt);
	$base = "You are a WordPress content builder for a local business site.\n";
	$base .= "Return ONLY one JSON object. No markdown. No explanation.\n";
	$base .= "Context type: {$context_type}. Batch post_type: {$post_type}. Requested count: {$count}.\n";
	$base .= "Output schema:\n";
	$base .= "{ \"items\": [ { \"title\": \"string\", \"slug\": \"string optional\", \"excerpt\": \"string optional\", \"content\": \"string\", \"primary_keyword\": \"string optional\", \"secondary_keywords\": [\"string\"], \"question\": \"string optional\", \"answer\": \"string optional\", \"city\": \"string optional\", \"state\": \"string optional\" } ] }\n";
	$base .= "Rules:\n";
	$base .= "- Return exactly {$count} useful items.\n";
	$base .= "- Avoid duplicates in titles and slugs.\n";
	$base .= "- Each item must be substantial and unique.\n";
	$base .= "- Status is always draft, do not include status field.\n";
	return $base . "\nUser request:\n" . $prompt;
}

function lf_ai_assistant_validate_creation_payload(array $decoded, string $mode, string $cpt_type): array {
	$post_type = lf_ai_assistant_creation_post_type($mode, $cpt_type);
	if ($post_type === '') {
		return [];
	}
	$title = sanitize_text_field((string) ($decoded['title'] ?? ''));
	$content = wp_kses_post((string) ($decoded['content'] ?? ''));
	$content = trim($content);
	if ($title === '' || $content === '' || strlen(wp_strip_all_tags($content)) < 40) {
		return [];
	}
	$payload = [
		'post_type' => $post_type,
		'title' => $title,
		'slug' => sanitize_title((string) ($decoded['slug'] ?? '')),
		'excerpt' => sanitize_textarea_field((string) ($decoded['excerpt'] ?? '')),
		'content' => $content,
		'primary_keyword' => sanitize_text_field((string) ($decoded['primary_keyword'] ?? '')),
		'secondary_keywords' => lf_ai_assistant_parse_secondary_keywords($decoded['secondary_keywords'] ?? []),
		'question' => sanitize_text_field((string) ($decoded['question'] ?? '')),
		'answer' => sanitize_textarea_field((string) ($decoded['answer'] ?? '')),
		'city' => sanitize_text_field((string) ($decoded['city'] ?? '')),
		'state' => sanitize_text_field((string) ($decoded['state'] ?? '')),
		'mode' => $mode,
	];
	if ($post_type === 'lf_faq') {
		if ($payload['question'] === '') {
			$payload['question'] = $title;
		}
		if ($payload['answer'] === '') {
			$payload['answer'] = wp_strip_all_tags($content);
		}
	}
	return $payload;
}

function lf_ai_assistant_creation_preview(array $payload): array {
	$pt_obj = get_post_type_object((string) ($payload['post_type'] ?? ''));
	$pt_label = $pt_obj ? (string) $pt_obj->labels->singular_name : (string) ($payload['post_type'] ?? '');
	$notes = [];
	if (!empty($payload['primary_keyword'])) {
		$notes[] = sprintf(__('Primary keyword: %s', 'leadsforward-core'), $payload['primary_keyword']);
	}
	if (!empty($payload['secondary_keywords']) && is_array($payload['secondary_keywords'])) {
		$notes[] = sprintf(__('Secondary keywords: %s', 'leadsforward-core'), implode(', ', $payload['secondary_keywords']));
	}
	if (($payload['post_type'] ?? '') === 'lf_service_area' && (!empty($payload['city']) || !empty($payload['state']))) {
		$notes[] = sprintf(__('Area: %s %s', 'leadsforward-core'), (string) ($payload['city'] ?? ''), (string) ($payload['state'] ?? ''));
	}
	return [
		'title' => (string) ($payload['title'] ?? ''),
		'type' => $pt_label,
		'status' => 'draft',
		'notes' => $notes,
	];
}

function lf_ai_assistant_batch_preview(array $payloads): array {
	$items = [];
	foreach ($payloads as $payload) {
		if (!is_array($payload)) {
			continue;
		}
		$items[] = lf_ai_assistant_creation_preview($payload);
	}
	return $items;
}

function lf_ai_assistant_create_post_from_payload(array $payload): array {
	$postarr = [
		'post_type' => (string) ($payload['post_type'] ?? ''),
		'post_status' => 'draft',
		'post_title' => (string) ($payload['title'] ?? ''),
		'post_content' => (string) ($payload['content'] ?? ''),
		'post_excerpt' => (string) ($payload['excerpt'] ?? ''),
	];
	$slug = (string) ($payload['slug'] ?? '');
	if ($slug !== '') {
		$postarr['post_name'] = $slug;
	}
	$post_id = wp_insert_post($postarr, true);
	if (is_wp_error($post_id)) {
		return ['success' => false, 'message' => $post_id->get_error_message()];
	}
	$post_id = (int) $post_id;
	$primary_keyword = (string) ($payload['primary_keyword'] ?? '');
	$secondary_keywords = (array) ($payload['secondary_keywords'] ?? []);
	if ($primary_keyword !== '') {
		update_post_meta($post_id, '_lf_seo_primary_keyword', $primary_keyword);
	}
	if (!empty($secondary_keywords)) {
		update_post_meta($post_id, '_lf_seo_secondary_keywords', implode(', ', $secondary_keywords));
	}
	$post_type = (string) ($payload['post_type'] ?? '');
	if ($post_type === 'lf_faq') {
		if (function_exists('update_field')) {
			update_field('lf_faq_question', (string) ($payload['question'] ?? ''), $post_id);
			update_field('lf_faq_answer', (string) ($payload['answer'] ?? ''), $post_id);
		}
	}
	if ($post_type === 'lf_service_area') {
		if (function_exists('update_field') && !empty($payload['state'])) {
			update_field('lf_service_area_state', (string) $payload['state'], $post_id);
		}
	}
	if (function_exists('lf_seo_maybe_populate_generated_meta')) {
		lf_seo_maybe_populate_generated_meta($post_id);
	}
	if (function_exists('lf_seo_calculate_content_quality')) {
		lf_seo_calculate_content_quality($post_id);
	}
	return [
		'success' => true,
		'post_id' => $post_id,
		'edit_link' => get_edit_post_link($post_id, ''),
		'view_link' => get_permalink($post_id),
	];
}

add_action('add_meta_boxes', 'lf_ai_editing_meta_box');
add_action('admin_enqueue_scripts', 'lf_ai_editing_scripts');
add_action('wp_ajax_lf_ai_generate', 'lf_ai_ajax_generate');
add_action('wp_ajax_lf_ai_apply', 'lf_ai_ajax_apply');
add_action('wp_ajax_lf_ai_rollback', 'lf_ai_ajax_rollback');
add_action('wp_ajax_lf_ai_rollback_latest', 'lf_ai_ajax_rollback_latest');
add_action('wp_ajax_lf_ai_redo_latest', 'lf_ai_ajax_redo_latest');
add_action('wp_ajax_lf_ai_extract_context_doc', 'lf_ai_ajax_extract_context_doc');
add_action('wp_ajax_lf_ai_inline_save', 'lf_ai_ajax_inline_save');
add_action('wp_ajax_lf_ai_inline_rewrite', 'lf_ai_ajax_inline_rewrite');
add_action('wp_ajax_lf_ai_inline_image_save', 'lf_ai_ajax_inline_image_save');
add_action('wp_ajax_lf_ai_seo_snapshot', 'lf_ai_ajax_seo_snapshot');
add_action('wp_ajax_lf_ai_reorder_sections', 'lf_ai_ajax_reorder_sections');
add_action('wp_ajax_lf_ai_toggle_section_columns', 'lf_ai_ajax_toggle_section_columns');
add_action('wp_ajax_lf_ai_update_section_checklist', 'lf_ai_ajax_update_section_checklist');
add_action('wp_ajax_lf_ai_update_hero_pills', 'lf_ai_ajax_update_hero_pills');
add_action('wp_ajax_lf_ai_update_hero_trust_strip', 'lf_ai_ajax_update_hero_trust_strip');
add_action('wp_ajax_lf_ai_update_section_cta', 'lf_ai_ajax_update_section_cta');
add_action('wp_ajax_lf_ai_update_section_media', 'lf_ai_ajax_update_section_media');
add_action('wp_ajax_lf_ai_update_section_lines', 'lf_ai_ajax_update_section_lines');
add_action('wp_ajax_lf_ai_faq_library', 'lf_ai_ajax_faq_library');
add_action('wp_ajax_lf_ai_reorder_faq_items', 'lf_ai_ajax_reorder_faq_items');
add_action('wp_ajax_lf_ai_set_trust_layout', 'lf_ai_ajax_set_trust_layout');
add_action('wp_ajax_lf_ai_toggle_section_visibility', 'lf_ai_ajax_toggle_section_visibility');
add_action('wp_ajax_lf_ai_delete_section', 'lf_ai_ajax_delete_section');
add_action('wp_ajax_lf_ai_duplicate_section', 'lf_ai_ajax_duplicate_section');
add_action('wp_ajax_lf_ai_add_section', 'lf_ai_ajax_add_section');
add_action('wp_ajax_lf_ai_update_section_style', 'lf_ai_ajax_update_section_style');
add_action('wp_ajax_lf_ai_update_hero_settings', 'lf_ai_ajax_update_hero_settings');

/**
 * @return list<string>
 */
function lf_ai_section_style_background_slugs(): array {
	return function_exists('lf_sections_bg_options') ? array_keys(lf_sections_bg_options()) : ['light', 'soft', 'dark', 'white', 'card'];
}

/**
 * @param list<string> $allowed
 */
function lf_ai_section_style_cycle_value(string $current, array $allowed): string {
	if ($allowed === []) {
		return $current;
	}
	$current = in_array($current, $allowed, true) ? $current : $allowed[0];
	$idx = array_search($current, $allowed, true);
	$idx = $idx === false ? 0 : (int) $idx;
	$next = $allowed[($idx + 1) % count($allowed)];
	return $next;
}

/**
 * Apply a section style patch from the front-end editor (background presets, custom color, header align).
 *
 * @param array<string, mixed> $settings
 * @param array{background_slug?:string,custom_background?:string,header_align?:string} $extra
 * @return array{0: array<string, mixed>, 1: string} Updated settings and empty string, or original settings and error message.
 */
function lf_ai_mutate_section_style_settings(array $settings, string $patch, array $extra): array {
	$bg_slugs = lf_ai_section_style_background_slugs();
	$aligns = ['left', 'center', 'right'];
	$slug = sanitize_key((string) ($extra['background_slug'] ?? ''));
	$custom_raw = (string) ($extra['custom_background'] ?? '');
	$h_align = sanitize_key((string) ($extra['header_align'] ?? ''));

	if ($patch === 'cycle_background') {
		$cur = (string) ($settings['section_background'] ?? 'light');
		$settings['section_background'] = lf_ai_section_style_cycle_value($cur, $bg_slugs);
		return [$settings, ''];
	}
	if ($patch === 'set_preset_bg') {
		if (!in_array($slug, $bg_slugs, true)) {
			return [$settings, __('That background preset is not allowed.', 'leadsforward-core')];
		}
		$settings['section_background'] = $slug;
		$settings['section_background_custom'] = '';
		return [$settings, ''];
	}
	if ($patch === 'set_custom_bg') {
		$clean = function_exists('lf_sections_sanitize_custom_background') ? lf_sections_sanitize_custom_background($custom_raw) : '';
		if ($clean === '') {
			return [$settings, __('Enter a valid hex (#rgb or #rrggbb) or rgb/rgba color.', 'leadsforward-core')];
		}
		$settings['section_background_custom'] = $clean;
		return [$settings, ''];
	}
	if ($patch === 'clear_custom_bg') {
		$settings['section_background_custom'] = '';
		return [$settings, ''];
	}
	if ($patch === 'set_header_align') {
		if (!in_array($h_align, $aligns, true)) {
			return [$settings, __('Choose left, center, or right alignment.', 'leadsforward-core')];
		}
		$settings['section_header_align'] = $h_align;
		return [$settings, ''];
	}
	if ($patch === 'cycle_header_align') {
		$cur = (string) ($settings['section_header_align'] ?? 'center');
		$cur = in_array($cur, $aligns, true) ? $cur : 'center';
		$settings['section_header_align'] = lf_ai_section_style_cycle_value($cur, $aligns);
		return [$settings, ''];
	}
	return [$settings, __('Unknown style action.', 'leadsforward-core')];
}

function lf_ai_ajax_update_section_style(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	$section_id = isset($_POST['section_id']) ? sanitize_text_field(wp_unslash($_POST['section_id'])) : '';
	$patch = isset($_POST['patch']) ? sanitize_text_field(wp_unslash($_POST['patch'])) : '';
	if ($context_type === '' || $context_id === '' || $section_id === '' || $patch === '') {
		wp_send_json_error(['message' => __('Invalid section style payload.', 'leadsforward-core')]);
	}
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	$style_extra = [
		'background_slug' => isset($_POST['background_slug']) ? sanitize_key(wp_unslash((string) $_POST['background_slug'])) : '',
		'custom_background' => isset($_POST['custom_background']) ? wp_unslash((string) $_POST['custom_background']) : '',
		'header_align' => isset($_POST['header_align']) ? sanitize_key(wp_unslash((string) $_POST['header_align'])) : '',
	];

	if ($context_type === 'homepage' || $context_id_use === 'homepage') {
		if (!defined('LF_HOMEPAGE_CONFIG_OPTION') || !function_exists('lf_get_homepage_section_config')) {
			wp_send_json_error(['message' => __('Homepage section settings are unavailable.', 'leadsforward-core')]);
		}
		$config = lf_get_homepage_section_config();
		if (!is_array($config)) {
			wp_send_json_error(['message' => __('Homepage configuration is missing.', 'leadsforward-core')]);
		}
		$resolved = function_exists('lf_ai_homepage_resolve_section_id')
			? lf_ai_homepage_resolve_section_id($section_id, '')
			: $section_id;
		if (!isset($config[$resolved]) || !is_array($config[$resolved])) {
			wp_send_json_error(['message' => __('That section was not found on the homepage.', 'leadsforward-core')]);
		}
		$old_row = $config[$resolved];
		$row_settings = is_array($config[$resolved]) ? $config[$resolved] : [];
		[$row_settings, $err] = lf_ai_mutate_section_style_settings($row_settings, $patch, $style_extra);
		if ($err !== '') {
			wp_send_json_error(['message' => $err]);
		}
		$config[$resolved] = $row_settings;
		update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
		$new_row = is_array($config[$resolved] ?? null) ? $config[$resolved] : [];
		$log_id = function_exists('lf_ai_log_action')
			? lf_ai_log_action(
				$context_type,
				$context_id_use,
				['__homepage_section_row::' . $resolved => $old_row],
				['__homepage_section_row::' . $resolved => $new_row],
				'Inline section style'
			)
			: '';
		wp_send_json_success([
			'message' => __('Section style updated.', 'leadsforward-core'),
			'reload' => true,
			'log_id' => $log_id,
			'section_id' => $resolved,
			'section_background' => (string) ($new_row['section_background'] ?? ''),
			'section_background_custom' => (string) ($new_row['section_background_custom'] ?? ''),
			'section_header_align' => (string) ($new_row['section_header_align'] ?? ''),
		]);
		return;
	}

	$pid = (int) $context_id_use;
	$post = get_post($pid);
	if (!$post instanceof \WP_Post || !defined('LF_PB_META_KEY') || !function_exists('lf_pb_get_post_config')) {
		wp_send_json_error(['message' => __('Section settings are unavailable for this page.', 'leadsforward-core')]);
	}
	$pb_context = function_exists('lf_ai_pb_context_for_post') ? lf_ai_pb_context_for_post($post) : '';
	if ($pb_context === '') {
		wp_send_json_error(['message' => __('This page does not support section styling here.', 'leadsforward-core')]);
	}
	$config = lf_pb_get_post_config($pid, $pb_context);
	$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	if (!isset($sections[$section_id]) || !is_array($sections[$section_id])) {
		wp_send_json_error(['message' => __('That section was not found.', 'leadsforward-core')]);
	}
	$row = $sections[$section_id];
	$settings = is_array($row['settings'] ?? null) ? $row['settings'] : [];
	$section_type = sanitize_text_field((string) ($row['type'] ?? ''));
	$old_settings = $settings;
	[$settings, $err_pb] = lf_ai_mutate_section_style_settings($settings, $patch, $style_extra);
	if ($err_pb !== '') {
		wp_send_json_error(['message' => $err_pb]);
	}
	$config['sections'][$section_id]['settings'] = $settings;
	update_post_meta($pid, LF_PB_META_KEY, $config);
	$log_id = function_exists('lf_ai_log_action')
		? lf_ai_log_action(
			$context_type,
			$context_id_use,
			['__pb_section_settings::' . $section_id => $old_settings],
			['__pb_section_settings::' . $section_id => $settings],
			'Inline section style'
		)
		: '';
	wp_send_json_success([
		'message' => __('Section style updated.', 'leadsforward-core'),
		'reload' => true,
		'log_id' => $log_id,
		'section_id' => $section_id,
		'section_background' => (string) ($settings['section_background'] ?? ''),
		'section_background_custom' => (string) ($settings['section_background_custom'] ?? ''),
		'section_header_align' => (string) ($settings['section_header_align'] ?? ''),
	]);
}

function lf_ai_ajax_update_hero_settings(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	$section_id = isset($_POST['section_id']) ? sanitize_text_field(wp_unslash($_POST['section_id'])) : '';
	$hero_variant_raw = isset($_POST['hero_variant']) ? sanitize_key(wp_unslash((string) $_POST['hero_variant'])) : '';
	$hero_background_mode = isset($_POST['hero_background_mode']) ? sanitize_key(wp_unslash((string) $_POST['hero_background_mode'])) : 'image';
	$hero_background_image_id = isset($_POST['hero_background_image_id']) ? (int) $_POST['hero_background_image_id'] : 0;
	$hero_background_video_id = isset($_POST['hero_background_video_id']) ? (int) $_POST['hero_background_video_id'] : 0;

	if ($context_type === '' || $context_id === '' || $section_id === '') {
		wp_send_json_error(['message' => __('Invalid hero settings payload.', 'leadsforward-core')]);
	}

	$variants = function_exists('lf_sections_hero_variant_options') ? lf_sections_hero_variant_options() : [];
	$variant_keys = array_keys($variants);
	if (!in_array($hero_variant_raw, $variant_keys, true)) {
		wp_send_json_error(['message' => __('That hero layout is not allowed.', 'leadsforward-core')]);
	}
	$modes = ['color', 'image', 'video'];
	if (!in_array($hero_background_mode, $modes, true)) {
		wp_send_json_error(['message' => __('That hero background mode is not allowed.', 'leadsforward-core')]);
	}
	$hero_background_image_id = max(0, $hero_background_image_id);
	$hero_background_video_id = max(0, $hero_background_video_id);
	if ($hero_background_image_id > 0 && !wp_attachment_is_image($hero_background_image_id)) {
		wp_send_json_error(['message' => __('Choose a valid image attachment for the hero background.', 'leadsforward-core')]);
	}
	if ($hero_background_video_id > 0) {
		$mime = get_post_mime_type($hero_background_video_id);
		if (!is_string($mime) || strpos($mime, 'video/') !== 0) {
			wp_send_json_error(['message' => __('Choose a valid video file for the hero background.', 'leadsforward-core')]);
		}
	}

	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;

	if ($context_type === 'homepage' || $context_id_use === 'homepage') {
		if (!defined('LF_HOMEPAGE_CONFIG_OPTION') || !function_exists('lf_get_homepage_section_config')) {
			wp_send_json_error(['message' => __('Homepage section settings are unavailable.', 'leadsforward-core')]);
		}
		$config = lf_get_homepage_section_config();
		if (!is_array($config)) {
			wp_send_json_error(['message' => __('Homepage configuration is missing.', 'leadsforward-core')]);
		}
		$resolved = function_exists('lf_ai_homepage_resolve_section_id')
			? lf_ai_homepage_resolve_section_id($section_id, 'hero')
			: $section_id;
		if (!isset($config[$resolved]) || !is_array($config[$resolved])) {
			wp_send_json_error(['message' => __('That hero section was not found.', 'leadsforward-core')]);
		}
		$base = function_exists('lf_homepage_base_section_type') ? lf_homepage_base_section_type($resolved) : '';
		if ($base !== 'hero') {
			wp_send_json_error(['message' => __('That section is not the homepage hero.', 'leadsforward-core')]);
		}
		$old_row = $config[$resolved];
		$config[$resolved]['variant'] = $hero_variant_raw;
		$config[$resolved]['hero_background_mode'] = $hero_background_mode;
		$config[$resolved]['hero_background_image_id'] = $hero_background_image_id;
		$config[$resolved]['hero_background_video_id'] = $hero_background_video_id;
		update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
		$new_row = $config[$resolved];
		$log_id = function_exists('lf_ai_log_action')
			? lf_ai_log_action(
				$context_type,
				$context_id_use,
				['__homepage_section_row::' . $resolved => $old_row],
				['__homepage_section_row::' . $resolved => $new_row],
				'Hero layout & background'
			)
			: '';
		wp_send_json_success([
			'message' => __('Hero settings updated.', 'leadsforward-core'),
			'reload' => true,
			'log_id' => $log_id,
			'section_id' => $resolved,
			'hero_variant' => $hero_variant_raw,
			'hero_background_mode' => $hero_background_mode,
			'hero_background_image_id' => $hero_background_image_id,
			'hero_background_video_id' => $hero_background_video_id,
		]);
		return;
	}

	$pid = (int) $context_id_use;
	$post = get_post($pid);
	if (!$post instanceof \WP_Post || !defined('LF_PB_META_KEY') || !function_exists('lf_pb_get_post_config')) {
		wp_send_json_error(['message' => __('Section settings are unavailable for this page.', 'leadsforward-core')]);
	}
	$pb_context = function_exists('lf_ai_pb_context_for_post') ? lf_ai_pb_context_for_post($post) : '';
	if ($pb_context === '') {
		wp_send_json_error(['message' => __('This page does not support hero settings here.', 'leadsforward-core')]);
	}
	$config = lf_pb_get_post_config($pid, $pb_context);
	$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	if (!isset($sections[$section_id]) || !is_array($sections[$section_id])) {
		wp_send_json_error(['message' => __('That section was not found.', 'leadsforward-core')]);
	}
	$row = $sections[$section_id];
	$section_type = sanitize_text_field((string) ($row['type'] ?? ''));
	if ($section_type !== 'hero') {
		wp_send_json_error(['message' => __('That section is not a hero block.', 'leadsforward-core')]);
	}
	$settings = is_array($row['settings'] ?? null) ? $row['settings'] : [];
	$old_settings = $settings;
	$settings['variant'] = $hero_variant_raw;
	$settings['hero_background_mode'] = $hero_background_mode;
	$settings['hero_background_image_id'] = $hero_background_image_id;
	$settings['hero_background_video_id'] = $hero_background_video_id;
	$config['sections'][$section_id]['settings'] = $settings;
	update_post_meta($pid, LF_PB_META_KEY, $config);
	$log_id = function_exists('lf_ai_log_action')
		? lf_ai_log_action(
			$context_type,
			$context_id_use,
			['__pb_section_settings::' . $section_id => $old_settings],
			['__pb_section_settings::' . $section_id => $settings],
			'Hero layout & background'
		)
		: '';
	wp_send_json_success([
		'message' => __('Hero settings updated.', 'leadsforward-core'),
		'reload' => true,
		'log_id' => $log_id,
		'section_id' => $section_id,
		'hero_variant' => $hero_variant_raw,
		'hero_background_mode' => $hero_background_mode,
		'hero_background_image_id' => $hero_background_image_id,
		'hero_background_video_id' => $hero_background_video_id,
	]);
}

function lf_ai_editing_meta_box(): void {
	if (!current_user_can(LF_AI_CAP)) {
		return;
	}
	$screen = get_current_screen();
	if (!$screen || $screen->base !== 'post') {
		return;
	}
	$post = get_post();
	if (!$post) {
		return;
	}
	$context_type = lf_ai_editing_context_type($post);
	$context_id   = lf_ai_editing_context_id($post);
	$editable     = lf_get_ai_editable_fields($context_id);
	if (empty($editable)) {
		return;
	}
	add_meta_box(
		'lf_ai_editing',
		__('Edit with AI', 'leadsforward-core'),
		'lf_ai_editing_meta_box_callback',
		$screen->post_type,
		'side',
		'default',
		['context_type' => $context_type, 'context_id' => $context_id, 'editable' => $editable]
	);
}

function lf_ai_editing_context_type(\WP_Post $post): string {
	$front_id = (int) get_option('page_on_front');
	if ($post->post_type === 'page' && (int) $post->ID === $front_id) {
		return 'homepage';
	}
	return $post->post_type;
}

function lf_ai_editing_context_id(\WP_Post $post): string|int {
	$front_id = (int) get_option('page_on_front');
	if ($post->post_type === 'page' && (int) $post->ID === $front_id) {
		return 'homepage';
	}
	return $post->ID;
}

function lf_ai_editing_meta_box_callback(\WP_Post $post, array $box): void {
	$context_type = $box['args']['context_type'] ?? '';
	$context_id   = $box['args']['context_id'] ?? '';
	$editable     = $box['args']['editable'] ?? [];
	$labels       = $editable;
	?>
	<div class="lf-ai-editing" data-context-type="<?php echo esc_attr($context_type); ?>" data-context-id="<?php echo esc_attr((string) $context_id); ?>">
		<p class="lf-ai-description"><?php esc_html_e('Suggest edits using plain English. Only conversion copy and allowed fields will be changed. URLs, slugs, and schema are never modified.', 'leadsforward-core'); ?></p>
		<label for="lf-ai-prompt" class="screen-reader-text"><?php esc_html_e('Edit prompt', 'leadsforward-core'); ?></label>
		<textarea id="lf-ai-prompt" class="widefat" rows="3" placeholder="<?php esc_attr_e('e.g. Make this more urgent for emergency roofing customers', 'leadsforward-core'); ?>"></textarea>
		<p>
			<button type="button" class="button button-primary" id="lf-ai-submit"><?php esc_html_e('Generate suggestions', 'leadsforward-core'); ?></button>
		</p>
		<div id="lf-ai-status" class="lf-ai-status" aria-live="polite"></div>
		<div id="lf-ai-diff" class="lf-ai-diff" style="display:none;">
			<h4><?php esc_html_e('Review suggestions', 'leadsforward-core'); ?></h4>
			<table class="widefat striped" id="lf-ai-diff-table"></table>
			<p>
				<button type="button" class="button button-primary" id="lf-ai-apply"><?php esc_html_e('Apply', 'leadsforward-core'); ?></button>
				<button type="button" class="button" id="lf-ai-reject"><?php esc_html_e('Reject', 'leadsforward-core'); ?></button>
			</p>
		</div>
		<?php
		$log = lf_ai_get_log();
		$relevant = array_filter($log, function ($e) use ($context_type, $context_id) {
			return ($e['context_type'] ?? '') === $context_type && (string) ($e['context_id'] ?? '') === (string) $context_id;
		});
		$relevant = array_slice($relevant, 0, 5);
		if (!empty($relevant)) {
			?>
			<div class="lf-ai-log" style="margin-top:1em;">
				<h4><?php esc_html_e('Recent AI edits', 'leadsforward-core'); ?></h4>
				<ul class="lf-ai-log-list">
					<?php foreach ($relevant as $entry) {
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
			<?php
		}
		?>
	</div>
	<?php
}

function lf_ai_editing_scripts(string $hook): void {
	if (!current_user_can(LF_AI_CAP)) {
		return;
	}
	if ($hook !== 'post.php' && $hook !== 'post-new.php') {
		return;
	}
	$post = get_post();
	if (!$post) {
		return;
	}
	$context_id = $post->post_type === 'page' && (int) $post->ID === (int) get_option('page_on_front') ? 'homepage' : $post->ID;
	$editable = lf_get_ai_editable_fields($context_id);
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
		.lf-ai-diff pre { white-space: pre-wrap; word-wrap: break-word; margin: 0; font-size: 12px; max-height: 120px; overflow: auto; }
		.lf-ai-status.error { color: #b32d2e; }
	');
}

function lf_ai_ajax_generate(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id   = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	$prompt       = isset($_POST['prompt']) ? sanitize_textarea_field(wp_unslash($_POST['prompt'])) : '';
	$assistant_mode = isset($_POST['assistant_mode']) ? sanitize_text_field(wp_unslash($_POST['assistant_mode'])) : 'edit_existing';
	$assistant_cpt_type = isset($_POST['assistant_cpt_type']) ? sanitize_text_field(wp_unslash($_POST['assistant_cpt_type'])) : '';
	$assistant_batch_type = isset($_POST['assistant_batch_type']) ? sanitize_text_field(wp_unslash($_POST['assistant_batch_type'])) : 'post';
	$assistant_batch_count = isset($_POST['assistant_batch_count']) ? (int) $_POST['assistant_batch_count'] : 5;
	$target_reference = isset($_POST['target_reference']) ? sanitize_text_field(wp_unslash($_POST['target_reference'])) : '';
	$selected_section_id = isset($_POST['selected_section_id']) ? sanitize_text_field(wp_unslash($_POST['selected_section_id'])) : '';
	$selected_section_type = isset($_POST['selected_section_type']) ? sanitize_text_field(wp_unslash($_POST['selected_section_type'])) : '';
	if (!in_array($assistant_mode, lf_ai_assistant_modes(), true)) {
		$assistant_mode = 'edit_existing';
	}
	$document_context = isset($_POST['document_context']) ? sanitize_textarea_field(wp_unslash($_POST['document_context'])) : '';
	$document_name = isset($_POST['document_name']) ? sanitize_text_field(wp_unslash($_POST['document_name'])) : '';
	if ($prompt === '' || $context_type === '') {
		wp_send_json_error(['message' => __('Invalid request.', 'leadsforward-core')]);
	}
	if ($document_context !== '') {
		if (strlen($document_context) > 12000) {
			$document_context = substr($document_context, 0, 12000);
		}
		$doc_heading = $document_name !== '' ? $document_name : __('Uploaded document', 'leadsforward-core');
		$prompt .= "\n\nDocument context (" . $doc_heading . "):\n" . $document_context;
	}
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	$context_type_use = $context_type;
	$auto_inference = ['mode' => 'edit_existing', 'cpt_type' => '', 'batch_type' => 'post', 'batch_count' => $assistant_batch_count];
	if ($assistant_mode === 'auto') {
		$auto_inference = lf_ai_assistant_infer_mode_from_prompt($prompt);
		$assistant_mode = (string) ($auto_inference['mode'] ?? 'edit_existing');
		$assistant_cpt_type = (string) ($auto_inference['cpt_type'] ?? '');
		$assistant_batch_type = (string) ($auto_inference['batch_type'] ?? 'post');
		$assistant_batch_count = (int) ($auto_inference['batch_count'] ?? $assistant_batch_count);
	}
	if ($assistant_mode === 'edit_existing') {
		if ($target_reference === '') {
			$target_reference = lf_ai_assistant_extract_reference_from_prompt($prompt);
		}
		$resolved = lf_ai_assistant_resolve_target_context($target_reference, $context_type_use, $context_id_use);
		$context_type_use = (string) ($resolved['type'] ?? $context_type_use);
		$context_id_use = $resolved['id'] ?? $context_id_use;
	}
	$qa_answer = lf_ai_assistant_try_answer_question($prompt, $context_type_use, $context_id_use);
	if (is_array($qa_answer) && !empty($qa_answer['answer'])) {
		wp_send_json_success([
			'mode' => 'qa',
			'assistant_cpt_type' => $assistant_cpt_type,
			'assistant_batch_type' => $assistant_batch_type,
			'assistant_batch_count' => $assistant_batch_count,
			'context_type' => $context_type_use,
			'context_id' => (string) $context_id_use,
			'target_label' => lf_ai_assistant_target_label_for_context($context_type_use, $context_id_use),
			'answer' => (string) $qa_answer['answer'],
			'snapshot' => is_array($qa_answer['snapshot'] ?? null) ? $qa_answer['snapshot'] : [],
		]);
	}
	if ($assistant_mode === 'edit_existing') {
		$homepage_section_row_id = '';
		$registry_section_type_for_labels = '';
		$scoped_editable = null;
		if ($context_type_use === 'homepage' && $selected_section_id !== '') {
			$homepage_section_row_id = $selected_section_id;
			$registry_section_type_for_labels = lf_ai_resolve_registry_section_type($context_type_use, $context_id_use, $selected_section_id, $selected_section_type);
			$section_keys_all = lf_ai_assistant_section_allowed_keys($context_type_use, $context_id_use, $selected_section_id, $selected_section_type);
			$copy_keys = [];
			foreach ($section_keys_all as $sk) {
				if (lf_is_field_ai_editable($sk)) {
					$copy_keys[] = $sk;
				}
			}
			if (empty($copy_keys)) {
				wp_send_json_error(['message' => __('No editable fields were found for the selected section target.', 'leadsforward-core')]);
			}
			$scoped_editable = lf_ai_editable_labels_for_registry_keys($registry_section_type_for_labels, $copy_keys);
			foreach ($copy_keys as $ck) {
				if (!isset($scoped_editable[ $ck ])) {
					$scoped_editable[ $ck ] = $ck;
				}
			}
		}
		$result = lf_ai_generate_proposal($context_type_use, $context_id_use, $prompt, $homepage_section_row_id, $scoped_editable);
		if (!$result['success']) {
			wp_send_json_error(['message' => $result['error']]);
		}
		$requested_keys = lf_ai_assistant_requested_edit_keys($prompt, $context_id_use);
		if (!empty($requested_keys)) {
			$result['proposed'] = array_intersect_key($result['proposed'], array_flip($requested_keys));
			if (empty($result['proposed'])) {
				wp_send_json_error(['message' => __('No matching editable field found for that exact request on this target page.', 'leadsforward-core')]);
			}
		}
		$section_keys = lf_ai_assistant_section_allowed_keys($context_type_use, $context_id_use, $selected_section_id, $selected_section_type);
		if (!empty($section_keys)) {
			$result['proposed'] = array_intersect_key($result['proposed'], array_flip($section_keys));
			if (empty($result['proposed'])) {
				wp_send_json_error(['message' => __('No editable fields were found for the selected section target.', 'leadsforward-core')]);
			}
		}
		$current = lf_ai_get_current_values($context_type_use, $context_id_use, array_keys($result['proposed']), $homepage_section_row_id);
		$labels = array_intersect_key(lf_get_ai_editable_fields($context_id_use), $result['proposed']);
		$missing = array_diff_key($result['proposed'], $labels);
		if (!empty($missing)) {
			$rt = $registry_section_type_for_labels !== '' ? $registry_section_type_for_labels : lf_ai_resolve_registry_section_type($context_type_use, $context_id_use, $selected_section_id, $selected_section_type);
			if ($rt !== '') {
				$labels = array_merge($labels, lf_ai_editable_labels_for_registry_keys($rt, array_keys($missing)));
			}
			foreach (array_keys($missing) as $mk) {
				if (!isset($labels[ $mk ])) {
					$labels[ $mk ] = $mk;
				}
			}
		}
		wp_send_json_success([
			'mode' => $assistant_mode,
			'assistant_cpt_type' => $assistant_cpt_type,
			'assistant_batch_type' => $assistant_batch_type,
			'assistant_batch_count' => $assistant_batch_count,
			'context_type' => $context_type_use,
			'context_id' => (string) $context_id_use,
			'target_label' => lf_ai_assistant_target_label_for_context($context_type_use, $context_id_use),
			'proposed' => $result['proposed'],
			'current'  => $current,
			'labels'   => $labels,
			'homepage_section_row_id' => ($context_type_use === 'homepage' && $homepage_section_row_id !== '') ? $homepage_section_row_id : '',
		]);
	}
	if ($assistant_mode === 'create_batch') {
		if (!in_array($assistant_batch_type, lf_ai_assistant_batch_types(), true)) {
			wp_send_json_error(['message' => __('Invalid batch type.', 'leadsforward-core')]);
		}
		$assistant_batch_count = max(1, min(20, $assistant_batch_count));
		$mapping = lf_ai_assistant_mode_and_cpt_for_batch_type($assistant_batch_type);
		$batch_mode = (string) ($mapping['mode'] ?? '');
		$batch_cpt = (string) ($mapping['cpt'] ?? '');
		$post_type = lf_ai_assistant_creation_post_type($batch_mode, $batch_cpt);
		if ($post_type === '') {
			wp_send_json_error(['message' => __('Unsupported batch configuration.', 'leadsforward-core')]);
		}
		$system = lf_ai_assistant_build_batch_prompt($assistant_batch_type, $assistant_batch_count, $context_type, $prompt);
		$response = apply_filters('lf_ai_completion', '', $system, $prompt, $context_type_use, $context_id_use);
		if (is_wp_error($response)) {
			wp_send_json_error(['message' => $response->get_error_message()]);
		}
		if (!is_string($response) || trim($response) === '') {
			wp_send_json_error(['message' => __('AI response was empty.', 'leadsforward-core')]);
		}
		$decoded = json_decode(trim($response), true);
		$items = is_array($decoded['items'] ?? null) ? $decoded['items'] : [];
		if (empty($items)) {
			wp_send_json_error(['message' => __('AI returned no batch items.', 'leadsforward-core')]);
		}
		$payloads = [];
		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}
			$payload = lf_ai_assistant_validate_creation_payload($item, $batch_mode, $batch_cpt);
			if (!empty($payload)) {
				$payloads[] = $payload;
			}
			if (count($payloads) >= $assistant_batch_count) {
				break;
			}
		}
		if (empty($payloads)) {
			wp_send_json_error(['message' => __('Could not validate batch payloads. Try a more specific prompt.', 'leadsforward-core')]);
		}
		wp_send_json_success([
			'mode' => $assistant_mode,
			'assistant_cpt_type' => $assistant_cpt_type,
			'assistant_batch_type' => $assistant_batch_type,
			'assistant_batch_count' => $assistant_batch_count,
			'context_type' => $context_type_use,
			'context_id' => (string) $context_id_use,
			'target_label' => lf_ai_assistant_target_label_for_context($context_type_use, $context_id_use),
			'creation_payload' => ['items' => $payloads, 'batch_type' => $assistant_batch_type],
			'creation_queue' => lf_ai_assistant_batch_preview($payloads),
		]);
	}
	$post_type = lf_ai_assistant_creation_post_type($assistant_mode, $assistant_cpt_type);
	if ($post_type === '') {
		wp_send_json_error(['message' => __('Invalid create mode.', 'leadsforward-core')]);
	}
	$system = lf_ai_assistant_build_creation_prompt($assistant_mode, $post_type, $context_type, $prompt);
	$response = apply_filters('lf_ai_completion', '', $system, $prompt, $context_type_use, $context_id_use);
	if (is_wp_error($response)) {
		wp_send_json_error(['message' => $response->get_error_message()]);
	}
	if (!is_string($response) || trim($response) === '') {
		wp_send_json_error(['message' => __('AI response was empty.', 'leadsforward-core')]);
	}
	$decoded = json_decode(trim($response), true);
	if (!is_array($decoded)) {
		wp_send_json_error(['message' => __('AI response was invalid JSON.', 'leadsforward-core')]);
	}
	$payload = lf_ai_assistant_validate_creation_payload($decoded, $assistant_mode, $assistant_cpt_type);
	if (empty($payload)) {
		wp_send_json_error(['message' => __('Could not validate creation payload. Try a more specific prompt.', 'leadsforward-core')]);
	}
	wp_send_json_success([
		'mode' => $assistant_mode,
		'assistant_cpt_type' => $assistant_cpt_type,
		'assistant_batch_type' => $assistant_batch_type,
		'assistant_batch_count' => $assistant_batch_count,
		'context_type' => $context_type_use,
		'context_id' => (string) $context_id_use,
		'target_label' => lf_ai_assistant_target_label_for_context($context_type_use, $context_id_use),
		'creation_payload' => $payload,
		'creation_preview' => lf_ai_assistant_creation_preview($payload),
	]);
}

function lf_ai_assistant_is_question_prompt(string $prompt): bool {
	$p = strtolower(trim($prompt));
	if ($p === '') return false;
	if (strpos($p, '?') !== false) return true;
	foreach (['what ', 'how ', 'why ', 'where ', 'which ', 'who ', 'does ', 'is ', 'are ', 'can ', 'could ', 'should '] as $start) {
		if (strpos($p, $start) === 0) return true;
	}
	return false;
}

function lf_ai_assistant_try_answer_question(string $prompt, string $context_type, $context_id): ?array {
	if (!lf_ai_assistant_is_question_prompt($prompt)) {
		return null;
	}
	$post_id = lf_ai_resolve_post_id_for_context($context_type, $context_id);
	if ($post_id <= 0) {
		return [
			'answer' => __('I could not resolve this page context, so I cannot answer reliably yet.', 'leadsforward-core'),
			'snapshot' => [],
		];
	}
	$snapshot = lf_ai_collect_seo_snapshot_payload($post_id);
	$meta = is_array($snapshot['meta'] ?? null) ? $snapshot['meta'] : [];
	$checks = is_array($snapshot['checks'] ?? null) ? $snapshot['checks'] : [];
	$prompt_l = strtolower(trim($prompt));

	if (strpos($prompt_l, 'primary keyword') !== false || strpos($prompt_l, 'target keyword') !== false) {
		$pk = trim((string) ($meta['primary_keyword'] ?? ''));
		$ans = $pk !== ''
			? sprintf(__('Primary target keyword: "%s".', 'leadsforward-core'), $pk)
			: __('No primary keyword is set yet in the SEO meta fields for this page.', 'leadsforward-core');
		return ['answer' => $ans, 'snapshot' => $snapshot];
	}
	if (strpos($prompt_l, 'secondary keyword') !== false) {
		$sk = trim((string) ($meta['secondary_keywords'] ?? ''));
		$ans = $sk !== ''
			? sprintf(__('Secondary keywords: %s.', 'leadsforward-core'), $sk)
			: __('No secondary keywords are set yet for this page.', 'leadsforward-core');
		return ['answer' => $ans, 'snapshot' => $snapshot];
	}
	if (strpos($prompt_l, 'intent') !== false) {
		$intent = trim((string) ($snapshot['intent'] ?? ''));
		$ans = $intent !== ''
			? sprintf(__('Detected search intent: %s.', 'leadsforward-core'), $intent)
			: __('Search intent is not clearly detected yet.', 'leadsforward-core');
		return ['answer' => $ans, 'snapshot' => $snapshot];
	}
	if (strpos($prompt_l, 'score') !== false || strpos($prompt_l, 'seo health') !== false) {
		$score = (int) ($snapshot['backend_seo_score'] ?? 0);
		$q = (int) ($snapshot['quality_score'] ?? 0);
		$grade = (string) ($snapshot['quality_grade'] ?? '');
		$ans = sprintf(__('SEO health score is %1$d/100. Backend content quality is %2$d (%3$s).', 'leadsforward-core'), $score, $q, $grade !== '' ? $grade : '-');
		return ['answer' => $ans, 'snapshot' => $snapshot];
	}
	if (strpos($prompt_l, 'missing') !== false || strpos($prompt_l, 'what should') !== false || strpos($prompt_l, 'what to do') !== false || strpos($prompt_l, 'improve') !== false) {
		$todo = [];
		foreach ($checks as $check) {
			if (!is_array($check)) continue;
			$status = (string) ($check['status'] ?? 'ok');
			if ($status === 'ok') continue;
			$action = trim((string) ($check['action'] ?? ''));
			$label = trim((string) ($check['label'] ?? ''));
			if ($action !== '') {
				$todo[] = $action;
			} elseif ($label !== '') {
				$todo[] = sprintf(__('Review: %s.', 'leadsforward-core'), $label);
			}
			if (count($todo) >= 3) break;
		}
		if (empty($todo)) {
			return ['answer' => __('No critical SEO gaps detected right now. Focus next on conversion copy testing and media performance polish.', 'leadsforward-core'), 'snapshot' => $snapshot];
		}
		return ['answer' => __('Top priorities: ', 'leadsforward-core') . implode(' ', $todo), 'snapshot' => $snapshot];
	}

	$score = (int) ($snapshot['backend_seo_score'] ?? 0);
	$intent = trim((string) ($snapshot['intent'] ?? ''));
	$pk = trim((string) ($meta['primary_keyword'] ?? ''));
	$fallback = sprintf(
		__('Page SEO summary: score %1$d/100, intent %2$s, primary keyword %3$s. Ask about keyword, intent, score, missing items, conversion, or performance and I will answer specifically from this page report.', 'leadsforward-core'),
		$score,
		$intent !== '' ? $intent : __('unknown', 'leadsforward-core'),
		$pk !== '' ? '"' . $pk . '"' : __('not set', 'leadsforward-core')
	);
	return ['answer' => $fallback, 'snapshot' => $snapshot];
}

function lf_ai_ajax_extract_context_doc(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	if (empty($_FILES['document']) || !is_array($_FILES['document'])) {
		wp_send_json_error(['message' => __('No document uploaded.', 'leadsforward-core')]);
	}
	$file = $_FILES['document'];
	$error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
	if ($error !== UPLOAD_ERR_OK) {
		wp_send_json_error(['message' => __('Upload failed. Please try again.', 'leadsforward-core')]);
	}
	$size = isset($file['size']) ? (int) $file['size'] : 0;
	if ($size <= 0 || $size > 5 * 1024 * 1024) {
		wp_send_json_error(['message' => __('Document must be between 1 byte and 5MB.', 'leadsforward-core')]);
	}
	$name = isset($file['name']) ? sanitize_file_name((string) $file['name']) : 'document';
	$ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
	$tmp = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
	if ($tmp === '' || !is_uploaded_file($tmp)) {
		wp_send_json_error(['message' => __('Invalid upload payload.', 'leadsforward-core')]);
	}
	$supported_text_ext = ['txt', 'md', 'csv', 'json', 'html', 'htm', 'rtf'];
	$context = '';
	if (in_array($ext, $supported_text_ext, true)) {
		$raw = (string) file_get_contents($tmp);
		$context = wp_strip_all_tags($raw);
	} elseif ($ext === 'docx') {
		if (!class_exists('ZipArchive')) {
			wp_send_json_error(['message' => __('DOCX import requires ZipArchive support on this server.', 'leadsforward-core')]);
		}
		$zip = new \ZipArchive();
		if ($zip->open($tmp) !== true) {
			wp_send_json_error(['message' => __('Could not read DOCX file.', 'leadsforward-core')]);
		}
		$xml = (string) $zip->getFromName('word/document.xml');
		$zip->close();
		if ($xml === '') {
			wp_send_json_error(['message' => __('DOCX file contained no readable text.', 'leadsforward-core')]);
		}
		$context = html_entity_decode(wp_strip_all_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
	} else {
		wp_send_json_error(['message' => __('Supported document types: txt, md, csv, json, html, rtf, docx.', 'leadsforward-core')]);
	}
	$context = preg_replace('/\s+/', ' ', trim((string) $context));
	if ($context === '') {
		wp_send_json_error(['message' => __('This document did not include readable text context.', 'leadsforward-core')]);
	}
	if (strlen($context) > 12000) {
		$context = substr($context, 0, 12000);
	}
	wp_send_json_success([
		'name' => $name,
		'context' => $context,
	]);
}

function lf_ai_ajax_inline_save(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	$field_key = isset($_POST['field_key']) ? sanitize_text_field(wp_unslash($_POST['field_key'])) : '';
	$selector = isset($_POST['selector']) ? sanitize_text_field(wp_unslash($_POST['selector'])) : '';
	$value_raw = isset($_POST['value']) ? (string) wp_unslash($_POST['value']) : '';
	$value = trim(sanitize_textarea_field($value_raw));
	if ($context_type === '' || $context_id === '') {
		wp_send_json_error(['message' => __('Invalid request payload.', 'leadsforward-core')]);
	}
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	if (strlen($value) > 4000) {
		wp_send_json_error(['message' => __('Text is too long for inline editing.', 'leadsforward-core')]);
	}

	// New site-wide inline text path: selector-based DOM text overrides.
	if ($selector !== '') {
		if (!function_exists('lf_ai_get_inline_dom_overrides') || !function_exists('lf_ai_set_inline_dom_overrides')) {
			wp_send_json_error(['message' => __('Inline override storage is unavailable.', 'leadsforward-core')]);
		}
		$selector = trim($selector);
		if ($selector === '' || strlen($selector) > 500) {
			wp_send_json_error(['message' => __('Invalid selector payload.', 'leadsforward-core')]);
		}
		$current_map = lf_ai_get_inline_dom_overrides($context_type, $context_id_use);
		$old_value = isset($current_map[$selector]) ? (string) $current_map[$selector] : '';
		$current_map[$selector] = $value;
		lf_ai_set_inline_dom_overrides($context_type, $context_id_use, $current_map);
		$log_id = function_exists('lf_ai_log_action')
			? lf_ai_log_action(
				$context_type,
				$context_id_use,
				['__dom_override::' . $selector => $old_value],
				['__dom_override::' . $selector => $value],
				'Inline frontend DOM edit'
			)
			: '';
		wp_send_json_success([
			'message' => __('Inline edit saved.', 'leadsforward-core'),
			'selector' => $selector,
			'value' => $value,
			'log_id' => $log_id,
		]);
	}

	// Backward-compatible field-key path for existing scoped inline edits.
	if ($field_key === '') {
		wp_send_json_error(['message' => __('Invalid request payload.', 'leadsforward-core')]);
	}
	$editable = lf_get_ai_editable_fields($context_id_use);
	if (!lf_is_field_ai_editable($field_key) || !isset($editable[$field_key])) {
		wp_send_json_error(['message' => __('That field is not editable in this context.', 'leadsforward-core')]);
	}
	$result = lf_ai_apply_proposal($context_type, $context_id_use, [$field_key => $value], 'Inline frontend edit');
	if (empty($result['success'])) {
		wp_send_json_error(['message' => __('Could not save inline edit.', 'leadsforward-core')]);
	}
	wp_send_json_success([
		'message' => __('Inline edit saved.', 'leadsforward-core'),
		'field_key' => $field_key,
		'value' => $value,
		'log_id' => (string) ($result['log_id'] ?? ''),
	]);
}

function lf_ai_ajax_inline_rewrite(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	$selector = isset($_POST['selector']) ? sanitize_text_field(wp_unslash($_POST['selector'])) : '';
	$current_text = isset($_POST['current_text']) ? sanitize_textarea_field(wp_unslash($_POST['current_text'])) : '';
	$prompt = isset($_POST['prompt']) ? sanitize_textarea_field(wp_unslash($_POST['prompt'])) : '';
	if ($context_type === '' || $context_id === '' || $selector === '' || $current_text === '' || $prompt === '') {
		wp_send_json_error(['message' => __('Invalid rewrite payload.', 'leadsforward-core')]);
	}
	if (strlen($current_text) > 1200 || strlen($prompt) > 1200) {
		wp_send_json_error(['message' => __('Rewrite payload is too long.', 'leadsforward-core')]);
	}
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	$is_heading = function_exists('mb_strlen') ? mb_strlen($current_text) <= 90 : strlen($current_text) <= 90;
	$system = "You are editing one specific website text snippet.\n";
	$system .= "Output ONLY the rewritten text. No JSON. No quotes. No preface.\n";
	$system .= "Do not change scope beyond this one snippet.\n";
	if ($is_heading) {
		$system .= "Keep it concise and headline-like (roughly 3-10 words).\n";
	} else {
		$system .= "Keep similar length and style to the original snippet.\n";
	}
	$user = "User instruction:\n" . $prompt . "\n\n";
	$user .= "Current snippet text:\n" . $current_text . "\n\n";
	$user .= "Rewrite only this snippet.";
	$response = apply_filters('lf_ai_completion', '', $system, $user, $context_type, $context_id_use);
	if (is_wp_error($response)) {
		wp_send_json_error(['message' => $response->get_error_message()]);
	}
	$rewritten = is_string($response) ? trim(wp_strip_all_tags($response)) : '';
	$rewritten = trim((string) preg_replace('/\s+/', ' ', $rewritten));
	$rewritten = trim($rewritten, "\"' \t\n\r\0\x0B");
	if ($rewritten === '') {
		wp_send_json_error(['message' => __('AI did not return a valid rewrite.', 'leadsforward-core')]);
	}
	wp_send_json_success([
		'rewritten_text' => $rewritten,
		'selector' => $selector,
	]);
}

function lf_ai_reversible_section_types(): array {
	return ['service_details', 'content_image', 'content_image_a', 'image_content', 'image_content_b', 'content_image_c'];
}

function lf_ai_toggle_section_layout_for_context(string $context_type, $context_id, string $section_id): array {
	$allowed_types = lf_ai_reversible_section_types();
	if ($context_type === 'homepage' || $context_id === 'homepage') {
		if (!defined('LF_HOMEPAGE_CONFIG_OPTION') || !function_exists('lf_get_homepage_section_config')) {
			return ['error' => __('Homepage section settings are unavailable.', 'leadsforward-core')];
		}
		$config = lf_get_homepage_section_config();
		if (!isset($config[$section_id]) || !is_array($config[$section_id])) {
			return ['error' => __('That section was not found on the homepage.', 'leadsforward-core')];
		}
		if (!in_array($section_id, $allowed_types, true)) {
			return ['error' => __('This section does not support column reversal.', 'leadsforward-core')];
		}
		$row = $config[$section_id];
		if (function_exists('lf_sections_normalize_service_details_settings')) {
			$row = lf_sections_normalize_service_details_settings($section_id, $row);
		}
		$old_layout = (string) ($row['service_details_layout'] ?? 'content_media');
		if (!in_array($old_layout, ['content_media', 'media_content'], true)) {
			$old_layout = 'content_media';
		}
		$new_layout = $old_layout === 'media_content' ? 'content_media' : 'media_content';
		$config[$section_id]['service_details_layout'] = $new_layout;
		update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
		return ['old_layout' => $old_layout, 'new_layout' => $new_layout, 'section_type' => $section_id];
	}
	$pid = (int) $context_id;
	$post = get_post($pid);
	if (!$post instanceof \WP_Post || !defined('LF_PB_META_KEY') || !function_exists('lf_pb_get_post_config')) {
		return ['error' => __('Section settings are unavailable for this target.', 'leadsforward-core')];
	}
	$pb_context = function_exists('lf_ai_pb_context_for_post') ? lf_ai_pb_context_for_post($post) : '';
	if ($pb_context === '') {
		return ['error' => __('This target does not support column reversal.', 'leadsforward-core')];
	}
	$config = lf_pb_get_post_config($pid, $pb_context);
	$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	$row = is_array($sections[$section_id] ?? null) ? $sections[$section_id] : [];
	$section_type = (string) ($row['type'] ?? '');
	if ($section_type === '' || !in_array($section_type, $allowed_types, true)) {
		return ['error' => __('This section does not support column reversal.', 'leadsforward-core')];
	}
	$settings = is_array($row['settings'] ?? null) ? $row['settings'] : [];
	if (function_exists('lf_sections_normalize_service_details_settings')) {
		$settings = lf_sections_normalize_service_details_settings($section_type, $settings);
	}
	$old_layout = (string) ($settings['service_details_layout'] ?? 'content_media');
	if (!in_array($old_layout, ['content_media', 'media_content'], true)) {
		$old_layout = 'content_media';
	}
	$new_layout = $old_layout === 'media_content' ? 'content_media' : 'media_content';
	$config['sections'][$section_id]['settings']['service_details_layout'] = $new_layout;
	update_post_meta($pid, LF_PB_META_KEY, $config);
	return ['old_layout' => $old_layout, 'new_layout' => $new_layout, 'section_type' => $section_type];
}

function lf_ai_ajax_toggle_section_columns(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	$section_id = isset($_POST['section_id']) ? sanitize_text_field(wp_unslash($_POST['section_id'])) : '';
	if ($context_type === '' || $context_id === '' || $section_id === '') {
		wp_send_json_error(['message' => __('Invalid column toggle payload.', 'leadsforward-core')]);
	}
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	$result = lf_ai_toggle_section_layout_for_context($context_type, $context_id_use, $section_id);
	if (!empty($result['error'])) {
		wp_send_json_error(['message' => (string) $result['error']]);
	}
	$old_layout = (string) ($result['old_layout'] ?? '');
	$new_layout = (string) ($result['new_layout'] ?? '');
	if (!in_array($old_layout, ['content_media', 'media_content'], true) || !in_array($new_layout, ['content_media', 'media_content'], true)) {
		wp_send_json_error(['message' => __('Unable to update this section layout.', 'leadsforward-core')]);
	}
	$log_id = function_exists('lf_ai_log_action')
		? lf_ai_log_action(
			$context_type,
			$context_id_use,
			['__section_layout::' . $section_id => $old_layout],
			['__section_layout::' . $section_id => $new_layout],
			'Inline section column reversal'
		)
		: '';
	wp_send_json_success([
		'message' => __('Section columns reversed.', 'leadsforward-core'),
		'log_id' => $log_id,
		'section_id' => $section_id,
		'old_layout' => $old_layout,
		'new_layout' => $new_layout,
	]);
}

function lf_ai_homepage_resolve_section_id(string $section_id, string $expected_type = ''): string {
	$sid = sanitize_text_field($section_id);
	if ($sid === '' || !function_exists('lf_get_homepage_section_config')) {
		return $sid;
	}
	$config = lf_get_homepage_section_config();
	if (!is_array($config)) {
		return $sid;
	}
	if (is_array($config[$sid] ?? null)) {
		return $sid;
	}
	$type = sanitize_text_field($expected_type);
	if ($type === '') {
		$type = $sid;
		if (function_exists('lf_homepage_base_section_type')) {
			$type = (string) lf_homepage_base_section_type($sid);
		}
	}
	foreach ($config as $candidate_id => $row) {
		if (!is_string($candidate_id) || !is_array($row)) {
			continue;
		}
		$base = $candidate_id;
		if (function_exists('lf_homepage_base_section_type')) {
			$base = (string) lf_homepage_base_section_type($candidate_id);
		}
		$row_type = sanitize_text_field((string) ($row['section_type'] ?? $row['type'] ?? ''));
		if ($base === $type || $row_type === $type) {
			return $candidate_id;
		}
	}
	return $sid;
}

function lf_ai_ajax_update_section_checklist(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	$section_id = isset($_POST['section_id']) ? sanitize_text_field(wp_unslash($_POST['section_id'])) : '';
	$items_raw = isset($_POST['items']) ? wp_unslash((string) $_POST['items']) : '[]';
	$items_decoded = json_decode($items_raw, true);
	if ($context_type === '' || $context_id === '' || $section_id === '' || !is_array($items_decoded)) {
		wp_send_json_error(['message' => __('Invalid checklist payload.', 'leadsforward-core')]);
	}
	$items = [];
	foreach ($items_decoded as $item) {
		$value = trim(sanitize_textarea_field((string) $item));
		if ($value === '') {
			continue;
		}
		$items[] = $value;
	}
	$items = array_values(array_unique($items));
	if (count($items) > 20) {
		$items = array_slice($items, 0, 20);
	}
	$value = implode("\n", $items);
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	$allowed_types = lf_ai_reversible_section_types();
	if ($context_type === 'homepage' || $context_id_use === 'homepage') {
		if (!defined('LF_HOMEPAGE_CONFIG_OPTION') || !function_exists('lf_get_homepage_section_config')) {
			wp_send_json_error(['message' => __('Homepage section settings are unavailable.', 'leadsforward-core')]);
		}
		$section_type = $section_id;
		if (function_exists('lf_homepage_base_section_type')) {
			$section_type = lf_homepage_base_section_type($section_id);
		}
		if ($section_type === '' || !in_array($section_type, $allowed_types, true)) {
			wp_send_json_error(['message' => __('This section does not support checklist editing.', 'leadsforward-core')]);
		}
		$config = lf_get_homepage_section_config();
		$resolved_section_id = lf_ai_homepage_resolve_section_id($section_id, $section_type);
		$old_row = is_array($config[$resolved_section_id] ?? null) ? $config[$resolved_section_id] : [];
		if (empty($old_row)) {
			wp_send_json_error(['message' => __('Section not found for this page.', 'leadsforward-core')]);
		}
		$config[$resolved_section_id]['service_details_checklist'] = $value;
		update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
		$new_row = is_array($config[$resolved_section_id] ?? null) ? $config[$resolved_section_id] : [];
		$log_id = function_exists('lf_ai_log_action')
			? lf_ai_log_action(
				$context_type,
				$context_id_use,
				['__homepage_section_row::' . $resolved_section_id => $old_row],
				['__homepage_section_row::' . $resolved_section_id => $new_row],
				'Inline checklist edit'
			)
			: '';
		wp_send_json_success([
			'message' => __('Checklist updated.', 'leadsforward-core'),
			'items' => $items,
			'log_id' => $log_id,
		]);
	}
	$pid = (int) $context_id_use;
	$post = get_post($pid);
	if (!$post instanceof \WP_Post || !defined('LF_PB_META_KEY') || !function_exists('lf_pb_get_post_config')) {
		wp_send_json_error(['message' => __('Section settings are unavailable for this target.', 'leadsforward-core')]);
	}
	$pb_context = function_exists('lf_ai_pb_context_for_post') ? lf_ai_pb_context_for_post($post) : '';
	if ($pb_context === '') {
		wp_send_json_error(['message' => __('This target does not support checklist editing.', 'leadsforward-core')]);
	}
	$config = lf_pb_get_post_config($pid, $pb_context);
	$old_row = is_array($config['sections'][$section_id] ?? null) ? $config['sections'][$section_id] : [];
	if (empty($old_row)) {
		wp_send_json_error(['message' => __('Section not found for this page.', 'leadsforward-core')]);
	}
	$section_type = sanitize_text_field((string) ($old_row['type'] ?? ''));
	if ($section_type === '' || !in_array($section_type, $allowed_types, true)) {
		wp_send_json_error(['message' => __('This section does not support checklist editing.', 'leadsforward-core')]);
	}
	$settings = is_array($old_row['settings'] ?? null) ? $old_row['settings'] : [];
	$settings['service_details_checklist'] = $value;
	$config['sections'][$section_id]['settings'] = $settings;
	update_post_meta($pid, LF_PB_META_KEY, $config);
	$new_row = is_array($config['sections'][$section_id] ?? null) ? $config['sections'][$section_id] : [];
	$log_id = function_exists('lf_ai_log_action')
		? lf_ai_log_action(
			$context_type,
			$context_id_use,
			['__section_record::' . $section_id => $old_row],
			['__section_record::' . $section_id => $new_row],
			'Inline checklist edit'
		)
		: '';
	wp_send_json_success([
		'message' => __('Checklist updated.', 'leadsforward-core'),
		'items' => $items,
		'log_id' => $log_id,
	]);
}

function lf_ai_ajax_update_hero_pills(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	$section_id = isset($_POST['section_id']) ? sanitize_text_field(wp_unslash($_POST['section_id'])) : '';
	$items_raw = isset($_POST['items']) ? wp_unslash((string) $_POST['items']) : '[]';
	$items_decoded = json_decode($items_raw, true);
	if ($context_type === '' || $context_id === '' || $section_id === '' || !is_array($items_decoded)) {
		wp_send_json_error(['message' => __('Invalid hero pills payload.', 'leadsforward-core')]);
	}
	$list_kind = isset($_POST['list_kind']) ? sanitize_key(wp_unslash((string) $_POST['list_kind'])) : 'chips';
	if (!in_array($list_kind, ['chips', 'proof'], true)) {
		$list_kind = 'chips';
	}
	$field_key = $list_kind === 'proof' ? 'hero_proof_bullets' : 'hero_chip_bullets';
	$items = [];
	foreach ($items_decoded as $item) {
		$value = trim(sanitize_text_field((string) $item));
		if ($value === '') {
			continue;
		}
		$items[] = $value;
	}
	$items = array_values(array_unique($items));
	if (count($items) > 20) {
		$items = array_slice($items, 0, 20);
	}
	$value = implode("\n", $items);
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	if ($context_type === 'homepage' || $context_id_use === 'homepage') {
		if (!defined('LF_HOMEPAGE_CONFIG_OPTION') || !function_exists('lf_get_homepage_section_config')) {
			wp_send_json_error(['message' => __('Homepage section settings are unavailable.', 'leadsforward-core')]);
		}
		$section_type = $section_id;
		if (function_exists('lf_homepage_base_section_type')) {
			$section_type = lf_homepage_base_section_type($section_id);
		}
		if ($section_type !== 'hero') {
			wp_send_json_error(['message' => __('This section does not support hero pills editing.', 'leadsforward-core')]);
		}
		$config = lf_get_homepage_section_config();
		$resolved_section_id = lf_ai_homepage_resolve_section_id($section_id, 'hero');
		$old_row = is_array($config[$resolved_section_id] ?? null) ? $config[$resolved_section_id] : [];
		if (empty($old_row)) {
			wp_send_json_error(['message' => __('Section not found for this page.', 'leadsforward-core')]);
		}
		$config[$resolved_section_id][ $field_key ] = $value;
		update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
		$new_row = is_array($config[$resolved_section_id] ?? null) ? $config[$resolved_section_id] : [];
		$log_label = $list_kind === 'proof' ? 'Inline hero proof card edit' : 'Inline hero pills edit';
		$log_id = function_exists('lf_ai_log_action')
			? lf_ai_log_action(
				$context_type,
				$context_id_use,
				['__homepage_section_row::' . $resolved_section_id => $old_row],
				['__homepage_section_row::' . $resolved_section_id => $new_row],
				$log_label
			)
			: '';
		wp_send_json_success([
			'message' => $list_kind === 'proof' ? __('Proof card updated.', 'leadsforward-core') : __('Hero pills updated.', 'leadsforward-core'),
			'items' => $items,
			'log_id' => $log_id,
		]);
	}
	$pid = (int) $context_id_use;
	$post = get_post($pid);
	if (!$post instanceof \WP_Post || !defined('LF_PB_META_KEY') || !function_exists('lf_pb_get_post_config')) {
		wp_send_json_error(['message' => __('Section settings are unavailable for this target.', 'leadsforward-core')]);
	}
	$pb_context = function_exists('lf_ai_pb_context_for_post') ? lf_ai_pb_context_for_post($post) : '';
	if ($pb_context === '') {
		wp_send_json_error(['message' => __('This target does not support hero pills editing.', 'leadsforward-core')]);
	}
	$config = lf_pb_get_post_config($pid, $pb_context);
	$old_row = is_array($config['sections'][$section_id] ?? null) ? $config['sections'][$section_id] : [];
	if (empty($old_row)) {
		wp_send_json_error(['message' => __('Section not found for this page.', 'leadsforward-core')]);
	}
	$section_type = sanitize_text_field((string) ($old_row['type'] ?? ''));
	if ($section_type !== 'hero') {
		wp_send_json_error(['message' => __('This section does not support hero pills editing.', 'leadsforward-core')]);
	}
	$settings = is_array($old_row['settings'] ?? null) ? $old_row['settings'] : [];
	$settings[ $field_key ] = $value;
	$config['sections'][$section_id]['settings'] = $settings;
	update_post_meta($pid, LF_PB_META_KEY, $config);
	$new_row = is_array($config['sections'][$section_id] ?? null) ? $config['sections'][$section_id] : [];
	$log_label = $list_kind === 'proof' ? 'Inline hero proof card edit' : 'Inline hero pills edit';
	$log_id = function_exists('lf_ai_log_action')
		? lf_ai_log_action(
			$context_type,
			$context_id_use,
			['__section_record::' . $section_id => $old_row],
			['__section_record::' . $section_id => $new_row],
			$log_label
		)
		: '';
	wp_send_json_success([
		'message' => $list_kind === 'proof' ? __('Proof card updated.', 'leadsforward-core') : __('Hero pills updated.', 'leadsforward-core'),
		'items' => $items,
		'log_id' => $log_id,
	]);
}

function lf_ai_ajax_update_hero_trust_strip(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	$section_id = isset($_POST['section_id']) ? sanitize_text_field(wp_unslash($_POST['section_id'])) : '';
	$enabled_raw = isset($_POST['enabled']) ? sanitize_text_field(wp_unslash((string) $_POST['enabled'])) : '1';
	$enabled = $enabled_raw === '0' ? '0' : '1';
	if ($context_type === '' || $context_id === '' || $section_id === '') {
		wp_send_json_error(['message' => __('Invalid trust strip payload.', 'leadsforward-core')]);
	}
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	if ($context_type === 'homepage' || $context_id_use === 'homepage') {
		if (!defined('LF_HOMEPAGE_CONFIG_OPTION') || !function_exists('lf_get_homepage_section_config')) {
			wp_send_json_error(['message' => __('Homepage section settings are unavailable.', 'leadsforward-core')]);
		}
		$section_type = $section_id;
		if (function_exists('lf_homepage_base_section_type')) {
			$section_type = lf_homepage_base_section_type($section_id);
		}
		if ($section_type !== 'hero') {
			wp_send_json_error(['message' => __('This section is not the homepage hero.', 'leadsforward-core')]);
		}
		$config = lf_get_homepage_section_config();
		$resolved = lf_ai_homepage_resolve_section_id($section_id, 'hero');
		$old_row = is_array($config[$resolved] ?? null) ? $config[$resolved] : [];
		if (empty($old_row)) {
			wp_send_json_error(['message' => __('Section not found for this page.', 'leadsforward-core')]);
		}
		$config[$resolved]['hero_trust_strip_enabled'] = $enabled;
		update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
		$new_row = $config[$resolved];
		$log_id = function_exists('lf_ai_log_action')
			? lf_ai_log_action(
				$context_type,
				$context_id_use,
				['__homepage_section_row::' . $resolved => $old_row],
				['__homepage_section_row::' . $resolved => $new_row],
				'Hero trust strip toggle'
			)
			: '';
		wp_send_json_success([
			'message' => __('Trust strip setting saved.', 'leadsforward-core'),
			'enabled' => $enabled,
			'log_id' => $log_id,
		]);
		return;
	}
	$pid = (int) $context_id_use;
	$post = get_post($pid);
	if (!$post instanceof \WP_Post || !defined('LF_PB_META_KEY') || !function_exists('lf_pb_get_post_config')) {
		wp_send_json_error(['message' => __('Section settings are unavailable for this page.', 'leadsforward-core')]);
	}
	$pb_context = function_exists('lf_ai_pb_context_for_post') ? lf_ai_pb_context_for_post($post) : '';
	if ($pb_context === '') {
		wp_send_json_error(['message' => __('This page does not support hero settings here.', 'leadsforward-core')]);
	}
	$config = lf_pb_get_post_config($pid, $pb_context);
	$old_row = is_array($config['sections'][$section_id] ?? null) ? $config['sections'][$section_id] : [];
	if (empty($old_row) || sanitize_text_field((string) ($old_row['type'] ?? '')) !== 'hero') {
		wp_send_json_error(['message' => __('That section is not a hero block.', 'leadsforward-core')]);
	}
	$settings = is_array($old_row['settings'] ?? null) ? $old_row['settings'] : [];
	$settings['hero_trust_strip_enabled'] = $enabled;
	$config['sections'][$section_id]['settings'] = $settings;
	update_post_meta($pid, LF_PB_META_KEY, $config);
	$new_row = is_array($config['sections'][$section_id] ?? null) ? $config['sections'][$section_id] : [];
	$log_id = function_exists('lf_ai_log_action')
		? lf_ai_log_action(
			$context_type,
			$context_id_use,
			['__section_record::' . $section_id => $old_row],
			['__section_record::' . $section_id => $new_row],
			'Hero trust strip toggle'
		)
		: '';
	wp_send_json_success([
		'message' => __('Trust strip setting saved.', 'leadsforward-core'),
		'enabled' => $enabled,
		'log_id' => $log_id,
	]);
}

function lf_ai_ajax_update_section_cta(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	$section_id = isset($_POST['section_id']) ? sanitize_text_field(wp_unslash($_POST['section_id'])) : '';
	$slot = isset($_POST['slot']) ? sanitize_text_field(wp_unslash($_POST['slot'])) : 'primary';
	$text = isset($_POST['text']) ? sanitize_text_field(wp_unslash($_POST['text'])) : '';
	$cta_action = isset($_POST['cta_action']) ? sanitize_text_field(wp_unslash($_POST['cta_action'])) : '';
	$url = isset($_POST['url']) ? esc_url_raw((string) wp_unslash($_POST['url'])) : '';
	if ($context_type === '' || $context_id === '' || $section_id === '') {
		wp_send_json_error(['message' => __('Invalid CTA update payload.', 'leadsforward-core')]);
	}
	$slot = $slot === 'secondary' ? 'secondary' : 'primary';
	if (!in_array($cta_action, ['quote', 'call', 'link'], true)) {
		$cta_action = 'quote';
	}
	if ($cta_action !== 'link') {
		$url = '';
	}
	$text = trim($text);
	if ($text === '') {
		wp_send_json_error(['message' => __('Button text cannot be empty.', 'leadsforward-core')]);
	}
	$override_key = $slot === 'secondary' ? 'cta_secondary_override' : 'cta_primary_override';
	$action_key = $slot === 'secondary' ? 'cta_secondary_action' : 'cta_primary_action';
	$url_key = $slot === 'secondary' ? 'cta_secondary_url' : 'cta_primary_url';
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	if ($context_type === 'homepage' || $context_id_use === 'homepage') {
		if (!defined('LF_HOMEPAGE_CONFIG_OPTION') || !function_exists('lf_get_homepage_section_config')) {
			wp_send_json_error(['message' => __('Homepage section settings are unavailable.', 'leadsforward-core')]);
		}
		$config = lf_get_homepage_section_config();
		$resolved_section_id = lf_ai_homepage_resolve_section_id($section_id);
		$old_row = is_array($config[$resolved_section_id] ?? null) ? $config[$resolved_section_id] : [];
		if (empty($old_row)) {
			wp_send_json_error(['message' => __('Section not found for this page.', 'leadsforward-core')]);
		}
		$config[$resolved_section_id][$override_key] = $text;
		$config[$resolved_section_id][$action_key] = $cta_action;
		$config[$resolved_section_id][$url_key] = $url;
		update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
		$new_row = is_array($config[$resolved_section_id] ?? null) ? $config[$resolved_section_id] : [];
		$log_id = function_exists('lf_ai_log_action')
			? lf_ai_log_action(
				$context_type,
				$context_id_use,
				['__homepage_section_row::' . $resolved_section_id => $old_row],
				['__homepage_section_row::' . $resolved_section_id => $new_row],
				'Inline CTA edit'
			)
			: '';
		wp_send_json_success([
			'message' => __('Button updated.', 'leadsforward-core'),
			'reload' => true,
			'log_id' => $log_id,
		]);
	}
	$pid = (int) $context_id_use;
	$post = get_post($pid);
	if (!$post instanceof \WP_Post || !defined('LF_PB_META_KEY') || !function_exists('lf_pb_get_post_config')) {
		wp_send_json_error(['message' => __('Section settings are unavailable for this target.', 'leadsforward-core')]);
	}
	$pb_context = function_exists('lf_ai_pb_context_for_post') ? lf_ai_pb_context_for_post($post) : '';
	if ($pb_context === '') {
		wp_send_json_error(['message' => __('This target does not support button editing.', 'leadsforward-core')]);
	}
	$config = lf_pb_get_post_config($pid, $pb_context);
	$old_row = is_array($config['sections'][$section_id] ?? null) ? $config['sections'][$section_id] : [];
	if (empty($old_row)) {
		wp_send_json_error(['message' => __('Section not found for this page.', 'leadsforward-core')]);
	}
	$settings = is_array($old_row['settings'] ?? null) ? $old_row['settings'] : [];
	$settings[$override_key] = $text;
	$settings[$action_key] = $cta_action;
	$settings[$url_key] = $url;
	$config['sections'][$section_id]['settings'] = $settings;
	update_post_meta($pid, LF_PB_META_KEY, $config);
	$new_row = is_array($config['sections'][$section_id] ?? null) ? $config['sections'][$section_id] : [];
	$log_id = function_exists('lf_ai_log_action')
		? lf_ai_log_action(
			$context_type,
			$context_id_use,
			['__section_record::' . $section_id => $old_row],
			['__section_record::' . $section_id => $new_row],
			'Inline CTA edit'
		)
		: '';
	wp_send_json_success([
		'message' => __('Button updated.', 'leadsforward-core'),
		'reload' => true,
		'log_id' => $log_id,
	]);
}

function lf_ai_media_capable_section_types(): array {
	return ['service_details', 'content_image', 'content_image_a', 'image_content', 'image_content_b', 'content_image_c'];
}

function lf_ai_ajax_update_section_media(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	$section_id = isset($_POST['section_id']) ? sanitize_text_field(wp_unslash($_POST['section_id'])) : '';
	$mode = isset($_POST['mode']) ? sanitize_text_field(wp_unslash($_POST['mode'])) : 'image';
	$image_id = isset($_POST['image_id']) ? (int) $_POST['image_id'] : 0;
	$video_url = isset($_POST['video_url']) ? esc_url_raw((string) wp_unslash($_POST['video_url'])) : '';
	$embed_code = isset($_POST['embed_code']) ? trim((string) wp_unslash($_POST['embed_code'])) : '';
	if ($context_type === '' || $context_id === '' || $section_id === '') {
		wp_send_json_error(['message' => __('Invalid media update payload.', 'leadsforward-core')]);
	}
	if (!in_array($mode, ['image', 'video', 'none'], true)) {
		$mode = 'image';
	}
	if ($mode === 'image' && $image_id <= 0) {
		wp_send_json_error(['message' => __('Please select an image.', 'leadsforward-core')]);
	}
	if ($mode === 'video' && $video_url === '' && $embed_code === '') {
		wp_send_json_error(['message' => __('Provide a video URL or embed code.', 'leadsforward-core')]);
	}
	if ($mode !== 'video') {
		$video_url = '';
		$embed_code = '';
	}
	if ($mode === 'none') {
		$image_id = 0;
		$video_url = '';
		$embed_code = '';
	}
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	$allowed_types = lf_ai_media_capable_section_types();
	if ($context_type === 'homepage' || $context_id_use === 'homepage') {
		if (!defined('LF_HOMEPAGE_CONFIG_OPTION') || !function_exists('lf_get_homepage_section_config')) {
			wp_send_json_error(['message' => __('Homepage section settings are unavailable.', 'leadsforward-core')]);
		}
		$section_type = $section_id;
		if (function_exists('lf_homepage_base_section_type')) {
			$section_type = lf_homepage_base_section_type($section_id);
		}
		if ($section_type === '' || !in_array($section_type, $allowed_types, true)) {
			wp_send_json_error(['message' => __('This section does not support media editing.', 'leadsforward-core')]);
		}
		$config = lf_get_homepage_section_config();
		$resolved_section_id = lf_ai_homepage_resolve_section_id($section_id, $section_type);
		$old_row = is_array($config[$resolved_section_id] ?? null) ? $config[$resolved_section_id] : [];
		if (empty($old_row)) {
			wp_send_json_error(['message' => __('Section not found for this page.', 'leadsforward-core')]);
		}
		$config[$resolved_section_id]['service_details_media_mode'] = $mode;
		$config[$resolved_section_id]['service_details_media_image_id'] = $image_id;
		$config[$resolved_section_id]['service_details_media_video_url'] = $video_url;
		$config[$resolved_section_id]['service_details_media_embed'] = $embed_code;
		if ($image_id > 0) {
			$config[$resolved_section_id]['image_id'] = $image_id;
		}
		update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
		$new_row = is_array($config[$resolved_section_id] ?? null) ? $config[$resolved_section_id] : [];
		$log_id = function_exists('lf_ai_log_action')
			? lf_ai_log_action(
				$context_type,
				$context_id_use,
				['__homepage_section_row::' . $resolved_section_id => $old_row],
				['__homepage_section_row::' . $resolved_section_id => $new_row],
				'Inline section media edit'
			)
			: '';
		wp_send_json_success([
			'message' => __('Section media updated.', 'leadsforward-core'),
			'reload' => true,
			'log_id' => $log_id,
		]);
	}
	$pid = (int) $context_id_use;
	$post = get_post($pid);
	if (!$post instanceof \WP_Post || !defined('LF_PB_META_KEY') || !function_exists('lf_pb_get_post_config')) {
		wp_send_json_error(['message' => __('Section settings are unavailable for this target.', 'leadsforward-core')]);
	}
	$pb_context = function_exists('lf_ai_pb_context_for_post') ? lf_ai_pb_context_for_post($post) : '';
	if ($pb_context === '') {
		wp_send_json_error(['message' => __('This target does not support media editing.', 'leadsforward-core')]);
	}
	$config = lf_pb_get_post_config($pid, $pb_context);
	$old_row = is_array($config['sections'][$section_id] ?? null) ? $config['sections'][$section_id] : [];
	if (empty($old_row)) {
		wp_send_json_error(['message' => __('Section not found for this page.', 'leadsforward-core')]);
	}
	$section_type = sanitize_text_field((string) ($old_row['type'] ?? ''));
	if ($section_type === '' || !in_array($section_type, $allowed_types, true)) {
		wp_send_json_error(['message' => __('This section does not support media editing.', 'leadsforward-core')]);
	}
	$settings = is_array($old_row['settings'] ?? null) ? $old_row['settings'] : [];
	$settings['service_details_media_mode'] = $mode;
	$settings['service_details_media_image_id'] = $image_id;
	$settings['service_details_media_video_url'] = $video_url;
	$settings['service_details_media_embed'] = $embed_code;
	if ($image_id > 0) {
		$settings['image_id'] = $image_id;
	}
	$config['sections'][$section_id]['settings'] = $settings;
	update_post_meta($pid, LF_PB_META_KEY, $config);
	$new_row = is_array($config['sections'][$section_id] ?? null) ? $config['sections'][$section_id] : [];
	$log_id = function_exists('lf_ai_log_action')
		? lf_ai_log_action(
			$context_type,
			$context_id_use,
			['__section_record::' . $section_id => $old_row],
			['__section_record::' . $section_id => $new_row],
			'Inline section media edit'
		)
		: '';
	wp_send_json_success([
		'message' => __('Section media updated.', 'leadsforward-core'),
		'reload' => true,
		'log_id' => $log_id,
	]);
}

function lf_ai_allowed_line_fields_for_section_type(string $section_type): array {
	$type = sanitize_text_field($section_type);
	$map = [
		'hero' => ['hero_proof_bullets', 'hero_chip_bullets'],
		'trust_bar' => ['trust_badges'],
		'benefits' => ['benefits_icon_overrides', 'benefits_items'],
		'service_intro' => ['service_intro_service_ids'],
		'process' => ['process_steps'],
		'faq_accordion' => ['faq_selected_ids'],
		'service_details' => ['service_details_checklist'],
		'content_image' => ['service_details_checklist'],
		'content_image_a' => ['service_details_checklist'],
		'image_content' => ['service_details_checklist'],
		'image_content_b' => ['service_details_checklist'],
		'content_image_c' => ['service_details_checklist'],
	];
	return $map[$type] ?? [];
}

function lf_ai_ajax_faq_library(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
	$query_args = [
		'post_type' => 'lf_faq',
		'post_status' => 'publish',
		'posts_per_page' => 200,
		'orderby' => 'menu_order title',
		'order' => 'ASC',
		'no_found_rows' => true,
	];
	if ($search !== '') {
		$query_args['s'] = $search;
	}
	$query = new WP_Query($query_args);
	$rows = [];
	while ($query->have_posts()) {
		$query->the_post();
		$faq_id = (int) get_the_ID();
		$question = function_exists('get_field') ? (string) get_field('lf_faq_question', $faq_id) : '';
		$answer = function_exists('get_field') ? (string) get_field('lf_faq_answer', $faq_id) : '';
		if ($question === '') {
			$question = (string) get_the_title($faq_id);
		}
		if ($answer === '') {
			$answer = trim((string) get_post_field('post_content', $faq_id));
		}
		$rows[] = [
			'id' => $faq_id,
			'question' => $question,
			'answer' => wp_strip_all_tags($answer),
		];
	}
	wp_reset_postdata();
	wp_send_json_success([
		'items' => $rows,
	]);
}

function lf_ai_ajax_update_section_lines(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	$section_id = isset($_POST['section_id']) ? sanitize_text_field(wp_unslash($_POST['section_id'])) : '';
	$field_key = isset($_POST['field_key']) ? sanitize_text_field(wp_unslash($_POST['field_key'])) : '';
	$items_raw = isset($_POST['items']) ? wp_unslash((string) $_POST['items']) : '[]';
	$items_decoded = json_decode($items_raw, true);
	if ($context_type === '' || $context_id === '' || $section_id === '' || $field_key === '' || !is_array($items_decoded)) {
		wp_send_json_error(['message' => __('Invalid list payload.', 'leadsforward-core')]);
	}
	$items = [];
	foreach ($items_decoded as $item) {
		$value = trim(sanitize_text_field((string) $item));
		if ($value === '') {
			continue;
		}
		$items[] = $value;
	}
	if (count($items) > 40) {
		$items = array_slice($items, 0, 40);
	}
	$value = implode("\n", $items);
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	if ($context_type === 'homepage' || $context_id_use === 'homepage') {
		if (!defined('LF_HOMEPAGE_CONFIG_OPTION') || !function_exists('lf_get_homepage_section_config')) {
			wp_send_json_error(['message' => __('Homepage section settings are unavailable.', 'leadsforward-core')]);
		}
		$section_type = $section_id;
		if (function_exists('lf_homepage_base_section_type')) {
			$section_type = lf_homepage_base_section_type($section_id);
		}
		$allowed = lf_ai_allowed_line_fields_for_section_type($section_type);
		if (!in_array($field_key, $allowed, true)) {
			wp_send_json_error(['message' => __('This list is not editable for the selected section.', 'leadsforward-core')]);
		}
		$config = lf_get_homepage_section_config();
		$resolved_section_id = lf_ai_homepage_resolve_section_id($section_id, $section_type);
		$old_row = is_array($config[$resolved_section_id] ?? null) ? $config[$resolved_section_id] : [];
		if (empty($old_row)) {
			wp_send_json_error(['message' => __('Section not found for this page.', 'leadsforward-core')]);
		}
		$config[$resolved_section_id][$field_key] = $value;
		update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
		$new_row = is_array($config[$resolved_section_id] ?? null) ? $config[$resolved_section_id] : [];
		$log_id = function_exists('lf_ai_log_action')
			? lf_ai_log_action(
				$context_type,
				$context_id_use,
				['__homepage_section_row::' . $resolved_section_id => $old_row],
				['__homepage_section_row::' . $resolved_section_id => $new_row],
				'Inline list edit'
			)
			: '';
		wp_send_json_success([
			'message' => __('List updated.', 'leadsforward-core'),
			'items' => $items,
			'log_id' => $log_id,
		]);
	}
	$pid = (int) $context_id_use;
	$post = get_post($pid);
	if (!$post instanceof \WP_Post || !defined('LF_PB_META_KEY') || !function_exists('lf_pb_get_post_config')) {
		wp_send_json_error(['message' => __('Section settings are unavailable for this target.', 'leadsforward-core')]);
	}
	$pb_context = function_exists('lf_ai_pb_context_for_post') ? lf_ai_pb_context_for_post($post) : '';
	if ($pb_context === '') {
		wp_send_json_error(['message' => __('This target does not support list editing.', 'leadsforward-core')]);
	}
	$config = lf_pb_get_post_config($pid, $pb_context);
	$old_row = is_array($config['sections'][$section_id] ?? null) ? $config['sections'][$section_id] : [];
	if (empty($old_row)) {
		wp_send_json_error(['message' => __('Section not found for this page.', 'leadsforward-core')]);
	}
	$section_type = sanitize_text_field((string) ($old_row['type'] ?? ''));
	$allowed = lf_ai_allowed_line_fields_for_section_type($section_type);
	if (!in_array($field_key, $allowed, true)) {
		wp_send_json_error(['message' => __('This list is not editable for the selected section.', 'leadsforward-core')]);
	}
	$settings = is_array($old_row['settings'] ?? null) ? $old_row['settings'] : [];
	$settings[$field_key] = $value;
	$config['sections'][$section_id]['settings'] = $settings;
	update_post_meta($pid, LF_PB_META_KEY, $config);
	$new_row = is_array($config['sections'][$section_id] ?? null) ? $config['sections'][$section_id] : [];
	$log_id = function_exists('lf_ai_log_action')
		? lf_ai_log_action(
			$context_type,
			$context_id_use,
			['__section_record::' . $section_id => $old_row],
			['__section_record::' . $section_id => $new_row],
			'Inline list edit'
		)
		: '';
	wp_send_json_success([
		'message' => __('List updated.', 'leadsforward-core'),
		'items' => $items,
		'log_id' => $log_id,
	]);
}

function lf_ai_ajax_reorder_faq_items(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$ids_raw = isset($_POST['faq_ids']) ? wp_unslash((string) $_POST['faq_ids']) : '[]';
	$ids = json_decode($ids_raw, true);
	if (!is_array($ids) || empty($ids)) {
		wp_send_json_error(['message' => __('No FAQ IDs were provided.', 'leadsforward-core')]);
	}
	$order = 10;
	$updated = 0;
	foreach ($ids as $id) {
		$faq_id = (int) $id;
		if ($faq_id <= 0) {
			continue;
		}
		$post = get_post($faq_id);
		if (!$post instanceof \WP_Post || $post->post_type !== 'lf_faq') {
			continue;
		}
		if (!current_user_can('edit_post', $faq_id)) {
			continue;
		}
		wp_update_post([
			'ID' => $faq_id,
			'menu_order' => $order,
		]);
		$order += 10;
		$updated++;
	}
	wp_send_json_success([
		'message' => $updated > 0 ? __('FAQ order saved.', 'leadsforward-core') : __('No FAQ items were updated.', 'leadsforward-core'),
		'updated' => $updated,
	]);
}

function lf_ai_set_trust_layout_for_context(string $context_type, $context_id, string $section_id, string $layout): array {
	$allowed = ['grid', 'masonry', 'slider'];
	if (!in_array($layout, $allowed, true)) {
		return ['error' => __('Invalid trust layout.', 'leadsforward-core')];
	}
	if ($context_type === 'homepage' || $context_id === 'homepage') {
		if (!defined('LF_HOMEPAGE_CONFIG_OPTION') || !function_exists('lf_get_homepage_section_config')) {
			return ['error' => __('Homepage section settings are unavailable.', 'leadsforward-core')];
		}
		$section_type = $section_id;
		if (function_exists('lf_homepage_base_section_type')) {
			$section_type = lf_homepage_base_section_type($section_id);
		}
		if ($section_type !== 'trust_reviews') {
			return ['error' => __('This section does not support trust layout switching.', 'leadsforward-core')];
		}
		$config = lf_get_homepage_section_config();
		$row = is_array($config[$section_id] ?? null) ? $config[$section_id] : [];
		if (empty($row)) {
			return ['error' => __('Section not found for this page.', 'leadsforward-core')];
		}
		$old_layout = (string) ($row['trust_layout'] ?? 'grid');
		if (!in_array($old_layout, $allowed, true)) {
			$old_layout = 'grid';
		}
		$config[$section_id]['trust_layout'] = $layout;
		update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
		return ['old_layout' => $old_layout, 'new_layout' => $layout, 'section_type' => $section_type];
	}
	$pid = (int) $context_id;
	$post = get_post($pid);
	if (!$post instanceof \WP_Post || !defined('LF_PB_META_KEY') || !function_exists('lf_pb_get_post_config')) {
		return ['error' => __('Section settings are unavailable for this target.', 'leadsforward-core')];
	}
	$pb_context = function_exists('lf_ai_pb_context_for_post') ? lf_ai_pb_context_for_post($post) : '';
	if ($pb_context === '') {
		return ['error' => __('This target does not support trust layout switching.', 'leadsforward-core')];
	}
	$config = lf_pb_get_post_config($pid, $pb_context);
	$row = is_array($config['sections'][$section_id] ?? null) ? $config['sections'][$section_id] : [];
	$section_type = (string) ($row['type'] ?? '');
	if ($section_type !== 'trust_reviews') {
		return ['error' => __('This section does not support trust layout switching.', 'leadsforward-core')];
	}
	$settings = is_array($row['settings'] ?? null) ? $row['settings'] : [];
	$old_layout = (string) ($settings['trust_layout'] ?? 'grid');
	if (!in_array($old_layout, $allowed, true)) {
		$old_layout = 'grid';
	}
	$config['sections'][$section_id]['settings']['trust_layout'] = $layout;
	update_post_meta($pid, LF_PB_META_KEY, $config);
	return ['old_layout' => $old_layout, 'new_layout' => $layout, 'section_type' => $section_type];
}

function lf_ai_ajax_set_trust_layout(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	$section_id = isset($_POST['section_id']) ? sanitize_text_field(wp_unslash($_POST['section_id'])) : '';
	$layout = isset($_POST['layout']) ? sanitize_text_field(wp_unslash($_POST['layout'])) : 'grid';
	if ($context_type === '' || $context_id === '' || $section_id === '') {
		wp_send_json_error(['message' => __('Invalid trust layout payload.', 'leadsforward-core')]);
	}
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	$result = lf_ai_set_trust_layout_for_context($context_type, $context_id_use, $section_id, $layout);
	if (!empty($result['error'])) {
		wp_send_json_error(['message' => (string) $result['error']]);
	}
	$old_layout = (string) ($result['old_layout'] ?? 'grid');
	$new_layout = (string) ($result['new_layout'] ?? 'grid');
	$log_id = function_exists('lf_ai_log_action')
		? lf_ai_log_action(
			$context_type,
			$context_id_use,
			['__section_layout::' . $section_id => $old_layout],
			['__section_layout::' . $section_id => $new_layout],
			'Inline trust layout switch'
		)
		: '';
	wp_send_json_success([
		'message' => __('Review layout updated.', 'leadsforward-core'),
		'section_id' => $section_id,
		'old_layout' => $old_layout,
		'new_layout' => $new_layout,
		'reload' => true,
		'log_id' => $log_id,
	]);
}

function lf_ai_ajax_toggle_section_visibility(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	$section_id = isset($_POST['section_id']) ? sanitize_text_field(wp_unslash($_POST['section_id'])) : '';
	$visible_raw = isset($_POST['visible']) ? sanitize_text_field(wp_unslash($_POST['visible'])) : '1';
	$visible = $visible_raw === '1';
	if ($context_type === '' || $context_id === '' || $section_id === '') {
		wp_send_json_error(['message' => __('Invalid visibility payload.', 'leadsforward-core')]);
	}
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	if ($context_type === 'homepage' || $context_id_use === 'homepage') {
		if (!defined('LF_HOMEPAGE_CONFIG_OPTION') || !function_exists('lf_get_homepage_section_config')) {
			wp_send_json_error(['message' => __('Homepage section settings are unavailable.', 'leadsforward-core')]);
		}
		$config = lf_get_homepage_section_config();
		if (!is_array($config[$section_id] ?? null)) {
			wp_send_json_error(['message' => __('Section not found for this page.', 'leadsforward-core')]);
		}
		$old_enabled = !empty($config[$section_id]['enabled']);
		$config[$section_id]['enabled'] = $visible;
		update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
		$log_id = function_exists('lf_ai_log_action')
			? lf_ai_log_action(
				$context_type,
				$context_id_use,
				['__section_enabled::' . $section_id => $old_enabled],
				['__section_enabled::' . $section_id => $visible],
				$visible ? 'Inline section show' : 'Inline section hide'
			)
			: '';
		wp_send_json_success([
			'message' => $visible ? __('Section shown.', 'leadsforward-core') : __('Section hidden.', 'leadsforward-core'),
			'visible' => $visible,
			'log_id' => $log_id,
		]);
	}
	$pid = (int) $context_id_use;
	$post = get_post($pid);
	if (!$post instanceof \WP_Post || !defined('LF_PB_META_KEY') || !function_exists('lf_pb_get_post_config')) {
		wp_send_json_error(['message' => __('Section settings are unavailable for this target.', 'leadsforward-core')]);
	}
	$pb_context = function_exists('lf_ai_pb_context_for_post') ? lf_ai_pb_context_for_post($post) : '';
	if ($pb_context === '') {
		wp_send_json_error(['message' => __('This target does not support section visibility updates.', 'leadsforward-core')]);
	}
	$config = lf_pb_get_post_config($pid, $pb_context);
	if (!is_array($config['sections'] ?? null) || !is_array($config['sections'][$section_id] ?? null)) {
		wp_send_json_error(['message' => __('Section not found for this page.', 'leadsforward-core')]);
	}
	$old_enabled = !empty($config['sections'][$section_id]['enabled']);
	$config['sections'][$section_id]['enabled'] = $visible;
	update_post_meta($pid, LF_PB_META_KEY, $config);
	$log_id = function_exists('lf_ai_log_action')
		? lf_ai_log_action(
			$context_type,
			$context_id_use,
			['__section_enabled::' . $section_id => $old_enabled],
			['__section_enabled::' . $section_id => $visible],
			$visible ? 'Inline section show' : 'Inline section hide'
		)
		: '';
	wp_send_json_success([
		'message' => $visible ? __('Section shown.', 'leadsforward-core') : __('Section hidden.', 'leadsforward-core'),
		'visible' => $visible,
		'log_id' => $log_id,
	]);
}

function lf_ai_ajax_delete_section(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	$section_id = isset($_POST['section_id']) ? sanitize_text_field(wp_unslash($_POST['section_id'])) : '';
	if ($context_type === '' || $context_id === '' || $section_id === '') {
		wp_send_json_error(['message' => __('Invalid delete payload.', 'leadsforward-core')]);
	}
	if ($section_id === 'hero' || $section_id === 'hero_1') {
		wp_send_json_error(['message' => __('Hero cannot be deleted from this inline editor.', 'leadsforward-core')]);
	}
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	if ($context_type === 'homepage' || $context_id_use === 'homepage') {
		if (!defined('LF_HOMEPAGE_CONFIG_OPTION') || !function_exists('lf_get_homepage_section_config')) {
			wp_send_json_error(['message' => __('Homepage section settings are unavailable.', 'leadsforward-core')]);
		}
		$config = lf_get_homepage_section_config();
		if (!is_array($config[$section_id] ?? null)) {
			wp_send_json_error(['message' => __('Section not found for this page.', 'leadsforward-core')]);
		}
		$old_enabled = !empty($config[$section_id]['enabled']);
		$config[$section_id]['enabled'] = false;
		update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
		$log_id = function_exists('lf_ai_log_action')
			? lf_ai_log_action(
				$context_type,
				$context_id_use,
				['__section_enabled::' . $section_id => $old_enabled],
				['__section_enabled::' . $section_id => false],
				'Inline section delete (soft)'
			)
			: '';
		wp_send_json_success([
			'message' => __('Section deleted. Use undo to restore.', 'leadsforward-core'),
			'soft_delete' => true,
			'log_id' => $log_id,
		]);
	}
	$pid = (int) $context_id_use;
	$post = get_post($pid);
	if (!$post instanceof \WP_Post || !defined('LF_PB_META_KEY') || !function_exists('lf_pb_get_post_config')) {
		wp_send_json_error(['message' => __('Section settings are unavailable for this target.', 'leadsforward-core')]);
	}
	$pb_context = function_exists('lf_ai_pb_context_for_post') ? lf_ai_pb_context_for_post($post) : '';
	if ($pb_context === '') {
		wp_send_json_error(['message' => __('This target does not support section deletion.', 'leadsforward-core')]);
	}
	$config = lf_pb_get_post_config($pid, $pb_context);
	$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	$order = is_array($config['order'] ?? null) ? $config['order'] : [];
	if (!is_array($sections[$section_id] ?? null)) {
		wp_send_json_error(['message' => __('Section not found for this page.', 'leadsforward-core')]);
	}
	$old_row = $sections[$section_id];
	$old_order = $order;
	unset($sections[$section_id]);
	$new_order = array_values(array_filter($order, static function ($id) use ($section_id): bool {
		return (string) $id !== (string) $section_id;
	}));
	$config['sections'] = $sections;
	$config['order'] = $new_order;
	update_post_meta($pid, LF_PB_META_KEY, $config);
	$log_id = function_exists('lf_ai_log_action')
		? lf_ai_log_action(
			$context_type,
			$context_id_use,
			['__section_record::' . $section_id => $old_row, '__section_order' => $old_order],
			['__section_record::' . $section_id => [], '__section_order' => $new_order],
			'Inline section delete'
		)
		: '';
	wp_send_json_success([
		'message' => __('Section deleted. Use undo to restore.', 'leadsforward-core'),
		'deleted' => true,
		'log_id' => $log_id,
	]);
}

function lf_ai_ajax_duplicate_section(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	$section_id = isset($_POST['section_id']) ? sanitize_text_field(wp_unslash($_POST['section_id'])) : '';
	if ($context_type === '' || $context_id === '' || $section_id === '') {
		wp_send_json_error(['message' => __('Invalid duplicate payload.', 'leadsforward-core')]);
	}
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	if ($context_type === 'homepage' || $context_id_use === 'homepage') {
		if (!defined('LF_HOMEPAGE_CONFIG_OPTION') || !defined('LF_HOMEPAGE_ORDER_OPTION') || !function_exists('lf_get_homepage_section_config') || !function_exists('lf_homepage_controller_order') || !function_exists('lf_homepage_sanitize_order') || !function_exists('lf_homepage_base_section_type')) {
			wp_send_json_error(['message' => __('Homepage section settings are unavailable.', 'leadsforward-core')]);
		}
		$config = lf_get_homepage_section_config();
		if (!is_array($config[$section_id] ?? null)) {
			wp_send_json_error(['message' => __('Section not found for this page.', 'leadsforward-core')]);
		}
		$source_row = is_array($config[$section_id]) ? $config[$section_id] : [];
		$source_type = lf_homepage_base_section_type($section_id);
		if ($source_type === '' || $source_type === 'hero') {
			wp_send_json_error(['message' => __('This section type cannot be duplicated on homepage.', 'leadsforward-core')]);
		}
		$new_index = 2;
		do {
			$target_id = $source_type . '__' . $new_index;
			$new_index++;
		} while (isset($config[$target_id]));
		$old_order = lf_homepage_controller_order();
		$new_target_row = $source_row;
		$new_target_row['enabled'] = true;
		$new_target_row['type'] = $source_type;
		$config[$target_id] = $new_target_row;
		$order = [];
		$inserted = false;
		foreach ($old_order as $id) {
			$order[] = (string) $id;
			if ((string) $id === $section_id) {
				$order[] = $target_id;
				$inserted = true;
			}
		}
		if (!$inserted) {
			$order[] = $target_id;
		}
		$order = lf_homepage_sanitize_order($order, true);
		update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
		update_option(LF_HOMEPAGE_ORDER_OPTION, $order, true);
		$log_id = function_exists('lf_ai_log_action')
			? lf_ai_log_action(
				$context_type,
				$context_id_use,
				['__homepage_section_row::' . $target_id => [], '__section_order' => $old_order],
				['__homepage_section_row::' . $target_id => $new_target_row, '__section_order' => $order],
				'Inline section duplicate'
			)
			: '';
		wp_send_json_success([
			'message' => __('Section duplicated.', 'leadsforward-core'),
			'new_section_id' => $target_id,
			'reload' => true,
			'log_id' => $log_id,
		]);
	}
	$pid = (int) $context_id_use;
	$post = get_post($pid);
	if (!$post instanceof \WP_Post || !defined('LF_PB_META_KEY') || !function_exists('lf_pb_get_post_config')) {
		wp_send_json_error(['message' => __('Section settings are unavailable for this target.', 'leadsforward-core')]);
	}
	$pb_context = function_exists('lf_ai_pb_context_for_post') ? lf_ai_pb_context_for_post($post) : '';
	if ($pb_context === '') {
		wp_send_json_error(['message' => __('This target does not support section duplication.', 'leadsforward-core')]);
	}
	$config = lf_pb_get_post_config($pid, $pb_context);
	$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	$order = is_array($config['order'] ?? null) ? $config['order'] : [];
	$row = is_array($sections[$section_id] ?? null) ? $sections[$section_id] : [];
	if (empty($row)) {
		wp_send_json_error(['message' => __('Section not found for this page.', 'leadsforward-core')]);
	}
	$type = sanitize_text_field((string) ($row['type'] ?? ''));
	if ($type === '') {
		wp_send_json_error(['message' => __('Unable to determine section type for duplication.', 'leadsforward-core')]);
	}
	$new_index = 1;
	if (function_exists('lf_pb_instance_id')) {
		do {
			$new_index++;
			$new_id = lf_pb_instance_id($type, $new_index);
		} while (isset($sections[$new_id]));
	} else {
		$new_id = $type . '_copy_' . wp_generate_password(6, false, false);
	}
	$new_row = $row;
	$new_row['enabled'] = true;
	$new_row['deletable'] = true;
	$sections[$new_id] = $new_row;
	$old_order = $order;
	$new_order = [];
	foreach ($order as $id) {
		$new_order[] = (string) $id;
		if ((string) $id === $section_id) {
			$new_order[] = $new_id;
		}
	}
	if (!in_array($new_id, $new_order, true)) {
		$new_order[] = $new_id;
	}
	$config['sections'] = $sections;
	$config['order'] = $new_order;
	update_post_meta($pid, LF_PB_META_KEY, $config);
	$log_id = function_exists('lf_ai_log_action')
		? lf_ai_log_action(
			$context_type,
			$context_id_use,
			['__section_record::' . $new_id => [], '__section_order' => $old_order],
			['__section_record::' . $new_id => $new_row, '__section_order' => $new_order],
			'Inline section duplicate'
		)
		: '';
	wp_send_json_success([
		'message' => __('Section duplicated.', 'leadsforward-core'),
		'new_section_id' => $new_id,
		'section_type' => $type,
		'log_id' => $log_id,
	]);
}

function lf_ai_allowed_section_types_for_context(string $context_key): array {
	if (!function_exists('lf_sections_get_context_sections')) {
		return [];
	}
	$sections = lf_sections_get_context_sections($context_key);
	if (!is_array($sections)) {
		return [];
	}
	return array_values(array_filter(array_map(static function ($id): string {
		return sanitize_text_field((string) $id);
	}, array_keys($sections))));
}

function lf_ai_ajax_add_section(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	$section_type = isset($_POST['section_type']) ? sanitize_text_field(wp_unslash($_POST['section_type'])) : '';
	$after_section_id = isset($_POST['after_section_id']) ? sanitize_text_field(wp_unslash($_POST['after_section_id'])) : '';
	if ($context_type === '' || $context_id === '' || $section_type === '') {
		wp_send_json_error(['message' => __('Invalid add-section payload.', 'leadsforward-core')]);
	}
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	if ($context_type === 'homepage' || $context_id_use === 'homepage') {
		$allowed = lf_ai_allowed_section_types_for_context('homepage');
		if (!in_array($section_type, $allowed, true)) {
			wp_send_json_error(['message' => __('That section type is not available for homepage.', 'leadsforward-core')]);
		}
		if (!defined('LF_HOMEPAGE_CONFIG_OPTION') || !defined('LF_HOMEPAGE_ORDER_OPTION') || !function_exists('lf_get_homepage_section_config') || !function_exists('lf_homepage_controller_order') || !function_exists('lf_homepage_sanitize_order') || !function_exists('lf_homepage_base_section_type')) {
			wp_send_json_error(['message' => __('Homepage section settings are unavailable.', 'leadsforward-core')]);
		}
		$config = lf_get_homepage_section_config();
		$order = lf_homepage_controller_order();
		$old_order = $order;
		$target_section_id = $section_type;
		$current_row = is_array($config[$section_type] ?? null) ? $config[$section_type] : [];
		$current_enabled = !empty($current_row['enabled']);
		if ($current_enabled) {
			$target_type = lf_homepage_base_section_type($section_type);
			if ($target_type === '' || $target_type === 'hero') {
				wp_send_json_error(['message' => __('This section type cannot be added again on homepage.', 'leadsforward-core')]);
			}
			$new_index = 2;
			do {
				$target_section_id = $target_type . '__' . $new_index;
				$new_index++;
			} while (isset($config[$target_section_id]));
		}
		$old_row = is_array($config[$target_section_id] ?? null) ? $config[$target_section_id] : [];
		if (!is_array($config[$target_section_id] ?? null)) {
			$target_type = lf_homepage_base_section_type((string) $target_section_id);
			$config[$target_section_id] = function_exists('lf_homepage_default_section_config') ? lf_homepage_default_section_config($target_type) : ['enabled' => true, 'variant' => 'default'];
		}
		$config[$target_section_id]['type'] = lf_homepage_base_section_type((string) $target_section_id);
		$config[$target_section_id]['enabled'] = true;
		if (!in_array($target_section_id, $order, true)) {
			$insert_at = count($order);
			$cta_idx = array_search('cta', $order, true);
			if ($cta_idx !== false) {
				$insert_at = (int) $cta_idx;
			}
			if ($after_section_id !== '') {
				$after_idx = array_search($after_section_id, $order, true);
				if ($after_idx !== false) {
					$insert_at = (int) $after_idx + 1;
				}
			}
			array_splice($order, $insert_at, 0, [$target_section_id]);
		} elseif ($after_section_id !== '' && $after_section_id !== $target_section_id) {
			$current_idx = array_search($target_section_id, $order, true);
			$after_idx = array_search($after_section_id, $order, true);
			if ($current_idx !== false && $after_idx !== false) {
				array_splice($order, (int) $current_idx, 1);
				$after_idx = array_search($after_section_id, $order, true);
				$insert_at = $after_idx === false ? count($order) : ((int) $after_idx + 1);
				array_splice($order, $insert_at, 0, [$target_section_id]);
			}
		}
		$order = lf_homepage_sanitize_order($order, true);
		update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
		update_option(LF_HOMEPAGE_ORDER_OPTION, $order, true);
		$log_old = [
			'__section_enabled::' . $target_section_id => !empty($old_row['enabled']),
			'__section_order' => $old_order,
			'__homepage_section_row::' . $target_section_id => $old_row,
		];
		$log_new = [
			'__section_enabled::' . $target_section_id => true,
			'__section_order' => $order,
			'__homepage_section_row::' . $target_section_id => $config[$target_section_id],
		];
		$log_id = function_exists('lf_ai_log_action')
			? lf_ai_log_action($context_type, $context_id_use, $log_old, $log_new, 'Inline add section from library')
			: '';
		wp_send_json_success([
			'message' => __('Section added from library.', 'leadsforward-core'),
			'reload' => true,
			'section_id' => $target_section_id,
			'log_id' => $log_id,
		]);
	}
	$pid = (int) $context_id_use;
	$post = get_post($pid);
	if (!$post instanceof \WP_Post || !defined('LF_PB_META_KEY') || !function_exists('lf_pb_get_post_config')) {
		wp_send_json_error(['message' => __('Section settings are unavailable for this target.', 'leadsforward-core')]);
	}
	$pb_context = function_exists('lf_ai_pb_context_for_post') ? lf_ai_pb_context_for_post($post) : '';
	if ($pb_context === '') {
		wp_send_json_error(['message' => __('This target does not support adding sections.', 'leadsforward-core')]);
	}
	$allowed = lf_ai_allowed_section_types_for_context($pb_context);
	if (!in_array($section_type, $allowed, true)) {
		wp_send_json_error(['message' => __('That section type is not available for this page.', 'leadsforward-core')]);
	}
	$config = lf_pb_get_post_config($pid, $pb_context);
	$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	$order = is_array($config['order'] ?? null) ? $config['order'] : [];
	$new_index = 1;
	if (function_exists('lf_pb_instance_id')) {
		do {
			$new_index++;
			$new_id = lf_pb_instance_id($section_type, $new_index);
		} while (isset($sections[$new_id]));
	} else {
		$new_id = $section_type . '_copy_' . wp_generate_password(6, false, false);
	}
	$settings = function_exists('lf_sections_defaults_for') ? lf_sections_defaults_for($section_type) : [];
	$new_row = [
		'type' => $section_type,
		'enabled' => true,
		'deletable' => true,
		'settings' => is_array($settings) ? $settings : [],
	];
	$sections[$new_id] = $new_row;
	$old_order = $order;
	$new_order = [];
	$inserted = false;
	foreach ($order as $id) {
		$new_order[] = (string) $id;
		if (!$inserted && $after_section_id !== '' && (string) $id === $after_section_id) {
			$new_order[] = $new_id;
			$inserted = true;
		}
	}
	if (!$inserted) {
		$new_order[] = $new_id;
	}
	$config['sections'] = $sections;
	$config['order'] = $new_order;
	update_post_meta($pid, LF_PB_META_KEY, $config);
	$log_id = function_exists('lf_ai_log_action')
		? lf_ai_log_action(
			$context_type,
			$context_id_use,
			['__section_record::' . $new_id => [], '__section_order' => $old_order],
			['__section_record::' . $new_id => $new_row, '__section_order' => $new_order],
			'Inline add section from library'
		)
		: '';
	wp_send_json_success([
		'message' => __('Section added from library.', 'leadsforward-core'),
		'reload' => true,
		'new_section_id' => $new_id,
		'section_type' => $section_type,
		'log_id' => $log_id,
	]);
}

function lf_ai_ajax_reorder_sections(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	$ordered_ids_raw = isset($_POST['ordered_ids']) ? wp_unslash((string) $_POST['ordered_ids']) : '';
	$ordered_ids = json_decode($ordered_ids_raw, true);
	if ($context_type === '' || $context_id === '' || !is_array($ordered_ids) || empty($ordered_ids)) {
		wp_send_json_error(['message' => __('Invalid reorder payload.', 'leadsforward-core')]);
	}
	$ordered_ids = array_values(array_filter(array_map(static function ($id): string {
		return sanitize_text_field((string) $id);
	}, $ordered_ids)));
	if (empty($ordered_ids)) {
		wp_send_json_error(['message' => __('No section IDs were provided.', 'leadsforward-core')]);
	}
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	$old_order = [];
	$new_order = [];
	if ($context_type === 'homepage' || $context_id_use === 'homepage') {
		if (!defined('LF_HOMEPAGE_ORDER_OPTION') || !function_exists('lf_homepage_controller_order') || !function_exists('lf_homepage_sanitize_order')) {
			wp_send_json_error(['message' => __('Homepage section reorder is unavailable.', 'leadsforward-core')]);
		}
		$old_order = lf_homepage_controller_order();
		$new_order = lf_homepage_sanitize_order($ordered_ids, true);
		update_option(LF_HOMEPAGE_ORDER_OPTION, $new_order, true);
	} else {
		$pid = (int) $context_id_use;
		$post = get_post($pid);
		if (!$post instanceof \WP_Post || !defined('LF_PB_META_KEY') || !function_exists('lf_pb_get_post_config')) {
			wp_send_json_error(['message' => __('Section reorder is unavailable for this target.', 'leadsforward-core')]);
		}
		$pb_context = '';
		if (function_exists('lf_ai_pb_context_for_post')) {
			$pb_context = lf_ai_pb_context_for_post($post);
		}
		if ($pb_context === '') {
			wp_send_json_error(['message' => __('This post type does not support section reordering.', 'leadsforward-core')]);
		}
		$config = lf_pb_get_post_config($pid, $pb_context);
		$current_order = is_array($config['order'] ?? null) ? $config['order'] : [];
		if (empty($current_order)) {
			wp_send_json_error(['message' => __('No sections found to reorder.', 'leadsforward-core')]);
		}
		$new_order = [];
		foreach ($ordered_ids as $id) {
			if (in_array($id, $current_order, true) && !in_array($id, $new_order, true)) {
				$new_order[] = $id;
			}
		}
		foreach ($current_order as $id) {
			if (!in_array($id, $new_order, true)) {
				$new_order[] = $id;
			}
		}
		$old_order = $current_order;
		$config['order'] = $new_order;
		update_post_meta($pid, LF_PB_META_KEY, $config);
	}
	$log_id = function_exists('lf_ai_log_action')
		? lf_ai_log_action($context_type, $context_id_use, ['__section_order' => $old_order], ['__section_order' => $new_order], 'Inline section reorder')
		: '';
	wp_send_json_success([
		'message' => __('Section order saved.', 'leadsforward-core'),
		'log_id' => $log_id,
		'order' => $new_order,
	]);
}

function lf_ai_ajax_inline_image_save(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	$selector = isset($_POST['selector']) ? sanitize_text_field(wp_unslash($_POST['selector'])) : '';
	$attachment_id = isset($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;
	$image_url = isset($_POST['image_url']) ? esc_url_raw((string) wp_unslash($_POST['image_url'])) : '';
	$image_alt = isset($_POST['image_alt']) ? sanitize_text_field((string) wp_unslash($_POST['image_alt'])) : '';
	if ($context_type === '' || $context_id === '' || $selector === '' || $attachment_id <= 0 || $image_url === '') {
		wp_send_json_error(['message' => __('Invalid image replacement payload.', 'leadsforward-core')]);
	}
	if (!function_exists('lf_ai_get_inline_image_overrides') || !function_exists('lf_ai_set_inline_image_overrides')) {
		wp_send_json_error(['message' => __('Inline image override storage is unavailable.', 'leadsforward-core')]);
	}
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	$selector = trim($selector);
	if ($selector === '' || strlen($selector) > 500) {
		wp_send_json_error(['message' => __('Invalid selector payload.', 'leadsforward-core')]);
	}
	$current_map = lf_ai_get_inline_image_overrides($context_type, $context_id_use);
	$old_value = isset($current_map[$selector]) && is_array($current_map[$selector]) ? $current_map[$selector] : [];
	$new_value = [
		'attachment_id' => $attachment_id,
		'url' => $image_url,
		'alt' => $image_alt,
	];
	$current_map[$selector] = $new_value;
	lf_ai_set_inline_image_overrides($context_type, $context_id_use, $current_map);
	$log_id = function_exists('lf_ai_log_action')
		? lf_ai_log_action(
			$context_type,
			$context_id_use,
			['__img_override::' . $selector => $old_value],
			['__img_override::' . $selector => $new_value],
			'Inline frontend image replace'
		)
		: '';
	wp_send_json_success([
		'message' => __('Image replaced.', 'leadsforward-core'),
		'selector' => $selector,
		'image_url' => $image_url,
		'image_alt' => $image_alt,
		'attachment_id' => $attachment_id,
		'log_id' => $log_id,
	]);
}

function lf_ai_resolve_post_id_for_context(string $context_type, $context_id) {
	if ($context_type === 'homepage' || $context_id === 'homepage') {
		$front_id = (int) get_option('page_on_front');
		return $front_id > 0 ? $front_id : 0;
	}
	$pid = (int) $context_id;
	return $pid > 0 ? $pid : 0;
}

function lf_ai_collect_seo_snapshot_payload(int $post_id): array {
	$post = get_post($post_id);
	if (!$post instanceof \WP_Post) {
		return [
			'post_id' => 0,
			'post_type' => '',
			'post_title' => '',
			'permalink' => '',
			'intent' => '',
			'quality_score' => 0,
			'quality_grade' => '',
			'signals' => [],
			'backend_seo_score' => 0,
			'meta' => [],
			'checks' => [],
		];
	}
	$quality = function_exists('lf_seo_calculate_content_quality')
		? lf_seo_calculate_content_quality($post_id)
		: ['score' => (int) get_post_meta($post_id, '_lf_seo_quality_score', true), 'grade' => (string) get_post_meta($post_id, '_lf_seo_quality_grade', true), 'signals' => get_post_meta($post_id, '_lf_seo_quality_signals', true)];
	$quality_score = max(0, min(100, (int) ($quality['score'] ?? 0)));
	$quality_grade = sanitize_text_field((string) ($quality['grade'] ?? ''));
	$signals = $quality['signals'] ?? [];
	if (!is_array($signals)) {
		$signals = [];
	}

	$primary = trim((string) get_post_meta($post_id, '_lf_seo_primary_keyword', true));
	$secondary = trim((string) get_post_meta($post_id, '_lf_seo_secondary_keywords', true));
	$meta_title = trim((string) get_post_meta($post_id, '_lf_seo_meta_title', true));
	$meta_desc = trim((string) get_post_meta($post_id, '_lf_seo_meta_description', true));
	$canonical = trim((string) get_post_meta($post_id, '_lf_seo_canonical_url', true));
	$noindex = (string) get_post_meta($post_id, '_lf_seo_noindex', true) === '1';
	$nofollow = (string) get_post_meta($post_id, '_lf_seo_nofollow', true) === '1';
	$intent = trim((string) get_post_meta($post_id, '_lf_seo_serp_intent_detected', true));
	if ($intent === '' && function_exists('lf_seo_detect_serp_intent')) {
		$intent = (string) lf_seo_detect_serp_intent($post_id, $primary);
	}

	$checks = [];
	$checks[] = [
		'status' => $primary !== '' ? 'ok' : 'warn',
		'label' => __('Primary keyword', 'leadsforward-core'),
		'message' => $primary !== '' ? __('Primary keyword is set.', 'leadsforward-core') : __('Primary keyword is missing.', 'leadsforward-core'),
		'action' => $primary !== '' ? '' : __('Set one clear primary keyword for this page intent.', 'leadsforward-core'),
	];
	$checks[] = [
		'status' => $meta_title !== '' ? 'ok' : 'warn',
		'label' => __('Meta title', 'leadsforward-core'),
		'message' => $meta_title !== '' ? __('Custom meta title is set.', 'leadsforward-core') : __('Meta title fallback is being used.', 'leadsforward-core'),
		'action' => $meta_title !== '' ? '' : __('Set a custom title aligned to search intent.', 'leadsforward-core'),
	];
	$checks[] = [
		'status' => $meta_desc !== '' ? 'ok' : 'warn',
		'label' => __('Meta description', 'leadsforward-core'),
		'message' => $meta_desc !== '' ? __('Custom meta description is set.', 'leadsforward-core') : __('Meta description fallback is being used.', 'leadsforward-core'),
		'action' => $meta_desc !== '' ? '' : __('Set a custom description focused on value + CTA.', 'leadsforward-core'),
	];
	$checks[] = [
		'status' => $canonical !== '' ? 'ok' : 'warn',
		'label' => __('Canonical URL', 'leadsforward-core'),
		'message' => $canonical !== '' ? __('Canonical URL is set.', 'leadsforward-core') : __('Canonical URL not explicitly set.', 'leadsforward-core'),
		'action' => $canonical !== '' ? '' : __('Set canonical to avoid duplicate-url ambiguity.', 'leadsforward-core'),
	];
	$checks[] = [
		'status' => !$noindex ? 'ok' : 'error',
		'label' => __('Indexing', 'leadsforward-core'),
		'message' => !$noindex ? __('Page is indexable.', 'leadsforward-core') : __('Page is marked noindex.', 'leadsforward-core'),
		'action' => !$noindex ? '' : __('Remove noindex if this page should rank.', 'leadsforward-core'),
	];
	$checks[] = [
		'status' => $quality_score >= 80 ? 'ok' : ($quality_score >= 60 ? 'warn' : 'error'),
		'label' => __('Backend quality score', 'leadsforward-core'),
		'message' => sprintf(__('Current score: %d (%s).', 'leadsforward-core'), $quality_score, $quality_grade !== '' ? $quality_grade : '-'),
		'action' => $quality_score >= 80 ? '' : __('Increase depth, intent-match, internal linking, and metadata quality.', 'leadsforward-core'),
	];

	$backend_score = 100;
	if ($primary === '') $backend_score -= 12;
	if ($secondary === '') $backend_score -= 4;
	if ($meta_title === '') $backend_score -= 10;
	if ($meta_desc === '') $backend_score -= 10;
	if ($canonical === '') $backend_score -= 6;
	if ($noindex) $backend_score -= 24;
	if ($nofollow) $backend_score -= 5;
	$backend_score = (int) round(max(0, min(100, ($backend_score * 0.45) + ($quality_score * 0.55))));

	return [
		'post_id' => $post_id,
		'post_type' => (string) $post->post_type,
		'post_title' => (string) get_the_title($post_id),
		'permalink' => (string) get_permalink($post_id),
		'intent' => $intent,
		'quality_score' => $quality_score,
		'quality_grade' => $quality_grade,
		'signals' => array_values(array_map('sanitize_text_field', $signals)),
		'backend_seo_score' => $backend_score,
		'meta' => [
			'primary_keyword' => $primary,
			'secondary_keywords' => $secondary,
			'meta_title' => $meta_title,
			'meta_description' => $meta_desc,
			'canonical' => $canonical,
			'noindex' => $noindex,
			'nofollow' => $nofollow,
		],
		'checks' => $checks,
	];
}

function lf_ai_ajax_seo_snapshot(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	if ($context_type === '' || $context_id === '') {
		wp_send_json_error(['message' => __('Invalid SEO snapshot request.', 'leadsforward-core')]);
	}
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	$post_id = lf_ai_resolve_post_id_for_context($context_type, $context_id_use);
	if ($post_id <= 0) {
		wp_send_json_error(['message' => __('Unable to resolve target page for SEO snapshot.', 'leadsforward-core')]);
	}
	$payload = lf_ai_collect_seo_snapshot_payload($post_id);
	if (empty($payload['post_id'])) {
		wp_send_json_error(['message' => __('Target page is unavailable.', 'leadsforward-core')]);
	}
	wp_send_json_success($payload);
}

function lf_ai_ajax_apply(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id   = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	$assistant_mode = isset($_POST['assistant_mode']) ? sanitize_text_field(wp_unslash($_POST['assistant_mode'])) : 'edit_existing';
	$assistant_cpt_type = isset($_POST['assistant_cpt_type']) ? sanitize_text_field(wp_unslash($_POST['assistant_cpt_type'])) : '';
	$assistant_batch_type = isset($_POST['assistant_batch_type']) ? sanitize_text_field(wp_unslash($_POST['assistant_batch_type'])) : 'post';
	if (!in_array($assistant_mode, lf_ai_assistant_modes(), true)) {
		$assistant_mode = 'edit_existing';
	}
	$prompt_snippet = isset($_POST['prompt_snippet']) ? sanitize_textarea_field(wp_unslash($_POST['prompt_snippet'])) : '';
	$submitted_raw = isset($_POST['proposed']) ? wp_unslash((string) $_POST['proposed']) : '';
	$submitted_proposed = json_decode($submitted_raw, true);
	if (!is_array($submitted_proposed)) {
		$submitted_proposed = [];
	}
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	$selected_section_id_apply = isset($_POST['selected_section_id']) ? sanitize_text_field(wp_unslash($_POST['selected_section_id'])) : '';
	if ($assistant_mode === 'create_batch') {
		if (!in_array($assistant_batch_type, lf_ai_assistant_batch_types(), true)) {
			wp_send_json_error(['message' => __('Invalid batch type.', 'leadsforward-core')]);
		}
		$mapping = lf_ai_assistant_mode_and_cpt_for_batch_type($assistant_batch_type);
		$batch_mode = (string) ($mapping['mode'] ?? '');
		$batch_cpt = (string) ($mapping['cpt'] ?? '');
		$submitted_creation_raw = isset($_POST['creation_payload']) ? wp_unslash((string) $_POST['creation_payload']) : '';
		$submitted_creation = json_decode($submitted_creation_raw, true);
		$raw_items = is_array($submitted_creation['items'] ?? null) ? $submitted_creation['items'] : [];
		if (empty($raw_items)) {
			wp_send_json_error(['message' => __('Invalid batch payload. Generate again.', 'leadsforward-core')]);
		}
		$created_ids = [];
		$created_rows = [];
		foreach ($raw_items as $row) {
			if (!is_array($row)) {
				continue;
			}
			$payload = lf_ai_assistant_validate_creation_payload($row, $batch_mode, $batch_cpt);
			if (empty($payload)) {
				$mapped = lf_ai_assistant_mode_and_cpt_for_post_type((string) ($row['post_type'] ?? ''));
				if (($mapped['mode'] ?? '') !== '') {
					$payload = lf_ai_assistant_validate_creation_payload($row, (string) $mapped['mode'], (string) $mapped['cpt']);
				}
			}
			if (empty($payload)) {
				continue;
			}
			$created = lf_ai_assistant_create_post_from_payload($payload);
			if (empty($created['success'])) {
				continue;
			}
			$post_id = (int) ($created['post_id'] ?? 0);
			if ($post_id > 0) {
				$created_ids[] = $post_id;
				$created_rows[] = [
					'post_id' => $post_id,
					'edit_link' => (string) ($created['edit_link'] ?? ''),
					'view_link' => (string) ($created['view_link'] ?? ''),
					'title' => get_the_title($post_id),
				];
			}
		}
		if (empty($created_ids)) {
			wp_send_json_error(['message' => __('No drafts were created from this batch payload.', 'leadsforward-core')]);
		}
		$log_id = function_exists('lf_ai_log_creation_action')
			? lf_ai_log_creation_action($context_type, $context_id_use, $created_ids, $prompt_snippet)
			: '';
		wp_send_json_success([
			'created_batch' => true,
			'created_items' => $created_rows,
			'count' => count($created_rows),
			'log_id' => $log_id,
			'message' => sprintf(__('Created %d draft items.', 'leadsforward-core'), count($created_rows)),
		]);
	}
	if ($assistant_mode !== 'edit_existing') {
		$submitted_creation_raw = isset($_POST['creation_payload']) ? wp_unslash((string) $_POST['creation_payload']) : '';
		$submitted_creation = json_decode($submitted_creation_raw, true);
		if (!is_array($submitted_creation)) {
			$submitted_creation = [];
		}
		$payload = lf_ai_assistant_validate_creation_payload($submitted_creation, $assistant_mode, $assistant_cpt_type);
		if (empty($payload)) {
			$mapped = lf_ai_assistant_mode_and_cpt_for_post_type((string) ($submitted_creation['post_type'] ?? ''));
			if (($mapped['mode'] ?? '') !== '') {
				$payload = lf_ai_assistant_validate_creation_payload($submitted_creation, (string) $mapped['mode'], (string) $mapped['cpt']);
			}
		}
		if (empty($payload)) {
			wp_send_json_error(['message' => __('Invalid creation payload. Generate again.', 'leadsforward-core')]);
		}
		$created = lf_ai_assistant_create_post_from_payload($payload);
		if (empty($created['success'])) {
			wp_send_json_error(['message' => (string) ($created['message'] ?? __('Creation failed.', 'leadsforward-core'))]);
		}
		$post_id = (int) ($created['post_id'] ?? 0);
		$log_id = function_exists('lf_ai_log_creation_action')
			? lf_ai_log_creation_action($context_type, $context_id_use, [$post_id], $prompt_snippet)
			: '';
		wp_send_json_success([
			'created' => true,
			'post_id' => $post_id,
			'edit_link' => (string) ($created['edit_link'] ?? ''),
			'view_link' => (string) ($created['view_link'] ?? ''),
			'log_id' => $log_id,
			'message' => __('Draft created successfully.', 'leadsforward-core'),
		]);
	}
	$stored = lf_ai_get_stored_proposal($context_type, $context_id_use);
	$proposed = [];
	if ($stored && !empty($stored['proposed']) && is_array($stored['proposed'])) {
		$proposed = $stored['proposed'];
	} elseif (!empty($submitted_proposed)) {
		// Fallback: allow apply from client payload if transient key was lost.
		$editable = lf_get_ai_editable_fields($context_id_use);
		$t_store = lf_ai_get_stored_proposal($context_type, $context_id_use);
		$row_scope = is_array($t_store) ? (string) ($t_store['homepage_section_row_id'] ?? '') : '';
		if ($row_scope === '' && $context_type === 'homepage' && $selected_section_id_apply !== '') {
			$row_scope = $selected_section_id_apply;
		}
		foreach ($submitted_proposed as $key => $value) {
			if (!is_string($key) || !lf_is_field_ai_editable($key)) {
				continue;
			}
			if ($context_type === 'homepage' && $row_scope !== '') {
				$proposed[ $key ] = is_string($value) ? $value : (string) $value;
				continue;
			}
			if (!isset($editable[ $key ])) {
				continue;
			}
			$proposed[ $key ] = is_string($value) ? $value : (string) $value;
		}
	}
	if (empty($proposed)) {
		wp_send_json_error(['message' => __('No pending suggestions. Generate again.', 'leadsforward-core')]);
	}
	$result = lf_ai_apply_proposal($context_type, $context_id_use, $proposed, $prompt_snippet, $selected_section_id_apply);
	if (!$result['success']) {
		wp_send_json_error(['message' => __('Apply failed.', 'leadsforward-core')]);
	}
	wp_send_json_success(['log_id' => $result['log_id'], 'reload' => true]);
}

function lf_ai_ajax_rollback(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
	if ($id === '') {
		wp_send_json_error(['message' => __('Invalid request.', 'leadsforward-core')]);
	}
	$ok = lf_ai_rollback($id);
	if (!$ok) {
		wp_send_json_error(['message' => __('Rollback failed or already rolled back.', 'leadsforward-core')]);
	}
	wp_send_json_success(['reload' => true]);
}

function lf_ai_ajax_rollback_latest(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id   = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	if ($context_type === '' || $context_id === '') {
		wp_send_json_error(['message' => __('Invalid request.', 'leadsforward-core')]);
	}
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	$log_id = lf_ai_latest_rollback_candidate($context_type, $context_id_use, get_current_user_id());
	if ($log_id === '') {
		wp_send_json_error(['message' => __('No recent AI change found for this page.', 'leadsforward-core')]);
	}
	$ok = lf_ai_rollback($log_id);
	if (!$ok) {
		wp_send_json_error(['message' => __('Rollback failed or already rolled back.', 'leadsforward-core')]);
	}
	wp_send_json_success(['reload' => true, 'log_id' => $log_id]);
}

function lf_ai_ajax_redo_latest(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	if ($context_type === '' || $context_id === '') {
		wp_send_json_error(['message' => __('Invalid request.', 'leadsforward-core')]);
	}
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	$log_id = function_exists('lf_ai_latest_redo_candidate')
		? lf_ai_latest_redo_candidate($context_type, $context_id_use, get_current_user_id())
		: '';
	if ($log_id === '') {
		wp_send_json_error(['message' => __('No redo action found for this page.', 'leadsforward-core')]);
	}
	$ok = function_exists('lf_ai_redo') ? lf_ai_redo($log_id) : false;
	if (!$ok) {
		wp_send_json_error(['message' => __('Redo failed.', 'leadsforward-core')]);
	}
	wp_send_json_success(['reload' => true, 'log_id' => $log_id]);
}

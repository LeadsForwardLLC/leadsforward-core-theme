<?php
/**
 * Deterministic media intelligence + matching for orchestrated image assignment.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_MEDIA_INDEX_TRANSIENT = 'lf_media_index_cache';
const LF_MEDIA_VISION_OPTION = 'lf_media_vision_annotations';

add_action('add_attachment', 'lf_invalidate_media_index_cache');
add_action('edit_attachment', 'lf_invalidate_media_index_cache');
add_action('delete_attachment', 'lf_invalidate_media_index_cache');
add_action('add_attachment', 'lf_image_intelligence_finalize_uploaded_attachment', 20);
add_action('admin_enqueue_scripts', 'lf_image_intelligence_enqueue_upload_feedback_assets');
add_action('wp_enqueue_scripts', 'lf_image_intelligence_enqueue_upload_feedback_assets');
add_filter('wp_handle_upload_prefilter', 'lf_image_intelligence_upload_prefilter');
add_filter('wp_editor_set_quality', 'lf_image_intelligence_editor_quality', 10, 2);
add_filter('wp_generate_attachment_metadata', 'lf_image_intelligence_optimize_uploaded_image', 10, 2);
add_action('admin_menu', 'lf_image_intelligence_register_debug_page');

function lf_image_intelligence_enqueue_upload_feedback_assets(): void {
	if (!current_user_can('upload_files')) {
		return;
	}
	if (!function_exists('wp_enqueue_media')) {
		return;
	}
	wp_enqueue_media();
	wp_register_style('lf-image-intelligence-upload-feedback', false, [], LF_THEME_VERSION);
	wp_enqueue_style('lf-image-intelligence-upload-feedback');
	wp_add_inline_style('lf-image-intelligence-upload-feedback', '
		#lf-image-upload-feedback {
			position: fixed;
			right: 18px;
			bottom: 18px;
			z-index: 100001;
			min-width: 280px;
			max-width: min(420px, calc(100vw - 28px));
			background: #111827;
			color: #fff;
			border-radius: 12px;
			box-shadow: 0 14px 34px rgba(15, 23, 42, .35);
			padding: 12px 14px;
			font-size: 13px;
			line-height: 1.4;
			opacity: 0;
			transform: translateY(8px);
			pointer-events: none;
			transition: opacity .18s ease, transform .18s ease;
		}
		#lf-image-upload-feedback.is-visible {
			opacity: 1;
			transform: translateY(0);
		}
		#lf-image-upload-feedback .lf-upload-feedback__title {
			display: block;
			font-weight: 600;
			margin-bottom: 2px;
		}
		#lf-image-upload-feedback .lf-upload-feedback__meta {
			display: block;
			color: #cbd5e1;
			font-size: 12px;
		}
	');
	wp_register_script('lf-image-intelligence-upload-feedback', '', ['jquery'], LF_THEME_VERSION, true);
	wp_enqueue_script('lf-image-intelligence-upload-feedback');
	wp_add_inline_script('lf-image-intelligence-upload-feedback', '(function($){
		"use strict";
		if (!$ || typeof window.wp === "undefined") return;
		var activeCount = 0;
		var doneTimer = null;
		var toast = null;
		function getToast(){
			if (toast) return toast;
			toast = document.getElementById("lf-image-upload-feedback");
			if (toast) return toast;
			toast = document.createElement("div");
			toast.id = "lf-image-upload-feedback";
			toast.innerHTML = "<span class=\"lf-upload-feedback__title\"></span><span class=\"lf-upload-feedback__meta\"></span>";
			document.body.appendChild(toast);
			return toast;
		}
		function show(title, meta){
			var el = getToast();
			var titleNode = el.querySelector(".lf-upload-feedback__title");
			var metaNode = el.querySelector(".lf-upload-feedback__meta");
			if (titleNode) titleNode.textContent = String(title || "");
			if (metaNode) metaNode.textContent = String(meta || "");
			el.classList.add("is-visible");
		}
		function hideSoon(ms){
			if (doneTimer) {
				window.clearTimeout(doneTimer);
			}
			doneTimer = window.setTimeout(function(){
				var el = getToast();
				el.classList.remove("is-visible");
			}, ms || 1400);
		}
		function labelForCount(count){
			return count === 1 ? "image" : "images";
		}
		function bindPlupload(pl){
			if (!pl || pl.__lfUploadBound) return;
			pl.__lfUploadBound = true;
			pl.bind("FilesAdded", function(up, files){
				activeCount += Array.isArray(files) ? files.length : 0;
				show("Uploading and optimizing " + activeCount + " " + labelForCount(activeCount) + "...", "Compression and resizing run automatically.");
			});
			pl.bind("UploadProgress", function(up, file){
				var pct = file && typeof file.percent === "number" ? Math.max(0, Math.min(100, file.percent)) : 0;
				var msg = activeCount > 0 ? ("Processing " + activeCount + " " + labelForCount(activeCount)) : "Processing uploads";
				show(msg + " (" + pct + "%)", "Optimizing for performance.");
			});
			pl.bind("FileUploaded", function(){
				activeCount = Math.max(0, activeCount - 1);
				if (activeCount > 0) {
					show("Continuing optimization...", activeCount + " " + labelForCount(activeCount) + " remaining.");
				} else {
					show("Image optimization complete.", "Uploads are compressed and resized.");
					hideSoon(1600);
				}
			});
			pl.bind("Error", function(up, err){
				var message = err && err.message ? err.message : "Upload error";
				show("Upload issue detected.", message);
				hideSoon(2600);
			});
		}
		function bindKnownUploaders(){
			try {
				if (window.wp && wp.Uploader && wp.Uploader.queue && wp.Uploader.queue.uploader) {
					bindPlupload(wp.Uploader.queue.uploader);
				}
			} catch (e) {}
			try {
				if (window.plupload && Array.isArray(plupload.instances)) {
					plupload.instances.forEach(function(instance){ bindPlupload(instance); });
				}
			} catch (e) {}
		}
		$(document).ready(function(){
			bindKnownUploaders();
			window.setTimeout(bindKnownUploaders, 600);
			window.setTimeout(bindKnownUploaders, 1500);
		});
		$(document).on("click", ".media-modal, .media-frame-router, .upload-ui", function(){
			bindKnownUploaders();
		});
	})(jQuery);');
}

function lf_invalidate_media_index_cache(): void {
	delete_transient(LF_MEDIA_INDEX_TRANSIENT);
}

function lf_image_intelligence_get_vision_annotations(): array {
	$raw = get_option(LF_MEDIA_VISION_OPTION, []);
	return is_array($raw) ? $raw : [];
}

function lf_image_intelligence_update_vision_annotations(array $annotations): void {
	update_option(LF_MEDIA_VISION_OPTION, $annotations, false);
}

function lf_image_intelligence_normalize_filename(string $filename): string {
	$filename = strtolower(trim($filename));
	$filename = preg_replace('/\.[a-z0-9]+$/', '', $filename);
	$filename = preg_replace('/[^a-z0-9]+/', '-', $filename);
	$filename = trim((string) $filename, '-');
	return $filename;
}

/**
 * Turn workflow-suggested filenames into a safe attachment basename; reject junk like "...-jpg" from LLM output.
 */
function lf_image_intelligence_sanitize_recommended_attachment_basename(string $raw): string {
	$raw = trim($raw);
	if ($raw === '') {
		return '';
	}
	$base = $raw;
	if (preg_match('/\.[a-z0-9]{2,5}$/i', $raw) === 1) {
		$base = (string) preg_replace('/\.[a-z0-9]{2,5}$/i', '', $raw);
	}
	$slug = sanitize_title($base);
	if ($slug === '') {
		return '';
	}
	$bad_tokens = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'avif'];
	foreach ($bad_tokens as $tok) {
		if (preg_match('/(^|-)' . preg_quote($tok, '/') . '(-|$)/', $slug) === 1) {
			return '';
		}
	}
	if (strlen($slug) < 4) {
		return '';
	}
	return $slug;
}

/**
 * Whether an incoming filename already looks intentional for SEO (do not replace with a generic niche slug).
 *
 * @param array<string, string> $context primary_keyword, city, niche, business_name, service_name, etc.
 */
function lf_image_intelligence_should_preserve_original_upload_basename(string $original_name, array $context = []): bool {
	$base = pathinfo($original_name, PATHINFO_FILENAME);
	$base = trim((string) $base);
	if ($base === '') {
		return false;
	}
	$norm = lf_image_intelligence_normalize_filename($original_name);
	if ($norm === '' || $norm === 'image') {
		return false;
	}
	// Camera / export noise and WordPress placeholders.
	if (preg_match('/^(img|image|dsc|dscn|dscf|mvi|mov|pic|pict|photo|screenshot|screen[-_]?shot|wp[-_]?image|export|untitled|snapshot|scan)[-_]?\d*$/i', $norm) === 1) {
		return false;
	}
	if (preg_match('/^img[-_]?\d+$/i', $norm) === 1 || preg_match('/^photo[-_]?\d+$/i', $norm) === 1) {
		return false;
	}
	// UUID / hash-like tokens.
	if (preg_match('/^[a-f0-9\-]{24,}$/', $norm) === 1) {
		return false;
	}
	// Too little signal (allow slightly shorter intentional slugs).
	if (strlen($norm) < 6) {
		return false;
	}
	if (preg_match('/^[0-9\-]+$/', $norm) === 1) {
		return false;
	}
	$segments = array_values(array_filter(explode('-', $norm), static function ($s) {
		return strlen((string) $s) >= 2;
	}));
	if (count($segments) >= 2) {
		return true;
	}
	if (strlen($norm) >= 12 && preg_match('/[a-z]/', $norm) === 1) {
		return true;
	}
	foreach (['primary_keyword', 'city', 'niche', 'business_name', 'service_name'] as $key) {
		$chunk = trim((string) ($context[$key] ?? ''));
		if ($chunk === '') {
			continue;
		}
		$slug = sanitize_title($chunk);
		if ($slug !== '' && strlen($slug) >= 4 && strpos($norm, $slug) !== false) {
			return true;
		}
	}
	return false;
}

/**
 * Second-chance preservation for Website Manifester batch uploads when strict heuristics would rename to a niche slug.
 *
 * Callers should only use this on the Manifester upload path so generic camera filenames still get contextual names.
 */
function lf_image_intelligence_manifest_lenient_preserve_filename(string $original_name): bool {
	if ($original_name === '') {
		return false;
	}
	if (lf_image_intelligence_should_preserve_original_upload_basename($original_name, [])) {
		return true;
	}
	$norm = lf_image_intelligence_normalize_filename($original_name);
	if ($norm === '' || $norm === 'image' || strlen($norm) < 5) {
		return false;
	}
	if (preg_match('/^(img|image|dsc|dscn|dscf|mvi|mov|pic|pict|photo|screenshot|screen[-_]?shot|wp[-_]?image|export|untitled|snapshot|scan)[-_]?\d*$/i', $norm) === 1) {
		return false;
	}
	if (preg_match('/^img[-_]?\d+$/i', $norm) === 1 || preg_match('/^photo[-_]?\d+$/i', $norm) === 1) {
		return false;
	}
	if (preg_match('/^[a-f0-9\-]{24,}$/', $norm) === 1) {
		return false;
	}
	if (preg_match('/^[0-9\-]+$/', $norm) === 1) {
		return false;
	}
	return preg_match('/[a-z]/', $norm) === 1;
}

/**
 * Keep keyword-rich original names; otherwise generate a contextual slug (Manifester + media library uploads).
 *
 * @param array<string, string>|null $context_override Manifest / site context; null uses homepage defaults only.
 */
function lf_image_intelligence_resolve_upload_filename(string $original_name, ?array $context_override = null): string {
	$original_name = (string) $original_name;
	if ($original_name === '') {
		return lf_image_intelligence_generate_upload_filename($original_name, $context_override);
	}
	$ctx = is_array($context_override) && $context_override !== []
		? $context_override
		: lf_image_intelligence_upload_context_defaults();
	if (lf_image_intelligence_should_preserve_original_upload_basename($original_name, $ctx)) {
		return sanitize_file_name($original_name);
	}
	return lf_image_intelligence_generate_upload_filename($original_name, $context_override);
}

/**
 * @return int[]
 */
function lf_image_intelligence_get_logo_ids(): array {
	static $cache = null;
	if (is_array($cache)) {
		return $cache;
	}
	$ids = [];
	if (function_exists('lf_get_global_option')) {
		$ids[] = (int) lf_get_global_option('lf_global_logo', 0);
	}
	$ids[] = (int) get_option('options_lf_global_logo', 0);
	if (function_exists('lf_get_business_info_value')) {
		$ids[] = (int) lf_get_business_info_value('lf_business_logo', 0);
	}
	if (function_exists('lf_business_entity_get')) {
		$entity = lf_business_entity_get();
		if (is_array($entity)) {
			$ids[] = (int) ($entity['logo_id'] ?? 0);
		}
	}
	$ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
	$cache = $ids;
	return $ids;
}

function lf_image_intelligence_is_logo_id(int $attachment_id): bool {
	$attachment_id = (int) $attachment_id;
	if ($attachment_id <= 0) {
		return false;
	}
	return in_array($attachment_id, lf_image_intelligence_get_logo_ids(), true);
}

function lf_image_intelligence_is_placeholder_asset(int $attachment_id): bool {
	$attachment_id = (int) $attachment_id;
	if ($attachment_id <= 0) {
		return false;
	}
	$manual_skip = (string) get_post_meta($attachment_id, '_lf_skip_auto_distribution', true);
	if ($manual_skip === '1') {
		return true;
	}
	$file = (string) get_attached_file($attachment_id);
	$filename = strtolower((string) basename($file));
	$title = strtolower((string) get_the_title($attachment_id));
	$caption = strtolower((string) wp_get_attachment_caption($attachment_id));
	$stack = trim($filename . ' ' . $title . ' ' . $caption);
	$markers = [
		'placeholder',
		'stock-interior',
		'leadforward-placeholder',
		'sample-image',
	];
	foreach ($markers as $marker) {
		if ($marker !== '' && strpos($stack, $marker) !== false) {
			return true;
		}
	}
	return false;
}

function lf_image_intelligence_upload_context_defaults(): array {
	$keywords = get_option('lf_homepage_keywords', []);
	$primary = is_array($keywords) ? sanitize_text_field((string) ($keywords['primary'] ?? '')) : '';
	$city = sanitize_text_field((string) get_option('lf_homepage_city', ''));
	$niche = sanitize_text_field((string) (defined('LF_HOMEPAGE_NICHE_OPTION') ? get_option(LF_HOMEPAGE_NICHE_OPTION, '') : ''));
	$business = '';
	if (function_exists('lf_business_entity_get')) {
		$entity = lf_business_entity_get();
		$business = sanitize_text_field((string) ($entity['name'] ?? ''));
	}
	if ($business === '') {
		$business = sanitize_text_field((string) get_bloginfo('name'));
	}
	$service_name = $primary !== '' ? $primary : ($niche !== '' ? $niche : ($business !== '' ? $business : __('service image', 'leadsforward-core')));
	return [
		'primary_keyword' => $primary,
		'city' => $city,
		'niche' => $niche,
		'business_name' => $business,
		'service_name' => $service_name,
	];
}

/**
 * Richer context for manifest-step uploads using stored manifest when present.
 *
 * @return array{primary_keyword:string,city:string,niche:string,business_name:string,service_name:string}
 */
function lf_image_intelligence_manifest_upload_context(): array {
	$base = lf_image_intelligence_upload_context_defaults();
	$manifest = function_exists('lf_ai_studio_get_manifest') ? lf_ai_studio_get_manifest() : [];
	if (!is_array($manifest) || $manifest === []) {
		return $base;
	}
	$biz = is_array($manifest['business'] ?? null) ? $manifest['business'] : [];
	$name = sanitize_text_field((string) ($biz['name'] ?? ''));
	$niche = sanitize_text_field((string) ($biz['niche'] ?? ''));
	$addr = is_array($biz['address'] ?? null) ? $biz['address'] : [];
	$city = sanitize_text_field((string) ($biz['primary_city'] ?? ($addr['city'] ?? '')));
	if ($city === '') {
		$city = (string) ($base['city'] ?? '');
	}
	$hp = is_array($manifest['homepage'] ?? null) ? $manifest['homepage'] : [];
	$primary = sanitize_text_field((string) ($hp['primary_keyword'] ?? ''));
	if ($primary === '') {
		$primary = (string) ($base['primary_keyword'] ?? '');
	}
	$first_service = '';
	$services = $manifest['services'] ?? [];
	if (is_array($services)) {
		foreach ($services as $svc) {
			if (!is_array($svc)) {
				continue;
			}
			$t = sanitize_text_field((string) ($svc['name'] ?? ($svc['title'] ?? '')));
			if ($t !== '') {
				$first_service = $t;
				break;
			}
		}
	}
	$business = $name !== '' ? $name : (string) ($base['business_name'] ?? '');
	$niche_out = $niche !== '' ? $niche : (string) ($base['niche'] ?? '');
	$service_name = $primary !== '' ? $primary : ($first_service !== '' ? $first_service : ($niche_out !== '' ? $niche_out : ($business !== '' ? $business : (string) ($base['service_name'] ?? ''))));

	return [
		'primary_keyword' => $primary,
		'city' => $city,
		'niche' => $niche_out,
		'business_name' => $business,
		'service_name' => $service_name,
	];
}

function lf_image_intelligence_upload_base_slug_for_context(array $context): string {
	$parts = array_filter([
		(string) ($context['primary_keyword'] ?? ''),
		(string) ($context['city'] ?? ''),
		(string) ($context['niche'] ?? ''),
	]);
	if ($parts === []) {
		$parts = array_filter([
			(string) ($context['business_name'] ?? ''),
			(string) ($context['city'] ?? ''),
			(string) ($context['niche'] ?? ''),
			'image',
		]);
	}
	$slug = sanitize_title(implode(' ', $parts));
	return $slug !== '' ? $slug : 'local-service-image';
}

function lf_image_intelligence_upload_base_slug(): string {
	return lf_image_intelligence_upload_base_slug_for_context(lf_image_intelligence_upload_context_defaults());
}

function lf_image_intelligence_next_upload_index(): int {
	$current = (int) get_option('lf_image_upload_counter', 0);
	$next = $current + 1;
	update_option('lf_image_upload_counter', $next, false);
	return $next;
}

function lf_image_intelligence_generate_upload_filename(string $original_name, ?array $context_override = null): string {
	$ext = strtolower((string) pathinfo($original_name, PATHINFO_EXTENSION));
	if ($ext === '') {
		$ext = 'jpg';
	}
	$base = is_array($context_override)
		? lf_image_intelligence_upload_base_slug_for_context($context_override)
		: lf_image_intelligence_upload_base_slug();
	$index = lf_image_intelligence_next_upload_index();
	return sprintf('%s-%03d.%s', $base, $index, $ext);
}

function lf_image_intelligence_rename_attachment_file(int $attachment_id, string $target_base_slug): bool {
	$attachment_id = (int) $attachment_id;
	$target_base_slug = sanitize_title($target_base_slug);
	if ($attachment_id <= 0 || $target_base_slug === '') {
		return false;
	}
	$current_path = (string) get_attached_file($attachment_id);
	if ($current_path === '' || !file_exists($current_path)) {
		return false;
	}
	$dir = dirname($current_path);
	$ext = strtolower((string) pathinfo($current_path, PATHINFO_EXTENSION));
	if ($ext === '') {
		$ext = 'jpg';
	}
	$target_name = wp_unique_filename($dir, $target_base_slug . '.' . $ext);
	$target_path = trailingslashit($dir) . $target_name;
	if ($target_path === $current_path) {
		return true;
	}
	$renamed = @rename($current_path, $target_path);
	if (!$renamed) {
		return false;
	}
	update_attached_file($attachment_id, $target_path);
	$metadata = wp_get_attachment_metadata($attachment_id);
	$upload_dir = wp_get_upload_dir();
	$basedir = (string) ($upload_dir['basedir'] ?? '');
	if (is_array($metadata) && $basedir !== '') {
		$relative = ltrim(str_replace(trailingslashit($basedir), '', $target_path), '/');
		$metadata['file'] = $relative;
		wp_update_attachment_metadata($attachment_id, $metadata);
	}
	return true;
}

function lf_image_intelligence_upload_prefilter(array $file): array {
	$name = (string) ($file['name'] ?? '');
	if ($name === '') {
		return $file;
	}
	$type = wp_check_filetype($name);
	$mime = (string) ($type['type'] ?? '');
	if (strpos($mime, 'image/') !== 0) {
		return $file;
	}
	// Keep operator / workflow filenames: do not rewrite uploads to niche-keyword slugs here.
	// Website Manifester still runs contextual naming in lf_ai_studio_process_images_upload when needed.
	$file['name'] = sanitize_file_name($name);
	return $file;
}

function lf_image_intelligence_editor_quality(int $quality, string $mime_type): int {
	if (in_array($mime_type, ['image/jpeg', 'image/webp', 'image/avif'], true)) {
		return 72;
	}
	return $quality;
}

function lf_image_intelligence_optimize_uploaded_image(array $metadata, int $attachment_id): array {
	// Global toggle: allow disabling optimization without a plugin.
	$enabled = get_option('lf_tools_image_optimization', '1');
	if (!($enabled === '1' || $enabled === 1 || $enabled === true)) {
		return $metadata;
	}
	$attachment_id = (int) $attachment_id;
	if ($attachment_id <= 0) {
		return $metadata;
	}
	if ((string) get_post_meta($attachment_id, '_lf_image_processing_lock', true) === '1') {
		return $metadata;
	}
	update_post_meta($attachment_id, '_lf_image_processing_lock', '1');
	if ((string) get_post_meta($attachment_id, '_lf_image_optimized', true) === '1') {
		delete_post_meta($attachment_id, '_lf_image_processing_lock');
		return $metadata;
	}
	$mime = (string) get_post_mime_type($attachment_id);
	if (strpos($mime, 'image/') !== 0) {
		delete_post_meta($attachment_id, '_lf_image_processing_lock');
		return $metadata;
	}
	$file = (string) get_attached_file($attachment_id);
	if ($file === '' || !file_exists($file)) {
		delete_post_meta($attachment_id, '_lf_image_processing_lock');
		return $metadata;
	}
	if ($mime === 'image/png' && (string) get_post_meta($attachment_id, '_lf_image_png_converted', true) !== '1') {
		if (!lf_image_intelligence_png_has_transparency($file)) {
			$converted = lf_image_intelligence_convert_png_to_jpeg($attachment_id, $file);
			if (is_array($converted)) {
				update_post_meta($attachment_id, '_lf_image_png_converted', '1');
				update_post_meta($attachment_id, '_lf_image_optimized', '1');
				delete_post_meta($attachment_id, '_lf_image_processing_lock');
				return $converted;
			}
		}
	}
	$editor = wp_get_image_editor($file);
	if (is_wp_error($editor)) {
		delete_post_meta($attachment_id, '_lf_image_processing_lock');
		return $metadata;
	}
	$size = method_exists($editor, 'get_size') ? $editor->get_size() : [];
	$width = (int) ($size['width'] ?? 0);
	$height = (int) ($size['height'] ?? 0);
	if ($width > 0 && $height > 0 && ($width > 1600 || $height > 1600)) {
		$editor->resize(1600, 1600, false);
	}
	if (method_exists($editor, 'set_quality')) {
		$editor->set_quality(72);
	}
	$saved = $editor->save($file);
	if (!is_wp_error($saved)) {
		$current_size = @filesize($file);
		if (is_int($current_size) && $current_size > 120000) {
			if (method_exists($editor, 'set_quality')) {
				$editor->set_quality(62);
			}
			$editor->save($file);
		}
	}
	if (!is_wp_error($saved)) {
		update_post_meta($attachment_id, '_lf_image_optimized', '1');
		lf_image_intelligence_store_image_profile($attachment_id);
	}
	delete_post_meta($attachment_id, '_lf_image_processing_lock');
	return $metadata;
}

function lf_image_intelligence_store_image_profile(int $attachment_id): void {
	$file = (string) get_attached_file($attachment_id);
	if ($file === '' || !file_exists($file)) {
		return;
	}
	$size = @getimagesize($file);
	$width = is_array($size) ? (int) ($size[0] ?? 0) : 0;
	$height = is_array($size) ? (int) ($size[1] ?? 0) : 0;
	$bytes = @filesize($file);
	$mime = (string) get_post_mime_type($attachment_id);
	$profile = [
		'width' => $width,
		'height' => $height,
		'bytes' => is_int($bytes) ? $bytes : 0,
		'mime' => $mime,
		'ext' => strtolower((string) pathinfo($file, PATHINFO_EXTENSION)),
		'updated' => time(),
	];
	update_post_meta($attachment_id, '_lf_image_profile', $profile);
}

function lf_image_intelligence_png_has_transparency(string $file): bool {
	if ($file === '' || !file_exists($file)) {
		return false;
	}
	$handle = @fopen($file, 'rb');
	if (!$handle) {
		return false;
	}
	$header = fread($handle, 64 * 1024);
	fclose($handle);
	if (!is_string($header) || strlen($header) < 33) {
		return false;
	}
	// PNG color type byte in IHDR chunk payload (offset 25)
	$color_type = ord($header[25]);
	if (in_array($color_type, [4, 6], true)) {
		return true;
	}
	// Palette transparency chunk
	return strpos($header, 'tRNS') !== false;
}

function lf_image_intelligence_convert_png_to_jpeg(int $attachment_id, string $source_file): array {
	$attachment_id = (int) $attachment_id;
	if ($attachment_id <= 0 || $source_file === '' || !file_exists($source_file)) {
		return [];
	}
	$editor = wp_get_image_editor($source_file);
	if (is_wp_error($editor)) {
		return [];
	}
	if (method_exists($editor, 'set_quality')) {
		$editor->set_quality(72);
	}
	$dir = dirname($source_file);
	$base = sanitize_title((string) pathinfo($source_file, PATHINFO_FILENAME));
	if ($base === '') {
		$base = 'image';
	}
	$jpg_name = wp_unique_filename($dir, $base . '.jpg');
	$jpg_path = trailingslashit($dir) . $jpg_name;
	$saved = $editor->save($jpg_path, 'image/jpeg');
	if (is_wp_error($saved)) {
		return [];
	}
	update_attached_file($attachment_id, $jpg_path);
	wp_update_post([
		'ID' => $attachment_id,
		'post_mime_type' => 'image/jpeg',
	]);
	$new_metadata = wp_generate_attachment_metadata($attachment_id, $jpg_path);
	if (is_array($new_metadata)) {
		wp_update_attachment_metadata($attachment_id, $new_metadata);
	}
	lf_image_intelligence_store_image_profile($attachment_id);
	if ($source_file !== $jpg_path && file_exists($source_file)) {
		@unlink($source_file);
	}
	return is_array($new_metadata) ? $new_metadata : [];
}

/**
 * @return string[]
 */
function lf_image_intelligence_tokenize(string $value): array {
	$value = strtolower(trim($value));
	if ($value === '') {
		return [];
	}
	$value = preg_replace('/[^a-z0-9]+/', ' ', $value);
	$parts = preg_split('/\s+/', (string) $value);
	if (!is_array($parts)) {
		return [];
	}
	$tokens = array_values(array_unique(array_filter(array_map('trim', $parts))));
	return $tokens;
}

/**
 * Build and cache full image metadata/token index.
 *
 * @return array<int,array<string,mixed>>
 */
function lf_build_media_index(): array {
	$cached = get_transient(LF_MEDIA_INDEX_TRANSIENT);
	if (is_array($cached)) {
		return $cached;
	}

	$ids = get_posts([
		'post_type' => 'attachment',
		'post_mime_type' => 'image',
		'post_status' => 'inherit',
		'posts_per_page' => -1,
		'orderby' => 'ID',
		'order' => 'ASC',
		'fields' => 'ids',
		'no_found_rows' => true,
	]);

	$index = [];
	$logo_ids = lf_image_intelligence_get_logo_ids();
	$vision_map = lf_image_intelligence_get_vision_annotations();
	foreach ($ids as $attachment_id) {
		$attachment_id = (int) $attachment_id;
		if ($attachment_id > 0 && in_array($attachment_id, $logo_ids, true)) {
			continue;
		}
		if (lf_image_intelligence_is_placeholder_asset($attachment_id)) {
			continue;
		}
		$file = (string) get_attached_file($attachment_id);
		$filename = $file !== '' ? basename($file) : '';
		if ($filename === '') {
			continue;
		}
		$normalized_filename = lf_image_intelligence_normalize_filename($filename);
		$title = (string) get_the_title($attachment_id);
		$alt = (string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
		$caption = (string) wp_get_attachment_caption($attachment_id);
		$attached_post_id = (int) get_post_field('post_parent', $attachment_id);

		$tokens = [];
		$tokens = array_merge($tokens, lf_image_intelligence_tokenize($normalized_filename));
		$tokens = array_merge($tokens, lf_image_intelligence_tokenize($title));
		$tokens = array_merge($tokens, lf_image_intelligence_tokenize($alt));
		$tokens = array_merge($tokens, lf_image_intelligence_tokenize($caption));
		$tokens = array_values(array_unique($tokens));
		$vision = is_array($vision_map[$attachment_id] ?? null) ? $vision_map[$attachment_id] : [];
		$vision_description = (string) ($vision['description'] ?? '');
		$vision_alt = (string) ($vision['alt_text'] ?? '');
		$vision_keywords = is_array($vision['keywords'] ?? null) ? $vision['keywords'] : [];
		$vision_service_slugs = is_array($vision['service_slugs'] ?? null) ? $vision['service_slugs'] : [];
		$vision_area_slugs = is_array($vision['area_slugs'] ?? null) ? $vision['area_slugs'] : [];
		$vision_page_types = is_array($vision['page_types'] ?? null) ? $vision['page_types'] : [];
		$vision_slots = is_array($vision['slots'] ?? null) ? $vision['slots'] : [];
		$vision_keywords = array_values(array_filter(array_map('sanitize_text_field', $vision_keywords)));
		$tokens = array_merge($tokens, lf_image_intelligence_tokenize($vision_description));
		$tokens = array_merge($tokens, lf_image_intelligence_tokenize($vision_alt));
		$tokens = array_merge($tokens, lf_image_intelligence_tokenize(implode(' ', $vision_keywords)));
		$tokens = array_values(array_unique($tokens));

		$index[] = [
			'id' => $attachment_id,
			'filename' => $filename,
			'normalized_filename' => $normalized_filename,
			'title' => $title,
			'alt' => $alt,
			'caption' => $caption,
			'attached_post_id' => $attached_post_id,
			'tokens' => $tokens,
			'vision_description' => $vision_description,
			'vision_alt_text' => $vision_alt,
			'vision_keywords' => $vision_keywords,
			'vision_service_slugs' => array_values(array_map('sanitize_title', $vision_service_slugs)),
			'vision_area_slugs' => array_values(array_map('sanitize_title', $vision_area_slugs)),
			'vision_page_types' => array_values(array_map('sanitize_key', $vision_page_types)),
			'vision_slots' => array_values(array_map('sanitize_key', $vision_slots)),
		];
	}

	usort($index, static function (array $a, array $b): int {
		$name_cmp = strcmp((string) ($a['normalized_filename'] ?? ''), (string) ($b['normalized_filename'] ?? ''));
		if ($name_cmp !== 0) {
			return $name_cmp;
		}
		return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
	});

	set_transient(LF_MEDIA_INDEX_TRANSIENT, $index, 12 * HOUR_IN_SECONDS);
	return $index;
}

/**
 * @param string[] $needles
 * @param string[] $haystack
 */
function lf_image_intelligence_count_matches(array $needles, array $haystack): int {
	if (empty($needles) || empty($haystack)) {
		return 0;
	}
	$map = array_fill_keys($haystack, true);
	$count = 0;
	foreach ($needles as $needle) {
		if (isset($map[$needle])) {
			$count++;
		}
	}
	return $count;
}

/**
 * @return array<string,mixed>
 */
function lf_image_intelligence_score_item(array $item, array $context): array {
	$tokens = is_array($item['tokens'] ?? null) ? $item['tokens'] : [];
	$normalized_filename = (string) ($item['normalized_filename'] ?? '');
	$service_slug = (string) ($context['service_slug'] ?? '');
	$city_slug = (string) ($context['city_slug'] ?? '');
	$niche_slug = (string) ($context['niche_slug'] ?? '');
	$primary_tokens = is_array($context['primary_tokens'] ?? null) ? $context['primary_tokens'] : [];
	$secondary_tokens = is_array($context['secondary_tokens'] ?? null) ? $context['secondary_tokens'] : [];

	$service_exact = $service_slug !== '' && strpos($normalized_filename, $service_slug) !== false;
	$city_exact = $city_slug !== '' && in_array($city_slug, $tokens, true);
	$niche_exact = $niche_slug !== '' && in_array($niche_slug, $tokens, true);
	$primary_count = lf_image_intelligence_count_matches($primary_tokens, $tokens);
	$secondary_count = lf_image_intelligence_count_matches($secondary_tokens, $tokens);
	$is_generic = in_array('general', $tokens, true);
	$area_slug = (string) ($context['area_slug'] ?? '');
	$page_type = (string) ($context['page_type'] ?? '');
	$vision_service = $service_slug !== '' && in_array($service_slug, is_array($item['vision_service_slugs'] ?? null) ? $item['vision_service_slugs'] : [], true);
	$vision_area = $area_slug !== '' && in_array($area_slug, is_array($item['vision_area_slugs'] ?? null) ? $item['vision_area_slugs'] : [], true);
	$vision_page = $page_type !== '' && in_array($page_type, is_array($item['vision_page_types'] ?? null) ? $item['vision_page_types'] : [], true);

	return [
		'vision_service' => $vision_service ? 1 : 0,
		'vision_area' => $vision_area ? 1 : 0,
		'vision_page' => $vision_page ? 1 : 0,
		'service_exact' => $service_exact ? 1 : 0,
		'city_exact' => $city_exact ? 1 : 0,
		'niche_exact' => $niche_exact ? 1 : 0,
		'primary_match' => $primary_count > 0 ? 1 : 0,
		'secondary_match' => $secondary_count > 0 ? 1 : 0,
		'generic' => $is_generic ? 1 : 0,
		'primary_count' => $primary_count,
		'secondary_count' => $secondary_count,
	];
}

/**
 * @return array<int,mixed>
 */
function lf_image_intelligence_score_vector(array $score): array {
	return [
		(int) ($score['vision_slot'] ?? 0),
		(int) ($score['vision_service'] ?? 0),
		(int) ($score['vision_area'] ?? 0),
		(int) ($score['vision_page'] ?? 0),
		(int) ($score['service_exact'] ?? 0),
		(int) ($score['city_exact'] ?? 0),
		(int) ($score['niche_exact'] ?? 0),
		(int) ($score['primary_match'] ?? 0),
		(int) ($score['secondary_match'] ?? 0),
		(int) ($score['generic'] ?? 0),
		(int) ($score['primary_count'] ?? 0),
		(int) ($score['secondary_count'] ?? 0),
	];
}

/**
 * @param array<int,mixed> $a
 * @param array<int,mixed> $b
 */
function lf_image_intelligence_compare_vectors(array $a, array $b): int {
	$len = max(count($a), count($b));
	for ($i = 0; $i < $len; $i++) {
		$left = (int) ($a[$i] ?? 0);
		$right = (int) ($b[$i] ?? 0);
		if ($left === $right) {
			continue;
		}
		return $right <=> $left;
	}
	return 0;
}

function lf_image_intelligence_seed_hash(string $seed, string $slot, string $filename): int {
	return abs((int) crc32($seed . '|' . $slot . '|' . $filename));
}

function lf_image_intelligence_context_discriminator(array $context): string {
	$parts = [
		(string) ($context['page_type'] ?? ''),
		(string) ($context['service_slug'] ?? ''),
		(string) ($context['area_slug'] ?? ''),
		(string) ($context['city_slug'] ?? ''),
	];
	$tokens = is_array($context['primary_tokens'] ?? null) ? $context['primary_tokens'] : [];
	if (!empty($tokens)) {
		$parts[] = implode('-', array_slice($tokens, 0, 3));
	}
	return implode('|', array_filter($parts));
}

/**
 * @param array<int,array<string,mixed>> $index
 * @param array<string,mixed>            $context
 * @param int[]                          $used_ids
 */
function lf_image_intelligence_pick_slot(array $index, array $context, string $slot, array $used_ids = []): int {
	if (empty($index)) {
		return 0;
	}
	$seed = (string) ($context['variation_seed'] ?? get_option('lf_homepage_variation_seed', 'lf-default-seed'));
	$candidates = [];
	foreach ($index as $item) {
		$id = (int) ($item['id'] ?? 0);
		if ($id <= 0 || in_array($id, $used_ids, true) || lf_image_intelligence_is_logo_id($id)) {
			continue;
		}
		$score = lf_image_intelligence_score_item($item, $context);
		$vision_slots = is_array($item['vision_slots'] ?? null) ? $item['vision_slots'] : [];
		$score['vision_slot'] = in_array($slot, $vision_slots, true) ? 1 : 0;
		$candidates[] = [
			'id' => $id,
			'filename' => (string) ($item['normalized_filename'] ?? ''),
			'vector' => lf_image_intelligence_score_vector($score),
			'hash' => lf_image_intelligence_seed_hash(
				$seed . '|' . lf_image_intelligence_context_discriminator($context),
				$slot,
				(string) ($item['normalized_filename'] ?? '')
			),
		];
	}
	if (empty($candidates)) {
		return 0;
	}
	usort($candidates, static function (array $a, array $b): int {
		$vector_cmp = lf_image_intelligence_compare_vectors($a['vector'] ?? [], $b['vector'] ?? []);
		if ($vector_cmp !== 0) {
			return $vector_cmp;
		}
		$hash_cmp = ((int) ($a['hash'] ?? 0)) <=> ((int) ($b['hash'] ?? 0));
		if ($hash_cmp !== 0) {
			return $hash_cmp;
		}
		return strcmp((string) ($a['filename'] ?? ''), (string) ($b['filename'] ?? ''));
	});
	return (int) ($candidates[0]['id'] ?? 0);
}

/**
 * Match deterministic media assets for a content context.
 *
 * @param array<string,mixed> $context
 * @return array<string,int>
 */
function lf_match_images_for_context(array $context): array {
	$index = lf_build_media_index();
	$slots = ['hero', 'content_image_a', 'featured'];
	$out = [
		'hero' => 0,
		'content_image_a' => 0,
		'image_content_b' => 0,
		'content_image_c' => 0,
		'featured' => 0,
	];
	if (empty($index)) {
		return $out;
	}

	$service_slug = sanitize_title((string) ($context['service_slug'] ?? ''));
	$area_slug = sanitize_title((string) ($context['area_slug'] ?? ''));
	$niche_slug = sanitize_title((string) ($context['niche'] ?? ''));
	$city_slug = sanitize_title((string) ($context['city'] ?? get_option('lf_homepage_city', '')));
	$primary_keyword = trim((string) ($context['primary_keyword'] ?? ''));
	$secondary = $context['secondary_keywords'] ?? [];
	if (is_string($secondary)) {
		$secondary = preg_split('/\r\n|\r|\n|,/', $secondary);
	}
	if (!is_array($secondary)) {
		$secondary = [];
	}
	$secondary = array_values(array_filter(array_map('sanitize_text_field', $secondary)));

	$score_context = [
		'page_type' => (string) ($context['page_type'] ?? ''),
		'service_slug' => $service_slug,
		'area_slug' => $area_slug,
		'niche_slug' => $niche_slug,
		'city_slug' => $city_slug,
		'primary_tokens' => lf_image_intelligence_tokenize($primary_keyword),
		'secondary_tokens' => lf_image_intelligence_tokenize(implode(' ', $secondary)),
		'variation_seed' => (string) ($context['variation_seed'] ?? ''),
	];

	$used = [];
	foreach ($slots as $slot) {
		$pick = lf_image_intelligence_pick_slot($index, $score_context, $slot, $used);
		if ($pick > 0) {
			$out[$slot] = $pick;
			$used[] = $pick;
		}
	}
	return $out;
}

/**
 * @return array<string,mixed>
 */
function lf_image_intelligence_build_context_for_post(\WP_Post $post, array $overrides = []): array {
	$post_type = (string) $post->post_type;
	$page_type = 'overview';
	if ($post_type === 'lf_service') {
		$page_type = 'service';
	} elseif ($post_type === 'lf_service_area') {
		$page_type = 'service_area';
	} elseif ($post_type === 'page') {
		$page_type = $post->post_name === 'home' ? 'homepage' : 'overview';
	} elseif ($post_type === 'post') {
		$page_type = 'overview';
	}
	$secondary = (string) get_post_meta((int) $post->ID, '_lf_seo_secondary_keywords', true);
	$secondary_list = $secondary === '' ? [] : preg_split('/\r\n|\r|\n|,/', $secondary);
	$secondary_list = is_array($secondary_list) ? array_values(array_filter(array_map('sanitize_text_field', $secondary_list))) : [];

	$context = [
		'page_type' => $page_type,
		'service_slug' => $post_type === 'lf_service' ? $post->post_name : '',
		'area_slug' => $post_type === 'lf_service_area' ? $post->post_name : '',
		'niche' => (string) (defined('LF_HOMEPAGE_NICHE_OPTION') ? get_option(LF_HOMEPAGE_NICHE_OPTION, '') : ''),
		'city' => (string) get_option('lf_homepage_city', ''),
		'primary_keyword' => (string) get_post_meta((int) $post->ID, '_lf_seo_primary_keyword', true),
		'secondary_keywords' => $secondary_list,
		'variation_seed' => (string) get_option('lf_homepage_variation_seed', ''),
		'service_name' => (string) get_the_title($post),
	];
	return array_merge($context, $overrides);
}

function lf_image_intelligence_is_logo_asset(int $attachment_id, array $context): bool {
	$file = (string) get_attached_file($attachment_id);
	$filename = strtolower((string) basename($file));
	$title = strtolower((string) get_the_title($attachment_id));
	$haystack = $filename . ' ' . $title;
	if (strpos($haystack, 'logo') !== false || strpos($haystack, 'icon') !== false || strpos($haystack, 'brandmark') !== false) {
		return true;
	}
	$mime = (string) get_post_mime_type($attachment_id);
	if ($mime === 'image/png' && $file !== '' && function_exists('lf_image_intelligence_png_has_transparency')) {
		return lf_image_intelligence_png_has_transparency($file);
	}
	return false;
}

function lf_image_intelligence_alt_needs_upgrade(string $alt): bool {
	$alt = trim(wp_strip_all_tags($alt));
	if ($alt === '') {
		return true;
	}
	$lower = strtolower($alt);
	$generic = ['image', 'photo', 'upload', 'placeholder'];
	foreach ($generic as $word) {
		if ($lower === $word || strpos($lower, $word . ' ') === 0) {
			return true;
		}
	}
	if (function_exists('mb_strlen') ? mb_strlen($alt) < 16 : strlen($alt) < 16) {
		return true;
	}
	return false;
}

function lf_image_intelligence_maybe_set_alt_text(int $attachment_id, array $context): void {
	if ($attachment_id <= 0) {
		return;
	}
	$current_alt = trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true));
	if (!lf_image_intelligence_alt_needs_upgrade($current_alt)) {
		return;
	}
	$business = '';
	if (function_exists('lf_business_entity_get')) {
		$entity = lf_business_entity_get();
		$business = trim((string) ($entity['name'] ?? ''));
	}
	if ($business === '') {
		$business = trim((string) get_bloginfo('name'));
	}
	$city = trim((string) ($context['city'] ?? get_option('lf_homepage_city', '')));
	$service_name = trim((string) ($context['service_name'] ?? ''));
	$primary = trim((string) ($context['primary_keyword'] ?? ''));
	$vision_description = trim((string) get_post_meta($attachment_id, '_lf_vision_description', true));
	if (lf_image_intelligence_is_logo_asset($attachment_id, $context)) {
		$logo_alt = $business !== '' ? sprintf('%s logo', $business) : __('Company logo', 'leadsforward-core');
		update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($logo_alt));
		return;
	}
	if ($service_name === '') {
		$service_name = $primary;
	}
	if ($service_name === '' && $vision_description !== '') {
		$service_name = $vision_description;
	}
	$alt_parts = [];
	if ($service_name !== '') {
		$alt_parts[] = $service_name;
	}
	if ($city !== '' && stripos($service_name, $city) === false) {
		$alt_parts[] = sprintf(__('in %s', 'leadsforward-core'), $city);
	}
	$alt = trim(implode(' ', $alt_parts));
	if ($alt === '' && $primary !== '') {
		$alt = $primary;
	}
	if ($alt === '' && $vision_description !== '') {
		$alt = $vision_description;
	}
	if ($alt === '' && $business !== '') {
		$alt = sprintf(__('%s project image', 'leadsforward-core'), $business);
	}
	$alt = trim(preg_replace('/\s+/', ' ', $alt));
	if (function_exists('mb_substr') && mb_strlen($alt) > 120) {
		$alt = rtrim(mb_substr($alt, 0, 120));
	}
	if ($alt !== '') {
		update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt));
	}
}

function lf_image_intelligence_clean_media_text(string $text): string {
	$text = wp_strip_all_tags($text);
	$text = preg_replace('/\s+/', ' ', trim($text));
	if ($text === '') {
		return '';
	}
	// Remove machine-sounding SEO jargon from media metadata.
	$patterns = [
		'/\boptimized local seo asset\b/i',
		'/\boptimized for seo\b/i',
		'/\bfor section distribution\b/i',
		'/\bfeatured imagery\b/i',
		'/\bsection distribution\b/i',
	];
	foreach ($patterns as $pattern) {
		$text = preg_replace($pattern, '', $text);
	}
	$text = preg_replace('/\s+/', ' ', trim((string) $text));
	if ($text === '') {
		return '';
	}
	// Collapse accidental adjacent duplicate words (e.g. "Boston Boston").
	$parts = preg_split('/\s+/', $text) ?: [];
	$deduped = [];
	$prev = '';
	foreach ($parts as $part) {
		$normalized = strtolower(trim($part, " \t\n\r\0\x0B.,;:!?"));
		if ($normalized !== '' && $normalized === $prev) {
			continue;
		}
		$deduped[] = $part;
		$prev = $normalized;
	}
	$text = trim(implode(' ', $deduped));
	$text = preg_replace('/\s+([.,;:!?])/', '$1', $text);
	return trim((string) $text);
}

function lf_image_intelligence_media_text_needs_upgrade(string $text): bool {
	$text = lf_image_intelligence_clean_media_text($text);
	if ($text === '') {
		return true;
	}
	$lower = strtolower($text);
	$generic = ['image', 'photo', 'upload', 'placeholder', 'hero', 'banner'];
	foreach ($generic as $word) {
		if ($lower === $word) {
			return true;
		}
	}
	if (preg_match('/\boptimized\b/i', $text) === 1) {
		return true;
	}
	if (function_exists('mb_strlen') ? mb_strlen($text) < 10 : strlen($text) < 10) {
		return true;
	}
	return false;
}

function lf_image_intelligence_build_media_metadata_from_context(array $context): array {
	$city = trim((string) ($context['city'] ?? get_option('lf_homepage_city', '')));
	$service_name = trim((string) ($context['service_name'] ?? ''));
	$primary = trim((string) ($context['primary_keyword'] ?? ''));
	$business = '';
	if (function_exists('lf_business_entity_get')) {
		$entity = lf_business_entity_get();
		$business = trim((string) ($entity['name'] ?? ''));
	}
	if ($business === '') {
		$business = trim((string) get_bloginfo('name'));
	}
	$topic = $service_name !== '' ? $service_name : $primary;
	if ($topic === '') {
		$topic = $business !== '' ? $business : __('Project image', 'leadsforward-core');
	}
	$title = $topic;
	if ($city !== '' && stripos($title, $city) === false) {
		$title .= ' ' . $city;
	}
	$title = lf_image_intelligence_clean_media_text($title);
	$caption = $city !== '' ? sprintf('%s in %s', $topic, $city) : $topic;
	$caption = lf_image_intelligence_clean_media_text($caption);
	$desc_parts = [sprintf('Photo used for %s', $topic)];
	if ($city !== '') {
		$desc_parts[] = sprintf('in %s', $city);
	}
	if ($business !== '' && stripos(implode(' ', $desc_parts), $business) === false) {
		$desc_parts[] = sprintf('for %s', $business);
	}
	$description = lf_image_intelligence_clean_media_text(implode(' ', $desc_parts) . '.');
	return [
		'title' => $title,
		'caption' => $caption,
		'description' => $description,
	];
}

function lf_image_intelligence_maybe_set_media_metadata(int $attachment_id, array $context): void {
	$attachment_id = (int) $attachment_id;
	if ($attachment_id <= 0) {
		return;
	}
	$mime = (string) get_post_mime_type($attachment_id);
	if (strpos($mime, 'image/') !== 0) {
		return;
	}
	// Always try ALT, but only upgrades when empty/generic.
	lf_image_intelligence_maybe_set_alt_text($attachment_id, $context);

	$attachment = get_post($attachment_id);
	if (!$attachment instanceof \WP_Post) {
		return;
	}
	$meta = lf_image_intelligence_build_media_metadata_from_context($context);
	$next = ['ID' => $attachment_id];
	$changed = false;

	$current_title = (string) ($attachment->post_title ?? '');
	if (lf_image_intelligence_media_text_needs_upgrade($current_title) && ($meta['title'] ?? '') !== '') {
		$next['post_title'] = (string) $meta['title'];
		$next['post_name'] = sanitize_title((string) $meta['title']);
		$changed = true;
	}
	$current_caption = (string) ($attachment->post_excerpt ?? '');
	if (lf_image_intelligence_media_text_needs_upgrade($current_caption) && ($meta['caption'] ?? '') !== '') {
		$next['post_excerpt'] = (string) $meta['caption'];
		$changed = true;
	}
	$current_desc = (string) ($attachment->post_content ?? '');
	if (lf_image_intelligence_media_text_needs_upgrade($current_desc) && ($meta['description'] ?? '') !== '') {
		$next['post_content'] = (string) $meta['description'];
		$changed = true;
	}
	if ($changed) {
		wp_update_post($next);
	}
}

/**
 * Manifester uploads should never ship with generic media metadata.
 * This force-sets ALT/title/caption/description using context, with a keyword-first fallback.
 */
function lf_image_intelligence_enforce_manifest_media_metadata(int $attachment_id, array $context): void {
	$attachment_id = (int) $attachment_id;
	if ($attachment_id <= 0) {
		return;
	}
	$mime = (string) get_post_mime_type($attachment_id);
	if (strpos($mime, 'image/') !== 0) {
		return;
	}
	$attachment = get_post($attachment_id);
	if (!$attachment instanceof \WP_Post) {
		return;
	}

	$context = is_array($context) ? $context : [];
	$preserved_original = (string) get_post_meta($attachment_id, '_lf_manifester_preserved_original', true) === '1';
	$meta = lf_image_intelligence_build_media_metadata_from_context($context);

	// ALT: only fill when empty or clearly generic (do not replace intentional alt text).
	$alt_existing = trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true));
	if ($alt_existing === '' || lf_image_intelligence_media_text_needs_upgrade($alt_existing)) {
		$city = trim((string) ($context['city'] ?? ''));
		$topic = trim((string) ($context['service_name'] ?? ($context['primary_keyword'] ?? '')));
		$business = trim((string) ($context['business_name'] ?? ''));
		if ($topic === '') {
			$topic = $business !== '' ? $business : __('Local service', 'leadsforward-core');
		}
		$alt = $city !== '' ? ($topic . ' in ' . $city) : $topic;
		$alt = lf_image_intelligence_clean_media_text($alt);
		update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt));
	}

	if ($preserved_original) {
		// Do not replace title/slug/caption/description with niche-keyword templates when the operator kept a deliberate filename.
		return;
	}

	// Title / caption / description: upgrade-only so good filenames and manual work survive the Manifester.
	$next = ['ID' => $attachment_id];
	$changed = false;
	$current_title = (string) ($attachment->post_title ?? '');
	if (lf_image_intelligence_media_text_needs_upgrade($current_title) && ($meta['title'] ?? '') !== '') {
		$next['post_title'] = (string) $meta['title'];
		$next['post_name'] = sanitize_title((string) $meta['title']);
		$changed = true;
	}
	$current_caption = (string) ($attachment->post_excerpt ?? '');
	if (lf_image_intelligence_media_text_needs_upgrade($current_caption) && ($meta['caption'] ?? '') !== '') {
		$next['post_excerpt'] = (string) $meta['caption'];
		$changed = true;
	}
	$current_desc = (string) ($attachment->post_content ?? '');
	if (lf_image_intelligence_media_text_needs_upgrade($current_desc) && ($meta['description'] ?? '') !== '') {
		$next['post_content'] = (string) $meta['description'];
		$changed = true;
	}
	if ($changed) {
		wp_update_post($next);
	}
}

function lf_image_intelligence_finalize_uploaded_attachment(int $attachment_id): void {
	$attachment_id = (int) $attachment_id;
	if ($attachment_id <= 0) {
		return;
	}
	$mime = (string) get_post_mime_type($attachment_id);
	if (strpos($mime, 'image/') !== 0) {
		return;
	}
	$file = (string) get_attached_file($attachment_id);
	$filename = $file !== '' ? basename($file) : '';
	$normalized_filename = lf_image_intelligence_normalize_filename($filename);
	if (preg_match('/^upload-\d+$/', $normalized_filename) === 1 || $normalized_filename === '' || $normalized_filename === 'image') {
		$fallback_slug = lf_image_intelligence_upload_base_slug() . '-' . sprintf('%03d', lf_image_intelligence_next_upload_index());
		if (lf_image_intelligence_rename_attachment_file($attachment_id, $fallback_slug)) {
			$file = (string) get_attached_file($attachment_id);
			$filename = $file !== '' ? basename($file) : $filename;
			$normalized_filename = lf_image_intelligence_normalize_filename($filename);
		}
	}
	$title = trim(str_replace('-', ' ', $normalized_filename));
	if ($title !== '') {
		$title = ucwords($title);
		$business = trim((string) get_bloginfo('name'));
		$city = trim((string) get_option('lf_homepage_city', ''));
		$caption = $city !== ''
			? sprintf('%s in %s', $title, $city)
			: $title;
		$description_parts = [sprintf('Photo of %s', $title)];
		if ($city !== '') {
			$description_parts[] = sprintf('in %s', $city);
		}
		if ($business !== '') {
			$description_parts[] = sprintf('for %s', $business);
		}
		$description = implode(' ', $description_parts) . '.';
		$caption = lf_image_intelligence_clean_media_text($caption);
		$description = lf_image_intelligence_clean_media_text($description);
		wp_update_post([
			'ID' => $attachment_id,
			'post_title' => $title,
			'post_name' => sanitize_title($title),
			'post_excerpt' => $caption,
			'post_content' => $description,
		]);
	}
	lf_image_intelligence_maybe_set_alt_text($attachment_id, lf_image_intelligence_upload_context_defaults());
	lf_image_intelligence_store_image_profile($attachment_id);

	// If this came from the Manifester, enforce stronger metadata immediately.
	if ((string) get_post_meta($attachment_id, '_lf_manifester_upload', true) === '1') {
		$ctx = lf_image_intelligence_manifest_upload_context();
		if (is_array($ctx) && $ctx !== []) {
			lf_image_intelligence_enforce_manifest_media_metadata($attachment_id, $ctx);
		}
	}
}

function lf_image_intelligence_resolve_annotation_attachment_id(array $row): int {
	$direct_keys = ['attachment_id', 'id', 'media_id', 'image_id', 'wp_attachment_id'];
	foreach ($direct_keys as $key) {
		$candidate = absint($row[$key] ?? 0);
		if ($candidate > 0) {
			$post = get_post($candidate);
			if ($post instanceof \WP_Post && $post->post_type === 'attachment') {
				return $candidate;
			}
		}
	}

	$url_keys = ['url', 'image_url', 'imageUrl', 'src', 'image'];
	foreach ($url_keys as $key) {
		$url = esc_url_raw((string) ($row[$key] ?? ''));
		if ($url === '') {
			continue;
		}
		$by_url = (int) attachment_url_to_postid($url);
		if ($by_url > 0) {
			return $by_url;
		}
	}

	$filename_keys = ['filename', 'file_name', 'image_name', 'recommended_filename', 'recommendedFilename', 'original_filename'];
	foreach ($filename_keys as $key) {
		$raw = trim((string) ($row[$key] ?? ''));
		if ($raw === '') {
			continue;
		}
		$basename = basename($raw);
		$basename = sanitize_file_name($basename);
		if ($basename === '') {
			continue;
		}
		$query = get_posts([
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'posts_per_page' => 1,
			'fields' => 'ids',
			'meta_query' => [[
				'key' => '_wp_attached_file',
				'value' => '/' . $basename,
				'compare' => 'LIKE',
			]],
			'no_found_rows' => true,
		]);
		if (!empty($query)) {
			return (int) $query[0];
		}
	}

	return 0;
}

function lf_image_intelligence_build_media_candidates_for_vision(int $limit = 200): array {
	$index = lf_build_media_index();
	$out = [];
	foreach (array_slice($index, 0, $limit) as $item) {
		$id = (int) ($item['id'] ?? 0);
		if ($id <= 0) {
			continue;
		}
		$out[] = [
			'attachment_id' => $id,
			'url' => wp_get_attachment_url($id),
			'filename' => (string) ($item['filename'] ?? ''),
			'title' => (string) ($item['title'] ?? ''),
			'alt' => (string) ($item['alt'] ?? ''),
			'caption' => (string) ($item['caption'] ?? ''),
		];
	}
	return $out;
}

function lf_image_intelligence_apply_vision_annotations(array $annotations): array {
	$stored = lf_image_intelligence_get_vision_annotations();
	$applied = 0;
	foreach ($annotations as $row) {
		if (!is_array($row)) {
			continue;
		}
		$attachment_id = lf_image_intelligence_resolve_annotation_attachment_id($row);
		if ($attachment_id <= 0) {
			continue;
		}
		$post = get_post($attachment_id);
		if (!$post instanceof \WP_Post || $post->post_type !== 'attachment') {
			continue;
		}
		$keywords = $row['keywords'] ?? ($row['keyword_tags'] ?? []);
		if (is_string($keywords)) {
			$keywords = preg_split('/\r\n|\r|\n|,/', $keywords);
		}
		$description_raw = (string) ($row['description'] ?? ($row['summary'] ?? ''));
		$alt_raw = (string) ($row['alt_text'] ?? ($row['altText'] ?? ($row['alt'] ?? '')));
		$title_raw = (string) ($row['title'] ?? ($row['image_title'] ?? ''));
		$caption_raw = (string) ($row['caption'] ?? ($row['image_caption'] ?? ''));
		$recommended_filename_raw = (string) ($row['recommended_filename'] ?? ($row['recommendedFilename'] ?? ($row['filename_suggestion'] ?? '')));
		$description_clean = lf_image_intelligence_clean_media_text($description_raw);
		$alt_clean = lf_image_intelligence_clean_media_text($alt_raw);
		$title_clean = lf_image_intelligence_clean_media_text($title_raw);
		$caption_clean = lf_image_intelligence_clean_media_text($caption_raw);
		$recommended_filename_clean = lf_image_intelligence_sanitize_recommended_attachment_basename($recommended_filename_raw);
		if ($caption_clean === '' && $description_clean !== '') {
			$caption_clean = $description_clean;
		}
		if ($description_clean === '' && $alt_clean !== '') {
			$description_clean = $alt_clean;
		}
		$entry = [
			'description' => sanitize_text_field($description_clean),
			'alt_text' => sanitize_text_field($alt_clean),
			'title' => sanitize_text_field($title_clean),
			'caption' => sanitize_text_field($caption_clean),
			'keywords' => is_array($keywords) ? array_values(array_filter(array_map('sanitize_text_field', $keywords))) : [],
			'service_slugs' => is_array($row['service_slugs'] ?? null) ? array_values(array_map('sanitize_title', $row['service_slugs'])) : [],
			'area_slugs' => is_array($row['area_slugs'] ?? null) ? array_values(array_map('sanitize_title', $row['area_slugs'])) : [],
			'page_types' => is_array($row['page_types'] ?? null) ? array_values(array_map('sanitize_key', $row['page_types'])) : [],
			'slots' => is_array($row['slots'] ?? null) ? array_values(array_map('sanitize_key', $row['slots'])) : [],
			'recommended_filename' => $recommended_filename_clean,
		];
		$stored[$attachment_id] = $entry;
		$allow_vision_rename = (bool) apply_filters('lf_image_intelligence_rename_from_vision_annotations', false);
		if ($allow_vision_rename && $entry['recommended_filename'] !== '') {
			lf_image_intelligence_rename_attachment_file($attachment_id, $entry['recommended_filename']);
		}
		if ($entry['alt_text'] !== '') {
			update_post_meta($attachment_id, '_wp_attachment_image_alt', $entry['alt_text']);
		}
		$post_updates = ['ID' => $attachment_id];
		$has_post_update = false;
		if ($entry['title'] !== '') {
			$post_updates['post_title'] = $entry['title'];
			$post_updates['post_name'] = sanitize_title($entry['title']);
			$has_post_update = true;
		}
		if ($entry['caption'] !== '') {
			$post_updates['post_excerpt'] = $entry['caption'];
			$has_post_update = true;
		}
		if ($entry['description'] !== '') {
			update_post_meta($attachment_id, '_lf_vision_description', $entry['description']);
			$post_updates['post_content'] = $entry['description'];
			$has_post_update = true;
		}
		if ($has_post_update) {
			wp_update_post($post_updates);
		}
		$applied++;
	}
	lf_image_intelligence_update_vision_annotations($stored);
	lf_invalidate_media_index_cache();
	lf_build_media_index();
	return ['applied' => $applied];
}

/**
 * @param array<string,mixed> $registry_section
 * @return string[]
 */
function lf_image_intelligence_registry_image_fields(array $registry_section): array {
	$keys = [];
	foreach ($registry_section['fields'] ?? [] as $field) {
		if (!is_array($field)) {
			continue;
		}
		if ((string) ($field['type'] ?? '') !== 'image') {
			continue;
		}
		$key = (string) ($field['key'] ?? '');
		if ($key !== '') {
			$keys[] = $key;
		}
	}
	return $keys;
}

function lf_image_intelligence_slot_for_section_field(string $section_type, string $field_key): string {
	if ($section_type === 'hero' || in_array($field_key, ['hero_image_id', 'hero_background_image_id'], true)) {
		return 'hero';
	}
	if ($section_type === 'image_content_b') {
		return 'image_content_b';
	}
	if ($section_type === 'content_image_c') {
		return 'content_image_c';
	}
	if ($section_type === 'content_image_a' || $section_type === 'content_image' || $section_type === 'image_content' || $section_type === 'service_details') {
		return 'content_image_a';
	}
	if ($section_type === 'map_nap') {
		return 'content_image_a';
	}
	return 'content_image_a';
}

function lf_image_intelligence_empty_image_value($value): bool {
	if ($value === '' || $value === null) {
		return true;
	}
	if (is_numeric($value)) {
		$id = (int) $value;
		if ($id === 0) {
			return true;
		}
		if (lf_image_intelligence_is_placeholder_asset($id)) {
			return true;
		}
	}
	return false;
}

/**
 * Enforce strong image metadata for an attachment using context.
 *
 * This is used when the theme assigns images into section slots. We prefer the
 * stronger manifester-aware metadata enforcement when available.
 *
 * @param array<string,mixed> $context
 */
function lf_image_intelligence_enforce_assigned_image_metadata(int $attachment_id, array $context, string $slot = '', string $section_type = '', string $field_key = ''): void {
	if ($attachment_id <= 0) {
		return;
	}
	$ctx = $context;
	if ($slot !== '') {
		$ctx['slot'] = $slot;
	}
	if ($section_type !== '') {
		$ctx['section_type'] = $section_type;
	}
	if ($field_key !== '') {
		$ctx['field_key'] = $field_key;
	}
	if (function_exists('lf_image_intelligence_enforce_manifest_media_metadata')) {
		lf_image_intelligence_enforce_manifest_media_metadata($attachment_id, $ctx);
		return;
	}
	if (function_exists('lf_image_intelligence_maybe_set_media_metadata')) {
		lf_image_intelligence_maybe_set_media_metadata($attachment_id, $ctx);
		return;
	}
	if (function_exists('lf_image_intelligence_maybe_set_alt_text')) {
		lf_image_intelligence_maybe_set_alt_text($attachment_id, $ctx);
	}
}

function lf_image_intelligence_assign_images_to_post_sections(\WP_Post $post, array $matches, array $context): int {
	if (!function_exists('lf_pb_get_context_for_post') || !function_exists('lf_pb_get_post_config')) {
		return 0;
	}
	$builder_context = lf_pb_get_context_for_post($post);
	if ($builder_context === '') {
		return 0;
	}
	$config = lf_pb_get_post_config((int) $post->ID, $builder_context);
	$order = is_array($config['order'] ?? null) ? $config['order'] : [];
	$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	$registry = function_exists('lf_sections_registry') ? lf_sections_registry() : [];
	if (empty($order) || empty($sections) || empty($registry)) {
		return 0;
	}
	$assigned = 0;
	foreach ($order as $instance_id) {
		$section = $sections[$instance_id] ?? null;
		if (!is_array($section) || empty($section['enabled'])) {
			continue;
		}
		$type = (string) ($section['type'] ?? '');
		if ($type === '' || !isset($registry[$type])) {
			continue;
		}
		$settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
		$image_fields = lf_image_intelligence_registry_image_fields($registry[$type]);
		$changed = false;
		foreach ($image_fields as $field_key) {
			$current = $settings[$field_key] ?? '';
			if (!lf_image_intelligence_empty_image_value($current)) {
				continue;
			}
			$slot = lf_image_intelligence_slot_for_section_field($type, $field_key);
			$image_id = (int) ($matches[$slot] ?? 0);
			if ($image_id <= 0) {
				continue;
			}
			$settings[$field_key] = $image_id;
			lf_image_intelligence_enforce_assigned_image_metadata($image_id, $context, $slot, $type, $field_key);
			$assigned++;
			$changed = true;
		}
		if ($changed) {
			$sections[$instance_id]['settings'] = $settings;
		}
	}
	if ($assigned > 0) {
		$meta_key = defined('LF_PB_META_KEY') ? LF_PB_META_KEY : '_lf_pagebuilder_config';
		update_post_meta((int) $post->ID, $meta_key, [
			'order' => $order,
			'sections' => $sections,
			'seo' => $config['seo'] ?? ['title' => '', 'description' => '', 'noindex' => false],
		]);
	}
	return $assigned;
}

function lf_image_intelligence_assign_images_to_homepage_sections(array $matches, array $context): int {
	if (!function_exists('lf_get_homepage_section_config') || !function_exists('lf_sections_registry')) {
		return 0;
	}
	$config = lf_get_homepage_section_config();
	$registry = lf_sections_registry();
	if (!is_array($config) || empty($config) || !is_array($registry) || empty($registry)) {
		return 0;
	}
	$assigned = 0;
	$changed = false;
	foreach ($config as $section_id => $settings) {
		$section_id = (string) $section_id;
		if (!is_array($settings) || !isset($registry[$section_id])) {
			continue;
		}
		$image_fields = lf_image_intelligence_registry_image_fields($registry[$section_id]);
		foreach ($image_fields as $field_key) {
			$current = $settings[$field_key] ?? '';
			if (!lf_image_intelligence_empty_image_value($current)) {
				continue;
			}
			$slot = lf_image_intelligence_slot_for_section_field($section_id, $field_key);
			$image_id = (int) ($matches[$slot] ?? 0);
			if ($image_id <= 0) {
				continue;
			}
			$config[$section_id][$field_key] = $image_id;
			lf_image_intelligence_enforce_assigned_image_metadata($image_id, $context, $slot, $section_id, $field_key);
			$assigned++;
			$changed = true;
		}
	}
	if ($changed) {
		update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
	}
	return $assigned;
}

/**
 * Pre-prime deterministic image distribution for current site content.
 *
 * @return array<string,int>
 */
function lf_prime_image_distribution_for_site(): array {
	$summary = ['featured_set' => 0, 'processed' => 0, 'section_images_set' => 0];
	lf_build_media_index();

	$post_ids = [];
	$front_id = (int) get_option('page_on_front');
	if ($front_id > 0) {
		$post_ids[] = $front_id;
	}
	$post_ids = array_merge($post_ids, get_posts([
		'post_type' => ['lf_service', 'lf_service_area', 'lf_project'],
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'fields' => 'ids',
		'no_found_rows' => true,
	]));
	$post_ids = array_merge($post_ids, get_posts([
		'post_type' => 'page',
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'fields' => 'ids',
		'no_found_rows' => true,
		'post_name__in' => ['about-us', 'our-services', 'service-areas', 'reviews', 'contact'],
	]));
	$post_ids = array_values(array_unique(array_map('intval', $post_ids)));

	foreach ($post_ids as $post_id) {
		$post = get_post($post_id);
		if (!$post instanceof \WP_Post) {
			continue;
		}
		$summary['processed']++;
		$context = lf_image_intelligence_build_context_for_post($post);
		$matches = lf_match_images_for_context($context);
		$featured = (int) ($matches['featured'] ?? 0);
		$current_thumb_id = (int) get_post_thumbnail_id($post_id);
		$current_thumb_placeholder = $current_thumb_id > 0 ? lf_image_intelligence_is_placeholder_asset($current_thumb_id) : false;
		if ($featured > 0 && (!has_post_thumbnail($post_id) || $current_thumb_placeholder)) {
			set_post_thumbnail($post_id, $featured);
			lf_image_intelligence_maybe_set_alt_text($featured, $context);
			$summary['featured_set']++;
		}
		$summary['section_images_set'] += lf_image_intelligence_assign_images_to_post_sections($post, $matches, $context);
	}
	$home_keywords = get_option('lf_homepage_keywords', []);
	$home_primary = is_array($home_keywords) ? (string) ($home_keywords['primary'] ?? '') : '';
	$home_secondary = is_array($home_keywords) ? ($home_keywords['secondary'] ?? []) : [];
	if (!is_array($home_secondary)) {
		$home_secondary = [];
	}
	$home_context = [
		'page_type' => 'homepage',
		'niche' => (string) (defined('LF_HOMEPAGE_NICHE_OPTION') ? get_option(LF_HOMEPAGE_NICHE_OPTION, '') : ''),
		'city' => (string) get_option('lf_homepage_city', ''),
		'primary_keyword' => $home_primary,
		'secondary_keywords' => $home_secondary,
		'variation_seed' => (string) get_option('lf_homepage_variation_seed', ''),
		'service_name' => $home_primary,
	];
	$home_matches = lf_match_images_for_context($home_context);
	$summary['section_images_set'] += lf_image_intelligence_assign_images_to_homepage_sections($home_matches, $home_context);

	return $summary;
}

function lf_image_intelligence_register_debug_page(): void {
	if (!current_user_can('manage_options')) {
		return;
	}
	add_management_page(
		__('Image Intelligence Debug', 'leadsforward-core'),
		__('Image Intelligence Debug', 'leadsforward-core'),
		'manage_options',
		'lf-image-intelligence-debug',
		'lf_image_intelligence_render_debug_page'
	);
}

function lf_image_intelligence_render_debug_page(): void {
	if (!current_user_can('manage_options')) {
		return;
	}
	$index = lf_build_media_index();
	$front = (int) get_option('page_on_front');
	$front_post = $front > 0 ? get_post($front) : null;
	$sample_context = $front_post instanceof \WP_Post ? lf_image_intelligence_build_context_for_post($front_post, ['page_type' => 'homepage']) : [];
	$sample_matches = !empty($sample_context) ? lf_match_images_for_context($sample_context) : [];
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Image Intelligence Debug', 'leadsforward-core'); ?></h1>
		<p class="description"><?php esc_html_e('Deterministic media index and sample match output.', 'leadsforward-core'); ?></p>
		<h2><?php esc_html_e('Media Index', 'leadsforward-core'); ?></h2>
		<pre style="max-height:320px;overflow:auto;background:#fff;border:1px solid #dcdcde;padding:12px;"><?php echo esc_html(wp_json_encode($index, JSON_PRETTY_PRINT)); ?></pre>
		<h2><?php esc_html_e('Sample Homepage Match', 'leadsforward-core'); ?></h2>
		<pre style="max-height:220px;overflow:auto;background:#fff;border:1px solid #dcdcde;padding:12px;"><?php echo esc_html(wp_json_encode(['context' => $sample_context, 'matches' => $sample_matches], JSON_PRETTY_PRINT)); ?></pre>
	</div>
	<?php
}

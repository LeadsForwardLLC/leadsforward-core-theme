<?php
/**
 * OpenAI provider for AI Assistant (server-side).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_filter('lf_ai_completion', 'lf_ai_openai_completion', 10, 5);

function lf_ai_openai_get_key(): string {
	$key = get_option('lf_openai_api_key', '');
	return is_string($key) ? trim($key) : '';
}

function lf_ai_openai_completion($response, string $system, string $user, string $context_type, $context_id) {
	$key = lf_ai_openai_get_key();
	if ($key === '') {
		return new WP_Error('lf_ai_no_key', __('OpenAI key is missing. Add it in LeadsForward → Global Settings.', 'leadsforward-core'));
	}
	$payload = [
		'model'       => 'gpt-4o-mini',
		'temperature' => 0.2,
		'max_tokens'  => 500,
		'messages'    => [
			['role' => 'system', 'content' => $system],
			['role' => 'user', 'content' => $user],
		],
		'response_format' => ['type' => 'json_object'],
	];
	$args = [
		'headers' => [
			'Authorization' => 'Bearer ' . $key,
			'Content-Type'  => 'application/json',
		],
		'body'    => wp_json_encode($payload),
		'timeout' => 15,
	];
	$result = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);
	if (is_wp_error($result)) {
		return new WP_Error('lf_ai_http', $result->get_error_message());
	}
	$code = wp_remote_retrieve_response_code($result);
	$body = wp_remote_retrieve_body($result);
	if ($code < 200 || $code >= 300) {
		$detail = '';
		$type = '';
		$err_code = '';
		$data = json_decode($body, true);
		if (is_array($data) && !empty($data['error'])) {
			if (!empty($data['error']['message'])) {
				$detail = (string) $data['error']['message'];
			}
			if (!empty($data['error']['type'])) {
				$type = (string) $data['error']['type'];
			}
			if (!empty($data['error']['code'])) {
				$err_code = (string) $data['error']['code'];
			}
		}
		$detail = $detail !== '' ? $detail : __('Check your key, model access, and billing.', 'leadsforward-core');
		$meta = [];
		if ($type !== '') {
			$meta[] = 'type=' . sanitize_text_field($type);
		}
		if ($err_code !== '') {
			$meta[] = 'code=' . sanitize_text_field($err_code);
		}
		$request_id = wp_remote_retrieve_header($result, 'x-request-id');
		if (is_string($request_id) && $request_id !== '') {
			$meta[] = 'request_id=' . sanitize_text_field($request_id);
		}
		$meta_text = !empty($meta) ? ' [' . implode(' ', $meta) . ']' : '';
		return new WP_Error('lf_ai_http', sprintf(__('OpenAI API error (%d): %s%s', 'leadsforward-core'), $code, $detail, $meta_text));
	}
	$data = json_decode($body, true);
	if (!is_array($data) || empty($data['choices'][0]['message']['content'])) {
		return new WP_Error('lf_ai_response', __('OpenAI response was empty or invalid.', 'leadsforward-core'));
	}
	return (string) $data['choices'][0]['message']['content'];
}

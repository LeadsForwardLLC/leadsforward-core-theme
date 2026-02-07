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
		return new WP_Error('lf_ai_no_key', __('OpenAI key is missing. Add it in LeadsForward → Setup.', 'leadsforward-core'));
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
		$data = json_decode($body, true);
		if (is_array($data) && !empty($data['error']['message'])) {
			$detail = (string) $data['error']['message'];
		}
		$detail = $detail !== '' ? $detail : __('Check your key, model access, and billing.', 'leadsforward-core');
		return new WP_Error('lf_ai_http', sprintf(__('OpenAI API error (%d): %s', 'leadsforward-core'), $code, $detail));
	}
	$data = json_decode($body, true);
	if (!is_array($data) || empty($data['choices'][0]['message']['content'])) {
		return new WP_Error('lf_ai_response', __('OpenAI response was empty or invalid.', 'leadsforward-core'));
	}
	return (string) $data['choices'][0]['message']['content'];
}

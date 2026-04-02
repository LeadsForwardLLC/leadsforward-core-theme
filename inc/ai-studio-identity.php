<?php
declare(strict_types=1);

if (!function_exists('lf_ai_studio_identity_normalize_text')) {
    function lf_ai_studio_identity_normalize_text($value): string {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }
        $text = str_replace('&', ' and ', $text);
        $text = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim((string) $text);
    }
}

if (!function_exists('lf_ai_studio_identity_slug')) {
    function lf_ai_studio_identity_slug($value): string {
        $raw = (string) $value;
        if ($raw === '') {
            return '';
        }
        if (function_exists('sanitize_title')) {
            return (string) sanitize_title($raw);
        }
        $text = function_exists('mb_strtolower') ? mb_strtolower($raw) : strtolower($raw);
        $text = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $text);
        $text = preg_replace('/\s+/u', '-', $text);
        return trim((string) $text, '-');
    }
}

if (!function_exists('lf_ai_studio_identity_compare')) {
    function lf_ai_studio_identity_compare(array $expected, array $incoming): array {
        $expected_name = lf_ai_studio_identity_normalize_text($expected['business_name'] ?? '');
        $incoming_name = lf_ai_studio_identity_normalize_text($incoming['business_name'] ?? '');
        $expected_city = lf_ai_studio_identity_normalize_text($expected['city_region'] ?? '');
        $incoming_city = lf_ai_studio_identity_normalize_text($incoming['city_region'] ?? '');
        $expected_niche_label = lf_ai_studio_identity_normalize_text($expected['niche'] ?? '');
        $expected_niche_slug = lf_ai_studio_identity_slug($expected['niche'] ?? '');
        $incoming_niche_slug = lf_ai_studio_identity_slug($incoming['niche'] ?? '');

        if ($expected_name !== '' && $incoming_name === '') {
            return ['match' => false, 'reason' => 'missing_business_name'];
        }

        $comparables = [];
        if ($expected_name !== '' && $incoming_name !== '') {
            $comparables['business_name'] = [$expected_name, $incoming_name];
        }
        if ($expected_city !== '' && $incoming_city !== '') {
            $comparables['city_region'] = [$expected_city, $incoming_city];
        }
        if (($expected_niche_slug !== '' || $expected_niche_label !== '') && $incoming_niche_slug !== '') {
            $comparables['niche'] = [$expected_niche_slug ?: $expected_niche_label, $incoming_niche_slug];
        }

        if (empty($comparables)) {
            return ['match' => true, 'reason' => 'no_comparable_fields'];
        }

        foreach ($comparables as $key => $pair) {
            [$left, $right] = $pair;
            if ($left !== $right) {
                return ['match' => false, 'reason' => 'mismatch_' . $key];
            }
        }
        return ['match' => true, 'reason' => 'match'];
    }
}

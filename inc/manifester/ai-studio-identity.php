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
        $expected_niche_label = (string) ($expected['niche'] ?? '');
        $expected_niche_label_slug = lf_ai_studio_identity_slug($expected_niche_label);
        $expected_niche_slug = lf_ai_studio_identity_slug($expected['niche_slug'] ?? '');
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
        if (($expected_niche_slug !== '' || $expected_niche_label_slug !== '') && $incoming_niche_slug !== '') {
            $expected_niche_options = array_values(array_filter([$expected_niche_slug, $expected_niche_label_slug]));
            $comparables['niche'] = [$expected_niche_options, $incoming_niche_slug];
        }

        if (empty($comparables)) {
            return ['match' => true, 'reason' => 'no_comparable_fields'];
        }

        foreach ($comparables as $key => $pair) {
            [$left, $right] = $pair;
            if ($key === 'niche') {
                if (!in_array($right, (array) $left, true)) {
                    return ['match' => false, 'reason' => 'mismatch_' . $key];
                }
                continue;
            }
            if ($left !== $right) {
                return ['match' => false, 'reason' => 'mismatch_' . $key];
            }
        }
        return ['match' => true, 'reason' => 'match'];
    }
}

if (!function_exists('lf_ai_studio_identity_build_expected')) {
    function lf_ai_studio_identity_build_expected($job, $manifest, $options): array {
        $job = is_array($job) ? $job : [];
        $manifest = is_array($manifest) ? $manifest : [];
        $options = is_array($options) ? $options : [];

        $first_value = static function (...$values): string {
            foreach ($values as $value) {
                $text = trim((string) $value);
                if ($text !== '') {
                    return $text;
                }
            }
            return '';
        };

        $job_name = $first_value($job['business_name'] ?? '');
        $job_city = $first_value($job['city_region'] ?? '');
        $job_niche = $first_value($job['niche'] ?? '');
        $job_niche_slug = $first_value($job['niche_slug'] ?? '');

        $manifest_business = is_array($manifest['business'] ?? null) ? $manifest['business'] : [];
        $manifest_name = $first_value($manifest_business['name'] ?? '');
        $manifest_city = $first_value($manifest_business['primary_city'] ?? '');
        if ($manifest_city === '') {
            $manifest_address = is_array($manifest_business['address'] ?? null) ? $manifest_business['address'] : [];
            $manifest_city = $first_value($manifest_address['city'] ?? '');
        }
        $manifest_niche_slug = $first_value($manifest_business['niche_slug'] ?? '');
        $manifest_niche_label = $first_value($manifest_business['niche'] ?? '');
        $manifest_niche = $manifest_niche_slug !== '' ? $manifest_niche_slug : $manifest_niche_label;

        $option_name = $first_value($options['lf_business_name'] ?? '');
        $option_city = $first_value($options['lf_city_region'] ?? '');
        if ($option_city === '') {
            $option_city = $first_value($options['lf_homepage_city'] ?? '');
        }
        $option_niche = $first_value($options['lf_homepage_niche_slug'] ?? '');

        $expected = [
            'business_name' => $first_value($job_name, $manifest_name, $option_name),
            'city_region' => $first_value($job_city, $manifest_city, $option_city),
            'niche' => $first_value($job_niche, $manifest_niche, $option_niche),
        ];

        $niche_slug = $first_value($job_niche_slug, $manifest_niche_slug, $option_niche);
        if ($niche_slug !== '') {
            $expected['niche_slug'] = $niche_slug;
        }

        return $expected;
    }
}

if (!function_exists('lf_ai_studio_identity_build_incoming')) {
    function lf_ai_studio_identity_build_incoming($apply_payload, $payload): array {
        $apply_payload = is_array($apply_payload) ? $apply_payload : [];
        $payload = is_array($payload) ? $payload : [];

        $apply_meta = is_array($apply_payload['meta'] ?? null) ? $apply_payload['meta'] : [];
        $payload_meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];

        $first_value = static function (...$values): string {
            foreach ($values as $value) {
                $text = trim((string) $value);
                if ($text !== '') {
                    return $text;
                }
            }
            return '';
        };

        return [
            'business_name' => $first_value(
                $apply_payload['business_name'] ?? '',
                $apply_meta['business_name'] ?? '',
                $payload['business_name'] ?? '',
                $payload_meta['business_name'] ?? ''
            ),
            'city_region' => $first_value(
                $apply_payload['city_region'] ?? '',
                $apply_meta['city_region'] ?? '',
                $payload['city_region'] ?? '',
                $payload_meta['city_region'] ?? ''
            ),
            'niche' => $first_value(
                $apply_payload['niche'] ?? '',
                $apply_meta['niche'] ?? '',
                $payload['niche'] ?? '',
                $payload_meta['niche'] ?? ''
            ),
        ];
    }
}

if (!function_exists('lf_ai_studio_identity_guard_decision')) {
    function lf_ai_studio_identity_guard_decision(array $expected, array $incoming, int $job_id): array {
        $comparison = lf_ai_studio_identity_compare($expected, $incoming);
        $allow = !empty($comparison['match']);
        $reason = (string) ($comparison['reason'] ?? '');
        $response = [];
        if (!$allow) {
            $response = [
                'success' => false,
                'error' => ['business_identity_mismatch'],
                'job_id' => $job_id,
                'acknowledged' => true,
            ];
        }
        return [
            'allow' => $allow,
            'reason' => $reason,
            'response' => $response,
        ];
    }
}

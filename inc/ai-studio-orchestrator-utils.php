<?php
declare(strict_types=1);

if (!function_exists('lf_ai_studio_orchestrator_force_apply_enabled')) {
    function lf_ai_studio_orchestrator_force_apply_enabled(array $payload): bool {
        $run_phase = '';
        if (isset($payload['run_phase'])) {
            $run_phase = (string) $payload['run_phase'];
        } elseif (isset($payload['apply']) && is_array($payload['apply']) && isset($payload['apply']['run_phase'])) {
            $run_phase = (string) $payload['apply']['run_phase'];
        }
        $run_phase = function_exists('sanitize_text_field')
            ? sanitize_text_field($run_phase)
            : trim($run_phase);
        if ($run_phase === 'repair') {
            return true;
        }
        return !empty($payload['force_apply']);
    }
}

if (!function_exists('lf_ai_studio_orchestrator_build_apply_counts')) {
    /**
     * @param array<string,mixed> $apply_payload
     * @param array<string,mixed> $apply_result
     * @return array{homepage_updated:bool,posts_updated:int,faqs_updated:int,service_meta_updated:int}
     */
    function lf_ai_studio_orchestrator_build_apply_counts(array $apply_payload, array $apply_result): array {
        $changes = $apply_result['changes'] ?? [];
        $posts = $changes['posts'] ?? [];
        $faqs = $changes['faqs'] ?? [];
        $updates = $apply_payload['updates'] ?? [];
        $service_n = 0;
        if (is_array($updates)) {
            foreach ($updates as $u) {
                if (is_array($u) && ($u['target'] ?? '') === 'service_meta') {
                    $service_n++;
                }
            }
        }
        return [
            'homepage_updated' => !empty($changes['homepage']),
            'posts_updated' => is_array($posts) ? count($posts) : 0,
            'faqs_updated' => is_array($faqs) ? count($faqs) : 0,
            'service_meta_updated' => $service_n,
        ];
    }
}

if (!function_exists('lf_ai_studio_orchestrator_resolve_run_phase')) {
    /**
     * @param array<string,mixed> $apply_payload
     * @param array<string,mixed> $outer_payload
     */
    function lf_ai_studio_orchestrator_resolve_run_phase(array $apply_payload, array $outer_payload = []): string {
        $candidates = [
            $apply_payload['run_phase'] ?? null,
            $outer_payload['run_phase'] ?? null,
        ];
        if (isset($outer_payload['apply']) && is_array($outer_payload['apply'])) {
            $candidates[] = $outer_payload['apply']['run_phase'] ?? null;
        }
        foreach ($candidates as $v) {
            if (!is_string($v)) {
                continue;
            }
            $s = trim($v);
            if ($s === '') {
                continue;
            }
            return strtolower($s);
        }
        return '';
    }
}

if (!function_exists('lf_ai_studio_repair_interior_only_enabled')) {
    /**
     * When true (default in WordPress), repair callbacks without explicit apply_scope apply interior updates only.
     */
    function lf_ai_studio_repair_interior_only_enabled(): bool {
        if (!function_exists('get_option')) {
            return false;
        }
        return get_option('lf_ai_studio_repair_interior_only', '1') === '1';
    }
}

if (!function_exists('lf_ai_studio_orchestrator_resolve_apply_scope')) {
    /**
     * Optional split-apply for n8n: same full `updates` array, two callbacks with different scopes.
     * - full: default, no filtering
     * - homepage: only target options + id homepage
     * - interior: everything except homepage options (posts, FAQs, service_meta, etc.)
     *
     * Repair + option lf_ai_studio_repair_interior_only: defaults to interior when apply_scope omitted.
     *
     * @param array<string,mixed> $apply_payload
     * @param array<string,mixed> $outer_payload Full REST body (e.g. wrapper with `apply` key)
     */
    function lf_ai_studio_orchestrator_resolve_apply_scope(array $apply_payload, array $outer_payload = []): string {
        $candidates = [
            $apply_payload['apply_scope'] ?? null,
            $outer_payload['apply_scope'] ?? null,
        ];
        foreach ($candidates as $v) {
            if (!is_string($v)) {
                continue;
            }
            $s = strtolower(trim($v));
            if ($s === 'homepage' || $s === 'interior' || $s === 'full') {
                return $s;
            }
        }
        $run_phase = lf_ai_studio_orchestrator_resolve_run_phase($apply_payload, $outer_payload);
        if ($run_phase === 'repair' && lf_ai_studio_repair_interior_only_enabled()) {
            return 'interior';
        }
        return 'full';
    }
}

if (!function_exists('lf_ai_studio_orchestrator_filter_updates_for_scope')) {
    /**
     * @param array<string,mixed> $apply_payload
     * @param array<string,mixed> $outer_payload
     * @return array{payload: array<string,mixed>, scope: string, filtered_from: int, filtered_to: int}
     */
    function lf_ai_studio_orchestrator_filter_updates_for_scope(array $apply_payload, array $outer_payload = []): array {
        $scope = lf_ai_studio_orchestrator_resolve_apply_scope($apply_payload, $outer_payload);
        $updates = $apply_payload['updates'] ?? [];
        $from = is_array($updates) ? count($updates) : 0;
        if ($scope === 'full' || !is_array($updates)) {
            return [
                'payload' => $apply_payload,
                'scope' => $scope,
                'filtered_from' => $from,
                'filtered_to' => $from,
            ];
        }
        $is_homepage_options = static function (array $u): bool {
            return ($u['target'] ?? '') === 'options' && (string) ($u['id'] ?? '') === 'homepage';
        };
        if ($scope === 'homepage') {
            $new = array_values(array_filter($updates, static function ($u) use ($is_homepage_options): bool {
                return is_array($u) && $is_homepage_options($u);
            }));
        } else {
            // interior
            $new = array_values(array_filter($updates, static function ($u) use ($is_homepage_options): bool {
                return is_array($u) && !$is_homepage_options($u);
            }));
        }
        $out = $apply_payload;
        $out['updates'] = $new;
        return [
            'payload' => $out,
            'scope' => $scope,
            'filtered_from' => $from,
            'filtered_to' => count($new),
        ];
    }
}

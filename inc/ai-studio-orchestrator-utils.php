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

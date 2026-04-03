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

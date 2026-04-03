<?php
require __DIR__ . '/../inc/ai-studio-orchestrator-utils.php';

function expect($cond, $msg) {
    if (!$cond) {
        fwrite(STDERR, "FAIL: " . $msg . PHP_EOL);
        exit(1);
    }
}

// run_phase=repair should force apply
expect(
    lf_ai_studio_orchestrator_force_apply_enabled(['run_phase' => 'repair']) === true,
    'repair run_phase should force apply'
);

// explicit force_apply should force apply
expect(
    lf_ai_studio_orchestrator_force_apply_enabled(['force_apply' => true]) === true,
    'force_apply flag should force apply'
);

// missing signals should not force apply
expect(
    lf_ai_studio_orchestrator_force_apply_enabled([]) === false,
    'missing signals should not force apply'
);

// nested apply payload should still be detected
expect(
    lf_ai_studio_orchestrator_force_apply_enabled(['apply' => ['run_phase' => 'repair']]) === true,
    'nested apply run_phase should force apply'
);

echo "PASS\n";

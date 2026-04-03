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

$counts = lf_ai_studio_orchestrator_build_apply_counts(
    ['updates' => [['target' => 'service_meta', 'id' => 1]]],
    [
        'changes' => [
            'homepage' => true,
            'posts' => [1, 2],
            'faqs' => [10],
        ],
    ]
);
expect($counts['homepage_updated'] === true, 'apply_counts homepage');
expect($counts['posts_updated'] === 2, 'apply_counts posts');
expect($counts['faqs_updated'] === 1, 'apply_counts faqs');
expect($counts['service_meta_updated'] === 1, 'apply_counts service_meta');

echo "PASS\n";

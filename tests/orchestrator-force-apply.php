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

$home = ['target' => 'options', 'id' => 'homepage', 'fields' => ['hero.hero_headline' => 'x']];
$post = ['target' => 'post_meta', 'id' => '123', 'fields' => ['hero-1.hero_headline' => 'y']];
$mixed = ['updates' => [$home, $post], 'ok' => true];

$f1 = lf_ai_studio_orchestrator_filter_updates_for_scope($mixed, []);
expect($f1['scope'] === 'full', 'default scope full');
expect($f1['filtered_to'] === 2, 'full keeps all');

$f2 = lf_ai_studio_orchestrator_filter_updates_for_scope(['apply_scope' => 'homepage'] + $mixed, []);
expect($f2['scope'] === 'homepage', 'homepage scope');
expect($f2['filtered_to'] === 1, 'homepage one item');
expect(($f2['payload']['updates'][0]['target'] ?? '') === 'options', 'homepage keeps options');

$f3 = lf_ai_studio_orchestrator_filter_updates_for_scope(['apply_scope' => 'interior'] + $mixed, []);
expect($f3['scope'] === 'interior', 'interior scope');
expect($f3['filtered_to'] === 1, 'interior one item');
expect(($f3['payload']['updates'][0]['target'] ?? '') === 'post_meta', 'interior keeps post_meta');

$f4 = lf_ai_studio_orchestrator_filter_updates_for_scope(['apply_scope' => 'homepage', 'updates' => [$post]], []);
expect($f4['filtered_from'] === 1 && $f4['filtered_to'] === 0, 'homepage scope no homepage rows');

// Without WordPress, repair does not auto-switch to interior (get_option absent).
$f5 = lf_ai_studio_orchestrator_filter_updates_for_scope(['run_phase' => 'repair'] + $mixed, []);
expect($f5['scope'] === 'full', 'repair CLI scope stays full');
expect($f5['filtered_to'] === 2, 'repair CLI keeps all updates');

$f6 = lf_ai_studio_orchestrator_filter_updates_for_scope(['run_phase' => 'repair', 'apply_scope' => 'interior'] + $mixed, []);
expect($f6['scope'] === 'interior', 'explicit interior on repair');
expect($f6['filtered_to'] === 1, 'interior strips homepage row');

expect(
    lf_ai_studio_orchestrator_updates_are_only_homepage_options([$home]) === true,
    'homepage-only detector single row'
);
expect(
    lf_ai_studio_orchestrator_updates_are_only_homepage_options([$home, $post]) === false,
    'homepage-only detector rejects mixed'
);

$f7 = lf_ai_studio_orchestrator_filter_updates_for_scope(['apply_scope' => 'interior', 'updates' => [$home]], []);
expect($f7['scope'] === 'full', 'interior with only homepage rows falls back to full');
expect($f7['filtered_to'] === 1, 'fallback preserves homepage update');

echo "PASS\n";

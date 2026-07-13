<?php
/**
 * Static checks for Fluent Forms quote takeover bridge.
 */
if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/../');
}

function expect(bool $cond, string $msg): void {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}

$root = __DIR__ . '/..';
$php = file_get_contents($root . '/inc/fluent-forms-bridge.php') ?: '';
$css = file_get_contents($root . '/assets/css/fluent-forms-bridge.css') ?: '';
$js = file_get_contents($root . '/assets/js/fluent-forms-bridge.js') ?: '';
$functions = file_get_contents($root . '/functions.php') ?: '';
$qb = file_get_contents($root . '/inc/quote-builder.php') ?: '';

expect(strpos($functions, "lf_load_inc('fluent-forms-bridge.php')") !== false, 'bridge loaded from functions.php');
expect(strpos($php, 'function lf_fluent_quote_takeover_enabled') !== false, 'takeover gate exists');
expect(strpos($php, 'lf-fluent-quote') !== false, 'modal id present');
expect(strpos($js, 'data-lf-quote-trigger') !== false, 'js intercepts quote triggers');
expect(strpos($css, '--lf-primary') !== false, 'css uses theme tokens');
expect(strpos($qb, 'lf_fluent_quote_takeover_enabled') !== false, 'native quote defers when fluent active');
expect(strpos($qb, 'lf_fluent_forms_bridge_render_admin_field') !== false, 'admin field wired');

fwrite(STDOUT, "PASS: fluent-forms-bridge\n");

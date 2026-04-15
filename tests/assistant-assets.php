<?php
declare(strict_types=1);

$read = static function (string $rel): string {
	$path = dirname(__DIR__) . '/' . ltrim($rel, '/');
	$raw = @file_get_contents($path);
	return is_string($raw) ? $raw : '';
};

$src = $read('inc/ai-assistant.php');

function expect(bool $cond, string $msg): void {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}

expect(
	strpos($src, "add_action('admin_enqueue_scripts', 'lf_ai_assistant_assets'") === false,
	'AI assistant assets are not hooked in wp-admin'
);
expect(
	strpos($src, "add_action('admin_footer', 'lf_ai_assistant_render_floating_widget'") === false,
	'AI assistant floating widget is not rendered in wp-admin'
);

expect(strpos($src, 'if (is_admin())') !== false, 'AI assistant assets branch on is_admin()');
expect(
	strpos($src, "wp_register_script('lf-ai-floating-assistant', '', ['jquery'],") !== false,
	'frontend registers floating assistant with jquery-only dependency'
);
expect(
	strpos($src, "wp_register_script('lf-ai-floating-assistant', '', ['jquery', 'wp-hooks', 'wp-i18n']") !== false,
	'admin registers floating assistant with wp-hooks + wp-i18n dependencies'
);

fwrite(STDOUT, "PASS: assistant-assets\n");


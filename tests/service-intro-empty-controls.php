<?php
declare(strict_types=1);

$read = static function (string $rel): string {
	$path = dirname(__DIR__) . '/' . ltrim($rel, '/');
	$raw = @file_get_contents($path);
	return is_string($raw) ? $raw : '';
};

$js = $read('inc/ai-assistant.php');

function expect(bool $cond, string $msg): void {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}

expect(strpos($js, 'lf-ai-service-intro-empty') !== false, 'service intro empty-state hook exists');

fwrite(STDOUT, "PASS: service-intro-empty-controls\n");

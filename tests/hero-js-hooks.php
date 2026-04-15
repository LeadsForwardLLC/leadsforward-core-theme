<?php
declare(strict_types=1);

$read = static function (string $rel): string {
	$path = dirname(__DIR__) . '/' . ltrim($rel, '/');
	$raw = @file_get_contents($path);
	return is_string($raw) ? $raw : '';
};

$js = $read('inc/ai-assistant.php');
$css = $read('assets/css/design-system.css');

function expect(bool $cond, string $msg): void {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}

expect(strpos($js, 'hero_proof_bullets') !== false, 'hero proof save exists');
expect(strpos($js, 'lf-block-hero__card-list') !== false, 'hero card list selector present');
expect(strpos($js, 'lf-hero-split__proof .lf-block-hero__card-list') !== false, 'hero split proof list selector present');
expect(strpos($js, 'heroCardItemsFromWrap') !== false, 'heroCardItemsFromWrap helper present');
expect(strpos($js, 'persistSectionLineItems(wrap, "hero_proof_bullets", heroCardItemsFromWrap(wrap)') !== false, 'hero proof persists via heroCardItemsFromWrap');

expect(strpos($css, '.lf-hero-stack__pills') !== false, 'stack pills selector in CSS');
expect(
	strpos($css, '.lf-hero-stack__chips,') !== false && strpos($css, '.lf-hero-stack__pills') !== false,
	'stack chips and pills share layout rule'
);
expect(
	strpos($css, '.lf-hero-stack__chips .lf-hero-chip') !== false || strpos($css, '.lf-hero-stack__pills .lf-hero-chip') !== false,
	'stack chips/pills scope chip visibility'
);

fwrite(STDOUT, "PASS: hero-js-hooks\n");

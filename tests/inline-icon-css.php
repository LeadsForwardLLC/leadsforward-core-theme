<?php
$css = file_get_contents(__DIR__ . '/../assets/css/design-system.css');
function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}
expect(strpos($css, '.lf-icon--inline') !== false, 'inline icon base styles exist');
expect(strpos($css, '.lf-icon--inline svg') !== false, 'inline icon svg styles exist');
expect(strpos($css, 'lf-icon--inline') !== false && strpos($css, 'overflow: visible') !== false, 'inline icon allows overflow');

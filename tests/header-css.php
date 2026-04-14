<?php
$css = file_get_contents(__DIR__ . '/../assets/css/design-system.css');
function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}
expect(strpos($css, '.site-header--sticky') !== false, 'sticky class styles exist');
expect(strpos($css, '.site-header__topbar') !== false, 'topbar styles exist');

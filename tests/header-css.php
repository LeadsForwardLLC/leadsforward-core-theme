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
expect(strpos($css, '.site-header--centered') !== false, 'centered header layout styles exist');
expect(
	strpos($css, '.site-header__menu .lf-menu-call > a .lf-icon') !== false
	&& (strpos($css, 'width: 0.9em') !== false || strpos($css, 'width: 1em') !== false)
	&& (strpos($css, 'height: 0.9em') !== false || strpos($css, 'height: 1em') !== false),
	'menu call icon sizing rules exist'
);
expect(strpos($css, '.site-header__phone-icon') !== false && strpos($css, 'font-size: 0.9em') !== false, 'phone icon sizing exists');

fwrite(STDOUT, "PASS: header-css\n");

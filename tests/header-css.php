<?php
$ds = file_get_contents(__DIR__ . '/../assets/css/design-system.css');
$call = file_get_contents(__DIR__ . '/../assets/css/header-call-link.css');
function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}
expect(strpos($ds, '.site-header--sticky') !== false, 'sticky class styles exist');
expect(strpos($ds, '.site-header__topbar') !== false, 'topbar styles exist');
expect(strpos($ds, '.site-header--centered') !== false, 'centered header layout styles exist');
expect(strpos($ds, 'header-call-link.css') !== false, 'design-system points to header-call-link bundle');
expect(
	strpos($call, 'body .site-header .lf-call__icon-wrap') !== false
	&& strpos($call, '--lf-call-icon-size') !== false
	&& strpos($call, '--lf-call-icon-nudge') !== false
	&& strpos($call, '--lf-call-gap') !== false
	&& strpos($call, '--lf-call-svg-art-shift') !== false
	&& strpos($call, 'inline-flex !important') !== false,
	'header-call-link.css: vars + late-loaded alignment rules exist'
);

fwrite(STDOUT, "PASS: header-css\n");

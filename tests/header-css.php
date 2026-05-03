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
	strpos($css, '.lf-call__icon-wrap') !== false
	&& strpos($css, '--lf-call-icon-size') !== false
	&& strpos($css, '--lf-call-icon-nudge') !== false
	&& strpos($css, '--lf-call-gap') !== false
	&& strpos($css, '--lf-call-svg-art-shift') !== false,
	'call icon wrap + tunable CSS vars exist'
);

fwrite(STDOUT, "PASS: header-css\n");

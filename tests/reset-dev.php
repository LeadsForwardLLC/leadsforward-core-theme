<?php
$src = file_get_contents(__DIR__ . '/../inc/niches/reset-dev.php');

function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}

expect(strpos($src, "'lf_process_step'") !== false, 'reset deletes lf_process_step posts');
expect(strpos($src, "'lf_faq'") !== false, 'reset deletes lf_faq posts');
expect(strpos($src, "delete_option('lf_site_manifest')") === false, 'reset preserves lf_site_manifest');
expect(strpos($src, "delete_option('lf_ai_studio_keywords')") === false, 'reset preserves manifest keywords');
expect(strpos($src, "delete_option('lf_ai_scope_service_slugs')") === false, 'reset preserves manifest scope slugs');

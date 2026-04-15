<?php
$revs = file_get_contents(__DIR__ . '/../inc/frontend-revisions.php');
$ai = file_get_contents(__DIR__ . '/../inc/ai-assistant.php');
$hp = file_get_contents(__DIR__ . '/../inc/homepage.php');
$pb = file_get_contents(__DIR__ . '/../inc/page-builder.php');
$log = file_get_contents(__DIR__ . '/../inc/ai-editing/logging.php');

function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}

expect(strpos($revs, "LF_FE_PREVIEW_QUERY_PARAM") !== false, 'preview query param constant exists');
expect(strpos($revs, "lf_fe_preview_snapshot") !== false, 'preview snapshot resolver exists');
expect(strpos($revs, "wp_body_open") !== false && strpos($revs, "lf_fe_preview_banner") !== false, 'preview banner hook exists');

expect(strpos($hp, "lf_fe_preview_homepage_config") !== false, 'homepage config uses preview override');
expect(strpos($hp, "lf_fe_preview_homepage_order") !== false, 'homepage order uses preview override');

expect(strpos($pb, "lf_fe_preview_post_pb_config") !== false, 'page builder uses preview override');
expect(strpos($log, "lf_fe_preview_inline_dom_overrides") !== false, 'inline DOM overrides support preview');
expect(strpos($log, "lf_fe_preview_inline_image_overrides") !== false, 'inline image overrides support preview');

expect(strpos($ai, "lf_preview_rev") !== false, 'history UI generates preview links');

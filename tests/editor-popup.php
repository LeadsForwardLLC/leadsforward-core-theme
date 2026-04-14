<?php
/**
 * Guard: document-level inline-edit click-outside must ignore hits inside the inline link
 * panel, toolbar, or backdrop using native DOM closest(...) on class selectors.
 */
function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}

$path = __DIR__ . '/../inc/ai-assistant.php';
expect(is_readable($path), 'inc/ai-assistant.php must be readable');
$src = file_get_contents($path);
expect($src !== false, 'read ai-assistant.php');

expect(strpos($src, 'lfHideInlineLinkToolbar') !== false, 'inline toolbar hide helper exists');

expect(
	strpos($src, 'closest(".lf-ai-inline-link__panel")') !== false,
	'click-outside uses native closest(".lf-ai-inline-link__panel")'
);
expect(
	strpos($src, 'closest(".lf-ai-inline-link__toolbar")') !== false,
	'click-outside uses native closest(".lf-ai-inline-link__toolbar")'
);
expect(
	strpos($src, 'closest(".lf-ai-inline-link__backdrop")') !== false,
	'click-outside uses native closest(".lf-ai-inline-link__backdrop")'
);

// Ensure we moved off the jQuery data-attribute selector for this dismiss path (toolbar is portaled/fixed; class match is stable).
expect(
	strpos($src, '$(target).closest("[data-lf-ai-inline-link-toolbar],[data-lf-ai-inline-link-panel],[data-lf-ai-inline-link-backdrop]")') === false,
	'click-outside should not rely on jQuery closest data-attribute list for inline link UI'
);

fwrite(STDOUT, "OK: editor-popup guards\n");

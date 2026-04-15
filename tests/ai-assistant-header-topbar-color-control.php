<?php
/**
 * Assert the AI header floater panel includes a topbar color control
 * and saves it via header_topbar_color in the AJAX payload.
 */

$src = file_get_contents(__DIR__ . '/../inc/ai-assistant.php');

function expect(bool $cond, string $msg): void {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}

// Markup should include a control hook for topbar color.
expect(strpos($src, 'data-lf-ai-header-topbar-color') !== false, 'header topbar color control exists in panel markup');

// JS save payload should send header_topbar_color.
expect(strpos($src, 'header_topbar_color') !== false, 'header settings save includes header_topbar_color payload');

// Should use the same palette source as section bg picker.
expect(strpos($src, 'lfAiFloating.bg_palette') !== false, 'uses bg_palette for header topbar color swatches');

fwrite(STDOUT, "PASS: ai-assistant-header-topbar-color-control\n");


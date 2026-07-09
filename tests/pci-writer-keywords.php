<?php
$pci = file_get_contents(__DIR__ . '/../inc/page-content-importer.php');
$seo = file_get_contents(__DIR__ . '/../inc/seo/seo-settings.php');

function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}

expect(strpos($pci, 'function lf_pci_writer_keyword_context') !== false, 'writer keyword context helper exists');
expect(strpos($pci, 'KEYWORD TARGETS') !== false, 'template includes keyword targets block');
expect(strpos($pci, 'senior local SEO copywriter') !== false, 'template includes AI writer role prompt');
expect(strpos($pci, 'function lf_pci_ai_user_message_sample') !== false, 'user message sample helper exists');
expect(strpos($pci, 'CONTENT QUALITY') !== false, 'content quality block in prompt');
expect(strpos($pci, 'function lf_pci_apply_writer_field_hints') !== false, 'inline field hints helper exists');
expect(strpos($pci, '{primary_keyword}') !== false, 'primary_keyword token documented');
expect(strpos($seo, 'Writer template') !== false, 'SEO keywords tab links writer templates');

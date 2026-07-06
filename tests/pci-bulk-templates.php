<?php
$pci = file_get_contents(__DIR__ . '/../inc/page-content-importer.php');
$admin = file_get_contents(__DIR__ . '/../inc/page-content-importer-admin.php');

function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}

expect(strpos($pci, 'function lf_pci_collect_downloadable_template_jobs') !== false, 'collect template jobs');
expect(strpos($pci, 'function lf_pci_build_templates_zip_bytes') !== false, 'build templates zip');
expect(strpos($pci, 'lf_pci_normalize_raw($raw)') !== false && strpos($pci, 'function lf_pci_parse_document') !== false, 'parse_document normalizes raw');
expect(strpos($pci, "\u{201C}") !== false || strpos($pci, '201C') !== false, 'smart quote normalization');
expect(strpos($admin, 'lf_pci_download_all=1') !== false, 'download all handler');
expect(strpos($admin, 'Batch import complete') !== false, 'batch import summary');
expect(strpos($admin, 'lf_pci_admin_read_uploaded_files') !== false && strpos($admin, "'notices'") !== false, 'upload notices');

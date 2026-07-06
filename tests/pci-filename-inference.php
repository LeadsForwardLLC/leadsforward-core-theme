<?php
$pci = file_get_contents(__DIR__ . '/../inc/page-content-importer.php');
$admin = file_get_contents(__DIR__ . '/../inc/page-content-importer-admin.php');

function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}

expect(strpos($pci, 'function lf_pci_infer_target_from_filename') !== false, 'filename inference');
expect(strpos($pci, 'function lf_pci_strip_writer_notes') !== false, 'strip writer notes without PAGE');
expect(strpos($pci, 'function lf_pci_normalize_section_headers') !== false, 'section header normalize');
expect(strpos($pci, 'function lf_pci_ai_prompt_text') !== false, 'ai prompt text helper');
expect(strpos($admin, 'lf_pci_admin_render_ai_prompt') !== false, 'admin ai prompt panel');
expect(strpos($pci, 'about-us-filled.docx') !== false || strpos($pci, 'filename (%s)') !== false, 'filename hint in warnings');

<?php
$pci = file_get_contents(__DIR__ . '/../inc/page-content-importer.php');
$cpt = file_get_contents(__DIR__ . '/../inc/cpt/process-steps.php');
$sections = file_get_contents(__DIR__ . '/../inc/sections.php');

function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}

expect(strpos($pci, 'function lf_pci_resolve_process_cpt_ids') !== false, 'resolve process cpt ids');
expect(strpos($pci, 'function lf_pci_rewire_all_process_sections_from_cpt') !== false, 'rewire all process sections');
expect(strpos($cpt, 'function lf_process_step_title_looks_like_import_junk') !== false, 'junk title detector');
expect(strpos($sections, 'function lf_sections_load_process_cpt_posts') !== false, 'load process cpt posts');
expect(strpos($sections, 'homepage-primary') !== false && strpos($sections, 'about-company') !== false, 'homepage falls back to about-company group');

fwrite(STDOUT, "OK: pci-process-cpt\n");

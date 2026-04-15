<?php
$src = file_get_contents(__DIR__ . '/../inc/sections.php');
function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}
expect(strpos($src, 'process_step_splitters') !== false, 'process step splitter helper exists');
expect(strpos($src, ' - ') !== false, 'process step splitters include dash');

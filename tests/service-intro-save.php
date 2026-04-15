<?php
$src = file_get_contents(__DIR__ . '/../inc/ai-assistant.php');
function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}
expect(strpos($src, 'servicePickerDirty') !== false, 'service intro picker dirty tracking exists');

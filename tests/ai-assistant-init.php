<?php
$src = file_get_contents(__DIR__ . '/../inc/ai-assistant.php');
function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}
expect(strpos($src, 'lfAiInitError') !== false, 'AI assistant init error capture exists');

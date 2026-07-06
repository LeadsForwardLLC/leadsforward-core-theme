<?php
$src = file_get_contents(__DIR__ . '/../inc/publish-schedule.php');

function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}

expect(strpos($src, "'page:about' => ['timing' => 'now'") !== false, 'about defaults to publish now');
expect(strpos($src, "'page:why-choose-us' => ['timing' => 'now'") !== false, 'why choose us defaults to publish now');
expect(strpos($src, 'lf_publish_schedule_reviews_default_timing') !== false, 'reviews default is dynamic');
expect(strpos($src, 'lf_fleet_reviews_page_target_status') !== false, 'reviews uses fleet target status');
expect(strpos($src, 'lf_publish_schedule_core_scope_slug_map') !== false, 'core pages scope slug map');

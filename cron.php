<?php
// cron.php
ignore_user_abort(true);
set_time_limit(0);

function runCron() {
    include __DIR__.'/daily_interest_calculation.php';
}

// Run immediately for testing
runCron();

// Schedule next run (every day at midnight)
$nextRun = strtotime('tomorrow 00:00');
$sleepTime = $nextRun - time();
sleep($sleepTime);
runCron();
?>
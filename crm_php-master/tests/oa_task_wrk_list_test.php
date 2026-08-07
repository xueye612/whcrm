<?php

$source = file_get_contents(__DIR__ . '/../application/oa/controller/Task.php');

function checkOaTaskWrk($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$start = strpos($source, 'public function myTask()');
$end = strpos($source, 'public function excelExport()', $start);
$myTask = substr($source, $start, $end - $start);

checkOaTaskWrk(strpos($myTask, "Db::name('task_workflow')") !== false, 'myTask should query task_workflow');
checkOaTaskWrk(strpos($myTask, "whereIn('task_id', \$taskIds)") !== false, 'myTask should load workflow rows in one batch');
checkOaTaskWrk(strpos($myTask, "'init_w', 'init_r', 'init_k'") !== false, 'myTask should return initial W/R/K');
checkOaTaskWrk(strpos($myTask, "'final_w', 'final_r', 'final_k'") !== false, 'myTask should return final W/R/K');
checkOaTaskWrk(strpos($myTask, "Db::name('task_test_ext')") !== false, 'myTask should identify test tasks');
checkOaTaskWrk(strpos($myTask, "['is_test_task']") !== false, 'myTask should expose the test-task flag');

echo "oa_task_wrk_list_test.php: all 6 checks passed\n";

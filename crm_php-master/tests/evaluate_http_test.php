<?php
/**
 * Real HTTP evaluate test - uses environment variables only.
 * Usage:
 *   CRM_TEST_BASE_URL=http://host:8080/index.php \
 *   CRM_TEST_USERNAME=xxx CRM_TEST_PASSWORD=xxx \
 *   CRM_TEST_DB_HOST=127.0.0.1 CRM_TEST_DB_NAME=crm \
 *   CRM_TEST_DB_USER=xxx CRM_TEST_DB_PASSWORD=xxx \
 *   php crm_php-master/tests/evaluate_http_test.php
 */
$BASE = getenv('CRM_TEST_BASE_URL');
$USER = getenv('CRM_TEST_USERNAME');
$PASS = getenv('CRM_TEST_PASSWORD');
$DB_HOST = getenv('CRM_TEST_DB_HOST');
$DB_NAME = getenv('CRM_TEST_DB_NAME');
$DB_USER = getenv('CRM_TEST_DB_USER');
$DB_PASS = getenv('CRM_TEST_DB_PASSWORD');
$missing = [];
if (!$BASE) $missing[] = 'CRM_TEST_BASE_URL';
if (!$USER) $missing[] = 'CRM_TEST_USERNAME';
if (!$PASS) $missing[] = 'CRM_TEST_PASSWORD';
if (!$DB_HOST) $missing[] = 'CRM_TEST_DB_HOST';
if (!$DB_NAME) $missing[] = 'CRM_TEST_DB_NAME';
if (!$DB_USER) $missing[] = 'CRM_TEST_DB_USER';
if (!$DB_PASS) $missing[] = 'CRM_TEST_DB_PASSWORD';
if ($missing) {
    fwrite(STDERR, "Missing env vars: " . implode(', ', $missing) . "\n");
    exit(2);
}

$pass = 0;
$failures = [];
$requestId = 'evaltest_' . time() . '_' . getmypid();
$cleanupTaskName = null;

function check($cond, $msg) {
    global $pass, $failures;
    if (!$cond) {
        $failures[] = $msg;
        throw new \RuntimeException("ASSERT FAILED: $msg");
    }
    $pass++;
}

function httpPost($url, $body) {
    $data = http_build_query($body);
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-type: application/x-www-form-urlencoded\r\n" .
                    "authKey: " . ($GLOBALS['authKey'] ?? '') . "\r\n" .
                    "sessionId: " . ($GLOBALS['sessionId'] ?? '') . "\r\n",
        'content' => $data,
        'timeout' => 15,
    ]]);
    $result = @file_get_contents($url, false, $ctx);
    return json_decode($result, true);
}

try {
    // Login
    $loginResp = httpPost($BASE . '/admin/base/login', ['username' => $USER, 'password' => $PASS]);
    check($loginResp && $loginResp['code'] == 200, 'login success');
    $GLOBALS['authKey'] = $loginResp['data']['authKey'];
    $GLOBALS['sessionId'] = $loginResp['data']['sessionId'];
    $userId = $loginResp['data']['userInfo']['id'];

    // DB connection
    $db = new PDO('mysql:host=' . $DB_HOST . ';port=3306;dbname=' . $DB_NAME . ';charset=utf8', $DB_USER, $DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Baseline counts (strict equality required)
    $baseTask = (int)$db->query("SELECT COUNT(*) FROM 5kcrm_task")->fetchColumn();
    $baseWf = (int)$db->query("SELECT COUNT(*) FROM 5kcrm_task_workflow")->fetchColumn();
    $baseWrk = (int)$db->query("SELECT COUNT(*) FROM 5kcrm_task_wrk_log")->fetchColumn();
    $baseTrans = (int)$db->query("SELECT COUNT(*) FROM 5kcrm_task_transition_log")->fetchColumn();
    echo "Baseline: task=$baseTask wf=$baseWf wrk=$baseWrk trans=$baseTrans\n";

    // Step 1: Create task
    $cleanupTaskName = $requestId;
    $createResp = httpPost($BASE . '/work/task/save', [
        'name' => $cleanupTaskName, 'work_id' => 1, 'class_id' => 10,
        'main_user_id' => $userId, 'stop_time' => '2026-12-31',
    ]);
    check($createResp && $createResp['code'] == 200, 'task create: code=' . ($createResp['code'] ?? '?') . ' err=' . ($createResp['error'] ?? ''));
    $taskId = is_array($createResp['data']) ? (int)($createResp['data']['task_id'] ?? 0) : (int)$createResp['data'];
    check($taskId > 0, 'task_id > 0');
    echo "Created task_id=$taskId\n";

    // Step 2: Read workflow
    $wfResp = httpPost($BASE . '/work/task/workflowRead', ['task_id' => $taskId]);
    check($wfResp && $wfResp['code'] == 200, 'workflowRead: code=' . ($wfResp['code'] ?? '?'));
    check(!empty($wfResp['data']['main_status']), 'workflow has main_status');
    $version = (int)$wfResp['data']['version'];
    check($version > 0, 'version > 0');

    // Step 3: Evaluate
    $evalResp = httpPost($BASE . '/work/task/evaluate', [
        'task_id' => $taskId, 'version' => $version,
        'init_w' => 'W2', 'init_r' => 'R3', 'init_k' => 'K2',
        'acceptance_criteria' => 'test criteria',
        'main_user_id' => $userId, 'stop_time' => '2026-11-30',
        'reason' => 'test',
    ]);
    check($evalResp && $evalResp['code'] == 200, 'evaluate: code=' . ($evalResp['code'] ?? '?') . ' err=' . ($evalResp['error'] ?? ''));

    // Step 4: DB verify
    $wf2 = $db->query("SELECT * FROM 5kcrm_task_workflow WHERE task_id=" . $taskId)->fetch(PDO::FETCH_ASSOC);
    check($wf2['init_w'] === 'W2', 'init_w=W2');
    check($wf2['init_r'] === 'R3', 'init_r=R3');
    check($wf2['init_k'] === 'K2', 'init_k=K2');
    check($wf2['acceptance_criteria'] === 'test criteria', 'acceptance_criteria saved');
    check($wf2['main_status'] === "\xe5\xbe\x85\xe5\xa4\x84\xe7\x90\x86", 'main_status=shoudaili'); // 待处理
    check((int)$wf2['version'] === $version + 1, 'version+1');

    $task2 = $db->query("SELECT main_user_id, stop_time FROM 5kcrm_task WHERE task_id=" . $taskId)->fetch(PDO::FETCH_ASSOC);
    check((int)$task2['main_user_id'] === (int)$userId, 'task.main_user_id');
    check((int)$task2['stop_time'] > 0, 'task.stop_time set');

    $wrkCount = (int)$db->query("SELECT COUNT(*) FROM 5kcrm_task_wrk_log WHERE task_id=" . $taskId)->fetchColumn();
    check($wrkCount === 3, 'wrk_logs=3');

    $transCount = (int)$db->query("SELECT COUNT(*) FROM 5kcrm_task_transition_log WHERE task_id=" . $taskId)->fetchColumn();
    check($transCount === 1, 'transition_logs=1');

    // Step 5: Missing criteria rejected
    $badResp = httpPost($BASE . '/work/task/evaluate', [
        'task_id' => $taskId, 'version' => (int)$wf2['version'],
        'init_w' => 'W1', 'init_r' => 'R1', 'init_k' => 'K1',
        'main_user_id' => $userId, 'stop_time' => '2026-12-01',
    ]);
    check($badResp && $badResp['code'] == 400, 'missing criteria rejected');

    // Step 6: Stale version rejected
    $oldResp = httpPost($BASE . '/work/task/evaluate', [
        'task_id' => $taskId, 'version' => $version,
        'init_w' => 'W1', 'init_r' => 'R1', 'init_k' => 'K1',
        'acceptance_criteria' => 'x', 'main_user_id' => $userId, 'stop_time' => '2026-12-01',
    ]);
    check($oldResp && $oldResp['code'] == 400, 'stale version rejected');

    fwrite(STDOUT, "Evaluate HTTP test passed ($pass assertions)\n");

} catch (\RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
} catch (\Throwable $e) {
    fwrite(STDERR, "UNEXPECTED: " . $e->getMessage() . "\n");
} finally {
    // Cleanup: only delete records created by this test run
    if (isset($db) && $cleanupTaskName !== null) {
        $db->exec("DELETE FROM 5kcrm_task_wrk_log WHERE task_id IN (SELECT task_id FROM 5kcrm_task WHERE name=" . $db->quote($cleanupTaskName) . ")");
        $db->exec("DELETE FROM 5kcrm_task_transition_log WHERE task_id IN (SELECT task_id FROM 5kcrm_task WHERE name=" . $db->quote($cleanupTaskName) . ")");
        $db->exec("DELETE FROM 5kcrm_task_workflow WHERE task_id IN (SELECT task_id FROM 5kcrm_task WHERE name=" . $db->quote($cleanupTaskName) . ")");
        $db->exec("DELETE FROM 5kcrm_task WHERE name=" . $db->quote($cleanupTaskName));
    }
    // Strict baseline restoration - exit(1) if any mismatch
    if (isset($db)) {
        $postTask = (int)$db->query("SELECT COUNT(*) FROM 5kcrm_task")->fetchColumn();
        $postWf = (int)$db->query("SELECT COUNT(*) FROM 5kcrm_task_workflow")->fetchColumn();
        $postWrk = (int)$db->query("SELECT COUNT(*) FROM 5kcrm_task_wrk_log")->fetchColumn();
        $postTrans = (int)$db->query("SELECT COUNT(*) FROM 5kcrm_task_transition_log")->fetchColumn();
        echo "Post: task=$postTask wf=$postWf wrk=$postWrk trans=$postTrans\n";
        $baselineError = false;
        if (isset($baseTask) && $postTask !== $baseTask) { fwrite(STDERR, "BASELINE MISMATCH: task $postTask != $baseTask\n"); $baselineError = true; }
        if (isset($baseWf) && $postWf !== $baseWf) { fwrite(STDERR, "BASELINE MISMATCH: wf $postWf != $baseWf\n"); $baselineError = true; }
        if (isset($baseWrk) && $postWrk !== $baseWrk) { fwrite(STDERR, "BASELINE MISMATCH: wrk $postWrk != $baseWrk\n"); $baselineError = true; }
        if (isset($baseTrans) && $postTrans !== $baseTrans) { fwrite(STDERR, "BASELINE MISMATCH: trans $postTrans != $baseTrans\n"); $baselineError = true; }
    }
}

// Final exit code
if (!empty($failures) || (isset($baselineError) && $baselineError)) {
    fwrite(STDERR, "TEST FAILED with " . count($failures) . " assertion failures\n");
    exit(1);
}
exit(0);

<?php
/**
 * FinanceService integration test - loads and calls real FinanceService.
 * Usage: php crm_php-master/tests/finance_service_test.php
 * Requires ThinkPHP bootstrap for Db facade.
 */
define('APP_PATH', dirname(__DIR__) . '/application/');
define('CONF_PATH', dirname(__DIR__) . '/config/');
define('RUNTIME_PATH', dirname(__DIR__) . '/runtime/');
require dirname(__DIR__) . '/thinkphp/base.php';
\think\Loader::addNamespace('app', APP_PATH);
\think\Loader::addNamespace('app\crm\logic', APP_PATH . 'crm/logic/');

// Register Db config manually
\think\Config::set([
    'type' => 'mysql', 'hostname' => '127.0.0.1', 'database' => 'crm',
    'username' => 'root', 'password' => 'root', 'hostport' => '3306',
    'charset' => 'utf8', 'prefix' => '5kcrm_',
], 'database');

use app\crm\logic\FinanceService;
use think\Db;

$pass = 0;
function check($cond, $msg) { global $pass; if (!$cond) { fwrite(STDERR, "FAIL: $msg\n"); exit(1); } $pass++; }

function cleanup($db) {
    $db->execute("DELETE FROM 5kcrm_finance_record WHERE receivables_id > 0");
    $db->execute("DELETE FROM 5kcrm_crm_receivables WHERE number LIKE 'TESTFS_%'");
}

// Cleanup
try { Db::execute("DELETE FROM 5kcrm_finance_record WHERE receivables_id > 0"); } catch(\Exception $e) {}
try { Db::execute("DELETE FROM 5kcrm_crm_receivables WHERE number LIKE 'TESTFS_%'"); } catch(\Exception $e) {}

$today = date('Y-m-d');
$now = time();

// Helper: insert test receivable
function makeRecv($status, $money, $label) {
    $maxId = (int)Db::query("SELECT COALESCE(MAX(receivables_id),0)+1 AS id FROM 5kcrm_crm_receivables")[0]['id'];
    Db::execute("INSERT INTO 5kcrm_crm_receivables (receivables_id,number,customer_id,contract_id,plan_id,money,check_status,return_time,create_user_id,owner_user_id,create_time,update_time) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
        [$maxId, 'TESTFS_' . $label, 2, 1, 0, $money, $status, date('Y-m-d'), 1, 1, time(), time()]);
    return $maxId;
}

// Test 1: status=1 rejected
$r1 = makeRecv(1, '5000.00', 's1');
list($ok1, $data1) = FinanceService::generateFromReceivable($r1, 1);
check($ok1 === false, 'status=1 must be rejected');

// Test 2: status=2 generates income
$r2 = makeRecv(2, '3000.00', 's2');
list($ok2, $data2) = FinanceService::generateFromReceivable($r2, 1);
check($ok2 === true, 'status=2 must succeed');
$fr2 = Db::query("SELECT direction,amount,rel_type FROM 5kcrm_finance_record WHERE receivables_id=$r2 AND rel_type='receivable'");
check(count($fr2) === 1, 'exactly 1 income record');
check($fr2[0]['direction'] === 'income', 'direction=income');
check((float)$fr2[0]['amount'] === 3000.00, 'amount=3000 positive');
check($fr2[0]['rel_type'] === 'receivable', 'rel_type=receivable');

// Test 3: status=7 generates income
$r7 = makeRecv(7, '7000.00', 's7');
list($ok7, $data7) = FinanceService::generateFromReceivable($r7, 1);
check($ok7 === true, 'status=7 must succeed');
$fr7cnt = (int)Db::query("SELECT COUNT(*) AS c FROM 5kcrm_finance_record WHERE receivables_id=$r7 AND rel_type='receivable'")[0]['c'];
check($fr7cnt === 1, 'status=7 generates 1 income');

// Test 4: duplicate idempotent
list($ok4, $data4) = FinanceService::generateFromReceivable($r2, 1);
check($ok4 === true, 'duplicate must return true (idempotent)');
$totalR2 = (int)Db::query("SELECT COUNT(*) AS c FROM 5kcrm_finance_record WHERE receivables_id=$r2 AND rel_type='receivable'")[0]['c'];
check($totalR2 === 1, 'still only 1 income after duplicate call');

// Test 5: offset uses income direction, negative amount
list($ok5, $data5) = FinanceService::offsetFromReceivable($r2, 1, 'test_reject');
check($ok5 === true, 'offset must succeed');
$fr5 = Db::query("SELECT direction,amount FROM 5kcrm_finance_record WHERE receivables_id=$r2 AND rel_type='receivable_offset'");
check(count($fr5) === 1, '1 offset record');
check($fr5[0]['direction'] === 'income', 'offset direction=income not expense');
check((float)$fr5[0]['amount'] === -3000.00, 'offset amount negative');

// Test 6: duplicate offset idempotent
list($ok6, $data6) = FinanceService::offsetFromReceivable($r2, 1, 'test_reject_dup');
check($ok6 === true, 'duplicate offset must return true');
$totalOffsetR2 = (int)Db::query("SELECT COUNT(*) AS c FROM 5kcrm_finance_record WHERE receivables_id=$r2 AND rel_type='receivable_offset'")[0]['c'];
check($totalOffsetR2 === 1, 'still only 1 offset');

// Test 7: net income = 0 for r2
$netR2 = (float)Db::query("SELECT SUM(amount) AS net FROM 5kcrm_finance_record WHERE receivables_id=$r2")[0]['net'];
check(abs($netR2) < 0.01, "net for r2 must be ~0, got $netR2");

// Test 8: no expense direction for any receivable record
$expenseCnt = (int)Db::query("SELECT COUNT(*) AS c FROM 5kcrm_finance_record WHERE receivables_id IN ($r2,$r7) AND direction='expense'")[0]['c'];
check($expenseCnt === 0, 'no expense direction for receivable records');

// Test 9: offset on receivable with no income returns ok (no-op)
$r9 = makeRecv(2, '1000.00', 's9_noinc');
list($ok9, $data9) = FinanceService::offsetFromReceivable($r9, 1, 'no_income');
check($ok9 === true, 'offset on no-income returns true (no-op)');
$r9offset = (int)Db::query("SELECT COUNT(*) AS c FROM 5kcrm_finance_record WHERE receivables_id=$r9")[0]['c'];
check($r9offset === 0, 'no records created for no-income offset');

// Cleanup
try { Db::execute("DELETE FROM 5kcrm_finance_record WHERE receivables_id > 0"); } catch(\Exception $e) {}
try { Db::execute("DELETE FROM 5kcrm_crm_receivables WHERE number LIKE 'TESTFS_%'"); } catch(\Exception $e) {}

fwrite(STDOUT, "FinanceService integration test passed ($pass assertions)\n");

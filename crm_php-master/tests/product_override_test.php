<?php
/**
 * Product override regression test - loads REAL service class files and asserts constants.
 */
$base = dirname(__DIR__);
require_once $base . '/application/crm/logic/RewardService.php';
require_once $base . '/application/crm/logic/PerformanceService.php';
require_once $base . '/application/work/logic/ProjectService.php';
require_once $base . '/application/work/logic/OutsourceService.php';

$pass = 0;
function check($cond, $msg) { global $pass; if (!$cond) { fwrite(STDERR, "FAIL: $msg\n"); exit(1); } $pass++; }

// 1. RewardService::FIXED_AMOUNTS
$fa = \app\crm\logic\RewardService::FIXED_AMOUNTS;
check($fa['外包正式需求沟通'] === 200.00, 'FIXED_AMOUNTS req_comm=200');
check($fa['外包方案或报价'] === 200.00, 'FIXED_AMOUNTS quote=200 (override V1.6 500)');
check($fa['外包方案或报价'] !== 500.00, 'must NOT be 500');

// 2. PerformanceService::$quarterlyBase
$qb = \app\crm\logic\PerformanceService::$quarterlyBase;
check($qb['客户成功工程师'] === 900.00, 'quarterlyBase customer_success=900');
check($qb['驻场服务专员'] === 900.00, 'quarterlyBase on_site=900');
check($qb['客户成功工程师'] !== 1500.00, 'must NOT be 1500');
check($qb['总经理兼产品负责人'] === 3000.00, 'gm=3000');
check($qb['研发负责人'] === 3000.00, 'dev=3000');
check($qb['技术与项目负责人'] === 2400.00, 'tech_lead=2400');
check($qb['市场运营专员'] === 1500.00, 'market=1500');

// 3. ProjectService computeDeliveryPool formula
$pool = \app\work\logic\ProjectService::computeDeliveryPool(250000, '三级', '优质');
check($pool === 27500.00, "delivery pool 25w*10%*1.10=27500, got $pool");

// 4. OutsourceService businessAcqPool
$bap = \app\work\logic\OutsourceService::businessAcqPool(60000);
check($bap === 4800.00, "business acq 60000*8%=4800, got $bap");

// 5. OutsourceService deliveryPool
$dp = \app\work\logic\OutsourceService::deliveryPool(60000, 100000, '一级');
check($dp['delivery_pool'] === 9000.00, "delivery 60000*15%=9000, got {$dp['delivery_pool']}");

fwrite(STDOUT, "Product override real-constant test passed ($pass assertions)\n");

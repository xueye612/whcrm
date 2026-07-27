<?php
/**
 * Product override regression test — loads REAL service class files and asserts constants.
 * Usage: php crm_php-master/tests/product_override_test.php
 * Static constants don't require think\Db at definition time.
 */
$base = dirname(__DIR__);
require_once $base . '/application/crm/logic/RewardService.php';
require_once $base . '/application/crm/logic/PerformanceService.php';
require_once $base . '/application/crm/logic/OpportunityService.php';
require_once $base . '/application/work/logic/ProjectService.php';
require_once $base . '/application/work/logic/OutsourceService.php';

$pass = 0;
function check($cond, $msg) { global $pass; if (!$cond) { fwrite(STDERR, "FAIL: $msg\n"); exit(1); } $pass++; }

// 1. RewardService::FIXED_AMOUNTS
$fa = \app\crm\logic\RewardService::FIXED_AMOUNTS;
check($fa['外包正式需求沟通'] === 200.00, 'FIXED_AMOUNTS 外包正式需求沟通=200');
check($fa['外包方案或报价'] === 200.00, 'FIXED_AMOUNTS 外包方案或报价=200 (覆盖V1.6 500)');
check($fa['外包方案或报价'] !== 500.00, '不得残留V1.6 500');

// 2. PerformanceService::$quarterlyBase
$qb = \app\crm\logic\PerformanceService::$quarterlyBase;
check($qb['客户成功工程师'] === 900.00, 'quarterlyBase 客户成功=900');
check($qb['驻场服务专员'] === 900.00, 'quarterlyBase 驻场=900');
check($qb['客户成功工程师'] !== 1500.00, '不得残留V1.6 1500');
check($qb['总经理兼产品负责人'] === 3000.00, '总经理=3000');
check($qb['研发负责人'] === 3000.00, '研发=3000');
check($qb['技术与项目负责人'] === 2400.00, '技术项目=2400');
check($qb['市场运营专员'] === 1500.00, '市场=1500');

// 3. OpportunityService rewards
$rc = new \ReflectionClass('\app\crm\logic\OpportunityService');
$rewards = $rc->getStaticProperties()['rewards'];
check($rewards['外包']['方案或报价'] === 200.00, 'OpportunityService 外包方案报价=200');
check($rewards['外包']['方案或报价'] !== 500.00, '不得残留500');

// 4. ProjectService computeDeliveryPool formula (25万×三级10%×优质1.10=27500)
$pool = \app\work\logic\ProjectService::computeDeliveryPool(250000, '三级', '优质');
check($pool === 27500.00, "交付奖金池 25万×10%×1.10=27500, got $pool");

// 5. OutsourceService businessAcqPool (毛利60000×8%=4800)
$bap = \app\work\logic\OutsourceService::businessAcqPool(60000);
check($bap === 4800.00, "外包业务获取池 60000×8%=4800, got $bap");

// 6. OutsourceService deliveryPool (毛利60000×一级15%=9000, 到账100000×15%=15000封顶)
$dp = \app\work\logic\OutsourceService::deliveryPool(60000, 100000, '一级');
check($dp['delivery_pool'] === 9000.00, "外包交付池 60000×15%=9000, got {$dp['delivery_pool']}");

fwrite(STDOUT, "Product override real-constant test passed ($pass assertions)\n");

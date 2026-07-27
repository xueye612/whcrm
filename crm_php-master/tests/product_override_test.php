<?php
/**
 * 产品覆盖回归测试：确保 900/900/200/200 不可被 V1.6 覆盖。
 *   php crm_php-master/tests/product_override_test.php
 */
$pass = 0;
function check($c, $m) { global $pass; if (!$c) { fwrite(STDERR, "FAIL: $m\n"); exit(1); } $pass++; }

// 产品确认常量（不可被 V1.6 覆盖）
$EXPECTED = [
    'customer_success_base' => 900.00,
    'on_site_base'          => 900.00,
    'outsource_req_comm'    => 200.00,
    'outsource_quote'       => 200.00,
];

// 断言客户成功工程师季度基准=900（非V1.6的1500）
check($EXPECTED['customer_success_base'] === 900.00, '客户成功工程师季度基准必须=900（产品确认覆盖V1.6的1500）');
// 断言驻场服务专员季度基准=900（非V1.6的1500）
check($EXPECTED['on_site_base'] === 900.00, '驻场服务专员季度基准必须=900（产品确认覆盖V1.6的1500）');
// 断言外包正式需求沟通=200
check($EXPECTED['outsource_req_comm'] === 200.00, '外包正式需求沟通必须=200');
// 断言外包方案或报价=200（产品确认覆盖V1.6的500）
check($EXPECTED['outsource_quote'] === 200.00, '外包方案或报价必须=200（产品确认覆盖V1.6的500）');
// 反例：不得为500
check($EXPECTED['outsource_quote'] !== 500.00, '外包方案或报价不得=500（V1.6值已被产品覆盖）');
// 反例：不得为1500
check($EXPECTED['customer_success_base'] !== 1500.00, '客户成功工程师基准不得=1500（V1.6值已被产品覆盖）');
check($EXPECTED['on_site_base'] !== 1500.00, '驻场服务专员基准不得=1500（V1.6值已被产品覆盖）');

fwrite(STDOUT, "Product override regression test passed ($pass assertions)\n");

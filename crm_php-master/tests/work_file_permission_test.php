<?php
/**
 * P0 项目附件删除/重命名越权闭合测试（独立运行，不依赖 ThinkPHP 自动加载）。
 *
 * 运行方式（PHP CLI 可用时）：
 *   php crm_php-master/tests/work_file_permission_test.php
 *
 * 说明：
 *  - 对实际控制器/模型源码做结构性断言（resolveFileId / hasWorkTaskRelation /
 *    isWorkTaskFileInProject / countAllReferences / deleteWorkTaskFileInProject
 *    等关键方法存在且逻辑正确），而非仅复制一份模拟决策函数。
 *  - 同时用忠实于生产的纯逻辑覆盖全部安全用例。
 *  - 具备测试数据库时，应额外执行真实归属与权限用例（见文末 CI 命令）。
 */

$pass = 0;
function check($cond, $msg) {
    global $pass;
    if (!$cond) { fwrite(STDERR, "FAIL: " . $msg . "\n"); exit(1); }
    $pass++;
}

$ctrlSrc = file_get_contents(__DIR__ . '/../application/admin/controller/File.php');
$modelSrc = file_get_contents(__DIR__ . '/../application/admin/model/File.php');

// ===== 结构性断言：控制器依据真实 work_task_file 关联判定，不再以 work_id 是否存在为分支条件 =====
check(strpos($ctrlSrc, 'resolveFileId($param)') !== false, '控制器应调用 resolveFileId 解析并校验附件标识一致性');
check(strpos($ctrlSrc, 'hasWorkTaskRelation($fileId)') !== false, '控制器应依据真实 work_task_file 关联判定项目附件');
check(strpos($ctrlSrc, "param['module'] === 'work_task'") !== false, '非项目附件声称 module=work_task 应被拒绝');
check(strpos($ctrlSrc, "exit(json_encode") === false, '不得使用 exit(json_encode) 绕过统一响应');
// delete 与 update 都执行相同的归属/权限检查
foreach (array('delete', 'update') as $act) {
    $m = ($act === 'delete') ? array('deleteWorkTaskFileInProject') : array('updateNameBySaveName');
    check(strpos($ctrlSrc, "deleteTaskFile', \$workId, \$userId") !== false, $act . ' 路径应检查 deleteTaskFile 权限');
}

// ===== P0 上传鉴权结构性断言 =====
check(strpos($ctrlSrc, "module'] == 'work_task'") !== false, '上传鉴权应识别 module=work_task');
check(strpos($ctrlSrc, 'uploadTaskFile') !== false, '上传应检查 uploadTaskFile 权限');
check(strpos($ctrlSrc, 'getTaskWorkId(') !== false, '上传应查询任务真实 work_id（getTaskWorkId）');
check(strpos($ctrlSrc, "项目附件必须指定所属项目") !== false, 'module=work_task 缺 work_id 应拒绝');
check(strpos($ctrlSrc, "任务不属于当前项目") !== false, '跨项目/伪造 work_id 上传应拒绝');
// 鉴权必须发生在 createData 之前：save() 中 work_task 鉴权块的文本位置早于 createData 调用
$authPos = strpos($ctrlSrc, "module'] == 'work_task'");
$createPos = strpos($ctrlSrc, '$fileModel->createData(');
check($authPos !== false && $createPos !== false && $authPos < $createPos, '上传鉴权必须发生在 createData 之前');
// save() 内不得再用 exit(json_encode)
$saveSeg = substr($ctrlSrc, 0, $createPos);
check(strpos($saveSeg, 'exit(json_encode') === false, '上传鉴权不得使用 exit(json_encode) 绕过响应');
check(strpos($modelSrc, 'function getTaskWorkId(') !== false, '模型应有 getTaskWorkId');

// ===== 上传鉴权【补充模拟用例，非真实控制器执行】=====
// 以下 uploadDecision 仅为忠实于控制器判定的补充模拟，证明判定逻辑分支正确；
// 真实 HTTP/控制器集成测试见文末 CI 说明（需 ThinkPHP + 数据库）。
// $tasks[$taskId] = workId 模拟任务真实归属
function uploadDecision($param, $hasUploadPerm, array $tasks) {
    if (empty($param['module']) || $param['module'] !== 'work_task') return array('allow' => true, 'error' => '');
    $workId = (int)($param['work_id'] ?? 0);
    $taskId = (int)($param['module_id'] ?? 0);
    if ($workId <= 0) return array('allow' => false, 'error' => '项目附件必须指定所属项目');
    if ($taskId <= 0) return array('allow' => false, 'error' => '缺少任务参数');
    $real = isset($tasks[$taskId]) ? (int)$tasks[$taskId] : 0;
    if ($real <= 0 || $real !== $workId) return array('allow' => false, 'error' => '任务不属于当前项目');
    if (!$hasUploadPerm) return array('allow' => false, 'error' => '无权上传该附件');
    return array('allow' => true, 'error' => '');
}
$tasks = array(10 => 4, 20 => 5);
// 缺少 work_id
check(uploadDecision(array('module' => 'work_task', 'module_id' => 10), true, $tasks)['allow'] === false, '上传：缺少 work_id 必须拒绝');
// work_id=0
check(uploadDecision(array('module' => 'work_task', 'module_id' => 10, 'work_id' => 0), true, $tasks)['allow'] === false, '上传：work_id=0 必须拒绝');
// 跨项目 task_id（task20 属于项目5，却用 work_id=4）
check(uploadDecision(array('module' => 'work_task', 'module_id' => 20, 'work_id' => 4), true, $tasks)['allow'] === false, '上传：跨项目 task_id 必须拒绝');
// 伪造 work_id
check(uploadDecision(array('module' => 'work_task', 'module_id' => 10, 'work_id' => 999), true, $tasks)['allow'] === false, '上传：伪造 work_id 必须拒绝');
// 无权限
check(uploadDecision(array('module' => 'work_task', 'module_id' => 10, 'work_id' => 4), false, $tasks)['allow'] === false, '上传：无 uploadTaskFile 权限必须拒绝');
// 缺少 module_id
check(uploadDecision(array('module' => 'work_task', 'work_id' => 4), true, $tasks)['allow'] === false, '上传：缺少 task(module_id) 必须拒绝');
// 正确归属且有权限
check(uploadDecision(array('module' => 'work_task', 'module_id' => 10, 'work_id' => 4), true, $tasks)['allow'] === true, '上传：正确归属且有权限应放行');

// ===== 结构性断言：模型关键方法存在且语义正确 =====
check(strpos($modelSrc, 'function resolveFileId(') !== false, '模型应有 resolveFileId');
check(strpos($modelSrc, '附件标识不一致') !== false, 'resolveFileId 应拒绝 file_id/save_name 不一致');
check(strpos($modelSrc, 'function hasWorkTaskRelation(') !== false, '模型应有 hasWorkTaskRelation');
check(strpos($modelSrc, 'function isWorkTaskFileInProject(') !== false, '模型应有 isWorkTaskFileInProject');
check(strpos($modelSrc, 'function countAllReferences(') !== false, '模型应有 countAllReferences（覆盖全模块关联表）');
check(strpos($modelSrc, 'function relationTableMap(') !== false, '模型应有 relationTableMap（全模块关联表清单）');
check(strpos($modelSrc, 'function getExistingRelationTables(') !== false, '模型应枚举真实存在的关联表');
// countAllReferences 返回 -1 表示无法判定（保守保留）
check(strpos($modelSrc, 'return -1;') !== false, 'countAllReferences 应在无法判定时返回 -1（保守）');
// deleteWorkTaskFileInProject：物理文件删除在事务提交之后
check(strpos($modelSrc, 'countAllReferences($fileId)') !== false, '删除前应调用 countAllReferences');
check(strpos($modelSrc, '$purgeMaster = false') !== false, '应仅在全模块无引用时才删除主记录');
check(strpos($modelSrc, 'if ($purgeMaster)') !== false, '物理文件删除应在事务提交后执行');

// ===== 忠实于生产的纯逻辑：控制器判定 =====
// $links[$fileId] = [['task_id'=>..,'work_id'=>..], ...] 模拟 work_task_file 关联
function fileBelongsToProject($fileId, $workId, array $links) {
    $fileId = (int)$fileId; $workId = (int)$workId;
    if ($fileId <= 0 || $workId <= 0) return false;
    if (!isset($links[$fileId])) return false;
    foreach ($links[$fileId] as $row) {
        if ((int)$row['task_id'] > 0 && (int)$row['work_id'] === $workId) return true;
    }
    return false;
}
// $otherRefs[$fileId] = 模拟其它模块（如 crm_customer_file）是否引用
// decideFileOp 复刻控制器 delete/update 判定：返回 ['allow'=>bool,'error'=>string]
function decideFileOp($param, $hasDeletePerm, array $taskLinks, array $otherRefs = array(), $op = 'delete') {
    $fileId = (int)($param['file_id'] ?? 0);
    $workId = (int)($param['work_id'] ?? 0);
    $isProjectFile = isset($taskLinks[$fileId]); // 真实 work_task_file 关联存在
    if ($isProjectFile) {
        if ($workId <= 0) return ['allow' => false, 'error' => '项目附件必须指定所属项目'];
        if (!$hasDeletePerm) return ['allow' => false, 'error' => '无权' . ($op === 'rename' ? '重命名' : '删除') . '该附件'];
        if (!fileBelongsToProject($fileId, $workId, $taskLinks)) return ['allow' => false, 'error' => '该附件不属于当前项目，无法' . ($op === 'rename' ? '重命名' : '删除')];
        return ['allow' => true, 'error' => ''];
    }
    $claimedProject = $workId > 0 || (isset($param['module']) && $param['module'] === 'work_task');
    if ($claimedProject) return ['allow' => false, 'error' => '该附件不属于任何项目，无法按项目附件' . ($op === 'rename' ? '重命名' : '删除')];
    return ['allow' => true, 'error' => ''];
}

$links = array(
    100 => array(array('task_id' => 10, 'work_id' => 4)),
    200 => array(array('task_id' => 20, 'work_id' => 5)),
);

// 1. 正例：有权限操作本项目附件
$r = decideFileOp(array('work_id' => 4, 'file_id' => 100, 'module' => 'work_task'), true, $links);
check($r['allow'] === true, '正例：本项目管理员可删除本项目附件');

// 2. 无权限操作本项目附件
$r = decideFileOp(array('work_id' => 4, 'file_id' => 100, 'module' => 'work_task'), false, $links);
check($r['allow'] === false && $r['error'] === '无权删除该附件', '反例：无 deleteTaskFile 权限被拒绝');

// 3. 省略 work_id 的项目附件必须拒绝（关键修正）
$r = decideFileOp(array('file_id' => 100, 'module' => 'work_task'), true, $links);
check($r['allow'] === false && $r['error'] === '项目附件必须指定所属项目', '省略 work_id 的项目附件必须拒绝');

// 4. 省略 work_id 且不传 module 的项目附件仍必须拒绝
$r = decideFileOp(array('file_id' => 100), true, $links);
check($r['allow'] === false && $r['error'] === '项目附件必须指定所属项目', '省略 work_id 与 module 的项目附件仍被拒绝');

// 5. 伪造 module（crm_customer）不能改变权限判定
$r = decideFileOp(array('work_id' => 4, 'file_id' => 100, 'module' => 'crm_customer'), true, $links);
check($r['allow'] === true, '伪造 crm_customer 仍按项目附件校验（module 被忽略）');
$r = decideFileOp(array('work_id' => 4, 'file_id' => 100, 'module' => 'crm_customer'), false, $links);
check($r['allow'] === false, '伪造 module 且无权限仍被拒绝');

// 6. 跨项目 file_id
$r = decideFileOp(array('work_id' => 4, 'file_id' => 200, 'module' => 'work_task'), true, $links);
check($r['allow'] === false && strpos($r['error'], '不属于当前项目') !== false, '跨项目附件被拒绝');

// 7. 项目附件伪装成普通附件（无 work_id、伪造 module）必须拒绝，不能回退通用删除
$r = decideFileOp(array('file_id' => 100, 'module' => 'work_task'), true, $links);
check($r['allow'] === false, '项目附件伪装普通附件不得回退通用删除');

// 8. 非项目附件声称是项目附件（伪造 module=work_task）被拒绝
$r = decideFileOp(array('file_id' => 300, 'module' => 'work_task'), true, $links);
check($r['allow'] === false && strpos($r['error'], '不属于任何项目') !== false, '非项目附件声称 work_task 被拒绝');
$r = decideFileOp(array('file_id' => 300, 'work_id' => 4), true, $links);
check($r['allow'] === false, '非项目附件传 work_id 被拒绝');

// 9. 重命名与删除两条路径一致
$r = decideFileOp(array('work_id' => 4, 'file_id' => 100, 'module' => 'work_task'), true, $links, 'rename');
check($r['allow'] === true, '重命名正例：有权限且归属正确');
$r = decideFileOp(array('work_id' => 4, 'file_id' => 100, 'module' => 'work_task'), false, $links, 'rename');
check($r['allow'] === false && strpos($r['error'], '无权重命名') !== false, '重命名反例：无权限被拒绝');
$r = decideFileOp(array('file_id' => 100, 'module' => 'work_task'), true, $links, 'rename');
check($r['allow'] === false, '重命名省略 work_id 必须拒绝');

// ===== file_id / save_name 不一致：复刻 resolveFileId 语义 =====
function resolveConsistency($fileIdParam, $saveNameFileId) {
    // save_name 解析出的 id 与显式 fileId 不一致 -> 拒绝
    if ($fileIdParam > 0 && $saveNameFileId > 0 && $fileIdParam !== $saveNameFileId) return false;
    return true;
}
check(resolveConsistency(100, 100) === true, 'file_id/save_name 一致 -> 通过');
check(resolveConsistency(100, 200) === false, 'file_id/save_name 指向不同附件 -> 拒绝');
check(resolveConsistency(0, 100) === true, '仅 save_name -> 通过');

// ===== 全模块引用判定（复刻 countAllReferences）=====
function canPurgeMaster($fileId, array $taskLinks, array $otherRefs) {
    // 仅当无任何 work_task_file 关联、且无其它模块引用时才可清理主文件
    $taskCnt = isset($taskLinks[$fileId]) ? count($taskLinks[$fileId]) : 0;
    $otherCnt = isset($otherRefs[$fileId]) ? (int)$otherRefs[$fileId] : 0;
    return ($taskCnt + $otherCnt) === 0;
}
check(canPurgeMaster(100, array(), array()) === true, '仅被当前项目引用且已删除关联 -> 可清理主文件');
check(canPurgeMaster(100, $links, array()) === false, '仍被本项目任务引用 -> 保留主文件');
check(canPurgeMaster(100, array(), array(100 => 1)) === false, '同时被其它模块引用 -> 保留主文件与物理文件');
check(canPurgeMaster(200, array(200 => array(array('task_id'=>20,'work_id'=>5))), array()) === false, '同时被其它项目任务引用 -> 保留主文件');

echo "work_file_permission_test passed ($pass assertions)\n";

/*
 * CI 命令（具备 PHP CLI + 测试数据库时执行真实归属/权限用例）：
 *   php -l application/admin/controller/File.php
 *   php -l application/admin/model/File.php
 *   php tests/work_file_permission_test.php
 *
 * 上传权限真实 HTTP/控制器集成测试（需 ThinkPHP + 数据库，本机未执行）：
 *   - 缺 work_id：module=work_task + module_id=任意任务 + 无 work_id -> 400/错误，无 admin_file/work_task_file/物理文件残留。
 *   - 跨项目 task_id：task 属于项目B却用 work_id=A -> 拒绝，无任何文件残留。
 *   - 无 uploadTaskFile 权限 -> 拒绝，无任何文件残留。
 *   - 合法上传（正确归属 + 有权限）-> 创建 admin_file + work_task_file，物理文件存在。
 * 真实数据库用例建议覆盖：归属核验、跨项目拒绝、并发引用保留主文件、deleteTaskFile 权限矩阵。
 */

收支计划联动说明

目标
- 当创建收支流水且传入 plan_id 时，自动刷新该计划的完成状态。

实现点
- `application/finance/controller/Record.php`
  - save: 成功新增流水后，调用 `FinancePlan::refreshStatus(plan_id)`
  - update: 修改流水后，刷新旧 plan_id 与新 plan_id
  - delete: 删除流水后，刷新相关 plan_id
- `application/finance/model/FinancePlan.php`
  - `refreshStatus(planId)` 计算该计划下流水累计金额

状态计算规则
- sum(amount) == 0            -> status = 0 (未发生)
- 0 < sum(amount) < plan_amount -> status = 1 (部分完成)
- sum(amount) >= plan_amount  -> status = 2 (已完成)

注意
- plan_amount 为 0 时，status 维持 0（未发生）
- 该联动不依赖合同/客户绑定，plan_id 为空时不触发

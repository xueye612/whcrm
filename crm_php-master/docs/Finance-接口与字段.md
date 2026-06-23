收支管理 - 接口与字段

模块
- 模块名：finance
- 路由文件：`config/route_finance.php`

统一返回结构
```
{ "code": 200, "data": ..., "error": "" }
```

1) 收支类型（FinanceType）
接口（POST）
- `/finance/type/index`
- `/finance/type/save`
- `/finance/type/update`
- `/finance/type/delete`

字段（5kcrm_finance_type）
- type_id 主键
- direction 收支方向：income / expense
- name 类型名称
- status 1启用/0停用
- sort 排序
- create_user_id 创建人
- create_time/update_time

主要入参
- index：direction、status、name、page、limit
- save：direction、name、status(可选)、sort(可选)
- update：id(type_id)、direction(可选)、name(可选)、status(可选)、sort(可选)
- delete：id(type_id)

2) 收支流水（FinanceRecord）
接口（POST）
- `/finance/record/index`
- `/finance/record/read`
- `/finance/record/save`
- `/finance/record/update`
- `/finance/record/delete`

字段（5kcrm_finance_record）
- record_id 主键
- direction 收支方向：income / expense
- customer_id 可空
- contract_id 可空
- type_id 类型ID
- amount 金额
- occur_date 发生日期
- handler_user_id 经办人
- remark 备注
- plan_id 可空
- create_user_id 创建人
- create_time/update_time

主要入参
- index：direction、type_id、customer_id、contract_id、plan_id、handler_user_id、start_date、end_date、keyword、page、limit
- read：id(record_id)
- save：direction、amount、occur_date、type_id(可选)、customer_id(可选)、contract_id(可选)、handler_user_id(可选)、remark(可选)、plan_id(可选)
- update：id(record_id) + 任意可更新字段
- delete：id(record_id 或数组)

3) 收支计划（FinancePlan）
接口（POST）
- `/finance/plan/index`
- `/finance/plan/read`
- `/finance/plan/save`
- `/finance/plan/update`
- `/finance/plan/delete`

字段（5kcrm_finance_plan）
- plan_id 主键
- direction 收支方向：income / expense
- customer_id 可空
- contract_id 可空
- type_id 类型ID
- plan_amount 计划金额
- plan_date 计划日期
- status 0未发生/1部分完成/2已完成
- remark 备注
- create_user_id 创建人
- create_time/update_time

主要入参
- index：direction、type_id、customer_id、contract_id、status、start_date、end_date、page、limit
- read：id(plan_id)
- save：direction、plan_amount、plan_date、type_id(可选)、customer_id(可选)、contract_id(可选)、status(可选)、remark(可选)
- update：id(plan_id) + 任意可更新字段
- delete：id(plan_id 或数组)

SQL
- `sql/upgrade_finance_ledger.sql`

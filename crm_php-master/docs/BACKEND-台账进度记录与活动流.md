# 后端：台账进度记录与活动流

## 进度记录表
表名：`5kcrm_customer_ledger_record`

字段：
- record_id (PK)
- ledger_id 台账ID
- customer_id 客户ID
- content 记录内容
- old_status 变更前状态
- new_status 变更后状态
- create_user_id 创建人
- create_time 创建时间（时间戳）

索引：
- idx_ledger_id(ledger_id)
- idx_customer_id(customer_id)
- idx_create_time(create_time)

建表 SQL：`sql/migrate_ledger_record.sql`

## 进度记录逻辑
- 新建台账：写入一条“创建台账”的进度记录
- 状态变更：写入一条“状态变更 old -> new”的进度记录
- 手动新增记录：通过接口写入进度记录，可选传 new_status 同步更新台账状态

相关代码：
- `application/ledger/model/CustomerLedger.php`：
  - addProgressRecord(ledger_id, customer_id, content, old_status, new_status, user_id)
  - listProgressRecord(ledger_id)
- `application/ledger/controller/Ledger.php`：
  - save / update 时自动写入进度记录
- `application/ledger/controller/Record.php`：
  - /ledger/record/list
  - /ledger/record/add

## 接口
### POST /ledger/record/list
入参：
- ledger_id (必填)

返回：进度记录列表（包含 create_user_name、create_time 等）

### POST /ledger/record/add
入参：
- ledger_id (必填)
- content (必填)
- new_status (可选)

行为：
- 写入进度记录
- 如 new_status 有值，则同步更新台账状态
- 写入活动流（crm_activity）

## 活动流接入
活动类型：activity_type = 13（台账）

写入位置：
- 新建台账、状态变更、手动新增进度记录
- 写入 `crm_activity`（type=1, activity_type=13, activity_type_id=ledger_id）

活动名称解析：
- `application/crm/logic/ActivityLogic.php` 增加 activity_type=13，并通过 `crm_customer_ledger.title` 取名称

前端活动筛选：
- 使用 activity_type=13 过滤，显示“台账”

BASELINE - Receivables (回款) Module

Scope
- Backend only (ThinkPHP). Source under `application/crm` and routes in `config/route_crm.php`.
- BI dependencies under `application/bi` and `config/route_bi.php`.

Business Relationship
- Contract -> Receivables Plan: 1:N (multiple plans per contract).
- Receivables Plan -> Receivable: 1:1 (plan_id in receivables; plan has receivables_id).
- Receivable -> Contract/Customer: required. In `Receivables::createData`, missing `contract_id` or `customer_id` blocks creation.

Key Tables
- `5kcrm_crm_receivables` (main record)
- `5kcrm_crm_receivables_plan` (plan)
- `5kcrm_crm_receivables_data` (custom field data for receivables)
- `5kcrm_crm_receivables_plan_data` (custom field data for plan)
- `5kcrm_crm_receivables_file` (attachments, linked to admin_file)

Table Fields (Core)
Receivables (`5kcrm_crm_receivables`, base from `public/sql/5kcrm_all.sql`)
- receivables_id, plan_id, number, customer_id, contract_id
- return_time, return_type, money
- check_status, flow_id, order_id, check_user_id, flow_user_id
- remark, create_user_id, owner_user_id, create_time, update_time
Updates
- `update_sql_20210626.sql` adds `ro_user_id`, `rw_user_id` (read-only / read-write team)

Receivables Plan (`5kcrm_crm_receivables_plan`, base from `public/sql/5kcrm_all.sql`)
- plan_id, num, receivables_id, status
- contract_id, customer_id
- money, return_date, return_type, remind, remind_date, remark
- create_user_id, owner_user_id, create_time, update_time
- file
Updates
- `update_sql_20210316.sql`: `is_dealt`
- `update_sql_20210903.sql`: `real_money`, `real_data`, `un_money`

Key Status / Flags
Receivables.check_status (model: `application/crm/model/Receivables.php`)
- 0 待审核
- 1 审核中
- 2 审核通过
- 3 已拒绝
- 4 已撤回
- 7 正常
Note: SQL comment in `5kcrm_all.sql` only lists 0-3, model logic extends to 4 and 7.

ReceivablesPlan.status (model: `application/crm/model/ReceivablesPlan.php`)
- 0 待回款
- 1 完成
- 2 部分回款
- 3 已作废
- 4 已逾期
- 5 待生效
Note: In `Receivables::check`, plan.status is set to 1 (完成) or 2 (部分回款) based on un_money calculation.

Core Controllers / Models
Controllers
- `application/crm/controller/Receivables.php`
- `application/crm/controller/ReceivablesPlan.php`

Models
- `application/crm/model/Receivables.php`
- `application/crm/model/ReceivablesPlan.php`

Key Logic Highlights
- Receivables creation requires customer_id and contract_id; contract must be approved (check_status 2 or 7). If contract is void (6) or not approved, creation fails.
- If receivable is linked to plan_id, controller `save()` writes `receivables_id` back to plan.
- Approval (`check`) updates receivables check_status and updates plan real_money/un_money/status when linked.
- Team permissions: receivables list uses owner_user_id + ro_user_id + rw_user_id for data access.

API Endpoints (from routes)
All POST unless stated otherwise.

Receivables (CRM)
- `crm/receivables/index`
- `crm/receivables/save`
- `crm/receivables/update`
- `crm/receivables/read`
- `crm/receivables/delete`
- `crm/receivables/check`
- `crm/receivables/revokeCheck`
- `crm/receivables/transfer`
- `crm/receivables/system`
- `crm/receivables/count`
- `crm/receivables/excelExport`

Receivables Plan (CRM)
- `crm/receivables_plan/index`
- `crm/receivables_plan/save`
- `crm/receivables_plan/update`
- `crm/receivables_plan/delete`
- `crm/receivablesPlan/excelExport`
Note: Controller has `read()` but route list in `config/route_crm.php` does not include `crm/receivables_plan/read`.

Receivables (BI)
- `bi/receivables/statistics`
- `bi/receivables/statisticList`
- `bi/ranking/receivables`

Receivables Message Reminders
- `crm/message/checkReceivables`
- `crm/message/remindReceivablesPlan`

Permissions and Menu Mount Points
Admin Menu (from `public/sql/5kcrm_all.sql`)
- `admin_menu`: ID 8, PID 1, name "回款", module key `receivables`
- Mounted under CRM module (PID 1: "CRM模块")

Admin Rule (from `public/sql/5kcrm_all.sql`)
- CRM -> 回款管理: rule_id 50, module `receivables`
  - save/update/index/read/delete (rule_id 51-55)
- BI -> 回款统计: rule_id 67, action `receivables`, read rule_id 68

Note: No explicit `admin_rule` entry for `receivables_plan` found in initial SQL files. However, `ReceivablesPlan` controller checks permissions via `getUserByPer('crm','receivables_plan',...)`. This may be injected elsewhere or rely on a newer migration. This needs confirmation before reusing permissions.

Custom Fields
- Receivables and plans use the dynamic field system (`admin_field`) and data tables:
  - `crm_receivables_data`
  - `crm_receivables_plan_data`
- Field metadata defined in `public/sql/5kcrm_all.sql` under `admin_field`.

Gaps / Risks for Reuse
- Receivables creation is contract-bound; it is not designed for standalone cash flow.
- Plan routes are partial in `route_crm.php` (missing read).
- Plan permissions appear missing from base SQL.

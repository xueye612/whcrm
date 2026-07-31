# 商机与季度绩效独立验收报告

> 验收日期：2026-07-28  
> 验收工作区：`D:\Users\SN\.codex\worktrees\ea92\whcrm`  
> 验收范围：《CRM 二次开发整体需求说明》与当前“商机与季度绩效最终收口”实现  
> 验收限制：只检查、测试和生成本报告；未修改业务代码，未写入正式库，未提交、未推送、未创建或切换分支

## 1. 总体结论

**结论：不通过。**

当前实现不能认定为“商机与季度绩效最终收口完成”，也不具备封版或上线条件。主要阻断依据：

1. `npm run build` 失败，季度绩效页面存在 JavaScript 语法错误。
2. 季度绩效仍把 `customer_ledger.description=''` 直接转换为负向事实，未经过质量问题确认流程，按验收规则判定为 P0 业务口径错误。
3. 绩效测试事实只归集“符合要求”，使用测试扩展记录创建时间，不使用评定时间；“不符合要求”负向事实未实现。
4. 已结算奖励按候选创建时间归集，不使用真实结算时间。
5. W/R/K、项目实施、外包项目、项目结果没有完整进入季度绩效事实。
6. 责任认定只有创建和列表，没有独立审核闭环，也不会在审核通过后生成负向绩效事实。
7. 四维度加权分和评级常量虽存在，但岗位基准×评级系数的参考结果、人工调整原因及调整前后审计未实现。
8. 医院代理商机只校验“所选客户是经销商”，没有验证所选经销商等于医院当前经销商；前端也只是全局搜索经销商，没有自动带出并锁定当前关系。
9. 商机状态推进只验证目标状态属于同一状态组，没有验证推进顺序，允许跳级或倒退。
10. 删除旧状态组和动态字段的 `forward` 内没有硬阻断。独立库故障注入证明：存在业务引用或动态字段数据时仍会执行删除。
11. 新增 forward 第二次执行会刷新奖励规则 `update_time`，严格幂等不通过。
12. 正式库存在 12 条孤儿商机状态；67 条历史商机中 41 条 `business_category` 为空。
13. 真实浏览器验收未完成：前端 8090 不可访问，且生产构建失败。
14. 三种真实账号的 HTTP 越权反例未完成；数据库中没有普通审核账号被授予新增的 `perf_*` 子权限。

## 2. Git 状态

- 当前分支：空，处于 detached HEAD。
- HEAD：`20e87edd2d63bc0c6c41b3572a12ceced971e405`
- `git status --short`：78 项，其中 25 个修改、12 个删除、41 个未跟踪。
- `git diff --stat`：37 个已跟踪文件，`1947 insertions(+), 1075 deletions(-)`。
- `git diff --check`：退出码 0；只有 LF/CRLF 提示，无空白错误。
- 依赖文件：`package.json`、`package-lock.json`、`yarn.lock`、`composer.json`、`composer.lock` 均无差异。
- 本轮验收没有改变既有 Git 改动；只新增本报告文档。

## 3. 修改、新增和删除文件分类

### 3.1 后端业务代码

修改：

- `crm_php-master/application/crm/controller/Business.php`
- `crm_php-master/application/crm/controller/Customer.php`
- `crm_php-master/application/crm/controller/Performance.php`
- `crm_php-master/application/crm/controller/Receivables.php`
- `crm_php-master/application/crm/controller/Reward.php`
- `crm_php-master/application/crm/logic/PerformanceService.php`
- `crm_php-master/application/crm/model/Business.php`
- `crm_php-master/application/ledger/controller/Ledger.php`
- `crm_php-master/application/work/controller/Task.php`
- `crm_php-master/application/work/logic/WorkflowService.php`
- `crm_php-master/config/route_crm.php`
- `crm_php-master/config/route_ledger.php`

新增：

- `crm_php-master/application/crm/logic/FinanceService.php`

删除：

- `crm_php-master/application/crm/controller/Leadpool.php`
- `crm_php-master/application/crm/controller/Opportunity.php`
- `crm_php-master/application/crm/logic/LeadPoolService.php`
- `crm_php-master/application/crm/logic/OpportunityService.php`

### 3.2 前端业务代码

修改：

- `crm_web-master/src/api/crm/performance.js`
- `crm_web-master/src/router/modules/crm.js`
- `crm_web-master/src/views/crm/business/Create.vue`
- `crm_web-master/src/views/crm/business/Detail.vue`
- `crm_web-master/src/views/crm/customer/Create.vue`
- `crm_web-master/src/views/crm/customer/Detail.vue`
- `crm_web-master/src/views/crm/ledger/index.vue`
- `crm_web-master/src/views/crm/performance/index.vue`
- `crm_web-master/src/views/pm/task/index.vue`
- `crm_web-master/src/views/taskExamine/task/components/TaskWorkflowPanel.vue`

新增：

- `crm_web-master/src/api/extensions.js`
- `crm_web-master/src/api/ledger_extensions.js`
- `crm_web-master/src/views/crm/customer/components/CustomerDealerPanel.vue`
- `crm_web-master/src/views/crm/performance/components/PerformanceFactPanel.vue`

删除：

- `crm_web-master/src/api/crm/leadpool.js`
- `crm_web-master/src/api/crm/opportunity.js`
- `crm_web-master/src/views/crm/leadpool/index.vue`
- `crm_web-master/src/views/crm/opportunity/index.vue`

### 3.3 测试

修改：

- `crm_php-master/tests/product_override_test.php`
- `crm_web-master/tests/p0Workflow.test.js`
- `crm_web-master/tests/p5Reward.test.js`

新增：

- `crm_php-master/tests/evaluate_http_test.php`
- `crm_php-master/tests/finance_service_test.php`
- `crm_php-master/tests/ledger_trigger_test.php`

删除：

- `crm_php-master/tests/p2_leadpool_test.php`
- `crm_php-master/tests/p3_opportunity_test.php`
- `crm_web-master/tests/p2Leadpool.test.js`
- `crm_web-master/tests/p3Opportunity.test.js`

### 3.4 SQL

新增 8 组、32 个 SQL 文件：

- `20260727_biz_reward_rule_{precheck,forward,verify,rollback_notes}.sql`
- `20260727_crm_arch_{precheck,forward_migration,verify,rollback_notes}.sql`
- `20260727_perf_fact_{precheck,forward,verify,rollback_notes}.sql`
- `20260728_final_biz_status_{precheck,forward,verify,rollback_notes}.sql`
- `20260728_final_biz_type_{precheck,forward,verify,rollback_notes}.sql`
- `20260728_final_field_dedup_{precheck,forward,verify,rollback_notes}.sql`
- `20260728_final_perf_rbac_{precheck,forward,verify,rollback_notes}.sql`
- `20260728_final_reward_rule_{precheck,forward,verify,rollback_notes}.sql`

### 3.5 文档

- 既有未跟踪：`docs/CRM-SECONDARY-DEVELOPMENT-OVERALL-REQUIREMENTS.md`
- 本轮新增：`docs/CRM-BUSINESS-PERFORMANCE-INDEPENDENT-ACCEPTANCE-REPORT.md`

## 4. GLM 声明—代码证据—运行证据矩阵

未在附件或工作区找到独立的《商机与季度绩效最终收口—完成报告》原文文件，因此下表依据验收指令中要求核验的完成声明重建。缺少原始报告本身也列为“无法证明”。

| GLM 声明/验收项 | 代码证据 | SQL/数据库证据 | HTTP/浏览器证据 | 结论 | 风险 |
|---|---|---|---|---|---|
| 四类商机与固定状态组已收口 | `Business::$categoryTypeMap` 固定 2/3/4/5 | 正式库存在 4 个中文组和 23 个状态 | 未完成登录态页面操作 | 部分通过 | 数据层存在，但端到端未证明 |
| 商机类别规则统一用于创建、编辑、推进 | `validateBusinessCategoryRules()` 被 create/update/advance 调用 | 列已存在 | 无直接 HTTP 反例 | 部分通过 | 医院当前经销商关系未校验 |
| 医院代理自动使用医院当前经销商 | 前端调用全局 `dealerOptions`；后端仅验证 dealer 类型 | 正式库关系表存在但当前 0 行 | 未完成浏览器验证 | 不通过 | 可选择与医院关系不一致的经销商 |
| 所属经销商显示名称 | read 会补 `dealer_customer_name`，前端仍有 `客户#ID` 回退 | 无运行数据 | 无浏览器截图 | 无法证明 | 仍可能显示数字 ID |
| 商机阶段推进合法且不可乱跳 | `advance()` 只检查目标状态属于 type | 状态数据存在 | 无非法跳转 HTTP 反例 | 不通过 | 可跨阶段、倒退、重复推进 |
| 推进与奖励在同一事务 | 商机更新、活动、日志、候选位于事务块 | 15 条规则存在 | 无故障注入 HTTP/DB 事务测试 | 部分通过 | 活动和日志 insert 返回值未检查；并发未锁行 |
| 15 条奖励规则完整 | `business_stage_reward_rule` 查询 | 正式库 15 条金额与需求一致 | 无完整逐阶段真实推进 | 部分通过 | 静态与 DB 通过，运行闭环未证明 |
| 奖励候选字段完整且幂等 | 保存商机、客户、人员、金额、发生时间、规则版本、source_ref | source_ref 唯一设计存在 | 无全部字段运行证据 | 部分通过 | 规则 ID 未保存，仅版本；证据为拼接文本 |
| 历史商机类别已回填 | 未见完整历史回填逻辑 | 67 条中 41 条类别为空 | 不适用 | 不通过 | 历史数据无法进入新规则闭环 |
| 迁移具备硬阻断 | forward 内无 SIGNAL/条件阻断 | 独立库危险引用测试仍删除成功 | 不适用 | 不通过 | 会产生孤儿或直接丢失扩展数据 |
| 迁移严格幂等 | ON DUPLICATE KEY | 第二次执行规则摘要变化 | 不适用 | 不通过 | 15 条规则 update_time 被刷新 |
| 绩效事实自动归集完整 | `autoAggregate()` 仅覆盖完成任务、通过测试、已结算奖励、空描述台账 | 正式库已生成 4 条“台账缺失描述”负向事实 | 无登录态 HTTP | 不通过 | 归集范围和时间口径错误 |
| 任务完成时间正确 | 使用进入“已完成”的 transition log 时间 | 未构造运行样本 | 无 HTTP | 部分通过 | 代码口径正确但无运行证据 |
| W/R/K 形成绩效事实 | `autoAggregate()` 未查询 `task_wrk_log` | 无对应事实证据 | 无 | 不通过 | 初始/最终变化未归集 |
| 测试正负事实完整 | 只查 compliant，使用 `task_test_ext.create_time` | 无评定时间事实 | 无 | 不通过 | 不合格负向事实缺失，时间错误 |
| 奖励使用结算时间 | 查询已结算状态，但按候选 `create_time` 过滤和记录 | 无结算时间字段使用 | 无 | 不通过 | 事实可能归入错误季度 |
| 台账质量需确认后入绩效 | 直接按 description 为空生成负向事实 | 正式库已有 4 条此类事实 | 无 | 不通过 | P0 业务口径错误 |
| 项目/实施/外包结果进入绩效 | `autoAggregate()` 未查询项目实施和外包结果 | 无对应事实 | 无 | 不通过 | 重点项目维度不完整 |
| 责任认定独立审核并生成事实 | 只有 `caseSave`、`caseList` | 无审核/事实生成 SQL | 无 | 不通过 | 没有审核闭环 |
| 四维度评分与评级闭环 | 加权公式、评级系数、岗位基准常量存在 | performance 表保存分数与评级 | 构建失败，未完成页面操作 | 部分通过 | 基准×系数结果、调整审计缺失 |
| RBAC 已可用 | 后端查询 `perf_*` 规则 | 规则 454—461 存在，但没有普通组被授予 | 三账号反例未完成 | 不通过 | 只有超管代码旁路可操作管理接口 |
| 前端已完成并可构建 | 页面和组件存在 | 不适用 | `npm run build` 失败 | 不通过 | 季度绩效页面不能生产构建 |
| 真实浏览器逐页完成 | 无可替代证据 | 不适用 | 8090 不可达，无截图、无运行记录 | 无法证明 | 按判定规则整体不得通过 |
| Dashboard 无新增 500 | 后端服务可访问 | 不适用 | 未登录 7 接口均 HTTP 200、业务 code=101 | 无法证明 | 未登录结果不能证明登录后查询成功 |

## 5. 已通过项目

1. 17 个本轮改动/新增 PHP 文件全部通过 `php -l`。
2. 7 个可安全执行的后端纯逻辑测试通过，共 116 项断言。
3. 7 个前端 Node 测试脚本通过；这些测试未覆盖生产构建。
4. `git diff --check` 通过。
5. 依赖和锁文件未变化。
6. 正式库已经存在：
   - 四个中文业务类别状态组；
   - 15 条奖励规则，金额与确认口径一致；
   - `business_category` 可空生成列唯一索引；
   - `performance_fact` 的 `(source_type, source_id, period)` 唯一索引；
   - 8 个绩效子权限规则定义。
7. 独立测试库中 8 个 verify 脚本均能执行结束。
8. `advance()` 将商机更新、活动、推进日志和奖励候选放在同一个显式事务块中。

## 6. 未通过项目

1. 前端生产构建。
2. 迁移 forward 内硬阻断。
3. 严格幂等。
4. 历史商机类别回填和孤儿状态清理。
5. 医院代理与医院当前经销商的一致性。
6. 商机状态有序推进。
7. 阶段奖励故障回滚与并发幂等的运行证明。
8. W/R/K 绩效事实。
9. 测试不通过负向事实及评定时间。
10. 奖励结算时间。
11. 台账质量确认流程。
12. 自有实施、外包和项目结果归集。
13. 责任认定审核和负向事实。
14. 岗位基准×评级系数参考结果。
15. 人工调整原因及调整前后审计。
16. 普通审核账号的 RBAC 配置与越权反例。

## 7. 无法证明项目

1. GLM 完成报告原文未随附件提供，工作区也未找到该报告文件。
2. 四类商机真实创建、编辑和回显。
3. 所属经销商真实显示名称和客户详情跳转。
4. 15 个奖励阶段逐项真实推进。
5. 奖励候选故障注入回滚。
6. Dashboard 登录后 7 个主要接口无 500。
7. 三账号 HTTP 权限矩阵。
8. Chrome/Playwright 逐页操作、console、pageerror、网络 500 和截图。

## 8. P0 阻断问题

### P0-1 台账空描述直接生成负向绩效事实

`Performance::autoAggregate()` 直接查询 `customer_ledger.description=''`，生成 `ledger_missing_desc` 负向事实。正式库已有 4 条此类事实。没有质量问题登记、确认、忽略和复核状态。

### P0-2 数据库 forward 不具备删除硬阻断

独立库故障注入结果：

- 构造 1 条 `type_id=6` 商机引用后执行 `final_biz_type_forward`：
  - 类型被删除；
  - 引用商机仍保留；
  - 产生 1 条孤儿引用；
  - 脚本返回成功。
- 构造 field 952 和一条 `crm_rianjp` 扩展数据后执行 `final_field_dedup_forward`：
  - 字段定义被删除；
  - 扩展数据被删除；
  - 脚本返回成功。

### P0-3 前端生产构建失败

`crm_web-master/src/views/crm/performance/index.vue` 的 computed 属性缺少逗号：

```text
canScoreInput() { ... }
isSuperAdmin() { ... }
```

Webpack 报 `Unexpected token, expected , (94:4)`，`npm run build` 退出码 1。

### P0-4 正式库存在孤儿状态和错误绩效事实

- 12 条未结束商机的 `type_id + status_id` 无法匹配状态表。
- 4 条绩效事实均为未经确认的“台账缺失描述”负向事实。

## 9. P1 重要问题

1. 67 条历史商机中 41 条类别为空。
2. 医院代理没有校验医院当前经销商，前后端均可选择任意经销商。
3. 商机状态允许跳级、倒退和重复推进。
4. 绩效测试事实时间和奖励事实时间错误。
5. W/R/K、项目实施和外包结果未进入事实。
6. 责任认定无审核及事实生成。
7. 绩效人工调整没有原因和前后值审计。
8. `perf_*` 规则只创建未分配，无法组成要求的三账号权限矩阵。
9. `finance_service_test.php` 内硬编码正式库连接并执行 DELETE，不适合作为安全验收测试，因此本轮未在正式库执行。

## 10. P2 一般问题

1. 多个 API 错误提示仍包含英文内部值和英文参数名。
2. 商机详情和编辑回显存在 `客户#ID` 回退显示。
3. verify 脚本以查询输出代替断言，发现不符时仍可能退出 0。
4. `final_perf_rbac_forward.sql` 注释写“超级管理员角色 id=1”，实际正式库管理员组为 id=18，且脚本没有真正更新任何组的 rules。
5. 现有纯逻辑测试大量复制常量或规则，不能证明真实控制器、事务和数据库行为。

## 11. 数据库迁移验证记录

### 11.1 静态检查

- 8 个 precheck：非注释 SQL 中未发现 DDL/DML，属于只读。
- 8 个 rollback notes：只有说明和注释，没有自动 DROP/DELETE。
- 未发现账号、密码、固定 IP 或本机绝对路径写入 SQL。
- forward 使用 MySQL 5.7 支持的生成列、PREPARE、`INSERT ... ON DUPLICATE KEY UPDATE`，语法在 MySQL 5.7.26 可执行。

### 11.2 正式库只读结果

- MySQL：5.7.26。
- 商机类型：1 个系统默认组 + 4 个正式中文组。
- 商机状态：23 条。
- 奖励规则：15 条，金额符合确认口径。
- 商机：67 条，其中 41 条类别为空。
- 孤儿商机状态：12 条。
- 孤儿奖励规则：0。
- 旧 type_id 6—33 引用：0。
- performance：1 条。
- performance_fact：4 条。

### 11.3 独立库双执行

测试库：`crm_codex_accept_20260728`，由正式库单事务只读备份恢复。

- 第一次执行 8 个新增 forward：退出码 0。
- 第二次执行 8 个新增 forward：退出码 0。
- 结构数量保持：5 个类型、23 个状态、15 条规则、4 条绩效事实。
- 奖励规则最大 `update_time` 从 `1785230251` 变为 `1785230254`。
- 奖励规则摘要从 `c558a26f...` 变为 `833c6188...`。
- 结论：严格幂等不通过。

### 11.4 危险引用

危险引用和动态字段数据均只在独立测试库构造。两个 forward 都没有阻断，详见 P0-2。

### 11.5 清理

- 独立测试库已删除，存在性检查为 0。
- 临时 SQL 备份已删除。
- 未在正式库构造或删除任何测试数据。

## 12. HTTP 权限反例记录

本轮没有得到三种真实账号的有效登录会话，且正式库没有任何普通用户组被授予 `perf_*` 子权限，因此无法安全完成要求的三账号正反例。

只读观察：

| 检查 | 结果 |
|---|---|
| 超级管理员 | user_id=1，代码直接旁路全部绩效子权限 |
| 有审核权限的非本人账号 | 正式库未找到任何组包含 perf_fact_review/perf_final_rate/perf_responsibility |
| 普通员工 | 存在多个普通账号，但没有可用登录会话 |
| 普通员工改 user_id 查看他人 | 未执行，无法证明 |
| 普通员工审核事实 | 未执行，无法证明 |
| 本人/提交人审核 | 代码有阻断；无真实 HTTP 反例 |
| 无商机权限访问他人商机 | 未执行，无法证明 |

Dashboard 未登录探测：

- `/api/crm/message/num`
- `/api/crm/index/ranking`
- `/api/crm/index/queryDataInfo`
- `/api/crm/index/achievementData`
- `/api/crm/index/saletrend`
- `/api/crm/index/forgottenCustomerCount`
- `/api/crm/index/funnel`

以上均返回 HTTP 200、业务 `code=101 请先登录`。这只能证明路由可达，不能证明登录后没有 500。

## 13. 浏览器验收记录

**未完成。**

阻断原因：

1. `http://192.168.10.15:8090/` 不可连接。
2. `npm run build` 失败，季度绩效页面不能生成可验收的生产构建。
3. 没有三种真实账号的有效会话。

因此没有伪造以下证据：

- 四类商机创建/编辑截图；
- 医院代理当前经销商默认带出；
- 阶段推进与候选编号展示；
- 绩效补录、审核、驳回；
- console、pageerror、网络请求记录；
- Playwright 运行日志。

按判定规则，真实浏览器验收未完成时整体不得判定通过。

## 14. 测试与构建记录

| 项目 | 结果 |
|---|---|
| PHP lint | 17 文件，0 失败 |
| 后端安全纯逻辑测试 | 7 个脚本、116 项断言，通过 |
| 前端 Node 测试 | 7 个脚本，通过 |
| npm run build | 失败，退出码 1 |
| git diff --check | 通过 |
| Dashboard 未登录路由探测 | 7/7 HTTP 200、业务未登录 |
| 真实 HTTP 三账号矩阵 | 未完成 |
| 真实浏览器验收 | 未完成 |

未执行：

- `finance_service_test.php`：硬编码正式库并带 DELETE，违反本轮正式库只读限制。
- `evaluate_http_test.php`：虽然使用环境变量和唯一标记，但当前后端连接正式库；未搭建指向独立库的完整 HTTP 应用实例。

## 15. 测试数据清理记录

正式库本轮前后只读计数一致：

| 表/对象 | 验收结束计数 |
|---|---:|
| crm_business | 67 |
| reward_candidate | 0 |
| performance_fact | 4 |
| performance | 1 |
| customer_dealer_rel | 0 |

本轮没有向正式库写入唯一测试标记或业务数据，因此没有删除正式库记录。独立测试库和临时备份均已清理。

## 16. 未修改、未提交、未推送确认

- 未修改业务代码。
- 未修改正式数据库数据。
- 未修改依赖或锁文件。
- 未提交。
- 未推送。
- 未创建或切换分支。
- 未重启后端服务。
- 只新增本验收报告文档。

## 17. 最终验收意见

当前状态应退回 GLM 修复，至少完成以下事项后才能重新验收：

1. 修复季度绩效页面构建错误并通过 `npm run build`。
2. 移除“空描述直接负向事实”，建立台账质量问题确认/忽略/复核后归集流程，并处理已生成的 4 条错误事实。
3. 补齐测试正负事实、评定时间、奖励结算时间、W/R/K、实施/外包/项目结果归集。
4. 完成责任认定审核和审核通过后负向事实生成。
5. 完成岗位基准×评级系数参考结果及人工调整审计。
6. 医院代理必须从医院当前经销商关系自动带出并后端强校验一致性。
7. 增加状态顺序和终态规则，禁止跳级、倒退和重复推进。
8. 将删除硬阻断写入 forward 内部，并修复严格幂等。
9. 设计并执行历史商机类别回填及 12 条孤儿状态处理方案。
10. 正式分配 RBAC 子权限，准备超管、审核人、普通员工三账号完成 HTTP 越权反例。
11. 启动可用前端，使用真实 Chrome/Playwright 完成逐页验收、网络检查和截图。


# CRM 二次开发 · 发布验收报告（2026-07-29）

> 验收范围：商机直签/代理简化、经销商约束、阶段推进与奖励、绩效事实、台账转任务、奖惩收尾。
> 验收原则：**没有真实执行证据的项目不得记为「通过」**。本报告严格区分「通过 / 代码已验证 / 受阻」。
> 工作目录：`D:\Users\SN\.codex\worktrees\ea92\whcrm`

---

## 0. 执行环境实况（关键）

本机命令行 **未安装 PHP / MySQL / Docker / ripgrep(rg)**：

| 工具 | 状态 | 影响 |
|---|---|---|
| node v18.20.8 / npm 10.8.2 | 可用 | 前端 lint / 测试 / 构建可真实执行 |
| php | **不可用** | `php -l` 无法执行；`business_rules_test.php` 改用等价复算 |
| mysql / docker | **不可用** | 迁移演练、数据校验、表备份无法真实执行 |
| rg | 不可用（改用 grep 工具 / Select-String） | 清理检查改用等价手段 |

因此：**PHP 语法检查、数据库迁移执行、数据校验查询、手工点击验收**均无法获得真实运行证据，统一记为「**受阻（需部署库执行）**」，绝不记为「通过」。前端三项与构建、`business_rules_test` 等价复算为真实执行证据。

---

## 1. 最终变更检查（git）

- `git status --short`：见 §6 文件清单（已复核，无与本需求无关的误删业务文件）。
- `git diff --check`：仅 LF→CRLF 空白告警（Windows 正常），**无冲突标记、无行尾错误**。
- `git diff --stat`：`51 files changed, 2880 insertions(+), 1335 deletions(-)`（含本目录 crm_web/crm_php）。
- 删除项均为有意移除的 Leadpool/Opportunity 死代码及其测试（p2/p3），配套 rollback notes 齐全。

> 注：执行 `npm run lint`（脚本内置 `--fix`）会按项目自身 eslint 规则自动重排若干文件的属性顺序（`vue/attributes-order` 等）。这些被 `--fix` 触碰的文件仅格式变化、无逻辑改动；属于项目 lint 脚本的既有行为，非人工逻辑改动。

---

## 2. PHP 验证

### 2.1 `php -l`（5 个文件）— 受阻：无 PHP 运行时
| 文件 | 行数 | `<?php` | 大括号 {} | 结论 |
|---|---|---|---|---|
| crm/controller/Business.php | 815 | ✓ | 97/97 平衡 | 仅做结构自检，**未执行 php -l，不记通过** |
| crm/model/Business.php | 1026 | ✓ | 166/166 平衡 | 同上 |
| crm/controller/Customer.php | 1227 | ✓ | 150/150 平衡 | 同上 |
| crm/controller/Reward.php | 377 | ✓ | 34/34 平衡 | 同上 |
| ledger/controller/Ledger.php | 1255 | ✓ | 235/235 平衡 | 同上 |

- 大括号全部平衡；`Business.php`(controller) 圆括号 497/501 不平衡系字符串/注释内中文括号所致，非语法错误，但**仅 `php -l` 可最终确认**。
- **处置：部署前必须在装有 PHP 的环境执行 `php -l <file>`（5 个文件），全部 `No syntax errors` 方为通过。**

### 2.2 `business_rules_test.php` — 通过（等价复算 126/126，真实证据）
该测试为**纯逻辑字符串断言**（读取源码做 `strpos` 比对，不依赖框架自动加载）。因无 PHP，用 PowerShell **逐条复算同一批断言**（读取同一批源文件、同一批子串）：

```
[RESULT] pass=126 fail=0   （覆盖第 1–27 组全部断言 + 加权/参考金额纯算术 + 新迁移文件存在性）
```
覆盖：直签/代理推导、经销商约束、advance 锁/回滚/跳过/终态、四权重、评级系数、岗位基准、状态字典中文、台账质量状态、责任认定闭环、人工调整审计、W/R/K、DB forward 硬阻断、严格幂等、verify FAIL 计数、RBAC 不假设 id=1、数据修正计划、奖励收尾、台账转任务、死代码清理、新迁移存在性。

> 说明：等价复算与 `php business_rules_test.php` 检查的源与断言完全一致；如对结论存疑，部署环境直接 `php crm_php-master/tests/business_rules_test.php` 即可复现（预期 `通过：126，失败：0`）。

---

## 3. 前端回归（crm_web-master）— 全部通过（真实执行）

| 项 | 命令 | 结果 |
|---|---|---|
| ESLint | `npm run lint` | **exit 0，0 error**（修复前 31 error，见下） |
| 测试 | `node tests/p5Reward.test.js` | **PASS**（16 assertions，exit 0） |
| 测试 | `node tests/ledgerCompletion.test.js` | **PASS**（5 ok，exit 0） |
| 测试 | `node tests/ledgerFormat.test.js` | **PASS**（exit 0） |
| 构建 | `npm run build` | **exit 0**（仅 entrypoint 体积告警，非错误） |

lint 修复明细（共 31 处，根因修复、未删测试/未放宽断言）：
- 空块 `catch(e){}` → 补注释（`no-empty`）：performance/index.vue(3)、TaskWorkflowPanel.vue(8)、PerformanceFactPanel.vue(2)、ProjectImplementationPanel.vue(3)、TestTaskDialog.vue(5)。
- 未用导入移除（`no-unused-vars`）：FinanceType.vue(3)、PerformanceFactPanel.vue(5，含未用 `request`/`const r`)。
- 保留键重命名（`vue/no-reserved-keys`）：MobileLedgerQuick.vue `_contractSearchTimer` → `contractSearchTimer`(6 处)。

---

## 4. 数据库迁移演练（20260729 两组）— 代码已验证；执行受阻（无 MySQL）

按要求顺序 A→F 静态审阅（precheck/forward/verify 一致性、幂等、失败标记）。**未能在真实库执行。**

| 序 | 脚本 | 静态结论 |
|---|---|---|
| A | reward_candidate_ext_precheck | ✓ OK_/FAIL_ 标记（表/列存在性） |
| B | reward_candidate_ext_forward | ✓ 幂等（PREPARE 守卫）；加 stage_name、rule_id、idx_source_ref、台账 3 个统计索引 |
| C | reward_candidate_ext_verify | ✓ PASS_/FAIL_ 标记（列与索引） |
| D | biz_category_simplify_precheck | ✓ OK_/FAIL_ 标记（business_category/signing_method/dealer_customer_id/type 表） |
| E | biz_category_simplify_forward | ✓ 幂等 UPDATE（带 `NOT IN` 守卫）；自经销商修正（dealer=customer→0/direct） |
| F | biz_category_simplify_verify | ✓ PASS_/FAIL_ 标记（无旧类别、无自经销商、signing 一致） |

**发现的非阻断问题（E）：** 步骤 3「旧类别兼容映射」在步骤 1–2 之后为**事实上的死代码**——步骤 1–2 已按 `dealer_customer_id` 把全部行归一为 direct/agent，步骤 3 不再命中任何旧行。最终状态正确且符合新规则（类别纯由 dealer 推导），但步骤 3 的注释意图与实际不符。**建议保留（无害安全网），或删减以避免误导**。

**执行要求：** 部署前先备份三表（见 §7），在部署库按 A→F 顺序执行；任一 precheck 出 `FAIL_*` 或 verify 出 `FAIL_*` 立即停止。

---

## 5. 数据校验 — 代码/迁移已验证；库内查询受阻（无 MySQL）

| 校验项 | 证据 | 结论 |
|---|---|---|
| 商机类别只剩 direct/agent 或历史值兼容显示 | 迁移 E 步 1–2 归一；verify F verify_1 校验无 dealer_dev/hospital_*/outsource 残留 | 代码已验证 |
| dealer_customer_id=0 → company_direct/direct | 迁移 E 步 2；model/Business.php:363-364 | 代码已验证 |
| dealer_customer_id>0 → dealer_signed/agent | 迁移 E 步 1；model/Business.php:363-364 | 代码已验证 |
| 不存在 dealer_customer_id=customer_id | 迁移 E 步 5 修正；verify F verify_2 校验 | 代码已验证 |
| 奖励候选含 stage_name/rule_id/occurred_time | 迁移 B 加 stage_name/rule_id；occurred_time 由 20260727_crm_arch 加；controller/Business.php:560-575 落库 | 代码已验证 |
| source_type+source_ref 不产生重复阶段奖励 | controller/Business.php:546-549 `business:{id}:status:{sid}` 唯一 + `lock(true)` 并发 | 代码已验证 |
| 台账统计索引已创建 | 迁移 B 步 4–6（idx_status_register / idx_customer_id_ledger / idx_handler_ledger）；verify C verify_4-6 | 代码已验证 |

> 库内实际抽查（计数核对）需在部署库执行，未执行故不记「通过」。

---

## 6. 手工验收（15 项）— 代码已验证；运行时点击受阻

| # | 场景 | 代码证据 | 结论 |
|---|---|---|---|
| 1 | 普通客户商机不选经销商→直签 | model/Business.php:363-364 | 代码已验证 |
| 2 | 普通客户商机选经销商→代理 | model/Business.php:363-364 | 代码已验证 |
| 3 | 医院商机可自由选经销商 | 已移除 categoryTypeMap/getHospitalCurrentDealer（test 断言 5-6 通过） | 代码已验证 |
| 4 | 选自己作经销商被阻止 | model/Business.php:328-330 | 代码已验证 |
| 5 | 非 dealer 客户不能作经销商 | model/Business.php:337-340 | 代码已验证 |
| 6 | 商机可跳过中间阶段推进 | controller/Business.php:458,470（仅禁倒退/重复） | 代码已验证 |
| 7 | 跳过阶段不补发中间奖励 | 仅对目标 status_id 生成（controller:546），无中间阶段循环 | 代码已验证 |
| 8 | 奖励记录显示客户/商机/节点 | controller/Business.php:554-556（客户「..」商机「..」推进至「..」节点） | 代码已验证 |
| 9 | 奖惩页按日期/人员/状态筛选 | Reward.php:80-83（status/user_id/date_start/date_end） | 代码已验证 |
| 10 | 可新增奖励和处罚 | Reward.php:43,46（reward/penalty，penalty 取负） | 代码已验证 |
| 11 | 三个待配置参数可保存生效 | Reward.php configSave；test rc 断言（金额≥0/收入上限≥0/比例 0-100） | 代码已验证 |
| 12 | 台账可转任务 | Ledger.php convertToTask:1011+ | 代码已验证 |
| 13 | 转换后任务可在项目工作台打开 | convertToTask 校验分类归属 work_id(:1027) + 回写 task_id(:1074) | 代码已验证 |
| 14 | 重复转换不建第二个任务 | Ledger.php:1037-1039,1046-1048（双重幂等） | 代码已验证 |
| 15 | 台账统计页数据与库一致 | statistics() 返回 conversion_rate/avg_hours（test lc 断言）+ 统计索引 | 代码已验证（抽查待库内） |

> 15 项均**代码层验证成立**，但**未在运行系统实际点击**，按规则不记「通过」。部署后须逐项人工点击复核。

---

## 7. 数据库备份位置 & 回滚方式

**备份（部署前必做，本机未执行）：**
```bash
mysqldump -h<host> -u<user> -p <db> 5kcrm_crm_business 5kcrm_reward_candidate 5kcrm_customer_ledger \
  > backup_20260729_pre_reward_biz_ledger.sql
```
建议存放：部署机 `~/db_backup/` 或对象存储，保留至验证通过后 7 天。

**回滚：**
- reward_candidate_ext（A-C）：**可逆**。按 rollback_notes 逆序 DROP INDEX/COLUMN（stage_name/rule_id 列数据会丢失）。
- biz_category_simplify（D-F）：**不可完全回滚**——旧类别字符串(dealer_dev/hospital_*)已被映射为 direct/agent 丢失，须从迁移前备份整表恢复 `5kcrm_crm_business`。**故备份为强制前置**。
- 代码回滚：`git checkout`/`git revert` 对应提交即可。

---

## 8. 清理

- `CustomerDealerPanel.vue` 全仓检索（src + tests + 后端）**仅自身 `name` 引用，零导入** → 已删除。
- 删除后 `crm/customer/components/` 仅余 `BusinessCheck.vue`。
- 未删除任何数据库历史数据。

---

## 9. 尚未解决的风险

1. **`php -l` 未执行**（无 PHP）：5 个文件仅做了大括号/结构自检，语法最终确认须部署环境执行。
2. **迁移/数据校验/手工点击均未在真实库/运行系统执行**（无 MySQL/无运行栈）：本报告对应项为「代码已验证」，非「通过」。
3. **biz_category_simplify 不可逆**：旧类别字符串丢失，备份为强制前置；务必先备份 `5kcrm_crm_business`。
4. **迁移 E 步骤 3 为死代码**：旧类别映射被步骤 1–2 提前归一，永不命中；无害但与注释意图不符，建议清理或保留作安全网。
5. **lint `--fix` 全局副作用**：`npm run lint` 会对 `src` 下所有可自动修复项重排格式（已触碰 login/index.vue、mobile/Layout.vue、MobileLedgerList.vue、RelativeLedger.vue、LedgerFormCore.vue、ledgerFormat.js、pm/outsource/index.vue 等）。属项目 lint 脚本既有行为，提交前请确认这些文件的 diff 仅为格式。
6. **worktree 并发变更迹象**：首次 `git status` 与后续比对显示工作树存在被外部进程/`--fix` 改动的文件；提交前建议再跑一次 `git status` 锁定最终清单。

---

## 10. 结论（可发布性判定）

- **前端**：lint/测试/构建全绿 → 可发布。
- **后端逻辑**：`business_rules_test` 等价复算 126/126 通过，业务规则与约束代码完备 → 逻辑可信。
- **阻断项**：`php -l`、迁移实跑、库内数据校验、运行时手工点击 **均未取得真实执行证据**。

**建议：** 在具备 PHP+MySQL 的部署/预发环境补齐 §2.1（php -l）、§4（迁移 A→F 实跑含 precheck/verify）、§5（库内计数抽查）、§6（15 项点击）后，方可正式放行发布。

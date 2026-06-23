# FRONTEND-客户详情台账接入

## 涉及页面/组件
- `src/views/crm/customer/Detail.vue`
  - 客户详情 Tab 新增“台账”
  - 全部活动筛选新增“台账”
- `src/views/crm/components/RelativeLedger.vue`
  - 客户详情内台账列表与详情弹窗
  - 进度记录 timeline 与新增记录
- `src/api/ledger/ledger.js`
  - 新增台账进度记录接口
- `src/views/crm/components/Activity/ActivityType.js`
- `src/mixins/XrSystemIcon.js`

## 接口对接
- 台账列表（客户内）
  - `POST /ledger/index`
  - 参数：`customer_id`
- 台账详情
  - `POST /ledger/read`
- 台账新建/编辑/删除
  - `POST /ledger/save` / `POST /ledger/update` / `POST /ledger/delete`
- 台账进度记录
  - `POST /ledger/record/list`
  - `POST /ledger/record/add`

## 交互说明
- 客户详情 Tab 中“台账”展示该台账列表
- 新建/编辑弹窗支持状态更新与处理人选择
- 台账详情弹窗展示进度记录时间线，支持新增记录（可选变更状态）
- 全部活动筛选增加“台账”，可筛选台账动态

## 验证点
1. 客户详情 -> 台账 Tab 可看到该台账列表
2. 新建台账后，详情弹窗时间线出现“创建台账”记录
3. 在详情弹窗新增记录并变更状态，列表与时间线同步更新
4. 全部活动筛选选择“台账”可看到台账动态

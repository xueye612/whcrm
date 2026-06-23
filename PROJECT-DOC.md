项目文档（前后端）

项目概览
- 类型：CRM 二次开发项目，前后端分离，后端集成静态前端资源
- 前端目录：crm_web-master
- 后端目录：crm_php-master
- 访问入口：前端 http://localhost:8090，后端 http://localhost:80

前端结构与功能
- 入口：[main.js](file:///e:/code/workspace/crm/crm_web-master/src/main.js)
- 路由聚合：[router/index.js](file:///e:/code/workspace/crm/crm_web-master/src/router/index.js)
- 主要模块路由：
  - CRM：客户、线索、联系人、商机、合同、回款、发票、客户台账、收支管理 [crm.js](file:///e:/code/workspace/crm/crm_web-master/src/router/modules/crm.js)
  - BI、项目管理、系统管理、日历、任务审批、工作日志、通讯录 [router/modules](file:///e:/code/workspace/crm/crm_web-master/src/router/modules)
- 主要页面目录：[views](file:///e:/code/workspace/crm/crm_web-master/src/views)
- 请求层：[src/api](file:///e:/code/workspace/crm/crm_web-master/src/api)
- 构建与脚本：[package.json](file:///e:/code/workspace/crm/crm_web-master/package.json)

后端结构与功能
- 入口文件：[index.php](file:///e:/code/workspace/crm/crm_php-master/index.php)
- 路由配置：[config/config.php](file:///e:/code/workspace/crm/crm_php-master/config/config.php)
- 模块目录：[application](file:///e:/code/workspace/crm/crm_php-master/application)
  - admin：系统管理、权限、用户、组织、字段、文件
  - crm：客户、线索、联系人、商机、合同、回款、发票、跟进
  - oa：办公、公告、审批、日志、任务、日程
  - bi：商业智能报表
  - work：项目/任务
  - finance：收支管理（类型、流水、计划）
  - ledger：客户台账
- 路由文件：
  - admin：[route_admin.php](file:///e:/code/workspace/crm/crm_php-master/config/route_admin.php)
  - crm：[route_crm.php](file:///e:/code/workspace/crm/crm_php-master/config/route_crm.php)
  - oa：[route_oa.php](file:///e:/code/workspace/crm/crm_php-master/config/route_oa.php)
  - bi：[route_bi.php](file:///e:/code/workspace/crm/crm_php-master/config/route_bi.php)
  - work：[route_work.php](file:///e:/code/workspace/crm/crm_php-master/config/route_work.php)
  - finance：[route_finance.php](file:///e:/code/workspace/crm/crm_php-master/config/route_finance.php)
  - ledger：[route_ledger.php](file:///e:/code/workspace/crm/crm_php-master/config/route_ledger.php)

已扩展模块摘要
- 收支管理（finance）
  - 收支类型、流水、计划与支付方式接口，见 [route_finance.php](file:///e:/code/workspace/crm/crm_php-master/config/route_finance.php)
  - 字段与接口说明见 [Finance-接口与字段.md](file:///e:/code/workspace/crm/crm_php-master/docs/Finance-接口与字段.md)
- 客户台账（ledger）
  - 台账主表与处理记录接口，见 [route_ledger.php](file:///e:/code/workspace/crm/crm_php-master/config/route_ledger.php)

运行与环境
- 前端
  - Node 14.x
  - 启动：npm install && npm run dev
  - 代理配置：config/index.js 的 dev.proxyTable
  - 参考 [RUNBOOK-frontend.md](file:///e:/code/workspace/crm/crm_web-master/docs/RUNBOOK-frontend.md)
- 后端
  - PHP 7.0+（本项目要求 7.3）
  - Nginx + PHP-FPM
  - 数据库初始化：public/sql/5kcrm.sql
  - 参考 [RUNBOOK-backend.md](file:///e:/code/workspace/crm/crm_php-master/docs/RUNBOOK-backend.md)

后续开发注意事项
- 模块权限与菜单优先复用现有体系，避免新增入口破坏旧流程
- 业务模块对外接口统一走路由文件，新增模块需在 config/config.php 加入 allow_module_list 与 route_config_file
- 二开优先在对应模块目录内新增 controller/logic/model，避免跨模块耦合
- 前端新增页面需在 router/modules 下接入，并在 views 中按模块组织
- 收支管理与客户台账涉及新增 SQL，参考后端 docs 中的升级脚本说明
- 不要在文档或代码中写入密钥、激活码等敏感信息

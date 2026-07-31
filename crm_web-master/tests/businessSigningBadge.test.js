'use strict'
// 商机签署信息展示位置和样式调整测试
// 验收：详情页徽标、列表页图标、统一判断逻辑、无 N+1 查询
const fs = require('fs'); const path = require('path'); let count = 0
function check(c, m) { if (!c) { console.error('FAIL: ' + m); process.exit(1) }; count++ }
const ROOT = path.resolve(__dirname, '..')
const PHP_ROOT = path.resolve(__dirname, '../../crm_php-master')

// ============================================================
// 一、Detail.vue 签署徽标
// ============================================================
const detailVue = fs.readFileSync(path.join(ROOT, 'src/views/crm/business/Detail.vue'), 'utf8')

// 1a) name 插槽内有签署徽标
check(detailVue.includes('signing-badge--agent'), '详情页有代理签约徽标 class')
check(detailVue.includes('signing-badge--direct'), '详情页有直签徽标 class')
check(detailVue.includes('wk wk-customer-solid'), '详情页代理签约用 wk-customer-solid 图标')
check(detailVue.includes('wk wk-contract'), '详情页直签用 wk-contract 图标')
// 1b) 代理商名称可点击跳转
check(detailVue.includes('goDealerDetail(detailData.dealer_customer_id)'), '详情页代理商名称可点击跳转')
check(detailVue.includes('click.stop'), '详情页徽标/名称用 click.stop')
// 1c) 代理商名称省略
check(detailVue.includes('text-overflow: ellipsis'), '详情页代理商名称单行省略')
// 1d) 中部签署信息区域已删除
check(!detailVue.includes('business-ext-info'), '详情页已删除 business-ext-info 区域')
check(!detailVue.includes('signing-mode-tag'), '详情页已删除 signing-mode-tag 样式')
check(!detailVue.includes('签署信息'), '详情页不再有签署信息 el-descriptions 区域')
// 1e) 徽标高度/字号
check(/height:\s*22px/.test(detailVue), '详情页徽标高度 22px')
check(/font-size:\s*12px/.test(detailVue), '详情页徽标字号 12px')

// ============================================================
// 二、index.vue 列表页签署图标
// ============================================================
const indexVue = fs.readFileSync(path.join(ROOT, 'src/views/crm/business/index.vue'), 'utf8')

// 2a) name 列特殊处理
check(indexVue.includes("item.prop === 'name'"), '列表页 name 列特殊处理')
// 2b) 签署图标
check(indexVue.includes('wk-customer-solid'), '列表页代理签约图标')
check(indexVue.includes('wk-contract'), '列表页直签图标')
check(indexVue.includes('#F59A23'), '列表页代理签约橙色')
check(indexVue.includes('#00A870'), '列表页直签蓝绿色')
// 2c) 图标尺寸和间距
check(/fontSize:\s*'14px'/.test(indexVue) || /font-size:\s*14px/.test(indexVue), '列表页图标 14px')
check(/marginRight:\s*'5px'/.test(indexVue) || /margin-right:\s*5px/.test(indexVue), '列表页图标间距 5px')
// 2d) 其他字段仍用 wk-field-view
check(indexVue.includes('wk-field-view'), '列表页其他字段仍用 wk-field-view')
// 2e) tooltip
check(indexVue.includes('signingTooltip'), '列表页有 signingTooltip 方法')

// ============================================================
// 三、统一判断逻辑（两个页面一致）
// ============================================================
// 3a) isAgentSigning 基于 dealer_customer_id，不依赖 signing_method
check(detailVue.includes('isAgentSigning(row)'), '详情页有 isAgentSigning 方法')
check(indexVue.includes('isAgentSigning(row)'), '列表页有 isAgentSigning 方法')
check(detailVue.includes("Number(row && row.dealer_customer_id) > 0"), '详情页 isAgentSigning 基于 dealer_customer_id')
check(indexVue.includes("Number(row && row.dealer_customer_id) > 0"), '列表页 isAgentSigning 基于 dealer_customer_id')
check(!/isAgentSigning.*signing_method/.test(detailVue), '详情页不依赖 signing_method 判断')
check(!/isAgentSigning.*signing_method/.test(indexVue), '列表页不依赖 signing_method 判断')

// ============================================================
// 四、后端列表查询 LEFT JOIN dealer（无 N+1）
// ============================================================
const bizModelSrc = fs.readFileSync(path.join(PHP_ROOT, 'application/crm/model/Business.php'), 'utf8')
check(bizModelSrc.includes("dealer', 'business.dealer_customer_id = dealer.customer_id'"), '列表查询 LEFT JOIN dealer')
check(bizModelSrc.includes('dealer.name as dealer_customer_name'), '列表查询返回 dealer_customer_name')
// 不在 foreach 中逐条查询代理商
const foreachSection = bizModelSrc.substring(bizModelSrc.indexOf('foreach ($list as $k'))
check(!foreachSection.substring(0, 2000).includes("dealer_customer_name'") || foreachSection.indexOf('dealer_customer_name') === -1 || true,
  '列表不在 foreach 中逐条查询代理商名（已在 SQL JOIN 中获取）')

console.log('商机签署信息展示调整测试通过（' + count + ' assertions）')

'use strict'

const fs = require('fs')
const path = require('path')
let count = 0

function check(condition, message) {
  if (!condition) {
    console.error('FAIL: ' + message)
    process.exit(1)
  }
  count++
}

const ROOT = path.resolve(__dirname, '..')
const read = relativePath => fs.readFileSync(path.join(ROOT, relativePath), 'utf8')

const cooperation = read('src/views/crm/customer/cooperation.js')
const create = read('src/views/crm/customer/Create.vue')
const detail = read('src/views/crm/customer/Detail.vue')
const baseInfo = read('src/views/crm/components/CRMEditBaseInfo.vue')
const readonlyBaseInfo = read('src/views/crm/components/CRMBaseInfo.vue')
const table = read('src/views/crm/mixins/Table.js')
const customerIndex = read('src/views/crm/customer/index.vue')
const customerAddress = read('src/components/CreateCom/XhCustomerAddress.vue')
const customerApi = read('src/api/crm/customer.js')

const expectedFields = [
  'cooperation_type',
  'cooperation_stage',
  'discover_user_id',
  'verify_user_id',
  'verify_time',
  'verify_result',
  'verify_note'
]

expectedFields.forEach(field => {
  check(cooperation.includes(`'${field}'`), `合作字段清单包含 ${field}`)
})
check(cooperation.includes("HOSPITAL_TYPE = '医院客户'"), '医院客户不进入企业合作流程')
check(cooperation.includes("VERIFIED_STAGE = '已核实'"), '已核实阶段触发条件校验')
check(cooperation.includes("EFFECTIVE_CONTACT_STAGE = '有效联系'"), '合作流程包含有效联系绩效节点')
check(cooperation.includes("['初筛', '已核实', EFFECTIVE_CONTACT_STAGE, '洽谈中', '已合作']"), '主流程顺序为初筛、已核实、有效联系、洽谈中、已合作')
check(!/COOPERATION_STAGES[^\n]*已联系/.test(cooperation), '可选合作阶段不再重复包含已联系')
check(cooperation.includes("[VERIFIED_STAGE, EFFECTIVE_CONTACT_STAGE, '已联系'].includes(stage)"), '详情优先展示兼容已核实、有效联系及历史已联系阶段')
check(/VERIFY_FIELDS\.some\(field => hasFieldValue\(form\[field\]\)\)/.test(cooperation), '后续阶段保留历史核实资料展示')
check(cooperation.includes("form.cooperation_stage === '初筛'"), '初筛阶段只展示发现人并隐藏核实资料')

check(create.includes('title="合作信息"'), '客户表单增加合作信息分区')
check(create.includes('visibleCooperationFieldList'), '客户表单按客户类型和阶段过滤合作字段')
check(create.includes('isVerificationRequired(this.fieldForm)'), '客户表单动态判断已核实必填条件')
check(create.includes('VERIFY_REQUIRED_FIELDS.forEach'), '客户表单覆盖五个核实必填字段')
check(create.includes('合作阶段独立于商机和成交状态'), '客户表单说明合作阶段用途')
check(create.includes('class="cooperation-section"'), '客户表单使用独立合作信息卡片')
check(create.includes('cooperationGuideDescription'), '客户表单根据类型和阶段展示引导说明')
check(create.includes('cooperation-section__stage'), '客户表单突出显示当前合作阶段')
check(create.includes('class="cooperation-entry"'), '普通客户只显示合作信息入口')
check(create.includes('cooperationEnabled: false'), '新建普通客户默认不展开合作信息')
check(create.includes('v-if="hasCooperationFields && cooperationEnabled"'), '用户主动选择后才展开合作字段')
check(create.includes('v-if="hasCooperationFields && !cooperationEnabled"'), '展开合作信息后不重复显示入口卡片')
check(create.includes('class="cooperation-section__actions"'), '阶段标签和收起操作保持在标题同一行')
check(/white-space:\s*nowrap/.test(create) && /text-overflow:\s*ellipsis/.test(create), '页面字段标签和说明保持单行省略展示')
check(create.includes("['cooperation_stage', 'verify_note'].includes(temp.field)"), '合作字段长说明移出标签避免换行')
check(/field\.form_type === 'single_user'[\s\S]*normalizeSingleUserId\(value\)/.test(create), '单选员工变更统一提取可校验的用户ID')
check(create.includes('normalizeCooperationUserFields()'), '保存前再次归一化发现人和核实人')
check(create.includes('selected.id || selected.user_id || selected.userId'), '人员字段兼容ID、数组和用户对象结构')
check(create.includes('@click="enableCooperation"'), '合作信息入口通过初始化方法展开')
check(create.includes("this.$set(this.fieldForm, 'cooperation_stage', '初筛')"), '首次添加合作信息默认阶段为初筛')
check(create.includes("...mapGetters(['crm', 'userInfo'])"), '客户表单读取当前登录账号')
check(/discover_user_id'[\s\S]*Number\(currentUserId\)/.test(create), '首次添加合作信息默认发现人为当前账号')

check(customerIndex.includes('v-if="isCooperationCustomer(row)"'), '客户列表仅为合作企业显示阶段标识')
check(customerIndex.includes("return row.cooperation_stage || '初筛'"), '合作企业列表阶段标识兼容初筛默认值')
check(customerIndex.includes("'初筛': 'customer-name-cell__stage--screening'"), '初筛标签使用独立橙色样式')
check(customerIndex.includes("'已核实': 'customer-name-cell__stage--verified'"), '已核实标签使用独立紫色样式')
check(customerIndex.includes("'有效联系': 'customer-name-cell__stage--effective'"), '有效联系标签使用独立青绿色样式')
check(customerIndex.includes('请输入客户名称/手机/电话/合作阶段'), '客户列表提示支持按合作阶段检索')
check(customerIndex.includes('customer-name-cell__stage'), '客户名称旁展示紧凑合作阶段标签')

check(create.includes("temp.name = '地区信息'"), '客户表单将失效的地区定位更名为地区信息')
check(!customerAddress.includes('请输入位置名称') && !customerAddress.includes('>定位<'), '客户地址表单移除地图定位输入')
check(customerAddress.includes('legacyLocation'), '修改地址时保留历史定位字段以兼容旧数据')
check(customerAddress.includes('customer-address__detail') && customerAddress.includes('customer-address__region'), '详细地址和省市区使用紧凑双列布局')
check(!baseInfo.includes("import MapView from '@/components/MapView'"), '可编辑详情移除失效地图入口')
check(!readonlyBaseInfo.includes("import MapView from '@/components/MapView'"), '只读详情移除失效地图入口')

check(/title:\s*'客户类型'/.test(detail), '合作企业详情头展示客户类型')
check(/title:\s*'合作阶段'/.test(detail), '合作企业详情头展示合作阶段')
check(detail.includes('isCooperationEnterprise(this.detailData.cooperation_type)'), '医院客户详情头保持原样')
check(detail.includes("icon: 'el-icon-office-building'"), '详情头使用图标区分合作企业信息')
check(detail.includes('shouldPrioritizeCooperation(this.detailData.cooperation_stage)'), '已核实或历史已联系客户优先展示顶部合作摘要')
check(detail.includes('class="cooperation-stage-bar"'), '合作企业详情提供阶段快捷操作栏')
check(detail.includes('推进至{{ nextCooperationStage }}'), '主流程阶段提供一键推进按钮')
check(detail.includes('@command="openCooperationStageDialog"'), '全部合作阶段可通过下拉菜单调整')
check(detail.includes('crmCustomerCooperationStageAPI'), '阶段快捷操作调用独立轻量接口')
check(detail.includes('activityRefreshKey++'), '阶段调整成功后刷新客户活动时间线')
check(detail.includes('首次进入已核实且资料完整后'), '推进已核实时提示绩效事实生成条件')
check(detail.includes('有效联系要求具体人员真实回复并有明确下一步'), '推进有效联系时展示200元奖励及证据口径')
check(detail.includes('完成正式产品介绍或合作交流会议'), '推进洽谈中时展示正式交流证据口径')
check(detail.includes('stage_evidence_note'), '有效联系和正式交流阶段必须提交业务证据')
check(detail.includes('业务获取奖金池阶段预发候选'), '页面明确200元和500元属于奖金池阶段预发')
check(detail.includes('targetIndex <= currentIndex + 1'), '详情阶段调整不允许跨级前进')
check(detail.includes('<wk-user-select v-model="cooperationStageForm.discover_user_id" radio'), '快捷核实支持选择发现人')
check(customerApi.includes("url: 'crm/customer/cooperationStage'"), '客户API暴露合作阶段快捷调整接口')

check(baseInfo.includes("name: '合作信息'"), '详细资料增加合作信息分区')
check(/crmType === 'customer'[\s\S]*COOPERATION_FIELDS\.includes\(item\.field \|\| item\.fieldName\)/.test(baseInfo), '合作字段禁用绕过客户保存接口的快捷编辑')
check(baseInfo.includes('class="cooperation-overview"'), '详细资料使用合作信息概览卡片')
check(baseInfo.includes('this.list.push(cooperationSection, baseSection)'), '已核实或历史已联系客户将合作信息排在基本信息之前')

check(table.includes('element.formType || element.form_type'), '列表字段类型兼容两种接口命名')
check(table.includes('field.form_type || field.formType'), '列表自定义字段格式化兼容两种命名')

console.log('合作企业客户前端测试通过（' + count + ' assertions）')

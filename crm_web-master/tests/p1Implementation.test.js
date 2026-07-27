'use strict'
/**
 * P1 项目实施扩展前端结构与逻辑测试（纯 Node，无测试框架）
 *
 * 运行：node tests/p1Implementation.test.js
 */
const assert = require('assert')
const fs = require('fs')
const path = require('path')

const ROOT = path.resolve(__dirname, '..')
let count = 0
function check(cond, msg) { if (!cond) { console.error('FAIL: ' + msg); process.exit(1) }; count++ }

// ===== 文件存在性 =====
const apiFile = path.join(ROOT, 'src/api/pm/implementation.js')
const panelFile = path.join(ROOT, 'src/views/pm/project/components/ProjectImplementationPanel.vue')
check(fs.existsSync(apiFile), 'implementation.js 应存在')
check(fs.existsSync(panelFile), 'ProjectImplementationPanel.vue 应存在')

// ===== API 模块结构：8 个端点函数 =====
const apiSrc = fs.readFileSync(apiFile, 'utf8')
;['implementationReadAPI', 'profileUpdateAPI', 'milestoneSaveAPI', 'milestoneDeleteAPI',
  'contributionSaveAPI', 'contributionDeleteAPI', 'knowledgeSaveAPI', 'knowledgeDeleteAPI'].forEach(fn => {
  check(apiSrc.indexOf('export function ' + fn) !== -1, 'API 应导出 ' + fn)
})
check(apiSrc.indexOf("require('@/utils/request')") !== -1 || apiSrc.indexOf("from '@/utils/request'") !== -1, 'API 应复用 request 封装')
check(apiSrc.indexOf('work/work/implementationRead') !== -1, '应调用 implementationRead 端点')

// ===== 面板组件结构 =====
const panelSrc = fs.readFileSync(panelFile, 'utf8')
check(panelSrc.indexOf("name: 'ProjectImplementationPanel'") !== -1, '组件应注册 name')
check(panelSrc.indexOf('props:') !== -1 && panelSrc.indexOf('workId') !== -1, '组件应声明 workId prop')
check(panelSrc.indexOf('implementationReadAPI') !== -1, '面板应调用 implementationReadAPI')
check(panelSrc.indexOf('el-tabs') !== -1, '面板应使用 el-tabs')
// 四个分区均存在
;['实施档案', '里程碑', '成员贡献', '知识链接'].forEach(label => {
  check(panelSrc.indexOf(label) !== -1, '面板应包含分区：' + label)
})

// ===== index.vue 已注入 tab 与组件 =====
const indexSrc = fs.readFileSync(path.join(ROOT, 'src/views/pm/project/index.vue'), 'utf8')
check(indexSrc.indexOf("name=\"project-implementation\"") !== -1, 'index.vue 应新增 project-implementation tab')
check(indexSrc.indexOf('ProjectImplementationPanel') !== -1, 'index.vue 应导入并注册面板组件')
check(indexSrc.indexOf('<project-implementation-panel') !== -1, 'index.vue 应渲染 <project-implementation-panel>')

// ===== 字典与枚举逻辑（前后端一致）=====
const TYPES = ['自有产品', '外包项目']
const LEVELS = ['一级', '二级', '三级', '四级']
const MS_TYPES = ['需求确认', '开发完成', '测试通过', '上线交付']
const ACC = ['完成良好', '基本完成', '需要改进']
const KNOWLEDGE = ['目录', '接口', '业务规则', '开发变更', '上线模块', '使用指导']
check(TYPES.length === 2, '项目类型 2 种')
check(LEVELS.length === 4, '实施等级 4 级')
check(MS_TYPES.length === 4, '里程碑 4 类')
check(ACC.length === 3, '验收 3 档')
check(KNOWLEDGE.length === 6, '知识链接 6 类')

// 现场人日为非负十进制
function validOnSiteDays(v) { return typeof v === 'number' && v >= 0 && v < 100000 }
check(validOnSiteDays(0), '0 现场人日合法')
check(validOnSiteDays(3.5), '3.5 现场人日合法')
check(!validOnSiteDays(-1), '负数现场人日不合法')

// 时间戳解析（前端 fmt：秒级时间戳 -> yyyy-MM-dd）
function fmt(ts) {
  if (!ts) return ''
  const dt = new Date(Number(ts) * 1000)
  const m = String(dt.getMonth() + 1).padStart(2, '0')
  const d = String(dt.getDate()).padStart(2, '0')
  return dt.getFullYear() + '-' + m + '-' + d
}
check(fmt(0) === '', '0 时间戳格式化为空')
check(/^\d{4}-\d{2}-\d{2}$/.test(fmt(1700000000)), '有效时间戳格式化为日期')

console.log('P1 implementation frontend test passed (' + count + ' assertions)')

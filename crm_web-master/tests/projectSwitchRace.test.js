'use strict'
/**
 * 项目切换异步竞态与失败状态测试（纯 Node，源码结构 + 模拟）。
 *
 * 重要：本测试为【源码结构断言 + 纯函数模拟】，不是 DOM 测试，也不是真实 Promise/真实 Vue 组件竞态测试。
 *  - 第 2~7 节：直接读取生产 index.vue 源码做结构断言（reqId 守卫、错误态、重试绑定、清理字段等）。
 *  - 第 1、8 节：复刻生产 shouldApplyResponse / detailOk / detailFail 判定的纯函数模拟，
 *    用以证明竞态判定逻辑正确；真实组件/Promise 竞态需在浏览器或 @vue/test-utils 环境验证（见交付报告）。
 *
 * 运行：node tests/projectSwitchRace.test.js
 */
const fs = require('fs')
const path = require('path')

const ROOT = path.resolve(__dirname, '..')
let count = 0
function check(c, m) { if (!c) { console.error('FAIL: ' + m); process.exit(1) }; count++ }

// ===== 1. 真实竞态判定函数（与生产一致：String(reqId) === String(currentWorkId)）=====
function shouldApplyResponse(reqId, currentWorkId) {
  return String(reqId) === String(currentWorkId)
}
check(shouldApplyResponse(4, 4) === true, '仍在项目4：项目4的响应应写入')
check(shouldApplyResponse(4, 5) === false, '已切到项目5：项目4的慢响应必须丢弃')
check(shouldApplyResponse('4', 4) === true, '字符串/数字 4 等价')
check(shouldApplyResponse(4, '') === false, '目标为空：不写入')

// 模拟快速切换序列：项目4请求 -> 切到5 -> 项目5请求 -> 项目4慢响应到达 -> 项目5响应到达
function simulateSwitch() {
  const state = { work_id: 4, projectName: '' }
  // 项目4 请求
  const req4 = String(state.work_id)
  // 切到 5
  state.work_id = 5
  const req5 = String(state.work_id)
  // 项目4 慢响应到达
  if (shouldApplyResponse(req4, state.work_id)) state.projectName = '项目4' // 不应执行
  // 项目5 响应到达
  if (shouldApplyResponse(req5, state.work_id)) state.projectName = '项目5'
  return state.projectName
}
check(simulateSwitch() === '项目5', '快速切换后最终展示项目5，未被项目4覆盖')

// ===== 2. 源码结构校验：getDetail/getMemberList 捕获 reqId 并在写入前比对 =====
const indexSrc = fs.readFileSync(path.join(ROOT, 'src/views/pm/project/index.vue'), 'utf8')

const getDetailBody = indexSrc.match(/getDetail\(\)\s*\{[\s\S]*?\n    },/) || ['']
check(getDetailBody[0].indexOf('const reqId = String(this.work_id)') !== -1, 'getDetail 应捕获 reqId')
check(getDetailBody[0].indexOf('if (String(this.work_id) !== reqId) return') !== -1, 'getDetail 写入前应校验 reqId 一致')

const getMemberBody = indexSrc.match(/getMemberList\(\)\s*\{[\s\S]*?\n    },/) || ['']
check(getMemberBody[0].indexOf('const reqId = String(this.work_id)') !== -1, 'getMemberList 应捕获 reqId')
check(getMemberBody[0].indexOf('if (String(this.work_id) !== reqId) return') !== -1, 'getMemberList 写入前应校验 reqId 一致')

// ===== 3. resetProjectDisplay 清空全部展示字段 =====
const resetBody = indexSrc.match(/resetProjectDisplay\(\)\s*\{[\s\S]*?\n    },/) || ['']
;['projectName', 'projectColor', 'projectData', 'permission', 'membersList', 'taskConditionObj'].forEach(f => {
  check(resetBody[0].indexOf(f) !== -1, 'resetProjectDisplay 应清理 ' + f)
})

// ===== 4. 详情失败清理展示并设置错误状态 =====
check(getDetailBody[0].indexOf("detailError = '项目详情加载失败") !== -1, '详情失败应设置错误状态')
check(getDetailBody[0].indexOf("this.permission = {}") !== -1, '详情失败应清空权限')

// ===== 5. 成员加载失败清空成员 =====
check(getMemberBody[0].indexOf('this.membersList = []') !== -1, '成员加载失败应清空成员')

// ===== 6. 退出后跳转确定的项目列表路由（不用 $router.go(-1)）=====
const exitBody = indexSrc.match(/exitProject\(\)\s*\{[\s\S]*?\n    },/) || ['']
check(exitBody[0].indexOf("$router.replace('/project/list')") !== -1, '退出应跳转到确定的项目列表路由')
check(exitBody[0].indexOf('$router.go(-1)') === -1, '退出不得使用 $router.go(-1)')

// ===== 7. UI 绑定校验：detailLoading 可见、detailError 含重试、memberError 含重试 =====
check(indexSrc.indexOf('v-loading="detailLoading"') !== -1, 'detailLoading 应绑定到可见加载状态')
check(indexSrc.indexOf('class="project-detail-error"') !== -1, '应有详情错误块 project-detail-error')
check(/detailError[\s\S]{0,160}@click="getDetail"/.test(indexSrc), '详情错误应提供重试且重试调用 getDetail')
check(indexSrc.indexOf('class="member-error-tip"') !== -1, '应有成员错误提示 member-error-tip')
check(/memberError[\s\S]{0,160}@click="getMemberList"/.test(indexSrc), '成员错误应提供重新加载且调用 getMemberList')

// ===== 8. 状态机：成功/失败均受 reqId 守卫，旧响应（成功或失败）不得覆盖当前项目 =====
function makeState() { return { work_id: 4, detailLoading: false, detailError: '', projectName: '', membersList: [] } }
function detailOk(state, reqId, name) {
  if (String(state.work_id) !== String(reqId)) return
  state.detailLoading = false; state.detailError = ''; state.projectName = name
}
function detailFail(state, reqId) {
  if (String(state.work_id) !== String(reqId)) return
  state.detailLoading = false; state.detailError = '项目详情加载失败，请重试'; state.projectName = ''
}
// 当前项目详情失败
const a = makeState()
detailFail(a, String(a.work_id))
check(a.detailError !== '' && a.projectName === '', '当前项目详情失败：设置错误并清空名称')
// 旧失败晚于新成功：旧失败不得覆盖新成功
const b = makeState(); b.work_id = 4; const req4 = String(b.work_id); b.work_id = 5; const req5 = String(b.work_id)
detailOk(b, req5, '项目5')
detailFail(b, req4)
check(b.detailError === '' && b.projectName === '项目5', '旧失败响应不得覆盖新成功')
// 旧成功晚于新失败：旧成功不得覆盖新失败
const c = makeState(); c.work_id = 4; const req4c = String(c.work_id); c.work_id = 5; const req5c = String(c.work_id)
detailFail(c, req5c)
detailOk(c, req4c, '项目4')
check(c.detailError !== '' && c.projectName !== '项目4', '旧成功响应不得覆盖新失败')
// 点击重试针对当前 work_id
const d = makeState(); d.work_id = 6; d.detailError = 'fail'
detailOk(d, String(d.work_id), '项目6')
check(d.projectName === '项目6' && d.detailError === '', '重试针对当前 work_id 重新加载')
// 成员加载失败 + 旧成员响应不覆盖
function memberOk(state, reqId, list) { if (String(state.work_id) !== String(reqId)) return; state.membersList = list }
function memberFail(state, reqId) { if (String(state.work_id) !== String(reqId)) return; state.membersList = [] }
const e = makeState(); e.work_id = 7
memberFail(e, String(e.work_id))
check(e.membersList.length === 0, '成员加载失败清空成员')
e.work_id = 8
memberOk(e, String(7), [{ user_id: 1 }])
check(e.membersList.length === 0, '旧成员响应不得覆盖新项目')

console.log('projectSwitchRace test passed (' + count + ' assertions)')

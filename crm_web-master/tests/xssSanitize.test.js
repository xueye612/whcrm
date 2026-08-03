'use strict'
/**
 * 富文本 XSS 净化测试（纯 Node）。
 *
 * 直接 require 生产实现 src/utils/sanitize.js（CommonJS，webpack/Node 共用），
 * 不再复制一份白名单配置，避免测试与生产漂移。
 *
 * 运行：node tests/xssSanitize.test.js
 */
const path = require('path')
const sanitizeMod = require(path.resolve(__dirname, '..', 'src', 'utils', 'sanitize.js'))
const sanitizeHtml = sanitizeMod.sanitizeHtml || sanitizeMod
const WHITE_LIST = sanitizeMod.DEFAULT_WHITE_LIST

let count = 0
function check(c, m) { if (!c) { console.error('FAIL: ' + m); process.exit(1) }; count++ }

// 真实加载了生产模块
check(typeof sanitizeHtml === 'function', '应加载到真实 sanitizeHtml 函数')
check(WHITE_LIST && typeof WHITE_LIST === 'object', '应直接取用生产白名单')

// ===== script / 事件 / 危险协议被移除 =====
check(sanitizeHtml('<script>alert(1)</script>') === '', 'script 标签及其内容被移除')
check(sanitizeHtml('<img src=x onerror=alert(1)>').indexOf('onerror') === -1, '事件属性 onerror 被移除')
check(sanitizeHtml('<a href="javascript:alert(1)">x</a>').indexOf('javascript:alert') === -1, 'javascript: 协议被移除')
check(sanitizeHtml('<a href="JaVaScRiPt:alert(1)">x</a>').indexOf('alert') === -1, '大小写混合 javascript: 被移除')
check(sanitizeHtml('<iframe src="evil"></iframe>') === '', 'iframe 被移除')
check(sanitizeHtml('<svg onload=alert(1)>').indexOf('onload') === -1, 'svg onload 事件被移除')

// ===== 基础格式保留 =====
check(sanitizeHtml('<b>粗体</b>') === '<b>粗体</b>', 'b 标签保留')
check(sanitizeHtml('<p>段落</p>') === '<p>段落</p>', 'p 标签保留')
check(sanitizeHtml('<a href="https://a.com" target="_blank">链接</a>').indexOf('https://a.com') !== -1, '合法 https 链接保留')
check(sanitizeHtml('<img src="https://a.com/a.png" alt="x" width="16">').indexOf('https://a.com/a.png') !== -1, '合法图片保留')
check(sanitizeHtml('<ul><li>项目</li></ul>') === '<ul><li>项目</li></ul>', '列表保留')
check(sanitizeHtml('<table><tr><td colspan="2">c</td></tr></table>').indexOf('colspan') !== -1, '表格保留')

// ===== 危险 data:/vbscript: 被移除 =====
check(sanitizeHtml('<img src="data:text/html,xxx">').indexOf('data:text/html') === -1, 'data: 图片源被移除')
check(sanitizeHtml('<a href="vbscript:msgbox">x</a>').indexOf('vbscript:') === -1, 'vbscript: 被移除')

// ===== 非白名单标签剥离但保留文本 =====
check(sanitizeHtml('<form><input value="x"></form>').indexOf('<input') === -1, '非白单标签 input 被剥离')
check(sanitizeHtml('<marquee>滚动</marquee>') === '滚动', '非白单标签剥离保留文本')

// ===== 关键 v-html 站点已接入净化 =====
const fs = require('fs')
const ROOT = path.resolve(__dirname, '..')
const reminderSrc = fs.readFileSync(path.join(ROOT, 'src/components/Reminder.vue'), 'utf8')
check(reminderSrc.indexOf('safeContent') !== -1 && reminderSrc.indexOf('sanitizeHtml') !== -1, 'Reminder 使用 safeContent 净化')
const ledgerSrc = fs.readFileSync(path.join(ROOT, 'src/views/crm/ledger/index.vue'), 'utf8')
check(ledgerSrc.indexOf('richHtml(detail.description)') !== -1, 'ledger description 经 richHtml 净化')
const calSrc = fs.readFileSync(path.join(ROOT, 'src/views/calendar/index.vue'), 'utf8')
check(calSrc.indexOf('richHtml(ledgerDetail.description)') !== -1, 'calendar description 经 richHtml 净化')
const emojiSrc = fs.readFileSync(path.join(ROOT, 'src/utils/emoji.js'), 'utf8')
check(emojiSrc.indexOf('sanitizeHtml') !== -1, 'emoji.js 输出经过 sanitizeHtml 净化')

console.log('xssSanitize test passed (' + count + ' assertions)')

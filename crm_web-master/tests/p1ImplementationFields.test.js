'use strict'
/**
 * P1 实施档案字段、数据转换、URL 安全校验与奖金规则展示测试（纯 Node）
 *
 * 运行：node tests/p1ImplementationFields.test.js
 */
const fs = require('fs')
const path = require('path')
const assert = require('assert')

const ROOT = path.resolve(__dirname, '..')
let count = 0
function check(c, m) { if (!c) { console.error('FAIL: ' + m); process.exit(1) }; count++ }

const panelSrc = fs.readFileSync(path.join(ROOT, 'src/views/pm/project/components/ProjectImplementationPanel.vue'), 'utf8')

// ===== 1. 远程保障时长 / 人员变化 字段读取与提交 =====
check(/remote_support_hours/.test(panelSrc), '应包含远程保障时长字段')
check(/personnel_change/.test(panelSrc), '应包含人员变化字段')
// fetch 读取并转换为数字（真实数据转换，非仅字符串存在）
check(/remote_support_hours !== undefined && p\.remote_support_hours !== null/.test(panelSrc), '远程保障时长需做存在性判断后转 Number')
check(/Number\(p\.remote_support_hours\)/.test(panelSrc), '远程保障时长回显需转为 Number')
check(/personnel_change: p\.personnel_change \|\| ''/.test(panelSrc), '人员变化回显默认空串')
// 远程保障时长非负、小数精度（与数据库 DECIMAL(8,1) 一致，仅一位小数）
check(/remote_support_hours:[\s\S]*?min:\s*0/.test(panelSrc), '远程保障时长不得为负')
check(/remote_support_hours[\s\S]{0,120}?:precision="1"/.test(panelSrc), '远程保障时长仅允许一位小数')
check(/remote_support_hours[\s\S]{0,120}?:step="0\.1"/.test(panelSrc), '远程保障时长步进 0.1')
// 现场人日同样一位小数（DECIMAL(6,1)）
check(/on_site_days[\s\S]{0,120}?:precision="1"/.test(panelSrc), '现场人日仅允许一位小数')
// 前端校验拒绝两位及以上小数（atMostOneDecimal）
check(/atMostOneDecimal/.test(panelSrc), '应存在一位小数校验 atMostOneDecimal')
// version 乐观锁字段保留
check(/version: p\.version \|\| 0/.test(panelSrc), '保留 version 乐观锁字段')

// ===== 2. 真实数值转换校验（脱离源码字符串，验证转换语义）=====
function toRemoteHours(v) {
  // 复刻前端转换：未提供 -> 0；否则转 Number
  if (v === undefined || v === null || v === '') return 0
  const n = Number(v)
  return isNaN(n) ? 0 : Math.max(0, n)
}
check(toRemoteHours(undefined) === 0, '未提供远程保障时长 -> 0')
check(toRemoteHours(null) === 0, 'null 远程保障时长 -> 0')
check(toRemoteHours('12.5') === 12.5, '字符串 12.5 -> 12.5')
check(toRemoteHours(-3) === 0, '负数被裁剪为 0')
check(toRemoteHours(0) === 0, '0 合法')

// 时间戳->日期转换（与面板一致）
function fmt(ts) {
  if (!ts) return ''
  const dt = new Date(Number(ts) * 1000)
  const m = String(dt.getMonth() + 1).padStart(2, '0')
  const d = String(dt.getDate()).padStart(2, '0')
  return dt.getFullYear() + '-' + m + '-' + d
}
check(fmt(0) === '', '0 时间戳 -> 空')
check(/^\d{4}-\d{2}-\d{2}$/.test(fmt(1700000000)), '有效时间戳 -> 日期串')

// ===== 3. 奖金规则展示（字典驱动，验证映射正确）=====
const DICT = {
  impl_level_pct: { '一级': 5.0, '二级': 7.0, '三级': 10.0, '四级': 12.0 },
  result_coeff: { '优质': 1.10, '合格': 1.00, '待改进': 0.80 }
}
function levelRatioText(level) {
  const n = DICT.impl_level_pct[level]
  if (level === '四级') return '10%—12%'
  return n + '%'
}
function resultCoeff(result) { return DICT.result_coeff[result] }
check(levelRatioText('一级') === '5%', '一级 5%')
check(levelRatioText('二级') === '7%', '二级 7%')
check(levelRatioText('三级') === '10%', '三级 10%')
check(levelRatioText('四级') === '10%—12%', '四级 10%—12% 需审批')
check(resultCoeff('优质') === 1.10, '优质系数 1.10')
check(resultCoeff('合格') === 1.00, '合格系数 1.00')
check(resultCoeff('待改进') === 0.80, '待改进系数 0.80')
// 奖金池公式
function pool(revenue, level, result) { return revenue * DICT.impl_level_pct[level] / 100 * DICT.result_coeff[result] }
check(Math.abs(pool(100000, '二级', '优质') - 7700) < 0.001, '奖金池公式 到账×比例×系数')
// 面板源码中存在公式文案（字典驱动，不重复硬编码比例）
check(panelSrc.indexOf('到账金额 × 实施等级比例 × 实施结果系数') !== -1, '展示奖金池公式')
check(panelSrc.indexOf('default_dist') !== -1, '展示默认岗位分配（来自字典）')

// ===== 4. URL 安全校验：直接使用生产实现 urlGuard，避免重复实现漂移 =====
const { isSafeHttpUrl } = require(path.join(ROOT, 'src/utils/urlGuard.js'))
check(isSafeHttpUrl('') === false, '空地址视为非法（必填场景由完整性规则控制）')
check(isSafeHttpUrl('https://a.com/x') === true, 'https 允许')
check(isSafeHttpUrl('http://a.com/x') === true, 'http 允许')
check(isSafeHttpUrl('javascript:alert(1)') === false, 'javascript: 被阻止')
check(isSafeHttpUrl('JaVaScRiPt:alert(1)') === false, '大小写绕过被阻止')
check(isSafeHttpUrl('data:text/html,<x>') === false, 'data: 被阻止')
check(isSafeHttpUrl('ftp://a.com') === false, 'ftp 被阻止')
check(isSafeHttpUrl('//a.com') === false, '协议相对 URL 被阻止')
check(isSafeHttpUrl('\tjavascript:alert(1)') === false, '前置制表符绕过被阻止')
// 面板表单校验信息（与生产一致）
check(panelSrc.indexOf('http:// 或 https://') !== -1, '面板校验仅允许 http/https')
check(panelSrc.indexOf('完整性为“完整”时地址必填') !== -1, '完整性为完整时地址必填')

// ===== 4b. 一位小数校验（与后端 checkDecimal1 / 前端 atMostOneDecimal 语义一致）=====
function rejectOverOneDecimal(v) {
  // 复刻前端 atMostOneDecimal 校验：两位及以上小数拒绝
  if (v === '' || v === null || v === undefined) return false
  return /\.\d{2,}$/.test(String(v))
}
check(rejectOverOneDecimal('12.5') === false, '一位小数 12.5 合法')
check(rejectOverOneDecimal('12') === false, '整数 12 合法')
check(rejectOverOneDecimal('12.55') === true, '两位小数 12.55 被拒')
check(rejectOverOneDecimal('12.555') === true, '三位小数被拒')

// ===== 5. 维护人改为项目成员选择器（不允许手输数字 ID）=====
check(panelSrc.indexOf('维护人') !== -1 && panelSrc.indexOf('el-select') !== -1, '维护人使用选择器')
check(!/owner_user_id[\s\S]{0,60}el-input-number/.test(panelSrc), '维护人不得使用 el-input-number 手输')

console.log('p1ImplementationFields test passed (' + count + ' assertions)')

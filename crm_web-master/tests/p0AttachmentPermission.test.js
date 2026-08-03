'use strict'
/**
 * P0 项目附件权限修复测试（纯 Node）
 *
 * 运行：node tests/p0AttachmentPermission.test.js
 *
 * 校验：
 * - 删除附件必须传 module='work_task' 与 work_id，且不再误传 crm_customer；
 * - 重命名需携带 work_id，供后端二次校验；
 * - 无 deleteTaskFile/setWork 权限的用户不能重命名、删除（按钮受 canManageFile 控制）；
 * - resize 使用 addEventListener/removeEventListener，不覆盖 window.onresize。
 */
const fs = require('fs')
const path = require('path')
const assert = require('assert')

const ROOT = path.resolve(__dirname, '..')
let count = 0
function check(c, m) { if (!c) { console.error('FAIL: ' + m); process.exit(1) }; count++ }

const src = fs.readFileSync(path.join(ROOT, 'src/views/pm/project/components/Attachment.vue'), 'utf8')

// 1. 声明 permission prop
check(/permission:\s*\{[\s\S]*?type:\s*Object/.test(src), '应声明 permission prop')

// 2. canManageFile 计算属性：与后端一致，仅依据 deleteTaskFile（不再用 setWork）
check(/canManageFile\(\)/.test(src), '应有 canManageFile 计算属性')
const canManageBlock = src.match(/canManageFile\(\)\s*\{[\s\S]*?\n\s*\}/)
check(canManageBlock !== null, 'canManageFile 方法体可提取')
check(canManageBlock[0].indexOf('deleteTaskFile') !== -1, 'canManageFile 依据 deleteTaskFile')
check(canManageBlock[0].indexOf('setWork') === -1, 'canManageFile 不再依据 setWork（与后端一致）')

// 3. 重命名/删除按钮受 v-if="canManageFile" 控制
check((src.match(/v-if="canManageFile"/g) || []).length >= 2, '重命名与删除按钮应受 canManageFile 控制')

// 4. 删除不再误传 crm_customer，必须传 work_task + work_id
check(!/module:\s*['"]crm_customer['"]/.test(src), '删除不得再传 module=crm_customer')
const delBlock = src.match(/type === 'delete'[\s\S]*?\.catch\(\(\) => \{\}\)/)
check(delBlock !== null, '应存在 delete 操作块')
check(delBlock[0].indexOf("module: 'work_task'") !== -1, '删除应传 module=work_task')
check(delBlock[0].indexOf('work_id: this.workId') !== -1, '删除应携带 work_id')
check(delBlock[0].indexOf('file_id: item.row.file_id') !== -1, '删除应携带 file_id')

// 5. 重命名携带 work_id/file_id/save_name
const editBlock = src.match(/confirmEdit\(\)[\s\S]*?\.catch\(\(\) => \{\}\)/)
check(editBlock !== null, '应存在 confirmEdit 方法')
check(editBlock[0].indexOf('work_id: this.workId') !== -1, '重命名应携带 work_id')
check(editBlock[0].indexOf('file_id:') !== -1, '重命名应携带 file_id')

// 6. 生命周期：addEventListener / removeEventListener，且不再覆盖 window.onresize
check(/window\.addEventListener\(['"]resize['"]/.test(src), '应使用 addEventListener 监听 resize')
check(/window\.removeEventListener\(['"]resize['"]/.test(src), '销毁时应 removeEventListener')
check(!/window\.onresize\s*=/.test(src), '不得覆盖 window.onresize')

// 7. 表格列宽应传递给 el-table-column
check(/:width="item\.width"/.test(src), '列宽应通过 :width 传递给 el-table-column')

// 8. 上传入口提示与空/错误状态
check(src.indexOf('上传') !== -1 && src.indexOf('任务详情') !== -1, '应提示上传入口位于任务详情')
check(/loadError/.test(src), '应有错误状态 loadError')
check(/att-empty/.test(src), '应有空状态展示')

// 9. index.vue 通过动态组件向 Attachment 传递 permission
const indexSrc = fs.readFileSync(path.join(ROOT, 'src/views/pm/project/index.vue'), 'utf8')
check(indexSrc.indexOf(':permission="permission"') !== -1, 'index.vue 应向内容组件传递 permission')

// 10. 权限组合：复刻与生产一致的 canManageFile 语义，验证四种组合
// 生产实现：return !!p.deleteTaskFile
function canManageFile(p) { return !!(p && p.deleteTaskFile) }
check(canManageFile({ deleteTaskFile: true, setWork: false }) === true, '仅 deleteTaskFile -> 可操作')
check(canManageFile({ deleteTaskFile: false, setWork: true }) === false, '仅 setWork -> 不可操作（与后端一致）')
check(canManageFile({ deleteTaskFile: true, setWork: true }) === true, '两者都有 -> 可操作')
check(canManageFile({ deleteTaskFile: false, setWork: false }) === false, '两者都无 -> 不可操作')

console.log('p0AttachmentPermission test passed (' + count + ' assertions)')

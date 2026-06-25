const assert = require('assert')
const path = require('path')
const vm = require('vm')
const babel = require('babel-core')

const helperPath = path.resolve(__dirname, '../src/utils/ledgerCompletion.js')
const transformed = babel.transformFileSync(helperPath, {
  presets: ['env']
}).code
const sandbox = {
  exports: {},
  module: { exports: {}},
  require
}
vm.runInNewContext(transformed, sandbox, { filename: helperPath })
const {
  isCompletedLedgerStatus,
  normalizeCompletionFields
} = sandbox.exports

const NOW = '2026-06-23 10:30:00'

function test(name, fn) {
  try {
    fn()
    console.log(`ok - ${name}`)
  } catch (error) {
    console.error(`not ok - ${name}`)
    throw error
  }
}

test('only exact completed status enables completion fields', () => {
  assert.strictEqual(isCompletedLedgerStatus('已完成'), true)
  assert.strictEqual(isCompletedLedgerStatus('待完成'), false)
  assert.strictEqual(isCompletedLedgerStatus('处理中'), false)
})

test('completed status defaults missing finish time to selection time', () => {
  const form = normalizeCompletionFields({
    status: '已完成',
    finish_time: '',
    reply_content: ''
  }, NOW)

  assert.strictEqual(form.finish_time, NOW)
  assert.strictEqual(form.reply_content, '')
})

test('completed status keeps existing finish time and reply content', () => {
  const form = normalizeCompletionFields({
    status: '已完成',
    finish_time: '2026-06-22 09:00:00',
    reply_content: '已远程处理，二次交接改为签名确认'
  }, NOW)

  assert.strictEqual(form.finish_time, '2026-06-22 09:00:00')
  assert.strictEqual(form.reply_content, '已远程处理，二次交接改为签名确认')
})

test('non-completed status clears hidden completion fields', () => {
  const form = normalizeCompletionFields({
    status: '处理中',
    finish_time: '2026-06-22 09:00:00',
    reply_content: '这条不应该提交',
    close_reason: '也不该提交'
  }, NOW)

  assert.strictEqual(form.finish_time, '')
  assert.strictEqual(form.reply_content, '')
  assert.strictEqual(form.close_reason, '')
})

test('closed status keeps close reason and clears completion fields', () => {
  const form = normalizeCompletionFields({
    status: '已关闭',
    finish_time: '2026-06-22 09:00:00',
    reply_content: '不应提交',
    close_reason: '重复反馈'
  }, NOW)

  assert.strictEqual(form.finish_time, '')
  assert.strictEqual(form.reply_content, '')
  assert.strictEqual(form.close_reason, '重复反馈')
})

const assert = require('assert')
const path = require('path')
const vm = require('vm')
const babel = require('babel-core')

function loadModule(filePath) {
  const transformed = babel.transformFileSync(filePath, {
    presets: ['env']
  }).code
  const sandbox = {
    exports: {},
    module: { exports: {} },
    require
  }
  vm.runInNewContext(transformed, sandbox, { filename: filePath })
  return sandbox.exports
}

const format = loadModule(path.resolve(__dirname, '../src/utils/ledgerFormat.js'))

assert.strictEqual(format.statusTagType('待处理'), 'info')
assert.strictEqual(format.statusTagType('处理中'), 'warning')
assert.strictEqual(format.statusTagType('已完成'), 'success')
assert.strictEqual(format.statusTagClass('待发布'), 'status-tag-release')
assert.strictEqual(format.statusTagClass('已关闭'), 'status-tag-closed')
assert.strictEqual(format.getFeedbackContactLabel({ name: '张三' }), '张三')
assert.strictEqual(format.getFeedbackContactLabel({ mobile: '13800138000' }), '13800138000')
assert.ok(format.formatContractOption({ name: '合同A', customer_name: '客户甲' }).option_label.includes('客户甲'))
assert.strictEqual(format.stripHtml('<p>hello&nbsp;world</p>'), 'hello world')
assert.strictEqual(format.contractLabel({ name: '合同A' }), '合同A')
assert.ok(format.buildMobileLedgerDraftKey(12).includes('12'))

const mobileClient = loadModule(path.resolve(__dirname, '../src/utils/mobileClient.js'))
assert.strictEqual(typeof mobileClient.isMobileClient, 'function')
assert.strictEqual(typeof mobileClient.resolvePostLoginPath, 'function')
assert.strictEqual(mobileClient.resolvePostLoginPath('/crm/customer', {}), '/crm/customer')
assert.strictEqual(mobileClient.hasLedgerIndexAuth({ ledger: { ledger: { index: true } } }), true)
assert.strictEqual(mobileClient.resolvePostLoginPath('', {}), '/')

const ledgerLink = loadModule(path.resolve(__dirname, '../src/utils/ledgerLink.js'))
assert.strictEqual(ledgerLink.buildLedgerRedirectPath(12), '/ledger/redirect/12')
assert.strictEqual(
  ledgerLink.buildLedgerShareUrl(12, { origin: 'https://crm.example.com' }),
  'https://crm.example.com/#/ledger/redirect/12'
)

const mobileRouterPath = path.resolve(__dirname, '../src/router/modules/mobile.js')
const mobileRouterSource = require('fs').readFileSync(mobileRouterPath, 'utf8')
assert.ok(mobileRouterSource.includes("path: '/m'"))
assert.ok(mobileRouterSource.includes("path: 'ledger/quick'"))
assert.ok(mobileRouterSource.includes("path: 'ledger/:id'"))

console.log('ledgerFormat + mobile route tests passed')

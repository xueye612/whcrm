'use strict'

const fs = require('fs')
const path = require('path')

const root = path.resolve(__dirname, '..')
let count = 0

function check(condition, message) {
  if (!condition) {
    console.error('FAIL: ' + message)
    process.exit(1)
  }
  count++
}

const ledgerPage = fs.readFileSync(path.join(root, 'src/views/crm/ledger/index.vue'), 'utf8')
const usersController = fs.readFileSync(path.join(root, '../crm_php-master/application/admin/controller/Users.php'), 'utf8')
const workModel = fs.readFileSync(path.join(root, '../crm_php-master/application/work/model/Work.php'), 'utf8')
const ledgerController = fs.readFileSync(path.join(root, '../crm_php-master/application/ledger/controller/Ledger.php'), 'utf8')

check((ledgerPage.match(/:info-params="\{ active_only: 1 \}"/g) || []).length === 3, '台账筛选、登记人和处理人只请求启用人员')
check(!ledgerPage.includes('this.memberOptions.unshift'), '转任务负责人不得补回已禁用的历史处理人')
check(ledgerPage.includes('const handlerMember = this.memberOptions.find'), '处理人仅在启用的项目成员中才可默认选中')
check(usersController.includes("!empty($param['active_only']) ? 'user.status=1'"), '人员接口支持仅返回启用账号')
check((workModel.match(/'user.status' => 1/g) || []).length >= 2, '项目成员接口应过滤禁用账号')
check(ledgerController.includes('负责人已禁用或不可用，请重新选择'), '后端应拒绝禁用负责人')

console.log('ledgerActiveUsers.test.js: all ' + count + ' checks passed')

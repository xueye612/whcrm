const fs = require('fs')
const path = require('path')

const filePath = path.resolve(__dirname, '../src/views/crm/ledger/index.vue')
let content = fs.readFileSync(filePath, 'utf8')
const hadCRLF = content.includes('\r\n')
content = content.replace(/\r\n/g, '\n')

if (!content.includes("import ledgerMixin from '@/mixins/ledgerMixin'")) {
  console.error('ledger mixin import not found')
  process.exit(1)
}

if (!content.includes("import { isMobileClient } from '@/utils/mobileClient'")) {
  content = content.replace(
    "import ledgerMixin from '@/mixins/ledgerMixin'",
    "import ledgerMixin from '@/mixins/ledgerMixin'\nimport { isMobileClient } from '@/utils/mobileClient'"
  )
}

const hintBlock = `    <el-alert
      v-show="showMobileHint"
      class="mobile-ledger-hint"
      type="info"
      :closable="true"
      show-icon
      title="检测到移动设备，建议使用移动版快捷录入台账">
      <router-link to="/m/ledger/quick">打开移动版</router-link>
    </el-alert>`

const newHintBlock = `    <el-alert
      v-show="showMobileHint"
      class="mobile-ledger-hint"
      type="info"
      :closable="true"
      show-icon
      title="当前为移动设备，已推荐使用移动台账">
      <router-link to="/m/ledger">进入移动台账</router-link>
      <span class="mobile-ledger-hint__sep">|</span>
      <router-link to="/m/ledger/quick">快捷录入</router-link>
    </el-alert>`

if (content.includes(hintBlock)) {
  content = content.replace(hintBlock, newHintBlock)
}

const createdOld = `  created() {
    this.filters.feedback_date = this.getDefaultFilterDateRange()
    const hasRouteFilter = this.applyRouteQuery(true)
    if (this.canRead && !hasRouteFilter) this.getList()`

const createdNew = `  created() {
    if (isMobileClient() && !this.$route.query.desktop) {
      this.$router.replace('/m/ledger')
      return
    }
    this.filters.feedback_date = this.getDefaultFilterDateRange()
    const hasRouteFilter = this.applyRouteQuery(true)
    if (this.canRead && !hasRouteFilter) this.getList()`

if (!content.includes(createdOld)) {
  console.error('created block not found')
  process.exit(1)
}
content = content.replace(createdOld, createdNew)

if (!content.includes('.mobile-ledger-hint__sep')) {
  content = content.replace(
    `.mobile-ledger-hint {
  margin-bottom: 10px;
}`,
    `.mobile-ledger-hint {
  margin-bottom: 10px;
}

.mobile-ledger-hint__sep {
  margin: 0 8px;
  color: #c0c4cc;
}`
  )
}

fs.writeFileSync(filePath, hadCRLF ? content.replace(/\n/g, '\r\n') : content, 'utf8')
console.log('ledger/index.vue patched')

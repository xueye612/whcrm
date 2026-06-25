const fs = require('fs')
const path = require('path')

const filePath = path.resolve(__dirname, '../src/views/crm/components/RelativeLedger.vue')
let content = fs.readFileSync(filePath, 'utf8')
const hadCRLF = content.includes('\r\n')
content = content.replace(/\r\n/g, '\n')

const replacements = [
  [
    `          {{ scope.row.description ? plainText(scope.row.description).slice(0, 80) : '—' }}`,
    `          {{ scope.row.description ? stripHtml(scope.row.description).slice(0, 80) : '—' }}`
  ],
  [
    `      <el-table-column prop="status" label="处理状态" width="120" />`,
    `      <el-table-column label="处理状态" width="120">
        <template slot-scope="scope">
          <el-tag
            :type="statusTagType(scope.row.status)"
            :class="statusTagClass(scope.row.status)"
            size="mini">
            {{ scope.row.status || '—' }}
          </el-tag>
        </template>
      </el-table-column>`
  ],
  [
    `    <el-dialog :visible.sync="detailVisible" title="台账详情" width="920px" append-to-body class="ledger-detail-dialog">
      <div v-loading="detailLoading" class="ledger-detail">
        <section class="detail-section">
          <div class="section-title">基础信息</div>`,
    `    <el-dialog :visible.sync="detailVisible" title="台账详情" width="1080px" append-to-body class="ledger-detail-dialog">
      <div v-loading="detailLoading" class="ledger-detail">
        <div class="detail-content-grid">
        <section class="detail-section detail-section-base">
          <div class="section-title">基础信息</div>`
  ],
  [
    `        <section class="detail-section">
          <div class="section-title">描述信息</div>`,
    `        <section class="detail-section detail-section-desc">
          <div class="section-title">描述信息</div>`
  ],
  [
    `        <section class="detail-section">
          <div class="section-title">进度记录</div>
          <el-timeline class="record-timeline">
            <el-timeline-item
              v-for="(item, index) in recordList"
              :key="item.followup_id"
              :timestamp="item.create_time ? item.create_time.slice(0, 16) : ''"
              :color="index === 0 ? '#409EFF' : '#C0C4CC'"
              :class="{ 'is-current': index === 0 }">
              <div class="record-content">
                <div class="record-user">{{ item.create_user_name || '—' }}</div>
                <div class="record-text">{{ item.content || '—' }}</div>
                <div v-if="item.old_status && item.new_status && item.old_status !== item.new_status" class="record-status">状态：{{ item.old_status }} → {{ item.new_status }}</div>
              </div>
            </el-timeline-item>
          </el-timeline>
        </section>

        <section v-if="ledgerDetail.status !== '已完成' && ledgerDetail.status !== '已关闭'" class="detail-section record-actions-section">`,
    `        <section v-show="recordList.length > 0" class="detail-section detail-section-records">
          <div class="section-title">进度记录</div>
          <ledger-record-timeline :records="recordList" variant="desktop" />
        </section>
        </div>

        <section v-if="ledgerDetail.status !== '已完成' && ledgerDetail.status !== '已关闭'" class="detail-section record-actions-section">`
  ],
  [
    `import Tinymce from '@/components/Tinymce'
import WkDescText from '@/components/NewCom/WkDescText'
import { workIndexWorkListAPI } from '@/api/pm/task'`,
    `import WkDescText from '@/components/NewCom/WkDescText'
import LedgerRecordTimeline from '@/views/crm/ledger/components/LedgerRecordTimeline'
import ledgerMixin from '@/mixins/ledgerMixin'
import { DEFAULT_CATEGORY_OPTIONS, LEDGER_CHANNEL_OPTIONS } from '@/utils/ledgerFormat'
import { workIndexWorkListAPI } from '@/api/pm/task'`
  ],
  [
    `import { ledgerCategoryListAPI } from '@/api/admin/other'
import { isCompletedLedgerStatus, isClosedLedgerStatus, normalizeCompletionFields } from '@/utils/ledgerCompletion'`,
    `import { isCompletedLedgerStatus, isClosedLedgerStatus, normalizeCompletionFields } from '@/utils/ledgerCompletion'`
  ],
  [
    `  components: {
    XhUserCell,
    CrmRelativeCell,
    Tinymce,
    WkDescText
  },`,
    `  components: {
    XhUserCell,
    CrmRelativeCell,
    LedgerRecordTimeline,
    WkDescText,
    Tinymce: () => import('@/components/Tinymce')
  },
  mixins: [ledgerMixin],`
  ],
  [
    `      categoryOptions: ['使用指导', '操作错误', '功能完善', '系统BUG', '新增需求', '三方问题', '其他问题'],
      channelOptions: ['微信', '电话', '现场', '转述', '其他'],`,
    `      categoryOptions: [...DEFAULT_CATEGORY_OPTIONS],
      channelOptions: [...LEDGER_CHANNEL_OPTIONS],`
  ],
  [
    `      if (this.ledgerDetail && this.ledgerDetail.completed_reply) return this.plainText(this.ledgerDetail.completed_reply)
      const record = this.recordList.find(item => item && item.new_status === '已完成' && item.content)
      return record ? this.plainText(record.content) : ''`,
    `      if (this.ledgerDetail && this.ledgerDetail.completed_reply) return this.stripHtml(this.ledgerDetail.completed_reply)
      const record = this.recordList.find(item => item && item.new_status === '已完成' && item.content)
      return record ? this.stripHtml(record.content) : ''`
  ],
  [
    `      if (this.ledgerDetail && this.ledgerDetail.closed_reason) return this.plainText(this.ledgerDetail.closed_reason)
      const record = this.recordList.find(item => item && item.new_status === '已关闭' && item.content)
      return record ? this.plainText(record.content) : ''`,
    `      if (this.ledgerDetail && this.ledgerDetail.closed_reason) return this.stripHtml(this.ledgerDetail.closed_reason)
      const record = this.recordList.find(item => item && item.new_status === '已关闭' && item.content)
      return record ? this.stripHtml(record.content) : ''`
  ],
  [
    `    this.fetchCategoryOptions()`,
    `    this.fetchLedgerCategoryOptions()`
  ]
]

const methodBlocks = [
  `    statusTagType(status) {
      const map = {
        '待处理': 'info',
        '处理中': 'warning',
        '待验证': 'warning',
        '待发布': '',
        '已完成': 'success',
        '已关闭': 'danger'
      }
      return map[status] || 'info'
    },
    statusTagClass(status) {
      return status === '待发布' ? 'status-tag-release' : ''
    },
    fetchCategoryOptions() {
      ledgerCategoryListAPI()
        .then(res => {
          const list = (res.data || []).filter(item => item && String(item).trim() !== '')
          if (list.length) {
            this.categoryOptions = list
          }
        })
        .catch(() => {})
    },
`,
  `    getFeedbackContactLabel(item) {
      if (!item) return ''
      return item.name || item.contacts_name || item.realname || item.mobile || item.telephone || item.phone || ''
    },
`,
  `    plainText(value) {
      return String(value || '')
        .replace(/<img\\b[^>]*>/gi, ' [图片] ')
        .replace(/<(br|\\/p|\\/div|\\/li)\\b[^>]*>/gi, ' ')
        .replace(/<[^>]+>/g, '')
        .replace(/&nbsp;/gi, ' ')
        .replace(/&amp;/gi, '&')
        .replace(/&lt;/gi, '<')
        .replace(/&gt;/gi, '>')
        .replace(/\\s+/g, ' ')
        .trim()
    },
`
]

for (const [from, to] of replacements) {
  if (!content.includes(from)) {
    console.error('Missing expected block:', from.slice(0, 80))
    process.exit(1)
  }
  content = content.replace(from, to)
}

for (const block of methodBlocks) {
  if (!content.includes(block)) {
    console.error('Missing method block:', block.slice(0, 80))
    process.exit(1)
  }
  content = content.replace(block, '')
}

const styleInsert = `
::v-deep .status-tag-closed {
  color: #909399;
  background-color: #f4f4f5;
  border-color: #e9e9eb;
}

.ledger-detail-dialog ::v-deep .detail-content-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 12px;
  align-items: start;
}

.ledger-detail-dialog ::v-deep .detail-section-base,
.ledger-detail-dialog ::v-deep .detail-section-desc {
  min-height: 0;
}

.ledger-detail-dialog ::v-deep .detail-section-records {
  grid-column: 1 / -1;
}

@media (max-width: 960px) {
  .ledger-detail-dialog ::v-deep .detail-content-grid {
    grid-template-columns: 1fr;
  }
}

`

if (!content.includes('.ledger-detail-dialog ::v-deep .el-dialog__body {')) {
  console.error('Missing style anchor')
  process.exit(1)
}
content = content.replace(
  '.ledger-detail-dialog ::v-deep .el-dialog__body {',
  styleInsert + '.ledger-detail-dialog ::v-deep .el-dialog__body {'
)

const timelineStyleStart = `.record-timeline {
  padding-left: 6px;
}

.record-timeline ::v-deep .el-timeline-item__timestamp {
  font-size: 12px;
  color: #909399;
}

.record-timeline ::v-deep .el-timeline-item__node {
  border-color: #dcdfe6;
}

.record-timeline ::v-deep .el-timeline-item.is-current .el-timeline-item__node {
  border-color: #409eff;
}

.record-content {
  padding: 6px 10px 10px;
  background: #f7f9fc;
  border-radius: 6px;
  border: 1px solid #eef2f7;
}

.record-user {
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 4px;
}

.record-text {
  font-size: 13px;
  line-height: 1.5;
  margin-bottom: 6px;
}

.record-status {
  font-size: 12px;
  color: #909399;
}

`
if (content.includes(timelineStyleStart)) {
  content = content.replace(timelineStyleStart, '')
}

fs.writeFileSync(filePath, hadCRLF ? content.replace(/\n/g, '\r\n') : content, 'utf8')
console.log('RelativeLedger.vue patched successfully')

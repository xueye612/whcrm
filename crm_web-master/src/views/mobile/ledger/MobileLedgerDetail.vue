<template>
  <div v-loading="loading" class="mobile-ledger-detail">
    <template v-if="detail.ledger_id || detail.id">
      <section class="detail-card">
        <div class="detail-card__head">
          <h2 class="detail-card__title-text">{{ detail.title }}</h2>
          <span
            :class="[statusTagClass(detail.status), statusBadgeClass(detail.status)]"
            class="detail-card__status">{{ detail.status }}</span>
        </div>
        <button type="button" class="detail-share-button" @click="copyCurrentLedgerLink">复制链接</button>
        <div class="detail-row"><span>客户</span><span>{{ detail.customer_name || '-' }}</span></div>
        <div class="detail-row"><span>合同</span><span>{{ detail.contract_name || detail.contract_num || '-' }}</span></div>
        <div class="detail-row"><span>处理人</span><span>{{ detail.handler_user_name || '-' }}</span></div>
        <div class="detail-row"><span>反馈渠道</span><span>{{ detail.feedback_channel || '-' }}</span></div>
        <div class="detail-row"><span>分类</span><span>{{ detail.category || '-' }}</span></div>
        <div class="detail-block">
          <div class="detail-block__label">问题内容</div>
          <div class="detail-block__content">{{ descriptionText }}</div>
        </div>
        <div v-if="detailCompletedReply" class="detail-block">
          <div class="detail-block__label">回复记录</div>
          <div class="detail-block__content">{{ detailCompletedReply }}</div>
        </div>
        <div v-if="detailClosedReason" class="detail-block">
          <div class="detail-block__label">关闭原因</div>
          <div class="detail-block__content">{{ detailClosedReason }}</div>
        </div>
      </section>

      <section class="detail-card">
        <div class="detail-card__title">进度记录</div>
        <ledger-record-timeline :records="records" variant="mobile" />
      </section>

      <section v-if="canAddRecord" class="detail-card">
        <div class="detail-card__title">添加进度</div>
        <el-input
          v-model.trim="recordForm.content"
          :rows="3"
          type="textarea"
          placeholder="记录处理进展" />
        <el-select
          v-model="recordForm.new_status"
          clearable
          placeholder="同时变更状态（可选）"
          class="detail-field detail-field--gap"
          style="width: 100%;">
          <el-option v-for="item in statusOptions" :key="item" :label="item" :value="item" />
        </el-select>
        <el-button
          :loading="recordSubmitting"
          type="primary"
          class="detail-submit"
          @click="submitRecord">提交进度</el-button>
      </section>

      <section v-else-if="canChangeLockedStatus" class="detail-card">
        <div class="detail-card__title">变更状态</div>
        <p class="detail-card__hint">当前为{{ detail.status }}，无需补充进度；如需重新处理请选择新状态。</p>
        <el-select
          v-model="recordForm.new_status"
          clearable
          placeholder="选择新状态"
          class="detail-field"
          style="width: 100%;">
          <el-option
            v-for="item in reopenStatusOptions"
            :key="item"
            :label="item"
            :value="item" />
        </el-select>
        <el-input
          v-if="recordForm.new_status === '已关闭'"
          v-model.trim="recordForm.content"
          :rows="3"
          type="textarea"
          class="detail-field detail-field--gap"
          placeholder="请填写关闭原因" />
        <el-button
          :loading="recordSubmitting"
          type="primary"
          class="detail-submit"
          @click="submitStatusChange">确认变更</el-button>
      </section>
    </template>
  </div>
</template>

<script>
import { mapGetters } from 'vuex'
import LedgerRecordTimeline from '@/views/crm/ledger/components/LedgerRecordTimeline'
import {
  ledgerReadAPI,
  ledgerRecordListAPI,
  ledgerRecordAddAPI
} from '@/api/ledger/ledger'
import {
  statusTagType,
  statusTagClass,
  stripHtml,
  LEDGER_STATUS_OPTIONS
} from '@/utils/ledgerFormat'
import { isCompletedLedgerStatus, isClosedLedgerStatus } from '@/utils/ledgerCompletion'
import { copyLedgerShareLink } from '@/utils/ledgerLink'

export default {
  name: 'MobileLedgerDetail',
  components: {
    LedgerRecordTimeline
  },
  data() {
    return {
      loading: false,
      recordSubmitting: false,
      detail: {},
      records: [],
      statusOptions: LEDGER_STATUS_OPTIONS,
      recordForm: {
        content: '',
        new_status: ''
      }
    }
  },
  computed: {
    ...mapGetters(['allAuth']),
    ledgerId() {
      return this.$route.params.id
    },
    descriptionText() {
      return stripHtml(this.detail.description || '')
    },
    isRecordLocked() {
      return isCompletedLedgerStatus(this.detail.status) || isClosedLedgerStatus(this.detail.status)
    },
    hasRecordAddAuth() {
      const auth = this.allAuth && this.allAuth.ledger && this.allAuth.ledger.record
      return !!(auth && auth.add)
    },
    canAddRecord() {
      return this.hasRecordAddAuth && !this.isRecordLocked
    },
    canChangeLockedStatus() {
      return this.hasRecordAddAuth && this.isRecordLocked
    },
    reopenStatusOptions() {
      const current = this.detail && this.detail.status
      return this.statusOptions.filter(item => item && item !== current)
    },
    detailCompletedReply() {
      if (!isCompletedLedgerStatus(this.detail.status)) return ''
      if (this.detail.completed_reply) return stripHtml(this.detail.completed_reply)
      const record = this.records.find(item => item && item.new_status === '已完成' && item.content)
      return record ? stripHtml(record.content) : ''
    },
    detailClosedReason() {
      if (!isClosedLedgerStatus(this.detail.status)) return ''
      if (this.detail.closed_reason) return stripHtml(this.detail.closed_reason)
      const record = this.records.find(item => item && item.new_status === '已关闭' && item.content)
      return record ? stripHtml(record.content) : ''
    }
  },
  watch: {
    ledgerId: {
      immediate: true,
      handler() {
        this.loadDetail()
      }
    }
  },
  methods: {
    statusTagType,
    statusTagClass,
    statusBadgeClass(status) {
      const type = statusTagType(status)
      return type ? `status-badge--${type}` : 'status-badge--default'
    },
    resetRecordForm() {
      this.recordForm = { content: '', new_status: '' }
    },
    loadDetail() {
      if (!this.ledgerId) return
      this.loading = true
      Promise.all([
        ledgerReadAPI({ id: this.ledgerId }),
        ledgerRecordListAPI({ ledger_id: this.ledgerId })
      ]).then(([detailRes, recordRes]) => {
        this.detail = detailRes.data || {}
        this.records = recordRes.data || []
        this.resetRecordForm()
      }).finally(() => {
        this.loading = false
      })
    },
    submitRecord() {
      if (!this.recordForm.content) {
        this.$message.warning('请填写进度内容')
        return
      }
      this.submitRecordPayload(this.recordForm.content, this.recordForm.new_status || '')
    },
    submitStatusChange() {
      if (!this.recordForm.new_status) {
        this.$message.warning('请选择要变更的状态')
        return
      }
      if (this.recordForm.new_status === '已关闭' && !this.recordForm.content) {
        this.$message.warning('请填写关闭原因')
        return
      }
      const content = this.recordForm.content.trim() ||
        `状态变更：${this.detail.status} -> ${this.recordForm.new_status}`
      this.submitRecordPayload(content, this.recordForm.new_status)
    },
    submitRecordPayload(content, newStatus) {
      this.recordSubmitting = true
      ledgerRecordAddAPI({
        ledger_id: this.ledgerId,
        content,
        new_status: newStatus || ''
      }).then(() => {
        this.$message.success(newStatus ? '状态已变更' : '已添加进度')
        this.resetRecordForm()
        this.loadDetail()
      }).finally(() => {
        this.recordSubmitting = false
      })
    },
    copyCurrentLedgerLink() {
      if (!this.ledgerId) return
      copyLedgerShareLink(this.ledgerId)
        .then(() => {
          this.$message.success('已复制台账链接')
        })
        .catch(() => {
          this.$message.error('复制失败，请手动复制地址')
        })
    }
  }
}
</script>

<style lang="scss" scoped>
.mobile-ledger-detail {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 0;
  max-width: 100%;
  box-sizing: border-box;
}

.detail-card {
  background: #fff;
  border-radius: 10px;
  padding: 12px;
  max-width: 100%;
  min-width: 0;
  box-sizing: border-box;
}

.detail-card__head {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
  margin-bottom: 8px;
}

.detail-share-button {
  width: 100%;
  height: 34px;
  margin: 4px 0 10px;
  border: 1px solid #dcdfe6;
  border-radius: 6px;
  background: #fff;
  color: #2362fb;
  font-size: 14px;
}

.detail-card__title-text {
  margin: 0;
  width: 100%;
  font-size: 16px;
  line-height: 1.4;
  word-break: break-word;
  overflow-wrap: anywhere;
}

.detail-card__status {
  display: inline-block;
  flex-shrink: 0;
  padding: 2px 8px;
  border-radius: 4px;
  border: 1px solid transparent;
  font-size: 12px;
  line-height: 1.4;
  white-space: nowrap;
}

.detail-card__status.status-tag-release {
  color: #8e44ad;
  background-color: #f4ecf7;
  border-color: #e8daef;
}

.detail-card__status.status-tag-closed {
  color: #909399;
  background-color: #f4f4f5;
  border-color: #e9e9eb;
}

.detail-card__status.status-badge--success {
  color: #67c23a;
  background: #f0f9eb;
  border-color: #e1f3d8;
}

.detail-card__status.status-badge--warning {
  color: #e6a23c;
  background: #fdf6ec;
  border-color: #faecd8;
}

.detail-card__status.status-badge--info {
  color: #909399;
  background: #f4f4f5;
  border-color: #e9e9eb;
}

.detail-card__status.status-badge--danger {
  color: #f56c6c;
  background: #fef0f0;
  border-color: #fde2e2;
}

.detail-card__status.status-badge--default {
  color: #606266;
  background: #fff;
  border-color: #dcdfe6;
}

.detail-card__title {
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 8px;
}

.detail-card__hint {
  margin: 0 0 10px;
  font-size: 12px;
  line-height: 1.5;
  color: #909399;
}

.detail-row {
  display: grid;
  grid-template-columns: 72px minmax(0, 1fr);
  gap: 8px 12px;
  font-size: 13px;
  line-height: 1.8;
  color: #606266;
}

.detail-row span:first-child {
  color: #909399;
}

.detail-row span:last-child {
  min-width: 0;
  word-break: break-word;
  overflow-wrap: anywhere;
}

.detail-block {
  margin-top: 8px;
}

.detail-block__label {
  font-size: 12px;
  color: #909399;
  margin-bottom: 4px;
}

.detail-block__content {
  font-size: 14px;
  color: #303133;
  line-height: 1.6;
  white-space: pre-wrap;
  word-break: break-word;
  overflow-wrap: anywhere;
  max-width: 100%;
}

.detail-field {
  display: block;
  width: 100%;
}

.detail-field--gap {
  margin-top: 8px;
}

.detail-submit {
  width: 100%;
  margin-top: 10px;
}
</style>

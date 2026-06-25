<template>
  <div class="ledger-record-timeline">
    <div v-if="!records.length" class="empty-tip">{{ emptyText }}</div>
    <el-timeline v-else-if="variant === 'desktop'" class="record-timeline">
      <el-timeline-item
        v-for="(item, index) in records"
        :key="item.record_id || item.followup_id || item.id || index"
        :timestamp="formatTimestamp(item.create_time)"
        :color="index === 0 ? '#409EFF' : '#C0C4CC'"
        :class="{ 'is-current': index === 0 }">
        <div class="record-content">
          <div class="record-user">{{ item.create_user_name || '—' }}</div>
          <div class="record-text">{{ item.content || '—' }}</div>
          <div
            v-if="item.old_status && item.new_status && item.old_status != item.new_status"
            class="record-status">
            状态：{{ item.old_status }} → {{ item.new_status }}
          </div>
        </div>
      </el-timeline-item>
    </el-timeline>
    <div v-else class="mobile-timeline">
      <div
        v-for="(item, index) in records"
        :key="item.record_id || item.id || index"
        class="timeline-item">
        <div class="timeline-item__time">{{ formatLedgerDate(item.create_time) }}</div>
        <div class="timeline-item__user">{{ item.create_user_name || '' }}</div>
        <div class="timeline-item__content">{{ item.content }}</div>
        <div v-if="item.new_status" class="timeline-item__status">状态：{{ item.new_status }}</div>
      </div>
    </div>
  </div>
</template>

<script>
import { formatLedgerDate } from '@/utils/ledgerFormat'

export default {
  name: 'LedgerRecordTimeline',
  props: {
    records: {
      type: Array,
      default: () => []
    },
    variant: {
      type: String,
      default: 'desktop'
    },
    emptyText: {
      type: String,
      default: '暂无进度'
    }
  },
  methods: {
    formatLedgerDate,
    formatTimestamp(value) {
      if (!value) return ''
      return String(value).slice(0, 16)
    }
  }
}
</script>

<style lang="scss" scoped>
.ledger-record-timeline {
  min-width: 0;
}

.empty-tip {
  color: #909399;
  font-size: 13px;
}

.record-timeline {
  padding: 2px 0 0 2px;
}

.record-timeline ::v-deep .el-timeline-item {
  padding-bottom: 12px;
}

.record-timeline ::v-deep .el-timeline-item__timestamp {
  font-size: 12px;
  color: #909399;
  line-height: 1.35;
}

.record-timeline ::v-deep .el-timeline-item__node {
  border-color: #dcdfe6;
}

.record-timeline ::v-deep .el-timeline-item.is-current .el-timeline-item__node {
  border-color: #409eff;
}

.record-content {
  padding: 10px 12px;
  border-radius: 8px;
  background: #f7f9fc;
  border: 1px solid #eef2f7;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}

.record-timeline .is-current .record-content {
  background: #f0f7ff;
  border-color: #d9ecff;
}

.record-user {
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 5px;
}

.record-text {
  font-size: 13px;
  line-height: 1.55;
  margin-bottom: 6px;
  white-space: pre-wrap;
}

.record-status {
  font-size: 12px;
  color: #909399;
  background: #f4f6f8;
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
}

.mobile-timeline .timeline-item {
  padding: 8px 0;
  border-bottom: 1px solid #f0f2f5;
  max-width: 100%;
  min-width: 0;
  overflow: hidden;
}

.mobile-timeline .timeline-item:last-child {
  border-bottom: none;
}

.timeline-item__time,
.timeline-item__user {
  font-size: 12px;
  color: #909399;
}

.timeline-item__content {
  font-size: 14px;
  color: #303133;
  margin-top: 4px;
  white-space: pre-wrap;
  word-break: break-word;
  overflow-wrap: anywhere;
  max-width: 100%;
}

.timeline-item__status {
  font-size: 12px;
  color: #409eff;
  margin-top: 4px;
  max-width: 100%;
  word-break: break-word;
}
</style>

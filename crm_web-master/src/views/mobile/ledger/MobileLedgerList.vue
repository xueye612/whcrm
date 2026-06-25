<template>
  <div v-loading="loading" class="mobile-ledger-list">
    <div class="status-chips">
      <button
        v-for="item in statusFilters"
        :key="item.value"
        type="button"
        class="status-chip"
        :class="{ 'is-active': filters.status === item.value }"
        @click="setStatus(item.value)">
        {{ item.label }}
      </button>
    </div>

    <el-input
      v-model.trim="filters.keyword"
      clearable
      placeholder="搜索标题/内容"
      class="mobile-search-input"
      @keyup.enter.native="reload"
      @clear="reload" />

    <div v-if="!loading && list.length === 0" class="empty-tip">暂无台账记录</div>

    <article
      v-for="item in list"
      :key="item.ledger_id || item.id"
      class="ledger-card"
      @click="openDetail(item)">
      <div class="ledger-card__head">
        <div class="ledger-card__title">{{ item.title || '未命名' }}</div>
        <span
          v-if="item.status"
          class="ledger-card__status"
          :class="[statusTagClass(item.status), statusBadgeClass(item.status)]">
          {{ item.status }}
        </span>
      </div>
      <div class="ledger-card__meta">{{ item.customer_name || '-' }} · {{ item.contract_name || item.contract_num || '-' }}</div>
      <div class="ledger-card__meta">处理人：{{ item.handler_user_name || '-' }}</div>
      <div class="ledger-card__time">{{ formatLedgerDate(item.feedback_time || item.register_time) }}</div>
    </article>

    <div v-if="total > list.length" class="load-more">
      <el-button :loading="loadingMore" size="small" @click="loadMore">加载更多</el-button>
    </div>
  </div>
</template>

<script>
import { ledgerIndexAPI } from '@/api/ledger/ledger'
import {
  formatLedgerDate,
  statusTagType,
  statusTagClass,
  LEDGER_STATUS_OPTIONS
} from '@/utils/ledgerFormat'

export default {
  name: 'MobileLedgerList',
  data() {
    return {
      loading: false,
      loadingMore: false,
      list: [],
      page: 1,
      limit: 20,
      total: 0,
      filters: {
        status: '',
        keyword: ''
      },
      statusFilters: [
        { label: '全部', value: '' },
        ...LEDGER_STATUS_OPTIONS.map(item => ({ label: item, value: item }))
      ]
    }
  },
  created() {
    this.reload()
  },
  methods: {
    formatLedgerDate,
    statusTagType,
    statusTagClass,
    statusBadgeClass(status) {
      const type = statusTagType(status)
      return type ? `status-badge--${type}` : 'status-badge--default'
    },
    setStatus(status) {
      this.filters.status = status
      this.reload()
    },
    reload() {
      this.page = 1
      this.list = []
      this.fetchList()
    },
    loadMore() {
      if (this.loading || this.loadingMore) return
      this.page += 1
      this.fetchList(true)
    },
    fetchList(append) {
      const loadingKey = append ? 'loadingMore' : 'loading'
      this[loadingKey] = true
      ledgerIndexAPI({
        page: this.page,
        limit: this.limit,
        status: this.filters.status,
        keyword: this.filters.keyword
      }).then(res => {
        const data = res.data || {}
        const rows = data.list || []
        this.total = Number(data.dataCount || 0)
        this.list = append ? this.list.concat(rows) : rows
      }).finally(() => {
        this[loadingKey] = false
      })
    },
    openDetail(item) {
      const id = item.ledger_id || item.id
      if (!id) return
      this.$router.push(`/m/ledger/${id}`)
    }
  }
}
</script>

<style lang="scss" scoped>
.mobile-ledger-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 0;
  max-width: 100%;
  box-sizing: border-box;
}

.mobile-ledger-list ::v-deep .mobile-search-input {
  display: block;
  max-width: 100%;
}

.mobile-ledger-list ::v-deep .mobile-search-input .el-input__inner {
  max-width: 100%;
  box-sizing: border-box;
  font-size: 16px;
}

.status-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  max-width: 100%;
  box-sizing: border-box;
}

.status-chip {
  border: 1px solid #dcdfe6;
  background: #fff;
  border-radius: 16px;
  padding: 5px 12px;
  font-size: 13px;
  color: #606266;
  max-width: 100%;
  box-sizing: border-box;
}

.status-chip.is-active {
  border-color: #409eff;
  color: #409eff;
  background: #ecf5ff;
}

.ledger-card {
  background: #fff;
  border-radius: 10px;
  padding: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
  max-width: 100%;
  min-width: 0;
  box-sizing: border-box;
}

.ledger-card__head {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
  margin-bottom: 6px;
  min-width: 0;
}

.ledger-card__title {
  width: 100%;
  font-size: 16px;
  font-weight: 600;
  color: #303133;
  line-height: 1.4;
  word-break: break-word;
  overflow-wrap: anywhere;
}

.ledger-card__status {
  display: inline-block;
  flex-shrink: 0;
  padding: 2px 8px;
  border-radius: 4px;
  border: 1px solid transparent;
  font-size: 13px;
  line-height: 1.4;
  white-space: nowrap;
}

.status-badge--success {
  color: #67c23a;
  background: #f0f9eb;
  border-color: #e1f3d8;
}

.status-badge--warning {
  color: #e6a23c;
  background: #fdf6ec;
  border-color: #faecd8;
}

.status-badge--info {
  color: #909399;
  background: #f4f4f5;
  border-color: #e9e9eb;
}

.status-badge--danger {
  color: #f56c6c;
  background: #fef0f0;
  border-color: #fde2e2;
}

.status-badge--default {
  color: #606266;
  background: #fff;
  border-color: #dcdfe6;
}

.ledger-card__status.status-tag-release {
  color: #8e44ad;
  background-color: #f4ecf7;
  border-color: #e8daef;
}

.ledger-card__status.status-tag-closed {
  color: #909399;
  background-color: #f4f4f5;
  border-color: #e9e9eb;
}

.ledger-card__meta,
.ledger-card__time {
  font-size: 13px;
  color: #909399;
  line-height: 1.6;
  max-width: 100%;
  word-break: break-word;
  overflow-wrap: anywhere;
}

.empty-tip,
.load-more {
  text-align: center;
  color: #909399;
  padding: 12px 0;
}
</style>

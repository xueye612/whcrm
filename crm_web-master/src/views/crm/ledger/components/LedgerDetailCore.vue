<template>
  <div class="ledger-detail-core">
    <div class="detail-row"><span>客户</span><span>{{ detail.customer_name || '-' }}</span></div>
    <div class="detail-row"><span>合同</span><span>{{ detail.contract_name || detail.contract_num || '-' }}</span></div>
    <div class="detail-row"><span>处理人</span><span>{{ detail.handler_user_name || '-' }}</span></div>
    <div class="detail-row"><span>状态</span><span>{{ detail.status || '-' }}</span></div>
    <div class="detail-block">
      <div class="detail-block__label">问题内容</div>
      <div class="detail-block__content">{{ descriptionText }}</div>
    </div>
  </div>
</template>

<script>
import { stripHtml } from '@/utils/ledgerFormat'

export default {
  name: 'LedgerDetailCore',
  props: {
    detail: {
      type: Object,
      default: () => ({})
    }
  },
  computed: {
    descriptionText() {
      return stripHtml(this.detail.description || '')
    }
  }
}
</script>

<style lang="scss" scoped>
.detail-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  font-size: 13px;
  line-height: 1.8;
  color: #606266;
}

.detail-block__label {
  font-size: 12px;
  color: #909399;
  margin-bottom: 4px;
}

.detail-block__content {
  white-space: pre-wrap;
}
</style>

<template>
  <div class="relative-finance">
    <finance-record-panel
      v-if="canFinanceRecord && (panelCustomerId || panelBusinessId || panelContractId)"
      :customer-id="panelCustomerId"
      :business-id="panelBusinessId"
      :contract-id="panelContractId"
      :disable-default-date-range="true"
      :hide-customer-filter="true"
      :customer-detail="detail" />
    <div v-else class="empty-state">
      <p v-if="!canFinanceRecord">暂无权限查看收支流水</p>
      <p v-else>缺少关联对象ID，无法展示收支</p>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex'
import FinanceRecordPanel from '@/views/finance/Record'

export default {
  name: 'RelativeFinance',
  components: {
    FinanceRecordPanel
  },
  props: {
    id: {
      type: [String, Number],
      default: ''
    },
    crmType: {
      type: String,
      default: ''
    },
    detail: {
      type: Object,
      default: () => ({})
    },
    businessId: {
      type: [String, Number],
      default: ''
    },
    contractId: {
      type: [String, Number],
      default: ''
    }
  },
  computed: {
    ...mapGetters(['allAuth']),
    customerId() {
      return this.detail && (this.detail.customer_id || this.detail.customerId || '')
    },
    panelBusinessId() {
      const currentId = this.id || ''
      return this.businessId || (this.detail && (this.detail.business_id || this.detail.businessId || '')) || (this.crmType === 'business' ? currentId : '')
    },
    panelContractId() {
      const currentId = this.id || ''
      return this.contractId || (this.detail && (this.detail.contract_id || this.detail.contractId || '')) || (this.crmType === 'contract' ? currentId : '')
    },
    panelCustomerId() {
      // 商机/合同场景优先关联当前对象，避免保存时被强制改为客户关联
      if (this.panelBusinessId || this.panelContractId) return ''
      return this.customerId
    },
    financeRecordAuth() {
      const finance = (this.allAuth && this.allAuth.finance) || {}
      return (finance.record && finance.record.index) || false
    },
    canFinanceRecord() {
      return !!this.financeRecordAuth
    }
  }
}
</script>

<style lang="scss" scoped>
.relative-finance {
  padding: 0;
  height: 100%;
  overflow: auto;
  .empty-state {
    padding: 24px;
    color: #606266;
    font-size: 14px;
    text-align: center;
  }
}
</style>

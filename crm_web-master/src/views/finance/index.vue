<template>
  <div class="finance-page">
    <el-tabs v-model="activeTab" class="finance-tabs">
      <el-tab-pane label="收支类型" name="type">
        <finance-type-panel />
      </el-tab-pane>
      <el-tab-pane label="收支流水" name="record">
        <finance-record-panel />
      </el-tab-pane>
      <el-tab-pane label="收支计划" name="plan">
        <finance-plan-panel />
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script>
import FinanceTypePanel from './Type'
import FinanceRecordPanel from './Record'
import FinancePlanPanel from './Plan'

export default {
  name: 'Finance',
  components: {
    FinanceTypePanel,
    FinanceRecordPanel,
    FinancePlanPanel
  },
  data() {
    return {
      activeTab: 'type'
    }
  }
}
</script>

<style lang="scss" scoped>
.finance-page {
  padding: 0;

  .finance-tabs {
    background: transparent;
  }

  ::v-deep .el-tabs__header {
    margin: 0 0 8px;
    padding: 0 12px;
    background: #fff;
    border-radius: 8px;
    border: 1px solid #ebeef5;
  }

  ::v-deep .el-tabs__content {
    padding: 0;
  }

  .tab-operations {
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
  }
}

.dialog-footer {
  text-align: right;
}

@media (max-width: 768px) {
  .finance-page {
    ::v-deep .el-tabs__header {
      padding: 0 8px;
    }
  }
}
</style>

<template>
  <div class="finance-record-page">
    <finance-record-panel />
  </div>
</template>

<script>
import FinanceRecordPanel from './Record'

export default {
  name: 'FinanceRecordPage',
  components: {
    FinanceRecordPanel
  }
}
</script>

<style scoped>
.finance-record-page {
  padding: 0;
}
</style>

<template>
  <div
    v-empty="!canRead"
    xs-empty-icon="nopermission"
    xs-empty-text="暂无权限"
    class="finance-type-page">
    <el-card class="box-card" shadow="never">
      <finance-type-panel />
    </el-card>
  </div>
</template>

<script>
import FinanceTypePanel from './Type'

export default {
  name: 'FinanceTypePage',
  components: {
    FinanceTypePanel
  },
  computed: {
    typeAuth() {
      const allAuth = this.$store.getters.allAuth || {}
      return (allAuth.finance && allAuth.finance.type) || {}
    },
    canRead() {
      return !!this.typeAuth.index
    }
  }
}
</script>

<style scoped>
.finance-type-page {
  padding: 16px;
}
</style>

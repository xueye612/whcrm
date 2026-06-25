<template>
  <div v-show="visible" class="ledger-filter-bar">
    <slot />
  </div>
</template>

<script>
export default {
  name: 'LedgerFilterBar',
  props: {
    visible: {
      type: Boolean,
      default: true
    }
  }
}
</script>

<style lang="scss" scoped>
.ledger-filter-bar {
  display: block;
}
</style>

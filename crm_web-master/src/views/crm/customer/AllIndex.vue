<template>
  <keep-alive>
    <component
      :is="componentName"
      @menu-select="menuSelect" />
  </keep-alive>
</template>

<script>
import CustomerIndex from './index'
import SeasIndex from '../seas'

import { mapGetters } from 'vuex'
export default {
  name: 'CustomerAllIndex',
  components: {
    CustomerIndex,
    SeasIndex
  },
  props: {},
  data() {
    return {
      componentName: ''
    }
  },
  computed: {
    ...mapGetters(['crm'])
  },
  watch: {},
  mounted() {
    if (this.crm && this.crm.customer) {
      this.componentName = 'CustomerIndex'
    } else if (this.crm && this.crm.customer.pool) {
      this.componentName = 'SeasIndex'
    }
  },

  beforeDestroy() {},
  methods: {
    /**
     * 左侧菜单选择
     */
    menuSelect(key, keyPath) {
      this.componentName = {
        customer: 'CustomerIndex',
        seas: 'SeasIndex'
      }[key]
    }
  }
}
</script>

<style lang="scss" scoped>
</style>

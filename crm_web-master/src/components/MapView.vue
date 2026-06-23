<template>
  <div class="map-view">
    <div class="map-placeholder">
      地图功能已停用
    </div>
    <i class="el-icon-close map-close" @click="hiddenView" />
  </div>
</template>

<script type="text/javascript">
import { getMaxIndex } from '@/utils'

export default {
  name: 'MapView',
  props: {
    title: {
      type: String,
      default: ''
    },
    lat: {
      type: [String, Number],
      default: 0
    },
    lng: {
      type: [String, Number],
      default: 0
    }
  },
  mounted() {
    this.$el.style.zIndex = getMaxIndex()
    document.body.appendChild(this.$el)
  },
  destroyed() {
    if (this.$el && this.$el.parentNode) {
      this.$el.parentNode.removeChild(this.$el)
    }
  },
  methods: {
    hiddenView() {
      this.$emit('hidden')
    }
  }
}
</script>

<style lang="scss" scoped>
.map-view {
  position: fixed;
  width: 100%;
  height: 100%;
  top: 0;
  left: 0;
  background-color: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
}

.map-placeholder {
  background: #fff;
  padding: 24px 32px;
  border-radius: 8px;
  font-size: 14px;
  color: #606266;
}

.map-close {
  position: absolute;
  right: 10px;
  top: 10px;
  font-size: 28px;
  color: #fff;
  cursor: pointer;
}
</style>

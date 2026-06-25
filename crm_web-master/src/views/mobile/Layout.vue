<template>
  <div
    class="mobile-layout"
    :class="{
      'mobile-layout--form': isQuickPage,
      'mobile-layout--with-tabbar': showTabbar
    }">
    <div class="mobile-layout__inner">
      <header class="mobile-layout__header">
        <button
          v-if="showBack"
          type="button"
          class="mobile-layout__back"
          @click="goBack">‹</button>
        <div class="mobile-layout__title">{{ pageTitle }}</div>
        <router-link
          v-if="showQuickLink"
          class="mobile-layout__action"
          to="/m/ledger/quick">记一条</router-link>
      </header>
      <main class="mobile-layout__body">
        <router-view />
      </main>
      <nav v-if="showTabbar" class="mobile-layout__tabbar">
        <router-link to="/m/ledger" class="mobile-layout__tab" active-class="is-active">台账</router-link>
        <router-link to="/m/ledger/quick" class="mobile-layout__tab mobile-layout__tab--primary" active-class="is-active">录入</router-link>
        <router-link to="/crm/ledger?desktop=1" class="mobile-layout__tab">桌面版</router-link>
      </nav>
    </div>
  </div>
</template>

<script>
import { bindMobileViewport } from '@/utils/mobileViewport'

export default {
  name: 'MobileLayout',
  mounted() {
    this.unbindViewport = bindMobileViewport(this.$el)
  },
  beforeDestroy() {
    if (this.unbindViewport) {
      this.unbindViewport()
      this.unbindViewport = null
    }
  },
  computed: {
    pageTitle() {
      return (this.$route.meta && this.$route.meta.title) || '移动台账'
    },
    showBack() {
      return this.$route.name !== 'mobile-ledger-list'
    },
    showQuickLink() {
      return this.$route.name === 'mobile-ledger-list'
    },
    isQuickPage() {
      return this.$route.name === 'mobile-ledger-quick'
    },
    showTabbar() {
      return !this.isQuickPage
    }
  },
  methods: {
    goBack() {
      if (window.history.length > 1) {
        this.$router.back()
      } else {
        this.$router.replace('/m/ledger')
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.mobile-layout {
  position: fixed;
  z-index: 1;
  top: 0;
  left: 0;
  width: 100%;
  max-width: var(--mvw, 100%);
  height: var(--mvh, 100%);
  box-sizing: border-box;
  overflow: hidden;
  padding-top: env(safe-area-inset-top, 0px);
  background: #f3f4f6;
  font-size: 16px;
}

.mobile-layout__inner {
  position: relative;
  display: flex;
  flex-direction: column;
  box-sizing: border-box;
  height: 100%;
  min-width: 0;
  margin-left: 12px;
  margin-right: 12px;
  overflow: hidden;
}

.mobile-layout__header {
  flex: 0 0 auto;
  z-index: 20;
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  min-height: 48px;
  box-sizing: border-box;
  background: #fff;
  border-bottom: 1px solid #e8eaed;
}

.mobile-layout__back {
  flex-shrink: 0;
  border: none;
  background: transparent;
  font-size: 28px;
  line-height: 1;
  color: #303133;
  padding: 0 4px;
}

.mobile-layout__title {
  flex: 1 1 auto;
  min-width: 0;
  font-size: 16px;
  font-weight: 600;
  color: #303133;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.mobile-layout__action {
  flex-shrink: 0;
  font-size: 14px;
  color: #409eff;
  text-decoration: none;
  white-space: nowrap;
}

.mobile-layout__body {
  flex: 1 1 auto;
  min-width: 0;
  min-height: 0;
  box-sizing: border-box;
  padding-top: 10px;
  padding-bottom: 16px;
  overflow-x: hidden;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  overscroll-behavior: contain;
  touch-action: pan-y;
}

.mobile-layout--with-tabbar .mobile-layout__body {
  padding-bottom: calc(16px + 48px + env(safe-area-inset-bottom, 0px));
}

.mobile-layout--form .mobile-layout__body {
  display: flex;
  flex-direction: column;
  padding-top: 10px;
  padding-bottom: 0;
  overflow: hidden;
}

.mobile-layout__tabbar {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 30;
  display: flex;
  align-items: stretch;
  box-sizing: border-box;
  min-height: 48px;
  padding-bottom: env(safe-area-inset-bottom, 0px);
  background: #fff;
  border-top: 1px solid #e8eaed;
  box-shadow: 0 -2px 8px rgba(15, 23, 42, 0.04);
}

.mobile-layout__tab {
  flex: 1 1 0%;
  min-width: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 8px 4px;
  font-size: 14px;
  line-height: 1.2;
  color: #606266;
  text-decoration: none;
  text-align: center;
  white-space: nowrap;
}

.mobile-layout__tab.is-active {
  color: #409eff;
  font-weight: 600;
}

.mobile-layout__tab--primary {
  color: #409eff;
}
</style>

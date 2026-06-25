<template>
  <div id="app">
    <router-view class="router-view" />
    <vue-picture-viewer
      v-if="showPreviewImg"
      :img-data="previewImgs"
      :select-index="previewIndex"
      @close-viewer="showPreviewImg=false"/>
    <xr-import
      v-if="showFixImport"
      :process-status="crmImportStatus"
      @click.native="fixImportClick"/>
    <c-r-m-import
      :show.sync="showCRMImport"
      :crm-type="crmType"
      :props="crmProps"
      :cache-show.sync="cacheShow"
      :cache-done="cacheDone"
      @status="crmImportChange"
      @close="crmImportClose"/>
    <xr-upgrade-dialog v-if="upgradeDialogShow" :visible.sync="upgradeDialogShow" />
  </div>
</template>

<script>
/** 常用图片预览创建组件 */
import VuePictureViewer from '@/components/VuePictureViewer/index'
import XrImport from '@/components/XrImport'
import XrImportMixins from '@/components/XrImport/XrImportMixins'
import XrUpgradeDialog from '@/components/XrUpgradeDialog'
import CRMImport from '@/components/CRMImport'
import { mapGetters } from 'vuex'
import cache from '@/utils/cache'
import { syncMobileViewport } from '@/utils/mobileViewport'


export default {
  name: 'App',
  components: {
    VuePictureViewer,
    XrImport,
    CRMImport,
    XrUpgradeDialog
  },
  mixins: [XrImportMixins],
  data() {
    return {
      showPreviewImg: false,
      previewIndex: 0,
      previewImgs: [],
      upgradeDialogShow: false
    }
  },
  computed: {
    ...mapGetters(['activeIndex', 'addRouters', 'userInfo'])
  },
  watch: {
    $route: {
      immediate: true,
      handler(to) {
        this.showPreviewImg = false
        this.toggleMobileRouteClass(to)
      }
    },
    addRouters() {
      if (this.userInfo && this.userInfo.is_read_notice != 1) {
        setTimeout(() => {
          this.upgradeDialogShow = true
        }, 5000)
      }
    }
  },
  mounted() {
    this.addBus()
    this.addDocumentVisibilityChange()
    this.setMinHeight()
    this.toggleMobileRouteClass(this.$route)
  },
  methods: {
    toggleMobileRouteClass(route) {
      const isMobileRoute = !!(route && route.path && route.path.startsWith('/m'))
      const app = document.getElementById('app')
      if (app) {
        app.classList.toggle('is-mobile-route', isMobileRoute)
      }
      document.body.classList.toggle('is-mobile-route', isMobileRoute)
      document.documentElement.classList.toggle('is-mobile-route', isMobileRoute)
      if (isMobileRoute) {
        if (app) {
          app.style.minWidth = ''
          app.style.minHeight = ''
        }
        this.$nextTick(() => syncMobileViewport())
      } else {
        this.setMinHeight()
      }
    },
    addDocumentVisibilityChange() {
      // 网页当前状态判断
      // hidden,
      var state, visibilityChange
      if (typeof document.hidden !== 'undefined') {
        // hidden = 'hidden'
        visibilityChange = 'visibilitychange'
        state = 'visibilityState'
      } else if (typeof document.mozHidden !== 'undefined') {
        // hidden = 'mozHidden'
        visibilityChange = 'mozvisibilitychange'
        state = 'mozVisibilityState'
      } else if (typeof document.msHidden !== 'undefined') {
        // hidden = 'msHidden'
        visibilityChange = 'msvisibilitychange'
        state = 'msVisibilityState'
      } else if (typeof document.webkitHidden !== 'undefined') {
        // hidden = 'webkitHidden'
        visibilityChange = 'webkitvisibilitychange'
        state = 'webkitVisibilityState'
      }
      // 添加监听器，在title里显示状态变化
      document.addEventListener(visibilityChange, () => {
        if (document[state] == 'visible') {
          cache.updateAxiosHeaders()
        }
        this.$bus.emit('document-visibility', document[state])
      }, false)
    },

    addBus() {
      var self = this
      this.$bus.on('preview-image-bus', function(data) {
        self.previewIndex = data.index
        self.previewImgs = data.data
        self.showPreviewImg = true
      })
    },

    setMinHeight() {
      this.$nextTick(() => {
        if (this.$route && this.$route.path && this.$route.path.startsWith('/m')) {
          return
        }
        const dpr = window.devicePixelRatio || 1
        const clientWidth = document.body.clientWidth
        const dom = document.getElementById('app')
        if (dpr !== 1 && clientWidth > 1600) {
          dom.style.minHeight = '800px'
        } else if (dpr === 1 && clientWidth > 1600) {
          dom.style.minWidth = '1650px'
        } else {
          // dom.style.minWidth = '1200px'
          dom.style.minHeight = '605px'
        }
      })
    }
  }
}
</script>

<style>
#app {
  width: 100%;
  position: relative;
  height: 100%;
  min-height: 605px;
}

@media (min-width: 769px) {
  #app {
    min-width: 1200px;
  }
}

#app.is-mobile-route {
  min-width: 0 !important;
  min-height: 0 !important;
  width: 100%;
  max-width: 100%;
  height: 100%;
  overflow: hidden;
}

#app.is-mobile-login {
  min-width: 0;
  min-height: 0;
  overflow-x: hidden;
}

body.is-mobile-route {
  min-width: 0;
}

#app.is-mobile-route > .router-view {
  display: block;
  width: 100%;
  max-width: 100%;
  min-width: 0;
  height: 100%;
  overflow: hidden;
}
</style>

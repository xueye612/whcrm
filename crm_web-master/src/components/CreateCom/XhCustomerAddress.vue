<template>
  <flexbox align="stretch">
    <flexbox-item style="margin-right: 50px;">
      <div class="area-title">定位</div>
      <el-input
        v-model="searchInput"
        placeholder="请输入位置名称"
        @input="valueChange" />
      <div class="area-title">详细地址</div>
      <el-input
        v-model="detail_address"
        placeholder=""
        @input="valueChange" />
    </flexbox-item>
    <flexbox-item>
      <div class="area-title">省/市/区</div>
      <v-distpicker
        :province="addressSelect.province"
        :city="addressSelect.city"
        :area="addressSelect.area"
        @province="selectProvince"
        @city="selectCity"
        @area="selectArea" />
    </flexbox-item>
  </flexbox>
</template>
<script type="text/javascript">
import VDistpicker from '@/components/VDistpicker'

export default {
  name: 'XhCustomerAddress',
  components: {
    VDistpicker
  },
  props: {
    value: {
      type: Object,
      default: () => {
        return {}
      }
    },
    index: Number,
    item: Object
  },
  data() {
    return {
      searchInput: '',
      detail_address: '',
      addressSelect: {
        province: '',
        city: '',
        area: ''
      }
    }
  },
  watch: {
    value: {
      handler(newValue) {
        this.syncFromValue(newValue)
      },
      deep: true,
      immediate: true
    }
  },
  methods: {
    syncFromValue(val) {
      const next = val || {}
      this.searchInput = next.location || ''
      this.detail_address = next.detail_address || ''
      if (Array.isArray(next.address)) {
        this.addressSelect = {
          province: next.address[0] || '',
          city: next.address[1] || '',
          area: next.address[2] || ''
        }
      } else {
        this.addressSelect = {
          province: next.province || '',
          city: next.city || '',
          area: next.area || ''
        }
      }
    },
    selectProvince(value) {
      this.addressSelect.province = value.value
      this.valueChange()
    },
    selectCity(value) {
      this.addressSelect.city = value.value
      this.valueChange()
    },
    selectArea(value) {
      this.addressSelect.area = value.value
      this.valueChange()
    },
    valueChange() {
      this.$emit('value-change', {
        index: this.index,
        value: {
          address: [
            this.addressSelect.province,
            this.addressSelect.city,
            this.addressSelect.area
          ],
          location: this.searchInput,
          detail_address: this.detail_address,
          lat: '',
          lng: ''
        }
      })
    }
  }
}
</script>

<style rel="stylesheet/scss" lang="scss" scoped>
.area-title {
  font-size: 12px;
  color: #aaa;
  padding-left: 10px;
  margin: 8px 0 6px;
}
</style>

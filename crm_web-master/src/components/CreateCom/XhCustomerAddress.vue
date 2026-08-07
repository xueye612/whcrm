<template>
  <flexbox class="customer-address" align="flex-start">
    <flexbox-item class="customer-address__detail">
      <div class="area-title">详细地址</div>
      <el-input
        v-model="detail_address"
        placeholder="请输入详细地址"
        @input="detailAddressChange" />
    </flexbox-item>
    <flexbox-item class="customer-address__region">
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
import DISTRICTS from '@/components/VDistpicker/districts'

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
      legacyLocation: '',
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
      this.legacyLocation = next.location || ''
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
    detailAddressChange(value) {
      const region = this.findRegion(value)
      if (region) {
        this.addressSelect = region
      }
      this.valueChange()
    },
    findRegion(address) {
      const text = String(address || '').replace(/\s+/g, '')
      if (!text) return null

      let best = null
      DISTRICTS.forEach(province => {
        const provinceMatched = this.areaNameMatched(text, province.name, 'province')
        const cities = province.children || []
        cities.forEach(city => {
          const cityMatched = this.areaNameMatched(text, city.name, 'city')
          const areas = city.children || []
          areas.forEach(area => {
            const areaMatched = this.areaNameMatched(text, area.name, 'area')
            const score = (provinceMatched ? 4 : 0) + (cityMatched ? 6 : 0) + (areaMatched ? 8 : 0)
            if (areaMatched && (provinceMatched || cityMatched) && (!best || score > best.score)) {
              best = {
                score,
                province: province.name,
                city: city.name,
                area: area.name
              }
            }
          })
        })
      })

      if (!best) return null
      return {
        province: best.province,
        city: best.city,
        area: best.area
      }
    },
    areaNameMatched(address, name, level) {
      if (address.includes(name)) return true
      const suffixes = {
        province: /(特别行政区|维吾尔自治区|壮族自治区|回族自治区|自治区|省|市)$/,
        city: /(自治州|地区|盟|市)$/,
        area: /(自治县|自治旗|矿区|林区|新区|区|县|市|旗)$/
      }
      const shortName = name.replace(suffixes[level], '')
      return shortName.length >= 2 && address.includes(shortName)
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
          // 地图定位功能已下线，编辑地址时保留历史值，避免无意清空旧数据。
          location: this.legacyLocation,
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
  margin: 0 0 6px;
  color: #8a91a8;
  font-size: 12px;
  line-height: 18px;
}

.customer-address {
  width: 100%;

  &__detail {
    margin-right: 24px;
  }
}
</style>

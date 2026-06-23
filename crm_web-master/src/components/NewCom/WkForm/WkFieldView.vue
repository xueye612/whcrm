<template>
  <div
    :class="`is-${form_type}`"
    class="wk-field-view"
  >
    <template v-if="ignoreFields.includes(props.field)">
      <slot :data="$props" />
    </template>
    <span v-else-if="isCommonType">{{ getCommonShowValue() }}</span>
    <el-switch
      v-else-if="form_type == 'boolean_value'"
      :value="value"
      disabled
      active-value="1"
      inactive-value="0"
    />
    <wk-signature-image
      v-else-if="form_type == 'handwriting_sign'"
      :src=" !!value ? value.url:''"
      :height="config.signatureHeight"
    />
    <wk-desc-text
      v-else-if="form_type == 'desc_text'"
      :key="Date.now().toString()"
      :value="value"
    />
    <span v-else-if="form_type == 'location'">
      {{ objectHasValue(value, 'address') ? value.address : '--' }}
    </span>
    <span
      v-else-if="form_type == 'website'"
      :class="{'can-check': !isEmpty}"
      @click.stop="openUrl(value)"
    >{{ value }}</span>
    <file-list-view
      v-else-if="form_type == 'file'"
      :list="value || []"
    />
    <wk-detail-table-view
      v-else-if="form_type == 'detail_table'"
      :show-type="props.precisions === 2 ? 'table' : 'default'"
      :title="props.name"
      :add-field-list="props.fieldExtendList"
      :field-form="value"
      :field-list="props.fieldList"
    >
      <template slot-scope="{ data }">
        <slot :data="data" />
      </template>
    </wk-detail-table-view>
    <template v-else>
      <slot :data="$props" />
    </template>
  </div>
</template>

<script>
import WkSignatureImage from '@/components/NewCom/WkSignaturePad/Image'
import WkDescText from '@/components/NewCom/WkDescText'
import FileListView from '@/components/FileListView' // 闄勪欢
import WkDetailTableView from '@/components/NewCom/WkDetailTable/View'

import merge from '@/utils/merge'
import { isObject, isEmpty } from '@/utils/types'
import { getFormFieldShowName } from './utils'

const DefaultWkFieldView = {
  signatureHeight: '26px'
}

export default {
  // 鐗规畩瀛楁靛睍绀
  name: 'WkFieldView',

  components: {
    WkSignatureImage,
    WkDescText,
    FileListView,
    WkDetailTableView
  },

  props: {
    props: Object,
    form_type: String,
    value: [String, Object, Array, Number],
    ignoreFields: {
      type: Array,
      default: () => {
        return []
      }
    }
  },
  data() {
    return {}
  },


  computed: {
    config() {
      return merge({ ...DefaultWkFieldView }, this.props || {})
    },
    isEmpty() {
      return isEmpty(this.value)
    },
    isCommonType() {
      return [
        'text',
        'textarea',
        'website',
        'select',
        'checkbox',
        'number',
        'floatnumber',
        'percent',
        'mobile',
        'email',
        'date',
        'datetime',
        'date_interval',
        'user',
        'structure',
        'position'
      ].includes(this.form_type)
    }
  },

  watch: {},

  created() {},

  mounted() {},

  beforeDestroy() {},

  methods: {
    /**
		 * 鍒ゆ柇瀵硅薄鏄鍚﹀?		 */
    objectHasValue(obj, key) {
      if (isObject(obj)) {
        return !isEmpty(obj[key])
      }
      return false
    },

    openUrl(url) {
      if (!url.match(/^https?:\/\//i)) {
        url = 'http://' + url
      }
      window.open(url)
    },

    /**
     * 鑾峰彇绫诲瀷鐨勫睍绀哄?     */
    getCommonShowValue() {
      return getFormFieldShowName(this.form_type, this.value, '', this.props)
    }
  }
}
</script>

<style lang="scss" scoped>
.wk-field-view {
  overflow: hidden;
  text-overflow: ellipsis;
	.can-check {
		color: $xr-color-primary;
		cursor: pointer;
	}

	&.is-website {
		display: inline;
	}
}
</style>


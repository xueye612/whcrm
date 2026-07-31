<template>
  <xr-create
    :loading="loading"
    :title="title"
    @close="close"
    @save="saveClick">
    <create-sections title="基本信息">
      <el-form
        ref="crmForm"
        :model="fieldForm"
        :rules="fieldRules"
        :validate-on-rule-change="false"
        class="wk-form"
        label-position="top"
      >
        <wk-form-items
          v-for="(children, index) in fieldList"
          :key="index"
          :field-from="fieldForm"
          :field-list="children"
          @change="formChange"
        >
          <template slot-scope="{ data }">
            <crm-relative-cell
              v-if="data && data.form_type == 'customer'"
              :value="fieldForm[data.field]"
              :disabled="data.disabled"
              relative-type="customer"
              @value-change="otherChange($event, data)"
            />
            <xh-business-status
              v-if="data && data.form_type == 'business_type'"
              :value="fieldForm[data.field]"
              :options="data.setting"
              @value-change="otherChange($event, data)"
            />
            <xh-product
              v-if="data && data.form_type == 'product'"
              :value="fieldForm[data.field]"
              @value-change="otherChange($event, data)"
            />
            <el-select
              v-if="data && data.form_type == 'business_status'"
              :disabled="data.disabled"
              v-model="fieldForm[data.field]"
              style="width: 100%;">
              <el-option
                v-for="(item, index) in data.setting"
                :key="index"
                :label="item.name"
                :value="item.status_id"/>
            </el-select>
          </template>
        </wk-form-items>
      </el-form>
    </create-sections>
    <create-sections title="签署信息">
      <el-form label-position="top" class="wk-form">
        <el-form-item label="签约代理商（可选）">
          <el-select v-model="extForm.dealer_customer_id" :remote-method="searchDealer" :loading="dealerLoading" filterable remote reserve-keyword clearable placeholder="不选择代理商即为直签；选择后即为代理签约" style="width:100%" @focus="searchDealer('')">
            <el-option v-for="d in dealerOptions" :key="d.customer_id" :label="d.name" :value="d.customer_id"/>
          </el-select>
        </el-form-item>
        <div class="signing-tip">{{ extForm.dealer_customer_id ? '代理签约' : '直签' }}</div>
      </el-form>
    </create-sections>
  </xr-create>
</template>

<script>
import { filedGetFieldAPI } from '@/api/crm/common'
import { crmBusinessSaveAPI, crmBusinessReadAPI } from '@/api/crm/business'

import XrCreate from '@/components/XrCreate'
import CreateSections from '@/components/CreateSections'
import WkFormItems from '@/components/NewCom/WkForm/WkFormItems'

import {
  XhBusinessStatus,
  XhProduct,
  CrmRelativeCell
} from '@/components/CreateCom'

import CustomFieldsMixin from '@/mixins/CustomFields'
import { isEmpty } from '@/utils/types'
import request from '@/utils/request'

// 兼容旧缓存可能短期再次显示的 crm_rianjp / dealer_customer_id 字段
// 即使后端字段配置缓存未及时刷新，前端也强制隐藏，最终只显示一个中文"所属经销商"
// business_category / signing_method 由代理商选择自动推导，不需要用户填写
// dealer_customer_id 由签署信息区 extForm 管理，不使用 admin_field 重复字段
// business_type（商机组）和 business_status（阶段）选择器正常显示，由用户选择
// 废弃、重复的 business_status_id 字段不恢复；真实 status_id 字段恢复使用
const HIDDEN_LEGACY_FIELDS = [
  'crm_rianjp',
  'dealer_customer_id',
  'business_category',
  'signing_method',
  'signing_method_label',
  'signing_type',
  'signingtype',
  'business_status_id'
]
// business_type（商机组选择器）和 business_status（阶段选择器）正常显示
const HIDDEN_FORM_TYPES = []

export default {
  // 新建编辑
  name: 'BusinessCreate',

  components: {
    XrCreate,
    CreateSections,
    CrmRelativeCell,
    XhBusinessStatus,
    XhProduct,
    WkFormItems
  },

  mixins: [CustomFieldsMixin],

  props: {
    action: {
      type: Object,
      default: () => {
        return {
          type: 'save',
          id: '',
          data: {}
        }
      }
    }
  },

  data() {
    return {
      loading: false,
      baseFields: [],
      fieldList: [],
      fieldForm: {},
      fieldRules: {},
      extForm: {
        dealer_customer_id: ''
      },
      dealerOptions: [],
      dealerLoading: false,
      dealerName: ''
    }
  },

  computed: {
    title() {
      return this.action.type === 'update' ? '编辑商机' : '新建商机'
    }
  },

  watch: {},

  created() {
    this.getField()
  },

  mounted() {},

  beforeDestroy() {},

  methods: {
    /**
     * 获取数据
     */
    getField() {
      this.loading = true
      const params = {
        types: 'crm_business',
        module: 'crm',
        controller: 'business',
        action: this.action.type,
        format: 2
      }

      if (this.action.type == 'update') {
        params.action_id = this.action.id
      }

      filedGetFieldAPI(params)
        .then(res => {
          const list = res.data || []
          const assistIds = this.getFormAssistIds(list)

          const baseFields = []
          const fieldList = []
          const fieldRules = {}
          const fieldForm = {}
          list.forEach(children => {
            const fields = []
            children.forEach(item => {
              // 强制跳过废弃/重复字段：必须 return，不能只设 item.show
              // 否则后续 temp.show = !assistIds.includes(...) 会覆盖
              if (HIDDEN_LEGACY_FIELDS.indexOf(item.field) >= 0) {
                return
              }
              // HIDDEN_FORM_TYPES 默认为空，商机组/阶段选择器正常显示
              if (HIDDEN_FORM_TYPES.indexOf(item.form_type) >= 0) {
                return
              }
              // 按 name/label 识别签约方式等字段
              var fieldName = (item.name || '') + (item.label || '')
              if (fieldName.indexOf('签约方式') >= 0 || fieldName.indexOf('签署方式') >= 0) {
                return
              }

              const temp = this.getFormItemDefaultProperty(item)
              temp.show = !assistIds.includes(item.formAssistId)

              const canEdit = this.getItemIsCanEdit(item, this.action.type)
              if (temp.show && canEdit) {
                fieldRules[temp.field] = this.getRules(item)
              }
              temp.disabled = !canEdit

              if (temp.form_type == 'customer') {
                if (this.action.type == 'relative') {
                  const relativeDisInfos = {
                    customer: { customer: true },
                    contacts: { customer: true }
                  }
                  const relativeTypeDisInfos = relativeDisInfos[this.action.crmType]
                  if (relativeTypeDisInfos) {
                    temp.disabled = relativeTypeDisInfos[item.form_type] || false
                  }
                }
              }

              this.getItemRadio(item, temp)

              if (item.form_type === 'business_status') {
                temp.disabled = this.action.type === 'update'
              }

              if (temp.show) {
                fieldForm[temp.field] = this.getItemValue(item, this.action.data, this.action.type)
              }
              fields.push(temp)
              baseFields.push(item)
            })
            fieldList.push(fields)
          })

          this.baseFields = baseFields
          this.fieldList = fieldList
          this.fieldForm = fieldForm
          this.fieldRules = fieldRules

          // 编辑模式：必须读取正式详情恢复扩展字段和经销商名称，
          // 不能只依赖 action.data（可能为缓存或简化结构）
          if (this.action.type === 'update' && this.action.id) {
            this.loadBusinessDetail()
          } else if (this.action.type === 'update' && this.action.data) {
            // 退路：若无 read 接口可用，仍可从 action.data 恢复
            this.extForm.dealer_customer_id = this.action.data.dealer_customer_id || ''
            this.dealerName = this.action.data.dealer_customer_name || ''
          }

          this.loading = false
        })
        .catch((e) => {
          console.log(e)
          this.loading = false
        })
    },

    /**
     * 编辑时读取正式详情，恢复扩展字段和经销商名称
     */
    loadBusinessDetail() {
      crmBusinessReadAPI({ id: this.action.id })
        .then(res => {
          const d = res.data || {}
          this.extForm.dealer_customer_id = d.dealer_customer_id || ''
          this.dealerName = d.dealer_customer_name || ''
          // 若已有经销商 ID，提前预置经销商名进入下拉，避免显示数字 ID
          if (this.extForm.dealer_customer_id) {
            this.dealerOptions = [{
              customer_id: this.extForm.dealer_customer_id,
              name: this.dealerName || ('客户#' + this.extForm.dealer_customer_id)
            }]
          }
        })
        .catch(() => {})
    },

    /**
     * 保存
     */
    saveClick() {
      this.loading = true
      const crmForm = this.$refs.crmForm

      crmForm.validate(valid => {
        if (valid) {
          const params = this.getSubmiteParams(this.baseFields, this.fieldForm)
          if (this.action.type === 'update') {
            params.id = this.action.id
          }
          this.submiteParams(params)
        } else {
          this.loading = false
          this.getFormErrorMessage(crmForm)
          return false
        }
      })
    },

    /**
     * 提交上传
     */
    submiteParams(params) {
      // 只提交 dealer_customer_id，后端统一推导 signing_method 和 business_category
      params.dealer_customer_id = this.extForm.dealer_customer_id || 0
      delete params.signing_method
      delete params.business_category

      if (this.action.type == 'update') {
        params.id = this.action.id
        params.batchId = this.action.batchId
      }

      if (
        this.action.relativeData &&
        Object.keys(this.action.relativeData).length
      ) {
        params = { ...params, ...this.action.relativeData }
      }

      crmBusinessSaveAPI(params)
        .then(res => {
          this.loading = false

          this.$message.success(
            this.action.type == 'update' ? '编辑成功' : '添加成功'
          )

          this.close()

          this.$emit('save-success', {
            type: 'business'
          })
        })
        .catch(() => {
          this.loading = false
        })
    },

    /**
     * 验证唯一
     */
    UniquePromise({ field, value }) {
      return this.getUniquePromise(field, value, this.action)
    },

    async searchDealer(query) {
      // 从所有有效 CRM 客户中选择，排除当前商机所属客户
      this.dealerLoading = true
      try {
        const excludeId = this.fieldForm.customer_id || (this.action.data && this.action.data.customer_id) || 0
        const res = await request({
          url: 'crm/business/dealerOptions',
          method: 'post',
          data: { search: query || '', limit: 20, exclude_customer_id: excludeId }
        })
        const list = (res.data && res.data.list) || []
        // 保留已选经销商在列表头部，避免被分页裁掉
        if (this.extForm.dealer_customer_id) {
          const exist = list.find(d => d.customer_id === this.extForm.dealer_customer_id)
          if (!exist) {
            list.unshift({
              customer_id: this.extForm.dealer_customer_id,
              name: this.dealerName || ('客户#' + this.extForm.dealer_customer_id)
            })
          }
        }
        this.dealerOptions = list
      } catch (e) {
        this.dealerOptions = []
      } finally {
        this.dealerLoading = false
      }
    },

    /**
     * change
     */
    formChange(field, index, value, valueList) {
      if ([
        'select',
        'checkbox'
      ].includes(field.form_type) &&
          field.remark === 'options_type' &&
          field.optionsData) {
        const { fieldForm, fieldRules } = this.getFormContentByOptionsChange(this.fieldList, this.fieldForm)
        this.fieldForm = fieldForm
        this.fieldRules = fieldRules
      }
    },

    /**
     * 地址change
     */
    otherChange(data, field) {
      console.log(data, field)
      if (field.form_type === 'business_type') {
        const statusItem = this.getItemWithFromType(this.fieldList, 'business_status')
        if (statusItem) {
          if (isEmpty(data.value)) {
            this.fieldForm[field.field] = ''
          } else {
            if (data.type != 'init') {
              this.fieldForm[field.field] = ''
            }

            const typeObj = data.data.find(item => item.type_id == data.value)
            statusItem.setting = typeObj.statusList || []
            this.$set(this.fieldForm, statusItem.field, statusItem.setting.length > 0 ? statusItem.setting[0].status_id : '')
          }
        }
      } else if (field.form_type === 'product') {
        this.fieldForm.money = data.value.total_price || ''
      }
      this.$set(this.fieldForm, field.field, data.value)
      this.$refs.crmForm.validateField(field.field)
    },

    /**
     * 关闭
     */
    close() {
      this.$emit('close')
    }
  }
}
</script>

<style lang="scss" scoped>
.wk-form {
  ::v-deep .el-form-item.is-product {
    flex: 0 0 100%;
  }
}
.signing-tip {
  color: #909399;
  font-size: 12px;
}
</style>

<template>
  <xr-create
    :loading="loading"
    :title="title"
    @close="close"
    @save="saveClick">
    <el-form
      ref="crmForm"
      :model="fieldForm"
      :rules="fieldRules"
      :field-from="fieldForm"
      :validate-on-rule-change="false"
      class="wk-form"
      label-position="top"
    >
      <create-sections title="基本信息">
        <wk-form-items
          v-for="(children, index) in baseFieldList"
          :key="index"
          :field-from="fieldForm"
          :field-list="children"
          @change="formChange"
        >
          <template slot-scope="{ data }">
            <xh-customer-address
              v-if="data && data.form_type == 'map_address'"
              :value="fieldForm[data.field]"
              @value-change="otherChange($event, data)"
            />
          </template>
        </wk-form-items>
      </create-sections>

      <div
        v-if="hasCooperationFields && !cooperationEnabled"
        class="cooperation-entry">
        <div class="cooperation-entry__icon"><i class="el-icon-s-cooperation" /></div>
        <div class="cooperation-entry__content">
          <div class="cooperation-entry__title">合作企业信息</div>
          <div class="cooperation-entry__desc">仅代理商、软件厂商、渠道合作方等需要核实或跟进时填写</div>
        </div>
        <el-button
          type="primary"
          size="small"
          @click="enableCooperation">
          添加合作信息
          <i class="el-icon-arrow-down" />
        </el-button>
      </div>

      <create-sections
        v-if="hasCooperationFields && cooperationEnabled"
        m-color="#5B6BFF"
        class="cooperation-section"
        title="合作信息">
        <template slot="header">
          <span class="cooperation-section__subtitle">企业合作线索的核实与跟进</span>
          <div class="cooperation-section__actions">
            <el-tag
              v-if="isCooperationCustomer && fieldForm.cooperation_stage"
              :type="cooperationStageTagType"
              size="mini"
              class="cooperation-section__stage">{{ fieldForm.cooperation_stage }}</el-tag>
            <el-button type="text" size="mini" @click="cooperationEnabled = false">
              收起<i class="el-icon-arrow-up" />
            </el-button>
          </div>
        </template>
        <div class="cooperation-guide">
          <div class="cooperation-guide__icon">
            <i class="el-icon-connection" />
          </div>
          <div class="cooperation-guide__content">
            <div class="cooperation-guide__title">{{ cooperationGuideTitle }}</div>
            <div class="cooperation-guide__desc">{{ cooperationGuideDescription }}</div>
          </div>
        </div>
        <wk-form-items
          v-for="(children, index) in visibleCooperationFieldList"
          :key="index"
          :field-from="fieldForm"
          :field-list="children"
          @change="formChange" />
      </create-sections>
    </el-form>

    <el-button
      v-if="action.type == 'save' && contactsSaveAuth"
      slot="footer"
      class="handle-button"
      type="primary"
      @click="debouncedSaveField(true)">保存并新建联系人</el-button>

    <!-- 新建 -->
    <contacts-create
      v-if="contactsCreateShow"
      :action="contactsCreateAction"
      @close="close"
      @save-success="close"
    />

  </xr-create>
</template>

<script>
import { filedGetFieldAPI } from '@/api/crm/common'
import { crmCustomerSaveAPI } from '@/api/crm/customer'

import XrCreate from '@/components/XrCreate'
import CreateSections from '@/components/CreateSections'
import WkFormItems from '@/components/NewCom/WkForm/WkFormItems'

import {
  XhCustomerAddress
} from '@/components/CreateCom'

import CustomFieldsMixin from '@/mixins/CustomFields'
import ContactsCreate from '../contacts/Create'

import { debounce } from 'throttle-debounce'
import { isEmpty } from '@/utils/types'
import { mapGetters } from 'vuex'
import {
  COOPERATION_FIELDS,
  VERIFY_REQUIRED_FIELDS,
  getCooperationStageTagType,
  hasFieldValue,
  isCooperationEnterprise,
  isVerificationRequired,
  shouldShowCooperationField
} from './cooperation'

export default {
  // 新建编辑
  name: 'CustomerCreate',

  components: {
    XrCreate,
    CreateSections,
    WkFormItems,
    XhCustomerAddress,
    ContactsCreate
  },

  mixins: [CustomFieldsMixin],

  props: {
    phone: String,
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
      baseFieldList: [],
      cooperationFieldList: [],
      cooperationEnabled: false,
      fieldForm: {},
      fieldRules: {},
      contactsCreateAction: {
        type: 'save',
        id: '',
        data: {}
      },
      contactsCreateShow: false
    }
  },

  computed: {
    ...mapGetters(['crm', 'userInfo']),
    contactsSaveAuth() {
      return this.crm.contacts && this.crm.contacts.save
    },
    title() {
      return this.action.type === 'update' ? '编辑客户' : '新建客户'
    },
    hasCooperationFields() {
      return this.cooperationFieldList.some(children => children.length > 0)
    },
    visibleCooperationFieldList() {
      return this.cooperationFieldList
        .map(children => children.filter(item => shouldShowCooperationField(item.field, this.fieldForm)))
        .filter(children => children.length > 0)
    },
    isCooperationCustomer() {
      return isCooperationEnterprise(this.fieldForm.cooperation_type)
    },
    cooperationStageTagType() {
      return getCooperationStageTagType(this.fieldForm.cooperation_stage)
    },
    cooperationGuideTitle() {
      if (!this.fieldForm.cooperation_type) return '先选择客户类型'
      if (!this.isCooperationCustomer) return '医院客户沿用现有客户流程'
      if (this.fieldForm.cooperation_stage === '已核实') return '完整核实资料将进入绩效审核'
      return '记录企业合作进展'
    },
    cooperationGuideDescription() {
      if (!this.fieldForm.cooperation_type) return '选择非医院企业后，将显示合作阶段、发现人及核实资料。'
      if (!this.isCooperationCustomer) return '无需填写合作阶段，不影响现有商机、合同和成交状态。'
      if (this.fieldForm.cooperation_stage === '已核实') return '请填写发现人、核实人、核实时间、核实结果和核实说明。'
      return '合作阶段独立于商机和成交状态，后续联系与洽谈继续使用活动、商机和合同。'
    }
  },

  watch: {},

  created() {
    this.debouncedSaveField = debounce(300, this.saveClick)

    this.getField()
  },

  mounted() {},

  beforeDestroy() {},

  methods: {
    /**
     * 开启可选的合作企业信息，并为首次录入设置最小默认值。
     */
    enableCooperation() {
      this.cooperationEnabled = true

      if (!hasFieldValue(this.fieldForm.cooperation_stage)) {
        this.$set(this.fieldForm, 'cooperation_stage', '初筛')
      }

      const currentUserId = this.userInfo && (
        this.userInfo.id || this.userInfo.user_id || this.userInfo.userId
      )
      if (!hasFieldValue(this.fieldForm.discover_user_id) && currentUserId) {
        this.$set(this.fieldForm, 'discover_user_id', Number(currentUserId))
      }

      this.$nextTick(() => this.refreshCooperationRules())
    },

    /**
     * 获取数据
     */
    getField() {
      this.loading = true
      const params = {
        types: 'crm_customer',
        module: 'crm',
        controller: 'customer',
        action: this.action.type,
        format: 2
      }

      if (this.action.type == 'update') {
        params.action_id = this.action.id
      }

      filedGetFieldAPI(params)
        .then(res => {
          const list = res.data || []
          if (!isEmpty(this.phone)) {
            list.forEach(item => {
              if (item.form_type === 'mobile') {
                item.default_value = this.phone
              }
            })
          }
          const assistIds = this.getFormAssistIds(list)
          const baseFields = []

          const fieldList = []
          const fieldRules = {}
          const fieldForm = {}
          list.forEach(children => {
            const fields = []
            children.forEach(item => {
              const temp = this.getFormItemDefaultProperty(item)
              temp.show = !assistIds.includes(item.formAssistId)
              if (temp.form_type === 'map_address') {
                temp.name = '地区信息'
              }
              if (['cooperation_stage', 'verify_note'].includes(temp.field)) {
                temp.input_tips = ''
                temp.tips = ''
              }


              const canEdit = this.getItemIsCanEdit(item, this.action.type)
              // 是否能编辑权限
              if (temp.show && canEdit) {
                fieldRules[temp.field] = this.getRules(item)
              }

              // 是否可编辑
              temp.disabled = !canEdit

              // 特殊字段允许多选
              this.getItemRadio(item, temp)

              // 获取默认值
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
          this.baseFieldList = fieldList
            .map(children => children.filter(item => !COOPERATION_FIELDS.includes(item.field)))
            .filter(children => children.length > 0)
          this.cooperationFieldList = fieldList
            .map(children => children.filter(item => COOPERATION_FIELDS.includes(item.field)))
            .filter(children => children.length > 0)
          this.fieldForm = fieldForm
          this.fieldRules = fieldRules
          this.cooperationEnabled = COOPERATION_FIELDS.some(field => hasFieldValue(fieldForm[field]))

          this.$nextTick(() => this.refreshCooperationRules())

          this.loading = false
        })
        .catch((e) => {
          this.loading = false
        })
    },

    /**
     * 保存
     */
    saveClick(createContacts = false) {
      this.loading = true
      this.normalizeCooperationUserFields()
      const crmForm = this.$refs.crmForm
      crmForm.validate(valid => {
        if (valid) {
          const params = this.getSubmiteParams(this.baseFields, this.fieldForm)
          if (this.action.type === 'update') {
            params.id = this.action.action_id
          }
          this.submiteParams(params, createContacts)
        } else {
          this.loading = false
          if (isVerificationRequired(this.fieldForm)) {
            this.cooperationEnabled = true
          }
          // 提示第一个error
          this.getFormErrorMessage(crmForm)
          return false
        }
      })
    },

    /**
     * 提交上传
     */
    submiteParams(params, createContacts) {
      if (this.action.type == 'update') {
        // params.entity.customerId = this.action.id
        // params.entity.batchId = this.action.batchId
        params.id = this.action.id
      }

      // 相关添加时候的多余提交信息
      if (
        this.action.relativeData &&
        Object.keys(this.action.relativeData).length
      ) {
        params = { ...params, ...this.action.relativeData }
      }
      crmCustomerSaveAPI(params)
        .then(res => {
          this.loading = false
          this.$store.dispatch('GetMessageNum')

          if (createContacts) {
            this.contactsCreateAction = {
              type: 'relative',
              crmType: 'customer',
              data: {
                customer: res.data || {}
              }
            }
            this.contactsCreateShow = true
          } else {
            this.$message.success(
              this.action.type == 'update' ? '编辑成功' : '添加成功'
            )
            this.close()
          }

          // 保存成功
          this.$emit('save-success', {
            type: 'customer',
            data: res.data || {}
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

    /**
     * change
     */
    formChange(field, index, value, valueList) {
      if (field.form_type === 'single_user') {
        this.$set(this.fieldForm, field.field, this.normalizeSingleUserId(value))
      }

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

      this.$nextTick(() => this.refreshCooperationRules())
    },

    /** 仅在非医院企业进入已核实时要求完整核实资料。 */
    refreshCooperationRules() {
      const required = this.cooperationEnabled && isVerificationRequired(this.fieldForm)
      const labels = {
        discover_user_id: '发现人',
        verify_user_id: '核实人',
        verify_time: '核实时间',
        verify_result: '核实结果',
        verify_note: '核实说明'
      }

      VERIFY_REQUIRED_FIELDS.forEach(field => {
        const rules = (this.fieldRules[field] || []).filter(rule => !rule.cooperationRequired)
        if (required) {
          rules.push({
            validator: (rule, value, callback) => {
              const currentValue = ['discover_user_id', 'verify_user_id'].includes(field)
                ? this.normalizeSingleUserId(this.fieldForm[field])
                : this.fieldForm[field]
              if (hasFieldValue(currentValue)) {
                callback()
              } else {
                callback(new Error(`请填写${labels[field]}`))
              }
            },
            trigger: ['blur', 'change'],
            cooperationRequired: true
          })
        }
        this.$set(this.fieldRules, field, rules)
      })

      if (this.$refs.crmForm) {
        this.$refs.crmForm.clearValidate(VERIFY_REQUIRED_FIELDS)
      }
    },

    /** 兼容单选人员组件可能返回的 ID、数组或用户对象。 */
    normalizeSingleUserId(value) {
      const selected = Array.isArray(value) ? value[0] : value
      if (selected && typeof selected === 'object') {
        return Number(selected.id || selected.user_id || selected.userId) || ''
      }
      return Number(selected) || ''
    },

    normalizeCooperationUserFields() {
      const userFields = ['discover_user_id', 'verify_user_id']
      userFields.forEach(field => {
        if (hasFieldValue(this.fieldForm[field])) {
          this.$set(this.fieldForm, field, this.normalizeSingleUserId(this.fieldForm[field]))
        }
      })
    },

    /**
     * 地址change
     */
    otherChange(data, field) {
      this.$set(this.fieldForm, field.field, data.value)
      this.$refs.crmForm.validateField(field.field)
    },

    /**
     * 关闭
     */
    close() {
      this.contactsCreateShow = false
      this.$emit('close')
    }
  }
}
</script>

<style lang="scss" scoped>
::v-deep .xr-create__header {
  height: 48px;
  margin-bottom: 0;
  padding: 0 20px;
  border-bottom: 1px solid #edf0f5;
  background-color: #fff;
}

::v-deep .xr-create__body {
  padding: 10px 10px 2px;
  background-color: #f7f8fb;
}

::v-deep .xr-create__footer {
  padding: 10px 20px;
  border-top: 1px solid #edf0f5;
  background-color: #fff;
  box-shadow: 0 -3px 10px rgba(31, 45, 61, 0.04);
}

.wk-form {
  padding: 0 8px 12px;
  background-color: transparent;

  ::v-deep .el-form-item.is-map_address {
    flex: 0 0 100%;
  }

  ::v-deep .wk-form-items {
    padding: 0 8px 4px;
  }

  ::v-deep .wk-form-item {
    padding: 7px 10px 0;
  }

  ::v-deep .wk-form-item .el-form-item__label {
    padding-bottom: 5px;
    line-height: 18px;
  }

  ::v-deep .el-input__inner {
    height: 34px;
    line-height: 34px;
  }

  ::v-deep .el-textarea__inner {
    min-height: 62px !important;
  }

  ::v-deep .wk-form-item .el-form-item__label {
    display: block;
    width: 100%;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
  }

  ::v-deep > .create-sections:first-child .section-header {
    padding: 8px 15px;
    border-bottom: 1px solid #f0f1f5;
  }

  ::v-deep > .create-sections:first-child {
    padding-bottom: 6px;
    border: 1px solid #edf0f5;
    border-radius: 8px;
    box-shadow: 0 3px 12px rgba(31, 45, 61, 0.035);
    overflow: hidden;
  }
}

.cooperation-section {
  margin: 10px 8px 12px;
  border: 1px solid #e6e9ff;
  border-radius: 8px;
  box-shadow: 0 4px 14px rgba(76, 91, 210, 0.06);
  overflow: hidden;

  ::v-deep .section-header {
    padding: 9px 16px 7px;
    background: linear-gradient(90deg, #f5f6ff 0%, #fff 72%);
    white-space: nowrap;
  }

  ::v-deep .create-sections-content {
    padding: 0 12px 6px;
  }

  &__subtitle {
    min-width: 0;
    margin-left: 10px;
    overflow: hidden;
    color: #8a91a8;
    font-size: 12px;
    white-space: nowrap;
    text-overflow: ellipsis;
  }

  &__stage {
    margin-right: 10px;
    border-radius: 10px;
  }

  &__actions {
    display: flex;
    align-items: center;
    margin-left: auto;
    padding-left: 12px;
    flex-shrink: 0;

    .el-button {
      padding: 0;
    }
  }
}

.cooperation-entry {
  display: flex;
  align-items: center;
  margin: 10px 8px 6px;
  padding: 10px 14px;
  border: 1px dashed #d8dce8;
  border-radius: 8px;
  background-color: #fafbfc;
  transition: border-color 0.2s, background-color 0.2s;

  &__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    margin-right: 10px;
    border-radius: 10px;
    color: #5b6bff;
    font-size: 18px;
    background-color: #e9ecff;
    flex-shrink: 0;
  }

  &__content {
    min-width: 0;
    flex: 1;
  }

  &__title {
    overflow: hidden;
    color: #30364d;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    text-overflow: ellipsis;
  }

  &__desc {
    margin-top: 4px;
    overflow: hidden;
    color: #8a91a8;
    font-size: 12px;
    white-space: nowrap;
    text-overflow: ellipsis;
  }

  .el-button {
    margin-left: 16px;
    flex-shrink: 0;
  }
}

.cooperation-guide {
  display: flex;
  align-items: center;
  margin: 4px 2px 7px;
  padding: 8px 12px;
  border-radius: 6px;
  background-color: #f7f8ff;

  &__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    margin-right: 10px;
    border-radius: 9px;
    color: #5b6bff;
    font-size: 17px;
    background-color: #e9ecff;
    flex-shrink: 0;
  }

  &__content {
    min-width: 0;
    flex: 1;
  }

  &__title {
    overflow: hidden;
    color: #30364d;
    font-size: 13px;
    font-weight: 600;
    line-height: 20px;
    white-space: nowrap;
    text-overflow: ellipsis;
  }

  &__desc {
    margin-top: 2px;
    overflow: hidden;
    color: #7a8199;
    font-size: 12px;
    line-height: 18px;
    white-space: nowrap;
    text-overflow: ellipsis;
  }
}
</style>

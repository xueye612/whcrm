<template>
  <div class="mobile-ledger-quick">
    <div ref="quickScroll" class="quick-scroll">
      <el-form ref="formRef" :model="form" :rules="rules" label-position="top" class="quick-form">
        <el-form-item label="关联合同" prop="contract_id">
          <div class="contract-search">
            <el-input
              ref="contractSearchInput"
              v-model="contractKeyword"
              clearable
              placeholder="输入合同编号/名称/客户搜索"
              autocomplete="off"
              autocapitalize="off"
              inputmode="search"
              @input="handleContractKeywordInput"
              @clear="clearContractSelection"
              @focus="handleContractFieldFocus"
            />
            <div v-if="contractLoading" class="contract-search__hint">搜索中...</div>
            <ul
              v-else-if="showContractDropdown && contractOptions.length"
              class="contract-search__list">
              <li
                v-for="item in contractOptions"
                :key="item.contract_id"
                class="contract-search__item"
                @click="selectContract(item)">
                <div class="contract-option-row">
                  <span class="contract-option-customer">{{ item.customer_name || '未关联客户' }}</span>
                  <span class="contract-option-contract">{{ item.contract_display_name }}</span>
                </div>
              </li>
            </ul>
            <div
              v-else-if="showContractDropdown && contractKeyword.trim() && !contractLoading"
              class="contract-search__hint">
              未找到相关合同，请换个关键词
            </div>
            <div v-if="selectedContract && selectedContract.customer_name" class="selected-contract-tip">
              客户：{{ selectedContract.customer_name }}
            </div>
          </div>
        </el-form-item>

        <el-form-item label="问题标题" prop="title">
          <el-input
            v-model.trim="form.title"
            maxlength="200"
            placeholder="简要描述问题"
            @focus="scrollFieldInContainer" />
        </el-form-item>

        <el-form-item label="问题内容" prop="description">
          <el-input
            v-model.trim="form.description"
            type="textarea"
            :rows="5"
            maxlength="2000"
            placeholder="现场情况、复现步骤等"
            @focus="scrollFieldInContainer" />
        </el-form-item>

        <el-form-item label="反馈人">
          <el-input
            v-model="form.feedback_user"
            clearable
            placeholder="输入反馈人，或点下方快捷选择"
            @focus="scrollFieldInContainer" />
          <div v-if="feedbackContactsOptions.length" class="contact-chips">
            <button
              v-for="(item, index) in feedbackContactsOptions"
              :key="item.contacts_id || item.id || index"
              type="button"
              class="contact-chip"
              :class="{ 'is-active': form.feedback_user === getFeedbackContactLabel(item) }"
              @click="selectFeedbackUser(item)">
              {{ getFeedbackContactLabel(item) }}
            </button>
          </div>
        </el-form-item>

        <el-form-item label="反馈渠道">
          <el-select v-model="form.feedback_channel" style="width: 100%">
            <el-option v-for="item in channelOptions" :key="item" :label="item" :value="item" />
          </el-select>
        </el-form-item>

        <el-form-item label="问题分类">
          <el-select v-model="form.category" clearable placeholder="可选" style="width: 100%">
            <el-option v-for="item in categoryOptions" :key="item" :label="item" :value="item" />
          </el-select>
        </el-form-item>

        <el-form-item label="处理状态">
          <el-select v-model="form.status" style="width: 100%">
            <el-option v-for="item in statusOptions" :key="item" :label="item" :value="item" />
          </el-select>
        </el-form-item>
      </el-form>
    </div>

    <div class="quick-actions">
      <el-button @click="restoreDraft">恢复草稿</el-button>
      <el-button type="primary" :loading="submitting" @click="submit">保存</el-button>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex'
import { crmContractIndexAPI, crmContractReadAPI } from '@/api/crm/contract'
import { crmCustomerQueryContactsAPI } from '@/api/crm/customer'
import { ledgerSaveAPI } from '@/api/ledger/ledger'
import { ledgerCategoryListAPI } from '@/api/admin/other'
import {
  LEDGER_CHANNEL_OPTIONS,
  LEDGER_STATUS_OPTIONS,
  DEFAULT_CATEGORY_OPTIONS,
  DEFAULT_LEDGER_CATEGORY,
  buildMobileLedgerDraftKey,
  formatContractOption,
  getFeedbackContactLabel
} from '@/utils/ledgerFormat'
import { normalizeCompletionFields } from '@/utils/ledgerCompletion'

export default {
  name: 'MobileLedgerQuick',
  data() {
    return {
      submitting: false,
      contractLoading: false,
      feedbackContactsLoading: false,
      contractKeyword: '',
      showContractDropdown: false,
      contractOptions: [],
      feedbackContactsOptions: [],
      channelOptions: LEDGER_CHANNEL_OPTIONS,
      statusOptions: LEDGER_STATUS_OPTIONS,
      categoryOptions: DEFAULT_CATEGORY_OPTIONS.slice(),
      form: {
        contract_id: '',
        customer_id: '',
        title: '',
        description: '',
        feedback_user: '',
        feedback_channel: '微信',
        category: DEFAULT_LEDGER_CATEGORY,
        status: '待处理',
        handler_user_id: '',
        register_user_id: ''
      },
      rules: {
        contract_id: [{ required: true, message: '请选择合同', trigger: 'change' }],
        title: [{ required: true, message: '请填写问题标题', trigger: 'blur' }],
        description: [{ required: true, message: '请填写问题内容', trigger: 'blur' }]
      },
      _contractSearchTimer: null
    }
  },
  computed: {
    ...mapGetters(['userInfo']),
    draftKey() {
      return buildMobileLedgerDraftKey(this.userInfo && this.userInfo.id)
    },
    selectedContract() {
      if (!this.form.contract_id) return null
      return this.contractOptions.find(item => String(item.contract_id) === String(this.form.contract_id)) || null
    }
  },
  created() {
    this.form = this.createEmptyForm()
    this.loadCategories()
    this.restoreDraft()
  },
  mounted() {
    this._onDocumentTouch = () => {
      if (!this.showContractDropdown) return
      this.$nextTick(() => {
        const input = this.$refs.contractSearchInput && this.$refs.contractSearchInput.$el
        if (input && !input.contains(document.activeElement)) {
          this.showContractDropdown = false
        }
      })
    }
    document.addEventListener('touchstart', this._onDocumentTouch, { passive: true })
  },
  beforeDestroy() {
    if (this._contractSearchTimer) {
      clearTimeout(this._contractSearchTimer)
    }
    if (this._onDocumentTouch) {
      document.removeEventListener('touchstart', this._onDocumentTouch)
    }
  },
  methods: {
    getFeedbackContactLabel,
    createEmptyForm() {
      const userId = this.userInfo && this.userInfo.id
      return {
        contract_id: '',
        title: '',
        description: '',
        feedback_user: '',
        feedback_channel: '微信',
        category: DEFAULT_LEDGER_CATEGORY,
        status: '待处理',
        handler_user_id: userId || '',
        register_user_id: userId || '',
        customer_id: ''
      }
    },
    handleContractFieldFocus(event) {
      this.showContractDropdown = true
      this.scrollFieldInContainer(event)
    },
    scrollFieldInContainer(event) {
      const scrollEl = this.$refs.quickScroll
      const target = event && event.target
      if (!scrollEl || !target) return
      this.$nextTick(() => {
        const containerRect = scrollEl.getBoundingClientRect()
        const targetRect = target.getBoundingClientRect()
        const delta = targetRect.top - containerRect.top - 16
        if (Math.abs(delta) > 8) {
          scrollEl.scrollTop += delta
        }
      })
    },
    handleContractKeywordInput(value) {
      const keyword = String(value || '').trim()
      if (this.form.contract_id) {
        const selected = this.selectedContract
        if (!selected || selected.option_label !== keyword) {
          this.form.contract_id = ''
          this.form.customer_id = ''
          this.feedbackContactsOptions = []
        }
      }
      this.showContractDropdown = true
      if (this._contractSearchTimer) {
        clearTimeout(this._contractSearchTimer)
      }
      this._contractSearchTimer = setTimeout(() => {
        this.searchContracts(keyword)
      }, 300)
    },
    selectContract(item) {
      if (!item) return
      this.form.contract_id = item.contract_id
      this.form.customer_id = item.customer_id || ''
      this.contractKeyword = item.option_label || ''
      this.showContractDropdown = false
      if (!this.contractOptions.some(row => String(row.contract_id) === String(item.contract_id))) {
        this.contractOptions = [item].concat(this.contractOptions)
      }
      this.handleContractChange(item.contract_id)
      this.$nextTick(() => {
        if (this.$refs.formRef) {
          this.$refs.formRef.validateField('contract_id')
        }
      })
    },
    clearContractSelection() {
      this.form.contract_id = ''
      this.form.customer_id = ''
      this.contractKeyword = ''
      this.contractOptions = []
      this.feedbackContactsOptions = []
      this.form.feedback_user = ''
      this.showContractDropdown = false
    },
    selectFeedbackUser(item) {
      this.form.feedback_user = getFeedbackContactLabel(item)
    },
    loadCategories() {
      ledgerCategoryListAPI().then(res => {
        const list = (res.data || []).filter(item => item && String(item).trim() !== '')
        if (list.length) this.categoryOptions = list
      }).catch(() => {})
    },
    searchContracts(keyword) {
      const query = String(keyword || '').trim()
      if (!query) {
        this.contractOptions = []
        this.contractLoading = false
        return
      }
      this.contractLoading = true
      crmContractIndexAPI({
        page: 1,
        limit: 20,
        search: query,
        check_status: 2,
        order_field: 'start_time',
        order_type: 'desc'
      }).then(res => {
        const list = (res.data && res.data.list) || []
        this.contractOptions = list
          .filter(item => ['2', '7'].includes(String(item.check_status)))
          .map(item => formatContractOption(item))
          .filter(Boolean)
      }).finally(() => {
        this.contractLoading = false
      })
    },
    handleContractChange(contractId) {
      if (!contractId) {
        this.feedbackContactsOptions = []
        this.form.feedback_user = ''
        this.form.customer_id = ''
        return
      }
      const contract = this.contractOptions.find(item => String(item.contract_id) === String(contractId))
      if (contract) {
        this.form.customer_id = contract.customer_id || ''
      }
      this.loadFeedbackContacts(contract || { customer_id: this.form.customer_id })
    },
    loadFeedbackContacts(contract) {
      const customerId = (contract && contract.customer_id) || this.form.customer_id
      if (!customerId) {
        this.feedbackContactsOptions = []
        return
      }
      this.feedbackContactsLoading = true
      crmCustomerQueryContactsAPI({
        pageType: 'all',
        customer_id: customerId
      }).then(res => {
        const list = (res.data && res.data.list) ? res.data.list : []
        this.feedbackContactsOptions = list.map(item => ({
          ...item,
          name: getFeedbackContactLabel(item)
        }))
        if (!this.form.feedback_user && this.feedbackContactsOptions.length) {
          this.form.feedback_user = getFeedbackContactLabel(this.feedbackContactsOptions[0])
        }
      }).catch(() => {
        this.feedbackContactsOptions = []
      }).finally(() => {
        this.feedbackContactsLoading = false
      })
    },
    restoreSelectedContract() {
      if (!this.form.contract_id) return
      if (this.contractKeyword) return
      crmContractReadAPI({ id: this.form.contract_id }).then(res => {
        const formatted = formatContractOption(res.data || {})
        if (!formatted) return
        this.contractKeyword = formatted.option_label
        this.contractOptions = [formatted]
      }).catch(() => {})
    },
    buildPayload() {
      const userId = this.userInfo && this.userInfo.id
      const payload = normalizeCompletionFields({
        contract_id: this.form.contract_id,
        customer_id: '',
        business_id: '',
        title: this.form.title,
        description: this.form.description,
        feedback_user: this.form.feedback_user,
        feedback_channel: this.form.feedback_channel,
        category: this.form.category,
        status: this.form.status || '待处理',
        handler_user_id: this.form.handler_user_id || userId,
        register_user_id: this.form.register_user_id || userId
      }, () => this.formatNow())
      payload.sync_task_status = 1
      return payload
    },
    formatNow() {
      const now = new Date()
      const pad = n => String(n).padStart(2, '0')
      return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`
    },
    saveDraft() {
      try {
        localStorage.setItem(this.draftKey, JSON.stringify({
          ...this.form,
          contract_keyword: this.contractKeyword
        }))
      } catch (e) {
        // ignore
      }
    },
    restoreDraft() {
      try {
        const raw = localStorage.getItem(this.draftKey)
        if (!raw) return
        const parsed = JSON.parse(raw)
        this.form = Object.assign(this.createEmptyForm(), parsed)
        this.contractKeyword = parsed.contract_keyword || ''
        if (this.form.contract_id) {
          this.restoreSelectedContract()
        }
        if (this.form.contract_id && this.form.customer_id) {
          this.loadFeedbackContacts({ customer_id: this.form.customer_id })
        }
      } catch (e) {
        // ignore
      }
    },
    clearDraft() {
      try {
        localStorage.removeItem(this.draftKey)
      } catch (e) {
        // ignore
      }
    },
    submit() {
      this.$refs.formRef.validate(valid => {
        if (!valid) return
        this.submitting = true
        ledgerSaveAPI(this.buildPayload()).then(() => {
          this.clearDraft()
          this.$message.success('保存成功')
          this.$confirm('是否继续记一条？', '提示', {
            confirmButtonText: '继续',
            cancelButtonText: '返回列表',
            type: 'success'
          }).then(() => {
            this.form = this.createEmptyForm()
            this.contractKeyword = ''
            this.contractOptions = []
            this.feedbackContactsOptions = []
          }).catch(() => {
            this.$router.replace('/m/ledger')
          })
        }).finally(() => {
          this.submitting = false
        })
      })
    }
  },
  beforeRouteLeave(to, from, next) {
    this.saveDraft()
    next()
  }
}
</script>

<style lang="scss" scoped>
.mobile-ledger-quick {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  min-width: 0;
  box-sizing: border-box;
}

.quick-scroll {
  flex: 1;
  min-height: 0;
  min-width: 0;
  box-sizing: border-box;
  overflow-y: auto;
  overflow-x: hidden;
  -webkit-overflow-scrolling: touch;
  overscroll-behavior: contain;
  padding-bottom: 12px;
}

.quick-form ::v-deep .el-form-item {
  margin-bottom: 14px;
}

.quick-form ::v-deep .el-form-item__content {
  width: 100%;
}

.quick-form ::v-deep .el-input,
.quick-form ::v-deep .el-textarea,
.quick-form ::v-deep .el-select {
  display: block;
  width: 100%;
  max-width: 100%;
}

.quick-form ::v-deep .el-input__inner,
.quick-form ::v-deep .el-textarea__inner {
  width: 100%;
  box-sizing: border-box;
  font-size: 16px;
}

.quick-form ::v-deep .el-form-item__label {
  padding-bottom: 4px;
  line-height: 1.4;
}

.contract-search__list {
  list-style: none;
  margin: 8px 0 0;
  padding: 0;
  max-height: 220px;
  overflow-y: auto;
  border: 1px solid #ebeef5;
  border-radius: 8px;
  background: #fff;
}

.contract-search__item {
  padding: 10px 12px;
  border-bottom: 1px solid #f2f3f5;
}

.contract-search__item:last-child {
  border-bottom: none;
}

.contract-search__item:active {
  background: #ecf5ff;
}

.contract-search__hint {
  margin-top: 8px;
  font-size: 12px;
  color: #909399;
}

.contract-option-row {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.contract-option-customer {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.contract-option-contract {
  flex-shrink: 0;
  max-width: 42%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: #909399;
}

.selected-contract-tip {
  margin-top: 6px;
  font-size: 12px;
  color: #606266;
}

.contact-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 8px;
}

.contact-chip {
  border: 1px solid #dcdfe6;
  background: #fff;
  border-radius: 16px;
  padding: 4px 12px;
  font-size: 12px;
  color: #606266;
}

.contact-chip.is-active {
  border-color: #409eff;
  color: #409eff;
  background: #ecf5ff;
}

.quick-actions {
  flex-shrink: 0;
  display: flex;
  gap: 10px;
  min-width: 0;
  padding: 12px 0;
  background: #fff;
  border-top: 1px solid #e8eaed;
}

.quick-actions .el-button {
  flex: 1;
  min-height: 44px;
}
</style>

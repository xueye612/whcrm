<template>
  <div
    v-loading="loading"
    v-empty="nopermission"
    class="rc-cont"
    xs-empty-icon="nopermission"
    xs-empty-text="暂无权限">
    <flexbox
      class="rc-head"
      direction="row-reverse">
      <el-button
        v-if="canSave"
        class="xr-btn--orange rc-head-item"
        icon="el-icon-plus"
        type="primary"
        @click="openCreate">新建台账</el-button>
    </flexbox>
    <el-table
      v-show="list.length >= 0"
      :data="list"
      :height="tableHeight"
      stripe
      style="width: 100%;border: 1px solid #E6E6E6;"
      @row-click="handleRowClick">
      <el-table-column prop="title" label="反馈问题" min-width="200" show-overflow-tooltip />
      <el-table-column label="问题描述" min-width="280" show-overflow-tooltip>
        <template slot-scope="scope">
          {{ scope.row.description ? scope.row.description.replace(/<[^>]+>/g, '').slice(0, 80) : '—' }}
        </template>
      </el-table-column>
      <el-table-column prop="category" label="问题分类" width="120" />
      <el-table-column prop="status" label="处理状态" width="120" />
      <el-table-column prop="handler_user_name" label="处理人" width="120" />
      <el-table-column label="反馈时间" width="170" show-overflow-tooltip>
        <template slot-scope="scope">{{ formatDateTime(scope.row.feedback_time || scope.row.register_time) || '—' }}</template>
      </el-table-column>
      <el-table-column prop="finish_time" label="完成时间" width="170" show-overflow-tooltip />
      <el-table-column label="操作" width="200" fixed="right">
        <template slot-scope="scope">
          <el-button type="text" @click.stop="openDetail(scope.row)">详情</el-button>
          <el-button v-if="canUpdate" type="text" @click.stop="openEdit(scope.row)">编辑</el-button>
          <el-button v-if="canDelete" type="text" @click.stop="handleDelete(scope.row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog :title="formTitle" :visible.sync="formVisible" width="980px" append-to-body class="ledger-form-dialog">
      <el-form ref="ledgerForm" :model="form" :rules="rules" label-width="110px" class="ledger-form">
        <div class="ledger-form-section section-related">
          <div class="section-title">关联对象</div>
          <el-form-item label="合同" prop="contract_id" class="form-item-full">
            <crm-relative-cell
              :value="form.contract_id"
              :relation="contractRelation"
              relative-type="contract"
              @value-change="handleContractChange" />
          </el-form-item>
        </div>

        <div class="ledger-form-section section-core">
          <div class="section-title">核心内容</div>
          <el-form-item label="反馈问题" prop="title" class="form-item-strong">
            <el-input ref="titleInput" v-model.trim="form.title" placeholder="请输入简洁的问题标题" class="ledger-title-input" />
          </el-form-item>
          <el-form-item label="问题描述" class="form-item-full">
            <tinymce
              v-if="formVisible"
              v-model="form.description"
              :height="320"
              :toolbar="['undo redo | bold bullist numlist | image']"
              :plugins="['lists', 'image', 'paste', 'autoresize']"
              :init="{
                placeholder: '补充问题现象、复现步骤、影响范围等',
                menubar: false,
                content_style: 'img{max-width:100%;height:auto;}'
              }"
              class="ledger-form-rich"
            />
          </el-form-item>
        </div>

        <div class="ledger-form-section section-track">
          <div class="section-title">归类与跟踪</div>
          <el-row :gutter="16">
            <el-col :xs="24" :sm="12">
              <el-form-item label="反馈人">
                <el-select
                  v-model="form.feedback_user"
                  :loading="feedbackContactsLoading"
                  clearable
                  filterable
                  allow-create
                  default-first-option
                  placeholder="选择或输入反馈人">
                  <el-option
                    v-for="item in feedbackContactsOptions"
                    :key="item.contacts_id || item.contactsId || item.id"
                    :label="getFeedbackContactLabel(item)"
                    :value="getFeedbackContactLabel(item)" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :xs="24" :sm="12">
              <el-form-item label="反馈渠道">
                <el-select v-model="form.feedback_channel" placeholder="请选择">
                  <el-option v-for="item in channelOptions" :key="item" :label="item" :value="item" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :xs="24" :sm="12">
              <el-form-item label="问题分类">
                <el-select v-model="form.category" placeholder="请选择" @change="handleCategoryChange">
                  <el-option v-for="item in categoryOptions" :key="item" :label="item" :value="item" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col v-if="isTaskCategory(form.category)" :xs="24" :sm="12">
              <el-form-item label="项目">
                <el-select
                  v-model="form.work_id"
                  :loading="workLoading"
                  clearable
                  filterable
                  placeholder="选择项目"
                  @change="handleWorkChange">
                  <el-option v-for="item in workOptions" :key="item.work_id" :label="item.name" :value="item.work_id" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col v-if="isTaskCategory(form.category)" :xs="24" :sm="12">
              <el-form-item label="任务列表">
                <el-select
                  v-model="form.class_id"
                  :disabled="!form.work_id"
                  :loading="classLoading"
                  clearable
                  filterable
                  placeholder="选择任务列表">
                  <el-option v-for="item in classOptions" :key="item.class_id" :label="item.name" :value="item.class_id" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :xs="24" :sm="12">
              <el-form-item label="处理状态">
                <el-select v-model="form.status" placeholder="请选择">
                  <el-option v-for="item in statusOptions" :key="item" :label="item" :value="item" />
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>
        </div>

        <div class="ledger-form-section section-archive">
          <div class="section-title">归档信息</div>
          <el-row :gutter="16">
            <el-col :xs="24" :sm="12">
              <el-form-item label="反馈时间">
                <el-date-picker
                  v-model="form.feedback_time"
                  :append-to-body="true"
                  type="datetime"
                  value-format="yyyy-MM-dd HH:mm:ss"
                  placeholder="反馈时间"
                  popper-class="ledger-date-picker"
                  class="ledger-date-input"
                  clearable />
              </el-form-item>
            </el-col>
            <el-col :xs="24" :sm="12">
              <el-form-item label="登记人" prop="register_user_id">
                <xh-user-cell
                  :value="form.register_user_id"
                  :radio="true"
                  placeholder="选择登记人"
                  @value-change="handleRegisterChange" />
              </el-form-item>
            </el-col>
            <el-col :xs="24" :sm="12">
              <el-form-item label="处理人" prop="handler_user_id">
                <xh-user-cell
                  :value="form.handler_user_id"
                  :radio="true"
                  placeholder="选择处理人"
                  @value-change="handleHandlerChange" />
              </el-form-item>
            </el-col>
            <el-col :xs="24" :sm="12">
              <el-form-item label="完成时间">
                <el-date-picker
                  v-model="form.finish_time"
                  :append-to-body="true"
                  type="datetime"
                  value-format="yyyy-MM-dd HH:mm:ss"
                  placeholder="完成时间"
                  popper-class="ledger-date-picker"
                  class="ledger-date-input"
                  clearable />
              </el-form-item>
            </el-col>
            <el-col :xs="24" :sm="24">
              <el-form-item label="备注" class="form-item-full">
                <el-input v-model.trim="form.remark" :rows="2" type="textarea" placeholder="补充说明" />
              </el-form-item>
            </el-col>
          </el-row>
        </div>
      </el-form>
      <div slot="footer" class="ledger-form-footer">
        <div class="footer-left">* 为必填</div>
        <div class="footer-right">
          <el-button @click="formVisible=false">取消</el-button>
          <el-button :loading="formSubmitting" :disabled="formSubmitting" type="primary" @click="submitForm">保存</el-button>
        </div>
      </div>
    </el-dialog>

    <el-dialog :visible.sync="detailVisible" title="台账详情" width="920px" append-to-body class="ledger-detail-dialog">
      <div v-loading="detailLoading" class="ledger-detail">
        <section class="detail-section">
          <div class="section-title">基础信息</div>
          <div class="kv-grid">
            <div class="kv-item">
              <div class="kv-label">客户</div>
              <div class="kv-value">{{ ledgerDetail.customer_name || '—' }}</div>
            </div>
            <div class="kv-item">
              <div class="kv-label">反馈问题</div>
              <div class="kv-value">{{ ledgerDetail.title || '—' }}</div>
            </div>
            <div class="kv-item">
              <div class="kv-label">问题分类</div>
              <div class="kv-value">{{ ledgerDetail.category || '—' }}</div>
            </div>
            <div class="kv-item">
              <div class="kv-label">处理状态</div>
              <div class="kv-value">
                <el-tag
                  :type="ledgerDetail.status === '已完成' ? 'success' : ledgerDetail.status === '处理中' ? 'warning' : ledgerDetail.status === '已关闭' ? 'danger' : 'info'"
                  size="mini">
                  {{ ledgerDetail.status || '—' }}
                </el-tag>
              </div>
            </div>
            <div class="kv-item">
              <div class="kv-label">反馈人</div>
              <div class="kv-value">{{ ledgerDetail.feedback_user || '—' }}</div>
            </div>
            <div class="kv-item">
              <div class="kv-label">反馈渠道</div>
              <div class="kv-value">{{ ledgerDetail.feedback_channel || '微信' }}</div>
            </div>
            <div class="kv-item">
              <div class="kv-label">反馈时间</div>
              <div class="kv-value">{{ formatDateTime(ledgerDetail.feedback_time || ledgerDetail.register_time) || '—' }}</div>
            </div>
            <div class="kv-item">
              <div class="kv-label">完成时间</div>
              <div class="kv-value">
                {{ formatDateTime(ledgerDetail.finish_time) || '—' }}
                <el-tag
                  v-if="finishBadge.text"
                  :type="finishBadge.type"
                  size="mini"
                  class="time-badge">
                  {{ finishBadge.text }}
                </el-tag>
              </div>
            </div>
            <div class="kv-item">
              <div class="kv-label">登记人</div>
              <div class="kv-value">{{ ledgerDetail.register_user_name || '—' }}</div>
            </div>
            <div class="kv-item">
              <div class="kv-label">处理人</div>
              <div class="kv-value">{{ ledgerDetail.handler_user_name || '—' }}</div>
            </div>
          </div>
        </section>

        <section class="detail-section">
          <div class="section-title">描述信息</div>
          <div class="text-block">
            <div class="text-label">问题描述</div>
            <div v-if="ledgerDetail.description" class="text-value rich-text">
              <wk-desc-text :value="ledgerDetail.description" />
            </div>
            <div v-else class="text-value">—</div>
          </div>
          <div v-if="ledgerDetail.remark" class="text-block">
            <div class="text-label">备注</div>
            <div class="text-value">{{ ledgerDetail.remark || '—' }}</div>
          </div>
        </section>

        <section class="detail-section">
          <div class="section-title">进度记录</div>
          <el-timeline class="record-timeline">
            <el-timeline-item
              v-for="(item, index) in recordList"
              :key="item.followup_id"
              :timestamp="item.create_time ? item.create_time.slice(0, 16) : ''"
              :color="index === 0 ? '#409EFF' : '#C0C4CC'"
              :class="{ 'is-current': index === 0 }">
              <div class="record-content">
                <div class="record-user">{{ item.create_user_name || '—' }}</div>
                <div class="record-text">{{ item.content || '—' }}</div>
                <div v-if="item.old_status && item.new_status && item.old_status !== item.new_status" class="record-status">状态：{{ item.old_status }} → {{ item.new_status }}</div>
              </div>
            </el-timeline-item>
          </el-timeline>
        </section>

        <section v-if="ledgerDetail.status !== '已完成'" class="detail-section record-actions-section">
          <el-divider content-position="left">补充处理</el-divider>
          <el-input v-model.trim="recordForm.content" :rows="4" type="textarea" placeholder="填写处理结果" class="record-input" />
          <div class="record-actions">
            <el-select v-model="recordForm.new_status" clearable placeholder="变更状态（可选）" class="record-select">
              <el-option v-for="item in statusOptions" :key="item" :label="item" :value="item" />
            </el-select>
            <el-button type="primary" @click="addRecord">提交记录</el-button>
          </div>
        </section>
      </div>
      <div slot="footer" class="dialog-footer">
        <el-button @click="detailVisible=false">关闭</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script type="text/javascript">
import { XhUserCell } from '@/components/CreateCom'
import CrmRelativeCell from '@/components/CreateCom/CrmRelativeCell'
import Tinymce from '@/components/Tinymce'
import WkDescText from '@/components/NewCom/WkDescText'
import { workIndexWorkListAPI } from '@/api/pm/task'
import { workWorkStatisticAPI } from '@/api/pm/statistics'
import { crmCustomerQueryContactsAPI } from '@/api/crm/customer'
import { crmContractReadAPI } from '@/api/crm/contract'
import {
  ledgerIndexAPI,
  ledgerReadAPI,
  ledgerSaveAPI,
  ledgerUpdateAPI,
  ledgerDeleteAPI,
  ledgerRecordListAPI,
  ledgerRecordAddAPI
} from '@/api/ledger/ledger'
import { ledgerCategoryListAPI } from '@/api/admin/other'

export default {
  name: 'RelativeLedger',
  components: {
    XhUserCell,
    CrmRelativeCell,
    Tinymce,
    WkDescText
  },
  props: {
    id: [String, Number],
    crmType: {
      type: String,
      default: ''
    },
    detail: {
      type: Object,
      default: () => {
        return {}
      }
    }
  },
  data() {
    return {
      loading: false,
      nopermission: false,
      list: [],
      tableHeight: '400px',
      statusOptions: ['待处理', '处理中', '待验证', '已完成', '已关闭'],
      categoryOptions: ['使用指导', '操作错误', '功能完善', '系统BUG', '新增需求', '三方问题', '其他问题'],
      channelOptions: ['微信', '电话', '现场', '转述', '其他'],
      workOptions: [],
      classOptions: [],
      workLoading: false,
      classLoading: false,
      feedbackContactsOptions: [],
      feedbackContactsLoading: false,
      formVisible: false,
      formTitle: '新建台账',
      formSubmitting: false,
      form: {},
      rules: {
        contract_id: [{ required: true, message: '请选择合同', trigger: 'change' }],
        title: [{ required: true, message: '请填写反馈问题', trigger: 'blur' }],
        register_user_id: [{ validator: (rule, value, callback) => this.validateUserSelect(value, '请选择登记人', callback), trigger: 'change' }],
        handler_user_id: [{ validator: (rule, value, callback) => this.validateUserSelect(value, '请选择处理人', callback), trigger: 'change' }]
      },
      detailVisible: false,
      ledgerDetail: {},
      detailLoading: false,
      recordOriginStatus: '',
      taskLink: {
        work_id: '',
        class_id: ''
      },
      taskCreating: false,
      recordList: [],
      recordForm: {
        content: '',
        new_status: ''
      }
    }
  },
  computed: {
    ledgerAuth() {
      const allAuth = this.$store.getters.allAuth || {}
      return (allAuth.ledger && allAuth.ledger.ledger) || {}
    },
    canRead() {
      return !!this.ledgerAuth.index
    },
    canSave() {
      return !!this.ledgerAuth.save
    },
    canUpdate() {
      return !!this.ledgerAuth.update
    },
    canDelete() {
      return !!this.ledgerAuth.delete
    },
    isContractContext() {
      return this.crmType === 'contract'
    },
    contextContractId() {
      if (this.isContractContext) {
        return this.id || (this.detail && (this.detail.contract_id || this.detail.contractId || '')) || ''
      }
      return ''
    },
    contextCustomerId() {
      if (this.isContractContext) {
        return (this.detail && (this.detail.customer_id || this.detail.customerId || '')) || ''
      }
      return this.id || ''
    },
    currentContractSelection() {
      if (!this.isContractContext || !this.contextContractId) return null
      return this.normalizeContractSelection({
        contract_id: this.contextContractId,
        name: (this.detail && (this.detail.name || this.detail.num)) || '',
        num: (this.detail && this.detail.num) || '',
        customer_id: this.contextCustomerId || '',
        customer_name: (this.detail && this.detail.customer_name) || '',
        end_time: (this.detail && this.detail.end_time) || ''
      })
    },
    contractRelation() {
      return {
        moduleType: 'customer',
        customer_id: this.contextCustomerId || ''
      }
    },
    finishBadge() {
      return this.getFinishBadge()
    },
    isRecordLocked() {
      return this.recordOriginStatus === '已完成'
    }
  },
  watch: {
    id() {
      this.getList()
    },
    detail() {
      if (this.formVisible && this.isContractContext && !this.form.id) {
        this.form.contract_id = this.currentContractSelection ? [this.currentContractSelection] : []
        this.loadFeedbackContactsByCustomerId(this.getCurrentFormCustomerId(), false)
      }
    }
  },
  mounted() {
    this.getList()
    this.fetchCategoryOptions()
    this.fetchWorkOptions()
  },
  methods: {
    fetchCategoryOptions() {
      ledgerCategoryListAPI()
        .then(res => {
          const list = (res.data || []).filter(item => item && String(item).trim() !== '')
          if (list.length) {
            this.categoryOptions = list
          }
        })
        .catch(() => {})
    },
    fetchWorkOptions() {
      this.workLoading = true
      workIndexWorkListAPI()
        .then(res => {
          this.workOptions = Array.isArray(res.data) ? res.data : []
        })
        .catch(() => {
          this.workOptions = []
        })
        .finally(() => {
          this.workLoading = false
        })
    },
    fetchClassOptions(workId) {
      const id = workId || ''
      if (!id) {
        this.classOptions = []
        return
      }
      this.classLoading = true
      workWorkStatisticAPI({ work_id: id })
        .then(res => {
          const data = res.data || {}
          this.classOptions = data.classList || []
        })
        .catch(() => {
          this.classOptions = []
        })
        .finally(() => {
          this.classLoading = false
        })
    },
    handleWorkChange() {
      this.form.class_id = ''
      this.fetchClassOptions(this.form.work_id)
    },
    handleDetailWorkChange() {
      this.taskLink.class_id = ''
      this.fetchClassOptions(this.taskLink.work_id)
    },
    handleCategoryChange() {
      if (!this.isTaskCategory(this.form.category)) {
        this.form.work_id = ''
        this.form.class_id = ''
        this.classOptions = []
      }
    },
    isTaskCategory(category) {
      return ['系统BUG', '新增需求', '新需求'].includes(category)
    },
    createProjectTask() {
      if (!this.ledgerDetail || !this.ledgerDetail.ledger_id) return
      if (!this.isTaskCategory(this.ledgerDetail.category)) {
        this.$message.error('当前分类不支持生成任务')
        return
      }
      if (!this.taskLink.work_id || !this.taskLink.class_id) {
        this.$message.error('请选择项目和任务列表')
        return
      }
      this.taskCreating = true
      ledgerUpdateAPI({
        id: this.ledgerDetail.ledger_id,
        work_id: this.taskLink.work_id,
        class_id: this.taskLink.class_id
      }).then(() => {
        this.openDetail(this.ledgerDetail)
        this.getList()
      }).finally(() => {
        this.taskCreating = false
      })
    },
    openTaskDetail() {
      if (!this.ledgerDetail || !this.ledgerDetail.task_id) return
      const url = `/#/project/workbench?task_id=${this.ledgerDetail.task_id}`
      window.open(url, '_blank')
    },
    getList() {
      if (!this.canRead) return
      const contractId = this.contextContractId
      const customerId = this.contextCustomerId
      if (this.isContractContext && !contractId) {
        this.list = []
        return
      }
      if (!this.isContractContext && !customerId) {
        this.list = []
        return
      }
      this.loading = true
      const params = {
        page: 1,
        limit: 50
      }
      if (this.isContractContext) {
        params.contract_id = contractId
      } else {
        params.customer_id = customerId
      }
      ledgerIndexAPI(params).then(res => {
        this.loading = false
        this.nopermission = false
        const data = res.data || {}
        this.list = data.list || []
      }).catch(data => {
        if (data.code == 102) {
          this.nopermission = true
        }
        this.loading = false
      })
    },
    openCreate() {
      this.recordList = []
      this.recordForm = { content: '', new_status: '' }
      this.recordOriginStatus = ''
      const now = this.getNowTime()
      this.formTitle = '新建台账'
      const defaultContractSelection = this.currentContractSelection
      this.form = {
        customer_id: '',
        business_id: '',
        contract_id: defaultContractSelection ? [defaultContractSelection] : [],
        title: '',
        description: '',
        feedback_user: '',
        feedback_channel: '微信',
        category: '其他问题',
        status: '待处理',
        feedback_time: now,
        register_time: now,
        finish_time: '',
        work_id: '',
        class_id: '',
        register_user_id: this.getCurrentUserSelection(),
        handler_user_id: this.getCurrentUserSelection(),
        remark: ''
      }
      this.fetchClassOptions(this.form.work_id)
      this.loadFeedbackContactsByCustomerId(this.getCurrentFormCustomerId(), true)
      this.formVisible = true
      this.$nextTick(() => {
        this.$refs.ledgerForm && this.$refs.ledgerForm.clearValidate()
        if (this.$refs.titleInput && this.$refs.titleInput.focus) {
          this.$refs.titleInput.focus()
        }
      })
    },
    openEdit(row) {
      const now = this.getNowTime()
      this.formTitle = '编辑台账'
      const defaultContractSelection = this.currentContractSelection
      const rowContractSelection = row.contract_id ? [this.normalizeContractSelection({ contract_id: row.contract_id, num: row.contract_num || row.contract_name || '', name: row.contract_name || row.contract_num || '', customer_id: row.customer_id || '', customer_name: row.customer_name || '' })] : []
      this.form = {
        id: row.ledger_id,
        customer_id: '',
        business_id: '',
        contract_id: this.isContractContext && defaultContractSelection ? [defaultContractSelection] : rowContractSelection,
        title: row.title,
        description: row.description,
        feedback_user: row.feedback_user,
        feedback_channel: row.feedback_channel || '微信',
        category: row.category,
        status: row.status,
        feedback_time: row.feedback_time || row.register_time || now,
        register_time: row.register_time || now,
        finish_time: row.finish_time || '',
        work_id: row.work_id || '',
        class_id: row.class_id || '',
        register_user_id: row.register_user_id ? [{ id: row.register_user_id, realname: row.register_user_name || '' }] : this.getCurrentUserSelection(),
        handler_user_id: row.handler_user_id ? [{ id: row.handler_user_id, realname: row.handler_user_name || '' }] : this.getCurrentUserSelection(),
        remark: row.remark
      }
      this.fetchClassOptions(this.form.work_id)
      this.loadFeedbackContactsByCustomerId(this.getCurrentFormCustomerId(), !this.form.feedback_user)
      this.formVisible = true
      this.$nextTick(() => {
        this.$refs.ledgerForm && this.$refs.ledgerForm.clearValidate()
      })
    },
    handleContractChange(data) {
      if (this.isContractContext && this.currentContractSelection) {
        this.form.contract_id = [this.currentContractSelection]
      } else {
        const selected = data && Array.isArray(data.value) ? data.value : []
        this.form.contract_id = selected.map(item => this.normalizeContractSelection(item))
      }
      this.loadFeedbackContactsByCustomerId(this.getCurrentFormCustomerId(), false)
      if (this.$refs.ledgerForm) {
        this.$refs.ledgerForm.validateField('contract_id')
      }
    },
    handleHandlerChange(data) {
      this.form.handler_user_id = data.value || []
    },
    handleRegisterChange(data) {
      this.form.register_user_id = data.value || []
    },
    validateUserSelect(value, message, callback) {
      const list = Array.isArray(value) ? value : []
      if (!list.length) {
        callback(new Error(message))
        return
      }
      callback()
    },
    normalizeContractSelection(item) {
      const data = item || {}
      const name = data.name || data.contract_name || data.contractName || data.contractNum || data.num || ''
      return {
        ...data,
        name,
        contract_name: data.contract_name || name,
        contractNum: name
      }
    },
    getCachedUserInfo() {
      try {
        const raw = localStorage.getItem('loginUserInfo')
        return raw ? JSON.parse(raw) : {}
      } catch (e) {
        return {}
      }
    },
    getCurrentUserSelection() {
      const userInfo = this.$store.getters.userInfo || {}
      const cached = this.getCachedUserInfo()
      const userId = userInfo.id || userInfo.user_id || userInfo.userId || cached.id || cached.user_id || cached.userId || ''
      const userName = userInfo.realname || userInfo.username || cached.realname || cached.username || ''
      if (userId) {
        return [{ id: userId, realname: userName }]
      }
      return []
    },
    getFeedbackContactLabel(item) {
      if (!item) return ''
      return item.name || item.contacts_name || item.realname || item.mobile || item.telephone || item.phone || ''
    },
    getCurrentFormCustomerId() {
      const contractValue = Array.isArray(this.form.contract_id) && this.form.contract_id.length ? this.form.contract_id[0] : null
      if (contractValue) {
        return contractValue.customer_id || contractValue.customerId || ''
      }
      return this.contextCustomerId || ''
    },
    loadFeedbackContactsByCustomerId(customerId, autoFill = false) {
      if (!customerId) {
        this.feedbackContactsOptions = []
        this.feedbackContactsLoading = false
        return
      }
      this.feedbackContactsLoading = true
      crmCustomerQueryContactsAPI({ customer_id: customerId, pageType: 'all' })
        .then(res => {
          const list = (res.data && res.data.list) ? res.data.list : []
          this.feedbackContactsOptions = list.map(item => ({
            ...item,
            name: this.getFeedbackContactLabel(item)
          }))
          if (autoFill && !this.form.feedback_user && this.feedbackContactsOptions.length) {
            this.form.feedback_user = this.getFeedbackContactLabel(this.feedbackContactsOptions[0])
          }
        })
        .catch(() => {
          this.feedbackContactsOptions = []
        })
        .finally(() => {
          this.feedbackContactsLoading = false
        })
    },
    getNowTime() {
      if (this.$moment) {
        return this.$moment().format('YYYY-MM-DD HH:mm:ss')
      }
      const date = new Date()
      const pad = num => (num < 10 ? `0${num}` : `${num}`)
      return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`
    },
    formatDateTime(value) {
      if (!value) return ''
      if (typeof value === 'string') {
        return value.length > 19 ? value.slice(0, 19) : value
      }
      const num = Number(value)
      if (Number.isNaN(num)) return ''
      const ms = num > 1000000000000 ? num : num * 1000
      const date = new Date(ms)
      if (Number.isNaN(date.getTime())) return ''
      if (this.$moment) {
        return this.$moment(date).format('YYYY-MM-DD HH:mm:ss')
      }
      const pad = num2 => (num2 < 10 ? `0${num2}` : `${num2}`)
      return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`
    },
    submitForm() {
      this.$refs.ledgerForm.validate(async valid => {
        if (!valid) return
        const payload = { ...this.form }
        const contractValue = Array.isArray(this.form.contract_id) ? this.form.contract_id[0] : null
        payload.contract_id = contractValue ? (contractValue.contract_id || contractValue.id) : ''
        if (this.isContractContext && this.contextContractId) {
          payload.contract_id = this.contextContractId
        }
        payload.customer_id = ''
        payload.business_id = ''
        const handlerValue = Array.isArray(this.form.handler_user_id) ? this.form.handler_user_id[0] : null
        payload.handler_user_id = handlerValue ? handlerValue.id : ''
        const registerValue = Array.isArray(this.form.register_user_id) ? this.form.register_user_id[0] : null
        payload.register_user_id = registerValue ? registerValue.id : ''
        if (!payload.id) {
          await this.warnExpiredContractOnCreate(payload)
        }
        const request = payload.id ? ledgerUpdateAPI : ledgerSaveAPI
        this.formSubmitting = true
        request(payload).then(() => {
          this.formVisible = false
          this.getList()
          this.$bus.emit('crm-tab-num-update')
        }).finally(() => {
          this.formSubmitting = false
        })
      })
    },
    handleRowClick() {},
    openDetail(row) {
      if (!row || !row.ledger_id) return
      this.detailVisible = true
      this.detailLoading = true
      this.recordOriginStatus = row.status || ''
      this.taskLink = {
        work_id: row.work_id || '',
        class_id: row.class_id || ''
      }
      this.fetchClassOptions(this.taskLink.work_id)
      this.recordForm = { content: '', new_status: '' }
      this.recordList = []
      ledgerReadAPI({ id: row.ledger_id }).then(res => {
        this.ledgerDetail = res.data || {}
        this.taskLink = {
          work_id: this.ledgerDetail.work_id || '',
          class_id: this.ledgerDetail.class_id || ''
        }
        this.fetchClassOptions(this.taskLink.work_id)
        if (!this.recordOriginStatus) {
          this.recordOriginStatus = this.ledgerDetail.status || ''
        }
        this.detailLoading = false
      }).catch(() => {
        this.detailLoading = false
      })
      this.loadRecords(row.ledger_id)
    },
    loadRecords(ledgerId) {
      if (!ledgerId) {
        this.recordList = []
        return
      }
      ledgerRecordListAPI({ ledger_id: ledgerId }).then(res => {
        this.recordList = Array.isArray(res.data)
          ? res.data.map(item => ({ ...item, followup_id: item.followup_id || item.record_id }))
          : []
      }).catch(() => {
        this.recordList = []
      })
    },
    addRecord() {
      if (!this.recordForm.content) {
        this.$message.error('请填写处理说明')
        return
      }
      const params = {
        ledger_id: this.ledgerDetail.ledger_id,
        content: this.recordForm.content,
        new_status: this.recordForm.new_status
      }
      ledgerRecordAddAPI(params).then(() => {
        this.recordForm = { content: '', new_status: '' }
        this.loadRecords(this.ledgerDetail.ledger_id)
        this.getList()
      })
    },
    handleDelete(row) {
      this.$confirm('确认删除该台账记录吗？', '提示', {
        type: 'warning'
      }).then(() => {
        ledgerDeleteAPI({ id: row.ledger_id }).then(() => {
          this.getList()
          this.$bus.emit('crm-tab-num-update')
        })
      }).catch(() => {})
    },

    parseDateTime(value) {
      if (!value) return 0
      if (typeof value === 'number') return value > 1000000000000 ? value : value * 1000
      if (typeof value === 'string') {
        const ts = Date.parse(value.replace(/-/g, '/'))
        return Number.isNaN(ts) ? 0 : ts
      }
      return 0
    },
    normalizeToDateString(value) {
      if (!value) return ''
      if (typeof value === 'string') return value.slice(0, 10)
      const ts = this.parseDateTime(value)
      if (!ts) return ''
      if (this.$moment) return this.$moment(ts).format('YYYY-MM-DD')
      const date = new Date(ts)
      const pad = num => (num < 10 ? `0${num}` : `${num}`)
      return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
    },
    async warnExpiredContractOnCreate(payload) {
      const registerDate = this.normalizeToDateString(payload.feedback_time || this.form.feedback_time || payload.register_time || this.form.register_time)
      const contractId = payload.contract_id
      if (!contractId || !registerDate) return
      let endDate = ''
      const selectedContract = Array.isArray(this.form.contract_id) && this.form.contract_id.length ? this.form.contract_id[0] : null
      if (selectedContract) {
        endDate = this.normalizeToDateString(selectedContract.end_time || selectedContract.contract_end_time)
      }
      if (!endDate) {
        try {
          const res = await crmContractReadAPI({ id: contractId, team_only: 1 })
          const contract = res.data || {}
          endDate = this.normalizeToDateString(contract.end_time)
        } catch (e) {
          // ignore contract detail fetch errors for non-blocking warning
        }
      }
      if (endDate && endDate < registerDate) {
        this.$message.warning(`提醒：合同结束日期(${endDate})早于台账登记日期(${registerDate})`)
      }
    },
    getFinishBadge() {
      const start = this.parseDateTime(this.ledgerDetail.feedback_time || this.ledgerDetail.register_time)
      const end = this.parseDateTime(this.ledgerDetail.finish_time)
      if (!start || !end || end < start) return { text: '', type: '' }
      const diff = end - start
      const minutes = diff / 60000
      if (minutes <= 30) return { text: '30分钟内', type: 'success' }
      if (minutes <= 60) return { text: '60分钟内', type: 'warning' }
      if (minutes <= 1440) return { text: '1天内', type: 'info' }
      return { text: '', type: '' }
    }
  }
}
</script>

<style lang="scss" scoped>
@import '../styles/relativecrm.scss';
.ledger-form-dialog ::v-deep .el-dialog__body {
  padding: 16px 20px 62px;
  background: #f7f8fa;
  max-height: 70vh;
  overflow-y: auto;
  overflow-x: hidden;
}

.ledger-form-dialog ::v-deep .el-dialog {
  border-radius: 12px;
  overflow: hidden;
  max-width: 96vw;
}

.ledger-form-dialog ::v-deep .el-dialog__header {
  padding: 16px 22px;
  border-bottom: 1px solid #e8eaed;
  background: #f8fafc;
}

.ledger-form-dialog ::v-deep .el-dialog__footer {
  position: sticky;
  bottom: 0;
  background: #fff;
  border-top: 1px solid #ebeef5;
  padding: 12px 22px;
  z-index: 1;
}

.ledger-form {
  .el-form-item {
    margin-bottom: 8px;
  }

  .el-input,
  .el-select,
  .el-date-editor {
    width: 100%;
  }
}

.ledger-form-section {
  background: #fff;
  border-radius: 8px;
  border: 1px solid #ebeef5;
  padding: 12px 14px 6px;
  margin-bottom: 8px;
}

.ledger-form-section .section-title {
  font-size: 14px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 10px;
  padding-left: 10px;
  border-left: 3px solid #409EFF;
}

.form-item-strong ::v-deep .el-input__inner {
  font-size: 15px;
  font-weight: 600;
}

.form-item-strong {
  margin-bottom: 0;
}

.ledger-form ::v-deep .el-form-item__error {
  position: static;
  margin-top: 4px;
  line-height: 1.2;
}

.ledger-form-rich ::v-deep img {
  max-width: 100%;
  height: auto;
}

.ledger-form-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.ledger-form-footer .footer-left {
  font-size: 12px;
  color: #909399;
}

.ledger-form-footer .footer-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.ledger-form-dialog ::v-deep .el-form-item__label {
  padding-right: 8px;
}

.ledger-detail-dialog ::v-deep .el-dialog__body {
  padding: 20px 24px 10px;
}

.ledger-detail {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.detail-section {
  background: #fff;
  border: 1px solid #ebeef5;
  border-radius: 6px;
  padding: 14px 16px;
}

.section-title {
  font-size: 13px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 10px;
}

.kv-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px 18px;
}

.kv-item {
  display: flex;
  align-items: baseline;
  gap: 8px;
}

.kv-label {
  min-width: 72px;
  font-size: 12px;
  color: #909399;
}

.kv-value {
  font-size: 13px;
  color: #303133;
  font-weight: 500;
  word-break: break-all;
}

.time-badge {
  margin-left: 6px;
}

.text-block {
  background: #f7f8fa;
  border-radius: 6px;
  padding: 10px 12px;
  margin-bottom: 10px;
}

.text-block:last-child {
  margin-bottom: 0;
}

.text-label {
  font-size: 12px;
  color: #909399;
  margin-bottom: 6px;
}

.text-value {
  font-size: 13px;
  color: #303133;
  line-height: 1.6;
  white-space: pre-wrap;
}

.record-timeline {
  padding-left: 6px;
}

.record-timeline ::v-deep .el-timeline-item__timestamp {
  font-size: 12px;
  color: #909399;
}

.record-timeline ::v-deep .el-timeline-item__node {
  border-color: #dcdfe6;
}

.record-timeline ::v-deep .el-timeline-item.is-current .el-timeline-item__node {
  border-color: #409eff;
}

.record-content {
  padding: 6px 10px 10px;
  background: #f7f9fc;
  border-radius: 6px;
  border: 1px solid #eef2f7;
}

.record-user {
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 4px;
}

.record-text {
  font-size: 13px;
  line-height: 1.5;
  margin-bottom: 6px;
}

.record-status {
  font-size: 12px;
  color: #909399;
}

.record-actions-section {
  padding: 16px 18px 18px;
}

.record-input {
  display: block;
  width: 100%;
  margin-bottom: 12px;
}

.record-input ::v-deep .el-textarea__inner {
  width: 100%;
  min-height: 118px !important;
  line-height: 1.6;
  resize: vertical;
  box-sizing: border-box;
}

.record-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  align-items: center;
  gap: 10px;
}

.record-select {
  width: 240px;
  max-width: 100%;
}
.task-link-row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}
.task-link-row .el-select {
  width: 240px;
}
.task-link-actions {
  margin-top: 10px;
  display: flex;
  align-items: center;
  gap: 12px;
}
.task-link-tip {
  font-size: 12px;
  color: #909399;
}
</style>

<template>
  <slide-view
    v-empty="!canShowDetail"
    :listener-ids="listenerIDs"
    :no-listener-ids="noListenerIDs"
    :no-listener-class="noListenerClass"
    :body-style="{padding: 0, height: '100%'}"
    xs-empty-icon="nopermission"
    xs-empty-text="暂无权限"
    @afterEnter="viewAfterEnter"
    @close="hideView">
    <div
      v-loading="loading"
      ref="crmDetailMain"
      class="detail-main">
      <flexbox
        v-if="canShowDetail && detailData"
        direction="column"
        align="stretch"
        class="d-container">
        <c-r-m-detail-head
          :is-seas="isSeasDetail"
          :detail="detailData"
          :head-details="headDetails"
          :id="id"
          :pool_id="seasPoolId"
          :pool-auth="poolAuth"
          :crm-type="crmType"
          :page-list="pageList"
          @pageChange="pageChange"
          @handle="detailHeadHandle"
          @close="hideView">
          <template slot="name">
            <i v-if="detailData.is_lock == 1" class="wk wk-circle-password" />
            <el-tooltip v-if="!isSeasDetail" :content="detailData.star == 0 ? '添加关注' : '取消关注'" effect="dark" placement="top">
              <i
                :class="{active: detailData.star != 0}"
                class="wk wk-focus-on focus-icon"
                @click="toggleStar()" />
            </el-tooltip>
          </template>
          <div
            v-if="canAdjustCooperationStage"
            class="cooperation-stage-bar">
            <div class="cooperation-stage-bar__current">
              <span class="cooperation-stage-bar__label">合作阶段</span>
              <el-tag :type="cooperationStageTagType" size="small">{{ currentCooperationStage }}</el-tag>
            </div>
            <div class="cooperation-stage-bar__actions">
              <el-button
                v-if="nextCooperationStage"
                size="small"
                type="primary"
                @click="openCooperationStageDialog(nextCooperationStage)">推进至{{ nextCooperationStage }}</el-button>
              <el-dropdown trigger="click" @command="openCooperationStageDialog">
                <el-button size="small">调整阶段<i class="el-icon-arrow-down el-icon--right" /></el-button>
                <el-dropdown-menu slot="dropdown">
                  <el-dropdown-item
                    v-for="stage in adjustableCooperationStages"
                    :key="stage"
                    :command="stage">{{ stage }}</el-dropdown-item>
                </el-dropdown-menu>
              </el-dropdown>
            </div>
          </div>
        </c-r-m-detail-head>
        <flexbox
          class="d-container-bd"
          align="stretch">
          <el-tabs
            v-model="tabCurrentName"
            type="border-card"
            class="d-container-bd--left">
            <el-tab-pane
              v-for="(item, index) in tabNames"
              :key="index"
              :label="item.label"
              :name="item.name"
              lazy>
              <component
                :key="item.name === 'Activity' ? activityRefreshKey : item.name"
                :is="item.name"
                :detail="detailData"
                :type-list="logTyps"
                :id="id"
                :pool_id="seasPoolId"
                :handle="activityHandle"
                :is-seas="isSeasDetail"
                :crm-type="crmType"
                :contacts-id.sync="firstContactsId"
                @handle="detailHeadHandle" />
            </el-tab-pane>
          </el-tabs>
          <transition name="slide-fade">
            <el-tabs
              v-show="showImportInfo"
              value="chiefly-contacts"
              type="border-card"
              class="d-container-bd--right">
              <el-tab-pane
                label="重要信息"
                name="chiefly-contacts"
                lazy>
                <chiefly-contacts
                  :contacts-id="firstContactsId"
                  :id="id"
                  :pool_id="seasPoolId"
                  :crm-type="crmType"
                  :is-seas="isSeasDetail"
                  @add="addChieflyContacts" />
              </el-tab-pane>
            </el-tabs>
          </transition>
        </flexbox>
      </flexbox>
    </div>
    <el-button
      class="firse-button"
      @click="showImportInfo= !showImportInfo">重<br>要<br>信<br>息<br><i
        :class="{ 'is-reverse': !showImportInfo }"
        class="el-icon-arrow-right el-icon--right" /></el-button>
    <c-r-m-all-create
      v-if="isCreate"
      :action="createActionInfo"
      :crm-type="createCRMType"
      @save-success="editSaveSuccess"
      @close="isCreate=false" />
    <el-dialog
      :visible.sync="cooperationStageDialogVisible"
      :close-on-click-modal="false"
      append-to-body
      width="560px"
      custom-class="cooperation-stage-dialog"
      title="调整合作阶段">
      <el-form
        ref="cooperationStageForm"
        :model="cooperationStageForm"
        :rules="cooperationStageRules"
        label-position="top">
        <el-form-item label="合作阶段" prop="cooperation_stage">
          <el-select v-model="cooperationStageForm.cooperation_stage" style="width: 100%">
            <el-option v-for="stage in cooperationStages" :key="stage" :label="stage" :value="stage" />
          </el-select>
        </el-form-item>
        <template v-if="cooperationStageForm.cooperation_stage === verifiedStage">
          <div class="cooperation-stage-dialog__hint">首次进入已核实且资料完整后，将自动生成一条待审核绩效事实。</div>
          <div class="cooperation-stage-dialog__grid">
            <el-form-item label="发现人" prop="discover_user_id">
              <wk-user-select v-model="cooperationStageForm.discover_user_id" radio />
            </el-form-item>
            <el-form-item label="核实人" prop="verify_user_id">
              <wk-user-select v-model="cooperationStageForm.verify_user_id" radio />
            </el-form-item>
            <el-form-item label="核实时间" prop="verify_time">
              <el-date-picker
                v-model="cooperationStageForm.verify_time"
                type="datetime"
                value-format="yyyy-MM-dd HH:mm:ss"
                placeholder="请选择核实时间"
                style="width: 100%" />
            </el-form-item>
            <el-form-item label="核实结果" prop="verify_result">
              <el-select v-model="cooperationStageForm.verify_result" style="width: 100%">
                <el-option v-for="result in verifyResults" :key="result" :label="result" :value="result" />
              </el-select>
            </el-form-item>
          </div>
          <el-form-item label="核实说明" prop="verify_note">
            <el-input
              v-model.trim="cooperationStageForm.verify_note"
              :rows="4"
              type="textarea"
              maxlength="500"
              show-word-limit
              placeholder="请填写核实依据；无实质依据的记录不能进入绩效审核" />
          </el-form-item>
        </template>
        <template v-else-if="[effectiveContactStage, negotiatingStage].includes(cooperationStageForm.cooperation_stage)">
          <div class="cooperation-stage-dialog__hint">
            <template v-if="cooperationStageForm.cooperation_stage === effectiveContactStage">
              有效联系要求具体人员真实回复并有明确下一步；审核通过后生成200元业务获取奖金池阶段预发候选。
            </template>
            <template v-else>
              洽谈中必须已完成正式产品介绍或合作交流会议；审核通过后生成500元业务获取奖金池阶段预发候选。
            </template>
          </div>
          <el-form-item
            :label="cooperationStageForm.cooperation_stage === effectiveContactStage ? '有效联系记录' : '正式交流记录'"
            prop="stage_evidence_note">
            <el-input
              v-model.trim="cooperationStageForm.stage_evidence_note"
              :rows="4"
              :placeholder="cooperationStageForm.cooperation_stage === effectiveContactStage ? '请写明具体联系人、真实回复内容和明确下一步' : '请写明交流对象、产品介绍或会议内容、交流结论和下一步'"
              type="textarea"
              maxlength="500"
              show-word-limit />
          </el-form-item>
        </template>
      </el-form>
      <span slot="footer">
        <el-button @click="cooperationStageDialogVisible = false">取消</el-button>
        <el-button :loading="cooperationStageSubmitting" type="primary" @click="submitCooperationStage">确认调整</el-button>
      </span>
    </el-dialog>
  </slide-view>
</template>

<script>
import { crmCustomerReadAPI, crmCustomerPoolQueryAuthAPI, crmCustomerCooperationStageAPI } from '@/api/crm/customer'

import SlideView from '@/components/SlideView'
import CRMDetailHead from '../components/CRMDetailHead'
import Activity from '../components/Activity' // 活动
import ChieflyContacts from '../components/ChieflyContacts' // 主要联系人
import CRMEditBaseInfo from '../components/CRMEditBaseInfo' // 基本信息
import RelativeContacts from '../components/RelativeContacts' // 相关联系人
import RelativeBusiness from '../components/RelativeBusiness' // 相关商机
import RelativeContract from '../components/RelativeContract' // 相关合同
import RelativeReturnMoney from '../components/RelativeReturnMoney' // 相关回款
import RelativeFiles from '../components/RelativeFiles' // 相关附件
import RelativeHandle from '../components/RelativeHandle' // 相关操作
import RelativeTeam from '../components/RelativeTeam' // 团队成员
import RelativeVisit from '../components/RelativeVisit' // 回访
import RelativeLedger from '../components/RelativeLedger' // 台账
import RelativeInvoice from '../components/RelativeInvoice' // 发票
import RelativeFinance from '../components/RelativeFinance' // 收支

import CRMAllCreate from '../components/CRMAllCreate' // 新建页面
import WkUserSelect from '@/components/NewCom/WkUserSelect'
import DetailMixin from '../mixins/Detail'
import {
  COOPERATION_MAIN_STAGES,
  COOPERATION_STAGES,
  EFFECTIVE_CONTACT_STAGE,
  NEGOTIATING_STAGE,
  VERIFIED_STAGE,
  VERIFY_RESULTS,
  getCooperationStageTagType,
  isCooperationEnterprise,
  shouldPrioritizeCooperation
} from './cooperation'

export default {
  // 客户管理 的 客户详情
  name: 'CustomerDetail',
  components: {
    SlideView,
    Activity,
    ChieflyContacts,
    CRMDetailHead,
    CRMEditBaseInfo,
    RelativeContacts,
    RelativeBusiness,
    RelativeContract,
    RelativeReturnMoney,
    RelativeFiles,
    RelativeHandle,
    RelativeTeam,
    RelativeVisit,
    RelativeLedger,
    RelativeFinance,
    CRMAllCreate,
    WkUserSelect,
    RelativeInvoice
  },
  mixins: [DetailMixin],
  props: {
    // 详情信息id
    id: [String, Number],
    pool_id: [String, Number],
    // 监听的dom 进行隐藏详情
    listenerIDs: {
      type: Array,
      default: () => {
        return ['crm-main-container']
      }
    },
    // 不监听
    noListenerIDs: {
      type: Array,
      default: () => {
        return []
      }
    },
    noListenerClass: {
      type: Array,
      default: () => {
        return ['el-table__body']
      }
    }
  },
  data() {
    return {
      // 展示加载loading
      loading: false,
      crmType: 'customer',
      headDetails: [
        { title: '客户级别', value: '' },
        { title: '成交状态', value: '' },
        { title: '负责人', value: '' },
        { title: '更新时间', value: '' }
      ],
      tabCurrentName: 'Activity',
      // 编辑操作
      createActionInfo: null,
      createCRMType: 'customer',
      isCreate: false,
      // 活动操作
      // 展示重要信息
      showImportInfo: true,
      // 主要联系人信息
      firstContactsId: '',

      // 公海规则权限
      poolAuth: {},
      activityRefreshKey: 0,
      cooperationStageDialogVisible: false,
      cooperationStageSubmitting: false,
      cooperationStages: COOPERATION_STAGES,
      verifiedStage: VERIFIED_STAGE,
      effectiveContactStage: EFFECTIVE_CONTACT_STAGE,
      negotiatingStage: NEGOTIATING_STAGE,
      verifyResults: VERIFY_RESULTS,
      cooperationStageForm: {
        cooperation_stage: '',
        discover_user_id: '',
        verify_user_id: '',
        verify_time: '',
        verify_result: '',
        verify_note: '',
        stage_evidence_note: ''
      },
      cooperationStageRules: {
        cooperation_stage: [{ required: true, message: '请选择合作阶段', trigger: 'change' }],
        discover_user_id: [{ required: true, message: '请选择发现人', trigger: 'change' }],
        verify_user_id: [{ required: true, message: '请选择核实人', trigger: 'change' }],
        verify_time: [{ required: true, message: '请选择核实时间', trigger: 'change' }],
        verify_result: [{ required: true, message: '请选择核实结果', trigger: 'change' }],
        verify_note: [{ required: true, message: '请填写核实说明', trigger: 'blur' }],
        stage_evidence_note: [
          { required: true, message: '请填写本阶段的业务证据', trigger: 'blur' },
          { min: 10, message: '业务证据至少填写10个字', trigger: 'blur' }
        ]
      }
    }
  },
  computed: {
    currentCooperationStage() {
      return (this.detailData && this.detailData.cooperation_stage) || '初筛'
    },
    cooperationStageTagType() {
      return getCooperationStageTagType(this.currentCooperationStage)
    },
    nextCooperationStage() {
      const index = COOPERATION_MAIN_STAGES.indexOf(this.currentCooperationStage)
      return index >= 0 && index < COOPERATION_MAIN_STAGES.length - 1
        ? COOPERATION_MAIN_STAGES[index + 1]
        : ''
    },
    adjustableCooperationStages() {
      const currentIndex = COOPERATION_MAIN_STAGES.indexOf(this.currentCooperationStage)
      return COOPERATION_STAGES.filter(stage => {
        if (stage === this.currentCooperationStage) return false
        const targetIndex = COOPERATION_MAIN_STAGES.indexOf(stage)
        return currentIndex < 0 || targetIndex < 0 || targetIndex <= currentIndex + 1
      })
    },
    canAdjustCooperationStage() {
      return !!(this.detailData &&
        !this.isSeasDetail &&
        isCooperationEnterprise(this.detailData.cooperation_type) &&
        this.crm.customer && this.crm.customer.update)
    },
    ledgerAuth() {
      const allAuth = this.$store.getters.allAuth || {}
      return (allAuth.ledger && allAuth.ledger.ledger) || {}
    },
    canLedgerRead() {
      return !!this.ledgerAuth.index
    },
    financeRecordAuth() {
      const allAuth = this.$store.getters.allAuth || {}
      return (allAuth.finance && allAuth.finance.record) || {}
    },
    canFinanceRecord() {
      return !!this.financeRecordAuth.index
    },
    /**
     * 活动操作
     */
    activityHandle() {
      let temps = [
        {
          type: 'task',
          label: '创建任务'
        },
        {
          type: 'contacts',
          label: '创建联系人'
        },
        {
          type: 'business',
          label: '创建商机'
        },
        {
          type: 'contract',
          label: '创建合同'
        },
        {
          type: 'receivables',
          label: '创建回款'
        }
      ]

      if (this.canCreateFollowRecord) {
        temps = [{
          type: 'log',
          label: '写跟进'
        }].concat(temps)
      }

      return temps
    },

    /**
     * tabs
     */
    tabNames() {
      var tempsTabs = []
      tempsTabs.push({ label: '活动', name: 'Activity' })
      if (this.crm.customer && this.crm.customer.read) {
        tempsTabs.push({ label: '详细资料', name: 'CRMEditBaseInfo' })
      }
      if (this.crm.contacts && this.crm.contacts.index) {
        tempsTabs.push({ label: this.getTabName('联系人', this.tabsNumber.contactCount), name: 'RelativeContacts' })
      }

      tempsTabs.push({ label: this.getTabName('团队成员', this.tabsNumber.memberCount), name: 'RelativeTeam' })

      if (this.crm.business && this.crm.business.index) {
        tempsTabs.push({ label: this.getTabName('商机', this.tabsNumber.businessCount), name: 'RelativeBusiness' })
      }

      if (this.crm.contract && this.crm.contract.index) {
        tempsTabs.push({ label: this.getTabName('合同', this.tabsNumber.contractCount), name: 'RelativeContract' })
      }
      if (this.crm.receivables && this.crm.receivables.index) {
        tempsTabs.push({ label: this.getTabName('回款', this.tabsNumber.receivablesCount), name: 'RelativeReturnMoney' })
      }
      if (this.crm.visit && this.crm.visit.index) {
        tempsTabs.push({ label: this.getTabName('回访', this.tabsNumber.returnVisitCount), name: 'RelativeVisit' })
      }
      if (this.canLedgerRead) {
        tempsTabs.push({ label: this.getTabName('台账', this.tabsNumber.ledgerCount), name: 'RelativeLedger' })
      }
      if (this.canFinanceRecord) {
        tempsTabs.push({ label: this.getTabName('收支', this.tabsNumber.financeCount), name: 'RelativeFinance' })
      }

      if (this.crm.invoice && this.crm.invoice.index) {
        tempsTabs.push({ label: this.getTabName('发票', this.tabsNumber.invoiceCount), name: 'RelativeInvoice' })
      }

      tempsTabs.push({ label: this.getTabName('附件', this.tabsNumber.fileCount), name: 'RelativeFiles' })
      tempsTabs.push({ label: '操作记录', name: 'RelativeHandle' })
      return tempsTabs
    },
    /**
     * isSeas 是从公海模块传入的？配合详情is_pool字段确定
     */
    isSeasDetail() {
      if (this.detailData && this.detailData.hasOwnProperty('is_pool')) {
        return this.detailData.is_pool == 1
      }
      return this.isSeas
    },

    /**
     * 公海id
     */
    seasPoolId() {
      if (this.poolAuth && this.poolAuth.pool_id) {
        return this.poolAuth.pool_id
      }
      return this.pool_id
    },

    /**
     * 根据记录筛选
     */
    logTyps() {
      return [
        {
          icon: 'all',
          color: '#2362FB',
          command: '',
          label: '全部活动'
        },
        {
          icon: 'customer',
          color: '#487DFF',
          command: 2,
          label: '客户'
        },
        {
          icon: 'o-task',
          color: '#D376FF',
          command: 11,
          label: '任务'
        },
        {
          icon: 'business',
          color: '#FB9323',
          command: 5,
          label: '商机'
        },
        {
          icon: 'contract',
          color: '#FD5B4A',
          command: 6,
          label: '合同'
        },
        {
          icon: 'contacts',
          color: '#27BA4A',
          command: 3,
          label: '联系人'
        },
        {
          icon: 'receivables',
          color: '#FFB940',
          command: 7,
          label: '回款'
        },
        {
          icon: 'log',
          color: '#5864FF',
          command: 8,
          label: '日志'
        },
        {
          icon: 'record',
          color: '#19B5F6',
          command: 13,
          label: '台账'
        },
        {
          icon: 'approve',
          color: '#9376FF',
          command: 9,
          label: '审批'
        }
      ]
    }
  },
  watch: {},
  mounted() {},
  methods: {
    cooperationUserId(value) {
      const selected = Array.isArray(value) ? value[0] : value
      if (selected && typeof selected === 'object') {
        return Number(selected.id || selected.user_id || selected.userId) || ''
      }
      return Number(selected) || ''
    },
    formatCooperationDateTime(value) {
      if (!value) return ''
      if (typeof value === 'string' && value.includes('-')) return value
      const numericValue = Number(value)
      const date = new Date(numericValue * (numericValue < 100000000000 ? 1000 : 1))
      if (Number.isNaN(date.getTime())) return ''
      const pad = number => String(number).padStart(2, '0')
      return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`
    },
    openCooperationStageDialog(stage) {
      const currentUserId = Number((this.$store.getters.userInfo || {}).id) || ''
      this.cooperationStageForm = {
        cooperation_stage: stage,
        discover_user_id: this.cooperationUserId(this.detailData.discover_user_id) || currentUserId,
        verify_user_id: this.cooperationUserId(this.detailData.verify_user_id) || currentUserId,
        verify_time: this.formatCooperationDateTime(this.detailData.verify_time),
        verify_result: this.detailData.verify_result || '',
        verify_note: this.detailData.verify_note || '',
        stage_evidence_note: ''
      }
      this.cooperationStageDialogVisible = true
      this.$nextTick(() => {
        if (this.$refs.cooperationStageForm) this.$refs.cooperationStageForm.clearValidate()
      })
    },
    submitCooperationStage() {
      this.$refs.cooperationStageForm.validate(valid => {
        if (!valid) return
        this.cooperationStageSubmitting = true
        crmCustomerCooperationStageAPI({ id: this.id, ...this.cooperationStageForm })
          .then(() => {
            this.cooperationStageDialogVisible = false
            this.$message.success('合作阶段已更新，活动记录已生成')
            this.activityRefreshKey++
            this.editSaveSuccess()
          })
          .catch(error => {
            if (error && error.message === '接口不存在') {
              this.$message.error('合作阶段接口未加载，请刷新页面后重试')
            }
          })
          .finally(() => {
            this.cooperationStageSubmitting = false
          })
      })
    },
    /**
     * 详情
     */
    getDetial() {
      this.firstContactsId = ''
      this.loading = true
      const params = {
        id: this.id
      }

      if (this.pool_id) {
        params.pool_id = this.pool_id
        params.customer_id = this.id
      }

      crmCustomerReadAPI(params)
        .then(res => {
          this.loading = false
          const resData = res.data || {}
          this.detailData = resData
          if (resData.dataAuth === 0) return

          this.firstContactsId = this.detailData.contacts_id
          // 公海权限
          this.poolAuth = resData.poolAuthList || {}

          crmCustomerPoolQueryAuthAPI({ pool_id: this.pool_id }).then(res => {
            this.poolAuth = res.data || {}
          })

          const dealItem = { title: '成交状态', value: '' }
          if (this.detailData.deal_status === null || this.detailData.deal_status === '' || this.detailData.deal_status === undefined) {
            dealItem.showIcon = false
            dealItem.value = ''
          } else {
            dealItem.showIcon = true
            if (this.detailData.deal_status == '已成交') {
              dealItem.icon = 'wk wk-success deal-suc'
              dealItem.style = {
                fontSize: '14px',
                color: '#20b559',
                marginRight: '3px'
              }
              dealItem.value = '已成交'
            } else {
              dealItem.icon = 'wk wk-close deal-un'
              dealItem.style = {
                fontSize: '14px',
                color: '#f95a5a',
                marginRight: '3px'
              }
              dealItem.value = '未成交'
            }
          }

          const ownerInfo = this.detailData.owner_user_id_info || {}
          const basicHeadDetails = [
            { title: '客户级别', value: this.detailData.level || '' },
            dealItem
          ]
          const cooperationHeadDetails = []
          if (isCooperationEnterprise(this.detailData.cooperation_type)) {
            cooperationHeadDetails.push(
              {
                title: '客户类型',
                value: this.detailData.cooperation_type,
                showIcon: true,
                icon: 'el-icon-office-building',
                style: { color: '#5b6bff', marginRight: '5px' }
              },
              {
                title: '合作阶段',
                value: this.detailData.cooperation_stage || '',
                showIcon: !!this.detailData.cooperation_stage,
                icon: 'el-icon-connection',
                style: { color: '#20b559', marginRight: '5px' }
              }
            )
          }
          const headDetails = shouldPrioritizeCooperation(this.detailData.cooperation_stage)
            ? cooperationHeadDetails.concat(basicHeadDetails)
            : basicHeadDetails.concat(cooperationHeadDetails)
          headDetails.push(
            { title: '负责人', value: this.isSeasDetail ? (this.detailData.before_owner_user_name || '') : (ownerInfo.realname || '') },
            { title: '更新时间', value: this.detailData.create_time }
          )
          this.headDetails = headDetails
        })
        .catch(() => {
          this.loading = false
          this.hideView()
        })
    },

    /**
     * 关闭
     */
    hideView() {
      this.$emit('hide-view')
    },

    /**
     * 主要联系人添加
     */
    addChieflyContacts() {
      this.createCRMType = 'contacts'
      this.createActionInfo = {
        type: 'relative',
        crmType: this.crmType,
        data: { customer: this.detailData }
      }
      this.isCreate = true
    },
    /**
     * 顶部头部操作
     * @param {*} data
     */
    detailHeadHandleClick(data) {
      if (data.type === 'edit') {
        this.createCRMType = 'customer'
        this.createActionInfo = {
          type: 'update',
          id: this.id,
          batchId: this.detailData.batchId
        }
        this.isCreate = true
        return false
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.slide-fade-leave-active {
  will-change: transform;
  transition: all 0.1s;
}
.slide-fade-leave-to {
  transform: translateX(100%);
  opacity: 0;
}

.wk-circle-password  {
  background-color: #f56c6c;
  color: white;
  margin-left: 5px;
  border-radius: 3px;
  font-size: 12px;
  padding: 2px;
  transform: scale(0.6);
}

.cooperation-stage-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 46px;
  margin: 0 24px 12px;
  padding: 0 14px;
  border: 1px solid #e6eaff;
  border-radius: 6px;
  background: #f7f8ff;

  &__current,
  &__actions {
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
  }

  &__label {
    color: #6b778c;
    font-size: 13px;
  }
}
@import '../styles/crmdetail.scss';
</style>

<style lang="scss">
.cooperation-stage-dialog {
  .el-dialog__body {
    padding: 18px 24px 6px;
  }

  .el-form-item {
    margin-bottom: 17px;
  }

  .el-form-item__label {
    padding-bottom: 6px;
    line-height: 20px;
  }

  &__hint {
    margin: 0 0 14px;
    padding: 10px 12px;
    border-radius: 5px;
    color: #536174;
    background: #f4f6ff;
    font-size: 13px;
  }

  &__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0 16px;
  }
}
</style>

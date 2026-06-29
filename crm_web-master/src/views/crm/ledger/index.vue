<template>
  <div
    v-empty="!canRead"
    xs-empty-icon="nopermission"
    xs-empty-text="暂无权限"
    class="ledger-page">
    <el-alert
      v-show="showMobileHint"
      :closable="true"
      class="mobile-ledger-hint"
      type="info"
      show-icon
      title="当前为移动设备，已推荐使用移动台账">
      <router-link to="/m/ledger">进入移动台账</router-link>
      <span class="mobile-ledger-hint__sep">|</span>
      <router-link to="/m/ledger/quick">快捷录入</router-link>
    </el-alert>
    <ledger-status-dashboard :stats="stats" @filter="quickFilter" />
    <div v-show="canRead" class="filter-bar">
      <el-form :inline="true" :model="filters" class="filter-form">
        <el-form-item label="客户">
          <el-select
            v-model="filters.customer_keyword"
            :popper-append-to-body="true"
            :remote-method="fetchCustomerOptions"
            :loading="customerLoading"
            filterable
            remote
            clearable
            placeholder="客户名称/ID"
            popper-class="ledger-customer-popper"
            class="filter-customer-select"
            @visible-change="handleCustomerVisibleChange"
            @change="handleCustomerFilterChange">
            <el-option
              v-for="item in customerOptions"
              :key="item.customer_id"
              :label="item.customer_display_name"
              :value="String(item.customer_id)">
              <div class="customer-option-row">
                <span class="customer-option-short">{{ item.customer_display_name }}</span>
                <span :title="item.customer_name" class="customer-option-full">{{ item.customer_name }}</span>
              </div>
            </el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="合同">
          <el-select
            v-model="filters.contract_id"
            :loading="contractLoading"
            :disabled="!filters.customer_id"
            clearable
            filterable
            placeholder="先选择客户"
            class="filter-contract-select"
            @visible-change="handleContractVisibleChange"
            @change="handleSearch">
            <el-option
              v-for="item in contractOptions"
              :key="item.contract_id"
              :label="item.contract_display_name"
              :value="String(item.contract_id)">
              <div class="customer-option-row">
                <span class="customer-option-short">{{ item.contract_display_name }}</span>
                <span :title="item.contract_full_name" class="customer-option-full">{{ item.contract_full_name }}</span>
              </div>
            </el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="处理人">
          <xh-user-cell
            :value="filterHandlerUser"
            :radio="true"
            class="filter-user-cell"
            placeholder="处理人"
            @value-change="handleFilterHandlerChange" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filters.status" clearable placeholder="全部">
            <el-option v-for="item in statusOptions" :key="item" :label="item" :value="item" />
          </el-select>
        </el-form-item>
        <el-form-item label="分类">
          <el-select v-model="filters.category" clearable placeholder="全部">
            <el-option v-for="item in categoryOptions" :key="item" :label="item" :value="item" />
          </el-select>
        </el-form-item>
        <el-form-item label="时间">
          <el-date-picker
            v-model="filters.feedback_date"
            :append-to-body="true"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="yyyy-MM-dd"
            popper-class="ledger-date-picker"
            class="filter-date-range ledger-date-range"
            clearable />
        </el-form-item>
        <el-form-item label="关键词">
          <el-input v-model.trim="filters.keyword" placeholder="问题/描述/反馈人/备注" clearable />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button type="text" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
      <div class="filter-actions">
        <el-button v-if="canRead" :loading="exportLoading" @click="handleExport">导出Excel</el-button>
        <el-button v-if="canSave" type="primary" @click="openCreate">新建台账</el-button>
      </div>
    </div>

    <el-table
      v-loading="loading"
      :data="list"
      stripe
      border
      highlight-current-row
      size="small"
      class="ledger-table"
      @row-dblclick="openDetail">
      <el-table-column prop="ledger_id" label="ID" width="58" align="center" fixed="left" />
      <el-table-column label="关联对象" min-width="112" show-overflow-tooltip>
        <template slot-scope="scope">
          {{ relationName(scope.row) }}
        </template>
      </el-table-column>
      <el-table-column label="反馈问题" min-width="186" show-overflow-tooltip>
        <template slot-scope="scope">
          <span>{{ formatIssueTitle(scope.row) }}</span>
        </template>
      </el-table-column>
      <el-table-column label="台账描述" min-width="332" show-overflow-tooltip>
        <template slot-scope="scope">
          <div :title="descriptionCellTitle(scope.row)" class="desc-preview-wrap">
            <div class="desc-preview">
              <span class="desc-badge">描述</span>{{ descriptionPreview(scope.row.description) || '—' }}
            </div>
            <div v-if="completedReplyPreview(scope.row)" class="desc-preview desc-preview--reply">
              <span class="desc-badge desc-badge--reply">回答</span>{{ completedReplyPreview(scope.row) }}
            </div>
          </div>
        </template>
      </el-table-column>
      <el-table-column prop="category" label="问题分类" width="86" />
      <el-table-column prop="status" label="处理状态" width="78">
        <template slot-scope="scope">
          <el-tag :type="statusTagType(scope.row.status)" :class="statusTagClass(scope.row.status)" size="mini">{{ scope.row.status }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="feedback_user" label="反馈人" width="132" />
      <el-table-column prop="register_user_name" label="登记人" width="72" />
      <el-table-column prop="handler_user_name" label="处理人" width="72" />
      <el-table-column label="反馈时间" width="110" show-overflow-tooltip>
        <template slot-scope="scope">
          {{ formatListTime(scope.row.feedback_time || scope.row.register_time) }}
        </template>
      </el-table-column>
      <el-table-column label="完成时间" width="110" show-overflow-tooltip>
        <template slot-scope="scope">
          {{ formatListTime(scope.row.status === '已完成' ? scope.row.finish_time : '') }}
        </template>
      </el-table-column>
      <el-table-column label="操作" width="136" fixed="right">
        <template slot-scope="scope">
          <el-button type="text" @click="openDetail(scope.row)">详情</el-button>
          <el-button v-if="canUpdate" type="text" @click="openEdit(scope.row)">编辑</el-button>
          <el-button v-if="canDelete" type="text" @click="handleDelete(scope.row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="pager-bar">
      <el-pagination
        :current-page="page"
        :page-sizes="[50, 100, 200, 500]"
        :page-size.sync="limit"
        :total="total"
        :pager-count="5"
        background
        layout="prev, pager, next, sizes, total, jumper"
        @size-change="handlePageSizeChange"
        @current-change="handlePageChange"
      />
    </div>

    <el-dialog :title="formTitle" :visible.sync="formVisible" :before-close="handleFormBeforeClose" width="1120px" append-to-body class="ledger-form-dialog">
      <el-form ref="ledgerForm" :model="form" :rules="rules" label-width="92px" class="ledger-form" @validate="handleFormValidate">
        <div class="ledger-form-section section-related">
          <div class="section-title">关联对象</div>
          <el-form-item prop="contract_id" class="form-item-full relation-form-item">
            <div v-if="selectedContract" class="relation-summary-card">
              <div class="relation-summary-main">
                <div class="relation-summary-row">
                  <span class="relation-summary-label">合同：</span>
                  <span class="relation-summary-value">{{ selectedContractLabel }}</span>
                </div>
                <div v-if="selectedContractCustomer" class="relation-summary-row">
                  <span class="relation-summary-label">客户：</span>
                  <span class="relation-summary-value">{{ selectedContractCustomer }}</span>
                </div>
              </div>
              <el-button type="text" @click="reselectContract">重新选择</el-button>
            </div>
            <div v-if="!selectedContract || quickVisible" class="quick-select-row">
              <el-select v-model="quickSearchType" placeholder="仅支持合同" class="quick-select-type">
                <el-option label="合同" value="contract" />
              </el-select>
              <el-input
                v-model.trim="quickKeyword"
                :placeholder="quickSearchPlaceholder"
                clearable
                @keyup.enter.native="quickSelectRelation(quickSearchType)" />
              <el-button
                type="primary"
                @click="quickSelectRelation(quickSearchType)">搜索并选择</el-button>
            </div>
            <div v-if="quickVisible" class="quick-result">
              <div class="quick-result__header">
                <span>选择列表</span>
                <el-radio-group v-model="quickContractScope" size="mini" @change="refreshQuickResults">
                  <el-radio-button label="recent">近两年优先</el-radio-button>
                  <el-radio-button label="all">全部</el-radio-button>
                </el-radio-group>
                <el-button type="text" @click="quickVisible=false">收起</el-button>
              </div>
              <div v-loading="quickLoading" class="quick-result__body">
                <div v-if="!quickLoading && quickResults.length === 0" class="quick-result__empty">暂无匹配数据</div>
                <div
                  v-for="(item, index) in quickResults"
                  :key="quickResultKey(item, index)"
                  class="quick-result__item"
                  @click="applyQuickPick(item)">
                  <span class="quick-result__name">{{ quickResultName(item) }}</span>
                  <span class="quick-result__meta">{{ quickResultMeta(item) }}</span>
                </div>
              </div>
            </div>
          </el-form-item>
        </div>

        <div class="ledger-form-section section-track">
          <div class="section-title">归类与跟踪</div>
          <div class="track-grid">
            <div class="track-grid-row">
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
              <el-form-item label="反馈渠道">
                <el-select v-model="form.feedback_channel" placeholder="请选择">
                  <el-option v-for="item in channelOptions" :key="item" :label="item" :value="item" />
                </el-select>
              </el-form-item>
              <el-form-item label="问题分类">
                <el-select v-model="form.category" placeholder="请选择" @change="handleCategoryChange">
                  <el-option v-for="item in categoryOptions" :key="item" :label="item" :value="item" />
                </el-select>
              </el-form-item>
              <el-form-item label="问题状态">
                <el-select v-model="form.status" placeholder="请选择" @change="handleFormStatusChange">
                  <el-option v-for="item in statusOptions" :key="item" :label="item" :value="item" />
                </el-select>
              </el-form-item>
            </div>
            <div class="track-grid-row track-grid-row--3">
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
              <el-form-item label="登记人" prop="register_user_id">
                <xh-user-cell
                  :value="form.register_user_id"
                  :radio="true"
                  placeholder="选择登记人"
                  @value-change="handleRegisterChange" />
              </el-form-item>
              <el-form-item label="处理人" prop="handler_user_id">
                <xh-user-cell
                  :value="form.handler_user_id"
                  :radio="true"
                  placeholder="选择处理人"
                  @value-change="handleHandlerChange" />
              </el-form-item>
            </div>
            <div v-if="isTaskCategory(form.category)" class="track-grid-row track-grid-row--task">
              <el-form-item label="关联项目">
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
            </div>
          </div>
        </div>

        <div class="ledger-form-section section-core">
          <div class="section-title">问题内容</div>
          <el-form-item :show-message="false" prop="title" class="form-item-strong">
            <span slot="label" class="title-label-row">
              <span class="title-label-text">问题标题</span>
              <span v-if="titleErrorMsg" class="title-label-error">{{ titleErrorMsg }}</span>
            </span>
            <el-input ref="titleInput" v-model.trim="form.title" placeholder="请输入简洁的问题标题" class="ledger-title-input" />
          </el-form-item>
          <el-form-item label="问题描述" class="form-item-full">
            <tinymce
              v-if="formVisible"
              v-model="form.description"
              :height="170"
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

        <div v-if="isFormCompleted" class="ledger-form-section section-completion">
          <div class="section-title section-title-success">完成信息</div>
          <el-row :gutter="16">
            <el-col :xs="24" :sm="7">
              <el-form-item label="完成时间" class="completion-inline-field">
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
            <el-col :xs="24" :sm="17">
              <el-form-item label="原因及处理结果" class="form-item-full completion-reply-item">
                <el-input v-model.trim="form.reply_content" :rows="3" type="textarea" placeholder="输入处理过程、原因、结果" />
              </el-form-item>
            </el-col>
          </el-row>
        </div>

        <div v-if="isFormClosed" class="ledger-form-section section-closed">
          <div class="section-title section-title-danger">关闭信息</div>
          <el-form-item label="关闭原因" prop="close_reason" class="form-item-full close-reason-item">
            <el-input
              v-model.trim="form.close_reason"
              :rows="3"
              type="textarea"
              placeholder="说明关闭原因，如重复反馈、无效问题、客户放弃等" />
          </el-form-item>
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

    <el-dialog :visible.sync="detailVisible" title="台账详情" width="1080px" append-to-body class="ledger-detail-dialog">
      <div class="ledger-detail">
        <section class="detail-section">
          <div class="section-title header-flex">
            <div class="header-left">
              基础信息
              <span class="header-id">#{{ detail.ledger_id }}</span>
            </div>
            <div class="header-right">
              <div class="header-meta-chip" title="客户">
                <i class="el-icon-office-building"/>
                <span class="header-value">{{ detail.customer_name || '—' }}</span>
              </div>
              <div class="header-meta-chip" title="反馈人">
                <i class="el-icon-user"/>
                <span class="header-value">{{ detail.feedback_user || '—' }}</span>
              </div>
            </div>
          </div>
          <div class="detail-summary-card">
            <div class="detail-title-label">反馈问题</div>
            <div class="detail-title-line">
              <div class="detail-title-value">{{ detail.title || '—' }}</div>
              <el-tag
                :type="statusTagType(detail.status)"
                :class="statusTagClass(detail.status)"
                size="small"
                effect="plain"
                class="detail-title-status">
                {{ detail.status || '—' }}
              </el-tag>
            </div>
            <div v-if="detail.business_name || detail.contract_name || detail.contract_num" class="detail-relation-line">
              <el-tag v-if="detail.business_name" type="warning" size="mini" effect="plain">商机</el-tag>
              <el-tag v-else-if="detail.contract_name || detail.contract_num" size="mini" effect="plain">合同</el-tag>
              <span>{{ relationDetailName(detail) || '—' }}</span>
            </div>
          </div>
          <div class="detail-meta-panel">
            <div class="kv-grid detail-kv-grid">
              <div class="kv-item">
                <div class="kv-label">问题分类</div>
                <div class="kv-value">{{ detail.category || '—' }}</div>
              </div>
              <div class="kv-item">
                <div class="kv-label">反馈渠道</div>
                <div class="kv-value">{{ detail.feedback_channel || '微信' }}</div>
              </div>
              <div class="kv-item">
                <div class="kv-label">反馈时间</div>
                <div class="kv-value">{{ (detail.feedback_time || detail.register_time) ? (detail.feedback_time || detail.register_time).slice(0, 16) : '—' }}</div>
              </div>
              <div class="kv-item">
                <div class="kv-label">登记人</div>
                <div class="kv-value">{{ detail.register_user_name || '—' }}</div>
              </div>
              <div class="kv-item">
                <div class="kv-label">处理人</div>
                <div class="kv-value">{{ detail.handler_user_name || '—' }}</div>
              </div>
            </div>
          </div>
          <div v-if="detail.status === '已完成'" class="detail-completion-card">
            <div class="detail-completion-time">
              <span class="kv-label">完成时间</span>
              <span class="kv-value">
                {{ displayFinishTime }}
                <el-tag
                  v-if="finishBadge.text"
                  :type="finishBadge.type"
                  size="mini"
                  class="time-badge">
                  {{ finishBadge.text }}
                </el-tag>
              </span>
            </div>
            <div v-if="detailCompletedReply" class="detail-completion-reply">
              <div class="kv-label">回复记录</div>
              <div class="text-value">{{ detailCompletedReply }}</div>
            </div>
          </div>
          <div v-if="detail.status === '已关闭' && detailClosedReason" class="detail-closed-card">
            <div class="kv-label">关闭原因</div>
            <div class="text-value">{{ detailClosedReason }}</div>
          </div>
        </section>

        <div class="detail-content-grid">
          <section class="detail-section detail-section-desc">
            <div class="section-title">描述信息</div>
            <div class="text-block">
              <div v-if="detail.description" class="text-value rich-text rich-html" v-html="detail.description" />
              <div v-else class="text-value text-empty">暂无描述</div>
            </div>
          </section>

          <section v-loading="recordLoading" v-show="recordList.length > 0" class="detail-section detail-section-records">
            <div class="section-title">进度记录</div>
            <ledger-record-timeline :records="recordList" variant="desktop" />
          </section>
        </div>

        <section v-if="detail.status !== '已完成' && detail.status !== '已关闭'" class="detail-section record-actions-section">
          <el-divider content-position="left">补充处理</el-divider>
          <el-input
            v-model.trim="recordForm.content"
            :rows="4"
            :disabled="isRecordLocked"
            :placeholder="recordInputPlaceholder"
            type="textarea"
            class="record-input" />
          <div class="record-actions">
            <el-select
              v-model="recordForm.new_status"
              :disabled="isRecordLocked"
              clearable
              placeholder="变更状态（可选）"
              class="record-select">
              <el-option v-for="item in statusOptions" :key="item" :label="item" :value="item" />
            </el-select>
            <el-button :disabled="isRecordLocked" type="primary" @click="addRecord">{{ recordSubmitLabel }}</el-button>
          </div>
        </section>
      </div>
      <div slot="footer" class="dialog-footer">
        <el-button @click="detailVisible=false">关闭</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import {
  ledgerIndexAPI,
  ledgerReadAPI,
  ledgerSaveAPI,
  ledgerUpdateAPI,
  ledgerDeleteAPI,
  ledgerExcelExportAPI,
  ledgerRecordListAPI,
  ledgerRecordAddAPI
} from '@/api/ledger/ledger'
import { ledgerCategoryListAPI } from '@/api/admin/other'
import { crmCustomerIndexAPI, crmCustomerQueryContactsAPI } from '@/api/crm/customer'
import { crmContractIndexAPI, crmContractReadAPI } from '@/api/crm/contract'
import { XhUserCell } from '@/components/CreateCom'
import LedgerStatusDashboard from './components/LedgerStatusDashboard'
import LedgerRecordTimeline from './components/LedgerRecordTimeline'
import ledgerMixin from '@/mixins/ledgerMixin'
import { isMobileClient } from '@/utils/mobileClient'
import { workIndexWorkListAPI } from '@/api/pm/task'
import { workWorkStatisticAPI } from '@/api/pm/statistics'
import { downloadExcelWithResData } from '@/utils'
import { DEFAULT_LEDGER_CATEGORY } from '@/utils/ledgerFormat'
import { isCompletedLedgerStatus, isClosedLedgerStatus, normalizeCompletionFields } from '@/utils/ledgerCompletion'

export default {
  name: 'CustomerLedger',
  components: {
    XhUserCell,
    LedgerStatusDashboard,
    LedgerRecordTimeline,
    Tinymce: () => import('@/components/Tinymce')
  },
  mixins: [ledgerMixin],
  data() {
    return {
      showMobileHint: false,
      stats: {
        total: 0,
        pending: 0,
        processing: 0,
        releasePending: 0,
        completed: 0
      },
      loading: false,
      list: [],
      page: 1,
      limit: 50,
      total: 0,
      filters: {
        customer_keyword: '',
        customer_id: '',
        contract_id: '',
        handler_user_id: '',
        status: '',
        category: '',
        feedback_date: [],
        keyword: ''
      },
      filterHandlerUser: [],
      statusOptions: ['待处理', '处理中', '待验证', '待发布', '已完成', '已关闭'],
      categoryOptions: ['使用指导', '操作错误', '功能完善', '系统BUG', '新增需求', '三方问题', '其他问题'],
      channelOptions: ['微信', '电话', '现场', '转述', '其他'],
      workOptions: [],
      classOptions: [],
      workLoading: false,
      classLoading: false,
      customerOptions: [],
      customerLoading: false,
      contractOptions: [],
      contractLoading: false,
      // Add common customer aliases here, e.g. { '1001': '华北A', '北京某科技有限公司': '北京某科' }
      customerAliasMap: {},
      formVisible: false,
      titleErrorMsg: '',
      formTitle: '新建台账',
      formSubmitting: false,
      form: {},
      skipDraftSaveOnce: false,
      quickSearchType: 'contract',
      quickKeyword: '',
      quickResults: [],
      quickRawResults: [],
      quickContractScope: 'recent',
      quickLoading: false,
      quickVisible: false,
      exportLoading: false,
      feedbackContactsOptions: [],
      feedbackContactsLoading: false,
      rules: {
        contract_id: [{ validator: (rule, value, callback) => this.validateRelation(rule, value, callback), trigger: 'change' }],
        title: [{ required: true, message: '请填写反馈问题', trigger: 'blur' }],
        register_user_id: [{ validator: (rule, value, callback) => this.validateUserSelect(value, '请选择登记人', callback), trigger: 'change' }],
        handler_user_id: [{ validator: (rule, value, callback) => this.validateUserSelect(value, '请选择处理人', callback), trigger: 'change' }],
        close_reason: [{ validator: (rule, value, callback) => this.validateCloseReason(value, callback), trigger: 'blur' }]
      },
      detailVisible: false,
      detail: {},
      taskLink: {
        work_id: '',
        class_id: ''
      },
      taskCreating: false,
      recordLoading: false,
      recordOriginStatus: '',
      formOriginStatus: '',
      formOriginTaskId: 0,
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
    currentUserName() {
      const userInfo = this.$store.getters.userInfo || {}
      return userInfo.realname || userInfo.username || ''
    },
    currentUserId() {
      const userInfo = this.$store.getters.userInfo || {}
      return userInfo.id || userInfo.user_id || userInfo.userId || ''
    },
    finishBadge() {
      return this.getFinishBadge()
    },
    displayFinishTime() {
      if (this.detail.finish_time) return this.detail.finish_time.slice(0, 19)
      if (this.detail.status === '已完成' && this.detail.update_time) return this.detail.update_time.slice(0, 19)
      return '—'
    },
    isFormCompleted() {
      return isCompletedLedgerStatus(this.form && this.form.status)
    },
    isFormClosed() {
      return isClosedLedgerStatus(this.form && this.form.status)
    },
    detailCompletedReply() {
      if (!isCompletedLedgerStatus(this.detail && this.detail.status)) return ''
      if (this.detail && this.detail.completed_reply) return this.descriptionPreview(this.detail.completed_reply)
      const record = this.recordList.find(item => item && item.new_status === '已完成' && item.content)
      return record ? this.descriptionPreview(record.content) : ''
    },
    detailClosedReason() {
      if (!isClosedLedgerStatus(this.detail && this.detail.status)) return ''
      if (this.detail && this.detail.closed_reason) return this.descriptionPreview(this.detail.closed_reason)
      const record = this.recordList.find(item => item && item.new_status === '已关闭' && item.content)
      return record ? this.descriptionPreview(record.content) : ''
    },
    isCompleted() {
      return this.detail.status === '已完成'
    },
    isRecordLocked() {
      return this.detail.status === '已完成' || this.detail.status === '已关闭'
    },
    recordInputPlaceholder() {
      return this.recordForm.new_status === '已关闭' ? '填写关闭原因' : '填写处理结果'
    },
    recordSubmitLabel() {
      if (this.recordForm.new_status === '已完成') return '完成'
      if (this.recordForm.new_status === '已关闭') return '关闭'
      if (this.isRecordLocked) return '已完成'
      return '新增记录'
    },
    quickSearchPlaceholder() {
      return '输入合同编号或名称'
    },
    selectedContract() {
      if (!Array.isArray(this.form.contract_id) || !this.form.contract_id.length) return null
      return this.normalizeContractSelection(this.form.contract_id[0])
    },
    selectedContractLabel() {
      if (!this.selectedContract) return ''
      const contractFull = this.selectedContract.name || this.selectedContract.contract_name || this.selectedContract.contract_num || this.selectedContract.contract_id || ''
      const contractShort = this.selectedContract.contract_short_name || ''
      return this.formatFullAndShortName(contractFull, contractShort) || '合同'
    },
    selectedContractCustomer() {
      if (!this.selectedContract) return ''
      const customerFull = this.selectedContract.customer_name || this.selectedContract.customer_id || ''
      const customerShort = this.selectedContract.customer_short_name || ''
      return this.formatFullAndShortName(customerFull, customerShort)
    }
  },
  watch: {
    '$route.query.customer_id'() {
      this.applyRouteQuery(true)
    },
    '$route.query.ledger_id'() {
      this.applyRouteQuery(true)
    },
    canRead(val) {
      if (val && !this.applyRouteQuery(true) && this.list.length === 0) {
        this.getList()
      }
    }
  },
  created() {
    if (isMobileClient() && !this.$route.query.desktop) {
      const ledgerId = this.$route.query.ledger_id
      this.$router.replace(ledgerId ? `/m/ledger/${ledgerId}` : '/m/ledger')
      return
    }
    this.filters.feedback_date = this.getDefaultFilterDateRange()
    const hasRouteFilter = this.applyRouteQuery(true)
    if (this.canRead && !hasRouteFilter) this.getList()
    this.fetchCategoryOptions()
    this.fetchWorkOptions()
    this.fetchLedgerStats()
    this.updateMobileLedgerHint()
    if (typeof window !== 'undefined') {
      window.addEventListener('resize', this.updateMobileLedgerHint)
    }
  },
  beforeDestroy() {
    if (typeof window !== 'undefined') {
      window.removeEventListener('resize', this.updateMobileLedgerHint)
    }
  },
  methods: {
    fetchStats() {
      return this.fetchLedgerStats()
    },
    quickFilter(status) {
      this.filters.status = status
      this.handleSearch()
    },
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
    getNowTime() {
      if (this.$moment) {
        return this.$moment().format('YYYY-MM-DD HH:mm:ss')
      }
      const date = new Date()
      const pad = num => (num < 10 ? `0${num}` : `${num}`)
      return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`
    },
    handleFormStatusChange() {
      this.form = normalizeCompletionFields(this.form, () => this.getNowTime())
      this.$nextTick(() => {
        if (this.$refs.ledgerForm) {
          this.$refs.ledgerForm.validateField('close_reason')
        }
      })
    },
    canGenerateTask() {
      return this.detail && this.isTaskCategory(this.detail.category) && !this.detail.task_id
    },
    createProjectTask() {
      if (!this.detail || !this.detail.ledger_id) return
      if (!this.isTaskCategory(this.detail.category)) {
        this.$message.error('当前分类不支持生成任务')
        return
      }
      if (!this.taskLink.work_id || !this.taskLink.class_id) {
        this.$message.error('请选择项目和任务列表')
        return
      }
      this.taskCreating = true
      ledgerUpdateAPI({
        id: this.detail.ledger_id,
        work_id: this.taskLink.work_id,
        class_id: this.taskLink.class_id
      }).then(() => {
        this.openDetail(this.detail)
        this.getList()
      }).finally(() => {
        this.taskCreating = false
      })
    },
    openTaskDetail() {
      if (!this.detail || !this.detail.task_id) return
      const url = `/#/project/workbench?task_id=${this.detail.task_id}`
      window.open(url, '_blank')
    },
    fetchCustomerOptions(query) {
      if (!this.canRead) return
      this.customerLoading = true
      crmCustomerIndexAPI({
        page: 1,
        limit: 50,
        search: query || '',
        is_ledger_filter: 1,
        order_field: 'ledger_count',
        order_type: 'desc'
      })
        .then(res => {
          const list = (res.data && res.data.list) ? res.data.list : (res.data || [])
          this.customerOptions = list.map(item => ({
            customer_id: item.customer_id || item.id || '',
            customer_name: item.name || item.customer_name || item.customer_id || '',
            customer_short_name: item.crm_qpmlfv || item.customer_short_name || '',
            customer_display_name: this.getCustomerDisplayName(item.name || item.customer_name || item.customer_id || '', item.customer_id || item.id || '', item.crm_qpmlfv || item.customer_short_name || '')
          }))
        })
        .catch(() => {
          this.customerOptions = []
        })
        .finally(() => {
          this.customerLoading = false
        })
    },
    fetchContractOptions(customerId, keyword) {
      const cid = String(customerId || '').trim()
      if (!cid) {
        this.contractOptions = []
        return
      }
      this.contractLoading = true
      crmContractIndexAPI({
        page: 1,
        limit: 100,
        customer_id: cid,
        check_status: 2,
        order_field: 'start_time',
        order_type: 'desc',
        search: keyword || ''
      })
        .then(res => {
          const data = res.data || {}
          const list = Array.isArray(data.list) ? data.list : []
          this.contractOptions = list
            .filter(item => ['2', '7'].includes(String(item.check_status)))
            .sort((a, b) => String(b.start_time || '').localeCompare(String(a.start_time || '')))
            .map(item => {
              const fullName = item.name || item.num || `合同#${item.contract_id || ''}`
              const shortName = item.crm_defqwa || ''
              return {
                contract_id: item.contract_id,
                contract_full_name: fullName,
                contract_display_name: shortName || (fullName.length > 10 ? `${fullName.slice(0, 10)}...` : fullName)
              }
            })
        })
        .catch(() => {
          this.contractOptions = []
        })
        .finally(() => {
          this.contractLoading = false
        })
    },
    handleCustomerVisibleChange(visible) {
      if (visible) {
        this.fetchCustomerOptions('')
      }
    },
    handleCustomerFilterChange(value) {
      const text = String(value || '').trim()
      this.filters.customer_id = /^\d+$/.test(text) ? text : ''
      this.filters.contract_id = ''
      this.contractOptions = []
      if (this.filters.customer_id) {
        this.fetchContractOptions(this.filters.customer_id, '')
      }
      this.handleSearch()
    },
    handleContractVisibleChange(visible) {
      if (!visible || !this.filters.customer_id) return
      if (!this.contractOptions.length) {
        this.fetchContractOptions(this.filters.customer_id, '')
      }
    },
    getDefaultFilterDateRange() {
      const today = this.$moment()
      return [
        today.clone().startOf('year').format('YYYY-MM-DD'),
        today.format('YYYY-MM-DD')
      ]
    },
    handleFilterHandlerChange(data) {
      const selected = data && Array.isArray(data.value) ? data.value : []
      this.filterHandlerUser = selected
      const first = selected[0]
      this.filters.handler_user_id = first ? (first.id || first.user_id) : ''
    },
    applyRouteQuery(needFetch) {
      const ledgerId = this.$route.query.ledger_id
      if (ledgerId) {
        if (this.canRead && needFetch) {
          this.page = 1
          this.getList()
          this.openDetailById(ledgerId)
        }
        return true
      }
      const customerId = this.$route.query.customer_id
      if (customerId) {
        this.filters.customer_id = String(customerId)
        this.filters.customer_keyword = String(customerId)
        this.fetchContractOptions(this.filters.customer_id, '')
        if (this.canRead && needFetch) {
          this.page = 1
          this.getList()
        }
        return true
      }
      return false
    },
    openDetailById(ledgerId) {
      const id = Number(ledgerId)
      if (!id) return
      const row = { ledger_id: id }
      this.detail = { ...row }
      this.taskLink = {
        work_id: '',
        class_id: ''
      }
      this.detailVisible = true
      this.recordOriginStatus = ''
      this.recordForm = { content: '', new_status: '' }
      this.recordList = []
      this.loadRecords(id)
      ledgerReadAPI({ id }).then(res => {
        this.detail = { ...row, ...(res.data || {}) }
        this.detail.description = this.normalizeHtmlImages(this.detail.description)
        this.taskLink = {
          work_id: this.detail.work_id || '',
          class_id: this.detail.class_id || ''
        }
        this.fetchClassOptions(this.taskLink.work_id)
        this.recordOriginStatus = this.detail.status || ''
      }).catch(() => {})
    },
    clearRouteCustomer() {
      if (this.$route.query.customer_id) {
        const query = { ...this.$route.query }
        delete query.customer_id
        this.$router.replace({ path: this.$route.path, query })
      }
    },
    statusTagType(status) {
      const map = {
        '待处理': 'info',
        '处理中': 'warning',
        '待验证': 'warning',
        '待发布': '',
        '已完成': 'success',
        '已关闭': 'danger'
      }
      return map[status] || 'info'
    },
    statusTagClass(status) {
      return status === '待发布' ? 'status-tag-release' : ''
    },
    relationName(row) {
      if (!row) return '-'
      if (row.business_name) return row.business_name
      if (row.contract_id || row.contract_name || row.contract_num || row.contract_short_name) {
        const customerText = this.getCustomerDisplayName(row.customer_name, row.customer_id, row.customer_short_name)
        const contractText = row.contract_short_name || row.contract_name || row.contract_num || ''
        if (customerText && contractText) return `${customerText}·${contractText}`
        return contractText || customerText || '-'
      }
      return '-'
    },
    relationDetailName(row) {
      if (!row) return '-'
      return row.business_name || row.contract_name || row.contract_num || '-'
    },
    formatFullAndShortName(full, short) {
      const fullName = String(full || '').trim()
      const shortName = String(short || '').trim()
      if (!fullName) return shortName
      if (!shortName) return fullName
      return `${fullName}（${shortName}）`
    },
    formatIssueTitle(row) {
      const title = (row && row.title) ? row.title : '—'
      const count = Number((row && row.record_count) || 0)
      if (count > 0) return `${title}-（${count}）`
      return title
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
    getFinishBadge() {
      if (!this.detail.finish_time) return { text: '', type: '' }
      const start = this.parseDateTime(this.detail.feedback_time || this.detail.register_time)
      const end = this.parseDateTime(this.detail.finish_time)
      if (!start || !end || end < start) return { text: '', type: '' }
      const minutes = (end - start) / 60000
      if (minutes <= 30) return { text: '30分钟内', type: 'success' }
      if (minutes <= 60) return { text: '60分钟内', type: 'warning' }
      if (minutes <= 1440) return { text: '1天内', type: 'info' }
      return { text: '', type: '' }
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
    normalizeHtmlImages(html) {
      if (!html) return ''
      const base = (window && window.BASE_URL) ? window.BASE_URL.replace(/\/$/, '') : ''
      if (!base) return html
      const baseLower = base.toLowerCase()
      return html.replace(/<img\b[^>]*\bsrc=(['"])(.*?)\1[^>]*>/gi, (match, quote, src) => {
        const value = (src || '').trim()
        if (!value) return match
        const lower = value.toLowerCase()
        if (
          lower.startsWith('http://') ||
          lower.startsWith('https://') ||
          lower.startsWith('data:') ||
          lower.startsWith('blob:') ||
          lower.startsWith('//') ||
          lower.startsWith(baseLower)
        ) {
          return match
        }
        const fixed = value.startsWith('/') ? `${base}${value}` : `${base}/${value}`
        return match.replace(src, fixed)
      })
    },
    descriptionPreview(html) {
      const source = String(html || '')
      if (!source) return ''
      return source
        .replace(/<img\b[^>]*>/gi, ' [图片] ')
        .replace(/<(br|\/p|\/div|\/li)\b[^>]*>/gi, ' ')
        .replace(/<[^>]+>/g, '')
        .replace(/&nbsp;/gi, ' ')
        .replace(/&amp;/gi, '&')
        .replace(/&lt;/gi, '<')
        .replace(/&gt;/gi, '>')
        .replace(/\s+/g, ' ')
        .trim()
    },
    completedReplyPreview(row) {
      if (!row || row.status !== '已完成') return ''
      return this.descriptionPreview(row.completed_reply || '')
    },
    descriptionCellTitle(row) {
      const desc = this.descriptionPreview(row && row.description)
      const reply = this.completedReplyPreview(row)
      if (desc && reply) return `描述：${desc}\n回答：${reply}`
      if (desc) return `描述：${desc}`
      if (reply) return `回答：${reply}`
      return ''
    },
    formatListTime(value) {
      const text = String(value || '').trim()
      if (!text) return '—'
      const normalized = text.replace('T', ' ')
      if (normalized.length >= 16) return normalized.slice(5, 16) // MM-DD HH:mm
      return normalized
    },
    getCustomerDisplayName(name, customerId, shortName) {
      const explicitShort = String(shortName || '').trim()
      if (explicitShort) return explicitShort
      const fullName = String(name || '').trim()
      if (!fullName) return ''
      const idKey = String(customerId || '').trim()
      if (idKey && this.customerAliasMap[idKey]) return this.customerAliasMap[idKey]
      if (this.customerAliasMap[fullName]) return this.customerAliasMap[fullName]
      return fullName.length > 8 ? `${fullName.slice(0, 8)}...` : fullName
    },
    buildParams() {
      const params = {
        page: this.page,
        limit: this.limit
      }
      const customerKeyword = (this.filters.customer_keyword || '').trim()
      if (customerKeyword) {
        if (/^\d+$/.test(customerKeyword)) {
          params.customer_id = customerKeyword
        } else {
          params.customer_name = customerKeyword
        }
      } else if (this.filters.customer_id) {
        params.customer_id = this.filters.customer_id
      }
      if (this.filters.contract_id) params.contract_id = this.filters.contract_id
      if (this.filters.handler_user_id) params.handler_user_id = this.filters.handler_user_id
      if (this.filters.status) params.status = this.filters.status
      if (this.filters.category) params.category = this.filters.category
      if (this.filters.keyword) params.keyword = this.filters.keyword
      if (this.filters.feedback_date && this.filters.feedback_date.length === 2) {
        params.start_date = this.filters.feedback_date[0]
        params.end_date = this.filters.feedback_date[1]
      }
      return params
    },
    getList() {
      this.loading = true
      ledgerIndexAPI(this.buildParams())
        .then(res => {
          const data = res.data || {}
          this.list = data.list || []
          this.total = data.dataCount || 0
        })
        .finally(() => {
          this.loading = false
        })
    },
    loadRecords(ledgerId) {
      if (!ledgerId) {
        this.recordList = []
        return
      }
      this.recordLoading = true
      ledgerRecordListAPI({ ledger_id: ledgerId })
        .then(res => {
          this.recordList = Array.isArray(res.data)
            ? res.data.map(item => ({ ...item, followup_id: item.followup_id || item.record_id }))
            : []
        })
        .catch(() => {
          this.recordList = []
        })
        .finally(() => {
          this.recordLoading = false
        })
    },
    addRecord() {
      if (!this.detail || !this.detail.ledger_id) return
      if (!this.recordForm.content) {
        this.$message.error(this.recordForm.new_status === '已关闭' ? '请填写关闭原因' : '请填写处理说明')
        return
      }
      const params = {
        ledger_id: this.detail.ledger_id,
        content: this.recordForm.content,
        new_status: this.recordForm.new_status || '',
        sync_task_status: 1
      }
      const submit = syncTaskStatus => {
        params.sync_task_status = syncTaskStatus
        return ledgerRecordAddAPI(params)
      }
      const statusChanged = !!params.new_status && params.new_status !== this.detail.status
      const hasLinkedTask = !!(this.detail && this.detail.task_id)
      const request = (statusChanged && hasLinkedTask)
        ? this.confirmLedgerTaskSync(params.new_status).then(choice => {
          if (choice === null) return null
          return submit(choice)
        })
        : submit(1)
      request.then(res => {
        if (res === null) return
        this.recordForm.content = ''
        this.recordForm.new_status = ''
        this.loadRecords(this.detail.ledger_id)
        if (params.new_status) {
          this.detail.status = params.new_status
          if (params.new_status === '已完成') {
            this.detail.finish_time = this.detail.finish_time || this.detail.update_time || this.$moment().format('YYYY-MM-DD HH:mm:ss')
          }
          this.getList()
          this.fetchStats()
        }
      })
    },
    handleSearch() {
      if (!this.canRead) return
      const keyword = (this.filters.customer_keyword || '').trim()
      if (keyword && /^\d+$/.test(keyword)) {
        this.filters.customer_id = keyword
      } else {
        this.filters.customer_id = ''
        this.filters.contract_id = ''
      }
      this.page = 1
      this.getList()
    },
    handlePageChange(page) {
      if (!this.canRead) return
      this.page = Number(page || 1)
      this.getList()
    },
    handlePageSizeChange(size) {
      if (!this.canRead) return
      this.limit = Number(size || 15)
      this.page = 1
      this.getList()
    },
    handleReset() {
      this.filters = {
        customer_keyword: '',
        customer_id: '',
        contract_id: '',
        handler_user_id: '',
        status: '',
        category: '',
        feedback_date: this.getDefaultFilterDateRange(),
        keyword: ''
      }
      this.contractOptions = []
      this.filterHandlerUser = []

      this.clearRouteCustomer()
      this.page = 1
      this.handleSearch()
    },
    handleFormValidate(prop, valid, message) {
      if (prop === 'title') {
        this.titleErrorMsg = valid ? '' : (message || '')
      }
    },
    openCreate() {
      this.recordList = []
      this.recordForm = { content: '', new_status: '' }
      this.recordOriginStatus = ''
      this.formOriginStatus = ''
      this.formOriginTaskId = 0
      const now = this.getNowTime()
      this.formTitle = '新建台账'
      const draft = this.loadDraft()
      const baseForm = {
        customer_id: [],
        business_id: [],
        contract_id: [],
        title: '',
        description: '',
        feedback_user: '',
        feedback_channel: '微信',
        category: DEFAULT_LEDGER_CATEGORY,
        status: '待处理',
        feedback_time: now,
        register_time: now,
        finish_time: '',
        work_id: '',
        class_id: '',
        handler_user_id: this.getCurrentUserSelection(),
        register_user_id: this.getCurrentUserSelection(),
        reply_content: '',
        close_reason: ''
      }
      this.form = normalizeCompletionFields(draft ? { ...baseForm, ...draft } : baseForm, now)
      if (draft) {
        this.form.customer_id = []
        this.form.business_id = []
        this.form.handler_user_id = draft.handler_user_id || this.getCurrentUserSelection()
        this.form.register_user_id = draft.register_user_id || this.getCurrentUserSelection()
      }
      this.fetchClassOptions(this.form.work_id)
      this.quickSearchType = 'contract'
      this.quickKeyword = ''
      this.loadFeedbackContacts()
      this.formVisible = true
      this.titleErrorMsg = ''
      this.$nextTick(() => {
        this.$refs.ledgerForm && this.$refs.ledgerForm.clearValidate()
        if (this.$refs.titleInput && this.$refs.titleInput.focus) {
          this.$refs.titleInput.focus()
        }
      })
    },
    openEdit(row) {
      this.formOriginStatus = row.status || ''
      this.formOriginTaskId = Number(row.task_id || 0)
      this.formTitle = '编辑台账'
      const now = this.$moment().format('YYYY-MM-DD HH:mm:ss')
      const form = {
        id: row.ledger_id,
        customer_id: [],
        business_id: [],
        contract_id: row.contract_id ? [this.normalizeContractSelection({ contract_id: row.contract_id, name: row.contract_name || row.contract_num || '', customer_id: row.customer_id || '', customer_name: row.customer_name || '' })] : [],
        title: row.title,
        description: row.description,
        feedback_user: row.feedback_user,
        feedback_channel: row.feedback_channel || '微信',
        category: row.category,
        status: row.status,
        feedback_time: row.feedback_time || row.register_time || now,
        register_time: row.register_time || now,
        finish_time: row.finish_time,
        work_id: row.work_id || '',
        class_id: row.class_id || '',
        task_id: row.task_id || 0,
        handler_user_id: row.handler_user_id ? [{ id: row.handler_user_id, realname: row.handler_user_name || '' }] : [],
        register_user_id: row.register_user_id ? [{ id: row.register_user_id, realname: row.register_user_name || '' }] : [],
        reply_content: row.completed_reply || '',
        close_reason: row.closed_reason || ''
      }
      this.fetchClassOptions(form.work_id)
      this.quickSearchType = 'contract'
      this.quickKeyword = ''
      this.form = normalizeCompletionFields(form, () => this.getNowTime())
      this.loadFeedbackContacts()
      this.formVisible = true
      this.titleErrorMsg = ''

      this.$nextTick(() => {
        this.$refs.ledgerForm && this.$refs.ledgerForm.clearValidate()
      })
      if (!this.form.register_user_id.length) {
        ledgerReadAPI({ id: row.ledger_id }).then(res => {
          const data = res.data || {}
          if (!this.form.register_user_id.length) {
            this.form.register_user_id = data.register_user_id
              ? [{ id: data.register_user_id, realname: data.register_user_name || '' }]
              : this.getCurrentUserSelection()
          }
        }).catch(() => {})
      }
    },
    openDetail(row) {
      if (row && row.ledger_id) {
        this.detail = { ...row }
        this.taskLink = {
          work_id: row.work_id || '',
          class_id: row.class_id || ''
        }
        this.fetchClassOptions(this.taskLink.work_id)
        this.detailVisible = true
        this.recordOriginStatus = row.status || ''
        this.recordForm = { content: '', new_status: '' }
        this.recordList = []
        this.loadRecords(row.ledger_id)
        ledgerReadAPI({ id: row.ledger_id }).then(res => {
          this.detail = { ...row, ...(res.data || {}) }
          this.detail.description = this.normalizeHtmlImages(this.detail.description)
          this.taskLink = {
            work_id: this.detail.work_id || '',
            class_id: this.detail.class_id || ''
          }
          this.fetchClassOptions(this.taskLink.work_id)
          if (!this.recordOriginStatus) {
            this.recordOriginStatus = this.detail.status || ''
          }
        }).catch(() => {})
      }
    },
    submitForm() {
      this.$refs.ledgerForm.validate(async valid => {
        if (!valid) return
        const payload = this.buildSubmitPayload()
        const hasLinkedTask = Number(payload.task_id || this.formOriginTaskId || 0) > 0
        const statusChanged = payload.id ? payload.status !== this.formOriginStatus : false
        if (hasLinkedTask && statusChanged) {
          const choice = await this.confirmLedgerTaskSync(payload.status)
          if (choice === null) return
          payload.sync_task_status = choice
        }
        if (!payload.id) {
          await this.warnExpiredContractOnCreate(payload)
        }
        const request = payload.id ? ledgerUpdateAPI : ledgerSaveAPI
        this.formSubmitting = true
        request(payload).then(() => {
          this.skipDraftSaveOnce = true
          this.formVisible = false
          this.clearDraft()
          this.formOriginStatus = payload.status || ''
          this.formOriginTaskId = Number(payload.task_id || this.formOriginTaskId || 0)
          this.getList()
          this.fetchStats()
        }).finally(() => {
          this.formSubmitting = false
        })
      })
    },
    handleFormBeforeClose(done) {
      if (this.skipDraftSaveOnce) {
        this.skipDraftSaveOnce = false
        done()
        return
      }
      this.saveDraft()
      done()
    },
    isCompletedStatus(status) {
      return String(status || '').indexOf('完成') !== -1
    },
    async confirmLedgerTaskSync(targetStatus) {
      const isDone = this.isCompletedStatus(targetStatus)
      try {
        await this.$confirm(
          isDone ? '当前台账将标记为已完成，是否同步完成关联任务？' : '当前台账状态将回退，是否同步回退关联任务状态？',
          '台账任务联动',
          {
            confirmButtonText: '同步',
            cancelButtonText: '不同步',
            distinguishCancelAndClose: true,
            type: 'warning'
          }
        )
        return 1
      } catch (e) {
        if (e === 'cancel') return 0
        return null
      }
    },
    getDraftKey() {
      const ownerId = this.getDraftOwnerId()
      return ownerId ? `ledger_form_draft_${ownerId}` : ''
    },
    getDraftOwnerId() {
      const cached = this.getCachedUserInfo()
      const ownerId = this.currentUserId || cached.id || cached.user_id || cached.userId || ''
      return ownerId ? String(ownerId) : ''
    },
    getDraftExpiryMs() {
      return 7 * 24 * 60 * 60 * 1000
    },
    cloneDraftData(data) {
      try {
        return JSON.parse(JSON.stringify(data || {}))
      } catch (e) {
        return {}
      }
    },
    saveDraft() {
      if (this.formTitle !== '新建台账') return
      const draftKey = this.getDraftKey()
      if (!draftKey) return
      if (!this.hasDraftContent()) {
        this.clearDraft()
        return
      }
      const draft = this.cloneDraftData(this.form)
      draft.owner_user_id = this.getDraftOwnerId()
      draft.saved_at = Date.now()
      draft.expires_at = draft.saved_at + this.getDraftExpiryMs()
      delete draft.id
      delete draft.ledger_id
      try {
        localStorage.setItem(draftKey, JSON.stringify(draft))
      } catch (e) {
        // ignore localStorage write errors
      }
    },
    loadDraft() {
      const draftKey = this.getDraftKey()
      if (!draftKey) return null
      try {
        const raw = localStorage.getItem(draftKey)
        const draft = raw ? JSON.parse(raw) : null
        if (!draft) return null
        const ownerId = this.getDraftOwnerId()
        const draftOwnerId = draft.owner_user_id ? String(draft.owner_user_id) : ''
        if (draftOwnerId && ownerId && draftOwnerId !== ownerId) {
          this.clearDraft()
          return null
        }
        const now = Date.now()
        const expiresAt = Number(draft.expires_at || 0)
        const savedAt = Number(draft.saved_at || 0)
        if ((expiresAt && now > expiresAt) || (!expiresAt && savedAt && now - savedAt > this.getDraftExpiryMs())) {
          this.clearDraft()
          return null
        }
        if (draft.id || draft.ledger_id) return null
        return draft
      } catch (e) {
        return null
      }
    },
    clearDraft() {
      const draftKey = this.getDraftKey()
      if (!draftKey) return
      try {
        localStorage.removeItem(draftKey)
      } catch (e) {
        // ignore localStorage remove errors
      }
    },
    hasDraftContent() {
      const fields = ['title', 'description', 'feedback_user', 'reply_content']
      if (fields.some(key => this.form && String(this.form[key] || '').trim())) return true
      if (Array.isArray(this.form.contract_id) && this.form.contract_id.length) return true
      return false
    },
    handleDelete(row) {
      this.$confirm('确认删除该台账记录吗？', '提示', {
        type: 'warning'
      }).then(() => {
        ledgerDeleteAPI({ id: row.ledger_id }).then(() => {
          this.getList()
          this.fetchStats()
        })
      }).catch(() => {})
    },
    handleCustomerChange(data) {
      this.form.customer_id = []
      this.touchRelationValidation()
      this.loadFeedbackContacts()
    },
    handleBusinessChange(data) {
      this.form.business_id = []
      this.touchRelationValidation()
      this.loadFeedbackContacts()
    },
    handleContractChange(data) {
      const selected = data && Array.isArray(data.value) ? data.value : []
      this.form.contract_id = selected.map(item => this.normalizeContractSelection(item))
      if (this.form.contract_id.length) {
        this.form.customer_id = []
        this.form.business_id = []
      }
      this.touchRelationValidation()
      this.loadFeedbackContacts()
    },
    clearSelectedContract() {
      this.handleContractChange({ value: [] })
    },
    reselectContract() {
      this.clearSelectedContract()
      this.quickVisible = true
    },
    handleHandlerChange(data) {
      this.form.handler_user_id = data.value || []
    },
    handleRegisterChange(data) {
      this.form.register_user_id = data.value || []
    },
    getFeedbackContactLabel(item) {
      if (!item) return ''
      return item.name || item.contacts_name || item.realname || item.mobile || item.telephone || item.phone || ''
    },
    resetFeedbackContacts() {
      this.feedbackContactsOptions = []
      this.feedbackContactsLoading = false
    },
    loadFeedbackContacts() {
      const contract = Array.isArray(this.form.contract_id) && this.form.contract_id.length ? this.form.contract_id[0] : null
      const customerId = contract ? (contract.customer_id || '') : ''

      if (!customerId) {
        this.resetFeedbackContacts()
        return
      }

      this.feedbackContactsLoading = true
      const params = { pageType: 'all' }
      params.customer_id = customerId
      crmCustomerQueryContactsAPI(params).then(res => {
        const list = (res.data && res.data.list) ? res.data.list : []
        this.feedbackContactsOptions = list.map(item => ({
          ...item,
          name: this.getFeedbackContactLabel(item)
        }))
      }).catch(() => {
        this.feedbackContactsOptions = []
      }).finally(() => {
        this.feedbackContactsLoading = false
      })
    },
    normalizeContractSelection(item) {
      const data = item || {}
      const name = data.name || data.contract_name || data.contractName || data.contractNum || data.num || ''
      return {
        ...data,
        name,
        customer_short_name: data.customer_short_name || data.crm_qpmlfv || '',
        contract_short_name: data.contract_short_name || data.crm_defqwa || '',
        contract_name: data.contract_name || name,
        contractNum: name
      }
    },
    quickSelectRelation(type) {
      if (type !== 'contract') {
        this.quickSearchType = 'contract'
        this.$message.warning('台账仅支持关联合同')
        return
      }
      const keyword = (this.quickKeyword || '').trim()
      const request = crmContractIndexAPI
      if (!request) return
      const params = {
        page: 1,
        limit: 500,
        is_ledger_filter: 1,
        order_field: 'ledger_count',
        order_type: 'desc'
      }
      if (keyword) params.search = keyword
      this.quickLoading = true
      this.quickVisible = true
      request(params).then(res => {
        const list = (res.data && res.data.list) ? res.data.list : []
        this.quickRawResults = list
        this.refreshQuickResults()
      }).catch(() => {
        this.quickRawResults = []
        this.quickResults = []
        this.$message.error('获取列表失败，请重试')
      }).finally(() => {
        this.quickLoading = false
      })
    },
    refreshQuickResults() {
      this.quickResults = this.sortQuickContractResults(this.quickRawResults).slice(0, 50)
    },
    applyQuickPick(item) {
      if (!item) return
      if (this.quickSearchType === 'contract') {
        this.handleContractChange({ value: [{ contract_id: item.contract_id, name: item.name || item.num || item.contract_num || '', customer_id: item.customer_id || '', customer_name: item.customer_name || '', customer_short_name: item.customer_short_name || item.crm_qpmlfv || '', contract_short_name: item.contract_short_name || item.crm_defqwa || '' }] })
      }
      this.quickVisible = false
    },
    quickResultKey(item, index) {
      if (this.quickSearchType === 'contract') return `contract-${item.contract_id}-${index}`
      return `${item.id || item.name}-${index}`
    },
    quickResultName(item) {
      if (this.quickSearchType === 'contract') {
        const full = item.name || item.num || item.contract_num || item.contract_id
        const short = item.contract_short_name || item.crm_defqwa || ''
        return this.formatFullAndShortName(full, short)
      }
      return item.name || '--'
    },
    quickResultMeta(item) {
      if (this.quickSearchType === 'contract') {
        const customerFull = item.customer_name || '--'
        const customerShort = item.customer_short_name || item.crm_qpmlfv || ''
        return `客户：${this.formatFullAndShortName(customerFull, customerShort)} | 台账：${item.ledger_count || 0}`
      }
      return ''
    },
    sortByLedgerCount(list) {
      if (!Array.isArray(list)) return []
      return list.slice().sort((a, b) => {
        const c1 = Number(a.ledger_count || 0)
        const c2 = Number(b.ledger_count || 0)
        if (c1 === c2) return 0
        return c2 - c1
      })
    },
    sortQuickContractResults(list) {
      if (!Array.isArray(list)) return []
      if (this.quickContractScope === 'all') return this.sortByLedgerCount(list)
      const currentYear = new Date().getFullYear()
      return list.slice().sort((a, b) => {
        const aYear = this.getContractYear(a)
        const bYear = this.getContractYear(b)
        const aRecent = !aYear || aYear >= currentYear - 1
        const bRecent = !bYear || bYear >= currentYear - 1
        if (aRecent !== bRecent) return aRecent ? -1 : 1

        const c1 = Number(a.ledger_count || 0)
        const c2 = Number(b.ledger_count || 0)
        if (c1 !== c2) return c2 - c1
        if (aYear !== bYear) return (bYear || 0) - (aYear || 0)
        return String(this.quickResultName(a)).localeCompare(String(this.quickResultName(b)), 'zh-Hans-CN')
      })
    },
    getContractYear(item) {
      const data = item || {}
      const fields = [
        data.start_time,
        data.order_date,
        data.sign_time,
        data.create_time,
        data.name,
        data.num,
        data.contract_num,
        data.contract_short_name,
        data.crm_defqwa
      ]
      const currentYear = new Date().getFullYear()
      for (const field of fields) {
        const text = String(field || '')
        const fullYear = text.match(/20\d{2}/)
        if (fullYear) return Number(fullYear[0])

        const shortYear = text.match(/(?:^|[^\d])([2-3]\d)(?=[^\d]|$)/)
        if (shortYear) {
          const year = Number(`20${shortYear[1]}`)
          if (year >= currentYear - 10 && year <= currentYear + 2) return year
        }
      }
      return 0
    },
    handleExport() {
      if (!this.canRead) return
      this.exportLoading = true
      const params = this.buildParams()
      delete params.page
      delete params.limit
      ledgerExcelExportAPI(params).then(res => {
        if (!res || !res.headers || !res.data) {
          this.$message.error('导出失败，请确认导出权限后重试')
          return
        }
        downloadExcelWithResData(res)
      }).catch(() => {
        this.$message.error('导出失败，请稍后重试')
      }).finally(() => {
        this.exportLoading = false
      })
    },

    validateRelation(rule, value, callback) {
      const hasContract = Array.isArray(this.form.contract_id) && this.form.contract_id.length > 0
      if (!hasContract) {
        callback(new Error('台账必须选择合同'))
        return
      }
      callback()
    },
    validateUserSelect(value, message, callback) {
      const list = Array.isArray(value) ? value : []
      if (!list.length) {
        callback(new Error(message))
        return
      }
      callback()
    },
    validateCloseReason(value, callback) {
      if (isClosedLedgerStatus(this.form && this.form.status) && !String(value || '').trim()) {
        callback(new Error('请填写关闭原因'))
        return
      }
      callback()
    },
    touchRelationValidation() {
      if (this.$refs.ledgerForm) {
        this.$refs.ledgerForm.validateField('contract_id')
      }
    },
    getCustomerSelectionById(customerId) {
      if (!customerId) return []
      const hit = this.list.find(item => String(item.customer_id) === String(customerId))
      if (hit) {
        return [{ customer_id: hit.customer_id, name: this.getCustomerDisplayName(hit.customer_name, hit.customer_id, hit.customer_short_name) }]
      }
      return [{ customer_id: customerId, name: String(customerId) }]
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
      const cached = this.getCachedUserInfo()
      const userId = this.currentUserId || cached.id || cached.user_id || cached.userId || ''
      const userName = this.currentUserName || cached.realname || cached.username || ''
      if (userId) {
        return [{ id: userId, realname: userName }]
      }
      return []
    },
    buildSubmitPayload() {
      const payload = { ...this.form }
      const contractValue = Array.isArray(this.form.contract_id) ? this.form.contract_id[0] : null
      payload.contract_id = contractValue ? (contractValue.contract_id || contractValue.id) : ''
      payload.business_id = ''
      payload.customer_id = ''
      const handlerValue = Array.isArray(this.form.handler_user_id) ? this.form.handler_user_id[0] : null
      payload.handler_user_id = handlerValue ? (handlerValue.id || handlerValue.user_id) : ''
      const registerValue = Array.isArray(this.form.register_user_id) ? this.form.register_user_id[0] : null
      payload.register_user_id = registerValue ? (registerValue.id || registerValue.user_id) : ''
      const normalized = normalizeCompletionFields(payload, () => this.getNowTime())
      payload.finish_time = normalized.finish_time
      payload.reply_content = normalized.reply_content
      payload.close_reason = normalized.close_reason
      delete payload.remark
      payload.sync_task_status = 1
      return payload
    }
  }
}
</script>

<style lang="scss" scoped>
.mobile-ledger-hint {
  margin-bottom: 10px;
}

.mobile-ledger-hint__sep {
  margin: 0 8px;
  color: #c0c4cc;
}

.ledger-page {
  padding: 12px 14px 16px;
  background-color: #f0f2f5;
  min-height: 100vh;
}

.dashboard {
  display: flex;
  gap: 12px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}

.dashboard-card {
  flex: 1;
  min-width: 200px;
  background: #fff;
  border-radius: 8px;
  padding: 16px;
  display: flex;
  align-items: center;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
  transition: transform 0.2s, box-shadow 0.2s;
  cursor: pointer;
}

.dashboard-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.card-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  margin-right: 16px;
}

.card-info {
  flex: 1;
}

.card-label {
  font-size: 14px;
  color: #909399;
  margin-bottom: 4px;
}

.card-num {
  font-size: 24px;
  font-weight: bold;
  color: #303133;
}

.dashboard-card.total .card-icon { background: #ecf5ff; color: #409EFF; }
.dashboard-card.pending .card-icon { background: #fef0f0; color: #F56C6C; }
.dashboard-card.processing .card-icon { background: #fdf6ec; color: #E6A23C; }
.dashboard-card.release-pending .card-icon { background: #f4ecf7; color: #9b59b6; }
.dashboard-card.completed .card-icon { background: #f0f9eb; color: #67C23A; }

.ledger-table ::v-deep .status-tag-release {
  color: #8e44ad;
  background-color: #f4ecf7;
  border-color: #e8daef;
}

.ledger-detail-dialog ::v-deep .status-tag-release {
  color: #8e44ad;
  background-color: #f4ecf7;
  border-color: #e8daef;
}


.filter-bar {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 8px;
  padding: 10px 12px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
  margin-bottom: 16px;
  flex-wrap: nowrap;
  overflow-x: auto;
  overflow-y: hidden;
}

.filter-form {
  flex: 1 0 auto;
  display: flex;
  flex-wrap: nowrap;
  align-items: center;
  gap: 8px;
  min-width: max-content;
}

.filter-form .el-form-item {
  flex: 0 0 auto;
  margin-bottom: 0;
  margin-right: 0;
}

.filter-form .el-input,
.filter-form .el-select {
  width: 110px;
}

.filter-form .el-date-editor {
  width: 258px;
}

.filter-form ::v-deep .el-range-separator {
  width: 24px;
  padding: 0 4px;
  flex-shrink: 0;
  color: #606266;
}

.filter-form ::v-deep .el-range-input {
  min-width: 82px;
}

.filter-form .filter-customer-select {
  width: 140px;
}

.filter-form .filter-contract-select {
  width: 135px;
}

.customer-option-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.customer-option-short {
  max-width: 86px;
  flex-shrink: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.customer-option-full {
  flex: 1;
  color: #909399;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.filter-form .filter-user-cell ::v-deep .user-container {
  width: 100%;
  min-height: 32px;
  margin: 0;
}

.filter-form .filter-user-cell {
  width: 105px;
}

.filter-form ::v-deep .el-form-item__label,
.filter-form ::v-deep .el-form-item__content {
  line-height: 32px;
}

.filter-form ::v-deep .el-form-item__label {
  padding-right: 5px;
}

.filter-actions {
  flex: 0 0 auto;
  margin-left: 4px;
  white-space: nowrap;
}

.ledger-table {
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.ledger-table ::v-deep .el-table__header th {
  padding: 6px 0;
}

.ledger-table ::v-deep .el-table__body td {
  padding: 6px 0;
}

.desc-preview-wrap {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.desc-preview {
  line-height: 17px;
  max-height: 17px;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  word-break: break-word;
  white-space: normal;
}

.desc-preview--reply {
  color: #2f7d32;
}

.desc-badge {
  display: inline-block;
  margin-right: 6px;
  padding: 0 4px;
  border-radius: 3px;
  font-size: 12px;
  line-height: 16px;
  color: #606266;
  background: #f2f3f5;
}

.desc-badge--reply {
  color: #2f7d32;
  background: #e9f7ec;
}

.pager-bar {
  padding: 16px 0;
  text-align: right;
  background: transparent;
}

/* Detail Dialog Styling */
.ledger-detail-dialog ::v-deep .el-dialog {
  border-radius: 8px;
  overflow: hidden;
  max-width: 96vw;
  margin-top: 5vh !important;
}

.ledger-detail-dialog ::v-deep .el-dialog__header {
  padding: 14px 18px;
  border-bottom: 1px solid #ebeef5;
  background: #fff;
}

.ledger-detail-dialog ::v-deep .el-dialog__title {
  font-size: 16px;
  font-weight: 600;
  color: #303133;
}

.ledger-detail-dialog ::v-deep .el-dialog__body {
  padding: 12px;
  background: linear-gradient(180deg, #f3f6fb 0%, #f5f7fa 100%);
  max-height: calc(100vh - 152px);
  overflow-y: auto;
}

.ledger-detail-dialog ::v-deep .el-dialog__footer {
  padding: 10px 18px;
  border-top: 1px solid #ebeef5;
  background: #fff;
}

.ledger-detail {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.ledger-detail-dialog .detail-content-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.15fr) minmax(0, 0.85fr);
  gap: 8px;
  align-items: start;
}

.ledger-detail-dialog .detail-section-desc,
.ledger-detail-dialog .detail-section-records {
  height: 100%;
}

.ledger-detail-dialog .detail-section-records {
  max-height: 360px;
  overflow-y: auto;
}

@media (max-width: 960px) {
  .ledger-detail-dialog .detail-content-grid {
    grid-template-columns: 1fr;
  }

  .ledger-detail-dialog .detail-section-records {
    max-height: none;
  }
}

.ledger-detail-dialog .detail-section {
  padding: 10px 12px;
  border: 1px solid #ebeef5;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.ledger-detail-dialog .section-title {
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 8px;
  padding-left: 10px;
  border-left-width: 3px;
  line-height: 1.2;
}

.ledger-detail-dialog .header-flex {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 8px;
  padding-bottom: 8px;
  border-bottom: 1px solid #f0f2f5;
}

.ledger-detail-dialog .header-left {
  font-weight: 600;
}

.ledger-detail-dialog .header-right {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.ledger-detail-dialog .header-meta-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  max-width: 220px;
  padding: 4px 10px;
  border-radius: 999px;
  background: #f4f7fc;
  border: 1px solid #e4ebf5;
  color: #606266;
  font-size: 13px;
  line-height: 1.3;
}

.ledger-detail-dialog .header-meta-chip i {
  color: #7a869a;
  font-size: 14px;
}

.ledger-detail-dialog .header-value {
  max-width: 168px;
  font-weight: 500;
  color: #303133;
}

.ledger-detail-dialog .detail-summary-card {
  margin-bottom: 8px;
  padding: 10px 12px;
  border: 1px solid #e3eaf3;
  border-radius: 8px;
  background: linear-gradient(180deg, #fbfcfe 0%, #f6f9fc 100%);
}

.ledger-detail-dialog .detail-title-label {
  margin-bottom: 4px;
  color: #909399;
  font-size: 12px;
  letter-spacing: 0.2px;
}

.ledger-detail-dialog .detail-title-line {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.ledger-detail-dialog .detail-title-value {
  flex: 1;
  min-width: 0;
  color: #1f2d3d;
  font-size: 17px;
  font-weight: 600;
  line-height: 1.4;
  word-break: break-word;
}

.ledger-detail-dialog .detail-title-status {
  flex: 0 0 auto;
}

.ledger-detail-dialog .detail-relation-line {
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px dashed #e4ebf5;
  font-size: 13px;
  color: #606266;
}

.ledger-detail-dialog .detail-meta-panel {
  padding: 8px 10px;
  border-radius: 8px;
  background: #f8fafc;
  border: 1px solid #edf1f7;
}

.ledger-detail-dialog .detail-kv-grid {
  gap: 8px 16px;
}

.ledger-detail-dialog .kv-item {
  gap: 3px;
  min-width: 0;
}

.ledger-detail-dialog .kv-label {
  font-size: 12px;
  color: #909399;
}

.ledger-detail-dialog .kv-value {
  font-size: 13px;
  font-weight: 500;
  color: #303133;
  line-height: 1.4;
}

.ledger-detail-dialog .detail-completion-card,
.ledger-detail-dialog .detail-closed-card {
  margin-top: 8px;
  padding: 10px 12px;
  border-radius: 8px;
}

.ledger-detail-dialog .detail-completion-reply {
  margin-top: 8px;
  padding-top: 8px;
}

.ledger-detail-dialog .detail-section-desc .section-title {
  margin-bottom: 6px;
}

.ledger-detail-dialog .text-block {
  padding: 10px 12px;
  border-radius: 8px;
  background: #fafbfd;
}

.ledger-detail-dialog .text-value {
  font-size: 13px;
  line-height: 1.6;
  color: #4a5568;
}

.ledger-detail-dialog .text-empty {
  color: #c0c4cc;
}

.ledger-detail-dialog .rich-html ::v-deep p {
  margin: 0 0 6px;
}

.ledger-detail-dialog .rich-html ::v-deep p:last-child {
  margin-bottom: 0;
}

.ledger-detail-dialog .record-timeline {
  padding: 2px 0 0 2px;
}

.ledger-detail-dialog .record-timeline ::v-deep .el-timeline-item {
  padding-bottom: 12px;
}

.ledger-detail-dialog .record-timeline ::v-deep .el-timeline-item__timestamp {
  font-size: 12px;
  color: #909399;
  line-height: 1.35;
}

.ledger-detail-dialog .record-content {
  padding: 10px 12px;
  border-radius: 8px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}

.ledger-detail-dialog .record-user {
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 5px;
}

.ledger-detail-dialog .record-text {
  font-size: 13px;
  line-height: 1.55;
  margin-bottom: 6px;
}

.ledger-detail-dialog .record-status {
  background: #f4f6f8;
}

.ledger-detail-dialog .record-actions-section {
  padding: 12px 14px 14px;
}

.ledger-detail-dialog .record-input {
  margin-bottom: 12px;
}

.ledger-detail-dialog .record-input ::v-deep .el-textarea__inner {
  min-height: 96px !important;
  padding: 10px 12px;
  border-radius: 8px;
}

.ledger-detail-dialog .record-timeline .is-current .record-content::before {
  top: 12px;
}

.ledger-detail-dialog ::v-deep .status-tag-release.is-plain {
  color: #8e44ad;
  background-color: #f4ecf7;
  border-color: #e8daef;
}

.ledger-detail-dialog ::v-deep .status-tag-closed.is-plain {
  color: #909399;
  background-color: #f4f4f5;
  border-color: #e9e9eb;
}

.detail-section {
  background: #fff;
  border-radius: 8px;
  padding: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  border: none;
}

.ledger-form-dialog ::v-deep .el-dialog__body {
  padding: 8px 12px 48px;
  background: #f7f8fa;
  max-height: calc(100vh - 148px);
  overflow-y: auto;
  overflow-x: hidden;
}

.ledger-form-dialog ::v-deep .el-dialog {
  max-width: 96vw;
  margin-top: 4vh !important;
}

.ledger-form-dialog ::v-deep .el-dialog__footer {
  position: sticky;
  bottom: 0;
  background: #fff;
  border-top: 1px solid #ebeef5;
  padding: 8px 18px;
  z-index: 1;
}

.ledger-form {
  .el-form-item {
    margin-bottom: 6px;
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
  padding: 7px 12px 3px;
  margin-bottom: 6px;
}

.ledger-form-section .section-title {
  font-size: 14px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 1px;
  padding-left: 10px;
  border-left: 3px solid #409EFF;
  margin-top: 1px;
}

.ledger-form-section.section-related {
  padding: 6px 10px 2px;
  margin-bottom: 4px;
}

.ledger-form-section.section-related .section-title {
  margin-bottom: 6px;
}

.relation-form-item ::v-deep .el-form-item__content {
  position: relative;
  margin-left: 0 !important;
}

.relation-summary-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  min-height: 54px;
  padding: 8px 12px;
  border: 1px solid #e4e7ed;
  border-radius: 6px;
  background: #fbfcff;
}

.relation-summary-main {
  min-width: 0;
}

.relation-summary-row {
  display: flex;
  align-items: flex-start;
  min-width: 0;
  color: #606266;
  font-size: 13px;
  line-height: 22px;
}

.relation-summary-label {
  flex: 0 0 auto;
  color: #909399;
}

.relation-summary-value {
  min-width: 0;
  color: #303133;
  font-weight: 500;
  word-break: break-all;
}

.section-track ::v-deep .el-form-item__label,
.section-core ::v-deep .el-form-item__label,
.section-completion ::v-deep .el-form-item__label {
  float: none;
  display: block;
  width: auto !important;
  line-height: 20px;
  padding: 0 0 6px;
  color: #606266;
  font-size: 13px;
  text-align: left;
}

.section-track ::v-deep .el-form-item__content,
.section-core ::v-deep .el-form-item__content,
.section-completion ::v-deep .el-form-item__content {
  margin-left: 0 !important;
  line-height: normal;
}

.section-track .section-title,
.section-core .section-title,
.section-completion .section-title,
.section-closed .section-title {
  margin: 0 0 8px;
  line-height: 1.2;
}

.section-track,
.section-core,
.section-completion,
.section-closed {
  padding: 8px 12px 10px;
}

.section-core .el-form-item,
.section-track .el-form-item,
.section-completion .el-form-item {
  margin-bottom: 0;
}

.track-grid {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 0;
}

.track-grid-row {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px 16px;
  align-items: start;
  min-width: 0;
}

.track-grid-row--3 {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.track-grid-row--task {
  grid-template-columns: repeat(2, minmax(0, 1fr));
  padding-top: 2px;
  border-top: 1px dashed #ebeef5;
}

.track-grid-row .el-form-item {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  min-width: 0;
  margin-bottom: 0;
}

.section-track ::v-deep .user-container {
  width: 100%;
  min-height: 36px;
  margin: 0;
}

.section-completion {
  margin-top: 0;
}

.section-title-success {
  border-left-color: #67c23a !important;
}

.section-title-danger {
  border-left-color: #f56c6c !important;
}

.section-closed {
  margin-top: 0;
}

.close-reason-item ::v-deep .el-form-item__label {
  color: #f56c6c;
  font-weight: 600;
}

.detail-closed-card {
  margin-top: 12px;
  padding: 10px 12px;
  border: 1px solid #fde2e2;
  border-radius: 6px;
  background: #fef0f0;
}

.section-core .form-item-strong {
  margin-bottom: 0;
}

.section-core .form-item-full {
  margin-top: 8px;
}

.title-label-row {
  display: inline-flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.title-label-error {
  color: #f56c6c;
  font-size: 12px;
  font-weight: normal;
  line-height: 20px;
}

.form-item-strong ::v-deep .el-input__inner {
  font-size: 15px;
  font-weight: 600;
}

.form-item-strong {
  margin-bottom: 0;
}

.form-item-strong ::v-deep .el-form-item__error {
  display: none;
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

.ledger-form-section.section-process {
  padding-bottom: 0;
}

.completion-inline-field ::v-deep .el-form-item__label {
  color: #2f7d32;
  font-weight: 600;
  padding-bottom: 8px;
}

.section-completion ::v-deep .el-row {
  margin-top: 2px;
}

.section-completion ::v-deep .el-col .el-form-item {
  margin-bottom: 0;
}

.completion-reply-item ::v-deep .el-form-item__label {
  padding-bottom: 8px;
}

.completion-reply-item ::v-deep .el-textarea__inner {
  min-height: 64px !important;
}

.ledger-form ::v-deep .el-input__inner,
.ledger-form ::v-deep .el-date-editor .el-input__inner {
  height: 36px;
  line-height: 36px;
}

.ledger-form ::v-deep .el-form-item__label,
.ledger-form ::v-deep .el-form-item__content {
  line-height: 36px;
}

.section-core ::v-deep .el-form-item__label,
.section-completion ::v-deep .el-form-item__label {
  line-height: 20px;
  padding: 0 0 6px;
}

.section-track ::v-deep .el-form-item__label {
  line-height: 20px;
  padding: 0 0 6px;
}

.section-core ::v-deep .el-form-item__content,
.section-completion ::v-deep .el-form-item__content,
.section-track ::v-deep .el-form-item__content {
  line-height: normal;
}

.ledger-form ::v-deep .el-textarea__inner {
  min-height: 68px !important;
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

.quick-select-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.quick-select-row .quick-select-type {
  width: 120px;
  flex: 0 0 auto;
}

.quick-select-row ::v-deep .el-input {
  flex: 1 1 260px;
  min-width: 200px;
}

.quick-select-row .el-button {
  flex: 0 0 auto;
}

.quick-selected {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 6px;
}

.quick-selected ::v-deep .el-tag {
  max-width: 100%;
  height: auto;
  white-space: normal;
  line-height: 18px;
}

.quick-selected ::v-deep .el-tag__content {
  white-space: normal;
  word-break: break-all;
}

.quick-selected__meta {
  font-size: 12px;
  color: #909399;
  white-space: normal;
  word-break: break-all;
}

.section-title {
  font-size: 16px;
  font-weight: 500;
  color: #303133;
  margin-bottom: 16px;
  padding-left: 12px;
  border-left: 4px solid #409EFF;
  line-height: 1;
  display: flex;
  align-items: center;
}

.header-flex {
  justify-content: space-between;
  border-bottom: 1px solid #EBEEF5;
  padding-bottom: 12px;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.header-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  color: #606266;
}

.header-item i {
  color: #909399;
}

.header-value {
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-weight: 500;
}

.detail-summary-card {
  margin-bottom: 12px;
  padding: 12px;
  border: 1px solid #ebeef5;
  border-radius: 6px;
  background: #f8f9fb;
}

.detail-title-label {
  margin-bottom: 6px;
  color: #909399;
  font-size: 13px;
}

.detail-title-value {
  color: #303133;
  font-size: 18px;
  font-weight: 600;
  line-height: 1.35;
  word-break: break-all;
}

.detail-relation-line {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 8px;
  color: #606266;
  font-size: 14px;
  line-height: 1.4;
  word-break: break-all;
}

.kv-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px 24px;
}

.detail-kv-grid {
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px 18px;
}

.kv-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.kv-item.full-width {
  grid-column: span 4;
}

.kv-item.half-width {
  grid-column: span 2;
}

.header-id {
  font-size: 13px;
  color: #909399;
  font-weight: normal;
  margin-left: 8px;
  background: #f4f4f5;
  padding: 2px 6px;
  border-radius: 4px;
}

.highlight-text {
  font-size: 15px;
  font-weight: 500;
  color: #303133;
}

.kv-label {
  font-size: 13px;
  color: #909399;
}

.kv-value {
  font-size: 14px;
  color: #303133;
  font-weight: 500;
  word-break: break-all;
  line-height: 1.4;
}

.time-badge {
  margin-left: 8px;
  vertical-align: middle;
}

.detail-completion-card {
  margin-top: 12px;
  padding: 10px 12px;
  border: 1px solid #e1f3d8;
  border-radius: 6px;
  background: #f0f9eb;
}

.detail-completion-time {
  display: flex;
  align-items: center;
  gap: 12px;
}

.detail-completion-time .kv-label {
  flex: 0 0 auto;
}

.detail-completion-reply {
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px solid #d9ecff;
}

.text-block {
  background: #f8f9fb;
  border-radius: 6px;
  padding: 12px;
  margin-bottom: 0;
  border: 1px solid #ebeef5;
}

.text-block:last-child {
  margin-bottom: 0;
}

.text-label {
  font-size: 13px;
  color: #909399;
  margin-bottom: 8px;
  font-weight: 500;
}

.text-value {
  font-size: 14px;
  color: #606266;
  line-height: 1.6;
  white-space: pre-wrap;
}

.rich-html {
  white-space: normal;
}

.rich-html ::v-deep p {
  margin: 0 0 8px;
}

.rich-html ::v-deep img {
  max-width: 100%;
  height: auto;
  display: block;
  margin: 8px 0;
  border-radius: 6px;
}

/* Timeline Styling */
.record-timeline {
  padding-left: 4px;
  padding-top: 8px;
}

.record-timeline ::v-deep .el-timeline-item__node {
  background-color: #fff;
  border: 2px solid #e4e7ed;
  box-sizing: border-box;
}

.record-timeline ::v-deep .el-timeline-item__node--primary {
  border-color: #409EFF;
  background-color: #409EFF;
}

.record-timeline ::v-deep .el-timeline-item__tail {
  border-left: 2px solid #e4e7ed;
}

.record-content {
  padding: 12px 16px;
  background: #f9fafc;
  border-radius: 8px;
  border: 1px solid #ebeef5;
  transition: all 0.3s;
  position: relative;
}

.record-timeline .is-current .record-content {
  background: #ecf5ff;
  border-color: #b3d8ff;
  box-shadow: 0 2px 8px rgba(64, 158, 255, 0.1);
}

.record-timeline .is-current .record-content::before {
  content: '';
  position: absolute;
  left: -6px;
  top: 14px;
  width: 10px;
  height: 10px;
  background: #ecf5ff;
  border-left: 1px solid #b3d8ff;
  border-bottom: 1px solid #b3d8ff;
  transform: rotate(45deg);
}

.record-user {
  font-size: 14px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 6px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.record-text {
  font-size: 14px;
  color: #606266;
  line-height: 1.5;
  margin-bottom: 8px;
}

.record-status {
  font-size: 12px;
  color: #909399;
  background: rgba(0,0,0,0.03);
  padding: 2px 8px;
  border-radius: 4px;
  display: inline-block;
}

/* Record Actions */
.record-actions-section {
  padding: 18px 20px 20px;
}

.record-actions-section ::v-deep .el-divider__text {
  font-size: 15px;
  font-weight: 600;
  color: #303133;
}


.record-input {
  display: block;
  width: 100%;
  margin-bottom: 16px;
}

.record-input ::v-deep .el-textarea__inner {
  width: 100%;
  min-height: 118px !important;
  padding: 12px;
  font-size: 14px;
  line-height: 1.6;
  border-radius: 6px;
  resize: vertical;
  box-sizing: border-box;
}

.record-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  align-items: center;
  gap: 16px;
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
  width: 260px;
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

/* Quick Search Styling */
.quick-select-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  padding: 0;
  margin-top: 8px;
  margin-bottom: 0;
}


.quick-select-type {
  width: 120px;
  flex: 0 0 auto;
}

.quick-select-row ::v-deep .el-input {
  flex: 1 1 260px;
  min-width: 200px;
}

.quick-result {
  position: absolute;
  top: 100%;
  left: 0;
  width: 100%;
  z-index: 2000;
  margin-top: 4px;
  border: 1px solid #e4e7ed;
  border-radius: 4px;
  background: #fff;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.quick-result__header {
  padding: 0 12px;
  height: 36px;
  background-color: #f5f7fa;
  border-bottom: 1px solid #ebeef5;
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 13px;
  color: #909399;
}

.quick-result__header .el-button {
  margin-left: auto;
}

.quick-result__header ::v-deep .el-radio-button__inner {
  padding: 5px 8px;
  font-size: 12px;
}

.quick-result__body {
  max-height: 240px;
  overflow-y: auto;
}

.quick-result__item {
  padding: 6px 12px;
  border-bottom: 1px solid #f0f2f5;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.quick-result__item:last-child {
  border-bottom: none;
}

.quick-result__item:hover {
  background-color: #ecf5ff;
}

.quick-result__name {
  font-size: 14px;
  color: #303133;
  font-weight: 500;
  min-width: 0;
  padding-right: 12px;
  word-break: break-all;
}

.quick-result__meta {
  flex: 0 0 auto;
  font-size: 12px;
  color: #909399;
}

.quick-result__empty {
  padding: 20px;
  text-align: center;
  color: #909399;
  font-size: 13px;
}

@media (max-width: 900px) {
  .kv-grid {
    grid-template-columns: 1fr 1fr;
  }

  .detail-kv-grid {
    grid-template-columns: 1fr 1fr;
  }

  .header-flex,
  .header-right {
    align-items: flex-start;
    flex-direction: column;
    gap: 8px;
  }
}

@media (max-width: 600px) {
  .ledger-page {
    padding: 8px;
  }
  .kv-grid {
    grid-template-columns: 1fr;
  }
  .filter-form {
    flex-direction: column;
    align-items: stretch;
  }
  .filter-form .el-input,
  .filter-form .el-select,
  .filter-form .el-date-editor {
    width: 100%;
  }

  .relation-summary-card {
    align-items: flex-start;
    flex-direction: column;
    gap: 6px;
  }

  .track-grid-row,
  .track-grid-row--3,
  .track-grid-row--task {
    grid-template-columns: 1fr;
  }

  .detail-kv-grid {
    grid-template-columns: 1fr;
  }

  .detail-completion-time {
    align-items: flex-start;
    flex-direction: column;
    gap: 4px;
  }

  .quick-result__header {
    height: auto;
    min-height: 36px;
    flex-wrap: wrap;
    padding: 6px 12px;
  }

  .quick-result__item {
    align-items: flex-start;
    flex-direction: column;
    gap: 4px;
  }

  .quick-result__meta {
    flex: none;
  }
}
</style>

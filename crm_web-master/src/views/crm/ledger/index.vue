<template>
  <div
    v-empty="!canRead"
    xs-empty-icon="nopermission"
    xs-empty-text="暂无权限"
    class="ledger-page">
    <div class="dashboard">
      <div class="dashboard-card total" @click="quickFilter('')">
        <div class="card-icon"><i class="el-icon-s-data"/></div>
        <div class="card-info">
          <div class="card-label">总台账</div>
          <div class="card-num">{{ stats.total }}</div>
        </div>
      </div>
      <div class="dashboard-card pending" @click="quickFilter('待处理')">
        <div class="card-icon"><i class="el-icon-bell"/></div>
        <div class="card-info">
          <div class="card-label">待处理</div>
          <div class="card-num">{{ stats.pending }}</div>
        </div>
      </div>
      <div class="dashboard-card processing" @click="quickFilter('处理中')">
        <div class="card-icon"><i class="el-icon-service"/></div>
        <div class="card-info">
          <div class="card-label">处理中</div>
          <div class="card-num">{{ stats.processing }}</div>
        </div>
      </div>
      <div class="dashboard-card completed" @click="quickFilter('已完成')">
        <div class="card-icon"><i class="el-icon-success"/></div>
        <div class="card-info">
          <div class="card-label">已完成</div>
          <div class="card-num">{{ stats.completed }}</div>
        </div>
      </div>
    </div>
    <div class="filter-bar">
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
                <span class="customer-option-full" :title="item.customer_name">{{ item.customer_name }}</span>
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
                <span class="customer-option-full" :title="item.contract_full_name">{{ item.contract_full_name }}</span>
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
          <div class="desc-preview-wrap" :title="descriptionCellTitle(scope.row)">
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
          <el-tag :type="statusTagType(scope.row.status)" size="mini">{{ scope.row.status }}</el-tag>
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
          {{ formatListTime(scope.row.finish_time) }}
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

    <el-dialog :title="formTitle" :visible.sync="formVisible" :before-close="handleFormBeforeClose" width="980px" append-to-body class="ledger-form-dialog">
      <el-form ref="ledgerForm" :model="form" :rules="rules" label-width="110px" class="ledger-form">
        <div class="ledger-form-section section-related">
          <div class="section-title">关联对象</div>
          <el-form-item label="关联对象" prop="contract_id" class="form-item-full">
            <div class="quick-select-row">
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
            <div v-if="selectedContract" class="quick-selected">
              <el-tag type="success" closable @close="clearSelectedContract">
                {{ selectedContractLabel }}
              </el-tag>
              <span v-if="selectedContractCustomer" class="quick-selected__meta">客户：{{ selectedContractCustomer }}</span>
            </div>
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

    <el-dialog :visible.sync="detailVisible" title="台账详情" width="1000px" append-to-body class="ledger-detail-dialog">
      <div class="ledger-detail">
        <section class="detail-section">
          <div class="section-title header-flex">
            <div class="header-left">
              基础信息
              <span class="header-id">#{{ detail.ledger_id }}</span>
            </div>
            <div class="header-right">
              <div class="header-item" title="客户">
                <i class="el-icon-office-building"/>
                <span class="header-value">{{ detail.customer_name || '—' }}</span>
              </div>
              <div class="header-item" title="反馈人">
                <i class="el-icon-user"/>
                <span class="header-value">{{ detail.feedback_user || '—' }}</span>
              </div>
              <div class="header-item">
                <el-tag
                  :type="detail.status === '已完成' ? 'success' : detail.status === '处理中' ? 'warning' : detail.status === '已关闭' ? 'danger' : 'info'"
                  size="small">
                  {{ detail.status || '—' }}
                </el-tag>
              </div>
            </div>
          </div>
          <div class="kv-grid">
            <div class="kv-item full-width">
              <div class="kv-label">反馈问题</div>
              <div class="kv-value highlight-text">{{ detail.title || '—' }}</div>
            </div>
            <div v-if="detail.business_name || detail.contract_name || detail.contract_num" class="kv-item full-width">
              <div class="kv-label">关联对象</div>
              <div class="kv-value">
                <el-tag v-if="detail.business_name" type="warning" size="mini" effect="plain" style="margin-right: 6px;">商机</el-tag>
                <el-tag v-else-if="detail.contract_name || detail.contract_num" size="mini" effect="plain" style="margin-right: 6px;">合同</el-tag>
                {{ relationDetailName(detail) || '—' }}
              </div>
            </div>

            <div class="kv-item">
              <div class="kv-label">问题分类</div>
              <div class="kv-value">{{ detail.category || '—' }}</div>
            </div>
            <div class="kv-item">
              <div class="kv-label">反馈渠道</div>
              <div class="kv-value">{{ detail.feedback_channel || '微信' }}</div>
            </div>
            <div class="kv-item">
              <div class="kv-label">登记人</div>
              <div class="kv-value">{{ detail.register_user_name || '—' }}</div>
            </div>
            <div class="kv-item">
              <div class="kv-label">处理人</div>
              <div class="kv-value">{{ detail.handler_user_name || '—' }}</div>
            </div>

            <div class="kv-item">
              <div class="kv-label">反馈时间</div>
              <div class="kv-value">{{ (detail.feedback_time || detail.register_time) ? (detail.feedback_time || detail.register_time).slice(0, 16) : '—' }}</div>
            </div>
            <div class="kv-item half-width">
              <div class="kv-label">完成时间</div>
              <div class="kv-value">
                {{ displayFinishTime }}
                <el-tag
                  v-if="finishBadge.text"
                  :type="finishBadge.type"
                  size="mini"
                  class="time-badge">
                  {{ finishBadge.text }}
                </el-tag>
              </div>
            </div>
          </div>
        </section>

        <section class="detail-section">
          <div class="section-title">描述信息</div>
          <div class="text-block">
            <div class="text-label">问题描述</div>
            <div v-if="detail.description" class="text-value rich-text rich-html" v-html="detail.description" />
            <div v-else class="text-value">—</div>
          </div>
          <div v-if="detail.remark" class="text-block">
            <div class="text-label">备注</div>
            <div class="text-value">{{ detail.remark || '—' }}</div>
          </div>
        </section>



        <section v-loading="recordLoading" v-if="recordList.length > 1" class="detail-section">
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
                <div v-if="item.old_status && item.new_status && item.old_status != item.new_status" class="record-status">
                  状态：{{ item.old_status }} → {{ item.new_status }}
                </div>
              </div>
            </el-timeline-item>
          </el-timeline>
        </section>

        <section v-if="detail.status != '已完成'" class="detail-section record-actions-section">
          <el-divider content-position="left">补充处理</el-divider>
          <el-input
            v-model.trim="recordForm.content"
            :rows="4"
            :disabled="isRecordLocked"
            type="textarea"
            placeholder="填写处理结果"
            class="record-input" />
          <div class="record-actions">
            <el-select
              v-model="recordForm.new_status"
              :disabled="detail.status === '已完成'"
              clearable
              placeholder="变更状态（可选）"
              class="record-select">
              <el-option v-for="item in statusOptions" :key="item" :label="item" :value="item" />
            </el-select>
            <el-button :disabled="isRecordLocked" type="primary" @click="addRecord">{{ recordForm.new_status === '已完成' ? '完成' : (isRecordLocked ? '已完成' : '新增记录') }}</el-button>
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
import Tinymce from '@/components/Tinymce'
import { workIndexWorkListAPI } from '@/api/pm/task'
import { workWorkStatisticAPI } from '@/api/pm/statistics'
import { downloadExcelWithResData } from '@/utils'

export default {
  name: 'CustomerLedger',
  components: {
    XhUserCell,
    Tinymce
  },
  data() {
    return {
      stats: {
        total: 0,
        pending: 0,
        processing: 0,
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
      statusOptions: ['待处理', '处理中', '待验证', '已完成', '已关闭'],
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
      formTitle: '新建台账',
      formSubmitting: false,
      form: {},
      skipDraftSaveOnce: false,
      quickSearchType: 'contract',
      quickKeyword: '',
      quickResults: [],
      quickLoading: false,
      quickVisible: false,
      exportLoading: false,
      feedbackContactsOptions: [],
      feedbackContactsLoading: false,
      rules: {
        contract_id: [{ validator: (rule, value, callback) => this.validateRelation(rule, value, callback), trigger: 'change' }],
        title: [{ required: true, message: '请填写反馈问题', trigger: 'blur' }],
        register_user_id: [{ validator: (rule, value, callback) => this.validateUserSelect(value, '请选择登记人', callback), trigger: 'change' }],
        handler_user_id: [{ validator: (rule, value, callback) => this.validateUserSelect(value, '请选择处理人', callback), trigger: 'change' }]
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
    isCompleted() {
      return this.detail.status === '已完成'
    },
    isRecordLocked() {
      return this.detail.status === '已完成'
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
    canRead(val) {
      if (val && this.list.length === 0) {
        this.getList()
      }
    }
  },
  created() {
    this.filters.feedback_date = this.getDefaultFilterDateRange()
    const hasRouteFilter = this.applyRouteQuery(true)
    if (this.canRead && !hasRouteFilter) this.getList()
    this.fetchCategoryOptions()
    this.fetchWorkOptions()
    this.fetchStats()
  },
  methods: {
    fetchStats() {
      if (!this.canRead) return
      const p1 = ledgerIndexAPI({ page: 1, limit: 1 }).then(res => (res.data ? res.data.dataCount : 0))
      const p2 = ledgerIndexAPI({ page: 1, limit: 1, status: '待处理' }).then(res => (res.data ? res.data.dataCount : 0))
      const p3 = ledgerIndexAPI({ page: 1, limit: 1, status: '处理中' }).then(res => (res.data ? res.data.dataCount : 0))
      const p4 = ledgerIndexAPI({ page: 1, limit: 1, status: '已完成' }).then(res => (res.data ? res.data.dataCount : 0))

      Promise.all([p1, p2, p3, p4]).then(([total, pending, processing, completed]) => {
        this.stats = { total, pending, processing, completed }
      })
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
        '已完成': 'success',
        '已关闭': 'danger'
      }
      return map[status] || ''
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
        this.$message.error('请填写处理说明')
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
    openCreate() {
      this.recordList = []
      this.recordForm = { content: '', new_status: '' }
      this.recordOriginStatus = ''
      this.formOriginStatus = ''
      this.formOriginTaskId = 0
      const now = this.$moment().format('YYYY-MM-DD HH:mm:ss')
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
        category: '其他问题',
        status: '待处理',
        feedback_time: now,
        register_time: now,
        finish_time: '',
        work_id: '',
        class_id: '',
        handler_user_id: this.getCurrentUserSelection(),
        register_user_id: this.getCurrentUserSelection(),
        remark: ''
      }
      this.form = draft ? { ...baseForm, ...draft } : baseForm
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
        remark: row.remark
      }
      this.fetchClassOptions(form.work_id)
      this.quickSearchType = 'contract'
      this.quickKeyword = ''
      this.form = form
      this.loadFeedbackContacts()
      this.formVisible = true

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
      const fields = ['title', 'description', 'feedback_user', 'remark']
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
        limit: 200,
        is_ledger_filter: 1,
        order_field: 'ledger_count',
        order_type: 'desc'
      }
      if (keyword) params.search = keyword
      this.quickLoading = true
      this.quickVisible = true
      request(params).then(res => {
        const list = (res.data && res.data.list) ? res.data.list : []
        this.quickResults = this.sortByLedgerCount(list).slice(0, 50)
      }).catch(() => {
        this.quickResults = []
        this.$message.error('获取列表失败，请重试')
      }).finally(() => {
        this.quickLoading = false
      })
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
      payload.sync_task_status = 1
      return payload
    }
  }
}
</script>

<style lang="scss" scoped>
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
.dashboard-card.completed .card-icon { background: #f0f9eb; color: #67C23A; }


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
}

.ledger-detail-dialog ::v-deep .el-dialog__header {
  padding: 20px 24px;
  border-bottom: 1px solid #ebeef5;
  background: #fff;
}

.ledger-detail-dialog ::v-deep .el-dialog__body {
  padding: 24px;
  background-color: #f5f7fa;
  max-height: 70vh;
  overflow-y: auto;
}

.ledger-detail-dialog ::v-deep .el-dialog__footer {
  padding: 16px 24px;
  border-top: 1px solid #ebeef5;
  background: #fff;
}

.ledger-detail {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.detail-section {
  background: #fff;
  border-radius: 8px;
  padding: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  border: none;
}

.ledger-form-dialog ::v-deep .el-dialog__body {
  padding: 8px 14px 56px;
  background: #f7f8fa;
  max-height: 70vh;
  overflow-y: auto;
  overflow-x: hidden;
}

.ledger-form-dialog ::v-deep .el-dialog {
  max-width: 96vw;
}

.ledger-form-dialog ::v-deep .el-dialog__footer {
  position: sticky;
  bottom: 0;
  background: #fff;
  border-top: 1px solid #ebeef5;
  padding: 10px 18px;
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
  padding: 8px 12px 2px;
  margin-bottom: 8px;
}

.ledger-form-section .section-title {
  font-size: 14px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 6px;
  padding-left: 10px;
  border-left: 3px solid #409EFF;
  margin-top: 9px;
}

.ledger-form-section.section-related {
  padding: 4px 8px 0;
  margin-bottom: 4px;
}

.ledger-form-section.section-related .section-title {
  margin-bottom: 2px;
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

.kv-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px 24px;
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

.text-block {
  background: #f8f9fb;
  border-radius: 6px;
  padding: 16px;
  margin-bottom: 12px;
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
  align-items: center;
  gap: 10px;
  padding: 0 20px;
  margin-bottom: 0;
}


.quick-select-type {
  width: 120px;
  flex-shrink: 0;
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
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
  color: #909399;
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
}

.quick-result__meta {
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
}
</style>



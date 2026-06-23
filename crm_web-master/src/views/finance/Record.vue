<template>
  <div class="finance-record-panel">
    <el-card shadow="never" class="filter-card">
      <div :class="{ 'is-compact': hideCustomerFilter }" class="record-filter">
        <el-select
          v-if="!hideCustomerFilter"
          v-model="filters.customer_id"
          :popper-append-to-body="true"
          :remote-method="searchCustomers"
          :loading="customerLoading"
          class="filter-item filter-customer"
          size="mini"
          placeholder="请输入客户名称"
          filterable
          remote
          clearable
          style="width: 200px"
          @visible-change="handleCustomerFilterVisible">
          <el-option
            v-for="item in customerOptions"
            :key="item.customer_id"
            :label="item.name"
            :value="item.customer_id" />
        </el-select>

        <el-select
          v-model="filters.direction"
          class="filter-item"
          size="mini"
          placeholder="收支类型"
          clearable
          style="width: 112px"
          @change="handleFilterDirectionChange">
          <el-option label="收入" value="income" />
          <el-option label="支出" value="expense" />
        </el-select>

        <el-select
          v-model="filters.type_ids"
          class="filter-item"
          size="mini"
          placeholder="账目分类"
          multiple
          collapse-tags
          popper-class="finance-type-dropdown"
          style="width: 172px">
          <el-option-group
            v-for="group in filteredTypeGroupOptionsForFilter"
            :key="group.label"
            :label="group.label">
            <el-option
              v-for="item in group.children"
              :key="item.type_id"
              :label="item.label"
              :value="item.type_id" />
          </el-option-group>
        </el-select>

        <el-select
          v-model="filters.payment_method_id"
          class="filter-item"
          size="mini"
          placeholder="支付方式"
          clearable
          style="width: 132px">
          <el-option
            v-for="item in paymentMethodOptions"
            :key="item.method_id"
            :label="item.name"
            :value="item.method_id" />
        </el-select>

        <el-select
          v-model="filters.rel_type"
          class="filter-item"
          size="mini"
          placeholder="关联类型"
          clearable
          style="width: 124px">
          <el-option
            v-for="item in relationOptions"
            :key="item.value"
            :label="item.label"
            :value="item.value" />
        </el-select>

        <div class="filter-item filter-user">
          <xh-user-cell
            :value="filterHandlerUser"
            :radio="true"
            placeholder="处理人"
            @value-change="handleFilterHandlerChange" />
        </div>

        <el-date-picker
          v-model="filters.dateRange"
          class="filter-item filter-date"
          type="daterange"
          size="mini"
          start-placeholder="开始"
          end-placeholder="结束"
          value-format="yyyy-MM-dd"
          style="width: 250px" />
        <div class="filter-actions">
          <el-button type="primary" size="mini" @click="handleSearch">筛选</el-button>
          <el-button size="mini" @click="resetFilters">重置</el-button>
          <el-tooltip :disabled="recordAuth.save" effect="dark" content="暂无权限" placement="bottom">
            <el-button
              :disabled="!recordAuth.save"
              type="success"
              size="mini"
              @click="recordAuth.save && openDialog()">新增流水</el-button>
          </el-tooltip>
        </div>
      </div>
    </el-card>

    <el-alert
      v-if="recordError"
      class="fetch-error"
      title="加载失败"
      type="error"
      show-icon>
      <template #description>
        {{ recordError }}
        <el-button size="mini" type="primary" style="margin-left: 8px;" @click="loadRecords">重试</el-button>
      </template>
    </el-alert>

    <el-alert
      v-if="customFieldError"
      class="fetch-error"
      title="自定义字段不可用"
      type="warning"
      show-icon>
      <template #description>
        自定义字段模块不可用，基础功能仍可使用。
      </template>
    </el-alert>

    <el-card shadow="never" class="statistics-card">
      <div class="finance-statistics">
        <div class="stat-item">
          <span class="stat-label">总记录数：</span>
          <span class="stat-value">{{ statistics.totalCount }}</span>
        </div>
        <div class="stat-item income-stat">
          <span class="stat-label">收入总额：</span>
          <span class="stat-value income-value">+{{ statistics.totalIncome }}</span>
        </div>
        <div class="stat-item expense-stat">
          <span class="stat-label">支出总额：</span>
          <span class="stat-value expense-value">-{{ statistics.totalExpense }}</span>
        </div>
        <div class="stat-item profit-stat">
          <span class="stat-label">盈亏：</span>
          <span :class="['stat-value', parseFloat(statistics.profit) >= 0 ? 'profit-positive' : 'profit-negative']">
            {{ parseFloat(statistics.profit) >= 0 ? '+' : '' }}{{ statistics.profit }}
          </span>
        </div>
      </div>
    </el-card>

    <el-card shadow="never" class="table-card">
      <el-table
        v-loading="recordLoading"
        :data="records"
        :default-sort="{ prop: 'occur_date', order: 'descending' }"
        border
        stripe
        size="medium"
        style="width: 100%"
        @sort-change="handleTableSortChange">
        <el-table-column prop="record_id" label="ID" width="68" align="center" />
        <el-table-column prop="occur_date" label="收支时间" width="148" sortable="custom" />
        <el-table-column label="关联对象" min-width="240" show-overflow-tooltip>
          <template slot-scope="scope">{{ relationLabel(scope.row || {}) }}</template>
        </el-table-column>
        <el-table-column label="收支类型" width="96" align="center">
          <template slot-scope="scope">
            <el-tag :type="scope.row.direction === 'income' ? 'success' : 'danger'" size="mini" effect="plain">
              {{ scope.row.direction === 'income' ? '收入' : '支出' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="账目分类" width="146" show-overflow-tooltip>
          <template slot-scope="scope">
            <span :class="{'income-type': scope.row.direction === 'income', 'expense-type': scope.row.direction === 'expense'}">
              {{ typeLabel(scope.row && scope.row.type_id) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="amount" label="收支金额(元)" width="130" align="right">
          <template slot-scope="scope">
            <span :class="{'income-amount': scope.row.direction === 'income', 'expense-amount': scope.row.direction === 'expense'}">
              {{ scope.row.direction === 'income' ? '+' : '-' }}{{ parseFloat(scope.row.amount || 0).toFixed(2) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="支付方式" width="120" show-overflow-tooltip>
          <template slot-scope="scope">{{ scope.row.payment_method_name || '-' }}</template>
        </el-table-column>
        <el-table-column prop="handler_user_name" label="处理人" width="108" show-overflow-tooltip />
        <el-table-column :sort-by="['create_time']" label="创建时间" width="168" sortable="custom">
          <template slot-scope="scope">{{ scope.row.create_time || '-' }}</template>
        </el-table-column>
        <el-table-column label="备注" min-width="220" show-overflow-tooltip>
          <template slot-scope="scope">{{ scope.row.remark || '-' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="170" fixed="right" align="center">
          <template slot-scope="scope">
            <el-button type="text" size="small" @click="viewRecord(scope.row)">查看</el-button>
            <el-button :disabled="!recordAuth.update" type="text" size="small" @click="recordAuth.update && editRecord(scope.row)">编辑</el-button>
            <el-button :disabled="!recordAuth.delete" type="text" size="small" style="color: #F56C6C" @click="recordAuth.delete && confirmDelete(scope.row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
      <div class="pager-bar">
        <el-pagination
          :current-page="page"
          :page-sizes="[10, 20, 50, 100]"
          :page-size.sync="limit"
          :total="total"
          :pager-count="5"
          background
          layout="prev, pager, next, sizes, total, jumper"
          @size-change="handlePageSizeChange"
          @current-change="handlePageChange"
        />
      </div>
    </el-card>

    <el-dialog
      :visible.sync="dialog.visible"
      title="收支记录"
      width="600px"
      append-to-body
      class="finance-record-dialog">
      <el-form ref="recordForm" :model="dialog.form" :rules="recordRules" :disabled="dialog.isView" label-width="90px" class="finance-form">
        <div class="form-section section-main">
          <div class="section-title"><i class="el-icon-coin"/> 核心交易信息</div>
          <el-form-item label="收支方向" prop="direction">
            <el-radio-group v-model="dialog.form.direction" size="small" @change="handleDirectionChange">
              <el-radio-button label="expense">支出</el-radio-button>
              <el-radio-button label="income">收入</el-radio-button>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="收支金额" prop="amount">
            <div class="amount-container">
              <el-input-number
                v-model="dialog.form.amount"
                :precision="2"
                :step="1"
                :min="0"
                controls-position="right"
                placeholder="0.00"
                class="amount-input-number"/>
              <span class="amount-unit">元</span>
            </div>
          </el-form-item>
          <el-form-item label="收支时间" prop="occur_date">
            <el-date-picker
              v-model="dialog.form.occur_date"
              type="date"
              value-format="yyyy-MM-dd"
              placeholder="请选择日期"
              style="width: 100%"/>
          </el-form-item>
        </div>

        <div class="form-section">
          <div class="section-title"><i class="el-icon-files"/> 账目与关联</div>
          <el-form-item label="账目分类" prop="type_id">
            <el-select
              v-model="dialog.form.type_id"
              :placeholder="dialog.form.direction ? (dialog.form.direction === 'income' ? '请选择收入分类' : '请选择支出分类') : '请先选择收支方向'"
              :disabled="!dialog.form.direction"
              filterable
              popper-class="finance-type-dropdown"
              style="width: 100%">
              <el-option-group
                v-for="group in filteredTypeGroupOptions"
                :key="group.label"
                :label="group.label">
                <el-option
                  v-for="item in group.children"
                  :key="item.type_id"
                  :label="item.label"
                  :value="item.type_id" />
              </el-option-group>
            </el-select>
          </el-form-item>
          <el-form-item label="关联类型">
            <el-select v-model="dialog.form.rel_type" style="width: 100%" @change="handleRelTypeChange">
              <el-option
                v-for="item in relationOptions"
                :key="item.value"
                :label="item.label"
                :value="item.value" />
            </el-select>
          </el-form-item>
          <el-form-item v-if="dialog.form.rel_type !== 'none'" label="关联对象">
            <crm-relative-cell
              v-if="dialog.form.rel_type === 'customer' && (!customerId || !hideCustomerFilter)"
              :value="getCustomerValue"
              relative-type="customer"
              @value-change="handleCustomerChange" />
            <crm-relative-cell
              v-else-if="dialog.form.rel_type === 'contract' && !contractId"
              :value="getContractValue"
              :relation="contractRelation"
              relative-type="contract"
              @value-change="handleContractChange" />
            <crm-relative-cell
              v-else-if="dialog.form.rel_type === 'business' && !businessId"
              :value="getBusinessValue"
              :relation="businessRelation"
              relative-type="business"
              @value-change="handleBusinessChange" />
            <el-input v-else :value="relationDisplayName" disabled />
          </el-form-item>
        </div>

        <div class="form-section">
          <div class="section-title"><i class="el-icon-user"/> 执行人员</div>
          <el-form-item label="处理人" prop="handler_user_id">
            <xh-user-cell :value="handlerUserValue" @value-change="handleUserChange" />
          </el-form-item>
          <el-form-item label="记录人" prop="register_user_id">
            <xh-user-cell :value="registerUserValue" @value-change="handleRegisterUserChange" />
          </el-form-item>
          <el-form-item label="支付方式" prop="payment_method_id">
            <el-select v-model="dialog.form.payment_method_id" placeholder="请选择支付方式" clearable style="width: 100%">
              <el-option v-for="item in paymentMethodOptions" :key="item.method_id" :label="item.name" :value="item.method_id" />
            </el-select>
          </el-form-item>
        </div>

        <div class="form-section">
          <div class="section-title"><i class="el-icon-more"/> 辅助信息</div>
          <el-form-item label="凭证">
            <el-upload
              ref="voucherUpload"
              :action="crmFileSaveUrl"
              :headers="httpHeaders"
              :on-success="voucherUploadSuccess"
              :on-remove="voucherUploadRemove"
              :file-list="voucherFileList"
              :limit="5"
              name="file"
              multiple>
              <el-button size="small" icon="el-icon-upload2">上传文件</el-button>
            </el-upload>
          </el-form-item>
          <el-form-item label="备注">
            <el-input :rows="2" v-model="dialog.form.remark" type="textarea" placeholder="备注信息..." />
          </el-form-item>
        </div>
      </el-form>
      <div slot="footer" class="dialog-footer">
        <el-button @click="dialog.visible = false">{{ dialog.isView ? '关闭' : '取消' }}</el-button>
        <el-button v-if="!dialog.isView" type="primary" @click="saveRecord">保存</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import {
  financeRecordIndex,
  financeRecordSave,
  financeRecordUpdate,
  financeRecordDelete,
  financeTypeIndex,
  financePaymentMethodIndex
} from '@/api/finance'
import { crmCustomerIndexAPI } from '@/api/crm/customer'
import { crmBusinessNumAPI } from '@/api/crm/business'
import CrmRelativeCell from '@/components/CreateCom/CrmRelativeCell'
import { XhUserCell } from '@/components/CreateCom'

export default {
  name: 'FinanceRecordPanel',
  components: {
    CrmRelativeCell,
    XhUserCell
  },
  props: {
    customerId: {
      type: [String, Number],
      default: ''
    },
    businessId: {
      type: [String, Number],
      default: ''
    },
    contractId: {
      type: [String, Number],
      default: ''
    },
    hideCustomerFilter: {
      type: Boolean,
      default: false
    },
    customerDetail: {
      type: Object,
      default: () => ({})
    }
  },
  data() {
    return {
      records: [],
      page: 1,
      limit: 20,
      total: 0,
      typeOptions: [],
      typeGroupOptions: [],
      paymentMethodOptions: [],
      relationOptions: [
        { label: '无关联', value: 'none' },
        { label: '客户', value: 'customer' },
        { label: '合同', value: 'contract' },
        { label: '商机', value: 'business' }
      ],
      filters: {
        customer_id: '',
        rel_type: '',
        type_ids: [],
        direction: '',
        payment_method_id: '',
        dateRange: [],
        handler_user_id: '',
        orderBy: 'occur_date'
      },
      filterHandlerUser: [],
      dialog: {
        visible: false,
        isView: false,
        form: {
          customer_id: '',
          contract_id: '',
          business_id: '',
          rel_type: 'none',
          type_id: '',
          direction: 'expense',
          amount: 0,
          occur_date: '',
          handler_user_id: [],
          register_user_id: [],
          voucher: '',
          remark: ''
        },
        editingId: null
      },
      recordLoading: false,
      recordError: '',
      customerOptions: [],
      customerLoading: false,
      relationNames: {
        customer: null,
        contract: null,
        business: null
      },
      customFieldError: false,
      voucherBatchId: '',
      voucherFileList: [],
      statistics: {
        totalCount: 0,
        totalIncome: '0.00',
        totalExpense: '0.00',
        profit: '0.00'
      },
      recordRules: {
        direction: [
          { required: true, message: '请选择收支方向', trigger: 'change' }
        ],
        type_id: [
          { required: true, message: '请选择分类', trigger: 'change' }
        ],
        amount: [
          { required: true, message: '请输入金额', trigger: 'blur' },
          { type: 'number', min: 0.01, message: '金额必须大于0', trigger: 'blur' }
        ],
        occur_date: [
          { required: true, message: '请选择日期', trigger: 'change' }
        ],
        payment_method_id: [
          { required: true, message: '请选择支付方式', trigger: 'change' }
        ]
      },
      apiHealth: {
        prefix: window.BASE_URL || '/api/',
        proxyTarget: window.API_PROXY_TARGET || 'http://localhost',
        finalUrl: '',
        status: 'idle',
        message: '',
        snippet: '',
        httpStatus: null,
        contentType: '',
        route: 'finance/type/index'
      }
    }
  },
  computed: {
    recordAuth() {
      const allAuth = this.$store.getters.allAuth || {}
      return (allAuth.finance && allAuth.finance.record) || {}
    },
    contextCustomerId() {
      const detail = this.customerDetail || {}
      return this.customerId || detail.customer_id || detail.customerId || ''
    },
    contractRelation() {
      if (!this.contextCustomerId) return {}
      return {
        moduleType: 'customer',
        customer_id: this.contextCustomerId
      }
    },
    businessRelation() {
      if (!this.contextCustomerId) return {}
      return {
        moduleType: 'customer',
        customer_id: this.contextCustomerId
      }
    },
    contextHintText() {
      const detail = this.customerDetail || {}
      if (this.businessId) {
        const name = detail.business_name || detail.businessName || detail.name || ''
        return `商机：${name || `#${this.businessId}`}`
      }
      if (this.contractId) {
        const name = detail.contract_name || detail.contractName || detail.name || detail.num || detail.contract_num || ''
        return `合同：${name || `#${this.contractId}`}`
      }
      if (this.customerId) {
        const name = detail.customer_name || detail.customerName || detail.name || ''
        return `客户：${name || `#${this.customerId}`}`
      }
      return '客户：--'
    },
    showApiHealth() {
      return process.env.NODE_ENV === 'development'
    },
    crmFileSaveUrl() {
      return window.BASE_URL + 'admin/file/save'
    },
    httpHeaders() {
      const Lockr = require('lockr')
      return {
        authKey: Lockr.get('authKey') || '',
        sessionId: Lockr.get('sessionId') || ''
      }
    },
    filteredTypeGroupOptions() {
      if (!this.dialog.form.direction) {
        return []
      }
      const directionText = this.dialog.form.direction === 'income' ? '收入' : '支出'
      return this.typeGroupOptions.filter(group => {
        return group.label && group.label.includes(directionText)
      }).map(group => {
        return {
          ...group,
          children: group.children.filter(item => {
            if (item.direction) {
              return item.direction === this.dialog.form.direction
            }
            return true
          })
        }
      })
    },
    filteredTypeGroupOptionsForFilter() {
      if (!this.filters.direction) {
        return this.typeGroupOptions
      }
      return this.typeGroupOptions.filter(group => {
        return group.label && group.label.includes(this.filters.direction === 'income' ? '收入' : '支出')
      })
    },
    getCustomerValue() {
      if (!this.dialog.form.customer_id) return []
      if (typeof this.dialog.form.customer_id === 'number' || typeof this.dialog.form.customer_id === 'string') {
        if (this.relationNames.customer && this.relationNames.customer.customer_id == this.dialog.form.customer_id) {
          return [this.relationNames.customer]
        }
        if (this.customerDetail && this.customerDetail.name) {
          return [{ customer_id: this.dialog.form.customer_id, name: this.customerDetail.name }]
        }
        const record = this.records.find(r => r.customer_id == this.dialog.form.customer_id)
        if (record && record.customer_name) {
          return [{ customer_id: this.dialog.form.customer_id, name: record.customer_name }]
        }
        return [{ customer_id: this.dialog.form.customer_id, name: String(this.dialog.form.customer_id) }]
      }
      if (Array.isArray(this.dialog.form.customer_id)) {
        return this.dialog.form.customer_id
      }
      return []
    },
    getCustomerDisplayName() {
      if (!this.dialog.form.customer_id) return ''
      if (this.customerDetail && this.customerDetail.name) {
        return this.customerDetail.name
      }
      const record = this.records.find(r => r.customer_id == this.dialog.form.customer_id)
      if (record && record.customer_name) {
        return record.customer_name
      }
      return `客户#${this.dialog.form.customer_id}`
    },
    getContractValue() {
      if (!this.dialog.form.contract_id) return []
      if (typeof this.dialog.form.contract_id === 'number' || typeof this.dialog.form.contract_id === 'string') {
        if (this.relationNames.contract && this.relationNames.contract.contract_id == this.dialog.form.contract_id) {
          return [this.relationNames.contract]
        }
        const record = this.records.find(r => r.contract_id == this.dialog.form.contract_id)
        if (record && (record.contract_name || record.contract_num)) {
          return [{ contract_id: this.dialog.form.contract_id, name: record.contract_name || record.contract_num }]
        }
        return [{ contract_id: this.dialog.form.contract_id, name: String(this.dialog.form.contract_id) }]
      }
      if (Array.isArray(this.dialog.form.contract_id)) {
        return this.dialog.form.contract_id
      }
      return []
    },
    getBusinessValue() {
      if (!this.dialog.form.business_id) return []
      if (typeof this.dialog.form.business_id === 'number' || typeof this.dialog.form.business_id === 'string') {
        if (this.relationNames.business && this.relationNames.business.business_id == this.dialog.form.business_id) {
          return [this.relationNames.business]
        }
        if (this.customerDetail && (this.customerDetail.name || this.customerDetail.business_name)) {
          return [{
            business_id: this.dialog.form.business_id,
            name: this.customerDetail.business_name || this.customerDetail.name
          }]
        }
        const record = this.records.find(r => r.business_id == this.dialog.form.business_id)
        if (record && record.business_name) {
          return [{ business_id: this.dialog.form.business_id, name: record.business_name }]
        }
        return [{ business_id: this.dialog.form.business_id, name: String(this.dialog.form.business_id) }]
      }
      if (Array.isArray(this.dialog.form.business_id)) {
        return this.dialog.form.business_id
      }
      return []
    },
    handlerUserValue() {
      const value = this.dialog.form.handler_user_id
      if (Array.isArray(value)) return value
      if (value && typeof value === 'object') return [value]
      if (value || value === 0) return [{ id: value, realname: '' }]
      return []
    },
    registerUserValue() {
      const value = this.dialog.form.register_user_id
      if (Array.isArray(value)) return value
      if (value && typeof value === 'object') return [value]
      if (value || value === 0) return [{ id: value, realname: '' }]
      return []
    },
    relationDisplayName() {
      const relType = this.dialog.form.rel_type || 'none'
      if (relType === 'customer') {
        return this.getCustomerDisplayName
      }
      if (relType === 'business') {
        if (this.relationNames.business && (this.relationNames.business.business_name || this.relationNames.business.name)) {
          return this.relationNames.business.business_name || this.relationNames.business.name
        }
        if (this.customerDetail && (this.customerDetail.business_name || this.customerDetail.name)) {
          return this.customerDetail.business_name || this.customerDetail.name
        }
        const record = this.records.find(r => r.business_id == this.dialog.form.business_id)
        if (record && record.business_name) {
          return record.business_name
        }
        return this.dialog.form.business_id ? `商机#${this.dialog.form.business_id}` : ''
      }
      if (relType === 'contract') {
        if (this.relationNames.contract && (this.relationNames.contract.name || this.relationNames.contract.contract_name || this.relationNames.contract.contractNum || this.relationNames.contract.num)) {
          return this.relationNames.contract.name || this.relationNames.contract.contract_name || this.relationNames.contract.contractNum || this.relationNames.contract.num
        }
        if (this.customerDetail && (this.customerDetail.name || this.customerDetail.contract_name || this.customerDetail.num)) {
          return this.customerDetail.name || this.customerDetail.contract_name || this.customerDetail.num
        }
        const record = this.records.find(r => r.contract_id == this.dialog.form.contract_id)
        if (record && (record.contract_name || record.contract_num)) {
          return record.contract_name || record.contract_num
        }
        return this.dialog.form.contract_id ? `合同#${this.dialog.form.contract_id}` : ''
      }
      return ''
    }
  },
  watch: {
    customerId(val) {
      this.filters.customer_id = val || ''
      if (val) {
        this.dialog.form.customer_id = val
        this.dialog.form.rel_type = 'customer'
      } else if (!this.businessId && !this.contractId) {
        if (this.dialog.form.rel_type === 'customer') {
          this.dialog.form.rel_type = 'none'
        }
        this.dialog.form.customer_id = ''
      }
      this.loadRecords()
    },
    businessId(val) {
      if (val) {
        this.dialog.form.business_id = val
        this.dialog.form.rel_type = 'business'
        if (this.customerDetail && (this.customerDetail.business_name || this.customerDetail.name)) {
          this.relationNames.business = {
            business_id: val,
            name: this.customerDetail.business_name || this.customerDetail.name
          }
        }
        this.notifyBusinessContractAutoClassify(val)
      } else if (!this.customerId && !this.contractId) {
        if (this.dialog.form.rel_type === 'business') {
          this.dialog.form.rel_type = 'none'
        }
        this.dialog.form.business_id = ''
      }
      this.loadRecords()
    },
    contractId(val) {
      if (val) {
        this.dialog.form.contract_id = val
        this.dialog.form.rel_type = 'contract'
      } else if (!this.customerId && !this.businessId) {
        if (this.dialog.form.rel_type === 'contract') {
          this.dialog.form.rel_type = 'none'
        }
        this.dialog.form.contract_id = ''
      }
      this.loadRecords()
    }
  },
  mounted() {
    this.loadTypes()
    this.loadPaymentMethods()
    this.applyDefaultUsers(true)
    this.filters.customer_id = this.customerId || ''
    if (this.customerId) {
      this.dialog.form.customer_id = this.customerId
      this.dialog.form.rel_type = 'customer'
    } else if (this.businessId) {
      this.dialog.form.business_id = this.businessId
      this.dialog.form.rel_type = 'business'
      if (this.customerDetail && (this.customerDetail.business_name || this.customerDetail.name)) {
        this.relationNames.business = {
          business_id: this.businessId,
          name: this.customerDetail.business_name || this.customerDetail.name
        }
      }
    } else if (this.contractId) {
      this.dialog.form.contract_id = this.contractId
      this.dialog.form.rel_type = 'contract'
      if (this.customerDetail && (this.customerDetail.name || this.customerDetail.contract_name || this.customerDetail.num)) {
        this.relationNames.contract = {
          contract_id: this.contractId,
          name: this.customerDetail.name || this.customerDetail.contract_name || this.customerDetail.num
        }
      }
    }
    this.filters.dateRange = this.getDefaultFilterDateRange()
    this.loadRecords()
    if (this.showApiHealth) {
      this.checkApiHealth()
    }
    this.voucherBatchId = 'voucher_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9)
  },
  methods: {
    getDefaultFilterDateRange() {
      const today = this.$moment()
      return [
        today.clone().startOf('year').format('YYYY-MM-DD'),
        today.format('YYYY-MM-DD')
      ]
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
      if (!userId) return []
      return [{ id: userId, realname: userName || '当前账号' }]
    },
    applyDefaultUsers(force = false) {
      const defaultUser = this.getCurrentUserSelection()
      if (!defaultUser.length) return
      if (force || !this.handlerUserValue.length) {
        this.dialog.form.handler_user_id = [...defaultUser]
      }
      if (force || !this.registerUserValue.length) {
        this.dialog.form.register_user_id = [...defaultUser]
      }
    },
    handleFilterHandlerChange(data) {
      const selected = data && Array.isArray(data.value) ? data.value : []
      this.filterHandlerUser = selected
      const first = selected[0]
      this.filters.handler_user_id = first ? (first.id || first.user_id) : ''
    },
    searchCustomers(query) {
      this.customerLoading = true
      crmCustomerIndexAPI({ page: 1, limit: 20, search: query || '', is_finance_filter: 1 })
        .then(res => {
          const data = res.data || {}
          const list = Array.isArray(data.list) ? data.list : []
          this.customerOptions = list.map(item => ({
            customer_id: item.customer_id || item.id || '',
            name: item.name || item.customer_name || `客户#${item.customer_id || item.id || ''}`
          }))
        })
        .catch(() => {
          this.customerOptions = []
        })
        .finally(() => {
          this.customerLoading = false
        })
    },
    handleCustomerFilterVisible(visible) {
      if (visible && !this.customerOptions.length) {
        this.searchCustomers('')
      }
    },
    typeLabel(value) {
      if (!value || !this.typeOptions || !Array.isArray(this.typeOptions)) {
        return value || '-'
      }
      const item = this.typeOptions.find(it => it.type_id === value || it.value === value)
      return item ? (item.name || item.label || value) : value
    },
    relationLabel(row) {
      if (!row) return '无关联'
      const type = row.rel_type || 'none'
      if (type === 'customer') {
        return row.customer_name ? `客户：${row.customer_name}` : `客户#${row.customer_id || ''}`
      }
      if (type === 'contract') {
        return row.contract_name ? `合同：${row.contract_name}` : (row.contract_num ? `合同：${row.contract_num}` : `合同#${row.contract_id || ''}`)
      }
      if (type === 'business') {
        return row.business_name ? `商机：${row.business_name}` : `商机#${row.business_id || ''}`
      }
      return '无关联'
    },
    loadTypes() {
      Promise.all([
        financeTypeIndex({ direction: 'income', page: 1, limit: 500 }),
        financeTypeIndex({ direction: 'expense', page: 1, limit: 500 })
      ]).then(([incomeRes, expenseRes]) => {
        const incomeTypes = (incomeRes.data && incomeRes.data.list) || []
        const expenseTypes = (expenseRes.data && expenseRes.data.list) || []
        this.typeGroupOptions = this.buildTypeGroups(incomeTypes, expenseTypes)
        this.typeOptions = this.buildTypeTree([...incomeTypes, ...expenseTypes])
      }).catch(() => {
        this.typeOptions = []
        this.typeGroupOptions = []
      })
    },
    /**
     * 闂傚倸鍊搁崐椋庣矆娓氣偓楠炴牠姊虹拠鎻掝劉婵ǜ鍔庡Σ鎰板箻鐠囪尙锛滃┑鐐村灦閻熴儳澹曢幎鑺ュ€甸悷娆忓缁€鍐偓鍦矙鐠恒劍娈鹃梺鍛婎殘閸嬫劙寮告惔銊︾厵闁绘垶眉閼冲爼宕㈤柆宥嗏拺闁革箓宕曢妶澶婂瀭閻犻缚銆€濡插牊鎱ㄥ鈧畷鐟扳攽閸繄鐣柛搴″船鐓ら柕濞у啯娲樼缓浠嬪川婵犲嫬骞嶉梻浣侯攰鎼淬劌绠栭柛宀€鍋涢拑鐔兼煏婵炲灝鍔楁俊鎻掔墛娣囧﹪鏌ｉ幘宕囧哺闁告瑱绻濆缁樻媴閼恒儯鈧啴姊婚崟顐ばх€规洘鍔欏鐣岀磼閻庯綁濡烽妷锔界懇瀹曟洟鎮㈤崗鑲╁帗闂佸疇妗ㄧ粈渚€寮冲▎鎾寸厱婵犻潧妫欓幆鍫ユ煏韫囥儳鍒伴柣鎾村姉缁辨帞鈧綁骞愰崜褍鍨?
     */
    buildTypeGroups(incomeTypes, expenseTypes) {
      const groups = []
      const incomeParents = incomeTypes.filter(item => !item.parent_id || item.parent_id === 0)
        .sort((a, b) => (a.sort || 0) - (b.sort || 0))
      const incomeChildren = incomeTypes.filter(item => item.parent_id && item.parent_id > 0)

      incomeParents.forEach(parent => {
        const children = incomeChildren.filter(child => child.parent_id === parent.type_id)
          .sort((a, b) => (a.sort || 0) - (b.sort || 0))
        groups.push({
          label: `收入 - ${parent.name}`,
          children: [
            { ...parent, label: parent.name, type_id: parent.type_id, direction: 'income' },
            ...children.map(child => ({ ...child, label: `  ${child.name}`, type_id: child.type_id, direction: 'income' }))
          ]
        })
      })

      const expenseParents = expenseTypes.filter(item => !item.parent_id || item.parent_id === 0)
        .sort((a, b) => (a.sort || 0) - (b.sort || 0))
      const expenseChildren = expenseTypes.filter(item => item.parent_id && item.parent_id > 0)

      expenseParents.forEach(parent => {
        const children = expenseChildren.filter(child => child.parent_id === parent.type_id)
          .sort((a, b) => (a.sort || 0) - (b.sort || 0))
        groups.push({
          label: `支出 - ${parent.name}`,
          children: [
            { ...parent, label: parent.name, type_id: parent.type_id, direction: 'expense' },
            ...children.map(child => ({ ...child, label: `  ${child.name}`, type_id: child.type_id, direction: 'expense' }))
          ]
        })
      })

      return groups
    },
    /**
     * 闂傚倸鍊搁崐椋庣矆娓氣偓楠炲鍨鹃崘璇у姛缂侇噣鏌熸笟鍨妤犵偛妫濆Λ浣瑰緞閹邦厾鍘遍梺闈涱槶閸庡灚淇婇幖浣肝ㄩ柛鎰╁妿缁夋椽鏌熼柍鍝勬噹缁狅綁鏌熸导鏉戝瀭闁搞劌鈹戦悩宕囶暡闁?
     */
    loadPaymentMethods() {
      financePaymentMethodIndex({ page: 1, limit: 500 })
        .then(res => {
          this.paymentMethodOptions = (res.data && res.data.list) || []
        })
        .catch(() => {
          this.paymentMethodOptions = []
        })
    },
    /**
     * 闂傚倸鍊搁崐椋庣矆娓氣偓楠炴牠姊虹拠鎻掝劉婵ǜ鍔庡Σ鎰板箻鐠囪尙锛滃┑鐐村灦閻熴儳澹曢幎鑺ュ€甸悷娆忓缁€鍐偓鍦矙鐠恒劍娈鹃梺鍛婎殘閸嬫劙寮告惔銊︾厵闁绘垶眉閼冲爼宕㈤幖浣瑰€垫鐐茬仢閸旀碍淇婃俊銈呮噺閸婂爼鏌曟径鍡樻珕闁绘挾濞€閺屾稑鈽夊鍫濅紣闂佺硶鍓濋悷锕傚箛閺夎法顔婂┑掳鍊楃划缁樼節濮橆厼鈧敻鏌涢妸鈺佺畺闁稿瞼鍋涢拑鐔兼煏婵炲灝鍔楁俊鎻掔墛娣囧﹪鏌ｉ幘宕囧哺闁告瑱绻濆缁樻媴閼恒儯鈧啴姊婚崟顐ばх€规洘鍔欏鐣岀磼閻庯綁濡烽妷锔界懇瀹曟洟鎮㈤崗鑲╁帗闂侀潧顦崕鎶芥晬瀹ュ鍊甸柨婵嗘噹閿曘倝骞栭梺闈涢獜缁插墽娑垫ィ鍐┾拺闂傚牊鍗曢崼銉ョ柧婵炴垯鍨洪埛鎺楁⒒
     */
    buildTypeTree(allTypes) {
      const parents = allTypes.filter(item => !item.parent_id || item.parent_id === 0)
        .sort((a, b) => (a.sort || 0) - (b.sort || 0))
      const children = allTypes.filter(item => item.parent_id && item.parent_id > 0)

      const result = []
      parents.forEach(parent => {
        result.push({
          ...parent,
          label: parent.name,
          value: parent.type_id
        })
        children.filter(child => child.parent_id === parent.type_id)
          .sort((a, b) => (a.sort || 0) - (b.sort || 0))
          .forEach(child => {
            result.push({
              ...child,
              label: `  ${child.name}`,
              value: child.type_id
            })
          })
      })
      return result
    },

    loadRecords() {
      this.recordLoading = true
      this.recordError = ''
      this.customFieldError = false
      const map = {}

      if (this.contractId) {
        map.contract_id = String(this.contractId)
      } else if (this.businessId) {
        map.business_id = String(this.businessId)
      } else if (this.customerId) {
        map.customer_id = String(this.customerId)
        if (this.filters.rel_type && this.filters.rel_type !== '' && this.filters.rel_type !== null) {
          map.rel_type = this.filters.rel_type
        }
      } else {
        if (this.filters.customer_id && this.filters.customer_id !== '' && this.filters.customer_id !== null) {
          map.customer_id = String(this.filters.customer_id)
        }
        if (this.filters.rel_type && this.filters.rel_type !== '' && this.filters.rel_type !== null) {
          map.rel_type = this.filters.rel_type
        }
      }

      if (this.filters.type_ids && Array.isArray(this.filters.type_ids) && this.filters.type_ids.length > 0) {
        map.type_ids = this.filters.type_ids.map(id => String(id))
      }
      if (this.filters.direction && this.filters.direction !== '' && this.filters.direction !== null) {
        map.direction = this.filters.direction
      }
      if (this.filters.payment_method_id && this.filters.payment_method_id !== '' && this.filters.payment_method_id !== null) {
        map.payment_method_id = String(this.filters.payment_method_id)
      }
      if (this.filters.dateRange && this.filters.dateRange.length === 2) {
        map.start_date = this.filters.dateRange[0]
        map.end_date = this.filters.dateRange[1]
      }
      if (this.filters.orderBy) {
        map.order_by = this.filters.orderBy
      }

      if (this.filters.handler_user_id) {
        map.handler_user_id = String(this.filters.handler_user_id)
      }

      const params = {
        page: this.page,
        limit: this.limit,
        map
      }

      financeRecordIndex(params).then(res => {
        const data = res.data || {}
        this.records = data.list || []
        this.total = data.dataCount || 0
        const statistics = data.statistics || {}
        this.statistics = {
          totalCount: statistics.totalCount || statistics.total_count || this.total || 0,
          totalIncome: statistics.totalIncome || statistics.total_income || '0.00',
          totalExpense: statistics.totalExpense || statistics.total_expense || '0.00',
          profit: statistics.profit || statistics.total_profit || '0.00'
        }
      }).catch(err => {
        if (err && err.code === 2001) {
          this.customFieldError = true
        } else {
          this.recordError = err && (err.error || err.msg || err.message) ? (err.error || err.msg || err.message) : '加载失败'
        }
      }).finally(() => {
        this.recordLoading = false
      })
    },
    handleSearch() {
      this.page = 1
      this.loadRecords()
    },
    handlePageChange(page) {
      this.page = page
      this.loadRecords()
    },
    handlePageSizeChange(size) {
      this.limit = size
      this.page = 1
      this.loadRecords()
    },
    handleTableSortChange({ prop }) {
      if (prop === 'create_time') {
        this.filters.orderBy = 'create_time'
      } else {
        this.filters.orderBy = 'occur_date'
      }
      this.page = 1
      this.loadRecords()
    },
    checkApiHealth() {
      this.apiHealth.status = 'pending'
      this.apiHealth.message = ''
      this.apiHealth.snippet = ''
      this.apiHealth.finalUrl = `${(this.apiHealth.proxyTarget || 'http://localhost').replace(/\/$/, '')}/index.php/${this.apiHealth.route}`
      financeTypeIndex({ page: 1, limit: 1 })
        .then(res => {
          this.apiHealth.status = 'success'
          this.apiHealth.httpStatus = res.__status || 200
          this.apiHealth.contentType = res.__contentType || ''
          this.apiHealth.message = 'finance/type/index 可访问'
        })
        .catch(err => {
          this.apiHealth.status = 'fail'
          this.apiHealth.httpStatus = (err && err.__status) || (err && err.status) || null
          this.apiHealth.contentType = (err && err.__contentType) || ''
          const message = err && err.message ? err.message : (typeof err === 'string' ? err : '请求失败')
          this.apiHealth.message = message.slice(0, 50)
          this.apiHealth.snippet = message.length > 50 ? message.slice(0, 50) : message
        })
    },
    openDialog(row) {
      this.dialog.isView = false
      if (row) {
        this.dialog.form = {
          customer_id: row.customer_id,
          contract_id: row.contract_id,
          business_id: row.business_id,
          rel_type: row.rel_type || 'none',
          type_id: row.type_id,
          direction: row.direction,
          amount: row.amount,
          occur_date: row.occur_date,
          handler_user_id: row.handler_user_id ? [{ id: row.handler_user_id, realname: row.handler_user_name || '' }] : [],
          register_user_id: row.register_user_id ? [{ id: row.register_user_id, realname: row.register_user_name || '' }] : [],
          payment_method_id: row.payment_method_id || '',
          voucher: row.voucher || '',
          remark: row.remark || ''
        }
        this.dialog.editingId = row.record_id
        // 闂傚倸鍊峰ù鍥х暦閻㈢绐楅柟鎵閸嬶繝寮堕崼姘珔缂佽翰鍊曡灃闁挎繂鎳庨弳鐐烘煕婵犲洦娑ч棁澶愭煟濡灝鐨虹紒顔肩墦瀹曠喖鏌″畝瀣М闁诡喒鏅犲畷锝嗗緞瀹€鈧崢顒勬⒒娴ｈ櫣甯涢悽顖滅磼閻樿櫕灏梺褰掓？缁€浣虹矆閸愵喗鐓冮柛婵嗗閺嗙偤鎮介柣鎰惈缁犳煡鏌涢弴鐐垫殾闂備礁鎲″ú妯肩矉閹烘垶顭堥崺鏍偂閻斿吋鐓熼柟浼村川婵犲倸鐏￠梻鍌欐祰濡椼劎绮堟担铏圭煋闁荤喐澹嗛弳锕€鈹戦崒婊冩健閺屽秹鍩℃担鍛婃闂侀潧楠忕槐鏇犵不妤ｅ啯鍊垫繛鎴炵懆閸嬫劙鎮＄紓鍌氬€烽梽宥夊礉韫囨稑纾婚柟浼村礆閹烘鈷戠紒瀣濠€鎵磼鐎ｎ偄鐏︾紒杞扮矙瀹曘劍绻濋崒娆愮潖闂傚倷鐒﹂惇褰掑垂婵犳艾鏋侀柟闂寸劍閸嬪倸霉閿濆懏璐＄紒鐘荤畺閹鎮介棃娑欐珖闂傚倷绶氬缁樹繆閸モ晛鍨?
        this.voucherFileList = this.parseVoucherFilesForUpload(row.voucher)
      } else {
        this.resetDialogForm()
        this.dialog.editingId = null
        this.voucherFileList = []
      }
      this.$nextTick(() => {
        this.dialog.visible = true
      })
    },
    /**
     * 闂傚倸鍊峰ù鍥х暦閻㈢绐楅柟鎵閸嬶繝寮堕崼姘珔缂佽翰鍊曡灃闁挎繂鎳庨弳鐐烘煕婵犲洦娑ч棁澶愭煟濡灝鐨虹紒顔肩墦瀹曠喖鏌″畝瀣М闁诡喒鏅犲畷锝嗗緞瀹€鈧崢顒勬⒒娴ｈ櫣甯涢悽顖滅磼閻樿櫕灏梺褰掓？缁€浣虹矆閸愵喗鐓冮柛婵嗗閺嗙偤鎮介柣鎰惈缁犳煡鏌涢弴銏犵闁革箓骞夐幘顔肩闂佸摜濮靛ú婊呮閹捐纾兼繛鍡樺灥婵′粙鏌ｆ惔顖滄偧婵″弶鍔栫€佃偐鈧稒锚娴狀參姊洪崫鍕窛闁稿鍋涢埢搴ㄥ箣閻樼數鍘┑鐘灱閸╂牠宕濋弽顓炵９缂備焦眉缁诲棝姊洪悡搴㈠姇閺嶎偅宕叉繛鎴炴皑閺佹悂宕崘銊㈡斀闁绘劖褰冩繛鎻掝嚟鐟欏嫭绀€缂傚秴锕濠氭晲婢跺鈧兘鎮楅棃娑欏暈闁告帗鐩鐑樻姜閹殿喕鑳剁划鏃堟偡閹殿喗娈炬繝闈涘€婚崸妤佺厓闁宠桨绀侀弳鐐烘煏韫囥儳纾跨紒鐘荤畺閹鈽夊▎鎺戜汗闁绘挻绻濋棃娑欘殔閻楀繘鎮鹃棃娑掓斀闁斥晛鍟亸锔锯偓瑙勬礃閻撳海浠涚紒鍌涘笧閿濆洨鐭嗛柛宀€鍋為悡鏇㈢叓閸ャ劍灏垫慨锝囧仱閺岋紕鈧綁鎮у鍛潟闁规儳顕悷褰掓⒑瑜版帗鐓熼幖娣妽濞懷囨煙
     */

    parseVoucherFiles(voucherStr) {
      if (!voucherStr) return []
      try {
        const files = JSON.parse(voucherStr)
        if (Array.isArray(files)) {
          return files.map(file => ({
            name: file.name || file.file_name || '凭证',
            url: file.url || file.file_path || file.path,
            file_id: file.file_id || file.id
          }))
        }
      } catch (e) {
        if (typeof voucherStr === 'string' && voucherStr.trim()) {
          return [{
            name: '凭证',
            url: voucherStr
          }]
        }
      }
      return []
    },
    parseVoucherFilesForUpload(voucherStr) {
      if (!voucherStr) return []
      try {
        const files = JSON.parse(voucherStr)
        if (Array.isArray(files)) {
          return files.map(file => ({
            name: file.name || file.file_name || '凭证',
            url: file.url || file.file_path || file.path,
            file_id: file.file_id || file.id,
            response: {
              data: file
            }
          }))
        }
      } catch (e) {
        if (typeof voucherStr === 'string' && voucherStr.trim()) {
          return [{
            name: '凭证',
            url: voucherStr
          }]
        }
      }
      return []
    },
    voucherUploadSuccess(response, file, fileList) {
      this.voucherFileList = fileList
      const files = fileList.map(item => {
        if (item.response && item.response.data) {
          return item.response.data
        } else if (item.file_id) {
          return { file_id: item.file_id, url: item.url, name: item.name }
        }
        return { url: item.url, name: item.name }
      })
      this.dialog.form.voucher = JSON.stringify(files)
    },
    voucherUploadRemove(file, fileList) {
      this.voucherFileList = fileList
      const files = fileList.map(item => {
        if (item.response && item.response.data) {
          return item.response.data
        } else if (item.file_id) {
          return { file_id: item.file_id, url: item.url, name: item.name }
        }
        return { url: item.url, name: item.name }
      })
      this.dialog.form.voucher = files.length > 0 ? JSON.stringify(files) : ''
    },
    /**
     * 婵犵數濮锋径宀€鐦堥悗鐢稿醇閺囩偛鐎梻鍌氬€烽懗鑸垫償閹惧瓨鏉搁梻浣虹《閸撴繈鏁嬮梺缁樼墪閵堟悂鐛崘顓滀汗闁圭儤鍨归崐鐐烘⒑閹稿孩鐓ュ褌绮欓幃鍝勎熼懖鈺冿紳婵炶揪绲肩划娆撳传濞差亝鐓熼柣鏇炲€搁崶鈺佸灊婵炲棙鍔曠欢鐐烘倵閿濆洤濮繝鐢靛仩閹活亞绱炲┑鐐叉嫅缂嶄礁鐣烽幇鐗堝€烽柣銏㈡暩閿涙繈姊虹粙鎸庢拱闁荤啙鍕闁绘垶顭囧鍥ㄥ床婵炴垯鍨归獮銏ゆ煙娴煎瓨鍊剁紓鍌氬€风粈渚€鏌熸导瀛樺亗闁绘柨鍚嬮悡蹇撯攽閻愯尙浠㈤柛鏃€鑹鹃湁闁稿繐鍚嬬紞鎴犵磼閻庯絽顫忓ú顏勪紶闁靛／鍜冪床
     */
    handleVoucherPreview(file) {
      this.previewVoucher(file)
    },
    resetDialogForm() {
      this.dialog.form = {
        customer_id: '',
        contract_id: '',
        business_id: '',
        rel_type: 'none',
        type_id: '',
        direction: 'expense',
        amount: 0,
        occur_date: '',
        handler_user_id: [],
        register_user_id: [],
        payment_method_id: '',
        voucher: '',
        remark: ''
      }
      if (this.customerId) {
        this.dialog.form.customer_id = this.customerId
        this.dialog.form.rel_type = 'customer'
      } else if (this.businessId) {
        this.dialog.form.business_id = this.businessId
        this.dialog.form.rel_type = 'business'
        if (this.customerDetail && (this.customerDetail.business_name || this.customerDetail.name)) {
          this.relationNames.business = {
            business_id: this.businessId,
            name: this.customerDetail.business_name || this.customerDetail.name
          }
        }
      } else if (this.contractId) {
        this.dialog.form.contract_id = this.contractId
        this.dialog.form.rel_type = 'contract'
        if (this.customerDetail && (this.customerDetail.name || this.customerDetail.contract_name || this.customerDetail.num)) {
          this.relationNames.contract = {
            contract_id: this.contractId,
            name: this.customerDetail.name || this.customerDetail.contract_name || this.customerDetail.num
          }
        }
      }

      // 初始化默认处理人/记录人
      this.applyDefaultUsers(true)
    },
    validateRelation() {
      const relType = this.dialog.form.rel_type || 'none'
      if (!['none', 'customer', 'contract', 'business'].includes(relType)) {
        this.$message.warning('Invalid selection')
        return false
      }
      if (relType === 'customer' && !this.dialog.form.customer_id) {
        this.$message.warning('Invalid selection')
        return false
      }
      if (relType === 'contract' && !this.dialog.form.contract_id) {
        this.$message.warning('Invalid selection')
        return false
      }
      if (relType === 'business' && !this.dialog.form.business_id) {
        this.$message.warning('Invalid selection')
        return false
      }
      return true
    },
    saveRecord() {
      this.$refs.recordForm.validate((valid) => {
        if (!valid) {
          return false
        }
        if (!this.validateRelation()) {
          return false
        }
        const payload = { ...this.dialog.form }
        if (this.customerId) {
          payload.customer_id = this.customerId
          payload.rel_type = 'customer'
        }
        if (payload.payment_method_id === '' || payload.payment_method_id === null || payload.payment_method_id === undefined) {
          payload.payment_method_id = 0
        } else {
          payload.payment_method_id = parseInt(payload.payment_method_id) || 0
        }
        const handlerValue = Array.isArray(payload.handler_user_id) ? payload.handler_user_id[0] : null
        payload.handler_user_id = handlerValue ? (handlerValue.id || handlerValue.user_id) : 0
        const registerValue = Array.isArray(payload.register_user_id) ? payload.register_user_id[0] : null
        payload.register_user_id = registerValue ? (registerValue.id || registerValue.user_id) : 0
        const request = this.dialog.editingId ? financeRecordUpdate : financeRecordSave
        if (this.dialog.editingId) {
          payload.id = this.dialog.editingId
        }
        request(payload)
          .then(() => {
            this.$message.success('保存成功')
            this.dialog.visible = false
            this.loadRecords()
          })
          .catch(err => {
            this.$message.error(err.error || err.msg || err.message || '保存失败')
          })
      })
    },
    viewRecord(row) {
      if (!row) return
      this.openDialog(row)
      this.dialog.isView = true
    },
    editRecord(row) {
      if (!this.recordAuth.update) return
      this.openDialog(row)
    },
    confirmDelete(row) {
      if (!this.recordAuth.delete) return
      this.$confirm('确认删除该收支记录吗？', '提示', {
        type: 'warning',
        confirmButtonText: '确定',
        cancelButtonText: '取消'
      })
        .then(() => {
          financeRecordDelete({ id: row.record_id })
            .then(() => {
              this.$message.success('删除成功')
              this.loadRecords()
            })
            .catch(err => {
              this.$message.error(err.error || err.msg || err.message || '删除失败')
            })
        })
        .catch(() => {})
    },
    resetFilters() {
      this.filters.customer_id = this.customerId || ''
      this.filters.rel_type = ''
      this.filters.type_ids = []
      this.filters.direction = ''
      this.filters.payment_method_id = ''
      this.filters.dateRange = this.getDefaultFilterDateRange()
      this.filters.orderBy = 'occur_date'
      this.filters.handler_user_id = ''
      this.filterHandlerUser = []
      this.page = 1
      this.loadRecords()
    },
    /**
     * 婵犵數濮烽弫鍛婃叏娴兼潙鍨傞柣鎾崇岸閺嬫牗绻涢幋鐐殿暡鐎光偓閹间礁钃熼柣鏂跨殱閺€浠嬫煟濡绲诲┑锛勫仧閸涱喚褰х紓浣虹帛閻╊垶鐛€ｎ亖鏋庨煫鍥ㄦ磻閹綁姊虹拠鎻掑毐缂傚秳绶氶獮鍐灳閺傘儲鐎婚梺瑙勫劤缁嬪灝绠炲┑鐘绘涧濡も偓閻ｉ鎲撮崟顒€顎撻梺鍛婂姧缂傛艾效濡や胶绡€闁靛骏绲介悡鎰版煕閺冣偓濞叉粎鍒掗崼婵堟殕闁告洟鎮″☉姘畭閸庡崬煤閿曞倸姹查柍鍝勫暟绾惧吋绻濋姀锝呯厫闁告梹鐗犻幃鈥斥槈閵忥紕鍘遍梺闈涱槹閸ㄧ敻骞婅箛娑樼疅濠靛倸鎲￠埛鎴︽煕濠靛棗顏悗姘嵆閺屻劑寮撮悩鍝勫缁€鍐偓鍦矙鐠恒劍娈鹃梺鍛婎殘閸嬫劙寮告惔銊︾厵闁绘垶眉鐠佹煡骞忛崫鍕ㄦ斀闁绘﹢宕规總绋挎槬闁哄秴鐣?
     */
    handleFilterDirectionChange() {
      this.filters.type_ids = []
      this.page = 1
      this.loadRecords()
    },
    /**
     * 婵犵數濮烽弫鍛婃叏娴兼潙鍨傞柣鎾崇岸閺嬫牗绻涢幋鐐殿暡鐎光偓閹间礁钃熼柣鏂跨殱閺€浠嬫煟濡搫绾ч柟鍏煎姉缁辨帡鏌涘畝鈧崑鐐哄磹閻㈠憡鐓曢柨鏃囧Г閸欏繒鈧娲栧ú銈夊垂濠靛鐓欓柟顖嗗啫娴锋竟鏇熺附閸涘﹦鍘介梺缁樏鍓佺矚閸ф鍊堕煫鍥ㄦ⒒閹冲洭鏌熸繝濠傜墛鐎电姴顭跨捄铏圭伇闁告ê鈹戦悩闈涱槺缁♀偓閻?
     */
    handleRelTypeChange(newType) {
      if (newType === 'none') {
        this.dialog.form.customer_id = ''
        this.dialog.form.contract_id = ''
        this.dialog.form.business_id = ''
        return
      }
      if (newType === 'customer') {
        this.dialog.form.contract_id = ''
        this.dialog.form.business_id = ''
      } else if (newType === 'contract') {
        this.dialog.form.business_id = ''
      } else if (newType === 'business') {
        this.dialog.form.contract_id = ''
      }
    },
    /**
     * 婵犵數濮烽弫鍛婃叏娴兼潙鍨傞柣鎾崇岸閺嬫牗绻涢幋鐐殿暡鐎光偓閹间礁钃熼柣鏂跨殱閺€浠嬫煟濡绲荤紓鍌欒兌婵绮旈悷鎵殾闁荤喐鍣撮搹纭咁潐濞叉牠骞栭梺瀹犳閹邦剟鏌ㄥ☉妯侯仼濠殿喖鍢查埞鎴︽偐闁搞劎鍘ч埢鏂库槈閵忥紕鍘繝鐢靛仧閸嬫挸鈻嶉崨瀛樼厽濠德板€撳鎺旀崲閸℃稒鈷?
     */
    handleCustomerChange(data) {
      let customerId = ''
      if (data && data.value && Array.isArray(data.value) && data.value.length > 0) {
        this.relationNames.customer = data.value[0]
        customerId = data.value[0].customer_id || data.value[0].id || ''
      } else if (data && data.data && Array.isArray(data.data) && data.data.length > 0) {
        customerId = data.data[0].customer_id || data.data[0].id || ''
      } else if (data && data.value !== undefined && !Array.isArray(data.value)) {
        customerId = data.value
      } else if (Array.isArray(data) && data.length > 0) {
        customerId = data[0].customer_id || data[0].id || ''
      } else if (typeof data === 'number' || typeof data === 'string') {
        customerId = data
      }
      this.dialog.form.customer_id = customerId
      if (customerId) {
        this.dialog.form.rel_type = 'customer'
        this.dialog.form.contract_id = ''
        this.dialog.form.business_id = ''
      } else if (this.dialog.form.rel_type === 'customer') {
        this.dialog.form.rel_type = 'none'
      }
    },
    /**
     * 婵犵數濮烽弫鍛婃叏娴兼潙鍨傞柣鎾崇岸閺嬫牗绻涢幋鐐殿暡鐎光偓閹间礁钃熼柣鏂跨殱閺€浠嬫煟濡搫绾ч柟鍏煎姍閹嘲鈻庡▎鎴犳殼闂佸搫鏈粙鎺旀崲濠靛顥堟繛鎴炵懄閹瑩姊绘担鎼炲劚濡矂骞忛崫鍕ㄦ斀闁绘﹢宕归悡搴樻灃婵炴垯鍨洪悡蹇撯攽閻愰潧浜炬繛鍛嚇閺岋絾淇婇妶鍕樂缂佽妫濆?
     */
    handleContractChange(data) {
      let contractId = ''
      if (data && data.value && Array.isArray(data.value) && data.value.length > 0) {
        this.relationNames.contract = data.value[0]
        contractId = data.value[0].contract_id || data.value[0].id || ''
      } else if (data && data.data && Array.isArray(data.data) && data.data.length > 0) {
        contractId = data.data[0].contract_id || data.data[0].id || ''
      } else if (data && data.value !== undefined && !Array.isArray(data.value)) {
        contractId = data.value
      } else if (Array.isArray(data) && data.length > 0) {
        contractId = data[0].contract_id || data[0].id || ''
      } else if (typeof data === 'number' || typeof data === 'string') {
        contractId = data
      }
      this.dialog.form.contract_id = contractId
      if (contractId) {
        this.dialog.form.rel_type = 'contract'
        this.dialog.form.business_id = ''
      } else if (this.dialog.form.rel_type === 'contract') {
        this.dialog.form.rel_type = 'none'
      }
    },
    /**
     * 婵犵數濮烽弫鍛婃叏娴兼潙鍨傞柣鎾崇岸閺嬫牗绻涢幋鐐殿暡鐎光偓閹间礁钃熼柣鏂跨殱閺€浠嬫煟濡搫绾ч柟鍏煎姍閺岋綁濡舵径瀣ф嫼缂備線寮撮悙娴嬫嫟闂佽棄鍟虫禍顒傛閹烘惟闁挎洟宕悜妯诲弿濠电姴鍊归幆鍫ユ煏韫囥儳绋荤痪顓㈢畺濮婃椽妫冨ù銉ョ墦瀵彃鈽夐姀鈥冲壒濠德板€撳鎺旀崲閸℃稒鈷?
     */
    handleBusinessChange(data) {
      let businessId = ''
      if (data && data.value && Array.isArray(data.value) && data.value.length > 0) {
        this.relationNames.business = data.value[0]
        businessId = data.value[0].business_id || data.value[0].id || ''
      } else if (data && data.data && Array.isArray(data.data) && data.data.length > 0) {
        businessId = data.data[0].business_id || data.data[0].id || ''
      } else if (data && data.value !== undefined && !Array.isArray(data.value)) {
        businessId = data.value
      } else if (Array.isArray(data) && data.length > 0) {
        businessId = data[0].business_id || data[0].id || ''
      } else if (typeof data === 'number' || typeof data === 'string') {
        businessId = data
      }
      this.dialog.form.business_id = businessId
      if (businessId) {
        this.dialog.form.rel_type = 'business'
        this.dialog.form.contract_id = ''
        this.notifyBusinessContractAutoClassify(businessId)
      } else if (this.dialog.form.rel_type === 'business') {
        this.dialog.form.rel_type = 'none'
      }
    },
    notifyBusinessContractAutoClassify(businessId) {
      if (!businessId) return
      crmBusinessNumAPI({ business_id: businessId }).then(res => {
        const data = res.data || {}
        if (Number(data.contractCount || 0) > 0) {
          this.$message.warning('该商机已有关联合同，保存后将自动归类到最新合同')
        }
      }).catch(() => {})
    },
    handleUserChange(data) {
      this.dialog.form.handler_user_id = data.value || []
    },
    handleRegisterUserChange(data) {
      this.dialog.form.register_user_id = data.value || []
    },
    /**
     * 婵犵數濮烽弫鍛婃叏娴兼潙鍨傞柣鎾崇岸閺嬫牗绻涢幋鐐殿暡鐎光偓閹间礁钃熼柣鏂跨殱閺€浠嬫煟濡搫绾ч柟鍏煎姈娣囧﹥鎱ㄥΔ鍛摕闁挎繂顦伴崑鍕煕濠靛棗顏柛娑氬閻熸壋鏀介柣妯虹－瀹ュ鍋傞柡鍥╁枔缁犻箖鏌涢埄鍏狀亪宕㈣濮婅櫣鎷犻弻銉︽倐閹ê顫濋懜闈涗簵闂佽法鍠撴慨鎾几娴ｅ搫绠嶉崕閬嶅箠閹版澘姹查柛鈩冪⊕閻撴洟鏌熼幍铏珔濠德ゆ闇夐柣鎾虫捣閹界娀鏌ｉ幘瀛樼闁哄矉绻濆畷鎺戔攽閸パ呯シ闂傚倸鍊烽悞锕傛儑瑜版帒绀夐幖娣妼缁愭鏌″畵顔兼处閳锋垿鏌涘┑鍡楊仼闂佽绻堥崕鐢稿蓟閿濆棙鍎熼柕鍫濇噹閹偟绱?     */
    handleDirectionChange() {
      this.dialog.form.type_id = ''
    }
  }
}
</script>
<style lang="scss" scoped>
/* ========================================
   财务模块 - 柔和优雅设计
   ======================================= */

/* 整体布局 */
.finance-record-panel {
  background: #f0f2f5;
  min-height: calc(100vh - 80px);
  padding: 12px 12px 12px 6px;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", "微软雅黑", Arial, sans-serif;
}

/* 筛选器区域 */
.record-filter {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 0;
  padding: 6px 0;
  background: #ffffff;
  border-radius: 8px;
  box-shadow: none;

  .el-input,
  .el-select {
    font-size: 13px;
  }
}

/* 筛选器区域 */
.filter-card ::v-deep .el-card__body {
  padding: 8px 10px !important;
}

.record-filter {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #ffffff;
  border-radius: 8px;

  .el-input,
  .el-select {
    font-size: 13px;
  }
}

/* 统计卡片 */
.statistics-card {
  margin-bottom: 10px;
  border: none;
  border-radius: 8px;
  background: #ffffff;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);

  ::v-deep .el-card__body {
    padding: 10px 16px;
  }
}

.finance-statistics {
  display: flex;
  align-items: center;
  gap: 20px;

  .stat-item {
    padding: 8px 16px;
    background: #f8f9fb;
    border-radius: 6px;
    border: 1px solid #edf0f2;
    min-width: 140px;
    transition: all 0.2s ease;

    &:hover {
      background: #ffffff;
      border-color: #409EFF;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }
  }

  .stat-label {
    font-size: 13px;
    color: #909399;
    margin-bottom: 2px;
  }

  .stat-value {
    font-weight: bold;
    font-size: 18px;
    color: #303133;
  }

  .income-value { color: #67C23A; }
  .expense-value { color: #F56C6C; }
  .profit-positive { color: #67C23A; }
  .profit-negative { color: #F56C6C; }
}

/* 表格区域 */
.table-card {
  border: none;
  border-radius: 8px;
  background: #ffffff;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);

  ::v-deep .el-table {
    font-size: 13px;
    color: #606266;

    th {
      background: #f5f7fa !important;
      color: #303133;
      font-weight: 600;
      height: 44px;
      padding: 0 !important;
    }

    td {
      padding: 6px 0 !important;
      height: 44px;
    }

    .cell {
      line-height: 22px;
    }
  }
}

/* 金额字体增强 */
.income-amount, .expense-amount {
  font-family: "Monaco", "Menlo", "Ubuntu Mono", "Consolas", monospace;
  font-weight: 600;
}

/* 操作按钮间距 */
.table-card ::v-deep .el-button--text {
  padding: 0 8px;
  margin: 0;
  font-size: 14px;
}

/* 分页器 */
.pager-bar {
  padding: 12px 16px;
  background: #ffffff;
  border-top: 1px solid #f0f2f5;
  display: flex;
  justify-content: flex-end;
}

/* 收入类型标签 */
.income-type {
  color: #5bb956;
  font-weight: 500;
  padding: 4px 8px;
  background: #f0f9ff;
  border-radius: 4px;
  font-size: 14px;
}

.expense-type {
  color: #f7899a;
  font-weight: 500;
  padding: 4px 8px;
  background: #fef2f2;
  border-radius: 4px;
  font-size: 14px;
}

/* 收入金额 */
.income-amount {
  color: #5bb956;
  font-weight: 600;
  font-size: 14px;
}

.expense-amount {
  color: #f7899a;
  font-weight: 600;
  font-size: 14px;
}

/* 分页器 */
.pager-bar {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 0;
  padding: 10px 12px;
  background: #ffffff;
  border-radius: 0;
  box-shadow: none;

  ::v-deep .el-pagination {
    .el-pager li {
      border-radius: 4px;
      font-weight: 500;
      transition: all 0.2s ease;

      &:hover {
        color: #409EFF;
        background-color: #f5f7fa;
      }

      &.active {
        background: linear-gradient(135deg, #5bb956 0%, #69b48c 100%);
        color: #ffffff;
        font-weight: 600;
      }
    }

    .btn-prev,
    .btn-next {
      border-radius: 4px;
      transition: all 0.2s ease;

      &:hover {
        color: #409EFF;
        background-color: #f5f7fa;
      }
    }
  }
}

@media (max-width: 1366px) {
  .finance-record-panel {
    padding: 10px 10px 10px 4px;
  }

  .record-filter {
    gap: 6px;
  }
}

/* 提示信息 */
.api-health {
  padding: 16px 20px;
  margin-bottom: 16px;
  border-radius: 8px;
  background: #ffffff;
  border: 1px solid #e8eaed;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);

  .api-health__row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
    font-size: 13px;
    color: #606266;

    code {
      color: #5bb956;
      background: #f0f9ff;
      padding: 2px 8px;
      border-radius: 4px;
    }
  }

  .api-health__status {
    display: flex;
    align-items: center;
    gap: 8px;
  }
}

.type-empty-hint {
  font-size: 13px;
  color: #909399;
  margin-top: 8px;
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 8px 12px;
  background: #f5f7fa;
  border-radius: 6px;
}

/* 对话框样式 */
.dialog-footer {
  text-align: right;
}

/* 凭证展示区域 */
.voucher-display {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  max-height: 60px;
  overflow: hidden;
}

.voucher-item {
  width: 54px;
  height: 54px;
  border: 1px solid #e8eaed;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  overflow: hidden;
  background: #f5f7fa;
  flex-shrink: 0;
  transition: all 0.25s ease;

  &:hover {
    border-color: #409EFF;
    transform: translateY(-2px);
    box-shadow: 0 2px 8px rgba(64, 158, 255, 0.12);
  }
}

.voucher-thumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.voucher-icon {
  font-size: 24px;
  color: #c0c4cc;
}

.voucher-empty {
  color: #c0c4cc;
  font-size: 13px;
}

/* UI/UX Pro Max tuning: enterprise dense layout with balanced spacing */
.finance-record-panel {
  --ui-gap-sm: 8px;
  --ui-control-h: 32px;
}

.finance-record-panel {
  padding: 10px 12px;
}

.filter-card ::v-deep .el-card__body {
  padding: 8px 10px !important;
}

.record-filter {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--ui-gap-sm);
  padding: 4px 0;
}

.record-filter.is-compact {
  flex-wrap: nowrap;
  gap: 6px;
}

.record-filter.is-compact .filter-date {
  width: 220px !important;
}

.record-filter.is-compact .filter-user ::v-deep .user-container {
  width: 120px !important;
}

.record-filter ::v-deep .el-input__inner,
.record-filter ::v-deep .el-select .el-input__inner {
  height: var(--ui-control-h);
  line-height: var(--ui-control-h);
}

.record-filter .filter-actions {
  margin-left: auto;
  display: inline-flex;
  gap: 8px;
}

.record-filter .filter-user ::v-deep .user-container {
  width: 140px;
  min-height: 28px;
  margin: 0;
}

@media (max-width: 1200px) {
  .record-filter.is-compact {
    flex-wrap: wrap;
  }
}

.table-card ::v-deep .el-table th {
  padding: 8px 0 !important;
}

.table-card ::v-deep .el-table td {
  padding: 6px 0 !important;
  height: 42px;
}

.table-card ::v-deep .el-table .cell {
  line-height: 22px;
}

.pager-bar {
  padding: 8px 10px;
  margin-bottom: 0;
}

@media (max-width: 768px) {
  .finance-record-panel {
    padding: 8px 6px;
  }

  .record-filter {
    gap: 6px;
  }

  .record-filter .filter-item {
    width: calc(50% - 3px) !important;
  }

  .record-filter .filter-date {
    width: 100% !important;
  }

  .record-filter .filter-actions {
    width: 100%;
    margin-left: 0;
  }
}

/* dialog density aligned with ledger dialog */
.finance-record-dialog ::v-deep .el-dialog {
  border-radius: 12px;
  overflow: hidden;
}

.finance-record-dialog ::v-deep .el-dialog__header {
  padding: 16px 22px;
  border-bottom: 1px solid #e8eaed;
  background: #f8fafc;
}

.finance-record-dialog ::v-deep .el-dialog__body {
  padding: 18px 22px 12px;
  max-height: 75vh;
  overflow-y: auto;
}

.finance-record-dialog ::v-deep .el-dialog__footer {
  padding: 12px 22px;
  border-top: 1px solid #f0f2f5;
  background: #fff;
}

.finance-form .form-section {
  border: 1px solid #edf1f5;
  border-radius: 8px;
  padding: 12px 14px;
  margin-bottom: 10px;
  background: #fcfdff;
}

.finance-form .section-title {
  margin-bottom: 10px;
  font-size: 14px;
  font-weight: 600;
  color: #334155;
}
</style>

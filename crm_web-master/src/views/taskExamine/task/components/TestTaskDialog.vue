<template>
  <el-dialog
    :visible.sync="dialogVisible"
    :width="mgmtWidth"
    title="测试任务管理"
    append-to-body
    custom-class="tt-mgmt-dialog"
    @open="handleOpen">

    <!-- 顶部标题区 -->
    <div class="tt-header">
      <div class="tt-header-info">
        <span class="tt-header-label">原任务：</span>
        <span class="tt-header-name">{{ originTaskName || ('#' + originTaskId) }}</span>
      </div>
      <el-button size="small" type="primary" icon="el-icon-plus" @click="openInitiate">发起测试</el-button>
    </div>

    <!-- 状态统计 -->
    <div class="tt-stats">
      <div class="tt-stat">
        <div class="tt-stat-num">{{ stats.total }}</div>
        <div class="tt-stat-label">总数</div>
      </div>
      <div class="tt-stat">
        <div class="tt-stat-num tt-stat-warn">{{ stats.pending_feedback }}</div>
        <div class="tt-stat-label">待反馈</div>
      </div>
      <div class="tt-stat">
        <div class="tt-stat-num tt-stat-success">{{ stats.feedbacked }}</div>
        <div class="tt-stat-label">已反馈</div>
      </div>
      <div class="tt-stat">
        <div class="tt-stat-num tt-stat-danger">{{ stats.overdue }}</div>
        <div class="tt-stat-label">已逾期</div>
      </div>
    </div>

    <!-- 测试任务列表 -->
    <el-table
      v-loading="tableLoading"
      :data="list"
      :row-class-name="tableRowClassName"
      size="small"
      style="width:100%"
      element-loading-text="加载中..."
      @selection-change="handleSelectionChange">
      <el-table-column type="selection" width="40" />
      <el-table-column label="测试任务" min-width="130" show-overflow-tooltip>
        <template slot-scope="scope">
          <el-tag v-if="scope.row.is_urgent" type="danger" size="mini" style="margin-right:4px">加急</el-tag>
          {{ scope.row.task_name || ('#' + scope.row.task_id) }}
        </template>
      </el-table-column>
      <el-table-column label="测试人员" width="90" show-overflow-tooltip>
        <template slot-scope="scope">{{ scope.row.tester_name || ('#' + scope.row.tester_user_id) }}</template>
      </el-table-column>
      <el-table-column label="测试内容" min-width="120" show-overflow-tooltip>
        <template slot-scope="scope">{{ stripHtml(scope.row.test_scope) || '-' }}</template>
      </el-table-column>
      <el-table-column label="状态" width="80">
        <template slot-scope="scope">
          <el-tag :type="displayTagType(scope.row.display_status)" size="mini">{{ scope.row.display_status }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="截止时间" width="160">
        <template slot-scope="scope">
          <span :class="{ 'tt-overdue-text': scope.row.display_status === '已逾期' }" style="white-space:nowrap">{{ formatDateTime(scope.row.deadline) }}</span>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="190" fixed="right">
        <template slot-scope="scope">
          <el-button size="mini" type="text" @click="openDetail(scope.row)">详情</el-button>
          <el-button v-if="canSubmit(scope.row)" size="mini" type="text" @click="openSubmit(scope.row)">反馈</el-button>
          <el-button size="mini" type="text" @click="openHistory(scope.row)">历史</el-button>
          <el-button v-if="canReview(scope.row)" size="mini" type="text" @click="openReview(scope.row)">评定</el-button>
          <el-button size="mini" type="text" class="tt-delete-btn" @click="deleteTestTask(scope.row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 批量删除 -->
    <div v-if="selectedRows.length" class="tt-batch-bar">
      <span>已选 {{ selectedRows.length }} 项</span>
      <el-button size="mini" type="danger" plain @click="batchDelete">批量删除</el-button>
    </div>

    <!-- 空状态 -->
    <div v-if="!tableLoading && !list.length" class="tt-empty-state">
      <i class="el-icon-document" style="font-size:40px;color:#dcdfe6" />
      <p style="color:#909399;margin:12px 0">暂无测试任务</p>
      <el-button size="small" type="primary" icon="el-icon-plus" @click="openInitiate">发起测试</el-button>
    </div>

    <!-- ===================== 发起测试子弹窗 ===================== -->
    <el-dialog
      :visible.sync="initiateVisible"
      :width="initiateDialogWidth"
      :close-on-click-modal="false"
      title="发起测试"
      append-to-body
      custom-class="tt-initiate-dialog">
      <el-form ref="initiateFormRef" :model="initForm" :rules="initRules" label-width="100px" size="small">
        <el-form-item label="测试人员" prop="testers">
          <el-select
            v-model="initForm.testers"
            :remote-method="searchUsers"
            :loading="userLoading"
            multiple
            filterable
            remote
            reserve-keyword
            placeholder="搜索姓名/手机号，可选择自己"
            style="width:100%"
            @visible-change="onSelectVisible">
            <el-option v-for="u in userOptions" :key="u.id" :label="formatUserLabel(u)" :value="Number(u.id)" />
          </el-select>
          <div class="tt-selected-info">
            <span>已选 {{ initForm.testers.length }} 人</span>
            <el-button size="mini" type="text" @click="selectAllSearch">全选当前搜索结果</el-button>
            <el-button size="mini" type="text" @click="initForm.testers = []">清空</el-button>
          </div>
          <div v-if="initForm.testers.length" class="tt-selected-tags">
            <el-tag v-for="uid in initForm.testers" :key="uid" size="mini" closable @close="removeTester(uid)">
              {{ getSelectedUserName(uid) }}
            </el-tag>
          </div>
        </el-form-item>
        <el-form-item label="测试内容" prop="test_scope">
          <tinymce v-model="initForm.test_scope" :height="220" />
        </el-form-item>
        <el-form-item label="加急测试">
          <el-switch v-model="initForm.is_urgent" @change="onUrgentChange" />
          <span v-if="initForm.is_urgent" class="tt-urgent-tip">加急任务将在2小时内完成</span>
        </el-form-item>
        <el-form-item label="截止时间" prop="deadline">
          <div class="tt-quick-time">
            <el-button size="mini" :disabled="initForm.is_urgent" @click="setQuickDeadline(1)">1天内</el-button>
            <el-button size="mini" :disabled="initForm.is_urgent" @click="setQuickDeadline(3)">3天内</el-button>
            <el-button size="mini" :disabled="initForm.is_urgent" @click="setQuickDeadline(7)">1周内</el-button>
          </div>
          <el-date-picker v-model="initForm.deadline" type="datetime" placeholder="测试完成截止时间（必须晚于当前时间）" style="width:100%" :disabled="initForm.is_urgent" />
        </el-form-item>
      </el-form>
      <div v-if="initiateError" class="tt-retry-tip">
        <el-alert :title="initiateError" :closable="false" type="error" show-icon>
          <el-button size="mini" type="primary" @click="doInitiate">重试</el-button>
        </el-alert>
      </div>
      <span slot="footer" class="tt-dialog-footer">
        <el-button size="small" @click="initiateVisible = false">取消</el-button>
        <el-button :loading="acting" size="small" type="primary" @click="doInitiate">确认发起</el-button>
      </span>
    </el-dialog>

    <!-- ===================== 查看详情子弹窗 ===================== -->
    <el-dialog
      :visible.sync="detailVisible"
      :width="subDialogWidth"
      title="测试任务详情"
      append-to-body
      custom-class="tt-detail-dialog">
      <div v-loading="detailLoading" class="tt-detail" style="min-height:200px">
        <template v-if="detailData">
          <el-row :gutter="16">
            <el-col :xs="24" :sm="12"><div class="tt-detail-item"><span class="tt-detail-label">原任务</span><span class="tt-detail-value">{{ detailData.origin_task_name || ('#' + detailData.origin_task_id) }}</span></div></el-col>
            <el-col :xs="24" :sm="12"><div class="tt-detail-item"><span class="tt-detail-label">测试任务</span><span class="tt-detail-value">{{ detailData.task_name || ('#' + detailData.task_id) }}</span></div></el-col>
            <el-col :xs="24" :sm="12"><div class="tt-detail-item"><span class="tt-detail-label">测试人员</span><span class="tt-detail-value">{{ detailData.tester_name || ('#' + detailData.tester_user_id) }}</span></div></el-col>
            <el-col :xs="24" :sm="12"><div class="tt-detail-item"><span class="tt-detail-label">截止时间</span><span class="tt-detail-value">{{ formatDateTime(detailData.deadline) }}</span></div></el-col>
            <el-col :xs="24" :sm="12"><div class="tt-detail-item"><span class="tt-detail-label">状态</span><span class="tt-detail-value">{{ detailData.display_status }}</span></div></el-col>
          </el-row>
          <div class="tt-detail-full"><span class="tt-detail-label">测试内容</span><span class="tt-detail-value" v-html="safeHtml(detailData.test_scope)"></span></div>
          <template v-if="detailData.submit_result">
            <div class="tt-detail-full"><span class="tt-detail-label">测试结果</span><span class="tt-detail-value">{{ detailData.submit_result }}</span></div>
            <div v-if="detailData.submit_issues" class="tt-detail-full"><span class="tt-detail-label">{{ detailData.submit_result === '发现问题' ? '问题说明' : '测试说明' }}</span><span class="tt-detail-value">{{ detailData.submit_issues }}</span></div>
          </template>
          <!-- 旧数据兼容：显示评定人和历史评定信息（只读） -->
          <template v-if="detailData.reviewer_user_id > 0 && detailData.review_status && detailData.review_status !== 'pending'">
            <div class="tt-detail-full"><span class="tt-detail-label">评定人（旧）</span><span class="tt-detail-value">{{ detailData.reviewer_name || ('#' + detailData.reviewer_user_id) }}</span></div>
            <div class="tt-detail-full"><span class="tt-detail-label">评定结果（旧）</span><span class="tt-detail-value">{{ reviewText(detailData.review_status) }}</span></div>
            <template v-if="detailData.review_status === 'non_compliant'">
              <div class="tt-detail-full"><span class="tt-detail-label">不合格原因（旧）</span><span class="tt-detail-value">{{ detailData.return_reason || '-' }}</span></div>
            </template>
          </template>
        </template>
      </div>
      <span slot="footer">
        <el-button size="small" @click="detailVisible = false">关闭</el-button>
      </span>
    </el-dialog>

    <!-- ===================== 提交测试反馈子弹窗 ===================== -->
    <el-dialog
      :visible.sync="submitVisible"
      :width="subDialogWidth"
      :close-on-click-modal="false"
      title="提交测试反馈"
      append-to-body
      custom-class="tt-submit-dialog">
      <el-form ref="submitFormRef" :model="submitForm" :rules="submitRules" label-width="90px" size="small">
        <el-form-item label="测试内容">
          <div class="tt-readonly-box" v-html="safeHtml(submitRowInfo.test_scope)"></div>
        </el-form-item>
        <el-form-item label="测试结果" prop="result">
          <el-radio-group v-model="submitForm.result">
            <el-radio label="无问题">无问题</el-radio>
            <el-radio label="发现问题">发现问题</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item :label="submitForm.result === '发现问题' ? '问题说明' : '测试说明'" prop="issues">
          <el-input v-model="submitForm.issues" :rows="3" type="textarea" :placeholder="submitForm.result === '发现问题' ? '请填写发现的问题说明（必填）' : '请说明测试了哪些内容及结果（必填）'" />
        </el-form-item>
      </el-form>
      <span slot="footer" class="tt-dialog-footer">
        <el-button size="small" @click="submitVisible = false">取消</el-button>
        <el-button :loading="acting" size="small" type="primary" @click="doSubmit">提交反馈</el-button>
      </span>
    </el-dialog>

    <!-- ===================== 评定子弹窗（旧数据兼容）===================== -->
    <el-dialog
      :visible.sync="reviewVisible"
      :width="subDialogWidth"
      :close-on-click-modal="false"
      title="评定测试（旧流程）"
      append-to-body
      custom-class="tt-review-dialog">
      <el-form ref="reviewFormRef" :model="reviewForm" :rules="reviewRules" label-width="90px" size="small">
        <el-form-item label="评定结果" prop="verdict">
          <el-radio-group v-model="reviewForm.verdict">
            <el-radio label="compliant">合格</el-radio>
            <el-radio label="non_compliant">不合格</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="reviewForm.verdict === 'compliant'" label="评价说明" prop="return_reason">
          <el-input v-model="reviewForm.return_reason" :rows="3" type="textarea" placeholder="简短评价说明（必填）" />
        </el-form-item>
        <template v-if="reviewForm.verdict === 'non_compliant'">
          <el-form-item label="不合格原因" prop="return_reason">
            <el-input v-model="reviewForm.return_reason" :rows="3" type="textarea" placeholder="不合格原因（必填）" />
          </el-form-item>
          <el-form-item label="补充要求">
            <el-input v-model="reviewForm.return_requirements" :rows="3" type="textarea" placeholder="需要补充或修改的内容" />
          </el-form-item>
          <el-form-item label="重提截止">
            <el-date-picker v-model="reviewForm.return_deadline" type="date" value-format="yyyy-MM-dd" placeholder="重新提交截止时间" style="width:100%" />
          </el-form-item>
        </template>
      </el-form>
      <span slot="footer" class="tt-dialog-footer">
        <el-button size="small" @click="reviewVisible = false">取消</el-button>
        <el-button :loading="acting" size="small" type="primary" @click="doReview">确认评定</el-button>
      </span>
    </el-dialog>

    <!-- ===================== 测试历史子弹窗 ===================== -->
    <el-dialog
      :visible.sync="historyVisible"
      :width="subDialogWidth"
      title="测试历史"
      append-to-body
      custom-class="tt-history-dialog">
      <div v-loading="true" v-if="historyLoading" style="min-height:120px" />
      <div v-else-if="historyList.length" class="tt-timeline">
        <div v-for="h in historyList" :key="h.history_id" class="tt-timeline-item">
          <div :class="{ 'is-review': h.history_type === 'review' }" class="tt-timeline-dot" />
          <div class="tt-timeline-content">
            <div class="tt-timeline-header">
              <span class="tt-timeline-round">第 {{ h.round }} 轮</span>
              <el-tag :type="h.history_type === 'submit' ? 'info' : (h.review_status === 'compliant' ? 'success' : 'danger')" size="mini">{{ h.history_type_name }}</el-tag>
              <span class="tt-timeline-user">{{ h.user_name || ('#' + h.user_id) }}</span>
              <span class="tt-timeline-time">{{ formatDateTime(h.create_time) }}</span>
            </div>
            <div v-if="h.history_type === 'submit'" class="tt-timeline-body">
              <div v-if="h.content"><span class="tt-tl-label">测试结果：</span>{{ h.content }}</div>
              <div v-if="h.issues"><span class="tt-tl-label">{{ h.content === '发现问题' ? '问题说明' : '测试说明' }}：</span>{{ h.issues }}</div>
            </div>
            <div v-else class="tt-timeline-body">
              <div><span class="tt-tl-label">评定结果：</span>{{ h.review_status_name }}</span></div>
              <div v-if="h.content"><span class="tt-tl-label">{{ h.review_status === 'compliant' ? '评价说明' : '退回原因' }}：</span>{{ h.content }}</div>
              <div v-if="h.issues"><span class="tt-tl-label">补充要求：</span>{{ h.issues }}</div>
            </div>
          </div>
        </div>
      </div>
      <div v-else class="tt-empty">暂无历史记录</div>
    </el-dialog>
  </el-dialog>
</template>

<script>
import { initiateTestAPI, submitTestAPI, reviewTestAPI, testListAPI, testDetailAPI, testHistoryAPI, workflowReadAPI, deleteTestAPI } from '@/api/task/workflow'
import { usersListIndexAPI } from '@/api/common'
import { detailsTaskAPI } from '@/api/task/task'
import Tinymce from '@/components/Tinymce'
import xss from 'xss'

const REVIEW_MAP = { pending: '待评定', compliant: '合格', non_compliant: '不合格' }

export default {
  name: 'TestTaskDialog',
  components: { Tinymce },
  props: {
    visible: Boolean,
    originTaskId: [Number, String]
  },
  data() {
    return {
      tableLoading: false,
      acting: false,
      list: [],
      selectedRows: [],
      originTaskName: '',
      stats: { total: 0, pending_feedback: 0, feedbacked: 0, overdue: 0 },
      userOptions: [],
      userLoading: false,
      currentUserId: 0,
      currentUserName: '',
      // 发起测试
      initiateVisible: false,
      initiateError: '',
      requestId: '',
      initForm: { testers: [], test_scope: '', deadline: '', is_urgent: false },
      prevDeadline: null,
      initRules: {
        testers: [{ required: true, type: 'array', message: '请至少选择一名测试人员', trigger: 'change' }],
        test_scope: [{ required: true, validator: function(rule, value, callback) {
          var text = value ? value.replace(/<[^>]+>/g, '').replace(/&nbsp;/g, '').trim() : ''
          if (!text) callback(new Error('请填写测试内容'))
          else callback()
        }, trigger: 'blur' }],
        deadline: [{ required: true, message: '请选择测试完成截止时间', trigger: 'change' }]
      },
      // 详情
      detailVisible: false,
      detailLoading: false,
      detailData: null,
      // 提交
      submitVisible: false,
      submitRowInfo: {},
      submitForm: { task_id: 0, version: 0, result: '', issues: '' },
      submitRules: {
        result: [{ required: true, message: '请选择测试结果', trigger: 'change' }],
        issues: [{ required: true, message: '请填写测试说明或问题说明', trigger: 'blur' }]
      },
      // 评定（旧数据兼容）
      reviewVisible: false,
      reviewForm: { task_id: 0, version: 0, verdict: 'compliant', return_reason: '', return_requirements: '', return_deadline: '' },
      reviewRules: {
        verdict: [{ required: true, message: '请选择评定结果', trigger: 'change' }],
        return_reason: [{ required: true, message: '请填写说明', trigger: 'blur' }]
      },
      // 历史
      historyVisible: false,
      historyLoading: false,
      historyList: []
    }
  },
  computed: {
    dialogVisible: {
      get() { return this.visible },
      set(val) { this.$emit('update:visible', val) }
    },
    mgmtWidth() {
      if (typeof window === 'undefined') return '1050px'
      return window.innerWidth < 768 ? '95%' : '1050px'
    },
    subDialogWidth() {
      if (typeof window === 'undefined') return '600px'
      return window.innerWidth < 768 ? '95%' : '600px'
    },
    initiateDialogWidth() {
      if (typeof window === 'undefined') return '820px'
      return window.innerWidth < 768 ? '95%' : '820px'
    }
  },
  methods: {
    reviewText(s) { return REVIEW_MAP[s] || s },
    safeHtml(html) { return html ? xss(html) : '' },
    stripHtml(html) {
      if (!html) return ''
      return html.replace(/<[^>]+>/g, '').replace(/&nbsp;/g, ' ').trim()
    },
    tableRowClassName({ row }) {
      if (row.display_status === '已逾期') return 'tt-row-overdue'
      return ''
    },
    handleSelectionChange(rows) {
      this.selectedRows = rows
    },
    async deleteTestTask(row) {
      var testerName = row.tester_name || ('#' + row.tester_user_id)
      try {
        await this.$confirm(
          '确认删除「' + testerName + '」的测试任务吗？\n删除后该人员不再需要反馈测试结果。',
          '删除测试任务',
          { confirmButtonText: '确认删除', cancelButtonText: '取消', type: 'warning' }
        )
      } catch (e) { return }
      this.acting = true
      try {
        await deleteTestAPI({ task_id: Number(row.task_id), version: Number(row.version), reason: '取消该人员测试任务' })
        this.$message.success('已删除')
        await this.fetchList()
      } catch (e) { /* 全局拦截器提示 */ } finally {
        this.acting = false
      }
    },
    async batchDelete() {
      if (!this.selectedRows.length) return
      try {
        await this.$confirm(
          '确认删除选中的 ' + this.selectedRows.length + ' 个测试任务吗？',
          '批量删除',
          { confirmButtonText: '确认删除', cancelButtonText: '取消', type: 'warning' }
        )
      } catch (e) { return }
      this.acting = true
      try {
        for (var i = 0; i < this.selectedRows.length; i++) {
          await deleteTestAPI({ task_id: Number(this.selectedRows[i].task_id), version: Number(this.selectedRows[i].version), reason: '批量取消测试任务' })
        }
        this.$message.success('已删除 ' + this.selectedRows.length + ' 个测试任务')
        this.selectedRows = []
        await this.fetchList()
      } catch (e) { /* 全局拦截器提示 */ } finally {
        this.acting = false
      }
    },
    displayTagType(s) {
      if (s === '已反馈') return 'success'
      if (s === '已逾期') return 'danger'
      return 'warning'
    },
    formatUserLabel(u) {
      var label = u.realname || ('#' + u.id)
      if (u.s_name) label += ' / ' + u.s_name
      return label
    },
    getSelectedUserName(uid) {
      var u = this.userOptions.find(function(x) { return Number(x.id) === Number(uid) })
      return u ? (u.realname || ('#' + uid)) : ('#' + uid)
    },
    removeTester(uid) {
      this.initForm.testers = this.initForm.testers.filter(function(x) { return Number(x) !== Number(uid) })
    },
    canSubmit(row) {
      // 只有指定测试人 + 未提交（submit_status=not_submitted）+ 未合格 才允许提交
      return Number(row.tester_user_id) === Number(this.currentUserId) &&
        row.submit_status === 'not_submitted' &&
        row.review_status !== 'compliant'
    },
    canReview(row) {
      // 仅旧数据（有评定人）且已提交待评定时可用；新流程不再使用
      return Number(row.reviewer_user_id) > 0 &&
        Number(row.reviewer_user_id) === Number(this.currentUserId) &&
        row.submit_status === 'submitted' &&
        row.review_status === 'pending'
    },
    formatDate(v) {
      if (!v) return '-'
      if (/^\d+$/.test(String(v))) {
        var d = new Date(Number(v) * 1000)
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0')
      }
      return String(v).substring(0, 10)
    },
    formatDateTime(v) {
      if (!v) return '-'
      var d = new Date(Number(v) * 1000)
      return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0') + ' ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0')
    },
    handleOpen() {
      var userInfo = (this.$store && this.$store.state.user && this.$store.state.user.userInfo) || {}
      this.currentUserId = Number(userInfo.id) || 0
      this.currentUserName = userInfo.realname || ''
      this.fetchList()
    },
    async fetchList() {
      if (!this.originTaskId) return
      this.tableLoading = true
      try {
        const res = await testListAPI({ origin_task_id: Number(this.originTaskId) })
        const data = res.data || res
        this.list = data.list || []
        this.stats = data.stats || { total: 0, pending_feedback: 0, feedbacked: 0, overdue: 0 }
        if (data.origin_task_name) {
          this.originTaskName = data.origin_task_name
        } else if (this.list.length && this.list[0].origin_task_name) {
          this.originTaskName = this.list[0].origin_task_name
        }
      } catch (e) {
        /* 全局拦截器提示 */
      } finally {
        this.tableLoading = false
      }
    },
    onSelectVisible(visible) {
      if (visible && !this.userOptions.length) this.searchUsers('')
    },
    async searchUsers(query) {
      this.userLoading = true
      try {
        const res = await usersListIndexAPI({ search: query || '', page: 1, limit: 50, status: 1 })
        const d = res.data || {}
        var list = d.list || d.users || []
        if (!Array.isArray(list) && Array.isArray(d)) list = d
        // 发起人可以被选为测试人员，不再排除自己
        this.userOptions = list.filter(function(u) {
          return u.status === 1 || u.status === undefined
        })
      } catch (e) {
        this.userOptions = []
      } finally {
        this.userLoading = false
      }
    },
    selectAllSearch() {
      var self = this
      var existing = new Set(this.initForm.testers.map(Number))
      this.userOptions.forEach(function(u) {
        if (!existing.has(Number(u.id))) {
          self.initForm.testers.push(Number(u.id))
        }
      })
    },
    async openInitiate() {
      this.requestId = 'req-' + Date.now() + '-' + Math.random().toString(36).substr(2, 6)
      this.initiateError = ''
      this.initForm = { testers: [], test_scope: '', deadline: '', is_urgent: false }
      this.prevDeadline = null
      this.initiateVisible = true
      this.$nextTick(function() {
        if (this.$refs.initiateFormRef) this.$refs.initiateFormRef.clearValidate()
        if (!this.userOptions.length) this.searchUsers('')
      }.bind(this))
      // 自动带入任务描述和任务说明
      await this.autoFillTestScope()
    },
    async autoFillTestScope() {
      try {
        var parts = []
        // 获取任务描述
        var taskRes = await detailsTaskAPI({ task_id: Number(this.originTaskId) })
        var taskData = taskRes.data || taskRes
        if (taskData && taskData.description) {
          var descText = taskData.description.replace(/<[^>]+>/g, '').replace(/&nbsp;/g, ' ').trim()
          if (descText) parts.push('任务描述：<br>' + taskData.description)
        }
        // 获取工作流任务说明
        var wfRes = await workflowReadAPI({ task_id: Number(this.originTaskId) })
        var wfData = wfRes.data || wfRes
        if (wfData && wfData.acceptance_criteria) {
          var critText = wfData.acceptance_criteria.replace(/<[^>]+>/g, '').replace(/&nbsp;/g, ' ').trim()
          if (critText) parts.push('任务说明：<br>' + wfData.acceptance_criteria)
        }
        if (parts.length) {
          this.initForm.test_scope = parts.join('<br><br>')
        }
      } catch (e) {
        // 获取失败不影响用户手动填写
      }
    },
    onUrgentChange(val) {
      if (val) {
        // 选中加急：保存当前截止时间，设置为2小时后
        this.prevDeadline = this.initForm.deadline
        var urgentTime = new Date(Date.now() + 7200 * 1000)
        this.initForm.deadline = urgentTime
      } else {
        // 取消加急：恢复之前的截止时间，没有则恢复1天内
        if (this.prevDeadline) {
          this.initForm.deadline = this.prevDeadline
        } else {
          this.setQuickDeadline(1)
        }
      }
    },
    setQuickDeadline(days) {
      var d = new Date(Date.now() + days * 86400 * 1000)
      this.initForm.deadline = d
    },
    async doInitiate() {
      var self = this
      this.$refs.initiateFormRef.validate(function(valid) {
        if (!valid) return
        self.doInitiateApi()
      })
    },
    async doInitiateApi() {
      this.acting = true
      this.initiateError = ''
      // 加急测试由后端重新计算截止时间，非加急时前端校验
      if (!this.initForm.is_urgent) {
        var deadlineTs = this.initForm.deadline ? Math.floor(new Date(this.initForm.deadline).getTime() / 1000) : 0
        if (!deadlineTs || deadlineTs <= Math.floor(Date.now() / 1000)) {
          this.initiateError = '测试完成截止时间必须晚于当前时间'
          this.acting = false
          return
        }
      }
      try {
        await initiateTestAPI({
          origin_task_id: Number(this.originTaskId),
          request_id: this.requestId,
          testers: this.initForm.testers.map(Number),
          test_scope: this.initForm.test_scope,
          deadline: this.initForm.deadline ? Math.floor(new Date(this.initForm.deadline).getTime() / 1000) : 0,
          is_urgent: this.initForm.is_urgent ? 1 : 0,
          source_type: 'task',
          source_id: Number(this.originTaskId)
        })
        this.$message.success('测试任务已生成')
        this.initiateVisible = false
        await this.fetchList()
      } catch (e) {
        this.initiateError = (e && e.message) ? e.message : '发起测试失败，请重试'
      } finally {
        this.acting = false
      }
    },
    async openDetail(row) {
      this.detailVisible = true
      this.detailLoading = true
      this.detailData = null
      try {
        const res = await testDetailAPI({ task_id: Number(row.task_id) })
        this.detailData = res.data || res
      } catch (e) {
        this.detailData = row
      } finally {
        this.detailLoading = false
      }
    },
    openSubmit(row) {
      this.submitRowInfo = { test_scope: row.test_scope }
      this.submitForm = { task_id: Number(row.task_id), version: Number(row.version), result: '', issues: '' }
      this.submitVisible = true
      this.$nextTick(function() {
        if (this.$refs.submitFormRef) this.$refs.submitFormRef.clearValidate()
      }.bind(this))
    },
    doSubmit() {
      var self = this
      this.$refs.submitFormRef.validate(function(valid) {
        if (!valid) return
        self.doSubmitApi()
      })
    },
    async doSubmitApi() {
      this.acting = true
      try {
        await submitTestAPI(this.submitForm)
        this.$message.success('反馈已提交，测试任务已完成')
        this.submitVisible = false
        await this.fetchList()
      } catch (e) { /* 全局拦截器提示 */ } finally {
        this.acting = false
      }
    },
    openReview(row) {
      this.reviewForm = {
        task_id: Number(row.task_id), version: Number(row.version),
        verdict: 'compliant', return_reason: '', return_requirements: '', return_deadline: ''
      }
      this.reviewVisible = true
      this.$nextTick(function() {
        if (this.$refs.reviewFormRef) this.$refs.reviewFormRef.clearValidate()
      }.bind(this))
    },
    doReview() {
      var self = this
      this.$refs.reviewFormRef.validate(function(valid) {
        if (!valid) return
        self.doReviewApi()
      })
    },
    async doReviewApi() {
      this.acting = true
      try {
        await reviewTestAPI(this.reviewForm)
        this.$message.success('评定完成')
        this.reviewVisible = false
        await this.fetchList()
      } catch (e) { /* 全局拦截器提示 */ } finally {
        this.acting = false
      }
    },
    async openHistory(row) {
      this.historyVisible = true
      this.historyLoading = true
      this.historyList = []
      try {
        const res = await testHistoryAPI({ task_id: Number(row.task_id) })
        const data = res.data || res
        this.historyList = data.list || []
      } catch (e) { /* 全局拦截器提示 */ } finally {
        this.historyLoading = false
      }
    }
  }
}
</script>

<style scoped>
.tt-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; flex-wrap: wrap; gap: 8px; padding-bottom: 12px; border-bottom: 1px solid #ebeef5; }
.tt-header-info { display: flex; align-items: center; gap: 6px; }
.tt-header-label { font-size: 13px; color: #909399; }
.tt-header-name { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.tt-stats { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
.tt-stat { flex: 1; min-width: 90px; text-align: center; background: #fff; border-radius: 10px; padding: 12px 8px; border: 1px solid #ebeef5; transition: box-shadow 0.2s; position: relative; overflow: hidden; }
.tt-stat:hover { box-shadow: 0 3px 10px rgba(0,0,0,0.06); }
.tt-stat::before { content: ''; position: absolute; left: 0; top: 0; right: 0; height: 3px; }
.tt-stat:nth-child(1)::before { background: #909399; }
.tt-stat:nth-child(2)::before { background: #e6a23c; }
.tt-stat:nth-child(3)::before { background: #67c23a; }
.tt-stat:nth-child(4)::before { background: #f56c6c; }
.tt-stat-num { font-size: 24px; font-weight: 800; color: #303133; line-height: 1.2; }
.tt-stat-label { font-size: 11px; color: #909399; margin-top: 3px; text-transform: uppercase; letter-spacing: 0.3px; }
.tt-stat-warn { color: #e6a23c; }
.tt-stat-danger { color: #f56c6c; }
.tt-stat-success { color: #67c23a; }
.tt-readonly-box { font-size: 13px; color: #606266; background: #f7f8fa; padding: 10px 12px; border-radius: 6px; line-height: 1.6; word-break: break-all; border: 1px solid #ebeef5; }
.tt-selected-info { margin-top: 4px; display: flex; align-items: center; gap: 8px; font-size: 12px; color: #409eff; }
.tt-selected-tags { margin-top: 6px; display: flex; flex-wrap: wrap; gap: 4px; }
.tt-overdue-text { color: #f56c6c; font-weight: 600; }
.tt-delete-btn { color: #f56c6c !important; }
.tt-batch-bar { display: flex; align-items: center; gap: 12px; padding: 10px 0; margin-top: 8px; border-top: 1px solid #ebeef5; }
.tt-empty-state { text-align: center; padding: 48px 0; }
.tt-urgent-tip { color: #f56c6c; font-size: 12px; margin-left: 8px; font-weight: 600; }
.tt-quick-time { margin-bottom: 8px; display: flex; gap: 8px; }
.tt-retry-tip { margin-top: 12px; }
.tt-detail { }
.tt-detail-item { margin-bottom: 10px; display: flex; gap: 6px; }
.tt-detail-full { margin-bottom: 10px; }
.tt-detail-label { display: inline-block; min-width: 80px; color: #909399; font-size: 13px; }
.tt-detail-value { color: #303133; font-size: 13px; word-break: break-all; flex: 1; }
.tt-timeline { padding: 4px 0; }
.tt-timeline-item { position: relative; padding-left: 22px; padding-bottom: 18px; border-left: 2px solid #e4e7ed; }
.tt-timeline-item:last-child { border-left-color: transparent; padding-bottom: 0; }
.tt-timeline-dot { position: absolute; left: -6px; top: 2px; width: 10px; height: 10px; border-radius: 50%; background: #409eff; box-shadow: 0 0 0 3px rgba(64,158,255,0.15); }
.tt-timeline-dot.is-review { background: #e6a23c; box-shadow: 0 0 0 3px rgba(230,162,60,0.15); }
.tt-timeline-header { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 4px; }
.tt-timeline-round { font-weight: 700; font-size: 13px; color: #303133; }
.tt-timeline-user { font-size: 12px; color: #606266; }
.tt-timeline-time { font-size: 12px; color: #c0c4cc; margin-left: auto; }
.tt-timeline-body { font-size: 13px; color: #606266; line-height: 1.6; }
.tt-tl-label { color: #909399; }
.tt-empty { text-align: center; padding: 32px; color: #909399; font-size: 13px; }
</style>
<style>
.tt-mgmt-dialog .el-dialog__body { padding: 16px 20px; }
.tt-initiate-dialog .el-dialog__body { padding: 16px 20px; max-height: calc(100vh - 180px); overflow-y: auto; }
.tt-detail-dialog .el-dialog__body,
.tt-submit-dialog .el-dialog__body,
.tt-review-dialog .el-dialog__body,
.tt-history-dialog .el-dialog__body { padding: 16px 20px; }
.tt-dialog-footer { display: flex; justify-content: flex-end; gap: 8px; }
/* 逾期行背景 */
.el-table .tt-row-overdue { background-color: #fef0f0 !important; }
.el-table .tt-row-overdue:hover > td { background-color: #fde2e2 !important; }
@media (max-width: 768px) {
  .tt-mgmt-dialog,
  .tt-initiate-dialog,
  .tt-detail-dialog,
  .tt-submit-dialog,
  .tt-review-dialog,
  .tt-history-dialog { width: 95% !important; margin: 0 auto !important; }
}
</style>

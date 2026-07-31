<template>
  <div class="task-workflow-panel">
    <div v-if="loading" class="wp-loading">
      <i class="el-icon-loading" /> 加载工作流信息...
    </div>
    <div v-else-if="fetchError" class="wp-error">
      <el-alert :title="fetchError" :closable="false" type="error" show-icon>
        <el-button size="mini" type="text" @click="fetch">重试</el-button>
      </el-alert>
    </div>

    <!-- ===================== 测试任务详情卡片 ===================== -->
    <!-- 条件只依赖 data.is_test_task（不依赖 testData），避免 testData 加载期间闪现普通 W/R/K 卡片 -->
    <div v-else-if="visible && data.is_test_task" class="wp-test-card">
      <div v-if="!testData" class="wp-loading">
        <i class="el-icon-loading" /> 加载测试任务信息...
      </div>
      <template v-else>
        <div class="wp-header">
          <span class="wp-title">测试任务</span>
          <el-tag :type="testDisplayTagType(testData.display_status)" size="small">{{ testData.display_status || testReviewText(testData.review_status) }}</el-tag>
          <el-tag v-if="testData.current_round > 0" size="mini" type="info">第 {{ testData.current_round }} 轮</el-tag>
        </div>
        <div class="wp-test-grid">
          <div class="wp-test-item">
            <span class="wp-label">原任务</span>
            <span class="wp-value">{{ testData.origin_task_name || ('#' + testData.origin_task_id) }}</span>
          </div>
          <div class="wp-test-item">
            <span class="wp-label">测试类型</span>
            <span class="wp-value">{{ testData.test_type_name || testData.test_type }}</span>
          </div>
          <div class="wp-test-item">
            <span class="wp-label">测试人员</span>
            <span class="wp-value">{{ testData.tester_name || ('#' + testData.tester_user_id) }}</span>
          </div>
          <div class="wp-test-item">
            <span class="wp-label">截止时间</span>
            <span class="wp-value">{{ formatDate(testData.deadline) }}</span>
          </div>
          <div class="wp-test-item">
            <span class="wp-label">状态</span>
            <span class="wp-value">{{ testData.display_status || testSubmitText(testData.submit_status) }}</span>
          </div>
          <div class="wp-test-item wp-test-full">
            <span class="wp-label">测试内容</span>
            <span class="wp-value" v-html="safeHtml(testData.test_scope)"></span>
          </div>
          <template v-if="testData.submit_result">
            <div class="wp-test-item wp-test-full">
              <span class="wp-label">测试结果</span>
              <span class="wp-value">{{ testData.submit_result }}</span>
            </div>
            <div v-if="testData.submit_issues" class="wp-test-item wp-test-full">
              <span class="wp-label">{{ testData.submit_result === '发现问题' ? '问题说明' : '测试说明' }}</span>
              <span class="wp-value">{{ testData.submit_issues }}</span>
            </div>
          </template>
          <template v-if="testData.review_status === 'non_compliant'">
            <div class="wp-test-item wp-test-full">
              <span class="wp-label">不合格原因</span>
              <span class="wp-value">{{ testData.return_reason || '-' }}</span>
            </div>
            <div v-if="testData.return_requirements" class="wp-test-item wp-test-full">
              <span class="wp-label">补充要求</span>
              <span class="wp-value">{{ testData.return_requirements }}</span>
            </div>
            <div v-if="testData.return_deadline" class="wp-test-item">
              <span class="wp-label">重提截止</span>
              <span class="wp-value">{{ formatDate(testData.return_deadline) }}</span>
            </div>
          </template>
        </div>
        <div class="wp-actions">
          <el-button v-if="testData.can_submit" :loading="acting" size="small" type="primary" @click="openTestSubmit">提交测试结果</el-button>
          <el-button v-if="testData.can_review" :loading="acting" size="small" type="warning" @click="openTestReview">评定</el-button>
          <el-button size="small" @click="openTestHistory">查看测试历史</el-button>
        </div>
      </template>
    </div>

    <!-- ===================== 普通任务工作流摘要卡片 ===================== -->
    <div v-else-if="visible" class="wp-normal-card">
      <div class="wp-header">
        <span class="wp-title">任务评估 W/R/K</span>
        <el-popover placement="bottom-start" width="420" trigger="click">
          <div class="wp-dict">
            <div class="wp-dict-section">
              <div class="wp-dict-title">W = Workload（工作量）</div>
              <div v-for="(desc, code) in dict.W" :key="code" class="wp-dict-item">{{ code }} - {{ desc }}</div>
            </div>
            <div class="wp-dict-section">
              <div class="wp-dict-title">R = Risk（风险等级）</div>
              <div v-for="(desc, code) in dict.R" :key="code" class="wp-dict-item">{{ code }} - {{ desc }}</div>
            </div>
            <div class="wp-dict-section">
              <div class="wp-dict-title">K = 专业确认等级（数值越高，需要的专业确认/依据要求越高）</div>
              <div v-for="(desc, code) in dict.K" :key="code" class="wp-dict-item">{{ code }} - {{ desc }}</div>
            </div>
          </div>
          <i slot="reference" class="el-icon-question wp-help-icon" />
        </el-popover>
        <el-tag v-if="data.legacy" size="mini" type="info">旧任务</el-tag>
        <el-tag v-else size="mini" type="success">{{ data.main_status }}</el-tag>
        <el-tag v-if="data.aux_status" size="mini" type="warning">{{ data.aux_status }}</el-tag>
        <span v-if="data.version" class="wp-version">v{{ data.version }}</span>
      </div>

      <div v-if="data.legacy" class="wp-legacy">
        <el-alert :closable="false" type="warning" show-icon title="此任务尚未启用工作流评估">
          <span>W = 工作量，R = 风险，K = 专业确认等级。点击下方按钮初始化工作流以启用 W/R/K 评估。</span>
          <div style="margin-top:8px">
            <el-button :loading="initLoading" size="mini" type="primary" @click="initLegacyWorkflow">初始化工作流</el-button>
          </div>
        </el-alert>
      </div>

      <template v-if="!data.legacy">
        <div class="wp-info-bar">
          <span class="wp-info-item"><i class="el-icon-user" /> 负责人：{{ data.main_user_id ? getUserName(data.main_user_id) : '-' }}</span>
          <span v-if="data.start_time" class="wp-info-item"><i class="el-icon-time" /> 开始：{{ formatDateTime(data.start_time) }}</span>
          <span class="wp-info-item"><i class="el-icon-date" /> 截止：{{ data.stop_time ? formatDate(data.stop_time) : '-' }}</span>
          <span class="wp-info-item"><i class="el-icon-document" /> v{{ data.workflow_version || 1 }}</span>
        </div>

        <!-- 客户退回原因提示 -->
        <div v-if="data.last_customer_return_reason && data.main_status === '处理中'" class="wp-return-banner">
          <div class="wp-return-banner-title"><i class="el-icon-warning" /> 客户退回</div>
          <div class="wp-return-banner-body">
            <span>原因：{{ data.last_customer_return_reason }}</span>
            <span>退回人：{{ data.last_customer_return_user_name }}</span>
            <span>时间：{{ formatDateTime(data.last_customer_return_time) }}</span>
          </div>
        </div>

        <el-steps :active="stepActive" finish-status="success" process-status="process" align-center size="mini" class="wp-steps-bar">
          <el-step v-for="s in statusOrder" :key="s" :title="s" />
        </el-steps>

        <div class="wp-wrk-summary">
          <div class="wp-wrk-card-summary">
            <div class="wp-wrk-card-label">工作量 W</div>
            <div class="wp-wrk-card-value">{{ wrkDisplay('W', data.init_w, data.final_w) }}</div>
          </div>
          <div class="wp-wrk-card-summary">
            <div class="wp-wrk-card-label">风险 R</div>
            <div class="wp-wrk-card-value">{{ wrkDisplay('R', data.init_r, data.final_r) }}</div>
          </div>
          <div class="wp-wrk-card-summary">
            <div class="wp-wrk-card-label">专业确认等级 K</div>
            <div class="wp-wrk-card-value">{{ wrkDisplay('K', data.init_k, data.final_k) }}</div>
          </div>
        </div>
        <div v-if="data.acceptance_criteria" class="wp-criteria-row">
          <span class="wp-label">任务说明</span>
          <span class="wp-value" v-html="safeAcceptanceCriteria"></span>
        </div>

        <div class="wp-actions">
          <el-button v-if="data.main_status === '待评估'" :loading="acting" size="small" type="primary" @click="openEvaluate">进行评估</el-button>
          <el-button v-if="data.main_status === '待评估'" :loading="acting" size="small" @click="skipEvaluate">无需评估</el-button>
          <el-button v-if="data.main_status === '待处理'" :loading="acting" size="small" type="primary" @click="startProcess">开始处理</el-button>
          <el-button v-if="data.main_status === '处理中'" :loading="acting" size="small" type="primary" @click="openSubmitAcceptance">提交验收</el-button>
          <el-button v-if="data.main_status === '待内部验收'" :loading="acting" size="small" type="success" @click="simpleAct('acceptancePass')">验收通过</el-button>
          <el-button v-if="data.main_status === '待内部验收'" :loading="acting" size="small" @click="openReturn('acceptanceReturn')">验收退回</el-button>
          <el-button v-if="data.main_status === '待发布'" :loading="acting" size="small" type="primary" @click="confirmRelease">确认发布</el-button>
          <el-button v-if="data.main_status === '待客户验证'" :loading="acting" size="small" type="success" @click="simpleAct('customerConfirm')">客户确认</el-button>
          <el-button v-if="data.main_status === '待客户验证'" :loading="acting" size="small" @click="openReturn('customerReturn')">客户退回</el-button>
          <el-button v-if="data.need_customer_verify === 0 && (data.main_status === '待发布')" :loading="acting" size="small" type="success" @click="simpleAct('completeTask')">直接完成</el-button>
          <el-button size="small" @click="testDialogVisible = true">管理测试任务</el-button>
        </div>
      </template>

      <test-task-dialog :visible.sync="testDialogVisible" :origin-task-id="Number(taskId)" />
    </div>

    <!-- ===================== 评估弹窗 ===================== -->
    <el-dialog
      :visible.sync="showEvaluate"
      :width="dialogWidth"
      :close-on-click-modal="false"
      title="任务评估"
      append-to-body
      custom-class="wp-eval-dialog">
      <el-form ref="evaluateFormRef" :model="evaluateForm" :rules="evaluateRules" label-width="100px" size="small">
        <div class="wp-dialog-section-title">W/R/K 初始评估</div>
        <el-row :gutter="16">
          <el-col v-for="item in wrkSelectItems('init')" :key="item.field" :xs="24" :sm="8">
            <el-form-item :label="item.label" :prop="item.field">
              <el-select v-model="evaluateForm[item.field]" :placeholder="item.ph" style="width:100%" popper-class="wp-wrk-select-popper">
                <el-option v-for="v in item.options" :key="v" :label="v + ' - ' + (dict[item.type][v]||'')" :value="v" style="white-space: normal; line-height: 1.4; padding: 4px 12px;" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <div class="wp-dialog-section-title">执行与验收安排</div>
        <el-form-item label="任务说明" prop="acceptance_criteria">
          <tinymce v-model="evaluateForm.acceptance_criteria" :height="260" />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :xs="24" :sm="12">
            <el-form-item label="负责人" prop="main_user_id">
              <el-select v-model="evaluateForm.main_user_id" filterable placeholder="选择负责人" style="width:100%">
                <el-option v-for="u in userList" :key="u.id" :label="u.realname" :value="Number(u.id)" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :xs="24" :sm="12">
            <el-form-item label="截止时间" prop="stop_time">
              <el-date-picker v-model="evaluateForm.stop_time" type="date" value-format="yyyy-MM-dd" placeholder="截止时间" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <span slot="footer" class="wp-dialog-footer">
        <el-button size="small" @click="showEvaluate = false">取消</el-button>
        <el-button :loading="acting" size="small" type="primary" @click="confirmEvaluate">确认评估</el-button>
      </span>
    </el-dialog>

    <!-- ===================== 提交验收弹窗 ===================== -->
    <el-dialog
      :visible.sync="showAcceptance"
      :width="dialogWidth"
      :close-on-click-modal="false"
      title="提交验收"
      append-to-body
      custom-class="wp-accept-dialog">
      <el-form ref="acceptanceFormRef" :model="acceptanceForm" :rules="acceptanceRules" label-width="100px" size="small">
        <div v-if="hasInitWrk" class="wp-dialog-section-title">最终 W/R/K</div>
        <el-row v-if="hasInitWrk" :gutter="16">
          <el-col v-for="item in wrkSelectItems('final')" :key="item.field" :xs="24" :sm="8">
            <el-form-item :label="item.label" :prop="item.field">
              <el-select v-model="acceptanceForm[item.field]" :placeholder="item.ph" style="width:100%">
                <el-option v-for="v in item.options" :key="v" :label="v + ' - ' + (dict[item.type][v]||'')" :value="v" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <div class="wp-dialog-section-title">执行与验收安排</div>
        <el-form-item label="任务说明" prop="acceptance_criteria">
          <tinymce v-model="acceptanceForm.acceptance_criteria" :height="260" />
        </el-form-item>
        <el-form-item label="验收人" prop="acceptance_user_id">
          <el-select v-model="acceptanceForm.acceptance_user_id" filterable placeholder="选择验收人" style="width:100%">
            <el-option v-for="u in userList" :key="u.id" :label="u.realname + (u.status !== undefined && u.status !== 1 ? '（已停用）' : '')" :value="Number(u.id)" :disabled="u.status !== undefined && u.status !== 1" />
          </el-select>
        </el-form-item>
      </el-form>
      <span slot="footer" class="wp-dialog-footer">
        <el-button size="small" @click="showAcceptance = false">取消</el-button>
        <el-button :loading="acting" size="small" type="primary" @click="confirmAcceptance">确认提交验收</el-button>
      </span>
    </el-dialog>

    <!-- ===================== 退回原因弹窗 ===================== -->
    <el-dialog
      :visible.sync="showReturn"
      :width="dialogWidth"
      :title="returnAction === 'acceptanceReturn' ? '验收退回' : '客户退回'"
      :close-on-click-modal="false"
      append-to-body
      custom-class="wp-return-dialog">
      <el-form ref="returnFormRef" :model="returnForm" :rules="returnRules" label-width="80px" size="small">
        <el-form-item label="退回原因" prop="reason">
          <el-input v-model="returnForm.reason" :rows="4" type="textarea" placeholder="请填写退回原因" />
        </el-form-item>
      </el-form>
      <span slot="footer" class="wp-dialog-footer">
        <el-button size="small" @click="showReturn = false">取消</el-button>
        <el-button :loading="acting" size="small" type="primary" @click="doReturn">确认退回</el-button>
      </span>
    </el-dialog>

    <!-- ===================== 测试结果提交弹窗（测试任务）===================== -->
    <el-dialog
      :visible.sync="testSubmitVisible"
      :width="dialogWidth"
      :close-on-click-modal="false"
      title="提交测试结果"
      append-to-body
      custom-class="wp-test-submit-dialog">
      <el-form ref="testSubmitFormRef" :model="testSubmitForm" :rules="testSubmitRules" label-width="90px" size="small">
        <el-form-item label="测试内容">
          <div class="wp-readonly-box">{{ testData && testData.test_scope || '-' }}</div>
        </el-form-item>
        <el-form-item label="测试结果" prop="result">
          <el-radio-group v-model="testSubmitForm.result">
            <el-radio label="无问题">无问题</el-radio>
            <el-radio label="发现问题">发现问题</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item :label="testSubmitForm.result === '发现问题' ? '问题说明' : '测试说明'" prop="issues">
          <el-input v-model="testSubmitForm.issues" :rows="3" type="textarea" :placeholder="testSubmitForm.result === '发现问题' ? '请填写发现的问题说明（必填）' : '请说明测试了哪些内容及结果（必填）'" />
        </el-form-item>
      </el-form>
      <span slot="footer" class="wp-dialog-footer">
        <el-button size="small" @click="testSubmitVisible = false">取消</el-button>
        <el-button :loading="acting" size="small" type="primary" @click="doTestSubmit">提交</el-button>
      </span>
    </el-dialog>

    <!-- ===================== 测试评定弹窗（测试任务）===================== -->
    <el-dialog
      :visible.sync="testReviewVisible"
      :width="dialogWidth"
      :close-on-click-modal="false"
      title="评定测试"
      append-to-body
      custom-class="wp-test-review-dialog">
      <el-form ref="testReviewFormRef" :model="testReviewForm" :rules="testReviewRules" label-width="90px" size="small">
        <el-form-item label="评定结果" prop="verdict">
          <el-radio-group v-model="testReviewForm.verdict">
            <el-radio label="compliant">合格</el-radio>
            <el-radio label="non_compliant">不合格</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="testReviewForm.verdict === 'compliant'" label="评价说明" prop="return_reason">
          <el-input v-model="testReviewForm.return_reason" :rows="3" type="textarea" placeholder="简短评价说明（必填）" />
        </el-form-item>
        <template v-if="testReviewForm.verdict === 'non_compliant'">
          <el-form-item label="不合格原因" prop="return_reason">
            <el-input v-model="testReviewForm.return_reason" :rows="3" type="textarea" placeholder="不合格原因（必填）" />
          </el-form-item>
          <el-form-item label="补充要求">
            <el-input v-model="testReviewForm.return_requirements" :rows="3" type="textarea" placeholder="需要补充或修改的内容" />
          </el-form-item>
          <el-form-item label="重提截止">
            <el-date-picker v-model="testReviewForm.return_deadline" type="date" value-format="yyyy-MM-dd" placeholder="重新提交截止时间" style="width:100%" />
          </el-form-item>
        </template>
      </el-form>
      <span slot="footer" class="wp-dialog-footer">
        <el-button size="small" @click="testReviewVisible = false">取消</el-button>
        <el-button :loading="acting" size="small" type="primary" @click="doTestReview">确认评定</el-button>
      </span>
    </el-dialog>

    <!-- ===================== 测试历史弹窗（测试任务）===================== -->
    <el-dialog
      :visible.sync="testHistoryVisible"
      :width="dialogWidth"
      title="测试历史"
      append-to-body
      custom-class="wp-test-history-dialog">
      <div v-loading="true" v-if="testHistoryLoading" style="min-height:120px" />
      <div v-else-if="testHistoryList.length" class="wp-timeline">
        <div v-for="h in testHistoryList" :key="h.history_id" class="wp-timeline-item">
          <div :class="{ 'is-review': h.history_type === 'review' }" class="wp-timeline-dot" />
          <div class="wp-timeline-content">
            <div class="wp-timeline-header">
              <span class="wp-timeline-round">第 {{ h.round }} 轮</span>
              <el-tag :type="h.history_type === 'submit' ? 'info' : (h.review_status === 'compliant' ? 'success' : 'danger')" size="mini">{{ h.history_type_name }}</el-tag>
              <span class="wp-timeline-user">{{ h.user_name || ('#' + h.user_id) }}</span>
              <span class="wp-timeline-time">{{ formatDateTime(h.create_time) }}</span>
            </div>
            <div v-if="h.history_type === 'submit'" class="wp-timeline-body">
              <div v-if="h.content"><span class="wp-label">测试结果：</span>{{ h.content }}</div>
              <div v-if="h.issues"><span class="wp-label">{{ h.content === '发现问题' ? '问题说明' : '测试说明' }}：</span>{{ h.issues }}</div>
            </div>
            <div v-else class="wp-timeline-body">
              <div><span class="wp-label">评定结果：</span>{{ h.review_status_name }}</div>
              <div v-if="h.content"><span class="wp-label">{{ h.review_status === 'compliant' ? '评价说明' : '退回原因' }}：</span>{{ h.content }}</div>
              <div v-if="h.issues"><span class="wp-label">补充要求：</span>{{ h.issues }}</div>
            </div>
          </div>
        </div>
      </div>
      <div v-else class="wp-empty">暂无历史记录</div>
    </el-dialog>
  </div>
</template>

<script>
import {
  workflowReadAPI,
  wrkDictionaryAPI,
  evaluateTaskAPI,
  skipEvaluateAPI,
  startProcessTaskAPI,
  submitAcceptanceAPI,
  acceptancePassAPI,
  acceptanceReturnAPI,
  confirmReleaseAPI,
  customerConfirmAPI,
  customerReturnAPI,
  completeTaskAPI,
  testDetailAPI,
  testHistoryAPI,
  submitTestAPI,
  reviewTestAPI
} from '@/api/task/workflow'
import { workQueryMemberListAPI } from '@/api/pm/task'
import TestTaskDialog from './TestTaskDialog'
import Tinymce from '@/components/Tinymce'
import xss from 'xss'

const STATUS_ORDER = ['待评估', '待处理', '处理中', '待内部验收', '待发布', '待客户验证', '已完成']
const SUBMIT_MAP = { not_submitted: '未提交', submitted: '已提交' }
const REVIEW_MAP = { pending: '待评定', compliant: '合格', non_compliant: '不合格' }

export default {
  name: 'TaskWorkflowPanel',
  components: { TestTaskDialog, Tinymce },
  props: {
    taskId: [Number, String]
  },
  data() {
    return {
      visible: false,
      loading: false,
      fetchError: '',
      initLoading: false,
      acting: false,
      data: {},
      dict: { W: {}, R: {}, K: {}},
      userList: [],
      statusOrder: STATUS_ORDER,
      showEvaluate: false,
      showAcceptance: false,
      showReturn: false,
      returnAction: '',
      evaluateForm: { init_w: '', init_r: '', init_k: '', acceptance_criteria: '', main_user_id: '', stop_time: '' },
      acceptanceForm: { final_w: '', final_r: '', final_k: '', acceptance_criteria: '', acceptance_user_id: '' },
      returnForm: { reason: '' },
      evaluateRules: {
        init_w: [{ required: true, message: '请选择工作量 W', trigger: 'change' }],
        init_r: [{ required: true, message: '请选择风险 R', trigger: 'change' }],
        init_k: [{ required: true, message: '请选择专业确认等级 K', trigger: 'change' }],
        acceptance_criteria: [{ required: true, validator: function(rule, value, callback) {
          var text = value ? value.replace(/<[^>]+>/g, '').replace(/&nbsp;/g, '').trim() : ''
          if (!text) callback(new Error('请填写任务说明'))
          else callback()
        }, trigger: 'blur' }],
        main_user_id: [{ required: true, message: '请选择负责人', trigger: 'change' }],
        stop_time: [{ required: true, message: '请选择截止时间', trigger: 'change' }]
      },
      acceptanceRules: {
        final_w: [{ required: true, message: '请选择工作量 W', trigger: 'change' }],
        final_r: [{ required: true, message: '请选择风险 R', trigger: 'change' }],
        final_k: [{ required: true, message: '请选择专业确认等级 K', trigger: 'change' }],
        acceptance_criteria: [{ required: true, validator: function(rule, value, callback) {
          var text = value ? value.replace(/<[^>]+>/g, '').replace(/&nbsp;/g, '').trim() : ''
          if (!text) callback(new Error('请填写任务说明'))
          else callback()
        }, trigger: 'blur' }],
        acceptance_user_id: [{ required: true, message: '请选择验收人', trigger: 'change' }]
      },
      returnRules: {
        reason: [{ required: true, message: '请填写退回原因', trigger: 'blur' }]
      },
      testDialogVisible: false,
      testData: null,
      testSubmitVisible: false,
      testSubmitForm: { task_id: 0, version: 0, result: '', issues: '' },
      testSubmitRules: {
        result: [{ required: true, message: '请选择测试结果', trigger: 'change' }],
        issues: [{ required: true, message: '请填写测试说明或问题说明', trigger: 'blur' }]
      },
      testReviewVisible: false,
      testReviewForm: { task_id: 0, version: 0, verdict: 'compliant', return_reason: '', return_requirements: '', return_deadline: '' },
      testReviewRules: {
        verdict: [{ required: true, message: '请选择评定结果', trigger: 'change' }],
        return_reason: [{ required: true, message: '请填写说明', trigger: 'blur' }]
      },
      testHistoryVisible: false,
      testHistoryLoading: false,
      testHistoryList: []
    }
  },
  computed: {
    stepActive() {
      const index = STATUS_ORDER.indexOf(this.data.main_status)
      if (this.data.main_status === '已完成') {
        return STATUS_ORDER.length
      }
      return Math.max(0, index)
    },
    hasInitWrk() {
      return !!(this.data.init_w || this.data.init_r || this.data.init_k)
    },
    dialogWidth() {
      if (typeof window === 'undefined') return '960px'
      return window.innerWidth < 768 ? '95%' : '960px'
    },
    safeAcceptanceCriteria() {
      return this.data.acceptance_criteria ? xss(this.data.acceptance_criteria) : ''
    }
  },
  watch: {
    taskId: {
      handler(val) {
        if (val) this.fetch()
        else { this.visible = false; this.loading = false; this.fetchError = '' }
      },
      immediate: true
    }
  },
  methods: {
    async fetch() {
      if (!this.taskId) return
      this.loading = true
      this.fetchError = ''
      this.visible = false
      try {
        const res = await workflowReadAPI({ task_id: Number(this.taskId) })
        this.data = res.data || res
        this.visible = true
        if (this.data.is_test_task) {
          await this.fetchTestDetail()
        } else {
          if (!this.dict.W || !this.dict.W.W1) this.loadDict()
          if (!this.userList.length) this.loadUsers()
        }
      } catch (e) {
        this.fetchError = (e && e.message) ? e.message : '工作流信息加载失败，请检查接口或权限'
        this.visible = false
      } finally {
        this.loading = false
      }
    },
    async fetchTestDetail() {
      try {
        const res = await testDetailAPI({ task_id: Number(this.taskId) })
        this.testData = res.data || res
      } catch (e) {
        this.testData = this.data.test_ext || null
      }
    },
    async initLegacyWorkflow() {
      this.initLoading = true
      try {
        await workflowReadAPI({ task_id: Number(this.taskId), force_init: 1 })
        this.$message.success('工作流已初始化')
        this.fetch()
        this.$emit('refresh')
      } catch (e) {
        this.$message.error((e && e.message) ? e.message : '初始化失败')
      } finally {
        this.initLoading = false
      }
    },
    async loadDict() {
      try {
        const res = await wrkDictionaryAPI()
        this.dict = (res.data || res) || this.dict
      } catch (e) { /* 忽略：错误已由全局拦截器提示 */ }
    },
    async loadUsers() {
      try {
        const res = await workQueryMemberListAPI()
        this.userList = res.data || []
      } catch (e) { /* 忽略：错误已由全局拦截器提示 */ }
    },
    wrkText(type, val) {
      if (!val) return '尚未评估'
      const def = this.dict[type] && this.dict[type][val]
      return def ? val + ' - ' + def : val
    },
    wrkDisplay(type, initVal, finalVal) {
      if (!initVal && !finalVal) return '尚未评估'
      if (!finalVal) return this.wrkText(type, initVal)
      if (initVal === finalVal) return this.wrkText(type, finalVal)
      return this.wrkText(type, initVal) + ' -> ' + this.wrkText(type, finalVal)
    },
    getUserName(uid) {
      var u = this.userList.find(function(x) { return Number(x.id) === Number(uid) })
      return u ? u.realname : ('#' + uid)
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
    testSubmitText(s) { return SUBMIT_MAP[s] || s },
    testReviewText(s) { return REVIEW_MAP[s] || s },
    testReviewTagType(s) {
      if (s === 'compliant') return 'success'
      if (s === 'non_compliant') return 'danger'
      return 'warning'
    },
    testDisplayTagType(s) {
      if (s === '已反馈') return 'success'
      if (s === '已逾期') return 'danger'
      return 'warning'
    },
    safeHtml(html) {
      return html ? xss(html) : ''
    },
    wrkSelectItems(prefix) {
      var typeMap = { w: 'W', r: 'R', k: 'K' }
      var labelMap = { w: '工作量', r: '风险', k: '专业确认等级' }
      var opts = { W: ['W1', 'W2', 'W3', 'W4', 'W5'], R: ['R1', 'R2', 'R3', 'R4', 'R5'], K: ['K1', 'K2', 'K3', 'K4'] }
      return ['w', 'r', 'k'].map(function(letter) {
        var field = prefix + '_' + letter
        return { field: field, label: labelMap[letter], type: typeMap[letter], options: opts[typeMap[letter]], ph: '选择' + labelMap[letter] }
      })
    },
    async simpleAct(action) {
      var apiMap = {
        acceptancePass: acceptancePassAPI,
        customerConfirm: customerConfirmAPI,
        completeTask: completeTaskAPI
      }
      var fn = apiMap[action]
      if (!fn) return
      this.acting = true
      try {
        await fn({ task_id: Number(this.taskId), version: Number(this.data.version) })
        this.$message.success('操作成功')
        await this.fetch()
        this.$emit('refresh')
      } catch (e) { /* 忽略：错误已由全局拦截器提示 */ } finally { this.acting = false }
    },
    openEvaluate() {
      var stopTime = this.data.stop_time || ''
      if (stopTime && /^\d+$/.test(String(stopTime))) {
        var d = new Date(Number(stopTime) * 1000)
        stopTime = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0')
      } else if (stopTime && stopTime.indexOf(' ') > 0) {
        stopTime = stopTime.substring(0, 10)
      }
      this.evaluateForm = {
        init_w: this.data.init_w || 'W1',
        init_r: this.data.init_r || 'R1',
        init_k: this.data.init_k || 'K1',
        acceptance_criteria: this.data.acceptance_criteria || '',
        main_user_id: this.data.main_user_id ? Number(this.data.main_user_id) : '',
        stop_time: stopTime
      }
      this.showEvaluate = true
      this.$nextTick(function() {
        if (this.$refs.evaluateFormRef) this.$refs.evaluateFormRef.clearValidate()
      }.bind(this))
    },
    async confirmEvaluate() {
      this.$refs.evaluateFormRef.validate(function(valid) {
        if (!valid) return
        this.acting = true
        evaluateTaskAPI(Object.assign({ task_id: Number(this.taskId), version: Number(this.data.version) }, this.evaluateForm)).then(function() {
          this.$message.success('评估完成，已进入待处理')
          this.showEvaluate = false
          this.fetch()
          this.$emit('refresh')
        }.bind(this)).catch(function() {}).finally(function() {
          this.acting = false
        }.bind(this))
      }.bind(this))
    },
    skipEvaluate() {
      this.$confirm('确认跳过评估，直接进入待处理？跳过后可正常开始处理，不要求填写 W/R/K。', '无需评估', {
        confirmButtonText: '无需评估，进入待处理',
        cancelButtonText: '取消',
        type: 'info'
      }).then(function() {
        this.acting = true
        skipEvaluateAPI({ task_id: Number(this.taskId), version: Number(this.data.version) }).then(function() {
          this.$message.success('已跳过评估，进入待处理')
          this.fetch()
          this.$emit('refresh')
        }.bind(this)).catch(function() {}).finally(function() {
          this.acting = false
        }.bind(this))
      }.bind(this)).catch(function() {})
    },
    startProcess() {
      this.$confirm('确认开始处理？', '开始处理', {
        confirmButtonText: '确认开始处理',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(function() {
        this.acting = true
        startProcessTaskAPI({ task_id: Number(this.taskId), version: Number(this.data.version) }).then(function() {
          this.$message.success('已开始处理')
          this.fetch()
          this.$emit('refresh')
        }.bind(this)).catch(function() {}).finally(function() {
          this.acting = false
        }.bind(this))
      }.bind(this)).catch(function() {})
    },
    async openSubmitAcceptance() {
      // 确保人员列表已加载，再回填默认验收人
      if (!this.userList.length) {
        await this.loadUsers()
      }
      this.acceptanceForm = {
        final_w: this.data.final_w || this.data.init_w || '',
        final_r: this.data.final_r || this.data.init_r || '',
        final_k: this.data.final_k || this.data.init_k || '',
        acceptance_criteria: this.data.acceptance_criteria || '',
        acceptance_user_id: this.data.acceptance_user_id ? Number(this.data.acceptance_user_id) : ''
      }
      this.showAcceptance = true
      this.$nextTick(function() {
        if (this.$refs.acceptanceFormRef) this.$refs.acceptanceFormRef.clearValidate()
      }.bind(this))
    },
    async confirmAcceptance() {
      this.$refs.acceptanceFormRef.validate(function(valid) {
        if (!valid) return
        this.acting = true
        submitAcceptanceAPI(Object.assign({ task_id: Number(this.taskId), version: Number(this.data.version) }, this.acceptanceForm)).then(function() {
          this.$message.success('已提交验收')
          this.showAcceptance = false
          this.fetch()
          this.$emit('refresh')
        }.bind(this)).catch(function() {}).finally(function() {
          this.acting = false
        }.bind(this))
      }.bind(this))
    },
    openReturn(action) {
      this.returnAction = action
      this.returnForm = { reason: '' }
      this.showReturn = true
      this.$nextTick(function() {
        if (this.$refs.returnFormRef) this.$refs.returnFormRef.clearValidate()
      }.bind(this))
    },
    async doReturn() {
      this.$refs.returnFormRef.validate(function(valid) {
        if (!valid) return
        var fn = this.returnAction === 'acceptanceReturn' ? acceptanceReturnAPI : customerReturnAPI
        this.acting = true
        fn({ task_id: Number(this.taskId), version: Number(this.data.version), reason: this.returnForm.reason }).then(function() {
          this.$message.success('已退回')
          this.showReturn = false
          this.fetch()
          this.$emit('refresh')
        }.bind(this)).catch(function() {}).finally(function() {
          this.acting = false
        }.bind(this))
      }.bind(this))
    },
    async confirmRelease() {
      this.acting = true
      try {
        await confirmReleaseAPI({ task_id: Number(this.taskId), version: Number(this.data.version) })
        this.$message.success('已确认发布')
        this.fetch()
        this.$emit('refresh')
      } catch (e) { /* 忽略：错误已由全局拦截器提示 */ } finally { this.acting = false }
    },
    // ===================== 测试任务操作 =====================
    openTestSubmit() {
      this.testSubmitForm = { task_id: Number(this.taskId), version: Number(this.testData.version), result: '', issues: '' }
      this.testSubmitVisible = true
      this.$nextTick(function() {
        if (this.$refs.testSubmitFormRef) this.$refs.testSubmitFormRef.clearValidate()
      }.bind(this))
    },
    doTestSubmit() {
      this.$refs.testSubmitFormRef.validate(function(valid) {
        if (!valid) return
        this.acting = true
        submitTestAPI(this.testSubmitForm).then(function() {
          this.$message.success('反馈已提交，测试任务已完成')
          this.testSubmitVisible = false
          this.fetchTestDetail()
          this.$emit('refresh')
        }.bind(this)).catch(function() {}).finally(function() {
          this.acting = false
        }.bind(this))
      }.bind(this))
    },
    openTestReview() {
      this.testReviewForm = {
        task_id: Number(this.taskId), version: Number(this.testData.version),
        verdict: 'compliant', return_reason: '', return_requirements: '', return_deadline: ''
      }
      this.testReviewVisible = true
      this.$nextTick(function() {
        if (this.$refs.testReviewFormRef) this.$refs.testReviewFormRef.clearValidate()
      }.bind(this))
    },
    doTestReview() {
      this.$refs.testReviewFormRef.validate(function(valid) {
        if (!valid) return
        this.acting = true
        reviewTestAPI(this.testReviewForm).then(function() {
          this.$message.success('评定完成')
          this.testReviewVisible = false
          this.fetchTestDetail()
          this.$emit('refresh')
        }.bind(this)).catch(function() {}).finally(function() {
          this.acting = false
        }.bind(this))
      }.bind(this))
    },
    async openTestHistory() {
      this.testHistoryVisible = true
      this.testHistoryLoading = true
      this.testHistoryList = []
      try {
        const res = await testHistoryAPI({ task_id: Number(this.taskId) })
        const data = res.data || res
        this.testHistoryList = data.list || []
      } catch (e) { /* 全局拦截器提示 */ } finally {
        this.testHistoryLoading = false
      }
    }
  }
}
</script>

<style scoped>
.task-workflow-panel { padding: 12px 14px; background: linear-gradient(135deg, #f8f9fc 0%, #f0f2f8 100%); border-radius: 8px; margin-bottom: 12px; border: 1px solid #ebeef5; }
.wp-loading { padding: 24px; text-align: center; color: #909399; font-size: 13px; }
.wp-error { padding: 4px 0; }
.wp-legacy { margin-bottom: 8px; }
.wp-header { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
.wp-title { font-weight: 700; font-size: 15px; color: #1a1a2e; letter-spacing: 0.3px; }
.wp-version { font-size: 11px; color: #c0c4cc; margin-left: auto; }
.wp-label { color: #909399; margin-right: 6px; white-space: nowrap; font-size: 12px; }
.wp-value { color: #303133; word-break: break-all; }
.wp-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; padding-top: 10px; border-top: 1px dashed #e4e7ed; }
.wp-help-icon { color: #909399; cursor: pointer; font-size: 15px; transition: color 0.2s; }
.wp-help-icon:hover { color: #409eff; }
.wp-dict { max-height: 400px; overflow-y: auto; }
.wp-dict-section { margin-bottom: 12px; }
.wp-dict-title { font-weight: 600; font-size: 13px; color: #333; margin-bottom: 4px; }
.wp-dict-item { font-size: 12px; color: #606266; line-height: 1.6; }
.wp-info-bar { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 10px; padding: 10px 14px; background: #fff; border-radius: 8px; font-size: 12px; color: #606266; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.wp-info-item { display: inline-flex; align-items: center; }
.wp-info-item i { margin-right: 4px; color: #a0a4b8; font-size: 13px; }
.wp-steps-bar { margin-bottom: 12px; padding: 4px 0; }
.wp-wrk-summary { display: flex; gap: 10px; margin-bottom: 10px; }
.wp-wrk-card-summary { flex: 1; min-width: 0; background: #fff; border-radius: 10px; padding: 12px 14px; border: 1px solid #ebeef5; transition: box-shadow 0.2s; position: relative; overflow: hidden; }
.wp-wrk-card-summary::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; }
.wp-wrk-card-summary:nth-child(1)::before { background: linear-gradient(180deg, #409eff, #66b1ff); }
.wp-wrk-card-summary:nth-child(2)::before { background: linear-gradient(180deg, #e6a23c, #f0c78a); }
.wp-wrk-card-summary:nth-child(3)::before { background: linear-gradient(180deg, #67c23a, #95d475); }
.wp-wrk-card-summary:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.wp-wrk-card-label { font-size: 11px; color: #909399; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
.wp-wrk-card-value { font-size: 14px; color: #303133; font-weight: 700; line-height: 1.5; word-break: break-all; }
.wp-criteria-row { font-size: 12px; margin-bottom: 10px; padding: 10px 14px; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.wp-criteria-row .wp-label { font-weight: 600; color: #606266; }
.wp-criteria-row .wp-value { line-height: 1.7; color: #606266; }
/* 客户退回提示 */
.wp-return-banner { background: linear-gradient(135deg, #fef0f0 0%, #fde2e2 100%); border: 1px solid #fbc4c4; border-radius: 8px; padding: 12px 16px; margin-bottom: 12px; box-shadow: 0 2px 8px rgba(245,108,108,0.08); }
.wp-return-banner-title { color: #f56c6c; font-weight: 700; font-size: 14px; margin-bottom: 8px; display: flex; align-items: center; }
.wp-return-banner-title i { margin-right: 6px; font-size: 16px; }
.wp-return-banner-body { display: flex; flex-wrap: wrap; gap: 6px 24px; font-size: 12px; color: #7a4a4a; }
/* 测试任务卡片 */
.wp-test-card { background: #fff; border-radius: 10px; padding: 16px 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.wp-test-grid { display: flex; flex-wrap: wrap; gap: 8px 24px; }
.wp-test-item { font-size: 13px; margin-bottom: 8px; min-width: 200px; }
.wp-test-full { width: 100%; min-width: 0; }
.wp-readonly-box { font-size: 13px; color: #606266; background: #f7f8fa; padding: 10px 12px; border-radius: 6px; line-height: 1.6; word-break: break-all; border: 1px solid #ebeef5; }
.wp-empty { text-align: center; padding: 32px; color: #909399; font-size: 13px; }
/* 时间线 */
.wp-timeline { padding: 4px 0; }
.wp-timeline-item { position: relative; padding-left: 22px; padding-bottom: 18px; border-left: 2px solid #e4e7ed; }
.wp-timeline-item:last-child { border-left-color: transparent; padding-bottom: 0; }
.wp-timeline-dot { position: absolute; left: -6px; top: 2px; width: 10px; height: 10px; border-radius: 50%; background: #409eff; box-shadow: 0 0 0 3px rgba(64,158,255,0.15); }
.wp-timeline-dot.is-review { background: #e6a23c; box-shadow: 0 0 0 3px rgba(230,162,60,0.15); }
.wp-timeline-content { }
.wp-timeline-header { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 4px; }
.wp-timeline-round { font-weight: 700; font-size: 13px; color: #303133; }
.wp-timeline-user { font-size: 12px; color: #606266; }
.wp-timeline-time { font-size: 12px; color: #c0c4cc; margin-left: auto; }
.wp-timeline-body { font-size: 13px; color: #606266; line-height: 1.6; }
@media (max-width: 600px) {
  .wp-wrk-summary { flex-direction: column; }
  .wp-test-item { min-width: 100%; }
  .wp-info-bar { gap: 8px; }
}
</style>

<!-- 非 scoped：append-to-body 弹窗样式需全局生效 -->
<style>
.wp-eval-dialog .el-dialog__body,
.wp-accept-dialog .el-dialog__body { padding: 16px 20px; max-height: calc(100vh - 180px); overflow-y: auto; }
.wp-return-dialog .el-dialog__body,
.wp-test-submit-dialog .el-dialog__body,
.wp-test-review-dialog .el-dialog__body,
.wp-test-history-dialog .el-dialog__body { padding: 16px 20px; }
.wp-dialog-section-title { font-weight: 700; font-size: 14px; color: #1a1a2e; margin-bottom: 14px; padding-bottom: 8px; border-bottom: 2px solid #f0f0f0; }
.wp-dialog-section-title:not(:first-child) { margin-top: 18px; }
.wp-dialog-footer { display: flex; justify-content: flex-end; gap: 8px; }
@media (max-width: 768px) {
  .wp-eval-dialog,
  .wp-accept-dialog,
  .wp-return-dialog,
  .wp-test-submit-dialog,
  .wp-test-review-dialog,
  .wp-test-history-dialog { width: 95% !important; margin: 0 auto !important; }
}
</style>

<template>
  <div v-if="visible" class="task-workflow-panel">
    <div class="wp-header">
      <span class="wp-title">任务工作流</span>
      <el-tag v-if="data.legacy" size="mini" type="info">旧任务（未启用 P0 工作流）</el-tag>
      <el-tag v-else size="mini" type="success">{{ data.main_status }}</el-tag>
      <el-tag v-if="data.aux_status" size="mini" type="warning">{{ data.aux_status }}</el-tag>
      <span v-if="data.version" class="wp-version">v{{ data.version }}</span>
    </div>

    <template v-if="!data.legacy">
      <!-- 主状态进度条 -->
      <div class="wp-steps">
        <span
          v-for="(s, i) in statusOrder"
          :key="s"
          class="wp-step"
          :class="{ 'is-active': s === data.main_status, 'is-done': stepIndex > i }">
          {{ s }}
        </span>
      </div>

      <!-- W/R/K 展示 -->
      <div class="wp-wrk">
        <div class="wp-wrk-item">
          <span class="wp-label">工作量</span>
          <span class="wp-value">{{ wrkText('W', data.init_w) }} → {{ wrkText('W', data.final_w) }}</span>
        </div>
        <div class="wp-wrk-item">
          <span class="wp-label">风险</span>
          <span class="wp-value">{{ wrkText('R', data.init_r) }} → {{ wrkText('R', data.final_r) }}</span>
        </div>
        <div class="wp-wrk-item">
          <span class="wp-label">专业成熟度</span>
          <span class="wp-value">{{ wrkText('K', data.init_k) }} → {{ wrkText('K', data.final_k) }}</span>
        </div>
        <div v-if="data.acceptance_criteria" class="wp-wrk-item">
          <span class="wp-label">验收标准</span>
          <span class="wp-value">{{ data.acceptance_criteria }}</span>
        </div>
      </div>

      <!-- 状态允许的动作 -->
      <div class="wp-actions">
        <el-button v-if="data.main_status === '待评估'" size="mini" type="primary" :loading="acting" @click="simpleAct('evaluate')">评估</el-button>
        <el-button v-if="data.main_status === '待处理'" size="mini" type="primary" :loading="acting" @click="startProcess">开始处理</el-button>
        <el-button v-if="data.main_status === '处理中'" size="mini" type="primary" :loading="acting" @click="openSubmitAcceptance">提交验收</el-button>
        <el-button v-if="data.main_status === '待内部验收'" size="mini" type="success" :loading="acting" @click="simpleAct('acceptancePass')">验收通过</el-button>
        <el-button v-if="data.main_status === '待内部验收'" size="mini" :loading="acting" @click="openReturn('acceptanceReturn')">验收退回</el-button>
        <el-button v-if="data.main_status === '待发布'" size="mini" type="primary" :loading="acting" @click="confirmRelease">确认发布</el-button>
        <el-button v-if="data.main_status === '待客户验证'" size="mini" type="success" :loading="acting" @click="simpleAct('customerConfirm')">客户确认</el-button>
        <el-button v-if="data.main_status === '待客户验证'" size="mini" :loading="acting" @click="openReturn('customerReturn')">客户退回</el-button>
        <el-button v-if="data.need_customer_verify === 0 && (data.main_status === '待发布')" size="mini" type="success" :loading="acting" @click="simpleAct('completeTask')">直接完成</el-button>
        <el-button size="mini" @click="testDialogVisible = true">测试任务</el-button>
      </div>

      <!-- 开始处理：填写初始 W/R/K -->
      <div v-if="showInitWrk" class="wp-form">
        <div class="wp-form-row" v-for="item in wrkSelectItems('init')" :key="item.field">
          <span class="wp-label">{{ item.label }}</span>
          <el-select v-model="initForm[item.field]" size="mini" :placeholder="item.ph" style="width:220px">
            <el-option v-for="v in item.options" :key="v" :label="v + ' ' + (dict[item.type][v]||'')" :value="v" />
          </el-select>
        </div>
        <el-button size="mini" type="primary" :loading="acting" @click="confirmStart">确认开始处理</el-button>
        <el-button size="mini" @click="showInitWrk = false">取消</el-button>
      </div>

      <!-- 提交验收：最终 W/R/K + 验收标准 + 验收人 -->
      <div v-if="showAcceptance" class="wp-form">
        <div class="wp-form-row" v-for="item in wrkSelectItems('final')" :key="item.field">
          <span class="wp-label">{{ item.label }}</span>
          <el-select v-model="acceptanceForm[item.field]" size="mini" :placeholder="item.ph" style="width:220px">
            <el-option v-for="v in item.options" :key="v" :label="v + ' ' + (dict[item.type][v]||'')" :value="v" />
          </el-select>
        </div>
        <div class="wp-form-row">
          <span class="wp-label">验收标准</span>
          <el-input v-model="acceptanceForm.acceptance_criteria" type="textarea" :rows="2" size="mini" placeholder="验收标准（必填）" style="width:300px" />
        </div>
        <div class="wp-form-row">
          <span class="wp-label">验收人</span>
          <el-select v-model="acceptanceForm.acceptance_user_id" size="mini" filterable placeholder="选择验收人" style="width:220px">
            <el-option v-for="u in userList" :key="u.id" :label="u.realname" :value="Number(u.id)" />
          </el-select>
        </div>
        <el-button size="mini" type="primary" :loading="acting" @click="confirmAcceptance">确认提交验收</el-button>
        <el-button size="mini" @click="showAcceptance = false">取消</el-button>
      </div>

      <!-- 退回原因 -->
      <div v-if="showReturn" class="wp-form">
        <el-input v-model="returnReason" size="mini" placeholder="退回原因（必填）" style="width:300px" />
        <el-button size="mini" type="primary" :loading="acting" @click="doReturn">确认退回</el-button>
        <el-button size="mini" @click="showReturn = false">取消</el-button>
      </div>

      <!-- 测试任务信息 -->
      <div v-if="data.is_test_task && data.test_ext" class="wp-test">
        <div class="wp-test-row">
          <span class="wp-label">测试评定</span>
          <span class="wp-value">{{ reviewText(data.test_ext.review_status) }}</span>
          <span v-if="data.test_ext.current_round > 0" class="wp-value">第 {{ data.test_ext.current_round }} 轮</span>
        </div>
      </div>
    </template>

    <test-task-dialog :visible.sync="testDialogVisible" :origin-task-id="Number(taskId)" @refresh="fetch" />
  </div>
</template>

<script>
import {
  workflowReadAPI,
  wrkDictionaryAPI,
  evaluateTaskAPI,
  startProcessTaskAPI,
  submitAcceptanceAPI,
  acceptancePassAPI,
  acceptanceReturnAPI,
  confirmReleaseAPI,
  customerConfirmAPI,
  customerReturnAPI,
  completeTaskAPI
} from '@/api/task/workflow'
import { workQueryMemberListAPI } from '@/api/pm/task'
import TestTaskDialog from './TestTaskDialog'

const STATUS_ORDER = ['待评估', '待处理', '处理中', '待内部验收', '待发布', '待客户验证', '已完成']
const REVIEW_MAP = { pending: '待评定', compliant: '符合要求', non_compliant: '不符合要求' }

export default {
  name: 'TaskWorkflowPanel',
  components: { TestTaskDialog },
  props: {
    taskId: [Number, String]
  },
  data() {
    return {
      visible: false,
      acting: false,
      data: {},
      dict: { W: {}, R: {}, K: {} },
      userList: [],
      statusOrder: STATUS_ORDER,
      showInitWrk: false,
      showAcceptance: false,
      showReturn: false,
      returnAction: '',
      returnReason: '',
      initForm: { init_w: '', init_r: '', init_k: '' },
      acceptanceForm: { final_w: '', final_r: '', final_k: '', acceptance_criteria: '', acceptance_user_id: '' },
      testDialogVisible: false
    }
  },
  computed: {
    stepIndex() {
      return Math.max(0, STATUS_ORDER.indexOf(this.data.main_status))
    }
  },
  watch: {
    taskId(val) {
      if (val) this.fetch()
      else this.visible = false
    }
  },
  methods: {
    async fetch() {
      if (!this.taskId) return
      try {
        const res = await workflowReadAPI({ task_id: Number(this.taskId) })
        this.data = res.data || res
        this.visible = true
        if (!this.dict.W || !this.dict.W.W1) this.loadDict()
        if (!this.userList.length) this.loadUsers()
      } catch (e) {
        this.visible = false
      }
    },
    async loadDict() {
      try {
        const res = await wrkDictionaryAPI()
        this.dict = (res.data || res) || this.dict
      } catch (e) {}
    },
    async loadUsers() {
      try {
        const res = await workQueryMemberListAPI()
        this.userList = res.data || []
      } catch (e) {}
    },
    wrkText(type, val) {
      if (!val) return '—'
      const def = this.dict[type] && this.dict[type][val]
      return def ? val + '(' + def + ')' : val
    },
    reviewText(s) { return REVIEW_MAP[s] || s },
    wrkSelectItems(prefix) {
      var typeMap = { w: 'W', r: 'R', k: 'K' }
      var labelMap = { w: '工作量', r: '风险', k: '专业成熟度' }
      var opts = { W: ['W1','W2','W3','W4','W5'], R: ['R1','R2','R3','R4','R5'], K: ['K1','K2','K3','K4'] }
      return ['w','r','k'].map(function(letter) {
        var field = prefix + '_' + letter
        return { field: field, label: labelMap[letter], type: typeMap[letter], options: opts[typeMap[letter]], ph: '选择' + labelMap[letter] }
      })
    },
    async simpleAct(action) {
      var apiMap = {
        evaluate: evaluateTaskAPI,
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
        this.fetch()
        this.$emit('refresh')
      } catch (e) {} finally { this.acting = false }
    },
    startProcess() {
      if (this.data.init_w && this.data.init_r && this.data.init_k) {
        this.confirmStart()
      } else {
        this.initForm = { init_w: '', init_r: '', init_k: '' }
        this.showInitWrk = true
      }
    },
    async confirmStart() {
      if (!this.data.init_w || !this.data.init_r || !this.data.init_k) {
        if (!this.initForm.init_w || !this.initForm.init_r || !this.initForm.init_k) {
          this.$message.warning('请选择完整的初始 W/R/K'); return
        }
      }
      this.acting = true
      try {
        var payload = { task_id: Number(this.taskId), version: Number(this.data.version) }
        if (!this.data.init_w) Object.assign(payload, this.initForm)
        await startProcessTaskAPI(payload)
        this.$message.success('已开始处理')
        this.showInitWrk = false
        this.fetch()
        this.$emit('refresh')
      } catch (e) {} finally { this.acting = false }
    },
    openSubmitAcceptance() {
      this.acceptanceForm = { final_w: this.data.final_w || '', final_r: this.data.final_r || '', final_k: this.data.final_k || '', acceptance_criteria: this.data.acceptance_criteria || '', acceptance_user_id: this.data.acceptance_user_id ? Number(this.data.acceptance_user_id) : '' }
      this.showAcceptance = true
    },
    async confirmAcceptance() {
      if (!this.acceptanceForm.final_w || !this.acceptanceForm.final_r || !this.acceptanceForm.final_k) { this.$message.warning('请选择完整最终 W/R/K'); return }
      if (!this.acceptanceForm.acceptance_criteria) { this.$message.warning('请填写验收标准'); return }
      if (!this.acceptanceForm.acceptance_user_id) { this.$message.warning('请选择验收人'); return }
      this.acting = true
      try {
        await submitAcceptanceAPI(Object.assign({ task_id: Number(this.taskId), version: Number(this.data.version) }, this.acceptanceForm))
        this.$message.success('已提交验收')
        this.showAcceptance = false
        this.fetch()
        this.$emit('refresh')
      } catch (e) {} finally { this.acting = false }
    },
    openReturn(action) {
      this.returnAction = action
      this.returnReason = ''
      this.showReturn = true
    },
    async doReturn() {
      if (!this.returnReason) { this.$message.warning('请填写退回原因'); return }
      var fn = this.returnAction === 'acceptanceReturn' ? acceptanceReturnAPI : customerReturnAPI
      this.acting = true
      try {
        await fn({ task_id: Number(this.taskId), version: Number(this.data.version), reason: this.returnReason })
        this.$message.success('已退回')
        this.showReturn = false
        this.fetch()
        this.$emit('refresh')
      } catch (e) {} finally { this.acting = false }
    },
    async confirmRelease() {
      this.acting = true
      try {
        await confirmReleaseAPI({ task_id: Number(this.taskId), version: Number(this.data.version) })
        this.$message.success('已确认发布')
        this.fetch()
        this.$emit('refresh')
      } catch (e) {} finally { this.acting = false }
    }
  }
}
</script>

<style scoped>
.task-workflow-panel { padding: 10px 12px; background: #f7f8fa; border-radius: 4px; margin-bottom: 10px; }
.wp-header { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.wp-title { font-weight: 600; font-size: 13px; color: #333; }
.wp-version { font-size: 11px; color: #c0c4cc; margin-left: auto; }
.wp-steps { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 8px; }
.wp-step { font-size: 11px; padding: 2px 6px; border-radius: 3px; background: #e4e7ed; color: #909399; }
.wp-step.is-active { background: #409eff; color: #fff; }
.wp-step.is-done { background: #67c23a; color: #fff; }
.wp-wrk { margin-bottom: 8px; }
.wp-wrk-item { font-size: 12px; margin-bottom: 4px; }
.wp-label { color: #909399; margin-right: 6px; }
.wp-value { color: #333; }
.wp-actions { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
.wp-form { background: #fff; padding: 8px; border-radius: 4px; margin-bottom: 8px; }
.wp-form-row { margin-bottom: 6px; }
.wp-test { border-top: 1px solid #ebeef5; padding-top: 8px; }
.wp-test-row { font-size: 12px; margin-bottom: 4px; }
</style>

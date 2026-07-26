<template>
  <el-dialog
    :visible.sync="dialogVisible"
    :title="title"
    width="720px"
    append-to-body
    @open="handleOpen">
    <!-- 发起测试表单 -->
    <div class="tt-initiate">
      <el-button size="mini" type="primary" @click="showInitiate = !showInitiate">
        {{ showInitiate ? '收起发起' : '发起测试' }}
      </el-button>
      <div v-if="showInitiate" class="tt-form">
        <div class="tt-form-row">
          <span class="tt-label">测试人员</span>
          <el-select v-model="initForm.testers" multiple size="mini" placeholder="选择测试人员" style="width:260px">
            <el-option v-for="u in userList" :key="u.id" :label="u.realname" :value="Number(u.id)" />
          </el-select>
        </div>
        <div class="tt-form-row">
          <span class="tt-label">评定人</span>
          <el-select v-model="initForm.reviewer_user_id" size="mini" filterable placeholder="指定评定人（不能是测试执行人）" style="width:260px">
            <el-option v-for="u in userList" :key="u.id" :label="u.realname" :value="Number(u.id)" />
          </el-select>
        </div>
        <div class="tt-form-row">
          <span class="tt-label">测试类型</span>
          <el-select v-model="initForm.test_type" size="mini" placeholder="选择测试类型" style="width:220px">
            <el-option label="开发自测" value="dev_self" />
            <el-option label="非开发人员业务测试" value="business" />
          </el-select>
        </div>
        <div class="tt-form-row">
          <span class="tt-label">测试范围</span>
          <el-input v-model="initForm.test_scope" type="textarea" :rows="2" size="mini" placeholder="测试范围和完成标准" style="width:380px" />
        </div>
        <div class="tt-form-row">
          <span class="tt-label">截止时间</span>
          <el-date-picker v-model="initForm.deadline" type="datetime" size="mini" placeholder="截止时间" style="width:200px" />
        </div>
        <div class="tt-form-row">
          <el-checkbox v-model="initForm.is_required">必需测试</el-checkbox>
        </div>
        <el-button size="mini" type="primary" :loading="acting" @click="doInitiate">确认发起</el-button>
      </div>
    </div>

    <!-- 测试任务列表 -->
    <el-table :data="list" size="mini" style="width:100%; margin-top:12px">
      <el-table-column prop="task_name" label="测试任务" min-width="140" />
      <el-table-column label="提交状态" width="90">
        <template slot-scope="scope">{{ submitText(scope.row.submit_status) }}</template>
      </el-table-column>
      <el-table-column label="评定状态" width="100">
        <template slot-scope="scope">
          <el-tag size="mini" :type="reviewTagType(scope.row.review_status)">{{ reviewText(scope.row.review_status) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="current_round" label="轮次" width="55" />
      <el-table-column label="必需" width="55">
        <template slot-scope="scope">{{ scope.row.is_required ? '是' : '否' }}</template>
      </el-table-column>
      <el-table-column label="操作" width="160">
        <template slot-scope="scope">
          <el-button v-if="canSubmit(scope.row)" size="mini" @click="openSubmit(scope.row)">提交</el-button>
          <el-button v-if="canReview(scope.row)" size="mini" type="warning" @click="openReview(scope.row)">评定</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 提交弹窗 -->
    <el-dialog :visible.sync="submitVisible" title="提交测试结果" width="500px" append-to-body>
      <el-input v-model="submitForm.result" type="textarea" :rows="3" placeholder="测试结果" style="margin-bottom:10px" />
      <el-input v-model="submitForm.issues" type="textarea" :rows="2" placeholder="发现问题" />
      <span slot="footer">
        <el-button size="mini" @click="submitVisible = false">取消</el-button>
        <el-button size="mini" type="primary" :loading="acting" @click="doSubmit">提交</el-button>
      </span>
    </el-dialog>

    <!-- 评定弹窗 -->
    <el-dialog :visible.sync="reviewVisible" title="评定测试" width="500px" append-to-body>
      <div style="margin-bottom:10px">
        <el-radio-group v-model="reviewForm.verdict">
          <el-radio label="compliant">符合要求</el-radio>
          <el-radio label="non_compliant">不符合要求</el-radio>
        </el-radio-group>
      </div>
      <el-input v-if="reviewForm.verdict === 'non_compliant'" v-model="reviewForm.return_reason" type="textarea" :rows="2" placeholder="退回原因（必填）" style="margin-bottom:8px" />
      <el-input v-if="reviewForm.verdict === 'non_compliant'" v-model="reviewForm.return_requirements" type="textarea" :rows="2" placeholder="缺失内容/补充要求" />
      <span slot="footer">
        <el-button size="mini" @click="reviewVisible = false">取消</el-button>
        <el-button size="mini" type="primary" :loading="acting" @click="doReview">确认评定</el-button>
      </span>
    </el-dialog>
  </el-dialog>
</template>

<script>
import { initiateTestAPI, submitTestAPI, reviewTestAPI, testListAPI } from '@/api/task/workflow'
import { workQueryMemberListAPI } from '@/api/pm/task'

const SUBMIT_MAP = { not_submitted: '未提交', submitted: '已提交' }
const REVIEW_MAP = { pending: '待评定', compliant: '符合要求', non_compliant: '不符合要求' }

export default {
  name: 'TestTaskDialog',
  props: {
    visible: Boolean,
    originTaskId: [Number, String]
  },
  data() {
    return {
      showInitiate: false,
      acting: false,
      list: [],
      userList: [],
      currentUserId: 0,
      initForm: { testers: [], reviewer_user_id: '', test_type: '', test_scope: '', deadline: '', is_required: true },
      submitVisible: false,
      submitForm: { task_id: 0, version: 0, result: '', issues: '' },
      reviewVisible: false,
      reviewForm: { task_id: 0, version: 0, verdict: 'compliant', return_reason: '', return_requirements: '' }
    }
  },
  computed: {
    dialogVisible: {
      get() { return this.visible },
      set(val) { this.$emit('update:visible', val) }
    },
    title() { return '测试任务管理（原任务 #' + this.originTaskId + '）' }
  },
  methods: {
    submitText(s) { return SUBMIT_MAP[s] || s },
    reviewText(s) { return REVIEW_MAP[s] || s },
    reviewTagType(s) {
      if (s === 'compliant') return 'success'
      if (s === 'non_compliant') return 'danger'
      return 'info'
    },
    // 只有指定测试人员可提交，且未符合要求
    canSubmit(row) {
      return Number(row.tester_user_id) === Number(this.currentUserId) && row.review_status !== 'compliant'
    },
    // 只有保存的评定人可评定，且测试已提交、当前待评定
    canReview(row) {
      return Number(row.reviewer_user_id) === Number(this.currentUserId)
        && row.submit_status === 'submitted'
        && row.review_status === 'pending'
    },
    handleOpen() {
      this.currentUserId = this.$store && this.$store.state.user && this.$store.state.user.userInfo
        ? Number(this.$store.state.user.userInfo.id) : 0
      this.loadUsers()
      this.fetchList()
    },
    async fetchList() {
      if (!this.originTaskId) return
      try {
        const res = await testListAPI({ origin_task_id: Number(this.originTaskId) })
        const data = res.data || res
        this.list = data.list || []
      } catch (e) {}
    },
    async loadUsers() {
      try {
        const res = await workQueryMemberListAPI()
        this.userList = res.data || []
      } catch (e) {}
    },
    async doInitiate() {
      if (!this.initForm.testers.length) { this.$message.warning('请选择测试人员'); return }
      if (!this.initForm.reviewer_user_id) { this.$message.warning('请指定评定人'); return }
      // 评定人不能是测试执行人
      if (this.initForm.testers.indexOf(Number(this.initForm.reviewer_user_id)) !== -1) {
        this.$message.warning('评定人不能同时是测试执行人'); return
      }
      this.acting = true
      try {
        await initiateTestAPI({
          origin_task_id: Number(this.originTaskId),
          request_id: 'req-' + Date.now(),
          reviewer_user_id: Number(this.initForm.reviewer_user_id),
          testers: this.initForm.testers.map(Number),
          test_type: this.initForm.test_type,
          test_scope: this.initForm.test_scope,
          completion_criteria: this.initForm.test_scope,
          deadline: this.initForm.deadline ? Math.floor(new Date(this.initForm.deadline).getTime() / 1000) : 0,
          is_required: this.initForm.is_required ? 1 : 0,
          source_type: 'task',
          source_id: Number(this.originTaskId)
        })
        this.$message.success('测试任务已生成')
        this.showInitiate = false
        this.fetchList()
        this.$emit('refresh')
      } catch (e) {} finally { this.acting = false }
    },
    openSubmit(row) {
      this.submitForm = { task_id: Number(row.task_id), version: Number(row.version), result: '', issues: '' }
      this.submitVisible = true
    },
    async doSubmit() {
      this.acting = true
      try {
        await submitTestAPI(this.submitForm)
        this.$message.success('已提交')
        this.submitVisible = false
        this.fetchList()
      } catch (e) {} finally { this.acting = false }
    },
    openReview(row) {
      this.reviewForm = { task_id: Number(row.task_id), version: Number(row.version), verdict: 'compliant', return_reason: '', return_requirements: '' }
      this.reviewVisible = true
    },
    async doReview() {
      if (this.reviewForm.verdict === 'non_compliant' && !this.reviewForm.return_reason) {
        this.$message.warning('不符合要求必须填写退回原因'); return
      }
      this.acting = true
      try {
        await reviewTestAPI(this.reviewForm)
        this.$message.success('评定完成')
        this.reviewVisible = false
        this.fetchList()
      } catch (e) {} finally { this.acting = false }
    }
  }
}
</script>

<style scoped>
.tt-initiate { margin-bottom: 8px; }
.tt-form { background: #f7f8fa; padding: 10px; border-radius: 4px; margin-top: 8px; }
.tt-form-row { margin-bottom: 8px; }
.tt-label { display: inline-block; width: 70px; color: #909399; font-size: 12px; }
</style>

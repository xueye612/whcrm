<template>
  <div v-if="visible" class="project-implementation-panel">
    <el-alert
      v-if="!detail.can_manage"
      type="info"
      :closable="false"
      title="你无该项目管理权限，仅可查看实施信息"
      show-icon/>
    <el-tabs v-model="activeTab" class="imp-tabs">
      <!-- ===== 实施档案 ===== -->
      <el-tab-pane label="实施档案" name="profile">
        <el-form :model="profileForm" label-width="110px" size="small" class="imp-form">
          <el-row :gutter="16">
            <el-col :span="8">
              <el-form-item label="项目类型">
                <el-select v-model="profileForm.project_type" :disabled="!canManage" clearable placeholder="选择项目类型" style="width:100%">
                  <el-option v-for="t in dict.project_types" :key="t" :label="t" :value="t"/>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="实施等级">
                <el-select v-model="profileForm.impl_level" :disabled="!canManage" clearable placeholder="选择实施等级" style="width:100%">
                  <el-option v-for="l in dict.impl_levels" :key="l" :label="l" :value="l"/>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="稳定期(天)">
                <el-input-number v-model="profileForm.stability_days" :disabled="!canManage" :min="0" controls-position="right" style="width:100%"/>
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="16">
            <el-col :span="6"><el-form-item label="计划开始"><el-date-picker v-model="profileForm.plan_start_time" :disabled="!canManage" type="date" value-format="yyyy-MM-dd" style="width:100%"/></el-form-item></el-col>
            <el-col :span="6"><el-form-item label="计划结束"><el-date-picker v-model="profileForm.plan_end_time" :disabled="!canManage" type="date" value-format="yyyy-MM-dd" style="width:100%"/></el-form-item></el-col>
            <el-col :span="6"><el-form-item label="实际开始"><el-date-picker v-model="profileForm.actual_start_time" :disabled="!canManage" type="date" value-format="yyyy-MM-dd" style="width:100%"/></el-form-item></el-col>
            <el-col :span="6"><el-form-item label="实际结束"><el-date-picker v-model="profileForm.actual_end_time" :disabled="!canManage" type="date" value-format="yyyy-MM-dd" style="width:100%"/></el-form-item></el-col>
          </el-row>
          <el-form-item label="风险/责任归因">
            <el-input v-model="profileForm.risk_note" :disabled="!canManage" type="textarea" :rows="2" maxlength="500"/>
          </el-form-item>
          <el-form-item label="验收结果">
            <el-select v-model="profileForm.acceptance_result" :disabled="!canManage || !detail.can_accept" clearable :placeholder="detail.can_accept ? '选择验收结果三档' : '需先完成至少一条里程碑'" style="width:280px">
              <el-option v-for="a in dict.acceptance_result" :key="a" :label="a" :value="a"/>
            </el-select>
            <el-button v-if="canManage" type="primary" :loading="acting" @click="saveProfile" style="margin-left:12px">保存档案</el-button>
          </el-form-item>
        </el-form>
      </el-tab-pane>

      <!-- ===== 里程碑 ===== -->
      <el-tab-pane :label="'里程碑(' + milestones.length + ')'" name="milestone">
        <div class="imp-toolbar">
          <el-button v-if="canManage" type="primary" size="small" icon="el-icon-plus" @click="openMilestone(null)">新增里程碑</el-button>
        </div>
        <el-table :data="milestones" size="small" border>
          <el-table-column label="类型" prop="milestone_type" width="110"/>
          <el-table-column label="名称" prop="name"/>
          <el-table-column label="计划时间" width="120"><template slot-scope="s">{{ s.row.plan_time | fmtDate }}</template></el-table-column>
          <el-table-column label="实际时间" width="120"><template slot-scope="s">{{ s.row.actual_time | fmtDate }}</template></el-table-column>
          <el-table-column label="状态" width="100"><template slot-scope="s"><el-tag :type="msTag(s.row.status)" size="mini">{{ s.row.status }}</el-tag></template></el-table-column>
          <el-table-column label="操作" width="140" v-if="canManage">
            <template slot-scope="s">
              <el-button type="text" @click="openMilestone(s.row)">编辑</el-button>
              <el-button type="text" style="color:#f56c6c" @click="delMilestone(s.row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- ===== 成员贡献 ===== -->
      <el-tab-pane :label="'成员贡献(' + contributions.length + ')'" name="contribution">
        <div class="imp-toolbar">
          <el-button v-if="canManage" type="primary" size="small" icon="el-icon-plus" @click="openContribution(null)">新增贡献</el-button>
        </div>
        <el-table :data="contributions" size="small" border>
          <el-table-column label="贡献人" width="100"><template slot-scope="s">{{ memberName(s.row.user_id) }}</template></el-table-column>
          <el-table-column label="贡献角色" prop="contribution_role"/>
          <el-table-column label="现场人日" prop="on_site_days" width="100"/>
          <el-table-column label="起止" width="220"><template slot-scope="s">{{ s.row.start_time | fmtDate }} ~ {{ s.row.end_time | fmtDate }}</template></el-table-column>
          <el-table-column label="证据/说明" prop="evidence_note"/>
          <el-table-column label="操作" width="140" v-if="canManage">
            <template slot-scope="s">
              <el-button type="text" @click="openContribution(s.row)">编辑</el-button>
              <el-button type="text" style="color:#f56c6c" @click="delContribution(s.row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- ===== 知识链接 ===== -->
      <el-tab-pane :label="'知识链接(' + knowledge.length + ')'" name="knowledge">
        <div class="imp-toolbar">
          <el-button v-if="canManage" type="primary" size="small" icon="el-icon-plus" @click="openKnowledge(null)">新增链接</el-button>
        </div>
        <el-table :data="knowledge" size="small" border>
          <el-table-column label="类型" prop="link_type" width="110"/>
          <el-table-column label="标题" prop="title"/>
          <el-table-column label="地址"><template slot-scope="s"><a v-if="s.row.url" :href="s.row.url" target="_blank" rel="noopener">{{ s.row.url }}</a></template></el-table-column>
          <el-table-column label="完整性" width="100"><template slot-scope="s"><el-tag size="mini" :type="compTag(s.row.completeness_status)">{{ s.row.completeness_status }}</el-tag></template></el-table-column>
          <el-table-column label="操作" width="140" v-if="canManage">
            <template slot-scope="s">
              <el-button type="text" @click="openKnowledge(s.row)">编辑</el-button>
              <el-button type="text" style="color:#f56c6c" @click="delKnowledge(s.row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- 里程碑弹窗 -->
    <el-dialog :title="milestoneForm.milestone_id ? '编辑里程碑' : '新增里程碑'" :visible.sync="msDialog" width="520px" append-to-body>
      <el-form :model="milestoneForm" label-width="100px" size="small">
        <el-form-item label="类型"><el-select v-model="milestoneForm.milestone_type" style="width:100%"><el-option v-for="t in dict.milestone_types" :key="t" :label="t" :value="t"/></el-select></el-form-item>
        <el-form-item label="名称"><el-input v-model="milestoneForm.name"/></el-form-item>
        <el-form-item label="计划时间"><el-date-picker v-model="milestoneForm.plan_time" type="date" value-format="yyyy-MM-dd" style="width:100%"/></el-form-item>
        <el-form-item label="实际时间"><el-date-picker v-model="milestoneForm.actual_time" type="date" value-format="yyyy-MM-dd" style="width:100%"/></el-form-item>
        <el-form-item label="状态"><el-select v-model="milestoneForm.status" style="width:100%"><el-option v-for="s in dict.milestone_status" :key="s" :label="s" :value="s"/></el-select></el-form-item>
        <el-form-item label="排序"><el-input-number v-model="milestoneForm.sort" :min="0" controls-position="right"/></el-form-item>
        <el-form-item label="证据/说明"><el-input v-model="milestoneForm.evidence_note" type="textarea" :rows="2"/></el-form-item>
      </el-form>
      <span slot="footer"><el-button @click="msDialog=false">取消</el-button><el-button type="primary" :loading="acting" @click="saveMilestone">保存</el-button></span>
    </el-dialog>

    <!-- 贡献弹窗 -->
    <el-dialog :title="contributionForm.contribution_id ? '编辑贡献' : '新增贡献'" :visible.sync="ctDialog" width="520px" append-to-body>
      <el-form :model="contributionForm" label-width="100px" size="small">
        <el-form-item label="贡献人">
          <el-select v-model="contributionForm.user_id" filterable placeholder="选择项目成员" style="width:100%">
            <el-option v-for="m in members" :key="m.user_id" :label="m.realname" :value="m.user_id"/>
          </el-select>
        </el-form-item>
        <el-form-item label="贡献角色"><el-input v-model="contributionForm.contribution_role" placeholder="如：项目经理/开发/实施"/></el-form-item>
        <el-form-item label="现场人日"><el-input-number v-model="contributionForm.on_site_days" :min="0" :step="0.5" controls-position="right"/></el-form-item>
        <el-form-item label="开始"><el-date-picker v-model="contributionForm.start_time" type="date" value-format="yyyy-MM-dd" style="width:100%"/></el-form-item>
        <el-form-item label="结束"><el-date-picker v-model="contributionForm.end_time" type="date" value-format="yyyy-MM-dd" style="width:100%"/></el-form-item>
        <el-form-item label="证据/说明"><el-input v-model="contributionForm.evidence_note" type="textarea" :rows="2"/></el-form-item>
      </el-form>
      <span slot="footer"><el-button @click="ctDialog=false">取消</el-button><el-button type="primary" :loading="acting" @click="saveContribution">保存</el-button></span>
    </el-dialog>

    <!-- 知识链接弹窗 -->
    <el-dialog :title="knowledgeForm.link_id ? '编辑链接' : '新增链接'" :visible.sync="klDialog" width="520px" append-to-body>
      <el-form :model="knowledgeForm" label-width="100px" size="small">
        <el-form-item label="类型"><el-select v-model="knowledgeForm.link_type" style="width:100%"><el-option v-for="t in dict.knowledge_types" :key="t" :label="t" :value="t"/></el-select></el-form-item>
        <el-form-item label="标题"><el-input v-model="knowledgeForm.title"/></el-form-item>
        <el-form-item label="地址"><el-input v-model="knowledgeForm.url" placeholder="https://..."/></el-form-item>
        <el-form-item label="维护人ID"><el-input-number v-model="knowledgeForm.owner_user_id" :min="0" controls-position="right"/></el-form-item>
        <el-form-item label="完整性"><el-select v-model="knowledgeForm.completeness_status" style="width:100%"><el-option v-for="c in dict.completeness" :key="c" :label="c" :value="c"/></el-select></el-form-item>
        <el-form-item label="排序"><el-input-number v-model="knowledgeForm.sort" :min="0" controls-position="right"/></el-form-item>
      </el-form>
      <span slot="footer"><el-button @click="klDialog=false">取消</el-button><el-button type="primary" :loading="acting" @click="saveKnowledge">保存</el-button></span>
    </el-dialog>
  </div>
</template>

<script>
import {
  implementationReadAPI,
  profileUpdateAPI,
  milestoneSaveAPI,
  milestoneDeleteAPI,
  contributionSaveAPI,
  contributionDeleteAPI,
  knowledgeSaveAPI,
  knowledgeDeleteAPI
} from '@/api/pm/implementation'
import { workWorkOwnerListAPI } from '@/api/pm/project'

const EMPTY_DICT = {
  project_types: [], impl_levels: [], milestone_types: [], milestone_status: [],
  acceptance_result: [], knowledge_types: [], completeness: []
}

export default {
  name: 'ProjectImplementationPanel',
  props: {
    workId: [Number, String]
  },
  data() {
    return {
      visible: false,
      acting: false,
      activeTab: 'profile',
      detail: { can_manage: false, can_accept: false },
      dict: Object.assign({}, EMPTY_DICT),
      profileForm: {},
      milestones: [],
      contributions: [],
      knowledge: [],
      members: [],
      msDialog: false,
      ctDialog: false,
      klDialog: false,
      milestoneForm: {},
      contributionForm: {},
      knowledgeForm: {}
    }
  },
  computed: {
    canManage() { return !!(this.detail && this.detail.can_manage) }
  },
  watch: {
    workId: {
      handler(val) { if (val) this.fetch(); else this.visible = false },
      immediate: true
    }
  },
  methods: {
    async fetch() {
      try {
        const res = await implementationReadAPI({ work_id: Number(this.workId) })
        const d = res.data || res
        this.detail = d
        this.dict = d.dictionary || Object.assign({}, EMPTY_DICT)
        const p = d.profile || {}
        this.profileForm = {
          project_type: p.project_type || '',
          impl_level: p.impl_level || '',
          stability_days: Number(p.stability_days || 0),
          plan_start_time: p.plan_start_time ? this.fmt(p.plan_start_time) : '',
          plan_end_time: p.plan_end_time ? this.fmt(p.plan_end_time) : '',
          actual_start_time: p.actual_start_time ? this.fmt(p.actual_start_time) : '',
          actual_end_time: p.actual_end_time ? this.fmt(p.actual_end_time) : '',
          risk_note: p.risk_note || '',
          acceptance_result: p.acceptance_result || '',
          version: p.version || 0
        }
        this.milestones = d.milestones || []
        this.contributions = d.contributions || []
        this.knowledge = d.knowledge || []
        this.visible = true
        // 复用项目成员选择器数据
        try {
          const mr = await workWorkOwnerListAPI({ work_id: Number(this.workId) })
          this.members = (mr.data || mr) || []
        } catch (e) { this.members = [] }
      } catch (e) {
        this.visible = false
      }
    },
    fmt(ts) {
      if (!ts) return ''
      const dt = new Date(Number(ts) * 1000)
      const m = String(dt.getMonth() + 1).padStart(2, '0')
      const d = String(dt.getDate()).padStart(2, '0')
      return dt.getFullYear() + '-' + m + '-' + d
    },
    memberName(uid) {
      const m = this.members.find(x => Number(x.user_id) === Number(uid))
      return m ? m.realname : uid
    },
    msTag(s) { return { 未开始: 'info', 进行中: '', 已完成: 'success', 已延期: 'warning' }[s] || 'info' },
    compTag(c) { return { 完整: 'success', 待补充: 'warning', 缺失: 'danger' }[c] || 'info' },
    async saveProfile() {
      this.acting = true
      try {
        await profileUpdateAPI(Object.assign({ work_id: Number(this.workId) }, this.profileForm))
        this.$message.success('实施档案已保存')
        this.fetch()
        this.$emit('refresh')
      } catch (e) { /* request 拦截器已提示 */ } finally { this.acting = false }
    },
    openMilestone(row) {
      this.milestoneForm = row ? Object.assign({}, row, {
        plan_time: row.plan_time ? this.fmt(row.plan_time) : '',
        actual_time: row.actual_time ? this.fmt(row.actual_time) : ''
      }) : { milestone_type: this.dict.milestone_types[0] || '', name: '', plan_time: '', actual_time: '', status: '未开始', sort: this.milestones.length, evidence_note: '' }
      this.msDialog = true
    },
    async saveMilestone() {
      this.acting = true
      try {
        await milestoneSaveAPI(Object.assign({ work_id: Number(this.workId) }, this.milestoneForm))
        this.msDialog = false; this.$message.success('已保存'); this.fetch(); this.$emit('refresh')
      } catch (e) {} finally { this.acting = false }
    },
    delMilestone(row) {
      this.$confirm('确认删除该里程碑？', '提示', { type: 'warning' }).then(async() => {
        await milestoneDeleteAPI({ work_id: Number(this.workId), milestone_id: row.milestone_id })
        this.$message.success('已删除'); this.fetch()
      }).catch(() => {})
    },
    openContribution(row) {
      this.contributionForm = row ? Object.assign({}, row, {
        start_time: row.start_time ? this.fmt(row.start_time) : '',
        end_time: row.end_time ? this.fmt(row.end_time) : ''
      }) : { user_id: '', contribution_role: '', on_site_days: 0, start_time: '', end_time: '', evidence_note: '' }
      this.ctDialog = true
    },
    async saveContribution() {
      this.acting = true
      try {
        await contributionSaveAPI(Object.assign({ work_id: Number(this.workId) }, this.contributionForm))
        this.ctDialog = false; this.$message.success('已保存'); this.fetch()
      } catch (e) {} finally { this.acting = false }
    },
    delContribution(row) {
      this.$confirm('确认删除该贡献记录？', '提示', { type: 'warning' }).then(async() => {
        await contributionDeleteAPI({ work_id: Number(this.workId), contribution_id: row.contribution_id })
        this.$message.success('已删除'); this.fetch()
      }).catch(() => {})
    },
    openKnowledge(row) {
      this.knowledgeForm = row ? Object.assign({}, row) : { link_type: this.dict.knowledge_types[0] || '', title: '', url: '', owner_user_id: '', completeness_status: '待补充', sort: this.knowledge.length }
      this.klDialog = true
    },
    async saveKnowledge() {
      this.acting = true
      try {
        await knowledgeSaveAPI(Object.assign({ work_id: Number(this.workId) }, this.knowledgeForm))
        this.klDialog = false; this.$message.success('已保存'); this.fetch()
      } catch (e) {} finally { this.acting = false }
    },
    delKnowledge(row) {
      this.$confirm('确认删除该知识链接？', '提示', { type: 'warning' }).then(async() => {
        await knowledgeDeleteAPI({ work_id: Number(this.workId), link_id: row.link_id })
        this.$message.success('已删除'); this.fetch()
      }).catch(() => {})
    }
  },
  filters: {
    fmtDate(v) {
      if (!v) return ''
      const dt = new Date(Number(v) * 1000)
      const m = String(dt.getMonth() + 1).padStart(2, '0')
      const d = String(dt.getDate()).padStart(2, '0')
      return dt.getFullYear() + '-' + m + '-' + d
    }
  }
}
</script>

<style scoped>
.project-implementation-panel { padding: 12px 0; }
.imp-tabs { margin-top: 8px; }
.imp-form { max-width: 900px; }
.imp-toolbar { margin-bottom: 10px; }
</style>

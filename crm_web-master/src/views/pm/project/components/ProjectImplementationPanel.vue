<template>
  <div class="project-implementation-panel">
    <!-- loading：只显示加载态，不显示只读提示或空表单 -->
    <div v-loading="true" v-if="loadStatus === 'loading'" class="imp-loading"/>

    <!-- error：显示错误信息与重试 -->
    <div v-if="loadStatus === 'error'" class="imp-state imp-state-error">
      <span>{{ loadError }}</span>
      <el-button type="primary" size="mini" @click="fetch">重试</el-button>
    </div>

    <!-- ok：读取成功（含无档案的首次建档可编辑状态）才展示内容 -->
    <el-alert
      v-if="loadStatus === 'ok' && !canManage"
      :closable="false"
      type="info"
      title="你无该项目管理权限，仅可查看实施信息"
      show-icon/>
    <el-tabs v-if="loadStatus === 'ok'" v-model="activeTab" class="imp-tabs">
      <!-- ===== 实施档案 ===== -->
      <el-tab-pane label="实施档案" name="profile">
        <el-form ref="profileForm" :model="profileForm" :rules="profileRules" label-width="110px" size="small" class="imp-form">

          <!-- 卡片1：基本信息 -->
          <div class="imp-card">
            <div class="imp-card-title">基本信息</div>
            <el-row :gutter="20">
              <el-col :xs="24" :sm="12" :lg="8">
                <el-form-item label="项目类型" prop="project_type">
                  <el-select v-model="profileForm.project_type" :disabled="!canManage" clearable placeholder="选择项目类型" class="imp-full-width">
                    <el-option v-for="t in dict.project_types" :key="t" :label="t" :value="t"/>
                  </el-select>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="12" :lg="8">
                <el-form-item label="实施等级" prop="impl_level">
                  <el-select v-model="profileForm.impl_level" :disabled="!canManage" clearable placeholder="选择实施等级" class="imp-full-width">
                    <el-option v-for="l in dict.impl_levels" :key="l" :label="l" :value="l"/>
                  </el-select>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="12" :lg="8">
                <el-form-item label="稳定期(天)" prop="stability_days">
                  <el-input-number v-model="profileForm.stability_days" :disabled="!canManage" :min="0" controls-position="right" class="imp-full-width"/>
                </el-form-item>
              </el-col>
            </el-row>
            <div v-if="profileForm.impl_level" class="imp-card-hint">{{ levelRatioText(profileForm.impl_level) }}</div>
          </div>

          <!-- 卡片2：项目周期 -->
          <div class="imp-card">
            <div class="imp-card-title">项目周期</div>
            <el-row :gutter="20">
              <el-col :xs="24" :sm="12" :lg="6">
                <el-form-item label="计划开始" prop="plan_start_time">
                  <el-date-picker v-model="profileForm.plan_start_time" :disabled="!canManage" type="date" value-format="yyyy-MM-dd" class="imp-full-width"/>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="12" :lg="6">
                <el-form-item label="计划结束" prop="plan_end_time">
                  <el-date-picker v-model="profileForm.plan_end_time" :disabled="!canManage" type="date" value-format="yyyy-MM-dd" class="imp-full-width"/>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="12" :lg="6">
                <el-form-item label="实际开始" prop="actual_start_time">
                  <el-date-picker v-model="profileForm.actual_start_time" :disabled="!canManage" type="date" value-format="yyyy-MM-dd" class="imp-full-width"/>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="12" :lg="6">
                <el-form-item label="实际结束" prop="actual_end_time">
                  <el-date-picker v-model="profileForm.actual_end_time" :disabled="!canManage" type="date" value-format="yyyy-MM-dd" class="imp-full-width"/>
                </el-form-item>
              </el-col>
            </el-row>
          </div>

          <!-- 卡片3：实施记录 -->
          <div class="imp-card">
            <div class="imp-card-title">实施记录</div>
            <el-row :gutter="20">
              <el-col :xs="24" :sm="8">
                <el-form-item label="远程保障(小时)" prop="remote_support_hours">
                  <el-input-number v-model="profileForm.remote_support_hours" :disabled="!canManage" :min="0" :step="0.1" :precision="1" controls-position="right" class="imp-full-width"/>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="16">
                <el-form-item label="人员变化" prop="personnel_change">
                  <el-input v-model="profileForm.personnel_change" :disabled="!canManage" :rows="2" type="textarea" maxlength="500" show-word-limit placeholder="记录实施期间人员变动情况"/>
                </el-form-item>
              </el-col>
            </el-row>
            <el-form-item label="风险/责任归因">
              <el-input v-model="profileForm.risk_note" :disabled="!canManage" :rows="3" type="textarea" maxlength="500"/>
            </el-form-item>
          </div>

          <!-- 卡片4：验收信息 + 保存 -->
          <div class="imp-card">
            <div class="imp-card-title">验收信息</div>
            <el-row :gutter="20">
              <el-col :xs="24" :sm="12">
                <el-form-item label="验收结果">
                  <el-select v-model="profileForm.acceptance_result" :disabled="!canManage || !detail.can_accept" :placeholder="detail.can_accept ? '选择验收结果三档' : '需先完成至少一条里程碑'" clearable class="imp-full-width">
                    <el-option v-for="a in dict.acceptance_result" :key="a" :label="a" :value="a"/>
                  </el-select>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="12">
                <el-form-item v-if="profileForm.acceptance_result" label="结果系数">
                  <span class="imp-inline-tip">{{ resultCoeffText(profileForm.acceptance_result) }}</span>
                </el-form-item>
              </el-col>
            </el-row>
            <div v-if="canManage" class="imp-card-actions">
              <el-button :loading="acting" type="primary" @click="saveProfile">保存档案</el-button>
            </div>
          </div>

          <!-- 卡片5：交付奖金规则（只读） -->
          <div class="imp-card">
            <div class="imp-card-title">交付奖金规则</div>
            <div class="imp-rule-box">
              <div class="imp-rule-formula">公式：到账金额 × 实施等级比例 × 实施结果系数</div>
              <div v-if="profileForm.impl_level && profileForm.acceptance_result" class="imp-inline-tip imp-rule-current">
                当前：{{ levelRatioText(profileForm.impl_level) }} ；结果系数 {{ resultCoeffNum(profileForm.acceptance_result) }}
              </div>
              <div v-else class="imp-inline-tip">请选择实施等级与验收结果后展示适用比例与系数。</div>
              <table class="imp-dist-table">
                <caption>默认岗位分配方案（参考，实际以审批为准）</caption>
                <thead><tr><th>岗位</th><th>默认比例</th></tr></thead>
                <tbody>
                  <tr v-for="d in dict.default_dist" :key="d.role"><td>{{ d.role }}</td><td>{{ d.percentage }}%</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </el-form>
      </el-tab-pane>

      <!-- ===== 里程碑 ===== -->
      <el-tab-pane :label="'里程碑(' + milestones.length + ')'" name="milestone">
        <div class="imp-toolbar">
          <el-button v-if="canManage" type="primary" size="small" icon="el-icon-plus" @click="openMilestone(null)">新增里程碑</el-button>
        </div>
        <el-table ref="milestoneTable" :data="milestones" :row-class-name="milestoneRowClass" size="small" border>
          <el-table-column label="类型" prop="milestone_type" width="110"/>
          <el-table-column label="名称" prop="name"/>
          <el-table-column label="负责人" width="100"><template slot-scope="s">{{ memberName(s.row.responsible_user_id) }}</template></el-table-column>
          <el-table-column label="计划时间" width="120"><template slot-scope="s">{{ s.row.plan_time | fmtDate }}</template></el-table-column>
          <el-table-column label="实际时间" width="120"><template slot-scope="s">{{ s.row.actual_time | fmtDate }}</template></el-table-column>
          <el-table-column label="业务状态" width="100"><template slot-scope="s"><el-tag :type="msTag(s.row.status)" size="mini">{{ s.row.status }}</el-tag></template></el-table-column>
          <el-table-column label="绩效状态" width="110"><template slot-scope="s">
            <el-tooltip :disabled="!perfReason(s.row)" :content="perfReason(s.row)" placement="top">
              <el-tag :type="perfTag(s.row.performance_status)" size="mini">{{ s.row.performance_status_text || s.row.performance_status || '不计入' }}</el-tag>
            </el-tooltip>
          </template></el-table-column>
          <el-table-column v-if="canManage" label="操作" width="220">
            <template slot-scope="s">
              <el-tooltip :disabled="s.row.performance_status !== '已通过'" content="绩效已审核通过，请先撤回绩效" placement="top"><span><el-button :disabled="s.row.performance_status === '已通过'" type="text" @click="openMilestone(s.row)">编辑</el-button></span></el-tooltip>
              <el-tooltip :disabled="s.row.performance_status !== '已通过'" content="绩效已审核通过，请先撤回绩效" placement="top"><span><el-button :disabled="s.row.performance_status === '已通过'" type="text" style="color:#f56c6c" @click="delMilestone(s.row)">删除</el-button></span></el-tooltip>
              <el-button v-if="s.row.performance_status === '已驳回'" type="text" @click="resubmitPerformance('milestone', s.row.milestone_id)">重新提交</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- ===== 成员贡献 ===== -->
      <el-tab-pane :label="'成员贡献(' + contributions.length + ')'" name="contribution">
        <div class="imp-toolbar">
          <el-button v-if="canManage" type="primary" size="small" icon="el-icon-plus" @click="openContribution(null)">新增贡献</el-button>
        </div>
        <el-table ref="contributionTable" :data="contributions" :row-class-name="contributionRowClass" size="small" border>
          <el-table-column label="贡献人" width="100"><template slot-scope="s">{{ memberName(s.row.user_id) }}</template></el-table-column>
          <el-table-column label="贡献角色" prop="contribution_role"/>
          <el-table-column label="状态" width="90"><template slot-scope="s"><el-tag :type="contribTag(s.row.status)" size="mini">{{ s.row.status || '草稿' }}</el-tag></template></el-table-column>
          <el-table-column label="现场人日" prop="on_site_days" width="90"/>
          <el-table-column label="起止" width="220"><template slot-scope="s">{{ s.row.start_time | fmtDate }} ~ {{ s.row.end_time | fmtDate }}</template></el-table-column>
          <el-table-column label="绩效状态" width="110"><template slot-scope="s">
            <el-tooltip :disabled="!perfReason(s.row)" :content="perfReason(s.row)" placement="top">
              <el-tag :type="perfTag(s.row.performance_status)" size="mini">{{ s.row.performance_status_text || s.row.performance_status || '不计入' }}</el-tag>
            </el-tooltip>
          </template></el-table-column>
          <el-table-column label="证据/说明" prop="evidence_note"/>
          <el-table-column v-if="canManage" label="操作" width="220">
            <template slot-scope="s">
              <el-tooltip :disabled="s.row.performance_status !== '已通过'" content="绩效已审核通过，请先撤回绩效" placement="top"><span><el-button :disabled="s.row.performance_status === '已通过'" type="text" @click="openContribution(s.row)">编辑</el-button></span></el-tooltip>
              <el-tooltip :disabled="s.row.performance_status !== '已通过'" content="绩效已审核通过，请先撤回绩效" placement="top"><span><el-button :disabled="s.row.performance_status === '已通过'" type="text" style="color:#f56c6c" @click="delContribution(s.row)">删除</el-button></span></el-tooltip>
              <el-button v-if="s.row.performance_status === '已驳回'" type="text" @click="resubmitPerformance('contribution', s.row.contribution_id)">重新提交</el-button>
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
          <el-table-column label="地址"><template slot-scope="s">
            <a v-if="safeUrl(s.row.url)" :title="safeUrl(s.row.url)" :href="safeUrl(s.row.url)" target="_blank" rel="noopener noreferrer">{{ linkTitle(s.row.url) }}</a>
            <span v-else-if="s.row.url" class="imp-invalid-url" title="历史非法地址，未生成可点击链接">无效地址</span>
          </template></el-table-column>
          <el-table-column label="完整性" width="100"><template slot-scope="s"><el-tag :type="compTag(s.row.completeness_status)" size="mini">{{ s.row.completeness_status }}</el-tag></template></el-table-column>
          <el-table-column label="维护人" width="120"><template slot-scope="s">{{ memberName(s.row.owner_user_id) }}</template></el-table-column>
          <el-table-column v-if="canManage" label="操作" width="140">
            <template slot-scope="s">
              <el-button type="text" @click="openKnowledge(s.row)">编辑</el-button>
              <el-button type="text" style="color:#f56c6c" @click="delKnowledge(s.row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- 里程碑弹窗 -->
    <el-dialog :title="milestoneForm.milestone_id ? '编辑里程碑' : '新增里程碑'" :visible.sync="msDialog" width="520px" custom-class="imp-responsive-dialog" append-to-body>
      <el-form ref="msForm" :model="milestoneForm" :rules="msRules" label-width="100px" size="small">
        <el-form-item label="类型" prop="milestone_type"><el-select v-model="milestoneForm.milestone_type" style="width:100%"><el-option v-for="t in dict.milestone_types" :key="t" :label="t" :value="t"/></el-select></el-form-item>
        <el-form-item label="名称" prop="name"><el-input v-model="milestoneForm.name" maxlength="50"/></el-form-item>
        <el-form-item label="负责人" prop="responsible_user_id">
          <el-select v-model="milestoneForm.responsible_user_id" filterable placeholder="选择项目成员（绩效归属人）" style="width:100%">
            <el-option v-for="m in members" :key="m.user_id" :label="m.realname" :value="m.user_id"/>
          </el-select>
        </el-form-item>
        <el-form-item label="计划时间" prop="plan_time"><el-date-picker v-model="milestoneForm.plan_time" type="date" value-format="yyyy-MM-dd" style="width:100%"/></el-form-item>
        <el-form-item label="实际时间" prop="actual_time"><el-date-picker v-model="milestoneForm.actual_time" type="date" value-format="yyyy-MM-dd" style="width:100%"/></el-form-item>
        <el-form-item label="状态" prop="status"><el-select v-model="milestoneForm.status" style="width:100%"><el-option v-for="s in dict.milestone_status" :key="s" :label="s" :value="s"/></el-select></el-form-item>
        <el-form-item label="排序"><el-input-number v-model="milestoneForm.sort" :min="0" controls-position="right"/></el-form-item>
        <el-form-item label="证据/说明" prop="evidence_note"><el-input v-model="milestoneForm.evidence_note" :rows="2" type="textarea"/></el-form-item>
      </el-form>
      <span slot="footer"><el-button @click="msDialog=false">取消</el-button><el-button :loading="acting" type="primary" @click="saveMilestone">保存</el-button></span>
    </el-dialog>

    <!-- 贡献弹窗 -->
    <el-dialog :title="contributionForm.contribution_id ? '编辑贡献' : '新增贡献'" :visible.sync="ctDialog" width="520px" custom-class="imp-responsive-dialog" append-to-body>
      <el-form ref="ctForm" :model="contributionForm" :rules="ctRules" label-width="100px" size="small">
        <el-form-item label="贡献人" prop="user_id">
          <el-select v-model="contributionForm.user_id" filterable placeholder="选择项目成员" style="width:100%">
            <el-option v-for="m in members" :key="m.user_id" :label="m.realname" :value="m.user_id"/>
          </el-select>
        </el-form-item>
        <el-form-item label="贡献角色" prop="contribution_role"><el-input v-model="contributionForm.contribution_role" placeholder="如：项目经理/开发/实施"/></el-form-item>
        <el-form-item label="状态" prop="status">
          <el-select v-model="contributionForm.status" style="width:100%">
            <el-option label="草稿" value="草稿"/>
            <el-option label="已确认" value="已确认"/>
            <el-option label="已作废" value="已作废"/>
          </el-select>
        </el-form-item>
        <el-form-item label="现场人日" prop="on_site_days">
          <el-input-number v-model="contributionForm.on_site_days" :min="0" :step="0.1" :precision="1" controls-position="right" @change="onSiteDaysTouched = true"/>
          <span v-if="contributionPeriod > 0" class="imp-inline-tip" style="margin-left:12px">周期 {{ contributionPeriod }} 天</span>
        </el-form-item>
        <el-form-item label="开始" prop="start_time"><el-date-picker v-model="contributionForm.start_time" type="date" value-format="yyyy-MM-dd" style="width:100%"/></el-form-item>
        <el-form-item label="结束" prop="end_time"><el-date-picker v-model="contributionForm.end_time" type="date" value-format="yyyy-MM-dd" style="width:100%"/></el-form-item>
        <el-form-item label="证据/说明" prop="evidence_note"><el-input v-model="contributionForm.evidence_note" :rows="2" type="textarea"/></el-form-item>
      </el-form>
      <span slot="footer"><el-button @click="ctDialog=false">取消</el-button><el-button :loading="acting" type="primary" @click="saveContribution">保存</el-button></span>
    </el-dialog>

    <!-- 知识链接弹窗 -->
    <el-dialog :title="knowledgeForm.link_id ? '编辑链接' : '新增链接'" :visible.sync="klDialog" width="520px" custom-class="imp-responsive-dialog" append-to-body>
      <el-form ref="klForm" :model="knowledgeForm" :rules="klRules" label-width="100px" size="small">
        <el-form-item label="类型" prop="link_type"><el-select v-model="knowledgeForm.link_type" style="width:100%"><el-option v-for="t in dict.knowledge_types" :key="t" :label="t" :value="t"/></el-select></el-form-item>
        <el-form-item label="标题" prop="title"><el-input v-model="knowledgeForm.title" maxlength="100"/></el-form-item>
        <el-form-item label="地址" prop="url"><el-input v-model="knowledgeForm.url" placeholder="https://..."/></el-form-item>
        <el-form-item label="维护人" prop="owner_user_id">
          <el-select v-model="knowledgeForm.owner_user_id" filterable clearable placeholder="选择项目成员" style="width:100%">
            <el-option v-for="m in members" :key="m.user_id" :label="m.realname" :value="m.user_id"/>
          </el-select>
        </el-form-item>
        <el-form-item label="完整性"><el-select v-model="knowledgeForm.completeness_status" style="width:100%"><el-option v-for="c in dict.completeness" :key="c" :label="c" :value="c"/></el-select></el-form-item>
        <el-form-item label="排序"><el-input-number v-model="knowledgeForm.sort" :min="0" controls-position="right"/></el-form-item>
      </el-form>
      <span slot="footer"><el-button @click="klDialog=false">取消</el-button><el-button :loading="acting" type="primary" @click="saveKnowledge">保存</el-button></span>
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
  projectPerformanceResubmitAPI,
  knowledgeSaveAPI,
  knowledgeDeleteAPI
} from '@/api/pm/implementation'
import { workWorkOwnerListAPI } from '@/api/pm/project'
import { isSafeHttpUrl, normalizeHttpUrl } from '@/utils/urlGuard'
import { periodDays, shouldAutoFillOnSiteDays, isExactContributionDuplicate } from '@/utils/projectPerf'

const EMPTY_DICT = {
  project_types: [], impl_levels: [], milestone_types: [], milestone_status: [],
  acceptance_result: [], knowledge_types: [], completeness: [],
  impl_level_pct: {}, result_coeff: {}, default_dist: []
}

export default {
  name: 'ProjectImplementationPanel',
  filters: {
    fmtDate(v) {
      if (!v) return ''
      const dt = new Date(Number(v) * 1000)
      const m = String(dt.getMonth() + 1).padStart(2, '0')
      const d = String(dt.getDate()).padStart(2, '0')
      return dt.getFullYear() + '-' + m + '-' + d
    }
  },
  props: {
    workId: [Number, String]
  },
  data() {
    const dateNotEarlier = (startField, msg) => (rule, value, cb) => {
      const s = this.profileForm[startField]
      if (value && s && new Date(value).getTime() < new Date(s).getTime()) {
        return cb(new Error(msg))
      }
      cb()
    }
    // 仅允许一位小数（与数据库 DECIMAL(8,1)/DECIMAL(6,1) 一致），拒绝两位及以上小数
    const atMostOneDecimal = (msg) => (rule, value, cb) => {
      if (value === '' || value === null || value === undefined) return cb()
      const s = String(value)
      if (/\.\d{2,}$/.test(s)) return cb(new Error(msg))
      cb()
    }
    const safeUrl = (rule, value, cb) => {
      const v = (value || '')
      if (v.trim() === '') return cb()
      // 复用统一校验：拒绝 javascript:/data:/vbscript:/协议相对地址及控制字符绕过
      if (!isSafeHttpUrl(v)) return cb(new Error('地址必须为绝对 http:// 或 https://，且不得含控制字符'))
      cb()
    }
    const klUrlRequired = (rule, value, cb) => {
      if (this.knowledgeForm.completeness_status === '完整' && !(value || '').trim()) {
        return cb(new Error('完整性为“完整”时地址必填'))
      }
      return safeUrl(rule, value, cb)
    }
    return {
      loadStatus: 'loading', // loading | ok | error
      loadError: '',
      acting: false,
      // 标记用户是否手工修改过现场人日，避免后续日期联动无条件覆盖
      onSiteDaysTouched: false,
      deepLinkNoticeKey: '',
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
      knowledgeForm: {},
      profileRules: {
        project_type: [{ required: true, message: '请选择项目类型', trigger: 'change' }],
        impl_level: [{ required: true, message: '请选择实施等级', trigger: 'change' }],
        stability_days: [{ type: 'number', min: 0, message: '稳定期不得为负', trigger: 'blur' }],
        remote_support_hours: [
          { type: 'number', min: 0, message: '远程保障时长不得为负', trigger: 'blur' },
          { validator: atMostOneDecimal('远程保障时长最多保留一位小数'), trigger: 'blur' }
        ],
        plan_end_time: [{ validator: dateNotEarlier('plan_start_time', '计划结束不得早于计划开始'), trigger: 'change' }],
        actual_end_time: [{ validator: dateNotEarlier('actual_start_time', '实际结束不得早于实际开始'), trigger: 'change' }]
      },
      msRules: {
        milestone_type: [{ required: true, message: '请选择里程碑类型', trigger: 'change' }],
        name: [{ required: true, message: '请输入名称', trigger: 'blur' }],
        responsible_user_id: [{ required: true, message: '请选择负责人', trigger: 'change' },
          { validator: (r, v, cb) => {
            if (v && !this.members.some(m => Number(m.user_id) === Number(v))) return cb(new Error('负责人必须是当前项目成员'))
            cb()
          }, trigger: 'change' }],
        status: [{ required: true, message: '请选择状态', trigger: 'change' }],
        actual_time: [{ validator: (r, v, cb) => {
          if (this.milestoneForm.status === '已完成' && !(v || '').trim()) return cb(new Error('已完成里程碑必须填写实际时间'))
          cb()
        }, trigger: 'change' }],
        evidence_note: [{ validator: (r, v, cb) => {
          if (this.milestoneForm.status === '已延期' && !(v || '').trim()) return cb(new Error('已延期需填写证据/说明'))
          cb()
        }, trigger: 'blur' }]
      },
      ctRules: {
        user_id: [{ required: true, message: '请选择贡献人', trigger: 'change' },
          { validator: (r, v, cb) => {
            if (v && !this.members.some(m => Number(m.user_id) === Number(v))) return cb(new Error('贡献人必须是当前项目成员'))
            cb()
          }, trigger: 'change' }],
        contribution_role: [{ required: true, message: '请输入贡献角色', trigger: 'blur' }],
        status: [{ required: true, message: '请选择状态', trigger: 'change' }],
        on_site_days: [
          { type: 'number', min: 0, message: '现场人日不得为负', trigger: 'blur' },
          { validator: atMostOneDecimal('现场人日最多保留一位小数'), trigger: 'blur' },
          { validator: (r, v, cb) => {
            if (this.contributionForm.status === '已确认' && (v === '' || v === null || v === undefined || Number(v) <= 0)) return cb(new Error('已确认贡献现场人日必须大于 0'))
            cb()
          }, trigger: 'blur' }
        ],
        end_time: [{ validator: (r, v, cb) => {
          const s = this.contributionForm.start_time
          if (v && s && new Date(v).getTime() < new Date(s).getTime()) return cb(new Error('结束时间不得早于开始时间'))
          cb()
        }, trigger: 'change' }],
        evidence_note: [{ validator: (r, v, cb) => {
          const days = this.contributionPeriod
          const onSite = Number(this.contributionForm.on_site_days || 0)
          if (this.contributionForm.status === '已确认' && days > 0 && onSite > days && !(v || '').trim()) {
            return cb(new Error('现场人日超过周期天数时必须填写证据/说明'))
          }
          cb()
        }, trigger: 'blur' }]
      },
      klRules: {
        link_type: [{ required: true, message: '请选择类型', trigger: 'change' }],
        title: [{ required: true, message: '请输入标题', trigger: 'blur' }],
        url: [{ validator: klUrlRequired, trigger: 'blur' }],
        owner_user_id: [{ validator: (r, v, cb) => {
          if (v && !this.members.some(m => Number(m.user_id) === Number(v))) return cb(new Error('维护人必须是当前项目成员'))
          cb()
        }, trigger: 'change' }]
      }
    }
  },
  computed: {
    canManage() { return !!(this.detail && this.detail.can_manage) },
    // 贡献弹窗周期天数（包含首尾），用于提示与默认人日联动
    contributionPeriod() {
      return this.periodDays(this.contributionForm.start_time, this.contributionForm.end_time)
    }
  },
  watch: {
    workId: {
      handler(val) {
        // 路由项目 ID 变化：立即清理旧项目展示数据，避免残留
        this.resetData()
        if (val) this.fetch()
      },
      immediate: true
    },
    // 日期变化联动人日默认值：仅在用户尚未手工修改人日时自动带出周期天数
    'contributionForm.start_time'() { this.autoFillOnSiteDays() },
    'contributionForm.end_time'() { this.autoFillOnSiteDays() },
    '$route.query': {
      handler() { if (this.loadStatus === 'ok') this.applyDeepLink() },
      deep: true
    }
  },
  methods: {
    resetData() {
      this.detail = { can_manage: false, can_accept: false }
      this.dict = Object.assign({}, EMPTY_DICT)
      this.profileForm = {}
      this.milestones = []
      this.contributions = []
      this.knowledge = []
      this.members = []
      this.loadStatus = 'loading'
      this.loadError = ''
    },
    async fetch() {
      // 捕获当前 workId，避免快速切换项目时旧响应覆盖新项目数据
      const current = Number(this.workId)
      this.loadStatus = 'loading'
      this.loadError = ''
      try {
        const res = await implementationReadAPI({ work_id: current })
        if (Number(this.workId) !== current) return // 已切换到其它项目，丢弃过期响应
        const d = res.data || res
        this.detail = d
        this.dict = Object.assign({}, EMPTY_DICT, d.dictionary || {})
        const p = d.profile || {}
        this.profileForm = {
          project_type: p.project_type || '',
          impl_level: p.impl_level || '',
          stability_days: Number(p.stability_days || 0),
          remote_support_hours: p.remote_support_hours !== undefined && p.remote_support_hours !== null ? Number(p.remote_support_hours) : 0,
          personnel_change: p.personnel_change || '',
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
        this.loadStatus = 'ok'
        // 复用项目成员选择器数据
        try {
          const mr = await workWorkOwnerListAPI({ work_id: current })
          if (Number(this.workId) !== current) return
          this.members = (mr.data || mr) || []
        } catch (e) { this.members = [] }
        this.$nextTick(() => this.applyDeepLink())
      } catch (e) {
        if (Number(this.workId) !== current) return
        this.loadStatus = 'error'
        this.loadError = '实施信息加载失败，请重试'
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
      if (!uid) return ''
      const m = this.members.find(x => Number(x.user_id) === Number(uid))
      return m ? m.realname : uid
    },
    // 等级比例展示（字典驱动）
    levelRatioNum(level) {
      const pct = this.dict.impl_level_pct || {}
      return pct[level]
    },
    levelRatioText(level) {
      const n = this.levelRatioNum(level)
      if (level === '四级') return '四级：10%—12%（上限 12%，需立项审批）'
      return n !== undefined ? level + '：' + n + '%' : level
    },
    resultCoeffNum(result) {
      const c = this.dict.result_coeff || {}
      return c[result]
    },
    resultCoeffText(result) {
      const n = this.resultCoeffNum(result)
      return n !== undefined ? '结果系数 ' + n : result
    },
    linkTitle(url) {
      // 截断展示，避免长 URL 占满列；完整地址由 title 暴露
      const u = String(url || '')
      return u.length > 40 ? u.slice(0, 40) + '…' : u
    },
    // 渲染期再次规范化：仅允许绝对 http/https，非法历史地址返回空串（不生成可点击链接）
    safeUrl(url) {
      return normalizeHttpUrl(url)
    },
    msTag(s) { return { 未开始: 'info', 进行中: '', 已完成: 'success', 已延期: 'warning' }[s] || 'info' },
    compTag(c) { return { 完整: 'success', 待补充: 'warning', 缺失: 'danger' }[c] || 'info' },
    contribTag(s) { return { 草稿: 'info', 已确认: 'success', 已作废: 'danger' }[s] || 'info' },
    // 绩效状态标签颜色（与后端 perfStatusColor 口径一致）
    perfTag(status) { return { 不计入: 'info', 待归集: 'warning', 待审核: '', 已通过: 'success', 已驳回: 'danger' }[status] || 'info' },
    // 绩效不计入/驳回原因（el-tooltip 展示）
    perfReason(row) { return (row && row.performance_status_reason) ? row.performance_status_reason : '' },
    milestoneRowClass({ row }) {
      return this.isDeepLinkRow('milestone', row.milestone_id) ? 'imp-source-highlight' : ''
    },
    contributionRowClass({ row }) {
      return this.isDeepLinkRow('contribution', row.contribution_id) ? 'imp-source-highlight' : ''
    },
    isDeepLinkRow(section, id) {
      const q = this.$route && this.$route.query ? this.$route.query : {}
      return q.section === section && Number(q.source_id) === Number(id)
    },
    applyDeepLink() {
      const q = this.$route && this.$route.query ? this.$route.query : {}
      if (!['milestone', 'contribution'].includes(q.section) || !q.source_id) return
      this.activeTab = q.section
      const rows = q.section === 'milestone' ? this.milestones : this.contributions
      const idField = q.section === 'milestone' ? 'milestone_id' : 'contribution_id'
      const found = rows.some(row => Number(row[idField]) === Number(q.source_id))
      const noticeKey = q.section + ':' + q.source_id + ':' + (found ? 'found' : 'missing')
      if (!found && this.deepLinkNoticeKey !== noticeKey) {
        this.deepLinkNoticeKey = noticeKey
        this.$message.warning('来源记录已不存在')
        return
      }
      if (!found) return
      this.deepLinkNoticeKey = noticeKey
      this.$nextTick(() => {
        const el = this.$el.querySelector('.imp-source-highlight')
        if (el && el.scrollIntoView) el.scrollIntoView({ behavior: 'smooth', block: 'center' })
      })
    },
    // 两个 yyyy-MM-dd 之间包含首尾的天数（0 表示无效）——复用共享 utils，不在组件内复制实现
    periodDays(startStr, endStr) {
      return periodDays(startStr, endStr)
    },
    // 日期联动人日默认值：仅当用户尚未手工修改人日时按周期天数带出
    autoFillOnSiteDays() {
      const days = this.contributionPeriod
      if (shouldAutoFillOnSiteDays(this.onSiteDaysTouched, days)) this.contributionForm.on_site_days = days
    },
    async saveProfile() {
      if (this.acting) return
      const valid = await this.validateForm('profileForm')
      if (!valid) return
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
      }) : { milestone_type: this.dict.milestone_types[0] || '', name: '', responsible_user_id: '', plan_time: '', actual_time: '', status: '未开始', sort: this.milestones.length, evidence_note: '' }
      this.msDialog = true
      this.$nextTick(() => this.$refs.msForm && this.$refs.msForm.clearValidate())
    },
    async saveMilestone() {
      if (this.acting) return
      const valid = await this.validateForm('msForm')
      if (!valid) return
      this.acting = true
      try {
        await milestoneSaveAPI(Object.assign({ work_id: Number(this.workId) }, this.milestoneForm))
        this.msDialog = false; this.$message.success('已保存'); this.fetch(); this.$emit('refresh')
      } catch (e) { /* 忽略：错误已由全局拦截器提示 */ } finally { this.acting = false }
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
      }) : { user_id: '', contribution_role: '', status: '草稿', on_site_days: 0, start_time: '', end_time: '', evidence_note: '' }
      // 编辑已有记录时保护历史人工人日（初始化 true）；新建记录初始化 false 允许联动
      this.onSiteDaysTouched = !!row
      this.ctDialog = true
      this.$nextTick(() => this.$refs.ctForm && this.$refs.ctForm.clearValidate())
    },
    async saveContribution() {
      if (this.acting) return
      const valid = await this.validateForm('ctForm')
      if (!valid) return
      const duplicateRows = this.contributions.map(row => Object.assign({}, row, { start_date: this.fmt(row.start_time), end_date: this.fmt(row.end_time) }))
      const duplicate = isExactContributionDuplicate(duplicateRows, this.contributionForm)
      if (duplicate) { this.$message.warning('已存在相同贡献人、角色与起止时间的贡献记录'); return }
      this.acting = true
      try {
        await contributionSaveAPI(Object.assign({ work_id: Number(this.workId) }, this.contributionForm))
        this.ctDialog = false; this.$message.success('已保存'); this.fetch()
      } catch (e) { /* 忽略：错误已由全局拦截器提示 */ } finally { this.acting = false }
    },
    delContribution(row) {
      this.$confirm('确认删除该贡献记录？', '提示', { type: 'warning' }).then(async() => {
        await contributionDeleteAPI({ work_id: Number(this.workId), contribution_id: row.contribution_id })
        this.$message.success('已删除'); this.fetch()
      }).catch(() => {})
    },
    async resubmitPerformance(sourceType, sourceId) {
      if (this.acting) return
      this.acting = true
      try {
        await projectPerformanceResubmitAPI({ work_id: Number(this.workId), source_type: sourceType, source_id: Number(sourceId) })
        this.$message.success('绩效已重新提交，等待审核')
        await this.fetch()
      } catch (e) { /* 全局拦截器提示 */ } finally { this.acting = false }
    },
    openKnowledge(row) {
      this.knowledgeForm = row ? Object.assign({}, row) : { link_type: this.dict.knowledge_types[0] || '', title: '', url: '', owner_user_id: '', completeness_status: '待补充', sort: this.knowledge.length }
      this.klDialog = true
      this.$nextTick(() => this.$refs.klForm && this.$refs.klForm.clearValidate())
    },
    async saveKnowledge() {
      if (this.acting) return
      const valid = await this.validateForm('klForm')
      if (!valid) return
      this.acting = true
      try {
        await knowledgeSaveAPI(Object.assign({ work_id: Number(this.workId) }, this.knowledgeForm))
        this.klDialog = false; this.$message.success('已保存'); this.fetch()
      } catch (e) { /* 忽略：错误已由全局拦截器提示 */ } finally { this.acting = false }
    },
    delKnowledge(row) {
      this.$confirm('确认删除该知识链接？', '提示', { type: 'warning' }).then(async() => {
        await knowledgeDeleteAPI({ work_id: Number(this.workId), link_id: row.link_id })
        this.$message.success('已删除'); this.fetch()
      }).catch(() => {})
    },
    validateForm(refName) {
      const form = this.$refs[refName]
      if (!form) return Promise.resolve(true)
      return new Promise(resolve => {
        form.validate(ok => resolve(ok))
      })
    }
  }
}
</script>

<style scoped>
.project-implementation-panel { padding: 0; width: 100%; max-width: 1440px; margin: 0 auto; box-sizing: border-box; }
.imp-tabs { margin-top: 8px; }
.imp-tabs >>> .el-tabs__content { overflow: visible; }
.imp-form { width: 100%; }
.imp-form >>> .el-form-item { margin-bottom: 14px; }
.imp-form >>> .el-form-item__label { font-size: 13px; padding-right: 8px; }
.imp-full-width { width: 100%; }

/* 卡片 */
.imp-card {
  background: #fff; border: 1px solid #ebeef5; border-radius: 6px;
  padding: 20px; margin-bottom: 16px;
}
.imp-card-title { font-size: 15px; font-weight: 600; color: #303133; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid #f0f0f0; }
.imp-card-hint { font-size: 12px; color: #909399; margin-top: -8px; margin-bottom: 8px; padding-left: 110px; }
.imp-card-actions { border-top: 1px solid #ebeef5; margin-top: 16px; padding-top: 16px; display: flex; justify-content: flex-end; }

/* 工具栏 */
.imp-toolbar { margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.imp-toolbar-info { font-size: 13px; color: #606266; }
.imp-inline-tip { color: #909399; font-size: 12px; }

/* 奖金规则 */
.imp-rule-box { background: #fafafa; border-radius: 4px; padding: 12px 16px; }
.imp-rule-formula { font-size: 13px; color: #303133; font-weight: 500; margin-bottom: 8px; }
.imp-rule-current { margin-bottom: 8px; }
.imp-dist-table { border-collapse: collapse; width: 100%; max-width: 720px; margin-top: 8px; }
.imp-dist-table caption { text-align: left; color: #909399; font-size: 12px; padding-bottom: 4px; }
.imp-dist-table th, .imp-dist-table td { border: 1px solid #ebeef5; padding: 6px 10px; font-size: 13px; text-align: left; }

/* 状态 */
.imp-state-error { display: flex; align-items: center; gap: 12px; justify-content: center; color: #f56c6c; padding: 24px 0; }
.imp-loading { min-height: 200px; }
.imp-invalid-url { color: #f56c6c; font-size: 12px; }

/* 表格 */
.project-implementation-panel >>> .el-table--mini td,
.project-implementation-panel >>> .el-table--mini th { padding: 4px 0; }
.project-implementation-panel >>> .el-table { font-size: 12px; }
.project-implementation-panel >>> .imp-source-highlight > td { background: #fff4d6; transition: background-color .3s; }

/* 弹窗 */
.imp-responsive-dialog >>> .el-form-item { margin-bottom: 16px; }
.imp-responsive-dialog >>> .el-dialog__body { padding: 16px 20px; max-height: 60vh; overflow-y: auto; }

/* el-input-number 在 el-form-item 内全宽 */
.imp-full-width >>> .el-input__inner,
.project-implementation-panel >>> .imp-full-width .el-input__inner { text-align: left; }

/* 响应式 */
@media (max-width: 1199px) {
  .project-implementation-panel { padding: 0; }
  .imp-card { padding: 16px; }
}
@media (max-width: 767px) {
  .project-implementation-panel { padding: 0; }
  .imp-card { padding: 12px; margin-bottom: 12px; }
  .imp-form >>> .el-form-item__label { width: 90px; font-size: 12px; }
  .imp-card-hint { padding-left: 90px; }
  .project-implementation-panel >>> .el-table { font-size: 11px; }
}
</style>

<!-- 非 scoped：el-dialog append-to-body 后脱离组件 DOM，需全局样式约束窄屏宽度 -->
<style>
.imp-responsive-dialog {
  max-width: 92vw !important;
  box-sizing: border-box;
}
</style>

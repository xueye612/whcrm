<template>
  <div
    class="project-list"
    direction="column">
    <div v-loading="loading" class="nav-box">
      <xr-header
        :icon-color="projectColor || '#4AB8B8'"
        class="xr-header"
        icon-class="wk wk-project">
        <span slot="label">{{ projectName }}</span>
        <el-popover
          v-if="showSet"
          slot="label"
          v-model="projectHandleShow"
          placement="bottom-start"
          width="182">
          <div class="project-list-popover-btn-list">
            <project-settings
              v-if="permission.setWork"
              :work-id="work_id"
              :title="projectName"
              :color="projectColor"
              :is-open="projectData.is_open"
              :add-members-data="membersList"
              :permission="permission"
              tab-type="base"
              @close="projectHandleShow = false"
              @submite="setSubmite"
              @handle="projectSettingsHandle"
              @click="projectHandleShow = false"/>

            <project-settings
              v-if="permission.setTaskOwnerUser && projectData.is_open != 1"
              :work-id="work_id"
              :title="projectName"
              :color="projectColor"
              :is-open="projectData.is_open"
              :add-members-data="membersList"
              :permission="permission"
              tab-type="member"
              @submite="setSubmite"
              @handle="projectSettingsHandle"/>

            <p v-if="permission.excelImport" @click="taskImportShow = true">导入任务</p>
            <p v-if="permission.excelExport" @click="exportClick">导出任务</p>
            <p
              v-if="permission.archiveTask && permission.setWork"
              @click="archiveProject">归档项目</p>
            <p
              v-if="permission.deleteTask && permission.setWork"
              @click="deleteProject">删除项目</p>
            <p v-if="projectData.is_open == 0" @click="exitProject">退出项目</p>
          </div>
          <i
            slot="reference"
            class="wk wk-manage set-img" />
        </el-popover>

        <!-- 人员列表 -->
        <span
          slot="ft"
          class="ft-btn"
          @click="membersShow = true">
          <i class="wk wk-s-seas ft-img" />
          <span class="ft-label">成员管理</span>
        </span>
        <!-- 成员加载失败：在成员入口附近提示并提供重新加载（针对当前 work_id） -->
        <span
          v-if="memberError"
          slot="ft"
          class="member-error-tip"
          title="点击重新加载成员"
          @click="getMemberList">
          <i class="el-icon-warning" />{{ memberError }}（重试）
        </span>
        <span
          v-show="screeningButtonShow"
          slot="ft"
          class="ft-btn"
          @click="screeningShow = true">
          <i class="wk wk-screening ft-img" />
          <span class="ft-label">任务筛选</span>
        </span>

        <!-- 筛选 -->
      </xr-header>

      <div class="nav">
        <el-tabs
          v-model="activeName"
          @tab-click="tabClick">
          <el-tab-pane
            name="task-board">
            <el-dropdown slot="label" trigger="click" @command="tabShowType = $event">
              <span class="el-dropdown-link" >
                {{ tabShowType | showTypeName }}<i class="el-icon-arrow-down el-icon--right"/>
              </span>
              <el-dropdown-menu slot="dropdown">
                <el-dropdown-item command="board">看板视图</el-dropdown-item>
                <el-dropdown-item command="list">列表视图</el-dropdown-item>
                <el-dropdown-item command="user">负责人视图</el-dropdown-item>
              </el-dropdown-menu>
            </el-dropdown>
          </el-tab-pane>
          <el-tab-pane
            label="附件"
            name="attachment"/>
          <el-tab-pane
            label="任务统计"
            name="task-statistical"/>
          <el-tab-pane
            label="归档任务"
            name="archiving-task"/>
          <el-tab-pane
            label="项目实施"
            name="project-implementation"/>
          <el-tab-pane
            label="奖金分配"
            name="reward-distribution"/>
        </el-tabs>
      </div>
    </div>
    <div class="content">
      <!-- 项目详情加载失败：显示错误与重试（针对当前 work_id），不展示上一项目残留内容 -->
      <div
        v-if="detailError"
        class="project-detail-error">
        <i class="el-icon-warning"/>
        <span>{{ detailError }}</span>
        <el-button
          type="primary"
          size="mini"
          @click="getDetail">重试</el-button>
      </div>
      <!-- keep-alive 始终挂载：v-if 移到内部动态组件，切换到“项目实施”时不再整体销毁其它标签页缓存 -->
      <div
        v-loading="detailLoading"
        v-else
        element-loading-text="加载中"
        class="content-inner">
        <keep-alive>
          <component
            v-if="activeName !== 'project-implementation' && activeName !== 'reward-distribution'"
            :is="showComponent"
            :condition-data="taskConditionObj"
            :work-id="work_id"
            :show-type="tabShowType"
            :permission="permission"/>
        </keep-alive>
        <project-implementation-panel
          v-if="activeName === 'project-implementation'"
          :work-id="work_id"
          @refresh="onProfileSaved"/>
        <!-- 奖金分配：内联展示，双栏布局 -->
        <div v-loading="rewardLoading" v-if="activeName === 'reward-distribution'" class="reward-panel">
          <!-- 计算规则说明 -->
          <div class="reward-rules-card">
            <div class="reward-card-title">计算规则</div>
            <div class="rr-step">
              <span class="rr-num">1</span>
              <span class="rr-text">奖励池 = 到账收入 × 奖励比例（默认 2%）</span>
              <span v-if="rewardProfile" class="rr-calc">{{ rewardForm.revenue || 0 }} × 2% = <b>{{ rewardProfile.reward_pool || 0 }}</b></span>
            </div>
            <div class="rr-step">
              <span class="rr-num">2</span>
              <span class="rr-text">调整后奖励池 = 奖励池 × 验收结果系数</span>
              <span v-if="rewardDistResult" class="rr-calc">
                {{ rewardDistResult.base_pool }} ×
                <b :class="{ 'rms-green': rewardDistResult.result_coeff > 1, 'rms-orange': rewardDistResult.result_coeff < 1 }">{{ rewardDistResult.result_coeff }}</b>
                （{{ rewardDistResult.acceptance_result || '合格' }}）= <b>{{ rewardDistResult.adjusted_pool }}</b>
              </span>
              <span v-else class="rr-calc rr-calc-hint">保存分配后显示（优质×1.10 / 合格×1.00 / 待改进×0.80）</span>
            </div>
            <div class="rr-step">
              <span class="rr-num">3</span>
              <span class="rr-text">阶段拆分（70/30 发放节奏）</span>
              <span v-if="rewardDistResult && rewardDistResult.payout_rhythm" class="rr-calc">
                交付阶段 70% = <b>{{ rewardDistResult.payout_rhythm.phase1_deliver }}</b> ·
                验收阶段 30% = <b>{{ rewardDistResult.payout_rhythm.phase2_accept }}</b>
              </span>
            </div>
            <div class="rr-step">
              <span class="rr-num">4</span>
              <span class="rr-text">岗位分配金额 = 交付阶段金额 × 各岗位比例</span>
              <span class="rr-calc rr-calc-hint">详见右侧分配明细表</span>
            </div>
          </div>
          <!-- 奖金池概览 -->
          <div v-if="rewardProfile" class="reward-pool-cards">
            <div class="reward-stat-card"><div class="rsc-label">到账收入</div><div class="rsc-value">{{ rewardForm.revenue || 0 }}</div></div>
            <div class="reward-stat-card"><div class="rsc-label">毛利</div><div class="rsc-value">{{ rewardProfile.gross_margin || 0 }}</div></div>
            <div class="reward-stat-card rsc-highlight"><div class="rsc-label">奖励池</div><div class="rsc-value rsc-amount">{{ rewardProfile.reward_pool || 0 }}</div></div>
            <div v-if="rewardDistResult && rewardDistResult.result_coeff && rewardDistResult.result_coeff !== 1" class="reward-stat-card rsc-coeff">
              <div class="rsc-label">验收系数（{{ rewardDistResult.acceptance_result }}）</div>
              <div :class="{ 'rms-green': rewardDistResult.result_coeff > 1, 'rms-orange': rewardDistResult.result_coeff < 1 }" class="rsc-value">{{ rewardDistResult.result_coeff }}</div>
            </div>
          </div>
          <!-- 系数调整提示 -->
          <div v-if="rewardDistResult && rewardDistResult.result_coeff && rewardDistResult.result_coeff !== 1" class="reward-coeff-banner">
            <i class="el-icon-info"/>
            <span>验收结果"{{ rewardDistResult.acceptance_result }}"系数 {{ rewardDistResult.result_coeff }}，奖励池 {{ rewardDistResult.base_pool }} → <b>{{ rewardDistResult.adjusted_pool }}</b></span>
          </div>
          <!-- 双栏：左比例 + 右明细 -->
          <div v-if="rewardProfile" class="reward-dual">
            <!-- 左：岗位分配比例 -->
            <div class="reward-card reward-card-left">
              <div class="reward-card-header">
                <span class="reward-card-title">岗位分配比例</span>
                <span :class="{ 'rrs-ok': rewardRatioSum === 100, 'rrs-under': rewardRatioSum < 100, 'rrs-over': rewardRatioSum > 100 }" class="reward-ratio-sum">合计 {{ rewardRatioSum }}%</span>
              </div>
              <el-table :data="rewardRatios" size="small" border>
                <el-table-column label="岗位角色" prop="role"/>
                <el-table-column label="比例" width="200">
                  <template slot-scope="s">
                    <div class="reward-ratio-input">
                      <el-input-number v-model="s.row.percentage" :min="0" :max="100" controls-position="right" size="small"/>
                      <span class="reward-ratio-unit">%</span>
                    </div>
                  </template>
                </el-table-column>
              </el-table>
              <div class="reward-card-actions">
                <el-button :loading="rewardSaving" type="primary" size="small" @click="saveRewardDist">保存并计算</el-button>
              </div>
            </div>
            <!-- 右：分配明细 -->
            <div class="reward-card reward-card-right">
              <div class="reward-card-title">分配明细</div>
              <template v-if="rewardDistResult">
                <div class="reward-mini-stats">
                  <div class="rms-item"><span class="rms-label">已分配</span><b class="rms-green">{{ rewardDistResult.allocated_pct }}%</b></div>
                  <div class="rms-item"><span class="rms-label">未分配</span><b class="rms-orange">{{ rewardDistResult.unallocated }}</b></div>
                  <div class="rms-item"><span class="rms-label">节奏</span><b class="rms-blue">70/30</b></div>
                </div>
                <el-table :data="rewardDistResult.rows" size="small" border>
                  <el-table-column label="岗位角色" prop="role"/>
                  <el-table-column label="比例" width="80"><template slot-scope="s">{{ s.row.percentage }}%</template></el-table-column>
                  <el-table-column label="金额" width="120"><template slot-scope="s"><b class="rsc-amount">{{ s.row.amount }}</b></template></el-table-column>
                </el-table>
                <div v-if="rewardDistResult.payout_rhythm" class="reward-payout-info">
                  <div class="rpi-row"><span class="rpi-label">阶段一（交付 {{ rewardDistResult.payout_rhythm.phase1_pct }}%）</span><b>{{ rewardDistResult.payout_rhythm.phase1_deliver }}</b></div>
                  <div class="rpi-row"><span class="rpi-label">阶段二（验收 {{ rewardDistResult.payout_rhythm.phase2_pct }}%）</span><b>{{ rewardDistResult.payout_rhythm.phase2_accept }}</b></div>
                </div>
              </template>
              <template v-else-if="rewardSavedRows.length">
                <div class="reward-card-subtitle">已保存的分配</div>
                <el-table :data="rewardSavedRows" size="small" border>
                  <el-table-column label="岗位角色" prop="role_name"/>
                  <el-table-column label="比例" width="80"><template slot-scope="s">{{ s.row.percentage }}%</template></el-table-column>
                  <el-table-column label="金额" width="120"><template slot-scope="s"><b>{{ s.row.amount }}</b></template></el-table-column>
                </el-table>
              </template>
              <div v-else class="reward-empty-card">保存岗位比例后显示分配结果</div>
            </div>
          </div>
          <div v-if="!rewardProfile && !rewardLoading" class="reward-empty">暂无外包项目档案，请先在项目档案中填写到账收入等信息</div>
        </div>
      </div>
    </div>

    <!-- 筛选 -->
    <task-screening
      v-if="screeningShow"
      :work-id="work_id"
      :data="taskConditionObj"
      @change="taskScreeningChange"
      @close="screeningShow = false"/>

    <!-- 人员列表 -->
    <members
      :work-id="work_id"
      :list="membersList"
      :is-open="projectData.is_open"
      :permission="permission"
      :visible.sync="membersShow"
      @handle="membersHandle"/>

    <!-- 任务导入 -->
    <task-import
      :work-id="work_id"
      :show.sync="taskImportShow"
      @success="taskImportSuccess"
    />
  </div>
</template>

<script>
import {
  workWorkReadAPI,
  workWorkDeleteAPI,
  workWorkLeaveAPI,
  workWorkOwnerListAPI,
  workTaskExportAPI,
  workWorkArchiveAPI,
  workWorkAddUserGroupAPI
} from '@/api/pm/project'

import TaskBoard from './components/TaskBoard'
import TaskListBoard from './components/TaskListBoard'
import Attachment from './components/Attachment'
import TaskStatistical from './components/TaskStatistical'
import ArchivingTask from './components/ArchivingTask'
import ProjectSettings from './components/ProjectSettings'
import ProjectImplementationPanel from './components/ProjectImplementationPanel'
import TaskScreening from './components/TaskScreening'
import Members from './components/Members'
import TaskImport from '../components/TaskImport' // 任务导入
import MembersDep from '@/components/SelectEmployee/MembersDep'
import XrHeader from '@/components/XrHeader'
import { outsourceProjectReadAPI, outsourceDistributeSaveAPI, outsourceDistributeReadAPI, outsourceDictionaryAPI } from '@/api/work/outsource'

import { downloadExcelWithResData } from '@/utils'

export default {
  components: {
    TaskBoard,
    TaskListBoard,
    Attachment,
    TaskStatistical,
    ArchivingTask,
    ProjectSettings,
    ProjectImplementationPanel,
    TaskScreening,
    Members,
    TaskImport,
    MembersDep,
    XrHeader
  },

  filters: {
    showTypeName(value) {
      return {
        board: '看板视图',
        list: '列表视图',
        user: '负责人视图'
      }[value]
    }
  },

  data() {
    return {
      // 项目ID
      loading: false,
      detailLoading: false,
      detailError: '',
      memberError: '',
      // 奖金分配
      rewardLoading: false,
      rewardProfile: null,
      rewardForm: {},
      rewardRatios: [],
      rewardDistResult: null,
      rewardSavedRows: [],
      rewardSaving: false,
      rewardDictLoaded: false,
      tabShowType: 'board',
      work_id: '',
      projectName: '',
      projectColor: '',
      projectData: {
        is_open: 0
      },
      taskConditionObj: {
        userIds: [],
        timeId: '',
        tagIds: []
      },

      activeName: 'task-board',
      // 项目设置
      projectHandleShow: false,

      // 人员列表
      membersShow: false,
      membersList: [],
      // 是否显示筛选
      screeningButtonShow: true,
      screeningShow: false,
      // 任务导入展示
      taskImportShow: false,

      // 权限
      permission: {}
    }
  },

  computed: {
    // 展示项目设置按钮
    showSet() {
      return (this.permission.setTaskOwnerUser && this.projectData.is_open != 1) ||
      this.permission.setWork ||
      this.permission.excelImport ||
      this.permission.excelExport ||
      this.permission.archiveTask ||
      this.permission.deleteTask ||
      this.projectData.is_open == 0
    },

    // tabs 下内容视图的组件
    showComponent() {
      if (this.activeName == 'task-board') {
        if (this.tabShowType == 'list') {
          return 'TaskListBoard'
        }

        return this.activeName
      }

      return this.activeName
    },
    rewardRatioSum() { return this.rewardRatios.reduce((s, r) => s + (Number(r.percentage) || 0), 0) }
  },

  beforeRouteUpdate(to, from, next) {
    this.work_id = to.params.id
    // 项目切换：立即清理上一项目的所有展示数据与权限，避免跨项目串数据/闪烁
    this.resetProjectDisplay()
    // 从绩效页跳转时按 query 定位到"项目实施"Tab
    this.applyQueryTab(to.query)
    this.getDetail()
    this.getMemberList()
    next()
  },

  created() {
    this.activeName = 'task-board'
    // 当页面刷新时重新获取路由信息
    this.work_id = this.$route.params.id
    // 从绩效页跳转时按 query 定位到"项目实施"Tab
    this.applyQueryTab(this.$route.query)
    this.getDetail()
    this.getMemberList()
  },

  methods: {
    /**
     * 从绩效页跳转时按 query 定位到"项目实施"Tab 及子区域。
     * query: tab=project-implementation&section=milestone|contribution&source_id={id}
     */
    applyQueryTab(query) {
      if (query && query.tab === 'project-implementation') {
        this.activeName = 'project-implementation'
      }
    },
    goOutsource() {
      this.$router.push({ path: '/project/outsource', query: { work_id: this.work_id }})
    },
    resetProjectDisplay() {
      this.projectName = ''
      this.projectColor = ''
      this.projectData = { is_open: 0 }
      this.permission = {}
      this.membersList = []
      this.membersShow = false
      this.screeningShow = false
      this.taskConditionObj = { userIds: [], timeId: '', tagIds: [], search: '' }
      this.screeningButtonShow = true
      this.activeName = 'task-board'
      this.detailError = ''
      this.memberError = ''
      this.detailLoading = true
    },
    /**
     * 获取项目详情（捕获发起时的 work_id，响应返回时仅当 ID 仍匹配才写入，防止旧响应覆盖新项目）
     */
    getDetail() {
      const reqId = String(this.work_id)
      this.detailLoading = true
      this.detailError = ''
      workWorkReadAPI({
        work_id: this.work_id
      })
        .then(res => {
          // 快速切换项目时，丢弃过期响应，避免旧项目数据覆盖新项目
          if (String(this.work_id) !== reqId) return
          const data = res.data
          this.projectData = data
          this.projectColor = data.color
          this.projectName = data.name

          this.permission = data.auth || {}
          this.detailLoading = false
        })
        .catch(() => {
          if (String(this.work_id) !== reqId) return
          // 详情失败：清理展示并显示错误状态，不继续展示旧项目
          this.projectName = ''
          this.projectColor = ''
          this.projectData = { is_open: 0 }
          this.permission = {}
          this.detailLoading = false
          this.detailError = '项目详情加载失败，请重试'
        })
    },

    tabClick(val) {
      this.screeningButtonShow = this.activeName == 'task-board'
      if (this.activeName === 'reward-distribution') {
        this.loadRewardData()
      }
    },

    /**
     * 档案保存后联动：刷新详情 + 自动重新计算奖金分配
     */
    async onProfileSaved() {
      this.getDetail()
      // 如果已有保存的比例，自动重新计算分配（读取最新验收结果系数）
      if (this.rewardRatios.length && this.rewardRatios.some(r => r.percentage > 0)) {
        try {
          const r = await outsourceDistributeSaveAPI({ work_id: Number(this.work_id), ratios: JSON.stringify(this.rewardRatios) })
          this.rewardDistResult = r.data || null
          const dr = await outsourceDistributeReadAPI({ work_id: Number(this.work_id) })
          this.rewardSavedRows = (dr.data && dr.data.rows) || []
          if (r.data && r.data.result_coeff && r.data.result_coeff !== 1) {
            this.$message.success('档案已保存，奖金分配已按验收系数 ' + r.data.result_coeff + ' 重新计算')
          }
        } catch (e) { /* 静默：奖金分配需要外包档案，可能不存在 */ }
      }
    },

    async loadRewardData() {
      if (!this.work_id) return
      this.rewardLoading = true
      try {
        if (!this.rewardDictLoaded) {
          const dr = await outsourceDictionaryAPI({})
          const ddict = dr.data || dr
          this.rewardRatios = (ddict.default_distribution || []).map(x => ({ role: x.role, percentage: x.percentage }))
          this.rewardDictLoaded = true
        }
        const r = await outsourceProjectReadAPI({ work_id: Number(this.work_id) })
        const d = r.data || r
        this.rewardProfile = d.profile || null
        if (this.rewardProfile) {
          this.rewardForm = { revenue: Number(this.rewardProfile.revenue) || 0 }
        }
        // 读取已保存分配
        const dr2 = await outsourceDistributeReadAPI({ work_id: Number(this.work_id) })
        this.rewardSavedRows = (dr2.data && dr2.data.rows) || []
        if (this.rewardSavedRows.length) {
          this.rewardRatios = this.rewardSavedRows.map(x => ({ role: x.role_name, percentage: Number(x.percentage) }))
        }
      } catch (e) {
        /* 全局拦截器提示 */
      } finally {
        this.rewardLoading = false
      }
    },

    async saveRewardDist() {
      if (this.rewardRatioSum > 100) { this.$message.error('总比例超过100%'); return }
      this.rewardSaving = true
      try {
        const r = await outsourceDistributeSaveAPI({ work_id: Number(this.work_id), ratios: JSON.stringify(this.rewardRatios) })
        this.rewardDistResult = r.data || null
        this.$message.success('奖金分配已保存')
        const dr = await outsourceDistributeReadAPI({ work_id: Number(this.work_id) })
        this.rewardSavedRows = (dr.data && dr.data.rows) || []
      } catch (e) {
        /* 全局拦截器提示 */
      } finally {
        this.rewardSaving = false
      }
    },

    /**
     * 获取列表
     */
    getMemberList() {
      const reqId = String(this.work_id)
      this.memberError = ''
      workWorkOwnerListAPI({
        work_id: this.work_id
      })
        .then(res => {
          if (String(this.work_id) !== reqId) return
          this.membersList = res.data || []
          this.$bus.$emit('members-update', this.membersList)
        })
        .catch(() => {
          if (String(this.work_id) !== reqId) return
          // 成员加载失败：清空成员并显示状态，不残留上一项目成员
          this.membersList = []
          this.memberError = '成员加载失败'
        })
    },

    /**
     * 编辑成员
     */
    userSelectChange(members, dep) {
      workWorkAddUserGroupAPI({
        work_id: this.work_id,
        owner_user_id: members.map(item => item.id)
      })
        .then(res => {
          this.membersList = res.data || []
          this.$bus.$emit('members-update', this.membersList)
          this.$message.success('添加成功')
        })
        .catch(() => {})
    },

    /**
     * 删除项目
     */
    deleteProject() {
      this.$confirm(
        '确定要删除项目吗？删除后此项目中的所有任务将一并彻底删除，无法恢复',
        '提示',
        {
          confirmButtonText: '确定',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )
        .then(() => {
          workWorkDeleteAPI({ work_id: this.work_id })
            .then(res => {
              this.$message({
                type: 'success',
                message: '删除成功!'
              })
              this.$bus.$emit('delete-project', this.work_id)
              this.$router.go(-1)
            })
            .catch(() => {})
        })
        .catch(() => {
          this.$message({
            type: 'info',
            message: '已取消删除'
          })
        })
    },

    /**
     * 退出项目
     */
    exitProject() {
      this.$confirm('确认退出' + ' "' + this.projectName + '"', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      })
        .then(() => {
          workWorkLeaveAPI({ work_id: this.work_id })
            .then(res => {
              this.$message({
                type: 'success',
                message: '退出成功!'
              })
              this.$bus.$emit('delete-project', this.work_id)
              // 退出成功后跳转到确定的项目列表路由，避免回退到无权限页面或外部历史页面
              this.$router.replace('/project/list')
            })
            .catch(() => {
            })
        })
        .catch(() => {
          this.$message({
            type: 'info',
            message: '取消操作'
          })
        })
    },

    /**
     * 归档项目
     */
    archiveProject() {
      this.$confirm('确认归档项目' + ' "' + this.projectName + '"', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      })
        .then(() => {
          workWorkArchiveAPI({ work_id: this.work_id, status: 3 }) // 状态 1启用 2 删除 3归档
            .then(res => {
              this.$message({
                type: 'success',
                message: '归档成功'
              })
              this.$bus.$emit('delete-project', this.work_id)
              this.$router.go(-1)
            })
            .catch(() => {})
        })
        .catch(() => {
          this.$message({
            type: 'info',
            message: '取消操作'
          })
        })
    },

    /**
     * 项目设置更新数据
     */
    setSubmite(name, color, is_open) {
      if (this.projectData.is_open != is_open) {
        this.getDetail()
        this.getMemberList()
      } else {
        this.projectColor = color
        this.projectName = name
        this.$bus.$emit('project-setting', name, this.work_id)
      }
    },

    /**
     * 项目设置
     */
    projectSettingsHandle(type, data) {
      if (type == 'member') {
        this.getMemberList()
      }
    },

    /**
     * 成员设置
     */
    membersHandle(type, data) {
      if (type == 'member') {
        this.$bus.$emit('members-update', data)
        this.membersList = data
      }
    },

    /**
     * 审批导出
     */
    exportClick() {
      this.projectHandleShow = false
      this.loading = true
      workTaskExportAPI({
        work_id: this.work_id,
        search: this.taskConditionObj.search,
        owner_user_id: this.taskConditionObj.userIds,
        time_type: this.taskConditionObj.timeId,
        label_id: this.taskConditionObj.tagIds
      })
        .then(res => {
          downloadExcelWithResData(res)
          this.loading = false
        })
        .catch(() => {
          this.loading = false
        })
    },

    /**
     * 任务导入成功
     */
    taskImportSuccess() {
      this.$bus.$emit('work-task-import')
    },

    /**
     * 任务筛选
     */
    taskScreeningChange(userIds, timeId, tagIds, search) {
      this.taskConditionObj = {
        userIds,
        timeId,
        tagIds,
        search
      }
    }
  }
}
</script>

<style scoped lang="scss">
.project-list {
  height: 100%;
  overflow: hidden;
  .nav-box {
    margin-bottom: 15px;
    background: #fff;
    border-radius: $xr-border-radius-base;
    border: 1px solid $xr-border-color-base;
    .xr-header {
      padding: 10px 15px;
    }

    .ft-img {
      color: #999;
      cursor: pointer;
    }

    .ft-btn {
      margin-left: 25px;
      color: #999;
      cursor: pointer;
      .ft-img {
        margin-right: 2px;
      }
      .ft-label {
        font-size: 12px;
      }
    }

    .set-img {
      margin-left: 15px;
      font-size: 14px;
      color: #ccc;
      cursor: pointer;
    }

    .ft-btn:hover {
      color: $xr-color-primary;
      .ft-img  {
        color: $xr-color-primary;
      }
    }

    .ft-img:hover,
    .set-img:hover {
      color: $xr-color-primary;
    }

    .nav {
      margin-left: 64px;
      .el-tabs ::v-deep .el-tabs__header {
        margin-bottom: 0;
        .el-tabs__nav-wrap::after {
          height: 0;
        }
      }
    }
  }
  .content {
    height: calc(100% - 105px);
    overflow-y: auto;
    position: relative;
  }
  .content-inner {
    height: 100%;
    min-height: 120px;
  }
  .project-detail-error {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    color: #f56c6c;
    padding: 40px 0;
    i { font-size: 16px; }
  }
  .member-error-tip {
    color: #f56c6c;
    font-size: 12px;
    cursor: pointer;
    margin-left: 8px;
  }
  .reward-panel {
    width: 100%; max-width: 1440px; margin: 0 auto;
    padding: 20px 24px 32px; box-sizing: border-box;
  }

  /* 奖金池概览卡片 */
  .reward-pool-cards { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
  .reward-stat-card {
    flex: 1; min-width: 140px; min-height: 84px;
    background: #fff; border: 1px solid #ebeef5; border-radius: 6px;
    padding: 12px 16px; display: flex; flex-direction: column; justify-content: center;
  }
  .reward-stat-card.rsc-highlight { background: linear-gradient(135deg, #fff8e6 0%, #fff3d6 100%); border-color: #ffe58f; }
  .rsc-label { font-size: 12px; color: #909399; }
  .rsc-value { font-size: 22px; font-weight: 700; color: #303133; margin-top: 4px; word-break: break-all; }
  .rsc-amount { color: #e6a23c; }

  /* 双栏布局 */
  .reward-dual { display: flex; gap: 16px; align-items: flex-start; }
  .reward-card {
    background: #fff; border: 1px solid #ebeef5; border-radius: 6px; padding: 20px;
  }
  .reward-card-left { flex: 0 0 55%; min-width: 0; }
  .reward-card-right { flex: 1; min-width: 0; }
  .reward-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
  .reward-card-title { font-size: 15px; font-weight: 600; color: #303133; }
  .reward-card-subtitle { font-size: 13px; color: #606266; margin-bottom: 8px; }
  .reward-card-actions { border-top: 1px solid #ebeef5; margin-top: 12px; padding-top: 12px; display: flex; justify-content: flex-end; }

  /* 比例合计状态 */
  .reward-ratio-sum { font-size: 13px; font-weight: 600; }
  .rrs-ok { color: #67c23a; }
  .rrs-under { color: #e6a23c; }
  .rrs-over { color: #f56c6c; }

  /* 比例输入框容器 */
  .reward-ratio-input { display: flex; align-items: center; gap: 4px; }
  .reward-ratio-input .el-input-number { width: 130px; flex-shrink: 0; }
  .reward-ratio-input .el-input-number .el-input__inner { text-align: center; padding-left: 8px; padding-right: 30px; }
  .reward-ratio-unit { font-size: 14px; color: #606266; }

  /* 小统计块 */
  .reward-mini-stats { display: flex; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
  .rms-item { display: flex; flex-direction: column; }
  .rms-label { font-size: 12px; color: #909399; }
  .rms-green { color: #67c23a; font-size: 16px; }
  .rms-orange { color: #e6a23c; font-size: 16px; }
  .rms-blue { color: #409eff; font-size: 16px; }

  /* 发放节奏 */
  .reward-payout-info { background: #fafafa; border-radius: 4px; padding: 10px 14px; margin-top: 12px; }
  .rpi-row { display: flex; justify-content: space-between; align-items: center; padding: 4px 0; }
  .rpi-label { font-size: 13px; color: #606266; }

  /* 空状态 */
  .reward-empty-card { text-align: center; padding: 32px 16px; color: #909399; font-size: 13px; }
  .reward-empty { text-align: center; padding: 40px; color: #909399; }

  /* 计算规则卡片 */
  .reward-rules-card {
    background: #fff; border: 1px solid #ebeef5; border-radius: 6px;
    padding: 20px; margin-bottom: 16px;
  }
  .rr-step {
    display: flex; align-items: center; gap: 10px;
    padding: 6px 0; font-size: 13px; line-height: 1.6;
  }
  .rr-num {
    flex-shrink: 0; width: 22px; height: 22px; border-radius: 50%;
    background: #409eff; color: #fff; font-size: 12px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
  }
  .rr-text { color: #303133; min-width: 200px; }
  .rr-calc { color: #606266; font-size: 12px; }
  .rr-calc b { color: #303133; font-size: 13px; }
  .rr-calc-hint { color: #c0c4cc; font-style: italic; }

  /* 验收系数调整提示 */
  .reward-coeff-banner {
    display: flex; align-items: center; gap: 8px;
    background: #fdf6ec; border: 1px solid #f5dab1; border-radius: 4px;
    padding: 8px 14px; margin-bottom: 16px; font-size: 13px; color: #e6a23c;
  }
  .reward-coeff-banner b { color: #303133; font-size: 15px; }
  .rsc-coeff { background: #fdf6ec; border-color: #f5dab1; }

  /* 响应式 */
  @media (max-width: 1099px) {
    .reward-dual { flex-direction: column; }
    .reward-card-left, .reward-card-right { flex: 1; }
  }
  @media (max-width: 767px) {
    .reward-panel { padding: 12px; }
    .reward-pool-cards { flex-direction: column; }
    .reward-stat-card { min-width: 100%; }
  }
}
// 设置
.project-list-popover-btn-list {
  margin: 0 -12px;
  p {
    height: 40px;
    line-height: 40px;
    cursor: pointer;
    padding-left: 32px;
  }
  p:hover {
    background: #f7f8fa;
    color: #2362FB;
  }
}
.slide-fade-enter-active,
.slide-fade-leave-active {
  will-change: transform;
  transition: all 0.35s ease;
}
.slide-fade-enter,
.slide-fade-leave-to {
  transform: translateX(100%);
}

// 项目图
.wukong-subproject {
  font-size: 27px;
  margin-right: 8px;
}
</style>

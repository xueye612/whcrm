<template>
  <div class="my-task">
    <!-- 紧凑筛选工具栏 -->
    <div class="filter-toolbar">
      <el-input
        v-model="searchKeyword"
        placeholder="任务名称/描述"
        prefix-icon="el-icon-search"
        size="mini"
        clearable
        style="width:180px;flex-shrink:0"
        @input="debounceSearch"
        @clear="debounceSearch"/>
      <el-select v-model="quickFilter.status" placeholder="状态" clearable size="mini" style="width:100px;flex-shrink:0">
        <el-option label="未开始" value="1"/>
        <el-option label="进行中" value="2"/>
        <el-option label="已完成" value="5"/>
        <el-option label="已延期" value="delayed"/>
      </el-select>
      <el-select v-model="quickFilter.owner_user_id" placeholder="负责人" clearable filterable size="mini" style="width:120px;flex-shrink:0">
        <el-option v-for="u in memberOptions" :key="u.id" :label="u.realname" :value="u.id"/>
      </el-select>
      <el-select v-model="quickFilter.time_type" placeholder="时间" clearable size="mini" style="width:90px;flex-shrink:0">
        <el-option label="今天" value="today"/>
        <el-option label="明天" value="tomorrow"/>
        <el-option label="本周" value="week"/>
        <el-option label="本月" value="month"/>
        <el-option label="已逾期" value="overdue"/>
        <el-option label="未设置截止" value="nodeadline"/>
      </el-select>
      <el-select v-model="quickFilter.init_w" placeholder="工作量W" clearable size="mini" style="width:100px;flex-shrink:0">
        <el-option label="W1" value="W1"/>
        <el-option label="W2" value="W2"/>
        <el-option label="W3" value="W3"/>
        <el-option label="W4" value="W4"/>
        <el-option label="W5" value="W5"/>
      </el-select>
      <el-select v-model="quickFilter.init_r" placeholder="风险R" clearable size="mini" style="width:90px;flex-shrink:0">
        <el-option label="R1" value="R1"/>
        <el-option label="R2" value="R2"/>
        <el-option label="R3" value="R3"/>
        <el-option label="R4" value="R4"/>
        <el-option label="R5" value="R5"/>
      </el-select>
      <el-select v-model="quickFilter.init_k" placeholder="专业确认K" clearable size="mini" style="width:110px;flex-shrink:0">
        <el-option label="K1" value="K1"/>
        <el-option label="K2" value="K2"/>
        <el-option label="K3" value="K3"/>
        <el-option label="K4" value="K4"/>
      </el-select>
      <el-radio-group v-model="mineScope" size="mini" class="scope-segment">
        <el-radio-button label="all">全部</el-radio-button>
        <el-radio-button label="mine">我负责的</el-radio-button>
        <el-radio-button label="participate">我参与的</el-radio-button>
      </el-radio-group>
      <el-button size="mini" type="primary" @click="getList">查询</el-button>
      <el-button size="mini" @click="resetQuickFilter">重置</el-button>
      <el-button size="mini" icon="el-icon-setting" @click="screeningShow = true">更多筛选<span v-if="screeningCount"> {{ screeningCount }}</span></el-button>
      <el-tooltip content="显示/隐藏已完成任务" placement="bottom">
        <span class="completed-toggle">
          <el-switch v-model="showCompleted" active-text="已完成" inactive-text="" />
        </span>
      </el-tooltip>
      <span v-if="activeFilterCount" class="filter-count-badge">{{ activeFilterCount }} 项</span>
      <div class="toolbar-right">
        <el-dropdown trigger="click" @command="filterClick">
          <span class="toolbar-dropdown-link">
            <i class="el-icon-sort" /> {{ filterObj[filterValue.sort] }}<i class="el-icon-arrow-down el-icon--right"/>
          </span>
          <el-dropdown-menu slot="dropdown">
            <div class="el-dropdown-title">排序</div>
            <el-dropdown-item
              v-for="(item, index) in filterList"
              :key="index"
              :command="item.value">{{ item.label }}</el-dropdown-item>
            <div class="el-dropdown-footer">
              已完成排最后<el-switch
                v-model="filterValue.completed_task"
                @change="getList"/>
            </div>
          </el-dropdown-menu>
        </el-dropdown>
        <el-dropdown trigger="click" @command="moreAction">
          <span class="toolbar-dropdown-link">
            <i class="el-icon-more" />
          </span>
          <el-dropdown-menu slot="dropdown">
            <el-dropdown-item command="export" icon="el-icon-download">导出任务</el-dropdown-item>
          </el-dropdown-menu>
        </el-dropdown>
      </div>
    </div>

    <!-- 看板区域 -->
    <div class="kanban-area">
      <div
        v-loading="loading"
        class="kanban-columns">
        <div
          v-for="(item, index) in displayTaskList"
          :key="index"
          class="board-column">
          <div class="board-column-wrapper">
            <div class="board-column-header">
              <div class="col-header-row">
                <span class="col-title">{{ item.title }}</span>
                <el-tooltip :content="'已完成 ' + item.checkedNum + ' 项，共 ' + item.list.length + ' 项'" placement="top">
                  <span class="col-count">{{ item.checkedNum }} / {{ item.list.length }}</span>
                </el-tooltip>
              </div>
              <el-progress
                :percentage="item.list.length ? Math.round(item.checkedNum / item.list.length * 100) : 0"
                :stroke-width="3"
                :show-text="false"/>
            </div>
            <draggable
              :list="item.list"
              :options="{ group: 'mission', forceFallback: false, dragClass: 'sortable-drag' }"
              :id="index"
              class="board-column-content"
              @end="moveEndTask">
              <div v-if="!item.list.length" class="col-empty-hint">暂无任务</div>
              <div
                v-for="(element, i) in item.list"
                ref="taskRow"
                :key="i"
                :class="element.checked ? 'board-item board-item-active' : 'board-item'"
                :style="{'border-color': getPriorityColor(element.priority).color }"
                @click="showDetailView(element, index, i)">
                <div class="card-row-1">
                  <div class="card-check" @click.stop>
                    <el-checkbox
                      v-if="!element.workflow_version || element.workflow_version < 2"
                      v-model="element.checked"
                      @change="checkboxChange(element, item)"/>
                    <el-tooltip v-else content="工作流任务需在任务详情中推进状态" placement="top">
                      <span class="wf-v2-lock">
                        <i class="el-icon-lock"/>
                      </span>
                    </el-tooltip>
                  </div>
                  <el-tooltip
                    :content="element.name"
                    :disabled="!element.name || element.name.length < 20"
                    placement="top"
                    effect="dark">
                    <span class="card-title">{{ element.name }}</span>
                  </el-tooltip>
                  <div class="card-status-slot">
                    <span v-if="element.checked && (!element.workflow_version || element.workflow_version < 2)" class="task-done-tag">已完成</span>
                    <span v-if="element.mainStatus" class="task-wf-status">{{ element.mainStatus }}</span>
                    <xr-avatar
                      v-if="element.main_user"
                      :name="element.main_user.realname"
                      :id="element.main_user.id"
                      :size="26"
                      :src="element.main_user.img"
                      :disabled="false"
                      trigger="hover"
                      class="card-avatar" />
                    <span v-else class="card-no-user" title="未分配负责人">
                      <i class="el-icon-user" style="color:#dcdfe6;font-size:16px" />
                    </span>
                  </div>
                </div>
                <div class="card-row-2">
                  <span v-if="element.sourceType" class="task-source-tag">{{ element.sourceType }}</span>
                  <el-tooltip v-if="(element.initW || element.init_w) || (element.initR || element.init_r) || (element.initK || element.init_k)" :content="wrkTooltip(element)" placement="top">
                    <span class="task-wr-compact">
                      {{ [element.initW || element.init_w, element.initR || element.init_r, element.initK || element.init_k].filter(Boolean).join(' \u00B7 ') }}
                    </span>
                  </el-tooltip>
                  <span v-if="element.stop_time" :class="deadlineClass(element)" class="card-deadline">
                    {{ deadlineText(element) }}
                  </span>
                  <span v-if="element.commentcount" class="card-meta-item">
                    <i class="wukong wukong-comment-task"/>{{ element.commentcount }}
                  </span>
                  <span v-if="element.filecount" class="card-meta-item">
                    <i class="wukong wukong-file"/>{{ element.filecount }}
                  </span>
                  <span v-if="element.childAllCount > 0 || element.subcount > 0" class="card-meta-item">
                    <i class="wukong wukong-sub-task"/>{{ element.childWCCount || element.subdonecount }}/{{ element.childAllCount || (element.subdonecount + element.subcount) }}
                  </span>
                </div>
              </div>
            </draggable>
            <div v-if="!showCompleted && hiddenCount(item, index) > 0" class="col-hidden-hint">
              已隐藏 {{ hiddenCount(item, index) }} 条已完成任务
            </div>
            <task-quick-add
              :params="{ is_top: item.is_top }"
              show-style="hideBorder"
              class="col-quick-add"
              @send="getList" />
          </div>
        </div>
      </div>
    </div>

    <!-- 详情 -->
    <task-detail
      v-if="taskDetailShow"
      ref="particulars"
      :id="task_iD"
      :detail-index="detailIndex"
      :detail-section="detailSection"
      :no-listener-class="['board-item']"
      @on-handle="detailHandle"
      @close="closeBtn"/>

    <!-- 筛选 -->
    <task-screening
      v-if="screeningShow"
      :props="screeningProps"
      :data="screeningValue"
      @change="taskScreeningChange"
      @close="screeningShow = false"/>
  </div>
</template>
<script>
import {
  workTaskMyTaskAPI,
  workTaskUpdateTopAPI,
  taskWorkbenchExportAPI,
  workQueryMemberListAPI
} from '@/api/pm/task'
import { workTaskStatusSetAPI } from '@/api/pm/projectTask'

import TaskQuickAdd from '@/views/taskExamine/task/components/TaskQuickAdd'
import TaskDetail from '@/views/taskExamine/task/components/TaskDetail'
import TaskScreening from '../project/components/TaskScreening'

import draggable from 'vuedraggable'
import scrollx from '@/directives/scrollx'
import TaskMixin from '@/views/taskExamine/task/mixins/TaskMixin'
import { downloadExcelWithResData } from '@/utils'


export default {
  components: {
    draggable,
    TaskQuickAdd,
    TaskDetail,
    TaskScreening
  },

  directives: {
    scrollx
  },

  mixins: [TaskMixin],

  errorCaptured(err, vm, info) {
    console.error('[Workbench] child component error:', err.message, info)
    return false
  },

  data() {
    return {
      // 任务设置
      taskHandleShow: false,

      taskList: [],
      loading: true,
      filterObj: {},
      filterValue: {
        sort: 2,
        completed_task: true
      },
      filterList: [{
        label: '按手动拖拽',
        value: 1
      }, {
        label: '按最近创建',
        value: 2
      }, {
        label: '按最近截止',
        value: 3
      }, {
        label: '按最近更新',
        value: 4
      }, {
        label: '按最高优先级',
        value: 5
      }],
      searchKeyword: '',
      mineScope: 'all',
      showCompleted: false,
      quickFilter: {
        status: '',
        owner_user_id: '',
        time_type: '',
        init_w: '',
        init_r: '',
        init_k: ''
      },
      memberOptions: [],
      searchTimer: null,
      screeningValue: {
        userIds: [],
        timeId: '',
        tagIds: []
      },
      screeningShow: false,
      screeningProps: {
        searchShow: false,
        userRequest: workQueryMemberListAPI
      },

      // 详情数据
      task_iD: '',
      detailIndex: -1,
      detailSection: -1,
      taskDetailShow: false
    }
  },

  computed: {
    activeFilterCount() {
      var c = 0
      if (this.searchKeyword) c++
      if (this.quickFilter.status) c++
      if (this.quickFilter.owner_user_id) c++
      if (this.quickFilter.time_type) c++
      if (this.quickFilter.init_w) c++
      if (this.quickFilter.init_r) c++
      if (this.quickFilter.init_k) c++
      if (this.mineScope !== 'all') c++
      if (this.screeningValue.userIds && this.screeningValue.userIds.length) c++
      if (this.screeningValue.tagIds && this.screeningValue.tagIds.length) c++
      return c
    },
    screeningCount() {
      var c = 0
      if (this.screeningValue.userIds && this.screeningValue.userIds.length) c++
      if (this.screeningValue.tagIds && this.screeningValue.tagIds.length) c++
      if (this.screeningValue.timeId) c++
      return c
    },
    displayTaskList() {
      if (this.showCompleted) return this.taskList
      return this.taskList.map(function(col) {
        return {
          title: col.title,
          is_top: col.is_top,
          checkedNum: col.checkedNum,
          list: col.list.filter(function(t) { return !t.checked })
        }
      })
    }
  },

  watch: {
    '$route.query.task_id'(val) {
      if (val) {
        this.task_iD = String(val)
        this.detailIndex = -1
        this.detailSection = -1
        this.taskDetailShow = true
      } else if (this.taskDetailShow && !this._manualClose) {
        this.taskDetailShow = false
      }
    }
  },

  created() {
    this.filterList.forEach(item => {
      this.filterObj[item.value] = item.label
    })
    this.getList()
    this.loadMembers()
    this.openTaskFromRoute()
  },

  mounted() {
    // 为了防止火狐浏览器拖拽的时候以新标签打开，此代码真实有效
    document.body.ondrop = function(event) {
      event.preventDefault()
      event.stopPropagation()
    }
  },

  methods: {
    wrkTooltip(element) {
      var parts = []
      var w = element.initW || element.init_w
      var r = element.initR || element.init_r
      var k = element.initK || element.init_k
      if (w) parts.push(w + '：工作量')
      if (r) parts.push(r + '：风险')
      if (k) parts.push(k + '：专业确认')
      return parts.join('； ')
    },
    hiddenCount(item, index) {
      var original = this.taskList[index]
      if (!original) return 0
      return original.list.filter(function(t) { return t.checked }).length
    },
    deadlineText(element) {
      if (!element.stop_time) return ''
      var ts = element.stop_time
      var d = new Date(typeof ts === 'number' ? ts * 1000 : ts)
      if (isNaN(d.getTime())) return ''
      var now = new Date()
      var yy = d.getFullYear()
      var mm = String(d.getMonth() + 1).padStart(2, '0')
      var dd = String(d.getDate()).padStart(2, '0')
      var dateStr = yy === now.getFullYear() ? (mm + '-' + dd) : (yy + '-' + mm + '-' + dd)
      var isOverdue = element.is_end == 1 && !element.checked
      if (isOverdue) return '逾期 ' + dateStr
      return dateStr + ' 截止'
    },
    deadlineClass(element) {
      if (!element.stop_time) return ''
      if (element.is_end == 1 && !element.checked) return 'deadline-overdue'
      var ts = element.stop_time
      var d = new Date(typeof ts === 'number' ? ts * 1000 : ts)
      var now = new Date()
      var diff = (d.getTime() - now.getTime()) / 86400000
      if (diff >= 0 && diff <= 3) return 'deadline-near'
      return ''
    },
    /**
     * 搜索操作
     */
    debounceSearch() {
      if (this.searchTimer) clearTimeout(this.searchTimer)
      var self = this
      this.searchTimer = setTimeout(function() {
        self.getList()
      }, 400)
    },

    resetQuickFilter() {
      this.searchKeyword = ''
      this.mineScope = 'all'
      this.quickFilter = { status: '', owner_user_id: '', time_type: '', init_w: '', init_r: '', init_k: '' }
      this.screeningValue = { userIds: [], timeId: '', tagIds: [] }
      this.getList()
    },

    async loadMembers() {
      try {
        const res = await workQueryMemberListAPI()
        this.memberOptions = res.data || []
      } catch (e) { this.memberOptions = [] }
    },

    /**
     * 任务筛选
     */
    taskScreeningChange(userIds, timeId, tagIds) {
      this.screeningValue = {
        userIds,
        timeId,
        tagIds
      }
      this.getList()
    },

    /**
     * 筛选操作
     */
    filterClick(command) {
      this.filterValue.sort = command
      this.getList()
    },

    moreAction(command) {
      if (command === 'export') this.exportClick()
    },

    /**
     * 获取数据列表
     */
    getList() {
      this.loading = true
      workTaskMyTaskAPI({
        search: this.searchKeyword,
        sort_field: this.filterValue.sort,
        completed_task: this.filterValue.completed_task,
        owner_user_id: this.quickFilter.owner_user_id ? [this.quickFilter.owner_user_id] : this.screeningValue.userIds,
        time_type: this.quickFilter.time_type || this.screeningValue.timeId,
        label_id: this.screeningValue.tagIds,
        status: this.quickFilter.status,
        init_w: this.quickFilter.init_w,
        init_r: this.quickFilter.init_r,
        init_k: this.quickFilter.init_k,
        only_mine: this.mineScope === 'mine' ? 1 : 0,
        only_participate: this.mineScope === 'participate' ? 1 : 0
      })
        .then(res => {
          for (const item of res.data) {
            item.checkedNum = 0
            item.showTaskAdd = false
            for (const i of item.list) {
              if (i.status == 5) {
                i.checked = true
                item.checkedNum += 1
              } else {
                i.checked = false
              }
            }
          }
          this.taskList = res.data
          this.loading = false
        })
        .catch(() => {
          this.loading = false
        })
    },

    /**
     * 移动任务
     */
    moveEndTask(evt) {
      document.dispatchEvent(new MouseEvent('mouseup'))
      if (evt) {
        const fromTop = evt.from.id
        const toTop = evt.to.id

        // 如果没有进行移动 不做处理
        if (fromTop == toTop && evt.oldIndex == evt.newIndex) {
          return
        }

        const fromTask = this.taskList[fromTop]
        const fromlist = fromTask.list
        this.updateTaskListCheckNum(fromTask)

        const toTask = this.taskList[toTop]
        const tolist = toTask.list
        this.updateTaskListCheckNum(toTask)

        let params = {}
        if (fromTop == toTop) {
          params = {
            tolist: tolist.map(item => {
              return item.task_id
            }),
            to_top_id: toTop
          }
        } else {
          params = {
            fromlist: fromlist.map(item => {
              return item.task_id
            }),
            from_top_id: fromTop,
            tolist: tolist.map(item => {
              return item.task_id
            }),
            to_top_id: toTop
          }
        }
        workTaskUpdateTopAPI(params)
          .then(res => {})
          .catch(() => {})
      }
    },


    /**
     * 更新勾选数字
     */
    updateTaskListCheckNum(task) {
      task.checkedNum = task.list.filter(item => {
        return item.checked
      }).length
    },

    /**
     * 勾选完成任务
     */
    async checkboxChange(element, value) {
      if (element.checked) {
        value.checkedNum++
      } else {
        value.checkedNum--
      }
      const status = element.checked ? 5 : 1
      const syncChoice = await this.confirmLedgerSyncChoice(status)
      if (syncChoice === null) {
        if (element.checked) {
          value.checkedNum--
        } else {
          value.checkedNum++
        }
        element.checked = !element.checked
        return
      }

      workTaskStatusSetAPI({
        task_id: element.task_id,
        status: status,
        sync_ledger_status: syncChoice
      })
        .then(res => {})
        .catch(() => {
          if (element.checked) {
            value.checkedNum--
          } else {
            value.checkedNum++
          }
          element.checked = !element.checked
        })
    },
    async confirmLedgerSyncChoice(status) {
      try {
        await this.$confirm(
          status === 5 ? '是否同步完成关联台账？' : '是否同步回退关联台账状态？',
          '任务台账联动',
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
    /**
     * 点击显示详情
     */
    showDetailView(val, seciton, index) {
      this.task_iD = val.task_id
      this.detailIndex = index
      this.detailSection = seciton
      this.taskDetailShow = true
    },

    /**
     * 从路由参数打开任务详情（台账转任务后跳转）
     */
    openTaskFromRoute() {
      const taskId = this.$route.query.task_id
      if (taskId) {
        this.task_iD = String(taskId)
        this.detailIndex = -1
        this.detailSection = -1
        this.taskDetailShow = true
      }
    },

    /**
     * 详情操作
     */
    detailHandle(data) {
      if (data.index == 0 || data.index) {
        // 是否完成勾选
        if (data.type == 'title-check') {
          const sectionItem = this.taskList[data.section]
          this.$set(sectionItem.list[data.index], 'checked', data.value)
          if (data.value) {
            sectionItem.checkedNum++
          } else {
            sectionItem.checkedNum--
          }
          this.$set(sectionItem, 'checkedNum', sectionItem.checkedNum)
        } else if (data.type == 'delete') {
          this.taskList[data.section].list.splice(data.index, 1)
        } else if (data.type == 'change-stop-time') {
          // 86399 一天总秒数减1
          const stop_time = new Date(data.value).getTime() / 1000 + 86399
          if (stop_time > new Date().getTime() / 1000) {
            this.taskList[data.section].list[data.index].is_end = false
          } else {
            this.taskList[data.section].list[data.index].is_end = true
          }
          this.taskList[data.section].list[data.index].stop_time = data.value
        } else if (data.type == 'change-priority') {
          this.taskList[data.section].list[data.index].priority = data.value.id
        } else if (data.type == 'change-name') {
          this.taskList[data.section].list[data.index].name = data.value
        } else if (data.type == 'change-comments') {
          const commentcount = this.taskList[data.section].list[data.index]
            .commentcount
          if (data.value == 'add') {
            this.taskList[data.section].list[data.index].commentcount =
              commentcount + 1
          } else {
            this.taskList[data.section].list[data.index].commentcount =
              commentcount - 1
          }
        } else if (data.type == 'change-sub-task') {
          this.taskList[data.section].list[data.index].childWCCount =
            data.value.subdonecount
          this.taskList[data.section].list[data.index].childAllCount =
            data.value.allcount
        } else if (data.type == 'change-main-user') {
          this.taskList[data.section].list[data.index].main_user = data.value
        } else if (data.type == 'change-label') {
          this.taskList[data.section].list[data.index].lableList = data.value
        }
      }
    },

    /**
     * 关闭详情页
     */
    closeBtn() {
      this._manualClose = true
      this.taskDetailShow = false
      if (this.$route.query.task_id) {
        const query = { ...this.$route.query }
        delete query.task_id
        this.$router.replace({ path: this.$route.path, query }).catch(() => {})
      }
      this.$nextTick(() => { this._manualClose = false })
    },

    /**
     * 审批导出
     */
    exportClick() {
      this.taskHandleShow = false
      this.loading = true
      taskWorkbenchExportAPI({
        is_top: 5,
        search: this.searchKeyword,
        sort_field: this.filterValue.sort,
        completed_task: this.filterValue.completed_task,
        owner_user_id: this.screeningValue.userIds,
        time_type: this.screeningValue.timeId,
        label_id: this.screeningValue.tagIds
      })
        .then(res => {
          downloadExcelWithResData(res)
          this.loading = false
        })
        .catch(() => {
          this.loading = false
        })
    }
  }
}
</script>

<style scoped lang="scss">
.my-task {
  --task-font-xs: 12px;
  --task-font-sm: 13px;
  --task-font-base: 14px;
  --task-font-lg: 16px;
  --task-font-xl: 18px;
  height: 100%;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  user-select: none;
}

/* ===== 筛选工具栏 ===== */
.filter-toolbar {
  flex-shrink: 0;
  height: 50px;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 14px;
  background: #fff;
  border-bottom: 1px solid #ebeef5;
  overflow-x: auto;
  white-space: nowrap;
  position: sticky;
  top: 0;
  z-index: 10;
  box-shadow: 0 1px 3px rgba(0,0,0,0.03);
  &::-webkit-scrollbar { height: 0; }

  /* Element UI override: all controls 14px, height 34px */
  ::v-deep .el-input__inner {
    font-size: var(--task-font-base);
    height: 34px;
    line-height: 34px;
  }
  ::v-deep .el-input { display: inline-flex; }
  ::v-deep .el-select .el-input__inner {
    font-size: var(--task-font-base);
    height: 34px;
    line-height: 34px;
  }
  ::v-deep .el-button {
    font-size: var(--task-font-base);
    height: 34px;
    padding: 0 14px;
  }
  ::v-deep .el-button--mini {
    font-size: var(--task-font-base);
    height: 34px;
    line-height: 1;
    padding: 0 14px;
  }
  ::v-deep .el-checkbox__label {
    font-size: var(--task-font-base);
    padding-left: 6px;
  }
  ::v-deep .el-radio-button__inner {
    font-size: var(--task-font-base);
    padding: 8px 12px;
    height: 34px;
    line-height: 18px;
  }
}
.toolbar-right {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-left: auto;
  flex-shrink: 0;
}
.toolbar-dropdown-link {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  cursor: pointer;
  font-size: var(--task-font-base);
  color: #606266;
  padding: 6px 10px;
  border-radius: 4px;
  white-space: nowrap;
  &:hover { background: #f5f7fa; color: #409eff; }
  i:first-child { font-size: var(--task-font-base); }
}
.completed-toggle {
  display: inline-flex;
  align-items: center;
  flex-shrink: 0;
  ::v-deep .el-switch__label {
    font-size: var(--task-font-base);
  }
}
.el-dropdown-title {
  font-size: var(--task-font-sm);
  color: #909399;
  padding: 4px 20px;
  border-bottom: 1px solid $xr-border-color-base;
}
.el-dropdown-menu__item {
  color: #333;
  font-size: var(--task-font-base);
}
.el-dropdown-footer {
  padding: 5px 20px 0;
  border-top: 1px solid $xr-border-color-base;
  font-size: var(--task-font-sm);
  .el-switch { margin-left: 5px; }
}
.filter-count-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 10px;
  background: #409EFF;
  color: #fff;
  font-size: var(--task-font-xs);
  flex-shrink: 0;
}

/* ===== 看板区域 ===== */
.kanban-area {
  flex: 1;
  min-height: 0;
  overflow: hidden;
  padding: 8px 0 0 0;
}
.kanban-columns {
  display: flex;
  gap: 10px;
  height: 100%;
  padding: 0 10px 8px;
  overflow-x: auto;
  overflow-y: hidden;
}
.board-column {
  flex: 1 1 0;
  min-width: 260px;
  max-width: 480px;
  height: 100%;
  display: flex;
}
.board-column-wrapper {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  border-radius: 8px;
  border: 1px solid #e8eaed;
  background: #F7F8FA;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

/* ===== 栏目头部 ===== */
.board-column-header {
  flex-shrink: 0;
  padding: 10px 14px 8px;
  border-bottom: 1px solid #ebeef5;
  background: #fff;
  .col-title {
    font-size: var(--task-font-lg);
    font-weight: 600;
    color: #303133;
  }
  .col-count {
    margin-left: 8px;
    font-size: var(--task-font-sm);
    color: #606266;
  }
  .el-progress {
    margin-top: 6px;
    ::v-deep .el-progress-bar { padding-right: 0; }
    ::v-deep .el-progress__text { display: none; }
  }
}
.col-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 4px;
}

/* ===== 任务卡片列表 ===== */
.board-column-content {
  flex: 1;
  min-height: 0;
  padding: 8px 8px;
  overflow-y: auto;
  &::-webkit-scrollbar { width: 5px; }
  &::-webkit-scrollbar-thumb { background: #dcdfe6; border-radius: 3px; }
}

.board-item {
  background: #fff;
  border-radius: 6px;
  border-left: 3px solid transparent;
  padding: 10px 12px;
  margin-bottom: 8px;
  min-height: 60px;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  transition: box-shadow 0.2s, transform 0.15s;
  &:hover {
    box-shadow: 0 3px 12px rgba(0,0,0,0.1);
    transform: translateY(-1px);
  }
}

.board-item-active {
  background: #fff;
  .card-title { color: #909399; }
}

/* 第一行 */
.card-row-1 {
  display: flex;
  align-items: flex-start;
  gap: 6px;
}
.card-check {
  flex-shrink: 0;
  padding-top: 3px;
  min-width: 28px;
  .el-icon-lock { color: #c0c4cc; font-size: var(--task-font-sm); }
}
.card-title {
  flex: 1;
  min-width: 0;
  font-size: var(--task-font-base);
  line-height: 20px;
  font-weight: 500;
  color: #303133;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  word-break: break-word;
  padding-right: 4px;
}
.card-status-slot {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 6px;
  padding-top: 1px;
}
.card-avatar {
  flex-shrink: 0;
}

/* 第二行 */
.card-row-2 {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px 8px;
  padding-left: 28px;
  padding-top: 6px;
  font-size: var(--task-font-sm);
  color: #606266;
  line-height: 18px;
}
.card-meta-item {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  font-size: var(--task-font-sm);
  color: #606266;
  i { font-size: var(--task-font-sm); }
}
.card-deadline {
  white-space: nowrap;
  font-size: var(--task-font-sm);
  color: #606266;
}
.deadline-overdue { color: #F56C6C !important; font-weight: 600; }
.deadline-near { color: #E6A23C; }

/* 空栏目 */
.col-empty-hint {
  text-align: center;
  padding: 24px 0;
  color: #c0c4cc;
  font-size: var(--task-font-sm);
}
.col-hidden-hint {
  flex-shrink: 0;
  text-align: center;
  padding: 6px 0;
  color: #c0c4cc;
  font-size: var(--task-font-xs);
}
.card-no-user {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: #f5f7fa;
  flex-shrink: 0;
}

/* 分段控件 */
.scope-segment {
  flex-shrink: 0;
  ::v-deep .el-radio-button__inner {
    padding: 8px 12px;
    font-size: var(--task-font-base);
    height: 34px;
    line-height: 18px;
  }
}

/* 添加任务 */
.col-quick-add {
  flex-shrink: 0;
  margin: 0 8px 8px;
}

.sortable-drag {
  background-color: white;
  box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.wukong { font-size: var(--task-font-sm); }
.wf-v2-lock { display: inline-block; width: 16px; }

/* 标签 */
.task-wf-status {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 3px;
  font-size: var(--task-font-xs);
  background: #e6f7ff;
  color: #1890ff;
  white-space: nowrap;
  flex-shrink: 0;
}
.task-done-tag {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 3px;
  font-size: var(--task-font-xs);
  background: #f0f0f0;
  color: #909399;
  white-space: nowrap;
  flex-shrink: 0;
}
.task-wr-compact {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: var(--task-font-sm);
  background: #e8eaf6;
  color: #3A5CCC;
  font-weight: 500;
  white-space: nowrap;
}
.task-overdue-tag {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 3px;
  font-size: var(--task-font-xs);
  background: #fff1f0;
  color: #f5222d;
  white-space: nowrap;
}
.task-source-tag {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 3px;
  font-size: var(--task-font-xs);
  background: #f6ffed;
  color: #52c41a;
  white-space: nowrap;
}
</style>

<template>
  <div
    ref="taskRow"
    class="list task-cell"
    @click="rowFun(data)">
    <div
      ref="listLeft"
      class="list-left">
      <div
        :class="data.checked ? 'title title-active' : 'title'"
        @click.stop>
        <el-checkbox
          v-model="data.checked"
          @change="taskOverClick(data)" />
      </div>
      <span
        :style="{ backgroundColor: priority.color }"
        class="priority">{{ priority.label }}</span>
      <el-tooltip
        placement="bottom"
        effect="light"
        popper-class="task-tooltip tooltip-change-border">
        <div slot="content">
          <span>{{ data.task_name ||data.name }}</span>
        </div>
        <span
          ref="itemSpan"
          :class="data.checked ? 'item-name-active' : 'item-name'">
          {{ data.task_name ||data.name }}
        </span>
      </el-tooltip>
    </div>
    <div class="list-right">
      <div class="meta-slot due-slot">
        <span
          v-if="data.stop_time||data.stopTime"
          :class="[ 'due-time', { 'is-past': data.is_end == 1 } ]">
          <i class="el-icon-time" />截止 {{ data.stop_time||data.stopTime }}
        </span>
        <span v-else class="empty-meta">—</span>
      </div>

      <div class="meta-slot tag-slot">
        <div
          v-if="data.lableList && data.lableList.length > 0"
          class="tag-box">
          <span
            v-for="(item, index) in showLabels"
            :key="index"
            :style="{'background': item.color}"
            class="k-name">{{ item.name }}</span>
          <el-tooltip
            v-if="hideShowLabels.length"
            placement="top"
            effect="light"
            popper-class="tooltip-change-border">
            <div
              slot="content"
              class="tooltip-content">
              <div
                v-for="(item, index) in hideShowLabels"
                :key="index"
                class="item-label"
                style="display: inline-block; margin-right: 10px;">
                <span
                  :style="{'background': item.color || '#ccc'}"
                  class="k-name"
                  style="border-radius: 3px; color: #FFF; padding: 3px 10px;">{{ item.name }}</span>
              </div>
            </div>
            <el-button class="more-btn" icon="el-icon-more"/>
          </el-tooltip>
        </div>
        <span v-else class="empty-meta">—</span>
      </div>

      <div class="meta-slot wrk-slot">
        <el-tooltip
          v-if="hasWrk"
          placement="top"
          effect="light"
          popper-class="tooltip-change-border">
          <div slot="content">{{ wrkTooltip(data) }}</div>
          <span class="task-wr-compact">{{ wrkValues.join(' · ') }}</span>
        </el-tooltip>
        <span v-else class="test-task-mark">不适用</span>
      </div>

      <div class="meta-slot img-group stats-slot">
        <div
          v-if="data.relationCount"
          class="img-box">
          <i class="wukong wukong-relevance" />
          <span>{{ data.relationCount }}</span>
        </div>
        <div
          v-if="data.subdonecount > 0 || data.subcount "
          class="img-box">
          <i class="wukong wukong-sub-task" />
          <span>{{ data.subdonecount }}/{{ data.subdonecount + data.subcount }}</span>
        </div>
        <div
          v-if="data.filecount"
          class="img-box">
          <i class="wukong wukong-file" />
          <span>{{ data.filecount }}</span>
        </div>
        <div
          v-if="data.commentcount||data.commentCount"
          class="img-box">
          <i class="wukong wukong-comment-task" />
          <span>{{ data.commentcount||data.commentCount }}</span>
        </div>
      </div>

      <div class="meta-slot owner-slot">
        <xr-avatar
          v-if="data.main_user && data.main_user.id"
          :name="data.main_user.realname"
          :id="data.main_user.id"
          :size="28"
          :src="data.main_user.img"
          :disabled="false"
          trigger="hover"
          class="user-img"
          @click.stop="" />
        <span v-else class="empty-meta">—</span>
      </div>
      <div class="meta-slot action-slot">
        <el-button
          class="detail-button"
          type="text"
          @click.stop="rowFun(data)">详情</el-button>
      </div>
    </div>
  </div>
</template>
<script type="text/javascript">
// API
import { workTaskStatusSetAPI } from '@/api/pm/projectTask'

import TaskMixin from '@/views/taskExamine/task/mixins/TaskMixin'

export default {
  name: 'TaskCell', // 任务cell
  components: {},
  mixins: [TaskMixin],
  props: {
    data: Object,
    dataIndex: Number,
    dataSection: Number
  },
  data() {
    return {}
  },
  computed: {
    priority() {
      if (this.data.priority == 0 || !this.data.priority) {
        return this.priorityList[3] // 默认读取 priorityList 返回
      }
      return this.getPriorityColor(this.data.priority)
    },

    showLabels() {
      if (this.data.lableList.length > 1) {
        return this.data.lableList.slice(0, 1)
      }
      return this.data.lableList
    },

    hideShowLabels() {
      if (this.data.lableList.length > 1) {
        return this.data.lableList.slice(1)
      }
      return []
    },

    wrkValues() {
      return [
        this.data.finalW || this.data.final_w || this.data.initW || this.data.init_w || 'W-',
        this.data.finalR || this.data.final_r || this.data.initR || this.data.init_r || 'R-',
        this.data.finalK || this.data.final_k || this.data.initK || this.data.init_k || 'K-'
      ]
    },

    hasWrk() {
      return !this.data.is_test_task
    }
  },
  watch: {},
  mounted() {},
  methods: {
    wrkTooltip(data) {
      var parts = []
      var w = data.finalW || data.final_w || data.initW || data.init_w
      var r = data.finalR || data.final_r || data.initR || data.init_r
      var k = data.finalK || data.final_k || data.initK || data.init_k
      if (!w && !r && !k) return 'W/R/K 尚未评估'
      if (w) parts.push(w + '：工作量')
      if (r) parts.push(r + '：风险')
      if (k) parts.push(k + '：专业确认')
      return parts.join('； ')
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
    // 列表标记任务
    async taskOverClick(val) {
      const status = val.checked ? 5 : 1
      const syncChoice = await this.confirmLedgerSyncChoice(status)
      if (syncChoice === null) {
        val.checked = !val.checked
        return
      }
      workTaskStatusSetAPI({
        task_id: val.task_id,
        status: status,
        sync_ledger_status: syncChoice
      })
        .then(res => {
          // this.$store.dispatch('GetOAMessageNum', 'task')
          this.$emit('on-handle', 'complete', this.data, this.dataIndex, this.dataSection)
        })
        .catch(() => {
          val.checked = false
        })
    },
    // 点击显示详情
    rowFun(val) {
      this.$emit('on-handle', 'view', this.data, this.dataIndex, this.dataSection)
    },
    onmouseoverFun(item) {
      if (
        this.$refs.itemSpan.offsetWidth >
        this.$refs.listLeft.offsetWidth - 21
      ) {
        this.$set(item, 'show', true)
      } else {
        this.$set(item, 'show', false)
      }
    }
  }
}
</script>
<style lang="scss" scoped>
@mixin v-align {
  vertical-align: middle;
}
@mixin cursor {
  cursor: pointer;
}
@mixin color9 {
  color: #999;
}
.task-wr-compact {
  display: inline-block;
  height: 20px;
  line-height: 20px;
  padding: 0 8px;
  border-radius: 3px;
  border: 1px solid #d9e5ff;
  background: #f4f7ff;
  color: #356ae6;
  margin: 0;
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
  @include v-align;
}
.popover-btn-group {
  margin: -12px;
  padding: 10px 0;
  p {
    font-size: 13px;
    height: 26px;
    line-height: 26px;
    padding-left: 20px;
    @include cursor;
  }
}
.popover-btn-group p:hover {
  background: #f7f8fa;
  color: #2362fb;
}

.list {
  padding: 0 14px;
  cursor: pointer;
  position: relative;
  min-height: 46px;
  line-height: 46px;
  display: flex;
  margin-top: 6px;
  border: 1px solid #edf0f5;
  border-radius: 6px;
  box-sizing: border-box;
  transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
  .header {
    margin-bottom: 15px;
    img {
      width: 32px;
      margin-right: 14px;
      vertical-align: middle;
    }
    .name-time {
      display: inline-block;
      vertical-align: middle;
      .time {
        color: #999;
        margin-top: 5px;
        font-size: 12px;
      }
    }
  }
  .title {
    cursor: pointer;
    color: #333;
    display: inline-block;
    .el-checkbox {
      padding-right: 7px;
    }
  }
  .title-active {
    color: #666;
    text-decoration: line-through;
    text-decoration-color: #aaa;
  }
  .img-group {
    color: #999;
    font-size: 12px;
    vertical-align: middle;
    display: inline-block;
    .img-box {
      display: inline-block;
      margin-right: 6px;
      .wukong {
        font-size: 12px;
      }
      .priority-btn {
        width: 68px;
        font-size: 12px;
        display: inline-block;
        text-align: center;
        border-radius: 10px;
        color: #fff;
        height: 16px;
        line-height: 16px;
      }
    }
  }
  .item-name-active {
    color: #8f8f8f;
    text-decoration: line-through;
  }
  .list-left,
  .list-right {
    display: inline-block;
  }
  .list-left {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    padding-right: 10px;

    .priority {
      color: white;
      font-size: 12px;
      padding: 2px 8px;
      border-radius: $xr-border-radius-base;
      margin-right: 20px;
    }
  }
  .list-right {
    display: grid;
    grid-template-columns: 138px 72px 100px 52px 48px 58px;
    flex-shrink: 0;
    align-items: center;
    .user-img {
      margin: 0 auto;
    }
    .tag-box {
      display: flex;
      align-items: center;
      justify-content: center;
      min-width: 0;
      .item-label {
        display: inline-block;
      }
      .k-name {
        display: inline-block;
        height: 20px;
        line-height: 20px;
        padding: 0 10px;
        border-radius: 3px;
        color: #fff;
        max-width: 60px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 12px;
      }
    }
  }
}

.more-btn {
  padding: 3px 8px;
  margin-right: 6px;
}

.list:hover {
  background: #fbfcff;
  border-color: #cbd8ff;
  box-shadow: 0 4px 12px rgba(38, 99, 235, 0.08);
  transform: translateY(-1px);
}

.meta-slot {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 0;
  line-height: normal;
}

.empty-meta {
  color: #c5cad3;
  font-size: 12px;
}

.test-task-mark {
  color: #909399;
  font-size: 12px;
}

.list:before {
  display: none;
}
.list:first-child:before {
  display: none;
}

// 截止时间
.due-time {
  color: #999999;
  font-size: 12px;
  padding: 2px 8px;
  border-radius: $xr-border-radius-base;
  background-color: #F1F1F1;
  max-width: 140px;
  overflow: hidden;
  line-height: 22px;
  text-overflow: ellipsis;
  white-space: nowrap;

  i {
    margin-right: 4px;
  }
}

.due-time.is-past {
  color: #F95A5A;
  background-color: #FFF2F2;
}

.detail-button {
  margin-left: 0;
  padding: 0;
  white-space: nowrap;
  flex-shrink: 0;

  i {
    margin-left: 3px;
  }
}

.tooltip-content {
  margin: 10px 10px 10px 0;
}
</style>

<template>
  <div
    v-loading="loading"
    class="attachment">
    <el-alert
      :closable="false"
      class="att-hint"
      type="info"
      title="本页汇总当前项目所有任务下的附件；如需上传，请进入具体任务详情后上传。"
      show-icon/>
    <!-- 接口失败：明确错误状态与重试入口 -->
    <div
      v-if="loadError"
      class="att-error">
      <span>{{ loadError }}</span>
      <el-button
        type="primary"
        size="mini"
        @click="getList(true)">重试</el-button>
    </div>
    <div class="attachment-body">
      <el-table
        :data="list"
        :height="tableHeight"
        :header-cell-style="headerRowStyle"
        :cell-style="cellStyle"
        align="center"
        header-align="center"
        stripe
        style="width: 100%;border: 1px solid #E6E6E6;">
        <template v-if="list.length">
          <el-table-column
            v-for="(item, index) in fieldList"
            :key="index"
            :prop="item.prop"
            :label="item.label"
            :width="item.width"
            show-overflow-tooltip/>
          <el-table-column
            label="操作"
            width="150">
            <template slot-scope="scope">
              <flexbox justify="center">
                <el-button
                  type="text"
                  title="预览"
                  @click.native="handleFile('preview', scope)">预览</el-button>
                <el-button
                  v-if="canManageFile"
                  type="text"
                  title="重命名"
                  @click.native="handleFile('edit', scope)">重命名</el-button>
                <el-button
                  v-if="canManageFile"
                  type="text"
                  title="删除"
                  style="color:#f56c6c"
                  @click.native="handleFile('delete', scope)">删除</el-button>
              </flexbox>
            </template>
          </el-table-column>
        </template>
      </el-table>
      <!-- 空状态 -->
      <div
        v-if="!loading && !loadError && !list.length"
        class="att-empty">
        暂无附件
      </div>
    </div>
    <el-dialog
      :append-to-body="true"
      :close-on-click-modal="false"
      :visible.sync="editDialog"
      title="重命名"
      width="30%">
      <el-form :model="editForm">
        <el-form-item
          label="新名称"
          label-width="100">
          <el-input
            v-model="editForm.name"
            autocomplete="off"/>
        </el-form-item>
      </el-form>
      <div
        slot="footer"
        class="dialog-footer">
        <el-button @click="editDialog = false">取 消</el-button>
        <el-button
          type="primary"
          @click="confirmEdit">确 定</el-button>
      </div>
    </el-dialog>
    <div class="p-contianer">
      <el-pagination
        :current-page="currentPage"
        :page-sizes="pageSizes"
        :page-size.sync="pageSize"
        :total="total"
        class="p-bar"
        background
        layout="prev, pager, next, sizes, total, jumper"
        @size-change="handleSizeChange"
        @current-change="handleCurrentChange"/>
    </div>
  </div>
</template>

<script>
import { crmFileDeleteAPI, crmFileUpdateAPI } from '@/api/common'
import { workWorkFileListAPI } from '@/api/pm/project'

import { fileSize } from '@/utils'

export default {

  props: {
    workId: [Number, String],
    // 项目权限对象（来自 work/work/read 的 auth），用于控制重命名/删除按钮
    permission: {
      type: Object,
      default: () => ({})
    }
  },
  data() {
    return {
      firstRequst: true,
      list: [],
      loading: false,
      loadError: '',
      // 分页
      currentPage: 1,
      pageSize: 15,
      pageSizes: [15, 30, 45, 60],
      total: 0,

      fieldList: [],
      tableHeight: document.documentElement.clientHeight - 250,
      /** 重命名 弹窗 */
      editDialog: false,
      /** 编辑信息 */
      editForm: { name: '', data: {}},
      /** resize 句柄，便于销毁时移除 */
      resizeHandler: null
    }
  },
  computed: {
    // 项目附件写入权限：具备「任务删除附件」或项目「管理」权限才允许重命名/删除
    canManageFile() {
      const p = this.permission || {}
      // 与后端 delete/update 实际许可条件完全一致：仅依据 deleteTaskFile
      return !!p.deleteTaskFile
    }
  },
  watch: {
    workId: function() {
      this.currentPage = 1
      this.list = []
      this.getList(true)
    }
  },

  mounted() {
    // 使用 addEventListener 而非覆盖 window.onresize，避免影响其它监听
    this.resizeHandler = () => {
      this.tableHeight = document.documentElement.clientHeight - 250
    }
    window.addEventListener('resize', this.resizeHandler)

    if (this.firstRequst) {
      this.firstRequst = false
      this.getList(true)
    } else {
      this.getList(false)
    }
  },

  beforeDestroy() {
    // 释放 resize 监听，防止内存泄漏
    if (this.resizeHandler) {
      window.removeEventListener('resize', this.resizeHandler)
      this.resizeHandler = null
    }
  },

  activated() {
    this.getList(false)
  },
  deactivated() {},

  created() {
    this.fieldList.push({ prop: 'name', width: '200', label: '附件名称' })
    this.fieldList.push({ prop: 'size', width: '120', label: '附件大小' })
    this.fieldList.push({
      prop: 'createName',
      width: '160',
      label: '上传人'
    })
    this.fieldList.push({
      prop: 'create_time',
      width: '180',
      label: '上传时间'
    })
  },

  methods: {
    /**
     * 更改每页展示数量
     */
    handleSizeChange(val) {
      this.pageSize = val
      this.getList(false)
    },

    /**
     * 更改当前页数
     */
    handleCurrentChange(val) {
      this.currentPage = val
      this.getList(false)
    },

    /**
     * 获取附件
     */
    getList(loading) {
      this.loading = loading
      this.loadError = ''
      workWorkFileListAPI({
        page: this.currentPage,
        limit: this.pageSize,
        work_id: this.workId
      })
        .then(res => {
          this.list = res.data.list.map(item => {
            item.size = fileSize(item.size)
            return item
          })
          this.total = res.data.totalRow
          this.loading = false
        })
        .catch(() => {
          this.loading = false
          this.loadError = '附件加载失败，请重试'
        })
    },

    /**
     * 通过回调控制表头style
     */
    headerRowStyle() {
      return { textAlign: 'center' }
    },

    /**
     * 通过回调控制style
     */
    cellStyle() {
      return { textAlign: 'center' }
    },

    /**
     * 编辑（重命名）提交
     */
    confirmEdit() {
      if (this.editForm.name) {
        // 项目附件重命名：携带 work_id/file_id/save_name，后端核验归属与 deleteTaskFile 权限
        crmFileUpdateAPI({
          file_id: this.editForm.data.row.file_id,
          name: this.editForm.name,
          save_name: this.editForm.data.row.save_name,
          work_id: this.workId
        })
          .then(res => {
            this.$message.success('编辑成功')
            this.editDialog = false
            var item = this.list[this.editForm.data.$index]
            item.name = this.editForm.name
          })
          .catch(() => {})
      }
    },

    /**
     * 操作
     */
    handleFile(type, item) {
      if (type === 'preview') {
        this.$bus.emit('preview-image-bus', {
          index: item.$index,
          data: this.list.map(function(mapItem) {
            return {
              url: mapItem.filePath || mapItem.url || mapItem.file_path,
              name: mapItem.name,
              save_name: mapItem.save_name
            }
          })
        })
      } else if (type === 'delete') {
        this.$confirm('您确定要删除该文件吗?', '提示', {
          confirmButtonText: '确定',
          cancelButtonText: '取消',
          type: 'warning'
        })
          .then(() => {
            // 项目附件删除：必须传递正确的 module=work_task + work_id，由后端再次核验归属与权限
            crmFileDeleteAPI({
              file_id: item.row.file_id,
              save_name: item.row.save_name,
              module: 'work_task',
              work_id: this.workId
            })
              .then(res => {
                this.list.splice(item.$index, 1)
                this.$message.success('删除成功')
              })
              .catch(() => {})
          })
          .catch(() => {
            this.$message({
              type: 'info',
              message: '已取消操作'
            })
          })
      } else {
        this.editForm.data = item
        this.editForm.name = item.row.name
        this.editDialog = true
      }
    }
  }
}
</script>

<style scoped lang="scss">
.attachment {
  background: #fff;
  .attachment-body {
    position: relative;
    overflow: hidden;
  }
}

.att-hint {
  margin-bottom: 10px;
}

.att-error {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 16px;
  color: #f56c6c;
}

.att-empty {
  text-align: center;
  color: #999;
  padding: 40px 0;
}

.el-table ::v-deep thead th {
  background-color: #f5f5f5;
  font-weight: 400;
  font-size: 12px;
}

.p-contianer {
  position: relative;
  background-color: white;
  height: 44px;
  border: 1px solid #e6e6e6;
  border-top: none;

  .p-bar {
    float: right;
    margin: 5px 100px 0 0;
    font-size: 14px !important;
  }
}

.el-table::before {
  display: none;
}
</style>

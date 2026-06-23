<template>
  <div v-loading="loading">
    <div class="content-title">
      <span>收支分类设置</span>
    </div>
    <div class="content-tabs">
      <el-tabs v-model="activeDirection" @tab-click="handleDirectionChange">
        <el-tab-pane name="income">
          <span slot="label">
            <i class="el-icon-arrow-up income-tab-icon" />
            <span>收入分类</span>
            <el-badge v-if="incomeCount > 0" :value="incomeCount" class="tab-badge" />
          </span>
        </el-tab-pane>
        <el-tab-pane name="expense">
          <span slot="label">
            <i class="el-icon-arrow-down expense-tab-icon" />
            <span>支出分类</span>
            <el-badge v-if="expenseCount > 0" :value="expenseCount" class="tab-badge" />
          </span>
        </el-tab-pane>
      </el-tabs>
    </div>
    <div class="content-body">
      <reminder class="reminder" content="您可以配置收支分类，支持父子级分类结构。收入分类和支出分类分别管理。" />
      <div class="type-tree">
        <div
          v-for="(item, index) in treeList"
          :key="index"
          :class="['type-item', { 'is-child': item.parent_id > 0 }]">
          <div class="type-item-content">
            <span v-if="item.parent_id > 0" class="type-indent">└─</span>
            <span class="type-name">{{ item.name }}</span>
            <el-tag :type="item.direction === 'income' ? 'success' : 'danger'" size="mini" style="margin-left: 8px;">
              {{ item.direction === 'income' ? '收入' : '支出' }}
            </el-tag>
            <span class="sort-label">排序：{{ item.sort || 0 }}</span>
            <el-tag :type="item.status == 1 ? 'success' : 'info'" size="mini">
              {{ item.status == 1 ? '启用' : '停用' }}
            </el-tag>
            <i
              class="wk wk-edit"
              @click="editItem(item)"/>
            <i
              class="el-icon-delete-solid"
              @click="deleteItem(item)"/>
          </div>
        </div>
      </div>
      <el-button
        type="text"
        @click="addItem">+添加{{ activeDirection === 'income' ? '收入' : '支出' }}分类</el-button>

      <el-dialog
        :title="title"
        :visible.sync="showCreate"
        append-to-body
        width="580px"
        class="finance-type-editor-dialog"
        @close="close"
      >
        <el-form ref="form" :model="form" :rules="rule" label-position="left" label-width="100px">
          <el-form-item label="父级分类">
            <el-select v-model="form.parent_id" clearable placeholder="选择父级分类（留空为顶级分类）">
              <el-option
                v-for="item in parentTypeOptions"
                :key="item.type_id"
                :label="item.name"
                :value="item.type_id" />
            </el-select>
          </el-form-item>
          <el-form-item label="分类名称" prop="name">
            <el-input v-model="form.name" placeholder="请输入分类名称" maxlength="100"/>
          </el-form-item>
          <el-form-item label="排序">
            <el-input-number v-model="form.sort" :min="0" :max="9999" />
          </el-form-item>
          <el-form-item label="状态">
            <el-radio-group v-model="form.status">
              <el-radio :label="1">启用</el-radio>
              <el-radio :label="0">停用</el-radio>
            </el-radio-group>
          </el-form-item>
        </el-form>
        <span slot="footer" class="dialog-footer">
          <el-button @click="showCreate = false">取 消</el-button>
          <el-button v-debounce="save" type="primary">确 定</el-button>
        </span>
      </el-dialog>
    </div>
  </div>
</template>

<script>
import {
  financeTypeListAPI,
  financeTypeAddAPI,
  financeTypeUpdateAPI,
  financeTypeDeleteAPI
} from '@/api/admin/other'
import {
  financeTypeSave,
  financeTypeUpdate,
  financeTypeDelete
} from '@/api/finance'
import Reminder from '@/components/Reminder'

export default {
  name: 'FinanceType',

  components: {
    Reminder
  },

  data() {
    return {
      loading: false,
      title: '新建收入分类',
      showCreate: false,
      activeDirection: 'income',
      form: {
        name: '',
        direction: 'income',
        parent_id: 0,
        sort: 0,
        status: 1,
        id: ''
      },
      rule: {
        name: [
          { required: true, message: '请输入分类名称', trigger: 'blur' },
          { min: 1, max: 100, message: '请输入1-100个字符', trigger: 'blur' }
        ]
      },
      list: [],
      treeList: [],
      incomeCount: 0,
      expenseCount: 0
    }
  },

  computed: {
    parentTypeOptions() {
      return this.getParentTypeOptions()
    }
  },

  created() {
    this.getDetail()
  },

  methods: {
    /**
     * 获取可选的父级分类（排除自己和自己的子级）
     */
    getParentTypeOptions() {
      const currentId = this.form.id
      return this.list.filter(item => {
        // 排除自己
        if (item.type_id === currentId) return false
        // 排除子级（避免循环引用）
        if (currentId && item.parent_id === currentId) return false
        // 只显示当前方向的分类
        if (item.direction !== this.activeDirection) return false
        // 只显示顶级分类作为父级
        return !item.parent_id || item.parent_id === 0
      })
    },
    /**
     * 获取详情
     */
    getDetail() {
      this.loading = true
      // 同时获取收入和支出分类的数量统计
      Promise.all([
        financeTypeListAPI({ direction: 'income', page: 1, limit: 1000 }),
        financeTypeListAPI({ direction: 'expense', page: 1, limit: 1000 }),
        financeTypeListAPI({ direction: this.activeDirection, page: 1, limit: 1000 })
      ]).then(([incomeRes, expenseRes, currentRes]) => {
        this.loading = false
        // 统计数量 - 使用 dataCount 字段，如果没有则使用 list.length
        const incomeData = incomeRes.data || {}
        const expenseData = expenseRes.data || {}
        this.incomeCount = incomeData.dataCount !== undefined ? incomeData.dataCount : (incomeData.list ? incomeData.list.length : 0)
        this.expenseCount = expenseData.dataCount !== undefined ? expenseData.dataCount : (expenseData.list ? expenseData.list.length : 0)
        // 设置当前方向的分类列表
        this.list = (currentRes.data && currentRes.data.list) || []
        this.buildTreeList()
      }).catch(() => {
        this.loading = false
        this.list = []
        this.treeList = []
        this.incomeCount = 0
        this.expenseCount = 0
      })
    },
    /**
     * 构建树形列表（按父级分组显示）
     */
    buildTreeList() {
      // 只显示当前方向的分类
      const all = (this.list || []).filter(item => {
        // 确保只显示当前选项卡方向的分类
        return item.direction === this.activeDirection
      })

      // 先按parent_id分组，再按sort排序
      const parents = all.filter(item => !item.parent_id || item.parent_id === 0)
        .sort((a, b) => (a.sort || 0) - (b.sort || 0))
      const children = all.filter(item => item.parent_id && item.parent_id > 0)
        .sort((a, b) => (a.sort || 0) - (b.sort || 0))

      // 构建树形结构：先显示父级，再显示其子级
      this.treeList = []
      parents.forEach(parent => {
        this.treeList.push(parent)
        // 添加该父级下的子级
        children.filter(child => child.parent_id === parent.type_id)
          .forEach(child => {
            this.treeList.push(child)
          })
      })
      // 添加没有父级的子级（数据异常情况）
      children.filter(child => {
        const parentExists = parents.some(p => p.type_id === child.parent_id)
        return !parentExists
      }).forEach(child => {
        this.treeList.push(child)
      })
    },

    /**
     * 切换方向
     */
    handleDirectionChange() {
      // 只重新加载当前方向的分类列表，数量统计保持不变
      this.loading = true
      financeTypeListAPI({ direction: this.activeDirection, page: 1, limit: 1000 })
        .then(res => {
          this.loading = false
          this.list = (res.data && res.data.list) || []
          this.buildTreeList()
        })
        .catch(() => {
          this.loading = false
          this.list = []
          this.treeList = []
        })
    },

    /**
     * 增加类型
     */
    addItem() {
      this.title = `新建${this.activeDirection === 'income' ? '收入' : '支出'}分类`
      this.form = {
        name: '',
        direction: this.activeDirection,
        parent_id: 0,
        sort: 0,
        status: 1,
        id: ''
      }
      this.showCreate = true
    },

    /**
     * 编辑类型
     */
    editItem(item) {
      // 如果编辑的分类方向与当前标签页不一致，切换标签页
      if (item.direction && item.direction !== this.activeDirection) {
        this.activeDirection = item.direction
        this.getDetail()
      }
      this.title = `编辑${item.direction === 'income' ? '收入' : '支出'}分类`
      this.form = {
        name: item.name,
        direction: item.direction || this.activeDirection,
        parent_id: item.parent_id || 0,
        sort: item.sort || 0,
        status: item.status !== undefined ? item.status : 1,
        id: item.type_id
      }
      this.showCreate = true
    },

    /**
     * 删除事项操作
     */
    deleteItem(item) {
      this.$confirm('此操作将永久删除分类, 是否继续?', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        financeTypeDelete({ id: item.type_id }).then(res => {
          this.$message({
            type: 'success',
            message: '删除成功!'
          })
          this.getDetail()
        }).catch(() => {})
      }).catch(() => {
        this.$message({
          type: 'info',
          message: '已取消删除'
        })
      })
    },

    /**
     * 保存操作
     */
    save() {
      this.form.direction = this.activeDirection
      this.$refs['form'].validate((valid) => {
        if (valid) {
          const payload = { ...this.form }
          if (payload.id) {
            // 更新时使用 id 作为参数
            const id = payload.id
            delete payload.id
            financeTypeUpdate({ id, ...payload })
              .then(res => {
                this.getDetail()
                this.showCreate = false
                this.$message.success('操作成功')
              })
              .catch(() => {
              })
          } else {
            // 新增时不需要 id
            delete payload.id
            financeTypeSave(payload)
              .then(res => {
                this.getDetail()
                this.showCreate = false
                this.$message.success('操作成功')
              })
              .catch(() => {
              })
          }
        }
      })
    },

    /**
     * 关闭时取消表单验证
     */
    close() {
      this.$refs['form'].resetFields()
    }
  }
}
</script>

<style rel="stylesheet/scss" lang="scss" scoped>
.content-title {
  padding: 10px 12px;
  border-bottom: 1px solid #e6e6e6;
}

.content-title > span {
  display: inline-block;
  height: 36px;
  line-height: 36px;
  margin-left: 8px;
}

.content-tabs {
  padding: 0 12px;
  border-bottom: 1px solid #e6e6e6;
}

.content-body {
  height: calc(100% - 57px);
  padding: 10px 12px 18px;
  overflow-y: auto;
}

.reminder {
  margin-bottom: 8px;
}

/* 事项布局 */
.input-item {
  margin-top: 20px;
  margin-bottom: 10px;
  height: 30px;
  align-items: center;
  .el-icon-delete-solid,.wk-edit {
    cursor: pointer;
    margin-left: 20px;
    display: none;
  }
}

.input-item:hover {
  .el-icon-delete-solid,.wk-edit {
    display: inline;
  }
}

.type-tree {
  margin-top: 8px;
}

.type-item {
  margin-bottom: 6px;
  padding: 8px 0;
  border-bottom: 1px solid #f0f0f0;

  &.is-child {
    background-color: #fafafa;
    padding-left: 20px;
  }

  .type-item-content {
    display: flex;
    align-items: center;
    gap: 12px;

    .type-indent {
      color: #999;
      font-size: 14px;
      margin-right: 4px;
    }

    .type-name {
      font-weight: 500;
      min-width: 120px;
    }

    .sort-label {
      color: #999;
      font-size: 12px;
    }

    .wk-edit,
    .el-icon-delete-solid {
      cursor: pointer;
      margin-left: 12px;
      display: none;
      color: #606266;

      &:hover {
        color: #409EFF;
      }
    }
  }

  &:hover .type-item-content .wk-edit,
  &:hover .type-item-content .el-icon-delete-solid {
    display: inline-block;
  }
}

.finance-type-editor-dialog ::v-deep .el-dialog {
  border-radius: 12px;
  overflow: hidden;
}

.finance-type-editor-dialog ::v-deep .el-dialog__header {
  padding: 16px 22px;
  border-bottom: 1px solid #e8eaed;
  background: #f8fafc;
}

.finance-type-editor-dialog ::v-deep .el-dialog__body {
  padding: 16px 22px 8px;
}

.finance-type-editor-dialog ::v-deep .el-dialog__footer {
  padding: 12px 22px;
  border-top: 1px solid #f0f2f5;
}

/* 选项卡图标和徽章样式 */
.income-tab-icon {
  color: #67C23A;
  margin-right: 4px;
}

.expense-tab-icon {
  color: #F56C6C;
  margin-right: 4px;
}

.tab-badge {
  margin-left: 8px;
}

/* 优化选项卡样式，让收入和支出更明显 */
::v-deep .el-tabs__item {
  font-weight: 500;

  &.is-active {
    color: #409EFF;
  }
}

::v-deep .el-tabs__item[name="income"] {
  &.is-active {
    color: #67C23A;
  }
}

::v-deep .el-tabs__item[name="expense"] {
  &.is-active {
    color: #F56C6C;
  }
}

@media (max-width: 768px) {
  .content-title,
  .content-tabs,
  .content-body {
    padding-left: 8px;
    padding-right: 8px;
  }
}
</style>

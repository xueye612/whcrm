<template>
  <div class="finance-type-panel">
    <div class="tab-operations">
      <el-button
        v-if="typeAuth.save"
        type="primary"
        size="mini"
        @click="openDialog()">新增类型</el-button>
    </div>
    <el-alert
      v-if="error"
      :title="`类型加载失败：${error}`"
      class="fetch-error"
      type="error"
      show-icon>
      <template #description>
        <el-button type="primary" size="mini" @click="load">重试</el-button>
      </template>
    </el-alert>
    <el-table
      v-loading="loading"
      :data="types"
      stripe
      border
      size="small"
      style="width: 100%">
      <el-table-column prop="name" label="名称" />
      <el-table-column prop="direction" label="方向" width="120">
        <template #default="{ row }">
          {{ directionLabel(row.direction) }}
        </template>
      </el-table-column>
      <el-table-column prop="remark" label="备注" min-width="200" />
      <el-table-column prop="create_time" label="创建时间" width="180" />
      <el-table-column label="操作" width="160">
        <template #default="{ row }">
          <el-button
            v-if="typeAuth.update"
            type="text"
            size="mini"
            @click="openDialog(row)">编辑</el-button>
          <el-button
            v-if="typeAuth.delete"
            type="text"
            size="mini"
            @click="confirmDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog
      :visible.sync="dialog.visible"
      title="收支类型"
      width="460px"
      class="finance-type-dialog">
      <el-form :model="dialog.form" label-width="100px">
        <el-form-item label="名称" prop="name">
          <el-input v-model="dialog.form.name" maxlength="200" />
        </el-form-item>
        <el-form-item label="方向" prop="direction">
          <el-select v-model="dialog.form.direction" placeholder="请选择">
            <el-option v-for="item in directionOptions" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="dialog.form.sort" :min="0" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="dialog.form.remark" />
        </el-form-item>
      </el-form>
      <div slot="footer" class="dialog-footer">
        <el-button @click="dialog.visible = false">取消</el-button>
        <el-button type="primary" @click="saveType">保存</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import {
  financeTypeIndex,
  financeTypeSave,
  financeTypeUpdate,
  financeTypeDelete
} from '@/api/finance'

export default {
  name: 'FinanceTypePanel',
  data() {
    return {
      types: [],
      loading: false,
      error: '',
      dialog: {
        visible: false,
        form: {
          name: '',
          direction: 'income',
          sort: 0,
          remark: ''
        },
        editingId: null
      },
      directionOptions: [
        { label: '收入', value: 'income' },
        { label: '支出', value: 'expense' }
      ]
    }
  },
  computed: {
    typeAuth() {
      const allAuth = this.$store.getters.allAuth || {}
      return (allAuth.finance && allAuth.finance.type) || {}
    }
  },
  mounted() {
    this.load()
  },
  methods: {
    directionLabel(value) {
      const option = this.directionOptions.find(item => item.value === value)
      return option ? option.label : value
    },
    openDialog(row) {
      if (row) {
        this.dialog.form = {
          name: row.name,
          direction: row.direction,
          sort: row.sort,
          remark: row.remark
        }
        this.dialog.editingId = row.type_id
      } else {
        this.dialog.form = {
          name: '',
          direction: 'income',
          sort: 0,
          remark: ''
        }
        this.dialog.editingId = null
      }
      this.dialog.visible = true
    },
    confirmDelete(row) {
      this.$confirm('确认删除该类型？', '提示', {
        type: 'warning'
      }).then(() => {
        financeTypeDelete({ id: row.type_id }).then(() => {
          this.$message.success('删除成功')
          this.load()
        })
      }).catch(() => {})
    },
    load() {
      this.loading = true
      this.error = ''
      financeTypeIndex({ page: 1, limit: 999 })
        .then(res => {
          this.types = (res.data && res.data.list) || []
        })
        .catch(err => {
          this.error = err.error || err.msg || err.message || '接口加载失败'
          this.types = []
        })
        .finally(() => {
          this.loading = false
        })
    },
    saveType() {
      if (!this.dialog.form.name) {
        this.$message.warning('名称为必填项')
        return
      }
      if (!this.dialog.form.direction) {
        this.$message.warning('请选择方向')
        return
      }
      const payload = { ...this.dialog.form }
      const request = this.dialog.editingId ? financeTypeUpdate : financeTypeSave
      if (this.dialog.editingId) {
        payload.id = this.dialog.editingId
      }
      request(payload).then(() => {
        this.dialog.visible = false
        this.load()
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.finance-type-panel {
  padding: 10px 12px;
  background: #f0f2f5;
  min-height: calc(100vh - 80px);
  .tab-operations {
    margin-bottom: 12px;
  }
  .fetch-error {
    margin-bottom: 12px;
  }
}

.finance-type-panel ::v-deep .el-table {
  border-radius: 8px;
  overflow: hidden;
}

.finance-type-panel ::v-deep .el-table th {
  padding: 8px 0;
}

.finance-type-panel ::v-deep .el-table td {
  padding: 8px 0;
}

.finance-type-dialog ::v-deep .el-dialog {
  border-radius: 12px;
  overflow: hidden;
}

.finance-type-dialog ::v-deep .el-dialog__header {
  padding: 16px 22px;
  border-bottom: 1px solid #e8eaed;
  background: #f8fafc;
}

.finance-type-dialog ::v-deep .el-dialog__body {
  padding: 16px 22px 8px;
}

.finance-type-dialog ::v-deep .el-dialog__footer {
  padding: 12px 22px;
  border-top: 1px solid #f0f2f5;
}

.dialog-footer {
  text-align: right;
}

@media (max-width: 768px) {
  .finance-type-panel {
    padding: 8px;
  }
}
</style>

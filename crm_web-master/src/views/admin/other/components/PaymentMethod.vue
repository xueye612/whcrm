<template>
  <div v-loading="loading">
    <div class="content-title">
      <span>支付方式设置</span>
    </div>
    <div class="content-body">
      <reminder class="reminder" content="您可以配置支付方式，用于收支记录中选择。" />
      <el-table :data="list" border stripe>
        <el-table-column prop="method_id" label="ID" width="80" />
        <el-table-column prop="name" label="支付方式名称" />
        <el-table-column prop="sort" label="排序" width="100" />
        <el-table-column label="状态" width="100">
          <template slot-scope="scope">
            <el-tag :type="scope.row.status == 1 ? 'success' : 'info'" size="mini">
              {{ scope.row.status == 1 ? '启用' : '停用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="150">
          <template slot-scope="scope">
            <el-button type="text" size="small" @click="editItem(scope.row)">编辑</el-button>
            <el-button type="text" size="small" style="color: #F56C6C" @click="deleteItem(scope.row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
      <el-button
        type="text"
        style="margin-top: 16px"
        @click="addItem">+添加支付方式</el-button>

      <el-dialog
        :title="title"
        :visible.sync="showCreate"
        append-to-body
        width="580px"
        @close="close"
      >
        <el-form ref="form" :model="form" :rules="rule" label-position="left" label-width="100px">
          <el-form-item label="支付方式名称" prop="name">
            <el-input v-model="form.name" placeholder="请输入支付方式名称" maxlength="50"/>
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
  financePaymentMethodIndex,
  financePaymentMethodSave,
  financePaymentMethodUpdate,
  financePaymentMethodDelete
} from '@/api/finance'
import Reminder from '@/components/Reminder'

export default {
  name: 'PaymentMethod',

  components: {
    Reminder
  },

  data() {
    return {
      loading: false,
      list: [],
      showCreate: false,
      title: '添加支付方式',
      form: {
        id: null,
        name: '',
        sort: 0,
        status: 1
      },
      rule: {
        name: [
          { required: true, message: '请输入支付方式名称', trigger: 'blur' }
        ]
      }
    }
  },

  mounted() {
    this.getDetail()
  },

  methods: {
    /**
     * 获取列表
     */
    getDetail() {
      this.loading = true
      financePaymentMethodIndex({
        page: 1,
        limit: 1000
      }).then(res => {
        this.list = (res.data && res.data.list) || []
      }).catch(() => {
        this.list = []
      }).finally(() => {
        this.loading = false
      })
    },

    /**
     * 添加事项操作
     */
    addItem() {
      this.title = '添加支付方式'
      this.form = {
        id: null,
        name: '',
        sort: 0,
        status: 1
      }
      this.showCreate = true
    },

    /**
     * 编辑事项操作
     */
    editItem(item) {
      this.title = '编辑支付方式'
      this.form = {
        id: item.method_id,
        name: item.name,
        sort: item.sort || 0,
        status: item.status
      }
      this.showCreate = true
    },

    /**
     * 删除事项操作
     */
    deleteItem(item) {
      this.$confirm('此操作将永久删除支付方式, 是否继续?', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        financePaymentMethodDelete({ id: item.method_id }).then(res => {
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
      this.$refs['form'].validate((valid) => {
        if (valid) {
          const payload = { ...this.form }
          if (payload.id) {
            // 更新时使用 id 作为参数
            const id = payload.id
            delete payload.id
            financePaymentMethodUpdate({ id, ...payload })
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
            financePaymentMethodSave(payload)
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
  padding: 10px;
  border-bottom: 1px solid #e6e6e6;
}

.content-title > span {
  display: inline-block;
  height: 36px;
  line-height: 36px;
  font-size: 16px;
  font-weight: 500;
}

.content-tabs {
  padding: 10px;
  border-bottom: 1px solid #e6e6e6;
}

.content-body {
  padding: 20px;
}

.reminder {
  margin-bottom: 20px;
}
</style>

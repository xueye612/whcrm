<template>
  <div class="finance-plan-panel">
    <div class="plan-filter">
      <el-input
        v-model="filters.customer_id"
        size="mini"
        placeholder="客户ID"
        style="width: 150px" />
      <el-select
        v-model="filters.type_id"
        size="mini"
        placeholder="类型"
        style="width: 160px">
        <el-option
          v-for="item in typeOptions"
          :key="item.type_id"
          :label="item.name"
          :value="item.type_id" />
      </el-select>
      <el-select
        v-model="filters.direction"
        size="mini"
        placeholder="方向"
        style="width: 120px">
        <el-option
          v-for="item in directionOptions"
          :key="item.value"
          :label="item.label"
          :value="item.value" />
      </el-select>
      <el-date-picker
        v-model="filters.dateRange"
        type="daterange"
        size="mini"
        start-placeholder="开始日期"
        end-placeholder="结束日期"
        style="width: 220px" />
      <el-button
        type="primary"
        size="mini"
        @click="loadPlans">查询</el-button>
      <el-button
        v-if="planAuth.save"
        type="success"
        size="mini"
        @click="openDialog">新增计划</el-button>
    </div>
    <el-alert
      v-if="error"
      :title="`计划加载失败：${error}`"
      class="fetch-error"
      type="error"
      show-icon>
      <template #description>
        <el-button type="primary" size="mini" @click="loadPlans">重试</el-button>
      </template>
    </el-alert>

    <el-table
      v-loading="loading"
      :data="plans"
      stripe
      border
      style="width: 100%">
      <el-table-column
        prop="plan_id"
        label="ID"
        width="90" />
      <el-table-column
        prop="customer_id"
        label="客户ID"
        width="110" />
      <el-table-column
        prop="customer_name"
        label="客户名称"
        width="140" />
      <el-table-column
        prop="type_id"
        label="类型"
        width="140">
        <template #default="{ row }">
          {{ typeLabel(row.type_id) }}
        </template>
      </el-table-column>
      <el-table-column
        prop="direction"
        label="方向"
        width="100">
        <template #default="{ row }">
          {{ directionLabel(row.direction) }}
        </template>
      </el-table-column>
      <el-table-column
        prop="plan_amount"
        label="计划金额"
        width="140" />
      <el-table-column
        prop="plan_date"
        label="计划时间"
        width="150" />
      <el-table-column
        prop="status"
        label="状态"
        width="120" />
      <el-table-column
        prop="handler_user_id"
        label="处理人"
        width="120" />
      <el-table-column
        prop="remark"
        label="备注"
        min-width="160" />
      <el-table-column
        label="操作"
        width="180">
        <template #default="{ row }">
          <el-button
            v-if="planAuth.update"
            type="text"
            size="mini"
            @click="openDialog(row)">编辑</el-button>
          <el-button
            v-if="planAuth.delete"
            type="text"
            size="mini"
            @click="confirmDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog
      :visible.sync="dialog.visible"
      title="收支计划"
      width="440px">
      <el-form
        :model="dialog.form"
        label-width="120px">
        <el-form-item label="客户ID" prop="customer_id">
          <el-input v-model="dialog.form.customer_id" />
        </el-form-item>
        <el-form-item label="类型" prop="type_id">
          <el-select
            v-model="dialog.form.type_id"
            placeholder="请选择类型">
            <el-option
              v-for="item in typeOptions"
              :key="item.type_id"
              :label="item.name"
              :value="item.type_id" />
          </el-select>
        </el-form-item>
        <el-form-item label="方向" prop="direction">
          <el-select v-model="dialog.form.direction">
            <el-option
              v-for="item in directionOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="计划金额" prop="plan_amount">
          <el-input-number
            v-model="dialog.form.plan_amount"
            :precision="2"
            :min="0" />
        </el-form-item>
        <el-form-item label="计划时间" prop="plan_date">
          <el-date-picker
            v-model="dialog.form.plan_date"
            type="date"
            placeholder="请选择日期" />
        </el-form-item>
        <el-form-item label="处理人" prop="handler_user_id">
          <el-input v-model="dialog.form.handler_user_id" />
        </el-form-item>
        <el-form-item label="备注" prop="remark">
          <el-input :rows="2" v-model="dialog.form.remark" type="textarea" />
        </el-form-item>
      </el-form>
      <div slot="footer" class="dialog-footer">
        <el-button @click="dialog.visible = false">取消</el-button>
        <el-button type="primary" @click="savePlan">保存</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import {
  financePlanIndex,
  financePlanSave,
  financePlanUpdate,
  financePlanDelete,
  financeTypeIndex
} from '@/api/finance'

export default {
  name: 'FinancePlanPanel',
  data() {
    return {
      plans: [],
      typeOptions: [],
      loading: false,
      error: '',
      filters: {
        customer_id: '',
        type_id: '',
        direction: '',
        dateRange: []
      },
      dialog: {
        visible: false,
        form: {
          customer_id: '',
          type_id: '',
          direction: 'income',
          plan_amount: 0,
          plan_date: '',
          handler_user_id: '',
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
    planAuth() {
      const allAuth = this.$store.getters.allAuth || {}
      return (allAuth.finance && allAuth.finance.plan) || {}
    }
  },
  mounted() {
    this.loadTypes()
    this.loadPlans()
  },
  methods: {
    directionLabel(value) {
      const option = this.directionOptions.find(item => item.value === value)
      return option ? option.label : value
    },
    typeLabel(value) {
      const item = this.typeOptions.find(it => it.type_id === value)
      return item ? item.name : value
    },
    loadTypes() {
      financeTypeIndex({ page: 1, limit: 999 }).then(res => {
        this.typeOptions = (res.data && res.data.list) || []
      })
    },
    loadPlans() {
      this.loading = true
      this.error = ''
      const map = {
        customer_id: this.filters.customer_id,
        type_id: this.filters.type_id,
        direction: this.filters.direction
      }
      if (this.filters.dateRange.length === 2) {
        map.start_date = this.filters.dateRange[0]
        map.end_date = this.filters.dateRange[1]
      }
      financePlanIndex({ page: 1, limit: 50, map })
        .then(res => {
          this.plans = (res.data && res.data.list) || []
        })
        .catch(err => {
          this.error = err.error || err.msg || err.message || '接口加载失败'
          this.plans = []
        })
        .finally(() => {
          this.loading = false
        })
    },
    openDialog(row) {
      if (row) {
        this.dialog.form = {
          customer_id: row.customer_id,
          type_id: row.type_id,
          direction: row.direction,
          plan_amount: row.plan_amount,
          plan_date: row.plan_date,
          handler_user_id: row.handler_user_id,
          remark: row.remark
        }
        this.dialog.editingId = row.plan_id
      } else {
        this.dialog.form = {
          customer_id: '',
          type_id: '',
          direction: 'income',
          plan_amount: 0,
          plan_date: '',
          handler_user_id: '',
          remark: ''
        }
        this.dialog.editingId = null
      }
      this.dialog.visible = true
    },
    savePlan() {
      if (!this.dialog.form.customer_id) {
        this.$message.warning('客户ID为必填项')
        return
      }
      if (!this.dialog.form.type_id) {
        this.$message.warning('请选择类型')
        return
      }
      if (!this.dialog.form.plan_amount || this.dialog.form.plan_amount <= 0) {
        this.$message.warning('计划金额需大于0')
        return
      }
      if (!this.dialog.form.plan_date) {
        this.$message.warning('请选择计划时间')
        return
      }
      const payload = { ...this.dialog.form }
      const request = this.dialog.editingId ? financePlanUpdate : financePlanSave
      if (this.dialog.editingId) {
        payload.id = this.dialog.editingId
      }
      request(payload).then(() => {
        this.dialog.visible = false
        this.loadPlans()
      })
    },
    confirmDelete(row) {
      if (!this.planAuth.delete) return
      this.$confirm('确认删除该计划吗？', '提示', { type: 'warning' })
        .then(() => {
          financePlanDelete({ id: row.plan_id }).then(() => {
            this.$message.success('删除成功')
            this.loadPlans()
          })
        })
        .catch(() => {})
    }
  }
}
</script>

<style lang="scss" scoped>
.finance-plan-panel {
  .plan-filter {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
  }
  .fetch-error {
    margin-bottom: 12px;
  }
}

.dialog-footer {
  text-align: right;
}
</style>

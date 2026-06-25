<template>
  <div class="ledger-form-core">
    <el-form-item label="问题标题" prop="title">
      <el-input v-model.trim="localForm.title" placeholder="请输入简洁的问题标题" />
    </el-form-item>
    <el-form-item label="问题内容" prop="description">
      <slot name="description">
        <el-input v-model="localForm.description" type="textarea" :rows="4" placeholder="请描述问题" />
      </slot>
    </el-form-item>
    <el-form-item label="问题分类">
      <el-select v-model="localForm.category" clearable placeholder="请选择" style="width: 100%">
        <el-option v-for="item in categoryOptions" :key="item" :label="item" :value="item" />
      </el-select>
    </el-form-item>
    <el-form-item label="处理状态">
      <el-select v-model="localForm.status" style="width: 100%">
        <el-option v-for="item in statusOptions" :key="item" :label="item" :value="item" />
      </el-select>
    </el-form-item>
  </div>
</template>

<script>
import { LEDGER_STATUS_OPTIONS } from '@/utils/ledgerFormat'

export default {
  name: 'LedgerFormCore',
  props: {
    value: {
      type: Object,
      default: () => ({})
    },
    categoryOptions: {
      type: Array,
      default: () => []
    }
  },
  data() {
    return {
      statusOptions: LEDGER_STATUS_OPTIONS
    }
  },
  computed: {
    localForm: {
      get() {
        return this.value
      },
      set(val) {
        this.$emit('input', val)
      }
    }
  }
}
</script>

<template>
  <div class="reward-page">
    <div class="reward-inner">
      <!-- 页面标题 -->
      <div class="rp-header">
        <div class="rp-header-left">
          <div class="rp-header-title">奖惩管理</div>
          <div class="rp-header-sub">管理奖励候选、处罚、审核与结算</div>
        </div>
        <div class="rp-header-right">
          <el-button v-if="activeTab==='records'" type="primary" icon="el-icon-plus" @click="openCreate">新建奖惩</el-button>
          <el-button v-if="activeTab==='rules'" type="primary" icon="el-icon-plus" @click="openRuleEdit(null)">新增项目</el-button>
          <el-button v-if="activeTab==='config'" :loading="configSaving" type="primary" icon="el-icon-check" @click="saveAllConfig">保存配置</el-button>
        </div>
      </div>

      <!-- Tab 导航 -->
      <div class="rp-tabs">
        <div
          v-for="t in tabs"
          :key="t.name"
          :class="['rp-tab', activeTab===t.name?'rp-tab-active':'']"
          @click="activeTab=t.name"
        >{{ t.label }}</div>
      </div>

      <!-- ===== Tab 1: 奖惩记录 ===== -->
      <div v-if="activeTab==='records'" class="rp-tab-body">
        <!-- 1. 统计卡片 -->
        <div class="rp-stats">
          <div class="rp-stat-card rp-stat-pending" @click="quickFilter('status','待审核')">
            <div class="rp-stat-icon"><i class="el-icon-time" /></div>
            <div class="rp-stat-main">
              <div class="rp-stat-num">{{ stats.pending }}<span class="rp-stat-unit"> 条</span></div>
              <div class="rp-stat-label">待审核</div>
            </div>
          </div>
          <div class="rp-stat-card rp-stat-approved" @click="quickFilter('status','已通过')">
            <div class="rp-stat-icon"><i class="el-icon-circle-check" /></div>
            <div class="rp-stat-main">
              <div class="rp-stat-num">{{ formatMoney(stats.approvedAmount) }}<span class="rp-stat-unit"> 元</span></div>
              <div class="rp-stat-label">已通过奖励</div>
            </div>
          </div>
          <div class="rp-stat-card rp-stat-penalty" @click="quickFilter('direction','penalty')">
            <div class="rp-stat-icon"><i class="el-icon-warning-outline" /></div>
            <div class="rp-stat-main">
              <div class="rp-stat-num">{{ formatMoney(Math.abs(stats.penaltyAmount)) }}<span class="rp-stat-unit"> 元</span></div>
              <div class="rp-stat-label">处罚金额</div>
            </div>
          </div>
          <div class="rp-stat-card rp-stat-total" @click="quickFilter('')">
            <div class="rp-stat-icon"><i class="el-icon-document" /></div>
            <div class="rp-stat-main">
              <div class="rp-stat-num">{{ total }}<span class="rp-stat-unit"> 条</span></div>
              <div class="rp-stat-label">总记录数</div>
            </div>
          </div>
        </div>

        <!-- 2. 筛选区域（独立卡片） -->
        <div class="rp-card rp-filter-card">
          <el-form :inline="true" size="small" class="rp-filter-form" @submit.native.prevent>
            <el-form-item label="日期范围">
              <el-date-picker
                v-model="filters.dates"
                type="daterange"
                value-format="yyyy-MM-dd"
                start-placeholder="开始日期"
                end-placeholder="结束日期"
                style="width:240px"
              />
            </el-form-item>
            <el-form-item label="人员">
              <el-select
                v-model="filters.user_id"
                :remote-method="searchUser"
                filterable
                remote
                clearable
                reserve-keyword
                placeholder="搜索人员"
                style="width:170px"
                @focus="searchUser('')"
              >
                <el-option
                  v-for="u in userOptions"
                  :key="u.id"
                  :label="u.realname + (udept(u) ? ' · ' + udept(u) : '')"
                  :value="u.id"
                />
              </el-select>
            </el-form-item>
            <el-form-item label="奖励/处罚">
              <el-select v-model="filters.direction" clearable placeholder="全部" style="width:110px">
                <el-option label="奖励" value="reward" />
                <el-option label="处罚" value="penalty" />
              </el-select>
            </el-form-item>
            <el-form-item label="状态">
              <el-select v-model="filters.status" clearable placeholder="全部" style="width:120px">
                <el-option v-for="s in dict.statuses" :key="s" :label="s" :value="s" />
              </el-select>
            </el-form-item>
            <el-form-item label="关键词">
              <el-input
                v-model.trim="filters.keyword"
                placeholder="人员 / 客户 / 事由"
                clearable
                style="width:180px"
                @keyup.enter.native="fetchList"
              />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="fetchList">查询</el-button>
              <el-button @click="resetFilters">重置</el-button>
            </el-form-item>
          </el-form>
          <div v-if="activeFilterTags.length" class="rp-filter-tags">
            <span class="rp-filter-tags-label">已生效筛选：</span>
            <el-tag
              v-for="(tag,i) in activeFilterTags"
              :key="i"
              closable
              size="small"
              @close="removeFilter(tag.key)"
            >{{ tag.text }}</el-tag>
            <el-button type="text" size="small" @click="resetFilters">清空</el-button>
          </div>
        </div>

        <!-- 3. 记录列表 -->
        <div class="rp-card rp-table-card">
          <div class="rp-table-toolbar">
            <div class="rp-table-title">
              <span>奖惩记录</span>
              <span class="rp-table-count">共 {{ total }} 条</span>
            </div>
            <div class="rp-table-actions">
              <el-button size="small" icon="el-icon-refresh" @click="fetchList">刷新</el-button>
              <el-button v-if="isRewardAdmin" size="small" @click="batchCreate">生成结算批次</el-button>
            </div>
          </div>
          <el-table
            v-loading="loading"
            :data="list"
            :header-cell-style="{ background:'#fafbfc', color:'#303133', fontWeight:600, padding:'13px 0' }"
            :cell-style="{ padding:'15px 0' }"
            class="rp-table"
            style="width:100%"
          >
            <el-table-column label="编号" prop="cand_id" width="74" align="center" />
            <el-table-column label="奖惩项目" min-width="230">
              <template slot-scope="s">
                <div class="rp-cell-project">
                  <div class="rp-cell-project-line">
                    <el-tag
                      :type="Number(s.row.amount) < 0 ? 'danger' : 'success'"
                      size="mini"
                      effect="plain"
                    >{{ dirLabel(s.row) }}</el-tag>
                    <span class="rp-cell-project-name">{{ typeLabel(s.row) }}</span>
                  </div>
                  <div class="rp-cell-aux">{{ sourceAux(s.row) }}</div>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="候选人员" min-width="150">
              <template slot-scope="s">
                <div class="rp-cell-person">
                  <div class="rp-cell-person-name">{{ s.row.user_name || ('#' + s.row.user_id) }}</div>
                  <div class="rp-cell-aux">{{ personAux(s.row) }}</div>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="金额" width="120" align="right">
              <template slot-scope="s">
                <span :class="Number(s.row.amount) < 0 ? 'rp-amount-penalty' : 'rp-amount-reward'">{{ formatSignedMoney(s.row.amount) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="所属日期" width="118" align="center">
              <template slot-scope="s">
                <span class="rp-cell-date">{{ s.row.occurred_date || '-' }}</span>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="108" align="center">
              <template slot-scope="s">
                <el-tag :type="statusTag(s.row.status)" size="small">{{ s.row.status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="事由" min-width="220">
              <template slot-scope="s">
                <el-tooltip
                  :content="s.row.reason || '-'"
                  :disabled="!(s.row.reason)"
                  effect="dark"
                  placement="top"
                >
                  <div class="rp-cell-reason">{{ s.row.reason || '-' }}</div>
                </el-tooltip>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="132" fixed="right" align="center">
              <template slot-scope="s">
                <el-button v-if="canReview(s.row)" type="primary" size="mini" @click="openReview(s.row)">审核</el-button>
                <el-button v-else type="text" @click="openDetail(s.row)">详情</el-button>
                <el-dropdown trigger="click" @command="cmd => handleMore(cmd, s.row)">
                  <el-button type="text" size="mini" class="rp-more-btn">更多<i class="el-icon-arrow-down el-icon--right" /></el-button>
                  <el-dropdown-menu slot="dropdown">
                    <el-dropdown-item command="detail">详情</el-dropdown-item>
                    <el-dropdown-item v-if="s.row.can_edit" command="edit">编辑</el-dropdown-item>
                    <el-dropdown-item command="audit">审计</el-dropdown-item>
                    <el-dropdown-item v-if="s.row.status==='已通过'" command="offset" divided>冲销</el-dropdown-item>
                    <el-dropdown-item v-if="s.row.can_delete" command="delete" divided class="rp-delete-action">删除</el-dropdown-item>
                  </el-dropdown-menu>
                </el-dropdown>
              </template>
            </el-table-column>
            <template slot="empty">
              <div class="rp-table-empty">
                <i class="el-icon-document" />
                <div>暂无奖惩记录</div>
              </div>
            </template>
          </el-table>
          <div class="rp-pager">
            <span class="rp-pager-count">共 {{ total }} 条</span>
            <el-pagination
              :current-page.sync="page"
              :page-size="limit"
              :total="total"
              :page-sizes="[20,50,100]"
              layout="prev,pager,next,sizes"
              background
              @size-change="onSizeChange"
              @current-change="fetchList"
            />
          </div>
        </div>
      </div>

      <!-- ===== Tab 2: 奖惩项目 ===== -->
      <div v-if="activeTab==='rules'" class="rp-tab-body">
        <div class="rp-section-title">奖惩项目配置</div>
        <div class="rp-card rp-info-banner">
          <i class="el-icon-info" />
          <span>配置人工新增奖惩项目、商机阶段自动奖励与里程碑奖励档位。支持固定金额、金额区间和奖金池比例三种计算方式。</span>
        </div>
        <!-- 子页签 -->
        <div class="rp-sub-tabs">
          <div :class="['rp-sub-tab', ruleSubTab==='manual'?'rp-sub-tab-active':'']" @click="ruleSubTab='manual'">人工奖惩项目</div>
          <div :class="['rp-sub-tab', ruleSubTab==='stage'?'rp-sub-tab-active':'']" @click="ruleSubTab='stage'">商机阶段奖励</div>
          <div :class="['rp-sub-tab', ruleSubTab==='milestone'?'rp-sub-tab-active':'']" @click="ruleSubTab='milestone'">里程碑奖励</div>
        </div>

        <!-- 人工奖惩项目：左侧分类导航 + 右侧项目列表 -->
        <div v-if="ruleSubTab==='manual'">
          <div v-loading="ruleLoading" class="rp-cat-layout">
            <div class="rp-cat-side">
              <div
                :class="['rp-cat-item', projectCat==='全部'?'rp-cat-item-active':'']"
                @click="projectCat='全部'"
              >
                <span class="rp-cat-name">全部项目</span>
                <span class="rp-cat-count">{{ manualRules.length }}</span>
              </div>
              <div
                v-for="c in categoryGroups"
                :key="c.name"
                :class="['rp-cat-item', projectCat===c.name?'rp-cat-item-active':'']"
                @click="projectCat=c.name"
              >
                <span class="rp-cat-name">{{ c.name }}</span>
                <span class="rp-cat-count">{{ c.count }}</span>
              </div>
              <div v-if="!manualRules.length && !ruleLoading" class="rp-cat-empty">暂无项目</div>
            </div>
            <div class="rp-cat-main">
              <div class="rp-cat-head">
                <div>
                  <div class="rp-cat-title">{{ projectCat }}</div>
                  <div class="rp-cat-desc">{{ categoryDesc }}</div>
                </div>
                <el-button type="primary" size="small" icon="el-icon-plus" @click="openRuleEdit(null)">新增项目</el-button>
              </div>
              <div class="rp-cat-toolbar">
                <el-input v-model="projectSearch" placeholder="搜索项目名称 / 分类" prefix-icon="el-icon-search" clearable size="small" style="width:220px" />
                <el-select v-model="projectDirFilter" clearable placeholder="奖励 / 处罚" size="small" style="width:130px">
                  <el-option label="奖励" value="reward" />
                  <el-option label="处罚" value="penalty" />
                </el-select>
                <el-select v-model="projectEnabledFilter" clearable placeholder="启用状态" size="small" style="width:130px">
                  <el-option :value="1" label="已启用" />
                  <el-option :value="0" label="已停用" />
                </el-select>
              </div>
              <div class="rp-project-list">
                <div v-for="r in visibleManualRules" :key="r.manual_rule_id" class="rp-project-item">
                  <div class="rp-proj-main">
                    <div class="rp-proj-line1">
                      <el-tag :type="r.direction === 'penalty' ? 'danger' : 'success'" size="mini" effect="plain">{{ r.direction === 'penalty' ? '处罚' : '奖励' }}</el-tag>
                      <span class="rp-proj-name">{{ r.rule_name }}</span>
                      <span v-if="r.category" class="rp-proj-category">{{ r.category }}</span>
                    </div>
                    <div class="rp-proj-line2">
                      <span class="rp-proj-amount">{{ ruleAmountSummary(r) }}</span>
                      <span class="rp-proj-sep">·</span>
                      <span class="rp-proj-calc">{{ calcModeLabel(r.calc_mode) }}</span>
                      <template v-if="r.description">
                        <span class="rp-proj-sep">·</span>
                        <span class="rp-proj-desc">{{ r.description }}</span>
                      </template>
                    </div>
                  </div>
                  <div class="rp-proj-side">
                    <span :class="['rp-proj-state', r.is_enabled ? 'rp-proj-state-on' : 'rp-proj-state-off']">{{ r.is_enabled ? '启用' : '停用' }}</span>
                    <el-switch :value="!!r.is_enabled" @change="toggleRule(r)" />
                    <el-button type="text" @click="openRuleEdit(r)">编辑</el-button>
                  </div>
                </div>
                <div v-if="!visibleManualRules.length" class="rp-table-empty">
                  <i class="el-icon-setting" />
                  <div>暂无奖惩项目，点击右上角“新增项目”</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 商机阶段奖励：按商机类型分流程卡片 -->
        <div v-if="ruleSubTab==='stage'">
          <div class="rp-card rp-info-banner">
            <i class="el-icon-info" />
            <div>
              <div><b>商机阶段奖励</b>：商机推进到指定阶段时自动生成奖励候选。</div>
              <div class="rp-banner-sub">
                <b>商机类型</b>（如直签、代理签约）与<b>商机阶段</b>（如基础核实、有效联系）来源于「商机设置」，<b>阶段奖励规则</b>在此配置。
                三个概念通过稳定 ID 关联，修改名称不影响历史记录；历史奖励在生成时已保存金额快照。
              </div>
            </div>
          </div>

          <div class="rp-stage-toolbar">
            <div class="rp-stage-legend">
              <el-tag size="mini" type="success">启用组</el-tag>
              <el-tag size="mini" type="info">历史组</el-tag>
              <span class="rp-stage-legend-tip">商机推进按规则金额生成一次奖励候选；修改金额仅影响后续推进。</span>
            </div>
            <div class="rp-stage-toolbar-right">
              <el-button v-if="canEditStageRule" size="small" @click="goToBusinessSetting">商机类型与阶段设置</el-button>
              <el-button v-if="canEditStageRule" type="primary" size="small" icon="el-icon-plus" @click="openStageRuleEdit(null)">新增阶段奖励规则</el-button>
            </div>
          </div>

          <div v-loading="stageRuleLoading" class="rp-stage-wrap">
            <div v-for="g in stageBusinessGroups" :key="g.typeId" :class="['rp-card', 'rp-stage-card', g.typeIsActive ? '' : 'rp-stage-card-legacy']">
              <div class="rp-stage-head">
                <div class="rp-stage-name">
                  {{ g.typeName }}
                  <el-tag :type="g.typeIsActive ? 'success' : 'info'" size="mini" effect="plain">{{ g.typeIsActive ? '启用组' : '历史组' }}</el-tag>
                </div>
                <div class="rp-stage-meta">{{ g.enabledCount }} / {{ g.steps.length }} 个阶段启用 · 启用合计 ¥{{ g.total.toFixed(0) }}</div>
              </div>
              <div class="rp-stage-flow">
                <template v-for="(st,i) in g.steps">
                  <div :key="st.id" :class="['rp-stage-step', st.is_enabled ? '' : 'rp-stage-step-off']">
                    <div class="rp-stage-idx">{{ i + 1 }}</div>
                    <div class="rp-stage-body">
                      <div class="rp-stage-label">{{ st.label }}</div>
                      <div class="rp-stage-amount">¥{{ st.amountText }}</div>
                    </div>
                    <el-tag :type="st.is_enabled ? 'success' : 'info'" size="mini">{{ st.is_enabled ? '启用' : '停用' }}</el-tag>
                    <template v-if="canEditStageRule && !st.is_terminal">
                      <el-switch :value="!!st.is_enabled" @change="toggleStageRule(st)" />
                      <el-button type="text" size="mini" @click="openStageRuleEdit(st)">编辑</el-button>
                      <el-button type="text" size="mini" class="rp-stage-del" @click="deleteStageRule(st)">删除</el-button>
                    </template>
                  </div>
                  <i v-if="i < g.steps.length - 1" :key="'a'+st.id" class="el-icon-right rp-stage-arrow" />
                </template>
              </div>
              <div v-if="!g.steps.length" class="rp-table-empty">
                <i class="el-icon-document" />
                <div>该商机类型暂未配置阶段奖励规则</div>
              </div>
            </div>
            <div v-if="!stageRules.length && !stageRuleLoading" class="rp-card rp-table-empty">
              <i class="el-icon-document" />
              <div>暂无商机阶段奖励规则</div>
            </div>
          </div>
        </div>

        <!-- 里程碑奖励：动态档位与奖金池分配（政策参考） -->
        <div v-if="ruleSubTab==='milestone'">
          <div class="rp-card rp-info-banner">
            <i class="el-icon-info" />
            <span>里程碑奖励不是固定奖项，按推进档位与奖金池比例动态核算。下方为系统奖励政策参考，最终金额与审核状态以实际审核结果为准。</span>
          </div>

          <!-- 项目奖金池分配（存在配置时优先展示） -->
          <div v-if="hasPoolConfig" class="rp-card rp-ms-card">
            <div class="rp-card-title">
              <span>项目奖金池分配比例</span>
              <span class="rp-card-title-sub">基于系统参数「外包业务奖金池比例 = {{ poolConfigValue }}%」核算</span>
            </div>
            <div class="rp-ms-pool-grid">
              <div v-for="m in poolMilestones" :key="m.name" class="rp-ms-pool-item">
                <div class="rp-ms-name">{{ m.name }}</div>
                <div class="rp-ms-prop">建议 {{ m.pctMin }}% ~ {{ m.pctMax }}%</div>
                <div class="rp-ms-amount">{{ poolAmountFor(m) }}</div>
                <div class="rp-ms-status">建议金额</div>
              </div>
            </div>
            <div class="rp-ms-note">最终金额与审核状态在对应里程碑候选审核时确认。</div>
          </div>

          <!-- 里程碑档位 -->
          <div class="rp-card rp-ms-card">
            <div class="rp-card-title">
              <span>里程碑奖励档位</span>
              <span class="rp-card-title-sub">按单次奖励金额划分为五档，高档位需更高级别审核</span>
            </div>
            <el-table :data="milestoneTiers" :header-cell-style="{ background:'#fafbfc', color:'#303133', fontWeight:600, padding:'12px 0' }" :cell-style="{ padding:'13px 0' }" class="rp-table" style="width:100%">
              <el-table-column label="里程碑" prop="tier" width="110" align="center" />
              <el-table-column label="建议档位（区间）" prop="range" min-width="180" />
              <el-table-column label="建议金额" min-width="150">
                <template slot-scope="s"><span class="rp-ms-suggest">{{ msSuggestAmount(s.row) }}</span></template>
              </el-table-column>
              <el-table-column label="审核要求" min-width="150">
                <template slot-scope="s"><el-tag :type="s.row.special ? 'warning' : 'info'" size="mini">{{ s.row.review }}</el-tag></template>
              </el-table-column>
              <el-table-column label="最终金额" min-width="120" align="right">
                <template slot-scope="s"><span class="rp-ms-final">审核确认</span></template>
              </el-table-column>
              <el-table-column label="审核状态" min-width="110" align="center">
                <template slot-scope="s"><el-tag type="info" size="mini" effect="plain">待审核</el-tag></template>
              </el-table-column>
            </el-table>
            <div class="rp-ms-note">L5（3000 元以上）须提交专项审批，不可自动结算。</div>
          </div>
        </div>
      </div>

      <!-- ===== Tab 3: 系统参数 ===== -->
      <div v-if="activeTab==='config'" class="rp-tab-body">
        <div class="rp-card rp-info-banner">
          <i class="el-icon-info" />
          <span>配置奖惩系统的全局计算参数。空值显示“待配置”，对应功能不会自动计算。</span>
        </div>
        <div class="rp-config-grid">
          <div v-for="key in configKeys" :key="key" class="rp-config-card">
            <div class="rp-config-name">{{ configLabel(key) }}</div>
            <div class="rp-config-desc">{{ configHint(key) }}</div>
            <div class="rp-config-current">
              当前值：<span v-if="config[key] && config[key] !== '待配置'" class="rp-config-val">{{ config[key] }}</span>
              <span v-else class="rp-config-pending">待配置</span>
            </div>
            <el-input v-model="configForm[key]" :placeholder="config[key] === '待配置' ? '请输入数值' : String(config[key])" size="small" style="width:200px;margin-top:8px" />
          </div>
        </div>
        <div v-if="configDirty" class="rp-config-dirty"><i class="el-icon-warning-outline" /> 配置已修改，尚未保存</div>
      </div>

      <!-- ===== 新建/编辑候选弹窗 ===== -->
      <el-dialog
        :title="editMode ? '编辑奖惩候选' : '新建奖惩候选'"
        :visible.sync="candidateDialog"
        width="660px"
        custom-class="rp-dialog"
        append-to-body
        @closed="resetForm"
      >
        <el-form ref="candForm" :model="form" label-width="96px" size="small" class="rp-dialog-form">
          <!-- 第一段：选择奖惩项目 -->
          <div class="rp-dialog-section">
            <div class="rp-dialog-section-title"><span class="rp-dialog-step">1</span>选择奖惩项目</div>
            <el-form-item label="奖励/处罚" required>
              <el-radio-group v-model="candidateDirection" size="small" @change="onCandidateDirectionChange">
                <el-radio-button label="reward">添加奖励</el-radio-button>
                <el-radio-button label="penalty">添加处罚</el-radio-button>
              </el-radio-group>
            </el-form-item>
            <el-form-item label="奖惩项目" required>
              <el-select v-model="form.manual_rule_id" placeholder="选择奖惩项目" filterable style="width:100%" @change="onRuleSelect">
                <el-option v-for="r in candidateManualRules" :key="r.manual_rule_id" :label="ruleOptionLabel(r)" :value="r.manual_rule_id" />
              </el-select>
            </el-form-item>
            <div v-if="!candidateManualRules.length" class="rp-empty-inline">当前没有启用的{{ candidateDirection === 'penalty' ? '处罚' : '奖励' }}项目，请先到“奖惩项目”中启用。</div>
            <div v-if="selectedRule" class="rp-rule-preview">
              <div class="rp-rule-preview-top">
                <el-tag :type="selectedRule.direction === 'penalty' ? 'danger' : 'success'" size="small" effect="plain">{{ selectedRule.direction === 'penalty' ? '处罚' : '奖励' }}</el-tag>
                <span class="rp-rule-preview-name">{{ selectedRule.rule_name }}</span>
                <span :class="selectedRule.direction === 'penalty' ? 'rp-amount-penalty' : 'rp-amount-reward'" class="rp-rule-preview-amount">{{ ruleAmountDisplay(selectedRule) }}</span>
              </div>
              <div class="rp-rule-preview-meta">
                <span class="rp-rule-preview-mode">计算方式：{{ calcModeLabel(selectedRule.calc_mode) }}</span>
                <span v-if="selectedRule.category" class="rp-rule-preview-cat">分类：{{ selectedRule.category }}</span>
              </div>
              <div v-if="selectedRule.description" class="rp-rule-preview-desc">{{ selectedRule.description }}</div>
            </div>
          </div>

          <!-- 第二段：候选信息 -->
          <div class="rp-dialog-section">
            <div class="rp-dialog-section-title"><span class="rp-dialog-step">2</span>候选信息</div>
            <el-row :gutter="16">
              <el-col :span="12">
                <el-form-item label="候选人员" required>
                  <el-select v-model="form.user_id" :remote-method="searchUser" filterable remote reserve-keyword placeholder="搜索姓名" style="width:100%" @focus="searchUser('')">
                    <el-option v-for="u in userOptions" :key="u.id" :label="u.realname + (udept(u) ? ' · ' + udept(u) : '')" :value="u.id" />
                  </el-select>
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="所属日期" required>
                  <el-date-picker v-model="form.occurred_date" type="date" value-format="yyyy-MM-dd" placeholder="选择日期" style="width:100%" />
                </el-form-item>
              </el-col>
            </el-row>
          </div>

          <!-- 第三段：事由与证据 / 金额确认 -->
          <div class="rp-dialog-section">
            <div class="rp-dialog-section-title"><span class="rp-dialog-step">3</span>事由与证据</div>
            <!-- 金额区间：用户确认金额 -->
            <el-form-item v-if="selectedRule && selectedRule.calc_mode === 'range'" label="确认金额" required>
              <el-input-number v-model="form.amount" :min="Number(selectedRule.amount_min) || 0" :max="Number(selectedRule.amount_max) || 999999" :precision="2" :step="50" controls-position="right" style="width:180px" />
              <span class="rp-amount-hint">元 · 区间 {{ Number(selectedRule.amount_min).toFixed(0) }} ~ {{ Number(selectedRule.amount_max).toFixed(0) }}</span>
            </el-form-item>
            <!-- 奖金池比例：填写计算基数 -->
            <el-form-item v-if="selectedRule && selectedRule.calc_mode === 'pool'" label="计算基数" required>
              <el-input-number v-model="form.base_amount" :min="0" :precision="2" :step="1000" controls-position="right" style="width:180px" />
              <span class="rp-amount-hint">元 × {{ Number(selectedRule.pool_pct) }}% = <b>{{ poolConfirmAmount }}</b> 元</span>
            </el-form-item>
            <el-form-item label="事由说明" required>
              <el-input v-model="form.reason" :rows="3" type="textarea" maxlength="300" show-word-limit placeholder="请填写奖惩事由与证据说明" style="width:100%" />
            </el-form-item>
          </div>

          <!-- 编辑模式：修改说明 -->
          <div v-if="editMode" class="rp-dialog-section">
            <div class="rp-dialog-section-title"><span class="rp-dialog-step">!</span>修改说明</div>
            <div class="rp-edit-notice"><i class="el-icon-warning-outline" /> 修改待审核候选将保留操作日志；修改已通过但未结算的候选，会重新进入待审核状态。</div>
            <el-form-item label="修改原因" required>
              <el-input v-model="form.change_reason" :rows="2" type="textarea" placeholder="必须填写修改原因" style="width:100%" />
            </el-form-item>
          </div>
        </el-form>
        <span slot="footer" class="rp-dialog-footer">
          <el-button @click="candidateDialog=false">取消</el-button>
          <el-button :loading="formSubmitting" type="primary" @click="submitForm">{{ editMode ? '保存修改' : '保存候选' }}</el-button>
        </span>
      </el-dialog>

      <!-- ===== 审核弹窗 ===== -->
      <el-dialog :visible.sync="reviewDialog" title="奖惩审核" width="520px" custom-class="rp-dialog" append-to-body>
        <div v-if="reviewRow" class="rp-review-info">
          <el-descriptions :column="2" border size="small">
            <el-descriptions-item label="人员">{{ reviewRow.user_name }}</el-descriptions-item>
            <el-descriptions-item label="金额"><span :class="Number(reviewRow.amount) < 0 ? 'rp-amount-penalty' : 'rp-amount-reward'">{{ formatSignedMoney(reviewRow.amount) }}</span></el-descriptions-item>
            <el-descriptions-item :span="2" label="项目">{{ sourceLabel(reviewRow.source_type) }}</el-descriptions-item>
            <el-descriptions-item :span="2" label="事由">{{ reviewRow.reason }}</el-descriptions-item>
          </el-descriptions>
        </div>
        <el-form size="small" label-width="80px" style="margin-top:16px">
          <el-form-item label="审核操作">
            <el-radio-group v-model="reviewForm.decision">
              <el-radio label="approve">通过</el-radio>
              <el-radio label="reject">驳回</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item :required="isSelfCandidate" label="审核意见">
            <el-input v-model="reviewForm.review_note" :rows="3" :placeholder="isSelfCandidate ? '审核自己作为奖惩对象的记录，必须填写审核意见' : '审核意见'" type="textarea" />
          </el-form-item>
        </el-form>
        <span slot="footer" class="rp-dialog-footer">
          <el-button @click="reviewDialog=false">取消</el-button>
          <el-button :loading="reviewSubmitting" type="primary" @click="submitReview">确认审核</el-button>
        </span>
      </el-dialog>

      <!-- ===== 详情抽屉 ===== -->
      <el-drawer :visible.sync="detailVisible" title="奖惩详情" size="520px" append-to-body>
        <div v-if="detailData" class="rp-detail">
          <el-descriptions :column="2" border size="small">
            <el-descriptions-item label="编号">{{ detailData.cand_id }}</el-descriptions-item>
            <el-descriptions-item label="类型"><el-tag :type="Number(detailData.amount) < 0 ? 'danger':'success'" size="mini" effect="plain">{{ detailData.direction }}</el-tag></el-descriptions-item>
            <el-descriptions-item label="候选人员">{{ detailData.user_name || '-' }}</el-descriptions-item>
            <el-descriptions-item label="金额"><span :class="Number(detailData.amount) < 0 ? 'rp-amount-penalty':'rp-amount-reward'">{{ formatSignedMoney(detailData.amount) }}</span></el-descriptions-item>
            <el-descriptions-item label="奖惩项目">{{ sourceLabel(detailData.source_type) }}</el-descriptions-item>
            <el-descriptions-item label="所属日期">{{ detailData.occurred_date || '-' }}</el-descriptions-item>
            <el-descriptions-item label="状态"><el-tag :type="statusTag(detailData.status)" size="mini">{{ detailData.status }}</el-tag></el-descriptions-item>
            <el-descriptions-item label="创建人">{{ detailData.create_user_name || '-' }}</el-descriptions-item>
          </el-descriptions>
          <div class="rp-detail-section">
            <div class="rp-detail-label">事由</div>
            <div class="rp-detail-text">{{ detailData.reason || '-' }}</div>
          </div>
          <div v-if="detailData.evidence_note" class="rp-detail-section">
            <div class="rp-detail-label">证据说明</div>
            <div class="rp-detail-text">{{ detailData.evidence_note }}</div>
          </div>
          <div class="rp-detail-section">
            <div class="rp-detail-label">操作记录</div>
            <el-timeline v-if="auditList.length">
              <el-timeline-item v-for="a in auditList" :key="a.audit_id" :timestamp="a.operation_time_str" placement="top">
                <div class="rp-audit-item">
                  <div class="rp-audit-header">{{ a.operator_name || ('用户#' + a.operator_user_id) }} - {{ opLabel(a.operation_type) }}</div>
                  <div v-if="a.change_reason" class="rp-audit-reason">原因：{{ a.change_reason }}</div>
                  <div v-if="a.old_data && a.new_data" class="rp-audit-changes">
                    <span v-if="a.old_data.user_id !== a.new_data.user_id">人员：{{ a.old_data.user_name || a.old_data.user_id }} → {{ a.new_data.user_name || a.new_data.user_id }}</span>
                    <span v-if="Number(a.old_data.amount) !== Number(a.new_data.amount)">金额：{{ Number(a.old_data.amount).toFixed(2) }} → {{ Number(a.new_data.amount).toFixed(2) }}</span>
                    <span v-if="a.old_data.status !== a.new_data.status">状态：{{ a.old_data.status }} → {{ a.new_data.status }}</span>
                  </div>
                </div>
              </el-timeline-item>
            </el-timeline>
            <div v-else class="rp-empty-inline">暂无操作记录</div>
          </div>
          <el-collapse class="rp-detail-tech">
            <el-collapse-item title="技术信息">
              <el-descriptions :column="1" size="mini" border>
                <el-descriptions-item label="source_type">{{ detailData.source_type }}</el-descriptions-item>
                <el-descriptions-item label="source_ref">{{ detailData.source_ref }}</el-descriptions-item>
                <el-descriptions-item label="manual_rule_id">{{ detailData.manual_rule_id || '-' }}</el-descriptions-item>
                <el-descriptions-item label="update_user_id">{{ detailData.update_user_id || '-' }}</el-descriptions-item>
              </el-descriptions>
            </el-collapse-item>
          </el-collapse>
        </div>
      </el-drawer>

      <!-- ===== 新增/编辑奖惩项目弹窗 ===== -->
      <el-dialog
        :visible.sync="ruleDialog"
        :title="ruleForm.manual_rule_id ? '编辑奖惩项目' : '新增奖惩项目'"
        width="620px"
        custom-class="rp-dialog"
        append-to-body
      >
        <el-form :model="ruleForm" label-width="92px" size="small" class="rp-dialog-form">
          <!-- 基本信息 -->
          <div class="rp-dialog-section">
            <div class="rp-dialog-section-title">基本信息</div>
            <el-form-item label="项目名称" required>
              <el-input v-model="ruleForm.rule_name" placeholder="如：投标参与奖励" style="width:100%" />
            </el-form-item>
            <el-row :gutter="16">
              <el-col :span="12">
                <el-form-item label="分类">
                  <el-input v-model="ruleForm.category" placeholder="如：投标与标书" style="width:100%" />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="奖励/处罚" required>
                  <el-radio-group v-model="ruleForm.direction">
                    <el-radio-button label="reward">奖励</el-radio-button>
                    <el-radio-button label="penalty">处罚</el-radio-button>
                  </el-radio-group>
                </el-form-item>
              </el-col>
            </el-row>
            <el-form-item label="说明">
              <el-input v-model="ruleForm.description" :rows="2" type="textarea" placeholder="项目适用场景与规则说明" style="width:100%" />
            </el-form-item>
          </div>

          <!-- 计算规则 -->
          <div class="rp-dialog-section">
            <div class="rp-dialog-section-title">计算规则</div>
            <div class="rp-calccards">
              <div :class="['rp-calccard', ruleForm.calc_mode==='fixed'?'rp-calccard-active':'']" @click="ruleForm.calc_mode='fixed'">
                <i class="el-icon-coin rp-calccard-icon" />
                <div class="rp-calccard-name">固定金额</div>
                <div class="rp-calccard-desc">按固定数值发放</div>
              </div>
              <div :class="['rp-calccard', ruleForm.calc_mode==='range'?'rp-calccard-active':'']" @click="ruleForm.calc_mode='range'">
                <i class="el-icon-sort rp-calccard-icon" />
                <div class="rp-calccard-name">金额区间</div>
                <div class="rp-calccard-desc">在区间内确认金额</div>
              </div>
              <div :class="['rp-calccard', ruleForm.calc_mode==='pool'?'rp-calccard-active':'']" @click="ruleForm.calc_mode='pool'">
                <i class="el-icon-pie-chart rp-calccard-icon" />
                <div class="rp-calccard-name">奖金池比例</div>
                <div class="rp-calccard-desc">按基数比例计算</div>
              </div>
            </div>
            <el-form-item v-if="ruleForm.calc_mode === 'fixed'" label="固定金额" required>
              <el-input-number v-model="ruleForm.amount" :min="0.01" :precision="2" :step="50" controls-position="right" style="width:200px" />
              <span class="rp-amount-hint">元</span>
            </el-form-item>
            <el-form-item v-if="ruleForm.calc_mode === 'range'" label="金额区间" required>
              <el-input-number v-model="ruleForm.amount_min" :min="0" :precision="2" :step="50" controls-position="right" style="width:140px" />
              <span class="rp-range-sep">~</span>
              <el-input-number v-model="ruleForm.amount_max" :min="0.01" :precision="2" :step="50" controls-position="right" style="width:140px" />
              <span class="rp-amount-hint">元</span>
            </el-form-item>
            <el-form-item v-if="ruleForm.calc_mode === 'pool'" label="奖金池比例" required>
              <el-input-number v-model="ruleForm.pool_pct" :min="0.01" :max="100" :precision="2" :step="5" controls-position="right" style="width:200px" />
              <span class="rp-amount-hint">%</span>
            </el-form-item>
          </div>

          <!-- 状态设置 -->
          <div class="rp-dialog-section">
            <div class="rp-dialog-section-title">状态设置</div>
            <el-row :gutter="16">
              <el-col :span="12">
                <el-form-item label="是否启用">
                  <el-switch v-model="ruleForm.is_enabled" active-text="启用" inactive-text="停用" />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="排序">
                  <el-input-number v-model="ruleForm.sort_order" :min="0" :step="1" controls-position="right" style="width:160px" />
                </el-form-item>
              </el-col>
            </el-row>
          </div>
        </el-form>
        <span slot="footer" class="rp-dialog-footer">
          <el-button @click="ruleDialog=false">取消</el-button>
          <el-button :loading="ruleSaving" type="primary" @click="saveRule">保存项目</el-button>
        </span>
      </el-dialog>

      <!-- ===== 商机阶段奖励规则编辑弹窗 ===== -->
      <el-dialog
        :visible.sync="stageRuleDialog"
        :title="stageRuleForm.rule_id ? '编辑阶段奖励规则' : '新增阶段奖励规则'"
        width="600px"
        custom-class="rp-dialog"
        append-to-body
      >
        <el-form :model="stageRuleForm" label-width="100px" size="small" class="rp-dialog-form">
          <div class="rp-dialog-section">
            <div class="rp-dialog-section-title">商机类型与阶段</div>
            <el-form-item label="商机类型" required>
              <el-select
                v-model="stageRuleForm.type_id"
                :disabled="!!stageRuleForm.rule_id"
                placeholder="选择商机类型"
                style="width:100%"
                @change="onStageRuleTypeChange"
              >
                <el-option
                  v-for="t in stageTypeOptions"
                  :key="t.type_id"
                  :label="t.name + (t.type_is_active ? '' : '（历史组）')"
                  :value="t.type_id"
                />
              </el-select>
            </el-form-item>
            <el-form-item label="商机阶段" required>
              <el-select
                v-model="stageRuleForm.status_id"
                :disabled="!!stageRuleForm.rule_id"
                placeholder="选择商机阶段"
                style="width:100%"
              >
                <el-option
                  v-for="s in stageStageOptions"
                  :key="s.status_id"
                  :label="s.name + (s.is_terminal ? '（终态，不可配奖励）' : '')"
                  :disabled="!!s.is_terminal"
                  :value="s.status_id"
                />
              </el-select>
            </el-form-item>
          </div>

          <div class="rp-dialog-section">
            <div class="rp-dialog-section-title">奖励金额</div>
            <el-form-item label="计算方式">
              <el-radio-group v-model="stageRuleForm.calc_method">
                <el-radio-button label="fixed">固定金额</el-radio-button>
                <el-radio-button label="percent">比例(基数的%)</el-radio-button>
              </el-radio-group>
            </el-form-item>
            <el-form-item v-if="stageRuleForm.calc_method==='fixed'" label="奖励金额" required>
              <el-input-number v-model="stageRuleForm.amount" :min="0.01" :precision="2" :step="50" controls-position="right" style="width:200px" />
              <span class="rp-amount-hint">元（推进到此阶段时发放给负责人）</span>
            </el-form-item>
            <el-form-item v-else label="奖励比例" required>
              <el-input-number v-model="stageRuleForm.amount" :min="0.01" :max="100" :precision="2" :step="1" controls-position="right" style="width:200px" />
              <span class="rp-amount-hint">% （按商机金额计算）</span>
            </el-form-item>
          </div>

          <div class="rp-dialog-section">
            <div class="rp-dialog-section-title">规则选项</div>
            <el-form-item label="启用">
              <el-switch v-model="stageRuleForm.is_enabled" active-text="启用" inactive-text="停用" />
            </el-form-item>
            <el-form-item label="自动生成">
              <el-switch v-model="stageRuleForm.auto_generate" active-text="推进时自动生成候选" inactive-text="不自动生成" />
            </el-form-item>
            <el-form-item label="规则说明">
              <el-input v-model="stageRuleForm.description" :rows="2" type="textarea" placeholder="可选：规则适用说明" style="width:100%" />
            </el-form-item>
            <el-form-item v-if="stageRuleForm.rule_id" label="修改原因" required>
              <el-input v-model="stageRuleForm.change_reason" :rows="2" type="textarea" placeholder="修改规则需填写原因（记录审计）" style="width:100%" />
            </el-form-item>
          </div>
        </el-form>
        <div class="rp-stage-rule-note">
          <i class="el-icon-warning-outline" /> 已生成的历史奖励记录不受金额修改影响（生成时已保存金额快照）。
        </div>
        <span slot="footer" class="rp-dialog-footer">
          <el-button @click="stageRuleDialog=false">取消</el-button>
          <el-button :loading="stageRuleSaving" type="primary" @click="saveStageRule">保存规则</el-button>
        </span>
      </el-dialog>
    </div>
  </div>
</template>

<script>
import {
  rewardDictionaryAPI, rewardCandidateSaveAPI, rewardCandidateListAPI,
  rewardCandidateReadAPI, rewardCandidateUpdateAPI, rewardCandidateDeleteAPI, rewardCandidateAuditListAPI,
  rewardReviewAPI, rewardBatchCreateAPI, rewardOffsetAPI, rewardConfigSaveAPI,
  rewardManualRuleListAPI, rewardManualRuleSaveAPI,
  rewardRuleListAPI, rewardRuleSaveAPI, rewardRuleToggleAPI, rewardRuleDeleteAPI,
  rewardBusinessTypeStageListAPI
} from '@/api/crm/reward'
import { usersListIndexAPI } from '@/api/common'

export default {
  name: 'RewardPage',
  data() {
    return {
      activeTab: 'records',
      tabs: [
        { name: 'records', label: '奖惩记录' },
        { name: 'rules', label: '奖惩项目' },
        { name: 'config', label: '系统参数' }
      ],
      loading: false,
      formSubmitting: false,
      dict: { statuses: [] },
      isRewardAdmin: false,
      config: {},
      configKeys: ['dealer_first_payment_reward', 'outsource_business_pool_pct', 'outsource_revenue_cap'],
      configForm: {},
      configSaving: false,
      list: [],
      userOptions: [],
      filters: { user_id: '', dates: [], status: '', direction: '', keyword: '' },
      page: 1, limit: 50, total: 0,
      editMode: false,
      candidateDirection: 'reward',
      form: this.defaultForm(),
      candidateDialog: false,
      detailVisible: false,
      detailData: null,
      auditList: [],
      reviewDialog: false,
      reviewRow: null,
      reviewForm: { decision: 'approve', review_note: '' },
      reviewSubmitting: false,
      manualRules: [],
      ruleLoading: false,
      ruleDialog: false,
      ruleSaving: false,
      // 人工项目筛选与分类导航
      projectCat: '全部',
      projectSearch: '',
      projectDirFilter: '',
      projectEnabledFilter: '',
      ruleSubTab: 'manual',
      stageRules: [],
      stageRuleLoading: false,
      // 商机类型+阶段树（新增规则选择器与总览用）
      typeStageTree: [],
      // 商机阶段奖励规则编辑弹窗
      stageRuleDialog: false,
      stageRuleSaving: false,
      stageRuleForm: this.defaultStageRuleForm(),
      ruleForm: this.defaultRuleForm(),
      stats: { pending: 0, approvedAmount: 0, penaltyAmount: 0 },
      // 里程碑奖励政策参考（固定政策档位，非交易数据）
      milestoneTiers: [
        { tier: 'L1', range: '100 ~ 300 元', min: 100, max: 300, review: '常规审核', special: false },
        { tier: 'L2', range: '301 ~ 800 元', min: 301, max: 800, review: '常规审核', special: false },
        { tier: 'L3', range: '801 ~ 1,500 元', min: 801, max: 1500, review: '常规审核', special: false },
        { tier: 'L4', range: '1,501 ~ 3,000 元', min: 1501, max: 3000, review: '主管复核', special: false },
        { tier: 'L5', range: '3,000 元以上', min: 3001, max: null, review: '需专项审批', special: true }
      ],
      poolMilestones: [
        { name: '需求确认', pctMin: 10, pctMax: 15 },
        { name: '开发完成', pctMin: 25, pctMax: 35 },
        { name: '测试通过', pctMin: 20, pctMax: 25 },
        { name: '上线交付', pctMin: 30, pctMax: 40 }
      ]
    }
  },
  computed: {
    selectedRule() {
      if (!this.form.manual_rule_id) return null
      return this.manualRules.find(r => r.manual_rule_id === this.form.manual_rule_id) || null
    },
    enabledManualRules() {
      return this.manualRules.filter(function(m) { return m.is_enabled })
    },
    candidateManualRules() {
      const direction = this.candidateDirection
      return this.enabledManualRules.filter(function(m) { return m.direction === direction })
    },
    filteredManualRules() {
      var kw = (this.ruleSearch || '').trim().toLowerCase()
      if (!kw) return this.manualRules
      return this.manualRules.filter(function(r) {
        return (r.rule_name || '').toLowerCase().indexOf(kw) >= 0 ||
               (r.category || '').toLowerCase().indexOf(kw) >= 0 ||
               (r.rule_code || '').toLowerCase().indexOf(kw) >= 0
      })
    },
    categoryGroups() {
      const map = {}
      this.manualRules.forEach(r => {
        const c = (r.category || '').trim() || '其他'
        if (!map[c]) map[c] = 0
        map[c]++
      })
      return Object.keys(map).map(name => ({ name, count: map[name] }))
    },
    visibleManualRules() {
      let arr = this.manualRules
      if (this.projectCat && this.projectCat !== '全部') {
        arr = arr.filter(r => ((r.category || '').trim() || '其他') === this.projectCat)
      }
      const kw = (this.projectSearch || '').trim().toLowerCase()
      if (kw) {
        arr = arr.filter(r => (r.rule_name || '').toLowerCase().indexOf(kw) >= 0 ||
          (r.category || '').toLowerCase().indexOf(kw) >= 0)
      }
      if (this.projectDirFilter) {
        arr = arr.filter(r => r.direction === this.projectDirFilter)
      }
      if (this.projectEnabledFilter !== '' && this.projectEnabledFilter !== null) {
        arr = arr.filter(r => Number(r.is_enabled) === Number(this.projectEnabledFilter))
      }
      return arr
    },
    categoryDesc() {
      if (this.projectCat === '全部') return '全部人工奖惩项目，可按名称、类型与启用状态筛选'
      return '「' + this.projectCat + '」分类下的奖惩项目'
    },
    stageBusinessGroups() {
      // 统一数据源：类型名/阶段名/顺序/启用状态均来自后端（不再前端写死）
      const groups = {}
      this.stageRules.forEach(r => {
        const tid = Number(r.type_id) || 0
        if (!groups[tid]) groups[tid] = { typeName: r.type_name || ('类型' + tid), typeIsActive: !!r.type_is_active, raw: [] }
        groups[tid].raw.push(r)
      })
      const list = Object.keys(groups).map(tid => {
        const g = groups[tid]
        const steps = g.raw.slice().sort((a, b) => (Number(a.stage_order) || 0) - (Number(b.stage_order) || 0))
        let total = 0
        const mapped = steps.map(r => {
          if (r.is_enabled) total += Number(r.amount) || 0
          return {
            id: r.rule_id,
            rule_id: r.rule_id,
            type_id: Number(r.type_id),
            status_id: Number(r.status_id),
            label: r.stage_name || r.rule_name || ('阶段' + r.status_id),
            amountText: Number(r.amount || 0).toFixed(0),
            amount: Number(r.amount || 0),
            is_enabled: !!r.is_enabled,
            is_terminal: !!r.is_terminal,
            auto_generate: !!r.auto_generate
          }
        })
        return {
          typeId: Number(tid),
          typeName: g.typeName,
          typeIsActive: g.typeIsActive,
          steps: mapped,
          enabledCount: mapped.filter(s => s.is_enabled).length,
          total: total
        }
      })
      // 启用组在前，历史组在后；组内按 type_id
      list.sort((a, b) => {
        if (a.typeIsActive !== b.typeIsActive) return a.typeIsActive ? -1 : 1
        return a.typeId - b.typeId
      })
      return list
    },
    canEditStageRule() {
      // 前端仅控制编辑入口显隐；后端 assertRewardConfigAuth 做真实权限校验
      const manage = this.$store.getters.manage || {}
      const info = this.$store.getters.userInfo || {}
      return Number(info.id) === 1 || !!(manage && manage.crm && manage.crm.setting)
    },
    // 新增阶段奖励规则：可选的商机类型（启用组 + 已有规则的历史组，便于编辑展示）
    stageTypeOptions() {
      const map = {}
      // 启用组优先
      this.typeStageTree.forEach(t => { map[t.type_id] = { type_id: t.type_id, name: t.name, type_is_active: true } })
      // 已有规则的历史组（编辑历史规则时可见）
      this.stageRules.forEach(r => {
        const tid = Number(r.type_id)
        if (!map[tid]) map[tid] = { type_id: tid, name: r.type_name || ('类型' + tid), type_is_active: !!r.type_is_active }
      })
      return Object.keys(map).map(k => map[k]).sort((a, b) => {
        if (a.type_is_active !== b.type_is_active) return a.type_is_active ? -1 : 1
        return a.type_id - b.type_id
      })
    },
    // 当前所选类型下可选的阶段（排除已被其它规则占用；历史组回退用规则自带阶段名）
    stageStageOptions() {
      const tid = Number(this.stageRuleForm.type_id) || 0
      const type = this.typeStageTree.find(t => Number(t.type_id) === tid)
      const usedStatusIds = this.stageRules
        .filter(r => Number(r.type_id) === tid && Number(r.rule_id) !== Number(this.stageRuleForm.rule_id))
        .map(r => Number(r.status_id))
      let base = []
      if (type && type.stages) {
        base = type.stages.map(s => ({
          status_id: s.status_id,
          name: s.name,
          is_terminal: !!s.is_terminal,
          disabled: usedStatusIds.indexOf(Number(s.status_id)) >= 0
        }))
      }
      // 历史组：回退用 stageRules 中该组的阶段（保证编辑历史规则时能显示阶段名）
      if (!type) {
        this.stageRules.filter(r => Number(r.type_id) === tid).forEach(r => {
          if (!base.find(s => Number(s.status_id) === Number(r.status_id))) {
            base.push({ status_id: r.status_id, name: r.stage_name || r.rule_name, is_terminal: !!r.is_terminal, disabled: false })
          }
        })
      }
      return base
    },
    hasPoolConfig() {
      const v = this.config.outsource_business_pool_pct
      return v !== undefined && v !== null && v !== '' && v !== '待配置' && !isNaN(Number(v))
    },
    poolConfigValue() {
      return this.hasPoolConfig ? Number(this.config.outsource_business_pool_pct) : 0
    },
    poolConfirmAmount() {
      if (!this.selectedRule || this.selectedRule.calc_mode !== 'pool') return '0.00'
      const base = Number(this.form.base_amount) || 0
      const pct = Number(this.selectedRule.pool_pct) || 0
      return (base * pct / 100).toFixed(2)
    },
    isSelfCandidate() {
      if (!this.reviewRow) return false
      const myId = (this.$store.getters.userInfo || {}).id
      return Number(this.reviewRow.user_id) === Number(myId)
    },
    activeFilterTags() {
      var tags = []
      if (this.filters.keyword) tags.push({ key: 'keyword', text: '关键词：' + this.filters.keyword })
      if (this.filters.status) tags.push({ key: 'status', text: '状态：' + this.filters.status })
      if (this.filters.direction) tags.push({ key: 'direction', text: this.filters.direction === 'reward' ? '奖励' : '处罚' })
      if (this.filters.user_id) tags.push({ key: 'user_id', text: '人员ID：' + this.filters.user_id })
      if (this.filters.dates && this.filters.dates.length) tags.push({ key: 'dates', text: '日期：' + this.filters.dates[0] + '~' + this.filters.dates[1] })
      return tags
    },
    configDirty() {
      return this.configKeys.some(k => {
        return this.configForm[k] !== undefined && this.configForm[k] !== '' &&
          String(this.configForm[k]) !== String(this.config[k] || '')
      })
    }
  },
  watch: {
    activeTab(val) {
      if (val === 'rules') {
        this.fetchManualRules()
        this.fetchStageRules()
        this.fetchTypeStages()
      }
    }
  },
  async created() {
    await this.fetchDict()
    await this.fetchList()
    await this.fetchManualRules()
  },
  methods: {
    defaultForm() {
      return { manual_rule_id: '', user_id: '', occurred_date: new Date().toISOString().slice(0, 10), reason: '', change_reason: '', cand_id: 0, amount: 0, base_amount: 0 }
    },
    defaultRuleForm() {
      return { manual_rule_id: 0, rule_name: '', direction: 'reward', amount: 100, calc_mode: 'fixed', amount_min: 0, amount_max: 0, pool_pct: 0, category: '', description: '', is_enabled: true, sort_order: 0 }
    },
    udept(u) { return u.s_name || u.dept_name || u.structure_name || '' },
    formatMoney(v) { return Number(v || 0).toFixed(2) },
    formatSignedMoney(v) {
      var n = Number(v || 0)
      return (n < 0 ? '-' : '+') + Math.abs(n).toFixed(2)
    },
    sourceLabel(s) {
      if (!s) return '-'
      if (s.indexOf('manual:') === 0) return s.substring(7)
      if (s.indexOf('business_stage_reversal') === 0) return '冲销记录'
      if (s === 'business_stage') return '商机阶段'
      return s
    },
    sourceAux(row) {
      const s = row.source_type || ''
      if (!s) return ''
      if (s.indexOf('manual:') === 0) return '人工新增'
      if (s.indexOf('business_stage_reversal') === 0) return '阶段回退冲销'
      if (s === 'business_stage') return '商机阶段自动'
      return '系统规则'
    },
    personAux(row) {
      if (row.create_user_name) return '由 ' + row.create_user_name + ' 创建'
      return ''
    },
    dirLabel(row) { return Number(row.amount) < 0 ? '处罚' : '奖励' },
    typeLabel(row) {
      var s = row.source_type || ''
      if (!s) return '-'
      if (s.indexOf('manual:') === 0) return s.substring(7)
      if (s.indexOf('business_stage_reversal') === 0) return '冲销记录'
      if (s === 'business_stage') {
        var reason = row.reason || ''
        var m = reason.match(/推进至「(.+?)」节点/)
        if (m) return '商机-' + m[1]
        m = reason.match(/商机阶段奖励：(.+?)[（(]/)
        if (m) return '商机-' + m[1]
        return '商机阶段'
      }
      return s
    },
    ruleAmountSummary(r) {
      const sign = r.direction === 'penalty' ? '-' : '+'
      if (r.calc_mode === 'range') return sign + Number(r.amount_min).toFixed(0) + ' ~ ' + Number(r.amount_max).toFixed(0) + ' 元'
      if (r.calc_mode === 'pool') return '基数 × ' + Number(r.pool_pct) + '%'
      return sign + Number(r.amount).toFixed(2) + ' 元'
    },
    configLabel(key) {
      return {
        dealer_first_payment_reward: '经销商首期回款奖励',
        outsource_business_pool_pct: '外包业务奖金池比例',
        outsource_revenue_cap: '外包收入计算上限'
      }[key] || key
    },
    configHint(key) {
      return {
        dealer_first_payment_reward: '经销商客户首期回款到账后，奖励负责人的固定金额。单位：元，≥0。留空表示不自动计算。',
        outsource_business_pool_pct: '外包项目完成后，从合同金额中提取作为奖金池的比例。0-100。留空表示不自动计算。',
        outsource_revenue_cap: '计算外包奖金时的收入上限，超过部分不计入。单位：元，≥0。留空表示无上限。'
      }[key] || ''
    },
    opLabel(t) {
      var m = { manual_create: '新建', edit: '编辑', edit_and_reset: '编辑并重置审核', delete: '删除', review_approve: '审核通过', review_reject: '审核驳回', stage_rollback_void: '阶段回退作废', stage_rollback_reversal: '阶段回退冲销', stage_reactivate: '重新激活' }
      return m[t] || t
    },
    statusTag(s) {
      return { '待审核': 'warning', '待专项审批': 'warning', '已通过': 'success', '已驳回': 'danger', '已结算': '', '已冲销': 'warning', '已作废': 'info' }[s] || 'info'
    },
    canReview(row) {
      return row.status === '待审核' || row.status === '待专项审批'
    },
    msSuggestAmount(tier) {
      if (tier.max === null) return '≥ ¥' + Number(tier.min).toLocaleString() + '.00'
      const mid = Math.round((Number(tier.min) + Number(tier.max)) / 2)
      return '¥' + mid.toLocaleString() + '.00'
    },
    poolAmountFor(m) {
      if (!this.hasPoolConfig) return '—'
      const mid = (Number(m.pctMin) + Number(m.pctMax)) / 2
      return '¥' + (this.poolConfigValue * mid / 100).toFixed(2)
    },
    quickFilter(key, val) {
      if (!key) { this.resetFilters(); return }
      this.filters[key] = val
      this.page = 1
      this.fetchList()
    },
    removeFilter(key) {
      if (key === 'dates') { this.filters.dates = [] } else { this.filters[key] = '' }
      this.page = 1
      this.fetchList()
    },
    async fetchDict() {
      try {
        const r = await rewardDictionaryAPI({})
        const d = r.data || r
        this.dict = d
        this.isRewardAdmin = !!d.is_reward_admin
        this.config = d.config || {}
        this.configKeys.forEach(k => {
          if (this.config[k] && this.config[k] !== '待配置') this.configForm[k] = String(this.config[k])
        })
      } catch (e) { /* 全局拦截器提示 */ }
    },
    async fetchList() {
      this.loading = true
      try {
        const dates = this.filters.dates || []
        const r = await rewardCandidateListAPI({
          user_id: this.filters.user_id, status: this.filters.status,
          direction: this.filters.direction, keyword: this.filters.keyword,
          date_start: dates[0] || '', date_end: dates[1] || '',
          page: this.page, limit: this.limit
        })
        const d = r.data || {}
        this.list = d.list || []
        this.total = d.dataCount || 0
        this.stats.pending = this.list.filter(r => r.status === '待审核').length
        this.stats.approvedAmount = this.list.filter(r => r.status === '已通过' && Number(r.amount) > 0).reduce((s, r) => s + Number(r.amount), 0)
        this.stats.penaltyAmount = this.list.filter(r => Number(r.amount) < 0).reduce((s, r) => s + Number(r.amount), 0)
      } catch (e) {
        this.list = []
      } finally {
        this.loading = false
      }
    },
    onSizeChange(size) { this.limit = size; this.page = 1; this.fetchList() },
    resetFilters() { this.filters = { user_id: '', dates: [], status: '', direction: '', keyword: '' }; this.page = 1; this.fetchList() },
    async searchUser(query) {
      try {
        const r = await usersListIndexAPI({ search: query || '', page: 1, limit: 50, status: 1 })
        const d = r.data || {}
        var list = d.list || d.users || []
        if (!Array.isArray(list) && Array.isArray(d)) list = d
        this.userOptions = list.filter(function(u) { return u.status === 1 || u.status === undefined })
      } catch (e) {
        this.userOptions = []
      }
    },
    ruleOptionLabel(r) {
      var dir = r.direction === 'penalty' ? '处罚' : '奖励'
      if (r.calc_mode === 'range') return r.rule_name + '（' + dir + ' ' + Number(r.amount_min).toFixed(0) + '-' + Number(r.amount_max).toFixed(0) + '元）'
      if (r.calc_mode === 'pool') return r.rule_name + '（' + dir + ' 池比例' + Number(r.pool_pct) + '%）'
      return r.rule_name + '（' + dir + ' ' + r.amount + '元）'
    },
    ruleAmountDisplay(r) {
      var sign = r.direction === 'penalty' ? '-' : '+'
      if (r.calc_mode === 'range') return sign + '用户填写（' + Number(r.amount_min).toFixed(0) + '-' + Number(r.amount_max).toFixed(0) + '元）'
      if (r.calc_mode === 'pool') return '池比例' + Number(r.pool_pct) + '%'
      return sign + Number(r.amount).toFixed(2) + '元'
    },
    onRuleSelect() {
      this.form.amount = 0
      this.form.base_amount = 0
    },
    onCandidateDirectionChange() {
      this.form.manual_rule_id = ''
      this.form.amount = 0
      this.form.base_amount = 0
    },
    resetForm() { this.form = this.defaultForm(); this.editMode = false },
    openCreate() { this.editMode = false; this.candidateDirection = 'reward'; this.form = this.defaultForm(); this.searchUser(''); this.candidateDialog = true },
    async openEdit(row) {
      this.editMode = true
      try {
        const r = await rewardCandidateReadAPI({ cand_id: row.cand_id })
        const d = r.data || {}
        this.candidateDirection = Number(d.amount) < 0 ? 'penalty' : 'reward'
        this.form = {
          cand_id: d.cand_id, manual_rule_id: d.manual_rule_id || '',
          user_id: d.user_id, occurred_date: d.occurred_date || '',
          reason: d.reason || '', change_reason: '',
          amount: Math.abs(Number(d.amount)) || 0, base_amount: 0
        }
        await this.searchUser('')
        if (d.user_id && d.user_name) {
          var exists = this.userOptions.find(function(u) { return Number(u.id) === Number(d.user_id) })
          if (!exists) {
            this.userOptions.unshift({ id: d.user_id, realname: d.user_name, s_name: '' })
          }
        }
        this.candidateDialog = true
      } catch (e) { this.$message.error('读取详情失败') }
    },
    async submitForm() {
      const f = this.form
      if (!f.manual_rule_id) { this.$message.warning('请选择奖惩项目'); return }
      if (!f.user_id) { this.$message.warning('请选择候选人员'); return }
      if (!f.occurred_date) { this.$message.warning('请选择所属日期'); return }
      if (!f.reason) { this.$message.warning('请填写事由说明'); return }
      if (this.editMode && !f.change_reason) { this.$message.warning('请填写修改原因'); return }
      const isRange = this.selectedRule && this.selectedRule.calc_mode === 'range'
      const isPool = this.selectedRule && this.selectedRule.calc_mode === 'pool'
      if (isRange && (!f.amount || f.amount <= 0)) { this.$message.warning('请填写奖励金额'); return }
      if (isPool && (!f.base_amount || f.base_amount <= 0)) { this.$message.warning('请填写计算基数'); return }
      this.formSubmitting = true
      try {
        if (this.editMode) {
          await rewardCandidateUpdateAPI({
            cand_id: f.cand_id, manual_rule_id: Number(f.manual_rule_id),
            user_id: Number(f.user_id), reason: f.reason,
            occurred_time: f.occurred_date, change_reason: f.change_reason,
            amount: isRange ? Number(f.amount) : undefined
          })
          this.$message.success('已修改')
        } else {
          await rewardCandidateSaveAPI({
            manual_rule_id: Number(f.manual_rule_id), user_id: Number(f.user_id),
            occurred_date: f.occurred_date, reason: f.reason,
            amount: isRange ? Number(f.amount) : undefined,
            base_amount: isPool ? Number(f.base_amount) : undefined
          })
          this.$message.success('奖惩候选已创建')
        }
        this.candidateDialog = false
        this.fetchList()
      } catch (e) { /* 全局拦截器提示 */ } finally {
        this.formSubmitting = false
      }
    },
    openReview(row) {
      this.reviewRow = row
      this.reviewForm = { decision: 'approve', review_note: '' }
      this.reviewDialog = true
    },
    async submitReview() {
      if (this.isSelfCandidate && !this.reviewForm.review_note) {
        this.$message.warning('审核自己作为奖惩对象的记录，必须填写审核意见'); return
      }
      this.reviewSubmitting = true
      try {
        await rewardReviewAPI({ cand_id: this.reviewRow.cand_id, decision: this.reviewForm.decision, review_note: this.reviewForm.review_note })
        this.$message.success('已审核')
        this.reviewDialog = false
        this.fetchList()
      } catch (e) { /* 全局拦截器提示 */ } finally {
        this.reviewSubmitting = false
      }
    },
    async openDetail(row) {
      this.detailVisible = true
      this.detailData = row
      try {
        const r = await rewardCandidateReadAPI({ cand_id: row.cand_id })
        this.detailData = r.data || row
        const ar = await rewardCandidateAuditListAPI({ cand_id: row.cand_id })
        this.auditList = (ar.data && ar.data.list) || []
      } catch (e) { this.auditList = [] }
    },
    handleMore(cmd, row) {
      if (cmd === 'detail') this.openDetail(row)
      else if (cmd === 'edit') this.openEdit(row)
      else if (cmd === 'audit') this.openDetail(row)
      else if (cmd === 'offset') this.offset(row)
      else if (cmd === 'delete') this.deleteCandidate(row)
    },
    async deleteCandidate(row) {
      var reason = ''
      try {
        const result = await this.$prompt('删除后记录将从奖惩列表移除，删除操作和原始内容仍会保留在审计日志中。请填写删除原因：', '删除奖惩候选', {
          confirmButtonText: '确认删除',
          cancelButtonText: '取消',
          inputType: 'textarea',
          inputPlaceholder: '例如：重复生成、人员选择错误、测试数据',
          inputValidator: value => (value && String(value).trim()) ? true : '必须填写删除原因',
          type: 'warning'
        })
        reason = String(result.value || '').trim()
      } catch (e) { return }
      try {
        await rewardCandidateDeleteAPI({ cand_id: row.cand_id, delete_reason: reason })
        this.$message.success('奖惩候选已删除')
        if (this.list.length === 1 && this.page > 1) this.page--
        this.fetchList()
      } catch (e) { /* 全局拦截器提示 */ }
    },
    async offset(row) {
      this.$prompt('冲销金额', '冲销').then(async({ value }) => {
        await rewardOffsetAPI({ cand_id: row.cand_id, offset_amount: value })
        this.$message.success('已冲销')
        this.fetchList()
      }).catch(() => {})
    },
    async batchCreate() {
      try {
        const r = await rewardBatchCreateAPI({})
        this.$message.success('已生成结算批次：' + (r.data && r.data.batch_id))
        this.fetchList()
      } catch (e) { /* 全局拦截器提示 */ }
    },
    async fetchManualRules() {
      this.ruleLoading = true
      try {
        const r = await rewardManualRuleListAPI({})
        this.manualRules = r.data || []
      } catch (e) { this.manualRules = [] } finally {
        this.ruleLoading = false
      }
    },
    calcModeLabel(m) { return { fixed: '固定金额', range: '金额区间', pool: '奖金池比例' }[m] || m },
    defaultStageRuleForm() {
      return {
        rule_id: 0,
        type_id: '',
        status_id: '',
        rule_name: '',
        amount: 100,
        calc_method: 'fixed',
        is_enabled: true,
        auto_generate: true,
        description: '',
        change_reason: ''
      }
    },
    async fetchStageRules() {
      this.stageRuleLoading = true
      try {
        const r = await rewardRuleListAPI({})
        const d = r.data || {}
        this.stageRules = d.list || []
      } catch (e) { this.stageRules = [] } finally {
        this.stageRuleLoading = false
      }
    },
    async fetchTypeStages() {
      try {
        const r = await rewardBusinessTypeStageListAPI({})
        this.typeStageTree = (r.data && r.data.list) || []
      } catch (e) { this.typeStageTree = [] }
    },
    onStageRuleTypeChange() {
      this.stageRuleForm.status_id = ''
    },
    openStageRuleEdit(step) {
      if (step) {
        this.stageRuleForm = {
          rule_id: step.rule_id || step.id || 0,
          type_id: Number(step.type_id),
          status_id: Number(step.status_id),
          rule_name: step.label,
          amount: Number(step.amount),
          calc_method: 'fixed',
          is_enabled: !!step.is_enabled,
          auto_generate: !!step.auto_generate,
          description: '',
          change_reason: ''
        }
      } else {
        this.stageRuleForm = this.defaultStageRuleForm()
      }
      if (!this.typeStageTree.length) this.fetchTypeStages()
      this.stageRuleDialog = true
    },
    async saveStageRule() {
      const f = this.stageRuleForm
      if (!f.type_id) { this.$message.warning('请选择商机类型'); return }
      if (!f.status_id) { this.$message.warning('请选择商机阶段'); return }
      if (f.calc_method === 'fixed' && (!f.amount || f.amount <= 0)) { this.$message.warning('固定奖励金额必须大于0'); return }
      if (f.rule_id && !f.change_reason) { this.$message.warning('修改规则需填写修改原因'); return }
      this.stageRuleSaving = true
      try {
        await rewardRuleSaveAPI({
          rule_id: f.rule_id,
          type_id: Number(f.type_id),
          status_id: Number(f.status_id),
          rule_name: f.rule_name,
          amount: Number(f.amount),
          calc_method: f.calc_method,
          is_enabled: f.is_enabled ? 1 : 0,
          auto_generate: f.auto_generate ? 1 : 0,
          description: f.description,
          change_reason: f.change_reason
        })
        this.$message.success('规则已保存')
        this.stageRuleDialog = false
        this.fetchStageRules()
      } catch (e) { /* 全局拦截器提示 */ } finally {
        this.stageRuleSaving = false
      }
    },
    async toggleStageRule(step) {
      try {
        await rewardRuleToggleAPI({ rule_id: step.rule_id || step.id, is_enabled: step.is_enabled ? 0 : 1 })
        this.$message.success(step.is_enabled ? '已停用' : '已启用')
        this.fetchStageRules()
      } catch (e) { /* 全局拦截器提示 */ }
    },
    async deleteStageRule(step) {
      try {
        await this.$confirm('确认删除该阶段奖励规则？（已被奖励记录引用的将自动转为停用）', '删除确认', { type: 'warning' })
      } catch (e) { return }
      try {
        await rewardRuleDeleteAPI({ rule_id: step.rule_id || step.id })
        this.$message.success('已删除')
        this.fetchStageRules()
      } catch (e) { /* 全局拦截器提示 */ }
    },
    goToBusinessSetting() {
      // 跳转到既有的「业务参数设置 - 商机组/阶段」管理页（商机类型与阶段在此维护）
      this.$router.push('/manage/customer/biz-param')
    },
    openRuleEdit(rule) {
      if (rule) {
        this.ruleForm = { manual_rule_id: rule.manual_rule_id, rule_name: rule.rule_name, direction: rule.direction, amount: Number(rule.amount), calc_mode: rule.calc_mode || 'fixed', amount_min: Number(rule.amount_min) || 0, amount_max: Number(rule.amount_max) || 0, pool_pct: Number(rule.pool_pct) || 0, category: rule.category || '', description: rule.description || '', is_enabled: !!rule.is_enabled, sort_order: rule.sort_order || 0 }
      } else {
        this.ruleForm = this.defaultRuleForm()
      }
      this.ruleDialog = true
    },
    async saveRule() {
      if (!this.ruleForm.rule_name) { this.$message.warning('请填写项目名称'); return }
      if (this.ruleForm.calc_mode === 'fixed' && this.ruleForm.amount <= 0) { this.$message.warning('固定金额必须大于0'); return }
      if (this.ruleForm.calc_mode === 'range' && this.ruleForm.amount_max <= 0) { this.$message.warning('区间最大金额必须大于0'); return }
      if (this.ruleForm.calc_mode === 'pool' && (this.ruleForm.pool_pct <= 0 || this.ruleForm.pool_pct > 100)) { this.$message.warning('池比例必须在0-100之间'); return }
      this.ruleSaving = true
      try {
        await rewardManualRuleSaveAPI(this.ruleForm)
        this.$message.success('保存成功')
        this.ruleDialog = false
        this.fetchManualRules()
      } catch (e) { /* 全局拦截器提示 */ } finally {
        this.ruleSaving = false
      }
    },
    async toggleRule(rule) {
      try {
        await rewardManualRuleSaveAPI({ manual_rule_id: rule.manual_rule_id, rule_name: rule.rule_name, direction: rule.direction, amount: rule.amount, description: rule.description, is_enabled: rule.is_enabled ? 0 : 1, sort_order: rule.sort_order })
        this.$message.success(rule.is_enabled ? '已停用' : '已启用')
        this.fetchManualRules()
      } catch (e) { /* 全局拦截器提示 */ }
    },
    async saveAllConfig() {
      this.configSaving = true
      try {
        for (const key of this.configKeys) {
          const val = this.configForm[key]
          if (val !== undefined && val !== '') {
            const numVal = Number(val)
            if (isNaN(numVal)) continue
            if (key === 'outsource_business_pool_pct' && (numVal < 0 || numVal > 100)) continue
            if (key !== 'outsource_business_pool_pct' && numVal < 0) continue
            const r = await rewardConfigSaveAPI({ config_key: key, config_value: String(numVal) })
            this.config = (r.data && r.data.config) || this.config
          }
        }
        this.$message.success('配置已保存')
      } catch (e) { this.$message.error('保存失败') } finally {
        this.configSaving = false
      }
    }
  }
}
</script>

<style scoped>
/* ===== 页面骨架 ===== */
.reward-page { background: #f4f6f9; min-height: 100%; padding: 24px; }
.reward-inner { max-width: 1440px; margin: 0 auto; }

/* 页面标题 */
.rp-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.rp-header-title { font-size: 20px; font-weight: 700; color: #1f2329; line-height: 1.3; }
.rp-header-sub { font-size: 14px; color: #8a909c; margin-top: 4px; }

/* Tab 导航 */
.rp-tabs { display: flex; gap: 8px; border-bottom: 1px solid #e4e7ed; margin-bottom: 24px; }
.rp-tab { padding: 10px 20px; font-size: 15px; color: #8a909c; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -1px; transition: all 0.2s; }
.rp-tab:hover { color: #409EFF; }
.rp-tab-active { color: #409EFF; border-bottom-color: #409EFF; font-weight: 600; }
.rp-tab-body { display: flex; flex-direction: column; gap: 24px; }

/* 模块/卡片标题 */
.rp-section-title { font-size: 16px; font-weight: 600; color: #1f2329; }
.rp-card-title { font-size: 16px; font-weight: 600; color: #1f2329; margin-bottom: 16px; display: flex; align-items: baseline; gap: 12px; }
.rp-card-title-sub { font-size: 13px; color: #8a909c; font-weight: 400; }

/* 通用卡片 */
.rp-card { background: #fff; border: 1px solid #eceef3; border-radius: 10px; padding: 20px; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02); }

/* ===== 统计卡片 ===== */
.rp-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
.rp-stat-card { display: flex; align-items: center; gap: 14px; padding: 20px; background: #fff; border: 1px solid #eceef3; border-radius: 10px; cursor: pointer; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02); }
.rp-stat-card:hover { border-color: #d3d8e0; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06); }
.rp-stat-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
.rp-stat-icon i { font-size: 22px; }
.rp-stat-main { min-width: 0; }
.rp-stat-num { font-size: 26px; font-weight: 700; line-height: 1.2; color: #1f2329; font-variant-numeric: tabular-nums; }
.rp-stat-unit { font-size: 13px; font-weight: 500; color: #8a909c; margin-left: 2px; }
.rp-stat-label { font-size: 13px; color: #8a909c; margin-top: 4px; }
.rp-stat-pending .rp-stat-icon { background: #fdf6ec; color: #e6a23c; }
.rp-stat-approved .rp-stat-icon { background: #f0f9eb; color: #67c23a; }
.rp-stat-penalty .rp-stat-icon { background: #fef0f0; color: #f56c6c; }
.rp-stat-total .rp-stat-icon { background: #ecf2fe; color: #409eff; }

/* ===== 筛选卡片 ===== */
.rp-filter-card { padding: 20px 20px 4px; }
.rp-filter-form { display: flex; flex-wrap: wrap; row-gap: 4px; }
.rp-filter-form >>> .el-form-item { margin-bottom: 16px; margin-right: 16px; }
.rp-filter-form >>> .el-form-item__label { font-size: 13px; color: #5c6066; }
.rp-filter-tags { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; padding: 12px 0 4px; margin-top: 4px; border-top: 1px dashed #ebeef5; }
.rp-filter-tags-label { font-size: 13px; color: #8a909c; }

/* ===== 记录列表卡片 ===== */
.rp-table-card { padding: 0; overflow: hidden; }
.rp-table-toolbar { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid #f0f2f5; }
.rp-table-title { font-size: 15px; font-weight: 600; color: #1f2329; display: flex; align-items: baseline; gap: 8px; }
.rp-table-count { font-size: 13px; color: #8a909c; font-weight: 400; }
.rp-table-actions { display: flex; gap: 8px; }

/* 表格本体：降低网格感 */
.rp-table { font-size: 14px; }
.rp-table >>> th .cell { font-size: 13px; }
.rp-table >>> td { border-bottom: 1px solid #f2f4f7; }
.rp-table >>> th { border-bottom: 1px solid #ebeef5; background: #fafbfc; }
.rp-table >>> .cell { line-height: 1.5; }
.rp-table >>> tr:hover > td { background: #f8fafd; }

/* 表格单元格 */
.rp-cell-project-line { display: flex; align-items: center; gap: 8px; }
.rp-cell-project-name { font-size: 14px; font-weight: 600; color: #1f2329; }
.rp-cell-person-name { font-size: 14px; color: #1f2329; }
.rp-cell-aux { font-size: 13px; color: #a3a8b2; margin-top: 2px; }
.rp-cell-date { font-size: 14px; color: #5c6066; font-variant-numeric: tabular-nums; }
.rp-cell-reason { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.5; font-size: 13px; color: #5c6066; }
.rp-amount-reward { color: #67c23a; font-weight: 600; font-variant-numeric: tabular-nums; }
.rp-delete-action { color: #f56c6c; }
.rp-amount-penalty { color: #f56c6c; font-weight: 600; font-variant-numeric: tabular-nums; }
.rp-more-btn { margin-left: 6px; }

/* 分页 */
.rp-pager { display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; border-top: 1px solid #f0f2f5; }
.rp-pager-count { font-size: 13px; color: #8a909c; }

/* 空状态 */
.rp-table-empty { text-align: center; padding: 48px 0; color: #a3a8b2; font-size: 14px; }
.rp-table-empty i { font-size: 40px; color: #dcdfe6; display: block; margin-bottom: 8px; }
.rp-empty-inline { padding: 12px 0; color: #a3a8b2; font-size: 13px; text-align: center; }

/* 信息横幅 */
.rp-info-banner { display: flex; align-items: flex-start; gap: 8px; font-size: 14px; color: #5c6066; line-height: 1.6; }
.rp-info-banner i { color: #409eff; font-size: 16px; margin-top: 2px; flex-shrink: 0; }

/* 子页签 */
.rp-sub-tabs { display: flex; gap: 8px; }
.rp-sub-tab { padding: 8px 18px; font-size: 14px; color: #8a909c; cursor: pointer; border: 1px solid #e4e7ed; border-radius: 6px; background: #fff; transition: all 0.2s; }
.rp-sub-tab:hover { color: #409eff; border-color: #c6d4f5; }
.rp-sub-tab-active { color: #fff; background: #409eff; border-color: #409eff; font-weight: 600; }

/* ===== 项目分类布局 ===== */
.rp-cat-layout { display: flex; gap: 20px; align-items: flex-start; }
.rp-cat-side { width: 220px; flex-shrink: 0; background: #fff; border: 1px solid #eceef3; border-radius: 10px; padding: 10px; }
.rp-cat-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; border-radius: 8px; cursor: pointer; font-size: 14px; color: #5c6066; transition: all 0.15s; }
.rp-cat-item:hover { background: #f7f9fc; }
.rp-cat-item-active { background: #ecf2fe; color: #409eff; font-weight: 600; }
.rp-cat-count { font-size: 12px; color: #a3a8b2; background: #f0f2f5; border-radius: 10px; padding: 1px 8px; }
.rp-cat-item-active .rp-cat-count { background: #d6e4ff; color: #409eff; }
.rp-cat-empty { padding: 20px 12px; text-align: center; font-size: 13px; color: #a3a8b2; }
.rp-cat-main { flex: 1; min-width: 0; background: #fff; border: 1px solid #eceef3; border-radius: 10px; padding: 20px; }
.rp-cat-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.rp-cat-title { font-size: 16px; font-weight: 600; color: #1f2329; }
.rp-cat-desc { font-size: 13px; color: #8a909c; margin-top: 4px; }
.rp-cat-toolbar { display: flex; gap: 12px; margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid #f2f4f7; }

/* 项目列表项 */
.rp-project-list { display: flex; flex-direction: column; }
.rp-project-item { display: flex; justify-content: space-between; align-items: center; padding: 14px 12px; border-radius: 8px; transition: background 0.15s; border-bottom: 1px solid #f5f7fa; }
.rp-project-item:last-child { border-bottom: none; }
.rp-project-item:hover { background: #f8fafd; }
.rp-proj-main { min-width: 0; flex: 1; padding-right: 16px; }
.rp-proj-line1 { display: flex; align-items: center; gap: 8px; }
.rp-proj-name { font-size: 14px; font-weight: 600; color: #1f2329; }
.rp-proj-category { font-size: 12px; color: #8a909c; background: #f4f6f9; border-radius: 4px; padding: 1px 6px; }
.rp-proj-line2 { font-size: 13px; color: #8a909c; margin-top: 6px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.rp-proj-amount { color: #409eff; font-weight: 600; font-variant-numeric: tabular-nums; }
.rp-proj-sep { color: #d3d8e0; }
.rp-proj-side { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
.rp-proj-state { font-size: 12px; }
.rp-proj-state-on { color: #67c23a; }
.rp-proj-state-off { color: #a3a8b2; }

/* ===== 商机阶段流程卡片 ===== */
.rp-banner-sub { font-size: 13px; color: #8a909c; margin-top: 4px; line-height: 1.6; }
.rp-stage-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
.rp-stage-legend { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.rp-stage-legend-tip { font-size: 13px; color: #8a909c; }
.rp-stage-toolbar-right { display: flex; gap: 8px; }
.rp-stage-wrap { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
.rp-stage-card { padding: 20px; }
.rp-stage-card-legacy { background: #fafafa; border-style: dashed; }
.rp-stage-del { color: #f56c6c !important; }
.rp-stage-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; padding-bottom: 14px; border-bottom: 1px solid #f2f4f7; }
.rp-stage-name { font-size: 15px; font-weight: 600; color: #1f2329; }
.rp-stage-meta { font-size: 13px; color: #8a909c; }
.rp-stage-flow { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
.rp-stage-step { display: flex; align-items: center; gap: 10px; padding: 12px 14px; background: #f7f9fc; border: 1px solid #eceef3; border-radius: 8px; min-width: 0; }
.rp-stage-step-off { opacity: 0.55; background: #fafafa; }
.rp-stage-idx { width: 24px; height: 24px; border-radius: 50%; background: #409eff; color: #fff; font-size: 12px; font-weight: 600; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.rp-stage-step-off .rp-stage-idx { background: #c0c4cc; }
.rp-stage-body { min-width: 0; }
.rp-stage-label { font-size: 13px; font-weight: 600; color: #1f2329; }
.rp-stage-amount { font-size: 14px; font-weight: 700; color: #67c23a; margin-top: 2px; font-variant-numeric: tabular-nums; }
.rp-stage-arrow { color: #c0c4cc; font-size: 16px; }

/* ===== 里程碑奖励 ===== */
.rp-ms-card { margin-bottom: 20px; }
.rp-ms-pool-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
.rp-ms-pool-item { padding: 16px; background: #f7f9fc; border: 1px solid #eceef3; border-radius: 8px; text-align: center; }
.rp-ms-name { font-size: 14px; font-weight: 600; color: #1f2329; }
.rp-ms-prop { font-size: 13px; color: #409eff; margin-top: 6px; font-weight: 600; }
.rp-ms-amount { font-size: 20px; font-weight: 700; color: #1f2329; margin-top: 6px; font-variant-numeric: tabular-nums; }
.rp-ms-status { font-size: 12px; color: #a3a8b2; margin-top: 4px; }
.rp-ms-suggest { font-weight: 600; color: #1f2329; font-variant-numeric: tabular-nums; }
.rp-ms-final { font-size: 13px; color: #a3a8b2; }
.rp-ms-note { margin-top: 14px; font-size: 12px; color: #a3a8b2; }

/* ===== 系统参数 ===== */
.rp-config-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
.rp-config-card { background: #fff; border: 1px solid #eceef3; border-radius: 10px; padding: 20px; }
.rp-config-name { font-size: 15px; font-weight: 600; color: #1f2329; }
.rp-config-desc { font-size: 13px; color: #8a909c; margin-top: 6px; line-height: 1.6; }
.rp-config-current { font-size: 14px; margin-top: 12px; color: #5c6066; }
.rp-config-val { font-weight: 700; color: #409eff; }
.rp-config-pending { color: #e6a23c; }
.rp-config-dirty { margin-top: 8px; padding: 10px 16px; background: #fdf6ec; border: 1px solid #f5dab1; border-radius: 8px; font-size: 13px; color: #e6a23c; }
.rp-config-dirty i { margin-right: 4px; }

/* 响应式 */
@media (max-width: 1366px) {
  .rp-stats { grid-template-columns: repeat(2, 1fr); }
  .rp-stage-wrap { grid-template-columns: 1fr; }
  .rp-ms-pool-grid { grid-template-columns: repeat(2, 1fr); }
  .rp-config-grid { grid-template-columns: 1fr; }
}
</style>

<!--
  弹窗 / 抽屉样式（非 scoped）
  el-dialog / el-drawer 使用 append-to-body，DOM 被移到 <body> 下，
  脱离本组件作用域，scoped 样式与 >>> 深选择器无法命中其内部元素，
  因此必须用全局样式（配合 rp- 前缀类名）才能让弹窗美化生效。
-->
<style>
/* ===== 弹窗外壳 ===== */
.rp-dialog { border-radius: 12px !important; overflow: hidden; box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12); }
.rp-dialog .el-dialog__header { padding: 18px 24px 14px; border-bottom: 1px solid #f0f2f5; background: #fff; }
.rp-dialog .el-dialog__header .el-dialog__title { font-size: 16px; font-weight: 600; color: #1f2329; }
.rp-dialog .el-dialog__body { padding: 20px 24px 6px; color: #303133; }
.rp-dialog .el-dialog__footer { padding: 14px 24px 18px; border-top: 1px solid #f0f2f5; }
.rp-dialog .el-dialog__headerbtn { top: 18px; }

/* 表单间距与字号 */
.rp-dialog-form .el-form-item { margin-bottom: 16px; }
.rp-dialog-form .el-form-item__label { font-size: 13px; color: #5c6066; padding-right: 8px; line-height: 32px; }
.rp-dialog-form .el-input-number--small { line-height: 30px; }
.rp-dialog-form .el-radio-button__inner { font-size: 13px; }

/* 分段结构 */
.rp-dialog-section { margin-bottom: 18px; padding-bottom: 16px; border-bottom: 1px solid #f2f4f7; }
.rp-dialog-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
.rp-dialog-section-title { font-size: 14px; font-weight: 600; color: #1f2329; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
.rp-dialog-step { width: 20px; height: 20px; border-radius: 50%; background: #409eff; color: #fff; font-size: 12px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
.rp-dialog-footer { display: flex; justify-content: flex-end; gap: 12px; }

/* 项目预览卡 */
.rp-rule-preview { background: #f7f9fc; border: 1px solid #eceef3; border-radius: 8px; padding: 14px 16px; margin-bottom: 16px; }
.rp-rule-preview-top { display: flex; align-items: center; gap: 10px; }
.rp-rule-preview-name { font-size: 15px; font-weight: 600; color: #1f2329; }
.rp-rule-preview-amount { margin-left: auto; font-weight: 700; font-size: 16px; }
.rp-rule-preview-meta { display: flex; gap: 16px; margin-top: 8px; font-size: 13px; color: #8a909c; }
.rp-rule-preview-desc { font-size: 13px; color: #8a909c; line-height: 1.6; margin-top: 8px; }

.rp-amount-hint { margin-left: 10px; font-size: 13px; color: #8a909c; }
.rp-amount-hint b { color: #409eff; }
.rp-range-sep { margin: 0 8px; color: #a3a8b2; }
.rp-edit-notice { background: #fdf6ec; border: 1px solid #f5dab1; border-radius: 8px; padding: 10px 12px; font-size: 13px; color: #e6a23c; line-height: 1.6; margin-bottom: 14px; }
.rp-stage-rule-note { margin: 8px 0 0; padding: 10px 12px; background: #f4f6f9; border-radius: 8px; font-size: 13px; color: #8a909c; line-height: 1.6; }
.rp-stage-rule-note i { color: #e6a23c; margin-right: 4px; }

/* 计算方式选择卡 */
.rp-calccards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 18px; }
.rp-calccard { padding: 16px; border: 1px solid #e4e7ed; border-radius: 8px; cursor: pointer; text-align: center; transition: all 0.15s; background: #fff; }
.rp-calccard:hover { border-color: #c6d4f5; }
.rp-calccard-active { border-color: #409eff; background: #ecf2fe; box-shadow: 0 0 0 1px #409eff; }
.rp-calccard-icon { font-size: 22px; color: #409eff; }
.rp-calccard-name { font-size: 14px; font-weight: 600; color: #1f2329; margin-top: 6px; }
.rp-calccard-desc { font-size: 12px; color: #a3a8b2; margin-top: 4px; }

/* 详情抽屉（append-to-body，外壳与内容统一）*/
.rp-detail { padding: 0 20px 20px; }
.rp-detail-section { margin-top: 16px; }
.rp-detail-label { font-size: 13px; font-weight: 600; color: #1f2329; margin-bottom: 6px; }
.rp-detail-text { font-size: 14px; color: #5c6066; line-height: 1.6; background: #f7f9fc; padding: 10px 12px; border-radius: 8px; }
.rp-detail-tech { margin-top: 16px; }
.rp-audit-item { font-size: 13px; }
.rp-audit-header { font-weight: 600; color: #1f2329; }
.rp-audit-reason { color: #5c6066; margin: 4px 0; }
.rp-audit-changes { color: #e6a23c; display: flex; flex-direction: column; gap: 2px; margin-top: 4px; }
</style>

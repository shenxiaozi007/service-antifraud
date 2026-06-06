<script setup lang="ts">
import { ref } from 'vue';
import { onShow } from '@dcloudio/uni-app';
import { getRecords } from '@/api/client';
import { analysisStatusClass, analysisStatusLabel, riskClass, riskLabel } from '@/constants/risk';
import { ensureLogin } from '@/stores/session';
import type { AnalysisRecord, AnalysisStatus, AnalysisType, RiskLevel } from '@/types/api';
import '@/styles/common.scss';

const records = ref<AnalysisRecord[]>([]);
const filter = ref<RiskLevel | ''>('');
const type = ref<AnalysisType | ''>('');

onShow(async () => {
  await ensureLogin();
  await loadRecords();
});

// 方法：按当前筛选条件拉取历史列表，列表状态跟随异步分析任务实时展示
async function loadRecords() {
  const result = await getRecords({
    risk_level: filter.value,
    type: type.value,
    page_size: 50
  });
  records.value = result.items;
}

// 方法：切换风险筛选，成功记录才展示风险等级，其他状态展示任务状态
function changeFilter(next: RiskLevel | '') {
  filter.value = next;
  void loadRecords();
}

// 方法：切换图片/录音类型筛选，再次点击同类型时取消筛选
function changeType(next: AnalysisType) {
  type.value = type.value === next ? '' : next;
  void loadRecords();
}

// 方法：进入报告页查看异步任务详情或最终分析报告
function goReport(recordId: number) {
  uni.navigateTo({ url: `/pages/report/index?record_id=${recordId}` });
}

// 方法：根据任务状态决定列表标签，避免处理中记录被误展示为低风险
function recordTag(item: AnalysisRecord) {
  if (item.status === 'success') {
    return {
      text: riskLabel(item.risk_level),
      className: riskClass(item.risk_level)
    };
  }

  return {
    text: analysisStatusLabel(item.status),
    className: analysisStatusClass(item.status)
  };
}

// 方法：展示已扣点或冻结点数，让用户能区分完成消费和处理中冻结
function pointText(item: AnalysisRecord) {
  const status = item.status as AnalysisStatus;
  if ((status === 'pending' || status === 'processing') && item.frozen_points) {
    return `冻结${item.frozen_points}点`;
  }

  return `${item.cost_points}点`;
}
</script>

<template>
  <view class="page">
    <view class="section row">
      <view class="title small">历史记录</view>
      <view class="tag" @tap="loadRecords">刷新</view>
    </view>

    <view class="filters">
      <view class="filter" :class="{ active: filter === '' }" @tap="changeFilter('')">全部</view>
      <view class="filter" :class="{ active: filter === 'high' }" @tap="changeFilter('high')">高风险</view>
      <view class="filter" :class="{ active: type === 'image' }" @tap="changeType('image')">图片</view>
      <view class="filter" :class="{ active: type === 'audio' }" @tap="changeType('audio')">录音</view>
    </view>

    <view class="card">
      <view v-if="!records.length" class="empty">还没有分析记录</view>
      <view v-for="item in records" :key="item.id" class="list-item" @tap="goReport(item.id)">
        <view class="row">
          <view class="record-title">{{ item.title }}</view>
          <view class="tag" :class="recordTag(item).className">{{ recordTag(item).text }}</view>
        </view>
        <view class="muted">{{ item.type === 'image' ? '帮您看' : '帮您听' }} · {{ item.created_at }} · {{ pointText(item) }}</view>
      </view>
    </view>
  </view>
</template>

<style scoped>
.title.small {
  font-size: 42rpx;
}

.filters {
  display: flex;
  gap: 14rpx;
  margin-bottom: 24rpx;
  overflow-x: auto;
}

.filter {
  flex-shrink: 0;
  padding: 16rpx 26rpx;
  border-radius: 30rpx;
  background: rgba(255, 255, 255, 0.88);
  color: #6e6e73;
  font-size: 26rpx;
  border: 1rpx solid rgba(0, 0, 0, 0.06);
}

.filter.active {
  background: #1d1d1f;
  color: #ffffff;
  border-color: #1d1d1f;
}

.record-title {
  flex: 1;
  min-width: 0;
  font-weight: 700;
  line-height: 1.4;
}
</style>

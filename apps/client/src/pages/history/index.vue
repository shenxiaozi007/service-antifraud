<script setup lang="ts">
import { ref } from 'vue';
import { onShow } from '@dcloudio/uni-app';
import { getRecords } from '@/api/client';
import { riskClass, riskLabel } from '@/constants/risk';
import { ensureLogin } from '@/stores/session';
import type { AnalysisRecord, AnalysisType, RiskLevel } from '@/types/api';
import '@/styles/common.scss';

const records = ref<AnalysisRecord[]>([]);
const filter = ref<RiskLevel | ''>('');
const type = ref<AnalysisType | ''>('');

onShow(async () => {
  await ensureLogin();
  await loadRecords();
});

async function loadRecords() {
  const result = await getRecords({
    risk_level: filter.value,
    type: type.value,
    page_size: 50
  });
  records.value = result.items;
}

function changeFilter(next: RiskLevel | '') {
  filter.value = next;
  void loadRecords();
}

function changeType(next: AnalysisType) {
  type.value = type.value === next ? '' : next;
  void loadRecords();
}

function goReport(recordId: number) {
  uni.navigateTo({ url: `/pages/report/index?record_id=${recordId}` });
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
          <view class="tag" :class="riskClass(item.risk_level)">{{ riskLabel(item.risk_level) }}</view>
        </view>
        <view class="muted">{{ item.type === 'image' ? '帮您看' : '帮您听' }} · {{ item.created_at }} · {{ item.cost_points }}点</view>
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

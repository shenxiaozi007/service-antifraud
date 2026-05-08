<script setup lang="ts">
import { computed, ref } from 'vue';
import { onLoad, onShareAppMessage } from '@dcloudio/uni-app';
import { getReport } from '@/api/client';
import { durationText, riskClass, riskLabel } from '@/constants/risk';
import { ensureLogin } from '@/stores/session';
import type { AnalysisRecord } from '@/types/api';
import '@/styles/common.scss';

const loading = ref(true);
const recordId = ref(0);
const report = ref<AnalysisRecord | null>(null);
const riskText = computed(() => riskLabel(report.value?.risk_level || 'low'));
const riskStyle = computed(() => riskClass(report.value?.risk_level || 'low'));
const audioDuration = computed(() => durationText(report.value?.duration_seconds));

onLoad(async (options) => {
  await ensureLogin();
  recordId.value = Number(options?.record_id || 0);
  report.value = await getReport(recordId.value);
  loading.value = false;
});

function again() {
  uni.navigateTo({ url: report.value?.type === 'audio' ? '/pages/audio/index' : '/pages/image/index' });
}

onShareAppMessage(() => ({
  title: `${riskText.value}：${report.value?.title || '分析报告'}`,
  path: `/pages/report/index?record_id=${recordId.value}`
}));
</script>

<template>
  <view class="page">
    <view v-if="loading" class="empty">正在加载报告</view>

    <block v-else-if="report">
      <view class="card section">
        <view class="row">
          <view class="tag" :class="riskStyle">{{ riskText }}</view>
          <view class="muted">{{ report.analyzed_at }}</view>
        </view>
        <view class="report-title">{{ report.title }}</view>
        <view class="summary">{{ report.summary }}</view>
      </view>

      <view class="card section">
        <view class="block-title">建议动作</view>
        <view v-for="(item, index) in report.suggestions" :key="item" class="advice">{{ index + 1 }}. {{ item }}</view>
      </view>

      <view class="card section">
        <view class="block-title">主要风险点</view>
        <view v-if="!report.risk_items?.length" class="muted">暂未发现明显风险点</view>
        <view v-for="item in report.risk_items" :key="`${item.category}-${item.evidence_text}`" class="risk-item">
          <view class="row">
            <view class="risk-name">{{ item.category }}</view>
            <view class="tag" :class="riskClass(item.severity)">{{ item.severity }}</view>
          </view>
          <view class="muted">{{ item.description }}</view>
          <view v-if="item.evidence_text" class="evidence">“{{ item.evidence_text }}”</view>
        </view>
      </view>

      <view class="card section">
        <view class="block-title">分析信息</view>
        <view class="meta">类型：{{ report.type === 'image' ? '帮您看' : '帮您听' }}</view>
        <view class="meta">消耗：{{ report.cost_points }} 点</view>
        <view v-if="report.image_count" class="meta">图片：{{ report.image_count }} 张</view>
        <view v-if="report.duration_seconds" class="meta">录音：{{ audioDuration }}</view>
        <view class="disclaimer">{{ report.disclaimer }}</view>
      </view>

      <view class="row actions">
        <view class="button ghost" @tap="again">再分析一次</view>
        <button class="share" open-type="share">分享给家人</button>
      </view>
    </block>
  </view>
</template>

<style scoped>
.report-title {
  margin-top: 26rpx;
  font-size: 42rpx;
  font-weight: 800;
  line-height: 1.3;
}

.summary {
  margin-top: 18rpx;
  color: #48534d;
  line-height: 1.6;
}

.block-title {
  font-size: 34rpx;
  font-weight: 700;
  margin-bottom: 18rpx;
}

.advice {
  line-height: 1.7;
  margin-bottom: 10rpx;
}

.risk-item {
  padding: 20rpx 0;
  border-bottom: 1rpx solid #edf0ed;
}

.risk-item:last-child {
  border-bottom: none;
}

.risk-name {
  font-weight: 700;
}

.evidence {
  margin-top: 12rpx;
  padding: 18rpx;
  border-radius: 12rpx;
  background: #f6f8f6;
  color: #48534d;
}

.meta {
  line-height: 1.8;
}

.disclaimer {
  margin-top: 18rpx;
  color: #7b837e;
  font-size: 24rpx;
  line-height: 1.6;
}

.actions {
  align-items: stretch;
}

.actions .button,
.share {
  flex: 1;
}

.share {
  height: 92rpx;
  border: 0;
  border-radius: 46rpx;
  background: #1f8a5b;
  color: #ffffff;
  font-size: 32rpx;
  font-weight: 700;
  line-height: 92rpx;
}
</style>

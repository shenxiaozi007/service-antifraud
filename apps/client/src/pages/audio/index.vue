<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { onLoad } from '@dcloudio/uni-app';
import { createAudioAnalysis, uploadToken } from '@/api/client';
import { durationText } from '@/constants/risk';
import { ensureLogin } from '@/stores/session';
import '@/styles/common.scss';

const recording = ref(false);
const audioPath = ref('');
const duration = ref(0);
const text = ref('');
const loading = ref(false);
const startedAt = ref(0);
let timer: ReturnType<typeof setInterval> | null = null;
const recorder = uni.getRecorderManager();

const canSubmit = computed(() => !loading.value && (Boolean(audioPath.value) || text.value.length > 0));
const displayDuration = computed(() => durationText(duration.value));

onLoad(() => {
  void ensureLogin();
  recorder.onStop((result) => {
    clearTimer();
    duration.value = Math.max(1, Math.ceil((Date.now() - startedAt.value) / 1000));
    audioPath.value = result.tempFilePath;
    recording.value = false;
  });
});

onBeforeUnmount(() => {
  clearTimer();
});

function startRecord() {
  startedAt.value = Date.now();
  recording.value = true;
  audioPath.value = '';
  duration.value = 0;
  timer = setInterval(() => {
    duration.value = Math.floor((Date.now() - startedAt.value) / 1000);
  }, 1000);
  recorder.start({
    duration: 600000,
    sampleRate: 16000,
    numberOfChannels: 1,
    encodeBitRate: 48000,
    format: 'mp3'
  });
}

function stopRecord() {
  recorder.stop();
}

function resetRecord() {
  audioPath.value = '';
  duration.value = 0;
}

async function submit() {
  if (!canSubmit.value) return;

  loading.value = true;
  uni.showLoading({ title: '分析中' });

  try {
    const file = await uploadToken({
      file_type: 'audio',
      mime_type: 'audio/mpeg',
      file_size: 1
    });

    const result = await createAudioAnalysis({
      file_id: file.file_id,
      duration_seconds: Math.max(duration.value, 1),
      text: text.value || '不要告诉家人，把验证码发给我'
    });

    uni.navigateTo({ url: `/pages/report/index?record_id=${result.record_id}` });
  } finally {
    loading.value = false;
    uni.hideLoading();
  }
}

function clearTimer() {
  if (timer) {
    clearInterval(timer);
    timer = null;
  }
}
</script>

<template>
  <view class="page">
    <view class="section">
      <view class="title">录音前提醒</view>
      <view class="subtitle">请将电话外放，或让手机靠近正在沟通的人声。系统会分析对话中的风险话术，仅作风险提醒参考。</view>
    </view>

    <view class="card recorder">
      <view class="timer">{{ displayDuration }}</view>
      <view class="muted">{{ recording ? '正在录音' : audioPath ? '录音已就绪' : '当前计费：10点/分钟' }}</view>
      <view v-if="!recording && !audioPath" class="button secondary" @tap="startRecord">开始录音</view>
      <view v-if="recording" class="button secondary" @tap="stopRecord">结束录音</view>
      <view v-if="audioPath && !recording" class="button ghost" @tap="resetRecord">重新录音</view>
    </view>

    <view class="card section">
      <view class="row">
        <view class="feature-title">对话文本</view>
        <view class="tag medium">联调用</view>
      </view>
      <textarea v-model="text" class="textarea" placeholder="可粘贴录音转写，如：不要告诉家人，把验证码发给我" />
    </view>

    <view class="cost">不足 1 分钟按 1 分钟计算</view>
    <view class="button" :class="{ disabled: !canSubmit }" @tap="submit">结束并分析</view>
  </view>
</template>

<style scoped>
.recorder {
  text-align: center;
  margin-bottom: 28rpx;
  background: linear-gradient(145deg, #1d1d1f, #3a3a3c);
  color: #ffffff;
  box-shadow: 0 22rpx 54rpx rgba(29, 29, 31, 0.22);
}

.recorder .muted {
  color: rgba(255, 255, 255, 0.68);
}

.timer {
  font-size: 72rpx;
  font-weight: 800;
  color: #ffd60a;
  margin-bottom: 12rpx;
}

.recorder .button {
  margin-top: 32rpx;
}

.feature-title {
  font-size: 32rpx;
  font-weight: 700;
}

.cost {
  color: #86868b;
  margin: 28rpx 0 18rpx;
  text-align: center;
}
</style>

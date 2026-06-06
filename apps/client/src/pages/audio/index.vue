<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { onLoad } from '@dcloudio/uni-app';
import { createAudioAnalysis, uploadCommonFile } from '@/api/client';
import { durationText } from '@/constants/risk';
import { ensureLogin } from '@/stores/session';
import '@/styles/common.scss';

const recording = ref(false);
const audioPath = ref('');
const duration = ref(0);
const text = ref('');
const loading = ref(false);
const startedAt = ref(0);
const startingRecord = ref(false);
let timer: ReturnType<typeof setInterval> | null = null;
const recorder = uni.getRecorderManager();
let webRecorder: MediaRecorder | null = null;
let webAudioChunks: Blob[] = [];

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
  if (recording.value) {
    stopRecord();
  }
  clearTimer();
});

// 方法：判断当前是否在普通浏览器 H5 环境，用于切换到 MediaRecorder 兼容实现
function isWebRecorderAvailable() {
  return typeof window !== 'undefined'
    && Boolean(navigator?.mediaDevices?.getUserMedia)
    && typeof MediaRecorder !== 'undefined';
}

// 方法：统一初始化录音 UI 状态，开始录音和重新录音时都需要清理旧音频
function prepareRecordingState() {
  clearTimer();
  startedAt.value = Date.now();
  recording.value = true;
  audioPath.value = '';
  duration.value = 0;
  timer = setInterval(() => {
    duration.value = Math.floor((Date.now() - startedAt.value) / 1000);
  }, 1000);
}

// 方法：开始录音，H5 使用浏览器 MediaRecorder，小程序/App 继续使用 uni recorder
async function startRecord() {
  if (recording.value || startingRecord.value) {
    return;
  }

  startingRecord.value = true;
  if (isWebRecorderAvailable()) {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      webAudioChunks = [];
      webRecorder = new MediaRecorder(stream);
      webRecorder.ondataavailable = (event) => {
        if (event.data && event.data.size > 0) {
          webAudioChunks.push(event.data);
        }
      };
      webRecorder.onstop = () => {
        const blob = new Blob(webAudioChunks, { type: webRecorder?.mimeType || 'audio/webm' });
        audioPath.value = URL.createObjectURL(blob);
        webRecorder?.stream.getTracks().forEach((track) => track.stop());
        webRecorder = null;
        recording.value = false;
      };
      prepareRecordingState();
      webRecorder.start();
      startingRecord.value = false;
      return;
    } catch (error) {
      uni.showToast({ title: '无法使用麦克风', icon: 'none' });
      recording.value = false;
      startingRecord.value = false;
      clearTimer();
      return;
    }
  }

  prepareRecordingState();
  try {
    recorder.start({
      duration: 600000,
      sampleRate: 16000,
      numberOfChannels: 1,
      encodeBitRate: 48000,
      format: 'mp3'
    });
  } finally {
    startingRecord.value = false;
  }
}

// 方法：结束录音，H5 和 uni recorder 分别停止，并用本地状态兜底避免按钮卡住
function stopRecord() {
  if (!recording.value) {
    return;
  }

  clearTimer();
  duration.value = Math.max(1, Math.ceil((Date.now() - startedAt.value) / 1000));

  if (webRecorder) {
    webRecorder.stop();
    return;
  }

  recorder.stop();
  setTimeout(() => {
    if (recording.value) {
      clearTimer();
      duration.value = Math.max(1, Math.ceil((Date.now() - startedAt.value) / 1000));
      recording.value = false;
    }
  }, 300);
}

// 方法：清空当前录音结果，允许用户重新录制
function resetRecord() {
  clearTimer();
  audioPath.value = '';
  duration.value = 0;
}

async function submit() {
  if (!canSubmit.value) return;

  loading.value = true;
  uni.showLoading({ title: '分析中' });

  try {
    const file = audioPath.value
      ? await uploadCommonFile(audioPath.value, 'audio', 'analysis_audio')
      : null;

    const result = await createAudioAnalysis({
      file_id: file?.file_id || 0,
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
      <view v-if="!recording && !audioPath" class="button secondary record-button" @click="startRecord">开始录音</view>
      <view v-if="recording" class="button secondary record-button stop-button" @click="stopRecord">结束录音</view>
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
  position: relative;
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
  position: relative;
  z-index: 2;
  margin-top: 32rpx;
  cursor: pointer;
  user-select: none;
  pointer-events: auto;
}

.record-button {
  max-width: 520rpx;
  margin-left: auto;
  margin-right: auto;
}

.stop-button {
  background: #ff453a;
  box-shadow: 0 12rpx 28rpx rgba(255, 69, 58, 0.24);
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

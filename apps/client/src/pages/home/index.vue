<script setup lang="ts">
import { onShow } from '@dcloudio/uni-app';
import { ensureLogin } from '@/stores/session';
import '@/styles/common.scss';

onShow(() => {
  void ensureLogin();
});

function goImage() {
  uni.navigateTo({ url: '/pages/image/index' });
}

function goAudio() {
  uni.navigateTo({ url: '/pages/audio/index' });
}
</script>

<template>
  <view class="page home">
    <view class="section">
      <view class="hello">您好！</view>
      <view class="title">帮看帮听</view>
      <view class="subtitle">帮您判断宣传是否可信，以防上当受骗</view>
    </view>

    <view class="card feature visual">
      <view class="row">
        <view>
          <view class="feature-title">帮您看</view>
          <view class="subtitle">拍照或截图，一秒看懂是真是假、值不值得买</view>
        </view>
        <view class="price">20点/次</view>
      </view>
      <view class="button" @tap="goImage">拍照 / 截图</view>
    </view>

    <view class="card feature dark">
      <view class="row">
        <view>
          <view class="feature-title">帮您听</view>
          <view class="subtitle">录音分析对话，识破套路话术，及时提醒你</view>
        </view>
        <view class="price orange">10点/分钟</view>
      </view>
      <view class="button secondary" @tap="goAudio">开始帮您听</view>
    </view>
  </view>
</template>

<style scoped>
.hello {
  font-size: 34rpx;
  font-weight: 600;
  margin-bottom: 16rpx;
  color: #86868b;
}

.feature {
  position: relative;
  overflow: hidden;
  margin-bottom: 28rpx;
  min-height: 300rpx;
}

.feature.visual {
  background:
    linear-gradient(135deg, rgba(255, 255, 255, 0.96), rgba(242, 248, 255, 0.92)),
    #ffffff;
}

.feature.visual::after {
  content: "";
  position: absolute;
  right: -48rpx;
  top: -48rpx;
  width: 190rpx;
  height: 190rpx;
  border-radius: 96rpx;
  background: rgba(0, 113, 227, 0.12);
}

.feature.dark {
  background: linear-gradient(145deg, #1d1d1f, #3a3a3c);
  color: #ffffff;
  box-shadow: 0 22rpx 54rpx rgba(29, 29, 31, 0.22);
}

.feature.dark .subtitle {
  color: rgba(255, 255, 255, 0.72);
}

.feature-title {
  font-size: 40rpx;
  font-weight: 800;
}

.price {
  flex-shrink: 0;
  color: #0071e3;
  font-size: 26rpx;
  font-weight: 700;
}

.price.orange {
  color: #ffd60a;
}

.feature .button {
  margin-top: 32rpx;
}
</style>

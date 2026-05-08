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

    <view class="card feature">
      <view class="row">
        <view>
          <view class="feature-title">帮您看</view>
          <view class="subtitle">拍照或截图，一秒看懂是真是假、值不值得买</view>
        </view>
        <view class="price">20点/次</view>
      </view>
      <view class="button" @tap="goImage">拍照 / 截图</view>
    </view>

    <view class="card feature">
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
}

.feature {
  margin-bottom: 28rpx;
}

.feature-title {
  font-size: 40rpx;
  font-weight: 700;
}

.price {
  flex-shrink: 0;
  color: #1f8a5b;
  font-size: 26rpx;
  font-weight: 700;
}

.price.orange {
  color: #d87845;
}

.feature .button {
  margin-top: 32rpx;
}
</style>

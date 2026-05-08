<script setup lang="ts">
import { computed, ref } from 'vue';
import { onShow } from '@dcloudio/uni-app';
import { createPaymentOrder, getTransactions } from '@/api/client';
import { ensureLogin, resetSession, session } from '@/stores/session';
import type { PointTransaction, UserInfo } from '@/types/api';
import '@/styles/common.scss';

const user = ref<UserInfo | null>(null);
const transactions = ref<PointTransaction[]>([]);
const showTransactions = ref(false);
const initial = computed(() => (user.value?.nickname || '微').slice(0, 1));

onShow(async () => {
  user.value = await ensureLogin();
});

async function recharge() {
  const result = await createPaymentOrder({ package_id: 'points_100' });
  uni.showModal({
    title: '充值点数',
    content: result.message || 'MVP 暂未接入微信支付',
    showCancel: false
  });
}

async function goTransactions() {
  const result = await getTransactions();
  transactions.value = result.items;
  showTransactions.value = true;
}

function goContent(type: 'agreement' | 'privacy') {
  uni.navigateTo({ url: `/pages/content/index?type=${type}` });
}

function contact() {
  uni.showModal({
    title: '联系客服',
    content: '请保留可疑材料，必要时拨打 96110 咨询。',
    showCancel: false
  });
}

function clearLocal() {
  resetSession();
  session.user = null;
  user.value = null;
  uni.showToast({ title: '已清除', icon: 'success' });
}
</script>

<template>
  <view class="page">
    <view class="card profile">
      <view class="avatar">{{ initial }}</view>
      <view>
        <view class="name">{{ user?.nickname || '微信用户' }}</view>
        <view class="muted">点数余额</view>
      </view>
      <view class="points">{{ user?.points_balance || 0 }}</view>
    </view>

    <view class="button section" @tap="recharge">充值点数</view>

    <view class="card menu">
      <view class="menu-item" @tap="goTransactions">使用记录</view>
      <view class="menu-item" @tap="goContent('agreement')">用户协议</view>
      <view class="menu-item" @tap="goContent('privacy')">隐私政策</view>
      <view class="menu-item" @tap="contact">联系客服</view>
      <view class="menu-item danger" @tap="clearLocal">清除本机登录</view>
    </view>

    <view v-if="showTransactions" class="card transactions">
      <view class="block-title">点数流水</view>
      <view v-if="!transactions.length" class="empty">暂无流水</view>
      <view v-for="item in transactions" :key="item.id" class="list-item">
        <view class="row">
          <view>{{ item.remark || item.type }}</view>
          <view :class="item.amount < 0 ? 'minus' : 'plus'">{{ item.amount }}</view>
        </view>
        <view class="muted">{{ item.created_at }} · 余额 {{ item.balance_after }}</view>
      </view>
    </view>
  </view>
</template>

<style scoped>
.profile {
  display: flex;
  align-items: center;
  gap: 24rpx;
  margin-bottom: 28rpx;
}

.avatar {
  width: 104rpx;
  height: 104rpx;
  border-radius: 52rpx;
  background: #1f8a5b;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 42rpx;
  font-weight: 700;
}

.name {
  font-size: 34rpx;
  font-weight: 700;
}

.points {
  margin-left: auto;
  font-size: 52rpx;
  font-weight: 800;
  color: #1f8a5b;
}

.menu-item {
  padding: 30rpx 0;
  border-bottom: 1rpx solid #edf0ed;
}

.menu-item:last-child {
  border-bottom: none;
}

.danger {
  color: #cf2c2c;
}

.block-title {
  font-size: 34rpx;
  font-weight: 700;
  margin-bottom: 14rpx;
}

.minus {
  color: #cf2c2c;
  font-weight: 700;
}

.plus {
  color: #1f8a5b;
  font-weight: 700;
}
</style>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { onShow, onUnload } from '@dcloudio/uni-app';
import { codeLogin, createPaymentOrder, getPaymentPackages, getTransactions, me, sendCode } from '@/api/client';
import { ensureLogin, resetSession, session } from '@/stores/session';
import { setToken } from '@/api/request';
import type { PaymentPackage, PointTransaction, UserInfo } from '@/types/api';
import '@/styles/common.scss';

const user = ref<UserInfo | null>(null);
const transactions = ref<PointTransaction[]>([]);
const packages = ref<PaymentPackage[]>([]);
const showTransactions = ref(false);
const account = ref('');
const code = ref('');
const sendingCode = ref(false);
const codeCountdown = ref(0);
const loggingIn = ref(false);
const selectedPackageId = ref<number | null>(null);
const initial = computed(() => (user.value?.nickname || '微').slice(0, 1));
const selectedPackage = computed(() => packages.value.find((item) => item.id === selectedPackageId.value) || packages.value[0] || null);
const codeButtonText = computed(() => {
  if (sendingCode.value) {
    return '发送中';
  }

  return codeCountdown.value > 0 ? `${codeCountdown.value}s` : '发验证码';
});
let codeCountdownTimer: ReturnType<typeof setInterval> | null = null;

onShow(async () => {
  try {
    user.value = await ensureLogin();
  } catch (error) {
    user.value = null;
  }
  packages.value = await getPaymentPackages();
  if (!selectedPackageId.value && packages.value[0]) {
    selectedPackageId.value = packages.value[0].id;
  }
});

// 方法：清理验证码倒计时定时器，避免页面隐藏或卸载后仍继续更新状态
function clearCodeCountdownTimer() {
  if (codeCountdownTimer) {
    clearInterval(codeCountdownTimer);
    codeCountdownTimer = null;
  }
}

// 方法：发送验证码成功后启动 60 秒倒计时，防止用户高频重复请求验证码接口
function startCodeCountdown(seconds = 60) {
  clearCodeCountdownTimer();
  codeCountdown.value = seconds;
  codeCountdownTimer = setInterval(() => {
    codeCountdown.value -= 1;
    if (codeCountdown.value <= 0) {
      codeCountdown.value = 0;
      clearCodeCountdownTimer();
    }
  }, 1000);
}

onUnload(() => {
  clearCodeCountdownTimer();
});

// 方法：发送邮箱/手机号验证码，给非微信环境提供注册登录入口
async function submitSendCode() {
  if (sendingCode.value || codeCountdown.value > 0) {
    return;
  }

  if (!account.value) {
    uni.showToast({ title: '请输入邮箱或手机号', icon: 'none' });
    return;
  }

  sendingCode.value = true;
  try {
    const result = await sendCode({ account: account.value, scene: 'login' });
    const debug = result.debug_code ? `：${result.debug_code}` : '';
    uni.showToast({ title: `验证码已发送${debug}`, icon: 'none' });
    startCodeCountdown(60);
  } finally {
    sendingCode.value = false;
  }
}

// 方法：验证码登录注册一体化，成功后写入公共 token 并刷新当前用户
async function submitCodeLogin() {
  if (!account.value || !code.value) {
    uni.showToast({ title: '请输入账号和验证码', icon: 'none' });
    return;
  }

  loggingIn.value = true;
  try {
    const result = await codeLogin({ account: account.value, code: code.value, scene: 'login' });
    setToken(result.token);
    session.user = result.user;
    user.value = result.user;
    uni.showToast({ title: result.is_new_user ? '注册成功' : '登录成功', icon: 'success' });
  } finally {
    loggingIn.value = false;
  }
}

// 方法：用户手动选择点数套餐，后续下单使用当前选中的套餐
function selectPackage(packageId: number) {
  selectedPackageId.value = packageId;
}

// 方法：支付完成或订单创建后刷新公共用户余额，保证钱包到账状态及时反馈到页面
async function refreshUser() {
  const latest = await me();
  session.user = latest;
  user.value = latest;
}

// 方法：创建微信支付订单，真实支付成功后刷新余额；mock 模式保留订单号便于联调
async function recharge() {
  if (!user.value) {
    uni.showToast({ title: '请先登录', icon: 'none' });
    return;
  }

  const selected = selectedPackage.value;
  if (!selected) {
    uni.showToast({ title: '暂无可用套餐', icon: 'none' });
    return;
  }

  const result = await createPaymentOrder({ package_id: selected.id });
  const params = result.payment_params;
  if (typeof uni.requestPayment === 'function' && !params.mock) {
    await uni.requestPayment({
      provider: 'wxpay',
      timeStamp: String(params.timeStamp || ''),
      nonceStr: String(params.nonceStr || ''),
      package: String(params.package || ''),
      signType: String(params.signType || 'RSA'),
      paySign: String(params.paySign || '')
    });
    await refreshUser();
    return;
  }

  uni.showModal({ title: '充值点数', content: `订单已创建：${result.order_no}`, showCancel: false });
  await refreshUser();
}

// 方法：拉取公共钱包流水，用于查看充值和分析扣点记录
async function goTransactions() {
  if (!user.value) {
    uni.showToast({ title: '请先登录', icon: 'none' });
    return;
  }

  const result = await getTransactions();
  transactions.value = result.items;
  showTransactions.value = true;
}

// 方法：跳转协议/隐私静态内容页
function goContent(type: 'agreement' | 'privacy') {
  uni.navigateTo({ url: `/pages/content/index?type=${type}` });
}

// 方法：展示客服提醒，保留本地入口方便用户咨询高风险材料
function contact() {
  uni.showModal({
    title: '联系客服',
    content: '请保留可疑材料，必要时拨打 96110 咨询。',
    showCancel: false
  });
}

// 方法：清理本机 token 和缓存用户，方便切换账号测试
function clearLocal() {
  resetSession();
  session.user = null;
  user.value = null;
  uni.showToast({ title: '已清除', icon: 'success' });
}
</script>

<template>
  <view class="page">
    <view v-if="!user" class="card login-card section">
      <view class="block-title">登录 / 注册</view>
      <view class="muted login-copy">邮箱或手机号验证后会自动创建公共账号。</view>
      <input v-model="account" class="input" placeholder="邮箱或手机号" />
      <view class="code-row">
        <input v-model="code" class="input" placeholder="验证码" />
        <view class="button ghost code-button" :class="{ disabled: sendingCode || codeCountdown > 0 }" @tap="submitSendCode">
          {{ codeButtonText }}
        </view>
      </view>
      <view class="button" :class="{ disabled: loggingIn }" @tap="submitCodeLogin">
        {{ loggingIn ? '登录中' : '登录 / 注册' }}
      </view>
    </view>

    <view class="card profile">
      <view class="avatar">{{ initial }}</view>
      <view>
        <view class="name">{{ user?.nickname || '未登录' }}</view>
        <view class="muted">点数余额</view>
      </view>
      <view class="points">{{ user?.points_balance || 0 }}</view>
    </view>

    <view v-if="packages.length" class="packages section">
      <view
        v-for="item in packages"
        :key="item.id"
        class="package-item"
        :class="{ active: selectedPackageId === item.id }"
        @tap="selectPackage(item.id)"
      >
        <view>
          <view class="package-name">{{ item.name }}</view>
          <view class="muted">{{ item.points }} 点</view>
        </view>
        <view class="package-price">¥{{ (item.amount_cent / 100).toFixed(2) }}</view>
      </view>
    </view>

    <view class="button section" @tap="recharge">
      充值点数
      <text v-if="selectedPackage"> · {{ selectedPackage.name }} ¥{{ (selectedPackage.amount_cent / 100).toFixed(2) }}</text>
    </view>

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
      <view v-for="item in transactions" :key="item.id || item.transaction_no" class="list-item">
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
  background: linear-gradient(145deg, rgba(255, 255, 255, 0.94), rgba(245, 245, 247, 0.9));
}

.avatar {
  width: 104rpx;
  height: 104rpx;
  border-radius: 52rpx;
  background: linear-gradient(145deg, #0071e3, #62a8ff);
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
  color: #0071e3;
}

.packages {
  display: grid;
  gap: 18rpx;
}

.package-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18rpx;
  padding: 26rpx 28rpx;
  border-radius: 24rpx;
  background: rgba(255, 255, 255, 0.86);
  border: 1rpx solid rgba(0, 0, 0, 0.06);
}

.package-item.active {
  border-color: #0071e3;
  background: rgba(0, 113, 227, 0.08);
}

.package-name {
  font-weight: 700;
}

.package-price {
  font-size: 34rpx;
  font-weight: 800;
  color: #0071e3;
}

.menu-item {
  padding: 30rpx 0;
  border-bottom: 1rpx solid rgba(0, 0, 0, 0.08);
}

.menu-item:last-child {
  border-bottom: none;
}

.danger {
  color: #d70015;
}

.block-title {
  font-size: 34rpx;
  font-weight: 700;
  margin-bottom: 14rpx;
}

.login-copy {
  margin-bottom: 24rpx;
}

.input {
  box-sizing: border-box;
  width: 100%;
  height: 84rpx;
  padding: 0 24rpx;
  margin-bottom: 20rpx;
  border-radius: 20rpx;
  background: #f5f5f7;
  border: 1rpx solid rgba(0, 0, 0, 0.06);
  color: #1d1d1f;
}

.code-row {
  display: grid;
  grid-template-columns: 1fr 190rpx;
  gap: 16rpx;
  align-items: start;
}

.code-button {
  height: 84rpx;
  border-radius: 20rpx;
  font-size: 28rpx;
}

.minus {
  color: #d70015;
  font-weight: 700;
}

.plus {
  color: #34c759;
  font-weight: 700;
}
</style>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { onShow, onUnload } from '@dcloudio/uni-app';
import {
  codeLogin,
  createAlipayOrder,
  createPaymentOrder,
  getPaymentOrder,
  getPaymentPackages,
  getTransactions,
  passwordLogin,
  passwordRegister,
  sendCode
} from '@/api/client';
import { refreshSession, resetSession, session } from '@/stores/session';
import { setToken } from '@/api/request';
import type { AlipayPaymentParams, LoginResponse, PaymentPackage, PointTransaction, UserInfo, WechatPaymentParams } from '@/types/api';
import '@/styles/common.scss';

type LoginMode = 'code' | 'password-login' | 'password-register';
type PayChannel = 'wechat' | 'alipay';

const user = ref<UserInfo | null>(null);
const transactions = ref<PointTransaction[]>([]);
const packages = ref<PaymentPackage[]>([]);
const showTransactions = ref(false);
const account = ref('');
const code = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const nickname = ref('');
const loginMode = ref<LoginMode>('code');
const payChannel = ref<PayChannel>('alipay');
const sendingCode = ref(false);
const codeCountdown = ref(0);
const loggingIn = ref(false);
const loadingPackages = ref(false);
const recharging = ref(false);
const loadingTransactions = ref(false);
const selectedPackageId = ref<number | null>(null);
const showAlipayPanel = ref(false);
const alipayOrderNo = ref('');
const alipayQrCode = ref('');
const alipayStatusText = ref('等待支付');
const alipayPolling = ref(false);
const initial = computed(() => (user.value?.nickname || '微').slice(0, 1));
const selectedPackage = computed(() => packages.value.find((item) => item.id === selectedPackageId.value) || packages.value[0] || null);
const isCodeMode = computed(() => loginMode.value === 'code');
const isPasswordLoginMode = computed(() => loginMode.value === 'password-login');
const isPasswordRegisterMode = computed(() => loginMode.value === 'password-register');
const codeButtonText = computed(() => {
  if (sendingCode.value) {
    return '发送中';
  }

  return codeCountdown.value > 0 ? `${codeCountdown.value}s` : '发验证码';
});
const loginButtonText = computed(() => {
  if (loggingIn.value) {
    return '处理中';
  }

  if (isPasswordRegisterMode.value) {
    return '注册并登录';
  }

  return isPasswordLoginMode.value ? '密码登录' : '登录 / 注册';
});
const rechargeButtonText = computed(() => {
  if (recharging.value) {
    return '创建订单中';
  }

  return payChannel.value === 'alipay' ? '支付宝扫码充值' : '微信充值点数';
});
let codeCountdownTimer: ReturnType<typeof setInterval> | null = null;
let alipayPollTimer: ReturnType<typeof setTimeout> | null = null;
let alipayPollCount = 0;

onShow(async () => {
  // 业务逻辑：页面显示时刷新用户和套餐，失败不阻断页面展示，避免刷新后出现未处理异常
  try {
    user.value = await refreshSession();
  } catch (error) {
    user.value = null;
  }
  await loadPackages();
});

onUnload(() => {
  clearCodeCountdownTimer();
  stopAlipayPolling();
});

// 方法：拉取充值套餐并保留用户已选套餐，避免切回页面时选择丢失
async function loadPackages() {
  if (loadingPackages.value) {
    return;
  }

  loadingPackages.value = true;
  try {
    packages.value = await getPaymentPackages();
    if (!selectedPackageId.value && packages.value[0]) {
      selectedPackageId.value = packages.value[0].id;
    }
  } finally {
    loadingPackages.value = false;
  }
}

// 方法：切换登录模式时保留账号但清理一次性字段，减少误提交
function changeLoginMode(mode: LoginMode) {
  loginMode.value = mode;
  code.value = '';
  password.value = '';
  passwordConfirmation.value = '';
}

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

// 方法：统一处理登录成功后的 token、用户缓存和输入状态，避免三种登录方式重复写状态
function applyLoginResult(result: LoginResponse) {
  setToken(result.token);
  session.user = result.user;
  user.value = result.user;
  code.value = '';
  password.value = '';
  passwordConfirmation.value = '';
  uni.showToast({ title: result.is_new_user ? '注册成功' : '登录成功', icon: 'success' });
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
    applyLoginResult(result);
  } finally {
    loggingIn.value = false;
  }
}

// 方法：密码登录，减少用户重复收取验证码的操作成本
async function submitPasswordLogin() {
  if (!account.value || !password.value) {
    uni.showToast({ title: '请输入账号和密码', icon: 'none' });
    return;
  }

  loggingIn.value = true;
  try {
    const result = await passwordLogin({ account: account.value, password: password.value });
    applyLoginResult(result);
  } finally {
    loggingIn.value = false;
  }
}

// 方法：密码注册，注册成功后立即登录并写入本地登录态
async function submitPasswordRegister() {
  if (!account.value || !password.value || !passwordConfirmation.value) {
    uni.showToast({ title: '请输入账号、密码和确认密码', icon: 'none' });
    return;
  }

  if (password.value !== passwordConfirmation.value) {
    uni.showToast({ title: '两次输入密码不一致', icon: 'none' });
    return;
  }

  loggingIn.value = true;
  try {
    const result = await passwordRegister({
      account: account.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
      nickname: nickname.value || undefined
    });
    applyLoginResult(result);
  } finally {
    loggingIn.value = false;
  }
}

// 方法：根据当前登录模式分发提交逻辑，保证按钮 loading 和校验一致
async function submitLogin() {
  if (loggingIn.value) {
    return;
  }

  if (isPasswordRegisterMode.value) {
    await submitPasswordRegister();
    return;
  }

  if (isPasswordLoginMode.value) {
    await submitPasswordLogin();
    return;
  }

  await submitCodeLogin();
}

// 方法：用户手动选择点数套餐，后续下单使用当前选中的套餐
function selectPackage(packageId: number) {
  selectedPackageId.value = packageId;
}

// 方法：用户切换支付渠道，支付宝用于 H5 扫码，微信保留原生 requestPayment 能力
function selectPayChannel(channel: PayChannel) {
  payChannel.value = channel;
}

// 方法：支付完成或订单创建后刷新公共用户余额，保证钱包到账状态及时反馈到页面
async function refreshUser() {
  const latest = await refreshSession({ force: true });
  user.value = latest;
}

// 方法：创建充值订单，根据用户选择分发微信支付或支付宝扫码支付
async function recharge() {
  if (recharging.value) {
    return;
  }

  if (!user.value) {
    uni.showToast({ title: '请先登录', icon: 'none' });
    return;
  }

  if (payChannel.value === 'alipay') {
    await rechargeByAlipay();
    return;
  }

  await rechargeByWechat();
}

// 方法：创建微信支付订单，真实支付成功后刷新余额；mock 模式保留订单号便于联调
async function rechargeByWechat() {
  const selected = selectedPackage.value;
  if (!selected) {
    uni.showToast({ title: '暂无可用套餐', icon: 'none' });
    return;
  }

  recharging.value = true;
  try {
    const result = await createPaymentOrder({ package_id: selected.id });
    const params = result.payment_params as WechatPaymentParams;
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
  } finally {
    recharging.value = false;
  }
}

// 方法：创建支付宝扫码订单，展示二维码链接并启动到账轮询
async function rechargeByAlipay() {
  const selected = selectedPackage.value;
  if (!selected) {
    uni.showToast({ title: '暂无可用套餐', icon: 'none' });
    return;
  }

  recharging.value = true;
  try {
    const result = await createAlipayOrder({ package_id: selected.id });
    const params = result.payment_params as AlipayPaymentParams;
    alipayOrderNo.value = result.order_no;
    alipayQrCode.value = params.qr_code;
    alipayStatusText.value = params.mock ? '模拟订单已创建，等待回调入账' : '请使用支付宝扫码支付';
    showAlipayPanel.value = true;
    startAlipayPolling(result.order_no);
  } finally {
    recharging.value = false;
  }
}

// 方法：复制支付宝二维码链接，H5 第一版不引入二维码依赖也可完成扫码联调
function copyAlipayLink() {
  if (!alipayQrCode.value) {
    return;
  }

  uni.setClipboardData({
    data: alipayQrCode.value,
    success() {
      uni.showToast({ title: '已复制支付链接', icon: 'success' });
    }
  });
}

// 方法：关闭支付宝支付面板并停止轮询，避免用户离开后后台继续请求
function closeAlipayPanel() {
  showAlipayPanel.value = false;
  stopAlipayPolling();
}

// 方法：启动支付宝订单状态轮询，支付成功后自动刷新余额
function startAlipayPolling(orderNo: string) {
  stopAlipayPolling();
  alipayPollCount = 0;
  alipayPolling.value = true;
  scheduleAlipayPolling(orderNo);
}

// 方法：按固定间隔查询订单状态，并限制最大轮询次数避免长时间占用资源
function scheduleAlipayPolling(orderNo: string) {
  alipayPollTimer = setTimeout(async () => {
    alipayPollCount += 1;
    try {
      const status = await getPaymentOrder(orderNo);
      alipayStatusText.value = status.status === 'paid' ? '支付成功，正在刷新余额' : '等待支付到账';
      if (status.status === 'paid') {
        stopAlipayPolling();
        showAlipayPanel.value = false;
        await refreshUser();
        uni.showToast({ title: '充值成功', icon: 'success' });
        return;
      }

      if (alipayPollCount < 60 && showAlipayPanel.value) {
        scheduleAlipayPolling(orderNo);
        return;
      }

      alipayPolling.value = false;
      alipayStatusText.value = '未查询到到账，可稍后手动刷新余额';
    } catch (error) {
      alipayPolling.value = false;
      alipayStatusText.value = '查询订单失败，请稍后刷新';
    }
  }, 3000);
}

// 方法：停止支付宝支付轮询，页面卸载或弹窗关闭时调用
function stopAlipayPolling() {
  if (alipayPollTimer) {
    clearTimeout(alipayPollTimer);
    alipayPollTimer = null;
  }
  alipayPolling.value = false;
}

// 方法：拉取公共钱包流水，用于查看充值和分析扣点记录
async function goTransactions() {
  if (!user.value) {
    uni.showToast({ title: '请先登录', icon: 'none' });
    return;
  }

  if (loadingTransactions.value) {
    return;
  }

  loadingTransactions.value = true;
  try {
    const result = await getTransactions();
    transactions.value = result.items;
    showTransactions.value = true;
  } finally {
    loadingTransactions.value = false;
  }
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
  user.value = null;
  closeAlipayPanel();
  uni.showToast({ title: '已清除', icon: 'success' });
}
</script>

<template>
  <view class="page">
    <view v-if="!user" class="card login-card section">
      <view class="block-title">登录 / 注册</view>
      <view class="muted login-copy">支持邮箱或手机号验证码登录，也可以注册密码后长期登录。</view>

      <view class="mode-tabs">
        <view class="mode-tab" :class="{ active: isCodeMode }" @tap="changeLoginMode('code')">验证码</view>
        <view class="mode-tab" :class="{ active: isPasswordLoginMode }" @tap="changeLoginMode('password-login')">密码登录</view>
        <view class="mode-tab" :class="{ active: isPasswordRegisterMode }" @tap="changeLoginMode('password-register')">密码注册</view>
      </view>

      <input v-model="account" class="input" placeholder="邮箱或手机号" />
      <view v-if="isCodeMode" class="code-row">
        <input v-model="code" class="input" placeholder="验证码" />
        <view class="button ghost code-button" :class="{ disabled: sendingCode || codeCountdown > 0 }" @tap="submitSendCode">
          {{ codeButtonText }}
        </view>
      </view>
      <block v-else>
        <input v-model="password" class="input" password placeholder="密码（至少 8 位，含字母和数字）" />
        <input v-if="isPasswordRegisterMode" v-model="passwordConfirmation" class="input" password placeholder="确认密码" />
        <input v-if="isPasswordRegisterMode" v-model="nickname" class="input" placeholder="昵称（可选）" />
      </block>
      <view class="button" :class="{ disabled: loggingIn }" @tap="submitLogin">
        {{ loginButtonText }}
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

    <view v-if="loadingPackages" class="card section muted">正在加载套餐...</view>
    <view v-else-if="packages.length" class="packages section">
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
    <view v-else class="card section empty">暂无可用套餐</view>

    <view class="pay-channel section">
      <view class="channel-item" :class="{ active: payChannel === 'alipay' }" @tap="selectPayChannel('alipay')">支付宝扫码</view>
      <view class="channel-item" :class="{ active: payChannel === 'wechat' }" @tap="selectPayChannel('wechat')">微信支付</view>
    </view>

    <view class="button section" :class="{ disabled: recharging }" @tap="recharge">
      {{ rechargeButtonText }}
      <text v-if="selectedPackage"> · {{ selectedPackage.name }} ¥{{ (selectedPackage.amount_cent / 100).toFixed(2) }}</text>
    </view>

    <view v-if="showAlipayPanel" class="card alipay-panel section">
      <view class="row">
        <view class="block-title">支付宝扫码支付</view>
        <view class="tag" @tap="closeAlipayPanel">关闭</view>
      </view>
      <view class="muted">订单号：{{ alipayOrderNo }}</view>
      <view class="qr-link">{{ alipayQrCode }}</view>
      <view class="button ghost section" @tap="copyAlipayLink">复制支付链接</view>
      <view class="muted">{{ alipayStatusText }}{{ alipayPolling ? '...' : '' }}</view>
    </view>

    <view class="card menu">
      <view class="menu-item" @tap="goTransactions">{{ loadingTransactions ? '加载中...' : '使用记录' }}</view>
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

.pay-channel,
.mode-tabs {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 14rpx;
}

.mode-tabs {
  grid-template-columns: repeat(3, 1fr);
  margin-bottom: 22rpx;
}

.channel-item,
.mode-tab {
  padding: 18rpx 12rpx;
  border-radius: 22rpx;
  text-align: center;
  color: #6e6e73;
  background: rgba(255, 255, 255, 0.86);
  border: 1rpx solid rgba(0, 0, 0, 0.06);
  font-size: 26rpx;
}

.channel-item.active,
.mode-tab.active {
  color: #0071e3;
  background: rgba(0, 113, 227, 0.1);
  border-color: #0071e3;
  font-weight: 700;
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

.alipay-panel {
  border-color: rgba(0, 113, 227, 0.18);
}

.qr-link {
  margin: 22rpx 0;
  padding: 22rpx;
  border-radius: 20rpx;
  color: #0071e3;
  background: rgba(0, 113, 227, 0.08);
  word-break: break-all;
  line-height: 1.5;
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

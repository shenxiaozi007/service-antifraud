import { clearToken, getToken } from '@/api/request';
import { me } from '@/api/client';
import type { UserInfo } from '@/types/api';

export const session = {
  user: null as UserInfo | null,
  loadingUser: false
};

let refreshingPromise: Promise<UserInfo> | null = null;

// 方法：刷新公共用户信息，复用并发中的同一个请求，避免多页面 onShow 同时触发造成卡顿
export async function refreshSession(options: { force?: boolean } = {}): Promise<UserInfo> {
  if (!options.force && session.user) {
    return session.user;
  }

  if (!getToken()) {
    throw new Error('请先登录');
  }

  if (refreshingPromise) {
    return refreshingPromise;
  }

  session.loadingUser = true;
  refreshingPromise = me()
    .then((user) => {
      session.user = user;
      return user;
    })
    .catch((error) => {
      clearToken();
      session.user = null;
      throw error;
    })
    .finally(() => {
      session.loadingUser = false;
      refreshingPromise = null;
    });

  return refreshingPromise;
}

// 方法：确保页面已有登录用户，供首页、历史、分析页进入时复用
export async function ensureLogin(): Promise<UserInfo> {
  return refreshSession();
}

// 方法：清理本地登录态和缓存用户，切换账号或登录失效时调用
export function resetSession(): void {
  session.user = null;
  session.loadingUser = false;
  refreshingPromise = null;
  clearToken();
  uni.removeStorageSync('dev_openid');
}

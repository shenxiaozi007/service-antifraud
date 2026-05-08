import { clearToken, getToken, setToken } from '@/api/request';
import { me, wechatLogin } from '@/api/client';
import type { UserInfo } from '@/types/api';

export const session = {
  user: null as UserInfo | null
};

function storage(key: string): string {
  return String(uni.getStorageSync(key) || '');
}

export async function ensureLogin(): Promise<UserInfo> {
  if (session.user) {
    return session.user;
  }

  if (getToken()) {
    try {
      session.user = await me();
      return session.user;
    } catch (error) {
      clearToken();
    }
  }

  const openid = storage('dev_openid') || `dev_${Date.now()}`;
  uni.setStorageSync('dev_openid', openid);

  const result = await wechatLogin({
    code: String(Date.now()),
    openid,
    nickname: '微信用户'
  });

  setToken(result.token);
  session.user = result.user;
  return result.user;
}

export function resetSession(): void {
  session.user = null;
  clearToken();
  uni.removeStorageSync('dev_openid');
}

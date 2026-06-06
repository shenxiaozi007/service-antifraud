import { clearToken, getToken } from '@/api/request';
import { me } from '@/api/client';
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

  if (!getToken()) {
    throw new Error('请先登录');
  }

  try {
    session.user = await me();
    return session.user;
  } catch (error) {
    clearToken();
    throw error;
  }
}

export function resetSession(): void {
  session.user = null;
  clearToken();
  uni.removeStorageSync('dev_openid');
}

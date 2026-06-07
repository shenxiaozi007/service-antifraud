import { API_BASE_URL } from './config';
import type { ApiResponse } from '@/types/api';

type Method = 'GET' | 'POST' | 'PUT' | 'DELETE';

interface RequestOptions {
  url: string;
  method?: Method;
  data?: UniApp.RequestOptions['data'];
  showErrorToast?: boolean;
}

export function getToken(): string {
  return String(uni.getStorageSync('token') || '');
}

export function setToken(token: string): void {
  uni.setStorageSync('token', token);
}

export function clearToken(): void {
  uni.removeStorageSync('token');
}

export function request<T>(options: RequestOptions): Promise<T> {
  const token = getToken();
  const showErrorToast = options.showErrorToast !== false;

  return new Promise((resolve, reject) => {
    uni.request({
      url: `${API_BASE_URL}${options.url}`,
      method: options.method || 'GET',
      data: options.data,
      timeout: 15000,
      header: {
        'content-type': 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {})
      },
      success(response) {
        const body = response.data as ApiResponse<T>;
        if (response.statusCode >= 200 && response.statusCode < 300 && body?.code === 0) {
          resolve(body.data);
          return;
        }

        // 业务逻辑：登录态失效时统一清理本地 token，避免刷新页面后继续携带无效凭证重复请求
        if (response.statusCode === 401) {
          clearToken();
          const message = body?.message || '登录已失效，请重新登录';
          if (showErrorToast) {
            uni.showToast({ title: message, icon: 'none' });
          }
          reject(new Error(message));
          return;
        }

        const message = body?.message || `请求失败 ${response.statusCode}`;
        if (showErrorToast) {
          uni.showToast({ title: message, icon: 'none' });
        }
        reject(new Error(message));
      },
      fail(error) {
        const message = '网络连接失败，请稍后重试';
        if (showErrorToast) {
          uni.showToast({ title: message, icon: 'none' });
        }
        reject(error instanceof Error ? error : new Error(message));
      }
    });
  });
}

import { API_BASE_URL } from './config';
import type { ApiResponse } from '@/types/api';

type Method = 'GET' | 'POST' | 'PUT' | 'DELETE';

interface RequestOptions {
  url: string;
  method?: Method;
  data?: Record<string, unknown> | unknown[];
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

  return new Promise((resolve, reject) => {
    uni.request({
      url: `${API_BASE_URL}${options.url}`,
      method: options.method || 'GET',
      data: options.data,
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

        const message = body?.message || `请求失败 ${response.statusCode}`;
        uni.showToast({ title: message, icon: 'none' });
        reject(new Error(message));
      },
      fail(error) {
        uni.showToast({ title: '网络连接失败', icon: 'none' });
        reject(error);
      }
    });
  });
}

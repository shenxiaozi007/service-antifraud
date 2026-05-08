import { request } from './request';
import type {
  AnalysisRecord,
  CreateAnalysisResponse,
  LoginResponse,
  PageResult,
  PointTransaction,
  UploadTokenResponse,
  UserInfo
} from '@/types/api';

function query(params: Record<string, unknown>): string {
  const search = Object.entries(params)
    .filter(([, value]) => value !== '' && value !== undefined && value !== null)
    .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`)
    .join('&');

  return search ? `?${search}` : '';
}

export function wechatLogin(data: Record<string, unknown>) {
  return request<LoginResponse>({ url: '/api/v1/auth/wechat-login', method: 'POST', data });
}

export function me() {
  return request<UserInfo>({ url: '/api/v1/me' });
}

export function uploadToken(data: Record<string, unknown>) {
  return request<UploadTokenResponse>({ url: '/api/v1/files/upload-token', method: 'POST', data });
}

export function createImageAnalysis(data: Record<string, unknown>) {
  return request<CreateAnalysisResponse>({ url: '/api/v1/analysis/image', method: 'POST', data });
}

export function createAudioAnalysis(data: Record<string, unknown>) {
  return request<CreateAnalysisResponse>({ url: '/api/v1/analysis/audio', method: 'POST', data });
}

export function getReport(recordId: number) {
  return request<AnalysisRecord>({ url: `/api/v1/analysis/${recordId}` });
}

export function getRecords(params: Record<string, unknown>) {
  return request<PageResult<AnalysisRecord>>({ url: `/api/v1/analysis-records${query(params)}` });
}

export function getTransactions() {
  return request<PageResult<PointTransaction>>({ url: '/api/v1/points/transactions' });
}

export function createPaymentOrder(data: Record<string, unknown>) {
  return request<{ payment_params: Record<string, unknown>; status: string; message: string }>({
    url: '/api/v1/payments/wechat/order',
    method: 'POST',
    data
  });
}

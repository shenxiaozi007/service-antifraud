import { request } from './request';
import { FILE_BASE_URL } from './config';
import type {
  AnalysisRecord,
  AnalysisType,
  ApiResponse,
  CommonFileUploadResponse,
  CreateAnalysisResponse,
  LoginResponse,
  PageResult,
  PasswordLoginDTO,
  PasswordRegisterDTO,
  PaymentOrderResponse,
  PaymentOrderStatus,
  PaymentPackage,
  PointTransaction,
  RegisterFileResponse,
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

export function sendCode(data: Record<string, unknown>) {
  return request<{ expire_seconds: number; debug_code?: string }>({ url: '/api/v1/auth/send-code', method: 'POST', data });
}

export function codeLogin(data: Record<string, unknown>) {
  return request<LoginResponse>({ url: '/api/v1/auth/code-login', method: 'POST', data });
}

// 方法：密码注册公共账号，给邮箱用户提供长期登录方式
export function passwordRegister(data: PasswordRegisterDTO) {
  return request<LoginResponse>({ url: '/api/v1/auth/password-register', method: 'POST', data });
}

// 方法：密码登录公共账号，避免每次都依赖验证码
export function passwordLogin(data: PasswordLoginDTO) {
  return request<LoginResponse>({ url: '/api/v1/auth/password-login', method: 'POST', data });
}

export function me() {
  return request<UserInfo>({ url: '/api/v1/me' });
}

export function uploadToken(data: Record<string, unknown>) {
  return request<UploadTokenResponse>({ url: '/api/v1/files/upload-token', method: 'POST', data });
}

export function registerFile(data: Record<string, unknown>) {
  return request<RegisterFileResponse>({ url: '/api/v1/files/register', method: 'POST', data });
}

async function uploadCommonFileByFetch(filePath: string, bizType: string): Promise<CommonFileUploadResponse> {
  const fileResponse = await fetch(filePath);
  const blob = await fileResponse.blob();
  const formData = new FormData();
  const extension = blob.type.split('/')[1] || 'bin';

  formData.append('file', blob, `antifraud-${Date.now()}.${extension}`);
  formData.append('owner_project', 'antifraud');
  formData.append('biz_type', bizType);
  formData.append('disk', 'cloudflare_r2');

  const response = await fetch(`${FILE_BASE_URL}/service/api/v1/file/upload`, {
    method: 'POST',
    body: formData
  });
  const body = (await response.json()) as ApiResponse<CommonFileUploadResponse>;

  if (response.ok && body?.code === 0) {
    return body.data;
  }

  throw new Error(body?.message || `上传失败 ${response.status}`);
}

export function uploadCommonFile(filePath: string, fileType: AnalysisType, bizType: string) {
  return new Promise<CommonFileUploadResponse>((resolve, reject) => {
    if (typeof window !== 'undefined' && typeof FormData !== 'undefined') {
      uploadCommonFileByFetch(filePath, bizType).then(resolve).catch((error) => {
        uni.showToast({ title: error?.message || '文件上传失败', icon: 'none' });
        reject(error);
      });
      return;
    }

    uni.uploadFile({
      url: `${FILE_BASE_URL}/service/api/v1/file/upload`,
      filePath,
      name: 'file',
      formData: {
        owner_project: 'antifraud',
        biz_type: bizType,
        disk: 'cloudflare_r2'
      },
      success(response) {
        const body = JSON.parse(String(response.data || '{}'));
        if (response.statusCode >= 200 && response.statusCode < 300 && body?.code === 0) {
          resolve(body.data as CommonFileUploadResponse);
          return;
        }

        const message = body?.message || `上传失败 ${response.statusCode}`;
        uni.showToast({ title: message, icon: 'none' });
        reject(new Error(message));
      },
      fail(error) {
        uni.showToast({ title: '文件上传失败', icon: 'none' });
        reject(error);
      }
    });
  }).then((file) =>
    registerFile({
      storage_file_id: file.file_id,
      file_type: fileType,
      object_key: file.object_key,
      file_url: file.file_url,
      mime_type: file.mime_type,
      file_size: file.size
    })
  );
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

// 方法：创建微信支付订单，保留小程序和微信环境支付能力
export function createPaymentOrder(data: Record<string, unknown>) {
  return request<PaymentOrderResponse>({
    url: '/api/v1/payments/wechat/order',
    method: 'POST',
    data
  });
}

// 方法：创建支付宝扫码支付订单，H5 展示二维码链接并轮询订单状态
export function createAlipayOrder(data: Record<string, unknown>) {
  return request<PaymentOrderResponse>({
    url: '/api/v1/payments/alipay/order',
    method: 'POST',
    data
  });
}

// 方法：查询支付订单状态，用于支付宝扫码支付轮询到账
export function getPaymentOrder(orderNo: string) {
  return request<PaymentOrderStatus>({ url: `/api/v1/payments/orders/${encodeURIComponent(orderNo)}` });
}

export function getPaymentPackages() {
  return request<PaymentPackage[]>({ url: '/api/v1/payments/packages' });
}

export type RiskLevel = 'low' | 'medium' | 'high' | 'critical';
export type AnalysisType = 'image' | 'audio';
export type AnalysisStatus = 'pending' | 'processing' | 'success' | 'failed' | 'canceled';

export interface ApiResponse<T> {
  code: number;
  message: string;
  data: T;
}

export interface UserInfo {
  id: number;
  nickname: string | null;
  avatar_url: string | null;
  points_balance: number;
  status?: number;
}

export interface LoginResponse {
  token: string;
  user: UserInfo;
  is_new_user?: boolean;
}

export interface PasswordLoginDTO {
  account: string;
  password: string;
}

export interface PasswordRegisterDTO extends PasswordLoginDTO {
  password_confirmation: string;
  nickname?: string;
}

export interface UploadTokenResponse {
  upload_url: string;
  upload_method: 'multipart';
  register_url: string;
  owner_project: string;
  message: string;
}

export interface RegisterFileResponse {
  file_id: number;
  storage_file_id: string;
  file_type: AnalysisType;
  storage_key: string;
  file_url: string | null;
  mime_type: string | null;
  file_size: number;
}

export interface CommonFileUploadResponse {
  file_id: string;
  object_key: string;
  file_url: string;
  mime_type: string;
  size: number;
}

export interface RiskItem {
  category: string;
  severity: RiskLevel | string;
  description: string;
  evidence_text: string | null;
}

export interface FileAsset {
  id: number;
  file_type: AnalysisType;
  file_url: string | null;
  mime_type: string | null;
  file_size: number;
}

export interface AnalysisRecord {
  id: number;
  type: AnalysisType;
  title: string;
  risk_level: RiskLevel;
  risk_score: number;
  summary: string;
  suggestions: string[];
  status: AnalysisStatus;
  error_message?: string | null;
  cost_points: number;
  frozen_points?: number;
  image_count: number;
  duration_seconds: number;
  analyzed_at: string | null;
  created_at: string | null;
  risk_items?: RiskItem[];
  files?: FileAsset[];
  disclaimer?: string;
}

export interface PageResult<T> {
  items: T[];
  total: number;
  page: number;
  page_size: number;
}

export interface CreateAnalysisResponse {
  record_id: number;
  status: AnalysisStatus;
  frozen_points: number;
  cost_points: number;
  report: AnalysisRecord;
}

export interface PointTransaction {
  id?: number;
  transaction_no?: string;
  amount: number;
  frozen_amount?: number;
  balance_after: number;
  frozen_after?: number;
  type: string;
  status: string;
  remark: string | null;
  created_at: string | null;
}

export interface PaymentPackage {
  id: number;
  project_code: string;
  name: string;
  points: number;
  amount_cent: number;
}

export interface WechatPaymentParams {
  appId?: string;
  timeStamp?: string;
  nonceStr?: string;
  package?: string;
  signType?: string;
  paySign?: string;
  prepay_id?: string;
  mock?: boolean;
}

export interface AlipayPaymentParams {
  qr_code: string;
  order_no: string;
  trade_no?: string;
  mock?: boolean;
}

export interface PaymentOrderResponse {
  order_no: string;
  status: string;
  payment_params: WechatPaymentParams | AlipayPaymentParams;
}

export interface PaymentOrderStatus {
  order_no: string;
  status: string;
  channel: 'wechat' | 'alipay' | string;
  amount_cent: number;
  points: number;
  paid_at: string | null;
}

#!/usr/bin/env bash
set -euo pipefail

ANT_ENV="${ANT_ENV:-www/service.antifraud.local.com/.env}"
COMMON_ENV="${COMMON_ENV:-www/service.storage.company.com/.env}"

errors=()
warnings=()

fail() {
  errors+=("$*")
}

warn() {
  warnings+=("$*")
}

load_env_value() {
  local file="$1"
  local key="$2"
  local line value

  line="$(grep -E "^${key}=" "${file}" | tail -n 1 || true)"
  value="${line#*=}"
  value="${value%\"}"
  value="${value#\"}"
  value="${value%\'}"
  value="${value#\'}"

  printf '%s' "${value}"
}

require_file() {
  local file="$1"
  if [ ! -f "${file}" ]; then
    fail "env file missing: ${file}"
  fi
}

require_value() {
  local file="$1"
  local key="$2"
  local value

  value="$(load_env_value "${file}" "${key}")"
  if [ -z "${value}" ]; then
    fail "${file}: ${key} is required"
  fi
  if [[ "${value}" == "change-me" || "${value}" == "change-root-password" ]]; then
    fail "${file}: ${key} still uses placeholder value"
  fi
}

require_false() {
  local file="$1"
  local key="$2"
  local value

  value="$(load_env_value "${file}" "${key}")"
  if [ "${value}" != "false" ]; then
    fail "${file}: ${key} must be false for production"
  fi
}

require_url_prefix() {
  local file="$1"
  local key="$2"
  local prefix="$3"
  local value

  value="$(load_env_value "${file}" "${key}")"
  if [[ "${value}" != "${prefix}"* ]]; then
    fail "${file}: ${key} must start with ${prefix}"
  fi
}

if [ ! -f "${ANT_ENV}" ] || [ ! -f "${COMMON_ENV}" ]; then
  require_file "${ANT_ENV}"
  require_file "${COMMON_ENV}"
else
  require_value "${ANT_ENV}" APP_KEY
  require_value "${ANT_ENV}" DB_PASSWORD
  require_value "${ANT_ENV}" MYSQL_PASSWORD
  require_value "${ANT_ENV}" MYSQL_ROOT_PASSWORD
  require_value "${ANT_ENV}" COMMON_MYSQL_PASSWORD
  require_value "${ANT_ENV}" COMMON_SERVICE_SECRET
  require_value "${ANT_ENV}" LLM_BASE_URL
  require_value "${ANT_ENV}" LLM_API_KEY
  require_value "${ANT_ENV}" LLM_MODEL
  require_value "${ANT_ENV}" LLM_VISION_MODEL
  require_value "${ANT_ENV}" LLM_AUDIO_MODEL
  require_url_prefix "${ANT_ENV}" APP_URL "https://"
  require_url_prefix "${ANT_ENV}" COMMON_SERVICE_BASE_URL "https://file.hxcbox.cn/service/api/v1"

  require_value "${COMMON_ENV}" APP_KEY
  require_value "${COMMON_ENV}" DB_PASSWORD
  require_value "${COMMON_ENV}" SERVICE_SECRET
  require_value "${COMMON_ENV}" WECHAT_MINI_PROGRAM_APP_ID
  require_value "${COMMON_ENV}" WECHAT_MINI_PROGRAM_APP_SECRET
  require_value "${COMMON_ENV}" R2_ACCESS_KEY_ID
  require_value "${COMMON_ENV}" R2_SECRET_ACCESS_KEY
  require_value "${COMMON_ENV}" R2_BUCKET
  require_value "${COMMON_ENV}" R2_ENDPOINT
  require_value "${COMMON_ENV}" WECHAT_PAY_APP_ID
  require_value "${COMMON_ENV}" WECHAT_PAY_MCH_ID
  require_value "${COMMON_ENV}" WECHAT_PAY_API_V3_KEY
  require_value "${COMMON_ENV}" WECHAT_PAY_MERCHANT_SERIAL_NO
  require_value "${COMMON_ENV}" WECHAT_PAY_NOTIFY_URL
  require_url_prefix "${COMMON_ENV}" APP_URL "https://"
  require_url_prefix "${COMMON_ENV}" WECHAT_PAY_NOTIFY_URL "https://file.hxcbox.cn/service/api/v1/payment/wechat/notify"
  require_false "${COMMON_ENV}" WECHAT_LOGIN_MOCK
  require_false "${COMMON_ENV}" WECHAT_PAY_MOCK

  verification_webhook="$(load_env_value "${COMMON_ENV}" VERIFICATION_CODE_WEBHOOK_URL)"
  verification_mail_enabled="$(load_env_value "${COMMON_ENV}" VERIFICATION_CODE_MAIL_ENABLED)"
  if [ -z "${verification_webhook}" ]; then
    if [ "${verification_mail_enabled}" != "true" ]; then
      fail "${COMMON_ENV}: configure VERIFICATION_CODE_WEBHOOK_URL or set VERIFICATION_CODE_MAIL_ENABLED=true"
    fi
    require_value "${COMMON_ENV}" VERIFICATION_CODE_MAIL_HOST
    require_value "${COMMON_ENV}" VERIFICATION_CODE_MAIL_USERNAME
    require_value "${COMMON_ENV}" VERIFICATION_CODE_MAIL_PASSWORD
  fi

  ant_secret="$(load_env_value "${ANT_ENV}" COMMON_SERVICE_SECRET)"
  common_secret="$(load_env_value "${COMMON_ENV}" SERVICE_SECRET)"
  if [ -n "${ant_secret}" ] && [ -n "${common_secret}" ] && [ "${ant_secret}" != "${common_secret}" ]; then
    fail "COMMON_SERVICE_SECRET and SERVICE_SECRET must match"
  fi

  ant_app_id="$(load_env_value "${ANT_ENV}" COMMON_SERVICE_APP_ID)"
  common_app_id="$(load_env_value "${COMMON_ENV}" SERVICE_APP_ID)"
  if [ -n "${ant_app_id}" ] && [ -n "${common_app_id}" ] && [ "${ant_app_id}" != "${common_app_id}" ]; then
    fail "COMMON_SERVICE_APP_ID and SERVICE_APP_ID must match"
  fi

  ant_queue="$(load_env_value "${ANT_ENV}" QUEUE_CONNECTION)"
  common_queue="$(load_env_value "${COMMON_ENV}" QUEUE_CONNECTION)"
  if [ "${ant_queue}" != "redis" ]; then
    fail "${ANT_ENV}: QUEUE_CONNECTION must be redis for async analysis worker"
  fi
  if [ "${common_queue}" != "redis" ]; then
    fail "${COMMON_ENV}: QUEUE_CONNECTION must be redis"
  fi

  private_key="$(load_env_value "${COMMON_ENV}" WECHAT_PAY_MERCHANT_PRIVATE_KEY)"
  private_key_path="$(load_env_value "${COMMON_ENV}" WECHAT_PAY_MERCHANT_PRIVATE_KEY_PATH)"
  if [ -z "${private_key}" ] && [ -z "${private_key_path}" ]; then
    fail "${COMMON_ENV}: WECHAT_PAY_MERCHANT_PRIVATE_KEY or WECHAT_PAY_MERCHANT_PRIVATE_KEY_PATH is required"
  fi

  platform_cert="$(load_env_value "${COMMON_ENV}" WECHAT_PAY_PLATFORM_CERTIFICATE)"
  platform_cert_path="$(load_env_value "${COMMON_ENV}" WECHAT_PAY_PLATFORM_CERTIFICATE_PATH)"
  if [ -z "${platform_cert}" ] && [ -z "${platform_cert_path}" ]; then
    fail "${COMMON_ENV}: WECHAT_PAY_PLATFORM_CERTIFICATE or WECHAT_PAY_PLATFORM_CERTIFICATE_PATH is required for notify verification"
  fi

  allowed_origins="$(load_env_value "${ANT_ENV}" CORS_ALLOWED_ORIGINS)"
  if [[ "${allowed_origins}" != https://* ]]; then
    warn "${ANT_ENV}: CORS_ALLOWED_ORIGINS should contain the real HTTPS H5/admin origins"
  fi
fi

if [ "${#warnings[@]}" -gt 0 ]; then
  for warning in "${warnings[@]}"; do
    echo "WARN: ${warning}" >&2
  done
fi

if [ "${#errors[@]}" -gt 0 ]; then
  for error in "${errors[@]}"; do
    echo "FAIL: ${error}" >&2
  done
  exit 1
fi

echo "PROD ENV CHECK PASS"

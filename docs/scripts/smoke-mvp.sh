#!/usr/bin/env bash
set -euo pipefail

ANT_BASE_URL="${ANT_BASE_URL:-https://ant.hxcbox.cn}"
FILE_BASE_URL="${FILE_BASE_URL:-https://file.hxcbox.cn}"
ACCOUNT="${SMOKE_ACCOUNT:-smoke-${RANDOM}@hxcbox.cn}"
CODE="${SMOKE_CODE:-}"
PROJECT_CODE="${PROJECT_CODE:-antifraud}"
SMOKE_WECHAT_TOKEN="${SMOKE_WECHAT_TOKEN:-}"
SMOKE_PACKAGE_ID="${SMOKE_PACKAGE_ID:-}"

fail() {
  echo "FAIL: $*" >&2
  exit 1
}

request() {
  local method="$1"
  local url="$2"
  local data="${3:-}"
  local token="${4:-}"
  local tmp
  tmp="$(mktemp)"
  local headers=(-H "Accept: application/json")

  if [ -n "${token}" ]; then
    headers+=(-H "Authorization: Bearer ${token}")
  fi

  if [ -n "${data}" ]; then
    headers+=(-H "Content-Type: application/json")
    http_code="$(curl -sS -X "${method}" "${url}" "${headers[@]}" -d "${data}" -o "${tmp}" -w "%{http_code}")"
  else
    http_code="$(curl -sS -X "${method}" "${url}" "${headers[@]}" -o "${tmp}" -w "%{http_code}")"
  fi

  body="$(cat "${tmp}")"
  rm -f "${tmp}"

  if [ "${http_code}" -lt 200 ] || [ "${http_code}" -ge 300 ]; then
    echo "${body}" >&2
    fail "${method} ${url} returned HTTP ${http_code}"
  fi

  printf '%s' "${body}"
}

json_get() {
  php -r '$data=json_decode(stream_get_contents(STDIN), true); $path=explode(".", $argv[1]); foreach ($path as $key) { $data=$data[$key] ?? null; } if (is_array($data)) { echo json_encode($data, JSON_UNESCAPED_UNICODE); } else { echo (string) $data; }' "$1"
}

echo "1. health: ${ANT_BASE_URL}/api/v1/system/health"
health_body="$(request GET "${ANT_BASE_URL}/api/v1/system/health")"
[ "$(printf '%s' "${health_body}" | json_get code)" = "0" ] || fail "ant health code is not 0"

echo "2. file disks: ${FILE_BASE_URL}/service/api/v1/file/disks"
disks_body="$(request GET "${FILE_BASE_URL}/service/api/v1/file/disks")"
[ "$(printf '%s' "${disks_body}" | json_get code)" = "0" ] || fail "file disks code is not 0"

echo "3. send code: ${ACCOUNT}"
send_body="$(request POST "${ANT_BASE_URL}/api/v1/auth/send-code" "{\"account\":\"${ACCOUNT}\",\"scene\":\"login\"}")"
debug_code="$(printf '%s' "${send_body}" | json_get data.debug_code)"
if [ -z "${CODE}" ]; then
  CODE="${debug_code}"
fi
[ -n "${CODE}" ] || fail "verification code missing; set SMOKE_CODE for production"

echo "4. code login"
login_body="$(request POST "${ANT_BASE_URL}/api/v1/auth/code-login" "{\"account\":\"${ACCOUNT}\",\"code\":\"${CODE}\",\"scene\":\"login\"}")"
token="$(printf '%s' "${login_body}" | json_get data.token)"
[ -n "${token}" ] || fail "login token missing"

echo "5. me"
me_body="$(request GET "${ANT_BASE_URL}/api/v1/me" "" "${token}")"
[ "$(printf '%s' "${me_body}" | json_get code)" = "0" ] || fail "me code is not 0"

echo "6. packages"
packages_body="$(request GET "${ANT_BASE_URL}/api/v1/payments/packages?project_code=${PROJECT_CODE}")"
[ "$(printf '%s' "${packages_body}" | json_get code)" = "0" ] || fail "packages code is not 0"

echo "7. wallet balance"
balance_body="$(request GET "${FILE_BASE_URL}/service/api/v1/wallet/balance?project_code=${PROJECT_CODE}" "" "${token}")"
[ "$(printf '%s' "${balance_body}" | json_get code)" = "0" ] || fail "wallet balance code is not 0"

if [ -n "${SMOKE_WECHAT_TOKEN}" ] && [ -n "${SMOKE_PACKAGE_ID}" ]; then
  echo "8. wechat jsapi order"
  order_body="$(request POST "${ANT_BASE_URL}/api/v1/payments/wechat/order" "{\"project_code\":\"${PROJECT_CODE}\",\"package_id\":${SMOKE_PACKAGE_ID}}" "${SMOKE_WECHAT_TOKEN}")"
  [ "$(printf '%s' "${order_body}" | json_get code)" = "0" ] || fail "wechat order code is not 0"
  order_no="$(printf '%s' "${order_body}" | json_get data.order_no)"
  pay_package="$(printf '%s' "${order_body}" | json_get data.payment_params.package)"
  pay_sign="$(printf '%s' "${order_body}" | json_get data.payment_params.paySign)"
  [ -n "${order_no}" ] || fail "wechat order_no missing"
  [ -n "${pay_package}" ] || fail "wechat payment package missing"
  [ -n "${pay_sign}" ] || fail "wechat paySign missing"
else
  echo "8. wechat jsapi order skipped; set SMOKE_WECHAT_TOKEN and SMOKE_PACKAGE_ID after wx.login to verify prepay"
fi

echo "SMOKE PASS"

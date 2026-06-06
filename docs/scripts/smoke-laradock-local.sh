#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://nginx}"
ANT_HOST="${ANT_HOST:-service.antifraud.local.hxc}"
FILE_HOST="${FILE_HOST:-service.storage.company.hxc}"
ACCOUNT="${SMOKE_ACCOUNT:-local-smoke-${RANDOM}-$(date +%s)@hxcbox.cn}"
PROJECT_CODE="${PROJECT_CODE:-antifraud}"
POLL_SECONDS="${SMOKE_POLL_SECONDS:-90}"

fail() {
  echo "FAIL: $*" >&2
  exit 1
}

request() {
  local method="$1"
  local host="$2"
  local url="$3"
  local data="${4:-}"
  local token="${5:-}"
  local tmp http_code body
  tmp="$(mktemp)"
  local headers=(-H "Host: ${host}" -H "Accept: application/json")

  if [ -n "${token}" ]; then
    headers+=(-H "Authorization: Bearer ${token}")
  fi

  if [ -n "${data}" ]; then
    headers+=(-H "Content-Type: application/json")
    http_code="$(curl -sS -X "${method}" "${BASE_URL}${url}" "${headers[@]}" -d "${data}" -o "${tmp}" -w "%{http_code}")"
  else
    http_code="$(curl -sS -X "${method}" "${BASE_URL}${url}" "${headers[@]}" -o "${tmp}" -w "%{http_code}")"
  fi

  body="$(cat "${tmp}")"
  rm -f "${tmp}"

  if [ "${http_code}" -lt 200 ] || [ "${http_code}" -ge 300 ]; then
    echo "${body}" >&2
    fail "${method} ${url} (${host}) returned HTTP ${http_code}"
  fi

  printf '%s' "${body}"
}

json_get() {
  php -r '$data=json_decode(stream_get_contents(STDIN), true); $path=explode(".", $argv[1]); foreach ($path as $key) { $data=$data[$key] ?? null; } if (is_array($data)) { echo json_encode($data, JSON_UNESCAPED_UNICODE); } else { echo (string) $data; }' "$1"
}

json_string() {
  php -r 'echo json_encode($argv[1], JSON_UNESCAPED_UNICODE);' "$1"
}

assert_code_zero() {
  local body="$1"
  local label="$2"
  [ "$(printf '%s' "${body}" | json_get code)" = "0" ] || fail "${label} code is not 0: ${body}"
}

echo "1. ant health"
health_body="$(request GET "${ANT_HOST}" "/api/v1/system/health")"
assert_code_zero "${health_body}" "ant health"

echo "2. file disks"
disks_body="$(request GET "${FILE_HOST}" "/service/api/v1/file/disks")"
assert_code_zero "${disks_body}" "file disks"

echo "3. code login account=${ACCOUNT}"
send_body="$(request POST "${ANT_HOST}" "/api/v1/auth/send-code" "{\"account\":$(json_string "${ACCOUNT}"),\"scene\":\"login\"}")"
assert_code_zero "${send_body}" "send code"
code="$(printf '%s' "${send_body}" | json_get data.debug_code)"
[ -n "${code}" ] || fail "local mock verification code missing"

login_body="$(request POST "${ANT_HOST}" "/api/v1/auth/code-login" "{\"account\":$(json_string "${ACCOUNT}"),\"code\":$(json_string "${code}"),\"scene\":\"login\"}")"
assert_code_zero "${login_body}" "code login"
token="$(printf '%s' "${login_body}" | json_get data.token)"
[ -n "${token}" ] || fail "login token missing"

echo "4. me and initial wallet"
me_body="$(request GET "${ANT_HOST}" "/api/v1/me" "" "${token}")"
assert_code_zero "${me_body}" "me"
balance_body="$(request GET "${FILE_HOST}" "/service/api/v1/wallet/balance?project_code=${PROJECT_CODE}" "" "${token}")"
assert_code_zero "${balance_body}" "wallet balance"

echo "5. create mock WeChat payment order and notify"
packages_body="$(request GET "${ANT_HOST}" "/api/v1/payments/packages?project_code=${PROJECT_CODE}")"
assert_code_zero "${packages_body}" "payment packages"
package_id="$(printf '%s' "${packages_body}" | json_get data.0.id)"
amount_cent="$(printf '%s' "${packages_body}" | json_get data.0.amount_cent)"
[ -n "${package_id}" ] || fail "package id missing"
[ -n "${amount_cent}" ] || fail "package amount missing"

order_body="$(request POST "${ANT_HOST}" "/api/v1/payments/wechat/order" "{\"project_code\":$(json_string "${PROJECT_CODE}"),\"package_id\":${package_id},\"openid\":\"local-openid\"}" "${token}")"
assert_code_zero "${order_body}" "wechat order"
order_no="$(printf '%s' "${order_body}" | json_get data.order_no)"
[ -n "${order_no}" ] || fail "order_no missing"

notify_body="$(request POST "${FILE_HOST}" "/service/api/v1/payment/wechat/notify" "{\"out_trade_no\":$(json_string "${order_no}"),\"trade_state\":\"SUCCESS\",\"transaction_id\":$(json_string "local-${order_no}"),\"amount\":{\"total\":${amount_cent},\"payer_total\":${amount_cent}}}")"
[ "$(printf '%s' "${notify_body}" | json_get code)" = "SUCCESS" ] || fail "wechat notify failed: ${notify_body}"

recharged_body="$(request GET "${FILE_HOST}" "/service/api/v1/wallet/balance?project_code=${PROJECT_CODE}" "" "${token}")"
assert_code_zero "${recharged_body}" "recharged wallet"
balance="$(printf '%s' "${recharged_body}" | json_get data.balance)"
[ "${balance:-0}" -gt 0 ] || fail "wallet did not recharge"

echo "6. upload smoke file to configured object storage"
smoke_file="$(mktemp /tmp/antifraud-local-smoke.XXXXXX.txt)"
printf '本地 smoke: 保证收益，稳赚不赔，要求转账到个人账户。' > "${smoke_file}"
tmp="$(mktemp)"
http_code="$(
  curl -sS -X POST "${BASE_URL}/service/api/v1/file/upload" \
    -H "Host: ${FILE_HOST}" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${token}" \
    -F "owner_project=${PROJECT_CODE}" \
    -F "biz_type=local_smoke_analysis" \
    -F "file=@${smoke_file};type=text/plain" \
    -o "${tmp}" \
    -w "%{http_code}"
)"
rm -f "${smoke_file}"
upload_body="$(cat "${tmp}")"
rm -f "${tmp}"
[ "${http_code}" -ge 200 ] && [ "${http_code}" -lt 300 ] || fail "file upload returned HTTP ${http_code}: ${upload_body}"
assert_code_zero "${upload_body}" "file upload"

storage_file_id="$(printf '%s' "${upload_body}" | json_get data.file_id)"
object_key="$(printf '%s' "${upload_body}" | json_get data.object_key)"
file_url="$(printf '%s' "${upload_body}" | json_get data.file_url)"
mime_type="$(printf '%s' "${upload_body}" | json_get data.mime_type)"
file_size="$(printf '%s' "${upload_body}" | json_get data.size)"
[ -n "${storage_file_id}" ] || fail "storage file_id missing"
[ -n "${object_key}" ] || fail "object_key missing"

echo "7. register file and create analysis"
register_body="$(
  request POST "${ANT_HOST}" "/api/v1/files/register" \
    "{\"storage_file_id\":$(json_string "${storage_file_id}"),\"file_type\":\"image\",\"object_key\":$(json_string "${object_key}"),\"file_url\":$(json_string "${file_url}"),\"mime_type\":$(json_string "${mime_type}"),\"file_size\":${file_size:-0}}" \
    "${token}"
)"
assert_code_zero "${register_body}" "file register"
file_id="$(printf '%s' "${register_body}" | json_get data.file_id)"
[ -n "${file_id}" ] || fail "antifraud file_id missing"

analysis_body="$(
  request POST "${ANT_HOST}" "/api/v1/analysis/image" \
    "{\"file_ids\":[${file_id}],\"text\":\"保证收益，稳赚不赔，要求转账到个人账户。\"}" \
    "${token}"
)"
assert_code_zero "${analysis_body}" "analysis create"
record_id="$(printf '%s' "${analysis_body}" | json_get data.record_id)"
[ -n "${record_id}" ] || fail "record_id missing"

echo "8. poll report record_id=${record_id}"
deadline=$((SECONDS + POLL_SECONDS))
status=""
while [ "${SECONDS}" -lt "${deadline}" ]; do
  report_body="$(request GET "${ANT_HOST}" "/api/v1/analysis/${record_id}" "" "${token}")"
  assert_code_zero "${report_body}" "analysis detail"
  status="$(printf '%s' "${report_body}" | json_get data.status)"
  echo "   status=${status}"
  case "${status}" in
    success)
      risk_level="$(printf '%s' "${report_body}" | json_get data.risk_level)"
      summary="$(printf '%s' "${report_body}" | json_get data.summary)"
      [ -n "${risk_level}" ] || fail "risk_level missing"
      [ -n "${summary}" ] || fail "summary missing"
      echo "LOCAL E2E PASS: account=${ACCOUNT} balance=${balance} record_id=${record_id}"
      exit 0
      ;;
    failed|canceled)
      error_message="$(printf '%s' "${report_body}" | json_get data.error_message)"
      fail "analysis ended with ${status}: ${error_message}"
      ;;
  esac
  sleep 3
done

fail "analysis did not finish in ${POLL_SECONDS}s, last status=${status}"

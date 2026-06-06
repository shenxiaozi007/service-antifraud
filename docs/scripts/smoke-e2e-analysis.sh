#!/usr/bin/env bash
set -euo pipefail

ANT_BASE_URL="${ANT_BASE_URL:-https://ant.hxcbox.cn}"
FILE_BASE_URL="${FILE_BASE_URL:-https://file.hxcbox.cn}"
ACCOUNT="${SMOKE_ACCOUNT:-smoke-${RANDOM}@hxcbox.cn}"
CODE="${SMOKE_CODE:-}"
PROJECT_CODE="${PROJECT_CODE:-antifraud}"
ANALYSIS_COST="${SMOKE_ANALYSIS_COST:-20}"
POLL_SECONDS="${SMOKE_POLL_SECONDS:-90}"
REQUIRE_ANALYSIS="${SMOKE_REQUIRE_ANALYSIS:-false}"

fail() {
  echo "FAIL: $*" >&2
  exit 1
}

warn() {
  echo "WARN: $*" >&2
}

request() {
  local method="$1"
  local url="$2"
  local data="${3:-}"
  local token="${4:-}"
  local tmp http_code body
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

json_string() {
  php -r 'echo json_encode($argv[1], JSON_UNESCAPED_UNICODE);' "$1"
}

upload_file() {
  local path="$1"
  local token="$2"
  local tmp http_code body
  tmp="$(mktemp)"
  http_code="$(
    curl -sS -X POST "${FILE_BASE_URL}/service/api/v1/file/upload" \
      -H "Accept: application/json" \
      -H "Authorization: Bearer ${token}" \
      -F "owner_project=${PROJECT_CODE}" \
      -F "biz_type=smoke_analysis_image" \
      -F "file=@${path};type=text/plain" \
      -o "${tmp}" \
      -w "%{http_code}"
  )"
  body="$(cat "${tmp}")"
  rm -f "${tmp}"

  if [ "${http_code}" -lt 200 ] || [ "${http_code}" -ge 300 ]; then
    echo "${body}" >&2
    fail "file upload returned HTTP ${http_code}"
  fi

  printf '%s' "${body}"
}

echo "1. health: ${ANT_BASE_URL}/api/v1/system/health"
health_body="$(request GET "${ANT_BASE_URL}/api/v1/system/health")"
[ "$(printf '%s' "${health_body}" | json_get code)" = "0" ] || fail "ant health code is not 0"

echo "2. file disks: ${FILE_BASE_URL}/service/api/v1/file/disks"
disks_body="$(request GET "${FILE_BASE_URL}/service/api/v1/file/disks")"
[ "$(printf '%s' "${disks_body}" | json_get code)" = "0" ] || fail "file disks code is not 0"

echo "3. send code: ${ACCOUNT}"
send_body="$(request POST "${ANT_BASE_URL}/api/v1/auth/send-code" "{\"account\":$(json_string "${ACCOUNT}"),\"scene\":\"login\"}")"
debug_code="$(printf '%s' "${send_body}" | json_get data.debug_code)"
if [ -z "${CODE}" ]; then
  CODE="${debug_code}"
fi
[ -n "${CODE}" ] || fail "verification code missing; set SMOKE_CODE for production"

echo "4. code login"
login_body="$(request POST "${ANT_BASE_URL}/api/v1/auth/code-login" "{\"account\":$(json_string "${ACCOUNT}"),\"code\":$(json_string "${CODE}"),\"scene\":\"login\"}")"
token="$(printf '%s' "${login_body}" | json_get data.token)"
[ -n "${token}" ] || fail "login token missing"

echo "5. me"
me_body="$(request GET "${ANT_BASE_URL}/api/v1/me" "" "${token}")"
[ "$(printf '%s' "${me_body}" | json_get code)" = "0" ] || fail "me code is not 0"

echo "6. wallet balance"
balance_body="$(request GET "${FILE_BASE_URL}/service/api/v1/wallet/balance?project_code=${PROJECT_CODE}" "" "${token}")"
[ "$(printf '%s' "${balance_body}" | json_get code)" = "0" ] || fail "wallet balance code is not 0"
balance="$(printf '%s' "${balance_body}" | json_get data.balance)"
balance="${balance:-0}"

echo "7. upload smoke file"
smoke_file="$(mktemp /tmp/antifraud-smoke.XXXXXX.txt)"
printf 'smoke image text: 保证收益，稳赚不赔，要求转账到个人账户。' > "${smoke_file}"
upload_body="$(upload_file "${smoke_file}" "${token}")"
rm -f "${smoke_file}"
[ "$(printf '%s' "${upload_body}" | json_get code)" = "0" ] || fail "upload code is not 0"
storage_file_id="$(printf '%s' "${upload_body}" | json_get data.file_id)"
object_key="$(printf '%s' "${upload_body}" | json_get data.object_key)"
file_url="$(printf '%s' "${upload_body}" | json_get data.file_url)"
mime_type="$(printf '%s' "${upload_body}" | json_get data.mime_type)"
file_size="$(printf '%s' "${upload_body}" | json_get data.size)"
[ -n "${storage_file_id}" ] || fail "storage file_id missing"
[ -n "${object_key}" ] || fail "object_key missing"

echo "8. register antifraud file asset"
register_body="$(
  request POST "${ANT_BASE_URL}/api/v1/files/register" \
    "{\"storage_file_id\":$(json_string "${storage_file_id}"),\"file_type\":\"image\",\"object_key\":$(json_string "${object_key}"),\"file_url\":$(json_string "${file_url}"),\"mime_type\":$(json_string "${mime_type}"),\"file_size\":${file_size:-0}}" \
    "${token}"
)"
[ "$(printf '%s' "${register_body}" | json_get code)" = "0" ] || fail "register code is not 0"
file_id="$(printf '%s' "${register_body}" | json_get data.file_id)"
[ -n "${file_id}" ] || fail "antifraud file_id missing"

if [ "${balance}" -lt "${ANALYSIS_COST}" ]; then
  message="wallet balance ${balance} is less than ${ANALYSIS_COST}; complete WeChat Pay recharge, then rerun this script with the same account to verify analysis"
  if [ "${REQUIRE_ANALYSIS}" = "true" ]; then
    fail "${message}"
  fi
  warn "${message}"
  echo "E2E PARTIAL PASS: login, wallet, upload and file register passed"
  exit 0
fi

echo "9. create image analysis"
analysis_body="$(
  request POST "${ANT_BASE_URL}/api/v1/analysis/image" \
    "{\"file_ids\":[${file_id}],\"text\":\"保证收益，稳赚不赔，要求转账到个人账户。\"}" \
    "${token}"
)"
[ "$(printf '%s' "${analysis_body}" | json_get code)" = "0" ] || fail "analysis create code is not 0"
record_id="$(printf '%s' "${analysis_body}" | json_get data.record_id)"
[ -n "${record_id}" ] || fail "record_id missing"

echo "10. poll analysis report: record_id=${record_id}"
deadline=$((SECONDS + POLL_SECONDS))
status=""
while [ "${SECONDS}" -lt "${deadline}" ]; do
  report_body="$(request GET "${ANT_BASE_URL}/api/v1/analysis/${record_id}" "" "${token}")"
  status="$(printf '%s' "${report_body}" | json_get data.status)"
  echo "   status=${status}"
  case "${status}" in
    success)
      risk_level="$(printf '%s' "${report_body}" | json_get data.risk_level)"
      summary="$(printf '%s' "${report_body}" | json_get data.summary)"
      [ -n "${risk_level}" ] || fail "risk_level missing"
      [ -n "${summary}" ] || fail "summary missing"
      echo "E2E PASS"
      exit 0
      ;;
    failed|canceled)
      error_message="$(printf '%s' "${report_body}" | json_get data.error_message)"
      fail "analysis ended with ${status}: ${error_message}"
      ;;
  esac
  sleep 3
done

fail "analysis did not finish in ${POLL_SECONDS}s, last status=${status}; check queue worker"

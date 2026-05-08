<script setup lang="ts">
import { onLaunch } from '@dcloudio/uni-app';
import { ensureLogin } from '@/stores/session';

onLaunch(() => {
  void ensureLogin();
});
</script>

<style>
page {
  min-height: 100%;
  background: #f5f5f7;
  color: #1d1d1f;
  font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Helvetica Neue", sans-serif;
  font-size: 30rpx;
}

view,
text,
button,
input,
textarea {
  box-sizing: border-box;
}
</style>

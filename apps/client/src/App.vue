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
  background: #f3f6f2;
  color: #24312a;
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

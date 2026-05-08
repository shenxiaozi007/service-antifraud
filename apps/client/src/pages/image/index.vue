<script setup lang="ts">
import { computed, ref } from 'vue';
import { onLoad } from '@dcloudio/uni-app';
import { createImageAnalysis, uploadToken } from '@/api/client';
import { ensureLogin } from '@/stores/session';
import '@/styles/common.scss';

interface LocalImage {
  path: string;
  size: number;
}

const images = ref<LocalImage[]>([]);
const text = ref('');
const loading = ref(false);
const canSubmit = computed(() => images.value.length > 0 && !loading.value);

onLoad(() => {
  void ensureLogin();
});

function chooseImage() {
  uni.chooseImage({
    count: 3 - images.value.length,
    sourceType: ['album', 'camera'],
    success(result) {
      const tempFiles = Array.isArray(result.tempFiles) ? result.tempFiles : result.tempFiles ? [result.tempFiles] : [];
      const files = tempFiles.map((file) => ({
        path: 'path' in file ? file.path : URL.createObjectURL(file),
        size: file.size || 1
      }));
      images.value = images.value.concat(files).slice(0, 3);
    }
  });
}

function removeImage(index: number) {
  images.value = images.value.filter((_, current) => current !== index);
}

async function submit() {
  if (!canSubmit.value) return;

  loading.value = true;
  uni.showLoading({ title: '分析中' });

  try {
    const fileIds: number[] = [];
    for (const image of images.value) {
      const file = await uploadToken({
        file_type: 'image',
        mime_type: 'image/jpeg',
        file_size: image.size || 1
      });
      fileIds.push(file.file_id);
    }

    const result = await createImageAnalysis({
      file_ids: fileIds,
      text: text.value || '保证收益，稳赚不赔，名额有限'
    });

    uni.navigateTo({ url: `/pages/report/index?record_id=${result.record_id}` });
  } finally {
    loading.value = false;
    uni.hideLoading();
  }
}
</script>

<template>
  <view class="page">
    <view class="section">
      <view class="title">上传宣传材料</view>
      <view class="subtitle">拍摄或上传聊天截图、海报、合同、付款页面。MVP 联调可在下方补充识别文字。</view>
    </view>

    <view class="card upload" @tap="chooseImage">
      <view class="plus">+</view>
      <view class="upload-text">拍照 / 选择图片</view>
      <view class="muted">已选图片：{{ images.length }}/3</view>
    </view>

    <view v-if="images.length" class="thumbs">
      <view v-for="(image, index) in images" :key="image.path" class="thumb">
        <image :src="image.path" mode="aspectFill" />
        <view class="remove" @tap.stop="removeImage(index)">删除</view>
      </view>
    </view>

    <view class="card section">
      <view class="row">
        <view class="feature-title">风险文本</view>
        <view class="tag">联调用</view>
      </view>
      <textarea v-model="text" class="textarea" placeholder="可粘贴截图里的宣传话术，如：保证收益、稳赚不赔、名额有限" />
    </view>

    <view class="cost">本次将消耗 20 点</view>
    <view class="button" :class="{ disabled: !canSubmit }" @tap="submit">开始分析</view>
  </view>
</template>

<style scoped>
.upload {
  text-align: center;
  margin-bottom: 24rpx;
}

.plus {
  width: 96rpx;
  height: 96rpx;
  margin: 0 auto 16rpx;
  border-radius: 48rpx;
  background: #eef4ef;
  color: #1f8a5b;
  font-size: 72rpx;
  line-height: 88rpx;
}

.upload-text,
.feature-title {
  font-size: 34rpx;
  font-weight: 700;
}

.thumbs {
  display: flex;
  gap: 18rpx;
  margin-bottom: 24rpx;
}

.thumb {
  position: relative;
  width: 196rpx;
  height: 196rpx;
  border-radius: 16rpx;
  overflow: hidden;
}

.thumb image {
  width: 100%;
  height: 100%;
}

.remove {
  position: absolute;
  right: 8rpx;
  bottom: 8rpx;
  padding: 8rpx 14rpx;
  border-radius: 20rpx;
  background: rgba(0, 0, 0, 0.55);
  color: #fff;
  font-size: 22rpx;
}

.cost {
  color: #7b837e;
  margin: 28rpx 0 18rpx;
  text-align: center;
}
</style>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { toast } from 'vue3-toastify';

const route = useRoute();
const router = useRouter();

const bookingId = route.params.bookingId;
const qrCodeUrl = ref('');
const paymentProof = ref(null);
const previewUrl = ref('');
const uploading = ref(false);
const dragOver = ref(false);

async function fetchQRCode() {
  try {
    const res = await fetch(`/api/booking/${bookingId}/vietqr`);
    if (!res.ok) throw new Error('Không lấy được mã QR');
    const data = await res.json();
    qrCodeUrl.value = data.qrCodeUrl;
  } catch (error) {
    toast.error(error.message || 'Lỗi khi lấy mã QR');
  }
}

function downloadQRCode() {
  if (!qrCodeUrl.value) return;
  const link = document.createElement('a');
  link.href = qrCodeUrl.value;
  link.download = 'vietqr.png';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

function onFileChange(e) {
  const file = e.target.files[0];
  if (file && file.type.startsWith('image/')) {
    paymentProof.value = file;
    previewUrl.value = URL.createObjectURL(file);
  } else {
    toast.error('Vui lòng chọn file ảnh hợp lệ');
  }
}

function onDrop(e) {
  e.preventDefault();
  dragOver.value = false;
  const file = e.dataTransfer.files[0];
  if (file && file.type.startsWith('image/')) {
    paymentProof.value = file;
    previewUrl.value = URL.createObjectURL(file);
  } else {
    toast.error('Chỉ chấp nhận file ảnh');
  }
}

function onDragOver(e) {
  e.preventDefault();
  dragOver.value = true;
}

function onDragLeave(e) {
  e.preventDefault();
  dragOver.value = false;
}

async function submitProof() {
  if (!paymentProof.value) {
    toast.error('Vui lòng chọn ảnh bằng chứng');
    return;
  }
  uploading.value = true;

  try {
    const formData = new FormData();
    formData.append('booking_id', bookingId);
    formData.append('proof', paymentProof.value);

    const res = await fetch('/api/booking/upload-proof', {
      method: 'POST',
      body: formData,
    });

    if (!res.ok) {
      const errData = await res.json();
      throw new Error(errData.message || 'Lỗi khi upload bằng chứng');
    }
    router.push('/status');
  } catch (error) {
    toast.error(error.message || 'Lỗi không xác định');
  } finally {
    uploading.value = false;
  }
}

onMounted(fetchQRCode);
</script>

<template>
  <div class="container">
    <h3 class="title">Mã QR thanh toán</h3>

    <img
      :src="qrCodeUrl"
      alt="QR Code"
      class="qr-code"
      @click="downloadQRCode"
      title="Click để tải mã QR"
      v-if="qrCodeUrl"
    />
    <p v-else>Đang tải mã QR...</p>
    <p class="desc">Quét mã hoặc click để tải về QR thanh toán</p>

    <div
      class="upload-area"
      :class="{ 'drag-over': dragOver }"
      @drop="onDrop"
      @dragover="onDragOver"
      @dragleave="onDragLeave"
      @click="$refs.fileInput.click()"
    >
      <input
        type="file"
        accept="image/*"
        ref="fileInput"
        class="file-input"
        @change="onFileChange"
        hidden
      />
      <template v-if="previewUrl">
        <p>Ảnh giao dịch đã chọn:</p>
        <img :src="previewUrl" alt="Preview" class="preview-img" />
      </template>
      <template v-else>
        <p>Tải lên ảnh giao dịch</p>
      </template>
    </div>

    <button
      class="btn-submit"
      :disabled="uploading"
      @click="submitProof"
    >
      {{ uploading ? 'Đang upload...' : 'Gửi ảnh giao dịch thanh toán' }}
    </button>
  </div>
</template>

<style scoped>
.container {
  max-width: 400px;
  margin: 0 auto;
  background: white;
  border-radius: 8px;
  text-align: center;
}

.title {
  font-size: 1.5rem;
  margin-bottom: 1rem;
  user-select: none;
}

.qr-code {
  width: 100%;
  max-width: 280px;
  height: auto;
  margin: 0 auto 1rem;
  border-radius: 12px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.15);
  cursor: pointer;
  transition: transform 0.15s ease;
}

.qr-code:hover {
  transform: scale(1.05);
}

.desc {
  margin-bottom: 1.5rem;
  font-size: 1rem;
  color: #555;
  user-select: none;
}

.upload-area {
  border: 2px dashed #007bff;
  border-radius: 12px;
  padding: 1.5rem;
  cursor: pointer;
  user-select: none;
  transition: background-color 0.2s ease, border-color 0.2s ease;
}

.upload-area.drag-over {
  background-color: #e6f0ff;
  border-color: #0056b3;
}

.upload-area p {
  margin: 0;
  color: #555;
  font-weight: 500;
}

.file-input {
  display: none;
}

.preview-img {
  max-width: 100%;
  max-height: 200px;
  margin-top: 0.5rem;
  border-radius: 10px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

.btn-submit {
  margin-top: 1rem;
  width: 100%;
  max-width: 300px;
  padding: 0.75rem 1rem;
  font-size: 1.1rem;
  font-weight: 600;
  color: white;
  background-color: #007bff;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: background-color 0.25s ease;
}

.btn-submit:disabled {
  background-color: #a3cdfd;
  cursor: not-allowed;
}

.btn-submit:hover:not(:disabled) {
  background-color: #0056b3;
}

/* Responsive cho mobile */
@media (max-width: 480px) {
  .container {
    margin: 0 auto;
    padding: 0.75rem;
  }

  .title {
    font-size: 1.3rem;
  }

  .btn-submit {
    font-size: 1rem;
    padding: 0.65rem;
  }
}
</style>

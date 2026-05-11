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
    toast.success('Gửi bằng chứng thành công!');
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
  <div class="vietqr-page">
    <!-- Header -->
    <header class="booking-header sticky-top py-3 mb-4">
      <div class="container d-flex align-items-center px-4">
        <button class="header-back-btn me-4" @click="router.back()">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-chevron-left" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
          </svg>
        </button>
        <div class="header-brand">
          <img src="../../images/logo.png" alt="logo" class="header-logo" />
        </div>
      </div>
    </header>

    <div class="container pb-5">
      <div class="card section-card mb-4">
        <div class="card-body p-4 text-center">
          <h3 class="section-title mb-4">THANH TOÁN VIETQR</h3>
          
          <div class="qr-container mb-4">
            <div v-if="qrCodeUrl" class="qr-wrapper" @click="downloadQRCode">
              <img :src="qrCodeUrl" alt="VietQR Code" class="qr-code-img" />
              <div class="qr-overlay">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-download" viewBox="0 0 16 16">
                  <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                  <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                </svg>
                <span>Nhấn để tải mã</span>
              </div>
            </div>
            <div v-else class="qr-placeholder">
              <div class="spinner-border text-success" role="status">
                <span class="visually-hidden">Đang tải...</span>
              </div>
              <p class="mt-2 text-muted">Đang tạo mã QR...</p>
            </div>
          </div>

          <p class="desc text-muted mb-4 px-3">
            Vui lòng quét mã QR trên để thanh toán. Sau khi hoàn tất, hãy chụp lại màn hình giao dịch và tải lên bên dưới.
          </p>

          <div 
            class="upload-section"
            :class="{ 'drag-over': dragOver, 'has-preview': previewUrl }"
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
            
            <div v-if="previewUrl" class="preview-container">
              <img :src="previewUrl" alt="Payment Proof" class="preview-img" />
              <div class="change-hint">Nhấn để thay đổi ảnh</div>
            </div>
            
            <div v-else class="upload-hint">
              <div class="upload-icon mb-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-cloud-arrow-up" viewBox="0 0 16 16">
                  <path fill-rule="evenodd" d="M7.646 5.146a.5.5 0 0 1 .708 0l2 2a.5.5 0 0 1-.708.708L8.5 6.707V10.5a.5.5 0 0 1-1 0V6.707L6.354 7.854a.5.5 0 1 1-.708-.708l2-2z"/>
                  <path d="M4.406 3.342A5.53 5.53 0 0 1 8 2c2.69 0 4.923 2 5.166 4.579C14.758 6.804 16 8.137 16 9.773 16 11.569 14.502 13 12.687 13H3.781C1.708 13 0 11.366 0 9.318c0-1.763 1.266-3.223 2.942-3.593.143-.863.698-1.723 1.464-2.383zm.653.757c-.757.653-1.153 1.44-1.153 2.056v.448l-.445.049C2.064 6.805 1 7.952 1 9.318 1 10.785 2.23 12 3.781 12h8.906C13.98 12 15 10.988 15 9.773c0-1.216-1.02-2.228-2.313-2.228h-.5v-.5C12.188 4.825 10.328 3 8 3a4.53 4.53 0 0 0-2.941 1.1z"/>
                </svg>
              </div>
              <div class="fw-bold">Tải lên ảnh giao dịch</div>
              <div class="small text-muted">Nhấn hoặc kéo thả ảnh vào đây</div>
            </div>
          </div>

          <div class="action-section mt-5">
            <button 
              class="btn btn-submit-proof w-100" 
              :disabled="uploading || !paymentProof" 
              @click="submitProof"
            >
              <template v-if="uploading">
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                ĐANG GỬI...
              </template>
              <template v-else>
                GỬI BẰNG CHỨNG THANH TOÁN
              </template>
            </button>
          </div>
        </div>
      </div>

      <div class="support-section px-2">
        <button class="btn btn-support w-100" @click="window.location.href = 'tel:0988992268'">
          <span class="me-2">📞</span> Cần hỗ trợ? Gọi Hotline: 0988992268
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

.vietqr-page {
  font-family: 'Inter', sans-serif;
  min-height: 100vh;
  background-color: #f8f9fa;
}

/* Header Styles */
.booking-header {
  z-index: 1010;
  background: rgba(255, 255, 255, 0.5);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.header-back-btn {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  border: 1.5px solid #2D4F1E;
  background: white;
  color: #2D4F1E;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
}

.header-back-btn:hover {
  background: #2D4F1E;
  color: white;
  transform: translateX(-2px);
}

.header-logo {
  height: 38px;
  width: auto;
}

/* Card Styles */
.section-card {
  border: none;
  border-radius: 24px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03) !important;
  overflow: hidden;
}

.section-title {
  font-weight: 800;
  font-size: 1.2rem;
  letter-spacing: 0.05em;
  color: #1a1a1a;
}

/* QR Section */
.qr-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 280px;
}

.qr-wrapper {
  position: relative;
  width: 100%;
  max-width: 280px;
  border-radius: 20px;
  padding: 10px;
  background: white;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
  cursor: pointer;
  transition: all 0.3s ease;
}

.qr-wrapper:hover {
  transform: scale(1.02);
  box-shadow: 0 12px 35px rgba(0, 0, 0, 0.12);
}

.qr-code-img {
  width: 100%;
  height: auto;
  border-radius: 12px;
}

.qr-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(45, 79, 30, 0.8);
  border-radius: 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: white;
  opacity: 0;
  transition: opacity 0.3s ease;
  gap: 8px;
}

.qr-wrapper:hover .qr-overlay {
  opacity: 1;
}

.qr-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
}

/* Upload Section */
.upload-section {
  border: 2px dashed #e0e0e0;
  border-radius: 20px;
  padding: 30px 20px;
  cursor: pointer;
  transition: all 0.3s ease;
  background: #f9f9f9;
  position: relative;
  overflow: hidden;
  min-height: 180px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.upload-section:hover {
  border-color: #2D4F1E;
  background: #fcfdfc;
}

.upload-section.drag-over {
  border-color: #2D4F1E;
  background: #f0f7ef;
  transform: scale(1.01);
}

.upload-section.has-preview {
  padding: 10px;
  border-style: solid;
  border-color: #2D4F1E;
}

.upload-hint {
  display: flex;
  flex-direction: column;
  align-items: center;
  color: #666;
}

.upload-icon {
  color: #2D4F1E;
  opacity: 0.7;
}

.preview-container {
  width: 100%;
  position: relative;
}

.preview-img {
  width: 100%;
  max-height: 300px;
  object-fit: contain;
  border-radius: 12px;
}

.change-hint {
  position: absolute;
  bottom: 10px;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(0, 0, 0, 0.6);
  color: white;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 0.8rem;
  backdrop-filter: blur(4px);
}

/* Buttons */
.btn-submit-proof {
  background: #2D4F1E;
  color: white;
  border: none;
  border-radius: 16px;
  padding: 18px;
  font-weight: 700;
  font-size: 1rem;
  letter-spacing: 0.05em;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 10px 20px rgba(45, 79, 30, 0.2);
}

.btn-submit-proof:hover:not(:disabled) {
  background: #1e3614;
  transform: translateY(-2px);
  box-shadow: 0 15px 25px rgba(45, 79, 30, 0.3);
}

.btn-submit-proof:active:not(:disabled) {
  transform: translateY(0);
}

.btn-submit-proof:disabled {
  background: #ccc;
  box-shadow: none;
  cursor: not-allowed;
}

.btn-support {
  background: #fdf6ec;
  color: #e67e22;
  border: none;
  border-radius: 16px;
  padding: 12px;
  font-weight: 600;
  font-size: 0.9rem;
}

.text-success {
  color: #2D4F1E !important;
}

/* Responsive */
@media (max-width: 576px) {
  .container {
    padding-left: 15px;
    padding-right: 15px;
  }
  
  .section-card {
    border-radius: 20px;
  }
  
  .btn-submit-proof {
    padding: 15px;
  }
}
</style>


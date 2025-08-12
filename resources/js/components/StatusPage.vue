<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useBooking } from '../composables/useBooking'; // giả sử bạn có composable này
import { toast } from 'vue3-toastify'

const route = useRoute();
const router = useRouter();

const { selectedTable, endTime, total, formatVND, resetAll } = useBooking();

const isSuccess = ref(false);
const message = ref('');

onMounted(async () => {
  const resultCode = route.query.resultCode;
  const orderId = route.query.orderId || '';
  const msg = route.query.message || '';

  if (resultCode !== undefined) {
    if (resultCode === '0') {
      isSuccess.value = true;
      message.value = 'Giao dịch thành công!';
      toast.success('Giao dịch thành công!');
    } else {
      isSuccess.value = false;
      message.value = msg || 'Giao dịch thất bại hoặc bị hủy.';
      toast.error(message.value);
      // Gọi callback ipn thủ công khi thất bại
      await fetch('/api/momo/callback', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ resultCode, orderId }),
      });
    }
  } else {
    isSuccess.value = true;
    message.value = 'Giao dịch thành công!';
    toast.success('Giao dịch thành công!');
  }
});

function goHome() {
  resetAll();
  router.push('/package');
}
</script>

<template>
  <div class="text-center pt-5">
    <svg v-if="isSuccess" xmlns="http://www.w3.org/2000/svg" style="width: 80px; height: 80px; color: #198754" fill="currentColor" class="bi bi-check-circle-fill mb-3" viewBox="0 0 16 16">
      <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM6.97 11.03a.75.75 0 0 0 1.07 0l3.992-3.992a.75.75 0 1 0-1.06-1.06L7.5 9.439 5.525 7.464a.75.75 0 1 0-1.06 1.06l2.505 2.506z" />
    </svg>

    <svg v-else xmlns="http://www.w3.org/2000/svg" style="width: 80px; height: 80px; color: #dc3545" fill="currentColor" class="bi bi-x-circle-fill mb-3" viewBox="0 0 16 16">
      <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM4.646 4.646a.5.5 0 0 0 0 .708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293 5.354 4.646a.5.5 0 0 0-.708 0z" />
    </svg>

    <h3 class="mb-2 fw-bold" :class="isSuccess ? 'text-success' : 'text-danger'">{{ message }}</h3>

    <div v-if="isSuccess" class="confirm text-secondary fw-semibold">
      <p><strong>Số bàn:</strong> {{ selectedTable }}</p>
      <p><strong>Thời gian kết thúc:</strong> {{ endTime }}</p>
      <p><strong>Tổng:</strong> {{ formatVND(total) }}</p>
      <button class="btn btn-primary mt-3 px-4" @click="goHome">Xong</button>
    </div>

    <button class="btn btn-primary mt-3 px-4" v-if="!isSuccess" @click="goHome">Về trang đặt</button>
  </div>
</template>

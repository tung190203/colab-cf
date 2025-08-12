<script setup>
import { useRouter } from 'vue-router';
import { useBooking } from '../composables/useBooking';
import { toast } from 'vue3-toastify'

const router = useRouter();

const {
  selectedPackage,
  selectedTable,
  extras,
  total,
  formatVND,
} = useBooking();

async function pay(method) {
  try {
    const payload = {
      package_id: selectedPackage.value.id,
      table: selectedTable.value,
      end_time: new Date(Date.now() + selectedPackage.value.duration * 60000).toISOString(),
      extras: extras.value.map((e) => ({
        id: e.id,
        quantity: e.quantity || 1,
      })),
      payment_method: method,
    };

    const res = await fetch('/api/booking', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    if (!res.ok) {
      // Thử đọc lỗi backend trả về
      let errorMessage = `Lỗi thanh toán (status ${res.status})`;
      try {
        const errorData = await res.json();
        toast.error(`${errorData.message || 'Vui lòng thử lại sau'}`);
        if (errorData.message) errorMessage = errorData.message;
      } catch {
        // Nếu không phải JSON
        const text = await res.text();
        toast.error(`Lỗi thanh toán: ${text || 'Vui lòng thử lại sau'}`);
      }
      throw new Error(errorMessage);
    }

    const data = await res.json();

    if (method === 'momo' && data.payUrl) {
      window.location.href = data.payUrl;
      return;
    }
    if (method === 'transfer') {
      router.push(`/transfer/${data.booking.id}`);
      return;
    }
    router.push('/status');

  } catch (error) {
    console.error('Payment error:', error);
    // toast.error(`Thanh toán thất bại: ${error.message || 'Vui lòng thử lại sau'}`);
  }
}

function goBack() {
  router.back();
}
</script>

<template>
  <div>
    <div class="card p-4 mb-4 shadow-sm">
      <div class="d-flex justify-content-between mb-2">
        <div>Gói</div>
        <div class="fw-semibold">{{ selectedPackage?.name ?? '-' }}</div>
      </div>
      <div class="d-flex justify-content-between mb-2">
        <div>Bàn</div>
        <div class="fw-semibold">{{ selectedTable ?? '-' }}</div>
      </div>
      <div v-if="extras.length" class="mb-2">
        <div
          v-for="(e, i) in extras"
          :key="i"
          class="d-flex justify-content-between border-top pt-2 mt-2"
        >
          <div>{{ e.name }} x{{ e.quantity }}</div>
          <div>{{ formatVND(e.price * e.quantity) }}</div>
        </div>
      </div>
      <div class="d-flex justify-content-between fw-bold fs-5 border-top pt-3 mt-3">
        <div>Tổng</div>
        <div class="text-success">{{ formatVND(total) }}</div>
      </div>
    </div>

    <div class="d-flex flex-column gap-3">
      <!-- <button class="btn btn-momo" @click="pay('momo')">Thanh toán Momo</button> -->
      <button class="btn btn-success" @click="pay('transfer')">Chuyển khoản ngân hàng</button>
      <button class="btn btn-primary btn-custom" @click="pay('cash')">Thanh toán tiền mặt</button>
    </div>

    <div class="d-flex justify-content-between gap-3 mt-4">
      <button class="btn btn-outline-secondary flex-grow-1" @click="goBack">Quay lại</button>
    </div>
  </div>
</template>

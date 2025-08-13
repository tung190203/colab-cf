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
  start_time,
  end_time,
  name,
  phone
} = useBooking();

function toVietnamDatetime(localDateTimeStr) {
  const [date, time] = localDateTimeStr.split('T');
  return `${date} ${time}:00`;
}

async function pay(method) {
  try {
    const payload = {
      package_id: selectedPackage.value.id,
      table: selectedTable.value,
      start_time: toVietnamDatetime(start_time.value),
      end_time: toVietnamDatetime(end_time.value),
      extras: extras.value.map((e) => ({
        id: e.id,
        quantity: e.quantity || 1,
      })),
      payment_method: method,
      customer_name: name.value,
      customer_phone: phone.value,
    };

    const res = await fetch('/api/booking', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    if (!res.ok) {
      console.error('Payment error:', res.status, res.statusText);
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
  }
}

function formatTimeRange(start, end) {
  const o = { hour: 'numeric', minute: '2-digit', hour12: true };
  const s = new Date(start), e = new Date(end);

  const timeRange = `${s.toLocaleTimeString('en-US', o)} - ${e.toLocaleTimeString('en-US', o)}`;
  const day = s.toLocaleDateString('vi-VN');

  return { timeRange, day };
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
        <div>{{ selectedPackage?.name ?? '-' }}</div>
      </div>
      <div class="d-flex justify-content-between mb-2">
        <div>Bàn</div>
        <div>{{ selectedTable ?? '-' }}</div>
      </div>
      <div class="d-flex justify-content-between mb-2">
        <div>Thời gian</div>
        <div>
          <template v-if="start_time && end_time">
            {{ formatTimeRange(start_time, end_time).timeRange }}
          </template>
          <template v-else>-</template>
        </div>
      </div>

      <div class="d-flex justify-content-between mb-2">
        <div>Ngày</div>
        <div>
          <template v-if="start_time && end_time">
            {{ formatTimeRange(start_time, end_time).day }}
          </template>
          <template v-else>-</template>
        </div>
      </div>
      <div class="d-flex justify-content-between mb-2">
        <div>Người đặt</div>
        <div>{{ name }}</div>
      </div>
      <div class="d-flex justify-content-between mb-2">
        <div>SĐT</div>
        <div>{{ phone }}</div>
      </div>
      <div class="d-flex justify-content-between mb-2">
        <div>Giá tiền</div>
        <div>{{ formatVND(selectedPackage?.price) ?? '-' }}</div>
      </div>
      <div v-if="extras.length" class="mb-2">
        <div v-for="(e, i) in extras" :key="i" class="d-flex justify-content-between border-top pt-2 mt-2">
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

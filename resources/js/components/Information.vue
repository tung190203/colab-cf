<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useBooking } from '../composables/useBooking';
import { toast } from 'vue3-toastify';

const router = useRouter();
const { name, phone } = useBooking();

function goNext() {
  if (!name.value.trim() || !phone.value.trim()) {
    toast.error('Vui lòng nhập đầy đủ tên và số điện thoại');
    return;
  }

  const phoneRegex = /^[0-9]{9,12}$/;
  if (!phoneRegex.test(phone.value)) {
    toast.error('Số điện thoại không hợp lệ');
    return;
  }
  sessionStorage.setItem('customer_name', name.value);
  sessionStorage.setItem('customer_phone', phone.value);

  router.push('/summary');
}

function goBack() {
  router.back();
}
</script>

<template>
  <div class="card p-4 border-0">
    <h4 class="mb-3">Thông tin khách hàng</h4>

    <div class="mb-3">
      <label class="form-label">Họ và tên</label>
      <input v-model="name" type="text" class="form-control" placeholder="Nhập họ và tên"/>
    </div>

    <div class="mb-3">
      <label class="form-label">Số điện thoại</label>
      <input v-model="phone" type="text" class="form-control" placeholder="Nhập số điện thoại"/>
    </div>

    <div class="d-flex justify-content-between gap-3">
      <button class="btn btn-outline-secondary flex-grow-1" @click="goBack">Quay lại</button>
      <button class="btn btn-warning flex-grow-1" @click="goNext">Tiếp tục</button>
    </div>
  </div>
</template>

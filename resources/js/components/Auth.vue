<script setup>
import { useRouter } from 'vue-router';
import { useBooking } from '../composables/useBooking';
import { toast } from 'vue3-toastify';
import { onMounted } from 'vue';

const router = useRouter();
const { name, phone, fetchTables, selectTableFromUrl, fetchUserByCard, param, fetchUserByPhone } = useBooking();

function goNext() {
  if (!phone.value?.trim()) {
    toast.error('Vui lòng nhập số điện thoại');
    return;
  }

  const phoneRegex = /^[0-9]{9,12}$/;
  if (!phoneRegex.test(phone.value)) {
    toast.error('Số điện thoại không hợp lệ');
    return;
  }

  fetchUserByPhone(phone.value)
    .then(() => {
      if (name.value && phone.value) {
        toast.success('Đăng nhập thành công');
        setTimeout(() => {
          router.push('/package');
        }, 1000);
      }
    })
    .catch(err => {
      toast.error(err.message);
    });
}

function goBack() {
  router.back();
}

onMounted(async () => {
  await fetchTables();
  selectTableFromUrl();
  await fetchUserByCard(param.get('id'));
  if(name.value && phone.value) {
    router.push('/package');
  }
});
</script>

<template>
  <div class="card p-4 border-0">
    <h4 class="mb-3">Thông tin khách hàng</h4>
    <div class="mb-5">
      <label class="form-label">Số điện thoại</label>
      <input v-model="phone" type="text" class="form-control" placeholder="Nhập số điện thoại"/>
    </div>

    <div class="d-flex justify-content-between gap-3">
      <button class="btn btn-outline-secondary flex-grow-1" @click="goBack">Quay lại</button>
      <button class="btn btn-warning flex-grow-1" @click="goNext">Tiếp tục</button>
    </div>
  </div>
</template>

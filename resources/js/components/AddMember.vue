<script setup>
import { useBooking } from '../composables/useBooking';
import { toast } from 'vue3-toastify';

const { name, phone, addMemberToColab } = useBooking();

const addMember = async () => {
  if (!name.value || !phone.value) {
    toast.error('Vui lòng nhập đầy đủ thông tin!');
    return;
  }

  try {
    await addMemberToColab(name.value, phone.value);
    toast.success('Thêm thành viên thành công!');
    name.value = '';
    phone.value = '';
  } catch (error) {
    console.error('Error adding member:', error);
  }
};

</script>

<template>
  <div class="card p-4 border-0">
    <h4 class="mb-3">Nhập member mới</h4>
    <div class="mb-5">
      <label class="form-label">Họ và tên</label>
      <input v-model="name" type="text" class="form-control" placeholder="Nhập họ và tên"/>
    </div>
    <div class="mb-5">
      <label class="form-label">Số điện thoại</label>
      <input v-model="phone" type="text" class="form-control" placeholder="Nhập số điện thoại"/>
    </div>

    <div class="d-flex justify-content-between gap-3">
      <button class="btn btn-warning flex-grow-1" @click="addMember">Tạo mới</button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { toast } from 'vue3-toastify';
import { useBooking } from '../composables/useBooking';

const { addMemberToColab } = useBooking();

const name = ref('');
const phone = ref('');
const note = ref('');
const role = ref('');
const imageFile = ref(null);
const previewImage = ref(null);
const fileInput = ref(null);

function onFileChange(e) {
  const file = e.target.files[0];
  handleFile(file);
}

function onDrop(e) {
  const file = e.dataTransfer.files[0];
  handleFile(file);
}

function handleFile(file) {
  if (file && file.type.startsWith('image/')) {
    imageFile.value = file;
    previewImage.value = URL.createObjectURL(file);
  } else {
    toast.error('Vui lòng chọn đúng định dạng ảnh!');
  }
}

const addMember = async () => {
  if (!name.value || !phone.value || !role.value) {
    toast.error('Vui lòng nhập đầy đủ thông tin!');
    return;
  }

  try {
    const res = await addMemberToColab(
      name.value,
      phone.value,
      note.value,
      role.value,
      imageFile.value
    );

    toast.success(res.message || 'Thêm thành viên thành công!');
    name.value = '';
    phone.value = '';
    note.value = '';
    role.value = '';
    imageFile.value = null;
    previewImage.value = null;
  } catch (error) {
    if (error.errors) {
      Object.values(error.errors).flat().forEach(msg => toast.error(msg));
    } else {
      toast.error('Có lỗi xảy ra khi thêm thành viên');
    }
  }
};
</script>

<template>
  <div class="card p-4 border-0">
    <h4 class="mb-3">Nhập member mới</h4>
    <div class="mb-3">
      <label class="form-label">Họ và tên</label>
      <input v-model="name" type="text" class="form-control" placeholder="Nhập họ và tên"/>
    </div>
    <div class="mb-3">
      <label class="form-label">Số điện thoại</label>
      <input v-model="phone" type="text" class="form-control" placeholder="Nhập số điện thoại"/>
    </div>

    <div class="mb-3">
      <label class="form-label">Ảnh đại diện</label>
            <!-- Preview ảnh -->
            <div v-if="previewImage" class="preview-container mb-3">
        <img :src="previewImage" alt="Preview" class="preview-image" />
      </div>
      <!-- Khu vực kéo/thả -->
      <div
        class="dropzone"
        @dragover.prevent
        @drop.prevent="onDrop"
        @click="fileInput.click()"
      >
        <p class="mb-0">Kéo & thả ảnh hoặc bấm để chọn</p>
        <input
          type="file"
          ref="fileInput"
          class="d-none"
          accept="image/*"
          @change="onFileChange"
        />
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Ghi chú</label>
      <textarea v-model="note" class="form-control" rows="3" placeholder="Nhập ghi chú"></textarea>
    </div>
    <div class="mb-3">
      <label class="form-label">Chọn vai trò</label>
      <select class="form-select" v-model="role">
        <option value="">Chọn vai trò</option>
        <option value="member">Member</option>
        <option value="vip">VIP</option>
        <option value="admin">Admin</option>
      </select>
    </div>

    <div class="d-flex justify-content-between gap-3 mt-3">
      <button class="btn btn-warning flex-grow-1" @click="addMember">Tạo mới</button>
    </div>
  </div>
</template>

<style scoped>
.dropzone {
  border: 2px dashed #20451F;
  border-radius: 8px;
  padding: 20px;
  text-align: center;
  color: #20451F;
  cursor: pointer;
  background-color: #f8fdf8;
  transition: background-color 0.2s ease;
}
.dropzone:hover {
  background-color: #eaf6ea;
}

.preview-container {
  display: flex;
  justify-content: center;
}

.preview-image {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid #20451F;
}
</style>

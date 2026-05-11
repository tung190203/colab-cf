<script setup>
import { ref } from 'vue';
import { toast } from 'vue3-toastify';
import { useBooking } from '../composables/useBooking';
import AdminLayout from './admin/AdminLayout.vue';

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

function handleFile(file) {
  if (!file) return;
  const isImage = file.type.startsWith('image/');
  const isLt2M = file.size <= 2 * 1024 * 1024;
  
  if (!isImage) {
    toast.error('Vui lòng chọn đúng định dạng ảnh!');
    return;
  }
  if (!isLt2M) {
    toast.error('Ảnh vượt quá 2MB!');
    return;
  }

  imageFile.value = file;
  previewImage.value = URL.createObjectURL(file);
}

const addMember = async () => {
  if (!name.value || !phone.value || !role.value) {
    toast.error('Vui lòng nhập đầy đủ thông tin!');
    return;
  }

  const nameRegex = /^[\p{L}\s]+$/u;
  if (!nameRegex.test(name.value)) {
    toast.error('Tên không hợp lệ (chỉ chứa chữ và khoảng trắng)');
    return;
  }

  const phoneRegex = /^\d{8,15}$/;
  if (!phoneRegex.test(phone.value)) {
    toast.error('Số điện thoại không hợp lệ (chỉ chứa số, 8–15 chữ số)');
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
  <AdminLayout>
    <template #title>Thêm thành viên mới</template>

    <div class="am-wrap">
      <div class="am-card">
        <div class="am-avatar-section">
            <div class="am-avatar-wrap" @click="fileInput.click()">
                <img v-if="previewImage" :src="previewImage" />
                <div v-else class="am-avatar-ph">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="40" height="40">
                        <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>
                    </svg>
                </div>
                <div class="am-overlay">Chọn ảnh</div>
                <input type="file" ref="fileInput" class="d-none" accept="image/*" @change="onFileChange" />
            </div>
            <p class="am-hint">Bấm vào vòng tròn để chọn ảnh đại diện</p>
        </div>

        <div class="am-form">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="am-field">
                        <label>Họ và tên</label>
                        <input v-model="name" type="text" placeholder="Nguyễn Văn A" />
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="am-field">
                        <label>Số điện thoại</label>
                        <input v-model="phone" type="text" placeholder="09xxxxxxxx" />
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="am-field">
                        <label>Vai trò</label>
                        <select v-model="role">
                            <option value="">-- Chọn vai trò --</option>
                            <option value="member">Member</option>
                            <option value="vip">VIP</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="am-field">
                        <label>Ghi chú</label>
                        <textarea v-model="note" rows="3" placeholder="Nhập thêm ghi chú nếu có..."></textarea>
                    </div>
                </div>
            </div>

            <div class="am-actions">
                <button class="am-btn-save" @click="addMember">
                    TẠO THÀNH VIÊN MỚI
                </button>
                <router-link to="/admin/members/list" class="am-btn-list">
                    Xem danh sách
                </router-link>
            </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
.am-wrap { max-width: 700px; margin: 0 auto; }

.am-card { background: white; border-radius: 24px; padding: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }

.am-avatar-section { display: flex; flex-direction: column; align-items: center; margin-bottom: 40px; }
.am-avatar-wrap { 
    width: 140px; height: 140px; border-radius: 50%; overflow: hidden; 
    background: #f8fafb; border: 4px solid white; box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    cursor: pointer; position: relative; transition: all 0.2s;
}
.am-avatar-wrap:hover { transform: scale(1.02); }
.am-avatar-wrap img { width: 100%; height: 100%; object-fit: cover; }
.am-avatar-ph { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #2D4F1E; }
.am-overlay { 
    position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.4); 
    color: white; font-size: 0.75rem; text-align: center; padding: 6px 0;
    opacity: 0; transition: opacity 0.2s;
}
.am-avatar-wrap:hover .am-overlay { opacity: 1; }
.am-hint { font-size: 0.8rem; color: #888; margin-top: 12px; }

.am-field { margin-bottom: 8px; }
.am-field label { display: block; font-size: 0.8rem; font-weight: 700; color: #888; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.05em; }
.am-field input, .am-field select, .am-field textarea {
    width: 100%; padding: 12px 16px; border-radius: 14px; border: 1.5px solid #eef2ef;
    outline: none; font-size: 1rem; background: #fafbfc; transition: all 0.2s;
}
.am-field input:focus, .am-field select:focus, .am-field textarea:focus { 
    border-color: #2D4F1E; background: white; box-shadow: 0 0 0 4px rgba(45, 79, 30, 0.05);
}

.am-actions { margin-top: 32px; display: flex; flex-direction: column; gap: 12px; }
.am-btn-save {
    background: #2D4F1E; color: white; border: none; border-radius: 14px;
    padding: 16px; font-weight: 800; font-size: 1rem; letter-spacing: 0.05em;
    cursor: pointer; box-shadow: 0 8px 20px rgba(45, 79, 30, 0.2); transition: all 0.2s;
}
.am-btn-save:hover { background: #1f3815; transform: translateY(-2px); }
.am-btn-list {
    text-align: center; text-decoration: none; color: #888; font-weight: 600;
    font-size: 0.9rem; padding: 10px;
}
.am-btn-list:hover { color: #2D4F1E; }

@media (max-width: 500px) {
    .am-card { padding: 24px; }
}
</style>

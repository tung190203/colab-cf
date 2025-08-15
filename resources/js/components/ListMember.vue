<script setup>
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue3-toastify';

const listMember = ref([]);
const currentPage = ref(1);
const itemsPerPage = 30;

// Basic Auth
const pageLocked = ref(true);
const PASSWORD = import.meta.env.VITE_BASIC_AUTH_PASSWORD;

function checkPassword() {
  const isUnlocked = sessionStorage.getItem('pageUnlocked');
  if (isUnlocked) {
    pageLocked.value = false;
    getListMember();
    return;
  }

  const input = prompt('Nhập mật khẩu để truy cập:');
  if (input === PASSWORD) {
    pageLocked.value = false;
    sessionStorage.setItem('pageUnlocked', 'true');
    getListMember();
  } else {
    alert('Sai mật khẩu!');
    location.reload();
  }
}

// --- pagination ---
const totalPages = computed(() => Math.ceil(listMember.value.length / itemsPerPage));
const paginatedListMember = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage;
  const end = start + itemsPerPage;
  return listMember.value.slice(start, end);
});

const showEditModal = ref(false);
const editMemberData = ref({ id: null, name: '', phone: '', note: '', role: '', image_url: null });
const newImageFile = ref(null);
const previewImage = ref(null);
const fileInput = ref(null);

async function getListMember() {
  const response = await fetch('/api/list-members');
  if (!response.ok) throw new Error('Network response was not ok');
  listMember.value = await response.json();
}

function goToPage(page) {
  if (page >= 1 && page <= totalPages.value) currentPage.value = page;
}

function deleteMember(id) {
  if (confirm('Bạn có chắc chắn muốn xóa member này?')) {
    fetch(`/api/member/${id}`, { method: 'DELETE' })
      .then(r => r.json())
      .then(data => {
        listMember.value = listMember.value.filter(m => m.id !== id);
        toast.success(data.message || 'Xóa member thành công');
      })
      .catch(err => toast.error(err.message || 'Xóa member thất bại'));
  }
}

function openEditModal(member) {
  editMemberData.value = { ...member };
  previewImage.value = member.image_url || null;
  newImageFile.value = null;
  showEditModal.value = true;
}

function triggerFile() {
  fileInput.value?.click();
}

function onFileChange(e) {
  const file = e.target.files?.[0];
  handleFile(file);
}

function onDrop(e) {
  const file = e.dataTransfer?.files?.[0];
  handleFile(file);
}

function handleFile(file) {
  if (!file) return;
  const isImage = file.type.startsWith('image/');
  const isLt2M = file.size <= 2 * 1024 * 1024;
  if (!isImage) return toast.error('Vui lòng chọn đúng định dạng ảnh!');
  if (!isLt2M) return toast.error('Ảnh vượt quá 2MB!');
  newImageFile.value = file;
  previewImage.value = URL.createObjectURL(file);
}

function saveEdit() {
  const formData = new FormData();
  formData.append('name', editMemberData.value.name);
  formData.append('phone', editMemberData.value.phone);
  formData.append('note', editMemberData.value.note || '');
  formData.append('role', editMemberData.value.role || '');
  if (newImageFile.value) {
    formData.append('image', newImageFile.value);
  }

  formData.append('_method', 'PUT');

  fetch(`/api/member/${editMemberData.value.id}`, {
    method: 'POST',
    body: formData
  })
    .then(async (response) => {
      if (!response.ok) {
        const err = await response.json().catch(() => ({}));
        throw err;
      }
      return response.json();
    })
    .then((data) => {
      const idx = listMember.value.findIndex(m => m.id === editMemberData.value.id);
      if (idx !== -1) {
        listMember.value[idx] = data.user;
      }
      toast.success(data.message || 'Cập nhật member thành công');
      showEditModal.value = false;
    })
    .catch((error) => {
      if (error?.errors) {
        Object.values(error.errors).flat().forEach(msg => toast.error(msg));
      } else {
        toast.error(error?.message || 'Cập nhật member thất bại');
      }
    });
}

onMounted(() => {
  checkPassword();
});
</script>

<template>
  <div v-if="!pageLocked" class="container py-4">
    <h2 class="mb-4">Danh sách Member</h2>

    <div v-if="listMember.length === 0" class="text-center text-muted py-5">
      Không có member nào hiện tại
    </div>

    <div v-else class="table-responsive shadow-sm rounded">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th scope="col" class="text-center" style="width: 60px;">#</th>
            <th scope="col" style="width: 60px;">Ảnh đại diện</th>
            <th scope="col" style="width: 60px;">Tên</th>
            <th scope="col">Số điện thoại</th>
            <th scope="col" style="width: 60px;">Ghi chú</th>
            <th scope="col" style="width: 120px;">Ngày tham gia</th>
            <th scope="col" class="text-center" style="width: 120px;">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(member, index) in paginatedListMember" :key="member.id">
            <td class="text-center fw-bold">
              {{ (currentPage - 1) * itemsPerPage + index + 1 }}
            </td>
            <td class="text-nowrap text-center">
              <img v-if="member.image_url" :src="member.image_url" alt="Avatar" class="rounded-circle"
                style="width: 40px; height: 40px; object-fit: cover;">
              <span v-else class="text-muted"></span>
            </td>
            <td class="text-nowrap">{{ member.name }}</td>
            <td>{{ member.phone }}</td>
            <td class="text-nowrap">{{ member.note }}</td>
            <td>{{ new Date(member.created_at).toLocaleDateString('vi-VN') }}</td>
            <td class="text-center d-flex justify-content-center gap-2">
              <button class="btn btn-danger btn-sm" @click="deleteMember(member.id)">
                Xóa
              </button>
              <button class="btn btn-primary btn-sm" @click="openEditModal(member)">
                Sửa
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <nav v-if="totalPages > 1" class="mt-3">
      <ul class="pagination justify-content-center">
        <li class="page-item" :class="{ disabled: currentPage === 1 }">
          <button class="page-link" @click="goToPage(currentPage - 1)">Previous</button>
        </li>
        <li class="page-item" v-for="page in totalPages" :key="page" :class="{ active: currentPage === page }">
          <button class="page-link" @click="goToPage(page)">{{ page }}</button>
        </li>
        <li class="page-item" :class="{ disabled: currentPage === totalPages }">
          <button class="page-link" @click="goToPage(currentPage + 1)">Next</button>
        </li>
      </ul>
    </nav>
  </div>

  <!-- Edit Member Modal -->
  <div class="modal fade" tabindex="-1" :class="{ show: showEditModal }" style="display: block;" v-if="showEditModal">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title">Chỉnh sửa Member</h5>
          <button type="button" class="btn-close btn-close-white" @click="showEditModal = false"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Tên</label>
            <input type="text" v-model="editMemberData.name" class="form-control" />
          </div>

          <div class="mb-3">
            <label class="form-label">Số điện thoại</label>
            <input type="text" v-model="editMemberData.phone" class="form-control" />
          </div>
          <!-- Dropzone -->
          <div class="mb-2">
            <label class="form-label">Ảnh đại diện</label>
          <div class="d-flex justify-content-center mb-3" v-if="previewImage">
            <img :src="previewImage" alt="Preview" class="avatar-preview" />
          </div>
            <div class="dropzone text-center" @dragover.prevent @drop.prevent="onDrop" @click="triggerFile">
              <p class="mb-0">Kéo & thả ảnh vào đây hoặc bấm để chọn</p>
              <input type="file" ref="fileInput" class="d-none" accept="image/*" @change="onFileChange" />
            </div>
          </div>
          <!-- Note -->
          <div class="mb-3">
            <label class="form-label">Ghi chú</label>
            <textarea v-model="editMemberData.note" class="form-control" rows="3" placeholder="Nhập ghi chú"></textarea>
          </div>
          <!-- Role -->
          <div class="mb-1">
            <label class="form-label">Vai trò</label>
            <select class="form-select" v-model="editMemberData.role">
              <option value="">Chọn vai trò</option>
              <option value="member">Member</option>
              <option value="admin">Admin</option>
              <option value="vip">VIP</option>
            </select>
          </div>

        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showEditModal = false">Đóng</button>
          <button class="btn btn-primary" @click="saveEdit">Lưu</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.table-hover tbody tr:hover {
  background-color: #f8f9fa;
}

.table thead th {
  white-space: nowrap;
}

.pagination .page-item.active .page-link {
  background-color: #20451F;
  border-color: #20451F;
  color: white;
}

.pagination .page-link {
  color: #555555;
}

.pagination .page-link:hover {
  color: #20451F;
}

/* Overlay cho modal custom */
.modal {
  background-color: rgba(0, 0, 0, 0.5);
}

/* Dropzone: dashed + màu #20451F */
.dropzone {
  border: 2px dashed #20451F;
  border-radius: 12px;
  padding: 16px;
  cursor: pointer;
  user-select: none;
}

.dropzone:hover {
  background-color: #f6fbf6;
}

/* Preview ảnh tròn (bên ngoài dropzone) */
.avatar-preview {
  width: 120px;
  height: 120px;
  object-fit: cover;
  border-radius: 50%;
  border: 2px solid #20451F;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
}
</style>

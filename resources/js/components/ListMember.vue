<script setup>
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue3-toastify';
import AdminLayout from './admin/AdminLayout.vue';
import { Pencil, Trash2, Camera, Plus, Search } from 'lucide-vue-next';
import { useAdminAuth } from '../composables/useAdminAuth';
import ConfirmDialog from './ConfirmDialog.vue';

const { adminUser, authHeader } = useAdminAuth();
const confirmModal = ref({ show: false, id: null });
const listMember = ref([]);
const currentPage = ref(1);
const itemsPerPage = 12;
const loading = ref(true);
const searchQuery = ref('');

// Lọc bỏ tài khoản Admin, Staff và tìm kiếm theo tên/sđt
const filteredListMember = computed(() => {
  let list = listMember.value;
  
  // 1. Chỉ lấy những người có vai trò là member/vip hoặc không có vai trò (mặc định là member)
  // Loại bỏ admin và staff
  list = list.filter(m => m.role !== 'admin' && m.role !== 'staff');
  
  // 2. Tìm kiếm
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(m => 
      m.name?.toLowerCase().includes(q) || 
      m.phone?.includes(q)
    );
  }
  
  return list;
});

// --- pagination ---
const totalPages = computed(() => Math.ceil(filteredListMember.value.length / itemsPerPage));
const paginatedListMember = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage;
  const end = start + itemsPerPage;
  return filteredListMember.value.slice(start, end);
});
const visiblePageItems = computed(() => buildPageItems(currentPage.value, totalPages.value));

function buildPageItems(current, total) {
  if (total <= 7) {
    return Array.from({ length: total }, (_, index) => ({ type: 'page', page: index + 1, key: `page-${index + 1}` }));
  }

  const pages = new Set([1, total, current - 1, current, current + 1]);
  if (current <= 3) [2, 3, 4].forEach(page => pages.add(page));
  if (current >= total - 2) [total - 3, total - 2, total - 1].forEach(page => pages.add(page));

  const sortedPages = [...pages].filter(page => page >= 1 && page <= total).sort((a, b) => a - b);
  const items = [];

  sortedPages.forEach((page, index) => {
    const previous = sortedPages[index - 1];
    if (index > 0 && page - previous > 1) {
      items.push({ type: 'ellipsis', key: `ellipsis-${previous}-${page}` });
    }
    items.push({ type: 'page', page, key: `page-${page}` });
  });

  return items;
}

const showEditModal = ref(false);
const editMemberData = ref({ id: null, name: '', phone: '', note: '', role: '', image_url: null });
const newImageFile = ref(null);
const previewImage = ref(null);
const fileInput = ref(null);

async function getListMember() {
  loading.value = true;
  try {
    const response = await fetch('/api/list-members', {
        headers: authHeader()
    });
    if (!response.ok) throw new Error('Network response was not ok');
    listMember.value = await response.json();
  } catch (error) {
    toast.error('Không thể tải danh sách thành viên');
  } finally {
    loading.value = false;
  }
}

function goToPage(page) {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
  }
}

function deleteMember(id) {
    confirmModal.value = { show: true, id };
}

async function executeDeleteMember() {
    const id = confirmModal.value.id;
    confirmModal.value.show = false;
    try {
        const res = await fetch(`/api/member/${id}`, { 
            method: 'DELETE',
            headers: authHeader()
        });
        const data = await res.json();
        if (res.ok) {
            listMember.value = listMember.value.filter(m => m.id !== id);
            toast.success(data.message || 'Xóa thành công');
        } else {
            throw data;
        }
    } catch (err) {
        toast.error(err.message || 'Xóa thất bại');
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

function handleFile(file) {
  if (!file) return;
  const isImage = file.type.startsWith('image/');
  const isLt2M = file.size <= 2 * 1024 * 1024;
  if (!isImage) return toast.error('Vui lòng chọn đúng định dạng ảnh!');
  if (!isLt2M) return toast.error('Ảnh vượt quá 2MB!');
  newImageFile.value = file;
  previewImage.value = URL.createObjectURL(file);
}

async function saveEdit() {
  const formData = new FormData();
  formData.append('name', editMemberData.value.name);
  formData.append('phone', editMemberData.value.phone);
  formData.append('note', editMemberData.value.note || '');
  formData.append('role', editMemberData.value.role || '');
  if (newImageFile.value) {
    formData.append('image', newImageFile.value);
  }
  formData.append('_method', 'PUT');

  try {
    const response = await fetch(`/api/member/${editMemberData.value.id}`, {
        method: 'POST',
        headers: { 
            ...authHeader()
        },
        body: formData
    });
    
    const data = await response.json();
    if (!response.ok) throw data;

    const idx = listMember.value.findIndex(m => m.id === editMemberData.value.id);
    if (idx !== -1) {
        listMember.value[idx] = data.user;
    }
    toast.success('Cập nhật thành công');
    showEditModal.value = false;
  } catch (error) {
    toast.error(error.message || 'Cập nhật thất bại');
  }
}

onMounted(getListMember);
</script>

<template>
  <AdminLayout>
    <template #title>Danh sách thành viên</template>

    <div class="ml-wrap">
      <div class="ml-toolbar">
          <div class="ml-search-wrap">
              <Search :size="16" class="ml-search-icon" />
              <input v-model="searchQuery" class="ml-search" placeholder="Tìm tên hoặc số điện thoại..." />
          </div>
          <router-link to="/admin/members/add" class="ml-add-btn">
              <Plus :size="16" />
              Thêm thành viên
          </router-link>
      </div>

      <div v-if="loading" class="ml-loading">
          <div class="ml-spinner"></div>
      </div>

      <div v-else class="ml-table-card">
          <div class="table-responsive">
              <table class="ml-table">
                  <thead>
                      <tr>
                          <th>STT</th>
                          <th>Thành viên</th>
                          <th>Số điện thoại</th>
                          <th>Vai trò</th>
                          <th>Ngày tham gia</th>
                          <th class="text-end">Thao tác</th>
                      </tr>
                  </thead>
                  <tbody>
                      <tr v-if="listMember.length === 0">
                          <td colspan="6" class="text-center py-5 text-muted">Chưa có thành viên nào.</td>
                      </tr>
                      <tr v-for="(member, index) in paginatedListMember" :key="member.id">
                          <td class="ml-stt">{{ (currentPage - 1) * itemsPerPage + index + 1 }}</td>
                          <td>
                              <div class="ml-user">
                                  <div class="ml-avatar">
                                      <img v-if="member.image_url" :src="member.image_url" />
                                      <span v-else>{{ member.name.charAt(0).toUpperCase() }}</span>
                                  </div>
                                  <div class="ml-info">
                                      <div class="ml-name">{{ member.name }}</div>
                                      <div class="ml-note" v-if="member.note">{{ member.note }}</div>
                                  </div>
                              </div>
                          </td>
                          <td class="ml-phone">{{ member.phone }}</td>
                          <td>
                              <span class="ml-role" :class="member.role || 'member'">
                                  {{ member.role || 'Member' }}
                              </span>
                          </td>
                          <td class="ml-date">{{ new Date(member.created_at).toLocaleDateString('vi-VN') }}</td>
                          <td>
                              <div class="ml-actions">
                                  <button class="ml-btn-edit" title="Sửa" @click="openEditModal(member)">
                                      <Pencil :size="16" />
                                  </button>
                                  <button class="ml-btn-del" title="Xóa" @click="deleteMember(member.id)">
                                      <Trash2 :size="16" />
                                  </button>
                              </div>
                          </td>
                      </tr>
                  </tbody>
              </table>
          </div>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="ml-pagination">
          <button :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">←</button>
          <template v-for="item in visiblePageItems" :key="item.key">
              <span v-if="item.type === 'ellipsis'" class="ml-page-ellipsis">...</span>
              <button v-else :class="{ active: currentPage === item.page }" @click="goToPage(item.page)">{{ item.page }}</button>
          </template>
          <button :disabled="currentPage === totalPages" @click="goToPage(currentPage + 1)">→</button>
      </div>
    </div>

    <!-- Edit Modal -->
    <Teleport to="body">
        <div v-if="showEditModal" class="ml-modal-overlay" @click.self="showEditModal = false">
            <div class="ml-modal">
                <div class="ml-modal-header">
                    <h5>Sửa thành viên</h5>
                    <button @click="showEditModal = false">✕</button>
                </div>
                <div class="ml-modal-body">
                    <div class="ml-edit-avatar" @click="triggerFile">
                        <img v-if="previewImage" :src="previewImage" />
                        <div v-else class="ml-avatar-ph">
                            {{ editMemberData.name?.charAt(0).toUpperCase() || '?' }}
                        </div>
                        <input type="file" ref="fileInput" class="d-none" @change="onFileChange" />
                    </div>
                    
                    <div class="ml-form">
                        <div class="ml-field">
                            <label>Họ và tên</label>
                            <input v-model="editMemberData.name" type="text" />
                        </div>
                        <div class="ml-field">
                            <label>Số điện thoại</label>
                            <input v-model="editMemberData.phone" type="text" />
                        </div>
                        <div class="ml-field">
                            <label>Vai trò</label>
                            <select v-model="editMemberData.role">
                                <option value="member">Member</option>
                                <option value="vip">VIP</option>
                            </select>
                        </div>
                        <div class="ml-field">
                            <label>Ghi chú</label>
                            <textarea v-model="editMemberData.note"></textarea>
                        </div>
                    </div>
                </div>
                <div class="ml-modal-footer">
                    <button class="ml-btn-cancel" @click="showEditModal = false">Hủy</button>
                    <button class="ml-btn-save" @click="saveEdit">Lưu thay đổi</button>
                </div>
            </div>
        </div>
    </Teleport>

    <ConfirmDialog 
        :show="confirmModal.show"
        title="Xóa thành viên"
        message="Bạn có chắc chắn muốn xóa thành viên này? Hành động này không thể hoàn tác."
        confirmText="Xóa thành viên"
        type="danger"
        @confirm="executeDeleteMember"
        @cancel="confirmModal.show = false"
    />
  </AdminLayout>
</template>

<style scoped>
.ml-wrap { max-width: 1200px; margin: 0 auto; }

.ml-toolbar { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    margin-bottom: 24px; 
    gap: 16px;
    flex-wrap: wrap;
}

.ml-search-wrap {
    position: relative;
    flex: 1;
    min-width: 200px;
}

.ml-search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #aaa;
    pointer-events: none;
}

.ml-search {
    width: 100%;
    padding: 11px 16px 11px 40px;
    border: 1.5px solid #e0e6ed;
    border-radius: 12px;
    font-size: 0.9rem;
    background: white;
    outline: none;
    transition: border-color 0.2s;
    font-family: 'Inter', sans-serif;
}

.ml-search:focus {
    border-color: #2D4F1E;
}

.ml-add-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 11px 20px;
    background: linear-gradient(135deg, #2D4F1E, #4a7c2f);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
    text-decoration: none;
    font-family: 'Inter', sans-serif;
}
.ml-add-btn:hover { 
    background: linear-gradient(135deg, #1f3815, #3a6428); 
    transform: translateY(-1px);
    color: white;
}

.ml-loading { display: flex; justify-content: center; padding: 60px; }
.ml-spinner { width: 36px; height: 36px; border: 3px solid #f0f0f0; border-top-color: #2D4F1E; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.ml-table-card { background: white; border-radius: 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.05); overflow: hidden; }
.ml-table { width: 100%; border-collapse: collapse; min-width: 800px; }
.ml-table th { background: #fafbfc; padding: 16px; font-size: 0.75rem; font-weight: 700; color: #888; text-transform: uppercase; text-align: left; border-bottom: 1px solid #f0f0f0; white-space: nowrap; }
.ml-table td { padding: 16px; font-size: 0.9rem; border-bottom: 1px solid #f5f5f5; vertical-align: middle; white-space: nowrap; }

.ml-stt { color: #aaa; font-weight: 600; width: 60px; }
.ml-user { display: flex; align-items: center; gap: 12px; }
.ml-avatar { 
    width: 40px; height: 40px; border-radius: 12px; 
    background: linear-gradient(135deg, #2D4F1E, #4a7c2f); 
    color: white; display: flex; align-items: center; justify-content: center; 
    font-weight: 800; overflow: hidden; flex-shrink: 0;
}
.ml-avatar img { width: 100%; height: 100%; object-fit: cover; }
.ml-name { font-weight: 700; color: #1a1a2e; }
.ml-note { font-size: 0.75rem; color: #aaa; margin-top: 2px; }
.ml-phone { font-weight: 600; color: #555; }
.ml-role { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; padding: 4px 10px; border-radius: 100px; white-space: nowrap; }
.ml-role.admin { background: #fff0f0; color: #dc2626; }
.ml-role.vip { background: #fff8e6; color: #b45309; }
.ml-role.member { background: #f0f4f0; color: #2D4F1E; }
.ml-date { color: #888; font-size: 0.85rem; }

.ml-actions { display: flex; gap: 8px; }
.ml-btn-edit, .ml-btn-del { width: 32px; height: 32px; border: none; border-radius: 8px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; }
.ml-btn-edit { background: #f0f4f0; color: #2D4F1E; }
.ml-btn-edit:hover { background: #2D4F1E; color: white; }
.ml-btn-del { background: #fff0f0; color: #dc2626; }
.ml-btn-del:hover { background: #dc2626; color: white; }

.ml-pagination { display: flex; justify-content: center; gap: 8px; margin-top: 30px; }
.ml-page-ellipsis { width: 28px; height: 36px; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-weight: 800; }
.ml-pagination button { width: 36px; height: 36px; border-radius: 10px; border: 1px solid #e0e6ed; background: white; font-weight: 700; cursor: pointer; color: #666; }
.ml-pagination button.active { background: #2D4F1E; color: white; border-color: #2D4F1E; }
.ml-pagination button:disabled { opacity: 0.4; cursor: not-allowed; }

/* Modal */
.ml-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 20px; }
.ml-modal { background: white; width: 100%; max-width: 480px; border-radius: 24px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); overflow: hidden; }
.ml-modal-header { padding: 20px 28px; background: #2D4F1E; color: white; display: flex; justify-content: space-between; align-items: center; }
.ml-modal-header h5 { margin: 0; font-weight: 800; }
.ml-modal-header button { background: none; border: none; color: white; font-size: 1.2rem; cursor: pointer; }
.ml-modal-body { padding: 28px; }

.ml-edit-avatar { 
    width: 100px; height: 100px; margin: 0 auto 24px; border-radius: 20px; 
    overflow: hidden; border: 4px solid #f0f4f0; cursor: pointer; 
    background: linear-gradient(135deg, #2D4F1E, #4a7c2f); 
    display: flex; align-items: center; justify-content: center; 
}
.ml-edit-avatar img { width: 100%; height: 100%; object-fit: cover; }
.ml-avatar-ph { font-size: 2.5rem; font-weight: 800; color: white; }

.ml-field { margin-bottom: 16px; }
.ml-field label { display: block; font-size: 0.8rem; font-weight: 700; color: #888; margin-bottom: 6px; }
.ml-field input, .ml-field select, .ml-field textarea { width: 100%; padding: 12px; border-radius: 12px; border: 1.5px solid #e0e6ed; outline: none; font-family: inherit; font-size: 0.95rem; }
.ml-field input:focus { border-color: #2D4F1E; }

.ml-modal-footer { padding: 20px 28px; display: flex; gap: 12px; justify-content: flex-end; border-top: 1px solid #f5f5f5; }
.ml-btn-cancel { padding: 10px 20px; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; }
.ml-btn-save { padding: 10px 24px; border: none; border-radius: 10px; background: #2D4F1E; color: white; font-weight: 700; cursor: pointer; }
</style>

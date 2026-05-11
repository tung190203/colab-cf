<script setup>
import { ref, onMounted, computed } from 'vue';
import AdminLayout from './AdminLayout.vue';
import { Pencil, Trash2, Plus, Search } from 'lucide-vue-next';
import { useAdminAuth } from '../../composables/useAdminAuth';
import { toast } from 'vue3-toastify';
import axios from 'axios';

const { adminUser, authHeader } = useAdminAuth();
const staff = ref([]);
const loading = ref(true);
const showModal = ref(false);
const editMode = ref(false);
const submitting = ref(false);
const searchQuery = ref('');

const form = ref({
    id: null, name: '', phone: '', password: '',
    role: 'staff', hourly_rate: '', note: '', image: null
});
const previewImg = ref(null);

onMounted(fetchStaff);

async function fetchStaff() {
    loading.value = true;
    try {
        const res = await axios.get('/api/admin/staff', { headers: authHeader() });
        staff.value = res.data;
    } catch (e) {
        toast.error('Không thể tải danh sách');
    } finally {
        loading.value = false;
    }
}

function openAdd() {
    editMode.value = false;
    form.value = { id: null, name: '', phone: '', password: '', role: 'staff', hourly_rate: '', note: '', image: null };
    previewImg.value = null;
    showModal.value = true;
}

function openEdit(s) {
    editMode.value = true;
    form.value = { id: s.id, name: s.name, phone: s.phone, password: '', role: s.role, hourly_rate: s.hourly_rate ? parseInt(s.hourly_rate) : 0, note: s.note || '', image: null };
    previewImg.value = s.image_url;
    showModal.value = true;
}

function handleImage(e) {
    const file = e.target.files[0];
    if (!file) return;
    form.value.image = file;
    previewImg.value = URL.createObjectURL(file);
}

async function submitForm() {
    submitting.value = true;
    try {
        const fd = new FormData();
        Object.entries(form.value).forEach(([k, v]) => {
            if (v !== null && v !== '') fd.append(k, v);
        });

        const config = { headers: { ...authHeader(), 'Content-Type': 'multipart/form-data' } };
        if (editMode.value) {
            fd.append('_method', 'PUT');
            await axios.post(`/api/admin/staff/${form.value.id}`, fd, config);
            toast.success('Cập nhật thành công');
        } else {
            await axios.post('/api/admin/staff', fd, config);
            toast.success('Thêm nhân viên thành công');
        }
        showModal.value = false;
        fetchStaff();
    } catch (e) {
        const msg = e.response?.data?.message || Object.values(e.response?.data?.errors || {})?.[0]?.[0];
        toast.error(msg || 'Có lỗi xảy ra');
    } finally {
        submitting.value = false;
    }
}

async function deleteStaff(id) {
    if (!confirm('Bạn có chắc muốn xóa nhân viên này?')) return;
    try {
        await axios.delete(`/api/admin/staff/${id}`, { headers: authHeader() });
        toast.success('Đã xóa');
        fetchStaff();
    } catch (e) {
        toast.error('Không thể xóa');
    }
}

const filteredStaff = () => {
    let list = staff.value;
    
    // Ẩn tài khoản của chính người đang đăng nhập
    if (adminUser.value?.id) {
        list = list.filter(s => s.id !== adminUser.value.id);
    }

    if (!searchQuery.value) return list;
    const q = searchQuery.value.toLowerCase();
    return list.filter(s => s.name.toLowerCase().includes(q) || s.phone.includes(q));
};

// --- Xử lý định dạng lương ---
const displaySalary = computed(() => {
    if (!form.value.hourly_rate) return '';
    return new Intl.NumberFormat('vi-VN').format(form.value.hourly_rate);
});

function handleSalaryInput(e) {
    let val = e.target.value.replace(/\D/g, ''); // Chỉ giữ lại số
    form.value.hourly_rate = val ? parseInt(val) : 0;
}
</script>

<template>
    <AdminLayout>
        <template #title>Quản lý nhân viên</template>

        <div class="sl-wrap">
            <!-- Toolbar -->
            <div class="sl-toolbar">
                <div class="sl-search-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" class="sl-search-icon">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input v-model="searchQuery" class="sl-search" placeholder="Tìm theo tên, số điện thoại..." />
                </div>
                <button class="sl-add-btn" @click="openAdd">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Thêm nhân viên
                </button>
            </div>

            <!-- Table -->
            <div class="sl-table-wrap">
                <div v-if="loading" class="sl-loading">
                    <div class="sl-spinner"></div>
                </div>
                <table v-else class="sl-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">STT</th>
                            <th>Nhân viên</th>
                            <th>Số điện thoại</th>
                            <th>Vai trò</th>
                            <th>Lương theo giờ</th>
                            <th>Ghi chú</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="filteredStaff().length === 0">
                            <td colspan="7" class="sl-empty">Không có nhân viên nào</td>
                        </tr>
                        <tr v-for="(s, idx) in filteredStaff()" :key="s.id" class="sl-row">
                            <td class="sl-stt">{{ idx + 1 }}</td>
                            <td>
                                <div class="sl-staff-info">
                                    <div class="sl-avatar">
                                        <img v-if="s.image_url" :src="s.image_url" :alt="s.name" />
                                        <span v-else>{{ s.name?.charAt(0)?.toUpperCase() }}</span>
                                    </div>
                                    <span class="sl-staff-name">{{ s.name }}</span>
                                </div>
                            </td>
                            <td class="sl-phone">{{ s.phone }}</td>
                            <td>
                                <span class="sl-role-badge" :class="s.role">
                                    {{ s.role === 'admin' ? 'Admin' : 'Nhân viên' }}
                                </span>
                            </td>
                            <td class="sl-salary">{{ s.hourly_rate > 0 ? new Intl.NumberFormat('vi-VN').format(s.hourly_rate) + ' ₫' : '—' }}</td>
                            <td class="sl-note">{{ s.note || '—' }}</td>
                            <td>
                                <div class="sl-actions">
                                    <button class="sl-btn-edit" title="Sửa" @click="openEdit(s)">
                                        <Pencil :size="16" />
                                    </button>
                                    <button class="sl-btn-del" title="Xóa" @click="deleteStaff(s.id)">
                                        <Trash2 :size="16" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="sl-overlay" @click.self="showModal = false">
                <div class="sl-modal">
                    <div class="sl-modal-header">
                        <h3>{{ editMode ? 'Chỉnh sửa nhân viên' : 'Thêm nhân viên mới' }}</h3>
                        <button class="sl-modal-close" @click="showModal = false">✕</button>
                    </div>

                    <div class="sl-modal-body">
                        <!-- Avatar preview -->
                        <div class="sl-avatar-upload">
                            <div class="sl-avatar-preview">
                                <img v-if="previewImg" :src="previewImg" alt="preview" />
                                <span v-else>{{ form.name?.charAt(0)?.toUpperCase() || '?' }}</span>
                            </div>
                            <label class="sl-avatar-label">
                                <input type="file" accept="image/*" @change="handleImage" hidden />
                                Chọn ảnh
                            </label>
                        </div>

                        <div class="sl-form-grid">
                            <div class="sl-field">
                                <label>Họ tên <span class="req">*</span></label>
                                <input v-model="form.name" type="text" placeholder="Nguyễn Văn A" />
                            </div>
                            <div class="sl-field">
                                <label>Số điện thoại <span class="req">*</span></label>
                                <input v-model="form.phone" type="tel" placeholder="09xx xxx xxx" />
                            </div>
                            <div class="sl-field">
                                <label>Mật khẩu {{ editMode ? '(để trống nếu không đổi)' : '*' }}</label>
                                <input v-model="form.password" type="password" placeholder="Tối thiểu 6 ký tự" />
                            </div>
                            <div class="sl-field">
                                <label>Vai trò <span class="req">*</span></label>
                                <select v-model="form.role">
                                    <option value="staff">Nhân viên</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="sl-field">
                                <label>Lương theo giờ (VND)</label>
                                <input 
                                    :value="displaySalary" 
                                    @input="handleSalaryInput"
                                    type="text" 
                                    placeholder="25.000 ₫" 
                                />
                            </div>
                            <div class="sl-field sl-field--full">
                                <label>Ghi chú</label>
                                <textarea v-model="form.note" placeholder="Ghi chú thêm..." rows="2"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="sl-modal-footer">
                        <button class="sl-btn-cancel" @click="showModal = false">Hủy</button>
                        <button class="sl-btn-save" @click="submitForm" :disabled="submitting">
                            <span v-if="submitting" class="sl-mini-spinner"></span>
                            {{ editMode ? 'Cập nhật' : 'Thêm mới' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>

<style scoped>
.sl-wrap { max-width: 1200px; margin: 0 auto; }

.sl-toolbar {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.sl-search-wrap {
    position: relative;
    flex: 1;
    min-width: 200px;
}

.sl-search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #aaa;
    pointer-events: none;
}

.sl-search {
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
.sl-search:focus { border-color: #2D4F1E; }

.sl-add-btn {
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
    font-family: 'Inter', sans-serif;
}
.sl-add-btn:hover { background: linear-gradient(135deg, #1f3815, #3a6428); transform: translateY(-1px); }

.sl-table-wrap {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}

.sl-loading {
    display: flex;
    justify-content: center;
    padding: 60px;
}

.sl-spinner {
    width: 36px; height: 36px;
    border: 3px solid #e0e6ed;
    border-top-color: #2D4F1E;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.sl-table-wrap { overflow-x: auto; width: 100%; }
.sl-table { width: 100%; border-collapse: collapse; min-width: 800px; }
.sl-table th {
    padding: 14px 20px;
    font-size: 0.78rem;
    font-weight: 600;
    color: #888;
    text-align: left;
    background: #fafbfc;
    border-bottom: 1px solid #f0f0f0;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    white-space: nowrap;
}

.sl-row { border-bottom: 1px solid #f5f5f5; transition: background 0.15s; }
.sl-row:last-child { border-bottom: none; }
.sl-row:hover { background: #fafbfc; }
.sl-row td { padding: 14px 20px; font-size: 0.88rem; color: #333; vertical-align: middle; white-space: nowrap; }

.sl-staff-info { display: flex; align-items: center; gap: 12px; }

.sl-avatar {
    width: 38px; height: 38px;
    border-radius: 12px;
    background: linear-gradient(135deg, #2D4F1E, #4a7c2f);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.9rem; font-weight: 700; color: white; flex-shrink: 0;
    overflow: hidden;
}
.sl-avatar img { width: 100%; height: 100%; object-fit: cover; }
.sl-stt { color: #aaa; font-weight: 600; width: 60px; }
.sl-staff-name { font-weight: 600; }
.sl-phone { color: #666; }
.sl-salary { font-weight: 600; color: #2D4F1E; }
.sl-note { color: #888; font-size: 0.82rem; max-width: 160px; }

.sl-role-badge {
    font-size: 0.78rem; font-weight: 600;
    padding: 4px 10px; border-radius: 100px;
    white-space: nowrap;
}
.sl-role-badge.admin { background: rgba(45,79,30,0.1); color: #2D4F1E; }
.sl-role-badge.staff { background: rgba(59,130,246,0.1); color: #2563eb; }

.sl-actions { display: flex; gap: 8px; }
.sl-btn-edit, .sl-btn-del {
    width: 34px; height: 34px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 10px; cursor: pointer; transition: all 0.2s;
}
.sl-btn-edit {
    background: rgba(45,79,30,0.08); color: #2D4F1E;
    border: 1px solid rgba(45,79,30,0.15);
}
.sl-btn-edit:hover { background: #2D4F1E; color: white; }

.sl-btn-del {
    background: rgba(239,68,68,0.08); color: #dc2626;
    border: 1px solid rgba(239,68,68,0.15);
}
.sl-btn-del:hover { background: #dc2626; color: white; }

.sl-empty { text-align: center; padding: 48px; color: #aaa; }

/* Modal */
.sl-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.5);
    display: flex; align-items: center; justify-content: center;
    z-index: 1000; padding: 20px;
    backdrop-filter: blur(4px);
}

.sl-modal {
    background: white; border-radius: 24px;
    width: 100%; max-width: 580px;
    max-height: 90vh; overflow-y: auto;
    box-shadow: 0 24px 60px rgba(0,0,0,0.2);
}

.sl-modal-header {
    padding: 24px 28px 20px;
    display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid #f0f0f0;
}

.sl-modal-header h3 { margin: 0; font-size: 1.15rem; font-weight: 700; color: #1a1a2e; }
.sl-modal-close {
    background: #f5f5f5; border: none; border-radius: 8px;
    width: 32px; height: 32px; cursor: pointer;
    font-size: 0.9rem; color: #888; transition: all 0.2s;
}
.sl-modal-close:hover { background: #ffe4e4; color: #dc2626; }

.sl-modal-body { padding: 24px 28px; }

.sl-avatar-upload {
    display: flex; align-items: center; gap: 16px; margin-bottom: 24px;
}

.sl-avatar-preview {
    width: 64px; height: 64px; border-radius: 20px;
    background: linear-gradient(135deg, #2D4F1E, #4a7c2f);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; font-weight: 800; color: white;
    overflow: hidden; flex-shrink: 0;
}
.sl-avatar-preview img { width: 100%; height: 100%; object-fit: cover; }

.sl-avatar-label {
    padding: 8px 16px; border-radius: 10px;
    border: 1.5px dashed #d0d7de; background: #f8fafb;
    cursor: pointer; font-size: 0.85rem; font-weight: 600; color: #555;
    transition: all 0.2s;
}
.sl-avatar-label:hover { border-color: #2D4F1E; color: #2D4F1E; }

.sl-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.sl-field { display: flex; flex-direction: column; gap: 6px; }
.sl-field--full { grid-column: 1 / -1; }

.sl-field label { font-size: 0.82rem; font-weight: 600; color: #555; }
.req { color: #dc2626; }

.sl-field input,
.sl-field select,
.sl-field textarea {
    padding: 10px 14px;
    border: 1.5px solid #e0e6ed;
    border-radius: 10px;
    font-size: 0.9rem;
    color: #333;
    background: #fafbfc;
    outline: none;
    transition: border-color 0.2s;
    font-family: 'Inter', sans-serif;
    width: 100%;
    box-sizing: border-box;
}
.sl-field input:focus,
.sl-field select:focus,
.sl-field textarea:focus { border-color: #2D4F1E; background: white; }
.sl-field textarea { resize: vertical; }

.sl-modal-footer {
    padding: 20px 28px 24px;
    display: flex; gap: 12px; justify-content: flex-end;
    border-top: 1px solid #f0f0f0;
}

.sl-btn-cancel {
    padding: 10px 20px; border-radius: 10px;
    background: #f5f5f5; border: none; color: #666;
    font-size: 0.9rem; font-weight: 600; cursor: pointer;
    transition: background 0.2s; font-family: 'Inter', sans-serif;
}
.sl-btn-cancel:hover { background: #e8e8e8; }

.sl-btn-save {
    padding: 10px 24px; border-radius: 10px;
    background: linear-gradient(135deg, #2D4F1E, #4a7c2f);
    border: none; color: white;
    font-size: 0.9rem; font-weight: 700; cursor: pointer;
    display: flex; align-items: center; gap: 8px;
    transition: all 0.2s; font-family: 'Inter', sans-serif;
}
.sl-btn-save:hover:not(:disabled) { background: linear-gradient(135deg, #1f3815, #3a6428); }
.sl-btn-save:disabled { opacity: 0.6; cursor: not-allowed; }

.sl-mini-spinner {
    width: 14px; height: 14px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    display: inline-block;
}

@media (max-width: 768px) {
    .sl-toolbar {
        flex-direction: column;
        align-items: stretch;
    }

    .sl-search-wrap {
        min-width: 0;
    }

    .sl-add-btn {
        justify-content: center;
    }

    .sl-modal {
        max-height: 85vh;
        border-radius: 20px 20px 0 0;
        margin-bottom: 0;
        position: fixed;
        bottom: 0;
    }

    .sl-table-wrap {
        margin: 0 -16px;
        padding: 0 16px;
        width: calc(100% + 32px);
        display: block;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .sl-overlay {
        align-items: flex-end;
        padding: 0;
    }
}

@media (max-width: 600px) {
    .sl-form-grid { grid-template-columns: 1fr; }
    .sl-table th, .sl-row td { padding: 10px 12px; }
}
</style>

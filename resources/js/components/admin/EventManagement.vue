<template>
  <AdminLayout>
    <template #title>
      <div v-if="showModal" class="am-breadcrumb">
        <span @click="showModal = false">Quản lý sự kiện</span>
        <span style="color: #cbd5e1;">/</span>
        <span class="active">{{ form.id ? 'Sửa sự kiện' : 'Thêm sự kiện' }}</span>
      </div>
      <template v-else>Quản lý sự kiện</template>
    </template>
    
    <div v-if="!showModal" class="am-list-wrap">
      <div class="am-top-bar">
        <div class="am-search-box"></div>
        <div class="am-actions">
          <input type="file" ref="zipInput" accept=".zip" style="display: none;" @change="handleZipUpload">
          <button @click="$refs.zipInput.click()" class="am-btn-primary" style="background: linear-gradient(135deg, #10b981, #059669);" :disabled="isUploadingZip">
            <span v-if="isUploadingZip" class="am-spinner" style="width: 16px; height: 16px; margin-right: 8px; border-width: 2px;"></span>
            {{ isUploadingZip ? 'Đang xử lý...' : 'Upload ZIP' }}
          </button>
          <button @click="openModal()" class="am-btn-primary">
            Thêm sự kiện mới
          </button>
        </div>
      </div>

      <!-- Data Table -->
      <div class="am-table-card">
        <table class="am-table">
          <thead>
            <tr>
              <th>TIÊU ĐỀ</th>
              <th>THỜI GIAN</th>
              <th>TRẠNG THÁI</th>
              <th>HÀNH ĐỘNG</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="event in events" :key="event.id" class="am-row">
              <td>
                <div class="am-prod-name">{{ event.title }}</div>
                <div class="am-prod-sku">{{ event.slug }}</div>
              </td>
              <td style="color: #64748b;">{{ formatDate(event.start_time) }} - <br/> {{ formatDate(event.end_time) }}</td>
              <td>
                <span class="am-status-badge" :class="event.status === 'active' ? 'status-active' : (event.status === 'completed' ? 'status-inactive' : 'status-inactive')">
                  <span class="dot"></span> {{ event.status === 'active' ? 'Đang diễn ra' : (event.status === 'completed' ? 'Đã kết thúc' : 'Nháp') }}
                </span>
              </td>
              <td>
                <div class="am-row-actions">
                  <button class="am-btn-view" title="Xem" @click="viewEvent(event.slug)">
                    Xem
                  </button>
                  <button class="am-btn-edit" title="Sửa" @click="openModal(event)">
                    Sửa
                  </button>
                  <button class="am-btn-del" title="Xóa" @click="deleteEvent(event.id)">
                    Xóa
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="events.length === 0">
              <td colspan="4" style="text-align: center; color: #94a3b8; font-style: italic;">Chưa có sự kiện nào.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Form (Detail View) -->
    <div v-else class="am-detail-wrap">
      <div class="am-detail-header">
        <h2>{{ form.id ? 'Sửa sự kiện' : 'Thêm sự kiện' }}</h2>
        <div class="am-detail-actions">
          <button class="am-btn-white" @click="closeModal">Hủy</button>
          <button class="am-btn-green" @click="saveEvent">Lưu sự kiện</button>
        </div>
      </div>

      <div class="am-detail-grid">
        <div class="am-detail-left">
          <div class="am-card">
            <div class="am-card-title">Thông tin sự kiện</div>
            <div class="am-form-grid">
              <div class="am-field full">
                <label>Tiêu đề <span class="req">*</span></label>
                <input type="text" v-model="form.title" @input="generateSlug" required>
              </div>
              
              <div class="am-field full">
                <label>Đường dẫn (Slug)</label>
                <input type="text" v-model="form.slug" required>
                <span style="font-size: 0.8rem; color: #64748b; font-style: italic;">* Hãy upload source code của Landing Page vào thư mục <strong>public/events/{{ form.slug }}/</strong></span>
              </div>
              
              <div class="am-field">
                <label>Bắt đầu</label>
                <input type="datetime-local" v-model="form.start_time" required>
              </div>
              <div class="am-field">
                <label>Kết thúc</label>
                <input type="datetime-local" v-model="form.end_time" required>
              </div>

              <div class="am-field full">
                <label>Trạng thái</label>
                <select v-model="form.status">
                  <option value="draft">Nháp (Draft)</option>
                  <option value="active">Đang diễn ra (Active)</option>
                  <option value="completed">Đã kết thúc (Completed)</option>
                </select>
              </div>

              <div class="am-field full">
                <label>Mô tả ngắn</label>
                <textarea v-model="form.short_description" rows="2"></textarea>
              </div>

              <div class="am-field full">
                <label>Nội dung (HTML) - Không bắt buộc</label>
                <textarea v-model="form.content" rows="4"></textarea>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AdminLayout from './AdminLayout.vue';
import { useAdminAuth } from '../../composables/useAdminAuth';
import axios from 'axios';
import { toast } from 'vue3-toastify';

const { authHeader } = useAdminAuth();

const events = ref([]);
const showModal = ref(false);
const isUploadingZip = ref(false);
const zipInput = ref(null);
const form = ref({
  id: null,
  title: '',
  slug: '',
  short_description: '',
  content: '',
  image_url: '',
  start_time: '',
  end_time: '',
  status: 'draft'
});

const formatDate = (dateStr) => {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleString('vi-VN');
};

const fetchEvents = async () => {
  try {
    const response = await axios.get('/api/admin/events', { headers: authHeader() });
    events.value = response.data;
  } catch (error) {
    toast.error('Lỗi tải danh sách sự kiện');
  }
};

const openModal = (event = null) => {
  if (event) {
    form.value = { ...event };
    if (form.value.start_time) {
      form.value.start_time = new Date(form.value.start_time).toISOString().slice(0, 16);
    }
    if (form.value.end_time) {
      form.value.end_time = new Date(form.value.end_time).toISOString().slice(0, 16);
    }
  } else {
    form.value = {
      id: null,
      title: '',
      slug: '',
      short_description: '',
      content: '',
      image_url: '',
      start_time: '',
      end_time: '',
      status: 'draft'
    };
  }
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
};

const viewEvent = (slug) => {
  window.open(`/events/${slug}`, '_blank');
};

const generateSlug = () => {
  if (!form.value.id) {
    form.value.slug = form.value.title.toLowerCase().replace(/ /g, '-').replace(/[^\w-]+/g, '');
  }
};

const saveEvent = async () => {
  try {
    if (form.value.id) {
      await axios.put(`/api/admin/events/${form.value.id}`, form.value, { headers: authHeader() });
      toast.success('Cập nhật sự kiện thành công');
    } else {
      await axios.post('/api/admin/events', form.value, { headers: authHeader() });
      toast.success('Thêm sự kiện thành công');
    }
    closeModal();
    fetchEvents();
  } catch (error) {
    toast.error(error.response?.data?.message || 'Lỗi lưu sự kiện');
  }
};

const deleteEvent = async (id) => {
  if (confirm('Bạn có chắc muốn xóa sự kiện này?')) {
    try {
      await axios.delete(`/api/admin/events/${id}`, { headers: authHeader() });
      toast.success('Xóa sự kiện thành công');
      fetchEvents();
    } catch (error) {
      toast.error('Lỗi xóa sự kiện');
    }
  }
};

const handleZipUpload = async (event) => {
  const file = event.target.files[0];
  if (!file) return;

  if (!file.name.endsWith('.zip')) {
    toast.error('Vui lòng chọn file .zip');
    return;
  }

  const formData = new FormData();
  formData.append('zip_file', file);

  isUploadingZip.value = true;
  try {
    const response = await axios.post('/api/admin/events/upload-zip', formData, {
      headers: {
        ...authHeader(),
        'Content-Type': 'multipart/form-data'
      }
    });
    
    toast.success('Upload và giải nén thành công!');
    fetchEvents();
    
    // Open modal to edit the newly created event
    openModal(response.data);
    
  } catch (error) {
    toast.error(error.response?.data?.message || 'Lỗi xử lý file ZIP');
  } finally {
    isUploadingZip.value = false;
    if (zipInput.value) {
      zipInput.value.value = ''; // reset input
    }
  }
};

onMounted(() => {
  fetchEvents();
});
</script>

<style scoped>
* { box-sizing: border-box; }
.am-breadcrumb { display: flex; align-items: center; gap: 8px; font-size: 0.9rem; color: #64748b; }
.am-breadcrumb span { cursor: pointer; }
.am-breadcrumb span:hover { color: #1e293b; }
.am-breadcrumb .active { color: #2D4F1E; font-weight: 700; }

/* List View */
.am-list-wrap { width: 100%; max-width: 1200px; margin: 0 auto; overflow-x: hidden; }
.am-top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 16px; }
.am-search-box { position: relative; flex: 1; max-width: 400px; }
.am-actions { display: flex; gap: 12px; }
.am-btn-primary { 
    display: flex; align-items: center; gap: 8px; padding: 11px 20px; border-radius: 12px; 
    background: linear-gradient(135deg, #2D4F1E, #4a7c2f); color: white; border: none; 
    font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: all 0.2s; white-space: nowrap;
}
.am-btn-primary:hover { background: linear-gradient(135deg, #1f3815, #3a6428); transform: translateY(-1px); }

.am-table-card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow-x: auto; }
.am-table { width: 100%; border-collapse: collapse; min-width: 900px; }
.am-table th { background: #f8fafc; padding: 16px 20px; text-align: left; font-size: 0.75rem; color: #64748b; font-weight: 700; border-bottom: 1px solid #e2e8f0; }
.am-table td { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }

.am-prod-name { font-weight: 700; color: #1e293b; font-size: 1rem; }
.am-prod-sku { font-size: 0.8rem; color: #94a3b8; }

.am-status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 100px; font-size: 0.85rem; font-weight: 600; }
.status-active { background: #ecfdf5; color: #059669; white-space: nowrap; }
.status-active .dot { width: 6px; height: 6px; border-radius: 50%; background: #10b981; }
.status-inactive { background: #f1f5f9; color: #64748b; white-space: nowrap; }
.status-inactive .dot { width: 6px; height: 6px; border-radius: 50%; background: #94a3b8; }

.am-row-actions { display: flex; gap: 8px; }
.am-btn-view, .am-btn-edit, .am-btn-del {
    padding: 6px 12px;
    display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 600;
    border-radius: 10px; cursor: pointer; transition: all 0.2s;
}
.am-btn-view { background: rgba(59,130,246,0.08); color: #3b82f6; border: 1px solid rgba(59,130,246,0.15); }
.am-btn-view:hover { background: #3b82f6; color: white; }
.am-btn-edit { background: rgba(45,79,30,0.08); color: #2D4F1E; border: 1px solid rgba(45,79,30,0.15); }
.am-btn-edit:hover { background: #2D4F1E; color: white; }
.am-btn-del { background: rgba(239,68,68,0.08); color: #dc2626; border: 1px solid rgba(239,68,68,0.15); }
.am-btn-del:hover { background: #dc2626; color: white; }

/* Detail View */
.am-detail-wrap { width: 100%; max-width: 1200px; margin: 0 auto; overflow-x: hidden; }
.am-detail-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.am-detail-header h2 { font-size: 1.75rem; font-weight: 800; color: #1a3a1b; margin: 0; }
.am-detail-actions { display: flex; gap: 12px; }
.am-btn-white { padding: 10px 24px; border-radius: 12px; border: 1.5px solid #e2e8f0; background: white; font-weight: 700; color: #475569; cursor: pointer; transition: all 0.2s; }
.am-btn-white:hover { background: #f8fafc; border-color: #cbd5e1; }
.am-btn-green { padding: 10px 24px; border-radius: 12px; background: linear-gradient(135deg, #2D4F1E, #4a7c2f); color: white; border: none; font-weight: 700; cursor: pointer; transition: all 0.2s; }
.am-btn-green:hover:not(:disabled) { background: linear-gradient(135deg, #1f3815, #3a6428); transform: translateY(-1px); }

.am-detail-grid { display: grid; grid-template-columns: 1fr; gap: 24px; }
.am-card { background: white; border-radius: 20px; padding: 24px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px; }
.am-card-title { font-size: 1.1rem; font-weight: 700; color: #1a3a1b; margin-bottom: 20px; }

.am-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.am-field { display: flex; flex-direction: column; gap: 8px; }
.am-field.full { grid-column: 1 / -1; }
.am-field label { font-size: 0.9rem; font-weight: 600; color: #475569; }
.am-field label .req { color: #dc2626; }
.am-field input, .am-field select, .am-field textarea { padding: 12px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; background: #f8fafc; font-size: 0.95rem; outline: none; transition: 0.2s; }
.am-field input:focus, .am-field select:focus, .am-field textarea:focus { border-color: #1a3a1b; background: white; }

/* Spinner */
.am-spinner { width: 30px; height: 30px; border: 3px solid rgba(0,0,0,0.1); border-top-color: #1a3a1b; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 768px) {
    .am-top-bar { flex-direction: column; align-items: stretch; }
    .am-form-grid { grid-template-columns: 1fr; }
    .am-detail-header { flex-direction: column; align-items: stretch; gap: 16px; }
    .am-detail-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
}
</style>

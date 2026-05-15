<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import AdminLayout from './AdminLayout.vue';
import { useAdminAuth } from '../../composables/useAdminAuth';
import { 
    Search, 
    Plus, 
    Coffee, 
    Pencil, 
    Trash2, 
    Upload, 
    X, 
    CheckCircle2, 
    ChevronLeft, 
    ChevronRight, 
    Tag,
    Info,
    AlertCircle,
    Save,
    ChevronDown,
    Filter,
    Download
} from 'lucide-vue-next';
import { toast } from 'vue3-toastify';
import axios from 'axios';

const { authHeader } = useAdminAuth();

// View State: 'list' or 'detail'
const view = ref('list');

// List State
const items = ref([]);
const categories = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const selectedCategory = ref('');
const pagination = ref({
    current_page: 1,
    last_page: 1,
    total: 0,
    from: 0,
    to: 0,
    per_page: 5
});

// Detail State
const editingItem = ref(null);
const form = ref({
    sku: '',
    name: '',
    category: '',
    price: 0,
    description: '',
    image: '',
    status: true,
    tags: []
});
const uploading = ref(false);
const customTag = ref('');

function addCustomTag() {
    const val = customTag.value.trim();
    if (val) {
        form.value.tags = [val];
    }
    customTag.value = '';
}

// Confirm Modal
const showConfirm = ref(false);
const confirmAction = ref(null);
const confirmTitle = ref('');
const confirmMsg = ref('');

function triggerConfirm(title, msg, action) {
    confirmTitle.value = title;
    confirmMsg.value = msg;
    confirmAction.value = action;
    showConfirm.value = true;
}

async function fetchMenu(page = 1) {
    loading.value = true;
    try {
        const url = `/api/admin/menu?page=${page}&search=${encodeURIComponent(searchQuery.value)}&category=${selectedCategory.value}`;
        const res = await axios.get(url, { headers: authHeader() });
        items.value = res.data.items.data;
        pagination.value = {
            current_page: res.data.items.current_page,
            last_page: res.data.items.last_page,
            total: res.data.items.total,
            from: res.data.items.from,
            to: res.data.items.to,
            per_page: res.data.items.per_page
        };
        categories.value = res.data.categories;
    } catch (e) {
        toast.error('Lỗi khi tải dữ liệu menu');
    } finally {
        loading.value = false;
    }
}

onMounted(fetchMenu);

// Watchers for search/filter
watch([searchQuery, selectedCategory], () => {
    fetchMenu(1);
});

function openAdd() {
    editingItem.value = null;
    form.value = {
        sku: '',
        name: '',
        category: '',
        price: 0,
        description: '',
        image: '',
        status: true,
        tags: []
    };
    view.value = 'detail';
}

function openEdit(item) {
    editingItem.value = item;
    form.value = {
        sku: item.sku || '',
        name: item.name || '',
        category: item.category || '',
        price: item.price || 0,
        description: item.description || '',
        image: item.image || '',
        status: item.status !== undefined ? item.status : true,
        tags: Array.isArray(item.tags) ? [...item.tags] : []
    };
    view.value = 'detail';
}

async function saveItem() {
    if (!form.value.name || !form.value.category) {
        toast.warning('Vui lòng điền tên và danh mục');
        return;
    }
    loading.value = true;
    try {
        if (editingItem.value) {
            await axios.put(`/api/admin/menu/${editingItem.value.id}`, form.value, { headers: authHeader() });
            toast.success('Cập nhật thành công');
        } else {
            await axios.post('/api/admin/menu', form.value, { headers: authHeader() });
            toast.success('Thêm sản phẩm thành công');
        }
        view.value = 'list';
        fetchMenu(pagination.value.current_page);
    } catch (e) {
        const msg = e.response?.data?.message || 'Lỗi khi lưu sản phẩm';
        toast.error(msg);
    } finally {
        loading.value = false;
    }
}

async function deleteItem(id) {
    triggerConfirm(
        'Xác nhận xoá', 
        'Bạn có chắc chắn muốn xoá sản phẩm này? Hành động này không thể hoàn tác.',
        async () => {
            try {
                await axios.delete(`/api/admin/menu/${id}`, { headers: authHeader() });
                toast.success('Xoá thành công');
                if (view.value === 'detail') view.value = 'list';
                fetchMenu(pagination.value.current_page);
            } catch (e) {
                toast.error('Lỗi khi xoá sản phẩm');
            }
        }
    );
}

async function handleImageUpload(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    uploading.value = true;
    const formData = new FormData();
    formData.append('image', file);

    try {
        const res = await axios.post('/api/admin/menu/upload-image', formData, {
            headers: { ...authHeader(), 'Content-Type': 'multipart/form-data' }
        });
        form.value.image = res.data.url;
        toast.success('Tải ảnh thành công');
    } catch (e) {
        toast.error('Lỗi khi tải ảnh');
    } finally {
        uploading.value = false;
    }
}



function toggleTag(tag) {
    if (form.value.tags.includes(tag)) {
        form.value.tags = [];
    } else {
        form.value.tags = [tag];
    }
}

const formatCurrency = (val) => {
    return new Intl.NumberFormat('vi-VN').format(val) + 'đ';
};

const tagSuggestions = ['Best Seller', 'New', 'Ưu đãi', 'Giới hạn', 'Bán chạy', 'Món mới', 'Khuyến mãi'];

const availableSuggestions = computed(() => {
    return tagSuggestions;
});

const displayPrice = computed({
    get: () => {
        if (form.value.price === 0 || form.value.price === '') return '';
        return new Intl.NumberFormat('vi-VN').format(form.value.price).replace(/,/g, '.');
    },
    set: (val) => {
        const num = parseInt(val.replace(/\D/g, '')) || 0;
        form.value.price = num;
    }
});

</script>

<template>
    <AdminLayout>
        <template #title>
            <div v-if="view === 'detail'" class="am-breadcrumb">
                <span @click="view = 'list'">Quản lý thực đơn</span>
                <ChevronRight :size="14" />
                <span class="active">{{ editingItem ? 'Chỉnh sửa sản phẩm' : 'Sản phẩm mới' }}</span>
            </div>
            <template v-else>Quản lý Menu</template>
        </template>

        <!-- LIST VIEW -->
        <div v-if="view === 'list'" class="am-list-wrap">
            <div class="am-top-bar">
                <div class="am-search-box">
                    <Search :size="18" class="search-icon" />
                    <input v-model="searchQuery" type="text" placeholder="Tìm kiếm sản phẩm..." />
                </div>
                <div class="am-actions">
                    <button class="am-btn-primary" @click="openAdd">
                        <Plus :size="18" /> Thêm sản phẩm mới
                    </button>
                </div>
            </div>

            <div class="am-table-card">
                <table class="am-table">
                    <thead>
                        <tr>
                            <th>SẢN PHẨM</th>
                            <th>DANH MỤC</th>
                            <th>GIÁ</th>
                            <th>THẺ</th>
                            <th>TRẠNG THÁI</th>
                            <th>THAO TÁC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in items" :key="item.id" class="am-row">
                            <td>
                                <div class="am-prod-cell">
                                    <div class="am-prod-img">
                                        <img v-if="item.image" :src="item.image" />
                                        <Coffee v-else :size="20" class="text-muted" />
                                    </div>
                                    <div class="am-prod-info">
                                        <div class="am-prod-name text-truncate" style="max-width: 200px;">{{ item.name }}</div>
                                        <div class="am-prod-sku">SKU: {{ item.sku || '---' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ item.category }}</td>
                            <td class="font-bold">{{ formatCurrency(item.price) }}</td>
                            <td>
                                <div class="am-tags-list">
                                    <span v-for="tag in item.tags" :key="tag" class="am-tag-badge" 
                                          :class="tag.toLowerCase().replace(' ', '-')">
                                        {{ tag.toUpperCase() }}
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="am-status-badge" :class="item.status ? 'status-active' : 'status-inactive'">
                                    <span class="dot"></span> {{ item.status ? 'Đang bán' : 'Ngừng bán' }}
                                </span>
                            </td>
                            <td>
                                <div class="am-row-actions">
                                    <button class="am-btn-edit" title="Sửa" @click="openEdit(item)">
                                        <Pencil :size="16" />
                                    </button>
                                    <button class="am-btn-del" title="Xóa" @click="deleteItem(item.id)">
                                        <Trash2 :size="16" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="am-pagination">
                    <div class="am-pagi-info">Hiển thị {{ pagination.from }}-{{ pagination.to }} của {{ pagination.total }} sản phẩm</div>
                    <div class="am-pagi-btns">
                        <button class="am-pagi-btn" :disabled="pagination.current_page === 1" @click="fetchMenu(pagination.current_page - 1)">
                            <ChevronLeft :size="18"/>
                        </button>
                        <button v-for="p in pagination.last_page" :key="p" 
                                class="am-pagi-btn" :class="{ active: p === pagination.current_page }"
                                @click="fetchMenu(p)">
                            {{ p }}
                        </button>
                        <button class="am-pagi-btn" :disabled="pagination.current_page === pagination.last_page" @click="fetchMenu(pagination.current_page + 1)">
                            <ChevronRight :size="18"/>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- DETAIL VIEW -->
        <div v-else class="am-detail-wrap">
            <div class="am-detail-header">
                <h2>Thông tin chi tiết</h2>
                <div class="am-detail-actions">
                    <button class="am-btn-white" @click="view = 'list'">Hủy</button>
                    <button class="am-btn-green" @click="saveItem">
                        <Save :size="18" /> {{ editingItem ? 'Lưu thay đổi' : 'Tạo sản phẩm' }}
                    </button>
                </div>
            </div>

            <div class="am-detail-grid">
                <!-- Left Column -->
                <div class="am-detail-left">
                    <div class="am-card">
                        <div class="am-card-title"><Info :size="18"/> Thông tin cơ bản</div>
                        <div class="am-form-grid">
                            <div class="am-field full">
                                <label>Tên sản phẩm <span class="req">*</span></label>
                                <input v-model="form.name" type="text" placeholder="Ví dụ: Phở Bò Tái Lăn" />
                            </div>
                            <div class="am-field">
                                <label>SKU / Mã sản phẩm</label>
                                <input v-model="form.sku" type="text" readonly placeholder="Tự động phát sinh..." class="bg-gray-light" />
                            </div>
                            <div class="am-field">
                                <label>Danh mục</label>
                                <select v-model="form.category">
                                    <option value="">Chọn danh mục</option>
                                    <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                                </select>
                            </div>
                            <div class="am-field full">
                                <label>Mô tả sản phẩm</label>
                                <textarea v-model="form.description" rows="4" placeholder="Nhập mô tả sản phẩm"></textarea>
                            </div>
                            <div class="am-field full">
                                <label>Giá bán (VNĐ)</label>
                                <div class="am-price-input">
                                    <span>₫</span>
                                    <input v-model="displayPrice" type="text" placeholder="0" />
                                </div>
                            </div>
                        </div>
                    </div>



                    <div class="am-card">
                        <div class="am-card-title">Hình ảnh sản phẩm</div>
                        <div class="am-image-upload-area">
                            <div v-if="form.image" class="am-preview-wrap">
                                <img :src="form.image" />
                                <button class="am-remove-img" @click="form.image = ''"><X :size="14"/></button>
                            </div>
                            <label v-else class="am-upload-box">
                                <input type="file" hidden @change="handleImageUpload" accept="image/*" />
                                <div v-if="uploading" class="am-spinner"></div>
                                <template v-else>
                                    <Upload :size="32" />
                                    <strong>Tải hình ảnh lên</strong>
                                    <span>Hỗ trợ JPG, PNG (Tối đa 5MB)</span>
                                </template>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="am-detail-right">
                    <div class="am-card">
                        <div class="am-card-title">Trạng thái & Phân loại</div>
                        <div class="am-field">
                            <label>Trạng thái kinh doanh</label>
                            <div class="am-radio-group">
                                <label class="am-radio" :class="{ 'active-green': form.status }">
                                    <input type="radio" :value="true" v-model="form.status" />
                                    <CheckCircle2 :size="16" /> Đang bán
                                </label>
                                <label class="am-radio" :class="{ 'active-red': !form.status }">
                                    <input type="radio" :value="false" v-model="form.status" />
                                    <X :size="16" /> Ngừng bán
                                </label>
                            </div>
                        </div>
                        <div class="am-field">
                            <label>Gắn thẻ (Tags)</label>
                            <div class="am-tags-edit">
                                <span v-for="tag in form.tags" :key="tag" class="am-tag-removable">
                                    {{ tag }} <X :size="12" @click="toggleTag(tag)" />
                                </span>
                            </div>
                            <div class="am-tag-input-wrap">
                                <input v-model="customTag" type="text" placeholder="Thêm thẻ mới..." @keyup.enter="addCustomTag" />
                                <button @click="addCustomTag"><Plus :size="16"/></button>
                            </div>
                            <div class="am-tag-suggestions">
                                <span>GỢI Ý TỪ HỆ THỐNG</span>
                                <div class="am-sugg-list">
                                    <button v-for="t in availableSuggestions" :key="t" 
                                            @click="toggleTag(t)"
                                            class="am-sugg-btn" :class="{ active: form.tags.includes(t) }">
                                        {{ t }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>


                </div>
            </div>

            <div v-if="editingItem" class="am-detail-footer">
                <button class="am-btn-delete" @click="deleteItem(editingItem.id)">
                    <Trash2 :size="18" /> Xóa sản phẩm
                </button>
            </div>
        </div>

        <!-- Confirm Modal -->
        <Teleport to="body">
            <div v-if="showConfirm" class="am-modal-overlay" @click.self="showConfirm = false">
                <div class="am-confirm-modal">
                    <div class="am-confirm-icon">
                        <AlertCircle :size="32" />
                    </div>
                    <h3>{{ confirmTitle }}</h3>
                    <p>{{ confirmMsg }}</p>
                    <div class="am-confirm-footer">
                        <button class="am-btn-white" @click="showConfirm = false">Hủy</button>
                        <button class="am-btn-red" @click="() => { confirmAction(); showConfirm = false; }">Xác nhận</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>

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
.search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
.am-search-box input { width: 100%; padding: 12px 14px 12px 42px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; outline: none; }

.am-actions { display: flex; gap: 12px; }
.am-btn-primary { 
    display: flex; 
    align-items: center; 
    gap: 8px; 
    padding: 11px 20px; 
    border-radius: 12px; 
    background: linear-gradient(135deg, #2D4F1E, #4a7c2f); 
    color: white; 
    border: none; 
    font-size: 0.9rem;
    font-weight: 600; 
    cursor: pointer; 
    transition: all 0.2s;
    white-space: nowrap;
}
.am-btn-primary:hover { background: linear-gradient(135deg, #1f3815, #3a6428); transform: translateY(-1px); }

.am-table-card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow-x: auto; }
.am-table { width: 100%; border-collapse: collapse; min-width: 900px; }
.am-table th { background: #f8fafc; padding: 16px 20px; text-align: left; font-size: 0.75rem; color: #64748b; font-weight: 700; border-bottom: 1px solid #e2e8f0; }
.am-table td { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }

.am-prod-cell { display: flex; align-items: center; gap: 12px; }
.am-prod-img { width: 56px; height: 56px; border-radius: 12px; overflow: hidden; background: #f1f5f9; display: flex; align-items: center; justify-content: center; }
.am-prod-img img { width: 100%; height: 100%; object-fit: cover; }
.am-prod-name { font-weight: 700; color: #1e293b; font-size: 1rem; }
.am-prod-sku { font-size: 0.8rem; color: #94a3b8; }

.am-tags-list { display: flex; flex-wrap: wrap; gap: 4px; max-width: 250px; }
.am-tag-badge { padding: 4px 10px; border-radius: 100px; font-size: 0.7rem; font-weight: 800; }
.best-seller { background: #dcfce7; color: #166534; }
.cay { background: #fee2e2; color: #991b1b; }
.new { background: #f1f5f9; color: #475569; }
.mon-chay { background: #fef9c3; color: #854d0e; }

.am-status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 100px; font-size: 0.85rem; font-weight: 600; }
.status-active { background: #ecfdf5; color: #059669; white-space: nowrap; }
.status-active .dot { width: 6px; height: 6px; border-radius: 50%; background: #10b981; }
.status-inactive { background: #f1f5f9; color: #64748b; white-space: nowrap; }
.status-inactive .dot { width: 6px; height: 6px; border-radius: 50%; background: #94a3b8; }

.am-row-actions { display: flex; gap: 8px; }
.am-btn-edit, .am-btn-del {
    width: 34px; height: 34px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 10px; cursor: pointer; transition: all 0.2s;
}
.am-btn-edit {
    background: rgba(45,79,30,0.08); color: #2D4F1E;
    border: 1px solid rgba(45,79,30,0.15);
}
.am-btn-edit:hover { background: #2D4F1E; color: white; }

.am-btn-del {
    background: rgba(239,68,68,0.08); color: #dc2626;
    border: 1px solid rgba(239,68,68,0.15);
}
.am-btn-del:hover { background: #dc2626; color: white; }

.am-pagination { padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e2e8f0; }
.am-pagi-info { font-size: 0.9rem; color: #64748b; }
.am-pagi-btns { display: flex; gap: 6px; }
.am-pagi-btn { width: 36px; height: 36px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; display: flex; align-items: center; justify-content: center; cursor: pointer; font-weight: 600; font-size: 0.9rem; }
.am-pagi-btn.active { background: #1a3a1b; color: white; border-color: #1a3a1b; }
.am-pagi-btn:disabled { opacity: 0.4; cursor: not-allowed; }

/* Detail View */
.am-detail-wrap { width: 100%; max-width: 1200px; margin: 0 auto; overflow-x: hidden; }
.am-detail-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.am-detail-header h2 { font-size: 1.75rem; font-weight: 800; color: #1a3a1b; margin: 0; }
.am-detail-actions { display: flex; gap: 12px; }
.am-btn-white { 
    padding: 10px 24px; 
    border-radius: 12px; 
    border: 1.5px solid #e2e8f0; 
    background: white; 
    font-weight: 700; 
    color: #475569; 
    cursor: pointer; 
    transition: all 0.2s;
}
.am-btn-white:hover { background: #f8fafc; border-color: #cbd5e1; }

.am-btn-green { 
    padding: 10px 24px; 
    border-radius: 12px; 
    background: linear-gradient(135deg, #2D4F1E, #4a7c2f); 
    color: white; 
    border: none; 
    font-weight: 700; 
    cursor: pointer; 
    display: flex; 
    align-items: center; 
    gap: 8px; 
    transition: all 0.2s;
}
.am-btn-green:hover:not(:disabled) { background: linear-gradient(135deg, #1f3815, #3a6428); transform: translateY(-1px); }
.am-btn-green:disabled { opacity: 0.6; cursor: not-allowed; }

.am-detail-grid { display: grid; grid-template-columns: 1fr 380px; gap: 24px; }
.am-card { background: white; border-radius: 20px; padding: 24px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px; }
.am-card-title { display: flex; align-items: center; gap: 10px; font-size: 1.1rem; font-weight: 700; color: #1a3a1b; margin-bottom: 20px; }

.am-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.am-field { display: flex; flex-direction: column; gap: 8px; }
.am-field.full { grid-column: 1 / -1; }
.am-field label { font-size: 0.9rem; font-weight: 600; color: #475569; }
.am-field label .req { color: #dc2626; }
.am-field input, .am-field select, .am-field textarea { padding: 12px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; background: #f8fafc; font-size: 0.95rem; outline: none; transition: 0.2s; }
.am-field input:focus, .am-field select:focus, .am-field textarea:focus { border-color: #1a3a1b; background: white; }
.bg-gray-light { background: #f1f5f9 !important; color: #94a3b8; cursor: not-allowed; }

.am-price-input { position: relative; }
.am-price-input span { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-weight: 700; color: #94a3b8; }
.am-price-input input { padding-left: 32px; width: 100%; }

.am-toggle-row { display: flex; justify-content: space-between; align-items: center; }
.am-toggle-info { display: flex; flex-direction: column; }
.am-toggle-info strong { font-size: 1rem; color: #1e293b; }
.am-toggle-info span { font-size: 0.85rem; color: #64748b; }

.am-switch { position: relative; display: inline-block; width: 50px; height: 26px; }
.am-switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #e2e8f0; transition: .4s; border-radius: 34px; }
.slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
input:checked + .slider { background-color: #1a3a1b; }
input:checked + .slider:before { transform: translateX(24px); }

.am-image-upload-area { display: flex; gap: 16px; flex-wrap: wrap; }
.am-upload-box { flex: 1; min-width: 200px; height: 160px; border: 2px dashed #e2e8f0; border-radius: 16px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; color: #94a3b8; transition: 0.2s; }
.am-upload-box:hover { border-color: #1a3a1b; background: #f0fdf4; color: #1a3a1b; }
.am-upload-box strong { color: #1a3a1b; margin-top: 12px; }
.am-upload-box span { font-size: 0.8rem; }

.am-preview-wrap { position: relative; width: 160px; height: 160px; border-radius: 16px; overflow: hidden; }
.am-preview-wrap img { width: 100%; height: 100%; object-fit: cover; }
.am-remove-img { position: absolute; top: 8px; right: 8px; width: 24px; height: 24px; border-radius: 50%; background: rgba(0,0,0,0.5); border: none; color: white; cursor: pointer; }

.am-image-item { width: 80px; height: 80px; border-radius: 12px; border: 1.5px solid #e2e8f0; display: flex; align-items: center; justify-content: center; color: #94a3b8; cursor: pointer; }

.am-radio-group { display: flex; gap: 12px; }
.am-radio { flex: 1; padding: 12px; border-radius: 10px; border: 1.5px solid #e2e8f0; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; font-weight: 600; color: #64748b; transition: 0.2s; }
.am-radio.active-green { border-color: #10b981; background: #f0fdf4; color: #059669; }
.am-radio.active-red { border-color: #ef4444; background: #fef2f2; color: #dc2626; }
.am-radio input { display: none; }

.am-tags-edit { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.am-tag-removable { padding: 6px 12px; border-radius: 8px; background: #1a3a1b; color: white; font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; gap: 6px; }
.am-tag-removable svg { cursor: pointer; opacity: 0.7; }
.am-tag-removable svg:hover { opacity: 1; }

.am-tag-input-wrap {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
}
.am-tag-input-wrap input {
    flex: 1;
    padding: 8px 12px;
    border-radius: 8px;
    border: 1.5px solid #e2e8f0;
    background: #f8fafc;
    font-size: 0.85rem;
    outline: none;
}
.am-tag-input-wrap button {
    background: #1a3a1b;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0 12px;
    cursor: pointer;
}

.am-tag-suggestions { background: #f8fafc; border-radius: 12px; padding: 16px; }
.am-no-sugg { font-size: 0.85rem; color: #94a3b8; font-style: italic; }
.am-tag-suggestions span { font-size: 0.75rem; font-weight: 700; color: #94a3b8; display: block; margin-bottom: 10px; }
.am-sugg-btn { padding: 6px 12px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; font-size: 0.85rem; font-weight: 600; color: #475569; cursor: pointer; transition: 0.2s; }
.am-sugg-btn.active { background: #1a3a1b; color: white; border-color: #1a3a1b; }
.am-sugg-btn:hover:not(.active) { background: #f1f5f9; }



.am-detail-footer { margin-top: 40px; padding-top: 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; }
.am-btn-delete { padding: 10px 20px; border-radius: 10px; border: 1.5px solid #fee2e2; background: white; color: #dc2626; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; }
.am-btn-delete:hover { background: #fef2f2; }

/* Confirm Modal */
.am-confirm-modal {
    background: white;
    width: 100%;
    max-width: 400px;
    border-radius: 24px;
    padding: 32px;
    text-align: center;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}
.am-confirm-icon {
    width: 64px;
    height: 64px;
    background: #fef2f2;
    color: #dc2626;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}
.am-confirm-modal h3 { font-size: 1.25rem; font-weight: 800; color: #1e293b; margin-bottom: 12px; }
.am-confirm-modal p { color: #64748b; font-size: 0.95rem; line-height: 1.5; margin-bottom: 24px; }
.am-confirm-footer { display: flex; gap: 12px; }
.am-confirm-footer button { flex: 1; }
.am-btn-red { background: #dc2626; color: white; border: none; padding: 12px; border-radius: 12px; font-weight: 700; cursor: pointer; }

/* Spinner */
.am-spinner { width: 30px; height: 30px; border: 3px solid rgba(0,0,0,0.1); border-top-color: #1a3a1b; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Mobile Responsiveness */
@media (max-width: 1200px) {
    .am-detail-grid { grid-template-columns: 1fr; }
    .am-detail-header h2 { font-size: 1.5rem; }
}

@media (max-width: 768px) {
    .am-top-bar { flex-direction: column; align-items: stretch; }
    .am-search-box { max-width: 100%; }
    .am-form-grid { grid-template-columns: 1fr; }
    .am-detail-header { flex-direction: column; align-items: stretch; gap: 16px; }
    .am-detail-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .am-detail-actions button { width: 100%; justify-content: center; }
    .am-pagination { flex-direction: column; gap: 16px; text-align: center; }
    
    .am-breadcrumb {
        font-size: 0.8rem;
    }
}

@media (max-width: 480px) {
    .am-card { padding: 16px; }
    .am-btn-green, .am-btn-white { padding: 10px 12px; font-size: 0.85rem; }
    .am-status-badge { padding: 4px 8px; font-size: 0.75rem; }
}
</style>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import AdminLayout from './AdminLayout.vue';
import { useAdminAuth } from '../../composables/useAdminAuth';
import {
    Armchair,
    Clock,
    DoorOpen,
    Eye,
    EyeOff,
    Layers3,
    Package,
    Pencil,
    Plus,
    Save,
    Search,
    Trash2,
    X,
} from 'lucide-vue-next';
import { toast } from 'vue3-toastify';
import axios from 'axios';

const { authHeader } = useAdminAuth();

const activeTab = ref('packages');
const searchQuery = ref('');
const loading = ref(false);
const packages = ref([]);
const tables = ref([]);
const editingItem = ref(null);
const showForm = ref(false);

const packageForm = ref({
    name: '',
    category: 'basic',
    price: 0,
    duration: 60,
    duration_label: '',
    is_active: true,
    free_drinks_count: 0,
});

const tableForm = ref({
    code: '',
    category: 'indoor',
    status: 'free',
    total_seating: 1,
    is_active: true,
});

const isPackageTab = computed(() => activeTab.value === 'packages');
const currentItems = computed(() => isPackageTab.value ? packages.value : tables.value);

const packageCategories = ['basic', 'vip', 'ultra', 'ship'];
const tableCategories = ['indoor', 'outdoor', 'private', 'vip_room', 'meeting_room'];

const formatCurrency = (val) => new Intl.NumberFormat('vi-VN').format(val || 0) + 'đ';

const isVisible = (value) => value === true || value === 1 || value === '1';

const displayPackagePrice = computed({
    get: () => packageForm.value.price ? new Intl.NumberFormat('vi-VN').format(packageForm.value.price).replace(/,/g, '.') : '',
    set: (val) => {
        packageForm.value.price = parseInt(String(val).replace(/\D/g, ''), 10) || 0;
    },
});

function resetForms() {
    editingItem.value = null;
    packageForm.value = {
        name: '',
        category: 'basic',
        price: 0,
        duration: 60,
        duration_label: '',
        is_active: true,
        free_drinks_count: 0,
    };
    tableForm.value = {
        code: '',
        category: 'indoor',
        status: 'free',
        total_seating: 1,
        is_active: true,
    };
}

async function fetchPackages() {
    const res = await axios.get(`/api/admin/packages?search=${encodeURIComponent(searchQuery.value)}`, {
        headers: authHeader(),
    });
    packages.value = res.data.items;
}

async function fetchTables() {
    const res = await axios.get(`/api/admin/tables?search=${encodeURIComponent(searchQuery.value)}`, {
        headers: authHeader(),
    });
    tables.value = res.data.items;
}

async function fetchData() {
    loading.value = true;
    try {
        if (isPackageTab.value) {
            await fetchPackages();
        } else {
            await fetchTables();
        }
    } catch (e) {
        if (e.response && e.response.status === 401) {
            toast.error('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.');
            setTimeout(() => {
                window.location.href = '/admin/login';
            }, 1500);
        } else {
            toast.error('Lỗi khi tải dữ liệu: ' + (e.response?.data?.message || e.message));
        }
    } finally {
        loading.value = false;
    }
}

function openAdd() {
    resetForms();
    showForm.value = true;
}

function openEdit(item) {
    editingItem.value = item;
    if (isPackageTab.value) {
        packageForm.value = {
            name: item.name || '',
            category: item.category || 'basic',
            price: item.price || 0,
            duration: item.duration || 60,
            duration_label: item.duration_label || '',
            is_active: isVisible(item.is_active),
            free_drinks_count: item.free_drinks_count || 0,
        };
    } else {
        tableForm.value = {
            code: item.code || '',
            category: item.category || 'indoor',
            status: item.status || 'free',
            total_seating: item.total_seating || 0,
            is_active: isVisible(item.is_active),
        };
    }
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    resetForms();
}

async function savePackage() {
    if (!packageForm.value.name || !packageForm.value.category) {
        toast.warning('Vui lòng nhập tên gói và danh mục');
        return;
    }

    if (!packageForm.value.duration_label) {
        packageForm.value.duration_label = `${packageForm.value.duration} phút`;
    }

    const payload = {
        ...packageForm.value,
        is_active: packageForm.value.is_active ? 1 : 0,
    };

    if (editingItem.value) {
        await axios.put(`/api/admin/packages/${editingItem.value.id}`, payload, { headers: authHeader() });
        toast.success('Cập nhật gói thành công');
    } else {
        await axios.post('/api/admin/packages', payload, { headers: authHeader() });
        toast.success('Thêm gói thành công');
    }
}

async function saveTable() {
    if (!tableForm.value.code || !tableForm.value.category) {
        toast.warning('Vui lòng nhập mã bàn và khu vực');
        return;
    }

    tableForm.value.code = tableForm.value.code.trim().toUpperCase();

    const payload = {
        ...tableForm.value,
        is_active: tableForm.value.is_active ? 1 : 0,
    };

    if (editingItem.value) {
        await axios.put(`/api/admin/tables/${editingItem.value.id}`, payload, { headers: authHeader() });
        toast.success('Cập nhật bàn thành công');
    } else {
        await axios.post('/api/admin/tables', payload, { headers: authHeader() });
        toast.success('Thêm bàn thành công');
    }
}

async function saveItem() {
    loading.value = true;
    try {
        if (isPackageTab.value) {
            await savePackage();
        } else {
            await saveTable();
        }
        closeForm();
        await fetchData();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Lỗi khi lưu dữ liệu');
    } finally {
        loading.value = false;
    }
}

async function deleteItem(item) {
    const label = isPackageTab.value ? `gói "${item.name}"` : `bàn "${item.code}"`;
    if (!window.confirm(`Bạn có chắc chắn muốn xoá ${label}?`)) return;

    loading.value = true;
    try {
        if (isPackageTab.value) {
            await axios.delete(`/api/admin/packages/${item.id}`, { headers: authHeader() });
            toast.success('Xoá gói thành công');
        } else {
            await axios.delete(`/api/admin/tables/${item.id}`, { headers: authHeader() });
            toast.success('Xoá bàn thành công');
        }
        await fetchData();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Lỗi khi xoá dữ liệu');
    } finally {
        loading.value = false;
    }
}

function switchTab(tab) {
    activeTab.value = tab;
    searchQuery.value = '';
    closeForm();
    fetchData();
}

watch(searchQuery, () => fetchData());
onMounted(fetchData);
</script>

<template>
    <AdminLayout>
        <template #title>Quản lý gói & bàn</template>

        <div class="bs-wrap">
            <div class="bs-toolbar">
                <div class="bs-tabs">
                    <button :class="{ active: activeTab === 'packages' }" @click="switchTab('packages')">
                        <Package :size="18" /> Gói dịch vụ
                    </button>
                    <button :class="{ active: activeTab === 'tables' }" @click="switchTab('tables')">
                        <DoorOpen :size="18" /> Bàn & phòng
                    </button>
                </div>

                <button class="bs-primary" @click="openAdd">
                    <Plus :size="18" /> {{ isPackageTab ? 'Thêm gói' : 'Thêm bàn' }}
                </button>
            </div>

            <div class="bs-search">
                <Search :size="18" />
                <input
                    v-model="searchQuery"
                    type="text"
                    :placeholder="isPackageTab ? 'Tìm theo tên gói, danh mục...' : 'Tìm theo mã bàn, khu vực...'"
                />
            </div>

            <div class="bs-table-card">
                <div v-if="loading" class="bs-empty">Đang tải...</div>
                <div v-else-if="currentItems.length === 0" class="bs-empty">Chưa có dữ liệu</div>

                <table v-else class="bs-table">
                    <thead>
                        <tr v-if="isPackageTab">
                            <th>Gói</th>
                            <th>Danh mục</th>
                            <th>Giá</th>
                            <th>Thời lượng</th>
                            <th>Ưu đãi</th>
                            <th>Hiển thị</th>
                            <th>Đơn đã dùng</th>
                            <th>Thao tác</th>
                        </tr>
                        <tr v-else>
                            <th>Bàn/phòng</th>
                            <th>Khu vực</th>
                            <th>Trạng thái</th>
                            <th>Hiển thị</th>
                            <th>Số ghế</th>
                            <th>Đơn đã dùng</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in currentItems" :key="item.id">
                            <template v-if="isPackageTab">
                                <td>
                                    <div class="bs-name-cell">
                                        <Layers3 :size="18" />
                                        <strong>{{ item.name }}</strong>
                                    </div>
                                </td>
                                <td>{{ item.category }}</td>
                                <td>{{ formatCurrency(item.price) }}</td>
                                <td>
                                    <span class="bs-soft">
                                        <Clock :size="14" /> {{ item.duration_label || `${item.duration} phút` }}
                                    </span>
                                </td>
                                <td>{{ item.free_drinks_count || 0 }} nước miễn phí</td>
                                <td>
                                    <span class="bs-visibility" :class="{ hidden: !isVisible(item.is_active) }">
                                        <Eye v-if="isVisible(item.is_active)" :size="14" />
                                        <EyeOff v-else :size="14" />
                                        {{ isVisible(item.is_active) ? 'Đang hiện' : 'Đang ẩn' }}
                                    </span>
                                </td>
                                <td>{{ item.bookings_count || 0 }}</td>
                            </template>

                            <template v-else>
                                <td>
                                    <div class="bs-name-cell">
                                        <Armchair :size="18" />
                                        <strong>{{ item.code }}</strong>
                                    </div>
                                </td>
                                <td>{{ item.category }}</td>
                                <td>
                                    <span class="bs-status" :class="item.status">
                                        {{ item.status === 'free' ? 'Trống' : 'Đang dùng' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="bs-visibility" :class="{ hidden: !isVisible(item.is_active) }">
                                        <Eye v-if="isVisible(item.is_active)" :size="14" />
                                        <EyeOff v-else :size="14" />
                                        {{ isVisible(item.is_active) ? 'Đang hiện' : 'Đang ẩn' }}
                                    </span>
                                </td>
                                <td>{{ item.total_seating }}</td>
                                <td>{{ item.bookings_count || 0 }}</td>
                            </template>

                            <td>
                                <div class="bs-actions">
                                    <button title="Sửa" @click="openEdit(item)">
                                        <Pencil :size="16" />
                                    </button>
                                    <button title="Xoá" class="danger" @click="deleteItem(item)">
                                        <Trash2 :size="16" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Teleport to="body">
                <div v-if="showForm" class="bs-modal-overlay" @click.self="closeForm">
                    <div class="bs-modal" role="dialog" aria-modal="true">
                        <div class="bs-panel-head">
                            <h3>{{ editingItem ? 'Chỉnh sửa' : 'Thêm mới' }} {{ isPackageTab ? 'gói dịch vụ' : 'bàn/phòng' }}</h3>
                            <button class="bs-icon-btn" @click="closeForm"><X :size="18" /></button>
                        </div>

                        <div v-if="isPackageTab" class="bs-form-grid">
                            <label>
                                <span>Tên gói</span>
                                <input v-model="packageForm.name" type="text" placeholder="Ví dụ: Basic 2 giờ" />
                            </label>
                            <label>
                                <span>Danh mục</span>
                                <select v-model="packageForm.category">
                                    <option v-for="cat in packageCategories" :key="cat" :value="cat">{{ cat }}</option>
                                </select>
                            </label>
                            <label>
                                <span>Giá</span>
                                <input v-model="displayPackagePrice" type="text" placeholder="0" />
                            </label>
                            <label>
                                <span>Thời lượng phút</span>
                                <input v-model.number="packageForm.duration" min="1" type="number" />
                            </label>
                            <label>
                                <span>Nhãn thời lượng</span>
                                <input v-model="packageForm.duration_label" type="text" placeholder="Ví dụ: 2 giờ" />
                            </label>
                            <label>
                                <span>Số nước miễn phí</span>
                                <input v-model.number="packageForm.free_drinks_count" min="0" type="number" placeholder="0" />
                            </label>
                            <label>
                                <span>Hiển thị với khách</span>
                                <select v-model="packageForm.is_active">
                                    <option :value="true">Hiển thị</option>
                                    <option :value="false">Ẩn</option>
                                </select>
                            </label>
                        </div>

                        <div v-else class="bs-form-grid">
                            <label>
                                <span>Mã bàn/phòng</span>
                                <input v-model="tableForm.code" type="text" placeholder="Ví dụ: A01" />
                            </label>
                            <label>
                                <span>Khu vực</span>
                                <select v-model="tableForm.category">
                                    <option v-for="cat in tableCategories" :key="cat" :value="cat">{{ cat }}</option>
                                </select>
                            </label>
                            <label>
                                <span>Trạng thái</span>
                                <select v-model="tableForm.status">
                                    <option value="free">Trống</option>
                                    <option value="occupied">Đang dùng</option>
                                </select>
                            </label>
                            <label>
                                <span>Số ghế tối đa</span>
                                <input v-model.number="tableForm.total_seating" min="0" type="number" />
                            </label>
                            <label>
                                <span>Hiển thị với khách</span>
                                <select v-model="tableForm.is_active">
                                    <option :value="true">Hiển thị</option>
                                    <option :value="false">Ẩn</option>
                                </select>
                            </label>
                        </div>

                        <div class="bs-form-actions">
                            <button class="bs-secondary" @click="closeForm">Huỷ</button>
                            <button class="bs-primary" :disabled="loading" @click="saveItem">
                                <Save :size="18" /> Lưu
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>
        </div>
    </AdminLayout>
</template>

<style scoped>
* { box-sizing: border-box; }
.bs-wrap { width: 100%; max-width: 1180px; margin: 0 auto; }
.bs-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
.bs-tabs { display: inline-flex; padding: 4px; border: 1px solid #e2e8f0; border-radius: 12px; background: #f8fafc; }
.bs-tabs button { display: flex; align-items: center; gap: 8px; border: none; background: transparent; padding: 10px 16px; border-radius: 9px; color: #64748b; font-weight: 700; cursor: pointer; }
.bs-tabs button.active { background: white; color: #1a3a1b; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08); }
.bs-primary, .bs-secondary { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 16px; border-radius: 10px; font-weight: 700; cursor: pointer; white-space: nowrap; }
.bs-primary { border: none; background: #1a3a1b; color: white; }
.bs-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.bs-secondary { border: 1px solid #e2e8f0; background: white; color: #475569; }
.bs-search { position: relative; max-width: 420px; margin-bottom: 18px; }
.bs-search svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
.bs-search input { width: 100%; padding: 12px 14px 12px 42px; border-radius: 12px; border: 1px solid #e2e8f0; background: white; outline: none; }
.bs-panel-head { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 18px; }
.bs-panel-head h3 { margin: 0; font-size: 1.1rem; color: #1e293b; }
.bs-icon-btn { width: 34px; height: 34px; border-radius: 9px; border: 1px solid #e2e8f0; background: white; display: flex; align-items: center; justify-content: center; cursor: pointer; }
.bs-modal-overlay { position: fixed; inset: 0; z-index: 3000; display: flex; align-items: center; justify-content: center; padding: 20px; background: rgba(15, 23, 42, 0.48); backdrop-filter: blur(3px); }
.bs-modal { width: min(760px, 100%); max-height: calc(100vh - 40px); overflow-y: auto; background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 22px; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.22); }
.bs-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
.bs-form-grid label { display: flex; flex-direction: column; gap: 8px; font-weight: 700; color: #475569; font-size: 0.9rem; }
.bs-form-grid input, .bs-form-grid select { padding: 11px 12px; border-radius: 9px; border: 1.5px solid #e2e8f0; background: #f8fafc; outline: none; }
.bs-form-grid input:focus, .bs-form-grid select:focus { border-color: #1a3a1b; background: white; }
.bs-form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; }
.bs-table-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow-x: auto; }
.bs-table { width: 100%; min-width: 780px; border-collapse: collapse; }
.bs-table th { background: #f8fafc; color: #64748b; text-align: left; padding: 14px 16px; font-size: 0.75rem; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
.bs-table td { padding: 15px 16px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.bs-name-cell, .bs-soft { display: inline-flex; align-items: center; gap: 8px; }
.bs-name-cell svg { color: #1a3a1b; }
.bs-soft { padding: 6px 10px; border-radius: 999px; background: #f1f5f9; color: #475569; font-weight: 700; font-size: 0.85rem; }
.bs-status { display: inline-flex; padding: 6px 10px; border-radius: 999px; font-weight: 700; font-size: 0.85rem; }
.bs-status.free { background: #ecfdf5; color: #059669; }
.bs-status.occupied { background: #fef2f2; color: #dc2626; }
.bs-visibility { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; background: #ecfdf5; color: #047857; font-weight: 700; font-size: 0.85rem; white-space: nowrap; }
.bs-visibility.hidden { background: #f1f5f9; color: #64748b; }
.bs-actions { display: flex; gap: 8px; }
.bs-actions button { width: 34px; height: 34px; border-radius: 9px; border: 1px solid rgba(45,79,30,0.15); background: rgba(45,79,30,0.08); color: #1a3a1b; display: flex; align-items: center; justify-content: center; cursor: pointer; }
.bs-actions button.danger { border-color: rgba(239,68,68,0.15); background: rgba(239,68,68,0.08); color: #dc2626; }
.bs-empty { padding: 40px 16px; text-align: center; color: #64748b; }

@media (max-width: 768px) {
    .bs-toolbar { flex-direction: column; align-items: stretch; }
    .bs-tabs { display: grid; grid-template-columns: 1fr 1fr; }
    .bs-search { max-width: 100%; }
    .bs-form-grid { grid-template-columns: 1fr; }
    .bs-form-actions { display: grid; grid-template-columns: 1fr 1fr; }
}
</style>

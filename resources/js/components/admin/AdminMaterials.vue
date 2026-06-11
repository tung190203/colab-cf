<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import AdminLayout from './AdminLayout.vue';
import ConfirmDialog from '../ConfirmDialog.vue';
import { useAdminAuth } from '../../composables/useAdminAuth';
import {
    AlertTriangle,
    CheckCircle2,
    EyeOff,
    History,
    PackageMinus,
    PackageOpen,
    PackagePlus,
    Pencil,
    Plus,
    Save,
    Search,
    X,
} from 'lucide-vue-next';
import { toast } from 'vue3-toastify';
import axios from 'axios';

const { authHeader } = useAdminAuth();

const materials = ref([]);
const loading = ref(false);
const saving = ref(false);
const importing = ref(false);
const deducting = ref(false);
const searchQuery = ref('');
const statusFilter = ref('');
const alertFilter = ref('all');
const showForm = ref(false);
const showImportForm = ref(false);
const showDeductForm = ref(false);
const showLogsPanel = ref(false);
const showHideConfirm = ref(false);
const editingMaterial = ref(null);
const importingMaterial = ref(null);
const deductingMaterial = ref(null);
const viewingLogsMaterial = ref(null);
const pendingHideMaterial = ref(null);
const alertsCount = ref(0);
const stockLogs = ref([]);
const logsLoading = ref(false);
const unitOptions = ['g', 'ml', 'cái', 'chai'];
const useCustomUnit = ref(false);

const form = ref(defaultForm());
const importForm = ref(defaultImportForm());
const deductForm = ref(defaultDeductForm());

const filteredSummary = computed(() => {
    const active = materials.value.filter((item) => item.active).length;
    const low = materials.value.filter((item) => isLowStock(item)).length;

    return { active, low };
});

const visibleMaterials = computed(() => {
    if (alertFilter.value === 'alerts') {
        return materials.value.filter((item) => isLowStock(item));
    }

    return materials.value;
});
const availableUnitOptions = computed(() => {
    return [...new Set([
        ...unitOptions,
        ...materials.value.map((item) => item.unit).filter(Boolean),
        form.value.unit,
    ].filter(Boolean))];
});

function defaultForm() {
    return {
        name: '',
        unit: 'g',
        current_stock: 0,
        low_stock_threshold: 0,
        price_per_unit: '',
        note: '',
        active: true,
    };
}

function defaultImportForm() {
    return {
        quantity: '',
        unit_price: '',
        note: '',
    };
}

function defaultDeductForm() {
    return {
        quantity: '',
        note: '',
    };
}

function normalizeNumber(value) {
    const number = Number(value);
    return Number.isFinite(number) ? number : 0;
}

function isLowStock(item) {
    return item.active && normalizeNumber(item.current_stock) <= normalizeNumber(item.low_stock_threshold);
}

function formatStock(item) {
    return `${formatNumber(item.current_stock)} ${item.unit}`;
}

function formatNumber(value) {
    const number = normalizeNumber(value);
    return new Intl.NumberFormat('vi-VN', {
        maximumFractionDigits: 3,
    }).format(number);
}

function formatCurrency(value) {
    if (value === null || value === undefined || value === '') return 'Chưa nhập';

    return `${new Intl.NumberFormat('vi-VN', {
        maximumFractionDigits: 0,
    }).format(normalizeNumber(value))}đ`;
}

const displayPricePerUnit = computed({
    get: () => {
        if (form.value.price_per_unit === null || form.value.price_per_unit === undefined || form.value.price_per_unit === '') {
            return '';
        }

        return new Intl.NumberFormat('vi-VN', {
            maximumFractionDigits: 0,
        }).format(normalizeNumber(form.value.price_per_unit));
    },
    set: (value) => {
        form.value.price_per_unit = parseInt(String(value).replace(/\D/g, ''), 10) || '';
    },
});

const displayImportUnitPrice = computed({
    get: () => {
        if (importForm.value.unit_price === null || importForm.value.unit_price === undefined || importForm.value.unit_price === '') {
            return '';
        }

        return new Intl.NumberFormat('vi-VN', {
            maximumFractionDigits: 0,
        }).format(normalizeNumber(importForm.value.unit_price));
    },
    set: (value) => {
        importForm.value.unit_price = parseInt(String(value).replace(/\D/g, ''), 10) || '';
    },
});

async function fetchMaterials() {
    loading.value = true;

    try {
        const res = await axios.get('/api/materials', {
            headers: authHeader(),
            params: {
                search: searchQuery.value,
                status: statusFilter.value,
                per_page: 50,
            },
        });

        materials.value = res.data.materials.data;
        alertsCount.value = res.data.alerts_count;
    } catch (error) {
        toast.error('Lỗi khi tải danh sách NVL');
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editingMaterial.value = null;
    form.value = defaultForm();
    useCustomUnit.value = false;
    showForm.value = true;
}

function openEdit(material) {
    editingMaterial.value = material;
    form.value = {
        name: material.name,
        unit: material.unit,
        current_stock: normalizeNumber(material.current_stock),
        low_stock_threshold: normalizeNumber(material.low_stock_threshold),
        price_per_unit: material.price_per_unit ?? '',
        note: material.note || '',
        active: material.active,
    };
    useCustomUnit.value = !availableUnitOptions.value.includes(material.unit);
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    editingMaterial.value = null;
    useCustomUnit.value = false;
}

function handleUnitSelect(value) {
    if (value === '__custom__') {
        useCustomUnit.value = true;
        form.value.unit = '';
        return;
    }

    useCustomUnit.value = false;
    form.value.unit = value;
}

function openImport(material) {
    importingMaterial.value = material;
    importForm.value = defaultImportForm();
    showImportForm.value = true;
}

function closeImportForm() {
    showImportForm.value = false;
    importingMaterial.value = null;
}

function openDeduct(material) {
    deductingMaterial.value = material;
    deductForm.value = defaultDeductForm();
    showDeductForm.value = true;
}

function closeDeductForm() {
    showDeductForm.value = false;
    deductingMaterial.value = null;
}

async function saveStockImport() {
    if (!importingMaterial.value) return;

    const quantity = normalizeNumber(importForm.value.quantity);
    if (quantity <= 0) {
        toast.warning('Vui lòng nhập số lượng lớn hơn 0');
        return;
    }

    importing.value = true;

    try {
        await axios.post('/api/stock/import', {
            material_id: importingMaterial.value.id,
            quantity,
            unit_price: importForm.value.unit_price === '' ? null : normalizeNumber(importForm.value.unit_price),
            note: importForm.value.note,
        }, { headers: authHeader() });

        toast.success('Nhập kho thành công');
        closeImportForm();
        fetchMaterials();
    } catch (error) {
        toast.error(error.response?.data?.message || 'Lỗi khi nhập kho');
    } finally {
        importing.value = false;
    }
}

async function saveManualDeduct() {
    if (!deductingMaterial.value) return;

    const quantity = normalizeNumber(deductForm.value.quantity);
    if (quantity <= 0) {
        toast.warning('Vui lòng nhập số lượng lớn hơn 0');
        return;
    }

    if (quantity > normalizeNumber(deductingMaterial.value.current_stock)) {
        toast.warning('Số lượng xuất không được lớn hơn tồn kho hiện tại');
        return;
    }

    if (!deductForm.value.note.trim()) {
        toast.warning('Vui lòng nhập lý do xuất kho');
        return;
    }

    deducting.value = true;

    try {
        await axios.post('/api/stock/manual-deduct', {
            material_id: deductingMaterial.value.id,
            quantity,
            note: deductForm.value.note,
        }, { headers: authHeader() });

        toast.success('Xuất kho thành công');
        closeDeductForm();
        fetchMaterials();
    } catch (error) {
        toast.error(error.response?.data?.message || 'Lỗi khi xuất kho');
    } finally {
        deducting.value = false;
    }
}

async function openLogs(material) {
    viewingLogsMaterial.value = material;
    showLogsPanel.value = true;
    logsLoading.value = true;
    stockLogs.value = [];

    try {
        const res = await axios.get('/api/stock/logs', {
            headers: authHeader(),
            params: {
                material: material.id,
                per_page: 30,
            },
        });
        stockLogs.value = res.data.logs.data;
    } catch (error) {
        toast.error('Lỗi khi tải lịch sử tồn kho');
    } finally {
        logsLoading.value = false;
    }
}

function closeLogsPanel() {
    showLogsPanel.value = false;
    viewingLogsMaterial.value = null;
    stockLogs.value = [];
}

async function saveMaterial() {
    if (!form.value.name.trim() || !form.value.unit.trim()) {
        toast.warning('Vui lòng nhập tên NVL và đơn vị');
        return;
    }

    saving.value = true;

    const payload = {
        ...form.value,
        current_stock: normalizeNumber(form.value.current_stock),
        low_stock_threshold: normalizeNumber(form.value.low_stock_threshold),
        price_per_unit: form.value.price_per_unit === '' ? null : normalizeNumber(form.value.price_per_unit),
    };

    try {
        if (editingMaterial.value) {
            await axios.patch(`/api/materials/${editingMaterial.value.id}`, payload, { headers: authHeader() });
            toast.success('Đã cập nhật NVL');
        } else {
            await axios.post('/api/materials', payload, { headers: authHeader() });
            toast.success('Đã thêm NVL');
        }

        closeForm();
        fetchMaterials();
    } catch (error) {
        toast.error(error.response?.data?.message || 'Lỗi khi lưu NVL');
    } finally {
        saving.value = false;
    }
}

function requestHideMaterial(material) {
    pendingHideMaterial.value = material;
    showHideConfirm.value = true;
}

function cancelHideMaterial() {
    showHideConfirm.value = false;
    pendingHideMaterial.value = null;
}

async function confirmHideMaterial() {
    if (!pendingHideMaterial.value) return;

    try {
        await axios.delete(`/api/materials/${pendingHideMaterial.value.id}`, { headers: authHeader() });
        toast.success('Đã ẩn NVL');
        fetchMaterials();
    } catch (error) {
        toast.error('Lỗi khi ẩn NVL');
    } finally {
        cancelHideMaterial();
    }
}

function formatDateTime(value) {
    if (!value) return '--';

    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function logTypeLabel(type) {
    const labels = {
        import: 'Nhập kho',
        auto_deduct: 'Trừ theo đơn',
        manual_deduct: 'Xuất thủ công',
        adjustment: 'Điều chỉnh',
    };

    return labels[type] || type;
}

function formatLogQuantity(log) {
    const quantity = normalizeNumber(log.quantity);
    const sign = quantity > 0 ? '+' : '';
    const unit = log.material?.unit || viewingLogsMaterial.value?.unit || '';

    return `${sign}${formatNumber(quantity)} ${unit}`;
}

let searchTimer = null;
watch([searchQuery, statusFilter], () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(fetchMaterials, 250);
});

onMounted(fetchMaterials);
</script>

<template>
    <AdminLayout>
        <template #title>Quản lý nguyên vật liệu</template>

        <div class="materials-page">
            <div class="summary-grid">
                <div class="summary-tile">
                    <PackageOpen :size="22" />
                    <div>
                        <span>Tổng NVL</span>
                        <strong>{{ materials.length }}</strong>
                    </div>
                </div>
                <div class="summary-tile">
                    <CheckCircle2 :size="22" />
                    <div>
                        <span>Đang sử dụng</span>
                        <strong>{{ filteredSummary.active }}</strong>
                    </div>
                </div>
                <div class="summary-tile warning" :class="{ danger: alertsCount > 0 }">
                    <AlertTriangle :size="22" />
                    <div>
                        <span>Dưới ngưỡng</span>
                        <strong>{{ alertsCount }}</strong>
                    </div>
                </div>
            </div>

            <div class="toolbar">
                <div class="alert-tabs">
                    <button :class="{ active: alertFilter === 'all' }" @click="alertFilter = 'all'">
                        Tất cả
                    </button>
                    <button :class="{ active: alertFilter === 'alerts' }" @click="alertFilter = 'alerts'">
                        Đang cảnh báo
                        <span v-if="alertsCount">{{ alertsCount }}</span>
                    </button>
                </div>
                <div class="search-box">
                    <Search :size="18" />
                    <input v-model="searchQuery" type="text" placeholder="Tìm tên, đơn vị, ghi chú..." />
                </div>
                <select v-model="statusFilter" class="status-filter">
                    <option value="">Tất cả trạng thái</option>
                    <option value="active">Đang dùng</option>
                    <option value="inactive">Đã ẩn</option>
                </select>
                <button class="primary-btn" @click="openCreate">
                    <Plus :size="18" />
                    Thêm NVL
                </button>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nguyên vật liệu</th>
                            <th>Tồn kho</th>
                            <th>Ngưỡng cảnh báo</th>
                            <th>Giá nhập tham khảo</th>
                            <th>Trạng thái</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td colspan="6" class="empty-cell">Đang tải dữ liệu...</td>
                        </tr>
                        <tr v-else-if="visibleMaterials.length === 0">
                            <td colspan="6" class="empty-cell">Chưa có nguyên vật liệu</td>
                        </tr>
                        <tr v-for="item in visibleMaterials" v-else :key="item.id" :class="{ muted: !item.active }">
                            <td>
                                <div class="name-cell">
                                    <strong>{{ item.name }}</strong>
                                    <span v-if="item.note">{{ item.note }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="stock-badge" :class="{ low: isLowStock(item) }">
                                    <AlertTriangle v-if="isLowStock(item)" :size="15" />
                                    {{ formatStock(item) }}
                                </span>
                            </td>
                            <td>{{ formatNumber(item.low_stock_threshold) }} {{ item.unit }}</td>
                            <td>{{ formatCurrency(item.price_per_unit) }}</td>
                            <td>
                                <span class="status-badge" :class="item.active ? 'active' : 'inactive'">
                                    {{ item.active ? 'Đang dùng' : 'Đã ẩn' }}
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <button v-if="item.active" title="Nhập kho" @click="openImport(item)">
                                        <PackagePlus :size="16" />
                                    </button>
                                    <button v-if="item.active" title="Xuất kho hao hụt" @click="openDeduct(item)">
                                        <PackageMinus :size="16" />
                                    </button>
                                    <button title="Lịch sử tồn kho" @click="openLogs(item)">
                                        <History :size="16" />
                                    </button>
                                    <button title="Sửa" @click="openEdit(item)">
                                        <Pencil :size="16" />
                                    </button>
                                    <button v-if="item.active" title="Ẩn" @click="requestHideMaterial(item)">
                                        <EyeOff :size="16" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="showForm" class="modal-backdrop" @click.self="closeForm">
            <div class="material-modal">
                <div class="modal-header">
                    <h3>{{ editingMaterial ? 'Sửa nguyên vật liệu' : 'Thêm nguyên vật liệu' }}</h3>
                    <button @click="closeForm">
                        <X :size="20" />
                    </button>
                </div>

                <div class="form-grid">
                    <label class="field full">
                        <span>Tên NVL *</span>
                        <input v-model="form.name" type="text" placeholder="Ví dụ: Cốt dừa" />
                    </label>
                    <label class="field">
                        <span>Đơn vị *</span>
                        <select :value="useCustomUnit ? '__custom__' : form.unit" @change="handleUnitSelect($event.target.value)">
                            <option v-for="unit in availableUnitOptions" :key="unit" :value="unit">{{ unit }}</option>
                            <option value="__custom__">+ Tạo đơn vị mới</option>
                        </select>
                    </label>
                    <label v-if="useCustomUnit" class="field">
                        <span>Đơn vị mới *</span>
                        <input v-model.trim="form.unit" type="text" maxlength="30" placeholder="Ví dụ: lon, hộp, kg..." />
                    </label>
                    <label class="field">
                        <span>Tồn kho hiện tại</span>
                        <input v-model.number="form.current_stock" type="number" min="0" step="0.001" />
                    </label>
                    <label class="field">
                        <span>Ngưỡng cảnh báo</span>
                        <input v-model.number="form.low_stock_threshold" type="number" min="0" step="0.001" />
                    </label>
                    <label class="field">
                        <span>Giá nhập tham khảo</span>
                        <input v-model="displayPricePerUnit" type="text" inputmode="numeric" placeholder="VND / đơn vị" />
                    </label>
                    <label class="field full">
                        <span>Ghi chú</span>
                        <textarea v-model="form.note" rows="3" placeholder="Nhà cung cấp, đặc điểm, hạn dùng..."></textarea>
                    </label>
                    <label class="toggle-field full">
                        <input v-model="form.active" type="checkbox" />
                        <span>Đang sử dụng</span>
                    </label>
                </div>

                <div class="modal-actions">
                    <button class="secondary-btn" @click="closeForm">Hủy</button>
                    <button class="primary-btn" :disabled="saving" @click="saveMaterial">
                        <Save :size="18" />
                        {{ saving ? 'Đang lưu...' : 'Lưu NVL' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showImportForm" class="modal-backdrop" @click.self="closeImportForm">
            <div class="material-modal compact">
                <div class="modal-header">
                    <div>
                        <h3>Nhập kho</h3>
                        <p v-if="importingMaterial">{{ importingMaterial.name }} · Tồn hiện tại {{ formatStock(importingMaterial) }}</p>
                    </div>
                    <button @click="closeImportForm">
                        <X :size="20" />
                    </button>
                </div>

                <div class="form-grid">
                    <label class="field">
                        <span>Số lượng nhập *</span>
                        <input v-model.number="importForm.quantity" type="number" min="0.001" step="0.001" :placeholder="`Đơn vị ${importingMaterial?.unit || ''}`" />
                    </label>
                    <label class="field">
                        <span>Giá nhập</span>
                        <input v-model="displayImportUnitPrice" type="text" inputmode="numeric" placeholder="VND / đơn vị" />
                    </label>
                    <label class="field full">
                        <span>Ghi chú</span>
                        <textarea v-model="importForm.note" rows="3" placeholder="Lô hàng, nhà cung cấp, hạn dùng..."></textarea>
                    </label>
                </div>

                <div class="modal-actions">
                    <button class="secondary-btn" @click="closeImportForm">Hủy</button>
                    <button class="primary-btn" :disabled="importing" @click="saveStockImport">
                        <PackagePlus :size="18" />
                        {{ importing ? 'Đang nhập...' : 'Xác nhận nhập kho' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showDeductForm" class="modal-backdrop" @click.self="closeDeductForm">
            <div class="material-modal compact">
                <div class="modal-header">
                    <div>
                        <h3>Xuất kho thủ công</h3>
                        <p v-if="deductingMaterial">{{ deductingMaterial.name }} · Tồn hiện tại {{ formatStock(deductingMaterial) }}</p>
                    </div>
                    <button @click="closeDeductForm">
                        <X :size="20" />
                    </button>
                </div>

                <div class="form-grid">
                    <label class="field full">
                        <span>Số lượng xuất *</span>
                        <input v-model.number="deductForm.quantity" type="number" min="0.001" step="0.001" :placeholder="`Đơn vị ${deductingMaterial?.unit || ''}`" />
                    </label>
                    <label class="field full">
                        <span>Lý do *</span>
                        <textarea v-model="deductForm.note" rows="3" placeholder="Đổ vỡ, hết hạn, làm sai, thử nghiệm công thức..."></textarea>
                    </label>
                </div>

                <div class="modal-actions">
                    <button class="secondary-btn" @click="closeDeductForm">Hủy</button>
                    <button class="danger-btn" :disabled="deducting" @click="saveManualDeduct">
                        <PackageMinus :size="18" />
                        {{ deducting ? 'Đang xuất...' : 'Xác nhận xuất kho' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showLogsPanel" class="modal-backdrop" @click.self="closeLogsPanel">
            <div class="material-modal logs-modal">
                <div class="modal-header">
                    <div>
                        <h3>Lịch sử tồn kho</h3>
                        <p v-if="viewingLogsMaterial">{{ viewingLogsMaterial.name }}</p>
                    </div>
                    <button @click="closeLogsPanel">
                        <X :size="20" />
                    </button>
                </div>

                <div class="logs-list">
                    <div v-if="logsLoading" class="empty-cell">Đang tải lịch sử...</div>
                    <div v-else-if="stockLogs.length === 0" class="empty-cell">Chưa có lịch sử tồn kho</div>
                    <div v-for="log in stockLogs" v-else :key="log.id" class="log-item">
                        <div class="log-main">
                            <strong>{{ logTypeLabel(log.type) }}</strong>
                            <span>{{ formatDateTime(log.created_at) }} · {{ log.creator?.name || 'Hệ thống' }}</span>
                            <p v-if="log.note">{{ log.note }}</p>
                        </div>
                        <div class="log-stock" :class="{ deduct: normalizeNumber(log.quantity) < 0 }">
                            <strong>{{ formatLogQuantity(log) }}</strong>
                            <span>{{ formatNumber(log.stock_before) }} → {{ formatNumber(log.stock_after) }}</span>
                            <small v-if="log.unit_price">{{ formatCurrency(log.unit_price) }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmDialog
            :show="showHideConfirm"
            title="Ẩn nguyên vật liệu"
            :message="`Bạn có chắc muốn ẩn NVL '${pendingHideMaterial?.name || ''}'? NVL này sẽ không còn được dùng trong các bước nhập mới.`"
            confirm-text="Ẩn NVL"
            cancel-text="Hủy"
            type="warning"
            @confirm="confirmHideMaterial"
            @cancel="cancelHideMaterial"
        />
    </AdminLayout>
</template>

<style scoped>
.materials-page {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.summary-tile {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 82px;
    padding: 16px;
    background: #fff;
    border: 1px solid #e8ece8;
    border-radius: 8px;
    color: #20451f;
}

.summary-tile span {
    display: block;
    color: #667085;
    font-size: 13px;
}

.summary-tile strong {
    display: block;
    margin-top: 4px;
    color: #101828;
    font-size: 26px;
}

.summary-tile.warning {
    color: #b45309;
}

.summary-tile.warning.danger {
    border-color: #fed7aa;
    background: #fff7ed;
}

.toolbar {
    display: grid;
    grid-template-columns: auto minmax(240px, 1fr) 180px auto;
    gap: 12px;
}

.alert-tabs {
    display: inline-flex;
    align-items: center;
    height: 42px;
    padding: 4px;
    background: #f2f4f7;
    border-radius: 8px;
}

.alert-tabs button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    height: 34px;
    padding: 0 12px;
    border: 0;
    border-radius: 7px;
    background: transparent;
    color: #667085;
    font-weight: 800;
    white-space: nowrap;
    cursor: pointer;
}

.alert-tabs button.active {
    background: #fff;
    color: #20451f;
    box-shadow: 0 1px 3px rgba(16, 24, 40, 0.08);
}

.alert-tabs span {
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 999px;
    background: #dc2626;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
}

.search-box {
    display: flex;
    align-items: center;
    gap: 8px;
    height: 42px;
    padding: 0 12px;
    background: #fff;
    border: 1px solid #d0d5dd;
    border-radius: 8px;
    color: #667085;
}

.search-box input,
.status-filter,
.field input,
.field select,
.field textarea {
    width: 100%;
    border: 0;
    outline: 0;
    color: #101828;
    font: inherit;
}

.status-filter {
    height: 42px;
    padding: 0 12px;
    background: #fff;
    border: 1px solid #d0d5dd;
    border-radius: 8px;
}

.primary-btn,
.secondary-btn,
.danger-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    height: 42px;
    padding: 0 16px;
    border-radius: 8px;
    border: 1px solid transparent;
    font-weight: 700;
    cursor: pointer;
}

.primary-btn {
    background: #20451f;
    color: #fff;
}

.danger-btn {
    background: #b42318;
    color: #fff;
}

.primary-btn:disabled,
.danger-btn:disabled {
    opacity: 0.7;
    cursor: wait;
}

.secondary-btn {
    background: #fff;
    color: #344054;
    border-color: #d0d5dd;
}

.table-wrap {
    overflow-x: auto;
    background: #fff;
    border: 1px solid #e8ece8;
    border-radius: 8px;
}

table {
    width: 100%;
    min-width: 860px;
    border-collapse: collapse;
}

th,
td {
    padding: 14px 16px;
    text-align: left;
    border-bottom: 1px solid #f0f2f0;
    vertical-align: middle;
}

th {
    color: #667085;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0;
}

tbody tr:last-child td {
    border-bottom: 0;
}

.name-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.name-cell strong {
    color: #101828;
}

.name-cell span {
    max-width: 320px;
    color: #667085;
    font-size: 13px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.stock-badge,
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 28px;
    padding: 4px 9px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 700;
}

.stock-badge {
    color: #20451f;
    background: #edf7ed;
}

.stock-badge.low {
    color: #b42318;
    background: #fef3f2;
}

.status-badge.active {
    color: #027a48;
    background: #ecfdf3;
}

.status-badge.inactive {
    color: #667085;
    background: #f2f4f7;
}

.muted {
    color: #98a2b3;
}

.row-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.row-actions button,
.modal-header button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border: 1px solid #d0d5dd;
    border-radius: 8px;
    background: #fff;
    color: #344054;
    cursor: pointer;
}

.empty-cell {
    height: 120px;
    color: #667085;
    text-align: center;
}

.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1200;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(16, 24, 40, 0.45);
}

.material-modal {
    width: min(680px, 100%);
    max-height: calc(100vh - 40px);
    overflow-y: auto;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 24px 72px rgba(16, 24, 40, 0.18);
}

.material-modal.compact {
    width: min(560px, 100%);
}

.material-modal.logs-modal {
    width: min(760px, 100%);
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 22px;
    border-bottom: 1px solid #eaecf0;
}

.modal-header h3 {
    margin: 0;
    color: #101828;
    font-size: 20px;
}

.modal-header p {
    margin: 4px 0 0;
    color: #667085;
    font-size: 13px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    padding: 22px;
}

.field,
.toggle-field {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.field.full,
.toggle-field.full {
    grid-column: 1 / -1;
}

.field span,
.toggle-field span {
    color: #344054;
    font-size: 13px;
    font-weight: 700;
}

.field input,
.field select,
.field textarea {
    min-height: 42px;
    padding: 10px 12px;
    border: 1px solid #d0d5dd;
    border-radius: 8px;
    resize: vertical;
}

.toggle-field {
    flex-direction: row;
    align-items: center;
}

.toggle-field input {
    width: 18px;
    height: 18px;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 18px 22px 22px;
    border-top: 1px solid #eaecf0;
}

.logs-list {
    display: flex;
    flex-direction: column;
    max-height: 560px;
    overflow-y: auto;
    padding: 10px 22px 22px;
}

.log-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 180px;
    gap: 16px;
    padding: 14px 0;
    border-bottom: 1px solid #eaecf0;
}

.log-item:last-child {
    border-bottom: 0;
}

.log-main,
.log-stock {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.log-main strong,
.log-stock strong {
    color: #101828;
}

.log-main span,
.log-stock span,
.log-stock small {
    color: #667085;
    font-size: 13px;
}

.log-main p {
    margin: 2px 0 0;
    color: #344054;
    font-size: 14px;
}

.log-stock {
    align-items: flex-end;
    text-align: right;
}

.log-stock.deduct strong {
    color: #b42318;
}

@media (max-width: 768px) {
    .summary-grid,
    .toolbar,
    .form-grid {
        grid-template-columns: 1fr;
    }

    .toolbar {
        gap: 10px;
    }

    .alert-tabs {
        width: 100%;
    }

    .alert-tabs button {
        flex: 1;
    }

    .primary-btn,
    .secondary-btn,
    .danger-btn {
        width: 100%;
    }

    .modal-actions {
        flex-direction: column-reverse;
    }

    .log-item {
        grid-template-columns: 1fr;
    }

    .log-stock {
        align-items: flex-start;
        text-align: left;
    }
}
</style>

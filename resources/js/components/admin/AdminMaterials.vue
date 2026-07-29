<script setup>
import { computed, onMounted, ref, watch, nextTick } from 'vue';
import AdminLayout from './AdminLayout.vue';
import ConfirmDialog from '../ConfirmDialog.vue';
import { useAdminAuth } from '../../composables/useAdminAuth';
import {
    AlertTriangle,
    CheckCircle2,
    EyeOff,
    History,
    PackageOpen,
    Plus,
    Save,
    Search,
    X,
    FileSpreadsheet,
    Trash2
} from 'lucide-vue-next';
import { toast } from 'vue3-toastify';
import axios from 'axios';

const { authHeader } = useAdminAuth();

const gridContainer = ref(null);
const materials = ref([]);
const loading = ref(false);
const saving = ref(false);
const searchQuery = ref('');
const statusFilter = ref('active');
const showLogsPanel = ref(false);
const viewingLogsMaterial = ref(null);
const stockLogs = ref([]);
const logsLoading = ref(false);
const showHideConfirm = ref(false);
const pendingHideMaterial = ref(null);
const showStockChangeConfirm = ref(false);
const validItemsToSave = ref([]);

const alertsCount = computed(() => materials.value.filter(item => item.id && isLowStock(item)).length);

function normalizeNumber(value) {
    const number = Number(value);
    return Number.isFinite(number) ? number : 0;
}

function isLowStock(item) {
    return item.active && normalizeNumber(item.current_stock) <= normalizeNumber(item.low_stock_threshold);
}

function formatNumber(value) {
    return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 3 }).format(normalizeNumber(value));
}

function formatCurrency(value) {
    if (value === null || value === undefined || value === '') return '';
    return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }).format(normalizeNumber(value));
}

function parseCurrency(value) {
    const cleaned = String(value).replace(/\D/g, '');
    return cleaned ? parseInt(cleaned, 10) : '';
}

async function fetchMaterials() {
    loading.value = true;
    try {
        const res = await axios.get('/api/materials', {
            headers: authHeader(),
            params: {
                search: searchQuery.value,
                status: statusFilter.value,
                per_page: 200, // Load all for spreadsheet feel
            },
        });
        
        materials.value = res.data.materials.data.map(m => ({
            ...m,
            _original: { ...m } // Keep track of original state for dirty checking
        }));
        
        // Add one empty row at the bottom for easy adding
        if (statusFilter.value !== 'inactive') {
            addNewRow(false);
        }
    } catch (error) {
        toast.error('Lỗi khi tải danh sách NVL');
    } finally {
        loading.value = false;
    }
}

function addNewRow(scroll = true) {
    materials.value.push({
        id: null,
        name: '',
        unit: 'g',
        current_stock: 0,
        low_stock_threshold: 0,
        price_per_unit: '',
        note: '',
        active: true,
        _isNew: true
    });

    if (scroll) {
        nextTick(() => {
            if (gridContainer.value) {
                gridContainer.value.scrollTop = gridContainer.value.scrollHeight;
            }
        });
    }
}

function removeRow(index) {
    materials.value.splice(index, 1);
    if (materials.value.length === 0 || !materials.value[materials.value.length - 1]._isNew) {
        addNewRow(false);
    }
}

function handleSaveClick() {
    // Filter out completely empty new rows
    const validItems = materials.value.filter(item => {
        if (item._isNew && !item.name.trim() && !item.price_per_unit) return false;
        return true;
    });

    // Validate required fields for non-empty rows
    for (let item of validItems) {
        if (!item.name.trim()) {
            toast.warning('Tên nguyên vật liệu không được để trống');
            return;
        }
        if (!item.unit.trim()) {
            toast.warning(`Vui lòng nhập đơn vị cho NVL "${item.name}"`);
            return;
        }
    }

    const hasStockChanges = validItems.some(item => !item._isNew && normalizeNumber(item.current_stock) !== normalizeNumber(item._original?.current_stock));

    if (hasStockChanges) {
        validItemsToSave.value = validItems;
        showStockChangeConfirm.value = true;
    } else {
        saveAll(validItems);
    }
}

async function saveAll(validItems) {
    saving.value = true;
    try {
        await axios.post('/api/materials/bulk', { materials: validItems }, { headers: authHeader() });
        toast.success('Đã lưu tất cả thay đổi');
        fetchMaterials();
    } catch (error) {
        toast.error(error.response?.data?.message || 'Lỗi khi lưu dữ liệu');
    } finally {
        saving.value = false;
        showStockChangeConfirm.value = false;
    }
}

function requestHideMaterial(material) {
    if (!material.id) {
        // If it's a new row that hasn't been saved, just remove it
        materials.value = materials.value.filter(m => m !== material);
        return;
    }
    pendingHideMaterial.value = material;
    showHideConfirm.value = true;
}

async function confirmHideMaterial() {
    if (!pendingHideMaterial.value?.id) return;
    try {
        await axios.delete(`/api/materials/${pendingHideMaterial.value.id}`, { headers: authHeader() });
        toast.success('Đã chuyển NVL vào danh sách đã ẩn');
        showHideConfirm.value = false;
        fetchMaterials();
    } catch (error) {
        toast.error('Lỗi khi ẩn NVL');
    }
}

async function openLogs(material) {
    if (!material.id) return;
    viewingLogsMaterial.value = material;
    showLogsPanel.value = true;
    logsLoading.value = true;
    stockLogs.value = [];

    try {
        const res = await axios.get('/api/stock/logs', {
            headers: authHeader(),
            params: { material: material.id, per_page: 30 },
        });
        stockLogs.value = res.data.logs.data;
    } catch (error) {
        toast.error('Lỗi khi tải lịch sử tồn kho');
    } finally {
        logsLoading.value = false;
    }
}

function formatDateTime(value) {
    if (!value) return '--';
    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    }).format(new Date(value));
}

function logTypeLabel(type) {
    const labels = { import: 'Nhập kho', auto_deduct: 'Trừ theo đơn', manual_deduct: 'Xuất thủ công', adjustment: 'Điều chỉnh' };
    return labels[type] || type;
}

let searchTimer = null;
watch([searchQuery, statusFilter], () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(fetchMaterials, 300);
});

onMounted(fetchMaterials);
</script>

<template>
    <AdminLayout>
        <template #title>Quản lý kho</template>

        <div class="materials-page">
            <div class="page-tabs">
                <router-link to="/admin/materials" class="page-tab" active-class="active">Danh sách nguyên vật liệu</router-link>
            </div>

            <div class="toolbar">
                <div class="search-box">
                    <Search :size="18" />
                    <input v-model="searchQuery" type="text" placeholder="Tìm tên, đơn vị, ghi chú..." />
                </div>
                <select v-model="statusFilter" class="status-filter">
                    <option value="active">Đang dùng</option>
                    <option value="inactive">Đã ẩn</option>
                    <option value="">Tất cả</option>
                </select>
                <button class="primary-btn" @click="handleSaveClick" :disabled="saving">
                    <Save :size="18" />
                    {{ saving ? 'Đang lưu...' : 'Lưu tất cả thay đổi' }}
                </button>
            </div>

            <div class="excel-grid-container" ref="gridContainer">
                <table class="excel-table">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">#</th>
                            <th style="width: 250px;">Tên hàng hóa *</th>
                            <th style="width: 100px;">ĐVT *</th>
                            <th style="width: 120px;">Tồn kho HT</th>
                            <th style="width: 120px;">Ngưỡng báo</th>
                            <th style="width: 150px;">Giá nhập (VND)</th>
                            <th>Ghi chú</th>
                            <th style="width: 100px; text-align: center;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading" class="loading-row">
                            <td colspan="8">Đang tải dữ liệu...</td>
                        </tr>
                        <tr v-else-if="materials.length === 0" class="empty-row">
                            <td colspan="8">
                                <div class="flex flex-col items-center justify-center py-16 text-center w-full" style="text-align: center;">
                                    <PackageOpen :size="48" class="text-gray-300 mb-3 mx-auto" style="margin: 0 auto 12px auto; color: #cbd5e1;" />
                                    <p class="text-lg font-medium text-gray-600" style="margin: 0; font-size: 1.125rem; font-weight: 500; color: #475569;">Không tìm thấy nguyên vật liệu nào</p>
                                    <p class="text-sm text-gray-400 mt-1" style="margin: 4px 0 0 0; font-size: 0.875rem; color: #94a3b8;">Vui lòng thay đổi bộ lọc hoặc thêm mới hàng hóa</p>
                                </div>
                            </td>
                        </tr>
                        <tr v-for="(item, index) in materials" :key="index" :class="{ 'new-row': item._isNew, 'inactive-row': !item.active }">
                            <td class="text-center text-gray-500 font-mono text-xs">{{ index + 1 }}</td>
                            <td class="p-0">
                                <input type="text" class="excel-input font-semibold" v-model="item.name" placeholder="Ví dụ: Cà phê bột" />
                            </td>
                            <td class="p-0">
                                <input type="text" class="excel-input text-center" v-model="item.unit" placeholder="kg, gram..." />
                            </td>
                            <td class="p-0 bg-gray-50">
                                <div style="position: relative; width: 100%; height: 100%; display: flex; align-items: center;">
                                    <AlertTriangle v-if="isLowStock(item)" :size="14" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #ea580c; z-index: 10; pointer-events: none;" />
                                    <input type="number" class="excel-input text-right" 
                                        :style="isLowStock(item) ? 'padding-left: 32px;' : ''"
                                        :class="{'text-orange-600 font-bold': isLowStock(item)}"
                                        v-model="item.current_stock" min="0" step="0.01" />
                                </div>
                            </td>
                            <td class="p-0">
                                <input type="number" class="excel-input text-right" v-model="item.low_stock_threshold" min="0" step="0.01" />
                            </td>
                            <td class="p-0">
                                <input type="text" class="excel-input text-right" 
                                    :value="formatCurrency(item.price_per_unit)" 
                                    @input="item.price_per_unit = parseCurrency($event.target.value)" 
                                    placeholder="0" />
                            </td>
                            <td class="p-0">
                                <input type="text" class="excel-input" v-model="item.note" placeholder="NCC, loại..." />
                            </td>
                            <td>
                                <div class="row-actions justify-center">
                                    <button v-if="item.id" title="Lịch sử tồn kho" @click="openLogs(item)" class="action-btn">
                                        <History :size="16" />
                                    </button>
                                    <button v-if="item.id" title="Ẩn NVL" @click="requestHideMaterial(item)" class="action-btn text-red-500">
                                        <EyeOff :size="16" />
                                    </button>
                                    <button v-if="item._isNew" title="Xóa dòng" @click="removeRow(index)" class="action-btn text-gray-400 hover:text-red-500">
                                        <Trash2 :size="16" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="flex justify-between items-center mt-2 px-2">
                <button v-if="statusFilter !== 'inactive'" class="add-row-btn" @click="addNewRow">
                    <Plus :size="16" /> Thêm dòng mới
                </button>
                <div class="text-sm text-gray-500">
                    * Lưu ý: Tồn kho hiện tại sẽ được cập nhật thông qua quá trình Khai báo Giao ca.
                </div>
            </div>
        </div>

        <div v-if="showLogsPanel" class="modal-backdrop" @click.self="showLogsPanel = false">
            <div class="material-modal logs-modal">
                <div class="modal-header">
                    <div>
                        <h3>Lịch sử tồn kho</h3>
                        <p v-if="viewingLogsMaterial">{{ viewingLogsMaterial.name }}</p>
                    </div>
                    <button @click="showLogsPanel = false"><X :size="20" /></button>
                </div>
                <div class="logs-list">
                    <div v-if="logsLoading" class="flex flex-col items-center justify-center py-12 text-center w-full" style="text-align: center; padding: 48px 0;">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-600 mb-4 mx-auto" style="margin: 0 auto 16px auto;"></div>
                        <p class="text-gray-500 font-medium" style="margin: 0; color: #64748b;">Đang tải lịch sử...</p>
                    </div>
                    <div v-else-if="stockLogs.length === 0" class="flex flex-col items-center justify-center py-12 text-center w-full" style="text-align: center; padding: 48px 0;">
                        <History :size="48" class="text-gray-300 mb-3 mx-auto" style="margin: 0 auto 12px auto; color: #cbd5e1;" />
                        <p class="text-lg font-medium text-gray-600" style="margin: 0; font-size: 1.125rem; font-weight: 500; color: #475569;">Chưa có lịch sử tồn kho</p>
                        <p class="text-sm text-gray-400 mt-1" style="margin: 4px 0 0 0; font-size: 0.875rem; color: #94a3b8;">Chưa có giao dịch nhập/xuất nào được ghi nhận</p>
                    </div>
                    <div v-for="log in stockLogs" v-else :key="log.id" class="log-item">
                        <div class="log-main">
                            <strong>{{ logTypeLabel(log.type) }}</strong>
                            <span>{{ formatDateTime(log.created_at) }} · {{ log.creator?.name || 'Hệ thống' }}</span>
                            <p v-if="log.note">{{ log.note }}</p>
                        </div>
                        <div class="log-stock" :class="{ deduct: normalizeNumber(log.quantity) < 0 }">
                            <strong>{{ normalizeNumber(log.quantity) > 0 ? '+' : '' }}{{ formatNumber(log.quantity) }} {{ viewingLogsMaterial?.unit }}</strong>
                            <span>{{ formatNumber(log.stock_before) }} → {{ formatNumber(log.stock_after) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmDialog
            :show="showHideConfirm"
            title="Ẩn nguyên vật liệu"
            :message="`Bạn có chắc muốn chuyển NVL '${pendingHideMaterial?.name || ''}' vào danh sách ẩn?`"
            confirm-text="Đồng ý"
            cancel-text="Hủy"
            type="warning"
            @confirm="confirmHideMaterial"
            @cancel="showHideConfirm = false"
        />

        <ConfirmDialog
            :show="showStockChangeConfirm"
            title="Xác nhận lưu thay đổi"
            message="Xác nhận chỉnh sửa tồn kho có thể sai với số liệu giao ca của nhân viên. Bạn có chắc chắn muốn lưu?"
            confirm-text="Đồng ý lưu"
            cancel-text="Hủy"
            type="warning"
            @confirm="saveAll(validItemsToSave)"
            @cancel="showStockChangeConfirm = false"
        />
    </AdminLayout>
</template>

<style scoped>
.materials-page { display: flex; flex-direction: column; gap: 16px; height: calc(100vh - 100px); }
.page-tabs { display: flex; gap: 12px; border-bottom: 1px solid #e2e8f0; margin-bottom: 4px; }
.page-tab { padding: 12px 24px; color: #64748b; text-decoration: none; font-weight: 600; font-size: 0.95rem; border-bottom: 2px solid transparent; transition: all 0.2s; }
.page-tab:hover { color: #1e293b; }
.page-tab.active { color: #20451f; border-bottom-color: #20451f; }

.toolbar { display: flex; gap: 12px; justify-content: flex-end; align-items: center; }
.search-box { flex: 1; max-width: 350px; position: relative; display: flex; align-items: center; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0 12px; }
.search-box input { flex: 1; border: none; padding: 10px; outline: none; background: transparent; font-size: 0.9rem; }
.search-box svg { color: #94a3b8; }
.status-filter { padding: 8px 32px 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; font-size: 0.9rem; outline: none; }
.primary-btn { display: flex; align-items: center; gap: 8px; padding: 10px 20px; background: #20451f; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; }
.primary-btn:hover:not(:disabled) { background: #153314; }
.primary-btn:disabled { opacity: 0.6; cursor: not-allowed; }

/* Excel Grid Styles */
.excel-grid-container { flex: 1; overflow: auto; background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
.excel-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem; }
.excel-table th { background: #f8fafc; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; padding: 12px; font-weight: 700; color: #475569; position: sticky; top: 0; z-index: 10; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; }
.excel-table td { border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; vertical-align: middle; }
.excel-table tbody tr:hover { background: #f8fafc; }
.inactive-row { opacity: 0.6; background: #f1f5f9; }

.excel-input { width: 100%; height: 100%; min-height: 42px; padding: 8px 12px; border: none; background: transparent; outline: none; font-size: 0.875rem; color: #1e293b; transition: all 0.2s; box-sizing: border-box; }
.excel-input:focus { background: #fff; box-shadow: inset 0 0 0 2px #3b82f6; }
.excel-input::placeholder { color: #cbd5e1; }
.readonly-cell { padding: 10px 12px; color: #64748b; font-weight: 600; min-height: 42px; }

.row-actions { display: flex; gap: 8px; padding: 0 8px; }
.action-btn { background: none; border: none; padding: 6px; border-radius: 6px; cursor: pointer; color: #64748b; transition: 0.2s; display: flex; align-items: center; justify-content: center; }
.action-btn:hover { background: #e2e8f0; color: #1e293b; }

.add-row-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; font-weight: 600; font-size: 0.9rem; color: #20451f; background: #ecfdf5; border: 1px dashed #10b981; border-radius: 8px; cursor: pointer; transition: 0.2s; }
.add-row-btn:hover { background: #d1fae5; }

/* Modals */
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(4px); }
.material-modal { background: white; width: 95%; max-width: 500px; border-radius: 16px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); display: flex; flex-direction: column; max-height: 90vh; }
.modal-header { padding: 16px 24px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { margin: 0; font-size: 1.1rem; font-weight: 800; color: #1e293b; }
.modal-header p { margin: 4px 0 0 0; font-size: 0.85rem; color: #64748b; }
.modal-header button { background: none; border: none; cursor: pointer; color: #94a3b8; }

.logs-list { overflow-y: auto; padding: 0; flex: 1; }
.log-item { display: flex; justify-content: space-between; padding: 16px 24px; border-bottom: 1px solid #f0f0f0; }
.log-main strong { display: block; font-size: 0.9rem; color: #1e293b; }
.log-main span { display: block; font-size: 0.8rem; color: #64748b; margin-top: 4px; }
.log-main p { margin: 8px 0 0; font-size: 0.85rem; color: #475569; padding: 8px; background: #f8fafc; border-radius: 6px; }
.log-stock { text-align: right; }
.log-stock strong { display: block; font-size: 1.1rem; color: #16a34a; }
.log-stock.deduct strong { color: #dc2626; }
.log-stock span { display: block; font-size: 0.8rem; color: #64748b; margin-top: 4px; }

@media (max-width: 768px) {
    .materials-page { height: auto; min-height: calc(100vh - 100px); }
    .toolbar { flex-direction: column; align-items: stretch; }
    .search-box { max-width: 100%; }
    .status-filter { width: 100%; }
    .primary-btn { justify-content: center; }
    .excel-table { min-width: 800px; }
    .page-tabs { overflow-x: auto; white-space: nowrap; flex-wrap: nowrap; }
    .page-tab { flex: 0 0 auto; }
}
</style>

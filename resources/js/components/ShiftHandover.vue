<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import AdminLayout from './admin/AdminLayout.vue';
import { useAdminAuth } from '../composables/useAdminAuth';
import { toast } from 'vue3-toastify';
import axios from 'axios';
import {
    AlertTriangle,
    CheckCircle2,
    ClipboardPenLine,
    Eye,
    Plus,
    RefreshCw,
    Save,
    Trash2,
    XCircle,
} from 'lucide-vue-next';

const { authHeader } = useAdminAuth();
const route = useRoute();

const loading = ref(false);
const saving = ref(false);
const activeTab = ref('create');
const prepare = ref(null);
const handovers = ref([]);
const selected = ref(null);
const disputeNote = ref('');
const exportMonth = ref(new Date().toISOString().slice(0, 7));
const menuProducts = ref([]);
const filters = ref({
    date: '',
    shift_type: '',
    status: '',
    employee_id: '',
});

const form = ref(defaultForm());

const pendingHandovers = computed(() => handovers.value.filter((item) => item.status === 'pending'));
const materialOptions = computed(() => prepare.value?.materials || []);
const selectedProductIds = computed(() => form.value.sold_products.map((item) => Number(item.product_id)).filter(Boolean));
const selectedDamagedMaterialIds = computed(() => form.value.damaged_materials.map((item) => Number(item.material_id)).filter(Boolean));
const selectedSoldProducts = computed(() => (
    form.value.sold_products
        .map((item) => {
            const product = menuProducts.value.find((p) => Number(p.id) === Number(item.product_id));
            return product ? { item, product } : null;
        })
        .filter(Boolean)
));
const soldProductTotalQuantity = computed(() => (
    form.value.sold_products.reduce((sum, item) => sum + Number(item.quantity || 0), 0)
));
const expectedEndingCash = computed(() => Number(form.value.opening_cash || 0) + Number(form.value.revenue_cash || 0));
const shiftReport = computed(() => prepare.value?.report || null);
const reportSummary = computed(() => shiftReport.value?.summary || {
    total_orders: form.value.total_orders,
    total_revenue: Number(form.value.revenue_cash || 0) + Number(form.value.revenue_transfer || 0),
    average_order_value: 0,
    cash_total: form.value.revenue_cash,
    transfer_total: form.value.revenue_transfer,
});
const reportCashFlow = computed(() => {
    const openingCash = Number(form.value.opening_cash || 0);
    const cashIn = Number(reportSummary.value.cash_total || 0);
    const theoretical = Number(expectedEndingCash.value || 0);

    return [
        { label: 'Số dư đầu ca', amount: openingCash },
        { label: 'Thu tiền mặt trong ca', amount: cashIn },
        { label: 'Tiền trong két dự kiến', amount: theoretical },
    ];
});

const cashDiff = computed(() => {
    return Number(form.value.cash_actual || 0) - expectedEndingCash.value;
});

function defaultForm() {
    return {
        date: '',
        shift_type: 'sang',
        opening_cash: 0,
        cash_theoretical: 0,
        cash_actual: '',
        cash_note: '',
        total_orders: 0,
        revenue_cash: 0,
        revenue_transfer: 0,
        sold_products: [],
        damaged_materials: [],
        materials: [],
        equipment_checklist: {
            may_pha: { ok: true, note: '' },
            may_in: { ok: true, note: '' },
            tablet: { ok: true, note: '' },
            dieu_hoa: { ok: true, note: '' },
            khoa_cua: { ok: true, note: '' },
        },
        handover_note: '',
    };
}

function formatMoney(value) {
    return `${new Intl.NumberFormat('vi-VN').format(Number(value || 0))}đ`;
}

function formatNumber(value) {
    return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 3 }).format(Number(value || 0));
}

function formatPercent(value, total) {
    if (!Number(total || 0)) return '0%';
    return `${Math.round(Number(value || 0) / Number(total) * 100)}%`;
}

function formatDateTime(value) {
    if (!value) return '';
    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function reportFor(handover) {
    return handover?.report_snapshot || null;
}

function shiftLabel(shiftType) {
    return {
        sang: 'Ca sáng',
        chieu: 'Ca chiều',
    }[shiftType] || shiftType;
}

function equipmentLabel(key) {
    return {
        may_pha: 'Máy pha cà phê',
        may_in: 'Máy in',
        tablet: 'Tablet',
        dieu_hoa: 'Điều hoà',
        khoa_cua: 'Khoá cửa',
    }[key] || key;
}

function statusLabel(status) {
    return {
        pending: 'Nhận ca',
        confirmed: 'Đã nhận ca',
        disputed: 'Có sai lệch',
    }[status] || status;
}

function addSoldProductRow() {
    form.value.sold_products.push({
        product_id: '',
        quantity: 1,
    });
}

function removeSoldProduct(productId) {
    form.value.sold_products = form.value.sold_products.filter((item) => item !== productId);
}

function productOptionsFor(item) {
    return menuProducts.value.filter((product) => (
        Number(product.id) === Number(item.product_id)
        || !selectedProductIds.value.includes(Number(product.id))
    ));
}

function normalizeSoldProductQuantity(item) {
    item.quantity = Math.max(1, Number(item.quantity || 1));
}

function addDamagedMaterialRow() {
    form.value.damaged_materials.push({
        material_id: '',
        quantity: 1,
        note: '',
    });
}

function removeDamagedMaterialRow(item) {
    form.value.damaged_materials = form.value.damaged_materials.filter((row) => row !== item);
}

function materialOptionsFor(item) {
    return materialOptions.value.filter((material) => (
        Number(material.id) === Number(item.material_id)
        || !selectedDamagedMaterialIds.value.includes(Number(material.id))
    ));
}

function materialLabel(materialId) {
    const material = materialOptions.value.find((item) => Number(item.id) === Number(materialId));
    return material ? `${material.name} (${material.unit})` : 'Chọn NVL';
}

async function fetchPrepare() {
    loading.value = true;
    try {
        const res = await axios.get('/api/shift-handover/prepare', { headers: authHeader() });
        prepare.value = res.data;
        menuProducts.value = res.data.products || [];
        form.value = {
            ...defaultForm(),
            date: res.data.date,
            shift_type: res.data.shift_type,
            opening_cash: res.data.cash_received_previous,
            cash_theoretical: res.data.cash_theoretical,
            total_orders: res.data.total_orders,
            revenue_cash: res.data.revenue_cash,
            revenue_transfer: res.data.revenue_transfer,
            materials: (res.data.materials || []).map((material) => ({
                material_id: material.id,
                material_name: material.name,
                unit: material.unit,
                theoretical: Number(material.current_stock),
                actual: Number(material.current_stock),
                reason: '',
            })),
        };
    } catch (error) {
        toast.error('Lỗi khi tải dữ liệu giao ca');
    } finally {
        loading.value = false;
    }
}

async function fetchHandovers() {
    try {
        const res = await axios.get('/api/shift-handover', {
            headers: authHeader(),
            params: {
                per_page: 50,
                ...Object.fromEntries(Object.entries(filters.value).filter(([, value]) => value !== '')),
            },
        });
        handovers.value = res.data.handovers.data;
    } catch (error) {
        toast.error('Lỗi khi tải lịch sử giao ca');
    }
}

function resetFilters() {
    filters.value = {
        date: '',
        shift_type: '',
        status: '',
        employee_id: '',
    };
    fetchHandovers();
}

async function saveHandover() {
    form.value.cash_theoretical = expectedEndingCash.value;

    const payload = {
        ...form.value,
        sold_products: form.value.sold_products.filter((item) => item.product_id && Number(item.quantity || 0) > 0),
        damaged_materials: form.value.damaged_materials.filter((item) => item.material_id && Number(item.quantity || 0) > 0),
    };

    saving.value = true;
    try {
        await axios.post('/api/shift-handover', payload, { headers: authHeader() });
        toast.success('Đã lưu biên bản giao ca');
        await Promise.all([fetchPrepare(), fetchHandovers()]);
        activeTab.value = 'pending';
    } catch (error) {
        toast.error(error.response?.data?.message || 'Lỗi khi lưu giao ca');
    } finally {
        saving.value = false;
    }
}

async function confirmHandover(handover) {
    try {
        await axios.post(`/api/shift-handover/${handover.id}/confirm`, {}, { headers: authHeader() });
        toast.success('Đã xác nhận nhận ca');
        selected.value = null;
        disputeNote.value = '';
        await fetchHandovers();
    } catch (error) {
        toast.error(error.response?.data?.message || 'Lỗi khi xác nhận nhận ca');
    }
}

async function disputeHandover(handover) {
    if (!disputeNote.value.trim()) {
        toast.warning('Vui lòng nhập nội dung sai lệch');
        return;
    }
    try {
        await axios.post(`/api/shift-handover/${handover.id}/dispute`, {
            dispute_note: disputeNote.value,
        }, { headers: authHeader() });
        toast.success('Đã báo cáo sai lệch');
        disputeNote.value = '';
        selected.value = null;
        await fetchHandovers();
    } catch (error) {
        toast.error(error.response?.data?.message || 'Lỗi khi báo cáo sai lệch');
    }
}

function openDetail(handover) {
    selected.value = handover;
    disputeNote.value = '';
}

async function exportHandovers() {
    try {
        const res = await axios.get(`/api/shift-handover/export/${exportMonth.value}`, {
            headers: authHeader(),
            responseType: 'blob',
        });
        const url = window.URL.createObjectURL(new Blob([res.data], { type: 'text/csv;charset=utf-8;' }));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `shift-handovers-${exportMonth.value}.csv`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        toast.error('Lỗi khi export giao ca');
    }
}

onMounted(async () => {
    if (['create', 'pending', 'history'].includes(route.query.tab)) {
        activeTab.value = route.query.tab;
    }
    await Promise.all([fetchPrepare(), fetchHandovers()]);
});
</script>

<template>
    <AdminLayout>
        <template #title>Checklist giao ca</template>

        <div class="sh-page">
            <div class="sh-tabs">
                <button :class="{ active: activeTab === 'create' }" @click="activeTab = 'create'">
                    <ClipboardPenLine :size="18" /> Tạo giao ca
                </button>
                <button :class="{ active: activeTab === 'pending' }" @click="activeTab = 'pending'">
                    <CheckCircle2 :size="18" /> Nhận ca
                    <span v-if="pendingHandovers.length">{{ pendingHandovers.length }}</span>
                </button>
                <button :class="{ active: activeTab === 'history' }" @click="activeTab = 'history'">
                    <Eye :size="18" /> Lịch sử
                </button>
            </div>

            <section v-if="activeTab === 'create'" class="sh-card">
                <div class="sh-header">
                    <div>
                        <h3>Bắt đầu giao ca</h3>
                        <p>{{ form.date }} · Ca {{ form.shift_type }}</p>
                    </div>
                    <button class="sh-secondary" @click="fetchPrepare">
                        <RefreshCw :size="18" /> Làm mới
                    </button>
                </div>

                <div v-if="loading" class="sh-empty">Đang tải dữ liệu...</div>
                <template v-else>
                    <div v-if="prepare && !prepare.can_create" class="sh-schedule-warning">
                        <AlertTriangle :size="18" />
                        Bạn không có lịch làm ca {{ form.shift_type }} ngày {{ form.date }}, nên không thể tạo biên bản giao ca này.
                    </div>

                    <div class="sh-shift-info">
                        <div>
                            <span>Ca giao</span>
                            <strong>{{ shiftLabel(form.shift_type) }}</strong>
                        </div>
                        <div>
                            <span>Ngày giao</span>
                            <strong>{{ form.date }}</strong>
                        </div>
                        <div>
                            <span>Thời gian tính ca</span>
                            <strong>{{ formatDateTime(shiftReport?.period_start) }} - {{ formatDateTime(shiftReport?.period_end) }}</strong>
                        </div>
                    </div>

                    <div class="sh-block">
                        <h4>1. Tiền bàn giao</h4>
                        <div class="sh-grid two compact">
                            <label class="sh-field">
                                <span>Tiền bàn giao đầu ca</span>
                                <input v-model.number="form.opening_cash" type="number" min="0" />
                            </label>
                            <label class="sh-field">
                                <span>Tiền cuối ca</span>
                                <input v-model.number="form.cash_actual" type="number" min="0" />
                            </label>
                        </div>
                    </div>

                    <div class="sh-block">
                        <div class="sh-block-head">
                            <div>
                                <h4>2. Hàng bán trong ca</h4>
                                <p>Thêm món bán từ POS để tính NVL đã dùng.</p>
                            </div>
                            <button type="button" class="sh-add-btn" @click="addSoldProductRow">
                                <Plus :size="17" /> Thêm món
                            </button>
                        </div>
                        <div class="sh-compact-list" v-if="form.sold_products.length">
                            <div v-for="item in form.sold_products" :key="item" class="sh-compact-row">
                                <select v-model="item.product_id">
                                    <option value="">Chọn món</option>
                                    <option v-for="product in productOptionsFor(item)" :key="product.id" :value="product.id">
                                        {{ product.name }} · {{ product.category }}
                                    </option>
                                </select>
                                <input v-model.number="item.quantity" type="number" min="1" @blur="normalizeSoldProductQuantity(item)" />
                                <button type="button" class="sh-icon-danger" @click="removeSoldProduct(item)">
                                    <Trash2 :size="17" />
                                </button>
                            </div>
                        </div>
                        <div v-else class="sh-inline-empty">Chưa thêm món bán</div>
                    </div>

                    <div class="sh-block">
                        <div class="sh-block-head">
                            <div>
                                <h4>3. Ghi chú & NVL hỏng</h4>
                                <p>Ghi chú chung và thêm từng NVL hỏng nếu có.</p>
                            </div>
                            <button type="button" class="sh-add-btn" @click="addDamagedMaterialRow">
                                <Plus :size="17" /> Thêm NVL hỏng
                            </button>
                        </div>
                        <label class="sh-field no-top">
                            <span>Ghi chú bàn giao</span>
                            <textarea v-model="form.handover_note" rows="4" placeholder="Sự cố trong ca, việc cần chuyển giao, yêu cầu đặt hàng..."></textarea>
                        </label>
                        <div class="sh-compact-list" v-if="form.damaged_materials.length">
                            <div class="sh-list-title">NVL hỏng</div>
                            <div class="sh-compact-row damaged head">
                                <span>NVL</span>
                                <span>SL hỏng</span>
                                <span>Ghi chú</span>
                                <span></span>
                            </div>
                            <div v-for="item in form.damaged_materials" :key="item" class="sh-compact-row damaged">
                                <select v-model="item.material_id">
                                    <option value="">Chọn NVL</option>
                                    <option v-for="material in materialOptionsFor(item)" :key="material.id" :value="material.id">
                                        {{ material.name }} · {{ material.unit }}
                                    </option>
                                </select>
                                <input v-model.number="item.quantity" type="number" min="0.001" step="0.001" />
                                <input v-model="item.note" type="text" placeholder="Lý do hỏng" />
                                <button type="button" class="sh-icon-danger" @click="removeDamagedMaterialRow(item)">
                                    <Trash2 :size="17" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="sh-actions">
                        <button class="sh-primary" :disabled="saving || (prepare && !prepare.can_create)" @click="saveHandover">
                            <Save :size="18" /> {{ saving ? 'Đang lưu...' : 'Lưu biên bản giao ca' }}
                        </button>
                    </div>
                </template>
            </section>

            <section v-else class="sh-card">
                <div class="sh-header">
                    <div>
                        <h3>{{ activeTab === 'pending' ? 'Biên bản nhận ca' : 'Lịch sử giao ca' }}</h3>
                        <p>{{ handovers.length }} biên bản</p>
                    </div>
                    <div class="sh-header-actions">
                        <template v-if="activeTab === 'history'">
                            <input v-model="exportMonth" class="sh-month" type="month" />
                            <button class="sh-secondary" @click="exportHandovers">Export</button>
                        </template>
                        <button class="sh-secondary" @click="fetchHandovers">
                            <RefreshCw :size="18" /> Làm mới
                        </button>
                    </div>
                </div>
                <div v-if="activeTab === 'history'" class="sh-filters">
                    <input v-model="filters.date" type="date" />
                    <select v-model="filters.shift_type">
                        <option value="">Tất cả ca</option>
                        <option value="sang">Ca sáng</option>
                        <option value="chieu">Ca chiều</option>
                    </select>
                    <select v-model="filters.status">
                        <option value="">Tất cả trạng thái</option>
                        <option value="pending">Nhận ca</option>
                        <option value="confirmed">Đã nhận ca</option>
                        <option value="disputed">Có sai lệch</option>
                    </select>
                    <select v-model="filters.employee_id">
                        <option value="">Tất cả nhân viên</option>
                        <option v-for="staff in prepare?.staff || []" :key="staff.id" :value="staff.id">
                            {{ staff.name }}
                        </option>
                    </select>
                    <button class="sh-secondary" @click="fetchHandovers">Lọc</button>
                    <button class="sh-secondary" @click="resetFilters">Xoá lọc</button>
                </div>
                <div class="sh-list">
                    <div v-for="handover in (activeTab === 'pending' ? pendingHandovers : handovers)" :key="handover.id" class="sh-list-item" :class="{ alert: handover.has_alert }">
                        <div>
                            <strong>{{ handover.date }} · Ca {{ handover.shift_type }}</strong>
                            <span>{{ handover.outgoing_employee?.name || 'Không rõ' }} → {{ handover.incoming_employee?.name || 'Chưa nhận' }}</span>
                        </div>
                        <div class="sh-list-meta">
                            <span :class="handover.status">{{ statusLabel(handover.status) }}</span>
                            <em v-if="handover.has_alert"><AlertTriangle :size="16" /> Có cảnh báo</em>
                            <em v-if="handover.dispute_note" class="dispute"><XCircle :size="16" /> Có ghi chú sai lệch</em>
                            <button @click="openDetail(handover)">Chi tiết</button>
                        </div>
                    </div>
                    <div v-if="(activeTab === 'pending' ? pendingHandovers : handovers).length === 0" class="sh-empty">Chưa có biên bản</div>
                </div>
            </section>

            <div v-if="selected" class="sh-modal-backdrop" @click.self="selected = null">
                <div class="sh-modal">
                    <div class="sh-header">
                        <div>
                            <h3>Chi tiết nhận ca #{{ selected.id }}</h3>
                        </div>
                        <button class="sh-icon-btn" @click="selected = null"><XCircle :size="20" /></button>
                    </div>
                    <div class="sh-modal-body">
                        <div class="sh-shift-info">
                            <div>
                                <span>Ca nhận</span>
                                <strong>{{ shiftLabel(selected.receive_shift_type || selected.shift_type) }}</strong>
                            </div>
                            <div>
                                <span>Thời gian ghi nhận giao ca</span>
                                <strong>{{ formatDateTime(selected.handover_at) }}</strong>
                            </div>
                            <div>
                                <span>Người giao</span>
                                <strong>{{ selected.outgoing_employee?.name || 'Không rõ' }}</strong>
                            </div>
                        </div>

                        <h4>1. Tiền nhận đầu ca</h4>
                        <div class="sh-grid compact">
                            <div class="sh-stats"><span>Tiền nhận từ ca trước</span><strong>{{ formatMoney(selected.cash_actual) }}</strong></div>
                        </div>

                        <h4>2.Thống kê NVL</h4>
                        <div class="sh-snapshot">
                            <div class="sh-snapshot-row head">
                                <span>NVL</span>
                                <span>Đã dùng</span>
                                <span>Còn lại</span>
                                <span>Trạng thái</span>
                            </div>
                            <div v-for="item in selected.nvl_snapshot" :key="item.material_id" class="sh-snapshot-row" :class="{ warn: item.has_alert }">
                                <strong>{{ item.material_name }}</strong>
                                <span>Đã dùng {{ formatNumber(item.required ?? item.diff) }} {{ item.unit }}</span>
                                <span>Còn lại {{ formatNumber(item.actual) }} {{ item.unit }}</span>
                                <em>{{ item.has_alert ? (item.reason || 'Đang cảnh báo') : 'Ổn' }}</em>
                            </div>
                            <div v-if="!selected.nvl_snapshot?.length" class="sh-empty compact">Chưa có dữ liệu NVL</div>
                        </div>

                        <h4>3. Ghi chú & NVL hỏng</h4>
                        <p class="sh-note">{{ selected.handover_note || 'Không có' }}</p>
                        <div v-if="selected.damaged_materials?.length" class="sh-damaged-list">
                            <div class="sh-list-title">NVL hỏng</div>
                            <div class="sh-damaged-item head">
                                <span>NVL</span>
                                <span>SL hỏng</span>
                                <span>Ghi chú</span>
                            </div>
                            <div v-for="item in selected.damaged_materials" :key="item.material_id" class="sh-damaged-item">
                                <strong>{{ item.material_name }}</strong>
                                <span>{{ formatNumber(item.quantity) }} {{ item.unit }}</span>
                                <em>{{ item.note || 'Không ghi lý do' }}</em>
                            </div>
                        </div>
                        <template v-if="selected.dispute_note">
                            <h4>Ghi chú sai lệch</h4>
                            <p class="sh-note dispute">{{ selected.dispute_note }}</p>
                        </template>
                        <template v-if="selected.status === 'pending'">
                            <div v-if="!selected.can_confirm" class="sh-schedule-warning">
                                <AlertTriangle :size="18" />
                                Bạn không có lịch làm ca {{ selected.receive_shift_type }} ngày {{ selected.receive_date }}, nên không thể nhận biên bản này.
                            </div>
                            <textarea v-model="disputeNote" rows="3" placeholder="Nhập nội dung sai lệch nếu không đồng ý"></textarea>
                            <div class="sh-actions">
                                <button class="sh-secondary danger" :disabled="!selected.can_confirm" @click="disputeHandover(selected)">Báo cáo sai lệch</button>
                                <button class="sh-primary" :disabled="!selected.can_confirm" @click="confirmHandover(selected)">Xác nhận nhận ca</button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.sh-page { max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; gap: 16px; }
.sh-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
.sh-tabs button, .sh-secondary, .sh-primary, .sh-icon-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 40px; padding: 0 14px; border-radius: 8px; font-weight: 800; cursor: pointer; }
.sh-tabs button { border: 1px solid #d0d5dd; background: #fff; color: #344054; }
.sh-tabs button.active { background: #20451f; color: #fff; border-color: #20451f; }
.sh-tabs span { min-width: 22px; height: 22px; border-radius: 999px; background: #dc2626; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; }
.sh-card, .sh-block { background: #fff; border: 1px solid #e8ece8; border-radius: 8px; }
.sh-card { padding: 18px; }
.sh-card > .sh-block + .sh-block { margin-top: 18px; }
.sh-block { padding: 20px; }
.sh-block.nested { border: 0; background: #f9fafb; }
.sh-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; margin-bottom: 16px; }
.sh-header-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
.sh-month { width: 150px; }
.sh-filters { display: grid; grid-template-columns: 160px 150px 180px minmax(180px, 1fr) auto auto; gap: 10px; margin-bottom: 16px; align-items: center; }
.sh-header h3, .sh-block h4, .sh-modal-body h4 { margin: 0; color: #101828; }
.sh-header p { margin: 4px 0 0; color: #667085; font-weight: 700; }
.sh-shift-info { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-bottom: 16px; }
.sh-shift-info div { padding: 12px; border: 1px solid #e4e7ec; border-radius: 8px; background: #f9fafb; }
.sh-shift-info span { display: block; color: #667085; font-size: 12px; font-weight: 900; text-transform: uppercase; }
.sh-shift-info strong { display: block; margin-top: 4px; color: #101828; overflow-wrap: anywhere; }
.sh-subtitle { margin-top: 16px !important; }
.sh-report-hero { display: grid; grid-template-columns: minmax(240px, .8fr) minmax(0, 1.2fr); gap: 14px; margin-bottom: 16px; padding: 16px; border: 1px solid #dce7dc; border-radius: 8px; background: #f7fbf4; }
.sh-report-title { display: flex; flex-direction: column; justify-content: center; gap: 6px; }
.sh-report-title span { color: #20451f; font-size: 13px; font-weight: 900; text-transform: uppercase; }
.sh-report-title strong { color: #101828; font-size: 18px; }
.sh-report-kpis { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.sh-report-kpis div { padding: 12px; border: 1px solid #e4ece0; border-radius: 8px; background: #fff; }
.sh-report-kpis span { display: block; color: #667085; font-size: 12px; font-weight: 900; }
.sh-report-kpis strong { display: block; margin-top: 4px; color: #101828; font-size: 19px; }
.sh-report-table { display: flex; flex-direction: column; margin-top: 10px; border: 1px solid #eaecf0; border-radius: 8px; overflow: hidden; }
.sh-report-row { display: grid; grid-template-columns: minmax(0, 1fr) 72px 130px; gap: 10px; align-items: center; min-height: 42px; padding: 8px 10px; border-bottom: 1px solid #eaecf0; }
.sh-report-row:last-child { border-bottom: 0; }
.sh-report-row.head { min-height: 36px; background: #f9fafb; color: #667085; font-size: 12px; font-weight: 900; text-transform: uppercase; }
.sh-report-row strong { min-width: 0; overflow-wrap: anywhere; color: #101828; }
.sh-report-row span:not(:first-child) { text-align: right; font-weight: 800; color: #344054; }
.sh-report-bars { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; }
.sh-report-bar { display: flex; align-items: center; justify-content: space-between; gap: 10px; min-height: 48px; padding: 10px 12px; border: 1px solid #eaecf0; border-radius: 8px; background: #fff; }
.sh-report-bar strong, .sh-report-bar span { display: block; }
.sh-report-bar strong { color: #101828; }
.sh-report-bar span { margin-top: 2px; color: #667085; font-size: 12px; font-weight: 800; }
.sh-report-bar em { min-width: 44px; color: #20451f; font-style: normal; font-weight: 900; text-align: right; }
.sh-block-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; margin-bottom: 18px; }
.sh-block-head p { margin: 4px 0 0; color: #667085; font-size: 13px; font-weight: 700; }
.sh-add-btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 38px; padding: 0 12px; border: 1px solid #20451f; border-radius: 8px; background: #fff; color: #20451f; font-weight: 900; cursor: pointer; white-space: nowrap; }
.sh-sold-total { min-width: 70px; padding: 8px 10px; border-radius: 8px; background: #f0fdf4; color: #15803d; text-align: center; font-weight: 900; }
.sh-product-picker { display: flex; flex-direction: column; gap: 10px; }
.sh-product-results { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 8px; max-height: 320px; overflow-y: auto; padding-right: 4px; }
.sh-product-option { display: flex; flex-direction: column; align-items: flex-start; gap: 3px; min-height: 64px; padding: 10px 12px; border: 1px solid #e4e7ec; border-radius: 8px; background: #fff; color: #101828; text-align: left; cursor: pointer; }
.sh-product-option:hover { border-color: #20451f; background: #f8fbf6; }
.sh-product-option span, .sh-sold-row span { color: #667085; font-size: 12px; font-weight: 700; }
.sh-product-option em, .sh-sold-row em { color: #c2410c; font-size: 12px; font-style: normal; font-weight: 800; }
.sh-product-option.warning, .sh-sold-row.warning { border-color: #fed7aa; background: #fff7ed; }
.sh-sold-list { display: flex; flex-direction: column; gap: 8px; margin-top: 12px; }
.sh-sold-row { display: grid; grid-template-columns: minmax(0, 1fr) 100px auto; gap: 10px; align-items: center; padding: 10px 12px; border: 1px solid #eaecf0; border-radius: 8px; }
.sh-sold-row strong, .sh-sold-row span { display: block; }
.sh-sold-row input { text-align: center; }
.sh-sold-row button { min-height: 38px; border: 1px solid #fecaca; border-radius: 8px; background: #fff; color: #b42318; font-weight: 800; cursor: pointer; }
.sh-compact-list { display: flex; flex-direction: column; gap: 8px; margin-top: 12px; }
.sh-list-title { color: #344054; font-size: 13px; font-weight: 900; }
.sh-compact-row { display: grid; grid-template-columns: minmax(0, 1fr) 110px 42px; gap: 8px; align-items: center; }
.sh-compact-row.damaged { grid-template-columns: minmax(0, 1fr) 110px minmax(160px, .8fr) 42px; }
.sh-compact-row.head { min-height: 32px; padding: 0 4px; color: #667085; font-size: 12px; font-weight: 900; text-transform: uppercase; }
.sh-compact-row input[type="number"] { text-align: center; }
.sh-icon-danger { display: inline-flex; align-items: center; justify-content: center; width: 42px; height: 40px; border: 1px solid #fecaca; border-radius: 8px; background: #fff; color: #b42318; cursor: pointer; }
.sh-inline-empty { padding: 14px; margin-top: 12px; border: 1px dashed #d0d5dd; border-radius: 8px; color: #667085; text-align: center; font-weight: 800; }
.sh-sold-detail { display: flex; flex-wrap: wrap; gap: 8px; }
.sh-sold-detail span { padding: 7px 10px; border-radius: 999px; background: #f2f4f7; color: #344054; font-weight: 800; }
.sh-grid { display: grid; gap: 16px; margin-bottom: 16px; }
.sh-grid.two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.sh-grid.compact { gap: 10px; margin-bottom: 0; }
.sh-field { display: flex; flex-direction: column; gap: 7px; margin-top: 12px; }
.sh-field.no-top { margin-top: 0; }
.sh-field span { color: #344054; font-weight: 800; font-size: 13px; }
input, textarea, select { width: 100%; min-height: 40px; border: 1px solid #d0d5dd; border-radius: 8px; padding: 8px 10px; font: inherit; outline: 0; }
textarea { resize: vertical; }
.sh-stats { padding: 12px; background: #f9fafb; border-radius: 8px; }
.sh-stats span { display: block; color: #667085; font-size: 13px; font-weight: 800; }
.sh-stats strong { display: block; margin-top: 4px; color: #101828; font-size: 20px; }
.sh-cash-breakdown { display: flex; flex-direction: column; gap: 4px; margin-top: 8px; padding: 10px; border-radius: 8px; background: #f9fafb; color: #667085; font-size: 13px; font-weight: 800; }
.sh-diff { margin-top: 10px; padding: 10px; border-radius: 8px; background: #f0fdf4; color: #15803d; font-weight: 900; }
.sh-schedule-warning { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; padding: 12px 14px; border: 1px solid #fed7aa; border-radius: 8px; background: #fff7ed; color: #9a3412; font-weight: 800; }
.sh-diff.warn, .sh-material-row.warn, .sh-list-item.alert, .sh-snapshot-row.warn { background: #fff7ed; color: #9a3412; }
.sh-materials { overflow-x: auto; border: 1px solid #eaecf0; border-radius: 8px; }
.sh-material-row { display: grid; grid-template-columns: minmax(180px, 1fr) 150px 140px minmax(180px, 1fr); gap: 10px; align-items: center; min-width: 760px; padding: 10px 12px; border-bottom: 1px solid #eaecf0; }
.sh-material-row:last-child { border-bottom: 0; }
.sh-material-row.head { background: #f9fafb; color: #667085; font-size: 12px; font-weight: 900; text-transform: uppercase; }
.sh-equipment { display: flex; flex-direction: column; gap: 10px; }
.sh-equipment-row { display: grid; grid-template-columns: 180px 1fr; gap: 10px; align-items: center; }
.sh-equipment-row label { display: flex; align-items: center; gap: 8px; font-weight: 800; }
.sh-equipment-row input[type="checkbox"] { width: 18px; min-height: 18px; }
.sh-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px; }
.sh-primary { border: 1px solid #20451f; background: #20451f; color: #fff; }
.sh-secondary { border: 1px solid #d0d5dd; background: #fff; color: #344054; }
.sh-secondary.danger { color: #b42318; border-color: #fecaca; }
.sh-primary:disabled, .sh-secondary:disabled { opacity: .65; cursor: not-allowed; }
.sh-empty { padding: 40px; text-align: center; color: #667085; }
.sh-empty.compact { padding: 16px; }
.sh-list { display: flex; flex-direction: column; gap: 10px; }
.sh-list-item { display: flex; justify-content: space-between; gap: 12px; align-items: center; padding: 14px; border: 1px solid #eaecf0; border-radius: 8px; }
.sh-list-item strong, .sh-list-item span { display: block; }
.sh-list-item span { color: #667085; margin-top: 3px; }
.sh-list-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
.sh-list-meta > span { padding: 5px 9px; border-radius: 999px; background: #f2f4f7; color: #344054; font-weight: 800; }
.sh-list-meta > span.confirmed { background: #ecfdf3; color: #027a48; }
.sh-list-meta > span.disputed { background: #fef3f2; color: #b42318; }
.sh-list-meta em { display: inline-flex; align-items: center; gap: 5px; color: #c2410c; font-style: normal; font-weight: 800; }
.sh-list-meta em.dispute { color: #b42318; }
.sh-list-meta button { border: 1px solid #d0d5dd; border-radius: 8px; background: #fff; padding: 8px 10px; font-weight: 800; cursor: pointer; }
.sh-modal-backdrop { position: fixed; inset: 0; z-index: 1200; display: flex; align-items: center; justify-content: center; padding: 20px; background: rgba(16,24,40,.45); }
.sh-modal { width: min(820px, 100%); max-height: calc(100vh - 40px); overflow-y: auto; background: #fff; border-radius: 8px; padding: 18px; }
.sh-icon-btn { width: 40px; padding: 0; border: 1px solid #d0d5dd; background: #fff; color: #344054; }
.sh-modal-body { display: flex; flex-direction: column; gap: 12px; }
.sh-snapshot { display: flex; flex-direction: column; border: 1px solid #eaecf0; border-radius: 8px; overflow: hidden; }
.sh-snapshot-row { display: grid; grid-template-columns: minmax(0, 1fr) 150px 150px minmax(120px, .8fr); gap: 10px; padding: 10px 12px; border-bottom: 1px solid #eaecf0; }
.sh-snapshot-row:last-child { border-bottom: 0; }
.sh-snapshot-row span, .sh-snapshot-row em { color: #344054; font-style: normal; font-weight: 800; }
.sh-snapshot-row.head { background: #f9fafb; color: #667085; font-size: 12px; font-weight: 900; text-transform: uppercase; }
.sh-snapshot-row.head span { color: #667085; }
.sh-damaged-list { display: flex; flex-direction: column; gap: 8px; }
.sh-damaged-item { display: grid; grid-template-columns: minmax(0, 1fr) 110px minmax(160px, 1fr); gap: 10px; padding: 10px 12px; border: 1px solid #fed7aa; border-radius: 8px; background: #fff7ed; color: #9a3412; }
.sh-damaged-item span, .sh-damaged-item em { font-style: normal; font-weight: 800; }
.sh-damaged-item.head { border-color: #eaecf0; background: #f9fafb; color: #667085; font-size: 12px; font-weight: 900; text-transform: uppercase; }
.sh-note { margin: 0; padding: 12px; border-radius: 8px; background: #f9fafb; color: #344054; white-space: pre-line; }
.sh-note.dispute { border: 1px solid #fecaca; background: #fef3f2; color: #991b1b; font-weight: 700; }
@media (max-width: 768px) {
    .sh-block-head, .sh-sold-row { grid-template-columns: 1fr; }
    .sh-block-head { flex-direction: column; }
    .sh-shift-info, .sh-compact-row, .sh-compact-row.damaged, .sh-damaged-item { grid-template-columns: 1fr; }
    .sh-report-hero, .sh-report-kpis { grid-template-columns: 1fr; }
    .sh-report-row { grid-template-columns: minmax(0, 1fr) 54px 110px; }
    .sh-grid.two, .sh-equipment-row, .sh-snapshot-row { grid-template-columns: 1fr; }
    .sh-filters { grid-template-columns: 1fr; }
    .sh-list-item, .sh-header { flex-direction: column; align-items: stretch; }
    .sh-header-actions { justify-content: stretch; }
    .sh-month { width: 100%; }
    .sh-actions { flex-direction: column; }
    .sh-primary, .sh-secondary { width: 100%; }
}
</style>

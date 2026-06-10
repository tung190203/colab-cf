<script setup>
import { computed, onMounted, ref } from 'vue';
import AdminLayout from './admin/AdminLayout.vue';
import { useAdminAuth } from '../composables/useAdminAuth';
import { toast } from 'vue3-toastify';
import axios from 'axios';
import {
    AlertTriangle,
    CheckCircle2,
    ClipboardPenLine,
    Eye,
    RefreshCw,
    Save,
    XCircle,
} from 'lucide-vue-next';

const { authHeader, adminUser } = useAdminAuth();

const loading = ref(false);
const saving = ref(false);
const activeTab = ref('create');
const prepare = ref(null);
const handovers = ref([]);
const selected = ref(null);
const disputeNote = ref('');
const exportMonth = ref(new Date().toISOString().slice(0, 7));
const filters = ref({
    date: '',
    shift_type: '',
    status: '',
    employee_id: '',
});

const form = ref(defaultForm());

const pendingHandovers = computed(() => handovers.value.filter((item) => item.status === 'pending'));

const cashDiff = computed(() => {
    return Number(form.value.cash_actual || 0) - Number(form.value.cash_theoretical || 0);
});

function defaultForm() {
    return {
        date: '',
        shift_type: 'sang',
        cash_theoretical: 0,
        cash_actual: '',
        cash_note: '',
        total_orders: 0,
        revenue_cash: 0,
        revenue_transfer: 0,
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
        pending: 'Chờ nhận ca',
        confirmed: 'Đã nhận ca',
        disputed: 'Có sai lệch',
    }[status] || status;
}

function materialDiff(item) {
    return Number(item.theoretical || 0) - Number(item.actual || 0);
}

function materialDiffPercent(item) {
    const theoretical = Number(item.theoretical || 0);
    const diff = Math.abs(materialDiff(item));
    return theoretical > 0 ? (diff / theoretical) * 100 : (diff ? 100 : 0);
}

async function fetchPrepare() {
    loading.value = true;
    try {
        const res = await axios.get('/api/shift-handover/prepare', { headers: authHeader() });
        prepare.value = res.data;
        form.value = {
            ...defaultForm(),
            date: res.data.date,
            shift_type: res.data.shift_type,
            cash_theoretical: res.data.cash_theoretical,
            total_orders: res.data.total_orders,
            revenue_cash: res.data.revenue_cash,
            revenue_transfer: res.data.revenue_transfer,
            materials: res.data.materials.map((material) => ({
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
    if (Math.abs(cashDiff.value) > 50000 && !form.value.cash_note.trim()) {
        toast.warning('Vui lòng ghi chú lý do chênh lệch tiền mặt');
        return;
    }

    const missingReason = form.value.materials.find((item) => materialDiffPercent(item) > 10 && !item.reason.trim());
    if (missingReason) {
        toast.warning(`Vui lòng ghi lý do chênh lệch NVL: ${missingReason.material_name}`);
        return;
    }

    saving.value = true;
    try {
        await axios.post('/api/shift-handover', form.value, { headers: authHeader() });
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
                    <CheckCircle2 :size="18" /> Chờ nhận ca
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

                    <div class="sh-grid two">
                        <div class="sh-block">
                            <h4>Tiền mặt</h4>
                            <div class="sh-stats">
                                <span>Lý thuyết</span>
                                <strong>{{ formatMoney(form.cash_theoretical) }}</strong>
                            </div>
                            <div class="sh-cash-breakdown" v-if="prepare">
                                <span>Ca trước bàn giao: {{ formatMoney(prepare.cash_received_previous) }}</span>
                                <span>Doanh thu tiền mặt trong ca: {{ formatMoney(form.revenue_cash) }}</span>
                            </div>
                            <label class="sh-field">
                                <span>Tiền mặt thực đếm</span>
                                <input v-model.number="form.cash_actual" type="number" min="0" />
                            </label>
                            <div class="sh-diff" :class="{ warn: Math.abs(cashDiff) > 50000 }">
                                Chênh lệch: {{ formatMoney(cashDiff) }}
                            </div>
                            <label class="sh-field">
                                <span>Ghi chú tiền mặt</span>
                                <textarea v-model="form.cash_note" rows="3"></textarea>
                            </label>
                        </div>

                        <div class="sh-block">
                            <h4>Order & doanh thu</h4>
                            <div class="sh-grid two compact">
                                <label class="sh-field">
                                    <span>Tổng order</span>
                                    <input v-model.number="form.total_orders" type="number" min="0" />
                                </label>
                                <label class="sh-field">
                                    <span>Doanh thu tiền mặt</span>
                                    <input v-model.number="form.revenue_cash" type="number" min="0" />
                                </label>
                                <label class="sh-field">
                                    <span>Doanh thu chuyển khoản</span>
                                    <input v-model.number="form.revenue_transfer" type="number" min="0" />
                                </label>
                                <div class="sh-stats">
                                    <span>Tổng doanh thu</span>
                                    <strong>{{ formatMoney(Number(form.revenue_cash || 0) + Number(form.revenue_transfer || 0)) }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sh-block">
                        <h4>Kiểm kê NVL</h4>
                        <div class="sh-materials">
                            <div class="sh-material-row head">
                                <span>NVL</span><span>Lý thuyết</span><span>Thực tế</span><span>Lý do</span>
                            </div>
                            <div v-for="item in form.materials" :key="item.material_id" class="sh-material-row" :class="{ warn: materialDiffPercent(item) > 10 }">
                                <strong>{{ item.material_name }}</strong>
                                <span>{{ formatNumber(item.theoretical) }} {{ item.unit }}</span>
                                <input v-model.number="item.actual" type="number" min="0" step="0.001" />
                                <input v-model="item.reason" type="text" :placeholder="materialDiffPercent(item) > 10 ? 'Bắt buộc nhập lý do' : 'Ghi chú nếu có'" />
                            </div>
                        </div>
                    </div>

                    <div class="sh-grid two">
                        <div class="sh-block">
                            <h4>Thiết bị</h4>
                            <div class="sh-equipment">
                                <div v-for="(_, key) in form.equipment_checklist" :key="key" class="sh-equipment-row">
                                    <label>
                                        <input v-model="form.equipment_checklist[key].ok" type="checkbox" />
                                        {{ equipmentLabel(key) }}
                                    </label>
                                    <input v-if="!form.equipment_checklist[key].ok" v-model="form.equipment_checklist[key].note" type="text" placeholder="Mô tả lỗi" />
                                </div>
                            </div>
                        </div>
                        <div class="sh-block">
                            <h4>Ghi chú bàn giao</h4>
                            <textarea v-model="form.handover_note" rows="8" placeholder="Sự cố trong ca, việc cần chuyển giao, yêu cầu đặt hàng..."></textarea>
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
                        <h3>{{ activeTab === 'pending' ? 'Biên bản chờ nhận ca' : 'Lịch sử giao ca' }}</h3>
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
                        <option value="pending">Chờ nhận ca</option>
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
                            <h3>Chi tiết giao ca #{{ selected.id }}</h3>
                            <p>{{ statusLabel(selected.status) }}</p>
                        </div>
                        <button class="sh-icon-btn" @click="selected = null"><XCircle :size="20" /></button>
                    </div>
                    <div class="sh-modal-body">
                        <div class="sh-grid two compact">
                            <div class="sh-stats"><span>Tiền mặt thực tế</span><strong>{{ formatMoney(selected.cash_actual) }}</strong></div>
                            <div class="sh-stats"><span>Chênh lệch</span><strong>{{ formatMoney(selected.cash_diff) }}</strong></div>
                            <div class="sh-stats"><span>Order</span><strong>{{ selected.total_orders }}</strong></div>
                            <div class="sh-stats"><span>Doanh thu</span><strong>{{ formatMoney(selected.total_revenue) }}</strong></div>
                        </div>
                        <h4>NVL</h4>
                        <div class="sh-snapshot">
                            <div v-for="item in selected.nvl_snapshot" :key="item.material_id" class="sh-snapshot-row" :class="{ warn: item.has_alert }">
                                <strong>{{ item.material_name }}</strong>
                                <span>{{ formatNumber(item.theoretical) }} → {{ formatNumber(item.actual) }} {{ item.unit }}</span>
                                <em v-if="item.reason">{{ item.reason }}</em>
                            </div>
                        </div>
                        <h4>Ghi chú</h4>
                        <p class="sh-note">{{ selected.handover_note || 'Không có' }}</p>
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
.sh-block { padding: 16px; }
.sh-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; margin-bottom: 16px; }
.sh-header-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
.sh-month { width: 150px; }
.sh-filters { display: grid; grid-template-columns: 160px 150px 180px minmax(180px, 1fr) auto auto; gap: 10px; margin-bottom: 16px; align-items: center; }
.sh-header h3, .sh-block h4, .sh-modal-body h4 { margin: 0; color: #101828; }
.sh-header p { margin: 4px 0 0; color: #667085; font-weight: 700; }
.sh-grid { display: grid; gap: 16px; margin-bottom: 16px; }
.sh-grid.two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.sh-grid.compact { gap: 10px; margin-bottom: 0; }
.sh-field { display: flex; flex-direction: column; gap: 7px; margin-top: 12px; }
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
.sh-list { display: flex; flex-direction: column; gap: 10px; }
.sh-list-item { display: flex; justify-content: space-between; gap: 12px; align-items: center; padding: 14px; border: 1px solid #eaecf0; border-radius: 8px; }
.sh-list-item strong, .sh-list-item span { display: block; }
.sh-list-item span { color: #667085; margin-top: 3px; }
.sh-list-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
.sh-list-meta > span { padding: 5px 9px; border-radius: 999px; background: #f2f4f7; color: #344054; font-weight: 800; }
.sh-list-meta > span.confirmed { background: #ecfdf3; color: #027a48; }
.sh-list-meta > span.disputed { background: #fef3f2; color: #b42318; }
.sh-list-meta em { display: inline-flex; align-items: center; gap: 5px; color: #c2410c; font-style: normal; font-weight: 800; }
.sh-list-meta button { border: 1px solid #d0d5dd; border-radius: 8px; background: #fff; padding: 8px 10px; font-weight: 800; cursor: pointer; }
.sh-modal-backdrop { position: fixed; inset: 0; z-index: 1200; display: flex; align-items: center; justify-content: center; padding: 20px; background: rgba(16,24,40,.45); }
.sh-modal { width: min(820px, 100%); max-height: calc(100vh - 40px); overflow-y: auto; background: #fff; border-radius: 8px; padding: 18px; }
.sh-icon-btn { width: 40px; padding: 0; border: 1px solid #d0d5dd; background: #fff; color: #344054; }
.sh-modal-body { display: flex; flex-direction: column; gap: 12px; }
.sh-snapshot { display: flex; flex-direction: column; border: 1px solid #eaecf0; border-radius: 8px; overflow: hidden; }
.sh-snapshot-row { display: grid; grid-template-columns: 1fr 180px 1fr; gap: 10px; padding: 10px 12px; border-bottom: 1px solid #eaecf0; }
.sh-snapshot-row:last-child { border-bottom: 0; }
.sh-note { margin: 0; padding: 12px; border-radius: 8px; background: #f9fafb; color: #344054; white-space: pre-line; }
@media (max-width: 768px) {
    .sh-grid.two, .sh-equipment-row, .sh-snapshot-row { grid-template-columns: 1fr; }
    .sh-filters { grid-template-columns: 1fr; }
    .sh-list-item, .sh-header { flex-direction: column; align-items: stretch; }
    .sh-header-actions { justify-content: stretch; }
    .sh-month { width: 100%; }
    .sh-actions { flex-direction: column; }
    .sh-primary, .sh-secondary { width: 100%; }
}
</style>

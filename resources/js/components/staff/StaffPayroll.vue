<script setup>
import { ref, computed, onMounted } from 'vue';
import AdminLayout from '../admin/AdminLayout.vue';
import { useAdminAuth } from '../../composables/useAdminAuth';
import axios from 'axios';
import { toast } from 'vue3-toastify';
import { Download, Eye, ShieldAlert, CircleDollarSign, TrendingUp, Users, CheckCircle2, Clock } from 'lucide-vue-next';

const { authHeader } = useAdminAuth();
const now = new Date();
const selectedMonth = ref(now.getMonth() + 1);
const selectedYear = ref(now.getFullYear());
const payroll = ref(null);
const workedHours = ref(0);
const history = ref([]);
const loading = ref(true);

onMounted(() => { fetchPayroll(); fetchHistory(); });

async function fetchPayroll() {
    loading.value = true;
    try {
        const res = await axios.get(`/api/staff/payroll?month=${selectedMonth.value}&year=${selectedYear.value}`, { headers: authHeader() });
        payroll.value = res.data.payroll;
        workedHours.value = res.data.worked_hours;
    } catch(e) { toast.error('Không thể tải bảng lương'); }
    finally { loading.value = false; }
}

async function fetchHistory() {
    try {
        const res = await axios.get('/api/staff/payroll/history', { headers: authHeader() });
        history.value = res.data;
    } catch(e) {}
}

function fmt(v) { return new Intl.NumberFormat('vi-VN').format(v) + ' ₫'; }
function roundHours(v) {
    const n = Number(v || 0);
    return Math.round((n + Number.EPSILON) * 100) / 100;
}
function fmtHours(v) {
    return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 }).format(roundHours(v));
}

const total = computed(() => {
    if (!payroll.value) return 0;
    return Math.max(0, Number(payroll.value.calculated_salary) + Number(payroll.value.bonus) - Number(payroll.value.deduction));
});

const prevMonthTotal = computed(() => {
    let pMonth = selectedMonth.value - 1;
    let pYear = selectedYear.value;
    if (pMonth === 0) { pMonth = 12; pYear--; }
    const prev = history.value.find(h => h.month === pMonth && h.year === pYear);
    return prev ? prev.total : 0;
});

const growthRate = computed(() => {
    if (!prevMonthTotal.value || !total.value) return 0;
    return Math.round(((total.value - prevMonthTotal.value) / prevMonthTotal.value) * 100);
});

const chartPercents = computed(() => {
    if (!payroll.value || total.value === 0) return { base: 100, bonus: 0 };
    const base = Number(payroll.value.calculated_salary);
    const bonus = Number(payroll.value.bonus);
    const sum = base + bonus;
    if (sum === 0) return { base: 100, bonus: 0 };
    
    return {
        base: Math.round((base / sum) * 100),
        bonus: Math.round((bonus / sum) * 100)
    };
});

const chartStyle = computed(() => {
    const { base } = chartPercents.value;
    return `conic-gradient(#1e3a1f 0% ${base}%, #86efac ${base}% 100%)`;
});

const getDeductionIcon = (label) => {
    if (!label) return CircleDollarSign;
    const l = label.toLowerCase();
    if (l.includes('thuế') || l.includes('tncn')) return ShieldAlert;
    if (l.includes('bảo hiểm') || l.includes('bhxh')) return ShieldAlert;
    if (l.includes('công đoàn')) return Users;
    return CircleDollarSign;
};

function evidenceUrl(path) {
    if (!path) return '';
    if (String(path).startsWith('http')) return path;
    return `/storage/${path}`;
}
</script>

<template>
    <AdminLayout>
        <template #title>Bảng lương của tôi</template>

        <div class="sp-wrap">
            <div class="sp-filter">
                <select v-model="selectedMonth" @change="fetchPayroll">
                    <option v-for="m in 12" :key="m" :value="m">Tháng {{ String(m).padStart(2,'0') }}</option>
                </select>
                <select v-model="selectedYear" @change="fetchPayroll">
                    <option v-for="y in [2025, 2026, 2027]" :key="y" :value="y">{{ y }}</option>
                </select>
            </div>

            <div v-if="loading" class="sp-loading"><div class="sp-spinner"></div></div>
            <div v-else-if="!payroll || !payroll.is_settled" class="sp-empty-card">
                <div class="sp-empty-icon">💰</div>
                <h3>Chưa có bảng lương chính thức</h3>
                <p>Bảng lương tháng {{ selectedMonth }}/{{ selectedYear }} chưa được quyết toán. Liên hệ quản lý để biết thêm chi tiết.</p>
                <div class="sp-empty-hours">Số giờ đã chấm công: <strong>{{ fmtHours(workedHours) }}h</strong></div>
            </div>
            
            <div v-else class="sp-grid">
                <!-- TOP ROW -->
                <div class="sp-card sp-main-card">
                    <div class="sp-mc-top">
                        <div class="sp-mc-left">
                            <div class="sp-label">Tổng thu nhập thực nhận (T{{ String(selectedMonth).padStart(2,'0') }}/{{ selectedYear }})</div>
                            <div class="sp-total-val">{{ fmt(total).replace(' ₫', ' VND') }}</div>
                            <div class="sp-badge" v-if="growthRate !== 0" :class="growthRate > 0 ? 'badge-up' : 'badge-down'">
                                <TrendingUp :size="14" v-if="growthRate > 0"/> 
                                {{ growthRate > 0 ? '+' : '' }}{{ growthRate }}% so với tháng trước
                            </div>
                        </div>
                    </div>
                    <div class="sp-mc-divider"></div>
                    <div class="sp-mc-bottom">
                        <div class="sp-mc-col">
                            <div class="sp-mc-lbl">Lương cơ bản</div>
                            <div class="sp-mc-v">{{ fmt(payroll.calculated_salary).replace(' ₫', ' VND') }}</div>
                        </div>
                        <div class="sp-mc-col" v-if="payroll.bonus_details && payroll.bonus_details.length > 0">
                            <div class="sp-mc-lbl">Phụ cấp</div>
                            <div class="sp-mc-v">{{ fmt(payroll.bonus).replace(' ₫', ' VND') }}</div>
                        </div>
                        <div class="sp-mc-col" v-else>
                            <div class="sp-mc-lbl">Phụ cấp</div>
                            <div class="sp-mc-v">{{ fmt(payroll.bonus).replace(' ₫', ' VND') }}</div>
                        </div>
                        <div class="sp-mc-col">
                            <div class="sp-mc-lbl">Tổng phạt</div>
                            <div class="sp-mc-v" style="color: #ef4444;">-{{ fmt(payroll.deduction).replace(' ₫', ' VND') }}</div>
                        </div>
                    </div>

                    <template v-if="payroll.deduction > 0">
                        <div class="sp-mc-divider"></div>
                        <h4 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 16px; color: #334155;">Chi tiết khoản phạt</h4>
                        <div class="sp-ded-list" v-if="payroll.deduction_details && payroll.deduction_details.length > 0">
                            <div class="sp-ded-item" v-for="(d, i) in payroll.deduction_details" :key="i">
                                <div class="sp-di-icon"><component :is="getDeductionIcon(d.label)" :size="18"/></div>
                                <div class="sp-di-info">
                                    <div class="sp-di-name">{{ d.label }}</div>
                                    <div class="sp-di-note" v-if="d.quantity">Số lượt: {{ d.quantity }} × {{ fmt(d.unit_amount || 0).replace(' ₫', ' VND') }}</div>
                                    <div class="sp-di-note" v-if="d.reason">Lý do: {{ d.reason }}</div>
                                    <a v-if="d.evidence_path" class="sp-di-link" :href="evidenceUrl(d.evidence_path)" target="_blank">Xem bằng chứng</a>
                                </div>
                                <div class="sp-di-val">-{{ fmt(d.amount).replace(' ₫', ' VND') }}</div>
                            </div>
                        </div>
                        <div v-else class="sp-ded-list">
                             <div class="sp-ded-item">
                                <div class="sp-di-icon"><CircleDollarSign :size="18"/></div>
                                <div class="sp-di-info"><div class="sp-di-name">Phạt khác</div></div>
                                <div class="sp-di-val">-{{ fmt(payroll.deduction).replace(' ₫', ' VND') }}</div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="sp-card sp-chart-card">
                    <h3 class="sp-card-title">Phân tích cơ cấu</h3>
                    <div class="sp-donut-wrap">
                        <div class="sp-donut" :style="{ background: chartStyle }">
                            <div class="sp-donut-inner">100%</div>
                        </div>
                    </div>
                    <div class="sp-chart-legend">
                        <div class="sp-leg-item"><span class="sp-dot c-base"></span>Lương chính</div>
                        <div class="sp-leg-item"><span class="sp-dot c-bonus"></span>Phụ cấp</div>
                    </div>
                </div>

                <!-- BOTTOM ROW -->
                <div class="sp-card sp-history-card">
                    <div class="sp-card-header">
                        <h3 class="sp-card-title">Lịch sử nhận lương</h3>
                    </div>
                    <div class="sp-table-responsive">
                        <table class="sp-table">
                        <thead>
                            <tr>
                                <th>Tháng</th>
                                <th>Ngày thanh toán</th>
                                <th>Tổng thu nhập</th>
                                <th>Trạng thái</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="h in history.slice(0, 3)" :key="h.id" @click="selectedMonth = h.month; selectedYear = h.year; fetchPayroll()" style="cursor: pointer;">
                                <td><strong>Tháng {{ String(h.month).padStart(2,'0') }}/{{ h.year }}</strong></td>
                                <td class="text-muted">{{ new Date(h.updated_at).toLocaleDateString('vi-VN') }}</td>
                                <td><strong style="color: #1a1a2e;">{{ fmt(h.total).replace(' ₫', ' VND') }}</strong></td>
                                <td><span class="sp-status-done"><CheckCircle2 :size="14"/> Đã quyết toán</span></td>
                                <td><button class="sp-btn-eye"><Eye :size="16"/></button></td>
                            </tr>
                            <tr v-if="history.length === 0">
                                <td colspan="5" class="text-center text-muted py-4">Chưa có lịch sử</td>
                            </tr>
                        </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.sp-wrap {
    max-width: 1200px;
    margin: 0 auto;
    font-family: 'Inter', sans-serif;
    color: #1a1a2e;
}

.sp-filter {
    display: flex; gap: 12px; margin-bottom: 24px;
}
.sp-filter select {
    padding: 10px 16px; border: 1px solid #e2e8f0;
    border-radius: 12px; font-size: 0.95rem; background: white;
    outline: none; cursor: pointer; font-weight: 500;
}

.sp-loading { display: flex; justify-content: center; padding: 60px; }
.sp-spinner { width: 40px; height: 40px; border: 3px solid #f0f0f0; border-top-color: #3b82f6; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.sp-empty-card {
    background: white; border-radius: 20px; padding: 60px 20px;
    text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.02);
}
.sp-empty-icon { font-size: 3rem; margin-bottom: 16px; }
.sp-empty-card h3 { font-weight: 700; color: #1e293b; margin-bottom: 8px; }
.sp-empty-card p { color: #64748b; margin-bottom: 24px; }
.sp-empty-hours { background: #f0fdf4; color: #166534; padding: 12px 24px; border-radius: 100px; display: inline-block; font-size: 0.95rem; }

/* Grid Layout */
.sp-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
}

.sp-card {
    background: white;
    border-radius: 20px;
    padding: 28px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.02);
    border: 1px solid #f8fafc;
    min-width: 0;
}
.sp-card-title {
    font-size: 1.1rem; font-weight: 700; color: #334155; margin-bottom: 20px;
}
.sp-card-header {
    display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;
}
.sp-card-header .sp-card-title { margin-bottom: 0; }

/* Main Card */
.sp-main-card {
    border-left: 6px solid #1e3a1f;
}
.sp-mc-top {
    display: flex; justify-content: space-between; align-items: flex-start;
}
.sp-label { font-size: 0.95rem; color: #64748b; font-weight: 500; margin-bottom: 8px; }
.sp-total-val { font-size: 2.5rem; font-weight: 800; color: #1e3a1f; line-height: 1.2; margin-bottom: 12px; }
.sp-badge {
    display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 100px; font-size: 0.8rem; font-weight: 600;
}
.badge-up { background: #f0fdf4; color: #16a34a; }
.badge-down { background: #fef2f2; color: #ef4444; }



.sp-mc-divider { height: 1px; background: #e2e8f0; margin: 32px 0 24px; }
.sp-mc-bottom { display: flex; gap: 60px; }
.sp-mc-lbl { font-size: 0.85rem; color: #64748b; margin-bottom: 4px; }
.sp-mc-v { font-size: 1.1rem; font-weight: 700; color: #334155; }

/* Chart Card */
.sp-donut-wrap { display: flex; justify-content: center; margin: 30px 0; }
.sp-donut {
    width: 160px; height: 160px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    position: relative;
}
.sp-donut-inner {
    width: 120px; height: 120px; background: white; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; font-weight: 700; color: #1e3a1f;
}
.sp-chart-legend { display: flex; justify-content: center; gap: 20px; margin-top: 20px; }
.sp-leg-item { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #64748b; font-weight: 500; }
.sp-dot { width: 10px; height: 10px; border-radius: 50%; }
.c-base { background: #1e3a1f; }
.c-bonus { background: #86efac; }

/* History Card */
.sp-history-card { grid-column: 1 / -1; }
.sp-table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.sp-table { width: 100%; border-collapse: collapse; }
.sp-table th { text-align: left; padding: 16px; font-size: 0.85rem; color: #64748b; font-weight: 700; border-bottom: 2px solid #f1f5f9; background: #fafbfc; white-space: nowrap; }
.sp-table td { padding: 18px 16px; border-bottom: 1px solid #f1f5f9; font-size: 0.95rem; vertical-align: middle; white-space: nowrap; }
.sp-table tr:hover td { background: #fafbfc; }
.sp-status-done { display: inline-flex; align-items: center; gap: 6px; background: #f0fdf4; color: #16a34a; padding: 6px 12px; border-radius: 100px; font-size: 0.8rem; font-weight: 600; }
.sp-status-draft { display: inline-flex; align-items: center; gap: 6px; background: #eff6ff; color: #3b82f6; padding: 6px 12px; border-radius: 100px; font-size: 0.8rem; font-weight: 600; }
.sp-btn-eye { background: none; border: none; color: #94a3b8; cursor: pointer; transition: 0.2s; }
.sp-btn-eye:hover { color: #1e293b; }

/* Deduction Card */
.sp-deduction-card { grid-column: 2; }
.sp-ded-list { display: flex; flex-direction: column; gap: 20px; margin-bottom: 30px; }
.sp-ded-item { display: flex; align-items: center; gap: 16px; }
.sp-di-icon { width: 44px; height: 44px; background: #fef2f2; color: #ef4444; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.sp-di-info { flex: 1; }
.sp-di-name { font-weight: 600; color: #334155; font-size: 0.95rem; }
.sp-di-note { color: #64748b; font-size: 0.82rem; margin-top: 3px; }
.sp-di-link { color: #2563eb; font-size: 0.82rem; font-weight: 700; text-decoration: none; display: inline-block; margin-top: 4px; }
.sp-di-sub { font-size: 0.8rem; color: #94a3b8; margin-top: 2px; }
.sp-di-val { font-weight: 700; color: #ef4444; }

.sp-no-ded { color: #94a3b8; font-size: 0.9rem; text-align: center; padding: 20px 0; font-style: italic; }
.sp-ded-divider { height: 1px; background: #e2e8f0; margin: 0 0 24px; }
.sp-ded-summary { display: flex; flex-direction: column; gap: 12px; }
.sp-ds-row { display: flex; justify-content: space-between; font-size: 0.9rem; font-weight: 500; color: #64748b; }
.sp-text-red { color: #ef4444; font-weight: 600; }
.sp-ds-final { margin-top: 8px; font-size: 1.1rem; font-weight: 800; color: #1a1a2e; }

/* Responsive Grid */
@media (max-width: 992px) {
    .sp-grid { grid-template-columns: 1fr; }
    .sp-history-card, .sp-deduction-card { grid-column: 1; }
}
</style>

<script setup>
import { ref, onMounted } from 'vue';
import AdminLayout from './AdminLayout.vue';
import { useAdminAuth } from '../../composables/useAdminAuth';
import { toast } from 'vue3-toastify';
import axios from 'axios';

const { adminUser, authHeader } = useAdminAuth();
const now = new Date();
const selectedMonth = ref(now.getMonth() + 1);
const selectedYear = ref(now.getFullYear());
const payrollData = ref([]);
const loading = ref(true);
const editModal = ref(false);
const saving = ref(false);

const editForm = ref({
    staff_id: null, name: '', hourly_rate: 0,
    worked_hours: 0, calculated_salary: 0,
    bonus: 0, deduction: 0, note: '',
    bonus_details: [], deduction_details: [], is_settled: false, status: 'draft'
});

async function fetchPayroll() {
    loading.value = true;
    try {
        const res = await axios.get(`/api/admin/payroll?month=${selectedMonth.value}&year=${selectedYear.value}`, { headers: authHeader() });
        let list = res.data;
        
        // Ẩn tài khoản của chính người đang đăng nhập nếu là admin
        if (adminUser.value?.id && adminUser.value?.role === 'admin') {
            list = list.filter(p => p.staff_id !== adminUser.value.id);
        }
        
        payrollData.value = list;
    } catch (e) { toast.error('Không thể tải bảng lương'); }
    finally { loading.value = false; }
}

onMounted(fetchPayroll);

function openEdit(item) {
    const hourly = Number(item.payroll?.hourly_rate ?? item.hourly_rate ?? 0);
    const hours = roundHours(item.payroll?.worked_hours ?? item.worked_hours ?? 0);
    const calc = item.payroll ? Number(item.payroll.calculated_salary) : Number(item.calculated_salary ?? Math.round(hourly * hours));
    
    editForm.value = {
        staff_id: item.staff_id,
        name: item.name,
        hourly_rate: hourly,
        worked_hours: hours,
        calculated_salary: calc,
        bonus: Number(item.payroll?.bonus ?? 0),
        deduction: Number(item.payroll?.deduction ?? 0),
        note: item.payroll?.note ?? '',
        is_settled: Boolean(item.payroll?.is_settled),
        status: item.payroll?.status ?? (item.payroll?.is_settled ? 'approved' : 'draft'),
        bonus_details: Array.isArray(item.payroll?.bonus_details) ? [...item.payroll.bonus_details] : (Array.isArray(item.bonus_details) ? [...item.bonus_details] : []),
        deduction_details: Array.isArray(item.payroll?.deduction_details) ? [...item.payroll.deduction_details] : [],
    };
    if (editForm.value.bonus_details.length === 0 && editForm.value.bonus > 0) {
        editForm.value.bonus_details.push({ label: 'Thưởng/Phụ cấp', amount: editForm.value.bonus });
    }
    if (editForm.value.deduction_details.length === 0 && editForm.value.deduction > 0) {
        editForm.value.deduction_details.push({ label: 'Giảm trừ', amount: editForm.value.deduction });
    }
    editModal.value = true;
}

function calcTotals() {
    let bSum = 0;
    for (let b of editForm.value.bonus_details) bSum += Number(b.amount || 0);
    editForm.value.bonus = bSum;

    let dSum = 0;
    for (let d of editForm.value.deduction_details) dSum += Number(d.amount || 0);
    editForm.value.deduction = dSum;
}

function addBonus() { editForm.value.bonus_details.push({ label: '', amount: 0 }); calcTotals(); }
function removeBonus(idx) { editForm.value.bonus_details.splice(idx, 1); calcTotals(); }

function addDeduction() { editForm.value.deduction_details.push({ label: '', amount: 0 }); calcTotals(); }
function removeDeduction(idx) { editForm.value.deduction_details.splice(idx, 1); calcTotals(); }


async function savePayroll() {
    saving.value = true;
    try {
        const isSettled = adminUser.value?.role === 'admin' && editForm.value.status === 'approved';
        editForm.value.worked_hours = roundHours(editForm.value.worked_hours);

        await axios.post('/api/admin/payroll', {
            staff_id: editForm.value.staff_id,
            month: selectedMonth.value,
            year: selectedYear.value,
            hourly_rate: editForm.value.hourly_rate,
            worked_hours: editForm.value.worked_hours,
            bonus: editForm.value.bonus,
            deduction: editForm.value.deduction,
            note: editForm.value.note,
            bonus_details: editForm.value.bonus_details,
            deduction_details: editForm.value.deduction_details,
            is_settled: isSettled,
            status: editForm.value.status,
        }, { headers: authHeader() });
        toast.success('Đã lưu bảng lương');
        editModal.value = false;
        fetchPayroll();
    } catch (e) { toast.error('Có lỗi khi lưu'); }
    finally { saving.value = false; }
}

function computedTotal(item) {
    const hourly = Number(item.payroll?.hourly_rate ?? item.hourly_rate ?? 0);
    const hours = roundHours(item.payroll?.worked_hours ?? item.worked_hours ?? 0);
    const bonus = Number(item.payroll?.bonus ?? item.bonus ?? 0);
    const ded = Number(item.payroll?.deduction ?? 0);
    
    const calculated = item.payroll ? Number(item.payroll.calculated_salary) : Number(item.calculated_salary ?? (hourly * hours));
    return Math.max(0, calculated + bonus - ded);
}

function payrollStatus(item) {
    if (!item.payroll) return { text: 'Chưa lập', className: 'pr-status--pending' };
    const status = item.payroll.status || (item.payroll.is_settled ? 'approved' : 'draft');
    if (status === 'approved') return { text: 'Đã quyết toán', className: 'pr-status--done' };
    if (status === 'pending_approval') return { text: 'Chờ duyệt', className: 'pr-status--review' };
    return { text: 'Bản nháp', className: 'pr-status--draft' };
}

function fmt(v) { return new Intl.NumberFormat('vi-VN').format(v) + ' ₫'; }
function roundHours(v) {
    const n = Number(v || 0);
    return Math.round((n + Number.EPSILON) * 100) / 100;
}
function fmtHours(v) {
    return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 }).format(roundHours(v));
}
function fmtNoUnit(v) { 
    if (!v && v !== 0) return '';
    return new Intl.NumberFormat('vi-VN').format(v); 
}
function parseMoney(v) {
    let val = v.replace(/\D/g, '');
    return val ? parseInt(val) : 0;
}

const MONTHS = ['01','02','03','04','05','06','07','08','09','10','11','12'];
</script>

<template>
    <AdminLayout>
        <template #title>Bảng lương</template>

        <div class="pr-wrap">
            <!-- Filter -->
            <div class="pr-filter">
                <div class="pr-filter-group">
                    <label>Tháng</label>
                    <select v-model="selectedMonth" @change="fetchPayroll">
                        <option v-for="(m, i) in MONTHS" :key="i" :value="i+1">Tháng {{ m }}</option>
                    </select>
                </div>
                <div class="pr-filter-group">
                    <label>Năm</label>
                    <select v-model="selectedYear" @change="fetchPayroll">
                        <option v-for="y in [2025, 2026, 2027]" :key="y" :value="y">{{ y }}</option>
                    </select>
                </div>
                <button class="pr-refresh-btn" @click="fetchPayroll">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <polyline points="23 4 23 10 17 10"/>
                        <path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/>
                    </svg>
                    Tải lại
                </button>
            </div>

            <!-- Summary -->
            <div class="pr-summary">
                <div class="pr-sum-card">
                    <span class="pr-sum-label">Tổng nhân viên</span>
                    <span class="pr-sum-val">{{ payrollData.length }}</span>
                </div>
                <div class="pr-sum-card">
                    <span class="pr-sum-label">Đã tính lương</span>
                    <span class="pr-sum-val">{{ payrollData.filter(p => p.payroll).length }}</span>
                </div>
                <div class="pr-sum-card pr-sum-card--gold">
                    <span class="pr-sum-label">Tổng chi lương</span>
                    <span class="pr-sum-val">{{ fmt(payrollData.reduce((s, p) => s + computedTotal(p), 0)) }}</span>
                </div>
            </div>

            <!-- Table -->
            <div class="pr-table-wrap">
                <div v-if="loading" class="pr-loading">
                    <div class="pr-spinner"></div>
                </div>
                <table v-else class="pr-table">
                    <thead>
                        <tr>
                            <th>Nhân viên</th>
                            <th>Giờ làm</th>
                            <th>Lương/Giờ</th>
                            <th>Lương tính</th>
                            <th>Thưởng</th>
                            <th>Khấu trừ</th>
                            <th>Thực nhận</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="payrollData.length === 0">
                            <td colspan="9" class="pr-empty">Không có dữ liệu</td>
                        </tr>
                        <tr v-for="item in payrollData" :key="item.staff_id" class="pr-row">
                            <td>
                                <div class="pr-staff-info">
                                    <div class="pr-avatar">
                                        <img v-if="item.image_url" :src="item.image_url" />
                                        <span v-else>{{ item.name?.charAt(0)?.toUpperCase() }}</span>
                                    </div>
                                    <div>
                                        <div class="pr-staff-name">
                                            {{ item.name }}
                                            <span v-if="item.staff_id === adminUser?.id" style="color: #888; font-weight: 500; font-size: 0.85em;">(Tôi)</span>
                                        </div>
                                        <div class="pr-staff-role">{{ item.role === 'admin' ? 'Admin' : (item.role === 'shift_leader' ? 'Trưởng ca' : 'Nhân viên') }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="pr-center">
                                <span>{{ fmtHours(item.payroll?.worked_hours ?? item.worked_hours) }}h</span>
                            </td>
                            <td>{{ fmt(item.payroll?.hourly_rate ?? item.hourly_rate ?? 0) }}</td>
                            <td>{{ fmt(item.payroll ? item.payroll.calculated_salary : Number(item.calculated_salary ?? Math.round(Number(item.hourly_rate) * Number(item.worked_hours)))) }}</td>
                            <td class="pr-green">{{ fmt(item.payroll ? item.payroll.bonus : item.bonus ?? 0) }}</td>
                            <td class="pr-red">{{ item.payroll ? fmt(item.payroll.deduction) : '—' }}</td>
                            <td class="pr-total">{{ fmt(computedTotal(item)) }}</td>
                            <td>
                                <span class="pr-status" :class="payrollStatus(item).className">
                                    {{ payrollStatus(item).text }}
                                </span>
                            </td>
                            <td>
                                <button class="pr-edit-btn" @click="openEdit(item)">
                                    {{ item.payroll ? (item.payroll.is_settled ? 'Xem chi tiết' : 'Chỉnh sửa') : 'Tính lương' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Edit Modal -->
        <Teleport to="body">
            <div v-if="editModal" class="pr-overlay" @click.self="editModal = false">
                <div class="pr-modal">
                    <div class="pr-modal-header">
                        <h3>{{ editForm.status === 'approved' ? 'Chi tiết bảng lương' : 'Tính lương' }} - {{ editForm.name }}</h3>
                        <span class="pr-modal-period">Tháng {{ selectedMonth }}/{{ selectedYear }}</span>
                        <button class="pr-modal-close" @click="editModal = false">✕</button>
                    </div>
                    <div class="pr-modal-body">
                        <div class="pr-edit-grid">
                            <div class="pr-edit-field">
                                <label>Lương theo giờ (₫)</label>
                                <input 
                                    :value="fmtNoUnit(editForm.hourly_rate)" 
                                    @input="e => { editForm.hourly_rate = parseMoney(e.target.value); editForm.calculated_salary = Math.round(editForm.hourly_rate * editForm.worked_hours); }"
                                    type="text" placeholder="0 ₫" :disabled="editForm.status === 'approved'"
                                />
                            </div>
                            <div class="pr-edit-field">
                                <label>Giờ làm thực tế</label>
                                <div class="pr-computed-total" style="background: #f8f9fa; color: #333; font-size: 0.95rem;">
                                    {{ fmtHours(editForm.worked_hours) }}h
                                </div>
                            </div>
                            <div class="pr-edit-field">
                                <label>Lương tính theo giờ (₫)</label>
                                <div class="pr-computed-total" style="background: #f8f9fa; color: #333; font-size: 0.95rem;">
                                    {{ fmt(editForm.calculated_salary) }}
                                </div>
                            </div>
                            <div class="pr-edit-field pr-edit-field--full">
                                <label>Thưởng / Phụ cấp</label>
                                <div class="pr-details-list">
                                    <div v-for="(b, i) in editForm.bonus_details" :key="'b'+i" class="pr-detail-item">
                                        <input v-model="b.label" type="text" placeholder="Tên khoản (VD: Phụ cấp xăng xe...)" class="pr-di-name" :disabled="editForm.status === 'approved' || b.label?.startsWith('[AUTO]')" />
                                        <input :value="fmtNoUnit(b.amount)" @input="e => { b.amount = parseMoney(e.target.value); calcTotals(); }" type="text" placeholder="0" class="pr-di-val" :disabled="editForm.status === 'approved' || b.label?.startsWith('[AUTO]')" />
                                        <button v-if="editForm.status !== 'approved' && !b.label?.startsWith('[AUTO]')" class="pr-di-btn" @click="removeBonus(i)">✕</button>
                                    </div>
                                    <button v-if="editForm.status !== 'approved'" class="pr-add-btn" @click="addBonus">+ Thêm khoản thưởng</button>
                                </div>
                                <div class="pr-sub-total">Tổng thưởng: <strong class="pr-green">{{ fmt(editForm.bonus) }}</strong></div>
                            </div>
                            <div class="pr-edit-field pr-edit-field--full">
                                <label>Khấu trừ / Phạt</label>
                                <div class="pr-details-list">
                                    <div v-for="(d, i) in editForm.deduction_details" :key="'d'+i" class="pr-detail-item">
                                        <input v-model="d.label" type="text" placeholder="Tên khoản (VD: Đi trễ...)" class="pr-di-name" :disabled="editForm.status === 'approved'" />
                                        <input :value="fmtNoUnit(d.amount)" @input="e => { d.amount = parseMoney(e.target.value); calcTotals(); }" type="text" placeholder="0" class="pr-di-val" :disabled="editForm.status === 'approved'" />
                                        <button v-if="editForm.status !== 'approved'" class="pr-di-btn" @click="removeDeduction(i)">✕</button>
                                    </div>
                                    <button v-if="editForm.status !== 'approved'" class="pr-add-btn" @click="addDeduction">+ Thêm khoản khấu trừ</button>
                                </div>
                                <div class="pr-sub-total">Tổng khấu trừ: <strong class="pr-red">{{ fmt(editForm.deduction) }}</strong></div>
                            </div>
                            <div class="pr-edit-field">
                                <label>Thực nhận</label>
                                <div class="pr-computed-total">
                                    {{ fmt(Math.max(0, Number(editForm.calculated_salary) + Number(editForm.bonus) - Number(editForm.deduction))) }}
                                </div>
                            </div>
                            <div class="pr-edit-field pr-edit-field--full">
                                <label>Ghi chú</label>
                                <textarea v-model="editForm.note" rows="2" placeholder="Ghi chú thêm..." :disabled="editForm.status === 'approved'"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="pr-modal-footer">
                        <button class="pr-btn-cancel" @click="editModal = false">Đóng</button>
                        <button v-if="editForm.status !== 'approved'" class="pr-btn-draft" @click="editForm.status = 'draft'; savePayroll()" :disabled="saving">
                            Lưu nháp
                        </button>
                        <button v-if="editForm.status !== 'approved' && adminUser?.role !== 'admin'" class="pr-btn-save" @click="editForm.status = 'pending_approval'; savePayroll()" :disabled="saving">
                            Gửi duyệt
                        </button>
                        <button v-if="editForm.status !== 'approved' && adminUser?.role === 'admin'" class="pr-btn-save" @click="editForm.status = 'approved'; savePayroll()" :disabled="saving">
                            <span v-if="saving" class="pr-mini-spinner"></span>
                            Xác nhận Quyết toán
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>

<style scoped>
.pr-wrap { max-width: 1200px; margin: 0 auto; }

.pr-filter {
    display: flex;
    align-items: flex-end;
    gap: 16px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.pr-filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.pr-filter-group label { font-size: 0.8rem; font-weight: 600; color: #888; }
.pr-filter-group select {
    padding: 9px 14px;
    border: 1.5px solid #e0e6ed;
    border-radius: 10px;
    font-size: 0.9rem;
    background: white;
    outline: none;
    cursor: pointer;
    font-family: 'Inter', sans-serif;
}

.pr-refresh-btn {
    display: flex; align-items: center; gap: 6px;
    padding: 9px 16px;
    border: 1.5px solid #e0e6ed;
    border-radius: 10px;
    background: white;
    font-size: 0.875rem;
    font-weight: 600;
    color: #555;
    cursor: pointer;
    transition: all 0.2s;
    font-family: 'Inter', sans-serif;
}
.pr-refresh-btn:hover { border-color: #2D4F1E; color: #2D4F1E; }

.pr-summary {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.pr-sum-card {
    background: white; border-radius: 16px; padding: 20px;
    display: flex; flex-direction: column; gap: 6px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.pr-sum-card--gold { border-left: 4px solid #f59e0b; }
.pr-sum-label { font-size: 0.8rem; color: #888; font-weight: 500; }
.pr-sum-val { font-size: 1.4rem; font-weight: 800; color: #1a1a2e; }

.pr-table-wrap {
    background: white; border-radius: 20px;
    overflow-x: auto; box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    width: 100%;
}

.pr-loading { display: flex; justify-content: center; padding: 60px; }
.pr-spinner {
    width: 36px; height: 36px;
    border: 3px solid #e0e6ed; border-top-color: #2D4F1E;
    border-radius: 50%; animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.pr-table { width: 100%; border-collapse: collapse; min-width: 900px; }
.pr-table th {
    padding: 14px 16px; font-size: 0.78rem; font-weight: 600;
    color: #888; text-align: left; background: #fafbfc;
    border-bottom: 1px solid #f0f0f0;
    text-transform: uppercase; letter-spacing: 0.05em;
    white-space: nowrap;
}

.pr-row { border-bottom: 1px solid #f5f5f5; transition: background 0.15s; }
.pr-row:last-child { border-bottom: none; }
.pr-row:hover { background: #fafbfc; }
.pr-row td { padding: 14px 16px; font-size: 0.88rem; color: #333; vertical-align: middle; white-space: nowrap; }

.pr-staff-info { display: flex; align-items: center; gap: 10px; }
.pr-avatar {
    width: 36px; height: 36px; border-radius: 12px;
    background: linear-gradient(135deg, #2D4F1E, #4a7c2f);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; font-weight: 700; color: white;
    flex-shrink: 0; overflow: hidden;
}
.pr-avatar img { width: 100%; height: 100%; object-fit: cover; }
.pr-staff-name { font-weight: 600; font-size: 0.88rem; }
.pr-staff-role { font-size: 0.75rem; color: #aaa; }

.pr-center { text-align: center; }
.pr-green { color: #16a34a; font-weight: 600; }
.pr-red { color: #dc2626; font-weight: 600; }
.pr-total { font-weight: 800; color: #2D4F1E; font-size: 0.95rem; }

.pr-status { font-size: 0.8rem; font-weight: 600; padding: 4px 10px; border-radius: 100px; }
.pr-status--done { background: rgba(22,163,74,0.1); color: #16a34a; }
.pr-status--pending { background: rgba(245,158,11,0.1); color: #d97706; }
.pr-status--draft { background: rgba(59,130,246,0.1); color: #3b82f6; }
.pr-status--review { background: rgba(168,85,247,0.1); color: #9333ea; }

.pr-edit-btn {
    padding: 7px 14px; border-radius: 8px;
    background: rgba(45,79,30,0.08); color: #2D4F1E;
    border: 1px solid rgba(45,79,30,0.2);
    font-size: 0.82rem; font-weight: 600; cursor: pointer;
    transition: all 0.2s; font-family: 'Inter', sans-serif;
}
.pr-edit-btn:hover { background: rgba(45,79,30,0.15); }
.pr-empty { text-align: center; padding: 48px; color: #aaa; }

/* Modal */
.pr-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.45);
    display: flex; align-items: center; justify-content: center;
    z-index: 1000; padding: 20px; backdrop-filter: blur(4px);
}
.pr-modal {
    background: white; border-radius: 24px;
    width: 100%; max-width: 520px;
    box-shadow: 0 24px 60px rgba(0,0,0,0.2);
    display: flex; flex-direction: column;
    max-height: 90vh;
}
.pr-modal-header {
    padding: 24px 28px 20px;
    display: flex; align-items: center; gap: 12px;
    border-bottom: 1px solid #f0f0f0;
}
.pr-modal-header h3 { margin: 0; font-size: 1.1rem; font-weight: 700; color: #1a1a2e; flex: 1; }
.pr-modal-period {
    font-size: 0.8rem; font-weight: 600; color: #888;
    background: #f5f5f5; padding: 4px 10px; border-radius: 8px;
}
.pr-modal-close {
    background: #f5f5f5; border: none; border-radius: 8px;
    width: 32px; height: 32px; cursor: pointer; font-size: 0.9rem; color: #888;
}
.pr-modal-close:hover { background: #ffe4e4; color: #dc2626; }

.pr-modal-body { padding: 24px 28px; overflow-y: auto; }
.pr-edit-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.pr-edit-field { display: flex; flex-direction: column; gap: 6px; }
.pr-edit-field--full { grid-column: 1 / -1; }
.pr-edit-field label { font-size: 0.82rem; font-weight: 600; color: #555; }
.pr-edit-field input, .pr-edit-field textarea {
    padding: 10px 14px; border: 1.5px solid #e0e6ed; border-radius: 10px;
    font-size: 0.9rem; color: #333; background: #fafbfc; outline: none;
    transition: border-color 0.2s; font-family: 'Inter', sans-serif;
}
.pr-edit-field input:focus, .pr-edit-field textarea:focus { border-color: #2D4F1E; background: white; }
.pr-computed-total {
    padding: 10px 14px; border-radius: 10px;
    background: rgba(45,79,30,0.08);
    font-size: 1.1rem; font-weight: 800; color: #2D4F1E;
}

.pr-modal-footer {
    padding: 20px 28px 24px;
    display: flex; gap: 12px; justify-content: flex-end;
}
.pr-btn-cancel {
    padding: 10px 20px; border-radius: 10px; border: none;
    background: #f5f5f5; color: #555; font-weight: 600; cursor: pointer;
}
.pr-btn-cancel:hover { background: #eee; }
.pr-btn-draft {
    padding: 10px 20px; border-radius: 10px; border: 1px solid #e0e6ed;
    background: white; color: #3b82f6; font-weight: 600; cursor: pointer; transition: 0.2s;
}
.pr-btn-draft:hover { background: #eff6ff; border-color: #bfdbfe; }
.pr-btn-save {
    padding: 10px 24px; border-radius: 10px; border: none;
    background: #2D4F1E; color: white; font-weight: 600; cursor: pointer;
    display: flex; align-items: center; gap: 8px;
}
.pr-btn-save:hover { background: #234017; }
.pr-btn-save:disabled { opacity: 0.6; cursor: not-allowed; }

.pr-mini-spinner {
    width: 14px; height: 14px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: white; border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

/* CSS for dynamic list */
.pr-details-list {
    display: flex; flex-direction: column; gap: 8px; margin-bottom: 8px;
}
.pr-detail-item {
    display: flex; gap: 8px; align-items: center;
}
.pr-di-name {
    flex: 2; padding: 8px 12px; border: 1px solid #e0e6ed; border-radius: 8px; font-size: 0.85rem;
}
.pr-di-val {
    flex: 1; padding: 8px 12px; border: 1px solid #e0e6ed; border-radius: 8px; font-size: 0.85rem;
}
.pr-di-btn {
    background: #fee2e2; color: #ef4444; border: none; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer;
}
.pr-add-btn {
    background: none; border: 1px dashed #cbd5e1; color: #64748b; padding: 8px; border-radius: 8px; font-size: 0.85rem; cursor: pointer; text-align: center; font-weight: 500; margin-top: 4px;
}
.pr-add-btn:hover { background: #f8fafc; color: #3b82f6; border-color: #3b82f6; }
.pr-sub-total { font-size: 0.85rem; color: #64748b; text-align: right; margin-top: 4px; }


@media (max-width: 768px) {
    .pr-filter {
        flex-direction: column;
        align-items: stretch;
    }

    .pr-filter-group {
        flex: 1;
    }

    .pr-summary {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .pr-sum-card {
        padding: 16px;
    }

    .pr-sum-val {
        font-size: 1.1rem;
    }

    .pr-modal {
        max-height: 85vh;
        border-radius: 20px 20px 0 0;
        margin-bottom: 0;
        position: fixed;
        bottom: 0;
    }

    .pr-overlay {
        align-items: flex-end;
        padding: 0;
    }

    .pr-table-wrap {
        margin: 0 -16px;
        padding: 0 16px;
        width: calc(100% + 32px);
        display: block;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border-radius: 0;
    }

    .pr-edit-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .pr-summary {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

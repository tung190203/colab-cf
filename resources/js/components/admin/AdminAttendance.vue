<script setup>
import { computed, onMounted, ref } from 'vue';
import AdminLayout from './AdminLayout.vue';
import { useAdminAuth } from '../../composables/useAdminAuth';
import { toast } from 'vue3-toastify';
import axios from 'axios';
import { CalendarCheck, CalendarX, Clock, ClipboardCheck, RotateCcw, UserRound } from 'lucide-vue-next';

const { authHeader } = useAdminAuth();
const now = new Date();
const selectedMonth = ref(now.getMonth() + 1);
const selectedYear = ref(now.getFullYear());
const selectedStaffId = ref('');
const records = ref([]);
const staff = ref([]);
const shifts = ref([]);
const loading = ref(true);
const saving = ref(false);
const editModal = ref(false);
const editForm = ref({
    staff_id: null,
    staff_name: '',
    date: '',
    shift: '',
    shift_start_time: '',
    shift_end_time: '',
    is_special_shift: false,
    check_in_time: '',
    check_out_time: '',
    note: '',
});

const years = computed(() => {
    const year = now.getFullYear();
    return [year - 1, year, year + 1];
});

onMounted(async () => {
    await Promise.all([fetchStaff(), fetchShifts()]);
    await fetchAttendance();
});

async function fetchStaff() {
    try {
        const res = await axios.get('/api/admin/staff', { headers: authHeader() });
        staff.value = res.data.filter(item => item.role === 'staff' || item.role === 'shift_leader');
    } catch (e) {
        toast.error('Không thể tải danh sách nhân viên');
    }
}

async function fetchShifts() {
    try {
        const res = await axios.get('/api/shifts', { headers: authHeader() });
        shifts.value = res.data;
    } catch (e) {}
}

async function fetchAttendance() {
    loading.value = true;
    try {
        const params = new URLSearchParams({
            month: selectedMonth.value,
            year: selectedYear.value,
        });

        if (selectedStaffId.value) {
            params.append('staff_id', selectedStaffId.value);
        }

        const res = await axios.get(`/api/admin/attendance?${params.toString()}`, { headers: authHeader() });
        records.value = res.data;
    } catch (e) {
        toast.error('Không thể tải lịch sử chấm công');
    } finally {
        loading.value = false;
    }
}

function getShiftName(key) {
    const shift = shifts.value.find(item => item.key === key);
    return shift ? shift.name : key;
}

function formatTime(value) {
    if (!value) return '--:--';
    return new Date(value).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
}

function toTimeInput(value) {
    if (!value) return '';
    const d = new Date(value);
    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

function formatDate(value) {
    const date = new Date(value);
    return date.toLocaleDateString('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatDateTime(value) {
    if (!value) return '';
    return new Date(value).toLocaleString('vi-VN', {
        hour: '2-digit',
        minute: '2-digit',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function calcHours(checkIn, checkOut) {
    if (!checkIn || !checkOut) return 0;
    return Math.max(0, (new Date(checkOut) - new Date(checkIn)) / 3600000);
}

function displayHours(checkIn, checkOut) {
    return calcHours(checkIn, checkOut).toFixed(1);
}

function statusOf(record) {
    if (record.check_out_at) return { text: 'Hoàn thành', className: 'done' };
    if (record.check_in_at) return { text: 'Thiếu giờ ra', className: 'warn' };
    return { text: 'Vắng mặt', className: 'absent' };
}

function adjustmentText(record) {
    const changes = record.latest_adjustment_changes || [];
    if (changes.length) return changes.join(', ');
    return record.is_manual_adjusted ? 'Đã chỉnh thủ công' : '';
}

function openEdit(record) {
    editForm.value = {
        staff_id: record.staff_id,
        staff_name: record.staff_name,
        date: record.date,
        shift: record.shift,
        shift_start_time: record.shift_start_time || '',
        shift_end_time: record.shift_end_time || '',
        is_special_shift: Boolean(record.is_ot || record.is_holiday),
        check_in_time: toTimeInput(record.check_in_at),
        check_out_time: toTimeInput(record.check_out_at),
        note: record.note || '',
    };
    editModal.value = true;
}

function clampTimeToShift(field) {
    if (editForm.value.is_special_shift || !editForm.value[field]) return;
    if (editForm.value.shift_start_time && editForm.value[field] < editForm.value.shift_start_time) {
        editForm.value[field] = editForm.value.shift_start_time;
    }
    if (editForm.value.shift_end_time && editForm.value[field] > editForm.value.shift_end_time) {
        editForm.value[field] = editForm.value.shift_end_time;
    }
}

async function saveAttendance() {
    saving.value = true;
    try {
        await axios.post('/api/admin/attendance', {
            staff_id: editForm.value.staff_id,
            date: editForm.value.date,
            shift: editForm.value.shift,
            check_in_time: editForm.value.check_in_time || null,
            check_out_time: editForm.value.check_out_time || null,
            note: editForm.value.note || null,
        }, { headers: authHeader() });

        toast.success('Đã cập nhật chấm công');
        editModal.value = false;
        await fetchAttendance();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Không thể cập nhật chấm công');
    } finally {
        saving.value = false;
    }
}

const workedShifts = computed(() => records.value.filter(item => item.check_in_at).length);
const absentShifts = computed(() => records.value.filter(item => !item.check_in_at).length);
const incompleteShifts = computed(() => records.value.filter(item => item.check_in_at && !item.check_out_at).length);
const adjustedShifts = computed(() => records.value.filter(item => item.is_manual_adjusted).length);
const totalHours = computed(() => {
    const sum = records.value.reduce((total, item) => total + Number(item.payable_hours || 0), 0);
    return sum.toFixed(1);
});
</script>

<template>
    <AdminLayout>
        <template #title>Theo dõi chấm công</template>

        <div class="aa-wrap">
            <div class="aa-filter">
                <div class="aa-filter-group">
                    <label>Nhân viên</label>
                    <select v-model="selectedStaffId" @change="fetchAttendance">
                        <option value="">Tất cả nhân viên</option>
                        <option v-for="item in staff" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </select>
                </div>
                <div class="aa-filter-group">
                    <label>Tháng</label>
                    <select v-model="selectedMonth" @change="fetchAttendance">
                        <option v-for="month in 12" :key="month" :value="month">Tháng {{ String(month).padStart(2, '0') }}</option>
                    </select>
                </div>
                <div class="aa-filter-group">
                    <label>Năm</label>
                    <select v-model="selectedYear" @change="fetchAttendance">
                        <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                    </select>
                </div>
                <button class="aa-refresh" @click="fetchAttendance">
                    <RotateCcw :size="16" />
                    Tải lại
                </button>
            </div>

            <div class="aa-summary">
                <div class="aa-card">
                    <CalendarCheck :size="20" />
                    <span class="aa-card-label">Ca có chấm công</span>
                    <strong>{{ workedShifts }}</strong>
                </div>
                <div class="aa-card">
                    <CalendarX :size="20" />
                    <span class="aa-card-label">Ca vắng</span>
                    <strong>{{ absentShifts }}</strong>
                </div>
                <div class="aa-card">
                    <Clock :size="20" />
                    <span class="aa-card-label">Tổng giờ</span>
                    <strong>{{ totalHours }}h</strong>
                </div>
                <div class="aa-card">
                    <ClipboardCheck :size="20" />
                    <span class="aa-card-label">Thiếu giờ ra</span>
                    <strong>{{ incompleteShifts }}</strong>
                </div>
                <div class="aa-card adjusted">
                    <ClipboardCheck :size="20" />
                    <span class="aa-card-label">Đã sửa</span>
                    <strong>{{ adjustedShifts }}</strong>
                </div>
            </div>

            <div class="aa-table-section">
                <div class="aa-table-head">
                    <h3>Lịch sử chấm công</h3>
                    <span>{{ records.length }} ca</span>
                </div>

                <div v-if="loading" class="aa-loading">
                    <div class="aa-spinner"></div>
                </div>

                <div v-else class="aa-table-wrap">
                    <table class="aa-table">
                        <thead>
                            <tr>
                                <th>Nhân viên</th>
                                <th>Ngày</th>
                                <th>Ca</th>
                                <th>Giờ vào</th>
                                <th>Giờ ra</th>
                                <th>Số giờ</th>
                                <th>Trạng thái</th>
                                <th>Chỉnh sửa</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="records.length === 0">
                                <td colspan="9" class="aa-empty">Chưa có dữ liệu chấm công.</td>
                            </tr>
                            <tr v-for="record in records" :key="record.id">
                                <td>
                                    <div class="aa-staff">
                                        <img v-if="record.staff_image_url" :src="record.staff_image_url" :alt="record.staff_name" />
                                        <span v-else class="aa-avatar"><UserRound :size="16" /></span>
                                        <div>
                                            <strong>{{ record.staff_name }}</strong>
                                            <small>{{ record.staff_role === 'shift_leader' ? 'Trưởng ca' : 'Nhân viên' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ formatDate(record.date) }}</td>
                                <td>{{ getShiftName(record.shift) }}</td>
                                <td>{{ formatTime(record.check_in_at) }}</td>
                                <td>{{ formatTime(record.check_out_at) }}</td>
                                <td>{{ Number(record.payable_hours || 0).toFixed(1) }}</td>
                                <td>
                                    <span class="aa-badge" :class="statusOf(record).className">
                                        {{ statusOf(record).text }}
                                    </span>
                                </td>
                                <td>
                                    <div v-if="record.is_manual_adjusted" class="aa-adjusted-cell">
                                        <span class="aa-adjusted-badge">Đã sửa</span>
                                        <small>{{ adjustmentText(record) }}</small>
                                        <em v-if="record.latest_adjustment_at">
                                            {{ formatDateTime(record.latest_adjustment_at) }}
                                            <template v-if="record.adjusted_by_name"> bởi {{ record.adjusted_by_name }}</template>
                                        </em>
                                    </div>
                                    <span v-else class="aa-not-adjusted">Gốc</span>
                                </td>
                                <td>
                                    <button class="aa-edit-btn" @click="openEdit(record)">Sửa công</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="editModal" class="aa-modal-overlay" @click.self="editModal = false">
                <div class="aa-modal">
                    <div class="aa-modal-header">
                        <div>
                            <h3>Sửa chấm công</h3>
                            <p>{{ editForm.staff_name }} - {{ formatDate(editForm.date) }} - {{ getShiftName(editForm.shift) }}</p>
                        </div>
                        <button @click="editModal = false">×</button>
                    </div>

                    <div class="aa-modal-body">
                        <div class="aa-shift-detail">
                            <div>
                                <span>Khung giờ ca</span>
                                <strong>{{ editForm.shift_start_time || '--:--' }} - {{ editForm.shift_end_time || '--:--' }}</strong>
                            </div>
                            <div>
                                <span>Loại ca</span>
                                <strong>{{ editForm.is_special_shift ? 'OT / Lễ' : 'Ca thường' }}</strong>
                            </div>
                        </div>
                        <div class="aa-form-row">
                            <div class="aa-field">
                                <label>Giờ vào</label>
                                <input
                                    v-model="editForm.check_in_time"
                                    type="time"
                                    :min="editForm.is_special_shift ? null : editForm.shift_start_time"
                                    :max="editForm.is_special_shift ? null : editForm.shift_end_time"
                                    @change="clampTimeToShift('check_in_time')"
                                />
                            </div>
                            <div class="aa-field">
                                <label>Giờ ra</label>
                                <input
                                    v-model="editForm.check_out_time"
                                    type="time"
                                    :min="editForm.is_special_shift ? null : editForm.shift_start_time"
                                    :max="editForm.is_special_shift ? null : editForm.shift_end_time"
                                    @change="clampTimeToShift('check_out_time')"
                                />
                            </div>
                        </div>
                        <div v-if="!editForm.is_special_shift" class="aa-time-hint">
                            Chỉ được sửa trong khung ca {{ editForm.shift_start_time }} - {{ editForm.shift_end_time }}.
                        </div>
                        <div class="aa-field">
                            <label>Ghi chú chỉnh sửa</label>
                            <textarea v-model="editForm.note" rows="3" placeholder="Ví dụ: Nhân viên quên check-in, đã xác nhận qua camera"></textarea>
                        </div>
                    </div>

                    <div class="aa-modal-footer">
                        <button class="aa-btn-cancel" @click="editModal = false">Hủy</button>
                        <button class="aa-btn-save" @click="saveAttendance" :disabled="saving">
                            {{ saving ? 'Đang lưu...' : 'Lưu chấm công' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>

<style scoped>
.aa-wrap {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 20px;
    font-family: 'Inter', sans-serif;
}

.aa-filter {
    display: flex;
    align-items: end;
    gap: 12px;
    padding: 18px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    flex-wrap: wrap;
}

.aa-filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 160px;
}

.aa-filter-group:first-child {
    min-width: 240px;
}

.aa-filter-group label {
    color: #64748b;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
}

.aa-filter select {
    height: 42px;
    border: 1px solid #dbe3ea;
    border-radius: 10px;
    background: #f8fafc;
    padding: 0 12px;
    color: #1e293b;
    font-weight: 600;
    outline: none;
}

.aa-refresh {
    height: 42px;
    border: 0;
    border-radius: 10px;
    background: #1f2937;
    color: #fff;
    padding: 0 16px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
    cursor: pointer;
}

.aa-summary {
    display: flex;
    align-items: stretch;
    gap: 14px;
    overflow-x: auto;
    padding-bottom: 4px;
    -webkit-overflow-scrolling: touch;
}

.aa-card {
    min-width: 190px;
    flex: 1 0 190px;
    background: #fff;
    border: 1px solid #edf2f7;
    border-radius: 14px;
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    color: #475569;
}

.aa-card svg {
    color: #15803d;
}

.aa-card.adjusted svg {
    color: #b45309;
}

.aa-card-label {
    color: #64748b;
    font-size: 0.85rem;
    font-weight: 600;
}

.aa-card strong {
    color: #111827;
    font-size: 1.7rem;
    line-height: 1;
}

.aa-table-section {
    background: #fff;
    border: 1px solid #edf2f7;
    border-radius: 18px;
    padding: 22px;
}

.aa-table-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 16px;
}

.aa-table-head h3 {
    margin: 0;
    color: #111827;
    font-size: 1.08rem;
}

.aa-table-head span {
    color: #64748b;
    font-size: 0.9rem;
    font-weight: 700;
}

.aa-loading {
    display: flex;
    justify-content: center;
    padding: 50px;
}

.aa-spinner {
    width: 32px;
    height: 32px;
    border: 3px solid #e5e7eb;
    border-top-color: #15803d;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.aa-table-wrap {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.aa-table {
    width: 100%;
    min-width: 1440px;
    border-collapse: collapse;
}

.aa-table th {
    padding: 12px 14px;
    color: #64748b;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    font-size: 0.76rem;
    text-transform: uppercase;
    white-space: nowrap;
}

.aa-table td {
    padding: 14px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    vertical-align: middle;
    white-space: nowrap;
}

.aa-staff {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 220px;
}

.aa-staff img,
.aa-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    object-fit: cover;
    flex: 0 0 auto;
}

.aa-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e2e8f0;
    color: #64748b;
}

.aa-staff strong {
    display: block;
    color: #111827;
    font-size: 0.95rem;
    white-space: nowrap;
}

.aa-staff small {
    display: block;
    color: #64748b;
    margin-top: 2px;
}

.aa-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 6px 10px;
    font-size: 0.78rem;
    font-weight: 800;
    white-space: nowrap;
}

.aa-badge.done {
    background: #dcfce7;
    color: #15803d;
}

.aa-badge.warn {
    background: #fef3c7;
    color: #b45309;
}

.aa-badge.absent {
    background: #fee2e2;
    color: #dc2626;
}

.aa-adjusted-cell {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    min-width: 260px;
    white-space: nowrap;
}

.aa-adjusted-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    background: #fff7ed;
    color: #c2410c;
    border: 1px solid #fed7aa;
    padding: 5px 9px;
    font-size: 0.76rem;
    font-weight: 900;
}

.aa-adjusted-cell small {
    color: #334155;
    font-size: 0.78rem;
    font-weight: 800;
    white-space: nowrap;
}

.aa-adjusted-cell em {
    color: #64748b;
    font-size: 0.74rem;
    font-style: normal;
    line-height: 1.35;
    white-space: nowrap;
}

.aa-not-adjusted {
    color: #94a3b8;
    font-size: 0.8rem;
    font-weight: 700;
}

.aa-edit-btn {
    border: 1px solid #dbe3ea;
    background: #ffffff;
    color: #1f2937;
    border-radius: 9px;
    padding: 8px 12px;
    font-weight: 800;
    font-size: 0.8rem;
    cursor: pointer;
    white-space: nowrap;
}

.aa-edit-btn:hover {
    border-color: #2D4F1E;
    color: #2D4F1E;
    background: #f8fafc;
}

.aa-empty {
    text-align: center;
    color: #94a3b8;
    padding: 42px 16px !important;
}

.aa-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 1100;
    background: rgba(15, 23, 42, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.aa-modal {
    width: 100%;
    max-width: 480px;
    background: #ffffff;
    border-radius: 18px;
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
    overflow: hidden;
}

.aa-modal-header {
    padding: 20px 22px;
    border-bottom: 1px solid #edf2f7;
    display: flex;
    justify-content: space-between;
    gap: 16px;
}

.aa-modal-header h3 {
    margin: 0;
    color: #111827;
    font-size: 1.05rem;
}

.aa-modal-header p {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 0.85rem;
}

.aa-modal-header button {
    width: 32px;
    height: 32px;
    border: 0;
    border-radius: 9px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 1.4rem;
    line-height: 1;
    cursor: pointer;
}

.aa-modal-body {
    padding: 22px;
}

.aa-shift-detail {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 18px;
}

.aa-shift-detail > div {
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    border-radius: 12px;
    padding: 12px;
}

.aa-shift-detail span {
    display: block;
    color: #64748b;
    font-size: 0.74rem;
    font-weight: 800;
    text-transform: uppercase;
    margin-bottom: 5px;
}

.aa-shift-detail strong {
    display: block;
    color: #111827;
    font-size: 0.95rem;
    font-weight: 900;
}

.aa-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.aa-field {
    display: flex;
    flex-direction: column;
    gap: 7px;
    margin-bottom: 16px;
}

.aa-field:last-child {
    margin-bottom: 0;
}

.aa-field label {
    color: #64748b;
    font-size: 0.78rem;
    font-weight: 800;
    text-transform: uppercase;
}

.aa-field input,
.aa-field textarea {
    width: 100%;
    border: 1px solid #dbe3ea;
    border-radius: 11px;
    background: #f8fafc;
    color: #1e293b;
    font-weight: 700;
    outline: none;
    box-sizing: border-box;
}

.aa-field input {
    height: 42px;
    padding: 0 12px;
}

.aa-field textarea {
    resize: vertical;
    padding: 12px;
    font-family: inherit;
}

.aa-field input:focus,
.aa-field textarea:focus {
    background: #ffffff;
    border-color: #2D4F1E;
    box-shadow: 0 0 0 4px rgba(45, 79, 30, 0.08);
}

.aa-time-hint {
    margin: -6px 0 16px;
    color: #64748b;
    font-size: 0.82rem;
    font-weight: 700;
}

.aa-modal-footer {
    padding: 16px 22px;
    background: #f8fafc;
    border-top: 1px solid #edf2f7;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.aa-btn-cancel,
.aa-btn-save {
    height: 40px;
    border: 0;
    border-radius: 10px;
    padding: 0 16px;
    font-weight: 800;
    cursor: pointer;
}

.aa-btn-cancel {
    background: #e2e8f0;
    color: #475569;
}

.aa-btn-save {
    background: #2D4F1E;
    color: #ffffff;
}

.aa-btn-save:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .aa-filter {
        align-items: stretch;
    }

    .aa-filter-group,
    .aa-filter-group:first-child,
    .aa-refresh {
        width: 100%;
        min-width: 0;
    }

    .aa-table-section {
        padding: 16px;
    }

    .aa-form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
}
</style>

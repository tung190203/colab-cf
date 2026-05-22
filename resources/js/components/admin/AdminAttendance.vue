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

function formatDate(value) {
    const date = new Date(value);
    return date.toLocaleDateString('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });
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

const workedShifts = computed(() => records.value.filter(item => item.check_in_at).length);
const absentShifts = computed(() => records.value.filter(item => !item.check_in_at).length);
const incompleteShifts = computed(() => records.value.filter(item => item.check_in_at && !item.check_out_at).length);
const totalHours = computed(() => {
    const sum = records.value.reduce((total, item) => total + calcHours(item.check_in_at, item.check_out_at), 0);
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
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="records.length === 0">
                                <td colspan="7" class="aa-empty">Chưa có dữ liệu chấm công.</td>
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
                                <td>{{ displayHours(record.check_in_at, record.check_out_at) }}</td>
                                <td>
                                    <span class="aa-badge" :class="statusOf(record).className">
                                        {{ statusOf(record).text }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
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
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
}

.aa-card {
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
    overflow-x: auto;
}

.aa-table {
    width: 100%;
    min-width: 880px;
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
}

.aa-table td {
    padding: 14px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    vertical-align: middle;
}

.aa-staff {
    display: flex;
    align-items: center;
    gap: 10px;
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

.aa-empty {
    text-align: center;
    color: #94a3b8;
    padding: 42px 16px !important;
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

    .aa-summary {
        grid-template-columns: repeat(2, 1fr);
    }

    .aa-table-section {
        padding: 16px;
    }
}
</style>

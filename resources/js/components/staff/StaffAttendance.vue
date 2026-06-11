<script setup>
import { ref, onMounted, computed, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import AdminLayout from '../admin/AdminLayout.vue';
import { useAdminAuth } from '../../composables/useAdminAuth';
import { toast } from 'vue3-toastify';
import axios from 'axios';
import { Clock, LogIn, LogOut, CalendarCheck, CalendarX, Calendar, AlertCircle, CheckCircle2, ClipboardCheck, Hourglass } from 'lucide-vue-next';

const { authHeader } = useAdminAuth();
const router = useRouter();

const checkInLoading = ref({});
const checkOutLoading = ref({});
const monthRecords = ref([]);
const loadingRecords = ref(true);

const todaySchedules = ref([]);
const todayAttendances = ref([]);
const SHIFTS = ref([]);
const handoverReminder = ref({
    show: false,
    type: '',
    shiftName: '',
});

const now = new Date();
const selectedMonth = ref(now.getMonth() + 1);
const selectedYear = ref(now.getFullYear());

onMounted(async () => {
    await fetchShifts();
    await fetchToday();
    await fetchMonthRecords();
});

function toDateStr(d) {
    if (!d) return '';
    const dateObj = typeof d === 'string' ? new Date(d) : d;
    const year = dateObj.getFullYear();
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    const day = String(dateObj.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

async function fetchShifts() {
    try {
        const res = await axios.get('/api/shifts', { headers: authHeader() });
        SHIFTS.value = res.data;
    } catch (e) {}
}

async function fetchToday() {
    try {
        const todayStr = toDateStr(new Date());
        
        // Lấy lịch làm việc hôm nay
        const schedRes = await axios.get(`/api/staff/schedule?from=${todayStr}&to=${todayStr}`, { headers: authHeader() });
        todaySchedules.value = schedRes.data;

        // Lấy dữ liệu điểm danh tháng này
        const attRes = await axios.get(`/api/staff/attendance?month=${now.getMonth()+1}&year=${now.getFullYear()}`, { headers: authHeader() });
        
        // Lọc điểm danh ngày hôm nay
        todayAttendances.value = attRes.data.filter(r => r.date === todayStr);
    } catch(e) {}
}

async function fetchMonthRecords() {
    loadingRecords.value = true;
    try {
        const res = await axios.get(`/api/staff/attendance?month=${selectedMonth.value}&year=${selectedYear.value}`, { headers: authHeader() });
        monthRecords.value = res.data;
    } catch(e) { toast.error('Không thể tải dữ liệu'); }
    finally { loadingRecords.value = false; }
}

async function checkIn(shiftKey) {
    checkInLoading.value[shiftKey] = true;
    try {
        const coords = await getCurrentLocation();
        await axios.post('/api/staff/check-in', { 
            shift: shiftKey,
            lat: coords.lat,
            lng: coords.lng
        }, { headers: authHeader() });
        toast.success(`Check-in ca ${getShiftName(shiftKey)} thành công!`);
        showHandoverReminder('receive', shiftKey);
        await fetchToday();
        await fetchMonthRecords();
    } catch(e) { 
        console.error('Check-in error details:', e);
        toast.error(getErrorMsg(e, 'Lỗi check-in')); 
    }
    finally { checkInLoading.value[shiftKey] = false; }
}

async function checkOut(shiftKey) {
    checkOutLoading.value[shiftKey] = true;
    try {
        const coords = await getCurrentLocation();
        await axios.post('/api/staff/check-out', { 
            shift: shiftKey,
            lat: coords.lat,
            lng: coords.lng
        }, { headers: authHeader() });
        toast.success(`Check-out ca ${getShiftName(shiftKey)} thành công!`);
        showHandoverReminder('handover', shiftKey);
        await fetchToday();
        await fetchMonthRecords();
    } catch(e) { 
        console.error('Check-out error details:', e);
        toast.error(getErrorMsg(e, 'Lỗi check-out')); 
    }
    finally { checkOutLoading.value[shiftKey] = false; }
}

function formatTime(dt) {
    if (!dt) return '--:--';
    return new Date(dt).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
}

function calcHours(ci, co) {
    if (!ci || !co) return 0;
    const diff = (new Date(co) - new Date(ci)) / 3600000;
    return Math.max(0, diff);
}

function displayHours(ci, co) {
    const h = calcHours(ci, co);
    return h > 0 ? h.toFixed(1) : '0.0';
}

function getShiftName(key) {
    const s = SHIFTS.value.find(x => x.key === key);
    return s ? s.name : key;
}

function getAttForShift(shiftKey) {
    return todayAttendances.value.find(a => a.shift === shiftKey) || null;
}

const todayDateStr = now.toLocaleDateString('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });

// Stats calculations
const totalWorkedShifts = computed(() => monthRecords.value.filter(r => r.check_in_at).length);
const totalAbsentShifts = computed(() => monthRecords.value.filter(r => !r.check_in_at && new Date(r.date) < new Date()).length);
const totalHours = computed(() => {
    let sum = 0;
    monthRecords.value.forEach(r => {
        if (r.check_in_at && r.check_out_at) sum += calcHours(r.check_in_at, r.check_out_at);
    });
    return sum.toFixed(1);
});
const attendanceRate = computed(() => {
    const totalPast = monthRecords.value.filter(r => new Date(r.date) < new Date() || r.check_in_at).length;
    if (totalPast === 0) return 100;
    return Math.round((totalWorkedShifts.value / totalPast) * 100);
});

function getWeekday(dateStr) {
    const d = new Date(dateStr);
    const days = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
    return days[d.getDay()];
}
function formatDateShort(dateStr) {
    const d = new Date(dateStr);
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    return `${day}/${month}`;
}

const filteredHistory = computed(() => {
    const todayStr = toDateStr(new Date());
    return monthRecords.value.filter(r => {
        // Nếu là ngày hôm nay, đang có check-in nhưng chưa có check-out thì tạm ẩn khỏi lịch sử
        if (r.date === todayStr && r.check_in_at && !r.check_out_at) {
            return false;
        }
        return true;
    });
});

async function getCurrentLocation() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('Trình duyệt của bạn không hỗ trợ định vị GPS.'));
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (pos) => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
            (err) => {
                console.warn('Geolocation error:', err);
                if (err.code === 1) reject(new Error('Bạn phải cho phép truy cập vị trí để chấm công.'));
                else if (err.code === 3) reject(new Error('Hết thời gian xác định vị trí. Vui lòng thử lại.'));
                else reject(new Error('Không thể xác định vị trí. Vui lòng thử lại.'));
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });
}

function getErrorMsg(e, defaultMsg) {
    if (e.response?.data?.errors) {
        return Object.values(e.response.data.errors).flat()[0];
    }
    return e.response?.data?.message || e.message || defaultMsg;
}

function showHandoverReminder(type, shiftKey) {
    handoverReminder.value = {
        show: true,
        type,
        shiftName: getShiftName(shiftKey),
    };
}

function closeHandoverReminder() {
    handoverReminder.value.show = false;
}

function goToShiftHandover() {
    const tab = handoverReminder.value.type === 'receive' ? 'pending' : 'create';
    handoverReminder.value.show = false;
    router.push(`/staff/shift-handover?tab=${tab}`);
}
</script>

<template>
    <AdminLayout>
        <template #title>Chấm công</template>

        <div class="att-wrap">
            
            <!-- Dark Cards for Today's Shifts -->
            <div v-if="todaySchedules.length === 0" class="att-empty-schedule">
                <div class="aes-icon">🌴</div>
                <h4>Hôm nay bạn không có ca làm</h4>
                <p>Tận hưởng ngày nghỉ của bạn nhé!</p>
            </div>

            <div v-else class="att-shifts-container">
                <div v-for="sched in todaySchedules" :key="sched.id" class="att-dark-card">
                    <div class="adc-header">
                        <div class="adc-date">{{ todayDateStr.toUpperCase() }}</div>
                        <h3 class="adc-title">Chấm công - {{ getShiftName(sched.shift) }}</h3>
                    </div>

                    <div class="adc-time-boxes">
                        <div class="adc-box">
                            <div class="adc-icon"><Clock :size="20"/></div>
                            <div class="adc-label">Giờ vào</div>
                            <div class="adc-val">{{ formatTime(getAttForShift(sched.shift)?.check_in_at) }}</div>
                        </div>
                        <div class="adc-box">
                            <div class="adc-icon"><LogOut :size="20"/></div>
                            <div class="adc-label">Giờ ra</div>
                            <div class="adc-val">{{ formatTime(getAttForShift(sched.shift)?.check_out_at) }}</div>
                        </div>
                        <div class="adc-box">
                            <div class="adc-icon"><Hourglass :size="20"/></div>
                            <div class="adc-label">Số giờ</div>
                            <div class="adc-val">{{ displayHours(getAttForShift(sched.shift)?.check_in_at, getAttForShift(sched.shift)?.check_out_at) }}</div>
                        </div>
                    </div>

                    <div class="adc-actions">
                        <button
                            class="adc-btn adc-btn-in"
                            :disabled="!!getAttForShift(sched.shift)?.check_in_at || checkInLoading[sched.shift]"
                            @click="checkIn(sched.shift)"
                        >
                            <span v-if="checkInLoading[sched.shift]" class="att-spinner-small"></span>
                            <CheckCircle2 v-else :size="18"/>
                            Check-in
                        </button>
                        <button
                            class="adc-btn adc-btn-out"
                            :disabled="!getAttForShift(sched.shift)?.check_in_at || !!getAttForShift(sched.shift)?.check_out_at || checkOutLoading[sched.shift]"
                            @click="checkOut(sched.shift)"
                        >
                            <span v-if="checkOutLoading[sched.shift]" class="att-spinner-small"></span>
                            <ClipboardCheck v-else :size="18"/>
                            Check-out
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="att-stats-grid">
                <div class="stat-card" style="border-left-color: #22c55e;">
                    <div class="st-icon st-icon-green"><CalendarCheck :size="20"/></div>
                    <div class="st-val">{{ totalWorkedShifts }}</div>
                    <div class="st-label">Ca công tháng {{ selectedMonth }}</div>
                </div>
                <div class="stat-card" style="border-left-color: #ef4444;">
                    <div class="st-icon st-icon-red"><CalendarX :size="20"/></div>
                    <div class="st-val">{{ totalAbsentShifts }}</div>
                    <div class="st-label">Ca nghỉ</div>
                </div>
                <div class="stat-card" style="border-left-color: #84cc16;">
                    <div class="st-icon st-icon-lime"><Clock :size="20"/></div>
                    <div class="st-val">{{ totalHours }}h</div>
                    <div class="st-label">Tổng giờ làm</div>
                </div>
                <div class="stat-card" style="border-left-color: #64748b;">
                    <div class="st-icon st-icon-gray"><ClipboardCheck :size="20"/></div>
                    <div class="st-val">{{ attendanceRate }}%</div>
                    <div class="st-label">Chỉ số chuyên cần</div>
                </div>
            </div>

            <!-- History Table -->
            <div class="att-history-section">
                <div class="ahs-header">
                    <h4>Lịch sử chấm công</h4>
                    <div class="ahs-filters">
                        <select v-model="selectedMonth" @change="fetchMonthRecords">
                            <option v-for="m in 12" :key="m" :value="m">Tháng {{ String(m).padStart(2,'0') }}</option>
                        </select>
                        <select v-model="selectedYear" @change="fetchMonthRecords">
                            <option v-for="y in [2025, 2026, 2027]" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>
                </div>

                <div v-if="loadingRecords" class="ahs-loading">
                    <div class="att-spinner"></div>
                </div>
                
                <div v-else class="ahs-table-wrapper">
                    <table class="ahs-table">
                        <thead>
                            <tr>
                                <th class="th-date">NGÀY</th>
                                <th>CA</th>
                                <th>GIỜ VÀO</th>
                                <th>GIỜ RA</th>
                                <th>SỐ GIỜ</th>
                                <th>TRẠNG THÁI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="filteredHistory.length === 0">
                                <td colspan="6" class="ahs-empty">Chưa có dữ liệu chấm công.</td>
                            </tr>
                            <tr v-for="r in filteredHistory" :key="r.id">
                                <td class="col-date" :class="{ 'is-absent': !r.check_in_at }">
                                    {{ getWeekday(r.date) }}, {{ formatDateShort(r.date) }}
                                </td>
                                <td :class="{ 'is-absent': !r.check_in_at }">{{ getShiftName(r.shift) }}</td>
                                <td :class="{ 'is-absent': !r.check_in_at }">{{ formatTime(r.check_in_at) }}</td>
                                <td :class="{ 'is-absent': !r.check_in_at }">{{ formatTime(r.check_out_at) }}</td>
                                <td :class="{ 'is-absent': !r.check_in_at }">{{ displayHours(r.check_in_at, r.check_out_at) }}</td>
                                <td>
                                    <span class="badge" :class="r.check_out_at ? 'badge-done' : (r.check_in_at ? 'badge-warn' : 'badge-absent')">
                                        <span class="dot"></span>
                                        {{ r.check_out_at ? 'Hoàn thành' : (r.check_in_at ? 'Thiếu ra' : 'Vắng mặt') }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <div v-if="handoverReminder.show" class="att-reminder-backdrop" @click.self="closeHandoverReminder">
            <div class="att-reminder">
                <div class="att-reminder-icon" :class="handoverReminder.type">
                    <ClipboardCheck :size="30" />
                </div>
                <h3>{{ handoverReminder.type === 'receive' ? 'Nhắc nhận ca' : 'Nhắc giao ca' }}</h3>
                <p>
                    {{ handoverReminder.type === 'receive'
                        ? `Bạn vừa check-in ${handoverReminder.shiftName}. Vào màn nhận ca để kiểm tra bàn giao từ ca trước.`
                        : `Bạn vừa check-out ${handoverReminder.shiftName}. Vào màn giao ca để bàn giao tiền, món bán và ghi chú cho ca sau.`
                    }}
                </p>
                <div class="att-reminder-actions">
                    <button type="button" class="att-reminder-secondary" @click="closeHandoverReminder">Để sau</button>
                    <button type="button" class="att-reminder-primary" @click="goToShiftHandover">
                        {{ handoverReminder.type === 'receive' ? 'Đi nhận ca' : 'Đi giao ca' }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.att-wrap {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 24px;
    font-family: 'Inter', sans-serif;
}

/* Empty schedule */
.att-empty-schedule {
    background: #1c1e21;
    border-radius: 24px;
    padding: 60px 20px;
    text-align: center;
    color: white;
}
.aes-icon { font-size: 3rem; margin-bottom: 12px; }
.att-empty-schedule h4 { margin: 0 0 8px; font-weight: 700; font-size: 1.4rem; }
.att-empty-schedule p { margin: 0; color: #888; }

/* Dark Cards */
.att-shifts-container {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.att-dark-card {
    background: #1c1e21;
    border-radius: 24px;
    padding: 32px;
    color: white;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.adc-header {
    margin-bottom: 24px;
}

.adc-date {
    color: #4ade80;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.adc-title {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
}

.adc-time-boxes {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.adc-box {
    background: #25282c;
    border-radius: 16px;
    padding: 24px 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 8px;
}

.adc-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(255,255,255,0.05);
    color: #4ade80;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 4px;
}

.adc-label {
    font-size: 0.8rem;
    color: #9ca3af;
    font-weight: 500;
}

.adc-val {
    font-size: 1.4rem;
    font-weight: 700;
    color: white;
}

.adc-actions {
    display: flex;
    gap: 16px;
}

.adc-btn {
    flex: 1;
    padding: 16px;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}

.adc-btn-in {
    background: #15803d;
    color: white;
}
.adc-btn-in:hover:not(:disabled) {
    background: #166534;
}

.adc-btn-out {
    background: #2a2d31;
    color: #9ca3af;
}
.adc-btn-out:hover:not(:disabled) {
    background: #374151;
    color: white;
}

.adc-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.att-spinner-small {
    width: 18px; height: 18px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: currentColor;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

/* Stats Grid */
.att-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}

@media (max-width: 768px) {
    .att-stats-grid { grid-template-columns: repeat(2, 1fr); }
    .adc-time-boxes { grid-template-columns: 1fr 1fr 1fr; }
    .adc-actions { flex-direction: column; }
}

@media (max-width: 480px) {
    .att-stats-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
    .adc-time-boxes { grid-template-columns: 1fr; }
    .adc-actions { flex-direction: column; }
    .att-history-section { padding: 20px 16px; border-radius: 16px; }
    .ahs-header { flex-direction: column; align-items: flex-start; gap: 12px; }
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    border-left: 4px solid transparent;
    box-shadow: 0 4px 15px rgba(0,0,0,0.02);
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.st-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 8px;
}

.st-icon-green { background: #dcfce7; color: #16a34a; }
.st-icon-red { background: #fee2e2; color: #ef4444; }
.st-icon-lime { background: #ecfccb; color: #65a30d; }
.st-icon-gray { background: #f1f5f9; color: #64748b; }

.st-val {
    font-size: 1.8rem;
    font-weight: 800;
    color: #1a1a2e;
    line-height: 1;
}

.st-label {
    font-size: 0.8rem;
    color: #64748b;
    font-weight: 500;
}

/* History Section */
.att-history-section {
    background: white;
    border-radius: 24px;
    padding: 32px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.02);
}

.ahs-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.ahs-header h4 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 700;
    color: #1a1a2e;
}

.ahs-filters {
    display: flex;
    gap: 8px;
}

.ahs-filters select {
    padding: 6px 14px;
    border-radius: 100px;
    border: 1px solid #e2e8f0;
    background: #f8fafb;
    font-size: 0.85rem;
    font-weight: 600;
    color: #475569;
    outline: none;
    cursor: pointer;
}

.ahs-loading {
    display: flex;
    justify-content: center;
    padding: 40px;
}

.att-spinner {
    width: 32px; height: 32px;
    border: 3px solid #f0f0f0;
    border-top-color: #16a34a;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

.ahs-table-wrapper {
    overflow-x: auto;
    margin: 0 -16px;
    padding: 0 16px;
    -webkit-overflow-scrolling: touch;
}

.ahs-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}

.ahs-table th {
    text-align: left;
    padding: 16px 12px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
    border-bottom: 1px solid #f1f5f9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.ahs-table th:first-child, .ahs-table td:first-child {
    padding-left: 0;
}
.ahs-table th:last-child, .ahs-table td:last-child {
    padding-right: 0;
}

.ahs-table td {
    padding: 24px 12px;
    font-size: 0.95rem;
    color: #334155;
    font-weight: 600;
    border-bottom: 1px solid #f1f5f9;
}

.ahs-table tr:last-child td {
    border-bottom: none;
}

.col-date {
    font-weight: 700;
}

.is-absent {
    color: #ef4444 !important;
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 100px;
    font-size: 0.75rem;
    font-weight: 700;
}

.dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

.badge-done { background: #dcfce7; color: #16a34a; }
.badge-done .dot { background: #16a34a; }

.badge-warn { background: #fef9c3; color: #ca8a04; }
.badge-warn .dot { background: #ca8a04; }

.badge-absent { background: #fee2e2; color: #ef4444; }
.badge-absent .dot { background: #ef4444; }

.ahs-empty {
    text-align: center;
    padding: 40px !important;
    color: #94a3b8 !important;
    font-weight: 500 !important;
}

.att-reminder-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1400;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 23, 42, 0.55);
}

.att-reminder {
    width: min(420px, 100%);
    padding: 26px;
    border-radius: 20px;
    background: #fff;
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.25);
    text-align: center;
}

.att-reminder-icon {
    width: 58px;
    height: 58px;
    margin: 0 auto 14px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ecfdf3;
    color: #15803d;
}

.att-reminder-icon.handover {
    background: #fff7ed;
    color: #c2410c;
}

.att-reminder h3 {
    margin: 0 0 8px;
    color: #101828;
    font-size: 1.35rem;
    font-weight: 800;
}

.att-reminder p {
    margin: 0;
    color: #667085;
    line-height: 1.55;
    font-weight: 600;
}

.att-reminder-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-top: 22px;
}

.att-reminder-actions button {
    min-height: 44px;
    border-radius: 10px;
    font-weight: 800;
    cursor: pointer;
}

.att-reminder-secondary {
    border: 1px solid #d0d5dd;
    background: #fff;
    color: #344054;
}

.att-reminder-primary {
    border: 1px solid #20451f;
    background: #20451f;
    color: #fff;
}
</style>

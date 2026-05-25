<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import AdminLayout from '../admin/AdminLayout.vue';
import { useAdminAuth } from '../../composables/useAdminAuth';
import axios from 'axios';
import { toast } from 'vue3-toastify';
import { ChevronLeft, ChevronRight, X, Clock, Check } from 'lucide-vue-next';

const { authHeader } = useAdminAuth();

// State
const staff = ref([]);
const schedules = ref([]);
const loading = ref(true);
const currentMonth = ref(new Date()); 

const SHIFTS = ref([]);

async function fetchShifts() {
    try {
        const res = await axios.get('/api/shifts', { headers: authHeader() });
        SHIFTS.value = res.data.map(s => ({
            ...s,
            label: s.name,
            time: `${s.start_time} - ${s.end_time}`,
            short: s.name.charAt(3) || s.name.charAt(0).toUpperCase()
        }));
    } catch (e) {
        // Silent error
    }
}

// Calendar Logic
const monthDays = computed(() => {
    const year = currentMonth.value.getFullYear();
    const month = currentMonth.value.getMonth();
    const firstDayOfMonth = new Date(year, month, 1);
    const lastDayOfMonth = new Date(year, month + 1, 0);
    const days = [];
    
    let startDay = firstDayOfMonth.getDay(); 
    if (startDay === 0) startDay = 7; 
    
    for (let i = 1; i < startDay; i++) {
        const prevDate = new Date(year, month, 1 - (startDay - i));
        days.push({ date: prevDate, current: false });
    }
    
    for (let i = 1; i <= lastDayOfMonth.getDate(); i++) {
        days.push({ date: new Date(year, month, i), current: true });
    }
    
    const totalDaysSoFar = days.length;
    const remaining = 42 - totalDaysSoFar; 
    for (let i = 1; i <= remaining; i++) {
        days.push({ date: new Date(year, month + 1, i), current: false });
    }
    return days;
});

const monthLabel = computed(() => {
    return currentMonth.value.toLocaleDateString('vi-VN', { month: 'long', year: 'numeric' });
});

// APIs
async function fetchStaff() {
    try {
        // Staff should be able to see who is who
        const res = await axios.get('/api/admin/staff', { headers: authHeader() });
        staff.value = res.data;
    } catch (e) {}
}

async function fetchSchedules() {
    loading.value = true;
    const year = currentMonth.value.getFullYear();
    const month = currentMonth.value.getMonth();
    
    const from = new Date(year, month, -7).toISOString().slice(0, 10);
    const to = new Date(year, month + 1, 14).toISOString().slice(0, 10);
    
    try {
        const res = await axios.get(`/api/admin/schedule?from=${from}&to=${to}`, { headers: authHeader() });
        schedules.value = res.data;
    } catch (e) {
        toast.error('Không thể tải lịch làm việc');
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    loading.value = true;
    await Promise.all([fetchStaff(), fetchShifts(), fetchSchedules()]);
    loading.value = false;
});

watch(currentMonth, () => {
    fetchSchedules();
});

// Actions
function nextMonth() {
    currentMonth.value = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() + 1, 1);
}

function prevMonth() {
    currentMonth.value = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() - 1, 1);
}

function toDateStr(d) {
    if (!d) return '';
    const dateObj = typeof d === 'string' ? new Date(d) : d;
    const year = dateObj.getFullYear();
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    const day = String(dateObj.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function isToday(d) {
    return toDateStr(d) === toDateStr(new Date());
}

function getSchedulesForDay(date) {
    const ds = toDateStr(date);
    return schedules.value.filter(s => toDateStr(s.date) === ds);
}

function getShiftSchedulesForDay(date, shiftKey) {
    return getSchedulesForDay(date).filter(s => s.shift === shiftKey);
}

function formatScheduleNames(items) {
    return items.map(s => {
        const label = s.is_holiday ? 'Lễ' : (s.is_ot ? 'OT' : '');
        return `${s.staff?.name || 'Nhân viên'}${label ? ` (${label} ${formatOtMultiplier(s.ot_multiplier)})` : ''}`;
    }).join(', ');
}

function formatOtMultiplier(value) {
    const n = Number(value || 2);
    return `x${Number.isInteger(n) ? n : n.toFixed(2).replace(/0+$/, '').replace(/\.$/, '')}`;
}

const showModal = ref(false);
const modalDate = ref(null);

function openDay(day) {
    if (!day.current) return;
    modalDate.value = day.date;
    showModal.value = true;
}

const hasShift = (staffId, date, key) => {
    const ds = toDateStr(date);
    return schedules.value.some(s => 
        toDateStr(s.date) === ds && 
        s.shift === key && 
        Number(s.staff_id) === Number(staffId)
    );
};

const getSchedule = (staffId, date, key) => {
    const ds = toDateStr(date);
    return schedules.value.find(s =>
        toDateStr(s.date) === ds &&
        s.shift === key &&
        Number(s.staff_id) === Number(staffId)
    );
};

const isShiftOt = (staffId, date, key) => Boolean(getSchedule(staffId, date, key)?.is_ot);
const isShiftHoliday = (staffId, date, key) => Boolean(getSchedule(staffId, date, key)?.is_holiday);
const getShiftOtMultiplier = (staffId, date, key) => getSchedule(staffId, date, key)?.ot_multiplier;

const employeesOnly = computed(() => {
    return staff.value.filter(s => s.role === 'staff' || s.role === 'shift_leader');
});
</script>

<template>
    <AdminLayout>
        <template #title>Lịch làm việc hệ thống</template>

        <div class="ss-wrap">
            <div class="calendar-view">
                <div class="calendar-header">
                    <div class="header-main">
                        <h3 class="section-title">Bảng lịch chung</h3>
                        <p class="section-subtitle">Xem phân công nhân sự toàn cửa hàng</p>
                    </div>

                    <div class="month-nav">
                        <button @click="prevMonth" class="nav-btn"><ChevronLeft :size="20" /></button>
                        <span class="month-label">{{ monthLabel }}</span>
                        <button @click="nextMonth" class="nav-btn"><ChevronRight :size="20" /></button>
                    </div>
                </div>

                <div class="calendar-grid-wrapper">
                    <div v-if="loading" class="calendar-loading-overlay">
                        <div class="spinner"></div>
                    </div>
                    
                    <div class="calendar-grid">
                        <div v-for="d in ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN']" :key="d" class="grid-header">{{ d }}</div>

                        <div 
                            v-for="(day, idx) in monthDays" 
                            :key="idx" 
                            class="grid-day"
                            :class="{ 'not-current': !day.current, 'is-today': isToday(day.date) }"
                            @click="openDay(day)"
                        >
                            <span class="day-num">{{ day.date.getDate() }}</span>
                            <div class="day-shifts-summary">
                                <template v-for="shift in SHIFTS" :key="shift.key">
                                    <div 
                                        v-if="getShiftSchedulesForDay(day.date, shift.key).length > 0" 
                                        class="shift-names-row"
                                    >
                                        <span class="sn-label" :style="{ color: shift.color }">{{ shift.short }}:</span>
                                        <span class="sn-list">
                                            {{ formatScheduleNames(getShiftSchedulesForDay(day.date, shift.key)) }}
                                        </span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="calendar-legend">
                    <div v-for="s in SHIFTS" :key="s.key" class="legend-item">
                        <span class="dot" :style="{ background: s.color }"></span>
                        <span>{{ s.label }} ({{ s.time }})</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Read-only Day Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showModal" class="modal-overlay" @click="showModal = false">
                    <div class="modal-content modal-content--large" @click.stop>
                        <div class="modal-header">
                            <div>
                                <h3>Chi tiết nhân sự ngày {{ modalDate.toLocaleDateString('vi-VN') }}</h3>
                                <p class="modal-subtitle">Danh sách nhân viên được phân công</p>
                            </div>
                            <button class="close-btn" @click="showModal = false">
                                <X :size="20" />
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="day-management-layout">
                                <div v-for="shift in SHIFTS" :key="shift.key" class="shift-column-premium">
                                    <div class="scp-header" :style="{ background: shift.color }">
                                        <div class="scp-header-content">
                                            <span class="scp-name">{{ shift.label }}</span>
                                            <span class="scp-time">{{ shift.time }}</span>
                                        </div>
                                        <div class="scp-count">
                                            {{ getShiftSchedulesForDay(modalDate, shift.key).length }} nv
                                        </div>
                                    </div>
                                    <div class="scp-staff-list">
                                        <div 
                                            v-for="s in employeesOnly.filter(emp => hasShift(emp.id, modalDate, shift.key))" 
                                            :key="s.id"
                                            class="staff-row-item is-active read-only"
                                        >
                                            <div class="sri-avatar">
                                                <img v-if="s.image_url" :src="s.image_url" />
                                                <span v-else>{{ s.name?.charAt(0)?.toUpperCase() }}</span>
                                            </div>
                                            <div class="sri-info">
                                                <span class="sri-name">
                                                    {{ s.name }}
                                                    <span v-if="isShiftHoliday(s.id, modalDate, shift.key)" class="ot-badge">Lễ {{ formatOtMultiplier(getShiftOtMultiplier(s.id, modalDate, shift.key)) }}</span>
                                                    <span v-else-if="isShiftOt(s.id, modalDate, shift.key)" class="ot-badge">OT {{ formatOtMultiplier(getShiftOtMultiplier(s.id, modalDate, shift.key)) }}</span>
                                                </span>
                                                <span class="sri-status">{{ isShiftHoliday(s.id, modalDate, shift.key) ? `Ca lễ ${formatOtMultiplier(getShiftOtMultiplier(s.id, modalDate, shift.key))}` : (isShiftOt(s.id, modalDate, shift.key) ? `Ca OT ${formatOtMultiplier(getShiftOtMultiplier(s.id, modalDate, shift.key))}` : 'Đã phân công') }}</span>
                                            </div>
                                            <div class="sri-toggle">
                                                <div class="toggle-track">
                                                    <div class="toggle-thumb" :style="{ backgroundColor: shift.color }">
                                                        <Check :size="12" color="white" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="getShiftSchedulesForDay(modalDate, shift.key).length === 0" class="empty-state-mini">
                                            Chưa có nhân sự được phân công
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn-done" @click="showModal = false">Đóng</button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AdminLayout>
</template>

<style scoped>
.ss-wrap { max-width: 1200px; margin: 0 auto; }
.section-title { font-size: 1.5rem; font-weight: 700; color: #1a1a2e; }
.section-subtitle { font-size: 0.9rem; color: #64748b; margin-top: 4px; }

.calendar-view { background: white; border-radius: 24px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
.calendar-header { display: flex; justify-content: space-between; align-items: center; gap: 24px; margin-bottom: 24px; }
.header-main { flex: 1; }

.month-nav { display: flex; align-items: center; gap: 16px; background: #f8fafb; padding: 6px; border-radius: 14px; }
.nav-btn {
    width: 36px; height: 36px; border-radius: 10px; border: none; background: white;
    display: flex; align-items: center; justify-content: center; cursor: pointer;
    color: #555; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: all 0.2s;
}
.nav-btn:hover { color: #2D4F1E; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
.month-label { font-weight: 700; color: #1a1a2e; min-width: 140px; text-align: center; text-transform: capitalize; }

.calendar-grid-wrapper { position: relative; border: 1px solid #f0f0f0; border-radius: 16px; overflow: hidden; }
.calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); }
.grid-header { background: #fafbfc; padding: 12px; text-align: center; font-weight: 700; font-size: 0.8rem; color: #888; border-bottom: 1px solid #f0f0f0; }
.grid-day { min-height: 110px; padding: 10px; border-bottom: 1px solid #f0f0f0; border-right: 1px solid #f0f0f0; cursor: pointer; transition: all 0.2s; position: relative; }
.grid-day:nth-child(7n) { border-right: none; }
.grid-day:hover { background: #f8fafb; }
.grid-day.not-current { background: #fafbfc; color: #ccc; cursor: default; }
.grid-day.is-today { background: rgba(45,79,30,0.04); }
.grid-day.is-today .day-num { background: #2D4F1E; color: white; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; border-radius: 6px; }

.day-num { font-weight: 600; font-size: 0.9rem; margin-bottom: 8px; display: inline-block; }
.day-shifts-summary {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-top: 4px;
    max-height: 80px;
    overflow-y: auto;
}
.day-shifts-summary::-webkit-scrollbar { width: 2px; }
.day-shifts-summary::-webkit-scrollbar-thumb { background: #e2e8f0; }

.shift-names-row { display: flex; gap: 4px; line-height: 1.2; }
.sn-label { font-size: 0.65rem; font-weight: 800; white-space: nowrap; }
.sn-list { font-size: 0.65rem; font-weight: 600; color: #475569; display: block; }

.calendar-legend { display: flex; gap: 24px; margin-top: 24px; padding-top: 24px; border-top: 1px solid #f0f0f0; justify-content: center; flex-wrap: wrap; }
.legend-item { display: flex; align-items: center; gap: 8px; font-size: 0.8rem; color: #666; }
.dot { width: 10px; height: 10px; border-radius: 50%; }

/* Modal */
.modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(4px); }
.modal-content { background: white; width: 95%; max-width: 450px; border-radius: 24px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
.modal-content--large { max-width: 1000px; }

.modal-header { padding: 20px 24px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { margin: 0; font-size: 1.1rem; font-weight: 800; color: #1e293b; }
.modal-subtitle { font-size: 0.85rem; color: #64748b; margin-top: 4px; }
.close-btn { background: none; border: none; cursor: pointer; color: #94a3b8; }

.modal-body { padding: 24px; }
.modal-footer { padding: 16px 24px; background: #f8fafc; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; }

.day-management-layout {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 20px;
}

.shift-column-premium {
    background: #f8fafc;
    border-radius: 24px;
    overflow: hidden;
    border: 1px solid #eef2f6;
    display: flex;
    flex-direction: column;
    height: 500px;
    max-height: 60vh;
}

.scp-header {
    padding: 24px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: hidden;
}

.scp-header::after {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.2), transparent);
}

.scp-header-content {
    display: flex;
    flex-direction: column;
    z-index: 1;
}

.scp-name {
    font-weight: 900;
    font-size: 1.3rem;
    letter-spacing: -0.02em;
}

.scp-time {
    font-size: 0.85rem;
    opacity: 0.9;
    font-weight: 600;
}

.scp-count {
    background: rgba(255,255,255,0.2);
    padding: 6px 14px;
    border-radius: 12px;
    font-weight: 800;
    font-size: 0.85rem;
    backdrop-filter: blur(4px);
    z-index: 1;
}

.scp-staff-list {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.staff-row-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: white;
    border-radius: 20px;
    border: 1px solid #f1f5f9;
}

.staff-row-item.read-only {
    cursor: default;
}

.staff-row-item.is-active {
    background: #f0fdf4;
    border-color: #bcf0da;
}

.sri-avatar {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    background: #2D4F1E;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    overflow: hidden;
    flex-shrink: 0;
}

.sri-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.sri-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.sri-name {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 700;
    font-size: 0.95rem;
    color: #1e293b;
}

.ot-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 6px;
    border-radius: 999px;
    background: #fffbeb;
    color: #92400e;
    border: 1px solid #fbbf24;
    font-size: 0.65rem;
    font-weight: 900;
}

.sri-status {
    font-size: 0.75rem;
    color: #94a3b8;
    font-weight: 600;
}

.is-active .sri-name { color: #166534; }
.is-active .sri-status { color: #22c55e; }

.sri-toggle {
    display: flex;
    align-items: center;
}

.toggle-track {
    width: 32px;
    height: 18px;
    background: #e2e8f0;
    border-radius: 20px;
    position: relative;
    transition: all 0.3s;
}

.is-active .toggle-track {
    background: #dcfce7;
}

.toggle-thumb {
    width: 22px;
    height: 22px;
    background: white;
    border-radius: 50%;
    position: absolute;
    top: -2px;
    left: -2px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    display: flex;
    align-items: center;
    justify-content: center;
}

.is-active .toggle-thumb {
    left: 12px;
}

.btn-done {
    padding: 14px 40px;
    background: #2D4F1E;
    color: white;
    border: none;
    border-radius: 18px;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 12px rgba(45,79,30,0.2);
}

.btn-done:hover {
    background: #1f3815;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(45,79,30,0.3);
}

.empty-state-mini { text-align: center; padding: 20px; color: #94a3b8; font-size: 0.9rem; font-style: italic; }

.spinner { width: 40px; height: 40px; border: 4px solid #f0f0f0; border-top-color: #2D4F1E; border-radius: 50%; animation: spin 1s linear infinite; }
.calendar-loading-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.7); display: flex; align-items: center; justify-content: center; z-index: 10; }

@keyframes spin { to { transform: rotate(360deg); } }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-active .modal-content, .modal-leave-active .modal-content { transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
.modal-enter-from .modal-content { transform: scale(0.9) translateY(20px); }

@media (max-width: 900px) { .calendar-header { flex-direction: column; align-items: flex-start; } .month-nav { width: 100%; justify-content: center; } }
</style>

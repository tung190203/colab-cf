<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import AdminLayout from './AdminLayout.vue';
import { useAdminAuth } from '../../composables/useAdminAuth';
import { toast } from 'vue3-toastify';
import axios from 'axios';
import { ArrowLeft, ChevronLeft, ChevronRight, Check, X } from 'lucide-vue-next';

const { authHeader } = useAdminAuth();

// State
const staff = ref([]);
const schedules = ref([]);
const loading = ref(true);
const saving = ref(false);

const currentMonth = ref(new Date()); 

// Computed for filtering only staff (exclude admins)
const employeesOnly = computed(() => {
    return staff.value.filter(s => s.role === 'staff');
});

const SHIFTS = ref([]);
const showSettingModal = ref(false);
const shiftForms = ref([]);

async function fetchShifts() {
    try {
        const res = await axios.get('/api/shifts', { headers: authHeader() });
        SHIFTS.value = res.data.map(s => ({
            ...s,
            label: s.name,
            time: `${s.start_time} - ${s.end_time}`,
            short: s.name.charAt(3) || s.name.charAt(0).toUpperCase()
        }));
        shiftForms.value = JSON.parse(JSON.stringify(res.data));
    } catch (e) {
        toast.error('Không thể tải cấu hình ca làm');
    }
}

async function saveShiftSettings() {
    saving.value = true;
    try {
        await axios.post('/api/admin/shifts', { shifts: shiftForms.value }, { headers: authHeader() });
        toast.success('Đã cập nhật ca làm');
        await fetchShifts();
        showSettingModal.value = false;
    } catch (e) {
        toast.error('Lỗi khi lưu cấu hình');
    } finally {
        saving.value = false;
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
        const res = await axios.get('/api/admin/staff', { headers: authHeader() });
        staff.value = res.data;
    } catch (e) {
        toast.error('Không thể tải danh sách nhân viên');
    }
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

const showModal = ref(false);
const modalDate = ref(null);

function openDay(day) {
    if (!day.current) return;
    modalDate.value = day.date;
    showModal.value = true;
}

async function toggleStaffShift(staffId, shiftKey) {
    if (saving.value) return;
    const ds = toDateStr(modalDate.value);
    const existing = schedules.value.find(s => 
        toDateStr(s.date) === ds && 
        s.shift === shiftKey && 
        Number(s.staff_id) === Number(staffId)
    );
    
    saving.value = true;
    try {
        if (existing) {
            await axios.delete(`/api/admin/schedule/${existing.id}`, { headers: authHeader() });
            schedules.value = schedules.value.filter(s => s.id !== existing.id);
        } else {
            const res = await axios.post('/api/admin/schedule', {
                schedules: [{ staff_id: staffId, date: ds, shift: shiftKey }]
            }, { headers: authHeader() });
            
            const newSchedules = res.data.schedules.map(ns => {
                const sInfo = staff.value.find(st => Number(st.id) === Number(ns.staff_id));
                return { ...ns, staff: sInfo };
            });
            schedules.value = [...schedules.value, ...newSchedules];
        }
    } catch (e) { 
        const msg = e.response?.data?.message || 'Lỗi khi cập nhật ca làm';
        toast.error(msg); 
    } finally {
        saving.value = false;
    }
}

const hasShift = (staffId, date, key) => {
    const ds = toDateStr(date);
    return schedules.value.some(s => 
        toDateStr(s.date) === ds && 
        s.shift === key && 
        Number(s.staff_id) === Number(staffId)
    );
};

</script>

<template>
    <AdminLayout>
        <template #title>Lịch làm việc tổng quát</template>

        <div class="schedule-container">
            <div class="calendar-view">
                <!-- Calendar Header -->
                <div class="calendar-header">
                    <div class="header-main">
                        <h3 class="section-title">Lịch làm việc tháng</h3>
                        <p class="section-subtitle">Bấm vào từng ngày để phân công nhân viên</p>
                    </div>

                    <div class="month-nav">
                        <button @click="prevMonth" class="nav-btn">
                            <ChevronLeft :size="20" />
                        </button>
                        <span class="month-label">{{ monthLabel }}</span>
                        <button @click="nextMonth" class="nav-btn">
                            <ChevronRight :size="20" />
                        </button>
                    </div>

                    <button class="settings-btn" @click="showSettingModal = true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/>
                        </svg>
                        Cài đặt ca
                    </button>
                </div>

                <!-- Calendar Grid -->
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
                            :class="{ 
                                'not-current': !day.current, 
                                'is-today': isToday(day.date)
                            }"
                            @click="openDay(day)"
                        >
                            <span class="day-num">{{ day.date.getDate() }}</span>
                            <div class="day-shifts-summary">
                                <template v-for="shift in SHIFTS" :key="shift.key">
                                    <div 
                                        v-if="getSchedulesForDay(day.date).filter(s => s.shift === shift.key).length > 0" 
                                        class="shift-names-row"
                                    >
                                        <span class="sn-label" :style="{ color: shift.color }">{{ shift.short }}:</span>
                                        <span class="sn-list">
                                            {{ getSchedulesForDay(day.date).filter(s => s.shift === shift.key).map(s => s.staff?.name).join(', ') }}
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

        <!-- General Day Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showModal" class="modal-overlay" @click="showModal = false">
                    <div class="modal-content modal-content--large" @click.stop>
                        <div class="modal-header">
                            <div>
                                <h3>Quản lý nhân sự ngày {{ modalDate.toLocaleDateString('vi-VN') }}</h3>
                                <p class="modal-subtitle">Chọn nhân viên làm việc cho từng ca</p>
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
                                            {{ getSchedulesForDay(modalDate).filter(s => s.shift === shift.key).length }} nv
                                        </div>
                                    </div>
                                    <div class="scp-staff-list">
                                        <div 
                                            v-for="s in employeesOnly" 
                                            :key="s.id"
                                            class="staff-row-item"
                                            :class="{ 'is-active': hasShift(s.id, modalDate, shift.key) }"
                                            @click="toggleStaffShift(s.id, shift.key)"
                                        >
                                            <div class="sri-avatar">
                                                <img v-if="s.image_url" :src="s.image_url" />
                                                <span v-else>{{ s.name?.charAt(0)?.toUpperCase() }}</span>
                                            </div>
                                            <div class="sri-info">
                                                <span class="sri-name">{{ s.name }}</span>
                                                <span class="sri-status">{{ hasShift(s.id, modalDate, shift.key) ? 'Đang chọn' : 'Chưa chọn' }}</span>
                                            </div>
                                            <div class="sri-toggle">
                                                <div class="toggle-track">
                                                    <div class="toggle-thumb" :style="hasShift(s.id, modalDate, shift.key) ? { backgroundColor: shift.color } : {}">
                                                        <Check v-if="hasShift(s.id, modalDate, shift.key)" :size="12" color="white" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn-done" @click="showModal = false">Hoàn tất</button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Shift Settings Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showSettingModal" class="modal-overlay" @click="showSettingModal = false">
                    <div class="modal-content modal-content--wide" @click.stop>
                        <div class="modal-header">
                            <h3>Cài đặt thời gian các ca làm</h3>
                            <button class="close-btn" @click="showSettingModal = false">
                                <X :size="20" />
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="shift-settings-grid">
                                <div v-for="(form, idx) in shiftForms" :key="form.key" class="shift-setting-card">
                                    <div class="setting-card-header" :style="{ borderLeftColor: form.color }">
                                        <h4>{{ form.key === 'morning' ? 'Ca Sáng' : 'Ca Chiều' }}</h4>
                                    </div>
                                    <div class="setting-fields">
                                        <div class="field">
                                            <label>Tên hiển thị</label>
                                            <input v-model="form.name" type="text" />
                                        </div>
                                        <div class="field-row">
                                            <div class="field">
                                                <label>Bắt đầu</label>
                                                <input v-model="form.start_time" type="time" />
                                            </div>
                                            <div class="field">
                                                <label>Kết thúc</label>
                                                <input v-model="form.end_time" type="time" />
                                            </div>
                                        </div>
                                        <div class="field">
                                            <label>Màu sắc</label>
                                            <input v-model="form.color" type="color" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn-cancel" @click="showSettingModal = false">Hủy</button>
                            <button class="btn-save" @click="saveShiftSettings" :disabled="saving">
                                <span v-if="saving" class="spinner-mini"></span>
                                Lưu cài đặt
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AdminLayout>
</template>

<style scoped>
.schedule-container { max-width: 1200px; margin: 0 auto; }
.section-title { font-size: 1.5rem; font-weight: 700; color: #1a1a2e; }
.section-subtitle { font-size: 0.9rem; color: #64748b; margin-top: 4px; }

.settings-btn {
    display: flex; align-items: center; gap: 8px; padding: 10px 18px;
    background: #f1f5f9; color: #475569; border: none; border-radius: 12px;
    font-weight: 700; font-size: 0.9rem; cursor: pointer; transition: all 0.2s;
}
.settings-btn:hover { background: #e2e8f0; color: #1e293b; }

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

/* Hide scrollbar but allow scrolling */
.day-shifts-summary::-webkit-scrollbar {
    width: 2px;
}
.day-shifts-summary::-webkit-scrollbar-thumb {
    background: #e2e8f0;
}

.shift-names-row {
    display: flex;
    gap: 4px;
    line-height: 1.2;
}

.sn-label {
    font-size: 0.65rem;
    font-weight: 800;
    white-space: nowrap;
}

.sn-list {
    font-size: 0.65rem;
    font-weight: 600;
    color: #475569;
    display: block;
}

.calendar-legend { display: flex; gap: 24px; margin-top: 24px; padding-top: 24px; border-top: 1px solid #f0f0f0; justify-content: center; flex-wrap: wrap; }
.legend-item { display: flex; align-items: center; gap: 8px; font-size: 0.8rem; color: #666; }
.dot { width: 10px; height: 10px; border-radius: 50%; }

/* Modal */
.modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    backdrop-filter: blur(8px);
    padding: 20px;
}

.modal-content {
    background: white;
    width: 95%;
    max-width: 450px;
    border-radius: 28px;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    display: flex;
    flex-direction: column;
    max-height: 95vh;
}
.modal-content--wide { max-width: 800px; }
.modal-content--large { max-width: 1000px; }

.modal-header {
    padding: 24px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 800;
    color: #1e293b;
}

.close-btn {
    background: #f1f5f9;
    border: none;
    cursor: pointer;
    color: #64748b;
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.close-btn:hover {
    background: #e2e8f0;
    color: #1e293b;
}

.modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
}

.modal-footer {
    padding: 20px 24px;
    background: #f8fafc;
    border-top: 1px solid #f1f5f9;
    display: flex;
    justify-content: flex-end;
    flex-shrink: 0;
}

@media (max-width: 1024px) {
    .calendar-grid {
        min-width: 700px;
    }
    .calendar-grid-wrapper {
        overflow-x: auto;
    }
}

@media (max-width: 768px) {
    .calendar-header {
        flex-direction: column;
        align-items: stretch;
        gap: 16px;
    }
    
    .month-nav {
        justify-content: center;
    }
    
    .settings-btn {
        justify-content: center;
    }

    .day-management-layout {
        grid-template-columns: 1fr;
    }
    
    .shift-column-premium {
        height: auto;
        max-height: none;
    }

    .modal-content--large, .modal-content--wide {
        width: 100%;
        height: 100%;
        max-height: 100vh;
        border-radius: 0;
    }
}

@media (max-width: 480px) {
    .calendar-view {
        padding: 12px;
    }
    .month-label {
        font-size: 0.9rem;
        min-width: 110px;
    }
    .grid-day {
        min-height: 80px;
    }
    /* Show shift list on mobile as grid is scrollable */
    .sn-list {
        display: block;
    }
    .sn-label {
        font-size: 0.65rem;
    }
}

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
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid #f1f5f9;
}

.staff-row-item:hover {
    border-color: #e2e8f0;
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
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
}

.sri-name {
    font-weight: 700;
    font-size: 0.95rem;
    color: #1e293b;
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

/* Settings Modal */
.shift-settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
}

.shift-setting-card {
    background: #f8fafc;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid #e2e8f0;
}

.setting-card-header {
    border-left: 4px solid #ccc;
    padding-left: 12px;
    margin-bottom: 16px;
}

.setting-card-header h4 {
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
    color: #1e293b;
}

.setting-fields {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.field-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.field label {
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
}

.field input {
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.9rem;
    outline: none;
}

.field input[type="color"] {
    padding: 2px;
    height: 38px;
}

.btn-cancel {
    padding: 10px 20px;
    border-radius: 12px;
    border: none;
    background: #f1f5f9;
    color: #64748b;
    font-weight: 600;
    cursor: pointer;
}

.btn-save {
    padding: 10px 24px;
    background: #2D4F1E;
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}

.spinner-mini {
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

.spinner {
    width: 48px;
    height: 48px;
    border: 4px solid #f1f5f9;
    border-top-color: #2D4F1E;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

.calendar-loading-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(255,255,255,0.8);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Scrollbar styling */
.scp-staff-list::-webkit-scrollbar {
    width: 5px;
}
.scp-staff-list::-webkit-scrollbar-track {
    background: transparent;
}
.scp-staff-list::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}

@media (max-width: 900px) {
    .day-management-layout {
        grid-template-columns: 1fr;
    }
}

</style>

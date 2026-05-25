<script setup>
import { computed, onMounted, ref } from 'vue';
import AdminLayout from './AdminLayout.vue';
import { useAdminAuth } from '../../composables/useAdminAuth';
import { toast } from 'vue3-toastify';
import axios from 'axios';
import { FileClock, RotateCcw, UserRound } from 'lucide-vue-next';

const { authHeader } = useAdminAuth();
const now = new Date();
const selectedMonth = ref(now.getMonth() + 1);
const selectedYear = ref(now.getFullYear());
const selectedStaffId = ref('');
const staff = ref([]);
const logs = ref([]);
const loading = ref(true);

const years = computed(() => {
    const year = now.getFullYear();
    return [year - 1, year, year + 1];
});

onMounted(async () => {
    await fetchStaff();
    await fetchLogs();
});

async function fetchStaff() {
    try {
        const res = await axios.get('/api/admin/staff', { headers: authHeader() });
        staff.value = res.data.filter(item => item.role === 'staff' || item.role === 'shift_leader');
    } catch (e) {
        toast.error('Không thể tải danh sách nhân viên');
    }
}

async function fetchLogs() {
    loading.value = true;
    try {
        const params = new URLSearchParams({
            month: selectedMonth.value,
            year: selectedYear.value,
        });
        if (selectedStaffId.value) params.append('staff_id', selectedStaffId.value);

        const res = await axios.get(`/api/admin/audit-logs?${params.toString()}`, { headers: authHeader() });
        logs.value = res.data;
    } catch (e) {
        toast.error(e.response?.data?.message || 'Không thể tải audit log');
    } finally {
        loading.value = false;
    }
}

function formatDate(value) {
    if (!value) return '--';
    return new Date(value).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatDateTime(value) {
    if (!value) return '--';
    return new Date(value).toLocaleString('vi-VN', {
        hour: '2-digit',
        minute: '2-digit',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function formatTime(value) {
    if (!value) return '--:--';
    return new Date(value).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <AdminLayout>
        <template #title>Audit log chấm công</template>

        <div class="log-wrap">
            <div class="log-filter">
                <div class="log-filter-group">
                    <label>Nhân viên</label>
                    <select v-model="selectedStaffId" @change="fetchLogs">
                        <option value="">Tất cả nhân viên</option>
                        <option v-for="item in staff" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </select>
                </div>
                <div class="log-filter-group">
                    <label>Tháng</label>
                    <select v-model="selectedMonth" @change="fetchLogs">
                        <option v-for="month in 12" :key="month" :value="month">Tháng {{ String(month).padStart(2, '0') }}</option>
                    </select>
                </div>
                <div class="log-filter-group">
                    <label>Năm</label>
                    <select v-model="selectedYear" @change="fetchLogs">
                        <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                    </select>
                </div>
                <button class="log-refresh" @click="fetchLogs">
                    <RotateCcw :size="16" />
                    Tải lại
                </button>
            </div>

            <div class="log-panel">
                <div class="log-panel-head">
                    <div>
                        <h3>Lịch sử chỉnh sửa công</h3>
                        <p>Chỉ admin được xem danh sách này</p>
                    </div>
                    <span>{{ logs.length }} log</span>
                </div>

                <div v-if="loading" class="log-loading">
                    <div class="log-spinner"></div>
                </div>

                <div v-else-if="logs.length === 0" class="log-empty">
                    <FileClock :size="36" />
                    <strong>Chưa có chỉnh sửa công</strong>
                    <span>Không tìm thấy log trong bộ lọc hiện tại.</span>
                </div>

                <div v-else class="log-list">
                    <div v-for="item in logs" :key="item.id" class="log-item">
                        <div class="log-icon">
                            <FileClock :size="18" />
                        </div>
                        <div class="log-main">
                            <div class="log-title">
                                <strong>{{ item.staff_name || 'Nhân viên' }}</strong>
                                <span>{{ formatDateTime(item.created_at) }}</span>
                            </div>
                            <div class="log-meta">
                                <span>{{ formatDate(item.date) }}</span>
                                <span>{{ item.shift_name || item.shift }}</span>
                                <span>Sửa bởi {{ item.editor_name || 'Admin' }}</span>
                            </div>
                            <div class="log-change-grid">
                                <div>
                                    <label>Giờ vào</label>
                                    <p class="log-diff-line">
                                        <span class="log-diff-old">{{ formatTime(item.old_check_in_at) }}</span>
                                        <span class="log-diff-arrow">→</span>
                                        <span class="log-diff-new">{{ formatTime(item.new_check_in_at) }}</span>
                                    </p>
                                </div>
                                <div>
                                    <label>Giờ ra</label>
                                    <p class="log-diff-line">
                                        <span class="log-diff-old">{{ formatTime(item.old_check_out_at) }}</span>
                                        <span class="log-diff-arrow">→</span>
                                        <span class="log-diff-new">{{ formatTime(item.new_check_out_at) }}</span>
                                    </p>
                                </div>
                            </div>
                            <div class="log-note" v-if="item.new_note || item.old_note">
                                <UserRound :size="14" />
                                <span>{{ item.new_note || item.old_note }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.log-wrap {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.log-filter {
    display: flex;
    align-items: end;
    gap: 12px;
    padding: 18px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    flex-wrap: wrap;
}

.log-filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 160px;
}

.log-filter-group:first-child {
    min-width: 240px;
}

.log-filter-group label {
    color: #64748b;
    font-size: 0.78rem;
    font-weight: 800;
    text-transform: uppercase;
}

.log-filter select {
    height: 42px;
    border: 1px solid #dbe3ea;
    border-radius: 10px;
    background: #f8fafc;
    padding: 0 12px;
    color: #1e293b;
    font-weight: 700;
    outline: none;
}

.log-refresh {
    height: 42px;
    border: 0;
    border-radius: 10px;
    background: #1f2937;
    color: #fff;
    padding: 0 16px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 800;
    cursor: pointer;
}

.log-panel {
    background: #fff;
    border: 1px solid #edf2f7;
    border-radius: 18px;
    padding: 22px;
}

.log-panel-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 18px;
}

.log-panel-head h3 {
    margin: 0;
    color: #111827;
    font-size: 1.08rem;
}

.log-panel-head p {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 0.86rem;
}

.log-panel-head > span {
    color: #64748b;
    font-size: 0.9rem;
    font-weight: 800;
}

.log-loading {
    display: flex;
    justify-content: center;
    padding: 48px;
}

.log-spinner {
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

.log-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 56px 16px;
    color: #94a3b8;
}

.log-empty strong {
    color: #475569;
}

.log-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.log-item {
    display: flex;
    gap: 14px;
    padding: 16px;
    border: 1px solid #edf2f7;
    border-radius: 14px;
    background: #ffffff;
}

.log-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: #f1f5f9;
    color: #2D4F1E;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.log-main {
    flex: 1;
    min-width: 0;
}

.log-title {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    color: #111827;
}

.log-title strong {
    font-size: 0.98rem;
}

.log-title span {
    color: #64748b;
    font-size: 0.82rem;
    font-weight: 700;
    white-space: nowrap;
}

.log-meta {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 6px;
}

.log-meta span {
    background: #f8fafc;
    color: #64748b;
    border: 1px solid #edf2f7;
    border-radius: 999px;
    padding: 4px 9px;
    font-size: 0.76rem;
    font-weight: 800;
}

.log-change-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-top: 12px;
}

.log-change-grid > div {
    padding: 10px 12px;
    border-radius: 11px;
    background: #f8fafc;
}

.log-change-grid label {
    display: block;
    color: #94a3b8;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    margin-bottom: 4px;
}

.log-change-grid p {
    margin: 0;
    color: #334155;
    font-weight: 800;
}

.log-diff-line {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.log-diff-old,
.log-diff-new {
    display: inline-flex;
    align-items: center;
    min-height: 28px;
    padding: 4px 9px;
    border-radius: 8px;
    font-weight: 900;
    line-height: 1;
}

.log-diff-old {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

.log-diff-new {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
}

.log-diff-arrow {
    color: #94a3b8;
    font-weight: 900;
}

.log-note {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    color: #475569;
    margin-top: 12px;
    font-size: 0.86rem;
    font-weight: 700;
}

@media (max-width: 768px) {
    .log-filter {
        align-items: stretch;
    }

    .log-filter-group,
    .log-filter-group:first-child,
    .log-refresh {
        width: 100%;
        min-width: 0;
    }

    .log-panel {
        padding: 16px;
    }

    .log-title,
    .log-item {
        flex-direction: column;
    }

    .log-change-grid {
        grid-template-columns: 1fr;
    }
}
</style>

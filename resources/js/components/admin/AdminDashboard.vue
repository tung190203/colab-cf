<script setup>
import { ref, onMounted, computed } from 'vue';
import AdminLayout from './AdminLayout.vue';
import { useAdminAuth } from '../../composables/useAdminAuth';
import axios from 'axios';
import { 
    Users, 
    ClipboardList, 
    TrendingUp, 
    Clock, 
    ArrowRight,
    Coffee,
    CheckCircle2,
    FileClock,
    AlertTriangle,
    PackageOpen,
    LogIn,
    LogOut,
    ClipboardPenLine
} from 'lucide-vue-next';

const { adminUser, authHeader, isAdmin, isShiftLeader } = useAdminAuth();
const stats = ref({
    total_staff: 0,
    active_schedules: 0,
    total_bookings: 0,
    today_revenue: 0,
    worked_hours: 0
});
const stockAlerts = ref([]);
const todaySchedules = ref([]);
const todayAttendances = ref([]);
const handovers = ref([]);

onMounted(async () => {
    try {
        const requests = [
            axios.get('/api/admin/stats', { headers: authHeader() }),
            axios.get('/api/stock/alerts', { headers: authHeader() }),
        ];

        if (showShiftFlow.value) {
            const todayStr = toDateStr(new Date());
            requests.push(
                axios.get(`/api/staff/schedule?from=${todayStr}&to=${todayStr}`, { headers: authHeader() }),
                axios.get(`/api/staff/attendance?month=${new Date().getMonth() + 1}&year=${new Date().getFullYear()}`, { headers: authHeader() }),
                axios.get('/api/shift-handover', { headers: authHeader(), params: { per_page: 20, status: 'pending' } })
            );
        }

        const [statsRes, alertsRes, scheduleRes, attendanceRes, handoverRes] = await Promise.all(requests);
        stats.value = statsRes.data;
        stockAlerts.value = alertsRes.data.alerts || [];
        todaySchedules.value = scheduleRes?.data || [];
        todayAttendances.value = (attendanceRes?.data || []).filter((item) => item.date === toDateStr(new Date()));
        handovers.value = handoverRes?.data?.handovers?.data || [];
    } catch (e) {
        console.error('Failed to fetch dashboard data:', e);
    }
});

const formatPrice = (val) => {
    return new Intl.NumberFormat('vi-VN').format(val) + 'đ';
};

const formatCompact = (val) => {
    if (val >= 1000000) return (val / 1000000).toFixed(1).replace('.0', '') + 'M';
    if (val >= 1000) return (val / 1000).toFixed(0) + 'K';
    return val;
};

const formatTime = (dt) => {
    if (!dt) return '--:--';
    return new Date(dt).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
};

const formatStock = (material) => {
    return `${new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 3 }).format(material.current_stock)} ${material.unit}`;
};

const getBarHeight = (val) => {
    if (!stats.value.revenue_chart?.length) return 0;
    const max = Math.max(...stats.value.revenue_chart.map(i => i.total), 1);
    return (val / max) * 100;
};

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return 'Chào buổi sáng';
    if (h < 18) return 'Chào buổi chiều';
    return 'Chào buổi tối';
});

const showShiftFlow = computed(() => ['staff', 'shift_leader'].includes(adminUser.value?.role));

function toDateStr(d) {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

const hasTodaySchedule = computed(() => todaySchedules.value.length > 0);
const hasCheckedIn = computed(() => todayAttendances.value.some((item) => item.check_in_at));
const hasCheckedOut = computed(() => (
    hasTodaySchedule.value
        ? todaySchedules.value.every((schedule) => todayAttendances.value.some((item) => item.shift === schedule.shift && item.check_out_at))
        : false
));
const pendingHandovers = computed(() => handovers.value.filter((item) => item.status === 'pending'));
const shouldShowShiftFlow = computed(() => showShiftFlow.value && (hasTodaySchedule.value || pendingHandovers.value.length > 0));
const ordersPath = computed(() => isAdmin() ? '/admin/orders' : '/staff/orders');

function handleFlowStepClick(event, step) {
    if (step.disabled) {
        event.preventDefault();
    }
}

const shiftFlowSteps = computed(() => {
    const handoverPath = isAdmin() || isShiftLeader() ? '/admin/shift-handovers' : '/staff/shift-handover';
    return [
        {
            title: 'Chấm công vào ca',
            desc: hasCheckedIn.value ? 'Đã ghi nhận giờ vào ca.' : 'Check-in GPS khi bắt đầu ca.',
            to: '/staff/attendance',
            icon: LogIn,
            done: hasCheckedIn.value,
            disabled: !hasTodaySchedule.value,
        },
        {
            title: pendingHandovers.value.length ? 'Nhận giao ca' : 'Tạo giao ca cuối ca',
            desc: pendingHandovers.value.length
                ? `${pendingHandovers.value.length} biên bản đang chờ xác nhận.`
                : 'Đối soát tiền mặt, NVL và thiết bị trước khi kết ca.',
            to: handoverPath,
            icon: ClipboardPenLine,
            done: false,
            badge: pendingHandovers.value.length || '',
        },
        {
            title: 'Chấm công cuối ca',
            desc: hasCheckedIn.value ? 'Check-out khi kết thúc ca.' : 'Check-in trước khi chấm công cuối ca.',
            to: '/staff/attendance',
            icon: LogOut,
            done: hasCheckedOut.value,
            disabled: !hasCheckedIn.value,
        },
    ];
});
</script>

<template>
    <AdminLayout>
        <template #title>Tổng quan</template>

        <div class="db-wrap">
            <!-- Greeting -->
            <div class="db-greeting">
                <div>
                    <p class="db-greeting-sub">{{ greeting }},</p>
                    <h2 class="db-greeting-name">{{ adminUser?.name }}</h2>
                </div>
                <div class="db-date">
                    {{ new Date().toLocaleDateString('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' }) }}
                </div>
            </div>

            <div v-if="stockAlerts.length" class="db-stock-alert">
                <div class="db-stock-alert-main">
                    <div class="db-stock-alert-icon">
                        <AlertTriangle :size="22" />
                    </div>
                    <div>
                        <h3>{{ stockAlerts.length }} NVL dưới ngưỡng cảnh báo</h3>
                        <p>
                            <span v-for="item in stockAlerts.slice(0, 3)" :key="item.id">
                                {{ item.name }} còn {{ formatStock(item) }}
                            </span>
                        </p>
                    </div>
                </div>
                <router-link v-if="isAdmin() || isShiftLeader()" to="/admin/materials" class="db-stock-alert-link">
                    <PackageOpen :size="18" />
                    Xem NVL
                </router-link>
            </div>

            <section v-if="shouldShowShiftFlow" class="db-shift-flow">
                <div class="db-flow-head">
                    <div>
                        <p class="db-flow-kicker">Việc cần làm</p>
                        <h3>Thao tác trong ca</h3>
                    </div>
                    <router-link to="/staff/attendance" class="db-flow-status" :class="{ done: hasCheckedIn }">
                        <CheckCircle2 :size="18" />
                        {{ hasCheckedIn ? 'Đã vào ca' : 'Chưa vào ca' }}
                    </router-link>
                </div>
                <div class="db-flow-steps">
                    <router-link
                        v-for="step in shiftFlowSteps"
                        :key="step.title"
                        :to="step.to"
                        class="db-flow-step"
                        :class="{ done: step.done, disabled: step.disabled }"
                        @click="handleFlowStepClick($event, step)"
                    >
                        <div class="db-flow-index">
                            <CheckCircle2 v-if="step.done" :size="16" />
                            <span v-else></span>
                        </div>
                        <div class="db-flow-icon">
                            <component :is="step.icon" :size="20" />
                        </div>
                        <div class="db-flow-copy">
                            <div class="db-flow-title">
                                {{ step.title }}
                                <span v-if="step.badge" class="db-flow-badge">{{ step.badge }}</span>
                            </div>
                            <p>{{ step.desc }}</p>
                        </div>
                        <div class="db-flow-action">
                            <ArrowRight :size="17" />
                        </div>
                    </router-link>
                </div>
            </section>

            <!-- Stats cards -->
            <div class="db-cards">
                <template v-if="isAdmin() || isShiftLeader()">
                    <div class="db-card db-card--green">
                        <div class="db-card-icon"><Users :size="24" /></div>
                        <div class="db-card-info">
                            <span class="db-card-label">Nhân viên</span>
                            <h3 class="db-card-value">{{ stats.total_staff }}</h3>
                        </div>
                    </div>
                    <div class="db-card db-card--blue">
                        <div class="db-card-icon"><Clock :size="24" /></div>
                        <div class="db-card-info">
                            <span class="db-card-label">Lịch làm hôm nay</span>
                            <h3 class="db-card-value">{{ stats.active_schedules }}</h3>
                        </div>
                    </div>
                    <div class="db-card db-card--purple">
                        <div class="db-card-icon"><ClipboardList :size="24" /></div>
                        <div class="db-card-info">
                            <span class="db-card-label">Đơn hàng hôm nay</span>
                            <h3 class="db-card-value">{{ stats.total_bookings }}</h3>
                        </div>
                    </div>
                    <div class="db-card db-card--gold">
                        <div class="db-card-icon"><TrendingUp :size="24" /></div>
                        <div class="db-card-info">
                            <span class="db-card-label">Doanh thu hôm nay</span>
                            <h3 class="db-card-value">{{ formatPrice(stats.today_revenue) }}</h3>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <div class="db-card db-card--green">
                        <div class="db-card-icon"><Clock :size="24" /></div>
                        <div class="db-card-info">
                            <span class="db-card-label">Giờ làm tháng này</span>
                            <h3 class="db-card-value">{{ stats.worked_hours }}h</h3>
                        </div>
                    </div>
                    <div class="db-card db-card--blue">
                        <div class="db-card-icon"><ClipboardList :size="24" /></div>
                        <div class="db-card-info">
                            <span class="db-card-label">Đơn hàng mới</span>
                            <h3 class="db-card-value">{{ stats.total_bookings }}</h3>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Content Grid -->
            <div class="db-grid" v-if="isAdmin() || isShiftLeader()">
                <!-- Revenue Chart -->
                <div class="db-section-card">
                    <div class="db-sc-header">
                        <h4 class="db-sc-title">Doanh thu 7 ngày qua</h4>
                        <TrendingUp :size="18" class="text-muted" />
                    </div>
                    <div class="db-chart-wrap">
                        <div v-for="item in stats.revenue_chart" :key="item.day" class="db-chart-bar-item">
                            <div class="db-bar-container">
                                <div class="db-bar" :style="{ height: getBarHeight(item.total) + '%' }" :title="formatPrice(item.total)">
                                    <span class="db-bar-val" v-if="item.total > 0">{{ formatCompact(item.total) }}</span>
                                </div>
                            </div>
                            <span class="db-bar-label">{{ item.day }}</span>
                        </div>
                    </div>
                </div>

                <!-- Active Staff -->
                <div class="db-section-card">
                    <div class="db-sc-header">
                        <h4 class="db-sc-title">Nhân viên đang trực</h4>
                        <div class="db-pulse"></div>
                    </div>
                    <div class="db-staff-list" v-if="stats.active_staff?.length">
                        <div v-for="s in stats.active_staff" :key="s.name" class="db-staff-item">
                            <div class="db-staff-avatar">
                                <img v-if="s.image_url" :src="s.image_url" />
                                <span v-else>{{ s.name.charAt(0) }}</span>
                            </div>
                            <div class="db-staff-info">
                                <strong>{{ s.name }}</strong>
                                <span>Check-in: {{ s.check_in }}</span>
                            </div>
                            <div class="db-status-tag">Đang làm</div>
                        </div>
                    </div>
                    <div v-else class="db-empty">Không có nhân viên nào đang trực</div>
                </div>
            </div>

            <div class="db-grid mt-4">
                 <!-- Recent Bookings -->
                 <div class="db-section-card full-width">
                    <div class="db-sc-header">
                        <h4 class="db-sc-title">Đơn hàng gần đây</h4>
                        <router-link :to="ordersPath" class="db-sc-link">Xem tất cả</router-link>
                    </div>
                    <div class="db-table-wrap">
                        <table class="db-table">
                            <thead>
                                <tr>
                                    <th>Khách hàng</th>
                                    <th>Gói / Món</th>
                                    <th>Thời gian</th>
                                    <th>Tổng tiền</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="b in stats.recent_bookings" :key="b.id">
                                    <td>
                                        <div class="db-td-name">{{ b.full_name }}</div>
                                        <div class="db-td-sub">{{ b.phone }}</div>
                                    </td>
                                    <td>{{ b.package?.name || 'Đơn lẻ' }}</td>
                                    <td>{{ formatTime(b.created_at) }}</td>
                                    <td class="font-bold">{{ formatPrice(b.total_price) }}</td>
                                    <td>
                                        <span class="db-badge" :class="b.status">{{ b.status === 'confirmed' ? 'Hoàn thành' : 'Đang xử lý' }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="db-section mt-5">
                <h4 class="db-section-title">Thao tác nhanh</h4>
                <div class="db-actions">
                    <template v-if="isAdmin() || isShiftLeader()">
                        <router-link to="/admin/staff" class="db-action-btn">
                            <div class="db-ab-icon"><Users :size="20"/></div>
                            <span>Quản lý nhân sự</span>
                            <ArrowRight :size="18" />
                        </router-link>
                        <router-link to="/admin/menu" class="db-action-btn">
                            <div class="db-ab-icon"><Coffee :size="20"/></div>
                            <span>Cập nhật Menu</span>
                            <ArrowRight :size="18" />
                        </router-link>
                        <router-link to="/admin/materials" class="db-action-btn">
                            <div class="db-ab-icon"><PackageOpen :size="20"/></div>
                            <span>Kiểm tra NVL</span>
                            <span v-if="stockAlerts.length" class="db-action-badge">{{ stockAlerts.length }}</span>
                            <ArrowRight :size="18" />
                        </router-link>
                        <router-link to="/admin/schedule" class="db-action-btn">
                            <div class="db-ab-icon"><Clock :size="20"/></div>
                            <span>Phân lịch làm việc</span>
                            <ArrowRight :size="18" />
                        </router-link>
                        <router-link v-if="isAdmin()" to="/admin/audit-logs" class="db-action-btn">
                            <div class="db-ab-icon"><FileClock :size="20"/></div>
                            <span>Xem audit log chấm công</span>
                            <ArrowRight :size="18" />
                        </router-link>
                    </template>
                    <template v-else>
                        <router-link to="/staff/attendance" class="db-action-btn">
                            <div class="db-ab-icon"><CheckCircle2 :size="20"/></div>
                            <span>Chấm công hôm nay</span>
                            <ArrowRight :size="18" />
                        </router-link>
                        <router-link v-if="!isShiftLeader()" to="/staff/schedule" class="db-action-btn">
                            <div class="db-ab-icon"><Clock :size="20"/></div>
                            <span>Lịch làm việc của tôi</span>
                            <ArrowRight :size="18" />
                        </router-link>
                        <router-link to="/staff/orders" class="db-action-btn">
                            <div class="db-ab-icon"><ClipboardList :size="20"/></div>
                            <span>Xử lý đơn hàng</span>
                            <ArrowRight :size="18" />
                        </router-link>
                        <router-link v-if="isShiftLeader()" to="/admin/schedule" class="db-action-btn">
                            <div class="db-ab-icon"><Clock :size="20"/></div>
                            <span>Phân lịch làm việc</span>
                            <ArrowRight :size="18" />
                        </router-link>
                    </template>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.db-wrap { max-width: 1200px; margin: 0 auto; padding-bottom: 40px; }

.db-greeting { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 40px; }
.db-greeting-sub { margin: 0; color: #64748b; font-weight: 600; font-size: 1.1rem; }
.db-greeting-name { margin: 0; font-size: 2.2rem; font-weight: 800; color: #1a1a2e; letter-spacing: -0.5px; }
.db-date { color: #64748b; font-weight: 600; background: white; padding: 10px 24px; border-radius: 100px; border: 1px solid #f1f5f9; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }

.db-stock-alert {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
    padding: 16px;
    border: 1px solid #fed7aa;
    border-radius: 8px;
    background: #fff7ed;
}

.db-stock-alert-main {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.db-stock-alert-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border-radius: 8px;
    background: #ffedd5;
    color: #c2410c;
    flex-shrink: 0;
}

.db-stock-alert h3 {
    margin: 0;
    color: #9a3412;
    font-size: 1rem;
    font-weight: 900;
}

.db-stock-alert p {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 5px 0 0;
    color: #7c2d12;
    font-size: 0.86rem;
    font-weight: 700;
}

.db-stock-alert p span:not(:last-child)::after {
    content: "•";
    margin-left: 8px;
    color: #fb923c;
}

.db-stock-alert-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 40px;
    padding: 0 14px;
    border-radius: 8px;
    background: #20451f;
    color: #fff;
    text-decoration: none;
    font-weight: 800;
    white-space: nowrap;
}

.db-shift-flow {
    margin-bottom: 28px;
    padding: 18px;
    border: 1px solid #dfe7dc;
    border-radius: 8px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fbf6 100%);
    box-shadow: 0 12px 32px rgba(45, 79, 30, 0.06);
}

.db-flow-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
}

.db-flow-kicker {
    margin: 0 0 4px;
    color: #58704f;
    font-size: 0.72rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.6px;
}

.db-flow-head h3 {
    margin: 0;
    color: #1f3518;
    font-size: 1.15rem;
    font-weight: 900;
}

.db-flow-status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 38px;
    padding: 0 14px;
    border-radius: 8px;
    border: 1px solid #fed7aa;
    background: #fff7ed;
    color: #9a3412;
    text-decoration: none;
    font-size: 0.86rem;
    font-weight: 900;
    white-space: nowrap;
}

.db-flow-status.done {
    border-color: #bbf7d0;
    background: #ecfdf5;
    color: #047857;
}

.db-flow-steps {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
}

.db-flow-step {
    position: relative;
    display: grid;
    grid-template-columns: auto auto 1fr auto;
    align-items: center;
    gap: 12px;
    min-height: 76px;
    padding: 12px 14px;
    border: 1px solid transparent;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.72);
    color: #1e293b;
    text-decoration: none;
    transition: 0.2s ease;
}

.db-flow-step::before {
    content: "";
    position: absolute;
    left: 26px;
    top: -8px;
    width: 2px;
    height: 8px;
    background: #d9e4d5;
}

.db-flow-step:first-child::before {
    display: none;
}

.db-flow-step:hover {
    border-color: #c8d8c0;
    background: #ffffff;
}

.db-flow-step.done {
    background: rgba(240, 253, 244, 0.8);
}

.db-flow-step.disabled {
    cursor: not-allowed;
    opacity: 0.65;
}

.db-flow-index {
    width: 26px;
    height: 26px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #edf3ea;
    color: #506347;
    font-size: 0.78rem;
    font-weight: 900;
}

.db-flow-index span {
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: currentColor;
}

.db-flow-step.done .db-flow-index {
    background: #16a34a;
    color: white;
}

.db-flow-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f7faf5;
    color: #2D4F1E;
    box-shadow: inset 0 0 0 1px #e2e8f0;
}

.db-flow-copy {
    min-width: 0;
}

.db-flow-title {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #0f172a;
    font-size: 0.94rem;
    font-weight: 900;
}

.db-flow-copy p {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 0.79rem;
    font-weight: 600;
    line-height: 1.35;
}

.db-flow-badge {
    min-width: 22px;
    height: 22px;
    padding: 0 7px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #dc2626;
    color: #fff;
    font-size: 0.75rem;
    font-weight: 900;
}

.db-flow-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-width: 34px;
    height: 34px;
    padding: 0 9px;
    border-radius: 8px;
    color: #64748b;
    font-size: 0.78rem;
    font-weight: 900;
}

.db-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 24px; margin-bottom: 32px; }

.db-card {
    background: white; padding: 24px; border-radius: 24px;
    display: flex; align-items: center; gap: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    border: 1px solid #f1f5f9;
    transition: all 0.3s ease;
}
.db-card:hover { transform: translateY(-5px); box-shadow: 0 12px 30px rgba(0,0,0,0.08); }

.db-card-icon { width: 60px; height: 60px; border-radius: 18px; display: flex; align-items: center; justify-content: center; }
.db-card-info { flex: 1; }
.db-card-label { display: block; font-size: 0.85rem; font-weight: 700; color: #94a3b8; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
.db-card-value { margin: 0; font-size: 1.4rem; font-weight: 800; color: #1e293b; }

.db-card--green .db-card-icon { background: #f0fdf4; color: #10b981; }
.db-card--blue .db-card-icon { background: #eff6ff; color: #3b82f6; }
.db-card--purple .db-card-icon { background: #faf5ff; color: #a855f7; }
.db-card--gold .db-card-icon { background: #fffbeb; color: #f59e0b; }

.db-grid { display: grid; grid-template-columns: 1fr 350px; gap: 24px; }
.db-section-card { background: white; border-radius: 24px; padding: 24px; border: 1px solid #f1f5f9; box-shadow: 0 4px 20px rgba(0,0,0,0.02); }
.full-width { grid-column: 1 / -1; }

.db-sc-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.db-sc-title { margin: 0; font-size: 1.1rem; font-weight: 800; color: #1e293b; }
.db-sc-link { font-size: 0.85rem; font-weight: 700; color: #2D4F1E; text-decoration: none; }
.db-sc-link:hover { text-decoration: underline; }

/* Chart */
.db-chart-wrap { height: 200px; display: flex; align-items: flex-end; gap: 12px; padding-top: 20px; }
.db-chart-bar-item { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 12px; }
.db-bar-container { width: 100%; height: 160px; position: relative; display: flex; align-items: flex-end; justify-content: center; }
.db-bar { width: 32px; background: linear-gradient(to top, #2D4F1E, #4a7c2f); border-radius: 8px 8px 4px 4px; transition: height 0.6s cubic-bezier(0.4, 0, 0.2, 1); position: relative; }
.db-bar:hover { background: #1a3a1b; }
.db-bar-val { position: absolute; top: -20px; left: 50%; transform: translateX(-50%); font-size: 0.7rem; font-weight: 800; color: #64748b; }
.db-bar-label { font-size: 0.75rem; font-weight: 700; color: #94a3b8; }

/* Staff List */
.db-pulse { width: 8px; height: 8px; background: #10b981; border-radius: 50%; box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); animation: pulse 2s infinite; }
@keyframes pulse { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); } 70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); } 100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }

.db-staff-list { display: flex; flex-direction: column; gap: 16px; }
.db-staff-item { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 16px; background: #f8fafc; }
.db-staff-avatar { width: 40px; height: 40px; border-radius: 10px; background: #2D4F1E; color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem; overflow: hidden; }
.db-staff-avatar img { width: 100%; height: 100%; object-fit: cover; }
.db-staff-info { flex: 1; display: flex; flex-direction: column; }
.db-staff-info strong { font-size: 0.9rem; color: #1e293b; }
.db-staff-info span { font-size: 0.75rem; color: #94a3b8; }
.db-status-tag { font-size: 0.65rem; font-weight: 800; color: #059669; background: #ecfdf5; padding: 4px 8px; border-radius: 6px; }

/* Table */
.db-table-wrap { overflow-x: auto; margin: 0 -24px; padding: 0 24px; }
.db-table { width: 100%; border-collapse: collapse; min-width: 600px; }
.db-table th { text-align: left; padding: 12px 16px; font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; border-bottom: 1px solid #f1f5f9; }
.db-table td { padding: 16px; border-bottom: 1px solid #f8fafc; vertical-align: middle; }
.db-td-name { font-size: 0.9rem; font-weight: 700; color: #1e293b; }
.db-td-sub { font-size: 0.75rem; color: #94a3b8; }
.db-badge { padding: 4px 10px; border-radius: 100px; font-size: 0.75rem; font-weight: 700; }
.db-badge.confirmed { background: #ecfdf5; color: #059669; }
.db-badge.pending { background: #fffbeb; color: #d97706; }

/* Quick Actions */
.db-section-title { font-size: 1.25rem; font-weight: 800; color: #1e293b; margin-bottom: 24px; }
.db-actions { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
.db-action-btn {
    background: white; padding: 20px 24px; border-radius: 20px;
    text-decoration: none; color: #1e293b; font-weight: 700;
    display: flex; gap: 16px; align-items: center;
    border: 1px solid #f1f5f9; transition: all 0.3s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.01);
}
.db-ab-icon { width: 44px; height: 44px; border-radius: 12px; background: #f8fafc; color: #2D4F1E; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: 0.2s; }
.db-action-btn span { flex: 1; font-size: 0.95rem; }
.db-action-btn:hover { background: #2D4F1E; color: white; border-color: #2D4F1E; transform: translateY(-3px); box-shadow: 0 8px 20px rgba(45, 79, 30, 0.15); }
.db-action-btn:hover .db-ab-icon { background: rgba(255,255,255,0.1); color: white; }
.db-action-badge {
    flex: 0 0 auto !important;
    min-width: 26px;
    height: 26px;
    padding: 0 8px;
    border-radius: 999px;
    background: #dc2626;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.78rem !important;
    font-weight: 900;
}

.db-empty { padding: 40px; text-align: center; color: #94a3b8; font-style: italic; font-size: 0.9rem; }

@media (max-width: 1000px) {
    .db-grid { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    .db-greeting { margin-bottom: 24px; }
    .db-greeting-name { font-size: 1.5rem; }
    .db-greeting-sub { font-size: 0.9rem; }
    .db-date { display: none; }
    .db-stock-alert {
        align-items: stretch;
        flex-direction: column;
    }

    .db-stock-alert-link {
        width: 100%;
    }

    .db-shift-flow {
        padding: 16px;
        border-radius: 8px;
    }

    .db-flow-head {
        align-items: stretch;
        flex-direction: column;
        gap: 12px;
    }

    .db-flow-status {
        width: 100%;
    }

    .db-flow-steps {
        grid-template-columns: 1fr;
    }

    .db-flow-step {
        min-height: 82px;
        grid-template-columns: auto auto 1fr auto;
        padding: 12px;
    }

    .db-flow-action span {
        display: none;
    }
    
    .db-cards { 
        grid-template-columns: repeat(2, 1fr); 
        gap: 12px;
        margin-bottom: 24px;
    }
    
    .db-card {
        padding: 16px;
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
        border-radius: 20px;
    }
    
    .db-card-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
    }
    
    .db-card-icon :deep(svg) {
        width: 20px;
        height: 20px;
    }
    
    .db-card-value {
        font-size: 1.1rem;
    }
    
    .db-card-label {
        font-size: 0.7rem;
    }

    .db-chart-wrap {
        height: 180px;
        gap: 4px;
        padding-top: 30px;
    }
    
    .db-bar {
        width: 16px;
        border-radius: 4px 4px 2px 2px;
    }
    
    .db-bar-label {
        font-size: 0.65rem;
        transform: scale(0.9);
    }
    
    .db-bar-val {
        display: none;
    }

    .db-section-card {
        padding: 16px;
        border-radius: 20px;
        overflow: hidden;
    }

    .db-table-wrap {
        margin: 0 -16px;
        padding: 0 16px;
        width: calc(100% + 32px);
        display: block;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .db-chart-bar-item {
        gap: 8px;
    }

    .db-actions {
        grid-template-columns: 1fr;
    }
    
    .db-action-btn {
        padding: 16px;
    }
}

@media (max-width: 480px) {
    .db-cards {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

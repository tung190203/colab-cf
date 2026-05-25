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
    FileClock
} from 'lucide-vue-next';

const { adminUser, authHeader, isAdmin, isShiftLeader } = useAdminAuth();
const stats = ref({
    total_staff: 0,
    active_schedules: 0,
    total_bookings: 0,
    today_revenue: 0,
    worked_hours: 0
});

onMounted(async () => {
    try {
        const res = await axios.get('/api/admin/stats', { headers: authHeader() });
        console.log('Dashboard Stats:', res.data);
        stats.value = res.data;
    } catch (e) {
        console.error('Failed to fetch stats:', e);
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
                        <router-link to="/admin/orders" class="db-sc-link">Xem tất cả</router-link>
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
                        <router-link to="/admin/orders" class="db-action-btn">
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

.db-empty { padding: 40px; text-align: center; color: #94a3b8; font-style: italic; font-size: 0.9rem; }

@media (max-width: 1000px) {
    .db-grid { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    .db-greeting { margin-bottom: 24px; }
    .db-greeting-name { font-size: 1.5rem; }
    .db-greeting-sub { font-size: 0.9rem; }
    .db-date { display: none; }
    
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

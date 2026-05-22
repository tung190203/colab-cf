<script setup>
import { computed, onMounted, ref } from 'vue';
import AdminLayout from './AdminLayout.vue';
import { useAdminAuth } from '../../composables/useAdminAuth';
import { toast } from 'vue3-toastify';
import axios from 'axios';
import { Crown, ReceiptText, RefreshCw, TrendingUp, UserCheck, UserPlus, Users } from 'lucide-vue-next';

const { authHeader } = useAdminAuth();
const now = new Date();
const selectedMonth = ref(now.getMonth() + 1);
const selectedYear = ref(now.getFullYear());
const loading = ref(true);
const stats = ref({
    summary: {},
    member_breakdown: { member: 0, vip: 0 },
    top_customers: [],
    top_packages: [],
    monthly_trend: [],
});

const years = computed(() => {
    const year = now.getFullYear();
    return [year - 1, year, year + 1];
});

const maxTrendRevenue = computed(() => {
    const values = stats.value.monthly_trend.map(item => Number(item.revenue || 0));
    return Math.max(...values, 1);
});

onMounted(fetchStats);

async function fetchStats() {
    loading.value = true;
    try {
        const res = await axios.get(`/api/admin/customer-stats?month=${selectedMonth.value}&year=${selectedYear.value}`, {
            headers: authHeader(),
        });
        stats.value = res.data;
    } catch (e) {
        toast.error('Không thể tải thống kê khách hàng');
    } finally {
        loading.value = false;
    }
}

function money(value) {
    return new Intl.NumberFormat('vi-VN').format(Number(value || 0)) + ' ₫';
}

function number(value) {
    return new Intl.NumberFormat('vi-VN').format(Number(value || 0));
}

function shortMoney(value) {
    const amount = Number(value || 0);
    if (amount >= 1000000000) return (amount / 1000000000).toFixed(1).replace('.0', '') + ' tỷ';
    if (amount >= 1000000) return (amount / 1000000).toFixed(1).replace('.0', '') + ' tr';
    if (amount >= 1000) return (amount / 1000).toFixed(1).replace('.0', '') + 'k';
    return String(amount);
}

function formatDate(value) {
    if (!value) return '--/--/----';
    return new Date(value).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function initials(name) {
    return (name || '?')
        .split(' ')
        .filter(Boolean)
        .slice(-2)
        .map(part => part.charAt(0).toUpperCase())
        .join('');
}
</script>

<template>
    <AdminLayout>
        <template #title>Thống kê khách hàng</template>

        <div class="cs-wrap">
            <div class="cs-filter">
                <div class="cs-filter-group">
                    <label>Tháng</label>
                    <select v-model="selectedMonth" @change="fetchStats">
                        <option v-for="month in 12" :key="month" :value="month">Tháng {{ String(month).padStart(2, '0') }}</option>
                    </select>
                </div>
                <div class="cs-filter-group">
                    <label>Năm</label>
                    <select v-model="selectedYear" @change="fetchStats">
                        <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                    </select>
                </div>
                <button class="cs-refresh" @click="fetchStats">
                    <RefreshCw :size="16" />
                    Tải lại
                </button>
            </div>

            <div v-if="loading" class="cs-loading">
                <div class="cs-spinner"></div>
            </div>

            <template v-else>
                <div class="cs-summary">
                    <div class="cs-card">
                        <Users :size="22" />
                        <span>Tổng thành viên</span>
                        <strong>{{ number(stats.summary.total_members) }}</strong>
                    </div>
                    <div class="cs-card">
                        <Crown :size="22" />
                        <span>VIP</span>
                        <strong>{{ number(stats.summary.vip_members) }}</strong>
                    </div>
                    <div class="cs-card">
                        <UserPlus :size="22" />
                        <span>Thành viên mới</span>
                        <strong>{{ number(stats.summary.new_members) }}</strong>
                    </div>
                    <div class="cs-card">
                        <UserCheck :size="22" />
                        <span>Khách có đơn</span>
                        <strong>{{ number(stats.summary.active_customers) }}</strong>
                    </div>
                    <div class="cs-card">
                        <ReceiptText :size="22" />
                        <span>Tổng đơn</span>
                        <strong>{{ number(stats.summary.total_bookings) }}</strong>
                    </div>
                    <div class="cs-card">
                        <TrendingUp :size="22" />
                        <span>Doanh thu</span>
                        <strong>{{ shortMoney(stats.summary.revenue) }}</strong>
                    </div>
                </div>

                <div class="cs-grid">
                    <section class="cs-panel cs-panel-wide">
                        <div class="cs-panel-head">
                            <h3>Xu hướng 6 tháng</h3>
                            <span>Doanh thu và khách có đơn</span>
                        </div>
                        <div class="cs-trend">
                            <div v-for="item in stats.monthly_trend" :key="item.label" class="cs-trend-item">
                                <div class="cs-bar-wrap">
                                    <div class="cs-bar" :style="{ height: Math.max(8, (item.revenue / maxTrendRevenue) * 100) + '%' }"></div>
                                </div>
                                <strong>{{ shortMoney(item.revenue) }}</strong>
                                <span>{{ item.label }}</span>
                                <small>{{ number(item.customers) }} khách</small>
                            </div>
                        </div>
                    </section>

                    <section class="cs-panel">
                        <div class="cs-panel-head">
                            <h3>Cơ cấu thành viên</h3>
                            <span>Member / VIP</span>
                        </div>
                        <div class="cs-breakdown">
                            <div>
                                <span>Member</span>
                                <strong>{{ number(stats.member_breakdown.member) }}</strong>
                            </div>
                            <div>
                                <span>VIP</span>
                                <strong>{{ number(stats.member_breakdown.vip) }}</strong>
                            </div>
                        </div>
                        <div class="cs-metric-row">
                            <span>Khách quay lại</span>
                            <strong>{{ number(stats.summary.returning_customers) }}</strong>
                        </div>
                        <div class="cs-metric-row">
                            <span>Giá trị đơn TB</span>
                            <strong>{{ money(stats.summary.avg_order_value) }}</strong>
                        </div>
                    </section>
                </div>

                <div class="cs-grid">
                    <section class="cs-panel cs-panel-wide">
                        <div class="cs-panel-head">
                            <h3>Top khách hàng trong tháng</h3>
                            <span>Theo tổng chi tiêu</span>
                        </div>
                        <div class="cs-table-wrap">
                            <table class="cs-table">
                                <thead>
                                    <tr>
                                        <th>Khách hàng</th>
                                        <th>Số đơn</th>
                                        <th>Chi tiêu</th>
                                        <th>Đơn gần nhất</th>
                                        <th>Loại</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="stats.top_customers.length === 0">
                                        <td colspan="5" class="cs-empty">Chưa có dữ liệu khách hàng.</td>
                                    </tr>
                                    <tr v-for="customer in stats.top_customers" :key="customer.phone">
                                        <td>
                                            <div class="cs-customer">
                                                <span class="cs-avatar">{{ initials(customer.name) }}</span>
                                                <div>
                                                    <strong>{{ customer.name || 'Khách lẻ' }}</strong>
                                                    <small>{{ customer.phone }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ number(customer.orders_count) }}</td>
                                        <td>{{ money(customer.total_spent) }}</td>
                                        <td>{{ formatDate(customer.last_booking_at) }}</td>
                                        <td>
                                            <span class="cs-badge" :class="{ member: customer.is_member }">
                                                {{ customer.is_member ? 'Thành viên' : 'Khách lẻ' }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="cs-panel">
                        <div class="cs-panel-head">
                            <h3>Gói được đặt nhiều</h3>
                            <span>Top 5 trong tháng</span>
                        </div>
                        <div class="cs-package-list">
                            <div v-if="stats.top_packages.length === 0" class="cs-empty">Chưa có dữ liệu.</div>
                            <div v-for="item in stats.top_packages" :key="item.name" class="cs-package">
                                <div>
                                    <strong>{{ item.name }}</strong>
                                    <span>{{ number(item.bookings_count) }} đơn</span>
                                </div>
                                <b>{{ shortMoney(item.revenue) }}</b>
                            </div>
                        </div>
                    </section>
                </div>
            </template>
        </div>
    </AdminLayout>
</template>

<style scoped>
.cs-wrap {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 20px;
    font-family: 'Inter', sans-serif;
}

.cs-filter {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 18px;
    display: flex;
    align-items: end;
    gap: 12px;
    flex-wrap: wrap;
}

.cs-filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 160px;
}

.cs-filter label {
    color: #64748b;
    font-size: 0.78rem;
    font-weight: 800;
    text-transform: uppercase;
}

.cs-filter select {
    height: 42px;
    border: 1px solid #dbe3ea;
    border-radius: 10px;
    background: #f8fafc;
    padding: 0 12px;
    color: #1e293b;
    font-weight: 700;
    outline: none;
}

.cs-refresh {
    height: 42px;
    border: 0;
    border-radius: 10px;
    background: #1f2937;
    color: white;
    padding: 0 16px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 800;
    cursor: pointer;
}

.cs-loading {
    display: flex;
    justify-content: center;
    padding: 70px 0;
}

.cs-spinner {
    width: 34px;
    height: 34px;
    border: 3px solid #e5e7eb;
    border-top-color: #15803d;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.cs-summary {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 14px;
}

.cs-card,
.cs-panel {
    background: #fff;
    border: 1px solid #edf2f7;
    border-radius: 16px;
}

.cs-card {
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 9px;
    color: #15803d;
}

.cs-card span {
    color: #64748b;
    font-size: 0.82rem;
    font-weight: 700;
}

.cs-card strong {
    color: #111827;
    font-size: 1.45rem;
    line-height: 1;
}

.cs-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
}

.cs-panel {
    padding: 22px;
}

.cs-panel-wide {
    min-width: 0;
}

.cs-panel-head {
    display: flex;
    justify-content: space-between;
    align-items: start;
    gap: 16px;
    margin-bottom: 18px;
}

.cs-panel-head h3 {
    margin: 0;
    color: #111827;
    font-size: 1.05rem;
}

.cs-panel-head span {
    color: #94a3b8;
    font-size: 0.85rem;
    font-weight: 700;
}

.cs-trend {
    min-height: 260px;
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    align-items: end;
    gap: 14px;
}

.cs-trend-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 7px;
    min-width: 0;
}

.cs-bar-wrap {
    height: 150px;
    width: 100%;
    max-width: 48px;
    border-radius: 999px;
    background: #f1f5f9;
    display: flex;
    align-items: end;
    overflow: hidden;
}

.cs-bar {
    width: 100%;
    border-radius: 999px;
    background: #15803d;
}

.cs-trend-item strong {
    color: #111827;
    font-size: 0.82rem;
}

.cs-trend-item span,
.cs-trend-item small {
    color: #64748b;
    font-size: 0.78rem;
    font-weight: 700;
}

.cs-breakdown {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 16px;
}

.cs-breakdown div,
.cs-metric-row {
    background: #f8fafc;
    border-radius: 12px;
    padding: 14px;
}

.cs-breakdown span,
.cs-metric-row span {
    display: block;
    color: #64748b;
    font-size: 0.82rem;
    font-weight: 700;
    margin-bottom: 6px;
}

.cs-breakdown strong,
.cs-metric-row strong {
    color: #111827;
    font-size: 1.2rem;
}

.cs-metric-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-top: 10px;
}

.cs-metric-row span {
    margin-bottom: 0;
}

.cs-table-wrap {
    overflow-x: auto;
}

.cs-table {
    width: 100%;
    min-width: 760px;
    border-collapse: collapse;
}

.cs-table th {
    padding: 12px 14px;
    background: #f8fafc;
    color: #64748b;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    font-size: 0.76rem;
    text-transform: uppercase;
}

.cs-table td {
    padding: 14px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
}

.cs-customer {
    display: flex;
    align-items: center;
    gap: 10px;
}

.cs-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 900;
    flex: 0 0 auto;
}

.cs-customer strong,
.cs-package strong {
    display: block;
    color: #111827;
}

.cs-customer small,
.cs-package span {
    display: block;
    color: #64748b;
    margin-top: 3px;
}

.cs-badge {
    display: inline-flex;
    padding: 6px 10px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 0.78rem;
    font-weight: 800;
}

.cs-badge.member {
    background: #dcfce7;
    color: #15803d;
}

.cs-package-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.cs-package {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 14px;
    border-radius: 12px;
    background: #f8fafc;
}

.cs-package b {
    color: #15803d;
    white-space: nowrap;
}

.cs-empty {
    color: #94a3b8;
    text-align: center;
    padding: 30px 16px !important;
}

@media (max-width: 1024px) {
    .cs-summary {
        grid-template-columns: repeat(3, 1fr);
    }

    .cs-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .cs-filter,
    .cs-filter-group,
    .cs-refresh {
        width: 100%;
    }

    .cs-summary {
        grid-template-columns: repeat(2, 1fr);
    }

    .cs-panel-head {
        flex-direction: column;
        gap: 4px;
    }

    .cs-trend {
        overflow-x: auto;
        grid-template-columns: repeat(6, minmax(84px, 1fr));
        padding-bottom: 6px;
    }
}
</style>

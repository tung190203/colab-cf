<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import { useAdminAuth } from '../../composables/useAdminAuth';
import AdminLayout from './AdminLayout.vue';
import { Search, MoreVertical, Download, TrendingUp, CheckCircle2, Clock, CircleDollarSign } from 'lucide-vue-next';
import { toast } from 'vue3-toastify';

const { authHeader } = useAdminAuth();

const selectedMonth = ref(new Date().getMonth() + 1);
const selectedYear = ref(new Date().getFullYear());
const searchQuery = ref('');

const bookings = ref([]);
const loading = ref(false);

const selectedBooking = ref(null);
let modalInstance = null;

// Pagination
const currentPage = ref(1);
const itemsPerPage = 5;

onMounted(() => {
    fetchBookings();
});

async function fetchBookings() {
    loading.value = true;
    try {
        const url = `/api/all-bookings?month=${selectedMonth.value}&year=${selectedYear.value}&search=${encodeURIComponent(searchQuery.value)}`;
        const res = await axios.get(url, { headers: authHeader() });
        bookings.value = res.data.bookings;
        currentPage.value = 1;
    } catch (e) {
        toast.error('Lỗi khi tải dữ liệu đơn hàng');
    } finally {
        loading.value = false;
    }
}

const totalBookings = computed(() => bookings.value.length);
const pendingBookings = computed(() => bookings.value.filter(b => !b.is_served && b.status !== 'cancelled').length);
const completedBookings = computed(() => bookings.value.filter(b => b.is_served).length);
const totalRevenue = computed(() => {
    const sum = bookings.value.filter(b => b.status !== 'cancelled').reduce((acc, curr) => acc + Number(curr.total_price), 0);
    if (sum >= 1000000000) {
        return (sum / 1000000000).toFixed(1).replace('.0', '') + 'B';
    }
    if (sum >= 1000000) {
        return (sum / 1000000).toFixed(1).replace('.0', '') + 'M';
    }
    if (sum >= 1000) {
        return (sum / 1000).toFixed(1).replace('.0', '') + 'K';
    }
    return new Intl.NumberFormat('vi-VN').format(sum);
});

const totalPages = computed(() => Math.ceil(totalBookings.value / itemsPerPage) || 1);
const paginatedBookings = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return bookings.value.slice(start, start + itemsPerPage);
});

function openModal(booking) {
    selectedBooking.value = booking;
    if (!modalInstance) {
        modalInstance = new bootstrap.Modal(document.getElementById('adminBookingModal'));
    }
    modalInstance.show();
}

function formatTime(dt) {
    if (!dt) return '--:--';
    return new Date(dt).toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatDateOnly(dt) {
    if (!dt) return '--/--/----';
    return new Date(dt).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatCurrency(val) {
    return new Intl.NumberFormat('vi-VN').format(val) + 'đ';
}

function getStatusBadgeClass(b) {
    if (b.status === 'cancelled') return 'badge-err';
    if (b.is_served) return 'badge-done';
    return 'badge-warn';
}

function getStatusLabel(b) {
    if (b.status === 'cancelled') return 'Đã huỷ';
    if (b.is_served) return 'Hoàn thành';
    return 'Đang xử lý';
}

function getInitials(name) {
    if (!name) return 'KH';
    const words = name.trim().split(' ');
    if (words.length === 1) return words[0].substring(0, 2).toUpperCase();
    return (words[0][0] + words[words.length - 1][0]).toUpperCase();
}
</script>

<template>
    <AdminLayout>
        <template #title>Tổng hợp Đơn hàng</template>

        <div class="ao-wrap">
            <!-- Filter Row -->
            <div class="ao-toolbar">
                <div class="ao-search-box">
                    <Search class="ao-search-icon" :size="18"/>
                    <input type="text" v-model="searchQuery" placeholder="Tìm kiếm mã đơn, khách hàng..." @keyup.enter="fetchBookings">
                </div>
                
                <div class="ao-filter-group">
                    <select v-model="selectedMonth" @change="fetchBookings">
                        <option v-for="m in 12" :key="m" :value="m">Tháng {{ String(m).padStart(2,'0') }}</option>
                    </select>
                    <select v-model="selectedYear" @change="fetchBookings">
                        <option v-for="y in [2025, 2026, 2027]" :key="y" :value="y">{{ y }}</option>
                    </select>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="ao-stats-grid">
                <div class="ao-stat-card">
                    <div class="ao-sc-label">Tổng đơn hàng</div>
                    <div class="ao-sc-val-row">
                        <div class="ao-sc-val">{{ totalBookings }}</div>
                    </div>
                </div>
                <div class="ao-stat-card">
                    <div class="ao-sc-label">Đang xử lý</div>
                    <div class="ao-sc-val-row">
                        <div class="ao-sc-val">{{ pendingBookings }}</div>
                        <div class="ao-sc-icon ic-gray"><Clock :size="20"/></div>
                    </div>
                </div>
                <div class="ao-stat-card">
                    <div class="ao-sc-label">Hoàn thành</div>
                    <div class="ao-sc-val-row">
                        <div class="ao-sc-val">{{ completedBookings }}</div>
                        <div class="ao-sc-icon ic-green"><CheckCircle2 :size="20"/></div>
                    </div>
                </div>
                <div class="ao-stat-card">
                    <div class="ao-sc-label">Tổng doanh thu</div>
                    <div class="ao-sc-val-row">
                        <div class="ao-sc-val">{{ totalRevenue }} <span class="ao-currency">VND</span></div>
                    </div>
                </div>
            </div>

            <!-- Table Card -->
            <div class="ao-table-card">
                <div v-if="loading" class="ao-loading">
                    <div class="ao-spinner"></div>
                </div>

                <div v-else>
                    <table class="ao-table">
                        <thead>
                            <tr>
                                <th width="15%">MÃ ĐƠN</th>
                                <th width="25%">KHÁCH HÀNG</th>
                                <th width="15%">NGÀY TẠO</th>
                                <th width="20%">TRẠNG THÁI</th>
                                <th width="20%">TỔNG TIỀN</th>
                                <th width="5%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="bookings.length === 0">
                                <td colspan="6" class="ao-empty">Không tìm thấy đơn hàng nào.</td>
                            </tr>
                            <tr v-for="b in paginatedBookings" :key="b.id">
                                <td>
                                    <strong class="c-code">#ORD-{{ b.id }}</strong>
                                </td>
                                <td>
                                    <div class="c-customer">
                                        <div class="c-avatar">{{ getInitials(b.full_name) }}</div>
                                        <div class="c-info">
                                            <div class="c-name text-truncate" style="max-width: 200px;">{{ b.full_name }}</div>
                                            <div class="c-phone">{{ b.phone }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="c-date">{{ formatDateOnly(b.start_time) }}</div>
                                </td>
                                <td>
                                    <span class="badge" :class="getStatusBadgeClass(b)">
                                        {{ getStatusLabel(b) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="c-price">{{ formatCurrency(b.total_price) }}</span>
                                </td>
                                <td class="text-end">
                                    <button class="btn-more" @click="openModal(b)">
                                        <MoreVertical :size="20"/>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div class="ao-pagination" v-if="totalBookings > 0">
                        <div class="ao-pag-info">
                            Hiển thị {{ (currentPage - 1) * itemsPerPage + 1 }} - {{ Math.min(currentPage * itemsPerPage, totalBookings) }} của {{ totalBookings }} đơn hàng
                        </div>
                        <div class="ao-pag-controls">
                            <button :disabled="currentPage === 1" @click="currentPage--">&lt;</button>
                            <button v-for="p in totalPages" :key="p" :class="{ active: p === currentPage }" @click="currentPage = p">{{ p }}</button>
                            <button :disabled="currentPage === totalPages" @click="currentPage++">&gt;</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div class="modal fade" id="adminBookingModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;" v-if="selectedBooking">
                    <div class="modal-header bg-dark text-white border-0 py-3">
                        <h5 class="modal-title fw-bold">Chi tiết Đơn hàng</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4 bg-light">
                        <div class="mb-3 p-3 bg-white rounded-3 shadow-sm">
                            <h6 class="text-secondary fw-bold mb-3">Thông tin Khách hàng</h6>
                            <p class="mb-1"><strong>Tên:</strong> {{ selectedBooking.full_name }}</p>
                            <p class="mb-1"><strong>SĐT:</strong> {{ selectedBooking.phone }}</p>
                            <p class="mb-0 text-muted small" v-if="selectedBooking.note"><strong>Ghi chú:</strong> {{ selectedBooking.note }}</p>
                        </div>
                        
                        <div class="mb-3 p-3 bg-white rounded-3 shadow-sm">
                            <h6 class="text-secondary fw-bold mb-3">Chi tiết Dịch vụ</h6>
                            <p class="mb-1"><strong>Gói:</strong> <span class="text-success fw-bold">{{ selectedBooking.package?.name }}</span></p>
                            <p class="mb-1"><strong>Bàn:</strong> {{ selectedBooking.table?.code || 'Không có' }}</p>
                            <p class="mb-1"><strong>Giờ vào:</strong> {{ formatTime(selectedBooking.start_time) }}</p>
                        </div>

                        <div class="p-3 bg-white rounded-3 shadow-sm" v-if="selectedBooking.extras && selectedBooking.extras.length > 0">
                            <h6 class="text-secondary fw-bold mb-3">Dịch vụ đi kèm</h6>
                            <ul class="list-group list-group-flush mb-0">
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-light" v-for="extra in selectedBooking.extras" :key="extra.id">
                                    <span>{{ extra.name }} <small class="text-muted">x{{ extra.pivot.quantity }}</small></span>
                                    <span v-if="extra.pivot.free_applied > 0" class="badge bg-success rounded-pill">Miễn phí {{ extra.pivot.free_applied }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted d-block small">Tổng thanh toán</span>
                            <strong class="fs-4 text-success">{{ formatCurrency(selectedBooking.total_price) }}</strong>
                        </div>
                        <button type="button" class="btn btn-secondary px-4 py-2 rounded-pill fw-bold" data-bs-dismiss="modal">Đóng</button>
                    </div>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>

<style scoped>
.ao-wrap {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 24px;
    font-family: 'Inter', sans-serif;
}

/* Toolbar */
.ao-toolbar {
    display: flex;
    gap: 16px;
    align-items: center;
}

.ao-search-box {
    position: relative;
    flex: 1;
    max-width: 400px;
}

.ao-search-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
}

.ao-search-box input {
    width: 100%;
    padding: 12px 16px 12px 44px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    outline: none;
    font-size: 0.95rem;
    transition: all 0.2s;
}

.ao-search-box input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.ao-filter-group {
    display: flex;
    gap: 12px;
}

.ao-filter-group select {
    padding: 12px 20px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    background: white;
    font-size: 0.95rem;
    font-weight: 500;
    color: #475569;
    outline: none;
    cursor: pointer;
}
/* Stats */
.ao-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

.ao-stat-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    border: 1px solid #f1f5f9;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
}

.ao-sc-label {
    font-size: 0.9rem;
    color: #64748b;
    margin-bottom: 12px;
    font-weight: 500;
}

.ao-sc-val-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.ao-sc-val {
    font-size: 2rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1;
}

.ao-currency {
    font-size: 1rem;
    color: #64748b;
    font-weight: 500;
    margin-left: 4px;
}

.ao-sc-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid currentColor;
}
.ic-gray { color: #94a3b8; }
.ic-green { color: #22c55e; }

/* Table */
.ao-table-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
    border: 1px solid #f1f5f9;
    overflow-x: auto;
}

.ao-loading {
    display: flex;
    justify-content: center;
    padding: 60px;
}

.ao-spinner {
    width: 40px; height: 40px;
    border: 3px solid #f0f0f0;
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

.ao-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}

.ao-table th {
    text-align: left;
    padding: 16px 24px;
    font-size: 0.8rem;
    font-weight: 700;
    color: #64748b;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.ao-table td {
    padding: 20px 24px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.c-code {
    color: #16a34a;
    font-weight: 700;
}

.c-customer {
    display: flex;
    align-items: center;
    gap: 12px;
}

.c-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.c-name {
    font-weight: 600;
    color: #1e293b;
    font-size: 0.95rem;
    margin-bottom: 2px;
}

.c-phone {
    font-size: 0.8rem;
    color: #64748b;
}

.c-date {
    font-size: 0.95rem;
    color: #475569;
    font-weight: 500;
}

.c-price {
    color: #475569;
    font-size: 0.95rem;
    font-weight: 500;
}

.badge {
    padding: 6px 16px;
    border-radius: 100px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
}

.badge-done { background: #dcfce7; color: #16a34a; }
.badge-warn { background: #f1f5f9; color: #64748b; }
.badge-err { background: #fee2e2; color: #ef4444; }

.btn-more {
    background: none;
    border: none;
    color: #64748b;
    cursor: pointer;
    padding: 8px;
    border-radius: 50%;
    transition: all 0.2s;
}
.btn-more:hover {
    background: #f1f5f9;
    color: #0f172a;
}

.ao-empty {
    text-align: center;
    padding: 60px !important;
    color: #94a3b8;
    font-weight: 500;
}

/* Pagination */
.ao-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 24px;
    border-top: 1px solid #f1f5f9;
}

.ao-pag-info {
    font-size: 0.85rem;
    color: #64748b;
}

.ao-pag-controls {
    display: flex;
    gap: 8px;
}

.ao-pag-controls button {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: white;
    color: #475569;
    font-weight: 500;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}

.ao-pag-controls button:hover:not(:disabled) {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.ao-pag-controls button.active {
    background: #1e293b;
    color: white;
    border-color: #1e293b;
}

.ao-pag-controls button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
@media (max-width: 768px) {
    .ao-toolbar {
        flex-direction: column;
        align-items: stretch;
    }

    .ao-search-box {
        max-width: none;
    }

    .ao-filter-group {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .ao-stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .ao-stat-card {
        padding: 16px;
    }

    .ao-sc-val {
        font-size: 1.5rem;
    }

    .ao-sc-icon {
        display: none;
    }

    .ao-table-card {
        margin: 0 -16px;
        padding: 0 16px;
        width: calc(100% + 32px);
        border-radius: 0;
        border-left: none;
        border-right: none;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .ao-pagination {
        flex-direction: column;
        gap: 16px;
        align-items: center;
    }
}

@media (max-width: 480px) {
    .ao-stats-grid {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

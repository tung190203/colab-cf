<script setup>
import { computed, onMounted, ref } from 'vue';
import AdminLayout from './admin/AdminLayout.vue';
import { useAdminAuth } from '../composables/useAdminAuth';
import { toast } from 'vue3-toastify';
import axios from 'axios';
import {
    Plus,
    RefreshCw,
    Save,
    ShoppingCart,
    Trash2,
} from 'lucide-vue-next';

const { authHeader } = useAdminAuth();

const products = ref([]);
const orders = ref([]);
const saving = ref(false);
const loading = ref(false);
const form = ref(defaultForm());

const totalAmount = computed(() => {
    return form.value.items.reduce((sum, item) => {
        const product = productById(item.product_id);
        return sum + Number(item.quantity || 0) * Number(product?.price || 0);
    }, 0);
});

function defaultForm() {
    return {
        payment_method: 'cash',
        note: '',
        items: [{ product_id: '', quantity: 1 }],
    };
}

function productById(id) {
    return products.value.find((product) => product.id === Number(id));
}

function formatMoney(value) {
    return `${new Intl.NumberFormat('vi-VN').format(Number(value || 0))}đ`;
}

function paymentLabel(method) {
    return {
        cash: 'Tiền mặt',
        transfer: 'Chuyển khoản',
        card: 'Thẻ',
        momo: 'Momo',
    }[method] || method;
}

function addItem() {
    form.value.items.push({ product_id: '', quantity: 1 });
}

function removeItem(index) {
    form.value.items.splice(index, 1);
    if (!form.value.items.length) addItem();
}

async function fetchOptions() {
    const res = await axios.get('/api/pos-orders/options', { headers: authHeader() });
    products.value = res.data.products;
}

async function fetchOrders() {
    loading.value = true;
    try {
        const res = await axios.get('/api/pos-orders', {
            headers: authHeader(),
            params: { per_page: 20 },
        });
        orders.value = res.data.orders.data;
    } catch (error) {
        toast.error('Lỗi khi tải đơn bán thủ công');
    } finally {
        loading.value = false;
    }
}

function responseMessage(data, fallback) {
    if (data?.errors) return Object.values(data.errors).flat().join('\n');
    return data?.message || fallback;
}

async function saveOrder() {
    const items = form.value.items
        .filter((item) => item.product_id && Number(item.quantity) > 0)
        .map((item) => ({
            product_id: Number(item.product_id),
            quantity: Number(item.quantity),
        }));

    if (!items.length) {
        toast.warning('Vui lòng chọn ít nhất một món');
        return;
    }

    saving.value = true;
    try {
        await axios.post('/api/pos-orders', {
            payment_method: form.value.payment_method,
            items,
            note: form.value.note,
        }, { headers: authHeader() });
        toast.success('Đã ghi nhận đơn và trừ NVL');
        form.value = defaultForm();
        await fetchOrders();
    } catch (error) {
        toast.error(responseMessage(error.response?.data, 'Lỗi khi ghi nhận đơn'));
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    await Promise.all([fetchOptions(), fetchOrders()]);
});
</script>

<template>
    <AdminLayout>
        <template #title>Nhập đơn bán thủ công</template>

        <div class="mp-page">
            <section class="mp-card">
                <div class="mp-header">
                    <div>
                        <h3>Ghi nhận đơn bán</h3>
                        <p>Dùng khi POS chưa có API hoặc cần nhập tay cuối ca</p>
                    </div>
                    <div class="mp-total">
                        <span>Tổng tiền</span>
                        <strong>{{ formatMoney(totalAmount) }}</strong>
                    </div>
                </div>

                <div class="mp-grid">
                    <label class="mp-field">
                        <span>Phương thức thanh toán</span>
                        <select v-model="form.payment_method">
                            <option value="cash">Tiền mặt</option>
                            <option value="transfer">Chuyển khoản</option>
                            <option value="card">Thẻ</option>
                            <option value="momo">Momo</option>
                        </select>
                    </label>
                    <label class="mp-field">
                        <span>Ghi chú</span>
                        <input v-model="form.note" type="text" placeholder="Ca làm, nguồn đơn, ghi chú..." />
                    </label>
                </div>

                <div class="mp-items">
                    <div class="mp-item-row head">
                        <span>Món</span>
                        <span>Số lượng</span>
                        <span>Thành tiền</span>
                        <span></span>
                    </div>
                    <div v-for="(item, index) in form.items" :key="index" class="mp-item-row">
                        <select v-model="item.product_id">
                            <option value="">Chọn món</option>
                            <option v-for="product in products" :key="product.id" :value="product.id">
                                {{ product.name }} - {{ formatMoney(product.price) }}
                            </option>
                        </select>
                        <input v-model.number="item.quantity" type="number" min="1" step="1" />
                        <strong>{{ formatMoney(Number(item.quantity || 0) * Number(productById(item.product_id)?.price || 0)) }}</strong>
                        <button title="Xóa dòng" @click="removeItem(index)">
                            <Trash2 :size="16" />
                        </button>
                    </div>
                </div>

                <div class="mp-actions">
                    <button class="mp-secondary" @click="addItem">
                        <Plus :size="18" /> Thêm món
                    </button>
                    <button class="mp-primary" :disabled="saving" @click="saveOrder">
                        <Save :size="18" /> {{ saving ? 'Đang lưu...' : 'Lưu đơn & trừ NVL' }}
                    </button>
                </div>
            </section>

            <section class="mp-card">
                <div class="mp-header">
                    <div>
                        <h3>Đơn đã nhập gần đây</h3>
                        <p>{{ orders.length }} đơn</p>
                    </div>
                    <button class="mp-secondary" @click="fetchOrders">
                        <RefreshCw :size="18" /> Làm mới
                    </button>
                </div>

                <div v-if="loading" class="mp-empty">Đang tải...</div>
                <div v-else-if="!orders.length" class="mp-empty">Chưa có đơn bán thủ công</div>
                <div v-else class="mp-order-list">
                    <div v-for="order in orders" :key="order.id" class="mp-order">
                        <div>
                            <strong>{{ order.order_code }}</strong>
                            <span>{{ paymentLabel(order.payment_method) }} · {{ order.creator?.name || 'Hệ thống' }}</span>
                            <small>{{ order.items.map(item => `${item.product_name} x${item.quantity}`).join(', ') }}</small>
                        </div>
                        <div>
                            <b>{{ formatMoney(order.total_amount) }}</b>
                            <em>{{ order.total_quantity }} món</em>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>

<style scoped>
.mp-page { max-width: 1100px; margin: 0 auto; display: flex; flex-direction: column; gap: 18px; }
.mp-card { background: #fff; border: 1px solid #e8ece8; border-radius: 8px; padding: 18px; }
.mp-header { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; margin-bottom: 16px; }
.mp-header h3 { margin: 0; color: #101828; }
.mp-header p { margin: 4px 0 0; color: #667085; font-weight: 700; }
.mp-total { text-align: right; padding: 10px 12px; border-radius: 8px; background: #f0fdf4; color: #166534; }
.mp-total span { display: block; font-size: 13px; font-weight: 800; }
.mp-total strong { display: block; margin-top: 3px; font-size: 22px; }
.mp-grid { display: grid; grid-template-columns: 220px minmax(0, 1fr); gap: 12px; margin-bottom: 16px; }
.mp-field { display: flex; flex-direction: column; gap: 7px; }
.mp-field span { color: #344054; font-size: 13px; font-weight: 800; }
input, select { width: 100%; min-height: 40px; padding: 8px 10px; border: 1px solid #d0d5dd; border-radius: 8px; font: inherit; outline: 0; }
.mp-items { border: 1px solid #eaecf0; border-radius: 8px; overflow-x: auto; }
.mp-item-row { display: grid; grid-template-columns: minmax(240px, 1fr) 120px 140px 44px; gap: 10px; align-items: center; min-width: 720px; padding: 10px 12px; border-bottom: 1px solid #eaecf0; }
.mp-item-row:last-child { border-bottom: 0; }
.mp-item-row.head { background: #f9fafb; color: #667085; font-size: 12px; font-weight: 900; text-transform: uppercase; }
.mp-item-row button { display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border: 1px solid #d0d5dd; border-radius: 8px; background: #fff; color: #344054; cursor: pointer; }
.mp-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px; }
.mp-primary, .mp-secondary { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 40px; padding: 0 14px; border-radius: 8px; font-weight: 800; cursor: pointer; }
.mp-primary { border: 1px solid #20451f; background: #20451f; color: #fff; }
.mp-secondary { border: 1px solid #d0d5dd; background: #fff; color: #344054; }
.mp-primary:disabled { opacity: .65; cursor: wait; }
.mp-empty { padding: 36px; text-align: center; color: #667085; }
.mp-order-list { display: flex; flex-direction: column; gap: 10px; }
.mp-order { display: flex; justify-content: space-between; gap: 14px; padding: 14px; border: 1px solid #eaecf0; border-radius: 8px; }
.mp-order strong, .mp-order span, .mp-order small, .mp-order b, .mp-order em { display: block; }
.mp-order span, .mp-order small, .mp-order em { color: #667085; margin-top: 3px; }
.mp-order em { font-style: normal; text-align: right; }
@media (max-width: 768px) {
    .mp-header, .mp-order { flex-direction: column; }
    .mp-grid { grid-template-columns: 1fr; }
    .mp-total { text-align: left; }
    .mp-actions { flex-direction: column; }
    .mp-primary, .mp-secondary { width: 100%; }
}
</style>

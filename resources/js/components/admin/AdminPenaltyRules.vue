<script setup>
import { computed, onMounted, ref } from 'vue';
import AdminLayout from './AdminLayout.vue';
import { useAdminAuth } from '../../composables/useAdminAuth';
import { Award, Ban, Eye, EyeOff, Pencil, Plus, Save, Search, Trash2, X } from 'lucide-vue-next';
import { toast } from 'vue3-toastify';
import axios from 'axios';

const { authHeader } = useAdminAuth();

const rules = ref([]);
const loading = ref(false);
const search = ref('');
const showForm = ref(false);
const editing = ref(null);
const activeType = ref('bonus');
const form = ref({ type: 'bonus', name: '', amount: 0, description: '', is_active: true });

const displayAmount = computed({
    get: () => form.value.amount ? new Intl.NumberFormat('vi-VN').format(form.value.amount).replace(/,/g, '.') : '',
    set: (value) => {
        form.value.amount = parseInt(String(value).replace(/\D/g, ''), 10) || 0;
    },
});

function fmt(value) {
    return new Intl.NumberFormat('vi-VN').format(value || 0) + ' ₫';
}

function resetForm() {
    editing.value = null;
    form.value = { type: activeType.value, name: '', amount: 0, description: '', is_active: true };
}

function openAdd() {
    resetForm();
    showForm.value = true;
}

function openEdit(rule) {
    editing.value = rule;
    form.value = {
        name: rule.name || '',
        type: rule.type || 'penalty',
        amount: Number(rule.amount || 0),
        description: rule.description || '',
        is_active: Boolean(rule.is_active),
    };
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    resetForm();
}

async function fetchRules() {
    loading.value = true;
    try {
        const res = await axios.get(`/api/admin/penalty-rules?type=${activeType.value}&search=${encodeURIComponent(search.value)}`, {
            headers: authHeader(),
        });
        rules.value = res.data.items || [];
    } catch (e) {
        toast.error('Không thể tải danh mục thưởng/phạt');
    } finally {
        loading.value = false;
    }
}

async function saveRule() {
    if (!form.value.name.trim()) {
        toast.warning('Vui lòng nhập tên danh mục');
        return;
    }
    if (form.value.amount <= 0) {
        toast.warning('Số tiền phải lớn hơn 0');
        return;
    }

    loading.value = true;
    try {
        const payload = { ...form.value, is_active: form.value.is_active ? 1 : 0 };
        if (editing.value) {
            await axios.put(`/api/admin/penalty-rules/${editing.value.id}`, payload, { headers: authHeader() });
            toast.success('Đã cập nhật danh mục');
        } else {
            await axios.post('/api/admin/penalty-rules', payload, { headers: authHeader() });
            toast.success('Đã thêm danh mục');
        }
        closeForm();
        await fetchRules();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Không thể lưu danh mục');
    } finally {
        loading.value = false;
    }
}

async function deleteRule(rule) {
    if (!window.confirm(`Xóa danh mục "${rule.name}"?`)) return;

    loading.value = true;
    try {
        await axios.delete(`/api/admin/penalty-rules/${rule.id}`, { headers: authHeader() });
        toast.success('Đã xóa danh mục');
        await fetchRules();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Không thể xóa danh mục');
    } finally {
        loading.value = false;
    }
}

onMounted(fetchRules);
</script>

<template>
    <AdminLayout>
        <template #title>Danh mục thưởng/phạt</template>

        <div class="pn-wrap">
            <div class="pn-toolbar">
                <div class="pn-tabs">
                    <button :class="{ active: activeType === 'bonus' }" @click="activeType = 'bonus'; fetchRules()">Thưởng</button>
                    <button :class="{ active: activeType === 'penalty' }" @click="activeType = 'penalty'; fetchRules()">Phạt</button>
                </div>
                <div class="pn-search">
                    <Search :size="18" />
                    <input v-model="search" type="text" placeholder="Tìm danh mục..." @keyup.enter="fetchRules" />
                </div>
                <button class="pn-btn pn-btn--ghost" @click="fetchRules">Tải lại</button>
                <button class="pn-btn pn-btn--primary" @click="openAdd">
                    <Plus :size="18" />
                    Thêm danh mục
                </button>
            </div>

            <div class="pn-table-wrap">
                <div v-if="loading" class="pn-loading">Đang tải...</div>
                <table v-else class="pn-table">
                    <thead>
                        <tr>
                            <th>Tên danh mục</th>
                            <th>Loại</th>
                            <th>Số tiền/lượt</th>
                            <th>Ghi chú quy định</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="rules.length === 0">
                            <td colspan="6" class="pn-empty">Chưa có danh mục</td>
                        </tr>
                        <tr v-for="rule in rules" :key="rule.id">
                            <td>
                                <div class="pn-name">
                                    <Award v-if="rule.type === 'bonus'" :size="18" />
                                    <Ban v-else :size="18" />
                                    <strong>{{ rule.name }}</strong>
                                </div>
                            </td>
                            <td>{{ rule.type === 'bonus' ? 'Thưởng' : 'Phạt' }}</td>
                            <td class="pn-money">{{ fmt(rule.amount) }}</td>
                            <td class="pn-desc">{{ rule.description || '—' }}</td>
                            <td>
                                <span class="pn-status" :class="rule.is_active ? 'pn-status--on' : 'pn-status--off'">
                                    <Eye v-if="rule.is_active" :size="14" />
                                    <EyeOff v-else :size="14" />
                                    {{ rule.is_active ? 'Đang áp dụng' : 'Tạm ẩn' }}
                                </span>
                            </td>
                            <td>
                                <div class="pn-actions">
                                    <button class="pn-icon-btn" title="Sửa" @click="openEdit(rule)"><Pencil :size="17" /></button>
                                    <button class="pn-icon-btn pn-icon-btn--danger" title="Xóa" @click="deleteRule(rule)"><Trash2 :size="17" /></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showForm" class="pn-overlay" @click.self="closeForm">
                <div class="pn-modal">
                    <div class="pn-modal-head">
                        <h3>{{ editing ? 'Sửa danh mục' : 'Thêm danh mục' }}</h3>
                        <button class="pn-close" @click="closeForm"><X :size="20" /></button>
                    </div>
                    <div class="pn-form">
                        <label>
                            Loại
                            <select v-model="form.type">
                                <option value="bonus">Thưởng</option>
                                <option value="penalty">Phạt</option>
                            </select>
                        </label>
                        <label>
                            Tên danh mục
                            <input v-model="form.name" type="text" placeholder="VD: Sinh nhật, Quốc khánh, Đi trễ..." />
                        </label>
                        <label>
                            Số tiền/lượt
                            <input v-model="displayAmount" type="text" placeholder="0" />
                        </label>
                        <label class="pn-full">
                            Ghi chú quy định
                            <textarea v-model="form.description" rows="3" placeholder="Điều kiện áp dụng danh mục này..."></textarea>
                        </label>
                        <label class="pn-check">
                            <input v-model="form.is_active" type="checkbox" />
                            Đang áp dụng
                        </label>
                    </div>
                    <div class="pn-modal-foot">
                        <button class="pn-btn pn-btn--ghost" @click="closeForm">Đóng</button>
                        <button class="pn-btn pn-btn--primary" @click="saveRule" :disabled="loading">
                            <Save :size="18" />
                            Lưu
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>

<style scoped>
.pn-wrap { max-width: 1180px; margin: 0 auto; }
.pn-toolbar { display: flex; gap: 12px; align-items: center; justify-content: space-between; margin-bottom: 18px; }
.pn-tabs { display: inline-flex; border: 1px solid #d8e0d5; border-radius: 8px; overflow: hidden; background: #fff; }
.pn-tabs button { border: 0; background: #fff; color: #64748b; padding: 10px 16px; font-weight: 800; cursor: pointer; }
.pn-tabs button.active { background: #2d4f1e; color: #fff; }
.pn-search { flex: 1; display: flex; align-items: center; gap: 10px; background: #fff; border: 1px solid #d8e0d5; border-radius: 8px; padding: 0 12px; min-height: 42px; }
.pn-search input { border: 0; outline: 0; width: 100%; font-size: 0.95rem; }
.pn-btn { border: 0; border-radius: 8px; padding: 10px 14px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; }
.pn-btn--primary { background: #2d4f1e; color: #fff; }
.pn-btn--ghost { background: #eef3ec; color: #2d4f1e; }
.pn-table-wrap { background: #fff; border: 1px solid #e2e8df; border-radius: 8px; overflow-x: auto; overflow-y: hidden; -webkit-overflow-scrolling: touch; }
.pn-table { width: 100%; min-width: 980px; border-collapse: collapse; }
.pn-table th { text-align: left; padding: 14px 16px; background: #f6f8f5; color: #475569; font-size: 0.82rem; text-transform: uppercase; white-space: nowrap; }
.pn-table td { padding: 14px 16px; border-top: 1px solid #edf2ea; vertical-align: middle; white-space: nowrap; }
.pn-name { display: flex; align-items: center; gap: 9px; color: #1f2f1a; }
.pn-money { font-weight: 800; color: #dc2626; white-space: nowrap; }
.pn-desc { color: #64748b; max-width: 420px; overflow: hidden; text-overflow: ellipsis; }
.pn-status { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; font-size: 0.82rem; font-weight: 700; }
.pn-status--on { background: #dcfce7; color: #166534; }
.pn-status--off { background: #f1f5f9; color: #64748b; }
.pn-actions { display: flex; gap: 8px; }
.pn-icon-btn { width: 36px; height: 36px; border: 1px solid #d8e0d5; border-radius: 8px; background: #fff; color: #2d4f1e; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
.pn-icon-btn--danger { color: #dc2626; }
.pn-empty, .pn-loading { text-align: center; color: #64748b; padding: 30px; }
.pn-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); display: flex; align-items: center; justify-content: center; z-index: 1050; padding: 18px; }
.pn-modal { width: min(620px, 100%); background: #fff; border-radius: 8px; box-shadow: 0 24px 70px rgba(0,0,0,.24); overflow: hidden; }
.pn-modal-head, .pn-modal-foot { display: flex; align-items: center; justify-content: space-between; padding: 18px 20px; border-bottom: 1px solid #e2e8df; }
.pn-modal-foot { border-top: 1px solid #e2e8df; border-bottom: 0; justify-content: flex-end; gap: 10px; }
.pn-modal-head h3 { margin: 0; font-size: 1.15rem; }
.pn-close { border: 0; background: transparent; display: inline-flex; cursor: pointer; }
.pn-form { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding: 20px; }
.pn-form label { display: flex; flex-direction: column; gap: 7px; font-weight: 700; color: #334155; }
.pn-form input, .pn-form textarea, .pn-form select { border: 1px solid #d8e0d5; border-radius: 8px; padding: 10px 12px; font: inherit; outline: 0; background: #fff; }
.pn-full { grid-column: 1 / -1; }
.pn-check { grid-column: 1 / -1; flex-direction: row !important; align-items: center; }
.pn-check input { width: 18px; height: 18px; }
@media (max-width: 760px) {
    .pn-toolbar { flex-wrap: wrap; }
    .pn-search, .pn-tabs { flex-basis: 100%; }
    .pn-table-wrap { overflow-x: auto; }
    .pn-form { grid-template-columns: 1fr; }
}
</style>

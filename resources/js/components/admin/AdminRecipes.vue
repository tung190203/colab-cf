<script setup>
import { computed, onMounted, ref } from 'vue';
import AdminLayout from './AdminLayout.vue';
import { useAdminAuth } from '../../composables/useAdminAuth';
import {
    Clock3,
    History,
    ListChecks,
    Plus,
    Save,
    Search,
    Trash2,
    X,
} from 'lucide-vue-next';
import { toast } from 'vue3-toastify';
import axios from 'axios';

const { authHeader } = useAdminAuth();

const loading = ref(false);
const saving = ref(false);
const logsLoading = ref(false);
const products = ref([]);
const materials = ref([]);
const recipes = ref([]);
const recipeLogs = ref([]);
const searchQuery = ref('');
const selectedProductId = ref(null);
const selectedRecipe = ref(null);
const showLogs = ref(false);

const form = ref(defaultForm());
const excludedRecipeCategories = new Set([
    'services',
    'office_services',
    'other_services',
    'others_services',
    'office services',
    'other services',
    'others services',
]);

const recipesByProduct = computed(() => {
    return recipes.value.reduce((map, recipe) => {
        map[recipe.product_id] = recipe;
        return map;
    }, {});
});

const recipeProducts = computed(() => {
    return products.value.filter((product) => {
        return !excludedRecipeCategories.has(String(product.category || '').toLowerCase());
    });
});

const filteredProducts = computed(() => {
    const keyword = searchQuery.value.trim().toLowerCase();
    const visibleProducts = recipeProducts.value;

    if (!keyword) return visibleProducts;

    return visibleProducts.filter((product) => {
        return [product.name, product.category, product.sku]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(keyword));
    });
});

const selectedProduct = computed(() => {
    return products.value.find((product) => product.id === selectedProductId.value) || null;
});

const recipeCount = computed(() => recipes.value.length);

function defaultForm() {
    return {
        active: true,
        ingredients: [],
    };
}

function normalizeNumber(value) {
    const number = Number(value);
    return Number.isFinite(number) ? number : 0;
}

function formatNumber(value) {
    return new Intl.NumberFormat('vi-VN', {
        maximumFractionDigits: 3,
    }).format(normalizeNumber(value));
}

function formatDateTime(value) {
    if (!value) return '--';

    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function materialById(id) {
    return materials.value.find((material) => material.id === Number(id)) || null;
}

function ingredientUnit(ingredient) {
    return materialById(ingredient.material_id)?.unit || ingredient.unit || '';
}

function recipeForProduct(productId) {
    return recipesByProduct.value[productId] || null;
}

async function fetchData() {
    loading.value = true;

    try {
        const [optionsRes, recipesRes] = await Promise.all([
            axios.get('/api/recipes/options', { headers: authHeader() }),
            axios.get('/api/recipes', { headers: authHeader() }),
        ]);

        products.value = optionsRes.data.products;
        materials.value = optionsRes.data.materials;
        recipes.value = recipesRes.data.recipes;

        if (!selectedProductId.value && recipeProducts.value.length) {
            selectProduct(recipeProducts.value[0]);
        } else if (selectedProductId.value) {
            const product = recipeProducts.value.find((item) => item.id === selectedProductId.value);
            if (product) selectProduct(product);
        }
    } catch (error) {
        toast.error('Lỗi khi tải dữ liệu công thức');
    } finally {
        loading.value = false;
    }
}

function selectProduct(product) {
    selectedProductId.value = product.id;
    selectedRecipe.value = recipeForProduct(product.id);

    if (selectedRecipe.value) {
        form.value = {
            active: selectedRecipe.value.active,
            ingredients: selectedRecipe.value.ingredients.map((ingredient) => ({
                material_id: ingredient.material_id,
                quantity: ingredient.quantity,
                unit: ingredient.unit,
            })),
        };
    } else {
        form.value = {
            active: true,
            ingredients: [emptyIngredient()],
        };
    }
}

function emptyIngredient() {
    return {
        material_id: materials.value[0]?.id || '',
        quantity: '',
        unit: materials.value[0]?.unit || '',
    };
}

function addIngredient() {
    form.value.ingredients.push(emptyIngredient());
}

function removeIngredient(index) {
    form.value.ingredients.splice(index, 1);
    if (form.value.ingredients.length === 0) {
        addIngredient();
    }
}

function onMaterialChange(ingredient) {
    ingredient.unit = ingredientUnit(ingredient);
}

async function saveRecipe() {
    if (!selectedProduct.value) {
        toast.warning('Vui lòng chọn sản phẩm');
        return;
    }

    const ingredients = form.value.ingredients
        .filter((ingredient) => ingredient.material_id && normalizeNumber(ingredient.quantity) > 0)
        .map((ingredient) => ({
            material_id: Number(ingredient.material_id),
            quantity: normalizeNumber(ingredient.quantity),
        }));

    if (ingredients.length === 0) {
        toast.warning('Vui lòng thêm ít nhất một nguyên vật liệu');
        return;
    }

    const duplicated = ingredients.some((ingredient, index) => {
        return ingredients.findIndex((item) => item.material_id === ingredient.material_id) !== index;
    });

    if (duplicated) {
        toast.warning('Một NVL chỉ nên xuất hiện một lần trong công thức');
        return;
    }

    saving.value = true;

    try {
        await axios.post('/api/recipes', {
            product_id: selectedProduct.value.id,
            ingredients,
            active: form.value.active,
        }, { headers: authHeader() });

        toast.success('Đã lưu công thức');
        await fetchData();
    } catch (error) {
        toast.error(error.response?.data?.message || 'Lỗi khi lưu công thức');
    } finally {
        saving.value = false;
    }
}

async function openLogs() {
    if (!selectedRecipe.value) return;

    showLogs.value = true;
    logsLoading.value = true;
    recipeLogs.value = [];

    try {
        const res = await axios.get(`/api/recipes/${selectedRecipe.value.id}/logs`, { headers: authHeader() });
        recipeLogs.value = res.data.logs;
    } catch (error) {
        toast.error('Lỗi khi tải lịch sử công thức');
    } finally {
        logsLoading.value = false;
    }
}

function closeLogs() {
    showLogs.value = false;
    recipeLogs.value = [];
}

function ingredientSummary(ingredients) {
    if (!Array.isArray(ingredients) || ingredients.length === 0) return 'Chưa có định mức';

    return ingredients
        .map((ingredient) => `${ingredient.material_name || materialById(ingredient.material_id)?.name || 'NVL'} ${formatNumber(ingredient.quantity)} ${ingredient.unit || ingredientUnit(ingredient)}`)
        .join(', ');
}

onMounted(fetchData);
</script>

<template>
    <AdminLayout>
        <template #title>Quản lý công thức món</template>

        <div class="recipes-page">
            <div class="summary-row">
                <div class="summary-tile">
                    <ListChecks :size="22" />
                    <div>
                        <span>Sản phẩm có công thức</span>
                        <strong>{{ recipeCount }}</strong>
                    </div>
                </div>
                <div class="summary-tile">
                    <Clock3 :size="22" />
                    <div>
                        <span>Menu đang bán</span>
                        <strong>{{ recipeProducts.length }}</strong>
                    </div>
                </div>
            </div>

            <div class="recipe-workspace">
                <aside class="product-panel">
                    <div class="search-box">
                        <Search :size="18" />
                        <input v-model="searchQuery" type="text" placeholder="Tìm món..." />
                    </div>

                    <div class="product-list">
                        <button
                            v-for="product in filteredProducts"
                            :key="product.id"
                            class="product-item"
                            :class="{ active: selectedProductId === product.id }"
                            @click="selectProduct(product)"
                        >
                            <span>{{ product.name }}</span>
                            <small>{{ product.category || 'Chưa phân loại' }}</small>
                            <em :class="{ ready: recipeForProduct(product.id) }">
                                {{ recipeForProduct(product.id) ? 'Đã có công thức' : 'Chưa có công thức' }}
                            </em>
                        </button>
                    </div>
                </aside>

                <section class="editor-panel">
                    <div v-if="loading" class="empty-state">Đang tải dữ liệu...</div>
                    <div v-else-if="!selectedProduct" class="empty-state">Chưa có sản phẩm menu để tạo công thức</div>
                    <template v-else>
                        <div class="editor-header">
                            <div>
                                <h3>{{ selectedProduct.name }}</h3>
                                <p>{{ selectedProduct.category || 'Chưa phân loại' }} · {{ selectedProduct.sku || 'Chưa có SKU' }}</p>
                            </div>
                            <div class="editor-actions">
                                <button class="secondary-btn" :disabled="!selectedRecipe" @click="openLogs">
                                    <History :size="18" />
                                    Lịch sử
                                </button>
                                <button class="primary-btn" :disabled="saving" @click="saveRecipe">
                                    <Save :size="18" />
                                    {{ saving ? 'Đang lưu...' : 'Lưu công thức' }}
                                </button>
                            </div>
                        </div>

                        <label class="toggle-field">
                            <input v-model="form.active" type="checkbox" />
                            <span>Công thức đang sử dụng</span>
                        </label>

                        <div class="ingredients-table">
                            <div class="ingredients-head">
                                <span>Nguyên vật liệu</span>
                                <span>Định mức / món</span>
                                <span>Đơn vị</span>
                                <span></span>
                            </div>

                            <div v-for="(ingredient, index) in form.ingredients" :key="index" class="ingredient-row">
                                <select v-model="ingredient.material_id" @change="onMaterialChange(ingredient)">
                                    <option value="">Chọn NVL</option>
                                    <option v-for="material in materials" :key="material.id" :value="material.id">
                                        {{ material.name }}
                                    </option>
                                </select>
                                <input v-model.number="ingredient.quantity" type="number" min="0.001" step="0.001" placeholder="0" />
                                <span class="unit-chip">{{ ingredientUnit(ingredient) || '-' }}</span>
                                <button title="Xóa dòng" @click="removeIngredient(index)">
                                    <Trash2 :size="16" />
                                </button>
                            </div>
                        </div>

                        <button class="add-row-btn" @click="addIngredient">
                            <Plus :size="18" />
                            Thêm nguyên vật liệu
                        </button>
                    </template>
                </section>
            </div>
        </div>

        <div v-if="showLogs" class="modal-backdrop" @click.self="closeLogs">
            <div class="logs-modal">
                <div class="modal-header">
                    <div>
                        <h3>Lịch sử công thức</h3>
                        <p>{{ selectedProduct?.name }}</p>
                    </div>
                    <button @click="closeLogs">
                        <X :size="20" />
                    </button>
                </div>

                <div class="logs-list">
                    <div v-if="logsLoading" class="empty-state">Đang tải lịch sử...</div>
                    <div v-else-if="recipeLogs.length === 0" class="empty-state">Chưa có lịch sử thay đổi</div>
                    <div v-for="log in recipeLogs" v-else :key="log.id" class="log-item">
                        <div>
                            <strong>{{ formatDateTime(log.created_at) }}</strong>
                            <span>{{ log.changer?.name || 'Hệ thống' }}</span>
                        </div>
                        <p>{{ ingredientSummary(log.ingredients_after) }}</p>
                    </div>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>

<style scoped>
.recipes-page {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.summary-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.summary-tile {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 82px;
    padding: 16px;
    background: #fff;
    border: 1px solid #e8ece8;
    border-radius: 8px;
    color: #20451f;
}

.summary-tile span {
    display: block;
    color: #667085;
    font-size: 13px;
}

.summary-tile strong {
    display: block;
    margin-top: 4px;
    color: #101828;
    font-size: 26px;
}

.recipe-workspace {
    display: grid;
    grid-template-columns: 320px minmax(0, 1fr);
    gap: 16px;
    align-items: start;
}

.product-panel,
.editor-panel {
    background: #fff;
    border: 1px solid #e8ece8;
    border-radius: 8px;
}

.product-panel {
    padding: 14px;
}

.search-box {
    display: flex;
    align-items: center;
    gap: 8px;
    height: 42px;
    padding: 0 12px;
    border: 1px solid #d0d5dd;
    border-radius: 8px;
    color: #667085;
}

.search-box input,
.ingredient-row select,
.ingredient-row input {
    width: 100%;
    border: 0;
    outline: 0;
    color: #101828;
    font: inherit;
}

.product-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 620px;
    margin-top: 12px;
    overflow-y: auto;
}

.product-item {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    width: 100%;
    padding: 12px;
    border: 1px solid #eaecf0;
    border-radius: 8px;
    background: #fff;
    text-align: left;
    cursor: pointer;
}

.product-item.active {
    border-color: #20451f;
    background: #f2f8f2;
}

.product-item span {
    color: #101828;
    font-weight: 800;
}

.product-item small {
    color: #667085;
}

.product-item em {
    color: #b42318;
    font-size: 12px;
    font-style: normal;
    font-weight: 800;
}

.product-item em.ready {
    color: #027a48;
}

.editor-panel {
    min-height: 520px;
    padding: 18px;
}

.editor-header,
.modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.editor-header h3,
.modal-header h3 {
    margin: 0;
    color: #101828;
    font-size: 22px;
}

.editor-header p,
.modal-header p {
    margin: 5px 0 0;
    color: #667085;
    font-size: 13px;
}

.editor-actions {
    display: flex;
    gap: 10px;
}

.primary-btn,
.secondary-btn,
.add-row-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 42px;
    padding: 0 16px;
    border-radius: 8px;
    font-weight: 800;
    cursor: pointer;
}

.primary-btn {
    border: 1px solid #20451f;
    background: #20451f;
    color: #fff;
}

.secondary-btn,
.add-row-btn {
    border: 1px solid #d0d5dd;
    background: #fff;
    color: #344054;
}

.primary-btn:disabled,
.secondary-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.toggle-field {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 18px;
    color: #344054;
    font-weight: 800;
}

.toggle-field input {
    width: 18px;
    height: 18px;
}

.ingredients-table {
    margin-top: 18px;
    border: 1px solid #eaecf0;
    border-radius: 8px;
    overflow: hidden;
}

.ingredients-head,
.ingredient-row {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) 160px 100px 48px;
    gap: 10px;
    align-items: center;
    padding: 12px;
}

.ingredients-head {
    background: #f9fafb;
    color: #667085;
    font-size: 12px;
    font-weight: 900;
    text-transform: uppercase;
}

.ingredient-row {
    border-top: 1px solid #eaecf0;
}

.ingredient-row select,
.ingredient-row input {
    min-height: 40px;
    padding: 8px 10px;
    border: 1px solid #d0d5dd;
    border-radius: 8px;
}

.unit-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: 4px 10px;
    border-radius: 999px;
    background: #edf7ed;
    color: #20451f;
    font-weight: 800;
}

.ingredient-row button,
.modal-header button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border: 1px solid #d0d5dd;
    border-radius: 8px;
    background: #fff;
    color: #344054;
    cursor: pointer;
}

.add-row-btn {
    margin-top: 14px;
}

.empty-state {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 180px;
    color: #667085;
    text-align: center;
}

.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1200;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(16, 24, 40, 0.45);
}

.logs-modal {
    width: min(760px, 100%);
    max-height: calc(100vh - 40px);
    overflow: hidden;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 24px 72px rgba(16, 24, 40, 0.18);
}

.modal-header {
    padding: 20px 22px;
    border-bottom: 1px solid #eaecf0;
}

.logs-list {
    display: flex;
    flex-direction: column;
    max-height: 560px;
    overflow-y: auto;
    padding: 10px 22px 22px;
}

.log-item {
    display: grid;
    grid-template-columns: 180px minmax(0, 1fr);
    gap: 18px;
    padding: 14px 0;
    border-bottom: 1px solid #eaecf0;
}

.log-item:last-child {
    border-bottom: 0;
}

.log-item div {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.log-item strong {
    color: #101828;
}

.log-item span,
.log-item p {
    color: #667085;
    font-size: 13px;
}

.log-item p {
    margin: 0;
    line-height: 1.5;
}

@media (max-width: 900px) {
    .summary-row,
    .recipe-workspace,
    .ingredients-head,
    .ingredient-row,
    .log-item {
        grid-template-columns: 1fr;
    }

    .editor-header {
        flex-direction: column;
    }

    .editor-actions,
    .primary-btn,
    .secondary-btn,
    .add-row-btn {
        width: 100%;
    }
}
</style>

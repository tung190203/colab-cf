<script setup>
import { ref, computed, onMounted } from 'vue';
import AdminLayout from './AdminLayout.vue';
import { useAdminAuth } from '../../composables/useAdminAuth';
import { toast } from 'vue3-toastify';
import axios from 'axios';
import {
    ShieldCheck, Play, CheckCircle2, XCircle,
    ChevronRight, ChevronLeft, AlertTriangle,
    TrendingUp, Clock, Send, RotateCcw, SlidersHorizontal,
    Camera, X as XIcon, Award, AlertCircle,
} from 'lucide-vue-next';

const { authHeader, adminUser } = useAdminAuth();

const activeTab = ref('start');
const loading = ref(false);
const submitting = ref(false);

const currentShift = ref(null);
const staffInShift = ref([]);
const shiftError = ref('');
const items = ref([]);
const results = ref({});
const notes = ref({});
const photos = ref({});
const uploadingItem = ref(null);
const activeGroup = ref('Tất cả');
const history = ref([]);
const summary = ref(null);
const historyMonth = ref(new Date().toISOString().slice(0, 7));

const groups = computed(() => {
    const g = [...new Set(items.value.map(i => i.group))];
    return ['Tất cả', ...g];
});
const visibleItems = computed(() =>
    activeGroup.value === 'Tất cả' ? items.value : items.value.filter(i => i.group === activeGroup.value)
);
const checkedCount = computed(() => Object.keys(results.value).length);
const passedCount = computed(() => Object.values(results.value).filter(v => v === 'pass').length);
const failedCount = computed(() => checkedCount.value - passedCount.value);
const score = computed(() => checkedCount.value > 0 ? Math.round((passedCount.value / checkedCount.value) * 100) : 0);
const checkedItems = computed(() => items.value.filter(i => results.value[i.id]));

const scoreColor = (s) => s >= 85 ? '#16a34a' : s >= 70 ? '#d97706' : '#dc2626';
const scoreBg = (s) => s >= 85 ? '#f0fdf4' : s >= 70 ? '#fffbeb' : '#fff5f5';
const scoreLabel = (s) => s >= 85 ? 'Đạt chuẩn' : s >= 70 ? 'Cần cải thiện' : 'Không đạt';

const groupMeta = {
    'Vệ sinh':    { color: '#2563eb', bg: '#eff6ff', emoji: '🧹' },
    'Không gian': { color: '#7c3aed', bg: '#f5f3ff', emoji: '🪑' },
    'Setup':      { color: '#b45309', bg: '#fffbeb', emoji: '☕' },
    'Tác phong':  { color: '#be185d', bg: '#fdf2f8', emoji: '👔' },
    'Phục vụ':    { color: '#166534', bg: '#f0fdf4', emoji: '🤝' },
};

async function loadCurrentShift() {
    loading.value = true;
    shiftError.value = '';
    try {
        const [shiftRes, itemsRes] = await Promise.all([
            axios.get('/api/spotcheck/current-shift', { headers: authHeader() }),
            axios.get('/api/spotcheck/items', { headers: authHeader() }),
        ]);
        currentShift.value = shiftRes.data.shift;
        staffInShift.value = shiftRes.data.staff;
        items.value = itemsRes.data.items;
        if (!staffInShift.value.length) shiftError.value = 'Không có nhân viên nào đang làm ca';
    } catch (e) {
        shiftError.value = e.response?.data?.message || 'Không có ca nào đang diễn ra';
    } finally {
        loading.value = false;
    }
}

async function loadDashboard() {
    loading.value = true;
    try {
        const [historyRes, summaryRes] = await Promise.all([
            axios.get('/api/spotcheck/history', { headers: authHeader(), params: { month: historyMonth.value } }),
            axios.get(`/api/spotcheck/summary/${historyMonth.value}`, { headers: authHeader() }),
        ]);
        history.value = historyRes.data.history?.data || [];
        summary.value = summaryRes.data;
    } catch {
        toast.error('Không thể tải dữ liệu');
    } finally {
        loading.value = false;
    }
}

function toggleResult(itemId, val) {
    const current = results.value[itemId];
    if (current === val) {
        const r = { ...results.value }; delete r[itemId]; results.value = r;
    } else {
        results.value = { ...results.value, [itemId]: val };
    }
}

function startCheck() {
    results.value = {}; notes.value = {}; photos.value = {};
    activeGroup.value = 'Tất cả';
    activeTab.value = 'check';
}

function resetAll() {
    results.value = {}; notes.value = {}; photos.value = {};
    currentShift.value = null; staffInShift.value = []; shiftError.value = '';
    activeTab.value = 'start';
    loadCurrentShift();
}

function triggerPhotoInput(itemId) {
    document.getElementById('photo-input-' + itemId)?.click();
}

async function handlePhotoSelected(itemId, event) {
    const files = Array.from(event.target.files);
    if (!files.length) return;
    const current = photos.value[itemId] || [];
    if (current.length + files.length > 3) { toast.warning('Tối đa 3 ảnh/mục'); return; }
    uploadingItem.value = itemId;
    for (const file of files.slice(0, 3 - current.length)) {
        const preview = URL.createObjectURL(file);
        photos.value = { ...photos.value, [itemId]: [...(photos.value[itemId] || []), { url: preview, uploading: true }] };
        try {
            const fd = new FormData(); fd.append('photo', file);
            const res = await axios.post('/api/spotcheck/upload-photo', fd, {
                headers: { ...authHeader(), 'Content-Type': 'multipart/form-data' },
            });
            const list = [...(photos.value[itemId] || [])];
            const pos = list.findIndex(p => p.url === preview);
            if (pos !== -1) list[pos] = { url: res.data.url, uploading: false };
            photos.value = { ...photos.value, [itemId]: list };
        } catch {
            photos.value = { ...photos.value, [itemId]: (photos.value[itemId] || []).filter(p => p.url !== preview) };
            toast.error('Upload ảnh thất bại');
        }
    }
    uploadingItem.value = null;
    event.target.value = '';
}

function removePhoto(itemId, url) {
    photos.value = { ...photos.value, [itemId]: (photos.value[itemId] || []).filter(p => p.url !== url) };
}

async function submitSpotCheck() {
    if (!checkedCount.value) { toast.warning('Phải chọn ít nhất 1 mục'); return; }
    submitting.value = true;
    try {
        const itemsChecked = checkedItems.value.map(item => ({
            id: item.id, result: results.value[item.id],
            note: notes.value[item.id] || null,
            photos: (photos.value[item.id] || []).filter(p => !p.uploading).map(p => p.url),
        }));
        await axios.post('/api/spotcheck', {
            shift_key: currentShift.value.key,
            staff_ids: staffInShift.value.map(s => s.id),
            items_checked: itemsChecked,
        }, { headers: authHeader() });
        activeTab.value = 'done';
    } catch (e) {
        toast.error(e.response?.data?.message || 'Lỗi khi lưu');
    } finally {
        submitting.value = false;
    }
}

onMounted(() => { loadCurrentShift(); loadDashboard(); });
</script>

<template>
    <AdminLayout>
        <template #title>Spot Check</template>
        <div class="sc-wrap">

            <!-- ─── NAV TABS ──────────────────────────────── -->
            <div class="sc-nav" v-if="activeTab === 'start' || activeTab === 'dashboard'">
                <button :class="['sc-nav-btn', { active: activeTab === 'start' }]" @click="activeTab = 'start'">
                    <ShieldCheck :size="15"/> Kiểm tra
                </button>
                <button :class="['sc-nav-btn', { active: activeTab === 'dashboard' }]" @click="activeTab = 'dashboard'; loadDashboard()">
                    <TrendingUp :size="15"/> Kết quả tháng
                </button>
            </div>

            <!-- ══════════════ SCREEN 1: BẮT ĐẦU ══════════════ -->
            <div v-if="activeTab === 'start'">
                <div v-if="loading" class="sc-loading"><span class="sc-spin"/><span>Đang tải ca làm việc...</span></div>
                <template v-else>
                    <!-- Error -->
                    <div v-if="shiftError" class="sc-empty-state">
                        <div class="sc-empty-icon">😴</div>
                        <div class="sc-empty-title">{{ shiftError }}</div>
                        <div class="sc-empty-sub">Spot check chỉ khả dụng khi có nhân viên đang check-in</div>
                    </div>

                    <!-- Main start card -->
                    <div v-if="currentShift" class="sc-start-card">
                        <!-- Shift banner -->
                        <div class="sc-shift-banner">
                            <div class="sc-shift-dot"/>
                            <div class="sc-shift-meta">
                                <span class="sc-shift-label">CA ĐANG DIỄN RA</span>
                                <span class="sc-shift-name">{{ currentShift.name }}</span>
                            </div>
                            <div class="sc-shift-time"><Clock :size="13"/>{{ currentShift.time_range }}</div>
                        </div>

                        <!-- Staff grid -->
                        <div v-if="staffInShift.length" class="sc-staff-section">
                            <div class="sc-section-label">NHÂN VIÊN ĐANG LÀM ({{ staffInShift.length }} người)</div>
                            <div class="sc-staff-grid">
                                <div v-for="s in staffInShift" :key="s.id" class="sc-staff-card">
                                    <div class="sc-staff-av">{{ s.init }}</div>
                                    <div class="sc-staff-name">{{ s.name }}</div>
                                    <div class="sc-staff-since">{{ s.inTime }}</div>
                                </div>
                            </div>
                            <div class="sc-notice">
                                Kết quả sẽ được ghi nhận cho <b>tất cả {{ staffInShift.length }} nhân viên</b>
                            </div>
                        </div>

                        <!-- Checker -->
                        <div class="sc-checker-row">
                            <div class="sc-checker-av">{{ adminUser?.name?.charAt(0) }}</div>
                            <div>
                                <div class="sc-checker-name">{{ adminUser?.name }}</div>
                                <div class="sc-checker-role">{{ adminUser?.role === 'admin' ? 'Quản lý' : 'Trưởng ca' }}</div>
                            </div>
                            <div class="sc-checker-badge">Người kiểm tra</div>
                        </div>

                        <button class="sc-cta" :disabled="!staffInShift.length" @click="startCheck">
                            <Play :size="17"/> Bắt đầu kiểm tra
                        </button>
                        <p class="sc-cta-hint">Chọn bao nhiêu mục tùy ý · Kết quả khoá sau khi gửi</p>
                    </div>
                </template>
            </div>

            <!-- ══════════════ SCREEN 2: KIỂM TRA ══════════════ -->
            <div v-if="activeTab === 'check'" class="sc-check-wrap">
                <!-- Sticky header -->
                <div class="sc-check-top">
                    <button class="sc-icon-btn" @click="activeTab = 'start'"><ChevronLeft :size="20"/></button>
                    <div class="sc-check-info">
                        <span class="sc-check-ca">{{ currentShift?.name }}</span>
                        <span class="sc-check-staff">{{ staffInShift.map(s => s.name).join(' · ') }}</span>
                    </div>
                    <!-- Live score pill -->
                    <div class="sc-live-score" :class="checkedCount > 0 ? (score >= 85 ? 'green' : score >= 70 ? 'amber' : 'red') : 'gray'">
                        {{ checkedCount > 0 ? score + '%' : '—' }}
                    </div>
                </div>

                <!-- Counters -->
                <div class="sc-counters" v-if="checkedCount > 0">
                    <div class="sc-counter sc-counter--pass"><CheckCircle2 :size="13"/>{{ passedCount }} đạt</div>
                    <div class="sc-counter sc-counter--fail"><XCircle :size="13"/>{{ failedCount }} không đạt</div>
                    <div class="sc-counter sc-counter--total">{{ checkedCount }}/{{ items.length }} mục</div>
                </div>

                <!-- Progress -->
                <div class="sc-pbar">
                    <div class="sc-pbar-fill"
                        :style="{ width: (checkedCount / Math.max(items.length,1)*100)+'%',
                                  background: score >= 85 ? '#16a34a' : score >= 70 ? '#d97706' : checkedCount > 0 ? '#dc2626' : '#2D4F1E' }"/>
                </div>

                <!-- Group chips -->
                <div class="sc-chips">
                    <button v-for="g in groups" :key="g"
                        :class="['sc-chip', { 'sc-chip--active': activeGroup === g }]"
                        :style="activeGroup === g && g !== 'Tất cả' ? { background: groupMeta[g]?.bg, color: groupMeta[g]?.color, borderColor: groupMeta[g]?.color+'55' } : {}"
                        @click="activeGroup = g">{{ g }}</button>
                </div>

                <!-- Item list -->
                <div class="sc-list">
                    <div v-for="item in visibleItems" :key="item.id"
                        class="sc-item"
                        :class="{ 'sc-item--pass': results[item.id]==='pass', 'sc-item--fail': results[item.id]==='fail' }">
                        <div class="sc-item-head">
                            <span class="sc-item-tag"
                                :style="{ background: groupMeta[item.group]?.bg||'#f8fafc', color: groupMeta[item.group]?.color||'#64748b' }">
                                {{ item.group }}
                            </span>
                            <div class="sc-item-title">{{ item.title }}</div>
                        </div>
                        <div class="sc-item-actions">
                            <button class="sc-btn-pass" :class="{ active: results[item.id]==='pass' }" @click="toggleResult(item.id,'pass')">
                                <CheckCircle2 :size="15"/> Đạt
                            </button>
                            <button class="sc-btn-fail" :class="{ active: results[item.id]==='fail' }" @click="toggleResult(item.id,'fail')">
                                <XCircle :size="15"/> Không đạt
                            </button>
                        </div>
                        <!-- Fail detail -->
                        <div v-if="results[item.id]==='fail'" class="sc-fail-box">
                            <input v-model="notes[item.id]" class="sc-fail-note" type="text" placeholder="Ghi chú lý do (tuỳ chọn)..."/>
                            <div class="sc-photos">
                                <div v-for="p in (photos[item.id]||[])" :key="p.url" class="sc-photo">
                                    <img :src="p.url" :class="{ dim: p.uploading }"/>
                                    <div v-if="p.uploading" class="sc-photo-spin"><span class="sc-spin sc-spin-sm"/></div>
                                    <button v-else class="sc-photo-del" @click.stop="removePhoto(item.id,p.url)"><XIcon :size="11"/></button>
                                </div>
                                <button v-if="(photos[item.id]||[]).length<3"
                                    class="sc-photo-add"
                                    :disabled="uploadingItem===item.id"
                                    @click="triggerPhotoInput(item.id)">
                                    <Camera :size="16"/>
                                    <span>{{ !(photos[item.id]||[]).length ? 'Thêm ảnh' : 'Thêm' }}</span>
                                </button>
                                <input :id="'photo-input-'+item.id" type="file" accept="image/*" multiple style="display:none" @change="handlePhotoSelected(item.id,$event)"/>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom CTA -->
                <div class="sc-bottom">
                    <button class="sc-cta" :disabled="!checkedCount" @click="activeTab='confirm'">
                        <ChevronRight :size="17"/>
                        {{ checkedCount ? `Xem lại & Gửi · ${checkedCount} mục` : 'Chọn ít nhất 1 mục' }}
                    </button>
                </div>
            </div>

            <!-- ══════════════ SCREEN 3: XÁC NHẬN ══════════════ -->
            <div v-if="activeTab === 'confirm'" class="sc-confirm-wrap">
                <button class="sc-back-link" @click="activeTab='check'"><ChevronLeft :size="16"/> Quay lại chỉnh sửa</button>

                <!-- Big score -->
                <div class="sc-result-hero" :style="{ borderColor: scoreColor(score)+'33', background: scoreBg(score) }">
                    <div class="sc-result-score" :style="{ color: scoreColor(score) }">{{ score }}<span>%</span></div>
                    <div class="sc-result-tag" :style="{ background: scoreColor(score), color: '#fff' }">
                        <component :is="score>=85 ? CheckCircle2 : AlertCircle" :size="13"/>
                        {{ scoreLabel(score) }}
                    </div>
                    <div class="sc-result-counts">
                        <span class="pass">{{ passedCount }} mục đạt</span>
                        <span class="sep">·</span>
                        <span class="fail">{{ failedCount }} không đạt</span>
                    </div>
                </div>

                <!-- Meta -->
                <div class="sc-meta-list">
                    <div class="sc-meta-row"><span>Ca</span><strong>{{ currentShift?.name }} · {{ currentShift?.time_range }}</strong></div>
                    <div class="sc-meta-row"><span>Người kiểm tra</span><strong>{{ adminUser?.name }}</strong></div>
                    <div class="sc-meta-row"><span>Ghi nhận cho</span><strong>{{ staffInShift.map(s=>s.name).join(', ') }}</strong></div>
                </div>

                <!-- Items breakdown -->
                <div class="sc-breakdown">
                    <div class="sc-breakdown-title">Chi tiết {{ checkedCount }} mục đã kiểm tra</div>
                    <div v-for="item in checkedItems" :key="item.id" class="sc-breakdown-row">
                        <div class="sc-bd-icon" :style="{ color: results[item.id]==='pass' ? '#16a34a':'#dc2626' }">
                            <CheckCircle2 v-if="results[item.id]==='pass'" :size="16"/>
                            <XCircle v-else :size="16"/>
                        </div>
                        <div class="sc-bd-body">
                            <span class="sc-bd-name">{{ item.title }}</span>
                            <span v-if="notes[item.id]" class="sc-bd-note">{{ notes[item.id] }}</span>
                            <div v-if="results[item.id]==='fail' && photos[item.id]?.length" class="sc-bd-photos">
                                <img v-for="p in photos[item.id]" :key="p.url" :src="p.url"/>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sc-lock-warn"><AlertTriangle :size="14"/>Kết quả sẽ được khoá sau khi gửi, không thể chỉnh sửa</div>

                <button class="sc-cta" :disabled="submitting" @click="submitSpotCheck">
                    <Send :size="16"/>{{ submitting ? 'Đang gửi...' : 'Gửi kết quả' }}
                </button>
            </div>

            <!-- ══════════════ SCREEN 4: DONE ══════════════ -->
            <div v-if="activeTab === 'done'" class="sc-done-wrap">
                <div class="sc-done-ring" :style="{ borderColor: scoreColor(score) }">
                    <div class="sc-done-num" :style="{ color: scoreColor(score) }">{{ score }}<small>%</small></div>
                </div>
                <h2 class="sc-done-h">Đã ghi nhận!</h2>
                <p class="sc-done-p">{{ passedCount }}/{{ checkedCount }} mục đạt · Ca {{ currentShift?.name }}</p>
                <p class="sc-done-sub">Điểm này sẽ được cộng vào KPI tháng của nhân viên trong ca</p>
                <div class="sc-done-acts">
                    <button class="sc-cta-outline" @click="resetAll"><RotateCcw :size="15"/> Kiểm tra mới</button>
                    <button class="sc-cta" @click="activeTab='dashboard'; loadDashboard()"><TrendingUp :size="15"/> Xem kết quả</button>
                </div>
            </div>

            <!-- ══════════════ DASHBOARD ══════════════ -->
            <div v-if="activeTab === 'dashboard'" class="sc-dashboard-wrap">
                <!-- Filter -->
                <div class="sc-filter-row">
                    <input type="month" v-model="historyMonth" class="sc-month-pick" @change="loadDashboard"/>
                    <button class="sc-filter-btn" @click="loadDashboard"><SlidersHorizontal :size="15"/> Áp dụng</button>
                </div>

                <div v-if="loading" class="sc-loading"><span class="sc-spin"/><span>Đang tải...</span></div>
                <template v-else>
                    <!-- KPI row -->
                    <div class="sc-kpi-row" v-if="summary">
                        <div class="sc-kpi">
                            <div class="sc-kpi-val">{{ summary.total_checks }}</div>
                            <div class="sc-kpi-label">lần kiểm tra</div>
                        </div>
                        <div class="sc-kpi sc-kpi--accent" :style="{ '--kc': scoreColor(summary.group_avg), '--kb': scoreBg(summary.group_avg) }">
                            <div class="sc-kpi-val" :style="{ color: scoreColor(summary.group_avg) }">{{ summary.group_avg }}%</div>
                            <div class="sc-kpi-label">điểm TB nhóm</div>
                        </div>
                        <div class="sc-kpi">
                            <div class="sc-kpi-val" style="color:#d97706">{{ (summary.staff_summary||[]).filter(s=>s.bonus_eligible).length }}</div>
                            <div class="sc-kpi-label">người đạt thưởng</div>
                        </div>
                    </div>

                    <!-- Staff table -->
                    <div class="sc-panel" v-if="summary?.staff_summary?.length">
                        <div class="sc-panel-title">Bảng điểm nhân viên tháng này</div>
                        <div v-for="(st, idx) in summary.staff_summary" :key="st.employee_id" class="sc-staff-row">
                            <div class="sc-rank" :class="{ gold: idx===0, silver: idx===1, bronze: idx===2 }">{{ idx+1 }}</div>
                            <div class="sc-staff-av2">{{ st.init }}</div>
                            <div class="sc-staff-info">
                                <span class="sc-staff-nm">{{ st.name }}</span>
                                <span class="sc-staff-cnt">{{ st.check_count }} lần <span v-if="st.check_count<4" class="warn">· chưa đủ 4 lần</span></span>
                            </div>
                            <div class="sc-score-bar">
                                <div class="sc-score-fill" :style="{ width: st.avg_score+'%', background: scoreColor(st.avg_score) }"/>
                            </div>
                            <div class="sc-score-pct" :style="{ color: scoreColor(st.avg_score) }">{{ st.avg_score }}%</div>
                            <div>
                                <span v-if="st.bonus_eligible" class="sc-tag sc-tag--bonus"><Award :size="11"/> +200K</span>
                                <span v-else-if="st.avg_score>=85" class="sc-tag sc-tag--ok">Đạt</span>
                                <span v-else class="sc-tag sc-tag--no">Chưa</span>
                            </div>
                        </div>
                        <div class="sc-bonus-note">🎁 Thưởng 200K khi tất cả lần check đạt 100% và có ≥ 4 lần kiểm tra</div>
                    </div>

                    <!-- History -->
                    <div class="sc-panel" v-if="history.length">
                        <div class="sc-panel-title">Lịch sử kiểm tra</div>
                        <div v-for="h in history" :key="h.id" class="sc-hist-row">
                            <div class="sc-hist-left">
                                <span class="sc-hist-date">{{ h.date }}</span>
                                <span class="sc-hist-time"><Clock :size="11"/>{{ h.time }}</span>
                            </div>
                            <div class="sc-hist-mid">
                                <span class="sc-hist-shift">Ca {{ h.shift }}</span>
                                <span class="sc-hist-staff">{{ h.staff.join(', ') }}</span>
                            </div>
                            <div class="sc-hist-score" :style="{ color: scoreColor(h.score), background: scoreBg(h.score) }">
                                {{ h.score }}%
                            </div>
                        </div>
                    </div>

                    <!-- Empty -->
                    <div v-if="!summary?.total_checks" class="sc-empty-state">
                        <div class="sc-empty-icon">📋</div>
                        <div class="sc-empty-title">Chưa có dữ liệu tháng này</div>
                        <div class="sc-empty-sub">Thực hiện spot check đầu tiên để thấy kết quả tại đây</div>
                    </div>
                </template>
            </div>

        </div>
    </AdminLayout>
</template>

<style scoped>
/* ── Base ─────────────────────────────────────────── */
.sc-wrap { max-width: 720px; margin: 0 auto; display: flex; flex-direction: column; gap: 14px; font-family: 'Inter', sans-serif; }

/* ── Nav ──────────────────────────────────────────── */
.sc-nav { display: flex; gap: 6px; background: #f1f5f1; padding: 4px; border-radius: 12px; width: fit-content; }
.sc-nav-btn { display: inline-flex; align-items: center; gap: 7px; padding: 8px 16px; border-radius: 9px; border: none; background: transparent; color: #64748b; font-weight: 600; font-size: 13.5px; cursor: pointer; transition: all .18s; }
.sc-nav-btn.active { background: #fff; color: #2D4F1E; box-shadow: 0 1px 4px rgba(0,0,0,.1); font-weight: 700; }

/* ── Loading ──────────────────────────────────────── */
.sc-loading { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 56px; color: #94a3b8; font-size: 14px; }
.sc-spin { display: inline-block; width: 22px; height: 22px; border: 2.5px solid #e2e8f0; border-top-color: #2D4F1E; border-radius: 50%; animation: spin .65s linear infinite; flex-shrink: 0; }
.sc-spin-sm { width: 16px; height: 16px; border-width: 2px; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Empty state ──────────────────────────────────── */
.sc-empty-state { text-align: center; padding: 48px 24px; background: #fff; border: 1px solid #f0f0f0; border-radius: 16px; }
.sc-empty-icon { font-size: 48px; margin-bottom: 16px; }
.sc-empty-title { font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 6px; }
.sc-empty-sub { font-size: 13px; color: #94a3b8; line-height: 1.6; }

/* ── Start card ───────────────────────────────────── */
.sc-start-card { background: #fff; border: 1px solid #e8ece8; border-radius: 18px; overflow: hidden; }

.sc-shift-banner { display: flex; align-items: center; gap: 12px; padding: 16px 20px; background: linear-gradient(135deg, #1a3a14, #2D4F1E); color: #fff; }
.sc-shift-dot { width: 10px; height: 10px; border-radius: 50%; background: #4ade80; box-shadow: 0 0 0 3px rgba(74,222,128,.25); flex-shrink: 0; }
.sc-shift-meta { flex: 1; }
.sc-shift-label { display: block; font-size: 10px; letter-spacing: 1.5px; font-weight: 700; color: rgba(255,255,255,.55); margin-bottom: 3px; }
.sc-shift-name { font-size: 17px; font-weight: 800; }
.sc-shift-time { display: flex; align-items: center; gap: 5px; font-size: 12px; color: rgba(255,255,255,.7); background: rgba(255,255,255,.12); padding: 5px 10px; border-radius: 20px; white-space: nowrap; }

.sc-staff-section { padding: 20px 20px 0; }
.sc-section-label { font-size: 10px; font-weight: 800; letter-spacing: 1.8px; color: #94a3b8; margin-bottom: 12px; }
.sc-staff-grid { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
.sc-staff-card { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 14px 16px; background: #f8faf8; border: 1px solid #e4ece4; border-radius: 14px; min-width: 90px; }
.sc-staff-av { width: 40px; height: 40px; border-radius: 50%; background: #2D4F1E; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 15px; }
.sc-staff-name { font-size: 12.5px; font-weight: 700; color: #1e293b; text-align: center; }
.sc-staff-since { font-size: 11px; color: #94a3b8; }
.sc-notice { font-size: 12px; color: #2D4F1E; background: #f0f7ed; border-radius: 10px; padding: 10px 14px; line-height: 1.5; margin-bottom: 8px; }

.sc-checker-row { display: flex; align-items: center; gap: 12px; padding: 16px 20px; border-top: 1px solid #f1f5f1; }
.sc-checker-av { width: 38px; height: 38px; border-radius: 11px; background: #1e293b; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px; flex-shrink: 0; }
.sc-checker-name { font-size: 14px; font-weight: 700; color: #1e293b; }
.sc-checker-role { font-size: 11.5px; color: #94a3b8; }
.sc-checker-badge { margin-left: auto; font-size: 11px; font-weight: 700; color: #2D4F1E; background: #edf7ea; padding: 4px 10px; border-radius: 20px; }

.sc-cta { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 15px; border-radius: 12px; border: none; background: #2D4F1E; color: #fff; font-weight: 800; font-size: 15px; cursor: pointer; transition: all .2s; margin: 0; }
.sc-cta:hover:not(:disabled) { background: #3a6326; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(45,79,30,.3); }
.sc-cta:disabled { background: #a3b8a3; cursor: not-allowed; transform: none; box-shadow: none; }
.sc-cta:last-child, .sc-start-card .sc-cta { margin: 16px 20px 0; width: calc(100% - 40px); }
.sc-cta-outline { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 14px 20px; border-radius: 12px; border: 2px solid #2D4F1E; background: transparent; color: #2D4F1E; font-weight: 700; font-size: 14px; cursor: pointer; transition: all .2s; flex: 1; }
.sc-cta-outline:hover { background: #f0f7ed; }
.sc-cta-hint { text-align: center; font-size: 11.5px; color: #94a3b8; padding: 10px 20px 18px; margin: 0; }

/* ── Check screen ─────────────────────────────────── */
.sc-check-wrap { display: flex; flex-direction: column; gap: 10px; }
.sc-check-top { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #e8ece8; border-radius: 14px; padding: 12px 14px; }
.sc-icon-btn { width: 36px; height: 36px; border-radius: 50%; border: 1.5px solid #e2e8f0; background: #fff; color: #2D4F1E; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: all .15s; }
.sc-icon-btn:hover { background: #f0f7ed; border-color: #2D4F1E; }
.sc-check-info { flex: 1; min-width: 0; }
.sc-check-ca { display: block; font-size: 14px; font-weight: 800; color: #1e293b; }
.sc-check-staff { display: block; font-size: 11px; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sc-live-score { font-size: 18px; font-weight: 900; padding: 6px 14px; border-radius: 20px; letter-spacing: -0.5px; }
.sc-live-score.green { background: #dcfce7; color: #16a34a; }
.sc-live-score.amber { background: #fef3c7; color: #d97706; }
.sc-live-score.red { background: #fee2e2; color: #dc2626; }
.sc-live-score.gray { background: #f1f5f9; color: #94a3b8; }

.sc-counters { display: flex; gap: 8px; }
.sc-counter { display: inline-flex; align-items: center; gap: 5px; font-size: 12.5px; font-weight: 700; padding: 5px 12px; border-radius: 20px; }
.sc-counter--pass { background: #dcfce7; color: #16a34a; }
.sc-counter--fail { background: #fee2e2; color: #dc2626; }
.sc-counter--total { background: #f1f5f9; color: #64748b; margin-left: auto; }

.sc-pbar { height: 4px; background: #f1f5f1; border-radius: 2px; overflow: hidden; }
.sc-pbar-fill { height: 100%; border-radius: 2px; transition: all .4s ease; }

.sc-chips { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 2px; }
.sc-chip { flex-shrink: 0; padding: 7px 14px; border-radius: 20px; border: 1.5px solid #e2e8f0; background: #fff; color: #64748b; font-size: 12.5px; font-weight: 600; cursor: pointer; transition: all .15s; white-space: nowrap; display: flex; align-items: center; gap: 5px; }
.sc-chip--active { border-color: #2D4F1E; background: #2D4F1E; color: #fff; }

.sc-list { display: flex; flex-direction: column; gap: 8px; padding-bottom: 20px; }
.sc-item { background: #fff; border: 1.5px solid #e8ece8; border-radius: 14px; overflow: hidden; transition: border-color .15s; }
.sc-item--pass { border-color: #86efac; background: #f9fef9; }
.sc-item--fail { border-color: #fca5a5; background: #fff9f9; }
.sc-item-head { padding: 14px 14px 0; display: flex; flex-direction: column; gap: 6px; }
.sc-item-tag { align-self: flex-start; font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 6px; letter-spacing: .3px; }
.sc-item-title { font-size: 14px; font-weight: 600; color: #1e293b; line-height: 1.4; }
.sc-item-actions { display: flex; gap: 8px; padding: 10px 14px 14px; }
.sc-btn-pass, .sc-btn-fail { flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 10px; border-radius: 10px; border: 1.5px solid #e2e8f0; background: transparent; font-weight: 700; font-size: 13px; cursor: pointer; transition: all .15s; color: #94a3b8; }
.sc-btn-pass.active { background: #16a34a; border-color: #16a34a; color: #fff; }
.sc-btn-fail.active { background: #dc2626; border-color: #dc2626; color: #fff; }
.sc-btn-pass:hover:not(.active) { border-color: #86efac; color: #16a34a; }
.sc-btn-fail:hover:not(.active) { border-color: #fca5a5; color: #dc2626; }

.sc-fail-box { border-top: 1px solid #fecaca; padding: 12px 14px; display: flex; flex-direction: column; gap: 10px; }
.sc-fail-note { width: 100%; padding: 9px 12px; border-radius: 9px; border: 1px solid #fca5a5; font-size: 13px; outline: none; background: #fff; box-sizing: border-box; color: #1e293b; }
.sc-fail-note::placeholder { color: #cbd5e1; }
.sc-photos { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.sc-photo { position: relative; width: 70px; height: 70px; border-radius: 10px; overflow: hidden; border: 1.5px solid #fecaca; flex-shrink: 0; }
.sc-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
.sc-photo img.dim { opacity: .45; filter: blur(1px); }
.sc-photo-spin { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; }
.sc-photo-del { position: absolute; top: 4px; right: 4px; width: 18px; height: 18px; border-radius: 50%; border: none; background: rgba(220,38,38,.9); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; }
.sc-photo-add { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; width: 70px; height: 70px; border-radius: 10px; border: 1.5px dashed #fca5a5; background: transparent; color: #dc2626; font-size: 10.5px; font-weight: 700; cursor: pointer; transition: all .15s; flex-shrink: 0; }
.sc-photo-add:hover:not(:disabled) { background: #fff0f0; }
.sc-photo-add:disabled { opacity: .5; cursor: not-allowed; }

.sc-bottom { position: sticky; bottom: 0; padding: 16px 0; background: rgba(255,255,255,.95); backdrop-filter: blur(8px); border-top: 1px solid #f0f0f0; z-index: 100; margin-top: 10px; }
.sc-bottom .sc-cta { width: 100%; margin: 0; max-width: 720px; margin: 0 auto; }

/* ── Confirm ──────────────────────────────────────── */
.sc-confirm-wrap { display: flex; flex-direction: column; gap: 14px; }
.sc-back-link { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: #64748b; background: none; border: none; cursor: pointer; padding: 0; }
.sc-back-link:hover { color: #2D4F1E; }

.sc-result-hero { border: 2px solid; border-radius: 20px; padding: 28px; text-align: center; }
.sc-result-score { font-size: 72px; font-weight: 900; line-height: 1; letter-spacing: -3px; }
.sc-result-score span { font-size: 36px; letter-spacing: -1px; }
.sc-result-tag { display: inline-flex; align-items: center; gap: 6px; margin-top: 12px; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 700; }
.sc-result-counts { margin-top: 14px; font-size: 14px; color: #64748b; display: flex; gap: 8px; justify-content: center; }
.sc-result-counts .pass { color: #16a34a; font-weight: 700; }
.sc-result-counts .fail { color: #dc2626; font-weight: 700; }
.sc-result-counts .sep { color: #cbd5e1; }

.sc-meta-list { background: #f8fafc; border-radius: 14px; overflow: hidden; }
.sc-meta-row { display: flex; justify-content: space-between; align-items: flex-start; padding: 11px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13px; gap: 12px; }
.sc-meta-row:last-child { border: none; }
.sc-meta-row span { color: #94a3b8; flex-shrink: 0; }
.sc-meta-row strong { color: #1e293b; text-align: right; }

.sc-breakdown { background: #fff; border: 1px solid #e8ece8; border-radius: 14px; overflow: hidden; }
.sc-breakdown-title { padding: 12px 16px; font-size: 11px; font-weight: 800; letter-spacing: 1.5px; color: #94a3b8; border-bottom: 1px solid #f1f5f9; }
.sc-breakdown-row { display: flex; align-items: flex-start; gap: 10px; padding: 11px 16px; border-bottom: 1px solid #f9fafb; }
.sc-breakdown-row:last-child { border: none; }
.sc-bd-icon { flex-shrink: 0; margin-top: 2px; }
.sc-bd-body { flex: 1; min-width: 0; }
.sc-bd-name { display: block; font-size: 13px; font-weight: 600; color: #1e293b; }
.sc-bd-note { display: block; font-size: 12px; color: #dc2626; margin-top: 3px; font-style: italic; }
.sc-bd-photos { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
.sc-bd-photos img { width: 52px; height: 52px; object-fit: cover; border-radius: 8px; border: 1px solid #fecaca; }

.sc-lock-warn { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 11px 14px; }

/* ── Done ─────────────────────────────────────────── */
.sc-done-wrap { display: flex; flex-direction: column; align-items: center; padding: 40px 24px; text-align: center; }
.sc-done-ring { width: 130px; height: 130px; border-radius: 50%; border: 6px solid; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; }
.sc-done-num { font-size: 38px; font-weight: 900; line-height: 1; }
.sc-done-num small { font-size: 20px; }
.sc-done-h { font-size: 22px; font-weight: 800; color: #1e293b; margin: 0 0 8px; }
.sc-done-p { font-size: 14px; color: #64748b; margin: 0 0 6px; }
.sc-done-sub { font-size: 12.5px; color: #94a3b8; line-height: 1.6; margin-bottom: 24px; }
.sc-done-acts { display: flex; gap: 10px; width: 100%; max-width: 400px; }
.sc-done-acts .sc-cta { flex: 1; width: auto; margin: 0; }

/* ── Dashboard ────────────────────────────────────── */
.sc-dashboard-wrap { display: flex; flex-direction: column; gap: 16px; }

.sc-filter-row { display: flex; align-items: center; gap: 10px; }
.sc-month-pick { padding: 9px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-size: 14px; font-weight: 600; color: #1e293b; outline: none; cursor: pointer; }
.sc-filter-btn { display: inline-flex; align-items: center; gap: 7px; padding: 9px 16px; border-radius: 10px; border: 1.5px solid #e2e8f0; background: #fff; color: #64748b; font-weight: 600; font-size: 13px; cursor: pointer; transition: all .15s; }
.sc-filter-btn:hover { background: #f8fafc; color: #2D4F1E; }

.sc-kpi-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.sc-kpi { background: #fff; border: 1px solid #e8ece8; border-radius: 16px; padding: 18px 16px; }
.sc-kpi--accent { background: var(--kb, #f0fdf4); border-color: var(--kc, #86efac)33; }
.sc-kpi-val { font-size: 32px; font-weight: 900; color: #1e293b; line-height: 1; }
.sc-kpi-label { font-size: 11.5px; color: #94a3b8; margin-top: 6px; }

.sc-panel { background: #fff; border: 1px solid #e8ece8; border-radius: 16px; overflow: hidden; }
.sc-panel-title { padding: 14px 18px 12px; font-size: 13px; font-weight: 700; color: #1e293b; border-bottom: 1px solid #f1f5f1; }
.sc-staff-row { display: flex; align-items: center; gap: 10px; padding: 12px 18px; border-bottom: 1px solid #f8fafc; }
.sc-staff-row:last-of-type { border-bottom: none; }
.sc-rank { width: 22px; text-align: center; font-size: 12px; font-weight: 800; color: #cbd5e1; flex-shrink: 0; }
.sc-rank.gold { color: #f59e0b; }
.sc-rank.silver { color: #94a3b8; }
.sc-rank.bronze { color: #b45309; }
.sc-staff-av2 { width: 34px; height: 34px; border-radius: 50%; background: #f1f5f1; border: 2px solid #2D4F1E33; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; color: #2D4F1E; flex-shrink: 0; }
.sc-staff-info { flex: 1; min-width: 0; }
.sc-staff-nm { display: block; font-size: 13.5px; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sc-staff-cnt { font-size: 11px; color: #94a3b8; }
.sc-staff-cnt .warn { color: #d97706; font-weight: 700; }
.sc-score-bar { width: 80px; flex-shrink: 0; height: 6px; background: #f1f5f1; border-radius: 3px; overflow: hidden; }
.sc-score-fill { height: 100%; border-radius: 3px; transition: width .5s ease; }
.sc-score-pct { width: 44px; text-align: right; font-size: 15px; font-weight: 800; flex-shrink: 0; }
.sc-tag { font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 6px; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px; }
.sc-tag--bonus { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
.sc-tag--ok { background: #f0fdf4; color: #16a34a; }
.sc-tag--no { background: #fef2f2; color: #dc2626; }
.sc-bonus-note { padding: 12px 18px; font-size: 12px; color: #64748b; background: #fafafa; border-top: 1px solid #f1f5f1; line-height: 1.6; }

.sc-hist-row { display: flex; align-items: center; gap: 14px; padding: 12px 18px; border-bottom: 1px solid #f8fafc; }
.sc-hist-row:last-child { border: none; }
.sc-hist-left { display: flex; flex-direction: column; gap: 3px; width: 62px; flex-shrink: 0; }
.sc-hist-date { font-size: 12px; font-weight: 700; color: #1e293b; }
.sc-hist-time { display: flex; align-items: center; gap: 3px; font-size: 11px; color: #94a3b8; }
.sc-hist-mid { flex: 1; min-width: 0; }
.sc-hist-shift { display: block; font-size: 13px; font-weight: 600; color: #1e293b; }
.sc-hist-staff { display: block; font-size: 11.5px; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sc-hist-score { font-size: 16px; font-weight: 800; padding: 5px 12px; border-radius: 10px; }

@media (max-width: 640px) {
    .sc-wrap { padding: 0 10px; }
    
    /* Gom 3 thẻ KPI thành 1 hàng ngang duy nhất */
    .sc-kpi-row { display: flex; flex-direction: row; gap: 0; background: #fff; border: 1px solid #e8ece8; border-radius: 14px; overflow: hidden; }
    .sc-kpi { flex: 1; padding: 12px 4px; border: none; border-radius: 0; border-right: 1px solid #f1f5f1; display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .sc-kpi:last-child { border-right: none; }
    .sc-kpi--accent { background: var(--kb, #f0fdf4); }
    .sc-kpi-val { font-size: 22px; line-height: 1; }
    .sc-kpi-label { margin-top: 6px; font-size: 10px; font-weight: 600; color: #64748b; text-align: center; }
    
    .sc-score-bar { display: none; }
    .sc-staff-av2, .sc-rank { display: none; }
    .sc-staff-row { padding: 14px; }
    .sc-staff-info { padding-right: 10px; }
    
    .sc-hist-row { padding: 14px; gap: 8px; align-items: flex-start; }
    .sc-hist-left { width: 70px; }
}
</style>

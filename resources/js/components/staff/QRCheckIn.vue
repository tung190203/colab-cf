<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAdminAuth } from '../../composables/useAdminAuth';
import axios from 'axios';
import { CheckCircle2, XCircle, Loader2, Clock, ClipboardCheck, ArrowLeft } from 'lucide-vue-next';

const router = useRouter();
const { authHeader, isLoggedIn } = useAdminAuth();

const status = ref('waiting'); // waiting, processing, success, error
const message = ref('Nhấn nút bên dưới để bắt đầu xác thực vị trí và chấm công.');
const resultData = ref(null);
const handoverReminder = ref(false);

onMounted(() => {
    if (!isLoggedIn()) {
        router.push(`/admin/login?redirect=${encodeURIComponent(router.currentRoute.value.fullPath)}`);
    }
});

async function startCheckIn() {
    if (!navigator.geolocation) {
        status.value = 'error';
        message.value = 'Trình duyệt của bạn không hỗ trợ định vị GPS.';
        return;
    }

    status.value = 'processing';
    message.value = 'Đang xác thực vị trí của bạn...';

    navigator.geolocation.getCurrentPosition(
        async (position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            
            message.value = 'Đang xử lý chấm công...';
            try {
                const res = await axios.post('/api/staff/smart-check-in', { lat, lng }, { headers: authHeader() });
                status.value = 'success';
                message.value = res.data.message;
                resultData.value = res.data;
                handoverReminder.value = true;
            } catch (err) {
                status.value = 'error';
                message.value = err.response?.data?.message || 'Có lỗi xảy ra khi xử lý check-in.';
            }
        },
        (err) => {
            status.value = 'error';
            if (err.code === 1) {
                message.value = 'Bạn phải cho phép truy cập vị trí để chấm công. Vui lòng kiểm tra cài đặt trình duyệt.';
            } else {
                message.value = 'Không thể xác định vị trí (Lỗi: ' + err.message + '). Vui lòng thử lại.';
            }
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}

function goBack() {
    router.push('/staff/attendance');
}

function closeReminder() {
    handoverReminder.value = false;
}

function goToShiftHandover() {
    const tab = resultData.value?.type === 'check-in' ? 'pending' : 'create';
    handoverReminder.value = false;
    router.push(`/staff/shift-handover?tab=${tab}`);
}
</script>

<template>
    <div class="qrc-page">
        <div class="qrc-bg-left"></div>
        <div class="qrc-bg-right"></div>

        <div class="qrc-card">
            <!-- Waiting State -->
            <div v-if="status === 'waiting'" class="qrc-content">
                <div class="qrc-icon-wrap is-loading">
                    <Clock :size="48" />
                </div>
                <h2 class="qrc-title">Xác thực</h2>
                <p class="qrc-message">{{ message }}</p>
                <button class="qrc-btn" @click="startCheckIn">
                    Bắt đầu chấm công
                </button>
            </div>

            <!-- Processing State -->
            <div v-if="status === 'processing'" class="qrc-content">
                <div class="qrc-icon-wrap is-loading">
                    <Loader2 :size="48" class="qrc-spinner" />
                </div>
                <h2 class="qrc-title">Vui lòng đợi</h2>
                <p class="qrc-message">{{ message }}</p>
            </div>

            <!-- Success State -->
            <div v-else-if="status === 'success'" class="qrc-content">
                <div class="qrc-icon-wrap is-success">
                    <CheckCircle2 :size="64" />
                </div>
                <h2 class="qrc-title">Thành công!</h2>
                <p class="qrc-message">{{ message }}</p>

                <div class="qrc-info-box" v-if="resultData">
                    <div class="qib-row">
                        <div class="qib-label">Ca làm việc:</div>
                        <div class="qib-val">{{ resultData.shift }}</div>
                    </div>
                    <div class="qib-row">
                        <div class="qib-label">Thời gian:</div>
                        <div class="qib-val">{{ resultData.time }}</div>
                    </div>
                    <div class="qib-row">
                        <div class="qib-label">Trạng thái:</div>
                        <div class="qib-val badge" :class="resultData.type === 'check-in' ? 'badge-in' : 'badge-out'">
                            {{ resultData.type === 'check-in' ? 'Check-in' : 'Check-out' }}
                        </div>
                    </div>
                </div>

                <button class="qrc-btn" @click="goBack">
                    <ArrowLeft :size="18" />
                    Về trang điểm danh
                </button>
            </div>

            <!-- Error State -->
            <div v-else-if="status === 'error'" class="qrc-content">
                <div class="qrc-icon-wrap is-error">
                    <XCircle :size="64" />
                </div>
                <h2 class="qrc-title">Thất bại</h2>
                <p class="qrc-message">{{ message }}</p>

                <button class="qrc-btn is-outline" @click="goBack">
                    <ArrowLeft :size="18" />
                    Quay lại
                </button>
            </div>
        </div>

        <div v-if="handoverReminder" class="qrc-reminder-backdrop" @click.self="closeReminder">
            <div class="qrc-reminder">
                <div class="qrc-reminder-icon" :class="{ out: resultData?.type === 'check-out' }">
                    <ClipboardCheck :size="30" />
                </div>
                <h3>{{ resultData?.type === 'check-in' ? 'Nhắc nhận ca' : 'Nhắc giao ca' }}</h3>
                <p>
                    {{ resultData?.type === 'check-in'
                        ? 'Bạn vừa check-in. Vào màn nhận ca để kiểm tra bàn giao từ ca trước.'
                        : 'Bạn vừa check-out. Vào màn giao ca để bàn giao tiền, món bán và ghi chú cho ca sau.'
                    }}
                </p>
                <div class="qrc-reminder-actions">
                    <button type="button" class="qrc-reminder-secondary" @click="closeReminder">Để sau</button>
                    <button type="button" class="qrc-reminder-primary" @click="goToShiftHandover">
                        {{ resultData?.type === 'check-in' ? 'Đi nhận ca' : 'Đi giao ca' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

.qrc-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #0f1117;
    position: relative;
    overflow: hidden;
    font-family: 'Inter', sans-serif;
    padding: 20px;
}

.qrc-bg-left {
    position: absolute;
    top: -200px;
    left: -200px;
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, rgba(101, 163, 13, 0.15) 0%, transparent 70%);
    pointer-events: none;
}

.qrc-bg-right {
    position: absolute;
    bottom: -200px;
    right: -100px;
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(163, 101, 13, 0.1) 0%, transparent 70%);
    pointer-events: none;
}

.qrc-card {
    width: 100%;
    max-width: 400px;
    background: rgba(255, 255, 255, 0.04);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 32px;
    padding: 48px 32px;
    position: relative;
    z-index: 1;
    box-shadow: 0 32px 80px rgba(0, 0, 0, 0.5);
    text-align: center;
}

.qrc-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
}

.qrc-icon-wrap {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 8px;
}

.is-loading { color: #64748b; background: rgba(100, 116, 139, 0.1); }
.is-success { color: #4ade80; background: rgba(74, 222, 128, 0.1); }
.is-error { color: #f87171; background: rgba(248, 113, 113, 0.1); }

.qrc-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

.qrc-title {
    font-size: 1.75rem;
    font-weight: 800;
    color: #fff;
    margin: 0;
    letter-spacing: -0.02em;
}

.qrc-message {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1rem;
    line-height: 1.6;
    margin: 0;
}

.qrc-info-box {
    width: 100%;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 20px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 8px;
}

.qib-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.qib-label {
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.9rem;
    font-weight: 500;
}

.qib-val {
    color: #fff;
    font-size: 0.95rem;
    font-weight: 700;
}

.badge {
    padding: 4px 12px;
    border-radius: 100px;
    font-size: 0.8rem;
}
.badge-in { background: rgba(74, 222, 128, 0.15); color: #4ade80; }
.badge-out { background: rgba(248, 113, 113, 0.15); color: #f87171; }

.qrc-btn {
    width: 100%;
    padding: 16px;
    background: #4ade80;
    color: #064e3b;
    border: none;
    border-radius: 16px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 16px;
}

.qrc-btn:hover {
    background: #22c55e;
    transform: translateY(-2px);
}

.qrc-btn.is-outline {
    background: rgba(255, 255, 255, 0.05);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.qrc-btn.is-outline:hover {
    background: rgba(255, 255, 255, 0.1);
}

.qrc-reminder-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1400;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(0, 0, 0, 0.58);
}

.qrc-reminder {
    width: min(420px, 100%);
    padding: 26px;
    border-radius: 22px;
    background: #fff;
    box-shadow: 0 28px 90px rgba(0, 0, 0, 0.35);
    text-align: center;
}

.qrc-reminder-icon {
    width: 58px;
    height: 58px;
    margin: 0 auto 14px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ecfdf3;
    color: #15803d;
}

.qrc-reminder-icon.out {
    background: #fff7ed;
    color: #c2410c;
}

.qrc-reminder h3 {
    margin: 0 0 8px;
    color: #101828;
    font-size: 1.35rem;
    font-weight: 800;
}

.qrc-reminder p {
    margin: 0;
    color: #667085;
    line-height: 1.55;
    font-weight: 600;
}

.qrc-reminder-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-top: 22px;
}

.qrc-reminder-actions button {
    min-height: 44px;
    border-radius: 10px;
    font-weight: 800;
    cursor: pointer;
}

.qrc-reminder-secondary {
    border: 1px solid #d0d5dd;
    background: #fff;
    color: #344054;
}

.qrc-reminder-primary {
    border: 1px solid #20451f;
    background: #20451f;
    color: #fff;
}
</style>

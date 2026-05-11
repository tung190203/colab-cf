<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAdminAuth } from '../../composables/useAdminAuth';
import axios from 'axios';
import { CheckCircle2, XCircle, Loader2, Clock, LogOut, LogIn, ArrowLeft } from 'lucide-vue-next';

const router = useRouter();
const { authHeader, isLoggedIn } = useAdminAuth();

const status = ref('loading'); // loading, success, error
const message = ref('Đang xử lý thông tin check-in...');
const resultData = ref(null);

onMounted(async () => {
    if (!isLoggedIn()) {
        router.push(`/admin/login?redirect=${encodeURIComponent(router.currentRoute.value.fullPath)}`);
        return;
    }

    message.value = 'Đang xác thực vị trí của bạn...';
    
    if (!navigator.geolocation) {
        status.value = 'error';
        message.value = 'Trình duyệt của bạn không hỗ trợ định vị GPS.';
        return;
    }

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
                
                setTimeout(() => {
                    router.push('/staff/attendance');
                }, 5000);
            } catch (err) {
                status.value = 'error';
                message.value = err.response?.data?.message || 'Có lỗi xảy ra khi xử lý check-in.';
            }
        },
        (err) => {
            status.value = 'error';
            if (err.code === 1) {
                message.value = 'Bạn phải cho phép truy cập vị trí để chấm công.';
            } else {
                message.value = 'Không thể xác định vị trí của bạn. Vui lòng thử lại.';
            }
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
});

function goBack() {
    router.push('/staff/attendance');
}
</script>

<template>
    <div class="qrc-page">
        <div class="qrc-bg-left"></div>
        <div class="qrc-bg-right"></div>

        <div class="qrc-card">
            <!-- Loading State -->
            <div v-if="status === 'loading'" class="qrc-content">
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
                <p class="qrc-auto-back">Tự động quay lại sau 5 giây...</p>
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

.qrc-auto-back {
    color: rgba(255, 255, 255, 0.3);
    font-size: 0.85rem;
    margin: 0;
}
</style>

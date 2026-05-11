<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { toast } from 'vue3-toastify';
import { useAdminAuth } from '../../composables/useAdminAuth';

const router = useRouter();
const { login, adminUser } = useAdminAuth();

const phone = ref('');
const password = ref('');
const loading = ref(false);
const showPass = ref(false);

async function handleLogin() {
    if (!phone.value || !password.value) {
        toast.error('Vui lòng nhập đầy đủ thông tin');
        return;
    }
    loading.value = true;
    try {
        const data = await login(phone.value, password.value);
        toast.success(`Xin chào, ${data.user.name}!`);
        
        const redirectPath = router.currentRoute.value.query.redirect;

        setTimeout(() => {
            if (redirectPath) {
                router.push(redirectPath);
            } else if (data.user.role === 'admin') {
                router.push('/admin/dashboard');
            } else {
                router.push('/staff/dashboard');
            }
        }, 800);
    } catch (err) {
        toast.error(err.response?.data?.message || 'Đăng nhập thất bại');
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="al-page">
        <div class="al-bg-left"></div>
        <div class="al-bg-right"></div>

        <div class="al-card">
            <div class="al-logo-wrap">
                <img src="../../../images/logo.png" alt="Colab Coffee" class="al-logo" />
            </div>

            <h1 class="al-title">Đăng nhập</h1>
            <p class="al-subtitle">Dành cho admin & nhân viên Co-lab</p>

            <div class="al-form">
                <div class="al-field">
                    <label class="al-label">Số điện thoại</label>
                    <div class="al-input-wrap">
                        <svg class="al-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/>
                        </svg>
                        <input v-model="phone" type="tel" class="al-input" placeholder="09xx xxx xxx" @keyup.enter="handleLogin" />
                    </div>
                </div>

                <div class="al-field">
                    <label class="al-label">Mật khẩu</label>
                    <div class="al-input-wrap">
                        <svg class="al-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0110 0v4"/>
                        </svg>
                        <input v-model="password" :type="showPass ? 'text' : 'password'" class="al-input" placeholder="••••••••" @keyup.enter="handleLogin" />
                        <button class="al-eye" @click="showPass = !showPass" type="button">
                            <svg v-if="!showPass" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24M1 1l22 22"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button class="al-btn" :class="{ 'al-btn--loading': loading }" @click="handleLogin" :disabled="loading">
                    <span v-if="!loading">Đăng nhập</span>
                    <span v-else class="al-spinner"></span>
                </button>
            </div>

            <div class="al-back">
                <router-link to="/" class="al-back-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Về trang chủ
                </router-link>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

.al-page {
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

.al-bg-left {
    position: absolute;
    top: -200px;
    left: -200px;
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, rgba(45, 79, 30, 0.35) 0%, transparent 70%);
    pointer-events: none;
}

.al-bg-right {
    position: absolute;
    bottom: -200px;
    right: -100px;
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(101, 163, 13, 0.2) 0%, transparent 70%);
    pointer-events: none;
}

.al-card {
    width: 100%;
    max-width: 440px;
    background: rgba(255, 255, 255, 0.04);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 28px;
    padding: 48px 40px;
    position: relative;
    z-index: 1;
    box-shadow: 0 32px 80px rgba(0, 0, 0, 0.5);
}

.al-logo-wrap {
    text-align: center;
    margin-bottom: 24px;
}

.al-logo {
    width: 70%;
    filter: brightness(0) invert(1);
    opacity: 0.9;
}

@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.8); }
}

.al-title {
    font-size: 2rem;
    font-weight: 800;
    color: #fff;
    margin: 0 0 6px;
    letter-spacing: -0.03em;
}

.al-subtitle {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.9rem;
    margin: 0 0 32px;
}

.al-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.al-field { display: flex; flex-direction: column; gap: 8px; }

.al-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.7);
    letter-spacing: 0.02em;
}

.al-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.al-icon {
    position: absolute;
    left: 16px;
    width: 18px;
    height: 18px;
    color: rgba(255, 255, 255, 0.35);
    pointer-events: none;
}

.al-input {
    width: 100%;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 14px;
    padding: 14px 16px 14px 46px;
    font-size: 1rem;
    font-weight: 500;
    color: #fff;
    transition: all 0.25s ease;
    outline: none;
    font-family: 'Inter', sans-serif;
}

.al-input::placeholder { color: rgba(255, 255, 255, 0.2); }

.al-input:focus {
    border-color: rgba(101, 163, 13, 0.6);
    background: rgba(101, 163, 13, 0.06);
    box-shadow: 0 0 0 4px rgba(101, 163, 13, 0.1);
}

.al-eye {
    position: absolute;
    right: 14px;
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.35);
    cursor: pointer;
    padding: 4px;
    display: flex;
    align-items: center;
    transition: color 0.2s;
}
.al-eye:hover { color: rgba(255,255,255,0.7); }

.al-btn {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, #2D4F1E, #4a7c2f);
    color: white;
    border: none;
    border-radius: 14px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    margin-top: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 52px;
    font-family: 'Inter', sans-serif;
    letter-spacing: 0.02em;
}

.al-btn:hover:not(:disabled) {
    background: linear-gradient(135deg, #3a6428, #5a9438);
    transform: translateY(-2px);
    box-shadow: 0 12px 28px rgba(45, 79, 30, 0.4);
}

.al-btn:active:not(:disabled) { transform: translateY(0); }
.al-btn:disabled { opacity: 0.6; cursor: not-allowed; }

.al-spinner {
    width: 20px;
    height: 20px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    display: inline-block;
}

@keyframes spin { to { transform: rotate(360deg); } }

.al-back {
    text-align: center;
    margin-top: 24px;
}

.al-back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: rgba(255, 255, 255, 0.4);
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    transition: color 0.2s;
}
.al-back-link:hover { color: rgba(255,255,255,0.75); }

@media (max-width: 480px) {
    .al-card { padding: 36px 24px; border-radius: 24px; }
    .al-title { font-size: 1.7rem; }
}
</style>

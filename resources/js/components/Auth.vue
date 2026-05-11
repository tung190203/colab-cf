<script setup>
import { useRouter } from 'vue-router';
import { useBooking } from '../composables/useBooking';
import { toast } from 'vue3-toastify';
import { onMounted } from 'vue';

const router = useRouter();
const { name, phone, fetchTables, selectTableFromUrl, fetchUserByCard, param, fetchUserByPhone } = useBooking();

function goNext() {
  if (!phone.value?.trim()) {
    toast.error('Vui lòng nhập số điện thoại');
    return;
  }

  const phoneRegex = /^[0-9]{9,12}$/;
  if (!phoneRegex.test(phone.value)) {
    toast.error('Số điện thoại không hợp lệ');
    return;
  }

  fetchUserByPhone(phone.value)
    .then(() => {
      if (name.value && phone.value) {
        toast.success('Đăng nhập thành công');
        setTimeout(() => {
          router.push('/package');
        }, 1000);
      }
    })
    .catch(err => {
      toast.error(err.message);
    });
}

function goBack() {
  router.push("/");
}

onMounted(async () => {
  await fetchTables();
  selectTableFromUrl();
  await fetchUserByCard(param.get('id'));
  if(name.value && phone.value) {
    router.push('/package');
  }
});
</script>

<template>
  <div class="auth-page">
    <div class="auth-card shadow">
      <div class="text-center mb-5">
        <img src="../../images/logo.png" alt="logo" class="auth-logo" />
      </div>

      <div class="auth-content">
        <h2 class="auth-title mb-2">Đăng nhập</h2>
        <p class="auth-subtitle mb-4">Chào mừng bạn quay trở lại với Co-lab</p>

        <div class="form-group mb-4">
          <label class="form-label">Số điện thoại</label>
          <div class="input-wrapper">
            <span class="input-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
            </span>
            <input 
              v-model="phone" 
              type="tel" 
              class="auth-input shadow-none" 
              placeholder="09xx xxx xxx"
              @keyup.enter="goNext"
            />
          </div>
        </div>

        <button 
          class="btn-auth w-100 mb-4" 
          :disabled="!phone"
          @click="goNext"
        >
          Tiếp tục
        </button>

        <div class="text-center">
          <button class="btn-link-back" @click="goBack">
            <span class="arrow-back">←</span> Quay lại
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

.auth-page {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: 'Inter', sans-serif;
}

.auth-card {
  width: 100%;
  max-width: 440px;
  background: #ffffff;
  border-radius: 24px;
  padding: 40px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08) !important;
  transition: all 0.3s ease;
}

.auth-logo {
  width: 80%;
}

.auth-title {
  font-weight: 800;
  font-size: 2rem;
  color: #1a1a1a;
  letter-spacing: -0.02em;
}

.auth-subtitle {
  color: #666;
  font-size: 1rem;
  font-weight: 500;
}

.form-label {
  font-size: 0.9rem;
  font-weight: 600;
  color: #1a1a1a;
  margin-bottom: 8px;
  display: block;
}

.input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.input-icon {
  position: absolute;
  left: 16px;
  color: #999;
  display: flex;
  align-items: center;
}

.auth-input {
  width: 100%;
  border: 2px solid #f0f0f0;
  border-radius: 16px;
  padding: 16px 16px 16px 48px;
  font-size: 1.1rem;
  font-weight: 500;
  background-color: #fdfdfd;
  transition: all 0.3s ease;
  color: #1a1a1a;
}

.auth-input:focus {
  border-color: #2D4F1E;
  background-color: #fff;
  box-shadow: 0 0 0 4px rgba(45, 79, 30, 0.1);
  outline: none;
}

.auth-input::placeholder {
  color: #ccc;
}

.btn-auth {
  background-color: #2D4F1E;
  color: white;
  border: none;
  padding: 18px;
  border-radius: 16px;
  font-weight: 700;
  font-size: 1.1rem;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  cursor: pointer;
}

.btn-auth:disabled {
  background-color: #e0e0e0;
  color: #a0a0a0;
  cursor: not-allowed;
}

.btn-auth:not(:disabled):hover {
  background-color: #1f3815;
  transform: translateY(-2px);
  box-shadow: 0 10px 20px rgba(45, 79, 30, 0.2);
}

.btn-auth:not(:disabled):active {
  transform: translateY(0);
}

.btn-link-back {
  background: none;
  border: none;
  color: #888;
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s ease;
  padding: 8px 16px;
  border-radius: 12px;
}

.btn-link-back:hover {
  color: #1a1a1a;
  background-color: #f0f0f0;
}

.arrow-back {
  font-size: 1.2rem;
}

@media (max-width: 576px) {
  .auth-page {
    align-items: flex-start;
    padding-top: 20px;
  }

  .auth-card {
    box-shadow: none !important;
    padding: 20px 10px;
    border-radius: 0;
  }

  .auth-title {
    font-size: 1.8rem;
  }
}
</style>

<script setup>
import { useRouter } from 'vue-router';

const router = useRouter();

function startBooking() {
  router.push('/login');
}
</script>

<template>
  <div class="welcome-container text-center py-5">
    <div class="logo-section mb-4">
      <img src="../../images/logo.png" alt="Logo" class="logo">
    </div>

    <div class="hero-text mb-5">
      <p class="main-desc">
        Không gian làm việc, gặp gỡ và nạp năng lượng<br />
        dành cho người Hà Nội hiện đại.
      </p>
    </div>

    <div class="action-section mb-4">
      <button class="btn-start shadow" @click="startBooking">
        Đặt chỗ làm việc <span class="arrow">→</span>
      </button>
      <div class="price-info mt-3">
        1 giờ từ 50,000đ · Gói ngày 150,000đ<br />
        Bao gồm đồ uống và in ấn
      </div>
    </div>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');

.welcome-container {
  max-width: 600px;
  margin: 0 auto;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  color: #1a1a1a;
  padding: 40px 20px;
  animation: fadeIn 0.8s ease-out;
}

.logo {
  width: 100%;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

.hero-text {
  margin-top: 50px;
}

.main-desc {
  font-size: 1.3rem;
  line-height: 1.5;
  color: #4a4a4a;
  font-weight: 500;
}

.action-section {
  margin-top: 60px;
  max-width: 440px;
  margin-left: auto;
  margin-right: auto;
}

.btn-start {
  background-color: #2D4F1E;
  color: white;
  border: none;
  padding: 22px 40px;
  border-radius: 20px;
  font-size: 1.7rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  width: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 15px;
  box-shadow: 0 15px 35px rgba(45, 79, 30, 0.25);
}

.btn-start:hover {
  background-color: #1f3815;
  transform: translateY(-5px);
  box-shadow: 0 20px 40px rgba(45, 79, 30, 0.35);
}

.btn-start:active {
  transform: translateY(0);
}

.arrow {
  font-size: 2rem;
  line-height: 1;
}

.price-info {
  font-size: 1.05rem;
  color: #999;
  line-height: 1.6;
  margin-top: 25px;
}

@media (max-width: 576px) {
  .main-desc {
    font-size: 1.1rem;
  }
  .btn-start {
    font-size: 1.4rem;
    padding: 18px 30px;
  }
}
</style>

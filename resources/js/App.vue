<script setup>
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();
const showGreeting = computed(() => {
  const hidePaths = ['/package'];
  return hidePaths.includes(route.path);
});

const showMenu = computed(() => {
  const shoePath = ['/new-bookings', '/add-member'];
  return shoePath.includes(route.path);
});
const isMenuOpen = ref(false);
const toggleMenu = () => {
  isMenuOpen.value = !isMenuOpen.value;
};

const showBackButton = computed(() => {
  const hidePaths = ['/table', '/extras', 'summary'];
  return hidePaths.includes(route.path);
});
</script>

<template>
  <div>
    <!-- Navbar desktop -->
    <nav v-if="showMenu" class="navbar navbar-expand-lg navbar-light bg-light d-none d-lg-block shadow-sm text-center">
      <div class="container-fluid">
        <div class="collapse navbar-collapse" id="navbarNav">
          <ul class="navbar-nav ms-auto">
            <li class="nav-item">
              <router-link to="/new-bookings" class="nav-link">Danh sách đặt chỗ</router-link>
            </li>
            <li class="nav-item">
              <router-link to="/add-member" class="nav-link">Thêm thành viên</router-link>
            </li>
          </ul>
        </div>
      </div>
    </nav>

    <!-- <div class="booking-root container py-5">
      <div class="card booking-card mx-auto pt-0 pb-4 mt-3 px-4 shadow">
        <div class="mb-4">
          <img
            src="../images/logo.png"
            alt="logo"
            class="rounded mt-4"
            style="width: 160px; display: flex; margin: 0 auto;"
          />
          <div>
            <p v-if="showGreeting" class="text-muted fs-6 mb-0 mt-3 text-center">
              Xin chào! Chọn gói làm việc của bạn
            </p>
          </div>
        </div>
        <router-view />
      </div>
    </div> -->
    <div class="booking-root container py-5">
      <div class="card booking-card mx-auto pt-0 pb-4 mt-3 px-4 shadow position-relative">
        <!-- Nút back -->
        <button v-if="showBackButton" class="back-btn" @click="$router.back()">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>

        <div class="mb-4 text-center">
          <img src="../images/logo.png" alt="logo" class="rounded mt-4"
            style="width: 160px; display: flex; margin: 0 auto;" />
          <div>
            <p v-if="showGreeting" class="text-muted fs-6 mb-0 mt-3 text-center">
              Xin chào! Chọn gói làm việc của bạn
            </p>
          </div>
        </div>
        <router-view />
      </div>
    </div>
    <!-- Menu mobile + tablet -->
    <div v-if="showMenu" class="mobile-menu-wrapper d-block d-lg-none">
      <button class="hamburger-btn" :class="{ open: isMenuOpen }" @click="toggleMenu">
        <span class="bar"></span>
        <span class="bar"></span>
        <span class="bar"></span>
      </button>
      <div class="mobile-menu shadow-sm" :class="{ 'is-open': isMenuOpen }">
        <button class="hamburger-btn close-btn" :class="{ open: isMenuOpen }" @click="toggleMenu">
          <span class="bar"></span>
          <span class="bar"></span>
          <span class="bar"></span>
        </button>
        <router-link to="/new-bookings" class="menu-item" @click="isMenuOpen = false">
          Danh sách đặt chỗ
        </router-link>
        <router-link to="/add-member" class="menu-item" @click="isMenuOpen = false">
          Thêm thành viên
        </router-link>
      </div>
    </div>
  </div>
</template>

<style>
/* --- CSS NGUYÊN GỐC --- */
.booking-root {
  min-height: 100vh;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  padding-top: 40px;
  box-sizing: border-box;
  font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
}

.booking-card {
  padding: 30px;
  background: #ffffff;
  border-radius: 16px;
  max-width: 780px;
  width: 100%;
  box-shadow: 0 8px 24px rgb(0 0 0 / 0.08);
}

.step-content {
  border-radius: 16px;
  padding: 24px 30px;
  min-height: 380px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.btn {
  border-radius: 8px;
  font-weight: 600;
  transition: background-color 0.25s ease;
}

.btn-warning {
  background-color: #20451F !important;
  border-color: #20451F !important;
  color: #fff;
}

.btn-warning:hover:not(:disabled) {
  background-color: #183317;
  border-color: #183317;
  color: #fff;
}

.btn-outline-secondary {
  color: #777;
  border-color: #ccc;
  background-color: transparent;
}

.btn-outline-secondary:hover {
  background-color: #eee;
  color: #333;
  border-color: #bbb;
}

.package-card {
  background: #fafafa;
  border: 1.5px solid #ddd;
  border-radius: 12px;
  transition: all 0.3s ease;
  user-select: none;
}

.package-card:hover {
  background: #f0f4fa;
  border-color: #20451F;
  box-shadow: 0 4px 12px rgb(32 69 31 / 0.25);
}

.package-card-selected {
  background: #d9e8ff;
  border-color: #20451F;
  box-shadow: 0 6px 18px rgb(32 69 31 / 0.4);
}

.table-card {
  user-select: none;
  transition: all 0.25s ease;
  border: 2px solid transparent;
  border-radius: 12px;
  padding: 12px;
}

.table-card.table-free {
  background: #fff;
  border-color: #ddd;
  color: #333;
  cursor: pointer;
}

.table-card.table-free:hover {
  background: #e1ebfa;
  border-color: #20451F;
  box-shadow: 0 6px 16px rgb(32 69 31 / 0.25);
}

.table-card.table-selected {
  background: #d0defe;
  color: #20451F;
  border-color: #20451F;
  box-shadow: 0 6px 18px rgb(32 69 31 / 0.5);
  cursor: pointer;
  transform: scale(1.04);
  z-index: 10;
}

.table-card.table-occupied {
  background: #f2f2f2;
  color: #999;
  cursor: not-allowed;
}

.legend-box.free {
  display: inline-block;
  width: 20px;
  height: 20px;
  background: #fff;
  border: 2px solid #b0c4de;
  border-radius: 6px;
}

.legend-box.selected {
  display: inline-block;
  width: 20px;
  height: 20px;
  background: #d0defe;
  border: 2px solid #20451F;
  border-radius: 6px;
}

.legend-box.occupied {
  display: inline-block;
  width: 20px;
  height: 20px;
  background: #f2f2f2;
  border: 2px solid #ccc;
  border-radius: 6px;
}

.form-check {
  border-color: #ddd;
  border-radius: 12px;
  border-style: solid;
  border-width: 1.5px;
  background: #fafafa;
  transition: background-color 0.3s ease, border-color 0.3s ease;
}

.form-check-input:checked {
  background-color: #20451F;
  border-color: #20451F;
  margin-top: 0;
}

.text-primary {
  color: #20451F !important;
}

.text-warning {
  color: #20451F !important;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.btn-momo {
  background-color: #F8579B;
  color: #fff;
  border: none;
  font-weight: 600;
  padding: 12px 20px;
  border-radius: 8px;
  transition: background-color 0.3s ease;
}

.btn-custom {
  background-color: #20451F;
  color: #fff;
  border: none;
  font-weight: 600;
  padding: 6px 12px;
  border-radius: 8px;
  transition: background-color 0.3s ease;
}

.extra-item {
  transition: background-color 0.2s ease;
}

.extra-item:hover {
  background-color: #f9f9f9;
}

.extra-item input[type="checkbox"] {
  cursor: pointer;
  width: 18px;
  height: 18px;
}

.quantity-input {
  width: 60px;
  padding: 2px 6px;
  font-size: 0.9rem;
  border-radius: 4px;
  border: 1px solid #ced4da;
  text-align: center;
}

.cursor-pointer {
  cursor: pointer;
}

@media (max-width: 480px) {
  .step-content {
    padding: 0;
  }

  .extra-item {
    padding: 6px 10px;
    font-size: 0.85rem;
    flex-wrap: nowrap;
    overflow-x: auto;
  }

  .extra-item>div.d-flex.align-items-center.flex-grow-1.cursor-pointer {
    flex: 1 1 auto;
    min-width: 120px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .extra-item input[type="checkbox"] {
    width: 16px;
    height: 16px;
  }

  .quantity-input {
    width: 50px;
    font-size: 0.85rem;
    padding: 2px 4px;
    min-width: 50px;
  }

  .extra-item .text-warning {
    font-size: 0.85rem;
    white-space: nowrap;
  }
}

.navbar.sticky-top {
  position: sticky;
  top: 0;
  z-index: 1000;
}

/* Menu desktop */
.nav-link {
  color: #20451F !important;
  font-weight: 600;
  transition: color 0.2s ease;
}

.nav-link:hover {
  color: #183317 !important;
}

.mobile-menu-wrapper {
  position: fixed;
  top: 15px;
  right: 15px;
  z-index: 1200;
}

.hamburger-btn {
  display: flex;
  flex-direction: column;
  justify-content: space-around;
  width: 30px;
  height: 25px;
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 0;
}

.hamburger-btn .bar {
  width: 100%;
  height: 3px;
  background-color: #20451F;
  border-radius: 5px;
  transition: all 0.3s ease-in-out;
}

.mobile-menu {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  width: 100%;
  background-color: #fff;
  overflow-y: auto;
  transform: translateY(-100%);
  transition: transform 0.3s ease;
  z-index: 1099;
  padding-top: 40px;
}

.mobile-menu.is-open {
  transform: translateY(0);
}

.mobile-menu .close-btn {
  position: absolute;
  top: 15px;
  right: 15px;
}

.menu-item {
  display: block;
  padding: 16px 20px;
  text-decoration: none;
  color: #555;
  border-bottom: 1px solid #eee;
  transition: background-color 0.2s ease;
  font-size: 1rem;
  font-weight: 500;
}

.menu-item:last-child {
  border-bottom: none;
}

.menu-item:hover {
  background-color: #f5f5f5;
  color: #20451F;
}

@media (min-width: 1025px) {

  .mobile-menu-wrapper,
  .mobile-menu {
    display: none !important;
  }
}

@media (min-width: 1025px) {
  .booking-root {
    padding-top: 120px;
  }
}

.back-btn {
  position: absolute;
  top: 30px;
  left: 25px;
  background: #fff;
  border: 2px solid #20451F;
  border-radius: 50%;
  width: 38px;
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
  cursor: pointer;
  transition: all 0.25s ease;
  color: #20451F;
}

.back-btn:hover {
  background: #20451F;
  color: #fff;
  transform: scale(1.05);
}
</style>

<script setup>
import { onMounted, ref, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useBooking } from '../composables/useBooking';
import { toast } from 'vue3-toastify';
import axios from 'axios';

const router = useRouter();
const {
  packages,
  selectedPackage,
  selectPackage,
  fetchPackages,
  tables,
  selectedTable,
  selectedTableId,
  selectTable,
  fetchTables,
  filteredTables,
  services,
  form,
  fetchServices,
  toggleExtra,
  updateQuantity,
  formatVND,
  formatCategoryName,
  start_time,
  end_time,
  total,
  param
} = useBooking();

// State cho Categories của Đồ uống
const activeServiceCategory = ref('all');
const showTimePopup = ref(false);
const localStartTime = ref('');
const localEndTime = ref('');
const minStartTime = ref('');
const tempSelectedTable = ref(null);

onMounted(async () => {
  await Promise.all([
    fetchPackages(),
    fetchTables(),
    fetchServices()
  ]);
  
  // Initialize times if not set
  if (!start_time.value) {
    const now = new Date();
    start_time.value = formatVietnamDatetime(now);
  }
});

function formatVietnamDatetime(date) {
  const pad = n => String(n).padStart(2, '0');
  const yyyy = date.getFullYear();
  const MM = pad(date.getMonth() + 1);
  const dd = pad(date.getDate());
  const hh = pad(date.getHours());
  const mm = pad(date.getMinutes());
  return `${yyyy}-${MM}-${dd}T${hh}:${mm}`;
}

const formatDateTimeLocal = (date) => {
  const pad = (n) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

function selectPackageWithBonus(pkg) {
  selectPackage(pkg);
  
  let freeDrinks = 0;
  if (pkg.category === 'basic') {
    freeDrinks = 1; // Mặc định các gói cơ bản thường có 1 nước miễn phí như trong ảnh
  } else if (pkg.category === 'vip') {
    // Logic cho gói VIP/Phòng họp
    if (pkg.name.includes('1 giờ')) freeDrinks = 3;
    else if (pkg.name.includes('2 giờ')) freeDrinks = 5;
    else if (pkg.name.includes('Nửa ngày')) freeDrinks = 7;
    else if (pkg.name.includes('Cả ngày')) freeDrinks = 9;
  }
  
  sessionStorage.setItem('freeDrinks', freeDrinks);
  
  // Update end time based on package duration
  if (start_time.value) {
    const start = new Date(start_time.value);
    const end = new Date(start.getTime() + pkg.duration * 60000);
    end_time.value = formatVietnamDatetime(end);
  }
}

function openTimePopup(table) {
  // Kiểm tra nếu chưa chọn package
  if (!selectedPackage.value) {
    toast.warning('Vui lòng chọn gói làm việc trước');
    // Scroll to packages
    document.getElementById('section-packages').scrollIntoView({ behavior: 'smooth' });
    return;
  }

  tempSelectedTable.value = table;
  const now = new Date();
  minStartTime.value = formatDateTimeLocal(now);

  const duration = selectedPackage.value?.duration || 60;
  localStartTime.value = start_time.value || formatDateTimeLocal(now);
  const start = new Date(localStartTime.value);
  localEndTime.value = formatDateTimeLocal(new Date(start.getTime() + duration * 60000));

  showTimePopup.value = true;
}

async function applyTimeSelection() {
  try {
    const tableId = tempSelectedTable.value.id;
    const data = {
      table_id: tableId,
      start_time: localStartTime.value,
      end_time: localEndTime.value,
      mode_booking: selectedPackage.value.category === 'basic' ? 'seat' : 'room'
    };
    
    const res = await axios.post('/api/check-table', data);

    if (res.data.success) {
      selectTable(tempSelectedTable.value);
      selectedTableId.value = tempSelectedTable.value.id;
      start_time.value = localStartTime.value;
      end_time.value = localEndTime.value;
      showTimePopup.value = false;
      toast.success('Đã chọn vị trí ' + tempSelectedTable.value.code);
    } else {
      toast.error(res.data.message || 'Vị trí này đã được đặt trong khung giờ đã chọn.');
    }
  } catch (err) {
    toast.error('Lỗi khi kiểm tra vị trí.');
  }
}

watch(localStartTime, (newVal) => {
  if (newVal && selectedPackage.value) {
    const duration = selectedPackage.value.duration || 60;
    const start = new Date(newVal);
    localEndTime.value = formatDateTimeLocal(new Date(start.getTime() + duration * 60000));
  }
});

function isTableLocked(category) {
  if (!selectedPackage.value) return false;
  
  const pkgName = selectedPackage.value.name.toLowerCase();
  
  // Phòng Họp yêu cầu Nửa ngày trở lên
  if (category === 'meeting_room') {
    return !(pkgName.includes('nửa ngày') || pkgName.includes('cả ngày'));
  }
  
  // Phòng VIP yêu cầu Cả ngày
  if (category === 'vip_room') {
    return !pkgName.includes('cả ngày');
  }
  
  return false;
}

function handleTableClick(table, category) {
  if (isTableLocked(category)) {
    let msg = 'Vị trí này yêu cầu gói cao hơn';
    if (category === 'meeting_room') msg = 'Phòng họp yêu cầu gói Nửa ngày trở lên';
    if (category === 'vip_room') msg = 'Phòng VIP yêu cầu gói Cả ngày';
    toast.info(msg);
    return;
  }
  openTimePopup(table);
}

const displayServices = computed(() => {
  if (activeServiceCategory.value === 'all') {
    return services;
  }
  const filtered = {};
  if (services[activeServiceCategory.value]) {
    filtered[activeServiceCategory.value] = services[activeServiceCategory.value];
  }
  return filtered;
});

const serviceCategories = computed(() => {
  return ['all', ...Object.keys(services)];
});

function getFreeDrinksCount() {
  return Number(sessionStorage.getItem('freeDrinks')) || 0;
}

function goSummary() {
  if (!selectedPackage.value) {
    toast.error('Vui lòng chọn gói làm việc');
    return;
  }
  if (!selectedTable.value) {
    toast.error('Vui lòng chọn vị trí ngồi');
    return;
  }
  router.push('/summary');
}

// Gộp nhóm Indoor và Outdoor thành "Khu mở"
const displayTables = computed(() => {
  const allTables = filteredTables.value;
  const result = {};
  
  // Gộp indoor và outdoor
  const openAreaTables = [
    ...(allTables.indoor || []),
    ...(allTables.outdoor || []),
    ...(allTables.private || [])
  ];
  
  if (openAreaTables.length > 0) {
    result['open_area'] = openAreaTables;
  }
  
  // Các nhóm còn lại giữ nguyên
  Object.keys(allTables).forEach(cat => {
    if (cat !== 'indoor' && cat !== 'outdoor' && cat !== 'private') {
      result[cat] = allTables[cat];
    }
  });
  
  return result;
});
</script>

<template>
  <div class="booking-flow">
    <!-- Header -->
    <header class="booking-header sticky-top py-3">
      <div class="container d-flex align-items-center px-4">
        <button class="header-back-btn me-4" @click="router.push('/')">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-chevron-left" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
          </svg>
        </button>
        <div class="header-brand">
          <img src="../../images/logo.png" alt="logo" class="header-logo" />
        </div>
      </div>
    </header>

    <div class="container pb-5">
      <!-- SECTION 1: GÓI LÀM VIỆC -->
      <section id="section-packages" class="card section-card mb-4">
        <div class="card-body p-4">
          <div class="section-header mb-4">
            <h3 class="section-title">GÓI LÀM VIỆC</h3>
          </div>
          
          <div class="row g-3">
            <template v-for="(pkgList, category) in packages" :key="category">
              <div 
                v-for="pkg in pkgList" 
                :key="pkg.id" 
                class="col-12 col-md-6"
              >
                <div 
                  class="card package-card-item h-100" 
                  :class="{ 'active': selectedPackage?.id === pkg.id }"
                  @click="selectPackageWithBonus(pkg)"
                >
                  <div v-if="pkg.name.includes('2 giờ')" class="badge-popular">PHỔ BIẾN</div>
                  <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                      <div>
                        <h4 class="pkg-name mb-1">{{ pkg.name }}</h4>
                        <div class="pkg-price">{{ formatVND(pkg.price) }}</div>
                      </div>
                    </div>
                    <div class="pkg-features">
                      <div v-if="pkg.name.includes('1 giờ')">1 đồ uống · 3 trang in</div>
                      <div v-else-if="pkg.name.includes('2 giờ')">1 đồ uống · 5 trang in</div>
                      <div v-else-if="pkg.name.includes('Nửa ngày')">Bánh nhẹ · 1 đồ uống · Pantry</div>
                      <div v-else-if="pkg.name.includes('Cả ngày')">Bánh nhẹ · 1 đồ uống · Pantry · Nơi riêng</div>
                      <div v-else>{{ pkg.durationLabel }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>
      </section>

      <!-- SECTION 2: VỊ TRÍ NGỒI -->
      <section id="section-tables" class="card section-card mb-4">
        <div class="card-body p-4">
          <div class="section-header mb-4">
            <h3 class="section-title">VỊ TRÍ NGỒI</h3>
          </div>

          <div v-for="(groupTables, category) in displayTables" :key="category" class="mb-4">
            <div class="d-flex align-items-center mb-3 gap-2">
              <h5 class="area-name mb-0">{{ formatCategoryName(category) }}</h5>
              <span v-if="category === 'meeting_room'" class="badge-lock">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="bi bi-lock-fill" viewBox="0 0 16 16"><path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/></svg>
                Nửa ngày+
              </span>
              <span v-if="category === 'vip_room'" class="badge-lock">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="bi bi-lock-fill" viewBox="0 0 16 16"><path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/></svg>
                Cả ngày
              </span>
            </div>

            <div class="seat-grid">
              <div 
                v-for="t in groupTables" 
                :key="t.code" 
                class="seat-item"
                :class="{ 
                  'active': selectedTable === t.code, 
                  'booked': t.booked_seats >= t.total_seating,
                  'locked': isTableLocked(category)
                }"
                @click="handleTableClick(t, category)"
              >
                {{ t.code }}
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- SECTION 3: MENU ĐỒ UỐNG -->
      <section id="section-extras" class="card section-card mb-4">
        <div class="card-body p-4">
          <div class="section-header d-flex justify-content-between align-items-center mb-4">
            <h3 class="section-title">MENU ĐỒ UỐNG</h3>
            <div v-if="getFreeDrinksCount() > 0" class="free-badge">
              {{ getFreeDrinksCount() }} ly miễn phí ✦
            </div>
          </div>

          <!-- Categories Filter -->
          <div class="categories-filter d-flex gap-2 overflow-auto pb-3 mb-4">
            <button 
              v-for="cat in serviceCategories" 
              :key="cat"
              class="btn btn-filter" 
              :class="{ 'active': activeServiceCategory === cat }"
              @click="activeServiceCategory = cat"
            >
              <span v-if="cat === 'all'">✦ Tất cả</span>
              <span v-else-if="cat === 'coffee'">Cà Phê</span>
              <span v-else-if="cat === 'juice_tea'">Trà</span>
              <span v-else-if="cat === 'coffee_machine_arabica'">Cà Phê Máy</span>
              <span v-else-if="cat === 'juice'">Nước Ép</span>
              <span v-else>{{ formatCategoryName(cat) }}</span>
            </button>
          </div>

          <div class="row g-3">
            <template v-for="(items, category) in displayServices" :key="category">
              <div 
                v-for="item in items" 
                :key="item.id" 
                class="col-6 col-md-3"
              >
                <div 
                  class="card drink-card h-100"
                  :class="{ 'selected': form[category]?.some(e => e.id === item.id) }"
                  @click="toggleExtra(item, category)"
                >
                  <div v-if="item.tags && item.tags.length > 0" class="drink-tags-container">
                    <span v-for="tag in item.tags" :key="tag" class="drink-badge" :class="tag.toLowerCase().replace(/\s+/g, '-')">
                      {{ tag }}
                    </span>
                  </div>
                  
                  <div class="drink-img-wrapper">
                    <img v-if="item.image" :src="item.image" class="card-img-top drink-img" alt="drink">
                    <div v-else class="drink-img-placeholder">
                      <span v-if="category.includes('coffee')">☕️</span>
                      <span v-else-if="category.includes('tea')">🍵</span>
                      <span v-else>🍹</span>
                    </div>
                  </div>
                  
                  <div class="card-body p-2">
                    <h5 class="drink-name mb-2">{{ item.name }}</h5>
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-1">
                      <span class="drink-price">{{ formatVND(item.price) }}</span>
                      <div v-if="form[category]?.some(e => e.id === item.id)" class="quantity-control" @click.stop>
                        <button class="btn-qty" @click="updateQuantity(category, item.id, (form[category].find(e => e.id === item.id).quantity || 1) - 1)">-</button>
                        <span class="qty-val">{{ form[category].find(e => e.id === item.id).quantity }}</span>
                        <button class="btn-qty" @click="updateQuantity(category, item.id, (form[category].find(e => e.id === item.id).quantity || 1) + 1)">+</button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>
      </section>
    </div>

    <!-- Bottom Action Bar -->
    <div class="bottom-bar shadow-lg">
      <div class="container h-100 px-4 py-2">
        <div class="d-flex justify-content-between align-items-center h-100">
          <div class="selection-info">
            <div class="text-dark fw-bold mb-0">
              <span v-if="selectedPackage">{{ selectedPackage.name }}</span>
              <span v-if="selectedTable"> · {{ selectedTable }} ☕️</span>
            </div>
            <div class="text-muted small">
              {{ start_time?.split('T')[1] }} ➞ {{ end_time?.split('T')[1] }}
            </div>
          </div>
          <div class="action-right d-flex flex-column align-items-end">
            <div class="total-amount fw-bold text-dark fs-4">{{ formatVND(total) }}</div>
            <div v-if="total > (selectedPackage?.price || 0)" class="text-danger small" style="margin-top: -5px;">+{{ formatVND(total - (selectedPackage?.price || 0)) }} đồ thêm</div>
          </div>
        </div>
        <button class="btn btn-confirm w-100 py-3 mt-2" @click="goSummary">
          Xác nhận đặt chỗ <span class="ms-2">→</span>
        </button>
      </div>
    </div>

    <!-- Popup chọn giờ (reused from TableSelect) -->
    <div v-if="showTimePopup" class="popup-backdrop">
      <div class="popup-content p-4 rounded shadow bg-white">
        <h5 class="mb-3 fw-bold">Xác nhận thời gian</h5>
        <p class="text-muted small mb-4">Bạn đang chọn vị trí {{ tempSelectedTable?.code }}</p>
        
        <div class="mb-3">
          <label class="form-label fw-semibold">Thời gian bắt đầu</label>
          <input type="datetime-local" v-model="localStartTime" class="form-control auth-input shadow-none" :min="minStartTime" />
        </div>
        
        <div class="mb-4">
          <label class="form-label fw-semibold">Thời gian kết thúc (Tự động)</label>
          <input type="datetime-local" v-model="localEndTime" class="form-control auth-input shadow-none bg-light" disabled />
          <small class="text-info mt-1 d-block">Dựa trên gói {{ selectedPackage?.durationLabel }}</small>
        </div>
        
        <div class="d-flex gap-3">
          <button class="btn btn-outline-secondary flex-grow-1 py-2" @click="showTimePopup = false">Hủy</button>
          <button class="btn btn-success flex-grow-1 py-2" @click="applyTimeSelection">Xác nhận</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

.booking-flow {
  font-family: 'Inter', sans-serif;
  padding-bottom: 160px;
  min-height: 100vh;
}

/* Header Styles */
.booking-header {
  z-index: 1010;
  background: rgba(255, 255, 255, 0.5);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
}

.header-back-btn {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  border: 1.5px solid #2D4F1E;
  background: white;
  color: #2D4F1E;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
}

.header-back-btn:hover {
  background: #2D4F1E;
  color: white;
}

.header-logo {
  height: 38px;
  width: auto;
}

/* Section Card Styles */
.section-card {
  border: none;
  border-radius: 24px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03) !important;
}

.section-title {
  font-weight: 800;
  font-size: 1.1rem;
  letter-spacing: 0.05em;
  color: #1a1a1a;
}

/* Package Cards */
.package-card-item {
  border: 1.5px solid #f0f0f0;
  border-radius: 16px;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.package-card-item:hover {
  border-color: #2D4F1E;
  background-color: #fcfdfc;
}

.package-card-item.active {
  border-color: #2D4F1E;
  background-color: #f8faf7;
  box-shadow: 0 8px 20px rgba(45, 79, 30, 0.08);
}

.pkg-name {
  font-weight: 700;
  font-size: 1.1rem;
  color: #1a1a1a;
}

.pkg-price {
  font-weight: 800;
  font-size: 1.05rem;
  color: #2D4F1E;
}

.pkg-features {
  font-size: 0.8rem;
  color: #777;
}

.badge-popular {
  position: absolute;
  top: 10px;
  right: -25px;
  background: #ff7e5f;
  color: white;
  padding: 3px 30px;
  font-size: 0.6rem;
  font-weight: 800;
  transform: rotate(45deg);
}

/* Seat Grid */
.area-name {
  font-weight: 700;
  font-size: 0.9rem;
  color: #444;
}

.badge-lock {
  font-size: 0.65rem;
  background: #f8f9fa;
  color: #aaa;
  padding: 4px 12px;
  border-radius: 20px;
  display: flex;
  align-items: center;
  gap: 4px;
  font-weight: 600;
  border: 1px solid #f0f0f0;
}

.seat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
  gap: 10px;
}

.seat-item {
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1.5px solid #f0f0f0;
  border-radius: 10px;
  font-weight: 700;
  font-size: 0.95rem;
  color: #1a1a1a;
  cursor: pointer;
  transition: all 0.2s ease;
  background: white;
}

.seat-item:hover:not(.locked):not(.booked) {
  border-color: #2D4F1E;
  color: #2D4F1E;
}

.seat-item.active {
  background: #2D4F1E;
  color: white !important;
  border-color: #2D4F1E;
  box-shadow: 0 4px 12px rgba(45, 79, 30, 0.2);
}

.seat-item.booked {
  background: #f5f5f5;
  color: #ddd !important;
  cursor: not-allowed;
  border-color: #eee;
}

.seat-item.locked {
  background: #fdfdfd;
  color: #eee !important;
  border-style: dashed;
  cursor: help;
}

/* Drinks Section */
.free-badge {
  background: #f8f1ff;
  color: #8e44ad;
  padding: 6px 15px;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 700;
}

.btn-filter {
  white-space: nowrap;
  background: #fff;
  border: 1.5px solid #f0f0f0;
  border-radius: 30px;
  padding: 8px 20px;
  font-weight: 600;
  font-size: 0.85rem;
  color: #666;
  transition: all 0.2s ease;
}

.btn-filter.active {
  background: #2D4F1E;
  color: white;
  border-color: #2D4F1E;
}

.drink-card {
  border: 1.5px solid #f0f0f0;
  border-radius: 20px;
  cursor: pointer;
  transition: all 0.3s ease;
  overflow: hidden;
  position: relative;
}

.drink-card.selected {
  border-color: #2D4F1E;
  background-color: #f8faf7;
}

.drink-img-wrapper {
  height: 120px;
  background: #fcfcfc;
  display: flex;
  align-items: center;
  justify-content: center;
}

.drink-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.drink-img-placeholder {
  font-size: 2.5rem;
}

.drink-card-body {
  padding: 12px;
}

.drink-name {
  font-weight: 700;
  font-size: 0.85rem;
  color: #1a1a1a;
  line-height: 1.3;
  height: 2.6em; /* Ensure consistent height for names */
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.drink-price {
  font-weight: 800;
  font-size: 0.85rem;
  color: #2D4F1E;
}

.drink-tags-container {
  position: absolute;
  top: 8px;
  left: 8px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  z-index: 5;
}

.drink-badge {
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 0.6rem;
  font-weight: 800;
  text-transform: uppercase;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Badge colors */
.drink-badge.best-seller { background: #ff7e5f; color: white; }
.drink-badge.new { background: #feb47b; color: white; }
.drink-badge.ưu-đãi { background: #43cea2; color: white; }
.drink-badge.giới-hạn { background: #185a9d; color: white; }
.drink-badge { background: #1a3a1b; color: white; } /* Default */

.quantity-control {
  display: flex;
  align-items: center;
  background: #f0f4f0;
  border-radius: 10px;
  padding: 2px 4px;
  margin-top: 4px;
}

.btn-qty {
  border: none;
  background: none;
  font-weight: 800;
  font-size: 1rem;
  color: #2D4F1E;
  padding: 0 5px;
}

.qty-val {
  font-weight: 700;
  font-size: 0.8rem;
  color: #2D4F1E;
  min-width: 12px;
  text-align: center;
}

/* Bottom Bar */
.bottom-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  z-index: 1000;
  border-top: 1px solid rgba(0, 0, 0, 0.05);
  padding: 15px 0;
}

.selection-info .text-dark {
  font-size: 1.05rem;
}

.total-amount {
  color: #1a1a1a;
}

.btn-confirm {
  background: #2D4F1E;
  color: white;
  border-radius: 12px;
  font-weight: 700;
  font-size: 1.1rem;
  border: none;
  transition: all 0.3s ease;
  box-shadow: 0 8px 20px rgba(45, 79, 30, 0.2);
}

.btn-confirm:hover {
  background: #1f3815;
  transform: translateY(-2px);
}

/* Popup */
.popup-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: 20px;
}

.popup-content {
  width: 100%;
  max-width: 400px;
  background: white;
}

.auth-input {
  border: 2px solid #f0f0f0;
  border-radius: 12px;
  padding: 12px;
}

@media (max-width: 768px) {
  .seat-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}
</style>

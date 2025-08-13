<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useBooking } from '../composables/useBooking';

const router = useRouter();
const {
  selectedTable,
  selectTable,
  fetchTables,
  formatCategoryName,
  filteredTables,
  selectedPackage,
  start_time,
  end_time
} = useBooking();

// Popup chọn giờ
const showTimePopup = ref(false);
const localStartTime = ref('');
const localEndTime = ref('');
const minStartTime = ref('');

// Format datetime-local
const formatDateTimeLocal = (date) => {
  const pad = (n) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

// Load tables + restore session
onMounted(async () => {
  await fetchTables();
  const savedStart = sessionStorage.getItem('start_time');
  const savedEnd = sessionStorage.getItem('end_time');
  const savedTable = sessionStorage.getItem('selectedTable');

  if (savedStart) start_time.value = savedStart;
  if (savedEnd) end_time.value = savedEnd;
  if (savedTable) selectedTable.value = savedTable;
});

// Watch localStartTime để tính localEndTime
watch(localStartTime, (val) => {
  if (!val) return;
  const duration = selectedPackage.value?.duration || 60;
  const start = new Date(val);
  localEndTime.value = formatDateTimeLocal(new Date(start.getTime() + duration * 60000));
});

function openTimePopup(table) {
  selectTable(table);
  const now = new Date();
  minStartTime.value = formatDateTimeLocal(now);

  const duration = selectedPackage.value?.duration || 60;
  localStartTime.value = formatDateTimeLocal(now);
  localEndTime.value = formatDateTimeLocal(new Date(now.getTime() + duration * 60000));

  showTimePopup.value = true;
}

function applyTimeSelection() {
  start_time.value = localStartTime.value;
  end_time.value = localEndTime.value;
  showTimePopup.value = false;
}

function goNext() {
  if (!selectedTable.value) return;

  if (!start_time.value || !end_time.value) {
    const now = new Date();
    const duration = selectedPackage.value?.duration || 60;
    start_time.value = formatDateTimeLocal(now);
    end_time.value = formatDateTimeLocal(new Date(now.getTime() + duration * 60000));
  }

  router.push('/extras');
}

function goBack() {
  router.back();
}
</script>

<template>
  <div>
    <!-- Danh sách bàn -->
    <div v-for="(groupTables, category) in filteredTables" :key="category" class="mb-4">
      <h5 class="mb-3 fw-semibold">{{ formatCategoryName(category) }}</h5>
      <div class="row row-cols-2 row-cols-md-4 g-3">
        <div v-for="t in groupTables" :key="t.code" class="col">
          <div
            @click="t.status !== 'occupied' && openTimePopup(t)"
            :class="[
              'table-card p-3 text-center rounded shadow-sm',
              {
                'table-free': t.status === 'free',
                'table-occupied': t.status === 'occupied',
                'table-selected': selectedTable === t.code,
                'cursor-pointer': t.status !== 'occupied',
              },
            ]"
          >
            <div class="fw-bold fs-5">{{ t.code }}</div>
            <div class="mt-1">
              <small v-if="t.status === 'occupied'" class="text-danger fw-semibold">Đã đặt</small>
              <small v-else-if="selectedTable === t.code" class="text-success fw-semibold">Đang chọn</small>
              <small v-else class="text-muted">Trống</small>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Legend -->
    <div class="d-flex justify-content-center gap-5 mt-3 flex-wrap" style="font-size: 0.875rem">
      <div class="d-flex aligns-item-center gap-2">
        <span class="legend-box free"></span> Trống
      </div>
      <div class="d-flex aligns-item-center gap-2">
        <span class="legend-box selected"></span> Đang chọn
      </div>
      <div class="d-flex aligns-item-center gap-2">
        <span class="legend-box occupied"></span> Đã đặt
      </div>
    </div>

    <!-- Nút điều hướng -->
    <div class="d-flex justify-content-between mt-4 gap-3">
      <button class="btn btn-outline-secondary flex-grow-1" @click="goBack">Quay lại</button>
      <button class="btn btn-warning flex-grow-1" :disabled="!selectedTable" @click="goNext">Tiếp tục</button>
    </div>

    <!-- Popup chọn giờ -->
    <div v-if="showTimePopup" class="popup-backdrop">
      <div class="popup-content p-4 rounded shadow bg-white" style="max-width: 400px; margin: auto;">
        <h5 class="mb-3">Chọn thời gian</h5>

        <div class="mb-3">
          <label class="form-label">Bắt đầu</label>
          <input
            type="datetime-local"
            v-model="localStartTime"
            class="form-control"
            :min="minStartTime"
          />
        </div>

        <div class="mb-3">
          <label class="form-label">Kết thúc</label>
          <input type="datetime-local" v-model="localEndTime" class="form-control" disabled />
        </div>

        <div class="d-flex justify-content-end gap-2">
          <button class="btn btn-outline-secondary" @click="showTimePopup = false">Hủy</button>
          <button class="btn btn-primary" @click="applyTimeSelection">Xác nhận</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.popup-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
}
.table-card {
  transition: all 0.2s ease;
}
.table-card.table-free:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.table-selected {
  border: 2px solid #28a745;
}
.legend-box {
  width: 16px;
  height: 16px;
  display: inline-block;
  border-radius: 3px;
}
.legend-box.free { background: #ccc; }
.legend-box.selected { background: #28a745; }
.legend-box.occupied { background: #dc3545; }
.popup-content {
  width: 90%;          /* chiếm 90% chiều ngang màn hình */
  max-width: 400px;    /* không vượt quá 400px trên màn hình lớn */
  margin: auto;
  padding: 1.5rem;
  border-radius: 0.5rem;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  background: white;
  max-height: 90vh;    /* không vượt quá 90% chiều cao viewport */
  overflow-y: auto;    /* nếu nội dung cao hơn sẽ scroll bên trong */
}
@media (max-width: 480px) {
  .popup-content {
    padding: 1rem;
  }
  .popup-content h5 {
    font-size: 1.1rem;
  }
  .popup-content .form-control {
    font-size: 0.9rem;
  }
}

</style>

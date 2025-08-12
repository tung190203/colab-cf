<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useBooking } from '../composables/useBooking';

const router = useRouter();
const {
  tables,
  selectedTable,
  selectTable,
  fetchTables,
  formatCategoryName,
} = useBooking();

onMounted(async() => {
  await fetchTables();
});

function goNext() {
  if (!selectedTable.value) return;
  router.push('/extras');
}
function goBack() {
  router.back();
}
</script>

<template>
  <div>
    <div v-for="(groupTables, category) in tables" :key="category" class="mb-4">
      <h5 class="mb-3 fw-semibold">{{ formatCategoryName(category) }}</h5>
      <div class="row row-cols-2 row-cols-md-4 g-3">
        <div v-for="t in groupTables" :key="t.code" class="col">
          <div
            @click="selectTable(t)"
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

    <div class="d-flex justify-content-between mt-4 gap-3">
      <button class="btn btn-outline-secondary flex-grow-1" @click="goBack">Quay lại</button>
      <button class="btn btn-warning flex-grow-1" :disabled="!selectedTable" @click="goNext">Tiếp tục</button>
    </div>
  </div>
</template>

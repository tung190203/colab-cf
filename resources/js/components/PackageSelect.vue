<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useBooking } from '../composables/useBooking';

const router = useRouter();
const {
  packages,
  selectedPackage,
  selectPackage,
  fetchPackages,
  fetchTables,
    formatVND,
    selectTableFromUrl,
    selectedTable,
} = useBooking();

onMounted(async() => {
  await fetchPackages();
  await fetchTables();
  selectTableFromUrl();
});

function goNext() {
  if (!selectedPackage.value) return;
  const savedTable = JSON.parse(localStorage.getItem('selectedTable'));
  if (savedTable) {
    selectedTable.value = savedTable;
    router.push('/extras');
  } else {
    router.push('/table');
  }
}
</script>

<template>
  <div class="step-content">
    <div class="row g-3">
      <div
        v-for="pkg in packages"
        :key="pkg.id"
        class="col-12 p-3 package-card cursor-pointer"
        :class="{ 'package-card-selected': selectedPackage && selectedPackage.id === pkg.id }"
        @click="selectPackage(pkg)"
      >
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-1 fw-semibold">{{ pkg.name }}</h5>
            <small class="text-muted !fs-6">{{ pkg.durationLabel }}</small>
          </div>
          <div class="fw-bold text-warning fs-5">{{ formatVND(pkg.price) }}</div>
        </div>
        <div class="fw-light" style="font-size: 12px;">{{ pkg.duration_label }}</div>
      </div>
    </div>
    <button class="btn btn-warning w-100 mt-4" :disabled="!selectedPackage" @click="goNext">
      Tiếp tục
    </button>
  </div>
</template>

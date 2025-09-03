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
  formatVND,
  selectedTable,
  formatCategoryName,
  param,
  start_time,
  end_time,
} = useBooking();

function formatVietnamDatetime(date) {
  const pad = n => String(n).padStart(2,'0');
  const yyyy = date.getFullYear();
  const MM = pad(date.getMonth() + 1);
  const dd = pad(date.getDate());
  const hh = pad(date.getHours());
  const mm = pad(date.getMinutes());
  return `${yyyy}-${MM}-${dd}T${hh}:${mm}`;
}

function setDefaultTimes(duration = null) {
  const nowVN = new Date();
  start_time.value = formatVietnamDatetime(nowVN);

  const durationMinutes = duration ?? selectedPackage.value?.duration ?? 60;
  const end = new Date(nowVN.getTime() + durationMinutes * 60000);
  end_time.value = formatVietnamDatetime(end);
}

function selectPackageWithBonus(pkg) {
  selectPackage(pkg);

  let freeDrinks = 0;

  if (pkg.name.includes('1 giờ') && pkg.category === 'basic') {
    freeDrinks = 1;
  } else if (pkg.name.includes('2 giờ') && pkg.category === 'basic') {
    freeDrinks = 1;
  } else if (pkg.name.includes('Nửa ngày') && pkg.category === 'basic') {
    freeDrinks = 1;
  } else if (pkg.name.includes('Cả ngày') && pkg.category === 'basic') {
    freeDrinks = 1;
  } else if (pkg.name.includes('1 giờ') && pkg.category === 'vip') {
    freeDrinks = 3;
  } else if (pkg.name.includes('2 giờ') && pkg.category === 'vip') {
    freeDrinks = 5;
  } else if (pkg.name.includes('Nửa ngày') && pkg.category === 'vip') {
    freeDrinks = 7;
  } else if (pkg.name.includes('Cả ngày') && pkg.category === 'vip') {
    freeDrinks = 9;
  }

  // Lưu vào sessionStorage
  sessionStorage.setItem('freeDrinks', freeDrinks);
}

onMounted(async () => {
  await fetchPackages();
});

function goNext() {
  if (!selectedPackage.value) return;
  if(selectedPackage.value?.category == 'ship') {
    setDefaultTimes(1);
    router.push('/extras');
    return;
  }

  const tableParam = param.get('table');
  if (tableParam) {
    selectedTable.value = tableParam;
    setDefaultTimes();
  }
  router.push('/table');
}
</script>

<template>
  <div class="step-content">
    <div v-for="(pkgList, category) in packages" :key="category" class="mb-4">
      <h4 class="text-capitalize mb-2">{{ formatCategoryName(category) }}</h4>
      
      <div class="row g-3">
        <div
          v-for="pkg in pkgList"
          :key="pkg.id"
          class="col-12 p-3 package-card cursor-pointer"
          :class="{ 'package-card-selected': selectedPackage && selectedPackage.id === pkg.id }"
          @click="selectPackageWithBonus(pkg)"
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
    </div>

    <button class="btn btn-warning w-100 mt-4" :disabled="!selectedPackage" @click="goNext">
      Tiếp tục
    </button>
  </div>
</template>

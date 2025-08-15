<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useBooking } from '../composables/useBooking';

const router = useRouter();
const {
  services,
  form,
  toggleExtra,
  updateQuantity,
  fetchServices,
  formatCategoryName,
  formatVND,
} = useBooking();

const openCategories = ref({});

onMounted(async () => {
  await fetchServices();
});

watch(services, (newServices) => {
  const keys = Object.keys(newServices);
  if (keys.length > 0) {
    keys.forEach((category, index) => {
      openCategories.value[category] = index === 0;
    });
  }
}, { immediate: true });

function canEditQuantity(category) {
  const noQuantityCategories = ['meeting_room', 'vip_room'];
  return !noQuantityCategories.includes(category);
}

function toggleCategory(category) {
  openCategories.value[category] = !openCategories.value[category];
}

function goNext() {
  router.push('/summary');
}

function goBack() {
  router.back();
}
</script>

<template>
  <div>
    <div class="fs-6 mb-4 text-danger">Dịch vụ thêm, không bao gồm gói đã chọn.</div>
    <div v-for="(items, category) in services" :key="category" class="mb-4">
      <h5 class="mb-3 fw-semibold cursor-pointer d-flex justify-content-between align-items-center"
        @click="toggleCategory(category)">
        {{ formatCategoryName(category) }}

        <svg v-if="openCategories[category]" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
          fill="currentColor" class="bi bi-chevron-up" viewBox="0 0 16 16">
          <path fill-rule="evenodd"
            d="M4.646 10.854a.5.5 0 0 0 .708 0L8 8.207l2.646 2.647a.5.5 0 0 0 .708-.708l-3-3a.5.5 0 0 0-.708 0l-3 3a.5.5 0 0 0 0 .708z" />
        </svg>

        <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
          class="bi bi-chevron-down" viewBox="0 0 16 16">
          <path fill-rule="evenodd"
            d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z" />
        </svg>
      </h5>
      <div v-show="openCategories[category]">
        <div v-for="item in items" :key="item.id"
          class="extra-item d-flex align-items-center justify-content-between p-2 mb-2 rounded border">
          <div class="d-flex align-items-center flex-grow-1 cursor-pointer" @click="toggleExtra(item, category)">
            <input type="checkbox" class="form-check-input me-2 mt-0" :id="category + '-' + item.id"
              :checked="form[category].some((e) => e.id === item.id)" readonly />
            <label class="mb-0" :for="category + '-' + item.id">{{ item.name }}</label>
          </div>

          <div class="d-flex align-items-center gap-2">
            <div class="text-warning fw-bold">{{ formatVND(item.price) }}</div>
            <input v-if="form[category].some((e) => e.id === item.id) && canEditQuantity(category)" type="number"
              min="1" class="form-control form-control-sm quantity-input"
              :value="form[category].find((e) => e.id === item.id).quantity"
              @input="(e) => updateQuantity(category, item.id, e.target.value)" />
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-between gap-3">
      <button class="btn btn-outline-secondary flex-grow-1" @click="goBack">Quay lại</button>
      <button class="btn btn-warning flex-grow-1" @click="goNext">Tiếp tục</button>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
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

onMounted(async() => {
  await fetchServices();
});

function canEditQuantity(category) {
  const noQuantityCategories = ['meeting_room', 'vip_room'];
  return !noQuantityCategories.includes(category);
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
    <div v-for="(items, category) in services" :key="category" class="mb-4">
      <h5 class="mb-3 fw-semibold">{{ formatCategoryName(category) }}</h5>
      <div>
        <div
          v-for="item in items"
          :key="item.id"
          class="extra-item d-flex align-items-center justify-content-between p-2 mb-2 rounded border"
        >
          <div class="d-flex align-items-center flex-grow-1 cursor-pointer" @click="toggleExtra(item, category)">
            <input
              type="checkbox"
              class="form-check-input me-2"
              :id="category + '-' + item.id"
              :checked="form[category].some((e) => e.id === item.id)"
              readonly
            />
            <label class="mb-0" :for="category + '-' + item.id">{{ item.name }}</label>
          </div>

          <div class="d-flex align-items-center gap-2">
            <div class="text-warning fw-bold">{{ formatVND(item.price) }}</div>
            <input
              v-if="form[category].some((e) => e.id === item.id) && canEditQuantity(category)"
              type="number"
              min="1"
              class="form-control form-control-sm quantity-input"
              :value="form[category].find((e) => e.id === item.id).quantity"
              @input="(e) => updateQuantity(category, item.id, e.target.value)"
            />
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

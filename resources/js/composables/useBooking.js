// composables/useBooking.js
import { ref, reactive, computed, watch } from 'vue';

const STORAGE_KEYS = {
  selectedPackage: 'booking_selectedPackage',
  selectedTable: 'booking_selectedTable',
  form: 'booking_form',
};

const packages = ref([]);
const tables = ref([]);

// Khôi phục từ localStorage hoặc mặc định ban đầu
const selectedPackage = ref(
  JSON.parse(localStorage.getItem(STORAGE_KEYS.selectedPackage)) || null
);

const selectedTable = ref(
  JSON.parse(localStorage.getItem(STORAGE_KEYS.selectedTable)) || null
);

const form = reactive(
  JSON.parse(localStorage.getItem(STORAGE_KEYS.form)) || {}
);

const services = reactive({});

const param = new URLSearchParams(window.location.search);

// Đảm bảo form có key mảng khi thay đổi services
watch(
  () => Object.keys(services),
  (keys) => {
    keys.forEach((k) => {
      if (!Array.isArray(form[k])) form[k] = [];
    });
  },
  { immediate: true }
);

// Đồng bộ thay đổi selectedPackage vào localStorage
watch(
  selectedPackage,
  (val) => {
    localStorage.setItem(STORAGE_KEYS.selectedPackage, JSON.stringify(val));
  },
  { deep: true }
);

// Đồng bộ thay đổi selectedTable vào localStorage
watch(
  selectedTable,
  (val) => {
    localStorage.setItem(STORAGE_KEYS.selectedTable, JSON.stringify(val));
  },
  { deep: true }
);

// Đồng bộ thay đổi form (extras) vào localStorage
watch(
  form,
  (val) => {
    localStorage.setItem(STORAGE_KEYS.form, JSON.stringify(val));
  },
  { deep: true }
);

const formatVND = (v) =>
  (v || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ' đ';

function selectPackage(p) {
  selectedPackage.value = p;
}

function selectTable(t) {
  if (t.status === 'occupied') return;
  selectedTable.value = t.code;
}

function toggleExtra(extra, category) {
  const list = form[category];
  const idx = list.findIndex((e) => e.id === extra.id);
  if (idx === -1) {
    list.push({ ...extra, quantity: 1 });
  } else {
    list.splice(idx, 1);
  }
}

function updateQuantity(category, id, value) {
  const list = form[category];
  const item = list.find((e) => e.id === id);
  if (item) {
    item.quantity = Math.max(1, Number(value) || 1);
  }
}

const extras = computed(() => {
  const list = [];
  for (const key in form) {
    if (Array.isArray(form[key])) {
      list.push(...form[key]);
    }
  }
  return list;
});

const total = computed(() => {
  let s = 0;
  if (selectedPackage.value) s += selectedPackage.value.price;
  extras.value.forEach((e) => (s += e.price * (e.quantity || 1)));
  return s;
});

const endTime = computed(() => {
  if (!selectedPackage.value) return '-';
  const d = new Date();
  d.setMinutes(d.getMinutes() + selectedPackage.value.duration);
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
});

function resetAll() {
  selectedPackage.value = null;
  selectedTable.value = null;
  Object.keys(form).forEach((key) => {
    if (Array.isArray(form[key])) {
      form[key] = [];
    }
  });
  // Xoá localStorage khi reset
  localStorage.removeItem(STORAGE_KEYS.selectedPackage);
  localStorage.removeItem(STORAGE_KEYS.selectedTable);
  localStorage.removeItem(STORAGE_KEYS.form);

  fetchPackages();
  fetchTables();
  fetchServices();
  selectTableFromUrl();
}

function formatCategoryName(key) {
  switch (key) {
    case 'coffee':
      return 'FINE ROBUSTA';
    case 'juice_tea':
      return 'JUICE TEA';
    case 'coffee_machine_arabica':
      return 'ARABICA';
    case 'coffee_machine_special':
      return 'SPECIAL';
    case 'juice':
      return 'JUICE';
    case 'matcha':
      return 'MATCHA';
    case 'desserts':
      return 'DESSERTS';
    case 'meeting_room':
      return 'PHÒNG HỌP';
    case 'vip_room':
      return 'PHÒNG VIP';
    case 'indoor':
      return 'Bàn trong nhà';
    case 'outdoor':
      return 'Bàn ngoài trời';
    case 'private':
      return 'Chỗ ngồi riêng tư';
    default:
      return 'Dịch vụ khác';
  }
}

async function fetchServices() {
  try {
    const res = await fetch('/api/extras');
    if (!res.ok) throw new Error('Failed to fetch services');
    const data = await res.json();
    Object.keys(services).forEach((k) => delete services[k]);
    Object.entries(data).forEach(([key, value]) => {
      services[key] = value || [];
      if (!Array.isArray(form[key])) form[key] = [];
    });
  } catch (error) {
    console.error('Error loading services:', error);
  }
}

async function fetchPackages() {
  try {
    const res = await fetch('/api/packages');
    if (!res.ok) throw new Error('Failed to fetch packages');
    const data = await res.json();
    packages.value = data.map((pkg) => ({
      ...pkg,
      durationLabel: `${pkg.duration} phút`,
      price: pkg.price || 0,
    }));
  } catch (error) {
    console.error('Error loading packages:', error);
  }
}

async function fetchTables() {
  try {
    const res = await fetch('/api/tables');
    if (!res.ok) throw new Error('Failed to fetch tables');
    const data = await res.json();
    tables.value = data;
  } catch (error) {
    console.error('Error loading tables:', error);
  }
}

function selectTableFromUrl() {
  const tq = param.get('table');
  if (!tq || tq.toLowerCase() === 'reception') return;
  const allTables = Object.values(tables.value).flat();

  const found = allTables.find((x) => x.code.toLowerCase() === tq.toLowerCase());
  if (found && found.status !== 'occupied') {
    selectedTable.value = found.code;
  } else {
    console.warn(`Table ${tq} không tồn tại hoặc đã bị đặt.`);
  }
}

export function useBooking() {
  return {
    packages,
    tables,
    selectedPackage,
    selectedTable,
    form,
    services,
    formatVND,
    selectPackage,
    selectTable,
    toggleExtra,
    updateQuantity,
    extras,
    total,
    endTime,
    resetAll,
    formatCategoryName,
    fetchServices,
    fetchPackages,
    fetchTables,
    selectTableFromUrl,
    param,
  };
}

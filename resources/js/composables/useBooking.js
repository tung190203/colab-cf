// composables/useBooking.js
import { ref, reactive, computed, watch } from 'vue';

const STORAGE_KEYS = {
  selectedPackage: 'booking_selectedPackage',
  selectedTable: 'booking_selectedTable',
  form: 'booking_form',
};

const START_TIME_KEY = 'booking_start_time';
const END_TIME_KEY = 'booking_end_time';
const packages = ref({});
const tables = ref([]);
const start_time = ref(null);
const end_time = ref(null);
const name = ref(null);
const phone = ref(null);

name.value = sessionStorage.getItem('customer_name') || null;
phone.value = sessionStorage.getItem('customer_phone') || null;

start_time.value = sessionStorage.getItem(START_TIME_KEY) || null;
end_time.value = sessionStorage.getItem(END_TIME_KEY) || null;

watch(start_time, (val) => {
  if (val) {
    sessionStorage.setItem(START_TIME_KEY, val);
  } else {
    sessionStorage.removeItem(START_TIME_KEY);
  }
});

watch(end_time, (val) => {
  if (val) {
    sessionStorage.setItem(END_TIME_KEY, val);
  } else {
    sessionStorage.removeItem(END_TIME_KEY);
  }
});

watch(name, (val) => {
  if (val) {
    sessionStorage.setItem('customer_name', val);
  } else {
    sessionStorage.removeItem('customer_name');
  }
});
watch(phone, (val) => {
  if (val) {
    sessionStorage.setItem('customer_phone', val);
  } else {
    sessionStorage.removeItem('customer_phone');
  }
}
);

// Khôi phục từ sessionStorage hoặc mặc định ban đầu
const selectedPackage = ref(
  JSON.parse(sessionStorage.getItem(STORAGE_KEYS.selectedPackage)) || null
);

const selectedTable = ref(
  JSON.parse(sessionStorage.getItem(STORAGE_KEYS.selectedTable)) || null
);

const form = reactive(
  JSON.parse(sessionStorage.getItem(STORAGE_KEYS.form)) || {}
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

// Đồng bộ thay đổi selectedPackage vào sessionStorage
watch(
  selectedPackage,
  (val) => {
    sessionStorage.setItem(STORAGE_KEYS.selectedPackage, JSON.stringify(val));
  },
  { deep: true }
);

// Đồng bộ thay đổi selectedTable vào sessionStorage
watch(
  selectedTable,
  (val) => {
    sessionStorage.setItem(STORAGE_KEYS.selectedTable, JSON.stringify(val));
  },
  { deep: true }
);

// Đồng bộ thay đổi form (extras) vào sessionStorage
watch(
  form,
  (val) => {
    sessionStorage.setItem(STORAGE_KEYS.form, JSON.stringify(val));
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
  start_time.value = null;
  end_time.value = null;
  name.value = null;
  phone.value = null;
  // Xoá sessionStorage khi reset
  sessionStorage.removeItem(STORAGE_KEYS.selectedPackage);
  sessionStorage.removeItem(STORAGE_KEYS.selectedTable);
  sessionStorage.removeItem(STORAGE_KEYS.form);
  sessionStorage.removeItem(START_TIME_KEY);
  sessionStorage.removeItem(END_TIME_KEY);

  fetchPackages();
  fetchTables();
  fetchServices();
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
    case 'basic':
      return 'Gói cơ bản';
    case 'vip':
      return 'Gói VIP';
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

async function fetchUserByPhone(phoneParam) {
  if( !phoneParam) return;
  try {
    const res = await fetch('/api/detail-user',{
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ phoneParam })
    });
    if (!res.ok) throw new Error('Không tìm thấy người dùng');
    const data = await res.json();
    name.value = data.user?.name ?? null;
    phone.value = data.user?.phone ?? null;
  } catch (error) {
    throw new Error(`Error fetching user by phone: ${error.message}`);
  }
}

async function fetchPackages() {
  try {
    const res = await fetch('/api/packages');
    if (!res.ok) throw new Error('Failed to fetch packages');
    const data = await res.json();
    Object.keys(packages.value).forEach((k) => delete packages.value[k]);
    const tableCode = param.get('table');
    let allowedCategory = null;
    if (tableCode) {
      const allTables = Object.entries(tables.value).flatMap(([cat, arr]) =>
        arr.map((t) => ({ ...t, category: cat }))
      );
      const foundTable = allTables.find(
        (t) => t.code.toLowerCase() === tableCode.toLowerCase()
      );

      if (foundTable) {
        if (['vip_room', 'meeting_room'].includes(foundTable.category)) {
          allowedCategory = 'vip';
        } else {
          allowedCategory = 'basic';
        }
      }
    }
    Object.entries(data).forEach(([category, list]) => {
      if (allowedCategory && category !== allowedCategory) return;
      packages.value[category] = list.map((pkg) => ({
        ...pkg,
        durationLabel: `${pkg.duration} phút`,
        price: pkg.price || 0,
      }));
    });
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

const filteredTables = computed(() => {
  if (!selectedPackage.value) return tables.value;

  const copy = JSON.parse(JSON.stringify(tables.value));

  if (selectedPackage.value.category === 'basic') {
    Object.keys(copy).forEach(cat => {
      if (['vip_room', 'meeting_room'].includes(cat)) {
        delete copy[cat];
      }
    });
  } else if (selectedPackage.value.category === 'vip') {
    Object.keys(copy).forEach(cat => {
      if (!['vip_room', 'meeting_room'].includes(cat)) {
        delete copy[cat];
      }
    });
  }

  return copy;
});

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
    filteredTables,
    start_time,
    end_time,
    name,
    phone,
    fetchUserByPhone
  };
}

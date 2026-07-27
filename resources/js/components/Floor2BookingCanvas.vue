<template>
  <div class="f2-wrapper">
    <!-- Header & Mode Selector -->
    <div class="f2-header">
      <h3 class="f2-title">Sơ đồ Tầng 2</h3>

      <!-- Mode selection toggle -->
      <div class="f2-mode-group">
        <button
          type="button"
          @click="setBookingMode('seat')"
          :class="['f2-mode-btn', { active: bookingMode === 'seat' }]"
        >
          Thuê chỗ (50k/chỗ/ngày)
        </button>
        <button
          type="button"
          @click="setBookingMode('room')"
          :class="['f2-mode-btn', { active: bookingMode === 'room' }]"
        >
          Thuê trọn phòng (300k/3h)
        </button>
      </div>
    </div>

    <!-- Minimalist Legend -->
    <div class="f2-legend-bar">
      <div class="d-flex align-items-center gap-4">
        <span class="d-flex align-items-center gap-1.5 font-medium">
          <span class="f2-dot f2-dot-active"></span> Ghế chọn
        </span>
        <span class="d-flex align-items-center gap-1.5 font-medium">
          <span class="f2-dot f2-dot-empty"></span> Ghế trống
        </span>
      </div>
      <div class="text-muted small">
        <template v-if="!selectedRoom">Nhấp chọn phòng để chọn bàn</template>
        <template v-else-if="bookingMode === 'seat'">Nhấp chọn các ghế 01, 02...</template>
        <template v-else>Thuê toàn bộ {{ selectedRoom.name }}</template>
      </div>
    </div>

    <!-- Clean White Wireframe Canvas Container -->
    <div ref="canvasWrapper" class="f2-canvas-container">
      <!-- Loading indicator -->
      <div v-if="loading" class="f2-loading-overlay">
        <svg class="spinner" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
        <span>Đang tải...</span>
      </div>

      <!-- Konva Stage Container -->
      <div class="f2-stage-inner">
        <v-stage
          ref="stage"
          :config="stageConfig"
        >
          <v-layer>
            <!-- Pure White Canvas Background -->
            <v-rect
              :config="{
                x: 0,
                y: 0,
                width: stageConfig.width,
                height: stageConfig.height,
                fill: '#ffffff',
                cornerRadius: 0
              }"
            />

            <!-- Subtle Light Grid Lines -->
            <v-group>
              <template v-for="x in gridX" :key="'gx-' + x">
                <v-line :config="{ points: [x, 0, x, stageConfig.height], stroke: '#f1f5f9', strokeWidth: 1 }" />
              </template>
              <template v-for="y in gridY" :key="'gy-' + y">
                <v-line :config="{ points: [0, y, stageConfig.width, y], stroke: '#f1f5f9', strokeWidth: 1 }" />
              </template>
            </v-group>

            <!-- Rooms Array (Wireframe Rectangles) -->
            <v-group
              v-for="room in rooms"
              :key="room.id"
              :config="{
                x: room.x,
                y: room.y,
              }"
            >
              <!-- Room Wireframe Boundary Box -->
              <v-rect
                :config="{
                  width: room.width,
                  height: room.height,
                  fill: isRoomDisabled(room.code) ? '#e2e8f0' : (selectedRoomCode === room.code ? '#e8f5e9' : '#ffffff'),
                  stroke: isRoomDisabled(room.code) ? '#94a3b8' : (selectedRoomCode === room.code ? '#2D4F1E' : '#1e293b'),
                  strokeWidth: selectedRoomCode === room.code ? 2 : 1,
                  cornerRadius: 0
                }"
                @click="!isRoomDisabled(room.code) && onRoomClick($event, room)"
                @tap="!isRoomDisabled(room.code) && onRoomClick($event, room)"
                @mouseenter="handleMouseEnter"
                @mouseleave="handleMouseLeave"
              />

              <!-- Room Name Text Header -->
              <v-text
                :config="{
                  x: 0,
                  y: 12,
                  width: room.width,
                  text: (room.name || room.code).toUpperCase(),
                  fontSize: 11,
                  fontStyle: 'bold',
                  fill: selectedRoomCode === room.code ? '#2D4F1E' : '#1e293b',
                  align: 'center'
                }"
                @click="onRoomClick($event, room)"
                @tap="onRoomClick($event, room)"
              />

              <!-- Center Table Top (Vertical rectangle for 8-seat rooms, Horizontal rectangle for Box kính) -->
              <v-rect
                v-if="room.code !== 'BOX'"
                :config="{
                  x: (room.width - 80) / 2,
                  y: 48,
                  width: 80,
                  height: 160,
                  fill: '#ffffff',
                  stroke: '#1e293b',
                  strokeWidth: 1.5,
                  cornerRadius: 0
                }"
                @click="onRoomClick($event, room)"
                @tap="onRoomClick($event, room)"
                @mouseenter="handleMouseEnter"
                @mouseleave="handleMouseLeave"
              />
              <v-rect
                v-else
                :config="{
                  x: (room.width - 90) / 2,
                  y: (room.height - 40) / 2,
                  width: 90,
                  height: 40,
                  fill: '#ffffff',
                  stroke: '#1e293b',
                  strokeWidth: 1.5,
                  cornerRadius: 0
                }"
                @click="onRoomClick($event, room)"
                @tap="onRoomClick($event, room)"
                @mouseenter="handleMouseEnter"
                @mouseleave="handleMouseLeave"
              />

              <!-- Table Label Text inside Table -->
              <v-text
                v-if="room.code !== 'BOX'"
                :config="{
                  x: (room.width - 80) / 2,
                  y: 122,
                  width: 80,
                  text: 'BÀN ' + room.code,
                  fontSize: 10,
                  fontStyle: 'bold',
                  fill: '#1e293b',
                  align: 'center'
                }"
                @click="onRoomClick($event, room)"
                @tap="onRoomClick($event, room)"
              />
              <v-text
                v-else
                :config="{
                  x: (room.width - 90) / 2,
                  y: (room.height - 40) / 2 + 14,
                  width: 90,
                  text: 'BOX KÍNH',
                  fontSize: 10,
                  fontStyle: 'bold',
                  fill: '#1e293b',
                  align: 'center'
                }"
                @click="onRoomClick($event, room)"
                @tap="onRoomClick($event, room)"
              />

              <!-- Chair Nodes (CIRCLES - v-circle) -->
              <template v-for="(seat, idx) in computeWireframeSeatNodes(room)" :key="'seat-' + idx">
                <v-group
                  :config="{
                    x: seat.x,
                    y: seat.y,
                    listening: true
                  }"
                  @click="onSeatClick($event, room, idx + 1)"
                  @tap="onSeatClick($event, room, idx + 1)"
                  @mouseenter="handleMouseEnter"
                  @mouseleave="handleMouseLeave"
                >
                  <!-- Circular Chair Node -->
                  <v-circle
                    :config="{
                      x: 0,
                      y: 0,
                      radius: 16,
                      fill: isSeatDisabled(room.code, idx + 1) ? '#cbd5e1' : (isSeatSelected(room.code, idx + 1) ? '#2D4F1E' : '#ffffff'),
                      stroke: isSeatDisabled(room.code, idx + 1) ? '#94a3b8' : (isSeatSelected(room.code, idx + 1) ? '#2D4F1E' : '#1e293b'),
                      strokeWidth: isSeatSelected(room.code, idx + 1) ? 2 : 1.5
                    }"
                  />

                  <!-- Chair Number Text Label inside Circle -->
                  <v-text
                    :config="{
                      x: -16,
                      y: -5,
                      width: 32,
                      text: String(idx + 1).padStart(2, '0'),
                      fontSize: 11,
                      fontStyle: 'bold',
                      fill: isSeatDisabled(room.code, idx + 1) ? '#64748b' : (isSeatSelected(room.code, idx + 1) ? '#ffffff' : '#1e293b'),
                      align: 'center'
                    }"
                  />
                </v-group>
              </template>

              <!-- Price Tag inside Room at bottom (Zero overlap!) -->
              <v-text
                :config="{
                  x: 0,
                  y: room.height - 22,
                  width: room.width,
                  text: bookingMode === 'room'
                    ? formatVND(room.block_price || 300000) + ' / 3h'
                    : formatVND(room.seat_price || 50000) + ' / chỗ',
                  fontSize: 10,
                  fontStyle: 'bold',
                  fill: '#059669',
                  align: 'center'
                }"
              />
            </v-group>
          </v-layer>
        </v-stage>
      </div>
    </div>


  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue';
import { toast } from 'vue3-toastify';
import axios from 'axios';

const props = defineProps({
  modelValue: { type: String, default: null },
  initialSeats: { type: Array, default: () => [] },
  initialMode: { type: String, default: 'seat' },
  tablesData: { type: Object, default: () => ({}) }
});

const emit = defineEmits(['update:modelValue', 'selectRoom']);

const canvasWrapper = ref(null);
const loading = ref(true);
const bookingMode = ref(props.initialMode); // 'seat' vs 'room'
const rooms = ref([]);
const selectedRoomCode = ref(props.modelValue || null);
const selectedRoom = ref(null);

// Multi-seat selection map: { 'D': [1, 2], 'C': [], 'BOX': [] }
const selectedSeatsMap = reactive({});

const stageConfig = reactive({
  width: 640,
  height: 580,
});

const gridX = computedGrid(stageConfig.width, 20);
const gridY = computedGrid(stageConfig.height, 20);

function computedGrid(max, step) {
  const arr = [];
  for (let i = 0; i <= max; i += step) {
    arr.push(i);
  }
  return arr;
}

// Rooms are rendered directly from API data (no position override).

const selectedSeatsList = computed(() => {
  if (!selectedRoomCode.value) return [];
  const list = selectedSeatsMap[selectedRoomCode.value] || [];
  return list;
});

const formattedSelectedSeats = computed(() => {
  const list = selectedSeatsList.value;
  if (list.length === 0) return 'Chưa chọn ghế';
  return list.map(n => String(n).padStart(2, '0')).join(', ');
});

const calculatedTotalPrice = computed(() => {
  if (!selectedRoom.value) return '0đ';
  if (bookingMode.value === 'room') {
    return formatVND(selectedRoom.value.block_price || 300000) + ' / 3h';
  }
  const count = selectedSeatsList.value.length;
  const unit = selectedRoom.value.seat_price || 50000;
  return formatVND(count * unit) + ' / ngày';
});

onMounted(async () => {
  await fetchLayout();
  handleResize();
  window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
  window.removeEventListener('resize', handleResize);
});

function handleResize() {
  if (!canvasWrapper.value) return;
  const containerWidth = canvasWrapper.value.clientWidth - 32;
  if (containerWidth > 0 && containerWidth < 640) {
    stageConfig.width = Math.max(340, containerWidth);
  } else {
    stageConfig.width = 640;
  }
}

watch(() => props.modelValue, (newVal) => {
  selectedRoomCode.value = newVal;
  if (newVal) {
    selectedRoom.value = rooms.value.find(r => r.code === newVal) || null;
  }
});

async function fetchLayout() {
  loading.value = true;
  try {
    const res = await axios.get('/api/floor-layout?floor=2');
    if (res.data && res.data.layout_json && res.data.layout_json.rooms) {
      rooms.value = res.data.layout_json.rooms;
      rooms.value.forEach(r => {
        if (!selectedSeatsMap[r.code]) {
          selectedSeatsMap[r.code] = [];
        }
      });
      if (selectedRoomCode.value) {
        selectedRoom.value = rooms.value.find(r => r.code === selectedRoomCode.value) || null;
        if (props.initialSeats && props.initialSeats.length > 0) {
          selectedSeatsMap[selectedRoomCode.value] = [...props.initialSeats];
        }
      }
    }
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

function setBookingMode(mode) {
  bookingMode.value = mode;
  emitSelection();
}

function onRoomClick(e, room) {
  if (e && e.cancelBubble !== undefined) {
    e.cancelBubble = true;
  }
  handleRoomAreaClick(room);
}

const allTablesFlat = computed(() => {
  if (!props.tablesData) return [];
  return Object.values(props.tablesData).flat();
});

function isRoomDisabled(roomCode) {
  const table = allTablesFlat.value.find(t => t.code === roomCode);
  if (!table) return false;
  if (table.has_room_booking) return true;
  if (bookingMode.value === 'room' && table.booked_seats > 0) return true;
  if (bookingMode.value === 'seat' && table.booked_seats >= (table.total_seating || 8)) return true;
  return false;
}

function isSeatDisabled(roomCode, seatNum) {
  const table = allTablesFlat.value.find(t => t.code === roomCode);
  if (!table) return false;
  if (table.has_room_booking) return true;
  if (seatNum <= table.booked_seats) return true;
  return false;
}

function onSeatClick(e, room, seatNum) {
  if (e && e.cancelBubble !== undefined) {
    e.cancelBubble = true;
  }
  if (isRoomDisabled(room.code) || isSeatDisabled(room.code, seatNum)) {
    toast.error('Chỗ này đã được đặt!');
    return;
  }
  toggleSeatSelection(room, seatNum);
}

function handleRoomAreaClick(room) {
  if (isRoomDisabled(room.code)) {
    toast.error('Phòng này đã được đặt!');
    return;
  }
  selectedRoomCode.value = room.code;
  selectedRoom.value = room;
  if (!selectedSeatsMap[room.code] || selectedSeatsMap[room.code].length === 0) {
    selectedSeatsMap[room.code] = [1];
  }
  emitSelection();
}

function toggleSeatSelection(room, seatNum) {
  if (selectedRoomCode.value !== room.code) {
    selectedRoomCode.value = room.code;
    selectedRoom.value = room;
    selectedSeatsMap[room.code] = [seatNum];
  } else {
    if (!selectedSeatsMap[room.code]) {
      selectedSeatsMap[room.code] = [];
    }
    const list = selectedSeatsMap[room.code];
    const idx = list.indexOf(seatNum);
    if (idx > -1) {
      if (list.length > 1) {
        list.splice(idx, 1);
      }
    } else {
      list.push(seatNum);
      list.sort((a, b) => a - b);
    }
  }
  emitSelection();
}

function selectAllSeats(room) {
  const count = room.total_seating || 8;
  const all = [];
  for (let i = 1; i <= count; i++) all.push(i);
  selectedSeatsMap[room.code] = all;
  emitSelection();
}

function isSeatSelected(roomCode, seatNum) {
  if (selectedRoomCode.value !== roomCode) return false;
  if (bookingMode.value === 'room') return true;
  const list = selectedSeatsMap[roomCode] || [];
  return list.includes(seatNum);
}

function emitSelection() {
  if (!selectedRoom.value) return;
  let seats = selectedSeatsList.value;

  if (bookingMode.value === 'room') {
    const count = selectedRoom.value.total_seating || 8;
    seats = [];
    for (let i = 1; i <= count; i++) seats.push(i);
  }

  emit('update:modelValue', selectedRoom.value.code);
  emit('selectRoom', {
    room: selectedRoom.value,
    code: selectedRoom.value.code,
    category: selectedRoom.value.category,
    bookingMode: bookingMode.value,
    seats: seats,
    seatCount: seats.length,
    seatPrice: selectedRoom.value.seat_price || 50000,
    blockPrice: selectedRoom.value.block_price || 300000,
  });
}

function computeWireframeSeatNodes(room) {
  const seats = [];
  const count = room.total_seating || 8;

  if (room.code === 'BOX' || room.category === 'box_room') {
    // Table is centered: y = (height-40)/2, center at height/2
    // Place chair 01 just above table, chair 02 just below table
    const tableCenter = room.height / 2;
    const cx = room.width / 2;
    seats.push({ x: cx, y: tableCenter - 55 }); // above table
    seats.push({ x: cx, y: tableCenter + 55 }); // below table
    return seats;
  }

  // 8-seat rooms (Phòng D, Phòng C): 4 circles left, 4 circles right
  // Table is from y=48 to y=208 (height 160). Center chairs symmetrically (22px top & bottom margin):
  const leftX = 30;
  const rightX = room.width - 30;
  const startY = 70;
  const stepY = 38.5;

  // Left 4 seats (1, 2, 3, 4)
  for (let i = 0; i < 4; i++) {
    seats.push({ x: leftX, y: startY + i * stepY });
  }

  // Right 4 seats (5, 6, 7, 8)
  for (let i = 0; i < 4; i++) {
    seats.push({ x: rightX, y: startY + i * stepY });
  }

  return seats;
}

function handleMouseEnter(e) {
  const stageNode = e.target.getStage();
  if (stageNode) stageNode.container().style.cursor = 'pointer';
}

function handleMouseLeave(e) {
  const stageNode = e.target.getStage();
  if (stageNode) stageNode.container().style.cursor = 'default';
}

function formatVND(val) {
  return new Intl.NumberFormat('vi-VN').format(val || 0) + 'đ';
}
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

.f2-wrapper {
  font-family: 'Inter', sans-serif;
  background: #ffffff;
  border-radius: 0px;
  border: 1px solid #cbd5e1;
  padding: 20px;
  color: #1e293b;
}

.f2-header {
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 14px;
  margin-bottom: 14px;
}

.f2-title {
  font-size: 18px;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.f2-mode-group {
  display: flex;
  align-items: center;
  gap: 4px;
  background: #f8fafc;
  padding: 4px;
  border-radius: 0px;
  border: 1px solid #cbd5e1;
}

.f2-mode-btn {
  background: transparent;
  border: none;
  padding: 6px 14px;
  border-radius: 0px;
  font-weight: 600;
  font-size: 12px;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s ease;
}

.f2-mode-btn:hover {
  color: #0f172a;
}

.f2-mode-btn.active {
  background: #2D4F1E;
  color: #ffffff;
}

.f2-legend-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 0px;
  padding: 8px 14px;
  font-size: 12px;
  color: #475569;
  margin-bottom: 16px;
}

.f2-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
}

.f2-dot-active {
  background: #2D4F1E;
}

.f2-dot-empty {
  background: #ffffff;
  border: 1px solid #1e293b;
}

.f2-canvas-container {
  position: relative;
  width: 100%;
  background: #ffffff;
  border: 1.5px solid #1e293b;
  border-radius: 0px;
  padding: 12px;
  min-height: 540px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.f2-stage-inner {
  width: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
  overflow: auto;
}

.f2-loading-overlay {
  position: absolute;
  inset: 0;
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(4px);
  z-index: 20;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  font-size: 14px;
  font-weight: 600;
  color: #1e293b;
}

.spinner {
  width: 24px;
  height: 24px;
  animation: spin 1s linear infinite;
  color: #2D4F1E;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.f2-summary-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 0px;
  padding: 14px 18px;
  margin-top: 16px;
}

.f2-unselected-prompt {
  background: #f8fafc;
  border: 1px dashed #cbd5e1;
  border-radius: 0px;
  padding: 14px 18px;
  margin-top: 16px;
}

.f2-room-badge {
  width: 40px;
  height: 40px;
  border-radius: 0px;
  background: #2D4F1E;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 16px;
}

.f2-room-title {
  font-size: 14px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.f2-room-sub {
  font-size: 12px;
  color: #475569;
}

.f2-highlight-pill {
  background: #2D4F1E;
  color: #ffffff;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 0px;
  font-size: 11px;
}

.f2-highlight-pill-blue {
  background: #1e1b4b;
  color: #ffffff;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 0px;
  font-size: 11px;
}

.f2-seat-pills {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
}

.f2-pill-label {
  font-size: 11px;
  font-weight: 600;
  color: #475569;
  margin-right: 4px;
}

.f2-seat-pill {
  background: #ffffff;
  border: 1px solid #1e293b;
  color: #1e293b;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 0px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.f2-seat-pill:hover {
  border-color: #2D4F1E;
  color: #2D4F1E;
}

.f2-seat-pill.active {
  background: #2D4F1E;
  border-color: #2D4F1E;
  color: #ffffff;
}

.f2-seat-pill-action {
  background: #f1f5f9;
  border: none;
  color: #334155;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 0px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.f2-seat-pill-action:hover {
  background: #e2e8f0;
  color: #0f172a;
}

.f2-total-label {
  font-size: 11px;
  color: #64748b;
  font-weight: 600;
}

.f2-total-price {
  font-size: 18px;
  font-weight: 800;
  color: #2D4F1E;
}
</style>

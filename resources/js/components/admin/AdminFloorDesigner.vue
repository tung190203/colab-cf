<template>
  <AdminLayout>
    <template #title>Sơ đồ Tầng 2</template>

    <div class="afd-wrap">
      <!-- Header Card -->
      <div class="afd-header">
        <div>
          <h1 class="afd-title">
            Thiết kế Sơ đồ Tầng 2
          </h1>
          <p class="afd-sub">Kéo thả di chuyển phòng/bàn, tùy chỉnh mã phòng, sức chứa ghế và giá thuê trực tiếp.</p>
        </div>
        <div class="afd-header-actions">
          <button type="button" @click="resetToDefault" class="afd-btn-secondary">
            Khôi phục mặc định
          </button>
          <button type="button" @click="saveLayout" :disabled="saving" class="afd-btn-primary">
            {{ saving ? 'Đang lưu...' : 'Lưu Sơ đồ Tầng 2' }}
          </button>
        </div>
      </div>

      <!-- Add Tools Toolbar -->
      <div class="afd-toolbar">
        <div class="afd-tools-left">
          <span class="afd-label">Thêm phòng mới:</span>
          <button type="button" @click="addRoom('meeting_room')" class="afd-btn-tool">
            + Phòng họp (Meeting Room)
          </button>
          <button type="button" @click="addRoom('box_room')" class="afd-btn-tool">
            + Box kính (Box Room)
          </button>
        </div>
        <div class="afd-tools-right">
          <label class="afd-checkbox-label">
            <input type="checkbox" v-model="snapToGrid">
            Hút theo lưới (Grid Snapping)
          </label>
        </div>
      </div>

      <!-- Main Canvas & Inspector 2-Column Grid -->
      <div class="afd-main-grid">
        <!-- Konva Stage Container (Left Column) -->
        <div class="afd-canvas-card">
          <div ref="stageContainer" class="afd-stage-inner">
            <v-stage
              ref="stage"
              :config="stageConfig"
              @mousedown="handleStageMouseDown"
              @touchstart="handleStageMouseDown"
            >
              <v-layer ref="layer">
                <!-- Pure White Background -->
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

                <!-- Grid lines -->
                <v-group>
                  <template v-for="x in gridLinesX" :key="'gx-' + x">
                    <v-line :config="{ points: [x, 0, x, stageConfig.height], stroke: '#f1f5f9', strokeWidth: 1 }" />
                  </template>
                  <template v-for="y in gridLinesY" :key="'gy-' + y">
                    <v-line :config="{ points: [0, y, stageConfig.width, y], stroke: '#f1f5f9', strokeWidth: 1 }" />
                  </template>
                </v-group>

                <!-- Rooms / Tables (Draggable Wireframe Objects) -->
                <v-group
                  v-for="room in rooms"
                  :key="room.id"
                  :config="{
                    id: room.id,
                    x: room.x,
                    y: room.y,
                    draggable: true,
                  }"
                  @dragend="(e) => handleDragEnd(e, room)"
                  @click="selectRoom(room)"
                  @tap="selectRoom(room)"
                >
                  <!-- Room Wireframe Outer Shape -->
                  <v-rect
                    :config="{
                      width: room.width,
                      height: room.height,
                      fill: selectedRoomId === room.id ? '#f0fdf4' : '#ffffff',
                      stroke: selectedRoomId === room.id ? '#2D4F1E' : '#1e293b',
                      strokeWidth: selectedRoomId === room.id ? 2.5 : 1.5,
                      cornerRadius: 0
                    }"
                  />

                  <!-- Room Header Text -->
                  <v-text
                    :config="{
                      x: 0,
                      y: 12,
                      width: room.width,
                      text: (room.name || room.code).toUpperCase() + ' (' + (room.total_seating || 8) + ' CHỖ)',
                      fontSize: 11,
                      fontStyle: 'bold',
                      fill: selectedRoomId === room.id ? '#2D4F1E' : '#1e293b',
                      align: 'center'
                    }"
                  />

                  <!-- Center Table Top -->
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
                  />

                  <!-- Table Label Text -->
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
                  />

                  <!-- Price Tag inside Room at bottom -->
                  <v-text
                    :config="{
                      x: 0,
                      y: room.height - 22,
                      width: room.width,
                      text: formatVND(room.seat_price || 50000) + '/chỗ • ' + formatVND(room.block_price || 300000) + '/3h',
                      fontSize: 10,
                      fontStyle: 'bold',
                      fill: '#059669',
                      align: 'center'
                    }"
                  />

                  <!-- Circular Chair Nodes (v-circle) -->
                  <template v-for="(seat, idx) in computeWireframeSeatNodes(room)" :key="'seat-' + idx">
                    <v-group
                      :config="{
                        x: seat.x,
                        y: seat.y,
                      }"
                    >
                      <v-circle
                        :config="{
                          x: 0,
                          y: 0,
                          radius: 16,
                          fill: selectedRoomId === room.id ? '#2D4F1E' : '#ffffff',
                          stroke: selectedRoomId === room.id ? '#2D4F1E' : '#1e293b',
                          strokeWidth: 1.5
                        }"
                      />
                      <v-text
                        :config="{
                          x: -16,
                          y: -5,
                          width: 32,
                          text: String(idx + 1).padStart(2, '0'),
                          fontSize: 11,
                          fontStyle: 'bold',
                          fill: selectedRoomId === room.id ? '#ffffff' : '#1e293b',
                          align: 'center'
                        }"
                      />
                    </v-group>
                  </template>
                </v-group>

                <!-- Transformer for Selected Room resizing -->
                <v-transformer
                  ref="transformer"
                  :config="{
                    boundBoxFunc: (oldBox, newBox) => {
                      if (newBox.width < 120 || newBox.height < 100) return oldBox;
                      return newBox;
                    },
                    enabledAnchors: ['top-left', 'top-right', 'bottom-left', 'bottom-right'],
                    rotateEnabled: false
                  }"
                  @transformend="handleTransformEnd"
                />
              </v-layer>
            </v-stage>
          </div>
        </div>

        <!-- Inspector Panel Sidebar (Right Column) -->
        <div class="afd-inspector-card">
          <div v-if="selectedRoom" class="afd-form-body">
            <div class="afd-inspector-head">
              <h3 class="afd-inspector-title">
                Cấu hình {{ selectedRoom.name || selectedRoom.code }}
              </h3>
              <button type="button" @click="deleteSelectedRoom" class="afd-btn-danger-sm">
                Xóa
              </button>
            </div>

            <!-- Form fields -->
            <div class="afd-field">
              <label>Mã phòng / Bàn</label>
              <input v-model="selectedRoom.code" type="text">
            </div>

            <div class="afd-field">
              <label>Tên hiển thị phòng</label>
              <input v-model="selectedRoom.name" type="text">
            </div>

            <div class="afd-field" v-if="false">
              <label>Số lượng ghế (Sức chứa)</label>
              <input v-model.number="selectedRoom.total_seating" type="number" min="1" max="20">
              <span class="afd-hint">Tự động sinh các ghế tròn 01, 02... trên sơ đồ</span>
            </div>

            <div class="afd-field">
              <label>Giá thuê 1 ghế (VNĐ/ngày)</label>
              <input v-model.number="selectedRoom.seat_price" type="number" step="10000">
            </div>

            <div class="afd-field">
              <label>Giá thuê trọn phòng (VNĐ/3h)</label>
              <input v-model.number="selectedRoom.block_price" type="number" step="50000">
            </div>

            <!-- Position X, Y -->
            <div class="afd-grid-2" v-if="false">
              <div class="afd-field">
                <label>Tọa độ X (px)</label>
                <input v-model.number="selectedRoom.x" type="number">
              </div>
              <div class="afd-field">
                <label>Tọa độ Y (px)</label>
                <input v-model.number="selectedRoom.y" type="number">
              </div>
            </div>

            <!-- Dimensions Width, Height -->
            <div class="afd-grid-2" v-if="false">
              <div class="afd-field">
                <label>Chiều rộng (px)</label>
                <input v-model.number="selectedRoom.width" type="number">
              </div>
              <div class="afd-field">
                <label>Chiều cao (px)</label>
                <input v-model.number="selectedRoom.height" type="number">
              </div>
            </div>
          </div>

          <div v-else class="afd-empty-state">
            <span>Nhấp chọn một phòng trên sơ đồ để chỉnh sửa thông tin & số ghế</span>
          </div>

          <!-- Bottom Save Quick Button -->
          <div class="afd-footer-action">
            <button type="button" @click="saveLayout" :disabled="saving" class="afd-btn-primary-full">
              {{ saving ? 'Đang lưu...' : 'Lưu thay đổi sơ đồ' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue';
import AdminLayout from './AdminLayout.vue';
import { useAdminAuth } from '../../composables/useAdminAuth';
import axios from 'axios';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const { authHeader } = useAdminAuth();

const stageContainer = ref(null);
const stage = ref(null);
const layer = ref(null);
const transformer = ref(null);

const saving = ref(false);
const snapToGrid = ref(true);
const gridSize = 20;

const stageConfig = reactive({
  width: 640,
  height: 580,
});

const gridLinesX = computed(() => {
  const arr = [];
  for (let i = 0; i <= stageConfig.width; i += gridSize) arr.push(i);
  return arr;
});

const gridLinesY = computed(() => {
  const arr = [];
  for (let i = 0; i <= stageConfig.height; i += gridSize) arr.push(i);
  return arr;
});

const rooms = ref([]);
const selectedRoomId = ref(null);

const selectedRoom = computed(() => {
  return rooms.value.find(r => r.id === selectedRoomId.value) || null;
});

onMounted(async () => {
  await fetchLayout();
});

async function fetchLayout() {
  try {
    const res = await axios.get('/api/floor-layout?floor=2');
    if (res.data && res.data.layout_json && res.data.layout_json.rooms) {
      rooms.value = res.data.layout_json.rooms;
    } else {
      resetToDefault();
    }
  } catch (e) {
    console.error(e);
    resetToDefault();
  }
}

function resetToDefault() {
  rooms.value = [
    {
      id: 'room-d',
      code: 'D',
      name: 'Phòng D',
      category: 'meeting_room',
      x: 30,
      y: 20,
      width: 230,
      height: 260,
      total_seating: 8,
      seat_price: 50000,
      block_price: 300000,
    },
    {
      id: 'room-box',
      code: 'BOX',
      name: 'Box kính',
      category: 'box_room',
      x: 100,
      y: 280,
      width: 190,
      height: 200,
      total_seating: 2,
      seat_price: 50000,
      block_price: 300000,
    },
    {
      id: 'room-c',
      code: 'C',
      name: 'Phòng C',
      category: 'meeting_room',
      x: 290,
      y: 280,
      width: 230,
      height: 280,
      total_seating: 8,
      seat_price: 50000,
      block_price: 300000,
    },
  ];
  toast.info('Đã tải sơ đồ mặc định');
}

function addRoom(category) {
  const newId = 'room-' + Date.now();
  const codeName = category === 'box_room' ? 'BOX' : 'M' + (rooms.value.length + 1);
  rooms.value.push({
    id: newId,
    code: codeName,
    name: category === 'box_room' ? 'Box kính' : 'Phòng ' + codeName,
    category: category,
    x: 50 + rooms.value.length * 30,
    y: 50 + rooms.value.length * 30,
    width: category === 'box_room' ? 190 : 230,
    height: category === 'box_room' ? 200 : 260,
    total_seating: category === 'box_room' ? 2 : 8,
    seat_price: 50000,
    block_price: 300000,
  });
  selectedRoomId.value = newId;
  updateTransformer();
}

function selectRoom(room) {
  selectedRoomId.value = room.id;
  updateTransformer();
}

function handleStageMouseDown(e) {
  if (e.target === stage.value.getStage()) {
    selectedRoomId.value = null;
    updateTransformer();
  }
}

function updateTransformer() {
  nextTick(() => {
    if (!transformer.value) return;
    const stageNode = stage.value.getStage();
    const selectedNode = stageNode.findOne('#' + selectedRoomId.value);
    if (selectedNode) {
      transformer.value.getNode().nodes([selectedNode]);
    } else {
      transformer.value.getNode().nodes([]);
    }
  });
}

function handleDragEnd(e, room) {
  let x = e.target.x();
  let y = e.target.y();
  if (snapToGrid.value) {
    x = Math.round(x / gridSize) * gridSize;
    y = Math.round(y / gridSize) * gridSize;
    e.target.x(x);
    e.target.y(y);
  }
  room.x = x;
  room.y = y;
}

function handleTransformEnd(e) {
  const node = transformer.value.getNode().nodes()[0];
  if (!node) return;
  const room = rooms.value.find(r => r.id === node.id());
  if (room) {
    room.width = Math.round(node.width() * node.scaleX());
    room.height = Math.round(node.height() * node.scaleY());
    node.scaleX(1);
    node.scaleY(1);
  }
}

function deleteSelectedRoom() {
  if (!selectedRoomId.value) return;
  rooms.value = rooms.value.filter(r => r.id !== selectedRoomId.value);
  selectedRoomId.value = null;
  updateTransformer();
  toast.info('Đã xóa mô hình');
}

function computeWireframeSeatNodes(room) {
  const seats = [];
  const count = room.total_seating || 8;

  if (room.code === 'BOX' || room.category === 'box_room') {
    const tableCenter = room.height / 2;
    const cx = room.width / 2;
    seats.push({ x: cx, y: tableCenter - 55 });
    seats.push({ x: cx, y: tableCenter + 55 });
    return seats;
  }

  const leftX = 30;
  const rightX = room.width - 30;
  const startY = 70;
  const stepY = 38.5;
  const half = Math.ceil(count / 2);

  for (let i = 0; i < half; i++) {
    seats.push({ x: leftX, y: startY + i * stepY });
  }
  for (let i = 0; i < count - half; i++) {
    seats.push({ x: rightX, y: startY + i * stepY });
  }

  return seats;
}

async function saveLayout() {
  saving.value = true;
  try {
    await axios.post('/api/admin/floor-layout', {
      floor: 2,
      layout_json: {
        rooms: rooms.value,
      },
    }, {
      headers: authHeader(),
    });
    toast.success('Đã lưu sơ đồ Tầng 2 thành công!');
  } catch (e) {
    console.error(e);
    if (e.response && e.response.status === 401) {
      toast.error('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.');
      setTimeout(() => {
        window.location.href = '/admin/login';
      }, 1500);
    } else {
      toast.error('Lỗi khi lưu sơ đồ!');
    }
  } finally {
    saving.value = false;
  }
}

function formatVND(val) {
  return new Intl.NumberFormat('vi-VN').format(val || 0) + 'đ';
}
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

* {
  box-sizing: border-box;
}

.afd-wrap {
  width: 100%;
  max-width: 1180px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 20px;
  font-family: 'Inter', sans-serif;
}

.afd-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 14px;
  padding: 20px 24px;
}

.afd-title {
  font-size: 1.25rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.afd-sub {
  font-size: 0.85rem;
  color: #64748b;
  margin: 4px 0 0 0;
}

.afd-header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.afd-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 14px;
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  padding: 12px 18px;
}

.afd-tools-left {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.afd-label {
  font-size: 0.8rem;
  font-weight: 800;
  text-transform: uppercase;
  color: #64748b;
}

.afd-btn-tool {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 6px 14px;
  font-size: 0.8rem;
  font-weight: 700;
  color: #1e293b;
  cursor: pointer;
  transition: all 0.15s ease;
}

.afd-btn-tool:hover {
  border-color: #2D4F1E;
  color: #2D4F1E;
}

.afd-checkbox-label {
  font-size: 0.8rem;
  font-weight: 700;
  color: #475569;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
}

.afd-main-grid {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 20px;
  width: 100%;
}

.afd-canvas-card {
  background: #ffffff;
  border: 1.5px solid #cbd5e1;
  border-radius: 14px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 580px;
}

.afd-stage-inner {
  width: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
  overflow: auto;
}

.afd-inspector-card {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 14px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.afd-inspector-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 12px;
  margin-bottom: 16px;
}

.afd-inspector-title {
  font-size: 0.95rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.afd-btn-danger-sm {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
}

.afd-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 12px;
}

.afd-field label {
  font-size: 0.8rem;
  font-weight: 700;
  color: #475569;
}

.afd-field input {
  padding: 9px 12px;
  border-radius: 8px;
  border: 1.5px solid #cbd5e1;
  background: #f8fafc;
  font-size: 0.85rem;
  font-weight: 700;
  color: #0f172a;
  outline: none;
  width: 100%;
  box-sizing: border-box;
}

.afd-field input:focus {
  border-color: #2D4F1E;
  background: #ffffff;
}

.afd-hint {
  font-size: 0.7rem;
  color: #94a3b8;
}

.afd-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.afd-btn-primary {
  background: #2D4F1E;
  color: #ffffff;
  border: none;
  padding: 9px 18px;
  border-radius: 10px;
  font-weight: 800;
  font-size: 0.85rem;
  cursor: pointer;
}

.afd-btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.afd-btn-secondary {
  background: #ffffff;
  color: #475569;
  border: 1px solid #cbd5e1;
  padding: 9px 16px;
  border-radius: 10px;
  font-weight: 700;
  font-size: 0.85rem;
  cursor: pointer;
}

.afd-btn-primary-full {
  width: 100%;
  background: #2D4F1E;
  color: #ffffff;
  border: none;
  padding: 12px 18px;
  border-radius: 10px;
  font-weight: 800;
  font-size: 0.85rem;
  cursor: pointer;
  margin-top: 16px;
}

.afd-empty-state {
  text-align: center;
  padding: 40px 10px;
  color: #64748b;
  font-size: 0.85rem;
  font-weight: 600;
}

@media (max-width: 900px) {
  .afd-main-grid {
    grid-template-columns: 1fr;
  }
}
</style>

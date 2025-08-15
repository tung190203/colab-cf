<script setup>
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue3-toastify';

const listMember = ref([]);
const currentPage = ref(1);
const itemsPerPage = 6;
const totalPages = computed(() => Math.ceil(listMember.value.length / itemsPerPage));
const paginatedListMember = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return listMember.value.slice(start, end);
});

// State cho edit modal
const showEditModal = ref(false);
const editMemberData = ref({ id: null, name: '', phone: '' });

async function getListMember() {
    const response = await fetch('/api/list-members');
    if (!response.ok) {
        throw new Error('Network response was not ok');
    }
    const data = await response.json();
    listMember.value = data;
}

function goToPage(page) {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
  }
}

function deleteMember(id) {
    if (confirm('Bạn có chắc chắn muốn xóa member này?')) {
        fetch(`/api/member/${id}`, {
            method: 'DELETE',
        })
        .then(response => response.json())
        .then(data => {
                listMember.value = listMember.value.filter(member => member.id !== id);
                toast.success(data.message || 'Xóa member thành công');
        })
        .catch(error => {
            toast.error(error.message || 'Xóa member thất bại');
        });
    }
}

// Mở modal edit
function openEditModal(member) {
    editMemberData.value = { ...member };
    showEditModal.value = true;
}

// Lưu thông tin edit
function saveEdit() {
    fetch(`/api/member/${editMemberData.value.id}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            name: editMemberData.value.name,
            phone: editMemberData.value.phone
        }),
    })
    .then(response => response.json())
    .then(data => {
        const index = listMember.value.findIndex(m => m.id === editMemberData.value.id);
        if (index !== -1) {
            listMember.value[index] = data.user;
        }
        toast.success(data.message || 'Cập nhật member thành công');
        showEditModal.value = false;
    })
    .catch(error => {
        toast.error(error.message || 'Cập nhật member thất bại');
    });
}


onMounted(async () => {
    await getListMember();
});
</script>

<template>
    <div class="container py-4">
      <h2 class="mb-4">Danh sách Member</h2>
  
      <div v-if="listMember.length === 0" class="text-center text-muted py-5">
        Không có member nào hiện tại
      </div>
  
      <div v-else class="table-responsive shadow-sm rounded">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th scope="col" class="text-center" style="width: 60px;">#</th>
              <th scope="col" style="width: 60px;">Tên</th>
              <th scope="col">Số điện thoại</th>
              <th scope="col" style="width: 120px;">Ngày tham gia</th>
              <th scope="col" class="text-center" style="width: 120px;">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(member, index) in paginatedListMember" :key="member.id">
              <td class="text-center fw-bold">
                {{ (currentPage - 1) * itemsPerPage + index + 1 }}
              </td>
              <td class="text-nowrap">{{ member.name }}</td>
              <td>{{ member.phone }}</td>
                <td>
                    {{ new Date(member.created_at).toLocaleDateString('vi-VN') }}
                </td>
                <td class="text-center d-flex justify-content-center gap-2">  
                    <button class="btn btn-danger btn-sm" @click="deleteMember(member.id)">
                        Xóa
                    </button>
                    <button class="btn btn-primary btn-sm" @click="openEditModal(member)">
                        Sửa
                    </button>   
                </td>
            </tr>
          </tbody>
        </table>
      </div>
  
      <!-- Pagination -->
      <nav v-if="totalPages > 1" class="mt-3">
        <ul class="pagination justify-content-center">
          <li class="page-item" :class="{ disabled: currentPage === 1 }">
            <button class="page-link" @click="goToPage(currentPage - 1)">Previous</button>
          </li>
          <li class="page-item" v-for="page in totalPages" :key="page" :class="{ active: currentPage === page }">
            <button class="page-link" @click="goToPage(page)">{{ page }}</button>
          </li>
          <li class="page-item" :class="{ disabled: currentPage === totalPages }">
            <button class="page-link" @click="goToPage(currentPage + 1)">Next</button>
          </li>
        </ul>
      </nav>
    </div>

    <!-- Edit Member Modal -->
    <div class="modal fade" tabindex="-1" :class="{ show: showEditModal }" style="display: block;" v-if="showEditModal">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-success text-white">
            <h5 class="modal-title">Chỉnh sửa Member</h5>
            <button type="button" class="btn-close btn-close-white" @click="showEditModal = false"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Tên</label>
              <input type="text" v-model="editMemberData.name" class="form-control" />
            </div>
            <div class="mb-3">
              <label class="form-label">Số điện thoại</label>
              <input type="text" v-model="editMemberData.phone" class="form-control" />
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="showEditModal = false">Đóng</button>
            <button class="btn btn-primary" @click="saveEdit">Lưu</button>
          </div>
        </div>
      </div>
    </div>
</template>

<style scoped>
.table-hover tbody tr:hover {
  background-color: #f8f9fa;
}

.table thead th {
  white-space: nowrap;
}

.pagination .page-item.active .page-link {
  background-color: #20451F;
  border-color: #20451F;
  color: white;
}

.pagination .page-link {
  color: #555555;
}

.pagination .page-link:hover {
  color: #20451F;
}

/* Overlay cho modal custom */
.modal {
  background-color: rgba(0, 0, 0, 0.5);
}
</style>

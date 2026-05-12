import { ref } from 'vue';
import axios from 'axios';

const adminUser = ref(JSON.parse(localStorage.getItem('admin_user') || 'null'));
const adminToken = ref(localStorage.getItem('admin_token') || null);

export function useAdminAuth() {
    async function login(phone, password) {
        const res = await axios.post('/api/admin/login', { phone, password });
        adminToken.value = res.data.token;
        adminUser.value = res.data.user;
        localStorage.setItem('admin_token', res.data.token);
        localStorage.setItem('admin_user', JSON.stringify(res.data.user));
        return res.data;
    }

    async function logout() {
        try {
            await axios.post('/api/admin/logout', {}, {
                headers: { Authorization: `Bearer ${adminToken.value}` }
            });
        } catch (_) {}
        adminToken.value = null;
        adminUser.value = null;
        localStorage.removeItem('admin_token');
        localStorage.removeItem('admin_user');
    }

    function isAdmin() {
        return adminUser.value?.role === 'admin';
    }

    function isStaff() {
        return adminUser.value?.role === 'staff';
    }

    function isShiftLeader() {
        return adminUser.value?.role === 'shift_leader';
    }

    function isLoggedIn() {
        return !!adminToken.value;
    }

    function authHeader() {
        return adminToken.value ? { Authorization: `Bearer ${adminToken.value}` } : {};
    }

    return { adminUser, adminToken, login, logout, isAdmin, isStaff, isShiftLeader, isLoggedIn, authHeader };
}

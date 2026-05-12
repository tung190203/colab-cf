<script setup>
import { AlertTriangle, Info, AlertCircle } from 'lucide-vue-next';
defineProps({
    show: Boolean,
    title: { type: String, default: 'Xác nhận' },
    message: String,
    confirmText: { type: String, default: 'Đồng ý' },
    cancelText: { type: String, default: 'Hủy' },
    type: { type: String, default: 'danger' } // danger, warning, info
});

const emit = defineEmits(['confirm', 'cancel']);
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="show" class="cd-overlay" @click.self="emit('cancel')">
                <div class="cd-modal">
                    <div class="cd-icon" :class="'cd-icon--' + type">
                        <AlertTriangle v-if="type === 'danger'" :size="32" />
                        <Info v-else-if="type === 'info'" :size="32" />
                        <AlertCircle v-else :size="32" />
                    </div>
                    <div class="cd-content">
                        <h3 class="cd-title">{{ title }}</h3>
                        <p class="cd-message">{{ message }}</p>
                    </div>
                    <div class="cd-actions">
                        <button class="cd-btn cd-btn--cancel" @click="emit('cancel')">{{ cancelText }}</button>
                        <button class="cd-btn" :class="'cd-btn--' + type" @click="emit('confirm')">{{ confirmText }}</button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.cd-overlay {
    position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5);
    display: flex; align-items: center; justify-content: center;
    z-index: 99999; backdrop-filter: blur(4px); padding: 20px;
}
.cd-modal {
    background: white; border-radius: 24px; padding: 32px 24px;
    width: 100%; max-width: 380px;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
    display: flex; flex-direction: column; align-items: center; text-align: center;
}
.cd-icon {
    width: 64px; height: 64px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center; margin-bottom: 20px;
}
.cd-icon--danger { background: #fee2e2; color: #dc2626; }
.cd-icon--warning { background: #fef3c7; color: #d97706; }
.cd-icon--info { background: #dbeafe; color: #2563eb; }
.cd-icon--info { background: #dbeafe; color: #2563eb; }

.cd-title { margin: 0 0 12px; font-size: 1.35rem; font-weight: 800; color: #1e293b; }
.cd-message { margin: 0 0 32px; font-size: 1rem; color: #64748b; line-height: 1.5; font-weight: 500; }

.cd-actions { display: flex; gap: 12px; width: 100%; }
.cd-btn {
    flex: 1; padding: 12px 20px; border: none; border-radius: 14px;
    font-size: 1rem; font-weight: 700; cursor: pointer; transition: all 0.2s;
    font-family: 'Inter', sans-serif;
}
.cd-btn--cancel { background: #f1f5f9; color: #475569; }
.cd-btn--cancel:hover { background: #e2e8f0; color: #1e293b; }
.cd-btn--danger { background: #ef4444; color: white; }
.cd-btn--danger:hover { background: #dc2626; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2); }
.cd-btn--warning { background: #f59e0b; color: white; }
.cd-btn--warning:hover { background: #d97706; }
.cd-btn--info { background: #3b82f6; color: white; }
.cd-btn--info:hover { background: #2563eb; }

.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.fade-enter-active .cd-modal { animation: bounce-in 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
@keyframes bounce-in {
    0% { transform: scale(0.85); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}
</style>

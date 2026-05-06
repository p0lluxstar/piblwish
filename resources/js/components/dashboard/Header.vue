<script setup lang="ts">
import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';
import { useLogout } from '@/composables/useAuth';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const { mutate: logout, isPending } = useLogout();
</script>

<template>
    <header class="header">
        <div class="container">
            <div class="logo">
                <div class="logo-icon">
                    ♡
                    <span class="logo-spark">✦</span>
                </div>
                <span class="logo-title">PiblWish</span>
            </div>
            <div class="user-info">
                <div v-if="auth.user" class="user-details">
                    <span class="user-username">{{ auth.user.username }}</span>
                    <span class="user-email">{{ auth.user.email }}</span>
                </div>
                <div class="avatar">
                    {{ auth.user?.username?.charAt(0).toUpperCase() }}
                </div>
                <button
                    class="logout-btn"
                    :disabled="isPending"
                    @click="logout"
                >
                    <LoaderButtonSpinner v-if="isPending" :size="18" />
                    <span v-else>Выход</span>
                </button>
            </div>
        </div>
    </header>
</template>

<style scoped>
.header {
    background: linear-gradient(135deg, #fff7fb 0%, #fff1f5 48%, #f5f3ff 100%);
    border-bottom: 1px solid rgba(226, 195, 211, 0.45);
    padding: 0 28px;
    height: 60px;
    display: flex;
    justify-content: center;
    align-items: center;
}
.container {
    display: flex;
    width: 1200px;
    justify-content: space-between;
}

.logo {
    display: flex;
    align-items: center;
    gap: 10px;
}
.logo-icon {
    width: 34px;
    height: 34px;
    border-radius: 12px;
    background: linear-gradient(135deg, #ff8fab, #c4b5fd);
    display: grid;
    place-items: center;
    font-size: 18px;
    position: relative;
}
.logo-spark {
    position: absolute;
    top: 3px;
    right: 4px;
    font-size: 8px;
    color: #fff7ad;
}
.logo-title {
    font-size: 19px;
    font-weight: 600;
    color: #3b2146;
    letter-spacing: -0.04em;
}
.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
    color: #3b2146;
}

.user-details {
    display: flex;
    flex-direction: column;
}

.user-username {
    font-weight: 600;
}
.user-email {
    font-size: 12px;
    color: #9b7caa;
}

.avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: linear-gradient(135deg, #ff8fab, #c4b5fd);
    display: grid;
    place-items: center;
    font-size: 12px;
    font-weight: 600;
    color: #fff;
}

.logout-btn {
    display: flex;
    justify-content: center;
    align-items: center;
    background: none;
    border: none;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    color: #9b7caa;
    cursor: pointer;
    font-family: 'DM Sans', sans-serif;
    transition: all 0.18s;
    background: rgba(255, 143, 171, 0.13);
    width: 80px;
    height: 30px;
}
.logout-btn.active {
    color: #c4697e;
    font-weight: 500;
}
.logout-btn:hover:not(.active) {
    background: rgba(196, 181, 253, 0.12);
}
</style>

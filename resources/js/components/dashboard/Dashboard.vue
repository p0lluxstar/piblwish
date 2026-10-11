<script setup lang="ts">
import { useAuthStore } from '@/stores/auth';

import UpcomingSavedWishlists from '../saved/UpcomingSavedWishlists.vue';
import WishlistMain from '../wishlist/WishlistMain.vue';

defineProps<{
    archived?: boolean;
}>();

const auth = useAuthStore();
</script>

<template>
    <!-- Ближайшие события чужих списков — только над активными карточками;
         строку можно выключить в настройках аккаунта -->
    <UpcomingSavedWishlists
        v-if="!archived && auth.user?.showFriendsEvents !== false"
    />

    <!-- При переходе между активными списками и архивом страница создаётся
         заново: у разделов свои запросы, фильтр, сортировка и окна -->
    <WishlistMain :key="archived ? 'archive' : 'active'" :archived="archived" />
</template>

<style scoped></style>

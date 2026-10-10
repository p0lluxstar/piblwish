<script setup lang="ts">
import WishlistMain from '../wishlist/WishlistMain.vue';

defineProps<{
    archived?: boolean;
}>();
</script>

<template>
    <!-- При переходе между активными списками и архивом страница создаётся
         заново: у разделов свои запросы, фильтр, сортировка и окна -->
    <WishlistMain :key="archived ? 'archive' : 'active'" :archived="archived" />
</template>

<style scoped></style>

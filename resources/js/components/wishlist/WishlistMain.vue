<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import axios from 'axios';
import { ref } from 'vue';
import { computed } from 'vue';

import type { Wishlist } from '../../types/wishlist';
import LaoderPageSpinner from '../ui/LaoderPageSpinner.vue';
import WishlistCard from './WishlistCard.vue';
import WishlistCreateModal from './WishlistCreateModal.vue';
import WishlistEditModal from './WishlistEditModal.vue';
import WishlistDeleteModal from './WishlistDeleteModal.vue';

const queryClient = useQueryClient();
const isCreateModalOpen = ref(false);
const isEditModalOpen = ref(false);
const selectedWishlist = ref<Wishlist | null>(null);
const isDeleteModalOpen = ref(false);
const wishlistToDelete = ref<Wishlist | null>(null);

const fetchWishlists = async (): Promise<Wishlist[]> => {
    const response = await axios.get('/v1/wishlists');

    return response.data.data;
};

const { data, isLoading, error } = useQuery({
    queryKey: ['wishlists'],
    queryFn: fetchWishlists,
});

const wishLists = computed(() => data.value ?? []);

const openCreateModal = (): void => {
    isCreateModalOpen.value = true;
};

const closeCreateModal = (): void => {
    isCreateModalOpen.value = false;
};

const createWishlistRequest = async (
    payload: Omit<Wishlist, 'id'>,
): Promise<Wishlist> => {
    const response = await axios.post('/v1/wishlists', payload);

    return response.data.data;
};

const { mutate: createWishlist, isPending: isCreating } = useMutation({
    mutationFn: createWishlistRequest,

    onSuccess: async () => {
        await queryClient.invalidateQueries({
            queryKey: ['wishlists'],
        });

        closeCreateModal();
    },

    onError: (error) => {
        console.error('Ошибка создания списка', error);
    },
});

const openEditModal = (wishlist: Wishlist): void => {
    console.log(
        'Открытие модального окна редактирования для списка:',
        wishlist,
    );
    selectedWishlist.value = JSON.parse(JSON.stringify(wishlist));
    isEditModalOpen.value = true;
};

const closeEditModal = (): void => {
    isEditModalOpen.value = false;
    selectedWishlist.value = null;
};

// Цепочка обновения списка: PATCH → emit('updated') → updateWishlist() → queryClient.setQueryData() → vue-query обновляет data.value → computed wishLists пересчитывается → WishlistCard получает новые props
const updateWishlist = (updated: Partial<Wishlist>): void => {
    queryClient.setQueryData<Wishlist[]>(['wishlists'], (oldData) => {
        if (!oldData) return [];

        return oldData.map((wishlist) =>
            wishlist.id === updated.id
                ? {
                      ...wishlist,
                      ...updated,
                  }
                : wishlist,
        );
    });
};

const deleteWishlistRequest = async (id: string): Promise<void> => {
    await axios.delete(`/v1/wishlists/${id}`);
};

const { mutate: deleteWishlist, isPending: isDeleting } = useMutation({
    mutationFn: deleteWishlistRequest,
    onSuccess: async () => {
        await queryClient.invalidateQueries({
            queryKey: ['wishlists'],
        });
        closeDeleteModal(); // Закрываем модальное окно при успехе
    },
    onError: (error) => {
        console.error('Ошибка удаления списка', error);
    },
});

const openDeleteModal = (wishlist: Wishlist): void => {
    wishlistToDelete.value = wishlist;
    isDeleteModalOpen.value = true;
};
const closeDeleteModal = (): void => {
    isDeleteModalOpen.value = false;
    wishlistToDelete.value = null;
};
</script>

<template>
    <!-- Блок "Мои списки" всегда отображается -->
    <div class="top">
        <div>
            <div class="heading">Мои списки</div>
            <div class="sub">
                <span v-if="!isLoading">
                    Cписков {{ wishLists.length }} · выбирай что хочешь
                </span>
                <span v-else>Загрузка списков...</span>
            </div>
        </div>
        <button class="add-btn" @click="openCreateModal">+ Новый список</button>
    </div>

    <!-- Контент меняется в зависимости от загрузки -->
    <div
        v-if="isLoading"
        class="flex items-center justify-center min-h-[400px]"
    >
        <LaoderPageSpinner />
    </div>

    <div
        v-else-if="error"
        class="flex items-center justify-center min-h-[400px]"
    >
        <p>Ошибка при загрузке</p>
    </div>

    <div v-else class="grid" id="grid">
        <WishlistCard
            v-for="wishlist in wishLists"
            :key="wishlist.id"
            :wishlist="wishlist"
            @edit="openEditModal"
            @delete="openDeleteModal"
        />
    </div>

    <WishlistCreateModal
        v-if="isCreateModalOpen"
        :is-pending="isCreating"
        @close="closeCreateModal"
        @create="createWishlist"
    />

    <WishlistEditModal
        v-if="isEditModalOpen && selectedWishlist"
        :wishlist="selectedWishlist"
        :is-pending="isUpdating"
        @close="closeEditModal"
        @updated="updateWishlist"
    />

    <WishlistDeleteModal
        v-if="isDeleteModalOpen && wishlistToDelete"
        :wishlist="wishlistToDelete"
        :is-pending="isDeleting"
        @close="closeDeleteModal"
        @confirm="deleteWishlist(wishlistToDelete.id)"
    />
</template>

<style scoped>
.top {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 22px;
}
.heading {
    font-size: 20px;
    font-weight: 600;
    color: #3b2146;
    letter-spacing: -0.03em;
}
.sub {
    font-size: 12px;
    color: #b08cbe;
    margin-top: 2px;
}
.add-btn {
    background: linear-gradient(135deg, #ff8fab, #c4b5fd);
    border: none;
    color: #fff;
    padding: 7px 16px;
    border-radius: 18px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    font-family: 'DM Sans', sans-serif;
}

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px;
}
</style>

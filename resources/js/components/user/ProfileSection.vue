<script setup lang="ts">
import { computed, ref } from 'vue';

import FormErrorMessage from '@/components/ui/FormErrorMessage.vue';
import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';
import UserAvatar from '@/components/ui/UserAvatar.vue';
import AvatarCropModal from '@/components/user/AvatarCropModal.vue';
import { useDeleteAvatar } from '@/composables/useAuth';
import { useAuthStore } from '@/stores/auth';

// Раздел профиля в окне настроек: аватар, имя пользователя и действия с фотографией
const auth = useAuthStore();

const username = computed(() => auth.user?.username ?? '');
const avatarUrl = computed(() => auth.user?.avatarUrl ?? null);

// «С 12 сентября 2026 г.»
const registeredAt = computed(() => {
    if (!auth.user?.createdAt) return '';

    return new Date(auth.user.createdAt).toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const {
    mutate: deleteAvatar,
    isPending: isDeleting,
    isError: isDeleteError,
    errorMessage: deleteErrorMessage,
    reset: resetDelete,
} = useDeleteAvatar();

// Наибольший размер исходного файла. На сервер он не отправляется: браузер
// кадрирует и уменьшает фото, поэтому лимит защищает только память браузера
const MAX_SOURCE_SIZE_MB = 20;

const fileInputRef = ref<HTMLInputElement | null>(null);

// Выбранный файл; пока он задан, открыто окно кадрирования
const selectedFile = ref<File | null>(null);
const selectError = ref<string | null>(null);

const openFilePicker = (): void => {
    selectError.value = null;
    fileInputRef.value?.click();
};

const handleFileChange = (event: Event): void => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    // Очистка поля позволяет снова выбрать тот же файл после отмены
    input.value = '';

    if (!file) {
        return;
    }

    if (!file.type.startsWith('image/')) {
        selectError.value = 'Выберите изображение';

        return;
    }

    if (file.size > MAX_SOURCE_SIZE_MB * 1024 * 1024) {
        selectError.value = `Файл должен быть не больше ${MAX_SOURCE_SIZE_MB} МБ`;

        return;
    }

    hideDeleteConfirm();
    selectedFile.value = file;
};

const closeCropModal = (): void => {
    selectedFile.value = null;
};

// Подтверждение показывается вместо кнопки «Удалить фото»
const isDeleteConfirmVisible = ref(false);

const showDeleteConfirm = (): void => {
    resetDelete();
    isDeleteConfirmVisible.value = true;
};

const hideDeleteConfirm = (): void => {
    isDeleteConfirmVisible.value = false;
};

const confirmDelete = (): void => {
    deleteAvatar(undefined, {
        onSuccess: hideDeleteConfirm,
    });
};
</script>

<template>
    <section v-if="username" class="profile-section">
        <div class="profile-main">
            <UserAvatar
                class="profile-avatar"
                :username="username"
                :avatar-url="avatarUrl"
            />

            <div class="profile-info">
                <span class="profile-label">Имя пользователя</span>
                <span class="profile-username">{{ username }}</span>
                <span v-if="registeredAt" class="profile-registered">
                    В сервисе с {{ registeredAt }}
                </span>

                <div v-if="!isDeleteConfirmVisible" class="avatar-actions">
                    <button
                        type="button"
                        class="avatar-action"
                        @click="openFilePicker"
                    >
                        {{ avatarUrl ? 'Сменить фото' : 'Загрузить фото' }}
                    </button>

                    <button
                        v-if="avatarUrl"
                        type="button"
                        class="avatar-action avatar-action-danger"
                        @click="showDeleteConfirm"
                    >
                        Удалить фото
                    </button>
                </div>
            </div>
        </div>

        <!-- Поле скрыто: выбор файла открывает кнопка «Загрузить фото».
             Список форматов в accept заставляет iOS отдать снимок HEIC в виде JPEG -->
        <input
            ref="fileInputRef"
            class="visually-hidden"
            type="file"
            accept="image/jpeg,image/png,image/webp,image/gif"
            tabindex="-1"
            aria-hidden="true"
            @change="handleFileChange"
        />

        <FormErrorMessage
            class="form-message select-error"
            :show="Boolean(selectError)"
            :message="selectError ?? undefined"
        />

        <AvatarCropModal
            v-if="selectedFile"
            :file="selectedFile"
            @close="closeCropModal"
        />

        <div v-if="isDeleteConfirmVisible" class="avatar-confirm">
            <p class="confirm-text">
                Удалить фото? Вместо него будет показана первая буква имени.
            </p>

            <FormErrorMessage
                class="form-message"
                :show="isDeleteError"
                :message="deleteErrorMessage ?? undefined"
            />

            <div class="form-actions">
                <button
                    type="button"
                    class="cancel-btn"
                    :disabled="isDeleting"
                    @click="hideDeleteConfirm"
                >
                    Отмена
                </button>

                <button
                    type="button"
                    class="delete-btn"
                    :disabled="isDeleting"
                    @click="confirmDelete"
                >
                    <LoaderButtonSpinner v-if="isDeleting" :size="18" />

                    <span v-else>Удалить</span>
                </button>
            </div>
        </div>
    </section>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/accountSection.scss';

.profile-section {
    margin-bottom: 20px;
    padding-bottom: 18px;
    border-bottom: 1px dashed rgba(139, 92, 246, 0.2);
}

.profile-main {
    display: flex;
    align-items: center;
    gap: 12px;
}

.profile-avatar {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, #a78bfa, #ec4899);
    color: #fff;
    font-size: 18px;
    font-weight: 700;
}

.profile-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.profile-label {
    font-size: 12px;
    color: var(--ink-soft, #6b5878);
}

.profile-username {
    overflow: hidden;
    font-size: 15px;
    font-weight: 700;
    color: var(--ink, #241533);
    text-overflow: ellipsis;
    white-space: nowrap;
}

.profile-registered {
    font-size: 12px;
    color: var(--ink-soft, #6b5878);
}

// Действия с фотографией — компактные текстовые кнопки под именем
.avatar-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 2px;
}

.avatar-action {
    padding: 0;
    border: none;
    background: none;
    color: #8b5cf6;
    font-size: 12px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;

    &:hover {
        text-decoration: underline;
    }
}

.avatar-action-danger {
    color: #e11d48;
}

.visually-hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0 0 0 0);
    white-space: nowrap;
}

.select-error {
    margin: 12px 0 0;
    text-align: left;
}

.avatar-confirm {
    margin-top: 14px;
}

.confirm-text {
    margin: 0 0 14px;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.5;
    color: var(--ink, #241533);
}

// Как кнопка подтверждения удаления аккаунта в UserSettingsModal
.delete-btn {
    display: flex;
    flex: 1;
    justify-content: center;
    align-items: center;
    padding: 11px;
    border: none;
    border-radius: 18px;
    background: linear-gradient(135deg, #fb7185, #ec4899);
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 14px 30px -12px rgba(236, 72, 153, 0.5);

    &:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 18px 36px -12px rgba(236, 72, 153, 0.55);
    }

    &:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
}
</style>

<script setup lang="ts">
import 'cropperjs/dist/cropper.css';

import { RotateCw, ZoomIn, ZoomOut } from '@lucide/vue';
import Cropper from 'cropperjs';
import { onBeforeUnmount, ref } from 'vue';

import FormErrorMessage from '@/components/ui/FormErrorMessage.vue';
import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';
import { useUploadAvatar } from '@/composables/useAuth';

// Окно кадрирования фотографии: пользователь выбирает квадратную область,
// она уменьшается в браузере до OUTPUT_SIZE и отправляется на сервер.
// Исходный файл не отправляется, поэтому его размер ограничен только памятью браузера
const props = defineProps<{
    file: File;
}>();

const emit = defineEmits<{
    close: [];
}>();

// Сторона отправляемого квадрата. Сервер всё равно уменьшает фото до 256 px,
// запас нужен, чтобы уменьшение выполнялось один раз и без потери чёткости
const OUTPUT_SIZE = 512;

// JPEG вместо WebP: Safari не кодирует WebP через canvas и отдал бы тяжёлый PNG
const OUTPUT_TYPE = 'image/jpeg';
const OUTPUT_QUALITY = 0.9;

const {
    mutate: uploadAvatar,
    isPending: isUploading,
    isError: isUploadError,
    errorMessage: uploadErrorMessage,
} = useUploadAvatar();

const imageUrl = URL.createObjectURL(props.file);
const imageRef = ref<HTMLImageElement | null>(null);

let cropper: Cropper | null = null;

const isReady = ref(false);
const loadError = ref<string | null>(null);
const isPreparing = ref(false);

const initCropper = (): void => {
    if (!imageRef.value) {
        return;
    }

    cropper = new Cropper(imageRef.value, {
        aspectRatio: 1,
        // Рамка не выходит за пределы изображения
        viewMode: 1,
        // Перетаскивание сдвигает изображение под рамкой, а не рисует новую рамку
        dragMode: 'move',
        autoCropArea: 1,
        background: false,
        guides: false,
        center: false,
        toggleDragModeOnDblclick: false,
        // Современные браузеры сами поворачивают изображение по EXIF;
        // проверка cropperjs повернула бы его второй раз
        checkOrientation: false,
        ready: (): void => {
            isReady.value = true;
        },
    });
};

// Формат, который браузер не умеет показывать (например, HEIC вне Safari)
const handleImageError = (): void => {
    loadError.value =
        'Не удалось открыть изображение. Выберите файл JPEG, PNG или WebP';
};

const zoom = (ratio: number): void => {
    cropper?.zoom(ratio);
};

const rotate = (): void => {
    cropper?.rotate(90);
};

const save = (): void => {
    if (!cropper || isUploading.value || isPreparing.value) {
        return;
    }

    const canvas = cropper.getCroppedCanvas({
        width: OUTPUT_SIZE,
        height: OUTPUT_SIZE,
        // У прозрачного PNG прозрачная область в JPEG стала бы чёрной
        fillColor: '#fff',
        imageSmoothingEnabled: true,
        imageSmoothingQuality: 'high',
    });

    isPreparing.value = true;

    canvas.toBlob(
        (blob) => {
            isPreparing.value = false;

            if (!blob) {
                loadError.value = 'Не удалось подготовить фото';

                return;
            }

            uploadAvatar(blob, {
                onSuccess: () => emit('close'),
            });
        },
        OUTPUT_TYPE,
        OUTPUT_QUALITY,
    );
};

const close = (): void => {
    if (!isUploading.value) {
        emit('close');
    }
};

onBeforeUnmount(() => {
    cropper?.destroy();
    URL.revokeObjectURL(imageUrl);
});
</script>

<template>
    <Teleport to="body">
        <div class="modal-overlay crop-overlay" @click.self="close">
            <div
                class="modal crop-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="avatar-crop-title"
            >
                <div class="modal-header">
                    <h2 id="avatar-crop-title">Фото профиля</h2>

                    <button
                        class="close-btn"
                        aria-label="Закрыть"
                        :disabled="isUploading"
                        @click="close"
                    ></button>
                </div>

                <template v-if="!loadError">
                    <p class="crop-hint">
                        Перетащите фото и измените масштаб, чтобы лицо попало в
                        круг
                    </p>

                    <div class="crop-area">
                        <img
                            ref="imageRef"
                            :src="imageUrl"
                            alt="Выбранное фото"
                            @load="initCropper"
                            @error="handleImageError"
                        />
                    </div>

                    <div class="crop-tools">
                        <button
                            type="button"
                            class="tool-btn"
                            aria-label="Уменьшить"
                            :disabled="!isReady"
                            @click="zoom(-0.1)"
                        >
                            <ZoomOut :size="18" />
                        </button>

                        <button
                            type="button"
                            class="tool-btn"
                            aria-label="Увеличить"
                            :disabled="!isReady"
                            @click="zoom(0.1)"
                        >
                            <ZoomIn :size="18" />
                        </button>

                        <button
                            type="button"
                            class="tool-btn"
                            aria-label="Повернуть"
                            :disabled="!isReady"
                            @click="rotate"
                        >
                            <RotateCw :size="18" />
                        </button>
                    </div>
                </template>

                <FormErrorMessage
                    class="form-message"
                    :show="Boolean(loadError) || isUploadError"
                    :message="loadError ?? uploadErrorMessage ?? undefined"
                />

                <div class="form-actions">
                    <button
                        type="button"
                        class="cancel-btn"
                        :disabled="isUploading"
                        @click="close"
                    >
                        Отмена
                    </button>

                    <button
                        v-if="!loadError"
                        type="button"
                        class="create-btn"
                        :disabled="!isReady || isUploading || isPreparing"
                        @click="save"
                    >
                        <LoaderButtonSpinner
                            v-if="isUploading || isPreparing"
                            :size="18"
                        />

                        <span v-else>Сохранить</span>
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/wishlistModal.scss';
@use '../../../scss/ui/createButton.scss';
@use '../../../scss/ui/accountSection.scss';

// Поверх окна настроек аккаунта
.crop-overlay {
    z-index: 1100;
}

.crop-modal {
    max-width: 400px;
    padding: 24px;
}

.crop-hint {
    margin: 0 0 12px;
    font-size: 13px;
    line-height: 1.5;
    color: var(--ink-soft, #6b5878);
}

// cropperjs заполняет контейнер, поэтому его высота задана явно
.crop-area {
    height: min(60vh, 320px);
    overflow: hidden;
    border-radius: 14px;
    background: #241533;

    img {
        display: block;
        max-width: 100%;
    }
}

// Рамка кадрирования круглая, как аватар; сохраняется квадрат,
// круг из него вырезает CSS при показе
.crop-area :deep(.cropper-view-box),
.crop-area :deep(.cropper-face) {
    border-radius: 50%;
}

.crop-area :deep(.cropper-view-box) {
    outline: 2px solid #fff;
    outline-offset: -1px;
}

.crop-tools {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin: 12px 0 16px;
}

.tool-btn {
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    border: 1.5px solid rgba(139, 92, 246, 0.25);
    border-radius: 50%;
    background: transparent;
    color: #8b5cf6;
    cursor: pointer;
    transition: all 0.2s ease;

    &:hover:not(:disabled) {
        background: rgba(139, 92, 246, 0.08);
    }

    &:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
}
</style>

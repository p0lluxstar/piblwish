<script setup lang="ts">
import { Gift, ListChecks, StickyNote } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import dashboardSlideImage from '../../images/slide-dashboard.jpg';
import editSlideImage from '../../images/slide-edit.jpg';
import sharedSlideImage from '../../images/slide-shared.jpg';

interface HeroSlide {
    image: string;
    alt: string;
    // Заголовок подписи; он же подпись точки-переключателя для экранного диктора
    title: string;
    text: string;
}

const slides: HeroSlide[] = [
    {
        image: dashboardSlideImage,
        alt: 'Скриншот страницы «Мои карточки» со списками желаний, делами и заметкой',
        title: 'Всё на одном экране',
        text: 'Желания, дела и заметки — рядом. Фильтруйте по типу и следите, сколько уже выполнено.',
    },
    {
        image: editSlideImage,
        alt: 'Скриншот окна редактирования списка желаний',
        title: 'Список желаний за минуту',
        text: 'Добавьте подарки с ценой, ссылкой и приоритетом, выберите цвет и включите режим сюрприза.',
    },
    {
        image: sharedSlideImage,
        alt: 'Скриншот списка подарков, открытого гостем по ссылке',
        title: 'Друзья выбирают по ссылке',
        text: 'Гости отмечают подарок без регистрации, и одно и то же не подарят дважды.',
    },
];

// Интервал автоматической смены слайдов, мс
const SLIDE_INTERVAL = 5000;

const activeSlide = ref(0);
let slideTimer: number | null = null;

function startSlideTimer(): void {
    stopSlideTimer();
    slideTimer = window.setInterval(() => {
        activeSlide.value = (activeSlide.value + 1) % slides.length;
    }, SLIDE_INTERVAL);
}

function stopSlideTimer(): void {
    if (slideTimer !== null) {
        window.clearInterval(slideTimer);
        slideTimer = null;
    }
}

// Ручной выбор слайда перезапускает таймер, чтобы слайд не сменился сразу после клика
function showSlide(index: number): void {
    activeSlide.value = index;
    startSlideTimer();
}

onMounted(startSlideTimer);
onBeforeUnmount(stopSlideTimer);

const offsetX = ref(0);
const offsetY = ref(0);

const imageStyle = computed(() => ({
    transform: `translate3d(${offsetX.value}px, ${offsetY.value}px, 0) rotate(${offsetX.value / 18}deg) scale(1.04)`,
}));

function handleMouseMove(event: MouseEvent): void {
    const { innerWidth, innerHeight } = window;

    const x = (event.clientX / innerWidth - 0.5) * 2;
    const y = (event.clientY / innerHeight - 0.5) * 2;

    offsetX.value = x * 26;
    offsetY.value = y * 26;
}

function resetImage(): void {
    offsetX.value = 0;
    offsetY.value = 0;
}
</script>

<template>
    <main class="landing" @mousemove="handleMouseMove" @mouseleave="resetImage">
        <section class="left-panel">
            <router-link to="/" class="brand-link">
                <div class="brand">
                    <div class="logo">
                        <Gift :size="30" color="#fff" />
                        <span class="logo-spark">✦</span>
                    </div>
                    <h1>PiblWish</h1>
                </div>
            </router-link>

            <div class="hero-content">
                <router-view />
            </div>
        </section>

        <section class="right-panel">
            <div class="floating-card card-one">
                <Gift class="floating-card-icon" :size="18" />
                Подарки
            </div>
            <div class="floating-card card-two">
                <StickyNote class="floating-card-icon" :size="18" />
                Заметки
            </div>
            <div class="floating-card card-three">
                <ListChecks class="floating-card-icon" :size="18" />
                Дела
            </div>
            <div class="image-glow"></div>

            <div class="hero-stage">
                <div class="hero-slider" :style="imageStyle">
                    <img
                        v-for="(slide, index) in slides"
                        :key="slide.title"
                        class="hero-image"
                        :class="{ active: index === activeSlide }"
                        :src="slide.image"
                        :alt="slide.alt"
                        :aria-hidden="index !== activeSlide"
                    />
                </div>

                <!-- Подпись к текущему слайду: что изображено на скриншоте -->
                <Transition name="caption" mode="out-in">
                    <div
                        :key="activeSlide"
                        class="slide-caption"
                        aria-live="polite"
                    >
                        <h3 class="slide-caption-title">
                            {{ slides[activeSlide].title }}
                        </h3>
                        <p class="slide-caption-text">
                            {{ slides[activeSlide].text }}
                        </p>
                    </div>
                </Transition>

                <div class="slider-dots">
                    <button
                        v-for="(slide, index) in slides"
                        :key="slide.title"
                        type="button"
                        class="slider-dot"
                        :class="{ active: index === activeSlide }"
                        :aria-label="slide.title"
                        :aria-current="index === activeSlide"
                        @click="showSlide(index)"
                    ></button>
                </div>
            </div>
        </section>
    </main>
</template>

<style scoped>
* {
    box-sizing: border-box;
}

.landing {
    min-height: 100vh;
    display: grid;
    grid-template-columns: minmax(320px, 42%) 1fr;
    overflow: hidden;
    background:
        radial-gradient(circle at 24% 22%, #ff8fab47, #0000 34%),
        radial-gradient(circle at 76% 72%, #c4b5fd57, #0000 38%),
        linear-gradient(135deg, #fffafd 0%, #f7f3ff 100%);
    color: var(--ink);
    font-family:
        Inter,
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        'Segoe UI',
        sans-serif;
}

.left-panel {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    padding: 56px;
    background: linear-gradient(135deg, #fff7fb 0%, #fff1f5 48%, #f5f3ff 100%);
    border-right: 1px solid #e2c3d373;
}

/* Логотип в обычном потоке: форма центрируется в оставшемся месте и не наезжает на него при низком окне */
.brand {
    display: flex;
    align-items: center;
    gap: 16px;
}

.hero-content {
    flex: 1;
    display: flex;
    margin: 0 auto;
    padding-top: 40px;
    flex-direction: column;
    justify-content: center;
}

.logo {
    position: relative;
    width: 58px;
    height: 58px;
    display: grid;
    place-items: center;
    border-radius: 15px;
    background: var(--logo-gradient);
    color: #ffffff;
    font-weight: 900;
    box-shadow: var(--shadow-glow-lg);
    transition: transform 0.25s ease;
}

.brand-link {
    align-self: flex-start;
}

.brand-link:hover .logo {
    transform: rotate(-6deg) scale(1.05);
}

.logo-spark {
    position: absolute;
    top: 8px;
    right: 9px;
    font-size: 13px;
    color: var(--brand-amber);
    animation: sparkle 2.4s ease-in-out infinite;
}

@keyframes sparkle {
    0%,
    100% {
        opacity: 0.5;
        transform: scale(0.85);
    }
    50% {
        opacity: 1;
        transform: scale(1.15);
    }
}

.brand h1 {
    margin: 0;
    font-size: 30px;
    font-weight: 800;
    letter-spacing: -0.05em;
    background: var(--logo-gradient);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.right-panel {
    position: relative;
    display: grid;
    place-items: center;
    padding: 56px;
    overflow: hidden;
    background:
        radial-gradient(circle at 24% 22%, #ff8fab47, #0000 34%),
        radial-gradient(circle at 76% 72%, #c4b5fd57, #0000 38%),
        linear-gradient(135deg, #fffafd 0%, #f7f3ff 100%);
}

.image-glow {
    position: absolute;
    width: min(52vw, 660px);
    height: min(52vw, 660px);
    border-radius: 50%;
    background: var(--brand-gradient);
    opacity: 0.28;
    filter: blur(56px);
}

.hero-slider {
    position: relative;
    width: min(46vw, 620px);
    height: min(62vh, 620px);
    overflow: hidden;
    border: 4px solid rgba(255, 255, 255, 0.9);
    border-radius: var(--radius-xl);
    background: rgba(255, 255, 255, 0.9);
    box-shadow: var(--shadow-glow-lg);
    transition: transform 0.16s ease-out;
    will-change: transform;
    /* Ниже подписи: смещённая за мышью картинка не должна её закрывать */
    z-index: 1;
}

/* Внутреннее затемнение по краям поверх всех слайдов */
.hero-slider::after {
    content: '';
    position: absolute;
    inset: 0;
    z-index: 1;
    pointer-events: none;
    box-shadow: inset 0 0 50px rgba(30, 16, 50, 0.24);
    background: radial-gradient(
        ellipse at center,
        rgba(30, 16, 50, 0) 55%,
        rgba(30, 16, 50, 0.1) 100%
    );
}

.hero-image {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    opacity: 0;
    transform: scale(1.06);
    transition:
        opacity 0.9s ease,
        transform 5s ease-out;
}

.hero-image.active {
    opacity: 1;
    transform: scale(1);
}

/* Слайдер, подпись и точки одной колонкой */
.hero-stage {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}

.slide-caption {
    position: relative;
    z-index: 2;
    /* Высота под две строки текста, чтобы точки не прыгали при смене подписи */
    min-height: 72px;
    max-width: min(46vw, 560px);
    /* Дополнительный отступ от картинки сверх общего gap */
    margin-top: 20px;
    text-align: center;
}

.slide-caption-title {
    margin: 0 0 6px;
    font-size: 18px;
    font-weight: 800;
    letter-spacing: -0.02em;
    color: var(--ink);
}

.slide-caption-text {
    margin: 0;
    font-size: 14px;
    line-height: 1.45;
    color: var(--ink-soft);
}

.caption-enter-active,
.caption-leave-active {
    transition:
        opacity 0.35s ease,
        transform 0.35s ease;
}

.caption-enter-from {
    opacity: 0;
    transform: translateY(6px);
}

.caption-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}

.slider-dots {
    z-index: 2;
    display: flex;
    gap: 10px;
}

.slider-dot {
    width: 10px;
    height: 10px;
    padding: 0;
    border: 0;
    border-radius: 999px;
    background: rgba(124, 58, 237, 0.25);
    cursor: pointer;
    transition:
        width 0.3s ease,
        background 0.3s ease;
}

.slider-dot.active {
    width: 28px;
    background: var(--brand-gradient);
}

@media (prefers-reduced-motion: reduce) {
    .hero-image {
        transform: none;
        transition: opacity 0.3s ease;
    }

    .caption-enter-from,
    .caption-leave-to {
        transform: none;
    }
}

.floating-card {
    position: absolute;
    z-index: 2;
    padding: 14px 20px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.85);
    color: var(--ink);
    font-size: 15px;
    font-weight: 800;
    box-shadow: var(--shadow-glow);
    backdrop-filter: blur(14px);
    border: 1px solid rgba(255, 255, 255, 0.9);
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Значки одного цвета у всех плашек */
.floating-card-icon {
    flex-shrink: 0;
    color: var(--brand-violet, #8b5cf6);
}

.card-one {
    top: 30%;
    left: 13%;
    animation: float 4.5s ease-in-out infinite;
}

.card-two {
    right: 13%;
    bottom: 24%;
    animation: float 5.2s ease-in-out infinite reverse;
}

.card-three {
    right: 21%;
    top: 16%;
    animation: float 5.8s ease-in-out infinite;
}

@keyframes float {
    0%,
    100% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(-14px);
    }
}

@media (max-width: 900px) {
    .hero-content {
        min-height: auto;
        padding-top: 48px;
    }

    .landing {
        grid-template-columns: 1fr;
    }

    .left-panel {
        min-height: 58vh;
        padding: 32px;
        border-right: 0;
        border-bottom: 1px solid var(--surface-border);
    }

    .right-panel {
        min-height: 42vh;
        padding: 32px;
    }

    .hero-slider {
        width: min(86vw, 520px);
        height: 320px;
    }

    .slide-caption {
        max-width: min(86vw, 520px);
    }
}

@media (max-width: 520px) {
    .left-panel {
        padding: 24px;
    }

    .brand h1 {
        font-size: 26px;
    }

    .content h2 {
        font-size: 42px;
    }

    .actions {
        flex-direction: column;
    }

    .btn {
        width: 100%;
    }

    .floating-card {
        display: none;
    }
}
</style>

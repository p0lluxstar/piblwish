<script setup lang="ts">
import { computed, ref } from 'vue';

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
            <div class="brand">
                <div class="logo">
                    <span class="logo-heart">♥</span>
                    <span class="logo-spark">✦</span>
                </div>
                <h1>PiblWish</h1>
            </div>

            <div class="hero-content">
                <router-view />
            </div>
        </section>

        <section class="right-panel">
            <div class="floating-card card-one">🎁 Подарки</div>
            <div class="floating-card card-two">✨ Мечты</div>
            <div class="image-glow"></div>
            <img
                class="hero-image"
                src="https://images.unsplash.com/photo-1513201099705-a9746e1e201f?auto=format&fit=crop&w=1100&q=80"
                alt="Wishlist moodboard"
                :style="imageStyle"
            />
        </section>
    </main>
</template>

<style>
* {
    box-sizing: border-box;
}

.landing {
    min-height: 100vh;
    display: grid;
    grid-template-columns: minmax(320px, 42%) 1fr;
    overflow: hidden;
    background: #fff8fb;
    color: #34233c;
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
    border-right: 1px solid rgba(226, 195, 211, 0.45);
}

.brand {
    position: absolute;
    top: 56px;
    left: 56px;
    display: flex;
    align-items: center;
    gap: 16px;
}

.hero-content {
    flex: 1;
    display: flex;
    margin: 0 auto;
    flex-direction: column;
    justify-content: center;
}

.logo {
    position: relative;
    width: 58px;
    height: 58px;
    display: grid;
    place-items: center;
    border-radius: 22px;
    background: linear-gradient(135deg, #ff8fab, #c4b5fd);
    color: #ffffff;
    font-weight: 900;
    box-shadow: 0 18px 38px rgba(255, 143, 171, 0.32);
}

.logo-heart {
    font-size: 40px;
    line-height: 1;
    transform: translateY(1px);
}

.logo-spark {
    position: absolute;
    top: 8px;
    right: 9px;
    font-size: 13px;
    color: #fff7ad;
}

.brand h1 {
    margin: 0;
    font-size: 30px;
    letter-spacing: -0.05em;
    color: #3b2146;
}

.right-panel {
    position: relative;
    display: grid;
    place-items: center;
    padding: 56px;
    background:
        radial-gradient(
            circle at 24% 22%,
            rgba(255, 143, 171, 0.28),
            transparent 34%
        ),
        radial-gradient(
            circle at 76% 72%,
            rgba(196, 181, 253, 0.34),
            transparent 38%
        ),
        linear-gradient(135deg, #fffafd 0%, #f7f3ff 100%);
}

.image-glow {
    position: absolute;
    width: min(52vw, 660px);
    height: min(52vw, 660px);
    border-radius: 50%;
    background: linear-gradient(
        135deg,
        rgba(255, 143, 171, 0.28),
        rgba(196, 181, 253, 0.32)
    );
    filter: blur(46px);
}

.hero-image {
    position: relative;
    width: min(46vw, 620px);
    height: min(62vh, 620px);
    object-fit: cover;
    border: 12px solid rgba(255, 255, 255, 0.82);
    border-radius: 42px;
    box-shadow: 0 34px 80px rgba(134, 86, 116, 0.24);
    transition: transform 0.16s ease-out;
    will-change: transform;
}

.floating-card {
    position: absolute;
    z-index: 2;
    padding: 14px 20px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.82);
    color: #56345e;
    font-size: 15px;
    font-weight: 800;
    box-shadow: 0 18px 34px rgba(134, 86, 116, 0.16);
    backdrop-filter: blur(14px);
    border: 1px solid rgba(255, 255, 255, 0.9);
}

.card-one {
    top: 18%;
    left: 13%;
    animation: float 4.5s ease-in-out infinite;
}

.card-two {
    right: 13%;
    bottom: 19%;
    animation: float 5.2s ease-in-out infinite reverse;
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
    .brand {
        position: static;
    }

    .hero-content {
        min-height: auto;
        padding-top: 72px;
    }

    .landing {
        grid-template-columns: 1fr;
    }

    .left-panel {
        min-height: 58vh;
        padding: 32px;
        border-right: 0;
        border-bottom: 1px solid rgba(226, 195, 211, 0.45);
    }

    .right-panel {
        min-height: 42vh;
        padding: 32px;
    }

    .hero-image {
        width: min(86vw, 520px);
        height: 320px;
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

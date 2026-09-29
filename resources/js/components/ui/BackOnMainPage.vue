<template>
    <RouterLink to="/" class="back-link">❮</RouterLink>
</template>

<style scoped>
.back-link {
    display: flex;
    justify-content: center;
    align-items: center;
    border-radius: 50%;
    text-decoration: none;
    background: #fff;
    color: var(--brand-pink, #ec4899);
    box-shadow: var(--shadow-glow, 0 14px 34px -12px rgba(139, 92, 246, 0.38));
    font-weight: bold;
    width: 34px;
    height: 34px;
    margin-bottom: 20px;
    transition: all 0.2s ease;

    &:hover {
        background: var(--brand-gradient, linear-gradient(135deg, #8b5cf6, #ec4899));
        color: #fff;
        transform: translateX(-3px);
    }
}
</style>

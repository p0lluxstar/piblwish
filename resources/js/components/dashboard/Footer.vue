<script setup lang="ts">
import { CircleQuestionMark, Mail } from '@lucide/vue';
</script>

<template>
    <footer class="footer">
        <div class="footer-inner">
            <span class="footer-copy">© 2026 PiblWish</span>
            <div class="footer-links">
                <router-link to="/help">
                    <CircleQuestionMark :size="13" />
                    Помощь
                </router-link>
                <router-link to="/contacts">
                    <Mail :size="13" />
                    Контакты
                </router-link>
            </div>
        </div>
    </footer>
</template>

<style scoped>
.footer {
    border-top: 1px solid var(--surface-border);
    padding: 16px 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--surface);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
}

/* Не container: у одноимённого класса Tailwind max-width по брейкпоинтам */
.footer-inner {
    display: flex;
    width: 1200px;
    justify-content: space-between;
    align-items: center;
}

.footer-copy {
    font-size: 12px;
    font-weight: 600;
    color: var(--ink-soft);
}

.footer-links {
    display: flex;
    flex-shrink: 0;
    gap: 18px;
}

.footer-links a {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
    font-size: 12px;
    font-weight: 600;
    color: var(--ink-soft);
    text-decoration: none;
    transition: color 0.18s ease;
}

/* Иконка не сжимается и не уходит на отдельную строку */
.footer-links a svg {
    display: inline-block;
    flex-shrink: 0;
}

.footer-links a:hover {
    color: var(--brand-pink);
}
</style>

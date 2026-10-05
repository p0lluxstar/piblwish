<script setup lang="ts">
import { ExternalLink } from '@lucide/vue';

const GITHUB_URL = 'https://github.com/p0lluxstar/piblwish';
</script>

<template>
    <div class="contacts">
        <div class="heading">Контакты</div>
        <p class="sub">
            Вопросы, предложения и сообщения об ошибках оставляйте в репозитории
            проекта на GitHub.
        </p>

        <a
            :href="GITHUB_URL"
            target="_blank"
            rel="noopener noreferrer"
            class="card"
        >
            <!-- В @lucide/vue нет логотипов брендов, поэтому значок GitHub встроен в разметку -->
            <span class="card-icon" aria-hidden="true">
                <svg viewBox="0 0 16 16" width="18" height="18">
                    <path
                        fill="currentColor"
                        d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"
                    />
                </svg>
            </span>
            <span class="card-text">
                <span class="card-title">GitHub</span>
                <span class="card-url">github.com/p0lluxstar/piblwish</span>
            </span>
            <ExternalLink :size="16" class="card-arrow" />
        </a>
    </div>
</template>

<style scoped>
/* Тот же стиль, что у заголовка «Мои карточки» в дашборде */
.heading {
    font-size: 26px;
    font-weight: 800;
    color: var(--ink);
    letter-spacing: -0.03em;
}

.contacts {
    max-width: 820px;
}

.sub {
    margin-top: 8px;
    font-size: 14px;
    line-height: 1.6;
    color: var(--ink-soft);
}

.card {
    display: inline-flex;
    align-items: center;
    gap: 14px;
    max-width: 100%;
    margin-top: 22px;
    padding: 16px 20px;
    border-radius: var(--radius-lg);
    border: 1px solid var(--surface-border);
    background: var(--surface);
    color: var(--ink);
    text-decoration: none;
    transition:
        box-shadow 0.2s ease,
        transform 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-glow);
}

.card-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    flex-shrink: 0;
    border-radius: 50%;
    background: var(--brand-gradient);
    color: #fff;
}

.card-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.card-title {
    font-size: 15px;
    font-weight: 700;
}

.card-url {
    font-size: 13px;
    color: var(--ink-soft);
    overflow-wrap: anywhere;
}

.card-arrow {
    flex-shrink: 0;
    color: var(--brand-violet);
}
</style>

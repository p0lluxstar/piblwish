<template>
    <footer class="footer">
        <div class="container">
            <span class="footer-copy">© 2026 PiblWish</span>
            <div class="footer-links">
                <a href="#">Помощь</a>
                <a href="#">Контакты</a>
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

.container {
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
    gap: 18px;
}

.footer-links a {
    font-size: 12px;
    font-weight: 600;
    color: var(--ink-soft);
    text-decoration: none;
    transition: color 0.18s ease;
}

.footer-links a:hover {
    color: var(--brand-pink);
}
</style>

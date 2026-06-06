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
    border-top: 1px solid rgba(226, 195, 211, 0.35);
    padding: 14px 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #fff7fb 0%, #f5f3ff 100%);
}

.container {
    display: flex;
    width: 1200px;
    justify-content: space-between;
    align-items: center;
}

.footer-copy {
    font-size: 11px;
    color: #c5a8d4;
}

.footer-links {
    display: flex;
    gap: 14px;
}

.footer-links a {
    font-size: 11px;
    color: #c5a8d4;
    text-decoration: none;
}
</style>

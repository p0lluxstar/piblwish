<script setup lang="ts"></script>

<template>
    <header class="header">
        <div class="container">
            <div class="logo">
                <div class="logo-icon">
                    ♡
                    <span class="logo-spark">✦</span>
                </div>
                <span class="logo-title">PiblWish</span>
            </div>
            <div class="avatar">А</div>
        </div>
    </header>
</template>

<style scoped>
.header {
    background: linear-gradient(135deg, #fff7fb 0%, #fff1f5 48%, #f5f3ff 100%);
    border-bottom: 1px solid rgba(226, 195, 211, 0.45);
    padding: 0 28px;
    height: 60px;
    display: flex;
    justify-content: center;
    align-items: center;
}
.container {
    display: flex;
    width: 1200px;
    justify-content: space-between;
}

.logo {
    display: flex;
    align-items: center;
    gap: 10px;
}
.logo-icon {
    width: 34px;
    height: 34px;
    border-radius: 12px;
    background: linear-gradient(135deg, #ff8fab, #c4b5fd);
    display: grid;
    place-items: center;
    font-size: 18px;
    position: relative;
}
.logo-spark {
    position: absolute;
    top: 3px;
    right: 4px;
    font-size: 8px;
    color: #fff7ad;
}
.logo-title {
    font-size: 19px;
    font-weight: 600;
    color: #3b2146;
    letter-spacing: -0.04em;
}
.avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: linear-gradient(135deg, #ff8fab, #c4b5fd);
    display: grid;
    place-items: center;
    font-size: 12px;
    font-weight: 600;
    color: #fff;
}
</style>

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
    background: white;
    color: #d9467c;
    font-weight: bold;
    width: 30px;
    height: 30px;
    margin-bottom: 20px;

    &:hover {
        opacity: 0.8;
    }
}
</style>

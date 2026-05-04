<script setup lang="ts">
const lists = [
    {
        title: 'День рождения',
        items: [
            { label: 'Книга «Мастер и Маргарита»', checked: true, who: 'Аня' },
            { label: 'Свеча с ароматом ванили', checked: false, who: '' },
            { label: 'Чайный набор', checked: false, who: '' },
            { label: 'Тёплые носки', checked: true, who: 'Миша' },
        ],
    },
    {
        title: 'На новый год',
        items: [
            { label: 'Уютный плед', checked: false, who: '' },
            { label: 'Шоколадный набор', checked: true, who: 'Саша' },
            { label: 'Фонарик для чтения', checked: false, who: '' },
            { label: 'Настольная игра', checked: false, who: '' },
            { label: 'Карты таро', checked: true, who: 'Лена' },
        ],
    },
    {
        title: 'Просто мечты',
        items: [
            { label: 'Курс по акварели', checked: false, who: '' },
            { label: 'Поездка в Лиссабон', checked: false, who: '' },
            { label: 'Камера Fujifilm X100', checked: false, who: '' },
        ],
    },
];
</script>

<template>
    <div class="top">
        <div>
            <div class="heading">Мои списки</div>
            <div class="sub">
                {{ lists.length }} списка · выбирай что хочешь
            </div>
        </div>
        <button class="add-btn">+ Новый список</button>
    </div>

    <div class="grid" id="grid">
        <div v-for="(list, listIndex) in lists" :key="listIndex" class="card">
            <div class="card-header">
                <span class="card-title">{{ list.title }}</span>
                <!-- <span class="card-count">
                    {{ getCheckedCount(list.items) }}/{{ list.items.length }}
                </span> -->
            </div>

            <div
                v-for="(item, itemIndex) in list.items"
                :key="itemIndex"
                class="item"
            >
                <label class="checkbox-wrapper">
                    <input
                        type="checkbox"
                        v-model="item.checked"
                        class="checkbox-input"
                    />
                    <span class="checkbox-custom"></span>
                </label>

                <span :class="['item-label', { 'checked-text': item.checked }]">
                    {{ item.label }}
                </span>
            </div>

            <div class="card-progress">
                <!-- <div
                    class="progress-fill"
                    :style="{ width: getProgressPercent(list.items) + '%' }"
                ></div> -->
            </div>
        </div>
    </div>
</template>

<style scoped>
.top {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 22px;
}
.heading {
    font-size: 20px;
    font-weight: 600;
    color: #3b2146;
    letter-spacing: -0.03em;
}
.sub {
    font-size: 12px;
    color: #b08cbe;
    margin-top: 2px;
}
.add-btn {
    background: linear-gradient(135deg, #ff8fab, #c4b5fd);
    border: none;
    color: #fff;
    padding: 7px 16px;
    border-radius: 18px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    font-family: 'DM Sans', sans-serif;
}
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px;
}
.card {
    background: linear-gradient(145deg, #fff 60%, #fff7fd);
    border: 1px solid rgba(226, 195, 211, 0.5);
    border-radius: 18px;
    padding: 18px;
}
.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
}
.card-title {
    font-size: 14px;
    font-weight: 600;
    color: #3b2146;
}
.card-count {
    font-size: 11px;
    color: #c5a4d8;
    background: rgba(196, 181, 253, 0.15);
    padding: 2px 8px;
    border-radius: 10px;
}
.item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 7px 0;
    border-bottom: 1px solid rgba(226, 195, 211, 0.25);
    cursor: pointer;
    user-select: none;
}
.item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
.item:first-of-type {
    padding-top: 0;
}

.item-label {
    font-size: 13px;
    color: #4a3356;
    transition: color 0.15s;
    line-height: 1.35;
}
.item-label.done {
    color: #c5a4d8;
    text-decoration: line-through;
}

.card-progress {
    margin-top: 14px;
    height: 3px;
    background: rgba(196, 181, 253, 0.2);
    border-radius: 3px;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #ff8fab, #c4b5fd);
    border-radius: 3px;
    transition: width 0.3s;
}

.checkbox-wrapper {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
}

.checkbox-input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.checkbox-custom {
    width: 18px;
    height: 18px;
    border-radius: 6px;
    border: 1.5px solid rgba(196, 181, 253, 0.6);
    display: grid;
    place-items: center;
    flex-shrink: 0;
    transition: all 0.15s;
    background: transparent;
    position: relative;
    cursor: pointer;
}

/* Отмеченное состояние */
.checkbox-input:checked + .checkbox-custom {
    background: linear-gradient(135deg, #ff8fab, #c4b5fd);
    border-color: transparent;
}

/* Галочка */
.checkbox-input:checked + .checkbox-custom::after {
    content: '✓';
    color: white;
    font-size: 12px;
    font-weight: bold;
}

/* Hover эффект */
.checkbox-custom:hover {
    border-color: #ff8fab;
    transform: scale(1.05);
}
</style>

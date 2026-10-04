<script setup lang="ts">
import {
    Copy,
    CopyPlus,
    EyeOff,
    FileEdit,
    Gift,
    Heart,
    Link,
    ListPlus,
    Share2,
    Trash2,
    UserCog,
    UserPlus,
    Users,
} from '@lucide/vue';
import type { Component } from 'vue';

interface HelpSection {
    id: string;
    title: string;
    icon: Component;
}

// Оглавление: id совпадают с якорями разделов ниже
const SECTIONS: HelpSection[] = [
    { id: 'start', title: 'Регистрация и вход', icon: UserPlus },
    { id: 'create', title: 'Создание списка', icon: ListPlus },
    { id: 'manage', title: 'Мои списки', icon: FileEdit },
    { id: 'share', title: 'Как поделиться', icon: Share2 },
    { id: 'guests', title: 'Для гостей', icon: Users },
    { id: 'surprise', title: 'Режим сюрприза', icon: EyeOff },
    { id: 'account', title: 'Настройки аккаунта', icon: UserCog },
];

interface FaqItem {
    question: string;
    answer: string;
}

const FAQ: FaqItem[] = [
    {
        question: 'Нужна ли гостям регистрация?',
        answer: 'Нет. Список по ссылке открывается без входа в аккаунт, и выбрать подарок может любой, у кого есть ссылка.',
    },
    {
        question: 'Может ли один подарок выбрать несколько гостей?',
        answer: 'Нет. Как только гость сохранил выбор, позиция становится недоступной для остальных. Если два гостя отмечают одну позицию одновременно, сохранится выбор того, кто успел первым, а второй увидит сообщение об ошибке.',
    },
    {
        question: 'Что будет с выбором гостей, если я отредактирую список?',
        answer: 'Выбор сохраняется за теми позициями, которые остались в списке, даже если вы изменили их описание, ссылку или порядок. Если удалить позицию, вместе с ней удаляется и выбор гостя.',
    },
    {
        question: 'Можно ли пользоваться одним списком повторно?',
        answer: 'Да. Сделайте дубликат: в копии будут те же позиции, цвет и приоритеты, но без отметок гостей. У копии появится собственная ссылка.',
    },
    {
        question: 'Почему ссылка на товар не сохраняется?',
        answer: 'Ссылка должна быть полным адресом страницы, например https://example.com/item. Проще всего скопировать её из адресной строки браузера на странице товара.',
    },
    {
        question: 'Не приходит письмо с кодом. Что делать?',
        answer: 'Проверьте папку «Спам» и правильность адреса. Код при регистрации действует 2 минуты, код для восстановления пароля — 10 минут. Если срок истёк, запросите новый код. Слишком частые запросы временно блокируются, поэтому подождите минуту перед повторной попыткой.',
    },
];
</script>

<template>
    <div class="help">
        <div class="heading">Помощь</div>
        <p class="sub">
            PiblWish — сервис списков желаний. Вы составляете список подарков и
            отправляете ссылку друзьям, а они отмечают, что собираются подарить.
            Так подарки не повторяются, а вы получаете то, что действительно
            хотите.
        </p>

        <nav class="toc" aria-label="Разделы помощи">
            <a
                v-for="section in SECTIONS"
                :key="section.id"
                :href="`#${section.id}`"
                class="toc-link"
            >
                <component :is="section.icon" :size="14" />
                {{ section.title }}
            </a>
        </nav>

        <div class="sections">
            <section id="start" class="card">
                <h2 class="card-title">
                    <span class="card-icon"><UserPlus :size="16" /></span>
                    Регистрация и вход
                </h2>
                <ol class="steps">
                    <li>
                        На главной странице нажмите «Регистрация» и укажите имя
                        пользователя, email и пароль.
                    </li>
                    <li>
                        На почту придёт письмо с кодом подтверждения. Введите
                        его на открывшейся странице. Код действует
                        <b>2 минуты</b>
                        ; если он истёк, отправьте код повторно.
                    </li>
                    <li>
                        После подтверждения аккаунт активирован. Входите по
                        email и паролю.
                    </li>
                </ol>
                <p class="note">
                    Имя пользователя видят гости на странице вашего списка: «
                    <i>Имя</i>
                    делится с вами списком подарков».
                </p>
            </section>

            <section id="create" class="card">
                <h2 class="card-title">
                    <span class="card-icon"><ListPlus :size="16" /></span>
                    Создание списка
                </h2>
                <p>
                    В разделе «Мои списки» нажмите «Новый список» и заполните
                    форму:
                </p>
                <ul class="list">
                    <li>
                        <b>Название</b>
                        — повод или тема, например «День рождения» или «Новый
                        год».
                    </li>
                    <li>
                        <b>Цвет</b>
                        — фон карточки списка. Помогает быстро находить нужный
                        список среди остальных.
                    </li>
                    <li>
                        <b>Желания</b>
                        — позиции списка. У каждой позиции есть описание
                        (обязательно) и ссылка на товар (необязательно). Кнопка
                        «+ Добавить желание» добавляет новую позицию, значок
                        корзины удаляет её, а стрелки меняют порядок.
                    </li>
                    <li>
                        <b>Приоритет</b>
                        — сердечки над описанием позиции. Покажите гостям, что
                        для вас важнее всего:
                    </li>
                </ul>
                <div class="priorities">
                    <span class="priority">
                        <span class="hearts">
                            <Heart :size="12" />
                        </span>
                        Было бы неплохо
                    </span>
                    <span class="priority">
                        <span class="hearts">
                            <Heart :size="12" />
                            <Heart :size="12" />
                        </span>
                        Хочу
                    </span>
                    <span class="priority">
                        <span class="hearts">
                            <Heart :size="12" />
                            <Heart :size="12" />
                            <Heart :size="12" />
                        </span>
                        Очень хочу
                    </span>
                </div>
                <p class="note">
                    Повторный щелчок по выбранному сердечку снимает приоритет. В
                    списке должна быть хотя бы одна позиция.
                </p>
            </section>

            <section id="manage" class="card">
                <h2 class="card-title">
                    <span class="card-icon"><FileEdit :size="16" /></span>
                    Мои списки
                </h2>
                <p>
                    Каждый список отображается карточкой. В правом верхнем углу
                    карточки находятся кнопки действий:
                </p>
                <ul class="actions">
                    <li>
                        <span class="action-icon"><FileEdit :size="14" /></span>
                        <span>
                            <b>Редактировать</b>
                            — изменить название, цвет, позиции и режим сюрприза.
                        </span>
                    </li>
                    <li>
                        <span class="action-icon"><CopyPlus :size="14" /></span>
                        <span>
                            <b>Дублировать</b>
                            — создать копию списка без отметок гостей, например
                            для следующего праздника.
                        </span>
                    </li>
                    <li>
                        <span class="action-icon"><Link :size="14" /></span>
                        <span>
                            <b>Скопировать ссылку</b>
                            — ссылка для гостей попадает в буфер обмена.
                        </span>
                    </li>
                    <li>
                        <span class="action-icon"><Trash2 :size="14" /></span>
                        <span>
                            <b>Удалить</b>
                            — список удаляется вместе со всеми позициями и
                            ссылкой, восстановить его нельзя.
                        </span>
                    </li>
                </ul>
                <p>
                    Позиции, которые уже выбрали гости, отмечены значком
                    <Gift :size="12" class="inline-icon" />
                    и выделены серым. Внизу карточки показано, какая доля
                    подарков выбрана, и дата создания списка.
                </p>
                <p class="note">
                    Если списков несколько, их можно отсортировать по дате или
                    по названию; повторный щелчок меняет направление сортировки.
                    Кнопка «Обновить» загружает свежие отметки гостей. Карточки
                    выводятся по 12, остальные открывает кнопка «Показать ещё».
                </p>
            </section>

            <section id="share" class="card">
                <h2 class="card-title">
                    <span class="card-icon"><Share2 :size="16" /></span>
                    Как поделиться
                </h2>
                <ol class="steps">
                    <li>
                        На карточке списка нажмите кнопку
                        <Link :size="12" class="inline-icon" />
                        — появится подсказка «Ссылка скопирована».
                    </li>
                    <li>
                        Отправьте ссылку друзьям в мессенджере, по почте или
                        любым другим способом.
                    </li>
                    <li>
                        Гости открывают список без регистрации и отмечают
                        подарки, а вы видите их выбор у себя в карточке.
                    </li>
                </ol>
                <p class="note">
                    Ссылка не меняется при редактировании списка, поэтому её не
                    нужно отправлять заново после правок.
                </p>
            </section>

            <section id="guests" class="card">
                <h2 class="card-title">
                    <span class="card-icon"><Users :size="16" /></span>
                    Для гостей
                </h2>
                <p>Если вам прислали ссылку на список подарков:</p>
                <ol class="steps">
                    <li>
                        Отметьте галочкой подарки, которые собираетесь подарить.
                        Если у позиции есть ссылка, по ней можно перейти на
                        страницу товара.
                    </li>
                    <li>
                        Нажмите кнопку сохранения в нижней части списка. После
                        этого выбранные позиции станут недоступны другим гостям.
                    </li>
                    <li>
                        Сохраните ссылку для отмены выбора из появившегося
                        уведомления
                        <Copy :size="12" class="inline-icon" />
                        , если планируете открыть список с другого устройства.
                    </li>
                </ol>
                <p>
                    <b>Как отменить выбор.</b>
                    В том же браузере рядом с вашими позициями отображается
                    кнопка «Отменить». На другом устройстве откройте список по
                    сохранённой ссылке для отмены: кнопка «Отменить» появится и
                    там.
                </p>
                <p class="note">
                    Позиции, выбранные другими гостями, выделены серым и
                    отмечены значком
                    <Gift :size="12" class="inline-icon" />
                    . Если владелец указал приоритеты, подарки можно упорядочить
                    «По приоритету», чтобы сначала увидеть самые желанные.
                </p>
            </section>

            <section id="surprise" class="card">
                <h2 class="card-title">
                    <span class="card-icon"><EyeOff :size="16" /></span>
                    Режим сюрприза
                </h2>
                <p>
                    Режим включается переключателем при создании или
                    редактировании списка. В этом режиме вы не видите, какие
                    подарки выбрали гости, и праздник остаётся сюрпризом. Гости
                    при этом видят отметки друг друга, поэтому подарки
                    по-прежнему не повторяются.
                </p>
                <p class="note">
                    Список в режиме сюрприза отмечен на карточке значком
                    <EyeOff :size="12" class="inline-icon" />
                    , а шкала выбранных подарков скрыта. Если выключить режим,
                    выбор гостей снова станет виден.
                </p>
            </section>

            <section id="account" class="card">
                <h2 class="card-title">
                    <span class="card-icon"><UserCog :size="16" /></span>
                    Настройки аккаунта
                </h2>
                <p>
                    Настройки открываются кнопкой-шестерёнкой у аватара в шапке
                    сайта. Здесь можно:
                </p>
                <ul class="list">
                    <li>
                        <b>сменить пароль</b>
                        — понадобится текущий пароль;
                    </li>
                    <li>
                        <b>удалить аккаунт</b>
                        — вместе с аккаунтом безвозвратно удаляются все ваши
                        списки.
                    </li>
                </ul>
                <p>
                    <b>Если вы забыли пароль,</b>
                    на странице входа нажмите «Забыли пароль?» и укажите email.
                    На почту придёт код, который действует
                    <b>10 минут</b>
                    ; после его ввода задайте новый пароль.
                </p>
            </section>

            <section class="card">
                <h2 class="card-title">Частые вопросы</h2>
                <details v-for="item in FAQ" :key="item.question" class="faq">
                    <summary>{{ item.question }}</summary>
                    <p>{{ item.answer }}</p>
                </details>
                <p class="note">
                    Не нашли ответ? Напишите нам — адреса указаны на странице
                    <router-link to="/contacts">«Контакты»</router-link>
                    .
                </p>
            </section>
        </div>
    </div>
</template>

<style scoped>
/* Тот же стиль, что у заголовка «Мои списки» в дашборде */
.heading {
    font-size: 26px;
    font-weight: 800;
    color: var(--ink);
    letter-spacing: -0.03em;
}

.help {
    max-width: 820px;
}

.sub {
    margin-top: 8px;
    font-size: 14px;
    line-height: 1.6;
    color: var(--ink-soft);
}

.toc {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 22px 0 26px;
}

.toc-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 20px;
    border: 1.5px solid var(--surface-border);
    background: #fff;
    color: var(--brand-violet);
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: box-shadow 0.2s ease;
}

.toc-link:hover {
    box-shadow: var(--shadow-glow);
}

.sections {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.card {
    /* Отступ при переходе по якорю из оглавления */
    scroll-margin-top: 24px;
    padding: 22px 24px;
    border-radius: var(--radius-lg);
    border: 1px solid var(--surface-border);
    background: var(--surface);
    font-size: 14px;
    line-height: 1.6;
}

.card > p,
.card > ul,
.card > ol,
.card > .priorities {
    margin-top: 10px;
}

.card-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 4px;
    font-size: 17px;
    font-weight: 700;
    letter-spacing: -0.01em;
}

.card-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    flex-shrink: 0;
    border-radius: 50%;
    background: var(--brand-gradient);
    color: #fff;
}

.steps,
.list {
    padding-left: 22px;
}

.steps {
    list-style: decimal;
}

.list {
    list-style: disc;
}

.steps li + li,
.list li + li {
    margin-top: 6px;
}

.steps li::marker {
    color: var(--brand-violet);
    font-weight: 700;
}

.list li::marker {
    color: var(--brand-pink);
}

.actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.actions li {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.action-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    flex-shrink: 0;
    border-radius: 8px;
    border: 1px solid var(--surface-border);
    background: #fff;
    color: var(--brand-violet);
}

.priorities {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.priority {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 5px 12px;
    border-radius: 20px;
    background: #fff;
    border: 1px solid var(--surface-border);
    font-size: 13px;
}

.hearts {
    display: inline-flex;
    gap: 2px;
    color: var(--brand-pink);
}

.hearts svg {
    fill: currentColor;
}

.inline-icon {
    display: inline;
    vertical-align: -1px;
    color: var(--brand-violet);
}

.note {
    color: var(--ink-soft);
    font-size: 13px;
}

.note a {
    color: var(--brand-violet);
    font-weight: 600;
}

.faq {
    margin-top: 10px;
    border-bottom: 1px solid var(--surface-border);
    padding-bottom: 10px;
}

.faq summary {
    cursor: pointer;
    font-weight: 600;
    list-style-position: inside;
}

.faq summary::marker {
    color: var(--brand-violet);
}

.faq p {
    margin-top: 6px;
    color: var(--ink-soft);
}

@media (max-width: 599px) {
    .card {
        padding: 18px 16px;
    }
}
</style>

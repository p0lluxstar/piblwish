<?php

namespace App\Http\Requests\Wishlist;

use App\Enums\WishlistColor;
use App\Enums\WishlistItemPriority;
use App\Enums\WishlistType;
use App\Models\Wishlist;
use App\Rules\AllowedFundUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWishlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Тип списка после создания не меняется, поэтому поля type здесь нет.
        // Ссылки, цену, приоритет и режим сюрприза у списка дел очищает WishlistService.
        // У заметки WishlistService изменяет только цвет и текст, у списков — всё, кроме текста.
        // isShared WishlistService не учитывает у заметки, а guestsCanCheck
        // и guestNameRequired учитывает только у списка дел.
        // dueDate у заметки WishlistService не сохраняет; null убирает дату.
        // Приоритет и режим сюрприза у сбора WishlistService очищает, а ссылки
        // и целевую сумму проверяют правила ниже по типу изменяемого списка
        $isFund = $this->isFund();

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'content' => ['sometimes', 'required', 'string', 'max:5000'],
            'color' => ['sometimes', Rule::enum(WishlistColor::class)],
            'hideSelections' => ['sometimes', 'boolean'],
            'isShared' => ['sometimes', 'boolean'],
            'guestsCanCheck' => ['sometimes', 'boolean'],
            'guestNameRequired' => ['sometimes', 'boolean'],
            // Границы — как в CreateWishlistRequest
            'dueDate' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before:2100-01-01'],
            'items' => ['sometimes', 'array'],
            // id существующей позиции: по нему в режиме сюрприза сохраняется выбор гостей
            'items.*.id' => ['sometimes', 'nullable', 'string'],
            'items.*.label' => ['required_with:items', 'string', 'max:1000'],
            'items.*.isSelected' => ['sometimes', 'boolean'],
            // Не больше трёх ссылок, только http(s); у цели сбора — одна ссылка
            // на разрешённую платформу: см. CreateWishlistRequest
            'items.*.urls' => $isFund
                ? ['required_with:items', 'array', 'size:1']
                : ['nullable', 'array', 'max:3'],
            'items.*.urls.*' => $isFund
                ? ['bail', 'required', 'string', 'max:2048', new AllowedFundUrl]
                : ['nullable', 'string', 'max:2048', 'url:http,https'],
            'items.*.priority' => ['nullable', Rule::enum(WishlistItemPriority::class)],
            // Стоимость в целых рублях: см. CreateWishlistRequest
            'items.*.price' => [
                'nullable',
                'integer',
                'min:0',
                'max:'.($isFund ? CreateWishlistRequest::MAX_FUND_PRICE : CreateWishlistRequest::MAX_PRICE),
            ],
        ];
    }

    // Тип изменяемого списка нужен для правил ссылок и целевой суммы сбора.
    // Чужой или несуществующий список правила не уточняет: на него ответит 404 сервис
    private function isFund(): bool
    {
        return $this->wishlistType() === WishlistType::Fund;
    }

    private ?WishlistType $wishlistType = null;

    private bool $wishlistTypeLoaded = false;

    private function wishlistType(): ?WishlistType
    {
        if (! $this->wishlistTypeLoaded) {
            $this->wishlistType = Wishlist::query()
                ->where('user_id', $this->user()?->id)
                ->whereKey($this->route('id'))
                ->value('type');
            $this->wishlistTypeLoaded = true;
        }

        return $this->wishlistType;
    }

    public function messages(): array
    {
        return ($this->isFund() ? CreateWishlistRequest::fundItemMessages() : []) + [
            'content.required' => 'Текст заметки обязателен',
            'content.max' => 'Текст заметки не может быть длиннее 5000 символов',
            'color.enum' => 'Недопустимый цвет списка',
            'dueDate.date_format' => 'Некорректная дата',
            'dueDate.after_or_equal' => 'Некорректная дата',
            'dueDate.before' => 'Некорректная дата',
            'items.required' => 'Добавьте хотя бы один элемент',
            'items.*.label.required_with' => 'Описание элемента обязательно',
            'items.*.urls.array' => 'Некорректный список ссылок на товар',
            'items.*.urls.max' => 'Можно указать не больше трёх ссылок на товар',
            'items.*.urls.*.string' => 'Некорректная ссылка на товар',
            'items.*.urls.*.url' => 'Некорректная ссылка на товар',
            'items.*.urls.*.max' => 'Ссылка на товар слишком длинная',
            'items.*.priority.enum' => 'Недопустимый приоритет позиции',
            'items.*.price.integer' => 'Стоимость должна быть целым числом рублей',
            'items.*.price.min' => 'Стоимость не может быть отрицательной',
            'items.*.price.max' => 'Стоимость не может превышать 10 000 000 ₽',
        ];
    }
}

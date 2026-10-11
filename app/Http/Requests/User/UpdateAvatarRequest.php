<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAvatarRequest extends FormRequest
{
    // Наибольший размер файла в килобайтах. Фронтенд отправляет уже кадрированное
    // и уменьшенное изображение, поэтому лимит нужен только для прямых запросов к API
    public const MAX_SIZE_KB = 2048;

    // Наибольшая сторона изображения в пикселях. GD распаковывает изображение
    // в память целиком (4 байта на пиксель), поэтому без лимита небольшой по размеру
    // файл с огромными размерами исчерпал бы memory_limit. 4096 пропускает снимки
    // 12-мегапиксельных камер (4032×3024)
    public const MAX_DIMENSION = 4096;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => [
                'required',
                'file',
                // SVG правило image без allow_svg не пропускает
                'image',
                'mimes:jpeg,png,webp,gif',
                'max:'.self::MAX_SIZE_KB,
                'dimensions:max_width='.self::MAX_DIMENSION.',max_height='.self::MAX_DIMENSION,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.required' => 'Выберите фотографию',
            // Файл не дошёл до приложения, обычно из-за upload_max_filesize
            'avatar.uploaded' => 'Не удалось загрузить файл. Возможно, он слишком большой',
            'avatar.file' => 'Выберите фотографию',
            'avatar.image' => 'Поддерживаются изображения JPEG, PNG, WebP и GIF',
            'avatar.mimes' => 'Поддерживаются изображения JPEG, PNG, WebP и GIF',
            'avatar.max' => 'Файл должен быть не больше 2 МБ',
            'avatar.dimensions' => 'Изображение должно быть не больше '.self::MAX_DIMENSION.'×'.self::MAX_DIMENSION.' пикселей',
        ];
    }
}

// Формат ошибки JSON-запроса, см. обработчик исключений в bootstrap/app.php
export type ApiErrorResponse = {
    success: false;
    statusCode: number;
    data: {
        message: string;
        errors: Record<string, string[]> | null;
    };
};

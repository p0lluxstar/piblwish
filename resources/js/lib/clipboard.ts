// Запасной способ копирования для небезопасного контекста (HTTP), где Clipboard API недоступен
const copyWithFallback = (text: string): boolean => {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    textarea.style.top = '0';
    textarea.setAttribute('aria-hidden', 'true');

    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();

    try {
        return document.execCommand('copy');
    } catch {
        return false;
    } finally {
        document.body.removeChild(textarea);
    }
};

export const copyToClipboard = async (text: string): Promise<boolean> => {
    if (window.navigator.clipboard && window.isSecureContext) {
        try {
            await window.navigator.clipboard.writeText(text);
            return true;
        } catch {
            // Пробуем запасной способ ниже
        }
    }

    return copyWithFallback(text);
};

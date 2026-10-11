export type LoginPayload = {
    email: string;
    password: string;
    // Сохранить вход после истечения сессии (cookie remember_web_*)
    remember: boolean;
};

export type RegisterPayload = {
    username: string;
    email: string;
    password: string;
    password_confirmation: string;
};

export type ChangePasswordPayload = {
    current_password: string;
    password: string;
    password_confirmation: string;
};

export type RequestEmailChangePayload = {
    email: string;
    current_password: string;
};

export type ConfirmEmailChangePayload = {
    code: string;
};

export type ForgotPasswordPayload = {
    email: string;
};

export type ResetPasswordPayload = {
    email: string;
    code: string;
    password: string;
    password_confirmation: string;
};

export type LoginPayload = {
    email: string;
    password: string;
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

export type ForgotPasswordPayload = {
    email: string;
};

export type ResetPasswordPayload = {
    email: string;
    code: string;
    password: string;
    password_confirmation: string;
};

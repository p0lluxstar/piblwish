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

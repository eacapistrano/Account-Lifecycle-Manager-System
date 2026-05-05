export type AuthUser = {
  id: number;
  name: string;
  email: string;
  role: string;
  permissions: string[];
};

export type LoginPayload = {
  email: string;
  password: string;
};

export type LoginResponse = {
  token: string;
  user: AuthUser;
};

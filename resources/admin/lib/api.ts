import axios, { AxiosError } from "axios";
import type {
  AddOn,
  AccountSettings,
  DashboardAnalytics,
  DashboardAnalyticsSessionPageViewsResponse,
  DashboardAnalyticsSessionsResponse,
  DashboardAnalyticsSessionsFilters,
  DashboardAnalyticsRange,
  AdminUser,
  DashboardSummary,
  OrderRequest,
  Vehicle,
  VehicleDraft,
  VehicleDiscount
} from "../types";

type ApiEnvelope<T> = {
  success: boolean;
  message?: string;
  data: T;
};

export type Paginated<T> = {
  items: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

export const ADMIN_TOKEN_KEY = "keys_admin_token";

const api = axios.create({
  baseURL: "/api/admin",
  headers: {
    "Content-Type": "application/json"
  }
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem(ADMIN_TOKEN_KEY);

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

export function getApiErrorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const axiosError = error as AxiosError<{ message?: string }>;
    const message = axiosError.response?.data?.message;

    if (typeof message === "string" && message.trim() !== "") {
      return message;
    }
  }

  if (error instanceof Error && error.message.trim() !== "") {
    return error.message;
  }

  return "Request failed. Please try again.";
}

export async function adminLogin(username: string, password: string): Promise<{ token: string; user: AdminUser }> {
  const response = await api.post<ApiEnvelope<{ token: string; user: AdminUser }>>("/login", {
    username,
    password
  });

  return response.data.data;
}

export async function adminMe(): Promise<AdminUser> {
  const response = await api.get<ApiEnvelope<{ user: AdminUser }>>("/me");
  return response.data.data.user;
}

export async function getAccountSettings(): Promise<AccountSettings> {
  const response = await api.get<ApiEnvelope<AccountSettings>>("/settings/account");
  return response.data.data;
}

export async function updateAccountProfile(payload: {
  username: string;
  email: string | null;
}): Promise<AdminUser> {
  const response = await api.put<ApiEnvelope<{ user: AdminUser }>>("/settings/account/profile", payload);
  return response.data.data.user;
}

export async function updateAccountPassword(payload: {
  current_password: string;
  password: string;
  password_confirmation: string;
}): Promise<void> {
  await api.put("/settings/account/password", payload);
}

export async function adminLogout(): Promise<void> {
  await api.post("/logout");
}

export async function getDashboardSummary(): Promise<DashboardSummary> {
  const response = await api.get<ApiEnvelope<DashboardSummary>>("/dashboard/summary");
  return response.data.data;
}

export async function getDashboardAnalytics(range: DashboardAnalyticsRange = "90d"): Promise<DashboardAnalytics> {
  const response = await api.get<ApiEnvelope<DashboardAnalytics>>("/dashboard/analytics", {
    params: {
      range
    }
  });

  return response.data.data;
}

export async function getDashboardAnalyticsSessions(
  date: string,
  params?: Partial<DashboardAnalyticsSessionsFilters> & {
    per_page?: number;
    page?: number;
  }
): Promise<DashboardAnalyticsSessionsResponse> {
  const response = await api.get<ApiEnvelope<DashboardAnalyticsSessionsResponse>>(
    `/dashboard/analytics/${date}/sessions`,
    {
      params
    }
  );

  return response.data.data;
}

export async function getDashboardAnalyticsSessionPageViews(
  sessionId: number,
  params?: {
    date?: string;
    per_page?: number;
    page?: number;
  }
): Promise<DashboardAnalyticsSessionPageViewsResponse> {
  const response = await api.get<ApiEnvelope<DashboardAnalyticsSessionPageViewsResponse>>(
    `/dashboard/analytics/sessions/${sessionId}/page-views`,
    {
      params
    }
  );

  return response.data.data;
}

export async function getVehicles(): Promise<Vehicle[]> {
  const response = await api.get<ApiEnvelope<Vehicle[]>>("/vehicles");
  return response.data.data;
}

function appendVehicleField(formData: FormData, key: string, value: unknown): void {
  if (value === undefined || key === "id" || key === "image_url") {
    return;
  }

  if (value === null) {
    formData.append(key, "");
    return;
  }

  if (typeof value === "boolean") {
    formData.append(key, value ? "1" : "0");
    return;
  }

  if (typeof value === "number") {
    formData.append(key, String(value));
    return;
  }

  formData.append(key, String(value));
}

function toVehicleFormData(payload: VehicleDraft, methodOverride?: "PUT"): FormData {
  const formData = new FormData();

  Object.entries(payload).forEach(([key, value]) => {
    if (key === "image") {
      if (value instanceof File) {
        formData.append("image", value);
      }

      return;
    }

    appendVehicleField(formData, key, value);
  });

  if (methodOverride) {
    formData.append("_method", methodOverride);
  }

  return formData;
}

export async function createVehicle(payload: VehicleDraft): Promise<Vehicle> {
  const response = await api.post<ApiEnvelope<Vehicle>>("/vehicles", toVehicleFormData(payload), {
    headers: {
      "Content-Type": "multipart/form-data"
    }
  });

  return response.data.data;
}

export async function updateVehicle(id: number, payload: VehicleDraft): Promise<Vehicle> {
  const response = await api.post<ApiEnvelope<Vehicle>>(`/vehicles/${id}`, toVehicleFormData(payload, "PUT"), {
    headers: {
      "Content-Type": "multipart/form-data"
    }
  });

  return response.data.data;
}

export async function deleteVehicle(id: number): Promise<void> {
  await api.delete(`/vehicles/${id}`);
}

export async function getAddOns(): Promise<AddOn[]> {
  const response = await api.get<ApiEnvelope<AddOn[]>>("/add-ons");
  return response.data.data;
}

export async function createAddOn(payload: Partial<AddOn>): Promise<AddOn> {
  const response = await api.post<ApiEnvelope<AddOn>>("/add-ons", payload);
  return response.data.data;
}

export async function updateAddOn(id: number, payload: Partial<AddOn>): Promise<AddOn> {
  const response = await api.put<ApiEnvelope<AddOn>>(`/add-ons/${id}`, payload);
  return response.data.data;
}

export async function deleteAddOn(id: number): Promise<void> {
  await api.delete(`/add-ons/${id}`);
}

export async function getVehicleDiscounts(): Promise<VehicleDiscount[]> {
  const response = await api.get<ApiEnvelope<VehicleDiscount[]>>("/vehicle-discounts");
  return response.data.data;
}

export async function createVehicleDiscount(payload: Partial<VehicleDiscount>): Promise<VehicleDiscount> {
  const response = await api.post<ApiEnvelope<VehicleDiscount>>("/vehicle-discounts", payload);
  return response.data.data;
}

export async function updateVehicleDiscount(id: number, payload: Partial<VehicleDiscount>): Promise<VehicleDiscount> {
  const response = await api.put<ApiEnvelope<VehicleDiscount>>(`/vehicle-discounts/${id}`, payload);
  return response.data.data;
}

export async function deleteVehicleDiscount(id: number): Promise<void> {
  await api.delete(`/vehicle-discounts/${id}`);
}

export async function getOrderRequests(params?: {
  per_page?: number;
  page?: number;
  status?: "all" | "pending" | "confirmed";
  search?: string;
}): Promise<Paginated<OrderRequest>> {
  const response = await api.get<ApiEnvelope<Paginated<OrderRequest>>>("/order-requests", {
    params
  });

  return response.data.data;
}

export async function getOrderRequest(id: number): Promise<OrderRequest> {
  const response = await api.get<ApiEnvelope<OrderRequest>>(`/order-requests/${id}`);
  return response.data.data;
}

export async function updateOrderStatus(id: number, status: "pending" | "confirmed"): Promise<OrderRequest> {
  const response = await api.patch<ApiEnvelope<OrderRequest>>(`/order-requests/${id}/status`, {
    status
  });

  return response.data.data;
}

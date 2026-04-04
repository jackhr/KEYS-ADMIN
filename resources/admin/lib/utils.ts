import { type ClassValue, clsx } from "clsx";
import { twMerge } from "tailwind-merge";
import { CachedResourceEnvelope, PaginationMeta } from "../types";
import { RESOURCE_CACHE_TTL_MS } from "../consts";

export function cn(...inputs: ClassValue[]): string {
  return twMerge(clsx(inputs));
}

export function formatDateTimeDisplay(value: string | null | undefined): string {
  if (!value) {
    return "-";
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  const day = String(date.getDate()).padStart(2, "0");
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const year = date.getFullYear();
  const hours24 = date.getHours();
  const minutes = String(date.getMinutes()).padStart(2, "0");
  const meridiem = hours24 >= 12 ? "pm" : "am";
  const hours12 = ((hours24 + 11) % 12) + 1;

  return `${hours12}:${minutes}${meridiem}, ${day}-${month}-${year}`;
}

export const initialPaginationMeta = (perPage: number): PaginationMeta => ({
  current_page: 1,
  last_page: 1,
  per_page: perPage,
  total: 0
});

export const readCachedResource = <TResource,>(cacheKey: string): TResource | null => {
  if (typeof window === "undefined" || !window.localStorage) {
    return null;
  }

  try {
    const rawValue = window.localStorage.getItem(cacheKey);

    if (!rawValue) {
      return null;
    }

    const parsed = JSON.parse(rawValue) as unknown;

    if (typeof parsed !== "object" || parsed === null) {
      window.localStorage.removeItem(cacheKey);
      return null;
    }

    if (!("value" in parsed) || !("expiresAt" in parsed)) {
      window.localStorage.removeItem(cacheKey);
      return null;
    }

    const cached = parsed as CachedResourceEnvelope<TResource>;

    if (typeof cached.expiresAt !== "number" || !Number.isFinite(cached.expiresAt)) {
      window.localStorage.removeItem(cacheKey);
      return null;
    }

    if (Date.now() >= cached.expiresAt) {
      window.localStorage.removeItem(cacheKey);
      return null;
    }

    return cached.value;
  } catch {
    window.localStorage.removeItem(cacheKey);
    return null;
  }
};

export const writeCachedResource = <TResource,>(cacheKey: string, value: TResource) => {
  if (typeof window === "undefined" || !window.localStorage) {
    return;
  }

  try {
    const payload: CachedResourceEnvelope<TResource> = {
      value,
      expiresAt: Date.now() + RESOURCE_CACHE_TTL_MS
    };
    window.localStorage.setItem(cacheKey, JSON.stringify(payload));
  } catch {
    // Ignore storage quota/privacy mode errors.
  }
};

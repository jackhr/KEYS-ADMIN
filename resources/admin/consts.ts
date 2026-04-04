import {
  BadgePercent,
  CarFront,
  ClipboardList,
  LayoutGrid,
  Settings,
  Tags
} from "lucide-react";
import { type DashboardTabItem } from "./components/dashboard/DashboardTabs";
import { AddOn, ConfirmDialogState, Section, VehicleDiscount, VehicleDraft } from "./types";

export const sectionTabs: DashboardTabItem<Section>[] = [
  { value: "overview", label: "Overview", icon: LayoutGrid },
  { value: "vehicles", label: "Vehicles", icon: CarFront },
  { value: "addons", label: "Add-Ons", icon: Tags },
  { value: "discounts", label: "Discounts", icon: BadgePercent },
  { value: "orders", label: "Orders", icon: ClipboardList },
  { value: "settings", label: "Settings", icon: Settings }
];

export const vehicleTemplate: VehicleDraft = {
  name: "",
  type: "suv",
  slug: "",
  showing: true,
  landing_order: null,
  base_price_XCD: 0,
  base_price_USD: 0,
  insurance: 0,
  times_requested: 0,
  people: 4,
  bags: 2,
  doors: 4,
  four_wd: false,
  ac: true,
  manual: false,
  image: null
};

export const addOnTemplate: Partial<AddOn> = {
  name: "",
  cost: 0,
  description: "",
  abbr: "",
  fixed_price: false
};

export const discountTemplate: Partial<VehicleDiscount> = {
  vehicle_id: 0,
  days: 4,
  price_USD: 0,
  price_XCD: 0
};

export const initialConfirmState: ConfirmDialogState = {
  open: false,
  title: "",
  description: "",
  action: null
};
export const ORDER_REQUESTS_PER_PAGE = 20;
export const RESOURCE_CACHE_TTL_MS = 60 * 1000;
export const RESOURCE_CACHE_KEYS = {
  vehicles: "keys_admin_cache_vehicles",
  addOns: "keys_admin_cache_add_ons",
  discounts: "keys_admin_cache_discounts"
} as const;

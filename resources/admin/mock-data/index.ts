import type {
  AddOn,
  DashboardSummary,
  OrderRequest,
  OrderRequestAddOn,
  Vehicle,
  VehicleDiscount
} from "../types";

import rawAddOns from "../../../mock-data/raw/add_ons.json";
import rawAdminApiTokens from "../../../mock-data/raw/admin_api_tokens.json";
import rawAdminUsers from "../../../mock-data/raw/admin_users.json";
import rawAnalyticsDailyMetrics from "../../../mock-data/raw/analytics_daily_metrics.json";
import rawContactInfo from "../../../mock-data/raw/contact_info.json";
import rawMigrations from "../../../mock-data/raw/migrations.json";
import rawOrderRequestAddOns from "../../../mock-data/raw/order_request_add_ons.json";
import rawOrderRequests from "../../../mock-data/raw/order_requests.json";
import rawVehicleDiscounts from "../../../mock-data/raw/vehicle_discounts.json";
import rawVehicles from "../../../mock-data/raw/vehicles.json";
import rawVisitorPageViews from "../../../mock-data/raw/visitor_page_views.json";
import rawVisitorSessions from "../../../mock-data/raw/visitor_sessions.json";
import rawSummary from "../../../mock-data/raw/_summary.json";

type RawAddOn = {
  id: number;
  name: string;
  cost: number | null;
  description: string;
  abbr: string;
  fixed_price: number | boolean;
};

type RawAdminUser = {
  id: number;
  username: string;
  password_hash: string;
  role: string;
  active: number | boolean;
  last_login_at: string | null;
  created_at: string | null;
  updated_at: string | null;
};

type RawAdminApiToken = {
  id: number;
  admin_user_id: number;
  token_hash: string;
  expires_at: string;
  last_used_at: string | null;
  created_at: string | null;
  updated_at: string | null;
};

type RawContactInfo = {
  id: number;
  first_name: string;
  last_name: string;
  driver_license: string | null;
  hotel: string | null;
  country_or_region: string | null;
  street: string | null;
  town_or_city: string | null;
  state_or_county: string | null;
  phone: string;
  email: string;
};

type RawAnalyticsDailyMetric = {
  id: number;
  snapshot_date: string;
  order_requests_count: number;
  new_customers_count: number;
  active_vehicles_count: number;
  revenue_usd: number;
  growth_rate_pct: number;
  unique_visitors_count: number;
  mobile_visitors_count: number;
  desktop_visitors_count: number;
  page_views_count: number;
  metadata: Record<string, unknown> | null;
  captured_at: string | null;
  created_at: string | null;
  updated_at: string | null;
};

type RawVisitorSession = {
  id: number;
  visitor_id: string;
  session_id: string;
  first_seen_at: string;
  last_seen_at: string;
  entry_path: string | null;
  entry_referrer: string | null;
  ip_address: string | null;
  user_agent: string | null;
  device_type: string;
  is_bot: number | boolean;
  os_name: string | null;
  browser_name: string | null;
  language: string | null;
  timezone: string | null;
  created_at: string | null;
  updated_at: string | null;
};

type RawVisitorPageView = {
  id: number;
  visitor_session_id: number | null;
  visitor_id: string;
  visited_at: string;
  route_path: string;
  full_url: string | null;
  query_string: string | null;
  referrer: string | null;
  user_agent: string | null;
  device_type: string;
  is_bot: number | boolean;
  os_name: string | null;
  browser_name: string | null;
  language: string | null;
  timezone: string | null;
  ip_address: string | null;
  viewport_width: number | null;
  viewport_height: number | null;
  screen_width: number | null;
  screen_height: number | null;
  event_type: string;
  metadata: Record<string, unknown> | null;
  created_at: string | null;
  updated_at: string | null;
};

type RawMigration = {
  id: number;
  migration: string;
  batch: number;
};

type RawOrderRequest = {
  id: number;
  key: string;
  pick_up: string;
  drop_off: string;
  pick_up_location: string;
  drop_off_location: string;
  confirmed: number | boolean;
  status?: string;
  contact_info_id: number;
  sub_total: number;
  car_id: number;
  days: number;
  created_at: string | null;
  updated_at: string | null;
};

type RawOrderRequestAddOn = {
  id: number;
  order_request_id: number;
  add_on_id: number;
  quantity: number;
};

type RawVehicle = {
  id: number;
  name: string;
  type: string;
  slug: string;
  showing: number | boolean;
  landing_order: number | null;
  base_price_XCD: number;
  base_price_USD: number;
  insurance: number;
  times_requested: number;
  people: number;
  bags: number | null;
  doors: number;
  "4wd": number | boolean;
  ac: number | boolean;
  manual: number | boolean;
};

type RawVehicleDiscount = {
  id: number;
  vehicle_id: number;
  price_XCD: number;
  price_USD: number;
  days: number;
};

type RawSummary = {
  source_sql: string;
  generated_at_utc: string;
  tables: Record<string, number>;
};

const toBoolean = (value: number | boolean | null | undefined): boolean => {
  return value === true || value === 1;
};

const VEHICLE_IMAGE_PREFIX = "/gallery/";

const rawAddOnsData = rawAddOns as RawAddOn[];
const rawAdminApiTokensData = rawAdminApiTokens as RawAdminApiToken[];
const rawAdminUsersData = rawAdminUsers as RawAdminUser[];
const rawAnalyticsDailyMetricsData = rawAnalyticsDailyMetrics as RawAnalyticsDailyMetric[];
const rawContactInfoData = rawContactInfo as RawContactInfo[];
const rawMigrationsData = rawMigrations as RawMigration[];
const rawOrderRequestsData = rawOrderRequests as RawOrderRequest[];
const rawOrderRequestAddOnsData = rawOrderRequestAddOns as RawOrderRequestAddOn[];
const rawVehiclesData = rawVehicles as RawVehicle[];
const rawVehicleDiscountsData = rawVehicleDiscounts as RawVehicleDiscount[];
const rawVisitorSessionsData = rawVisitorSessions as RawVisitorSession[];
const rawVisitorPageViewsData = rawVisitorPageViews as RawVisitorPageView[];
const rawSummaryData = rawSummary as RawSummary;

export const mockAddOns: AddOn[] = rawAddOnsData.map((row) => {
  return {
    id: row.id,
    name: row.name,
    cost: row.cost,
    description: row.description,
    abbr: row.abbr,
    fixed_price: toBoolean(row.fixed_price)
  };
});

export const mockVehicles: Vehicle[] = rawVehiclesData.map((row) => {
  return {
    id: row.id,
    name: row.name,
    type: row.type,
    slug: row.slug,
    showing: toBoolean(row.showing),
    landing_order: row.landing_order,
    base_price_XCD: row.base_price_XCD,
    base_price_USD: row.base_price_USD,
    insurance: row.insurance,
    times_requested: row.times_requested,
    people: row.people,
    bags: row.bags,
    doors: row.doors,
    four_wd: toBoolean(row["4wd"]),
    ac: toBoolean(row.ac),
    manual: toBoolean(row.manual),
    image_url: `${VEHICLE_IMAGE_PREFIX}${row.slug}.avif`
  };
});

const vehicleLookup = mockVehicles.reduce<Map<number, Pick<Vehicle, "id" | "name" | "slug" | "type">>>((map, row) => {
  map.set(row.id, {
    id: row.id,
    name: row.name,
    slug: row.slug,
    type: row.type
  });

  return map;
}, new Map<number, Pick<Vehicle, "id" | "name" | "slug" | "type">>());

const contactInfoLookup = rawContactInfoData.reduce<
  Map<
    number,
    {
      id: number;
      first_name: string;
      last_name: string;
      email: string;
      phone: string;
      hotel?: string | null;
      country_or_region?: string | null;
    }
  >
>((map, row) => {
  map.set(row.id, {
    id: row.id,
    first_name: row.first_name,
    last_name: row.last_name,
    email: row.email,
    phone: row.phone,
    hotel: row.hotel,
    country_or_region: row.country_or_region
  });

  return map;
}, new Map());

const addOnLookup = mockAddOns.reduce<Map<number, AddOn>>((map, row) => {
  map.set(row.id, row);
  return map;
}, new Map<number, AddOn>());

const addOnLinksByOrderId = rawOrderRequestAddOnsData.reduce<Map<number, OrderRequestAddOn[]>>((map, row) => {
  const current = map.get(row.order_request_id) ?? [];

  current.push({
    id: row.id,
    add_on_id: row.add_on_id,
    quantity: row.quantity,
    add_on: addOnLookup.get(row.add_on_id) ?? null
  });

  map.set(row.order_request_id, current);
  return map;
}, new Map<number, OrderRequestAddOn[]>());

export const mockOrderRequests: OrderRequest[] = rawOrderRequestsData.map((row) => {
  return {
    id: row.id,
    key: row.key,
    pick_up: row.pick_up,
    drop_off: row.drop_off,
    pick_up_location: row.pick_up_location,
    drop_off_location: row.drop_off_location,
    confirmed: toBoolean(row.confirmed),
    status: row.status === "confirmed" ? "confirmed" : "pending",
    sub_total: row.sub_total,
    days: row.days,
    created_at: row.created_at,
    updated_at: row.updated_at,
    vehicle: vehicleLookup.get(row.car_id) ?? null,
    contact_info: contactInfoLookup.get(row.contact_info_id) ?? null,
    add_ons: addOnLinksByOrderId.get(row.id) ?? [],
    history: []
  };
});

export const mockVehicleDiscounts: VehicleDiscount[] = rawVehicleDiscountsData.map((row) => {
  const vehicle = vehicleLookup.get(row.vehicle_id);

  return {
    id: row.id,
    vehicle_id: row.vehicle_id,
    price_XCD: row.price_XCD,
    price_USD: row.price_USD,
    days: row.days,
    vehicle: vehicle
      ? {
          id: vehicle.id,
          name: vehicle.name,
          slug: vehicle.slug
        }
      : undefined
  };
});

export const mockDashboardSummary: DashboardSummary = {
  vehicles_total: mockVehicles.length,
  vehicles_showing: mockVehicles.filter((row) => row.showing).length,
  add_ons_total: mockAddOns.length,
  vehicle_discounts_total: mockVehicleDiscounts.length,
  order_requests_total: mockOrderRequests.length,
  order_requests_pending: mockOrderRequests.filter((row) => !row.confirmed).length,
  order_requests_confirmed: mockOrderRequests.filter((row) => row.confirmed).length,
  order_requests_revenue: mockOrderRequests.reduce((sum, row) => sum + row.sub_total, 0),
};

export const mockAnalyticsDailyMetrics = rawAnalyticsDailyMetricsData;

export const mockVisitorSessions = rawVisitorSessionsData;

export const mockVisitorPageViews = rawVisitorPageViewsData;

export const mockData = {
  summary: mockDashboardSummary,
  analyticsDailyMetrics: mockAnalyticsDailyMetrics,
  addOns: mockAddOns,
  vehicles: mockVehicles,
  vehicleDiscounts: mockVehicleDiscounts,
  orderRequests: mockOrderRequests,
  visitorSessions: mockVisitorSessions,
  visitorPageViews: mockVisitorPageViews,
  raw: {
    add_ons: rawAddOnsData,
    admin_api_tokens: rawAdminApiTokensData,
    admin_users: rawAdminUsersData,
    analytics_daily_metrics: rawAnalyticsDailyMetricsData,
    contact_info: rawContactInfoData,
    migrations: rawMigrationsData,
    order_requests: rawOrderRequestsData,
    order_request_add_ons: rawOrderRequestAddOnsData,
    visitor_sessions: rawVisitorSessionsData,
    visitor_page_views: rawVisitorPageViewsData,
    vehicles: rawVehiclesData,
    vehicle_discounts: rawVehicleDiscountsData
  },
  source: rawSummaryData
};

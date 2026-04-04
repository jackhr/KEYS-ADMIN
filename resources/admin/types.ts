export type AdminUser = {
  id: number;
  username: string;
  email: string | null;
  role: string;
  active: boolean;
  last_login_at: string | null;
};

export type AccountSettings = {
  user: AdminUser;
  session: {
    token_created_at: string | null;
    token_last_used_at: string | null;
    token_expires_at: string | null;
  };
};

export type DashboardSummary = {
  vehicles_total: number;
  vehicles_showing: number;
  add_ons_total: number;
  vehicle_discounts_total: number;
  order_requests_total: number;
  order_requests_pending: number;
  order_requests_confirmed: number;
  order_requests_revenue: number;
};

export type DashboardAnalyticsRange = "7d" | "30d" | "90d";

export type DashboardMetricCard = {
  value: number;
  change_pct: number;
};

export type DashboardAnalyticsPoint = {
  date: string;
  label: string;
  revenue_usd: number;
  order_requests: number;
  new_customers: number;
  active_vehicles: number;
  unique_visitors: number;
  mobile_visitors: number;
  desktop_visitors: number;
  page_views: number;
  growth_rate_pct: number;
};

export type DashboardAnalytics = {
  range: DashboardAnalyticsRange;
  start_date: string;
  end_date: string;
  generated_at: string;
  cards: {
    total_revenue: DashboardMetricCard;
    new_customers: DashboardMetricCard;
    current_vehicles: DashboardMetricCard;
    growth_rate: DashboardMetricCard;
  };
  chart: DashboardAnalyticsPoint[];
  table: DashboardAnalyticsPoint[];
};

export type DashboardAnalyticsBotMode = "exclude" | "include" | "only";

export type DashboardAnalyticsSessionsFilters = {
  device_type: "desktop" | "mobile" | "tablet" | "bot" | "other" | null;
  bot_mode: DashboardAnalyticsBotMode;
  referrer_contains: string;
  min_page_views: number;
  min_duration_seconds: number;
};

export type DashboardAnalyticsSessionSummary = {
  unique_visitors: number;
  total_sessions: number;
  avg_session_duration_seconds: number;
  avg_pages_per_session: number;
  bot_session_pct: number;
};

export type DashboardAnalyticsSession = {
  id: number;
  session_id: string;
  visitor_id: string;
  first_seen_at: string | null;
  last_seen_at: string | null;
  session_duration_seconds: number;
  page_views: number;
  entry_path: string | null;
  entry_referrer: string | null;
  device_type: string;
  is_bot: boolean;
  os_name: string | null;
  browser_name: string | null;
  language: string | null;
  timezone: string | null;
  ip_address: string | null;
};

export type DashboardAnalyticsSessionsResponse = {
  date: string;
  filters: DashboardAnalyticsSessionsFilters;
  summary: DashboardAnalyticsSessionSummary;
  sessions: {
    items: DashboardAnalyticsSession[];
    meta: PaginationMeta;
  };
};

export type DashboardSessionPageView = {
  id: number;
  visited_at: string | null;
  route_path: string | null;
  full_url: string | null;
  query_string: string | null;
  referrer: string | null;
  event_type: string | null;
  device_type: string | null;
  is_bot: boolean;
  os_name: string | null;
  browser_name: string | null;
  language: string | null;
  timezone: string | null;
  ip_address: string | null;
  viewport_width: number | null;
  viewport_height: number | null;
  screen_width: number | null;
  screen_height: number | null;
  metadata: Record<string, unknown> | null;
};

export type DashboardAnalyticsSessionPageViewsResponse = {
  session: Omit<DashboardAnalyticsSession, "session_duration_seconds" | "page_views">;
  date: string | null;
  page_views: {
    items: DashboardSessionPageView[];
    meta: PaginationMeta;
  };
};

export type Vehicle = {
  id: number;
  name: string;
  type: string;
  slug: string;
  showing: boolean;
  landing_order: number | null;
  base_price_XCD: number;
  base_price_USD: number;
  insurance: number;
  times_requested: number;
  people: number;
  bags: number | null;
  doors: number;
  four_wd: boolean;
  ac: boolean;
  manual: boolean;
  image_url: string;
};

export type VehicleDraft = Partial<Vehicle> & {
  image: File | null;
};

export type AddOn = {
  id: number;
  name: string;
  cost: number | null;
  description: string;
  abbr: string;
  fixed_price: boolean;
};

export type VehicleDiscount = {
  id: number;
  vehicle_id: number;
  price_XCD: number;
  price_USD: number;
  days: number;
  vehicle?: {
    id: number;
    name: string;
    slug: string;
  };
};

export type ContactInfo = {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  hotel?: string | null;
  country_or_region?: string | null;
};

export type OrderRequestAddOn = {
  id: number;
  add_on_id: number;
  quantity: number;
  add_on: AddOn | null;
};

export type OrderRequest = {
  id: number;
  key: string;
  pick_up: string;
  drop_off: string;
  pick_up_location: string;
  drop_off_location: string;
  confirmed: boolean;
  status: string;
  sub_total: number;
  days: number;
  created_at: string | null;
  updated_at: string | null;
  vehicle: Pick<Vehicle, "id" | "name" | "slug" | "type"> | null;
  contact_info: ContactInfo | null;
  add_ons: OrderRequestAddOn[];
  history: OrderRequestHistory[];
};

export type OrderRequestHistory = {
  id: number;
  admin_user: string;
  action: string;
  change_summary: string | null;
  previous_data: Record<string, unknown> | null;
  new_data: Record<string, unknown> | null;
  created_at: string | null;
};

export type DashboardPageProps = {
  user: AdminUser;
  onLogout: () => Promise<void>;
  onUserChange: (user: AdminUser) => void;
};

export type Section = "overview" | "vehicles" | "addons" | "discounts" | "orders" | "settings";

export type ConfirmDialogState = {
  open: boolean;
  title: string;
  description: string;
  action: (() => Promise<void>) | null;
};

export type PaginationMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type LoadResourceOptions = {
  cacheKey?: string;
  readFromCache?: boolean;
  writeToCache?: boolean;
};

export type CachedResourceEnvelope<TResource> = {
  value: TResource;
  expiresAt: number;
};

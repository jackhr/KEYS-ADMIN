import {
  ArrowDownRight,
  ArrowUpRight,
  BadgeDollarSign,
  CarFront,
  ChartLine,
  Download,
  UserRoundPlus
} from "lucide-react";
import { useEffect, useMemo, useState, type ReactNode } from "react";
import {
  Area,
  AreaChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis
} from "recharts";

import {
  getDashboardAnalyticsSessionPageViews,
  getDashboardAnalyticsSessions,
  getApiErrorMessage
} from "../../lib/api";
import { formatDateTimeDisplay } from "../../lib/utils";
import type {
  DashboardAnalytics,
  DashboardAnalyticsBotMode,
  DashboardAnalyticsPoint,
  DashboardAnalyticsRange,
  DashboardAnalyticsSession,
  DashboardAnalyticsSessionPageViewsResponse,
  DashboardAnalyticsSessionsFilters,
  DashboardAnalyticsSessionsResponse,
  DashboardMetricCard
} from "../../types";
import { Button } from "../ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "../ui/card";
import { Input } from "../ui/input";
import {
  Modal,
  ModalContent,
  ModalDescription,
  ModalFooter,
  ModalHeader,
  ModalTitle
} from "../ui/modal";
import { Select } from "../ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "../ui/table";
import { Tabs, TabsList, TabsTrigger } from "../ui/tabs";
import DataTable from "./DataTable";

type AnalyticsOverviewProps = {
  analytics: DashboardAnalytics | null;
  range: DashboardAnalyticsRange;
  busy: boolean;
  onRangeChange: (range: DashboardAnalyticsRange) => void;
};

const RANGE_OPTIONS: { label: string; value: DashboardAnalyticsRange }[] = [
  { label: "Last 3 months", value: "90d" },
  { label: "Last month", value: "30d" },
  { label: "Last 7 days", value: "7d" }
];

const BOT_MODE_OPTIONS: { label: string; value: DashboardAnalyticsBotMode }[] = [
  { label: "Exclude bots", value: "exclude" },
  { label: "Include bots", value: "include" },
  { label: "Only bots", value: "only" }
];

const DEFAULT_SESSION_FILTERS: DashboardAnalyticsSessionsFilters = {
  device_type: null,
  bot_mode: "exclude",
  referrer_contains: "",
  min_page_views: 0,
  min_duration_seconds: 0
};

const SESSIONS_PER_PAGE = 15;
const SESSION_PAGE_VIEWS_PER_PAGE = 20;

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(amount);
}

function formatPercent(value: number): string {
  const absValue = Math.abs(value);
  return `${absValue.toFixed(1)}%`;
}

function formatDuration(seconds: number): string {
  const safeSeconds = Math.max(0, Math.round(seconds));
  const hours = Math.floor(safeSeconds / 3600);
  const minutes = Math.floor((safeSeconds % 3600) / 60);
  const remainingSeconds = safeSeconds % 60;

  if (hours > 0) {
    return `${hours}h ${minutes}m ${remainingSeconds}s`;
  }

  if (minutes > 0) {
    return `${minutes}m ${remainingSeconds}s`;
  }

  return `${remainingSeconds}s`;
}

function normalizeNumberInput(rawValue: string): number {
  if (rawValue.trim() === "") {
    return 0;
  }

  const parsed = Number(rawValue);

  if (!Number.isFinite(parsed) || parsed < 0) {
    return 0;
  }

  return Math.round(parsed);
}

function csvEscape(value: unknown): string {
  const serialized = value === null || value === undefined ? "" : String(value);
  return `"${serialized.replace(/"/g, '""')}"`;
}

function downloadCsv(filename: string, csvContent: string): void {
  const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");

  link.href = url;
  link.setAttribute("download", filename);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

function ChangeBadge({ value }: { value: number }) {
  const positive = value >= 0;

  return (
    <span
      className={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium ${
        positive
          ? "border-emerald-200 bg-emerald-50 text-emerald-700"
          : "border-rose-200 bg-rose-50 text-rose-700"
      }`}
    >
      {positive ? <ArrowUpRight className="h-3.5 w-3.5" /> : <ArrowDownRight className="h-3.5 w-3.5" />}
      {formatPercent(value)}
    </span>
  );
}

function MetricCard({
  label,
  value,
  helper,
  icon,
  currency = false
}: {
  label: string;
  value: DashboardMetricCard;
  helper: string;
  icon: ReactNode;
  currency?: boolean;
}) {
  return (
    <Card className="border-border/70 shadow-sm">
      <CardHeader className="space-y-3 pb-2">
        <div className="flex items-start justify-between gap-3">
          <CardDescription className="text-sm font-medium">{label}</CardDescription>
          <ChangeBadge value={value.change_pct} />
        </div>
      </CardHeader>
      <CardContent className="space-y-2">
        <CardTitle className="text-4xl font-semibold tracking-tight">
          {currency
            ? formatCurrency(value.value)
            : label === "Growth Rate"
              ? `${value.value.toFixed(2)}%`
              : Math.round(value.value).toLocaleString()}
        </CardTitle>
        <div className="text-muted-foreground flex items-center gap-2 text-sm">
          {icon}
          <span>{helper}</span>
        </div>
      </CardContent>
    </Card>
  );
}

function ChartTooltip({
  active,
  payload,
  label
}: {
  active?: boolean;
  payload?: Array<{ value?: number | string; name?: string }>;
  label?: string;
}) {
  if (!active || !payload || payload.length === 0) {
    return null;
  }

  const uniqueVisitors = Number(payload.find((entry) => entry.name === "Unique Visitors")?.value ?? 0);
  const pageViews = Number(payload.find((entry) => entry.name === "Page Views")?.value ?? 0);
  const mobileVisitors = Number(payload.find((entry) => entry.name === "Mobile Visitors")?.value ?? 0);
  const desktopVisitors = Number(payload.find((entry) => entry.name === "Desktop Visitors")?.value ?? 0);

  return (
    <div className="rounded-lg border border-border/70 bg-card px-3 py-2 text-xs shadow-md">
      <p className="mb-1 font-semibold text-foreground">{label}</p>
      <p className="text-muted-foreground">Unique Visitors: {Math.round(uniqueVisitors)}</p>
      <p className="text-muted-foreground">Page Views: {Math.round(pageViews)}</p>
      <p className="text-muted-foreground">Mobile Visitors: {Math.round(mobileVisitors)}</p>
      <p className="text-muted-foreground">Desktop Visitors: {Math.round(desktopVisitors)}</p>
    </div>
  );
}

function SessionSummaryCards({ data }: { data: DashboardAnalyticsSessionsResponse | null }) {
  const summary = data?.summary ?? {
    unique_visitors: 0,
    total_sessions: 0,
    avg_session_duration_seconds: 0,
    avg_pages_per_session: 0,
    bot_session_pct: 0
  };

  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
      <div className="rounded-md border bg-muted/20 p-3">
        <p className="text-muted-foreground text-xs uppercase tracking-wide">Unique Visitors</p>
        <p className="mt-1 text-lg font-semibold">{summary.unique_visitors}</p>
      </div>
      <div className="rounded-md border bg-muted/20 p-3">
        <p className="text-muted-foreground text-xs uppercase tracking-wide">Total Sessions</p>
        <p className="mt-1 text-lg font-semibold">{summary.total_sessions}</p>
      </div>
      <div className="rounded-md border bg-muted/20 p-3">
        <p className="text-muted-foreground text-xs uppercase tracking-wide">Avg Session Duration</p>
        <p className="mt-1 text-lg font-semibold">{formatDuration(summary.avg_session_duration_seconds)}</p>
      </div>
      <div className="rounded-md border bg-muted/20 p-3">
        <p className="text-muted-foreground text-xs uppercase tracking-wide">Avg Pages / Session</p>
        <p className="mt-1 text-lg font-semibold">{summary.avg_pages_per_session.toFixed(2)}</p>
      </div>
      <div className="rounded-md border bg-muted/20 p-3">
        <p className="text-muted-foreground text-xs uppercase tracking-wide">Bot Sessions</p>
        <p className="mt-1 text-lg font-semibold">{summary.bot_session_pct.toFixed(2)}%</p>
      </div>
    </div>
  );
}

export default function AnalyticsOverview({ analytics, range, busy, onRangeChange }: AnalyticsOverviewProps) {
  const rows = analytics?.table ?? [];
  const chartRows = analytics?.chart ?? [];

  const [sessionsModalOpen, setSessionsModalOpen] = useState(false);
  const [selectedAnalyticsDate, setSelectedAnalyticsDate] = useState<string | null>(null);
  const [sessionFilters, setSessionFilters] = useState<DashboardAnalyticsSessionsFilters>(DEFAULT_SESSION_FILTERS);
  const [sessionFilterDraft, setSessionFilterDraft] = useState<DashboardAnalyticsSessionsFilters>(DEFAULT_SESSION_FILTERS);
  const [sessionsPage, setSessionsPage] = useState(1);
  const [sessionsData, setSessionsData] = useState<DashboardAnalyticsSessionsResponse | null>(null);
  const [sessionsLoading, setSessionsLoading] = useState(false);
  const [sessionsError, setSessionsError] = useState<string | null>(null);
  const [sessionsCsvBusy, setSessionsCsvBusy] = useState(false);

  const [sessionDetailOpen, setSessionDetailOpen] = useState(false);
  const [selectedSession, setSelectedSession] = useState<DashboardAnalyticsSession | null>(null);
  const [sessionPageViewsPage, setSessionPageViewsPage] = useState(1);
  const [sessionPageViewsData, setSessionPageViewsData] =
    useState<DashboardAnalyticsSessionPageViewsResponse | null>(null);
  const [sessionPageViewsLoading, setSessionPageViewsLoading] = useState(false);
  const [sessionPageViewsError, setSessionPageViewsError] = useState<string | null>(null);

  const sessionsPagination = sessionsData?.sessions.meta;
  const pageViewsPagination = sessionPageViewsData?.page_views.meta;

  const appliedFilterLabel = useMemo(() => {
    const labels: string[] = [];

    if (sessionFilters.device_type) {
      labels.push(`Device: ${sessionFilters.device_type}`);
    }
    labels.push(
      `Bots: ${
        sessionFilters.bot_mode === "exclude"
          ? "excluded"
          : sessionFilters.bot_mode === "only"
            ? "only bots"
            : "included"
      }`
    );
    if (sessionFilters.referrer_contains.trim() !== "") {
      labels.push(`Referrer contains "${sessionFilters.referrer_contains.trim()}"`);
    }
    if (sessionFilters.min_page_views > 0) {
      labels.push(`Min page views: ${sessionFilters.min_page_views}`);
    }
    if (sessionFilters.min_duration_seconds > 0) {
      labels.push(`Min duration: ${sessionFilters.min_duration_seconds}s`);
    }

    return labels.join(" | ");
  }, [sessionFilters]);

  const loadDailySessions = async (
    date: string,
    page: number,
    filters: DashboardAnalyticsSessionsFilters
  ): Promise<void> => {
    setSessionsLoading(true);
    setSessionsError(null);

    try {
      const data = await getDashboardAnalyticsSessions(date, {
        ...filters,
        device_type: filters.device_type ?? undefined,
        referrer_contains: filters.referrer_contains.trim() || undefined,
        page,
        per_page: SESSIONS_PER_PAGE
      });
      setSessionsData(data);
    } catch (error) {
      setSessionsError(getApiErrorMessage(error));
    } finally {
      setSessionsLoading(false);
    }
  };

  const loadSessionPageViews = async (session: DashboardAnalyticsSession, page: number): Promise<void> => {
    if (!selectedAnalyticsDate) {
      return;
    }

    setSessionPageViewsLoading(true);
    setSessionPageViewsError(null);

    try {
      const data = await getDashboardAnalyticsSessionPageViews(session.id, {
        date: selectedAnalyticsDate,
        page,
        per_page: SESSION_PAGE_VIEWS_PER_PAGE
      });
      setSessionPageViewsData(data);
    } catch (error) {
      setSessionPageViewsError(getApiErrorMessage(error));
    } finally {
      setSessionPageViewsLoading(false);
    }
  };

  useEffect(() => {
    if (!sessionsModalOpen || !selectedAnalyticsDate) {
      return;
    }

    void loadDailySessions(selectedAnalyticsDate, sessionsPage, sessionFilters);
  }, [selectedAnalyticsDate, sessionFilters, sessionsModalOpen, sessionsPage]);

  useEffect(() => {
    if (!sessionDetailOpen || !selectedSession) {
      return;
    }

    void loadSessionPageViews(selectedSession, sessionPageViewsPage);
  }, [selectedSession, sessionDetailOpen, sessionPageViewsPage, selectedAnalyticsDate]);

  const openSessionsModalForDate = (date: string) => {
    setSelectedAnalyticsDate(date);
    setSessionFilters(DEFAULT_SESSION_FILTERS);
    setSessionFilterDraft(DEFAULT_SESSION_FILTERS);
    setSessionsPage(1);
    setSessionsData(null);
    setSessionsError(null);
    setSessionDetailOpen(false);
    setSelectedSession(null);
    setSessionPageViewsData(null);
    setSessionPageViewsError(null);
    setSessionsModalOpen(true);
  };

  const applySessionFilters = () => {
    setSessionsPage(1);
    setSessionFilters({
      ...sessionFilterDraft,
      referrer_contains: sessionFilterDraft.referrer_contains.trim(),
      min_page_views: Math.max(0, sessionFilterDraft.min_page_views),
      min_duration_seconds: Math.max(0, sessionFilterDraft.min_duration_seconds)
    });
  };

  const clearSessionFilters = () => {
    setSessionsPage(1);
    setSessionFilters(DEFAULT_SESSION_FILTERS);
    setSessionFilterDraft(DEFAULT_SESSION_FILTERS);
  };

  const openSessionDetail = (session: DashboardAnalyticsSession) => {
    setSelectedSession(session);
    setSessionPageViewsPage(1);
    setSessionPageViewsData(null);
    setSessionPageViewsError(null);
    setSessionDetailOpen(true);
  };

  const exportSessionsCsv = async () => {
    if (!selectedAnalyticsDate) {
      return;
    }

    setSessionsCsvBusy(true);
    setSessionsError(null);

    try {
      const baseParams = {
        ...sessionFilters,
        device_type: sessionFilters.device_type ?? undefined,
        referrer_contains: sessionFilters.referrer_contains.trim() || undefined,
        per_page: 500
      };
      const firstPage = await getDashboardAnalyticsSessions(selectedAnalyticsDate, {
        ...baseParams,
        page: 1
      });

      const collected = [...firstPage.sessions.items];
      const lastPage = firstPage.sessions.meta.last_page;

      for (let page = 2; page <= lastPage; page += 1) {
        const response = await getDashboardAnalyticsSessions(selectedAnalyticsDate, {
          ...baseParams,
          page
        });
        collected.push(...response.sessions.items);
      }

      const headers = [
        "id",
        "session_id",
        "visitor_id",
        "first_seen_at",
        "last_seen_at",
        "session_duration_seconds",
        "page_views",
        "device_type",
        "is_bot",
        "entry_path",
        "entry_referrer",
        "browser_name",
        "os_name",
        "language",
        "timezone",
        "ip_address"
      ];

      const csvRows = [
        headers.map(csvEscape).join(","),
        ...collected.map((row) =>
          [
            row.id,
            row.session_id,
            row.visitor_id,
            row.first_seen_at,
            row.last_seen_at,
            row.session_duration_seconds,
            row.page_views,
            row.device_type,
            row.is_bot ? 1 : 0,
            row.entry_path,
            row.entry_referrer,
            row.browser_name,
            row.os_name,
            row.language,
            row.timezone,
            row.ip_address
          ]
            .map(csvEscape)
            .join(",")
        )
      ].join("\n");

      downloadCsv(`analytics-sessions-${selectedAnalyticsDate}.csv`, csvRows);
    } catch (error) {
      setSessionsError(getApiErrorMessage(error));
    } finally {
      setSessionsCsvBusy(false);
    }
  };

  return (
    <div className="space-y-4">
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <MetricCard
          label="Total Revenue"
          value={analytics?.cards.total_revenue ?? { value: 0, change_pct: 0 }}
          helper="Booking subtotal over selected window"
          icon={<BadgeDollarSign className="h-4 w-4" />}
          currency
        />
        <MetricCard
          label="New Customers"
          value={analytics?.cards.new_customers ?? { value: 0, change_pct: 0 }}
          helper="Total order requests in the past 30 days"
          icon={<UserRoundPlus className="h-4 w-4" />}
        />
        <MetricCard
          label="Current Vehicles"
          value={analytics?.cards.current_vehicles ?? { value: 0, change_pct: 0 }}
          helper="Vehicles currently in use"
          icon={<CarFront className="h-4 w-4" />}
        />
        <MetricCard
          label="Growth Rate"
          value={analytics?.cards.growth_rate ?? { value: 0, change_pct: 0 }}
          helper="Revenue growth vs previous period"
          icon={<ChartLine className="h-4 w-4" />}
        />
      </div>

      <Card className="border-border/70 shadow-sm">
        <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <CardTitle>Total Visitors</CardTitle>
            <CardDescription>Total for the selected period.</CardDescription>
          </div>
          <Tabs
            value={range}
            onValueChange={(value) => onRangeChange(value as DashboardAnalyticsRange)}
            className="gap-0"
          >
            <TabsList className="h-10 p-1">
              {RANGE_OPTIONS.map((option) => (
                <TabsTrigger key={option.value} value={option.value} className="h-8 px-4" disabled={busy}>
                  {option.label}
                </TabsTrigger>
              ))}
            </TabsList>
          </Tabs>
        </CardHeader>
        <CardContent className="space-y-6">
          <div className="h-80 w-full">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={chartRows} margin={{ top: 8, right: 16, left: 0, bottom: 8 }}>
                <defs>
                  <linearGradient id="revenueGradient" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="hsl(var(--primary))" stopOpacity={0.3} />
                    <stop offset="95%" stopColor="hsl(var(--primary))" stopOpacity={0.02} />
                  </linearGradient>
                  <linearGradient id="ordersGradient" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="hsl(var(--foreground))" stopOpacity={0.18} />
                    <stop offset="95%" stopColor="hsl(var(--foreground))" stopOpacity={0.02} />
                  </linearGradient>
                </defs>
                <CartesianGrid vertical={false} strokeDasharray="3 3" />
                <XAxis dataKey="label" tickLine={false} axisLine={false} minTickGap={20} />
                <YAxis yAxisId="left" hide />
                <YAxis yAxisId="right" hide orientation="right" />
                <Tooltip content={<ChartTooltip />} />
                <Area
                  yAxisId="left"
                  type="monotone"
                  dataKey="unique_visitors"
                  name="Unique Visitors"
                  stroke="hsl(var(--primary))"
                  fill="url(#revenueGradient)"
                  strokeWidth={2.3}
                  dot={false}
                  activeDot={{ r: 4 }}
                />
                <Area
                  yAxisId="right"
                  type="monotone"
                  dataKey="page_views"
                  name="Page Views"
                  stroke="hsl(var(--foreground))"
                  fill="url(#ordersGradient)"
                  strokeWidth={1.8}
                  dot={false}
                  activeDot={{ r: 3 }}
                />
                <Area yAxisId="right" type="monotone" dataKey="mobile_visitors" name="Mobile Visitors" hide />
                <Area yAxisId="right" type="monotone" dataKey="desktop_visitors" name="Desktop Visitors" hide />
              </AreaChart>
            </ResponsiveContainer>
          </div>

          <DataTable>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Date</TableHead>
                  <TableHead>Unique Visitors</TableHead>
                  <TableHead>Mobile</TableHead>
                  <TableHead>Desktop</TableHead>
                  <TableHead>Page Views</TableHead>
                  <TableHead>Revenue (USD)</TableHead>
                  <TableHead>Orders</TableHead>
                  <TableHead>Growth Rate</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((row: DashboardAnalyticsPoint) => (
                  <TableRow
                    key={row.date}
                    className="cursor-pointer"
                    tabIndex={0}
                    onClick={() => openSessionsModalForDate(row.date)}
                    onKeyDown={(event) => {
                      if (event.key === "Enter" || event.key === " ") {
                        event.preventDefault();
                        openSessionsModalForDate(row.date);
                      }
                    }}
                  >
                    <TableCell>{row.date}</TableCell>
                    <TableCell>{row.unique_visitors}</TableCell>
                    <TableCell>{row.mobile_visitors}</TableCell>
                    <TableCell>{row.desktop_visitors}</TableCell>
                    <TableCell>{row.page_views}</TableCell>
                    <TableCell>{formatCurrency(row.revenue_usd)}</TableCell>
                    <TableCell>{row.order_requests}</TableCell>
                    <TableCell>
                      <span
                        className={`font-medium ${row.growth_rate_pct >= 0 ? "text-emerald-700" : "text-rose-700"}`}
                      >
                        {row.growth_rate_pct.toFixed(2)}%
                      </span>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </DataTable>
        </CardContent>
      </Card>

      <Modal
        open={sessionsModalOpen}
        onOpenChange={(open) => {
          setSessionsModalOpen(open);
          if (!open) {
            setSessionDetailOpen(false);
          }
        }}
      >
        <ModalContent className="w-[min(96vw,1120px)] max-w-none max-h-[90vh] overflow-y-auto overflow-x-hidden">
          <ModalHeader>
            <ModalTitle>Visitor Sessions for {selectedAnalyticsDate ?? "-"}</ModalTitle>
            <ModalDescription>Filter and inspect sessions captured for this day.</ModalDescription>
          </ModalHeader>

          <div className="space-y-4">
            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
              <div className="space-y-1">
                <label className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Device</label>
                <Select
                  value={sessionFilterDraft.device_type ?? ""}
                  onChange={(event) =>
                    setSessionFilterDraft((prev) => ({
                      ...prev,
                      device_type: event.target.value === "" ? null : (event.target.value as DashboardAnalyticsSessionsFilters["device_type"])
                    }))
                  }
                >
                  <option value="">All devices</option>
                  <option value="desktop">Desktop</option>
                  <option value="mobile">Mobile</option>
                  <option value="tablet">Tablet</option>
                  <option value="bot">Bot</option>
                  <option value="other">Other</option>
                </Select>
              </div>

              <div className="space-y-1">
                <label className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Bot mode</label>
                <Select
                  value={sessionFilterDraft.bot_mode}
                  onChange={(event) =>
                    setSessionFilterDraft((prev) => ({
                      ...prev,
                      bot_mode: event.target.value as DashboardAnalyticsBotMode
                    }))
                  }
                >
                  {BOT_MODE_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </Select>
              </div>

              <div className="space-y-1 xl:col-span-2">
                <label className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                  Referrer / Source Contains
                </label>
                <Input
                  placeholder="google.com"
                  value={sessionFilterDraft.referrer_contains}
                  onChange={(event) =>
                    setSessionFilterDraft((prev) => ({
                      ...prev,
                      referrer_contains: event.target.value
                    }))
                  }
                />
              </div>

              <div className="space-y-1">
                <label className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                  Min Page Views
                </label>
                <Input
                  type="number"
                  min={0}
                  value={sessionFilterDraft.min_page_views}
                  onChange={(event) =>
                    setSessionFilterDraft((prev) => ({
                      ...prev,
                      min_page_views: normalizeNumberInput(event.target.value)
                    }))
                  }
                />
              </div>

              <div className="space-y-1">
                <label className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                  Min Duration (sec)
                </label>
                <Input
                  type="number"
                  min={0}
                  value={sessionFilterDraft.min_duration_seconds}
                  onChange={(event) =>
                    setSessionFilterDraft((prev) => ({
                      ...prev,
                      min_duration_seconds: normalizeNumberInput(event.target.value)
                    }))
                  }
                />
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <Button onClick={applySessionFilters} disabled={sessionsLoading}>
                Apply Filters
              </Button>
              <Button variant="outline" onClick={clearSessionFilters} disabled={sessionsLoading}>
                Clear
              </Button>
              <Button
                variant="outline"
                onClick={() => void exportSessionsCsv()}
                disabled={sessionsCsvBusy || sessionsLoading || !selectedAnalyticsDate}
              >
                <Download className="h-4 w-4" />
                {sessionsCsvBusy ? "Exporting..." : "Export CSV"}
              </Button>
              <span className="text-muted-foreground text-xs">{appliedFilterLabel}</span>
            </div>

            <SessionSummaryCards data={sessionsData} />

            {sessionsError ? (
              <div className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm font-medium text-destructive">
                {sessionsError}
              </div>
            ) : null}

            <DataTable>
              <Table className="min-w-330">
                <TableHeader>
                  <TableRow>
                    <TableHead className="w-60">Session</TableHead>
                    <TableHead className="w-60">Visitor</TableHead>
                    <TableHead className="w-55">First Seen</TableHead>
                    <TableHead className="w-55">Last Seen</TableHead>
                    <TableHead className="w-32.5">Duration</TableHead>
                    <TableHead className="w-27.5">Page Views</TableHead>
                    <TableHead className="w-30">Device</TableHead>
                    <TableHead className="w-22.5">Bot</TableHead>
                    <TableHead className="w-30">Entry Path</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {sessionsLoading ? (
                    <TableRow>
                      <TableCell colSpan={9} className="text-muted-foreground">
                        Loading session data...
                      </TableCell>
                    </TableRow>
                  ) : (sessionsData?.sessions.items.length ?? 0) === 0 ? (
                    <TableRow>
                      <TableCell colSpan={9} className="text-muted-foreground">
                        No sessions matched this filter set.
                      </TableCell>
                    </TableRow>
                  ) : (
                    sessionsData?.sessions.items.map((session) => (
                      <TableRow
                        key={session.id}
                        className="cursor-pointer"
                        tabIndex={0}
                        onClick={() => openSessionDetail(session)}
                        onKeyDown={(event) => {
                          if (event.key === "Enter" || event.key === " ") {
                            event.preventDefault();
                            openSessionDetail(session);
                          }
                        }}
                      >
                        <TableCell className="max-w-60 truncate font-medium" title={session.session_id}>
                          {session.session_id}
                        </TableCell>
                        <TableCell className="max-w-60 truncate" title={session.visitor_id}>
                          {session.visitor_id}
                        </TableCell>
                        <TableCell
                          className="max-w-55 truncate"
                          title={formatDateTimeDisplay(session.first_seen_at)}
                        >
                          {formatDateTimeDisplay(session.first_seen_at)}
                        </TableCell>
                        <TableCell
                          className="max-w-55 truncate"
                          title={formatDateTimeDisplay(session.last_seen_at)}
                        >
                          {formatDateTimeDisplay(session.last_seen_at)}
                        </TableCell>
                        <TableCell>{formatDuration(session.session_duration_seconds)}</TableCell>
                        <TableCell>{session.page_views}</TableCell>
                        <TableCell className="capitalize">{session.device_type}</TableCell>
                        <TableCell>{session.is_bot ? "Yes" : "No"}</TableCell>
                        <TableCell className="max-w-48 truncate" title={session.entry_path ?? "-"}>
                          {session.entry_path ?? "-"}
                        </TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </DataTable>

            <div className="flex items-center justify-between text-sm">
              <span className="text-muted-foreground">
                {sessionsPagination
                  ? `Page ${sessionsPagination.current_page} of ${sessionsPagination.last_page} (${sessionsPagination.total} total sessions)`
                  : "No session pagination data"}
              </span>
              <div className="flex items-center gap-2">
                <Button
                  size="sm"
                  variant="outline"
                  disabled={sessionsLoading || !sessionsPagination || sessionsPagination.current_page <= 1}
                  onClick={() => setSessionsPage((prev) => Math.max(1, prev - 1))}
                >
                  Previous
                </Button>
                <Button
                  size="sm"
                  variant="outline"
                  disabled={
                    sessionsLoading ||
                    !sessionsPagination ||
                    sessionsPagination.current_page >= sessionsPagination.last_page
                  }
                  onClick={() =>
                    setSessionsPage((prev) =>
                      Math.min(sessionsPagination?.last_page ?? 1, prev + 1)
                    )
                  }
                >
                  Next
                </Button>
              </div>
            </div>
          </div>

          <ModalFooter>
            <Button variant="outline" onClick={() => setSessionsModalOpen(false)}>
              Close
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      <Modal open={sessionDetailOpen} onOpenChange={setSessionDetailOpen}>
        <ModalContent className="w-[min(96vw,1120px)] max-w-none max-h-[90vh] overflow-y-auto overflow-x-hidden">
          <ModalHeader>
            <ModalTitle>Session Detail {selectedSession ? `#${selectedSession.session_id}` : ""}</ModalTitle>
            <ModalDescription>Detailed page views for the selected session.</ModalDescription>
          </ModalHeader>

          {selectedSession ? (
            <div className="space-y-4">
              <div className="grid gap-3 md:grid-cols-3">
                <div className="rounded-md border bg-muted/20 p-3 text-sm">
                  <p className="text-muted-foreground text-xs uppercase tracking-wide">Visitor</p>
                  <p className="mt-1 font-medium">{selectedSession.visitor_id}</p>
                </div>
                <div className="rounded-md border bg-muted/20 p-3 text-sm">
                  <p className="text-muted-foreground text-xs uppercase tracking-wide">Duration</p>
                  <p className="mt-1 font-medium">{formatDuration(selectedSession.session_duration_seconds)}</p>
                </div>
                <div className="rounded-md border bg-muted/20 p-3 text-sm">
                  <p className="text-muted-foreground text-xs uppercase tracking-wide">Date Window</p>
                  <p className="mt-1 font-medium">{selectedAnalyticsDate ?? "-"}</p>
                </div>
              </div>

              {sessionPageViewsError ? (
                <div className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm font-medium text-destructive">
                  {sessionPageViewsError}
                </div>
              ) : null}

              <DataTable>
                <Table className="min-w-280">
                  <TableHeader>
                    <TableRow>
                      <TableHead className="w-55">Visited At</TableHead>
                      <TableHead className="w-45">Route</TableHead>
                      <TableHead className="w-30">Event</TableHead>
                      <TableHead className="w-65">Referrer</TableHead>
                      <TableHead className="w-30">Browser</TableHead>
                      <TableHead className="w-30">OS</TableHead>
                      <TableHead className="w-30">IP</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {sessionPageViewsLoading ? (
                      <TableRow>
                        <TableCell colSpan={7} className="text-muted-foreground">
                          Loading page views...
                        </TableCell>
                      </TableRow>
                    ) : (sessionPageViewsData?.page_views.items.length ?? 0) === 0 ? (
                      <TableRow>
                        <TableCell colSpan={7} className="text-muted-foreground">
                          No page views found for this session.
                        </TableCell>
                      </TableRow>
                    ) : (
                      sessionPageViewsData?.page_views.items.map((pageView) => (
                        <TableRow key={pageView.id}>
                          <TableCell
                            title={formatDateTimeDisplay(pageView.visited_at)}
                            className="max-w-55 truncate"
                          >
                            {formatDateTimeDisplay(pageView.visited_at)}
                          </TableCell>
                          <TableCell className="max-w-52 truncate">{pageView.route_path ?? "-"}</TableCell>
                          <TableCell>{pageView.event_type ?? "-"}</TableCell>
                          <TableCell className="max-w-72 truncate">{pageView.referrer ?? "-"}</TableCell>
                          <TableCell>{pageView.browser_name ?? "-"}</TableCell>
                          <TableCell>{pageView.os_name ?? "-"}</TableCell>
                          <TableCell>{pageView.ip_address ?? "-"}</TableCell>
                        </TableRow>
                      ))
                    )}
                  </TableBody>
                </Table>
              </DataTable>

              <div className="flex items-center justify-between text-sm">
                <span className="text-muted-foreground">
                  {pageViewsPagination
                    ? `Page ${pageViewsPagination.current_page} of ${pageViewsPagination.last_page} (${pageViewsPagination.total} total page views)`
                    : "No page-view pagination data"}
                </span>
                <div className="flex items-center gap-2">
                  <Button
                    size="sm"
                    variant="outline"
                    disabled={
                      sessionPageViewsLoading ||
                      !pageViewsPagination ||
                      pageViewsPagination.current_page <= 1
                    }
                    onClick={() => setSessionPageViewsPage((prev) => Math.max(1, prev - 1))}
                  >
                    Previous
                  </Button>
                  <Button
                    size="sm"
                    variant="outline"
                    disabled={
                      sessionPageViewsLoading ||
                      !pageViewsPagination ||
                      pageViewsPagination.current_page >= pageViewsPagination.last_page
                    }
                    onClick={() =>
                      setSessionPageViewsPage((prev) =>
                        Math.min(pageViewsPagination?.last_page ?? 1, prev + 1)
                      )
                    }
                  >
                    Next
                  </Button>
                </div>
              </div>
            </div>
          ) : (
            <p className="text-muted-foreground text-sm">No session selected.</p>
          )}

          <ModalFooter>
            <Button variant="outline" onClick={() => setSessionDetailOpen(false)}>
              Close
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

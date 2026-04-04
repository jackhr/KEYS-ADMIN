import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "../components/ui/card";
import AnalyticsOverview from "../components/dashboard/AnalyticsOverview";
import type { DashboardAnalytics, DashboardAnalyticsRange, DashboardSummary } from "../types";

type OverviewPageProps = {
  summary: DashboardSummary;
  analytics: DashboardAnalytics | null;
  analyticsRange: DashboardAnalyticsRange;
  busy: boolean;
  onAnalyticsRangeChange: (range: DashboardAnalyticsRange) => void;
};

export default function OverviewPage({
  summary,
  analytics,
  analyticsRange,
  busy,
  onAnalyticsRangeChange
}: OverviewPageProps) {
  return (
    <>
      <AnalyticsOverview
        analytics={analytics}
        range={analyticsRange}
        busy={busy}
        onRangeChange={onAnalyticsRangeChange}
      />

      <Card className="border-border/70 shadow-sm">
        <CardHeader>
          <CardTitle className="text-base">Operations Overview</CardTitle>
          <CardDescription>Quick-glance service health for the live system.</CardDescription>
        </CardHeader>
        <CardContent className="grid gap-3 text-sm md:grid-cols-4">
          <div className="rounded-lg border bg-background px-3 py-2">
            <p className="text-muted-foreground">Pending Orders</p>
            <p className="mt-1 text-lg font-semibold">{summary.order_requests_pending}</p>
          </div>
          <div className="rounded-lg border bg-background px-3 py-2">
            <p className="text-muted-foreground">Confirmed Orders</p>
            <p className="mt-1 text-lg font-semibold">{summary.order_requests_confirmed}</p>
          </div>
          <div className="rounded-lg border bg-background px-3 py-2">
            <p className="text-muted-foreground">Vehicles Showing</p>
            <p className="mt-1 text-lg font-semibold">{summary.vehicles_showing}</p>
          </div>
          <div className="rounded-lg border bg-background px-3 py-2">
            <p className="text-muted-foreground">Revenue Aggregate</p>
            <p className="mt-1 text-lg font-semibold">${summary.order_requests_revenue.toFixed(2)}</p>
          </div>
        </CardContent>
      </Card>
    </>
  );
}

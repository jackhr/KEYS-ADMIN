import { Button } from "../components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "../components/ui/card";
import DataTable from "../components/dashboard/DataTable";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "../components/ui/table";
import { formatDateTimeDisplay } from "../lib/utils";
import type { OrderRequest } from "../types";

type OrderRequestsPageProps = {
  orders: OrderRequest[];
  busy: boolean;
  onOpenDetail: (order: OrderRequest) => void;
  onToggleStatus: (order: OrderRequest) => void;
  paginationLabel: string;
  currentPage: number;
  lastPage: number;
  canGoPrevious: boolean;
  canGoNext: boolean;
  onPreviousPage: () => void;
  onNextPage: () => void;
};

export default function OrderRequestsPage({
  orders,
  busy,
  onOpenDetail,
  onToggleStatus,
  paginationLabel,
  currentPage,
  lastPage,
  canGoPrevious,
  canGoNext,
  onPreviousPage,
  onNextPage
}: OrderRequestsPageProps) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Order Requests</CardTitle>
        <CardDescription>Paginated car rental requests with quick status toggles.</CardDescription>
      </CardHeader>
      <CardContent>
        <DataTable>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Customer</TableHead>
                <TableHead>Vehicle</TableHead>
                <TableHead>Pick Up</TableHead>
                <TableHead>Drop Off</TableHead>
                <TableHead>Days</TableHead>
                <TableHead>Subtotal</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {orders.map((order) => (
                <TableRow
                  key={order.id}
                  role="button"
                  tabIndex={0}
                  className="cursor-pointer"
                  onClick={() => onOpenDetail(order)}
                  onKeyDown={(event) => {
                    if (event.key === "Enter" || event.key === " ") {
                      event.preventDefault();
                      onOpenDetail(order);
                    }
                  }}
                >
                  <TableCell>
                    {order.contact_info ? `${order.contact_info.first_name} ${order.contact_info.last_name}` : "-"}
                  </TableCell>
                  <TableCell>{order.vehicle?.name ?? "-"}</TableCell>
                  <TableCell>{formatDateTimeDisplay(order.pick_up)}</TableCell>
                  <TableCell>{formatDateTimeDisplay(order.drop_off)}</TableCell>
                  <TableCell>{order.days}</TableCell>
                  <TableCell>${order.sub_total.toFixed(2)}</TableCell>
                  <TableCell className="capitalize">{order.status}</TableCell>
                  <TableCell>
                    <div className="flex justify-end">
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={(event) => {
                          event.stopPropagation();
                          onToggleStatus(order);
                        }}
                        disabled={busy}
                      >
                        Mark {order.status === "confirmed" ? "Pending" : "Confirmed"}
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </DataTable>
        <div className="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
          <p className="text-muted-foreground">Showing {paginationLabel}</p>
          <div className="flex items-center gap-2">
            <Button type="button" variant="outline" size="sm" disabled={busy || !canGoPrevious} onClick={onPreviousPage}>
              Previous
            </Button>
            <span className="text-muted-foreground">
              Page {currentPage} of {Math.max(1, lastPage)}
            </span>
            <Button type="button" variant="outline" size="sm" disabled={busy || !canGoNext} onClick={onNextPage}>
              Next
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

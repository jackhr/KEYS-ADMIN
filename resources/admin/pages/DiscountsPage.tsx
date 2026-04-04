import { Pencil, Plus } from "lucide-react";

import DataTable from "../components/dashboard/DataTable";
import { Button } from "../components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "../components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "../components/ui/table";
import type { VehicleDiscount } from "../types";

type DiscountsPageProps = {
  discounts: VehicleDiscount[];
  busy: boolean;
  onCreate: () => void;
  onEdit: (discount: VehicleDiscount) => void;
};

export default function DiscountsPage({ discounts, busy, onCreate, onEdit }: DiscountsPageProps) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <div>
          <CardTitle>Vehicle Discounts</CardTitle>
          <CardDescription>Define discounted rates by vehicle and minimum days.</CardDescription>
        </div>
        <Button onClick={onCreate}>
          <Plus className="h-4 w-4" />
          New Discount
        </Button>
      </CardHeader>
      <CardContent>
        <DataTable>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Vehicle</TableHead>
                <TableHead>Days</TableHead>
                <TableHead>USD</TableHead>
                <TableHead>XCD</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {discounts.map((discount) => (
                <TableRow key={discount.id}>
                  <TableCell>{discount.vehicle?.name ?? `#${discount.vehicle_id}`}</TableCell>
                  <TableCell>{discount.days}</TableCell>
                  <TableCell>${discount.price_USD}</TableCell>
                  <TableCell>${discount.price_XCD}</TableCell>
                  <TableCell>
                    <div className="flex justify-end">
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => onEdit(discount)}
                        disabled={busy}
                      >
                        <Pencil className="h-3.5 w-3.5" />
                        Edit
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </DataTable>
      </CardContent>
    </Card>
  );
}

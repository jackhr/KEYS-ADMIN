import { Pencil, Plus } from "lucide-react";

import DataTable from "../components/dashboard/DataTable";
import { Button } from "../components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "../components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "../components/ui/table";
import type { Vehicle } from "../types";

type VehiclesPageProps = {
  vehicles: Vehicle[];
  busy: boolean;
  onCreate: () => void;
  onEdit: (vehicle: Vehicle) => void;
};

export default function VehiclesPage({ vehicles, busy, onCreate, onEdit }: VehiclesPageProps) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <div>
          <CardTitle>Vehicles</CardTitle>
          <CardDescription>Manage your rentable fleet and landing order.</CardDescription>
        </div>
        <Button onClick={onCreate}>
          <Plus className="h-4 w-4" />
          New Vehicle
        </Button>
      </CardHeader>
      <CardContent>
        <DataTable>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-24">Image</TableHead>
                <TableHead>Name</TableHead>
                <TableHead>Type</TableHead>
                <TableHead>USD</TableHead>
                <TableHead>Showing</TableHead>
                <TableHead>Requests</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {vehicles.map((vehicle) => (
                <TableRow key={vehicle.id}>
                  <TableCell>
                    <img
                      src={vehicle.image_url}
                      alt={`${vehicle.name} thumbnail`}
                      className="h-12 w-20 rounded-md border object-cover"
                      loading="lazy"
                    />
                  </TableCell>
                  <TableCell>{vehicle.name}</TableCell>
                  <TableCell>{vehicle.type}</TableCell>
                  <TableCell>${vehicle.base_price_USD}</TableCell>
                  <TableCell>{vehicle.showing ? "Yes" : "No"}</TableCell>
                  <TableCell>{vehicle.times_requested}</TableCell>
                  <TableCell>
                    <div className="flex justify-end">
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => onEdit(vehicle)}
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

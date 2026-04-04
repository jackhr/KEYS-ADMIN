import { Pencil, Plus } from "lucide-react";

import DataTable from "../components/dashboard/DataTable";
import { Button } from "../components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "../components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "../components/ui/table";
import type { AddOn } from "../types";

type AddOnsPageProps = {
  addOns: AddOn[];
  busy: boolean;
  onCreate: () => void;
  onEdit: (addOn: AddOn) => void;
};

export default function AddOnsPage({ addOns, busy, onCreate, onEdit }: AddOnsPageProps) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <div>
          <CardTitle>Add-Ons</CardTitle>
          <CardDescription>Configure optional rental items and extras.</CardDescription>
        </div>
        <Button onClick={onCreate}>
          <Plus className="h-4 w-4" />
          New Add-On
        </Button>
      </CardHeader>
      <CardContent>
        <DataTable>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Name</TableHead>
                <TableHead>Abbr</TableHead>
                <TableHead>Cost</TableHead>
                <TableHead>Fixed</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {addOns.map((addOn) => (
                <TableRow key={addOn.id}>
                  <TableCell>{addOn.name}</TableCell>
                  <TableCell>{addOn.abbr}</TableCell>
                  <TableCell>{addOn.cost ?? "-"}</TableCell>
                  <TableCell>{addOn.fixed_price ? "Yes" : "No"}</TableCell>
                  <TableCell>
                    <div className="flex justify-end">
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => onEdit(addOn)}
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

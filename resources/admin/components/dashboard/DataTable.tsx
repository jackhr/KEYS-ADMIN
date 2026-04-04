import type { ReactNode } from "react";

import { cn } from "../../lib/utils";

type DataTableProps = {
  children: ReactNode;
  className?: string;
};

export default function DataTable({ children, className }: DataTableProps) {
  return (
    <div
      className={cn(
        "w-full max-w-full overflow-x-auto overflow-y-hidden rounded-lg border bg-card",
        className
      )}
    >
      {children}
    </div>
  );
}

import * as React from "react";

import { cn } from "../../lib/utils";

type CheckboxProps = Omit<React.ComponentProps<"input">, "type">;

function Checkbox({ className, ...props }: CheckboxProps) {
  return (
    <input
      type="checkbox"
      data-slot="checkbox"
      className={cn(
        "border-input text-primary focus-visible:ring-ring h-4 w-4 rounded border focus-visible:ring-2",
        className
      )}
      {...props}
    />
  );
}

export { Checkbox };

import type { LucideIcon } from "lucide-react";

import { TabsList, TabsTrigger } from "../ui/tabs";

export type DashboardTabItem<T extends string> = {
  value: T;
  label: string;
  icon: LucideIcon;
};

type DashboardTabsProps<T extends string> = {
  tabs: DashboardTabItem<T>[];
};

export default function DashboardTabs<T extends string>({ tabs }: DashboardTabsProps<T>) {
  return (
    <TabsList className="flex h-auto w-full flex-col items-stretch gap-1 bg-transparent p-0">
      {tabs.map((tab) => (
        <TabsTrigger
          key={tab.value}
          value={tab.value}
          className="w-full justify-start rounded-lg px-3 py-2 text-sm font-medium whitespace-nowrap data-[state=active]:bg-accent"
        >
          <tab.icon className="h-4 w-4" />
          {tab.label}
        </TabsTrigger>
      ))}
    </TabsList>
  );
}

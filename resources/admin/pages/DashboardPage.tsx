import axios from "axios";
import { ChangeEvent, FormEvent, Suspense, lazy, useEffect, useMemo, useRef, useState } from "react";
import { LogOut, RefreshCw } from "lucide-react";
import {
  createAddOn,
  createVehicle,
  createVehicleDiscount,
  deleteAddOn,
  deleteVehicle,
  deleteVehicleDiscount,
  getAccountSettings,
  getDashboardAnalytics,
  getAddOns,
  getApiErrorMessage,
  getOrderRequest,
  getDashboardSummary,
  getOrderRequests,
  getVehicleDiscounts,
  getVehicles,
  updateAccountPassword,
  updateAccountProfile,
  updateAddOn,
  updateOrderStatus,
  updateVehicle,
  updateVehicleDiscount
} from "../lib/api";
import type {
  AddOn,
  AccountSettings,
  DashboardAnalytics,
  DashboardAnalyticsRange,
  DashboardSummary,
  OrderRequest,
  Vehicle,
  VehicleDraft,
  VehicleDiscount,
  DashboardPageProps,
  Section,
  ConfirmDialogState,
  PaginationMeta,
  LoadResourceOptions
} from "../types";
import DashboardTabs from "../components/dashboard/DashboardTabs";
import FormModal from "../components/dashboard/FormModal";
import { Button } from "../components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "../components/ui/card";
import { Checkbox } from "../components/ui/checkbox";
import {
  Modal,
  ModalContent,
  ModalDescription,
  ModalFooter,
  ModalHeader,
  ModalTitle
} from "../components/ui/modal";
import { Input } from "../components/ui/input";
import { Label } from "../components/ui/label";
import { Select } from "../components/ui/select";
import { Tabs, TabsContent } from "../components/ui/tabs";
import { Textarea } from "../components/ui/textarea";
import {
  addOnTemplate,
  discountTemplate,
  initialConfirmState,
  ORDER_REQUESTS_PER_PAGE,
  RESOURCE_CACHE_KEYS,
  sectionTabs,
  vehicleTemplate
} from "../consts";
import {
  formatDateTimeDisplay,
  initialPaginationMeta,
  readCachedResource,
  writeCachedResource
} from "../lib/utils";

const OverviewPage = lazy(() => import("./OverviewPage"));
const VehiclesPage = lazy(() => import("./VehiclesPage"));
const AddOnsPage = lazy(() => import("./AddOnsPage"));
const DiscountsPage = lazy(() => import("./DiscountsPage"));
const OrderRequestsPage = lazy(() => import("./OrderRequestsPage"));
const SettingsPage = lazy(() => import("./SettingsPage"));

export default function DashboardPage({ user, onLogout, onUserChange }: DashboardPageProps) {
  const [section, setSection] = useState<Section>("overview");
  const [busy, setBusy] = useState(false);
  const [feedback, setFeedback] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const [summary, setSummary] = useState<DashboardSummary | null>(null);
  const [analytics, setAnalytics] = useState<DashboardAnalytics | null>(null);
  const [analyticsRange, setAnalyticsRange] = useState<DashboardAnalyticsRange>("7d");
  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const [addOns, setAddOns] = useState<AddOn[]>([]);
  const [discounts, setDiscounts] = useState<VehicleDiscount[]>([]);
  const [orders, setOrders] = useState<OrderRequest[]>([]);
  const [accountSettings, setAccountSettings] = useState<AccountSettings | null>(null);
  const [orderRequestsMeta, setOrderRequestsMeta] = useState<PaginationMeta>(
    initialPaginationMeta(ORDER_REQUESTS_PER_PAGE)
  );
  const [orderRequestsPage, setOrderRequestsPage] = useState(1);
  const [orderDetailOpen, setOrderDetailOpen] = useState(false);
  const [selectedOrderRequest, setSelectedOrderRequest] = useState<OrderRequest | null>(null);

  const [vehicleModalOpen, setVehicleModalOpen] = useState(false);
  const [vehicleModalMode, setVehicleModalMode] = useState<"create" | "edit">("create");
  const [vehicleEditingId, setVehicleEditingId] = useState<number | null>(null);
  const [vehicleDraft, setVehicleDraft] = useState<VehicleDraft>(vehicleTemplate);
  const [vehicleImagePreviewUrl, setVehicleImagePreviewUrl] = useState<string | null>(null);
  const vehicleImageInputRef = useRef<HTMLInputElement | null>(null);

  const [addOnModalOpen, setAddOnModalOpen] = useState(false);
  const [addOnModalMode, setAddOnModalMode] = useState<"create" | "edit">("create");
  const [addOnEditingId, setAddOnEditingId] = useState<number | null>(null);
  const [addOnDraft, setAddOnDraft] = useState<Partial<AddOn>>(addOnTemplate);

  const [discountModalOpen, setDiscountModalOpen] = useState(false);
  const [discountModalMode, setDiscountModalMode] = useState<"create" | "edit">("create");
  const [discountEditingId, setDiscountEditingId] = useState<number | null>(null);
  const [discountDraft, setDiscountDraft] = useState<Partial<VehicleDiscount>>(discountTemplate);

  const [confirmDialog, setConfirmDialog] = useState<ConfirmDialogState>(initialConfirmState);
  const [confirmBusy, setConfirmBusy] = useState(false);

  const sortedVehicles = useMemo(
    () =>
      [...vehicles].sort(
        (a, b) =>
          (a.landing_order ?? Number.MAX_SAFE_INTEGER) -
          (b.landing_order ?? Number.MAX_SAFE_INTEGER)
      ),
    [vehicles]
  );

  useEffect(() => {
    if (!(vehicleDraft.image instanceof File)) {
      setVehicleImagePreviewUrl(null);
      return;
    }

    const previewUrl = URL.createObjectURL(vehicleDraft.image);
    setVehicleImagePreviewUrl(previewUrl);

    return () => URL.revokeObjectURL(previewUrl);
  }, [vehicleDraft.image]);

  const loadAll = async () => {
    setBusy(true);
    setError(null);

    try {
      const [summaryRes, analyticsRes, vehiclesRes, addOnsRes, discountsRes, ordersRes] = await Promise.all([
        getDashboardSummary(),
        getDashboardAnalytics(analyticsRange),
        getVehicles(),
        getAddOns(),
        getVehicleDiscounts(),
        getOrderRequests({ per_page: ORDER_REQUESTS_PER_PAGE, page: orderRequestsPage, status: "all" })
      ]);

      setSummary(summaryRes);
      setAnalytics(analyticsRes);
      setVehicles(vehiclesRes);
      setAddOns(addOnsRes);
      setDiscounts(discountsRes);
      setOrders(ordersRes.items);
      setOrderRequestsMeta(ordersRes.meta);
      writeCachedResource(RESOURCE_CACHE_KEYS.vehicles, vehiclesRes);
      writeCachedResource(RESOURCE_CACHE_KEYS.addOns, addOnsRes);
      writeCachedResource(RESOURCE_CACHE_KEYS.discounts, discountsRes);
    } catch (loadError) {
      if (axios.isAxiosError(loadError) && loadError.response?.status === 401) {
        setError("Your admin session expired. Please sign in again.");
        void onLogout();
        return;
      }

      setError(getApiErrorMessage(loadError));
    } finally {
      setBusy(false);
    }
  };

  const loadResource = async <TResource,>(
    apiGetter: () => Promise<TResource>,
    onSuccess: (data: TResource) => void,
    options?: LoadResourceOptions
  ) => {
    setBusy(true);
    setError(null);

    const shouldReadFromCache = options?.readFromCache ?? false;
    const shouldWriteToCache = options?.writeToCache ?? Boolean(options?.cacheKey);
    const cacheKey = options?.cacheKey;

    if (shouldReadFromCache && cacheKey) {
      const cached = readCachedResource<TResource>(cacheKey);

      if (cached !== null) {
        onSuccess(cached);
        setBusy(false);
        return;
      }
    }

    try {
      const data = await apiGetter();
      onSuccess(data);

      if (cacheKey && shouldWriteToCache) {
        writeCachedResource(cacheKey, data);
      }
    } catch (loadError) {
      if (axios.isAxiosError(loadError) && loadError.response?.status === 401) {
        setError("Your admin session expired. Please sign in again.");
        void onLogout();
        return;
      }

      setError(getApiErrorMessage(loadError));
    } finally {
      setBusy(false);
    }
  };

  const withFeedback = async (action: () => Promise<void>, successMessage: string) => {
    setBusy(true);
    setError(null);
    setFeedback(null);

    try {
      await action();
      setFeedback(successMessage);
    } catch (actionError) {
      setError(getApiErrorMessage(actionError));
    } finally {
      setBusy(false);
    }
  };

  const openConfirm = (title: string, description: string, action: () => Promise<void>) => {
    setConfirmDialog({
      open: true,
      title,
      description,
      action
    });
  };

  const executeConfirm = async () => {
    if (!confirmDialog.action) {
      return;
    }

    setConfirmBusy(true);

    try {
      await confirmDialog.action();
      setConfirmDialog(initialConfirmState);
    } finally {
      setConfirmBusy(false);
    }
  };

  const openVehicleCreateModal = () => {
    setVehicleModalMode("create");
    setVehicleEditingId(null);
    setVehicleDraft(vehicleTemplate);
    setVehicleModalOpen(true);
  };

  const openVehicleEditModal = (vehicle: Vehicle) => {
    setVehicleModalMode("edit");
    setVehicleEditingId(vehicle.id);
    setVehicleDraft({ ...vehicle, image: null });
    setVehicleModalOpen(true);
  };

  const handleVehicleImageChange = (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0] ?? null;

    setVehicleDraft((prev) => ({
      ...prev,
      image: file
    }));

    event.target.value = "";
  };

  const openVehicleImagePicker = () => {
    vehicleImageInputRef.current?.click();
  };

  const submitVehicleModal = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (vehicleModalMode === "edit" && vehicleEditingId === null) {
      setError("Vehicle edit context is missing.");
      return;
    }

    if (vehicleModalMode === "create" && !(vehicleDraft.image instanceof File)) {
      setError("Please upload a vehicle image.");
      return;
    }

    await withFeedback(async () => {
      if (vehicleModalMode === "create") {
        await createVehicle(vehicleDraft);
      } else {
        await updateVehicle(vehicleEditingId as number, vehicleDraft);
      }

      setVehicleModalOpen(false);
      setVehicleDraft(vehicleTemplate);
      setVehicleEditingId(null);
      await loadAll();
    }, vehicleModalMode === "create" ? "Vehicle created." : "Vehicle updated.");
  };

  const requestVehicleDelete = (vehicle: Vehicle) => {
    openConfirm(
      `Delete ${vehicle.name}?`,
      "This action cannot be undone.",
      async () => {
        await withFeedback(async () => {
          await deleteVehicle(vehicle.id);
          await loadAll();
        }, "Vehicle deleted.");
      }
    );
  };

  const requestVehicleDeleteFromModal = () => {
    if (vehicleModalMode !== "edit" || vehicleEditingId === null) {
      return;
    }

    const vehicle = vehicles.find((item) => item.id === vehicleEditingId);

    if (!vehicle) {
      setError("Vehicle no longer exists.");
      return;
    }

    setVehicleModalOpen(false);
    requestVehicleDelete(vehicle);
  };

  const vehicleModalImageSrc = vehicleImagePreviewUrl ?? vehicleDraft.image_url ?? null;

  const openAddOnCreateModal = () => {
    setAddOnModalMode("create");
    setAddOnEditingId(null);
    setAddOnDraft(addOnTemplate);
    setAddOnModalOpen(true);
  };

  const openAddOnEditModal = (addOn: AddOn) => {
    setAddOnModalMode("edit");
    setAddOnEditingId(addOn.id);
    setAddOnDraft({ ...addOn });
    setAddOnModalOpen(true);
  };

  const submitAddOnModal = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (addOnModalMode === "edit" && addOnEditingId === null) {
      setError("Add-on edit context is missing.");
      return;
    }

    await withFeedback(async () => {
      if (addOnModalMode === "create") {
        await createAddOn(addOnDraft);
      } else {
        await updateAddOn(addOnEditingId as number, addOnDraft);
      }

      setAddOnModalOpen(false);
      setAddOnDraft(addOnTemplate);
      setAddOnEditingId(null);
      await loadAll();
    }, addOnModalMode === "create" ? "Add-on created." : "Add-on updated.");
  };

  const requestAddOnDelete = (addOn: AddOn) => {
    openConfirm(
      `Delete ${addOn.name}?`,
      "This action cannot be undone.",
      async () => {
        await withFeedback(async () => {
          await deleteAddOn(addOn.id);
          await loadAll();
        }, "Add-on deleted.");
      }
    );
  };

  const requestAddOnDeleteFromModal = () => {
    if (addOnModalMode !== "edit" || addOnEditingId === null) {
      return;
    }

    const addOn = addOns.find((item) => item.id === addOnEditingId);

    if (!addOn) {
      setError("Add-on no longer exists.");
      return;
    }

    setAddOnModalOpen(false);
    requestAddOnDelete(addOn);
  };

  const openDiscountCreateModal = () => {
    setDiscountModalMode("create");
    setDiscountEditingId(null);
    setDiscountDraft(discountTemplate);
    setDiscountModalOpen(true);
  };

  const openDiscountEditModal = (discount: VehicleDiscount) => {
    setDiscountModalMode("edit");
    setDiscountEditingId(discount.id);
    setDiscountDraft({
      vehicle_id: discount.vehicle_id,
      days: discount.days,
      price_USD: discount.price_USD,
      price_XCD: discount.price_XCD
    });
    setDiscountModalOpen(true);
  };

  const submitDiscountModal = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (discountModalMode === "edit" && discountEditingId === null) {
      setError("Discount edit context is missing.");
      return;
    }

    await withFeedback(async () => {
      if (discountModalMode === "create") {
        await createVehicleDiscount(discountDraft);
      } else {
        await updateVehicleDiscount(discountEditingId as number, discountDraft);
      }

      setDiscountModalOpen(false);
      setDiscountDraft(discountTemplate);
      setDiscountEditingId(null);
      await loadAll();
    }, discountModalMode === "create" ? "Discount created." : "Discount updated.");
  };

  const requestDiscountDelete = (discount: VehicleDiscount) => {
    openConfirm(
      `Delete discount #${discount.id}?`,
      "This action cannot be undone.",
      async () => {
        await withFeedback(async () => {
          await deleteVehicleDiscount(discount.id);
          await loadAll();
        }, "Discount deleted.");
      }
    );
  };

  const requestDiscountDeleteFromModal = () => {
    if (discountModalMode !== "edit" || discountEditingId === null) {
      return;
    }

    const discount = discounts.find((item) => item.id === discountEditingId);

    if (!discount) {
      setError("Discount no longer exists.");
      return;
    }

    setDiscountModalOpen(false);
    requestDiscountDelete(discount);
  };

  const toggleOrderStatusHandler = async (order: OrderRequest) => {
    await withFeedback(async () => {
      await updateOrderStatus(order.id, order.status === "confirmed" ? "pending" : "confirmed");
      await loadAll();
    }, `Order #${order.id} updated.`);
  };

  const openOrderRequestDetail = (order: OrderRequest) => {
    setSelectedOrderRequest(order);
    setOrderDetailOpen(true);
    void loadResource(() => getOrderRequest(order.id), (data) => {
      setSelectedOrderRequest(data);
    });
  };

  const refreshAccountSettings = () => {
    void loadResource(getAccountSettings, (data) => {
      setAccountSettings(data);
      onUserChange(data.user);
    });
  };

  const updateAccountProfileHandler = async (payload: { username: string; email: string | null }) => {
    await withFeedback(async () => {
      const updatedUser = await updateAccountProfile(payload);

      onUserChange(updatedUser);
      setAccountSettings((previous) => {
        if (!previous) {
          return {
            user: updatedUser,
            session: {
              token_created_at: null,
              token_last_used_at: null,
              token_expires_at: null
            }
          };
        }

        return {
          ...previous,
          user: updatedUser
        };
      });
    }, "Account profile updated.");
  };

  const updateAccountPasswordHandler = async (payload: {
    current_password: string;
    password: string;
    password_confirmation: string;
  }) => {
    await withFeedback(async () => {
      await updateAccountPassword(payload);
      const latestAccount = await getAccountSettings();
      setAccountSettings(latestAccount);
      onUserChange(latestAccount.user);
    }, "Password updated.");
  };

  const formatPaginationRange = (meta: PaginationMeta) => {
    if (meta.total === 0) {
      return "0 of 0";
    }

    const start = (meta.current_page - 1) * meta.per_page + 1;
    const end = Math.min(meta.total, meta.current_page * meta.per_page);
    return `${start}-${end} of ${meta.total}`;
  };

  useEffect(() => {
    // need to fetch the latest data of a section when it's visited,
    // in case the user made changes in another section that would affect it
    // (e.g. creating a discount would affect the overview analytics)
    switch (section) {
      case "overview":
        void loadResource(getDashboardSummary, (data) => {
          setSummary(data);
        });
        void loadResource(() => getDashboardAnalytics(analyticsRange), (data) => {
          setAnalytics(data);
        });
        break;
      case "vehicles":
        void loadResource(getVehicles, (data) => {
          setVehicles(data);
        }, {
          cacheKey: RESOURCE_CACHE_KEYS.vehicles,
          readFromCache: true
        });
        break;
      case "addons":
        void loadResource(getAddOns, (data) => {
          setAddOns(data);
        }, {
          cacheKey: RESOURCE_CACHE_KEYS.addOns,
          readFromCache: true
        });
        break;
      case "discounts":
        void loadResource(getVehicleDiscounts, (data) => {
          setDiscounts(data);
        }, {
          cacheKey: RESOURCE_CACHE_KEYS.discounts,
          readFromCache: true
        });
        break;
      case "orders":
        void loadResource(
          () => getOrderRequests({ per_page: ORDER_REQUESTS_PER_PAGE, page: orderRequestsPage, status: "all" }),
          (data) => {
            setOrders(data.items);
            setOrderRequestsMeta(data.meta);
          }
        );
        break;
      case "settings":
        void loadResource(getAccountSettings, (data) => {
          setAccountSettings(data);
          onUserChange(data.user);
        });
        break;
    }
  }, [analyticsRange, onLogout, onUserChange, orderRequestsPage, section]);

  if (!summary) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-muted/40 p-6">
        <Card className="w-full max-w-lg">
          <CardHeader>
            <CardTitle>The Keys Admin Dashboard</CardTitle>
            <CardDescription>Load the latest data to begin.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {error ? (
              <div className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm font-medium text-destructive">
                {error}
              </div>
            ) : null}
            <Button onClick={() => void loadAll()} disabled={busy}>
              <RefreshCw className="h-4 w-4" />
              {busy ? "Loading..." : "Load Dashboard"}
            </Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-muted/35 p-4 md:p-6">
      <div className="mx-auto max-w-350 space-y-6">
        <Card className="border-border/70 shadow-sm">
          <CardHeader className="flex flex-col gap-4 pb-5 md:flex-row md:items-center md:justify-between">
            <div>
              <CardTitle className="text-3xl">The Keys Admin Dashboard</CardTitle>
              <CardDescription>Signed in as {user.username}. Manage fleet, pricing, and requests.</CardDescription>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <Button variant="outline" onClick={() => void loadAll()} disabled={busy}>
                <RefreshCw className="h-4 w-4" />
                {busy ? "Refreshing..." : "Refresh"}
              </Button>
              <Button variant="destructive" onClick={() => void onLogout()} disabled={busy}>
                <LogOut className="h-4 w-4" />
                Logout
              </Button>
            </div>
          </CardHeader>
        </Card>

        {feedback ? (
          <div className="rounded-md border border-emerald-300/70 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700">
            {feedback}
          </div>
        ) : null}
        {error ? (
          <div className="rounded-md border border-destructive/30 bg-destructive/10 px-4 py-2 text-sm font-medium text-destructive">
            {error}
          </div>
        ) : null}

        <Tabs
          value={section}
          onValueChange={(value) => setSection(value as Section)}
          className="grid gap-6 lg:grid-cols-[260px_1fr]"
        >
          <aside className="space-y-4">
            <Card className="overflow-hidden border-border/70">
              <CardHeader className="border-b bg-card/70 py-4">
                <CardTitle className="text-base">Navigation</CardTitle>
                <CardDescription>Switch between admin modules.</CardDescription>
              </CardHeader>
              <CardContent className="p-3">
                <DashboardTabs tabs={sectionTabs} />
              </CardContent>
            </Card>
            <Card className="border-border/70">
              <CardHeader>
                <CardTitle className="text-sm">Snapshot</CardTitle>
                <CardDescription>Current totals from the live booking database.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-2 text-sm text-muted-foreground">
                <p>{summary.vehicles_total} vehicles</p>
                <p>{summary.vehicles_showing} visible on site</p>
                <p>{summary.add_ons_total} add-ons</p>
                <p>{summary.vehicle_discounts_total} discount rows</p>
                <p>{summary.order_requests_total} order requests</p>
              </CardContent>
            </Card>
          </aside>

          <div className="min-w-0 space-y-6">
            <Suspense
              fallback={
                <Card className="border-border/70 shadow-sm">
                  <CardContent className="py-8 text-sm text-muted-foreground">Loading section...</CardContent>
                </Card>
              }
            >
              <TabsContent value="overview" className="space-y-4">
                <OverviewPage
                  summary={summary}
                  analytics={analytics}
                  analyticsRange={analyticsRange}
                  busy={busy}
                  onAnalyticsRangeChange={setAnalyticsRange}
                />
              </TabsContent>

              <TabsContent value="vehicles" className="space-y-4">
                <VehiclesPage
                  vehicles={sortedVehicles}
                  busy={busy}
                  onCreate={openVehicleCreateModal}
                  onEdit={openVehicleEditModal}
                />
              </TabsContent>

              <TabsContent value="addons" className="space-y-4">
                <AddOnsPage
                  addOns={addOns}
                  busy={busy}
                  onCreate={openAddOnCreateModal}
                  onEdit={openAddOnEditModal}
                />
              </TabsContent>

              <TabsContent value="discounts" className="space-y-4">
                <DiscountsPage
                  discounts={discounts}
                  busy={busy}
                  onCreate={openDiscountCreateModal}
                  onEdit={openDiscountEditModal}
                />
              </TabsContent>

              <TabsContent value="orders" className="space-y-4">
                <OrderRequestsPage
                  orders={orders}
                  busy={busy}
                  onOpenDetail={openOrderRequestDetail}
                  onToggleStatus={(order) => void toggleOrderStatusHandler(order)}
                  paginationLabel={formatPaginationRange(orderRequestsMeta)}
                  currentPage={orderRequestsMeta.current_page}
                  lastPage={orderRequestsMeta.last_page}
                  canGoPrevious={orderRequestsMeta.current_page > 1}
                  canGoNext={orderRequestsMeta.current_page < orderRequestsMeta.last_page}
                  onPreviousPage={() => setOrderRequestsPage((prev) => Math.max(1, prev - 1))}
                  onNextPage={() =>
                    setOrderRequestsPage((prev) => Math.min(Math.max(1, orderRequestsMeta.last_page), prev + 1))
                  }
                />
              </TabsContent>

              <TabsContent value="settings" className="space-y-4">
                <SettingsPage
                  user={user}
                  accountSettings={accountSettings}
                  busy={busy}
                  onRefreshAccount={refreshAccountSettings}
                  onUpdateProfile={updateAccountProfileHandler}
                  onUpdatePassword={updateAccountPasswordHandler}
                />
              </TabsContent>
            </Suspense>
          </div>
        </Tabs>

        <FormModal
          open={vehicleModalOpen}
          onOpenChange={setVehicleModalOpen}
          title={vehicleModalMode === "create" ? "Add Vehicle" : vehicleDraft.name?.trim() || "Edit Vehicle"}
          description="Set pricing, specs, and visibility for this vehicle."
          onSubmit={(event) => void submitVehicleModal(event)}
          submitLabel={vehicleModalMode === "create" ? "Create Vehicle" : "Save Changes"}
          loading={busy}
          dangerActionLabel={vehicleModalMode === "edit" ? "Delete Vehicle" : undefined}
          onDangerAction={vehicleModalMode === "edit" ? requestVehicleDeleteFromModal : undefined}
        >
          <div className="space-y-2">
            <div className="space-y-2">
              <div className="group bg-muted/40 relative flex h-48 w-64 items-center justify-center overflow-hidden rounded-xl border m-auto">
                {vehicleModalImageSrc ? (
                  <img
                    src={vehicleModalImageSrc}
                    alt={`${vehicleDraft.name || "Vehicle"} preview`}
                    className="h-full w-full object-cover"
                  />
                ) : (
                  <span className="text-muted-foreground px-3 text-center text-xs">No image selected</span>
                )}
                <div className="pointer-events-none absolute inset-0 bg-black/0 transition-colors group-hover:bg-black/10 group-focus-within:bg-black/10" />
                <Button
                  type="button"
                  variant="secondary"
                  size="sm"
                  className="pointer-events-none absolute top-3 right-3 opacity-0 shadow-sm transition-all duration-200 group-hover:pointer-events-auto group-hover:opacity-100 group-focus-within:pointer-events-auto group-focus-within:opacity-100"
                  onClick={openVehicleImagePicker}
                >
                  {vehicleModalImageSrc ? "Change" : "Upload"}
                </Button>
              </div>
              <input
                id="vehicle-image"
                ref={vehicleImageInputRef}
                type="file"
                accept=".avif,.jpg,.jpeg,.png,.webp,image/avif,image/jpeg,image/png,image/webp"
                className="sr-only"
                onChange={handleVehicleImageChange}
              />
              <p className="text-muted-foreground text-xs mt-1">
                Accepted file types: JPG, PNG, WebP, and AVIF. Max size: 10MB.
              </p>
              {vehicleDraft.image ? (
                <p className="text-muted-foreground text-xs">Selected file: {vehicleDraft.image.name}</p>
              ) : null}
            </div>
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="vehicle-name">Name</Label>
              <Input
                id="vehicle-name"
                value={vehicleDraft.name ?? ""}
                onChange={(event) => setVehicleDraft((prev) => ({ ...prev, name: event.target.value }))}
                required
              />
              <p className="text-muted-foreground text-xs">
                Slug and image filename are generated automatically from the vehicle name.
              </p>
            </div>
            <div className="space-y-2">
              <Label htmlFor="vehicle-type">Type</Label>
              <Input
                id="vehicle-type"
                value={vehicleDraft.type ?? ""}
                onChange={(event) => setVehicleDraft((prev) => ({ ...prev, type: event.target.value }))}
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="vehicle-usd">USD / Day</Label>
              <Input
                id="vehicle-usd"
                type="number"
                value={vehicleDraft.base_price_USD ?? 0}
                onChange={(event) => setVehicleDraft((prev) => ({ ...prev, base_price_USD: Number(event.target.value) }))}
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="vehicle-xcd">XCD / Day</Label>
              <Input
                id="vehicle-xcd"
                type="number"
                value={vehicleDraft.base_price_XCD ?? 0}
                onChange={(event) => setVehicleDraft((prev) => ({ ...prev, base_price_XCD: Number(event.target.value) }))}
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="vehicle-insurance">Insurance</Label>
              <Input
                id="vehicle-insurance"
                type="number"
                value={vehicleDraft.insurance ?? 0}
                onChange={(event) => setVehicleDraft((prev) => ({ ...prev, insurance: Number(event.target.value) }))}
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="vehicle-landing-order">Landing Order</Label>
              <Input
                id="vehicle-landing-order"
                type="number"
                value={vehicleDraft.landing_order ?? ""}
                onChange={(event) =>
                  setVehicleDraft((prev) => ({
                    ...prev,
                    landing_order: event.target.value === "" ? null : Number(event.target.value)
                  }))
                }
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="vehicle-seats">Seats</Label>
              <Input
                id="vehicle-seats"
                type="number"
                value={vehicleDraft.people ?? 4}
                onChange={(event) => setVehicleDraft((prev) => ({ ...prev, people: Number(event.target.value) }))}
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="vehicle-bags">Bags</Label>
              <Input
                id="vehicle-bags"
                type="number"
                value={vehicleDraft.bags ?? 0}
                onChange={(event) =>
                  setVehicleDraft((prev) => ({
                    ...prev,
                    bags: event.target.value === "" ? null : Number(event.target.value)
                  }))
                }
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="vehicle-doors">Doors</Label>
              <Input
                id="vehicle-doors"
                type="number"
                value={vehicleDraft.doors ?? 4}
                onChange={(event) => setVehicleDraft((prev) => ({ ...prev, doors: Number(event.target.value) }))}
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="vehicle-requests">Times Requested</Label>
              <Input
                id="vehicle-requests"
                type="number"
                value={vehicleDraft.times_requested ?? 0}
                onChange={(event) =>
                  setVehicleDraft((prev) => ({
                    ...prev,
                    times_requested: Number(event.target.value)
                  }))
                }
              />
            </div>
          </div>
          <div className="grid gap-3 md:grid-cols-3">
            <label className="flex items-center gap-2 text-sm font-medium">
              <Checkbox
                checked={Boolean(vehicleDraft.showing)}
                onChange={(event) => setVehicleDraft((prev) => ({ ...prev, showing: event.target.checked }))}
              />
              Showing
            </label>
            <label className="flex items-center gap-2 text-sm font-medium">
              <Checkbox
                checked={Boolean(vehicleDraft.ac)}
                onChange={(event) => setVehicleDraft((prev) => ({ ...prev, ac: event.target.checked }))}
              />
              A/C
            </label>
            <label className="flex items-center gap-2 text-sm font-medium">
              <Checkbox
                checked={Boolean(vehicleDraft.manual)}
                onChange={(event) => setVehicleDraft((prev) => ({ ...prev, manual: event.target.checked }))}
              />
              Manual
            </label>
            <label className="flex items-center gap-2 text-sm font-medium">
              <Checkbox
                checked={Boolean(vehicleDraft.four_wd)}
                onChange={(event) => setVehicleDraft((prev) => ({ ...prev, four_wd: event.target.checked }))}
              />
              4WD
            </label>
          </div>
        </FormModal>

        <FormModal
          open={addOnModalOpen}
          onOpenChange={setAddOnModalOpen}
          title={addOnModalMode === "create" ? "Add Add-On" : "Edit Add-On"}
          description="Add optional products or services for bookings."
          onSubmit={(event) => void submitAddOnModal(event)}
          submitLabel={addOnModalMode === "create" ? "Create Add-On" : "Save Changes"}
          loading={busy}
          dangerActionLabel={addOnModalMode === "edit" ? "Delete Add-On" : undefined}
          onDangerAction={addOnModalMode === "edit" ? requestAddOnDeleteFromModal : undefined}
        >
          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="addon-name">Name</Label>
              <Input
                id="addon-name"
                value={addOnDraft.name ?? ""}
                onChange={(event) => setAddOnDraft((prev) => ({ ...prev, name: event.target.value }))}
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="addon-abbr">Abbreviation</Label>
              <Input
                id="addon-abbr"
                value={addOnDraft.abbr ?? ""}
                onChange={(event) => setAddOnDraft((prev) => ({ ...prev, abbr: event.target.value }))}
                required
              />
            </div>
            <div className="space-y-2 md:col-span-2">
              <Label htmlFor="addon-description">Description</Label>
              <Textarea
                id="addon-description"
                value={addOnDraft.description ?? ""}
                onChange={(event) => setAddOnDraft((prev) => ({ ...prev, description: event.target.value }))}
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="addon-cost">Cost</Label>
              <Input
                id="addon-cost"
                type="number"
                value={addOnDraft.cost ?? 0}
                onChange={(event) =>
                  setAddOnDraft((prev) => ({
                    ...prev,
                    cost: event.target.value === "" ? null : Number(event.target.value)
                  }))
                }
              />
            </div>
            <label className="flex items-end gap-2 text-sm font-medium">
              <Checkbox
                checked={Boolean(addOnDraft.fixed_price)}
                onChange={(event) => setAddOnDraft((prev) => ({ ...prev, fixed_price: event.target.checked }))}
              />
              Fixed price
            </label>
          </div>
        </FormModal>

        <FormModal
          open={discountModalOpen}
          onOpenChange={setDiscountModalOpen}
          title={discountModalMode === "create" ? "Add Discount" : "Edit Discount"}
          description="Set discounted daily prices for longer rentals."
          onSubmit={(event) => void submitDiscountModal(event)}
          submitLabel={discountModalMode === "create" ? "Create Discount" : "Save Changes"}
          loading={busy}
          dangerActionLabel={discountModalMode === "edit" ? "Delete Discount" : undefined}
          onDangerAction={discountModalMode === "edit" ? requestDiscountDeleteFromModal : undefined}
        >
          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2 md:col-span-2">
              <Label htmlFor="discount-vehicle">Vehicle</Label>
              <Select
                id="discount-vehicle"
                value={discountDraft.vehicle_id ?? 0}
                onChange={(event) =>
                  setDiscountDraft((prev) => ({
                    ...prev,
                    vehicle_id: Number(event.target.value)
                  }))
                }
                required
              >
                <option value={0}>Select vehicle...</option>
                {vehicles.map((vehicle) => (
                  <option key={vehicle.id} value={vehicle.id}>
                    {vehicle.name}
                  </option>
                ))}
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="discount-days">Days</Label>
              <Input
                id="discount-days"
                type="number"
                value={discountDraft.days ?? 4}
                onChange={(event) =>
                  setDiscountDraft((prev) => ({
                    ...prev,
                    days: Number(event.target.value)
                  }))
                }
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="discount-usd">USD</Label>
              <Input
                id="discount-usd"
                type="number"
                value={discountDraft.price_USD ?? 0}
                onChange={(event) =>
                  setDiscountDraft((prev) => ({
                    ...prev,
                    price_USD: Number(event.target.value)
                  }))
                }
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="discount-xcd">XCD</Label>
              <Input
                id="discount-xcd"
                type="number"
                value={discountDraft.price_XCD ?? 0}
                onChange={(event) =>
                  setDiscountDraft((prev) => ({
                    ...prev,
                    price_XCD: Number(event.target.value)
                  }))
                }
                required
              />
            </div>
          </div>
        </FormModal>

        <Modal
          open={orderDetailOpen}
          onOpenChange={(open) => {
            setOrderDetailOpen(open);

            if (!open) {
              setSelectedOrderRequest(null);
            }
          }}
        >
          <ModalContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
            <ModalHeader>
              <ModalTitle>Order Request #{selectedOrderRequest?.id ?? "-"}</ModalTitle>
              <ModalDescription>Full details from the reservation request.</ModalDescription>
            </ModalHeader>

            {selectedOrderRequest ? (
              <div className="space-y-4 text-sm">
                <div className="grid gap-3 md:grid-cols-2">
                  <div>
                    <p className="text-muted-foreground text-xs font-semibold uppercase">Reservation Key</p>
                    <p>{selectedOrderRequest.key}</p>
                  </div>
                  <div>
                    <p className="text-muted-foreground text-xs font-semibold uppercase">Status</p>
                    <p className="capitalize">{selectedOrderRequest.status}</p>
                  </div>
                  <div>
                    <p className="text-muted-foreground text-xs font-semibold uppercase">Customer</p>
                    <p>
                      {selectedOrderRequest.contact_info
                        ? `${selectedOrderRequest.contact_info.first_name} ${selectedOrderRequest.contact_info.last_name}`
                        : "-"}
                    </p>
                  </div>
                  <div>
                    <p className="text-muted-foreground text-xs font-semibold uppercase">Email</p>
                    <p>{selectedOrderRequest.contact_info?.email ?? "-"}</p>
                  </div>
                  <div>
                    <p className="text-muted-foreground text-xs font-semibold uppercase">Phone</p>
                    <p>{selectedOrderRequest.contact_info?.phone ?? "-"}</p>
                  </div>
                  <div>
                    <p className="text-muted-foreground text-xs font-semibold uppercase">Vehicle</p>
                    <p>{selectedOrderRequest.vehicle?.name ?? "-"}</p>
                  </div>
                  <div>
                    <p className="text-muted-foreground text-xs font-semibold uppercase">Pick Up</p>
                    <p>{formatDateTimeDisplay(selectedOrderRequest.pick_up)}</p>
                  </div>
                  <div>
                    <p className="text-muted-foreground text-xs font-semibold uppercase">Drop Off</p>
                    <p>{formatDateTimeDisplay(selectedOrderRequest.drop_off)}</p>
                  </div>
                  <div>
                    <p className="text-muted-foreground text-xs font-semibold uppercase">Pick Up Location</p>
                    <p>{selectedOrderRequest.pick_up_location}</p>
                  </div>
                  <div>
                    <p className="text-muted-foreground text-xs font-semibold uppercase">Drop Off Location</p>
                    <p>{selectedOrderRequest.drop_off_location}</p>
                  </div>
                  <div>
                    <p className="text-muted-foreground text-xs font-semibold uppercase">Days</p>
                    <p>{selectedOrderRequest.days}</p>
                  </div>
                  <div>
                    <p className="text-muted-foreground text-xs font-semibold uppercase">Subtotal</p>
                    <p>${selectedOrderRequest.sub_total.toFixed(2)}</p>
                  </div>
                  <div>
                    <p className="text-muted-foreground text-xs font-semibold uppercase">Created</p>
                    <p>{formatDateTimeDisplay(selectedOrderRequest.created_at)}</p>
                  </div>
                  <div>
                    <p className="text-muted-foreground text-xs font-semibold uppercase">Updated</p>
                    <p>{formatDateTimeDisplay(selectedOrderRequest.updated_at)}</p>
                  </div>
                </div>

                <div>
                  <p className="text-muted-foreground mb-2 text-xs font-semibold uppercase">Add-Ons</p>
                  {selectedOrderRequest.add_ons.length === 0 ? (
                    <p className="text-muted-foreground">No add-ons selected.</p>
                  ) : (
                    <div className="overflow-x-auto rounded-md border">
                      <table className="w-full min-w-[480px] text-left text-sm">
                        <thead className="bg-muted/30">
                          <tr>
                            <th className="px-3 py-2 font-medium">Name</th>
                            <th className="px-3 py-2 font-medium">Quantity</th>
                            <th className="px-3 py-2 font-medium">Cost</th>
                          </tr>
                        </thead>
                        <tbody>
                          {selectedOrderRequest.add_ons.map((item) => (
                            <tr key={item.id} className="border-t">
                              <td className="px-3 py-2">{item.add_on?.name ?? "-"}</td>
                              <td className="px-3 py-2">{item.quantity}</td>
                              <td className="px-3 py-2">
                                {typeof item.add_on?.cost === "number" ? `$${item.add_on.cost.toFixed(2)}` : "-"}
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  )}
                </div>

                {selectedOrderRequest.history.length > 0 ? (
                  <div>
                    <p className="text-muted-foreground mb-2 text-xs font-semibold uppercase">History</p>
                    <div className="space-y-3">
                      {selectedOrderRequest.history.map((entry) => (
                        <div key={entry.id} className="rounded-lg border bg-muted/20 px-3 py-2">
                          <div className="flex flex-wrap items-center justify-between gap-2">
                            <p className="font-medium">{entry.admin_user}</p>
                            <p className="text-muted-foreground text-xs">{formatDateTimeDisplay(entry.created_at)}</p>
                          </div>
                          <p className="text-muted-foreground mt-1 text-xs uppercase">{entry.action}</p>
                          <p className="mt-1 whitespace-pre-wrap">{entry.change_summary || "No summary available."}</p>
                        </div>
                      ))}
                    </div>
                  </div>
                ) : null}
              </div>
            ) : null}

            <ModalFooter>
              <Button type="button" variant="outline" onClick={() => setOrderDetailOpen(false)}>
                Close
              </Button>
            </ModalFooter>
          </ModalContent>
        </Modal>

        <Modal
          open={confirmDialog.open}
          onOpenChange={(open) =>
            setConfirmDialog((prev) => ({
              ...prev,
              open,
              action: open ? prev.action : null
            }))
          }
        >
          <ModalContent>
            <ModalHeader>
              <ModalTitle>{confirmDialog.title}</ModalTitle>
              <ModalDescription>{confirmDialog.description}</ModalDescription>
            </ModalHeader>
            <ModalFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setConfirmDialog(initialConfirmState)}
                disabled={confirmBusy}
              >
                Cancel
              </Button>
              <Button type="button" variant="destructive" onClick={() => void executeConfirm()} disabled={confirmBusy}>
                {confirmBusy ? "Working..." : "Confirm"}
              </Button>
            </ModalFooter>
          </ModalContent>
        </Modal>
      </div>
    </div>
  );
}

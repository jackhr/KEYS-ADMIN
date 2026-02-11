const ADMIN_PORTAL = {
    vehicles: [],
    vehicleMap: new Map(),
    activeId: null,
    isDirty: false,
    isHydrating: false,
    orders: [],
    addons: [],
    addonMap: new Map(),
    activeAddonId: null,
    addonsDirty: false,
    addonsHydrating: false,
    discounts: [],
    discountMap: new Map(),
    activeDiscountVehicleId: null,
    discountsDirty: false,
    discountsHydrating: false,
    activeSection: "vehicles"
};

const boolFromValue = (value) => value === 1 || value === "1" || value === true;

const toInt = (value) => {
    const number = parseInt(value, 10);
    return Number.isNaN(number) ? 0 : number;
};

const toFloat = (value) => {
    const number = parseFloat(value);
    return Number.isNaN(number) ? 0 : number;
};

const buildSearchKey = (vehicle) => {
    const name = vehicle.name || "";
    const type = vehicle.type || "";
    const slug = vehicle.slug || "";
    return `${name} ${type} ${slug}`.toLowerCase().trim();
};

const parseJsonScript = (id) => {
    const el = document.getElementById(id);
    if (!el) return null;
    try {
        return JSON.parse(el.textContent);
    } catch (error) {
        return null;
    }
};

const stripTrailingZeros = (value) => {
    if (typeof value !== "number" || Number.isNaN(value)) return "";
    return value % 1 === 0 ? value.toString() : value.toFixed(2).replace(/\.?0+$/, "");
};

const setSection = (section) => {
    const sections = Array.from(document.querySelectorAll(".admin-section"));
    const tabs = Array.from(document.querySelectorAll(".admin-tab"));
    if (sections.length === 0 || tabs.length === 0) return false;

    const sectionNames = new Set(sections.map((el) => el.dataset.section));
    if (!sectionNames.has(section)) return false;

    if (ADMIN_PORTAL.activeSection && ADMIN_PORTAL.activeSection !== section) {
        if (ADMIN_PORTAL.activeSection === "vehicles" && ADMIN_PORTAL.isDirty) {
            if (!window.confirm("Discard unsaved vehicle changes?")) return false;
        }
        if (ADMIN_PORTAL.activeSection === "addons" && ADMIN_PORTAL.addonsDirty) {
            if (!window.confirm("Discard unsaved add-on changes?")) return false;
        }
        if (ADMIN_PORTAL.activeSection === "discounts" && ADMIN_PORTAL.discountsDirty) {
            if (!window.confirm("Discard unsaved discount changes?")) return false;
        }
    }

    ADMIN_PORTAL.activeSection = section;
    tabs.forEach((tab) => tab.classList.toggle("active", tab.dataset.section === section));
    sections.forEach((panel) => {
        panel.hidden = panel.dataset.section !== section;
    });

    try {
        localStorage.setItem("admin_section", section);
    } catch (error) {
        // ignore
    }

    if (window.location.hash.replace("#", "") !== section) {
        history.replaceState(null, "", `#${section}`);
    }

    return true;
};

const initTabs = () => {
    const tabs = Array.from(document.querySelectorAll(".admin-tab"));
    if (tabs.length === 0) return;

    tabs.forEach((tab) => {
        tab.addEventListener("click", () => {
            setSection(tab.dataset.section);
        });
    });

    const hashSection = window.location.hash.replace("#", "");
    if (hashSection && setSection(hashSection)) return;

    try {
        const stored = localStorage.getItem("admin_section");
        if (stored && setSection(stored)) return;
    } catch (error) {
        // ignore
    }

    setSection(tabs[0].dataset.section || "vehicles");
};

const initVehicles = () => {
    const data = parseJsonScript("vehicle-data");
    if (!data) return;

    ADMIN_PORTAL.vehicles = data || [];
    ADMIN_PORTAL.vehicleMap = new Map(
        ADMIN_PORTAL.vehicles.map((vehicle) => [String(vehicle.id), vehicle])
    );

    const form = document.getElementById("vehicle-form");
    const emptyState = document.querySelector(".vehicle-editor .editor-empty");
    const statusEl = document.getElementById("vehicle-status");
    const saveBtn = form.querySelector(".admin-save");
    const resetBtn = form.querySelector(".admin-reset");
    const listItems = Array.from(document.querySelectorAll(".vehicle-item"));
    const searchInput = document.getElementById("vehicle-search");
    const noResults = document.querySelector(".vehicle-empty");

    if (!form || !saveBtn || !resetBtn || !statusEl) return;

    const fields = {
        id: form.querySelector('[name="id"]'),
        name: form.querySelector('[name="name"]'),
        type: form.querySelector('[name="type"]'),
        slug: form.querySelector('[name="slug"]'),
        base_price_USD: form.querySelector('[name="base_price_USD"]'),
        insurance: form.querySelector('[name="insurance"]'),
        people: form.querySelector('[name="people"]'),
        bags: form.querySelector('[name="bags"]'),
        doors: form.querySelector('[name="doors"]'),
        manual: form.querySelector('[name="manual"]'),
        ac: form.querySelector('[name="ac"]'),
        fourwd: form.querySelector('[name="4wd"]'),
        showing: form.querySelector('[name="showing"]'),
        landing_order: form.querySelector('[name="landing_order"]')
    };

    const preview = {
        img: document.querySelector(".preview-image img"),
        title: document.querySelector(".preview-title"),
        subtitle: document.querySelector(".preview-subtitle"),
        price: document.querySelector(".preview-price"),
        showing: document.querySelector(".showing-preview"),
        landing: document.querySelector(".landing-preview")
    };

    const setStatus = (message, state = "") => {
        statusEl.textContent = message || "";
        statusEl.classList.remove("success", "error", "info");
        if (state) statusEl.classList.add(state);
    };

    const setDirty = (isDirty) => {
        ADMIN_PORTAL.isDirty = isDirty;
        saveBtn.disabled = !isDirty;
        form.classList.toggle("is-dirty", isDirty);
    };

    const updateTag = (tagEl, enabled, label) => {
        if (!tagEl) return;
        tagEl.textContent = label;
        tagEl.classList.toggle("tag-on", enabled);
        tagEl.classList.toggle("tag-off", !enabled);
    };

    const updatePreview = (vehicle) => {
        if (!vehicle) return;
        const name = vehicle.name || "Vehicle";
        const type = vehicle.type || "Type";
        const price = vehicle.base_price_USD ?? 0;
        const slug = vehicle.slug || "";
        const imgSrc = slug ? `/assets/images/vehicles/${slug}.avif` : "/assets/images/logo.avif";

        if (preview.title) preview.title.textContent = name;
        if (preview.subtitle) preview.subtitle.textContent = type;
        if (preview.price) preview.price.textContent = `USD$${price}/day`;
        if (preview.img) {
            preview.img.src = imgSrc;
            preview.img.alt = `${name} image`;
        }

        const showingEnabled = boolFromValue(vehicle.showing);
        const landingEnabled = vehicle.landing_order !== null && vehicle.landing_order !== "" && vehicle.landing_order !== undefined;
        const landingLabel = landingEnabled ? `Landing #${vehicle.landing_order}` : "Landing Hidden";

        updateTag(preview.showing, showingEnabled, "Showing");
        updateTag(preview.landing, landingEnabled, landingLabel);
    };

    const fillForm = (vehicle) => {
        ADMIN_PORTAL.isHydrating = true;
        fields.id.value = vehicle.id ?? "";
        fields.name.value = vehicle.name ?? "";
        fields.type.value = vehicle.type ?? "";
        fields.slug.value = vehicle.slug ?? "";
        fields.base_price_USD.value = vehicle.base_price_USD ?? "";
        fields.insurance.value = vehicle.insurance ?? "";
        fields.people.value = vehicle.people ?? "";
        fields.bags.value = vehicle.bags ?? "";
        fields.doors.value = vehicle.doors ?? "";
        fields.manual.checked = boolFromValue(vehicle.manual);
        fields.ac.checked = boolFromValue(vehicle.ac);
        fields.fourwd.checked = boolFromValue(vehicle["4wd"]);
        fields.showing.checked = boolFromValue(vehicle.showing);
        fields.landing_order.value = vehicle.landing_order ?? "";
        ADMIN_PORTAL.isHydrating = false;
    };

    const readFormValues = () => {
        const landingRaw = fields.landing_order.value.trim();
        return {
            id: fields.id.value ? parseInt(fields.id.value, 10) : null,
            name: fields.name.value.trim(),
            type: fields.type.value.trim(),
            slug: fields.slug.value.trim(),
            base_price_USD: toFloat(fields.base_price_USD.value),
            insurance: toFloat(fields.insurance.value),
            people: toInt(fields.people.value),
            bags: toInt(fields.bags.value),
            doors: toInt(fields.doors.value),
            manual: fields.manual.checked ? 1 : 0,
            ac: fields.ac.checked ? 1 : 0,
            "4wd": fields.fourwd.checked ? 1 : 0,
            showing: fields.showing.checked ? 1 : 0,
            landing_order: landingRaw === "" ? null : toInt(landingRaw)
        };
    };

    const updateListItem = (vehicle) => {
        const item = document.querySelector(`.vehicle-item[data-vehicle-id="${vehicle.id}"]`);
        if (!item) return;

        const nameEl = item.querySelector(".vehicle-name");
        const typeEl = item.querySelector(".vehicle-type");
        const priceEl = item.querySelector(".vehicle-price");
        const seatsEl = item.querySelector(".vehicle-seats");
        const transmissionEl = item.querySelector(".vehicle-transmission");
        const showingTag = item.querySelector(".showing-tag");
        const landingTag = item.querySelector(".landing-tag");

        if (nameEl) nameEl.textContent = vehicle.name || "Vehicle";
        if (typeEl) typeEl.textContent = vehicle.type || "";
        if (priceEl) priceEl.textContent = `USD$${vehicle.base_price_USD ?? 0}/day`;
        if (seatsEl) seatsEl.textContent = `${vehicle.people ?? 0} seats`;
        if (transmissionEl) transmissionEl.textContent = boolFromValue(vehicle.manual) ? "Manual" : "Automatic";

        const showingEnabled = boolFromValue(vehicle.showing);
        const landingEnabled = vehicle.landing_order !== null && vehicle.landing_order !== "" && vehicle.landing_order !== undefined;
        const landingLabel = landingEnabled ? `Landing #${vehicle.landing_order}` : "Landing Hidden";

        updateTag(showingTag, showingEnabled, "Showing");
        updateTag(landingTag, landingEnabled, landingLabel);

        item.dataset.search = buildSearchKey(vehicle);
    };

    const setActive = (id) => {
        const vehicle = ADMIN_PORTAL.vehicleMap.get(String(id));
        if (!vehicle) return;

        ADMIN_PORTAL.activeId = String(id);
        listItems.forEach((item) => item.classList.toggle("active", item.dataset.vehicleId === String(id)));

        if (emptyState) emptyState.hidden = true;
        form.hidden = false;
        fillForm(vehicle);
        updatePreview(vehicle);
        setDirty(false);
        setStatus("");
    };

    const filterList = (term) => {
        let visibleCount = 0;
        const query = term.toLowerCase();

        listItems.forEach((item) => {
            const haystack = (item.dataset.search || "").toLowerCase();
            const match = haystack.includes(query);
            item.hidden = !match;
            if (match) visibleCount++;
        });

        if (noResults) {
            noResults.hidden = query.length === 0 || visibleCount > 0;
        }
    };

    const saveVehicle = async () => {
        if (!ADMIN_PORTAL.activeId) return;

        const payload = readFormValues();
        setStatus("Saving...", "info");
        saveBtn.disabled = true;

        try {
            const response = await fetch("/includes/admin-vehicles.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    action: "update_vehicle",
                    vehicle: payload
                })
            });

            const result = await response.json();
            if (!response.ok || !result.success) {
                setStatus(result.message || "Save failed.", "error");
                saveBtn.disabled = false;
                return;
            }

            ADMIN_PORTAL.vehicleMap.set(String(result.vehicle.id), result.vehicle);
            updateListItem(result.vehicle);
            fillForm(result.vehicle);
            updatePreview(result.vehicle);
            setDirty(false);
            setStatus("Saved", "success");
        } catch (error) {
            setStatus("Network error.", "error");
            saveBtn.disabled = false;
        }
    };

    const resetForm = () => {
        if (!ADMIN_PORTAL.activeId) return;
        const vehicle = ADMIN_PORTAL.vehicleMap.get(String(ADMIN_PORTAL.activeId));
        if (!vehicle) return;
        fillForm(vehicle);
        updatePreview(vehicle);
        setDirty(false);
        setStatus("Changes reset.", "info");
    };

    listItems.forEach((item) => {
        item.addEventListener("click", () => {
            const nextId = item.dataset.vehicleId;
            if (String(nextId) === String(ADMIN_PORTAL.activeId)) return;

            if (ADMIN_PORTAL.isDirty && !window.confirm("Discard unsaved changes?")) {
                return;
            }

            setActive(nextId);
        });
    });

    form.addEventListener("submit", (event) => {
        event.preventDefault();
    });

    form.addEventListener("input", () => {
        if (ADMIN_PORTAL.isHydrating) return;
        setDirty(true);
        setStatus("Unsaved changes", "info");
        updatePreview(readFormValues());
    });

    saveBtn.addEventListener("click", saveVehicle);
    resetBtn.addEventListener("click", resetForm);

    if (searchInput) {
        searchInput.addEventListener("input", (event) => {
            filterList(event.target.value.trim());
        });
    }

    if (listItems.length > 0) {
        setActive(listItems[0].dataset.vehicleId);
    }
};

const initOrders = () => {
    const searchInput = document.getElementById("order-search");
    const rows = Array.from(document.querySelectorAll(".order-row"));
    const emptyState = document.querySelector(".table-empty");
    const pageSizeSelect = document.getElementById("order-page-size");
    const prevBtn = document.getElementById("order-prev");
    const nextBtn = document.getElementById("order-next");
    const pageInfo = document.getElementById("order-page-info");
    const modal = document.getElementById("order-modal");
    const detailsCard = document.getElementById("order-details");
    const orderForm = document.getElementById("order-form");
    const orderSaveBtn = document.querySelector(".order-save");
    const orderResetBtn = document.querySelector(".order-reset");
    const orderStatusEl = document.getElementById("order-status");
    if (!searchInput || rows.length === 0) return;

    const ordersData = parseJsonScript("orders-data") || [];
    const ordersConfig = parseJsonScript("orders-config") || {};
    const displayColumns = ordersConfig.display_columns || [];
    const ordersMap = new Map(ordersData.map((order) => [String(order.id), order]));
    const vehicleMap = (ADMIN_PORTAL.vehicleMap && ADMIN_PORTAL.vehicleMap.size)
        ? ADMIN_PORTAL.vehicleMap
        : new Map((parseJsonScript("vehicle-data") || []).map((vehicle) => [String(vehicle.id), vehicle]));

    const orderFields = orderForm
        ? {
            id: orderForm.querySelector('[name="id"]'),
            status: orderForm.querySelector('[name="status"]')
        }
        : null;

    const vehicleImage = document.getElementById("order-detail-image");
    const vehicleNameEl = document.getElementById("order-detail-vehicle-name");
    const vehicleTypeEl = document.getElementById("order-detail-vehicle-type");
    const vehiclePriceEl = document.getElementById("order-detail-vehicle-price");
    const vehicleCapacityEl = document.getElementById("order-detail-vehicle-capacity");
    const vehicleFeaturesEl = document.getElementById("order-detail-vehicle-features");

    let orderDirty = false;
    let activeOrderId = null;

    let currentPage = 1;
    let pageSize = pageSizeSelect ? parseInt(pageSizeSelect.value, 10) : 10;
    let filteredRows = rows.slice();

    const renderPage = () => {
        const totalPages = Math.max(1, Math.ceil(filteredRows.length / pageSize));
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const start = (currentPage - 1) * pageSize;
        const end = start + pageSize;

        rows.forEach((row) => {
            row.hidden = true;
        });

        filteredRows.slice(start, end).forEach((row) => {
            row.hidden = false;
        });

        if (pageInfo) {
            pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
        }
        if (prevBtn) prevBtn.disabled = currentPage <= 1;
        if (nextBtn) nextBtn.disabled = currentPage >= totalPages;

        if (emptyState) {
            emptyState.hidden = filteredRows.length > 0;
        }
    };

    const filterRows = (term) => {
        const query = term.toLowerCase();
        filteredRows = rows.filter((row) => {
            const haystack = (row.dataset.search || "").toLowerCase();
            return haystack.includes(query);
        });
        currentPage = 1;
        renderPage();
    };

    const formatMoney = (value) => {
        const number = parseFloat(value);
        if (Number.isNaN(number)) return "—";
        return `USD$${number.toFixed(2)}`;
    };

    const getVehicleById = (value) => {
        if (value === null || value === undefined || value === "") return null;
        return vehicleMap.get(String(value)) || null;
    };

    const resolveVehicleDisplayName = (value) => {
        if (value === null || value === undefined) return "—";
        const text = String(value).trim();
        if (text === "" || text === "—") return "—";
        if (!/^\d+$/.test(text)) return text;
        const vehicle = getVehicleById(text);
        return vehicle && vehicle.name ? vehicle.name : `Car ${text}`;
    };

    const buildVehicleSnapshot = (order, overrideVehicle) => {
        const vehicle = overrideVehicle || getVehicleById(order?.car_id);
        return {
            name: vehicle?.name ?? order?.vehicle_name ?? "Vehicle",
            type: vehicle?.type ?? order?.vehicle_type ?? "",
            slug: vehicle?.slug ?? order?.vehicle_slug ?? "",
            basePrice: vehicle?.base_price_USD ?? order?.vehicle_base_price ?? null,
            people: vehicle?.people ?? order?.vehicle_people ?? null,
            bags: vehicle?.bags ?? order?.vehicle_bags ?? null,
            doors: vehicle?.doors ?? order?.vehicle_doors ?? null,
            manual: vehicle?.manual ?? order?.vehicle_manual ?? null,
            ac: vehicle?.ac ?? order?.vehicle_ac ?? null,
            fourwd: vehicle ? vehicle["4wd"] : order?.vehicle_4wd ?? null
        };
    };

    const renderOrderVehiclePreview = (order, overrideVehicle) => {
        if (!order && !overrideVehicle) return;
        const snapshot = buildVehicleSnapshot(order || {}, overrideVehicle);
        const name = snapshot.name || "Vehicle";
        const slug = snapshot.slug || "";
        const imgSrc = slug ? `/assets/images/vehicles/${slug}.avif` : "/assets/images/logo.avif";

        if (vehicleImage) {
            vehicleImage.src = imgSrc;
            vehicleImage.alt = `${name} image`;
        }
        if (vehicleNameEl) vehicleNameEl.textContent = name;
        if (vehicleTypeEl) vehicleTypeEl.textContent = snapshot.type || "";
        if (vehiclePriceEl) {
            const basePrice = snapshot.basePrice;
            vehiclePriceEl.textContent = basePrice !== null && basePrice !== undefined && basePrice !== ""
                ? `USD$${basePrice}/day`
                : "USD$—/day";
        }
        if (vehicleCapacityEl) {
            const seats = snapshot.people ? `${snapshot.people} seats` : null;
            const bags = snapshot.bags ? `${snapshot.bags} bags` : null;
            vehicleCapacityEl.textContent = [seats, bags].filter(Boolean).join(" • ") || "—";
        }

        const features = [];
        if (snapshot.manual !== null && snapshot.manual !== undefined) {
            features.push(parseInt(snapshot.manual, 10) === 1 ? "Manual" : "Automatic");
        }
        if (snapshot.ac !== null && snapshot.ac !== undefined) {
            if (parseInt(snapshot.ac, 10) === 1) features.push("AC");
        }
        if (snapshot.fourwd !== null && snapshot.fourwd !== undefined) {
            if (parseInt(snapshot.fourwd, 10) === 1) features.push("4WD");
        }
        if (snapshot.doors !== null && snapshot.doors !== undefined) {
            const doors = parseInt(snapshot.doors, 10);
            if (!Number.isNaN(doors) && doors > 0) features.push(`${doors} doors`);
        }
        if (vehicleFeaturesEl) vehicleFeaturesEl.textContent = features.length ? features.join(" • ") : "—";
    };

    const closeModal = () => {
        if (!modal) return;
        modal.hidden = true;
        document.body.classList.remove("modal-open");
    };

    const openModal = () => {
        if (!modal) return;
        modal.hidden = false;
        document.body.classList.add("modal-open");
    };

    const setOrderStatus = (message, state = "") => {
        if (!orderStatusEl) return;
        orderStatusEl.textContent = message || "";
        orderStatusEl.classList.remove("success", "error", "info");
        if (state) orderStatusEl.classList.add(state);
    };

    const setOrderDirty = (dirty) => {
        orderDirty = dirty;
        if (orderSaveBtn) orderSaveBtn.disabled = !dirty;
    };

    const fillOrderForm = (order) => {
        if (!orderFields || !order) return;
        if (orderFields.id) orderFields.id.value = order.id ?? "";
        if (orderFields.status) orderFields.status.value = order.status ?? "pending";
        setOrderDirty(false);
        setOrderStatus("");
    };

    const buildSearchValue = (order) => {
        const parts = [
            order.id,
            order.customer_name,
            order.email,
            order.phone,
            order.vehicle_name,
            order.pick_up_location,
            order.drop_off_location
        ];
        return parts.filter(Boolean).join(" ").toLowerCase().trim();
    };

    const updateOrderRow = (order) => {
        const row = document.querySelector(`.order-row[data-order-id="${order.id}"]`);
        if (!row) return;
        const cells = Array.from(row.querySelectorAll("td"));
        displayColumns.forEach((column, index) => {
            const cell = cells[index];
            if (!cell) return;
            let value = order[column] ?? "";
            if (column === "status") {
                value = (order.status || "pending").replace(/^\w/, (c) => c.toUpperCase());
            }
            if (column === "sub_total" && value !== "") {
                value = `USD$${parseFloat(value).toFixed(2)}`;
            }
            cell.textContent = value;
        });
        row.dataset.search = buildSearchValue(order);
    };

    const escapeHtml = (value) => {
        if (value === null || value === undefined) return "";
        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");
    };

    const getPathValue = (obj, path) => {
        if (!obj || !path) return null;
        const parts = path.split(".");
        let current = obj;
        for (const part of parts) {
            if (current && Object.prototype.hasOwnProperty.call(current, part)) {
                current = current[part];
            } else {
                return null;
            }
        }
        return current;
    };

    const buildHistoryLines = (summary, previousData, newData) => {
        const keys = summary
            .split(",")
            .map((item) => item.trim())
            .filter(Boolean);
        if (keys.length === 0) return [];

        const prevOrder = previousData?.order || previousData || {};
        const nextOrder = newData?.order || newData || {};
        const prevContact = previousData?.contact || {};
        const nextContact = newData?.contact || {};

        return keys.map((key) => {
            let oldValue = null;
            let newValue = null;
            if (key.startsWith("contact.")) {
                const contactKey = key.replace("contact.", "");
                oldValue = getPathValue(prevContact, contactKey);
                newValue = getPathValue(nextContact, contactKey);
            } else {
                oldValue = getPathValue(prevOrder, key);
                newValue = getPathValue(nextOrder, key);
            }
            const label = key === "car_id" ? "car" : key;
            const oldText = key === "car_id"
                ? resolveVehicleDisplayName(oldValue)
                : (oldValue === null || oldValue === undefined || oldValue === "" ? "—" : oldValue);
            const newText = key === "car_id"
                ? resolveVehicleDisplayName(newValue)
                : (newValue === null || newValue === undefined || newValue === "" ? "—" : newValue);
            return `${label}: ${oldText} → ${newText}`;
        });
    };

    const humanizeHistoryLine = (line) => {
        if (!line) return line;
        const match = line.match(/^car_id:\s*(.*?)\s*→\s*(.*)$/i);
        if (match) {
            const oldLabel = resolveVehicleDisplayName(match[1]);
            const newLabel = resolveVehicleDisplayName(match[2]);
            return `car: ${oldLabel} → ${newLabel}`;
        }
        return line;
    };

    const fetchHistory = async (orderId) => {
        const historyWrap = document.getElementById("order-history");
        const historyList = document.getElementById("order-history-list");
        if (!historyWrap || !historyList) return;
        if (!ordersConfig.has_history) {
            historyWrap.hidden = true;
            return;
        }
        historyWrap.hidden = false;
        historyList.textContent = "Loading history...";
        try {
            const response = await fetch("/includes/admin-orders.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ action: "fetch_history", order_id: orderId })
            });
            const result = await response.json();
            console.log("here is the result", result);
            if (!response.ok || !result.success) {
                historyList.textContent = result.message || "Unable to load history.";
                return;
            }
            if (!result.history || result.history.length === 0) {
                historyList.textContent = "No history yet.";
                return;
            }
            historyList.innerHTML = result.history
                .map((item) => {
                    const summary = item.change_summary || item.action || "Update";
                    const when = item.created_at || "";
                    const by = item.admin_user || "Admin";
                    let lines = summary.split("\n").filter(Boolean);
                    if (lines.length <= 1 && summary.indexOf("→") === -1) {
                        let prevData = null;
                        let nextData = null;
                        try {
                            prevData = item.previous_data ? JSON.parse(item.previous_data) : null;
                        } catch (error) {
                            prevData = null;
                        }
                        try {
                            nextData = item.new_data ? JSON.parse(item.new_data) : null;
                        } catch (error) {
                            nextData = null;
                        }
                        const computed = buildHistoryLines(summary, prevData, nextData);
                        if (computed.length > 0) {
                            lines = computed;
                        }
                    }

                    lines = lines.map(humanizeHistoryLine);
                    const summaryHtml = lines.length > 1
                        ? `<div class=\"order-history-lines\">${lines.map((line) => `<div>${escapeHtml(line)}</div>`).join("")}</div>`
                        : `<strong>${escapeHtml(lines[0] || summary)}</strong>`;
                    return `<div class=\"order-history-item\">${summaryHtml}<span>${escapeHtml(when)} · ${escapeHtml(by)}</span></div>`;
                })
                .join("");
        } catch (error) {
            historyList.textContent = "Unable to load history.";
        }
    };

    const saveOrder = async () => {
        if (!orderFields || !activeOrderId) return;
        const payload = {
            id: activeOrderId,
            status: orderFields.status ? orderFields.status.value : null
        };
        setOrderStatus("Saving...", "info");
        if (orderSaveBtn) orderSaveBtn.disabled = true;

        try {
            const response = await fetch("/includes/admin-orders.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    action: "update_order",
                    order: payload
                })
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                setOrderStatus(result.message || "Save failed.", "error");
                if (orderSaveBtn) orderSaveBtn.disabled = false;
                return;
            }
            const updated = result.order;
            const existing = ordersMap.get(String(updated.id));
            if (existing && Array.isArray(existing.add_ons)) {
                updated.add_ons = existing.add_ons;
            }
            ordersMap.set(String(updated.id), updated);
            updateOrderRow(updated);
            fillOrderForm(updated);
            renderOrderVehiclePreview(updated);
            setOrderStatus("Saved", "success");
            await fetchHistory(updated.id);
        } catch (error) {
            setOrderStatus("Network error.", "error");
            if (orderSaveBtn) orderSaveBtn.disabled = false;
        }
    };

    const resetOrderForm = () => {
        if (!activeOrderId) return;
        const order = ordersMap.get(String(activeOrderId));
        if (!order) return;
        fillOrderForm(order);
        setOrderStatus("Changes reset.", "info");
    };

    const setDetail = (order) => {
        if (!detailsCard || !modal) return;
        if (!order) {
            closeModal();
            return;
        }

        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (!el) return;
            if (value === null || value === undefined || value === "") {
                el.textContent = "—";
            } else {
                el.textContent = value;
            }
        };

        renderOrderVehiclePreview(order);

        setText("order-detail-customer", order.customer_name);
        setText("order-detail-email", order.email);
        setText("order-detail-phone", order.phone);
        setText("order-detail-vehicle", order.vehicle_name);
        setText("order-detail-days", order.days);
        setText("order-detail-subtotal", formatMoney(order.sub_total));
        setText("order-detail-pickup", order.pick_up);
        setText("order-detail-dropoff", order.drop_off);
        setText("order-detail-pickup-location", order.pick_up_location);
        setText("order-detail-dropoff-location", order.drop_off_location);
        setText("order-detail-driver-license", order.driver_license);
        setText("order-detail-hotel", order.hotel);
        setText("order-detail-country", order.country_or_region);
        setText("order-detail-street", order.street);
        setText("order-detail-town", order.town_or_city);
        setText("order-detail-state", order.state_or_county);

        const addonsEl = document.getElementById("order-detail-addons");
        if (addonsEl) {
            const addons = order.add_ons || [];
            if (addons.length === 0) {
                addonsEl.textContent = "No add-ons.";
            } else {
                const lines = addons.map((addon) => {
                    const qty = addon.quantity ? `x${addon.quantity}` : "";
                    const cost = addon.cost !== null && addon.cost !== undefined && !Number.isNaN(parseFloat(addon.cost))
                        ? ` - USD$${stripTrailingZeros(parseFloat(addon.cost))}`
                        : "";
                    return `${addon.name} ${qty}${cost}`.trim();
                });
                addonsEl.textContent = lines.join(" | ");
            }
        }

        fillOrderForm(order);
        activeOrderId = order.id;
        fetchHistory(order.id);
        openModal();
    };

    rows.forEach((row) => {
        row.addEventListener("click", () => {
            const id = row.dataset.orderId;
            rows.forEach((r) => r.classList.remove("active"));
            row.classList.add("active");
            setDetail(ordersMap.get(String(id)));
        });
    });

    if (orderForm) {
        orderForm.addEventListener("input", () => {
            if (!activeOrderId) return;
            setOrderDirty(true);
            setOrderStatus("Unsaved changes", "info");
        });
    }


    if (orderSaveBtn) {
        orderSaveBtn.addEventListener("click", saveOrder);
    }

    if (orderResetBtn) {
        orderResetBtn.addEventListener("click", resetOrderForm);
    }

    if (modal) {
        modal.addEventListener("click", (event) => {
            if (event.target && event.target.matches("[data-modal-close]")) {
                closeModal();
            }
        });
    }

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeModal();
        }
    });

    if (pageSizeSelect) {
        pageSizeSelect.addEventListener("change", (event) => {
            const value = parseInt(event.target.value, 10);
            if (!Number.isNaN(value)) {
                pageSize = value;
                currentPage = 1;
                renderPage();
            }
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener("click", () => {
            currentPage -= 1;
            renderPage();
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener("click", () => {
            currentPage += 1;
            renderPage();
        });
    }

    searchInput.addEventListener("input", (event) => {
        filterRows(event.target.value.trim());
    });

    filterRows("");
};

const initAddons = () => {
    const data = parseJsonScript("addons-data");
    if (!data) return;

    const config = parseJsonScript("addons-config") || {};
    const fieldMap = config.field_map || {};
    const idField = config.id_field || "id";

    ADMIN_PORTAL.addons = data || [];
    ADMIN_PORTAL.addonMap = new Map(
        ADMIN_PORTAL.addons.map((addon) => [String(addon[idField]), addon])
    );

    const form = document.getElementById("addon-form");
    const emptyState = document.querySelector(".addon-empty-state");
    const statusEl = document.getElementById("addon-status");
    const saveBtn = document.querySelector(".addon-save");
    const resetBtn = document.querySelector(".addon-reset");
    const listItems = Array.from(document.querySelectorAll(".addon-item"));
    const searchInput = document.getElementById("addon-search");
    const noResults = document.querySelector(".addon-empty");

    if (!form || !saveBtn || !resetBtn || !statusEl) return;

    const fields = {
        id: form.querySelector('[name="id"]'),
        name: form.querySelector('[name="name"]'),
        price: form.querySelector('[name="price"]'),
        description: form.querySelector('[name="description"]'),
        active: form.querySelector('[name="active"]'),
        per_day: form.querySelector('[name="per_day"]'),
        sort_order: form.querySelector('[name="sort_order"]')
    };

    const hideField = (field) => {
        const nodes = document.querySelectorAll(`[data-addon-field="${field}"]`);
        nodes.forEach((node) => {
            node.hidden = true;
        });
    };

    Object.keys(fields).forEach((key) => {
        if (key === "id") return;
        if (!fieldMap[key]) {
            hideField(key);
        }
    });

    const setStatus = (message, state = "") => {
        statusEl.textContent = message || "";
        statusEl.classList.remove("success", "error", "info");
        if (state) statusEl.classList.add(state);
    };

    const setDirty = (isDirty) => {
        ADMIN_PORTAL.addonsDirty = isDirty;
        saveBtn.disabled = !isDirty;
        form.classList.toggle("is-dirty", isDirty);
    };

    const buildAddonSearch = (addon) => {
        const parts = [];
        ["name", "description", "price"].forEach((field) => {
            const column = fieldMap[field];
            if (column && addon[column]) parts.push(addon[column]);
        });
        return parts.join(" ").toLowerCase().trim();
    };

    const fillForm = (addon) => {
        ADMIN_PORTAL.addonsHydrating = true;
        fields.id.value = addon[idField] ?? "";
        if (fieldMap.name) fields.name.value = addon[fieldMap.name] ?? "";
        if (fieldMap.price) fields.price.value = addon[fieldMap.price] ?? "";
        if (fieldMap.description) fields.description.value = addon[fieldMap.description] ?? "";
        if (fieldMap.active) fields.active.checked = boolFromValue(addon[fieldMap.active]);
        if (fieldMap.per_day) fields.per_day.checked = boolFromValue(addon[fieldMap.per_day]);
        if (fieldMap.sort_order) fields.sort_order.value = addon[fieldMap.sort_order] ?? "";
        ADMIN_PORTAL.addonsHydrating = false;
    };

    const readFormValues = () => ({
        id: fields.id.value ? parseInt(fields.id.value, 10) : null,
        name: fields.name.value.trim(),
        price: fields.price.value === "" ? null : toFloat(fields.price.value),
        description: fields.description.value.trim(),
        active: fields.active ? (fields.active.checked ? 1 : 0) : null,
        per_day: fields.per_day && fields.per_day.checked ? 1 : 0,
        sort_order: fields.sort_order.value === "" ? null : toInt(fields.sort_order.value)
    });

    const updateListItem = (addon) => {
        const id = addon[idField];
        const item = document.querySelector(`.addon-item[data-addon-id="${id}"]`);
        if (!item) return;

        const nameEl = item.querySelector(".addon-name");
        const priceEl = item.querySelector(".addon-price");
        const tags = item.querySelectorAll(".vehicle-tag");

        const nameValue = fieldMap.name ? addon[fieldMap.name] : "Add-on";
        let priceValue = "—";
        if (fieldMap.price && addon[fieldMap.price] !== null && addon[fieldMap.price] !== undefined) {
            const priceNumber = parseFloat(addon[fieldMap.price]);
            priceValue = Number.isNaN(priceNumber) ? "—" : `USD$${stripTrailingZeros(priceNumber)}`;
        }
        const perDayValue = fieldMap.per_day ? boolFromValue(addon[fieldMap.per_day]) : false;

        if (nameEl) nameEl.textContent = nameValue || "Add-on";
        if (priceEl) priceEl.textContent = priceValue;

        if (tags.length >= 1) {
            tags[0].classList.toggle("tag-on", perDayValue);
            tags[0].classList.toggle("tag-off", !perDayValue);
        }

        item.dataset.search = buildAddonSearch(addon);
    };

    const setActive = (id) => {
        const addon = ADMIN_PORTAL.addonMap.get(String(id));
        if (!addon) return;

        ADMIN_PORTAL.activeAddonId = String(id);
        listItems.forEach((item) => item.classList.toggle("active", item.dataset.addonId === String(id)));

        if (emptyState) emptyState.hidden = true;
        form.hidden = false;
        fillForm(addon);
        setDirty(false);
        setStatus("");
    };

    const filterList = (term) => {
        let visibleCount = 0;
        const query = term.toLowerCase();

        listItems.forEach((item) => {
            const haystack = (item.dataset.search || "").toLowerCase();
            const match = haystack.includes(query);
            item.hidden = !match;
            if (match) visibleCount++;
        });

        if (noResults) {
            noResults.hidden = query.length === 0 || visibleCount > 0;
        }
    };

    const saveAddon = async () => {
        if (!ADMIN_PORTAL.activeAddonId) return;

        const payload = readFormValues();
        setStatus("Saving...", "info");
        saveBtn.disabled = true;

        try {
            const response = await fetch("/includes/admin-addons.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    action: "update_addon",
                    addon: payload
                })
            });

            const result = await response.json();
            if (!response.ok || !result.success) {
                setStatus(result.message || "Save failed.", "error");
                saveBtn.disabled = false;
                return;
            }

            ADMIN_PORTAL.addonMap.set(String(result.addon[idField]), result.addon);
            updateListItem(result.addon);
            fillForm(result.addon);
            setDirty(false);
            setStatus("Saved", "success");
        } catch (error) {
            setStatus("Network error.", "error");
            saveBtn.disabled = false;
        }
    };

    const resetForm = () => {
        if (!ADMIN_PORTAL.activeAddonId) return;
        const addon = ADMIN_PORTAL.addonMap.get(String(ADMIN_PORTAL.activeAddonId));
        if (!addon) return;
        fillForm(addon);
        setDirty(false);
        setStatus("Changes reset.", "info");
    };

    listItems.forEach((item) => {
        item.addEventListener("click", () => {
            const nextId = item.dataset.addonId;
            if (String(nextId) === String(ADMIN_PORTAL.activeAddonId)) return;

            if (ADMIN_PORTAL.addonsDirty && !window.confirm("Discard unsaved changes?")) {
                return;
            }

            setActive(nextId);
        });
    });

    form.addEventListener("submit", (event) => {
        event.preventDefault();
    });

    form.addEventListener("input", () => {
        if (ADMIN_PORTAL.addonsHydrating) return;
        setDirty(true);
        setStatus("Unsaved changes", "info");
    });

    saveBtn.addEventListener("click", saveAddon);
    resetBtn.addEventListener("click", resetForm);

    if (searchInput) {
        searchInput.addEventListener("input", (event) => {
            filterList(event.target.value.trim());
        });
    }

    if (listItems.length > 0) {
        setActive(listItems[0].dataset.addonId);
    }
};

const initDiscounts = () => {
    const data = parseJsonScript("discounts-data");
    if (!data) return;

    const config = parseJsonScript("discounts-config") || {};
    const fieldMap = config.field_map || {};
    const valueKind = config.value_kind || "amount";
    const currency = config.currency || "USD";

    ADMIN_PORTAL.discounts = data || [];
    ADMIN_PORTAL.discountMap = new Map(
        ADMIN_PORTAL.discounts.map((entry) => [String(entry.vehicle?.id ?? entry.vehicle_id), entry])
    );

    const form = document.getElementById("discount-form");
    const emptyState = document.querySelector(".discount-empty-state");
    const statusEl = document.getElementById("discount-status");
    const saveBtn = document.querySelector(".discount-save");
    const resetBtn = document.querySelector(".discount-reset");
    const listItems = Array.from(document.querySelectorAll(".discount-item"));
    const searchInput = document.getElementById("discount-search");
    const noResults = document.querySelector(".discount-empty");
    const previewTitle = document.querySelector(".discount-vehicle-title");
    const previewSubtitle = document.querySelector(".discount-vehicle-subtitle");
    const previewPrice = document.querySelector(".discount-vehicle-price");

    if (!form || !saveBtn || !resetBtn || !statusEl) return;

    const fields = {
        vehicle_id: form.querySelector('[name="vehicle_id"]'),
        price_USD: form.querySelector('[name="price_USD"]'),
        price_XCD: form.querySelector('[name="price_XCD"]'),
        days: form.querySelector('[name="days"]')
    };

    const hideField = (field) => {
        const nodes = document.querySelectorAll(`[data-discount-field="${field}"]`);
        nodes.forEach((node) => {
            node.hidden = true;
        });
    };

    Object.keys(fields).forEach((key) => {
        if (key === "vehicle_id") return;
        if (!fieldMap[key]) {
            hideField(key);
        }
    });

    const setStatus = (message, state = "") => {
        statusEl.textContent = message || "";
        statusEl.classList.remove("success", "error", "info");
        if (state) statusEl.classList.add(state);
    };

    const setDirty = (isDirty) => {
        ADMIN_PORTAL.discountsDirty = isDirty;
        saveBtn.disabled = !isDirty;
        form.classList.toggle("is-dirty", isDirty);
    };

    const formatSummary = (discount) => {
        if (!discount) return "No discount";
        const usd = discount.price_USD !== null && discount.price_USD !== "" ? parseFloat(discount.price_USD) : null;
        const xcd = discount.price_XCD !== null && discount.price_XCD !== "" ? parseFloat(discount.price_XCD) : null;
        let value = null;
        let label = currency;
        if (!Number.isNaN(usd)) {
            value = usd;
            label = "USD";
        } else if (!Number.isNaN(xcd)) {
            value = xcd;
            label = "XCD";
        }
        if (value === null) return "No discount";
        if (discount.days && !Number.isNaN(parseInt(discount.days, 10))) {
            return `${label}$${stripTrailingZeros(value)} for ${parseInt(discount.days, 10)} days`;
        }
        if (valueKind === "percent") {
            return `${stripTrailingZeros(value)}% off`;
        }
        return `${label}$${stripTrailingZeros(value)} off`;
    };

    const updateListItem = (entry) => {
        const id = entry.vehicle?.id ?? entry.vehicle_id;
        const item = document.querySelector(`.discount-item[data-vehicle-id="${id}"]`);
        if (!item) return;

        const summaryEl = item.querySelector(".discount-summary");
        const tag = item.querySelector(".vehicle-tag");
        const discount = entry.discount || {};
        const hasDiscount = (discount.price_USD !== null && discount.price_USD !== "" && !Number.isNaN(parseFloat(discount.price_USD)))
            || (discount.price_XCD !== null && discount.price_XCD !== "" && !Number.isNaN(parseFloat(discount.price_XCD)));
        const active = hasDiscount;

        if (summaryEl) summaryEl.textContent = formatSummary(discount);
        if (tag) {
            tag.textContent = active ? "Active" : "Inactive";
            tag.classList.toggle("tag-on", active);
            tag.classList.toggle("tag-off", !active);
        }

        const searchParts = [entry.vehicle?.name, entry.vehicle?.type, entry.vehicle?.slug]
            .filter(Boolean)
            .join(" ");
        item.dataset.search = searchParts.toLowerCase().trim();
    };

    const fillForm = (entry) => {
        const vehicle = entry.vehicle || {};
        const discount = entry.discount || {};
        ADMIN_PORTAL.discountsHydrating = true;
        fields.vehicle_id.value = vehicle.id ?? "";
        if (fields.price_USD) fields.price_USD.value = discount.price_USD ?? "";
        if (fields.price_XCD) fields.price_XCD.value = discount.price_XCD ?? "";
        if (fields.days) fields.days.value = discount.days ?? "";
        ADMIN_PORTAL.discountsHydrating = false;

        if (previewTitle) previewTitle.textContent = vehicle.name || "Vehicle";
        if (previewSubtitle) previewSubtitle.textContent = vehicle.type || "";
        const basePrice = vehicle.base_price_USD;
        if (previewPrice) {
            previewPrice.textContent = basePrice !== null && basePrice !== undefined && basePrice !== ""
                ? `Base USD$${basePrice}/day`
                : "Base price unavailable";
        }
    };

    const readFormValues = () => ({
        price_USD: fields.price_USD && fields.price_USD.value !== "" ? toFloat(fields.price_USD.value) : null,
        price_XCD: fields.price_XCD && fields.price_XCD.value !== "" ? toFloat(fields.price_XCD.value) : null,
        days: fields.days && fields.days.value !== "" ? toInt(fields.days.value) : null
    });

    const setActive = (id) => {
        const entry = ADMIN_PORTAL.discountMap.get(String(id));
        if (!entry) return;

        ADMIN_PORTAL.activeDiscountVehicleId = String(id);
        listItems.forEach((item) => item.classList.toggle("active", item.dataset.vehicleId === String(id)));

        if (emptyState) emptyState.hidden = true;
        form.hidden = false;
        fillForm(entry);
        setDirty(false);
        setStatus("");
    };

    const filterList = (term) => {
        let visibleCount = 0;
        const query = term.toLowerCase();

        listItems.forEach((item) => {
            const haystack = (item.dataset.search || "").toLowerCase();
            const match = haystack.includes(query);
            item.hidden = !match;
            if (match) visibleCount++;
        });

        if (noResults) {
            noResults.hidden = query.length === 0 || visibleCount > 0;
        }
    };

    const saveDiscount = async () => {
        if (!ADMIN_PORTAL.activeDiscountVehicleId) return;

        const payload = readFormValues();
        setStatus("Saving...", "info");
        saveBtn.disabled = true;

        try {
            const response = await fetch("/includes/admin-discounts.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    action: "update_discount",
                    vehicle_id: parseInt(ADMIN_PORTAL.activeDiscountVehicleId, 10),
                    discount: payload
                })
            });

            const result = await response.json();
            if (!response.ok || !result.success) {
                setStatus(result.message || "Save failed.", "error");
                saveBtn.disabled = false;
                return;
            }

            const entry = ADMIN_PORTAL.discountMap.get(String(ADMIN_PORTAL.activeDiscountVehicleId));
            if (entry) {
                entry.discount = result.discount;
                updateListItem(entry);
                fillForm(entry);
            }
            setDirty(false);
            setStatus("Saved", "success");
        } catch (error) {
            setStatus("Network error.", "error");
            saveBtn.disabled = false;
        }
    };

    const resetForm = () => {
        if (!ADMIN_PORTAL.activeDiscountVehicleId) return;
        const entry = ADMIN_PORTAL.discountMap.get(String(ADMIN_PORTAL.activeDiscountVehicleId));
        if (!entry) return;
        fillForm(entry);
        setDirty(false);
        setStatus("Changes reset.", "info");
    };

    listItems.forEach((item) => {
        item.addEventListener("click", () => {
            const nextId = item.dataset.vehicleId;
            if (String(nextId) === String(ADMIN_PORTAL.activeDiscountVehicleId)) return;

            if (ADMIN_PORTAL.discountsDirty && !window.confirm("Discard unsaved changes?")) {
                return;
            }

            setActive(nextId);
        });
    });

    form.addEventListener("submit", (event) => {
        event.preventDefault();
    });

    form.addEventListener("input", () => {
        if (ADMIN_PORTAL.discountsHydrating) return;
        setDirty(true);
        setStatus("Unsaved changes", "info");
    });

    saveBtn.addEventListener("click", saveDiscount);
    resetBtn.addEventListener("click", resetForm);

    if (searchInput) {
        searchInput.addEventListener("input", (event) => {
            filterList(event.target.value.trim());
        });
    }

    if (listItems.length > 0) {
        setActive(listItems[0].dataset.vehicleId);
    }
};

$(function () {
    initTabs();
    initVehicles();
    initOrders();
    initAddons();
    initDiscounts();
});

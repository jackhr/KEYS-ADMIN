const ADMIN_PORTAL = {
    vehicles: [],
    vehicleMap: new Map(),
    activeId: null,
    isDirty: false,
    isHydrating: false
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

$(function () {
    const dataEl = document.getElementById("vehicle-data");
    if (!dataEl) return;

    try {
        ADMIN_PORTAL.vehicles = JSON.parse(dataEl.textContent) || [];
    } catch (error) {
        ADMIN_PORTAL.vehicles = [];
    }

    ADMIN_PORTAL.vehicleMap = new Map(
        ADMIN_PORTAL.vehicles.map((vehicle) => [String(vehicle.id), vehicle])
    );

    const form = document.getElementById("vehicle-form");
    const emptyState = document.querySelector(".editor-empty");
    const statusEl = document.getElementById("vehicle-status");
    const saveBtn = document.querySelector(".admin-save");
    const resetBtn = document.querySelector(".admin-reset");
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

        emptyState.hidden = true;
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
});

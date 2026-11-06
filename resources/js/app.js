import proj4 from "proj4";
import { closeModal, initializeInteractions, openModal } from "./ui-interactions";
import { WGS84_EPSG, resolveUtmConfig } from "./utils/constants";

/**
 * Global UTM configuration (from config('geodata.utm') via window.APP_GIS_CONFIG).
 */
const activeUtm = resolveUtmConfig();
proj4.defs(activeUtm.epsg, activeUtm.def);

window.proj4 = proj4;
window.UTM_EPSG = activeUtm.epsg;
window.UTM_DEF = activeUtm.def;
window.UTM_LABEL = activeUtm.label;
window.UTM_ZONE = activeUtm.zone;
window.UTM_HEMISPHERE = activeUtm.hemisphere;
window.WGS84_EPSG = WGS84_EPSG;
window.openModal = openModal;
window.closeModal = closeModal;

/**
 * Escape a value for safe interpolation into HTML.
 */
export function escapeHtml(unsafe) {
    return (unsafe ?? "")
        .toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

/**
 * Return the value when it is a #rrggbb hex color, otherwise the fallback (safe for style attributes).
 */
export function safeColor(value, fallback = "#6b7280") {
    return /^#[0-9a-fA-F]{6}$/.test(value ?? "") ? value : fallback;
}

/**
 * Return the value when it is a Remix Icon class name, otherwise the fallback (safe for class attributes).
 */
export function safeIconClass(value, fallback = "ri-map-pin-2-fill") {
    return /^ri-[a-z0-9-]+$/.test(value ?? "") ? value : fallback;
}

/**
 * Parse a date string or epoch value into milliseconds (0 when invalid).
 */
export function parseTimestamp(value) {
    if (!value) return 0;
    if (!isNaN(value)) return Number(value);
    const parsed = new Date(value);
    return isNaN(parsed.getTime()) ? 0 : parsed.getTime();
}

/**
 * Check whether a date string falls in the given "YYYY-MM" month.
 */
export function matchMonthYear(dateStr, query) {
    if (!dateStr || !query) return false;
    const [year, month] = query.split("-").map((part) => parseInt(part, 10));
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) {
        return dateStr.toLowerCase().includes(query.toLowerCase());
    }
    return date.getFullYear() === year && date.getMonth() + 1 === month;
}

/**
 * Convert WGS84 longitude/latitude to the configured UTM zone.
 */
export function longLatToUtm(longitude, latitude) {
    const [utmX, utmY] = proj4(WGS84_EPSG, window.UTM_EPSG, [longitude, latitude]);
    return { utmX, utmY };
}

/**
 * Convert a Cesium Cartesian3 into geographic + UTM coordinates.
 */
export function cartesianToCoords(cartesian) {
    if (typeof Cesium === "undefined" || !Cesium.defined(cartesian)) return null;
    const cartographic = Cesium.Cartographic.fromCartesian(cartesian);
    const longitude = Cesium.Math.toDegrees(cartographic.longitude);
    const latitude = Cesium.Math.toDegrees(cartographic.latitude);
    const { utmX, utmY } = longLatToUtm(longitude, latitude);
    return { longitude, latitude, altitude: cartographic.height, utmX, utmY };
}

/**
 * Format a [lon, lat] pair with Indonesian hemisphere suffixes (BT/BB, LU/LS).
 */
export function formatIndonesianCoordinate(coordinate, format = "dd") {
    const [lon, lat] = coordinate;
    const lonDirection = lon < 0 ? "BB" : "BT";
    const latDirection = lat < 0 ? "LS" : "LU";

    if (format === "dms") {
        const toDms = (value, direction) => {
            const absolute = Math.abs(value);
            const degrees = Math.floor(absolute);
            const minutes = Math.floor((absolute - degrees) * 60);
            const seconds = ((absolute - degrees - minutes / 60) * 3600).toFixed(2);
            return `${degrees}° ${minutes}' ${seconds}" ${direction}`;
        };
        return { formattedLon: toDms(lon, lonDirection), formattedLat: toDms(lat, latDirection) };
    }

    return {
        formattedLon: `${Math.abs(lon).toFixed(5)}° ${lonDirection}`,
        formattedLat: `${Math.abs(lat).toFixed(5)}° ${latDirection}`,
    };
}

/**
 * Format a number with fixed fraction digits ("-" when invalid).
 */
export function formatNumber(amount, locale = "en-US", options = {}) {
    const numeric = typeof amount === "string" ? parseFloat(amount) : amount;
    if (!Number.isFinite(numeric)) return "-";
    return new Intl.NumberFormat(locale, { minimumFractionDigits: 2, maximumFractionDigits: 2, ...options }).format(numeric);
}

Object.assign(window, {
    escapeHtml,
    safeColor,
    safeIconClass,
    parseTimestamp,
    matchMonthYear,
    longLatToUtm,
    cartesianToCoords,
    formatIndonesianCoordinate,
    formatNumber,
});

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => initializeInteractions(), { once: true });
} else {
    initializeInteractions();
}

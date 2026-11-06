import { cpSync, existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

const root = dirname(fileURLToPath(import.meta.url));

/**
 * Copies Cesium's static runtime files (Workers, Assets, ThirdParty, Widgets) into public/cesium,
 * where the bundled Cesium module loads them from CESIUM_BASE_URL ("/cesium/").
 * Runs once per `vite` / `vite build` and skips the copy when the installed version is already there.
 */
function copyCesiumAssets() {
    return {
        name: "copy-cesium-assets",
        configResolved() {
            const source = join(root, "node_modules", "cesium", "Build", "Cesium");
            const target = join(root, "public", "cesium");
            const stamp = join(target, ".version");

            if (!existsSync(source)) {
                console.warn("[cesium] node_modules/cesium not found, skipping asset copy.");
                return;
            }

            const { version } = JSON.parse(readFileSync(join(root, "node_modules", "cesium", "package.json"), "utf8"));
            if (existsSync(stamp) && readFileSync(stamp, "utf8").trim() === version) {
                return;
            }

            rmSync(target, { recursive: true, force: true });
            mkdirSync(target, { recursive: true });
            for (const folder of ["Workers", "Assets", "ThirdParty", "Widgets"]) {
                cpSync(join(source, folder), join(target, folder), { recursive: true });
            }
            writeFileSync(stamp, version);
            console.log(`[cesium] Copied Cesium ${version} static assets to public/cesium`);
        },
    };
}

export default defineConfig({
    plugins: [
        copyCesiumAssets(),
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js", "resources/js/map.js"],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        chunkSizeWarningLimit: 6000,
    },
    server: {
        watch: {
            ignored: ["**/storage/framework/views/**", "**/public/cesium/**"],
        },
    },
});

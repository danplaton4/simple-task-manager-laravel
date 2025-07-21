import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.tsx"],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            "@": "/resources/js",
        },
    },
    server: {
        host: "0.0.0.0",
        port: 5173,
        strictPort: true,
        cors: true,
        origin: "http://app.simple-task-manager-laravel.local:5173",
        hmr: {
            host: "app.simple-task-manager-laravel.local",
            protocol: "ws",
            port: 5173,
            clientPort: 5173,
        },
    },
});

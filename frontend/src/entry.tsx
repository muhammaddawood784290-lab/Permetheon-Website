import React from "react";
import { ViteReactSSG } from "vite-react-ssg";
import { routes } from "./App";
import "./styles/globals.css";
// Self-hosted fonts — same
// --font-display-family variable via fonts.css.
import "@fontsource/space-grotesk/latin-500.css";
import "@fontsource/space-grotesk/latin-700.css";
import "@fontsource/inter/latin-400.css";
import "@fontsource/inter/latin-500.css";
import "./fonts.css";

// vite-react-ssg entry: it prerenders every route in the
// router config at build time and hydrates on the client.
export const createRoot = ViteReactSSG(
  { routes },
  () => {
    // No global side effects needed yet.
  },
  { rootContainer: "#root" },
);

// Keep React import referenced for JSX runtime config parity.
void React;

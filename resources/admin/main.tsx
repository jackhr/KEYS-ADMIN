import React from "react";
import { createRoot } from "react-dom/client";
import App from "./App";
import "./styles/globals.css";

const container = document.getElementById("admin-root");

if (!container) {
  throw new Error("Missing #admin-root mount element.");
}

createRoot(container).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
);

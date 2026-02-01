"use client";

import React, { createContext, useContext, useEffect, useState, useCallback } from "react";
import { api } from "./api";

interface ThemeSettings {
  app_name: string;
  app_description: string;
  primary_color: string;
  secondary_color: string;
}

interface ThemeContextType {
  settings: ThemeSettings | null;
  isLoading: boolean;
  refreshSettings: () => Promise<void>;
  isDarkMode: boolean;
  toggleDarkMode: () => void;
}

const defaultSettings: ThemeSettings = {
  app_name: "RAG System",
  app_description: "Your personal knowledge base",
  primary_color: "#3B82F6",
  secondary_color: "#1E40AF",
};

const ThemeContext = createContext<ThemeContextType | null>(null);

function hexToHSL(hex: string): string {
  // Remove the # if present
  hex = hex.replace(/^#/, "");

  // Parse the hex values
  const r = parseInt(hex.substring(0, 2), 16) / 255;
  const g = parseInt(hex.substring(2, 4), 16) / 255;
  const b = parseInt(hex.substring(4, 6), 16) / 255;

  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  let h = 0;
  let s = 0;
  const l = (max + min) / 2;

  if (max !== min) {
    const d = max - min;
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);

    switch (max) {
      case r:
        h = ((g - b) / d + (g < b ? 6 : 0)) / 6;
        break;
      case g:
        h = ((b - r) / d + 2) / 6;
        break;
      case b:
        h = ((r - g) / d + 4) / 6;
        break;
    }
  }

  // Return HSL values for CSS (without the hsl() wrapper)
  return `${Math.round(h * 360)} ${Math.round(s * 100)}% ${Math.round(l * 100)}%`;
}

function applyTheme(settings: ThemeSettings) {
  const root = document.documentElement;

  // Convert hex colors to HSL for CSS variables
  const primaryHSL = hexToHSL(settings.primary_color);
  const secondaryHSL = hexToHSL(settings.secondary_color);

  // Apply primary color
  root.style.setProperty("--primary", primaryHSL);

  // Create a lighter version for primary-foreground (white works for most colors)
  root.style.setProperty("--primary-foreground", "210 40% 98%");

  // Apply ring color (same as primary)
  root.style.setProperty("--ring", primaryHSL);

  // Update document title
  document.title = settings.app_name;
}

const DARK_MODE_KEY = "dark-mode";

function getInitialDarkMode(): boolean {
  if (typeof window === "undefined") return false;

  const stored = localStorage.getItem(DARK_MODE_KEY);
  if (stored !== null) {
    return stored === "true";
  }

  return window.matchMedia("(prefers-color-scheme: dark)").matches;
}

function applyDarkMode(isDark: boolean) {
  if (isDark) {
    document.documentElement.classList.add("dark");
  } else {
    document.documentElement.classList.remove("dark");
  }
}

export function ThemeProvider({ children }: { children: React.ReactNode }) {
  const [settings, setSettings] = useState<ThemeSettings | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isDarkMode, setIsDarkMode] = useState(false);

  // Initialize dark mode on mount
  useEffect(() => {
    const initialDark = getInitialDarkMode();
    setIsDarkMode(initialDark);
    applyDarkMode(initialDark);
  }, []);

  // Listen for system preference changes
  useEffect(() => {
    const mediaQuery = window.matchMedia("(prefers-color-scheme: dark)");
    const handleChange = (e: MediaQueryListEvent) => {
      // Only apply if user hasn't set a preference
      if (localStorage.getItem(DARK_MODE_KEY) === null) {
        setIsDarkMode(e.matches);
        applyDarkMode(e.matches);
      }
    };

    mediaQuery.addEventListener("change", handleChange);
    return () => mediaQuery.removeEventListener("change", handleChange);
  }, []);

  const toggleDarkMode = useCallback(() => {
    setIsDarkMode((prev) => {
      const newValue = !prev;
      localStorage.setItem(DARK_MODE_KEY, String(newValue));
      applyDarkMode(newValue);
      return newValue;
    });
  }, []);

  const fetchSettings = async () => {
    try {
      // Try to get settings from admin endpoint first (if logged in as admin)
      const token = localStorage.getItem("token");
      if (token) {
        try {
          const response = await api.get("/api/admin/settings");
          const data = response.data;
          setSettings({
            app_name: data.app_name,
            app_description: data.app_description,
            primary_color: data.primary_color,
            secondary_color: data.secondary_color,
          });
          applyTheme({
            app_name: data.app_name,
            app_description: data.app_description,
            primary_color: data.primary_color,
            secondary_color: data.secondary_color,
          });
          return;
        } catch {
          // Fall through to public settings
        }
      }

      // Fallback to public settings
      const response = await api.get("/api/settings/public");
      const publicSettings = {
        app_name: response.data.app_name || defaultSettings.app_name,
        app_description: response.data.app_description || defaultSettings.app_description,
        primary_color: response.data.primary_color || defaultSettings.primary_color,
        secondary_color: response.data.secondary_color || defaultSettings.secondary_color,
      };
      setSettings(publicSettings);
      applyTheme(publicSettings);
    } catch {
      setSettings(defaultSettings);
      applyTheme(defaultSettings);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchSettings();
  }, []);

  // Re-fetch settings when localStorage changes (login/logout)
  useEffect(() => {
    const handleStorage = () => {
      fetchSettings();
    };
    window.addEventListener("storage", handleStorage);
    return () => window.removeEventListener("storage", handleStorage);
  }, []);

  return (
    <ThemeContext.Provider
      value={{ settings, isLoading, refreshSettings: fetchSettings, isDarkMode, toggleDarkMode }}
    >
      {children}
    </ThemeContext.Provider>
  );
}

export function useTheme() {
  const context = useContext(ThemeContext);
  if (!context) {
    throw new Error("useTheme must be used within a ThemeProvider");
  }
  return context;
}

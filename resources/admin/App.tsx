import { useCallback, useEffect, useState } from "react";
import { BrowserRouter, Navigate, Route, Routes } from "react-router-dom";
import {
  adminLogin,
  adminLogout,
  adminMe,
  ADMIN_TOKEN_KEY,
  getApiErrorMessage
} from "./lib/api";
import type { AdminUser } from "./types";
import DashboardPage from "./pages/DashboardPage";
import LoginPage from "./pages/LoginPage";

export default function App() {
  const [user, setUser] = useState<AdminUser | null>(null);
  const [checking, setChecking] = useState(true);
  const [authBusy, setAuthBusy] = useState(false);

  useEffect(() => {
    const bootstrap = async () => {
      const token = localStorage.getItem(ADMIN_TOKEN_KEY);

      if (!token) {
        setChecking(false);
        return;
      }

      try {
        const me = await adminMe();
        setUser(me);
      } catch {
        localStorage.removeItem(ADMIN_TOKEN_KEY);
      } finally {
        setChecking(false);
      }
    };

    void bootstrap();
  }, []);

  const handleLogin = useCallback(async (username: string, password: string) => {
    setAuthBusy(true);

    try {
      const data = await adminLogin(username, password);
      localStorage.setItem(ADMIN_TOKEN_KEY, data.token);
      setUser(data.user);
    } catch (error) {
      throw new Error(getApiErrorMessage(error));
    } finally {
      setAuthBusy(false);
    }
  }, []);

  const handleLogout = useCallback(async () => {
    setAuthBusy(true);

    try {
      await adminLogout();
    } catch {
      // Ignore backend logout failures and always clear local session.
    } finally {
      localStorage.removeItem(ADMIN_TOKEN_KEY);
      setUser(null);
      setAuthBusy(false);
    }
  }, []);

  if (checking) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-muted/35 px-6 text-sm font-medium text-muted-foreground">
        Checking session...
      </div>
    );
  }

  return (
    <BrowserRouter basename="/admin">
      <Routes>
        <Route
          path="/login"
          element={user ? <Navigate to="/" replace /> : <LoginPage onSubmit={handleLogin} loading={authBusy} />}
        />
        <Route
          path="/*"
          element={
            user ? (
              <DashboardPage user={user} onLogout={handleLogout} onUserChange={setUser} />
            ) : (
              <Navigate to="/login" replace />
            )
          }
        />
      </Routes>
    </BrowserRouter>
  );
}

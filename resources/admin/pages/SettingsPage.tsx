import { FormEvent, useEffect, useMemo, useState } from "react";
import { KeyRound, RefreshCw, Save, ShieldCheck, UserCog } from "lucide-react";

import { Button } from "../components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "../components/ui/card";
import { Input } from "../components/ui/input";
import { Label } from "../components/ui/label";
import { formatDateTimeDisplay } from "../lib/utils";
import type { AccountSettings, AdminUser } from "../types";

type SettingsPageProps = {
  user: AdminUser;
  accountSettings: AccountSettings | null;
  busy: boolean;
  onRefreshAccount: () => void;
  onUpdateProfile: (payload: { username: string; email: string | null }) => Promise<void>;
  onUpdatePassword: (payload: {
    current_password: string;
    password: string;
    password_confirmation: string;
  }) => Promise<void>;
};

export default function SettingsPage({
  user,
  accountSettings,
  busy,
  onRefreshAccount,
  onUpdateProfile,
  onUpdatePassword
}: SettingsPageProps) {
  const effectiveUser = useMemo(() => accountSettings?.user ?? user, [accountSettings, user]);
  const [usernameDraft, setUsernameDraft] = useState(effectiveUser.username);
  const [emailDraft, setEmailDraft] = useState(effectiveUser.email ?? "");
  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");

  useEffect(() => {
    setUsernameDraft(effectiveUser.username);
    setEmailDraft(effectiveUser.email ?? "");
  }, [effectiveUser.email, effectiveUser.username]);

  const handleProfileSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    await onUpdateProfile({
      username: usernameDraft.trim(),
      email: emailDraft.trim() === "" ? null : emailDraft.trim()
    });
  };

  const handlePasswordSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    await onUpdatePassword({
      current_password: currentPassword,
      password: newPassword,
      password_confirmation: confirmPassword
    });
  };

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <CardTitle className="flex items-center gap-2">
              <UserCog className="h-5 w-5" />
              Account Settings
            </CardTitle>
            <CardDescription>Update your profile and security credentials.</CardDescription>
          </div>
          <Button type="button" variant="outline" onClick={onRefreshAccount} disabled={busy}>
            <RefreshCw className="h-4 w-4" />
            {busy ? "Refreshing..." : "Refresh Account"}
          </Button>
        </CardHeader>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-lg">
            <ShieldCheck className="h-4 w-4" />
            Profile
          </CardTitle>
          <CardDescription>Change your username and contact email.</CardDescription>
        </CardHeader>
        <CardContent>
          <form className="space-y-4" onSubmit={(event) => void handleProfileSubmit(event)}>
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="settings-username">Username</Label>
                <Input
                  id="settings-username"
                  value={usernameDraft}
                  onChange={(event) => setUsernameDraft(event.target.value)}
                  autoComplete="username"
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="settings-email">Email</Label>
                <Input
                  id="settings-email"
                  type="email"
                  value={emailDraft}
                  onChange={(event) => setEmailDraft(event.target.value)}
                  autoComplete="email"
                  placeholder="admin@example.com"
                />
              </div>
            </div>
            <div className="grid gap-3 rounded-md border bg-muted/20 p-3 text-sm md:grid-cols-2">
              <div>
                <p className="text-muted-foreground text-xs font-semibold uppercase">Role</p>
                <p className="font-medium">{effectiveUser.role}</p>
              </div>
              <div>
                <p className="text-muted-foreground text-xs font-semibold uppercase">Last Login</p>
                <p className="font-medium">{formatDateTimeDisplay(effectiveUser.last_login_at)}</p>
              </div>
            </div>
            <div className="flex justify-end">
              <Button type="submit" disabled={busy}>
                <Save className="h-4 w-4" />
                Save Profile
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-lg">
            <KeyRound className="h-4 w-4" />
            Password
          </CardTitle>
          <CardDescription>Change your password for this admin account.</CardDescription>
        </CardHeader>
        <CardContent>
          <form className="space-y-4" onSubmit={(event) => void handlePasswordSubmit(event)}>
            <div className="grid gap-4 md:grid-cols-3">
              <div className="space-y-2">
                <Label htmlFor="settings-current-password">Current Password</Label>
                <Input
                  id="settings-current-password"
                  type="password"
                  value={currentPassword}
                  onChange={(event) => setCurrentPassword(event.target.value)}
                  autoComplete="current-password"
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="settings-new-password">New Password</Label>
                <Input
                  id="settings-new-password"
                  type="password"
                  value={newPassword}
                  onChange={(event) => setNewPassword(event.target.value)}
                  autoComplete="new-password"
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="settings-confirm-password">Confirm Password</Label>
                <Input
                  id="settings-confirm-password"
                  type="password"
                  value={confirmPassword}
                  onChange={(event) => setConfirmPassword(event.target.value)}
                  autoComplete="new-password"
                  required
                />
              </div>
            </div>
            <div className="flex justify-end">
              <Button type="submit" disabled={busy}>
                <KeyRound className="h-4 w-4" />
                Update Password
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-lg">Session</CardTitle>
          <CardDescription>Current API token session details.</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4 text-sm md:grid-cols-3">
            <div>
              <p className="text-muted-foreground text-xs font-semibold uppercase">Token Created</p>
              <p className="font-medium">
                {formatDateTimeDisplay(accountSettings?.session.token_created_at ?? null)}
              </p>
            </div>
            <div>
              <p className="text-muted-foreground text-xs font-semibold uppercase">Last Used</p>
              <p className="font-medium">
                {formatDateTimeDisplay(accountSettings?.session.token_last_used_at ?? null)}
              </p>
            </div>
            <div>
              <p className="text-muted-foreground text-xs font-semibold uppercase">Expires</p>
              <p className="font-medium">
                {formatDateTimeDisplay(accountSettings?.session.token_expires_at ?? null)}
              </p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}


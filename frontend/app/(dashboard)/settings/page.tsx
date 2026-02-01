"use client";

import { useState, useEffect } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Save, Loader2, Bell } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { authApi, User } from "@/lib/api";

export default function UserSettingsPage() {
  const queryClient = useQueryClient();
  const [saved, setSaved] = useState(false);
  const [formData, setFormData] = useState<Partial<User>>({});

  const { data: user, isLoading } = useQuery({
    queryKey: ["user"],
    queryFn: () => authApi.me(),
  });

  useEffect(() => {
    if (user) {
      setFormData({
        email_notifications_enabled: user.email_notifications_enabled,
        email_daily_recap: user.email_daily_recap,
        email_weekly_recap: user.email_weekly_recap,
        email_monthly_recap: user.email_monthly_recap,
      });
    }
  }, [user]);

  const updateMutation = useMutation({
    mutationFn: (data: Partial<User>) => authApi.updateMe(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["user"] });
      setSaved(true);
      setTimeout(() => setSaved(false), 3000);
    },
  });

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, checked } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: checked,
    }));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    updateMutation.mutate(formData);
  };

  if (isLoading) {
    return (
      <div className="flex h-full items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin" />
      </div>
    );
  }

  return (
    <div className="h-full overflow-y-auto">
      <div className="border-b px-6 py-4">
        <h1 className="text-xl font-semibold">Settings</h1>
        <p className="text-sm text-muted-foreground">
          Manage your account preferences
        </p>
      </div>

      <form onSubmit={handleSubmit} className="p-6 space-y-6">
        {saved && (
          <div className="p-3 text-sm text-green-600 bg-green-100 dark:bg-green-900/30 dark:text-green-300 rounded-md">
            Settings saved successfully!
          </div>
        )}

        {/* Email Notifications */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Bell className="h-5 w-5" />
              Email Notifications
            </CardTitle>
            <CardDescription>
              Choose which email notifications you want to receive
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label htmlFor="email_notifications_enabled" className="text-base">
                    Email Notifications
                  </Label>
                  <p className="text-sm text-muted-foreground">
                    Enable or disable all email notifications
                  </p>
                </div>
                <input
                  type="checkbox"
                  id="email_notifications_enabled"
                  name="email_notifications_enabled"
                  checked={formData.email_notifications_enabled ?? true}
                  onChange={handleChange}
                  className="h-5 w-5"
                />
              </div>

              <div className="border-t pt-4">
                <h4 className="text-sm font-medium mb-3">Recap Notifications</h4>
                <p className="text-sm text-muted-foreground mb-4">
                  Get email notifications when new recaps are generated
                </p>

                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <div className="space-y-0.5">
                      <Label
                        htmlFor="email_daily_recap"
                        className={!formData.email_notifications_enabled ? "text-muted-foreground" : ""}
                      >
                        Daily Recaps
                      </Label>
                      <p className="text-xs text-muted-foreground">
                        Receive daily digest emails
                      </p>
                    </div>
                    <input
                      type="checkbox"
                      id="email_daily_recap"
                      name="email_daily_recap"
                      checked={formData.email_daily_recap ?? true}
                      onChange={handleChange}
                      disabled={!formData.email_notifications_enabled}
                      className="h-5 w-5"
                    />
                  </div>

                  <div className="flex items-center justify-between">
                    <div className="space-y-0.5">
                      <Label
                        htmlFor="email_weekly_recap"
                        className={!formData.email_notifications_enabled ? "text-muted-foreground" : ""}
                      >
                        Weekly Recaps
                      </Label>
                      <p className="text-xs text-muted-foreground">
                        Receive weekly summary emails
                      </p>
                    </div>
                    <input
                      type="checkbox"
                      id="email_weekly_recap"
                      name="email_weekly_recap"
                      checked={formData.email_weekly_recap ?? true}
                      onChange={handleChange}
                      disabled={!formData.email_notifications_enabled}
                      className="h-5 w-5"
                    />
                  </div>

                  <div className="flex items-center justify-between">
                    <div className="space-y-0.5">
                      <Label
                        htmlFor="email_monthly_recap"
                        className={!formData.email_notifications_enabled ? "text-muted-foreground" : ""}
                      >
                        Monthly Recaps
                      </Label>
                      <p className="text-xs text-muted-foreground">
                        Receive monthly summary emails
                      </p>
                    </div>
                    <input
                      type="checkbox"
                      id="email_monthly_recap"
                      name="email_monthly_recap"
                      checked={formData.email_monthly_recap ?? false}
                      onChange={handleChange}
                      disabled={!formData.email_notifications_enabled}
                      className="h-5 w-5"
                    />
                  </div>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <div className="flex justify-end">
          <Button type="submit" disabled={updateMutation.isPending}>
            {updateMutation.isPending ? (
              <>
                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                Saving...
              </>
            ) : (
              <>
                <Save className="h-4 w-4 mr-2" />
                Save Settings
              </>
            )}
          </Button>
        </div>
      </form>
    </div>
  );
}

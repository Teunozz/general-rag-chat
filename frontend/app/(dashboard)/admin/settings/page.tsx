"use client";

import { useState, useEffect } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Save, Loader2, RefreshCw, RotateCcw, Info } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { RequireAdmin } from "@/lib/auth";
import { adminApi, sourcesApi } from "@/lib/api";
import { useTheme } from "@/lib/theme";

interface Settings {
  app_name: string;
  app_description: string;
  primary_color: string;
  secondary_color: string;
  llm_provider: string;
  chat_model: string;
  embedding_provider: string;
  embedding_model: string;
  recap_enabled: boolean;
  recap_daily_enabled: boolean;
  recap_weekly_enabled: boolean;
  recap_monthly_enabled: boolean;
  chat_context_chunks: number;
  chat_temperature: number;
  chat_system_prompt: string;
}

interface ModelInfo {
  id: string;
  display_name: string;
}

interface SettingsOptions {
  llm_providers: string[];
  embedding_providers: string[];
  openai_chat_models: ModelInfo[];
  anthropic_chat_models: ModelInfo[];
  openai_embedding_models: string[];
  sentence_transformer_models: string[];
  last_updated: string | null;
}

export default function SettingsPage() {
  const queryClient = useQueryClient();
  const { refreshSettings } = useTheme();
  const [formData, setFormData] = useState<Partial<Settings>>({});
  const [saved, setSaved] = useState(false);

  const { data: settings, isLoading } = useQuery({
    queryKey: ["settings"],
    queryFn: () => adminApi.getSettings(),
  });

  const { data: options } = useQuery<SettingsOptions>({
    queryKey: ["settings-options"],
    queryFn: () => adminApi.getSettingsOptions(),
  });

  // Get available chat models based on selected LLM provider
  const getChatModels = (): ModelInfo[] => {
    if (!options) return [];
    switch (formData.llm_provider) {
      case "openai":
        return options.openai_chat_models;
      case "anthropic":
        return options.anthropic_chat_models;
      case "ollama":
        return []; // Ollama allows any model name
      default:
        return options.openai_chat_models;
    }
  };

  // Get available embedding models based on selected provider
  const getEmbeddingModels = () => {
    if (!options) return [];
    switch (formData.embedding_provider) {
      case "openai":
        return options.openai_embedding_models;
      case "sentence_transformers":
        return options.sentence_transformer_models;
      default:
        return options.openai_embedding_models;
    }
  };

  useEffect(() => {
    if (settings) {
      setFormData(settings);
    }
  }, [settings]);

  // Reset chat model when LLM provider changes to first valid option
  useEffect(() => {
    if (!options || !formData.llm_provider) return;

    let validModels: ModelInfo[] = [];
    if (formData.llm_provider === "openai") {
      validModels = options.openai_chat_models;
    } else if (formData.llm_provider === "anthropic") {
      validModels = options.anthropic_chat_models;
    }
    // For ollama, any model name is valid

    const validIds = validModels.map((m) => m.id);
    if (validModels.length > 0 && formData.chat_model && !validIds.includes(formData.chat_model)) {
      setFormData((prev) => ({ ...prev, chat_model: validModels[0].id }));
    }
  }, [formData.llm_provider, formData.chat_model, options]);

  // Reset embedding model when embedding provider changes to first valid option
  useEffect(() => {
    if (!options || !formData.embedding_provider) return;

    let validModels: string[] = [];
    if (formData.embedding_provider === "openai") {
      validModels = options.openai_embedding_models;
    } else if (formData.embedding_provider === "sentence_transformers") {
      validModels = options.sentence_transformer_models;
    }

    if (validModels.length > 0 && formData.embedding_model && !validModels.includes(formData.embedding_model)) {
      setFormData((prev) => ({ ...prev, embedding_model: validModels[0] }));
    }
  }, [formData.embedding_provider, formData.embedding_model, options]);

  const updateMutation = useMutation({
    mutationFn: (data: Partial<Settings>) => adminApi.updateSettings(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["settings"] });
      refreshSettings();
      setSaved(true);
      setTimeout(() => setSaved(false), 3000);
    },
  });

  const refreshModelsMutation = useMutation({
    mutationFn: () => adminApi.refreshModels(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["settings-options"] });
    },
  });

  const rechunkAllMutation = useMutation({
    mutationFn: () => sourcesApi.rechunkAll(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["sources"] });
    },
  });

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>
  ) => {
    const { name, value, type } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]:
        type === "checkbox"
          ? (e.target as HTMLInputElement).checked
          : type === "number"
          ? parseFloat(value)
          : value,
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
    <RequireAdmin>
      <div className="h-full overflow-y-auto">
        <div className="border-b px-6 py-4">
          <h1 className="text-xl font-semibold">Settings</h1>
          <p className="text-sm text-muted-foreground">
            Configure your RAG system
          </p>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-6">
          {saved && (
            <div className="p-3 text-sm text-green-600 bg-green-100 rounded-md">
              Settings saved successfully!
            </div>
          )}

          {/* Branding */}
          <Card>
            <CardHeader>
              <CardTitle>Branding</CardTitle>
              <CardDescription>
                Customize the appearance of your application
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="app_name">Application Name</Label>
                <Input
                  id="app_name"
                  name="app_name"
                  value={formData.app_name || ""}
                  onChange={handleChange}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="app_description">Description</Label>
                <Input
                  id="app_description"
                  name="app_description"
                  value={formData.app_description || ""}
                  onChange={handleChange}
                />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="primary_color">Primary Color</Label>
                  <div className="flex gap-2">
                    <Input
                      id="primary_color"
                      name="primary_color"
                      type="color"
                      value={formData.primary_color || "#3B82F6"}
                      onChange={handleChange}
                      className="w-12 h-10 p-1"
                    />
                    <Input
                      value={formData.primary_color || "#3B82F6"}
                      onChange={(e) =>
                        setFormData((prev) => ({
                          ...prev,
                          primary_color: e.target.value,
                        }))
                      }
                      className="flex-1"
                    />
                  </div>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="secondary_color">Secondary Color</Label>
                  <div className="flex gap-2">
                    <Input
                      id="secondary_color"
                      name="secondary_color"
                      type="color"
                      value={formData.secondary_color || "#1E40AF"}
                      onChange={handleChange}
                      className="w-12 h-10 p-1"
                    />
                    <Input
                      value={formData.secondary_color || "#1E40AF"}
                      onChange={(e) =>
                        setFormData((prev) => ({
                          ...prev,
                          secondary_color: e.target.value,
                        }))
                      }
                      className="flex-1"
                    />
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* LLM Configuration */}
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <div>
                  <CardTitle>LLM Configuration</CardTitle>
                  <CardDescription>
                    Configure your language model settings
                    {options?.last_updated && (
                      <span className="ml-2 text-xs text-muted-foreground">
                        (Models updated: {new Date(options.last_updated).toLocaleString()})
                      </span>
                    )}
                  </CardDescription>
                </div>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => refreshModelsMutation.mutate()}
                  disabled={refreshModelsMutation.isPending}
                >
                  {refreshModelsMutation.isPending ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                  ) : (
                    <RefreshCw className="h-4 w-4" />
                  )}
                  <span className="ml-2">Refresh Models</span>
                </Button>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="llm_provider">LLM Provider</Label>
                  <select
                    id="llm_provider"
                    name="llm_provider"
                    value={formData.llm_provider || "openai"}
                    onChange={handleChange}
                    className="w-full h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                  >
                    <option value="openai">OpenAI</option>
                    <option value="anthropic">Anthropic</option>
                    <option value="ollama">Ollama (Local)</option>
                  </select>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="chat_model">Chat Model</Label>
                  {formData.llm_provider === "ollama" ? (
                    <Input
                      id="chat_model"
                      name="chat_model"
                      value={formData.chat_model || ""}
                      onChange={handleChange}
                      placeholder="e.g., llama2, mistral"
                    />
                  ) : (
                    <select
                      id="chat_model"
                      name="chat_model"
                      value={formData.chat_model || ""}
                      onChange={handleChange}
                      className="w-full h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                    >
                      {getChatModels().map((model) => (
                        <option key={model.id} value={model.id}>
                          {model.display_name}
                        </option>
                      ))}
                    </select>
                  )}
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="embedding_provider">Embedding Provider</Label>
                  <select
                    id="embedding_provider"
                    name="embedding_provider"
                    value={formData.embedding_provider || "openai"}
                    onChange={handleChange}
                    className="w-full h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                  >
                    <option value="openai">OpenAI</option>
                    <option value="sentence_transformers">
                      Sentence Transformers (Local)
                    </option>
                  </select>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="embedding_model">Embedding Model</Label>
                  <select
                    id="embedding_model"
                    name="embedding_model"
                    value={formData.embedding_model || ""}
                    onChange={handleChange}
                    className="w-full h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                  >
                    {getEmbeddingModels().map((model) => (
                      <option key={model} value={model}>
                        {model}
                      </option>
                    ))}
                  </select>
                </div>
              </div>
              <div className="pt-4 border-t">
                <div className="flex items-start gap-4">
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => rechunkAllMutation.mutate()}
                    disabled={rechunkAllMutation.isPending}
                  >
                    {rechunkAllMutation.isPending ? (
                      <Loader2 className="h-4 w-4 animate-spin" />
                    ) : (
                      <RotateCcw className="h-4 w-4" />
                    )}
                    <span className="ml-2">Re-chunk All Sources</span>
                  </Button>
                  <div className="flex-1 text-sm text-muted-foreground">
                    <div className="flex items-center gap-1 font-medium">
                      <Info className="h-4 w-4" />
                      <span>When to use this</span>
                    </div>
                    <p className="mt-1">
                      After changing the embedding model, use this to re-process all existing content
                      with the new model. This re-chunks and re-embeds stored content without
                      re-fetching from sources, preserving historical data like old RSS articles.
                    </p>
                  </div>
                </div>
                {rechunkAllMutation.isSuccess && (
                  <p className="mt-2 text-sm text-green-600">
                    Re-chunking has been triggered for all sources. Check the sources page for progress.
                  </p>
                )}
                {rechunkAllMutation.isError && (
                  <p className="mt-2 text-sm text-red-600">
                    Failed to trigger re-chunking. Please try again.
                  </p>
                )}
              </div>
            </CardContent>
          </Card>

          {/* Chat Settings */}
          <Card>
            <CardHeader>
              <CardTitle>Chat Settings</CardTitle>
              <CardDescription>
                Configure chat behavior
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="chat_context_chunks">
                    Context Chunks (number of chunks to include)
                  </Label>
                  <Input
                    id="chat_context_chunks"
                    name="chat_context_chunks"
                    type="number"
                    min="1"
                    max="20"
                    value={formData.chat_context_chunks || 5}
                    onChange={handleChange}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="chat_temperature">
                    Temperature (0 = focused, 1 = creative)
                  </Label>
                  <Input
                    id="chat_temperature"
                    name="chat_temperature"
                    type="number"
                    min="0"
                    max="1"
                    step="0.1"
                    value={formData.chat_temperature || 0.7}
                    onChange={handleChange}
                  />
                </div>
              </div>
              <div className="space-y-2">
                <Label htmlFor="chat_system_prompt">System Prompt</Label>
                <textarea
                  id="chat_system_prompt"
                  name="chat_system_prompt"
                  value={formData.chat_system_prompt || ""}
                  onChange={handleChange}
                  rows={4}
                  className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
              </div>
            </CardContent>
          </Card>

          {/* Recap Settings */}
          <Card>
            <CardHeader>
              <CardTitle>Recap Settings</CardTitle>
              <CardDescription>
                Configure automated recap generation
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-center gap-4">
                <label className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    name="recap_enabled"
                    checked={formData.recap_enabled ?? true}
                    onChange={handleChange}
                    className="h-4 w-4"
                  />
                  <span className="text-sm">Enable Recaps</span>
                </label>
                <label className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    name="recap_daily_enabled"
                    checked={formData.recap_daily_enabled ?? true}
                    onChange={handleChange}
                    className="h-4 w-4"
                  />
                  <span className="text-sm">Daily</span>
                </label>
                <label className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    name="recap_weekly_enabled"
                    checked={formData.recap_weekly_enabled ?? true}
                    onChange={handleChange}
                    className="h-4 w-4"
                  />
                  <span className="text-sm">Weekly</span>
                </label>
                <label className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    name="recap_monthly_enabled"
                    checked={formData.recap_monthly_enabled ?? true}
                    onChange={handleChange}
                    className="h-4 w-4"
                  />
                  <span className="text-sm">Monthly</span>
                </label>
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
    </RequireAdmin>
  );
}

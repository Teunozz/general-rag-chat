"use client";

import { useState, useEffect } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Save, Loader2, RefreshCw, RotateCcw, Info, Mail, CheckCircle, XCircle } from "lucide-react";
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
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
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
  query_enrichment_enabled: boolean;
  query_enrichment_prompt: string | null;
  // Context expansion settings
  context_window_size: number;
  full_doc_score_threshold: number;
  max_full_doc_chars: number;
  max_context_tokens: number;
  // Email notification settings
  email_notifications_enabled: boolean;
  email_recap_notifications_enabled: boolean;
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
  default_enrichment_prompt: string;
}

export default function SettingsPage() {
  const queryClient = useQueryClient();
  const { refreshSettings } = useTheme();
  const [formData, setFormData] = useState<Partial<Settings>>({});
  const [saved, setSaved] = useState(false);
  const [showEmbeddingWarning, setShowEmbeddingWarning] = useState(false);
  const [originalEmbeddingSettings, setOriginalEmbeddingSettings] = useState<{
    provider: string;
    model: string;
  } | null>(null);

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
      // Store original embedding settings to detect changes
      if (originalEmbeddingSettings === null) {
        setOriginalEmbeddingSettings({
          provider: settings.embedding_provider,
          model: settings.embedding_model,
        });
      }
    }
  }, [settings, originalEmbeddingSettings]);

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

  const [testEmailStatus, setTestEmailStatus] = useState<{
    success: boolean;
    message: string;
  } | null>(null);

  const testEmailMutation = useMutation({
    mutationFn: () => adminApi.testEmail(),
    onSuccess: (data) => {
      setTestEmailStatus(data);
    },
    onError: (error: Error) => {
      setTestEmailStatus({
        success: false,
        message: error.message || "Failed to send test email",
      });
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

  const hasEmbeddingSettingsChanged = () => {
    if (!originalEmbeddingSettings) return false;
    return (
      formData.embedding_provider !== originalEmbeddingSettings.provider ||
      formData.embedding_model !== originalEmbeddingSettings.model
    );
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (hasEmbeddingSettingsChanged()) {
      setShowEmbeddingWarning(true);
    } else {
      updateMutation.mutate(formData);
    }
  };

  const handleConfirmEmbeddingChange = () => {
    setShowEmbeddingWarning(false);
    updateMutation.mutate(formData);
    // Update original settings after save so subsequent saves don't re-trigger
    setOriginalEmbeddingSettings({
      provider: formData.embedding_provider || "",
      model: formData.embedding_model || "",
    });
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
                    max="100"
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
                  rows={10}
                  className="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs"
                />
              </div>

              {/* Context Expansion Settings */}
              <div className="pt-4 border-t">
                <h4 className="text-sm font-medium mb-3">Context Expansion</h4>
                <p className="text-sm text-muted-foreground mb-4">
                  These settings control how the system retrieves and expands context around search results.
                </p>
                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label htmlFor="context_window_size">
                      Context Window Size
                    </Label>
                    <Input
                      id="context_window_size"
                      name="context_window_size"
                      type="number"
                      min="0"
                      max="5"
                      value={formData.context_window_size ?? 1}
                      onChange={handleChange}
                    />
                    <p className="text-xs text-muted-foreground">
                      Number of adjacent chunks to include around each match (0 = disabled)
                    </p>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="full_doc_score_threshold">
                      Full Document Threshold
                    </Label>
                    <Input
                      id="full_doc_score_threshold"
                      name="full_doc_score_threshold"
                      type="number"
                      min="0"
                      max="1"
                      step="0.05"
                      value={formData.full_doc_score_threshold ?? 0.85}
                      onChange={handleChange}
                    />
                    <p className="text-xs text-muted-foreground">
                      Score threshold to include full document content (0-1)
                    </p>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="max_full_doc_chars">
                      Max Full Doc Chars
                    </Label>
                    <Input
                      id="max_full_doc_chars"
                      name="max_full_doc_chars"
                      type="number"
                      min="1000"
                      max="50000"
                      step="1000"
                      value={formData.max_full_doc_chars ?? 10000}
                      onChange={handleChange}
                    />
                    <p className="text-xs text-muted-foreground">
                      Maximum characters to include from full documents
                    </p>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="max_context_tokens">
                      Max Context Tokens
                    </Label>
                    <Input
                      id="max_context_tokens"
                      name="max_context_tokens"
                      type="number"
                      min="1000"
                      max="100000"
                      step="1000"
                      value={formData.max_context_tokens ?? 16000}
                      onChange={handleChange}
                    />
                    <p className="text-xs text-muted-foreground">
                      Token budget for context (prevents context explosion)
                    </p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Query Enrichment */}
          <Card>
            <CardHeader>
              <CardTitle>Query Enrichment</CardTitle>
              <CardDescription>
                Improve search results by rewriting queries using conversation context
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-center gap-2">
                <input
                  type="checkbox"
                  id="query_enrichment_enabled"
                  name="query_enrichment_enabled"
                  checked={formData.query_enrichment_enabled ?? true}
                  onChange={handleChange}
                  className="h-4 w-4"
                />
                <Label htmlFor="query_enrichment_enabled">
                  Enable Query Enrichment
                </Label>
              </div>
              <p className="text-sm text-muted-foreground">
                When enabled, the LLM will rewrite your search queries to be more specific
                by expanding references like &quot;it&quot; or &quot;that&quot; using conversation history.
                For example, &quot;What about the second point?&quot; becomes &quot;Explain the second
                point about authentication tokens mentioned earlier&quot;.
              </p>
              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <Label htmlFor="query_enrichment_prompt">
                    Enrichment Prompt
                  </Label>
                  {formData.query_enrichment_prompt && (
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      onClick={() => setFormData((prev) => ({ ...prev, query_enrichment_prompt: null }))}
                      disabled={!formData.query_enrichment_enabled}
                    >
                      <RotateCcw className="h-3 w-3 mr-1" />
                      Reset to default
                    </Button>
                  )}
                </div>
                <textarea
                  id="query_enrichment_prompt"
                  name="query_enrichment_prompt"
                  value={formData.query_enrichment_prompt || options?.default_enrichment_prompt || ""}
                  onChange={handleChange}
                  rows={8}
                  className="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs"
                  disabled={!formData.query_enrichment_enabled}
                />
                <p className="text-xs text-muted-foreground">
                  This prompt instructs the LLM how to rewrite queries for better search results.
                </p>
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

          {/* Email Notifications */}
          <Card>
            <CardHeader>
              <CardTitle>Email Notifications</CardTitle>
              <CardDescription>
                Configure SMTP email notifications for recaps. SMTP settings are configured via environment variables.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-4">
                <div className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    id="email_notifications_enabled"
                    name="email_notifications_enabled"
                    checked={formData.email_notifications_enabled ?? false}
                    onChange={handleChange}
                    className="h-4 w-4"
                  />
                  <Label htmlFor="email_notifications_enabled">
                    Enable Email Notifications
                  </Label>
                </div>
                <p className="text-sm text-muted-foreground">
                  Master switch for all email notifications. When disabled, no emails will be sent.
                </p>

                <div className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    id="email_recap_notifications_enabled"
                    name="email_recap_notifications_enabled"
                    checked={formData.email_recap_notifications_enabled ?? false}
                    onChange={handleChange}
                    disabled={!formData.email_notifications_enabled}
                    className="h-4 w-4"
                  />
                  <Label htmlFor="email_recap_notifications_enabled" className={!formData.email_notifications_enabled ? "text-muted-foreground" : ""}>
                    Send Recap Notifications
                  </Label>
                </div>
                <p className="text-sm text-muted-foreground">
                  When enabled, users who have opted in will receive email notifications when new recaps are generated.
                </p>
              </div>

              <div className="pt-4 border-t">
                <div className="flex items-start gap-4">
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => {
                      setTestEmailStatus(null);
                      testEmailMutation.mutate();
                    }}
                    disabled={testEmailMutation.isPending}
                  >
                    {testEmailMutation.isPending ? (
                      <Loader2 className="h-4 w-4 animate-spin" />
                    ) : (
                      <Mail className="h-4 w-4" />
                    )}
                    <span className="ml-2">Test Email</span>
                  </Button>
                  <div className="flex-1 text-sm text-muted-foreground">
                    <div className="flex items-center gap-1 font-medium">
                      <Info className="h-4 w-4" />
                      <span>SMTP Configuration</span>
                    </div>
                    <p className="mt-1">
                      SMTP settings are configured via environment variables (SMTP_HOST, SMTP_PORT, etc.).
                      Click &quot;Test Email&quot; to verify your SMTP configuration by sending a test email to your admin email address.
                    </p>
                  </div>
                </div>
                {testEmailStatus && (
                  <div className={`mt-3 p-3 rounded-md flex items-center gap-2 ${
                    testEmailStatus.success
                      ? "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300"
                      : "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300"
                  }`}>
                    {testEmailStatus.success ? (
                      <CheckCircle className="h-4 w-4" />
                    ) : (
                      <XCircle className="h-4 w-4" />
                    )}
                    <span className="text-sm">{testEmailStatus.message}</span>
                  </div>
                )}
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

        <AlertDialog open={showEmbeddingWarning} onOpenChange={setShowEmbeddingWarning}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Change Embedding Model?</AlertDialogTitle>
              <AlertDialogDescription>
                Changing the embedding model will make all existing document vectors
                incompatible. Search results may be incorrect or empty until you
                re-chunk all sources. After saving, you&apos;ll need to click
                &quot;Re-chunk All Sources&quot; to re-process all content with the new
                embedding model.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>Cancel</AlertDialogCancel>
              <AlertDialogAction onClick={handleConfirmEmbeddingChange}>
                Continue and Save
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </div>
    </RequireAdmin>
  );
}

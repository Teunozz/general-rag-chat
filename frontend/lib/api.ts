import axios from "axios";

const API_URL = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000";

export const api = axios.create({
  baseURL: API_URL,
  headers: {
    "Content-Type": "application/json",
  },
});

// Add auth token to requests
api.interceptors.request.use((config) => {
  if (typeof window !== "undefined") {
    const token = localStorage.getItem("token");
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
  }
  return config;
});

// Handle auth errors
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      if (typeof window !== "undefined") {
        localStorage.removeItem("token");
        window.location.href = "/login";
      }
    }
    return Promise.reject(error);
  }
);

// Auth API
export interface User {
  id: number;
  email: string;
  name: string | null;
  role: "admin" | "user";
  is_active: boolean;
  email_notifications_enabled: boolean;
  email_daily_recap: boolean;
  email_weekly_recap: boolean;
  email_monthly_recap: boolean;
}

export interface UserNotificationUpdate {
  email_notifications_enabled?: boolean;
  email_daily_recap?: boolean;
  email_weekly_recap?: boolean;
  email_monthly_recap?: boolean;
}

export const authApi = {
  login: async (email: string, password: string) => {
    const formData = new FormData();
    formData.append("username", email);
    formData.append("password", password);
    const response = await api.post("/api/auth/login", formData, {
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
    });
    return response.data;
  },
  register: async (email: string, password: string, name?: string) => {
    const response = await api.post("/api/auth/register", {
      email,
      password,
      name,
    });
    return response.data;
  },
  me: async (): Promise<User> => {
    const response = await api.get("/api/auth/me");
    return response.data;
  },
  updateMe: async (data: Partial<User> & UserNotificationUpdate): Promise<User> => {
    const response = await api.put("/api/auth/me", data);
    return response.data;
  },
};

// Chat API
export interface ChatModelOption {
  id: string;
  name: string;
}

export interface ChatModelsResponse {
  llm_providers: string[];
  chat_models: Record<string, ChatModelOption[]>;
  last_updated: string | null;
}

export const chatApi = {
  send: async (
    message: string,
    options?: {
      sourceIds?: number[];
      conversationHistory?: { role: string; content: string }[];
      conversationId?: number;
      numChunks?: number;
      temperature?: number;
      llmProvider?: string;
      chatModel?: string;
    }
  ) => {
    const response = await api.post("/api/chat", {
      message,
      source_ids: options?.sourceIds,
      conversation_history: options?.conversationHistory,
      conversation_id: options?.conversationId,
      // Only include if explicitly provided; backend uses db settings as defaults
      ...(options?.numChunks !== undefined && { num_chunks: options.numChunks }),
      ...(options?.temperature !== undefined && { temperature: options.temperature }),
      ...(options?.llmProvider && { llm_provider: options.llmProvider }),
      ...(options?.chatModel && { chat_model: options.chatModel }),
    });
    return response.data;
  },
  search: async (query: string, limit = 5, sourceIds?: number[]) => {
    const params = new URLSearchParams({ query, limit: limit.toString() });
    if (sourceIds?.length) {
      params.append("source_ids", sourceIds.join(","));
    }
    const response = await api.get(`/api/chat/search?${params}`);
    return response.data;
  },
  getModels: async (): Promise<ChatModelsResponse> => {
    const response = await api.get("/api/chat/models");
    return response.data;
  },
};

// Conversations API
export interface Conversation {
  id: number;
  title: string | null;
  source_ids: number[] | null;
  created_at: string;
  updated_at: string;
}

export interface ConversationMessage {
  id: number;
  role: "user" | "assistant";
  content: string;
  sources: {
    document_id: number;
    source_id: number;
    title: string | null;
    url: string | null;
    content_preview: string;
    score: number;
  }[] | null;
  created_at: string;
}

export interface ConversationWithMessages extends Conversation {
  messages: ConversationMessage[];
}

export const conversationsApi = {
  list: async (): Promise<Conversation[]> => {
    const response = await api.get("/api/conversations");
    return response.data;
  },
  get: async (id: number): Promise<ConversationWithMessages> => {
    const response = await api.get(`/api/conversations/${id}`);
    return response.data;
  },
  create: async (data?: { title?: string; source_ids?: number[] }): Promise<Conversation> => {
    const response = await api.post("/api/conversations", data || {});
    return response.data;
  },
  delete: async (id: number): Promise<void> => {
    await api.delete(`/api/conversations/${id}`);
  },
  update: async (id: number, data: { title?: string; source_ids?: number[] }): Promise<Conversation> => {
    const response = await api.patch(`/api/conversations/${id}`, data);
    return response.data;
  },
};

// Sources API
export const sourcesApi = {
  list: async () => {
    const response = await api.get("/api/sources");
    return response.data;
  },
  get: async (id: number) => {
    const response = await api.get(`/api/sources/${id}`);
    return response.data;
  },
  createWebsite: async (data: {
    name: string;
    description?: string;
    url: string;
    crawlDepth?: number;
    crawlSameDomainOnly?: boolean;
    requireArticleType?: boolean;
    articleTypes?: string;
    minContentLength?: number;
  }) => {
    const response = await api.post("/api/sources/website", {
      name: data.name,
      description: data.description,
      url: data.url,
      crawl_depth: data.crawlDepth || 1,
      crawl_same_domain_only: data.crawlSameDomainOnly ?? true,
      require_article_type: data.requireArticleType ?? false,
      article_types: data.articleTypes || undefined,
      min_content_length: data.minContentLength || 0,
    });
    return response.data;
  },
  createRss: async (data: {
    name: string;
    description?: string;
    url: string;
    refreshIntervalMinutes?: number;
  }) => {
    const response = await api.post("/api/sources/rss", {
      name: data.name,
      description: data.description,
      url: data.url,
      refresh_interval_minutes: data.refreshIntervalMinutes || 60,
    });
    return response.data;
  },
  uploadDocument: async (name: string, file: File, description?: string) => {
    const formData = new FormData();
    formData.append("name", name);
    formData.append("file", file);
    if (description) formData.append("description", description);
    const response = await api.post("/api/sources/document", formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    return response.data;
  },
  update: async (id: number, data: Record<string, unknown>) => {
    const response = await api.put(`/api/sources/${id}`, data);
    return response.data;
  },
  delete: async (id: number) => {
    const response = await api.delete(`/api/sources/${id}`);
    return response.data;
  },
  reindex: async (id: number) => {
    const response = await api.post(`/api/sources/${id}/reindex`);
    return response.data;
  },
  rechunk: async (id: number) => {
    const response = await api.post(`/api/sources/${id}/rechunk`);
    return response.data;
  },
  rechunkAll: async () => {
    const response = await api.post("/api/sources/rechunk-all");
    return response.data;
  },
};

// Recaps API
export const recapsApi = {
  list: async (type?: string, limit = 20) => {
    const params = new URLSearchParams({ limit: limit.toString() });
    if (type) params.append("recap_type", type);
    const response = await api.get(`/api/recaps?${params}`);
    return response.data;
  },
  get: async (id: number) => {
    const response = await api.get(`/api/recaps/${id}`);
    return response.data;
  },
  getLatest: async () => {
    const response = await api.get("/api/recaps/latest");
    return response.data;
  },
  generate: async (type: string) => {
    const response = await api.post("/api/recaps/generate", {
      recap_type: type,
    });
    return response.data;
  },
};

// Admin API
export interface TestEmailResponse {
  success: boolean;
  message: string;
}

export const adminApi = {
  getStats: async () => {
    const response = await api.get("/api/admin/stats");
    return response.data;
  },
  getSettings: async () => {
    const response = await api.get("/api/admin/settings");
    return response.data;
  },
  getSettingsOptions: async () => {
    const response = await api.get("/api/admin/settings/options");
    return response.data;
  },
  refreshModels: async () => {
    const response = await api.post("/api/admin/settings/refresh-models");
    return response.data;
  },
  updateSettings: async (data: Record<string, unknown>) => {
    const response = await api.put("/api/admin/settings", data);
    return response.data;
  },
  testEmail: async (email?: string): Promise<TestEmailResponse> => {
    const response = await api.post("/api/admin/settings/test-email", { email });
    return response.data;
  },
  listUsers: async () => {
    const response = await api.get("/api/admin/users");
    return response.data;
  },
  createUser: async (data: {
    email: string;
    password: string;
    name?: string;
    role?: string;
  }) => {
    const response = await api.post("/api/admin/users", data);
    return response.data;
  },
  updateUser: async (id: number, data: Record<string, unknown>) => {
    const response = await api.put(`/api/admin/users/${id}`, data);
    return response.data;
  },
  deleteUser: async (id: number) => {
    const response = await api.delete(`/api/admin/users/${id}`);
    return response.data;
  },
};

export default api;

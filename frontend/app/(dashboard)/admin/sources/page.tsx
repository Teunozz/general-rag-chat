"use client";

import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  Globe,
  FileText,
  Rss,
  Plus,
  Trash2,
  RefreshCw,
  AlertCircle,
  CheckCircle,
  Clock,
  Loader2,
  Pencil,
} from "lucide-react";
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
import { sourcesApi } from "@/lib/api";
import { formatDateTime } from "@/lib/utils";

interface Source {
  id: number;
  name: string;
  description: string | null;
  source_type: string;
  status: string;
  url: string | null;
  file_path: string | null;
  crawl_depth: number;
  crawl_same_domain_only?: boolean;
  refresh_interval_minutes: number;
  document_count: number;
  chunk_count: number;
  last_indexed_at: string | null;
  error_message: string | null;
  created_at: string;
}

const sourceTypeIcons: Record<string, React.ReactNode> = {
  website: <Globe className="h-5 w-5" />,
  document: <FileText className="h-5 w-5" />,
  rss: <Rss className="h-5 w-5" />,
};

const statusColors: Record<string, string> = {
  pending: "text-yellow-600",
  processing: "text-blue-600",
  ready: "text-green-600",
  error: "text-red-600",
};

const statusIcons: Record<string, React.ReactNode> = {
  pending: <Clock className="h-4 w-4" />,
  processing: <Loader2 className="h-4 w-4 animate-spin" />,
  ready: <CheckCircle className="h-4 w-4" />,
  error: <AlertCircle className="h-4 w-4" />,
};

export default function SourcesPage() {
  const queryClient = useQueryClient();
  const [showAddForm, setShowAddForm] = useState(false);
  const [addType, setAddType] = useState<"website" | "rss" | "document" | null>(
    null
  );
  const [editingSource, setEditingSource] = useState<Source | null>(null);

  const { data: sources, isLoading } = useQuery({
    queryKey: ["sources"],
    queryFn: () => sourcesApi.list(),
    refetchInterval: 5000,
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => sourcesApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["sources"] });
    },
  });

  const reindexMutation = useMutation({
    mutationFn: (id: number) => sourcesApi.reindex(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["sources"] });
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Record<string, unknown> }) =>
      sourcesApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["sources"] });
      setEditingSource(null);
    },
  });

  return (
    <RequireAdmin>
      <div className="h-full overflow-y-auto">
        <div className="border-b px-6 py-4 flex justify-between items-center">
          <div>
            <h1 className="text-xl font-semibold">Sources</h1>
            <p className="text-sm text-muted-foreground">
              Manage your content sources
            </p>
          </div>
          <Button onClick={() => setShowAddForm(true)}>
            <Plus className="h-4 w-4 mr-2" />
            Add Source
          </Button>
        </div>

        <div className="p-6">
          {showAddForm && (
            <Card className="mb-6">
              <CardHeader>
                <CardTitle>Add New Source</CardTitle>
                <CardDescription>
                  Choose a source type to add
                </CardDescription>
              </CardHeader>
              <CardContent>
                {!addType ? (
                  <div className="grid grid-cols-3 gap-4">
                    <Button
                      variant="outline"
                      className="h-24 flex flex-col gap-2"
                      onClick={() => setAddType("website")}
                    >
                      <Globe className="h-8 w-8" />
                      Website
                    </Button>
                    <Button
                      variant="outline"
                      className="h-24 flex flex-col gap-2"
                      onClick={() => setAddType("rss")}
                    >
                      <Rss className="h-8 w-8" />
                      RSS Feed
                    </Button>
                    <Button
                      variant="outline"
                      className="h-24 flex flex-col gap-2"
                      onClick={() => setAddType("document")}
                    >
                      <FileText className="h-8 w-8" />
                      Document
                    </Button>
                  </div>
                ) : (
                  <AddSourceForm
                    type={addType}
                    onCancel={() => {
                      setAddType(null);
                      setShowAddForm(false);
                    }}
                    onSuccess={() => {
                      setAddType(null);
                      setShowAddForm(false);
                      queryClient.invalidateQueries({ queryKey: ["sources"] });
                    }}
                  />
                )}
              </CardContent>
            </Card>
          )}

          {editingSource && (
            <Card className="mb-6">
              <CardHeader>
                <CardTitle>Edit Source</CardTitle>
                <CardDescription>
                  Update source settings
                </CardDescription>
              </CardHeader>
              <CardContent>
                <EditSourceForm
                  source={editingSource}
                  onCancel={() => setEditingSource(null)}
                  onSubmit={(data) =>
                    updateMutation.mutate({ id: editingSource.id, data })
                  }
                  isSubmitting={updateMutation.isPending}
                  error={
                    updateMutation.error
                      ? (updateMutation.error as { response?: { data?: { detail?: string } } })
                          .response?.data?.detail || "Failed to update source"
                      : undefined
                  }
                />
              </CardContent>
            </Card>
          )}

          {isLoading ? (
            <div className="flex justify-center py-12">
              <Loader2 className="h-8 w-8 animate-spin" />
            </div>
          ) : !sources || sources.length === 0 ? (
            <div className="text-center py-12">
              <FileText className="mx-auto h-12 w-12 text-muted-foreground" />
              <h3 className="mt-4 text-lg font-medium">No sources yet</h3>
              <p className="mt-2 text-sm text-muted-foreground">
                Add your first source to start indexing content
              </p>
            </div>
          ) : (
            <div className="space-y-4">
              {sources.map((source: Source) => (
                <Card key={source.id}>
                  <CardContent className="p-6">
                    <div className="flex items-start justify-between">
                      <div className="flex items-start gap-4">
                        <div className="p-2 bg-muted rounded-lg">
                          {sourceTypeIcons[source.source_type]}
                        </div>
                        <div>
                          <h3 className="font-medium">{source.name}</h3>
                          {source.description && (
                            <p className="text-sm text-muted-foreground">
                              {source.description}
                            </p>
                          )}
                          {source.url && (
                            <p className="text-sm text-muted-foreground truncate max-w-md">
                              {source.url}
                            </p>
                          )}
                          <div className="flex items-center gap-4 mt-2 text-sm text-muted-foreground">
                            <span className={statusColors[source.status]}>
                              <span className="inline-flex items-center gap-1">
                                {statusIcons[source.status]}
                                {source.status}
                              </span>
                            </span>
                            <span>{source.document_count} documents</span>
                            <span>{source.chunk_count} chunks</span>
                            {source.last_indexed_at && (
                              <span>
                                Last indexed: {formatDateTime(source.last_indexed_at)}
                              </span>
                            )}
                          </div>
                          {source.error_message && (
                            <p className="text-sm text-destructive mt-2">
                              {source.error_message}
                            </p>
                          )}
                        </div>
                      </div>
                      <div className="flex gap-2">
                        <Button
                          variant="outline"
                          size="icon"
                          onClick={() => setEditingSource(source)}
                          title="Edit source"
                        >
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="outline"
                          size="icon"
                          onClick={() => reindexMutation.mutate(source.id)}
                          disabled={reindexMutation.isPending}
                          title="Reindex source"
                        >
                          <RefreshCw className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="outline"
                          size="icon"
                          onClick={() => deleteMutation.mutate(source.id)}
                          disabled={deleteMutation.isPending}
                          title="Delete source"
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          )}
        </div>
      </div>
    </RequireAdmin>
  );
}

function AddSourceForm({
  type,
  onCancel,
  onSuccess,
}: {
  type: "website" | "rss" | "document";
  onCancel: () => void;
  onSuccess: () => void;
}) {
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [url, setUrl] = useState("");
  const [file, setFile] = useState<File | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState("");

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setIsSubmitting(true);

    try {
      if (type === "website") {
        await sourcesApi.createWebsite({
          name,
          description: description || undefined,
          url,
        });
      } else if (type === "rss") {
        await sourcesApi.createRss({
          name,
          description: description || undefined,
          url,
        });
      } else if (type === "document" && file) {
        await sourcesApi.uploadDocument(name, file, description || undefined);
      }
      onSuccess();
    } catch (err: unknown) {
      const error = err as { response?: { data?: { detail?: string } } };
      setError(error.response?.data?.detail || "Failed to add source");
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {error && (
        <div className="p-3 text-sm text-destructive bg-destructive/10 rounded-md">
          {error}
        </div>
      )}
      <div className="space-y-2">
        <Label htmlFor="name">Name</Label>
        <Input
          id="name"
          value={name}
          onChange={(e) => setName(e.target.value)}
          required
        />
      </div>
      <div className="space-y-2">
        <Label htmlFor="description">Description (optional)</Label>
        <Input
          id="description"
          value={description}
          onChange={(e) => setDescription(e.target.value)}
        />
      </div>
      {(type === "website" || type === "rss") && (
        <div className="space-y-2">
          <Label htmlFor="url">URL</Label>
          <Input
            id="url"
            type="url"
            value={url}
            onChange={(e) => setUrl(e.target.value)}
            required
          />
        </div>
      )}
      {type === "document" && (
        <div className="space-y-2">
          <Label htmlFor="file">File</Label>
          <Input
            id="file"
            type="file"
            onChange={(e) => setFile(e.target.files?.[0] || null)}
            accept=".pdf,.docx,.doc,.txt,.md,.html"
            required
          />
        </div>
      )}
      <div className="flex gap-2">
        <Button type="button" variant="outline" onClick={onCancel}>
          Cancel
        </Button>
        <Button type="submit" disabled={isSubmitting}>
          {isSubmitting ? (
            <>
              <Loader2 className="h-4 w-4 mr-2 animate-spin" />
              Adding...
            </>
          ) : (
            "Add Source"
          )}
        </Button>
      </div>
    </form>
  );
}

function EditSourceForm({
  source,
  onCancel,
  onSubmit,
  isSubmitting,
  error,
}: {
  source: Source;
  onCancel: () => void;
  onSubmit: (data: Record<string, unknown>) => void;
  isSubmitting: boolean;
  error?: string;
}) {
  const [name, setName] = useState(source.name);
  const [description, setDescription] = useState(source.description || "");
  const [crawlDepth, setCrawlDepth] = useState(source.crawl_depth);
  const [crawlSameDomainOnly, setCrawlSameDomainOnly] = useState(
    source.crawl_same_domain_only ?? true
  );
  const [refreshIntervalMinutes, setRefreshIntervalMinutes] = useState(
    source.refresh_interval_minutes
  );

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    const data: Record<string, unknown> = {
      name,
      description: description || null,
    };

    if (source.source_type === "website") {
      data.crawl_depth = crawlDepth;
      data.crawl_same_domain_only = crawlSameDomainOnly;
    } else if (source.source_type === "rss") {
      data.refresh_interval_minutes = refreshIntervalMinutes;
    }

    onSubmit(data);
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {error && (
        <div className="p-3 text-sm text-destructive bg-destructive/10 rounded-md">
          {error}
        </div>
      )}

      <div className="flex items-center gap-2 text-sm text-muted-foreground mb-4">
        <span className="p-1.5 bg-muted rounded">
          {sourceTypeIcons[source.source_type]}
        </span>
        <span className="capitalize">{source.source_type}</span>
        {source.url && (
          <span className="truncate max-w-md">({source.url})</span>
        )}
      </div>

      <div className="space-y-2">
        <Label htmlFor="edit-name">Name</Label>
        <Input
          id="edit-name"
          value={name}
          onChange={(e) => setName(e.target.value)}
          required
        />
      </div>

      <div className="space-y-2">
        <Label htmlFor="edit-description">Description</Label>
        <Input
          id="edit-description"
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          placeholder="Optional description"
        />
      </div>

      {source.source_type === "website" && (
        <>
          <div className="space-y-2">
            <Label htmlFor="edit-crawl-depth">Crawl Depth</Label>
            <Input
              id="edit-crawl-depth"
              type="number"
              min={1}
              max={10}
              value={crawlDepth}
              onChange={(e) => setCrawlDepth(parseInt(e.target.value) || 1)}
            />
            <p className="text-xs text-muted-foreground">
              How many levels deep to crawl links (1 = only the specified URL)
            </p>
          </div>

          <div className="flex items-center gap-2">
            <input
              id="edit-same-domain"
              type="checkbox"
              checked={crawlSameDomainOnly}
              onChange={(e) => setCrawlSameDomainOnly(e.target.checked)}
              className="h-4 w-4 rounded border-gray-300"
            />
            <Label htmlFor="edit-same-domain" className="font-normal">
              Only crawl links on the same domain
            </Label>
          </div>
        </>
      )}

      {source.source_type === "rss" && (
        <div className="space-y-2">
          <Label htmlFor="edit-refresh-interval">Refresh Interval (minutes)</Label>
          <Input
            id="edit-refresh-interval"
            type="number"
            min={5}
            value={refreshIntervalMinutes}
            onChange={(e) =>
              setRefreshIntervalMinutes(parseInt(e.target.value) || 60)
            }
          />
          <p className="text-xs text-muted-foreground">
            How often to check for new RSS items
          </p>
        </div>
      )}

      <div className="flex gap-2">
        <Button type="button" variant="outline" onClick={onCancel}>
          Cancel
        </Button>
        <Button type="submit" disabled={isSubmitting}>
          {isSubmitting ? (
            <>
              <Loader2 className="h-4 w-4 mr-2 animate-spin" />
              Saving...
            </>
          ) : (
            "Save Changes"
          )}
        </Button>
      </div>
    </form>
  );
}

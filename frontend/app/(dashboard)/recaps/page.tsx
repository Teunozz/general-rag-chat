"use client";

import { useQuery } from "@tanstack/react-query";
import { Calendar, Clock, FileText } from "lucide-react";
import ReactMarkdown from "react-markdown";
import remarkGfm from "remark-gfm";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { recapsApi } from "@/lib/api";
import { formatDate } from "@/lib/utils";

interface Recap {
  id: number;
  recap_type: string;
  status: string;
  title: string | null;
  content: string | null;
  summary: string | null;
  period_start: string;
  period_end: string;
  document_count: number;
  generated_at: string | null;
}

const recapTypeLabels: Record<string, string> = {
  daily: "Daily Recap",
  weekly: "Weekly Recap",
  monthly: "Monthly Recap",
};

const recapTypeColors: Record<string, string> = {
  daily: "bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200",
  weekly: "bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200",
  monthly:
    "bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200",
};

export default function RecapsPage() {
  const { data: recaps, isLoading } = useQuery({
    queryKey: ["recaps"],
    queryFn: () => recapsApi.list(),
  });

  if (isLoading) {
    return (
      <div className="flex h-full items-center justify-center">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
      </div>
    );
  }

  return (
    <div className="h-full overflow-y-auto">
      <div className="border-b px-6 py-4">
        <h1 className="text-xl font-semibold">Recaps</h1>
        <p className="text-sm text-muted-foreground">
          Automated summaries of your knowledge base updates
        </p>
      </div>

      <div className="p-6">
        {!recaps || recaps.length === 0 ? (
          <div className="text-center py-12">
            <Calendar className="mx-auto h-12 w-12 text-muted-foreground" />
            <h3 className="mt-4 text-lg font-medium">No recaps yet</h3>
            <p className="mt-2 text-sm text-muted-foreground">
              Recaps will be generated automatically as content is added
            </p>
          </div>
        ) : (
          <div className="space-y-6">
            {recaps.map((recap: Recap) => (
              <Card key={recap.id}>
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <span
                        className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                          recapTypeColors[recap.recap_type]
                        }`}
                      >
                        {recapTypeLabels[recap.recap_type]}
                      </span>
                      <CardTitle className="text-lg">
                        {recap.title || "Untitled Recap"}
                      </CardTitle>
                    </div>
                    <div className="flex items-center gap-4 text-sm text-muted-foreground">
                      <div className="flex items-center gap-1">
                        <FileText className="h-4 w-4" />
                        {recap.document_count} documents
                      </div>
                      <div className="flex items-center gap-1">
                        <Clock className="h-4 w-4" />
                        {formatDate(recap.period_start)} -{" "}
                        {formatDate(recap.period_end)}
                      </div>
                    </div>
                  </div>
                  {recap.summary && (
                    <CardDescription className="mt-2">
                      {recap.summary}
                    </CardDescription>
                  )}
                </CardHeader>
                {recap.content && (
                  <CardContent>
                    <div className="prose prose-sm dark:prose-invert max-w-none">
                      <ReactMarkdown remarkPlugins={[remarkGfm]}>
                        {recap.content}
                      </ReactMarkdown>
                    </div>
                  </CardContent>
                )}
              </Card>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

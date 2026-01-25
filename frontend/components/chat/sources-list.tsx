"use client";

import { useState } from "react";
import { ChevronDown, ChevronRight, ExternalLink } from "lucide-react";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { cn } from "@/lib/utils";

interface Source {
  source_index: number;
  document_id: number;
  source_id: number;
  title: string | null;
  url: string | null;
  content_preview: string;
  score: number;
  cited: boolean;
}

interface SourcesListProps {
  sources: Source[];
}

interface GroupedSource {
  document_id: number;
  title: string | null;
  url: string | null;
  chunks: Source[];
  hasCited: boolean;
}

function groupSourcesByDocument(sources: Source[]): GroupedSource[] {
  const groups = new Map<number, GroupedSource>();

  for (const source of sources) {
    const existing = groups.get(source.document_id);
    if (existing) {
      existing.chunks.push(source);
      if (source.cited) existing.hasCited = true;
    } else {
      groups.set(source.document_id, {
        document_id: source.document_id,
        title: source.title,
        url: source.url,
        chunks: [source],
        hasCited: source.cited,
      });
    }
  }

  return Array.from(groups.values());
}

function SourceBadge({ group }: { group: GroupedSource }) {
  const indices = group.chunks.map((c) => c.source_index);
  const indicesStr = indices.length === 1
    ? `[${indices[0]}]`
    : `[${indices.join(", ")}]`;

  return (
    <Popover>
      <PopoverTrigger asChild>
        <button
          className={cn(
            "inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium transition-colors",
            group.hasCited
              ? "bg-primary/10 text-primary hover:bg-primary/20"
              : "bg-muted text-muted-foreground hover:bg-muted/80"
          )}
        >
          <span className="font-semibold">{indicesStr}</span>
          <span className="max-w-[120px] truncate">
            {group.title || "Untitled"}
          </span>
        </button>
      </PopoverTrigger>
      <PopoverContent className="w-80" align="start">
        <div className="space-y-2">
          <div className="flex items-start justify-between gap-2">
            <div className="min-w-0 flex-1">
              <p className="text-sm font-medium">
                {indicesStr} {group.title || "Untitled"}
              </p>
            </div>
            {group.url && (
              <a
                href={group.url}
                target="_blank"
                rel="noopener noreferrer"
                className="shrink-0 text-primary hover:text-primary/80"
              >
                <ExternalLink className="h-4 w-4" />
              </a>
            )}
          </div>
          {group.chunks.map((chunk) => (
            <div key={chunk.source_index} className="border-t pt-2">
              <p className="text-xs font-medium text-muted-foreground mb-1">
                Chunk [{chunk.source_index}]
                {chunk.cited && <span className="text-primary ml-1">(cited)</span>}
              </p>
              <p className="text-xs text-muted-foreground leading-relaxed">
                {chunk.content_preview}
              </p>
            </div>
          ))}
        </div>
      </PopoverContent>
    </Popover>
  );
}

export function SourcesList({ sources }: SourcesListProps) {
  const [isOpen, setIsOpen] = useState(false);

  if (!sources || sources.length === 0) {
    return null;
  }

  // Group sources by document
  const groupedSources = groupSourcesByDocument(sources);
  const citedGroups = groupedSources.filter((g) => g.hasCited);
  const uncitedGroups = groupedSources.filter((g) => !g.hasCited);

  // If no sources are cited, show all in a collapsible section
  const hasAnyCited = citedGroups.length > 0;

  if (!hasAnyCited) {
    return (
      <div className="mt-3 border-t pt-2">
        <Collapsible open={isOpen} onOpenChange={setIsOpen}>
          <CollapsibleTrigger className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors">
            {isOpen ? (
              <ChevronDown className="h-3 w-3" />
            ) : (
              <ChevronRight className="h-3 w-3" />
            )}
            <span>{groupedSources.length} source{groupedSources.length !== 1 ? "s" : ""} retrieved</span>
          </CollapsibleTrigger>
          <CollapsibleContent>
            <div className="mt-2 flex flex-wrap gap-1.5">
              {groupedSources.map((group) => (
                <SourceBadge key={group.document_id} group={group} />
              ))}
            </div>
          </CollapsibleContent>
        </Collapsible>
      </div>
    );
  }

  return (
    <div className="mt-3 border-t pt-2">
      {/* Cited sources - always visible */}
      <div className="mb-2">
        <p className="mb-1.5 text-xs font-medium text-muted-foreground">
          Sources
        </p>
        <div className="flex flex-wrap gap-1.5">
          {citedGroups.map((group) => (
            <SourceBadge key={group.document_id} group={group} />
          ))}
        </div>
      </div>

      {/* Uncited sources - collapsible */}
      {uncitedGroups.length > 0 && (
        <Collapsible open={isOpen} onOpenChange={setIsOpen}>
          <CollapsibleTrigger className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors">
            {isOpen ? (
              <ChevronDown className="h-3 w-3" />
            ) : (
              <ChevronRight className="h-3 w-3" />
            )}
            <span>{uncitedGroups.length} other source{uncitedGroups.length !== 1 ? "s" : ""} retrieved</span>
          </CollapsibleTrigger>
          <CollapsibleContent>
            <div className="mt-1.5 flex flex-wrap gap-1.5">
              {uncitedGroups.map((group) => (
                <SourceBadge key={group.document_id} group={group} />
              ))}
            </div>
          </CollapsibleContent>
        </Collapsible>
      )}
    </div>
  );
}

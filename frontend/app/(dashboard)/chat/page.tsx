"use client";

import { useState, useRef, useEffect, useCallback } from "react";
import { Send, Loader2 } from "lucide-react";
import ReactMarkdown from "react-markdown";
import remarkGfm from "remark-gfm";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { chatApi, conversationsApi } from "@/lib/api";
import { cn } from "@/lib/utils";
import { ConversationSidebar } from "@/components/chat/conversation-sidebar";
import { SourcesList } from "@/components/chat/sources-list";

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

interface Message {
  role: "user" | "assistant";
  content: string;
  sources?: Source[];
}

export default function ChatPage() {
  const queryClient = useQueryClient();
  const [messages, setMessages] = useState<Message[]>([]);
  const [input, setInput] = useState("");
  const [isLoading, setIsLoading] = useState(false);
  const [activeConversationId, setActiveConversationId] = useState<number | null>(null);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  // Fetch conversations list
  const { data: conversations = [], isLoading: isLoadingConversations } = useQuery({
    queryKey: ["conversations"],
    queryFn: conversationsApi.list,
  });

  // Create conversation mutation
  const createConversationMutation = useMutation({
    mutationFn: conversationsApi.create,
    onSuccess: (newConversation) => {
      queryClient.invalidateQueries({ queryKey: ["conversations"] });
      setActiveConversationId(newConversation.id);
      setMessages([]);
    },
  });

  // Delete conversation mutation
  const deleteConversationMutation = useMutation({
    mutationFn: conversationsApi.delete,
    onSuccess: (_, deletedId) => {
      queryClient.invalidateQueries({ queryKey: ["conversations"] });
      if (activeConversationId === deletedId) {
        setActiveConversationId(null);
        setMessages([]);
      }
    },
  });

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  // Load conversation messages when selecting a conversation
  const loadConversation = useCallback(async (conversationId: number) => {
    try {
      const conversation = await conversationsApi.get(conversationId);
      const loadedMessages: Message[] = conversation.messages.map((msg) => ({
        role: msg.role,
        content: msg.content,
        // Handle backward compatibility for old messages without source_index/cited
        sources: msg.sources?.map((s: any, idx: number) => ({
          ...s,
          source_index: s.source_index ?? idx + 1,
          cited: s.cited ?? true, // Assume cited for old data
        })) || undefined,
      }));
      setMessages(loadedMessages);
      setActiveConversationId(conversationId);
    } catch (error) {
      console.error("Failed to load conversation:", error);
    }
  }, []);

  const handleSelectConversation = (conversationId: number) => {
    if (conversationId !== activeConversationId) {
      loadConversation(conversationId);
    }
  };

  const handleNewChat = async () => {
    // Create a new conversation
    createConversationMutation.mutate({});
  };

  const handleDeleteConversation = async (conversationId: number) => {
    deleteConversationMutation.mutate(conversationId);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!input.trim() || isLoading) return;

    const userMessage: Message = { role: "user", content: input };
    setMessages((prev) => [...prev, userMessage]);
    setInput("");
    setIsLoading(true);

    try {
      const response = await chatApi.send(input, {
        conversationId: activeConversationId || undefined,
      });

      const assistantMessage: Message = {
        role: "assistant",
        content: response.answer,
        sources: response.sources,
      };
      setMessages((prev) => [...prev, assistantMessage]);

      // Update active conversation ID if a new one was created
      if (response.conversation_id && !activeConversationId) {
        setActiveConversationId(response.conversation_id);
      }

      // Refresh conversations to get updated title/list
      queryClient.invalidateQueries({ queryKey: ["conversations"] });
    } catch (error) {
      const errorMessage: Message = {
        role: "assistant",
        content: "Sorry, an error occurred. Please try again.",
      };
      setMessages((prev) => [...prev, errorMessage]);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="flex h-full">
      <ConversationSidebar
        conversations={conversations}
        activeConversationId={activeConversationId}
        isLoading={isLoadingConversations}
        onSelectConversation={handleSelectConversation}
        onNewChat={handleNewChat}
        onDeleteConversation={handleDeleteConversation}
      />

      <div className="flex flex-1 flex-col">
        <div className="border-b px-6 py-4">
          <h1 className="text-xl font-semibold">Chat</h1>
          <p className="text-sm text-muted-foreground">
            Ask questions about your knowledge base
          </p>
        </div>

        <div className="flex-1 overflow-y-auto p-6">
          {messages.length === 0 ? (
            <div className="flex h-full items-center justify-center">
              <div className="text-center">
                <h2 className="text-lg font-medium">Welcome to RAG Chat</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                  Start by asking a question about your indexed content
                </p>
              </div>
            </div>
          ) : (
            <div className="space-y-6">
              {messages.map((message, index) => (
                <div
                  key={index}
                  className={cn(
                    "flex gap-4",
                    message.role === "user" ? "justify-end" : "justify-start"
                  )}
                >
                  <div
                    className={cn(
                      "max-w-[80%] rounded-lg px-4 py-3",
                      message.role === "user"
                        ? "bg-primary text-primary-foreground"
                        : "bg-muted"
                    )}
                  >
                    <div className="prose prose-sm dark:prose-invert max-w-none">
                      <ReactMarkdown remarkPlugins={[remarkGfm]}>
                        {message.content}
                      </ReactMarkdown>
                    </div>

                    {message.sources && message.sources.length > 0 && (
                      <SourcesList sources={message.sources} />
                    )}
                  </div>
                </div>
              ))}
              {isLoading && (
                <div className="flex justify-start">
                  <div className="rounded-lg bg-muted px-4 py-3">
                    <Loader2 className="h-5 w-5 animate-spin" />
                  </div>
                </div>
              )}
              <div ref={messagesEndRef} />
            </div>
          )}
        </div>

        <div className="border-t p-4">
          <form onSubmit={handleSubmit} className="flex gap-4">
            <Input
              value={input}
              onChange={(e) => setInput(e.target.value)}
              placeholder="Ask a question..."
              disabled={isLoading}
              className="flex-1"
            />
            <Button type="submit" disabled={isLoading || !input.trim()}>
              {isLoading ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                <Send className="h-4 w-4" />
              )}
            </Button>
          </form>
        </div>
      </div>
    </div>
  );
}

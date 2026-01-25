FROM node:20-alpine

WORKDIR /app

# Create non-root user for security (use node user that already exists in base image)
RUN adduser -D -u 1001 appuser

# Install dependencies first (for caching)
COPY package.json package-lock.json* ./
RUN npm install

# Copy source files with correct ownership
COPY --chown=appuser:appuser . .

# Set environment for development
ENV NODE_ENV=development
ENV NEXT_TELEMETRY_DISABLED=1
ENV WATCHPACK_POLLING=true

# Switch to non-root user
USER appuser

# Expose port
EXPOSE 3000

# Development command with host binding
CMD ["npm", "run", "dev", "--", "-H", "0.0.0.0"]
